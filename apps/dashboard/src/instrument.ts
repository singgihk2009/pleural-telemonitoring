// Review metadata stays in memory; only the existing publication contract is sent.
export class InstrumentReviewError extends Error {}
export type Question = { code: string; label: string; required: boolean; options: {value:string;label:string}[] }
export type Packet = {
  definition: { version:string; schedule:{timezone:'Asia/Jakarta';first_day:number;last_day:number;late_policy:'same_day_only'};questions:Question[] }
  evidence: { instrument:string; vas:string; schedule:string; validation:string; questions:Record<string,{definition:string;required:string;options:Record<string,string>}> }
}
const object = (v:unknown): v is Record<string,unknown> => !!v && typeof v==='object' && !Array.isArray(v)
function keys(v:unknown, expected:string[]): asserts v is Record<string,unknown> {
  if (!object(v) || Object.keys(v).length!==expected.length || !expected.every(k=>Object.hasOwn(v,k))) throw new InstrumentReviewError('Struktur paket tidak sesuai; jangan sertakan skor, aturan klinis, atau field tambahan.')
}
function text(v:unknown,max:number): v is string {return typeof v==='string' && v.trim().length>0 && v.length<=max}
export function parsePacket(raw:string): Packet {
  if (!raw || raw.length>120000) throw new InstrumentReviewError('Menunggu kelengkapan/persetujuan: masukkan paket definisi dan sumber, maksimum 120.000 karakter.')
  let p:unknown
  try {p=JSON.parse(raw)} catch {throw new InstrumentReviewError('JSON paket belum valid.')}
  keys(p,['definition','evidence']);const d=p.definition;keys(d,['version','schedule','questions'])
  if (typeof d.version!=='string'|| !/^[A-Za-z0-9_-]{1,40}$/.test(d.version)) throw new InstrumentReviewError('Versi wajib menggunakan 1–40 huruf, angka, garis bawah atau tanda hubung.')
  const s=d.schedule;keys(s,['timezone','first_day','last_day','late_policy'])
  if(s.timezone!=='Asia/Jakarta'||s.late_policy!=='same_day_only'||!Number.isInteger(s.first_day)||!Number.isInteger(s.last_day)||Number(s.first_day)<1||Number(s.last_day)>30||Number(s.first_day)>Number(s.last_day)) throw new InstrumentReviewError('Jadwal harus eksplisit: WIB, hari 1–30, urutan valid, same_day_only. Kebijakan lain belum didukung.')
  const e=p.evidence;keys(e,['instrument','vas','schedule','validation','questions'])
  if(!['instrument','vas','schedule','validation'].every(k=>text(e[k],1000))||!object(e.questions)) throw new InstrumentReviewError('Lengkapi sumber instrumen, VAS, jadwal dan validasi; sumber bukan nomor persetujuan yang dibuat otomatis.')
  if(!Array.isArray(d.questions)||d.questions.length<1||d.questions.length>50)throw new InstrumentReviewError('Diperlukan 1–50 pertanyaan yang sudah ditinjau; tidak ada pertanyaan bawaan.')
  const codes=new Set<string>()
  for(const q of d.questions){
    keys(q,['code','label','required','options'])
    if(typeof q.code!=='string'||!/^[A-Z0-9_]{1,48}$/.test(q.code)||codes.has(q.code)||!text(q.label,300)||typeof q.required!=='boolean')throw new InstrumentReviewError('Periksa kode unik, label pertanyaan dan penetapan wajib/opsional.')
    codes.add(q.code)
    if(!Array.isArray(q.options)||q.options.length<1||q.options.length>20)throw new InstrumentReviewError('Setiap pertanyaan memerlukan 1–20 pilihan yang disetujui.')
    const ref=e.questions[q.code];keys(ref,['definition','required','options'])
    if(!text(ref.definition,1000)||!text(ref.required,1000)||!object(ref.options))throw new InstrumentReviewError('Sumber pertanyaan dan penetapan wajib/opsional harus eksplisit.')
    const values=new Set<string>()
    for(const o of q.options){
      keys(o,['value','label'])
      if(typeof o.value!=='string'||!/^[A-Z0-9_-]{1,32}$/.test(o.value)||values.has(o.value)||!text(o.label,150)||!Object.hasOwn(ref.options,o.value)||!text(ref.options[o.value],1000))throw new InstrumentReviewError('Periksa pilihan unik, label dan sumber setiap nilai jawaban.')
      values.add(o.value)
    }
    if(Object.keys(ref.options).length!==values.size)throw new InstrumentReviewError('Sumber pilihan harus cocok tepat dengan definisi.')
  }
  if(Object.keys(e.questions).length!==codes.size)throw new InstrumentReviewError('Sumber pertanyaan harus cocok tepat dengan definisi.')
  const packet=p as unknown as Packet
  checkAndroidBudget(packet)
  return packet
}
export function wibToUTC(value:string): string {
  if(!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(value))throw new InstrumentReviewError('Isi waktu kalender WIB sampai menit.')
  const stamp=Date.parse(`${value}:00+07:00`)
  if(!Number.isFinite(stamp)||new Date(stamp+7*3600000).toISOString().slice(0,16)!==value)throw new InstrumentReviewError('Waktu WIB tidak valid.')
  return new Date(stamp).toISOString()
}
export function publicationInput(p:Packet,reference:string,effective:string,expires:string,expected:string|null,now=Date.now()) {
  if(!/^[A-Za-z0-9_-]{1,80}$/.test(reference))throw new InstrumentReviewError('Isi referensi persetujuan nyata (1–80 huruf/angka/_/-); jangan membuat referensi pengganti.')
  const start=wibToUTC(effective);const end=wibToUTC(expires)
  if(Date.parse(start)<=now||Date.parse(end)<=Date.parse(start))throw new InstrumentReviewError('Waktu berlaku harus di masa depan dan kedaluwarsa setelah waktu berlaku; server memeriksa kembali.')
  return {...p.definition,expected_current_version:expected,approval_reference:reference,effective_at:start,expires_at:end,confirm_publication:true as const}
}

// Engineering compatibility bound from mobile offline/core.ts save(), not a
// clinical rule. Reserve space for a complete body AND receipt, all choices,
// maximum-width generated IDs and extra operational metadata. Never truncate.
function checkAndroidBudget(packet:Packet) {
  const id='ffffffff-ffff-4fff-8fff-ffffffffffff',date='2099-12-31',timestamp='2099-12-31T23:59:59.999999Z'
  const d=packet.definition
  const answers=d.questions.map(q=>({code:q.code,option_id:4503599627370495}))
  const body={submission_id:id,version:d.version,date_wib:date,vas_mm:100,filled_by:'KELUARGA',device_created_at:timestamp,answers}
  const snapshot={available:true,can_submit:true,submission_id:id,date_wib:date,study_day:30,submitted:null,questionnaire:{version:d.version,questions:d.questions.map(q=>({...q,options:q.options.map(o=>({id:4503599627370495,label:o.label}))}))}}
  const receipt={id,date_wib:date,study_day:30,vas_mm:100,type:'HARIAN',filled_by:'KELUARGA',received_at:timestamp,device_created_at:timestamp,version:d.version,answers:d.questions.map(q=>({code:q.code,option_id:4503599627370495,value:q.options.reduce((a,o)=>o.value.length>a.length?o.value:a,'')}))}
  if((JSON.stringify({schema:1,state:'SYNCED',snapshot,body,createdAt:9999999999999,attempts:999999,nextAttemptAt:9999999999999,uncertain:true,reason:'VERSION_DATE_OR_STATUS_CHANGED',receipt}).length+256)*3>16384)
    throw new InstrumentReviewError('Ukuran definisi melampaui anggaran antrean Android. Tinjau kompatibilitas; jangan memotong pertanyaan atau pilihan tanpa persetujuan peneliti.')
}
