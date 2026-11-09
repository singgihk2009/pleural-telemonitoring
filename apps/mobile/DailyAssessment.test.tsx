import { act, render, screen, fireEvent, waitFor } from '@testing-library/react-native';
import { AppState, type AppStateStatus } from 'react-native';
import { DailyAssessment, AssessmentHistory } from './src/patient/DailyAssessment';
import { PatientContext, type Context } from './src/patient/context';
import { ApiError } from './src/patient/api';
import type { AssessmentStatus } from './src/patient/assessment';

jest.mock('expo-router', () => ({ router: { push: jest.fn(), replace: jest.fn(), canGoBack: () => false } }));
const api = jest.fn();
const status: AssessmentStatus = {
  available: true, can_submit: true, submission_id: '11111111-1111-4111-8111-111111111111', date_wib: '2026-11-07', study_day: 1,
  questionnaire: { version: 'SYNTHETIC-V1', questions: [{ code: 'SYNTHETIC_ITEM', label: 'Item sintetis', required: true, options: [{ id: 1, label: 'Pilihan sintetis A' }] }] },
  submitted: null,
};
const receipt = { id: status.submission_id, date_wib: status.date_wib, study_day: 1, vas_mm: 0, filled_by: 'PASIEN', received_at: '2026-11-07T01:00:00Z', device_created_at: '2026-11-07T00:59:00Z', type: 'HARIAN', version: 'SYNTHETIC-V1', answers: [] };
function context(token = 'SYNTHETIC', offline = false): Context {
  return { session: { token, expires: '2099-01-01T00:00:00Z' }, profile: { id: 'SYNTHETIC-ID', status: 'AKTIF', tanggal_pulang: '2026-11-06' },
    ready: true, busy: false, offline, message: '', acknowledged: true,
    login: jest.fn(), logout: jest.fn(), retry: jest.fn(), acknowledge: jest.fn(), api };
}
async function mount(value = context()) {
  return render(<PatientContext.Provider value={value}><DailyAssessment /></PatientContext.Provider>);
}
async function confirm() {
  await screen.findByText('— — mm');
  await fireEvent(screen.getByRole('adjustable'), 'accessibilityAction', { nativeEvent: { actionName: 'increment' } });
  await fireEvent.press(screen.getByRole('button', { name: 'Lanjut' }));
  expect(screen.getByRole('button', { name: 'Periksa isian' })).toBeDisabled();
  await fireEvent.press(screen.getByRole('button', { name: 'Pilihan sintetis A' }));
  await fireEvent.press(screen.getByRole('button', { name: 'Pasien' }));
  await fireEvent.press(screen.getByRole('button', { name: 'Periksa isian' }));
}
beforeEach(() => {
  jest.clearAllMocks();
  api.mockReset();
  api.mockImplementation(async (path: string) => path.includes('/status') ? { data: status } : { data: receipt, replayed: false });
});

test('loads approved content, requires an explicit VAS and required answers, confirms and submits once', async () => {
  await mount(); await confirm();
  expect(api).toHaveBeenCalledTimes(1);
  await fireEvent.press(screen.getByRole('button', { name: 'Kirim isian' }));
  await screen.findByText('Isian berhasil tersimpan');
  expect(api).toHaveBeenLastCalledWith('/patient/assessments', expect.objectContaining({ submission_id: status.submission_id, vas_mm: 0, version: 'SYNTHETIC-V1', answers: [{ code: 'SYNTHETIC_ITEM', option_id: 1 }] }));
  expect(screen.queryByText(/MERAH|KUNING|HIJAU/)).toBeNull();
});

test('missing approved instrument shows unavailable with no wireframe answer submission', async () => {
  api.mockResolvedValue({ data: { ...status, available: false, can_submit: false, questionnaire: null } });
  await mount();
  await screen.findByText(/Assessment belum tersedia/);
  expect(screen.queryByRole('adjustable')).toBeNull();
  expect(screen.queryByRole('button', { name: 'Kirim isian' })).toBeNull();
});

test('loading and failed status are retryable without offering invented choices', async () => {
  let reject!: (error: Error) => void;
  api.mockImplementationOnce(() => new Promise((_resolve, fail) => { reject = fail; }));
  await mount(); expect(screen.getByLabelText('Memuat assessment')).toBeTruthy();
  await act(() => reject(new ApiError(0, 'offline')));
  await screen.findByRole('button', { name: 'Muat ulang status' });
  await fireEvent.press(screen.getByRole('button', { name: 'Muat ulang status' }));
  await screen.findByText('— — mm');
});

test.each([422, 409, 401, 403, 429])('safe error state for server %s', async code => {
  await mount(); await confirm(); api.mockRejectedValueOnce(new ApiError(code, 'synthetic'));
  await fireEvent.press(screen.getByRole('button', { name: 'Kirim isian' }));
  await screen.findByRole('button', { name: 'Coba kirim lagi dengan isian sama' });
  expect(screen.queryByText('Isian berhasil tersimpan')).toBeNull();
});

test('lost response retries exactly the same UUID, timestamp and answers, without repeated taps', async () => {
  await mount(); await confirm(); api.mockRejectedValueOnce(new ApiError(0, 'timeout'));
  await fireEvent.press(screen.getByRole('button', { name: 'Kirim isian' }));
  await screen.findByText(/Hasil pengiriman belum dapat dipastikan/);
  const body = api.mock.calls[1][1];
  expect(screen.getByRole('button', { name: 'Ubah isian' })).toBeDisabled();
  let resolve!: (value: unknown) => void;
  api.mockImplementationOnce(() => new Promise(done => { resolve = done; }));
  await fireEvent.press(screen.getByRole('button', { name: 'Coba kirim lagi dengan isian sama' }));
  await fireEvent.press(screen.getByRole('button', { name: 'Mengirim…' }));
  expect(api).toHaveBeenCalledTimes(3); expect(api.mock.calls[2][1]).toEqual(body);
  await act(() => resolve({ data: receipt, replayed: true }));
  await screen.findByText('Isian berhasil tersimpan');
});

test('offline confirmation cannot submit; help remains available', async () => {
  await mount(context('SYNTHETIC', true)); await confirm();
  expect(screen.getByRole('button', { name: 'Kirim isian' })).toBeDisabled();
  expect(screen.getByRole('button', { name: 'Darurat 119' })).toBeEnabled();
  expect(screen.getByRole('button', { name: 'Hubungi perawat' })).toBeEnabled();
});

test('closing a screen with unknown outcome recovers committed status without another POST', async () => {
  const view = await mount(); await confirm(); api.mockRejectedValueOnce(new ApiError(0, 'timeout'));
  await fireEvent.press(screen.getByRole('button', { name: 'Kirim isian' }));
  await screen.findByText(/Hasil pengiriman belum dapat dipastikan/); await view.unmount();
  api.mockResolvedValue({ data: { ...status, can_submit: false, submitted: receipt } });
  await mount(); await screen.findByText('Isian hari ini sudah tersimpan');
  expect(api.mock.calls.filter(call => call[0] === '/patient/assessments')).toHaveLength(1);
});

test('session change discards an old pending response and reloads new identity', async () => {
  const view = await mount(); await confirm(); let resolve!: (value: unknown) => void;
  api.mockImplementationOnce(() => new Promise(done => { resolve = done; }));
  await fireEvent.press(screen.getByRole('button', { name: 'Kirim isian' }));
  await view.rerender(<PatientContext.Provider value={context('OTHER-SYNTHETIC')}><DailyAssessment /></PatientContext.Provider>);
  await screen.findByText('— — mm'); await act(() => resolve({ data: receipt }));
  expect(screen.queryByText('Isian berhasil tersimpan')).toBeNull();
});

test('background hides a pending form and foreground preserves the same operation', async () => {
  let listener!: (state: AppStateStatus) => void;
  const spy = jest.spyOn(AppState, 'addEventListener').mockImplementation((_event, callback) => { listener = callback; return { remove: jest.fn() }; });
  await mount(); await confirm(); let resolve!: (value: unknown) => void;
  api.mockImplementationOnce(() => new Promise(done => { resolve = done; }));
  await fireEvent.press(screen.getByRole('button', { name: 'Kirim isian' }));
  await act(() => listener('background')); expect(screen.queryByText('Item sintetis: Pilihan sintetis A')).toBeNull();
  await act(() => listener('active')); await act(() => resolve({ data: receipt }));
  await screen.findByText('Isian berhasil tersimpan'); expect(api).toHaveBeenCalledTimes(2);
  spy.mockRestore();
});

test('history is patient scoped, paginated and contains only saved observations', async () => {
  api.mockResolvedValue({ data: [receipt], meta: { last_page: 2 } });
  await render(<PatientContext.Provider value={context()}><AssessmentHistory /></PatientContext.Provider>);
  await screen.findByText('VAS 0 mm · PASIEN'); expect(api).toHaveBeenCalledWith('/patient/assessments?page=1');
  await fireEvent.press(screen.getByRole('button', { name: 'Halaman berikutnya' }));
  await waitFor(() => expect(api).toHaveBeenCalledWith('/patient/assessments?page=2'));
});

test('published API-shaped instrument uses generated safe option IDs, separate unselected VAS and exact version in submission',async()=>{
  const listener=jest.spyOn(AppState,'addEventListener').mockReturnValue({remove:jest.fn()})
  const generated=4503599627370400
  const published={...status,questionnaire:{version:'SYNTHETIC_PUBLISHED_V1',questions:[{code:'SYNTHETIC_PUBLISHED',label:'Item versi terbit sintetis',required:true,options:[{id:generated,label:'Pilihan terbit sintetis'}]}]}}
  api.mockImplementation(async(path:string)=>path.includes('/status')?{data:published}:{data:{...receipt,version:published.questionnaire.version,answers:[{code:'SYNTHETIC_PUBLISHED',option_id:generated,value:'SYNTHETIC_A'}]},replayed:false})
  const mounted=await mount();await screen.findByText('— — mm')
  expect(screen.getByRole('button',{name:'Lanjut'})).toBeDisabled()
  await fireEvent(screen.getByRole('adjustable'),'accessibilityAction',{nativeEvent:{actionName:'increment'}})
  await fireEvent.press(screen.getByRole('button',{name:'Lanjut'}))
  await fireEvent.press(screen.getByRole('button',{name:'Pilihan terbit sintetis'}));await fireEvent.press(screen.getByRole('button',{name:'Pasien'}))
  await fireEvent.press(screen.getByRole('button',{name:'Periksa isian'}));await fireEvent.press(screen.getByRole('button',{name:'Kirim isian'}))
  await screen.findByText('Isian berhasil tersimpan')
  expect(api.mock.calls.find(call=>call[0]==='/patient/assessments')?.[1]).toMatchObject({version:'SYNTHETIC_PUBLISHED_V1',vas_mm:0,answers:[{code:'SYNTHETIC_PUBLISHED',option_id:generated}]})
  await mounted.unmount();listener.mockRestore()
})
