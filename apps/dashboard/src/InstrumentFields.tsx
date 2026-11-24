import type {InstrumentDraft,OptionDraft,Packet,QuestionDraft} from './instrument'

const newOption=(id:number):OptionDraft=>({id,value:"",label:"",source:""})

export function InstrumentFields({draft,onChange,nextId}:{
  draft:InstrumentDraft; onChange(value:InstrumentDraft):void; nextId():number;
}) {
  function update(patch:Partial<InstrumentDraft>) {onChange({...draft,...patch})}
  function edit(question:QuestionDraft, patch:Partial<QuestionDraft>) {
    update({questions:draft.questions.map(q=>q.id===question.id?{...q,...patch}:q)})
  }
  function move(index:number, direction:number) {
    const questions=[...draft.questions]
    const target=index+direction
    if(target<0||target>=questions.length)return
    const [question]=questions.splice(index,1)
    questions.splice(target,0,question)
    update({questions})
  }

  return <>
    <label>Versi instrumen<input value={draft.version} maxLength={40} onChange={e=>update({version:e.target.value})}/></label>
    <fieldset><legend>Jadwal pengisian (WIB)</legend>
      <div className="instrument-grid">
        <label>Hari pertama<input type="number" min={1} max={30} value={draft.firstDay} onChange={e=>update({firstDay:e.target.value})}/></label>
        <label>Hari terakhir<input type="number" min={1} max={30} value={draft.lastDay} onChange={e=>update({lastDay:e.target.value})}/></label>
      </div>
      <label className="instrument-check"><input type="checkbox" checked={draft.scheduleConfirmed} onChange={e=>update({scheduleConfirmed:e.target.checked})}/>Jadwal yang dirujuk mengizinkan pengiriman pada tanggal WIB yang sama.</label>
    </fieldset>
    <fieldset><legend>Referensi dokumen dan keputusan</legend>
      {([
        ['instrument','Sumber instrumen'],['vas','Sumber VAS'],['schedule','Sumber jadwal'],['validation','Sumber validasi'],
      ] as const).map(([key,label])=><label key={key}>{label}<input value={draft[key]} maxLength={1000} onChange={e=>update({[key]:e.target.value})}/></label>)}
    </fieldset>
    <fieldset><legend>Pertanyaan sesuai urutan pasien</legend>
      {draft.questions.map((q,index)=><fieldset key={q.id} className="instrument-question">
        <legend>Pertanyaan {index+1}</legend>
        <div className="instrument-actions">
          <button type="button" className="secondary" aria-label={`Naikkan pertanyaan ${index+1}`} disabled={index===0} onClick={()=>move(index,-1)}>Naikkan</button>
          <button type="button" className="secondary" aria-label={`Turunkan pertanyaan ${index+1}`} disabled={index===draft.questions.length-1} onClick={()=>move(index,1)}>Turunkan</button>
          <button type="button" className="secondary" aria-label={`Hapus pertanyaan ${index+1}`} onClick={()=>update({questions:draft.questions.filter(item=>item.id!==q.id)})}>Hapus pertanyaan</button>
        </div>
        <label>Kode pertanyaan<input value={q.code} maxLength={48} onChange={e=>edit(q,{code:e.target.value})}/></label>
        <label>Teks pertanyaan<textarea rows={2} value={q.label} maxLength={300} onChange={e=>edit(q,{label:e.target.value})}/></label>
        <label>Sumber teks pertanyaan<input value={q.definitionSource} maxLength={1000} onChange={e=>edit(q,{definitionSource:e.target.value})}/></label>
        <label>Kewajiban jawaban<select value={q.required} onChange={e=>edit(q,{required:e.target.value as QuestionDraft['required']})}><option value="">Pilih keputusan peneliti</option><option value="required">Wajib</option><option value="optional">Opsional</option></select></label>
        <label>Sumber keputusan wajib atau opsional<input value={q.requiredSource} maxLength={1000} onChange={e=>edit(q,{requiredSource:e.target.value})}/></label>
        {q.options.map((o,optionIndex)=>{
          const editOption=(patch:Partial<OptionDraft>)=>edit(q,{options:q.options.map(item=>item.id===o.id?{...item,...patch}:item)})
          return <fieldset key={o.id}><legend>Pilihan {optionIndex+1}</legend>
            <label>Teks pilihan<input value={o.label} maxLength={150} onChange={e=>editOption({label:e.target.value})}/></label>
            <label>Kode pilihan<input value={o.value} maxLength={32} onChange={e=>editOption({value:e.target.value})}/></label>
            <label>Sumber pilihan<input value={o.source} maxLength={1000} onChange={e=>editOption({source:e.target.value})}/></label>
            <button type="button" className="secondary" onClick={()=>edit(q,{options:q.options.filter(item=>item.id!==o.id)})}>Hapus pilihan {optionIndex+1}</button>
          </fieldset>
        })}
        <button type="button" className="secondary" disabled={q.options.length>=20} onClick={()=>edit(q,{options:[...q.options,newOption(nextId())]})}>Tambah pilihan</button>
      </fieldset>)}
      <button type="button" className="secondary" disabled={draft.questions.length>=50} onClick={()=>update({questions:[...draft.questions,{
        id:nextId(),code:'',label:'',required:'',definitionSource:'',requiredSource:'',options:[newOption(nextId())],
      }]})}>Tambah pertanyaan</button>
    </fieldset>
  </>
}

export function PatientPreview({packet}:{packet:Packet}) {
  return <section className="instrument-preview" aria-label="Pratinjau pasien">
    <h4>Pratinjau pasien</h4>
    <div className="instrument-preview-card">
      <strong>1 dari 3 · Tingkat sesak</strong>
      <p>Seberapa sesak Anda hari ini?</p>
      <div className="instrument-preview-track" aria-hidden="true"/>
      <div className="instrument-preview-scale"><span>0 · Tidak sesak</span><span>100 · Sangat sesak</span></div>
      <p>Belum ada nilai yang dipilih.</p>
    </div>
    <div className="instrument-preview-card">
      <strong>2 dari 3 · Jawaban</strong>
      {packet.definition.questions.map(q=><div key={q.code}>
        <p>{q.label} ({q.required?'wajib':'opsional'})</p>
        <ul>{q.options.map(o=><li key={o.value}>{o.label}</li>)}</ul>
      </div>)}
      <p>Diisi oleh · Pasien atau keluarga</p>
    </div>
    <div className="instrument-preview-card"><strong>3 dari 3 · Periksa dan konfirmasi</strong></div>
  </section>
}
