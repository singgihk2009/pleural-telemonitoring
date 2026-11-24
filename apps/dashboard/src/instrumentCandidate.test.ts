/// <reference types="node" />
// Safety regression for a research-review artifact, not a synthetic active catalog.
import {readFileSync} from 'node:fs'
import {createHash} from 'node:crypto'
import {test,expect} from 'vitest'
import {parsePacket,InstrumentReviewError} from './instrument'
const root=new URL('../../../',import.meta.url)
const read=(p:string)=>readFileSync(new URL(p,root),'utf8')
const raw=read('docs/research/pr16/daily-assessment.candidate.DO-NOT-PUBLISH.json')
test('PR16 unresolved candidate remains invalid for publication without changing the PR15 validator',()=>{
 const p=JSON.parse(raw)
 expect(Object.keys(p).sort()).toEqual(['definition','evidence'])
 expect(p.definition.version).toBeNull()
 expect(p.definition.schedule.late_policy).toBeNull()
 expect(p.definition.questions.every((q:{required:unknown})=>q.required===null)).toBe(true)
 expect(()=>parsePacket(raw)).toThrow(InstrumentReviewError)
 // A technical version/calendar alone must never resolve clinical decisions.
 p.definition.version='SYNTHETIC_NOT_APPROVAL';p.definition.schedule.late_policy='same_day_only'
 expect(()=>parsePacket(JSON.stringify(p))).toThrow(InstrumentReviewError)
 // Even blanket required flags must not silently select a chest-pain vocabulary.
 p.definition.questions.forEach((q:{required:unknown})=>q.required=true)
 expect(p.definition.questions.find((q:{code:string})=>q.code==='Q2').options).toEqual([])
 expect(()=>parsePacket(JSON.stringify(p))).toThrow(InstrumentReviewError)
})
test('candidate evidence has one source mapping per item and supplied answer; clinical outputs are absent',()=>{
 const p=JSON.parse(raw)
 expect(p.definition.questions.map((q:{code:string})=>q.code)).toEqual(['Q1','Q2','Q3','Q4','Q5','Q6','Q7','Q8'])
 for(const q of p.definition.questions){
  const e=p.evidence.questions[q.code]
  expect(e.definition).toContain('G hlm6');expect(e.definition).toContain('H hlm3')
  expect(e.required).toContain('BELUM DIPUTUSKAN')
  expect(Object.keys(e.options)).toEqual(q.options.map((o:{value:string})=>o.value))
 }
 expect(Object.keys(p.definition).sort()).toEqual(['questions','schedule','version'])
 expect(Object.keys(p.evidence.questions)).toHaveLength(8)
})
test('reviewed PDF copies match the documented source identities',()=>{
 const hash=(p:string)=>createHash('sha256').update(readFileSync(new URL(p,root))).digest('hex')
 expect(hash('docs/design/TeleNapas_Panduan_Sederhana_IT.pdf')).toBe('18e0ebb3c2cd3aec185f7dd94c65a0203d9f463397c6a85517e8deb6432bedb2')
 expect(hash('docs/design/TeleNapas_Panduan_Visual_IT.pdf')).toBe('0a564e84de7f45acf15d176de4db3167dda7d0f0047cd9d216219d3039516a1b')
})
