import { act, render, screen, fireEvent, waitFor } from '@testing-library/react-native';
import { Platform, AppState, Alert, type AppStateStatus } from 'react-native';
import * as SecureStore from 'expo-secure-store';
import { PatientContext, type Context } from './src/patient/context';
import { OfflineProvider } from './src/patient/offline/Provider';
import { DailyAssessment } from './src/patient/DailyAssessment';
import { request, ApiError } from './src/patient/api';
import type { AssessmentStatus, Submission } from './src/patient/assessment';
jest.mock('./src/patient/api', () => ({ ...jest.requireActual('./src/patient/api'), request: jest.fn() }));
jest.mock('expo-router', () => ({ router: { push: jest.fn(), replace: jest.fn(), canGoBack: () => false } }));
const id = '11111111-1111-4111-8111-111111111111'; const revision = '22222222-2222-4222-8222-222222222222';
const snapshot: AssessmentStatus = { available: true, can_submit: true, study_day: 1, date_wib: new Date(Date.now()+7*3600000).toISOString().slice(0,10), submission_id: '33333333-3333-4333-8333-333333333333', submitted: null,
  questionnaire: { version: 'SYNTHETIC_V1', questions: [{ code: 'SYNTHETIC', label: 'Item sintetis', required: true, options: [{ id: 1, label: 'Pilihan sintetis' }] }] } };
const disk = new Map<string,string>(); const logout = jest.fn(); let stored: ReturnType<typeof receipt> | null;
const originalOS = Platform.OS;
const originalAppState = Object.getOwnPropertyDescriptor(AppState,'currentState')!;
function receipt(body: Submission) { return { id: body.submission_id, date_wib: body.date_wib, study_day: 1, type: 'HARIAN', vas_mm: body.vas_mm, filled_by: body.filled_by, version: body.version, device_created_at: body.device_created_at, received_at: new Date().toISOString(), answers: body.answers.map(a => ({ ...a, value: 'SYNTHETIC_OPTION' })) }; }
function patient(offline = false, token = 'SYNTHETIC-ONLY'): Context {
  return { ready: true, busy: false, offline, message: '', acknowledged: true,
    session: { token, expires: '2099-01-01T00:00:00Z' }, profile: { id, status: 'AKTIF', tanggal_pulang: '2026-11-01', credential_revision: revision },
    login: jest.fn(), logout, retry: jest.fn(), acknowledge: jest.fn(), api: (path,body) => request(path,token,body) };
}
const view = (context = patient()) => <PatientContext.Provider value={context}><OfflineProvider><DailyAssessment /></OfflineProvider></PatientContext.Provider>;
async function fill() {
  await screen.findByText('— — mm');
  await fireEvent(screen.getByRole('adjustable'), 'accessibilityAction', { nativeEvent: { actionName: 'increment' } });
  await fireEvent.press(screen.getByRole('button', { name: 'Lanjut' }));
  await fireEvent.press(screen.getByRole('button', { name: 'Pilihan sintetis' }));
  await fireEvent.press(screen.getByRole('button', { name: 'Pasien' }));
  await waitFor(() => expect(screen.getByRole('button', { name: 'Periksa isian' })).toBeEnabled());
  await fireEvent.press(screen.getByRole('button', { name: 'Periksa isian' }));
}
beforeEach(() => {
  process.env.EXPO_PUBLIC_API_BASE_URL = 'http://localhost:8000/api/v1';
  Object.defineProperty(AppState,'currentState',{value:'active',configurable:true,writable:true});
  disk.clear(); stored = null; jest.clearAllMocks(); Object.defineProperty(Platform,'OS',{value:'android',configurable:true});
  jest.mocked(SecureStore.isAvailableAsync).mockResolvedValue(true);
  jest.mocked(SecureStore.getItemAsync).mockImplementation(async key => disk.get(key) ?? null);
  jest.mocked(SecureStore.setItemAsync).mockImplementation(async (key,value) => { disk.set(key,value); });
  jest.mocked(SecureStore.deleteItemAsync).mockImplementation(async key => { disk.delete(key); });
  jest.mocked(request).mockImplementation(async (path,_token,body) => {
    if (path === '/auth/me') return { data: { id, kind: 'patient', credential_revision: revision } };
    if (path.endsWith('/status')) return { data: snapshot };
    if (path.includes('?submission_id=')) return { data: stored ? [stored] : [], meta: { total: stored ? 1 : 0 } };
    stored = receipt(body as Submission); return { data: stored, replayed: false };
  });
});
afterEach(() => { Object.defineProperty(AppState,'currentState',originalAppState); Object.defineProperty(Platform,'OS',{value:originalOS,configurable:true}); jest.restoreAllMocks(); });
test('saves reviewed draft securely and shows success only after the exact server receipt', async () => {
  await render(view()); await fill();
  await fireEvent.press(screen.getByRole('button', { name: 'Simpan aman dan antrekan isian' }));
  await screen.findByText('Isian berhasil tersimpan');
  const posts = jest.mocked(request).mock.calls.filter(call => call[0] === '/patient/assessments'); expect(posts).toHaveLength(1);
  expect(posts[0][1]).toBe('SYNTHETIC-ONLY'); expect(posts[0][2]).toMatchObject({ submission_id: snapshot.submission_id, vas_mm: 0 });
  const item = [...disk.entries()].find(([key]) => key.startsWith('telenapas.offline.item.'))!;
  expect(item[1]).not.toContain('SYNTHETIC-ONLY'); expect(JSON.parse(item[1]).state).toBe('SYNCED');
});
test('offline pending submission survives unmount/restart and synchronizes after verified reconnect', async () => {
  const mounted = await render(view()); await fill();
  await mounted.rerender(view(patient(true)));
  await fireEvent.press(screen.getByRole('button', { name: 'Simpan aman dan antrekan isian' }));
  await screen.findByText(/Belum ada tanda terima server/);
  expect(jest.mocked(request).mock.calls.filter(call => call[0] === '/patient/assessments')).toHaveLength(0);
  const raw = [...disk.entries()].find(([key]) => key.startsWith('telenapas.offline.item.'))![1]; const original = JSON.parse(raw).body;
  await mounted.unmount(); await render(view());
  await screen.findByText('Isian berhasil tersimpan');
  expect(jest.mocked(request).mock.calls.filter(call => call[0] === '/patient/assessments')[0][2]).toEqual(original);
});
test('failed encryption is unavailable with no unencrypted or in-memory submission fallback', async () => {
  jest.mocked(SecureStore.isAvailableAsync).mockResolvedValue(false);
  await render(view()); await screen.findByText(/Antrean terenkripsi belum dapat dibuka/);
  expect(screen.queryByRole('adjustable')).toBeNull(); expect(request).not.toHaveBeenCalled();
});
test('authentication rejection stops synchronization and delegates secure logout', async () => {
  await render(view()); await fill();
  const prior = jest.mocked(request).getMockImplementation()!;
  jest.mocked(request).mockImplementation((path,...args) => path === '/auth/me' ? Promise.reject(new ApiError(401,'synthetic revoked')) : prior(path,...args));
  await fireEvent.press(screen.getByRole('button', { name: 'Simpan aman dan antrekan isian' }));
  await waitFor(() => expect(logout).toHaveBeenCalledTimes(1));
  expect(jest.mocked(request).mock.calls.filter(call => call[0] === '/patient/assessments')).toHaveLength(0);
});
test('background response loss preserves uncertain state; foreground reconciles committed history', async () => {
  const listeners: ((state: AppStateStatus) => void)[] = [];
  jest.spyOn(AppState,'addEventListener').mockImplementation((_event,listener) => { listeners.push(listener); return {remove:jest.fn()}; });
  await render(view()); await fill();
  let resolve!: (value: unknown) => void; const prior = jest.mocked(request).getMockImplementation()!;
  jest.mocked(request).mockImplementation((path,token,body) => {
    if (path === '/patient/assessments') { stored = receipt(body as Submission); return new Promise(done => { resolve = done; }); }
    return prior(path,token,body);
  });
  await fireEvent.press(screen.getByRole('button', { name: 'Simpan aman dan antrekan isian' }));
  await waitFor(() => expect(resolve).toBeDefined());
  await act(() => listeners.forEach(listener => listener('background')));
  await act(() => resolve({ data: stored, replayed: false }));
  const raw = [...disk.entries()].find(([key]) => key.startsWith('telenapas.offline.item.'))![1]; expect(JSON.parse(raw).state).toBe('SYNCING');
  await act(() => listeners.forEach(listener => listener('active')));
  await screen.findByText('Isian berhasil tersimpan');
  expect(jest.mocked(request).mock.calls.filter(call => call[0] === '/patient/assessments')).toHaveLength(1);
});


test('account switch during a pending request never sends an old body with the new token or logs out the new account', async () => {
  const mounted = await render(view()); await fill();
  const prior = jest.mocked(request).getMockImplementation()!;
  let reject!: (error: Error) => void;
  const otherId = '44444444-4444-4444-8444-444444444444';
  jest.mocked(request).mockImplementation((path,token,body) => {
    if (path === '/patient/assessments') return new Promise((_done,fail) => { reject = fail; });
    if (token === 'OTHER-SYNTHETIC' && path.endsWith('/status')) return Promise.resolve({ data: { ...snapshot, submission_id: otherId, questionnaire: { ...snapshot.questionnaire!, version: 'SYNTHETIC_OTHER' } } });
    return prior(path,token,body);
  });
  await fireEvent.press(screen.getByRole('button', { name: 'Simpan aman dan antrekan isian' }));
  await waitFor(() => expect(reject).toBeDefined());
  const other = { ...patient(false,'OTHER-SYNTHETIC'), profile: { ...patient().profile!, id: otherId } };
  await mounted.rerender(view(other)); await screen.findByText('— — mm');
  await act(() => reject(new ApiError(401,'Synthetic old token revoked')));
  expect(logout).not.toHaveBeenCalled(); expect(screen.queryByText('Isian berhasil tersimpan')).toBeNull();
  const posts = jest.mocked(request).mock.calls.filter(call => call[0] === '/patient/assessments');
  expect(posts).toHaveLength(1); expect(posts[0][1]).toBe('SYNTHETIC-ONLY');
});


test('unknown initial AppState cannot start a worker until foreground is observed', async () => {
  Object.defineProperty(AppState,'currentState',{value:null,configurable:true,writable:true});
  const listeners: ((state: AppStateStatus) => void)[] = [];
  jest.spyOn(AppState,'addEventListener').mockImplementation((_event,listener) => { listeners.push(listener); return {remove:jest.fn()}; });
  await render(view()); await fill();
  await fireEvent.press(screen.getByRole('button', { name: 'Simpan aman dan antrekan isian' }));
  await screen.findByText(/Belum ada tanda terima server/);
  expect(jest.mocked(request).mock.calls.filter(call => call[0] === '/patient/assessments')).toHaveLength(0);
  await act(() => listeners.forEach(listener => listener('active')));
  await screen.findByText('Isian berhasil tersimpan');
});

test('obsolete unsubmitted draft survives restart unchanged until confirmed discard then loads the new server day', async () => {
  const mounted = await render(view(patient(true))); await fill();
  const itemKey = [...disk.keys()].find(key => key.startsWith('telenapas.offline.item.'))!;
  const original = JSON.parse(disk.get(itemKey)!).body;
  await mounted.unmount();
  const next = { ...snapshot, date_wib: new Date(Date.parse(snapshot.date_wib)+86400000).toISOString().slice(0,10), study_day: 2,
    submission_id: '44444444-4444-4444-8444-444444444444', questionnaire: { ...snapshot.questionnaire!, version: 'SYNTHETIC_V2' } };
  const prior = jest.mocked(request).getMockImplementation()!;
  jest.mocked(request).mockImplementation((path,...args) => path.endsWith('/status') ? Promise.resolve({ data: next }) : prior(path,...args));
  const alert = jest.spyOn(Alert,'alert'); await render(view());
  await screen.findByText(`Draf belum dikirim · tanggal ${snapshot.date_wib} WIB.`);
  expect(JSON.parse(disk.get(itemKey)!).body).toEqual(original);
  await fireEvent.press(screen.getByRole('button',{name:'Buang draf belum dikirim…'}));
  const [,message,buttons] = alert.mock.calls[0];
  expect(message).toContain(snapshot.date_wib); expect(message).toContain('menghilangkan jawaban lokal');
  await act(() => buttons?.find(button => button.text === 'Batal')?.onPress?.());
  expect(JSON.parse(disk.get(itemKey)!).body).toEqual(original);
  await act(() => buttons?.find(button => button.text === 'Buang draf')?.onPress?.());
  await screen.findByText(`Draf belum dikirim · tanggal ${next.date_wib} WIB.`);
  expect(screen.getByText('— — mm')).toBeTruthy();
  expect(JSON.parse(disk.get(itemKey)!).body).toMatchObject({ date_wib: next.date_wib, submission_id: next.submission_id, version:'SYNTHETIC_V2', vas_mm:null, filled_by:null, answers:[] });
  expect(jest.mocked(request).mock.calls.filter(call => call[0] === '/patient/assessments')).toHaveLength(0);
  await fill(); await fireEvent.press(screen.getByRole('button',{name:'Simpan aman dan antrekan isian'}));
  await screen.findByText('Isian berhasil tersimpan');
  const posts = jest.mocked(request).mock.calls.filter(call => call[0] === '/patient/assessments');
  expect(posts).toHaveLength(1); expect(posts[0][2]).toMatchObject({ date_wib:next.date_wib, submission_id:next.submission_id,version:'SYNTHETIC_V2' });
});
test.each(['PENDING_SYNC','SYNCING','REJECTED','RECONCILIATION_REQUIRED'] as const)('%s never exposes simplified draft disposal', async state => {
  const mounted = await render(view(patient(true))); await fill(); await mounted.unmount();
  const key = [...disk.keys()].find(key => key.startsWith('telenapas.offline.item.'))!;
  disk.set(key,JSON.stringify({ ...JSON.parse(disk.get(key)!),state }));
  await render(view(patient(true)));
  await screen.findByRole('button',{name:'Periksa server / sinkronkan'});
  expect(screen.queryByRole('button',{name:'Buang draf belum dikirim…'})).toBeNull();
  expect(screen.getByRole('button',{name:'Buang isian lokal…'})).toBeTruthy();
});

test.each((['REJECTED','RECONCILIATION_REQUIRED'] as const).flatMap(state => [0,429,503,401,403].map(status => ({state,status}))))('$state stays read-only after a manual $status failure, foreground and restart', async ({state,status}) => {
  const listeners: ((state: AppStateStatus) => void)[] = [];
  jest.spyOn(AppState,'addEventListener').mockImplementation((_event,listener) => { listeners.push(listener); return {remove:jest.fn()}; });
  const mounted = await render(view(patient(true))); await fill(); await mounted.unmount();
  const key = [...disk.keys()].find(key => key.startsWith('telenapas.offline.item.'))!;
  const item = JSON.parse(disk.get(key)!); disk.set(key,JSON.stringify({...item,state}));
  const prior = jest.mocked(request).getMockImplementation()!;
  jest.mocked(request).mockImplementation((path,...args) => path === '/auth/me' ? Promise.reject(new ApiError(status,'Synthetic reconciliation failure')) : prior(path,...args));
  const restored = await render(view());
  await screen.findByRole('button',{name:'Periksa server / sinkronkan'});
  await fireEvent.press(screen.getByRole('button',{name:'Periksa server / sinkronkan'}));
  await waitFor(() => expect(JSON.parse(disk.get(key)!).reason).toBe(status===401 || status===403 ? 'AUTH_REQUIRED' : 'RECONCILIATION_READ_FAILED'));
  expect(JSON.parse(disk.get(key)!).state).toBe(state); expect(JSON.parse(disk.get(key)!).body).toEqual(item.body);
  if (status===401 || status===403) expect(logout).toHaveBeenCalledTimes(1);
  else expect(screen.getByText(/Ini hanya waktu pemeriksaan manual/)).toBeTruthy();
  await act(() => listeners.forEach(listener => listener('background')));
  await act(() => listeners.forEach(listener => listener('active')));
  await restored.unmount(); await render(view());
  await screen.findByRole('button',{name:'Periksa server / sinkronkan'});
  expect(JSON.parse(disk.get(key)!).state).toBe(state);
  expect(jest.mocked(request).mock.calls.filter(call => call[0] === '/patient/assessments')).toHaveLength(0);
});
test('a stale draft confirmation cannot dispose an item queued while the dialog was open', async () => {
  await render(view(patient(true))); await fill();
  const alert = jest.spyOn(Alert,'alert');
  await fireEvent.press(screen.getByRole('button',{name:'Buang draf belum dikirim…'}));
  const buttons = alert.mock.calls[0][2];
  await fireEvent.press(screen.getByRole('button',{name:'Simpan aman dan antrekan isian'}));
  await screen.findByText(/Belum ada tanda terima server/);
  const key = [...disk.keys()].find(key => key.startsWith('telenapas.offline.item.'))!; const before = disk.get(key);
  await act(() => buttons?.find(button => button.text==='Buang draf')?.onPress?.());
  await screen.findByText(/Pembuangan draf belum terkonfirmasi/);
  expect(disk.get(key)).toBe(before); expect(SecureStore.deleteItemAsync).not.toHaveBeenCalled();
});
test('failed secure draft deletion never resets the form or initializes a replacement', async () => {
  await render(view(patient(true))); await fill();
  const key = [...disk.keys()].find(key => key.startsWith('telenapas.offline.item.'))!; const before = disk.get(key);
  const calls = jest.mocked(request).mock.calls.length;
  jest.mocked(SecureStore.deleteItemAsync).mockRejectedValue(new Error('Synthetic deletion failure'));
  const alert = jest.spyOn(Alert,'alert');
  await fireEvent.press(screen.getByRole('button',{name:'Buang draf belum dikirim…'}));
  await act(() => alert.mock.calls[0][2]?.find(button => button.text==='Buang draf')?.onPress?.());
  await screen.findByText(/Pembuangan draf belum terkonfirmasi/);
  expect(disk.get(key)).toBe(before); expect(request).toHaveBeenCalledTimes(calls);
});

test.each(['REJECTED','RECONCILIATION_REQUIRED'] as const)('%s manual reconciliation after valid re-login does not reuse an old AUTH_REQUIRED logout signal', async state => {
  const mounted = await render(view(patient(true))); await fill(); await mounted.unmount();
  const key = [...disk.keys()].find(key => key.startsWith('telenapas.offline.item.'))!;
  const item = JSON.parse(disk.get(key)!); disk.set(key,JSON.stringify({ ...item,state,reason:'AUTH_REQUIRED' }));
  await render(view(patient(false,'SYNTHETIC-RELOGIN')));
  await screen.findByRole('button',{name:'Periksa server / sinkronkan'});
  await fireEvent.press(screen.getByRole('button',{name:'Periksa server / sinkronkan'}));
  await waitFor(() => expect(JSON.parse(disk.get(key)!).reason).toBe('RECONCILIATION_NO_RECEIPT'));
  expect(logout).not.toHaveBeenCalled(); expect(JSON.parse(disk.get(key)!).state).toBe(state);
  expect(JSON.parse(disk.get(key)!).body).toEqual(item.body);
  expect(jest.mocked(request).mock.calls.filter(call => call[0] === '/patient/assessments')).toHaveLength(0);
});
