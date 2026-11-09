import {useEffect,useRef,useState} from 'react'
import {ApiError,request,type Session} from './api'
import {parsePacket,publicationInput,InstrumentReviewError,type Packet} from './instrument'

type Published = {version:string;approved_by:string|null;approved_at:string|null;effective_at:string|null;expires_at:string|null;approval_reference:string|null;definition:unknown}
type Index = {current_version:string|null;data:Published[];meta:{current_page:number;last_page:number}}
const stamp=(v:string|null)=>v?`${new Date(v).toLocaleString('id-ID',{timeZone:'Asia/Jakarta'})} WIB`:'Belum tercatat'
export function InstrumentReview({session,onExpired,onClose,onLogout}:{session:Session;onExpired():void;onClose():void;onLogout():Promise<void>}) {
  const [index,setIndex]=useState<Index|null>(null);const [page,setPage]=useState(1);const [retry,setRetry]=useState(0)
  const [error,setError]=useState('');const [denied,setDenied]=useState(false);const [busy,setBusy]=useState(false)
  const [raw,setRaw]=useState('');const [reference,setReference]=useState('');const [effective,setEffective]=useState('');const [expires,setExpires]=useState('')
  const [review,setReview]=useState<{packet:Packet;expected:string|null}|null>(null);const [confirmed,setConfirmed]=useState(false)
  const [uncertain,setUncertain]=useState(false);const [receipt,setReceipt]=useState<Published|null>(null);const [notice,setNotice]=useState('')
  const live=useRef(true);const pending=useRef(false);const dialog=useRef<HTMLElement>(null)
  useEffect(()=>{live.current=true;return()=>{live.current=false}},[])
  useEffect(()=>{
    const previous=document.activeElement;const node=dialog.current;node?.focus()
    function trap(event:KeyboardEvent){
      if(event.key!=='Tab')return
      const controls=node?.querySelectorAll<HTMLElement>('button:not(:disabled),input:not(:disabled),textarea:not(:disabled),summary')
      if(!controls?.length)return
      const first=controls[0],last=controls[controls.length-1]
      if(event.shiftKey&&(document.activeElement===first||document.activeElement===node)){last.focus();event.preventDefault()}
      else if(!event.shiftKey&&document.activeElement===last){first.focus();event.preventDefault()}
    }
    node?.addEventListener('keydown',trap)
    return()=>{node?.removeEventListener('keydown',trap);if(previous instanceof HTMLElement&&document.contains(previous))previous.focus()}
  },[])
  function failure(e:unknown) {
    if(e instanceof ApiError && e.status===401){onExpired();return}
    if(e instanceof ApiError && e.status===403){setDenied(true);setIndex(null);setReview(null);setError('Akun tidak berwenang meninjau atau mempublikasikan instrumen.');return}
    if(e instanceof InstrumentReviewError){setError(e.message);return}
    setError(e instanceof ApiError ? e.status===409?'Versi aktif, waktu berlaku atau versi instrumen berubah/berkonflik. Periksa daftar server dan tinjau ulang.':e.status===422?'Kontrak ditolak server. Periksa definisi, jadwal, referensi dan waktu; tidak ada publikasi parsial.':e.status===429?'Permintaan dibatasi. Tunggu sebelum memeriksa kembali.':'Layanan belum dapat menyelesaikan permintaan.' : 'Tidak dapat terhubung. Periksa koneksi.')
  }
  useEffect(()=>{
    const controller=new AbortController();setIndex(null);setError('')
    void request<Index>(`/research/daily-instruments?page=${page}`,session.token,'GET',undefined,controller.signal)
      .then(data=>{if(!controller.signal.aborted){setIndex(data);setDenied(false)}})
      .catch(e=>{if(!controller.signal.aborted)failure(e)})
    return()=>controller.abort()
    // Only load on explicit navigation/reload; POST is never an effect.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  },[page,retry,session.token])
  function changed(){setReview(null);setConfirmed(false);setError('')}
  function prepare(){
    if(!index||uncertain||pending.current)return
    try{const packet=parsePacket(raw);publicationInput(packet,reference,effective,expires,index.current_version);setReview({packet,expected:index.current_version});setConfirmed(false);setError('')}
    catch(e){setReview(null);setError((e as Error).message)}
  }
  async function publish(){
    if(!review||!confirmed||!index||pending.current||uncertain||denied)return
    pending.current=true;setBusy(true);setError('');setNotice('');let sent=false
    const controller=new AbortController();const timeout=window.setTimeout(()=>controller.abort(),30000)
    try{
      const fresh=await request<Index>('/research/daily-instruments?page=1',session.token,'GET',undefined,controller.signal)
      if(!live.current)return
      if(fresh.current_version!==review.expected){setIndex(fresh);setReview(null);setConfirmed(false);setError('Versi aktif berubah. Tinjau ulang sebelum publikasi; belum ada POST.');return}
      const body=publicationInput(review.packet,reference,effective,expires,review.expected)
      sent=true
      const result=await request<{data:Published}>('/research/daily-instruments/publish',session.token,'POST',body,controller.signal)
      if(!live.current)return
      const r=result.data
      if(!r||r.version!==body.version||r.approval_reference!==body.approval_reference||typeof r.approved_by!=='string'||!Number.isFinite(Date.parse(r.approved_at??''))||Date.parse(r.effective_at??'')!==Date.parse(body.effective_at)||Date.parse(r.expires_at??'')!==Date.parse(body.expires_at))throw new Error('Unverified receipt')
      setReceipt(r);setReview(null);setConfirmed(false);setNotice('Publikasi tersimpan di server. Aktivasi Android tetap bergantung waktu berlaku, kedaluwarsa, persetujuan dan kelayakan peserta.');setRaw('');setRetry(n=>n+1)
    }catch(e){
      if(!live.current)return
      setConfirmed(false);setReview(null)
      // Lost HTTP response/5xx/abort is not server cancellation. Never blindly repost.
      if(sent && (!(e instanceof ApiError)||e.status>=500)){setUncertain(true);setNotice('Hasil publikasi belum dapat dipastikan. Permintaan mungkin sudah tersimpan. Jangan kirim ulang; periksa versi pada daftar server, aktor, waktu dan referensi. Tutup layar atau timeout bukan pembatalan server.')}
      failure(e)
    }finally{window.clearTimeout(timeout);pending.current=false;if(live.current)setBusy(false)}
  }
  return <div className="modal-backdrop"><section ref={dialog} tabIndex={-1} onKeyDown={e=>{if(e.key==='Escape'&&!pending.current)onClose()}} className="enrollment-modal instrument-modal" role="dialog" aria-modal="true" aria-labelledby="instrument-title">
    <header className="modal-head"><div><span className="eyebrow">PENELITI UTAMA · REVIEW</span><h2 id="instrument-title">Instrumen asesmen harian</h2></div>
      <div><button type="button" className="secondary" disabled={busy} onClick={onClose}>Tutup review</button><button type="button" className="secondary" onClick={()=>{void onLogout()}}>Keluar</button></div>
    </header>
    <div className="instrument-body">
      {error&&<p role="alert">{error}</p>}{notice&&<p role="status">{notice}</p>}
      {!index&&!denied&&<><p role="status">Memeriksa kewenangan dan versi server…</p><button type="button" className="secondary" onClick={()=>setRetry(n=>n+1)}>Periksa daftar server</button></>}
      {index&&!denied&&<>
        <p>Versi aktif server: <strong>{index.current_version??'Belum ada instrumen aktif'}</strong>. Daftar termasuk versi masa depan/kedaluwarsa; tercatat bukan berarti aktif.</p>
        <section className="form-section"><h3>Dasar dokumen dan keputusan yang masih diperlukan</h3>
          <p role="status">{review?'Siap ditinjau untuk publikasi eksplisit':receipt?'Versi tercatat; lihat tanda terima server':'Menunggu kelengkapan/persetujuan'}</p>
          <p>Penggunaan panduan sebagai dasar telah diizinkan dalam tugas PR-15. Ini belum memberikan daftar final wajib/opsional, pemetaan nilai, waktu berlaku atau referensi persetujuan versi ini.</p>
          <p>Panduan visual halaman 6: delapan kandidat gejala; nyeri dada masih usulan dan pilihan pada gambar berbeda dari catatan. Item kesadaran menyebut keluarga; kontrak saat ini hanya mencatat pengisi untuk seluruh formulir.</p>
          <p>Proposal asli belum tersedia. Health-ITUES, delta VAS, klasifikasi, pengingat dan notifikasi tidak termasuk publikasi ini. Gunakan matriks docs/research/instrument-traceability-pr15.md untuk review peneliti.</p>
        </section>
        <fieldset disabled={busy||uncertain}><legend>Paket definisi yang sudah ditinjau</legend>
          <p>Tidak ada katalog atau referensi persetujuan bawaan. JSON berisi definition dan evidence, sesuai format dokumentasi. Sumber wajib mencakup instrumen, VAS, jadwal, validasi, setiap pertanyaan, status wajib dan setiap pilihan.</p>
          <label>Paket review JSON<textarea rows={9} maxLength={120000} value={raw} onChange={e=>{setRaw(e.target.value);changed()}} spellCheck={false}/></label>
          <label>Referensi persetujuan versi ini<input value={reference} maxLength={80} onChange={e=>{setReference(e.target.value);changed()}} autoComplete="off"/></label>
          <label>Waktu berlaku (WIB)<input type="datetime-local" value={effective} onChange={e=>{setEffective(e.target.value);changed()}}/></label>
          <label>Kedaluwarsa (WIB)<input type="datetime-local" value={expires} onChange={e=>{setExpires(e.target.value);changed()}}/></label>
          <button type="button" className="secondary" onClick={prepare}>Tinjau kesiapan publikasi</button>
        </fieldset>
        {review&&<section className="form-section"><h3>Ringkasan sebelum publikasi</h3>
          <p>Versi {review.packet.definition.version}; menggantikan versi aktif {review.expected??'tidak ada'}.</p>
          <p>VAS terpisah: bilangan bulat 0–100 mm, tanpa pilihan awal. Sumber: {review.packet.evidence.vas}</p>
          <p>Jadwal: hari {review.packet.definition.schedule.first_day}–{review.packet.definition.schedule.last_day}, WIB; hanya hari yang sama. Sumber: {review.packet.evidence.schedule}</p>
          <p>Instrumen: {review.packet.evidence.instrument}. Validasi: {review.packet.evidence.validation}</p>
          <p>Berlaku {effective} WIB; kedaluwarsa {expires} WIB; referensi {reference}.</p>
          {review.packet.definition.questions.map(q=><article key={q.code}><h4>{q.label} · {q.code} · {q.required?'Wajib':'Opsional'}</h4>
            <p>Sumber: {review.packet.evidence.questions[q.code].definition}; wajib/opsional: {review.packet.evidence.questions[q.code].required}</p>
            <ul>{q.options.map(o=><li key={o.value}>{o.label} ({o.value}) — {review.packet.evidence.questions[q.code].options[o.value]}</li>)}</ul></article>)}
          <label><input type="checkbox" checked={confirmed} disabled={busy} onChange={e=>setConfirmed(e.target.checked)}/>Saya telah memeriksa sumber, pilihan, jadwal, VAS dan bukti persetujuan versi ini; saya mengonfirmasi publikasi eksplisit.</label>
          <p>Sitasi dan centang bukan verifikasi tanda tangan digital. Arsipkan matriks/paket lengkap pada catatan persetujuan yang dirujuk; server menyimpan referensi, aktor dan waktu, bukan evidence lokal ini.</p>
          <button type="button" className="primary" disabled={!confirmed||busy||uncertain} onClick={()=>{void publish()}}>{busy?'Menunggu tanda terima…':'Publikasikan versi ini'}</button>
        </section>}
        {receipt&&<section><h3>Tanda terima publikasi server</h3><p>Versi {receipt.version}; peneliti {receipt.approved_by}; disetujui {stamp(receipt.approved_at)}; berlaku {stamp(receipt.effective_at)}; kedaluwarsa {stamp(receipt.expires_at)}; referensi {receipt.approval_reference}.</p></section>}
        <section className="form-section"><h3>Versi tersimpan · hanya baca</h3>
          <button type="button" className="secondary" disabled={busy} onClick={()=>setRetry(n=>n+1)}>Periksa daftar server</button>
          {index.data.length===0?<p>Belum ada versi tersimpan. Tidak ada instrumen yang dibuat otomatis.</p>:index.data.map(entry=><details key={entry.version}><summary>{entry.version} · referensi {entry.approval_reference??'belum tercatat'}</summary>
            <p>Peneliti: {entry.approved_by??'belum tercatat'}; persetujuan: {stamp(entry.approved_at)}; berlaku: {stamp(entry.effective_at)}; kedaluwarsa: {stamp(entry.expires_at)}.</p><pre>{JSON.stringify(entry.definition,null,2)}</pre></details>)}
          <button type="button" className="secondary" disabled={busy||page<=1} onClick={()=>setPage(n=>n-1)}>Versi sebelumnya</button><button type="button" className="secondary" disabled={busy||page>=index.meta.last_page} onClick={()=>setPage(n=>n+1)}>Versi berikutnya</button>
          <p>Definisi tersimpan immutable; revisi memerlukan versi baru. Jika respons hilang, periksa versi pada seluruh halaman. Versi yang ditemukan tidak membuktikan bahwa permintaan terakhir Anda yang membuatnya.</p>
        </section>
      </>}
    </div>
  </section></div>
}
