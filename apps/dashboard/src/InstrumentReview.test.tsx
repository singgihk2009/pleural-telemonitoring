// @vitest-environment jsdom
import {render,screen,fireEvent,waitFor,cleanup} from '@testing-library/react'
import {vi,beforeEach,afterEach,test,expect} from 'vitest'
import {InstrumentReview} from './InstrumentReview'
import {ApiError,request} from './api'
import {packet,future} from './test/instrumentFixture'
vi.mock('./api',async()=>({...await vi.importActual('./api'),request:vi.fn()}))
const session={token:'SYNTHETIC',expiresAt:'2099-01-01T00:00:00Z'}
const expired=vi.fn(),close=vi.fn(),logout=vi.fn(async()=>{})
const index={current_version:null,data:[],meta:{current_page:1,last_page:1}}
const posts=()=>vi.mocked(request).mock.calls.filter(c=>c[2]==='POST')
const mount=()=>render(<InstrumentReview session={session} onExpired={expired} onClose={close} onLogout={logout}/> )
async function ready(){
 await screen.findByLabelText('Paket review JSON')
 fireEvent.change(screen.getByLabelText('Paket review JSON'),{target:{value:JSON.stringify(packet())}})
 fireEvent.change(screen.getByLabelText('Referensi persetujuan versi ini'),{target:{value:'SYNTHETIC_APPROVAL'}})
 fireEvent.change(screen.getByLabelText('Waktu berlaku (WIB)'),{target:{value:future(1)}})
 fireEvent.change(screen.getByLabelText('Kedaluwarsa (WIB)'),{target:{value:future(2)}})
 fireEvent.click(screen.getByRole('button',{name:'Tinjau kesiapan publikasi'}))
 await screen.findByRole('heading',{name:'Ringkasan sebelum publikasi'})
}
beforeEach(()=>{vi.resetAllMocks();vi.mocked(request).mockImplementation(async(_path,_token,method,data)=>method==='POST'?{data:{...(data as object),approved_by:'11111111-1111-4111-8111-111111111111',approved_at:new Date().toISOString()}}:index)})
afterEach(cleanup)
test('empty source data stays pending; opening/reloading never POSTs or seeds defaults',async()=>{
 mount();await screen.findByText('Menunggu kelengkapan/persetujuan');expect(posts()).toHaveLength(0)
 expect((screen.getByLabelText('Paket review JSON')as HTMLTextAreaElement).value).toBe('')
 fireEvent.click(screen.getByRole('button',{name:'Tinjau kesiapan publikasi'}));await screen.findByRole('alert');expect(posts()).toHaveLength(0)
 expect(screen.queryByRole('button',{name:'Publikasikan versi ini'})).toBeNull()
})
test('complete review still requires explicit confirmation; one guarded POST uses current version and strips citations',async()=>{
 mount();await ready();expect(posts()).toHaveLength(0)
 const button=screen.getByRole('button',{name:'Publikasikan versi ini'});expect(button.hasAttribute('disabled')).toBe(true)
 fireEvent.click(screen.getByRole('checkbox'));fireEvent.click(button);fireEvent.click(button)
 await screen.findByRole('heading',{name:'Tanda terima publikasi server'})
 expect(posts()).toHaveLength(1);expect(posts()[0][3]).toMatchObject({expected_current_version:null,confirm_publication:true,approval_reference:'SYNTHETIC_APPROVAL'})
 expect(posts()[0][3]).not.toHaveProperty('evidence')
})
test.each([401,403])('server %s denies access before showing the editor or any definitions',async code=>{
 vi.mocked(request).mockRejectedValue(new ApiError(code));mount()
 if(code===401)await waitFor(()=>expect(expired).toHaveBeenCalled());else await screen.findByRole('alert')
 expect(screen.queryByLabelText('Paket review JSON')).toBeNull();expect(posts()).toHaveLength(0)
})
test('fresh version conflict stops before POST and requires new review',async()=>{
 mount();await ready();vi.mocked(request).mockResolvedValue({...index,current_version:'SYNTHETIC_OTHER'})
 fireEvent.click(screen.getByRole('checkbox'));fireEvent.click(screen.getByRole('button',{name:'Publikasikan versi ini'}))
 await screen.findByText(/Versi aktif berubah/);expect(posts()).toHaveLength(0);expect(screen.queryByRole('checkbox')).toBeNull()
})
test.each([409,422])('publication %s never claims success or automatically retries',async code=>{
 mount();await ready();const prior=vi.mocked(request).getMockImplementation()!
 vi.mocked(request).mockImplementation((path,t,m,...rest)=>m==='POST'?Promise.reject(new ApiError(code)):prior(path,t,m,...rest))
 fireEvent.click(screen.getByRole('checkbox'));fireEvent.click(screen.getByRole('button',{name:'Publikasikan versi ini'}))
 await screen.findByRole('alert');expect(posts()).toHaveLength(1);expect(screen.queryByRole('heading',{name:'Tanda terima publikasi server'})).toBeNull()
})
test('lost response preserves uncertainty, no blind repost; pending close is disabled and unmounted response ignored',async()=>{
 const mounted=mount();await ready();let reject!:(e:Error)=>void;const prior=vi.mocked(request).getMockImplementation()!
 vi.mocked(request).mockImplementation((path,t,m,...rest)=>m==='POST'?new Promise((_ok,no)=>{reject=no}):prior(path,t,m,...rest))
 fireEvent.click(screen.getByRole('checkbox'));fireEvent.click(screen.getByRole('button',{name:'Publikasikan versi ini'}))
 await waitFor(()=>expect(reject).toBeDefined());expect(screen.getByRole('button',{name:'Tutup review'}).hasAttribute('disabled')).toBe(true)
 reject(new Error('Synthetic lost response'));await screen.findByText(/Hasil publikasi belum dapat dipastikan/)
 expect(screen.getByLabelText('Paket review JSON').closest('fieldset')?.disabled).toBe(true)
 fireEvent.click(screen.getByRole('button',{name:'Periksa daftar server'}));await screen.findByText('Belum ada versi tersimpan. Tidak ada instrumen yang dibuat otomatis.')
 expect(posts()).toHaveLength(1);mounted.unmount()
})
test('editing any review field invalidates confirmation',async()=>{
 mount();await ready();fireEvent.click(screen.getByRole('checkbox'))
 fireEvent.change(screen.getByLabelText('Referensi persetujuan versi ini'),{target:{value:'SYNTHETIC_NEW'}})
 expect(screen.queryByRole('checkbox')).toBeNull();expect(posts()).toHaveLength(0)
})

test('mismatched receipt is uncertain, never a success or permission to re-POST',async()=>{
 mount();await ready();const prior=vi.mocked(request).getMockImplementation()!
 vi.mocked(request).mockImplementation((p,t,m,...rest)=>m==='POST'?Promise.resolve({data:{version:'SYNTHETIC_WRONG'}}):prior(p,t,m,...rest))
 fireEvent.click(screen.getByRole('checkbox'));fireEvent.click(screen.getByRole('button',{name:'Publikasikan versi ini'}))
 await screen.findByText(/Hasil publikasi belum dapat dipastikan/);expect(posts()).toHaveLength(1)
 expect(screen.queryByRole('heading',{name:'Tanda terima publikasi server'})).toBeNull()
})
test('logout/unmount while POST is pending cannot show a late receipt or trigger another mutation',async()=>{
 const mounted=mount();await ready();let resolve!:(v:unknown)=>void;const prior=vi.mocked(request).getMockImplementation()!
 vi.mocked(request).mockImplementation((p,t,m,...rest)=>m==='POST'?new Promise(ok=>{resolve=ok}):prior(p,t,m,...rest))
 fireEvent.click(screen.getByRole('checkbox'));fireEvent.click(screen.getByRole('button',{name:'Publikasikan versi ini'}))
 await waitFor(()=>expect(resolve).toBeDefined());fireEvent.click(screen.getByRole('button',{name:'Keluar'}));expect(logout).toHaveBeenCalledTimes(1)
 mounted.unmount();resolve({data:{version:'SYNTHETIC_REVIEW_V1'}});await Promise.resolve()
 expect(posts()).toHaveLength(1);expect(screen.queryByRole('dialog')).toBeNull()
})
