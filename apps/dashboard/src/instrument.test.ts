import {expect,test} from 'vitest'
import {parsePacket,publicationInput,wibToUTC} from './instrument'
import {packet} from './test/instrumentFixture'
test('complete synthetic review maps only the existing publication contract and preserves VAS/source separation',()=>{
 const p=parsePacket(JSON.stringify(packet()));const body=publicationInput(p,'SYNTHETIC_APPROVAL','2027-01-01T08:00','2027-02-01T08:00',null,0)
 expect(body.effective_at).toBe('2027-01-01T01:00:00.000Z');expect(body.confirm_publication).toBe(true);expect(body.expected_current_version).toBe(null)
 expect(body).not.toHaveProperty('evidence');expect(body).not.toHaveProperty('vas_mm');expect(body.questions[0].options[0].value).toBe('SYNTHETIC_A')
})
test.each(['instrument','vas','schedule','validation'])('missing %s evidence never becomes publishable',field=>{
 const p=packet();p.evidence[field as 'vas']='';expect(()=>parsePacket(JSON.stringify(p))).toThrow()
})
test.each(['definition','required','option'])('every question/%s mapping needs its own source',field=>{
 const p=packet();if(field==='option')p.evidence.questions.SYNTHETIC_ITEM.options.SYNTHETIC_A='';else p.evidence.questions.SYNTHETIC_ITEM[field as 'required']=''
 expect(()=>parsePacket(JSON.stringify(p))).toThrow()
})
test.each(['score','float','late','duplicate','no-required','unknown-choice-field','missing-question-source'])('rejects incomplete or unsupported %s definitions',mode=>{
 const p:any=packet()
 if(mode==='score')p.definition.score='UNAPPROVED'
 if(mode==='float')p.definition.schedule.first_day=1.5
 if(mode==='late')p.definition.schedule.late_policy='allow_late'
 if(mode==='duplicate')p.definition.questions.push(p.definition.questions[0])
 if(mode==='no-required')delete p.definition.questions[0].required
 if(mode==='unknown-choice-field')p.definition.questions[0].options[0].risk='UNAPPROVED'
 if(mode==='missing-question-source')delete p.evidence.questions.SYNTHETIC_ITEM
 expect(()=>parsePacket(JSON.stringify(p))).toThrow()
})
test('rejects invalid calendar time, empty approval reference and expired/reversed windows without inventing defaults',()=>{
 expect(()=>wibToUTC('2027-02-30T12:00')).toThrow()
 const p=parsePacket(JSON.stringify(packet()))
 expect(()=>publicationInput(p,'','2027-01-01T08:00','2027-02-01T08:00',null,0)).toThrow()
 expect(()=>publicationInput(p,'SYNTHETIC','2027-02-01T08:00','2027-01-01T08:00',null,0)).toThrow()
 expect(()=>publicationInput(p,'SYNTHETIC','2027-01-01T08:00','2027-02-01T08:00',null,Date.parse('2028-01-01'))).toThrow()
})
test('API-valid but oversized content cannot be published into an incompatible encrypted Android queue',()=>{
 const p=packet()
 for(let i=1;i<=19;i++){
  const code=`SYNTHETIC_${i}`
  p.definition.questions.push({code,label:'SYNTHETIC '.repeat(25),required:true,options:[{value:'SYNTHETIC_A',label:'SYNTHETIC '.repeat(12)}]})
  p.evidence.questions[code as 'SYNTHETIC_ITEM']={definition:'SYNTHETIC source',required:'SYNTHETIC required',options:{SYNTHETIC_A:'SYNTHETIC mapping'}}
 }
 expect(()=>parsePacket(JSON.stringify(p))).toThrow(/anggaran antrean Android/)
})
