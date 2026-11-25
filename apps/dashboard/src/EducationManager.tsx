import {useEffect, useState, type FormEvent} from 'react'
import {ApiError, request, type Session} from './api'

const types = ['ARTICLE', 'JOURNAL', 'DOI', 'PDF', 'YOUTUBE'] as const
type Reference = {type: typeof types[number]; label: string; url: string|null; canonical_url: string|null}
type Draft = {code: string; title: string; description: string; display_order: number; references: Reference[]}
type Revision = {version: number; status: string; actor_id: number|null; recorded_at: string; approval_reference: string|null}
type Topic = Draft & Revision & {published_version: number|null; history: Revision[]}
type Page<T> = {data: T; meta: {current_page: number; last_page: number}}
const formatDate = (value: string) => new Intl.DateTimeFormat('id-ID', {
  timeZone:'Asia/Jakarta', dateStyle:'medium', timeStyle:'short',
}).format(new Date(value.replace(' ', 'T').slice(0,23) + 'Z'))
const empty: Draft = {code:'', title:'', description:'', display_order:1, references:[]}

export function EducationManager({session, onExpired}: {session: Session; onExpired(): void}) {
  const [list, setList] = useState<Page<Topic[]>|null>(null)
  const [page, setPage] = useState(1)
  const [historyPage, setHistoryPage] = useState(1)
  const [selected, setSelected] = useState<string|null>(null)
  const [historical, setHistorical] = useState<number|null>(null)
  const [topic, setTopic] = useState<Page<Topic>|null>(null)
  const [draft, setDraft] = useState<Draft>(empty)
  const [dirty, setDirty] = useState(false)
  const [preview, setPreview] = useState(false)
  const [reload, setReload] = useState(0)
  const [approval, setApproval] = useState('')
  const [confirmation, setConfirmation] = useState<'publish'|'archive'|null>(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [status, setStatus] = useState('')
  const path = '/research/education'

  function report(failure: unknown) {
    if (failure instanceof ApiError) {
      if (failure.status === 401) { onExpired(); return }
      if (failure.status === 403) { setList(null); setTopic(null); setError('Akses pengelolaan materi ditolak.'); return }
      if (failure.status === 409) { setError('Versi telah berubah. Buka kembali topik untuk memuat versi terbaru.'); return }
      if (failure.status === 422) { setError(Object.values(failure.fields).flat().join(' ') || 'Periksa isian materi.'); return }
    }
    setError('Permintaan belum dapat dipastikan. Periksa daftar sebelum mencoba kembali.')
  }

  useEffect(() => {
    const controller = new AbortController()
    void request<Page<Topic[]>>(`${path}?page=${page}`, session.token, 'GET', undefined, controller.signal)
      .then(result => { if (!controller.signal.aborted) setList(result) })
      .catch(failure => { if (!controller.signal.aborted) report(failure) })
    return () => controller.abort()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [session.token, page, reload])

  useEffect(() => {
    if (selected === null) return
    const controller = new AbortController()
    const version = historical === null ? '' : `&version=${historical}`
    void request<Page<Topic>>(`${path}/${encodeURIComponent(selected)}?page=${historyPage}${version}`, session.token, 'GET', undefined, controller.signal)
      .then(result => {
        if (controller.signal.aborted) return
        setTopic(result)
        const {code,title,description,display_order,references} = result.data
        setDraft({code,title,description,display_order,references})
      }).catch(failure => { if (!controller.signal.aborted) report(failure) })
    return () => controller.abort()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [session.token, selected, historical, historyPage, reload])

  function open(code: string|null) {
    setTopic(null); setSelected(code); setDraft(empty); setDirty(false)
    setHistorical(null); setHistoryPage(1); setPreview(false); setConfirmation(null); setApproval('')
    setError(''); setStatus(''); setReload(value => value + 1)
  }

  function edit(patch: Partial<Draft>) {
    setDraft(current => ({...current, ...patch}))
    setDirty(true); setConfirmation(null); setStatus('')
  }

  async function save(event: FormEvent) {
    event.preventDefault()
    if (busy || (selected !== null && (!topic || historical !== null))) return
    const {code, ...definition} = draft
    setBusy(true); setError('')
    try {
      await request(path + (selected === null ? '' : `/${encodeURIComponent(selected)}`), session.token,
        selected === null ? 'POST' : 'PUT', selected === null ? {...definition, code} : {...definition, expected_version: topic?.data.version})
      open(code)
      setStatus('Draf tersimpan.')
    } catch (failure) { report(failure) }
    finally { setBusy(false) }
  }

  async function transition() {
    if (busy || dirty || !topic || !confirmation || historical !== null) return
    setBusy(true); setError('')
    try {
      await request(`${path}/${encodeURIComponent(topic.data.code)}/${confirmation}`, session.token, 'POST', {
        expected_version: topic.data.version, confirm:true, ...(confirmation === 'publish' ? {approval_reference:approval} : {}),
      })
      open(topic.data.code)
      setStatus(confirmation === 'publish' ? 'Materi dipublikasikan.' : 'Materi diarsipkan.')
    } catch (failure) { report(failure) }
    finally { setBusy(false) }
  }

  return <section id="education-manager" className="form-section instrument-body">
    <h2>Materi Penelitian → Edukasi Pasien</h2>
    {error && <p role="alert">{error}</p>}
    {status && <p role="status">{status}</p>}
    {list && <>
      <ul>{list.data.map(item => <li key={item.code}>
        <button type="button" className="secondary" disabled={busy} onClick={() => open(item.code)}>{item.code} · {item.title}</button>
        {item.status} · v{item.version}{item.published_version ? ` · versi publik v${item.published_version}` : ''}
      </li>)}</ul>
      <button type="button" className="secondary" disabled={busy || page<=1} onClick={() => setPage(n=>n-1)}>Halaman sebelumnya</button>
      <button type="button" className="secondary" disabled={busy || page>=list.meta.last_page} onClick={() => setPage(n=>n+1)}>Halaman berikutnya</button>
      <button type="button" className="secondary" disabled={busy} onClick={() => open(null)}>Topik baru</button>
      {(selected === null || topic) && <>
        <form onSubmit={save}>
          <fieldset disabled={busy || historical !== null}>
            <legend>{selected === null ? 'Topik baru' : `${selected} · v${topic?.data.version}`}</legend>
            {selected === null && <label>Kode topik<input required pattern="[A-Z0-9-]+" maxLength={24} value={draft.code} onChange={e=>edit({code:e.target.value})}/></label>}
            <label>Judul<input required maxLength={160} value={draft.title} onChange={e=>edit({title:e.target.value})}/></label>
            <label>Deskripsi<textarea rows={4} maxLength={4000} value={draft.description} onChange={e=>edit({description:e.target.value})}/></label>
            <label>Urutan<input type="number" min={1} max={65535} required value={draft.display_order} onChange={e=>edit({display_order:Number(e.target.value)})}/></label>
            {draft.references.map((reference, index) => {
              const update = (patch: Partial<Reference>) => edit({references:draft.references.map((item,i)=>i===index ? {...item,...patch} : item)})
              return <fieldset key={index}><legend>Referensi {index+1}</legend>
                <label>Jenis<select value={reference.type} onChange={e=>update({type:e.target.value as Reference['type'], ...(e.target.value === 'YOUTUBE' ? {canonical_url:null} : {})})}>
                  {types.map(type=><option key={type}>{type}</option>)}
                </select></label>
                <label>Judul atau sitasi<textarea required maxLength={500} value={reference.label} onChange={e=>update({label:e.target.value})}/></label>
                <label>URL sumber<input type="url" maxLength={2048} value={reference.url ?? ''} onChange={e=>update({url:e.target.value || null})}/></label>
                {reference.type !== 'YOUTUBE' && <label>URL kanonik terverifikasi (opsional)<input type="url" maxLength={2048} value={reference.canonical_url ?? ''} onChange={e=>update({canonical_url:e.target.value || null})}/></label>}
                <button type="button" className="secondary" onClick={()=>edit({references:draft.references.filter((_,i)=>i!==index)})}>Hapus referensi {index+1}</button>
              </fieldset>
            })}
            <button type="button" className="secondary" disabled={draft.references.length>=30} onClick={()=>edit({references:[...draft.references,{type:'ARTICLE',label:'',url:null,canonical_url:null}]})}>Tambah referensi</button>
            <button type="submit" className="primary">Simpan draf</button>
          </fieldset>
        </form>
        <button type="button" className="secondary" aria-expanded={preview} onClick={()=>setPreview(value=>!value)}>Pratinjau pasien</button>
        {preview && <section className="instrument-preview"><h3>{draft.title || 'Topik tanpa judul'}</h3>
          <p>{draft.description}</p><ul>{draft.references.map((reference,index)=><li key={index}>{reference.label} · {reference.type}</li>)}</ul>
        </section>}
      </>}
      {topic && <>
        <button type="button" className="secondary" disabled={busy || dirty || historical!==null || topic.data.status!=='DRAFT'} onClick={()=>setConfirmation('publish')}>Tinjau publikasi</button>
        <button type="button" className="secondary" disabled={busy || dirty || historical!==null || topic.data.status==='ARCHIVED'} onClick={()=>setConfirmation('archive')}>Tinjau arsip</button>
        {confirmation && <fieldset disabled={busy}><legend>{confirmation === 'publish' ? 'Konfirmasi publikasi' : 'Konfirmasi arsip'}</legend>
          <p>{confirmation === 'publish' ? 'Pastikan deskripsi, referensi dan tujuan tautan telah ditinjau peneliti.' : 'Materi akan disembunyikan dari katalog pasien.'}</p>
          {confirmation === 'publish' && <label>Referensi persetujuan<input maxLength={80} value={approval} onChange={e=>setApproval(e.target.value)}/></label>}
          <button type="button" className="primary" disabled={confirmation==='publish' && !approval.trim()} onClick={()=>{void transition()}}>{confirmation==='publish'?'Publikasikan materi':'Arsipkan materi'}</button>
          <button type="button" className="secondary" onClick={()=>setConfirmation(null)}>Batal</button>
        </fieldset>}
        <h3>Riwayat perubahan</h3>
        {historical !== null && <button type="button" className="secondary" onClick={()=>{setTopic(null);setHistorical(null)}}>Kembali ke versi terakhir</button>}
        <ul>{topic.data.history.map(item=><li key={item.version}>
          <button type="button" className="secondary" disabled={busy || dirty || historical===item.version} onClick={()=>{setTopic(null);setHistorical(item.version);setConfirmation(null)}}>v{item.version} · {item.status}</button>
          {formatDate(item.recorded_at)} WIB · {item.actor_id ? `Staf ${item.actor_id}` : 'Impor konsol'}{item.approval_reference ? ` · ${item.approval_reference}` : ''}
        </li>)}</ul>
        <button type="button" className="secondary" disabled={busy || dirty || historyPage<=1} onClick={()=>setHistoryPage(n=>n-1)}>Riwayat sebelumnya</button>
        <button type="button" className="secondary" disabled={busy || dirty || historyPage>=topic.meta.last_page} onClick={()=>setHistoryPage(n=>n+1)}>Riwayat berikutnya</button>
      </>}
    </>}
  </section>
}
