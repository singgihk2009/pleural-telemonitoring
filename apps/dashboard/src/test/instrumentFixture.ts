// Synthetic technical fixture ONLY; not a researcher-approved clinical instrument.
export function packet() {
  return {definition:{version:'SYNTHETIC_REVIEW_V1',schedule:{timezone:'Asia/Jakarta',first_day:1,last_day:30,late_policy:'same_day_only'},questions:[{code:'SYNTHETIC_ITEM',label:'Item sintetis, bukan klinis',required:true,options:[{value:'SYNTHETIC_A',label:'Pilihan sintetis'}]}]},evidence:{instrument:'SYNTHETIC test document §1',vas:'SYNTHETIC test §2',schedule:'SYNTHETIC test §3',validation:'SYNTHETIC test §4',questions:{SYNTHETIC_ITEM:{definition:'SYNTHETIC §5 coding/label',required:'SYNTHETIC §5 mandatory',options:{SYNTHETIC_A:'SYNTHETIC §6 mapping'}}}}}
}
export const future=(hours:number)=>new Date(Date.now()+hours*3600000+7*3600000).toISOString().slice(0,16)
