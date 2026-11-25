// @vitest-environment jsdom
import {cleanup, fireEvent, render, screen, waitFor} from '@testing-library/react'
import {afterEach, expect, test, vi} from 'vitest'
import {request} from './api'
import {EducationManager} from './EducationManager'
vi.mock('./api', async () => ({...await vi.importActual('./api'), request:vi.fn()}))
afterEach(()=>{cleanup();vi.resetAllMocks()})

test('reviews an imported draft and requires an explicit publication action',async()=>{
  const draft={code:'EDU-02',title:'Topik uji',description:'Deskripsi sintetis',display_order:2,
    status:'DRAFT',version:1,published_version:null,history:[],references:[]}
  const meta={current_page:1,last_page:1}
  vi.mocked(request).mockImplementation(async(path)=>path.includes('/EDU-02')?{data:draft,meta}:{data:[draft],meta})
  render(<EducationManager session={{token:'SYNTHETIC',expiresAt:'2099-01-01T00:00:00Z'}} onExpired={vi.fn()}/>)
  fireEvent.click(await screen.findByRole('button',{name:'EDU-02 · Topik uji'}))
  fireEvent.click(await screen.findByRole('button',{name:'Pratinjau pasien'}))
  expect(screen.getByRole('heading',{name:'Topik uji'})).toBeTruthy()
  fireEvent.click(screen.getByRole('button',{name:'Tinjau publikasi'}))
  const publish=screen.getByRole('button',{name:'Publikasikan materi'})
  expect((publish as HTMLButtonElement).disabled).toBe(true)
  expect(vi.mocked(request).mock.calls.every(call=>call[2]==='GET')).toBe(true)
  fireEvent.change(screen.getByLabelText('Referensi persetujuan'),{target:{value:'SYNTHETIC_TEST'}})
  fireEvent.click(publish)
  await waitFor(()=>expect(request).toHaveBeenCalledWith('/research/education/EDU-02/publish','SYNTHETIC','POST',{
    expected_version:1,confirm:true,approval_reference:'SYNTHETIC_TEST',
  }))
})
