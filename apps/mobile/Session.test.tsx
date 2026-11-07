import { useEffect } from 'react';
import { act, render, waitFor } from '@testing-library/react-native';
import NetInfo from '@react-native-community/netinfo';
import { AppState, type AppStateStatus } from 'react-native';
import { PatientProvider } from './src/patient/session';
import { usePatient, type Context } from './src/patient/context';
import { request, ApiError } from './src/patient/api';
import { vault } from './src/patient/storage';

jest.mock('./src/patient/api', () => ({
  ...jest.requireActual('./src/patient/api'), request: jest.fn(),
}));
jest.mock('./src/patient/storage', () => ({
  vault: { read: jest.fn(), save: jest.fn(), clear: jest.fn() },
}));
function deferred() {
  let resolve!: (value?: unknown) => void;
  const promise = new Promise<unknown>((done) => { resolve = done; });
  return { promise, resolve };
}
let patient: Context;
let foreground: (state: AppStateStatus) => void;
function Probe() {
  const value = usePatient();
  useEffect(() => { patient = value; }, [value]);
  return null;
}
const session = { token: 'SYNTHETIC-ONE', expires: new Date(Date.now() + 3600000).toISOString() };
beforeEach(() => {
  jest.resetAllMocks();
  jest.mocked(NetInfo.addEventListener).mockReturnValue(jest.fn());
  jest.spyOn(AppState, 'addEventListener').mockImplementation((_event, listener) => {
    foreground = listener;
    return { remove: jest.fn() };
  });
  jest.mocked(vault.read).mockResolvedValue(null);
  jest.mocked(vault.save).mockResolvedValue(undefined);
  jest.mocked(vault.clear).mockResolvedValue(undefined);
});
afterEach(() => jest.restoreAllMocks());
function replies(stage: string, gate: ReturnType<typeof deferred>) {
  jest.mocked(request).mockImplementation(async (path) => {
    if ((stage === 'login' && path === '/auth/patient/login') ||
        (stage === 'identity' && path === '/auth/me') ||
        (stage === 'profile' && path.startsWith('/participants/'))) await gate.promise;
    if (path === '/auth/patient/login') return { access_token: session.token, expires_at: session.expires };
    if (path === '/auth/me') return { data: { id: 'SYNTHETIC-ID', kind: 'patient' } };
    if (path.startsWith('/participants/')) return { data: { id: 'SYNTHETIC-ID', status: 'AKTIF', tanggal_pulang: '2026-11-01' } };
    return {};
  });
  if (stage === 'storage') jest.mocked(vault.save).mockImplementation(async () => { await gate.promise; });
}
async function mount() {
  const view = await render(<PatientProvider><Probe /></PatientProvider>);
  await waitFor(() => expect(patient.ready).toBe(true));
  return view;
}
async function untilStage(stage: string) {
  await waitFor(() => {
    if (stage === 'storage') expect(vault.save).toHaveBeenCalled();
    else expect(request).toHaveBeenCalledWith(
      stage === 'login' ? '/auth/patient/login' : stage === 'identity' ? '/auth/me' : '/participants/SYNTHETIC-ID',
      stage === 'login' ? undefined : session.token,
      ...(stage === 'login' ? [{ code: 'SYNTHETIC', pin: '012345' }] : []),
    );
  });
}
test.each(['login', 'identity', 'profile', 'storage'])('foreground preserves pending %s and its token', async (stage) => {
  const gate = deferred(); replies(stage, gate); await mount();
  let login!: Promise<void>;
  await act(() => { login = patient.login('SYNTHETIC', '012345'); });
  await untilStage(stage);
  await act(async () => { foreground('background'); foreground('active'); await patient.retry(); });
  expect(patient.busy).toBe(true);
  expect(vault.read).toHaveBeenCalledTimes(1);
  await act(async () => { gate.resolve(); await login; });
  expect(patient.session).toEqual(session);
  expect(patient.busy).toBe(false);
  expect(vault.save).toHaveBeenCalledTimes(1);
  expect(vault.clear).not.toHaveBeenCalled();
  expect(request).not.toHaveBeenCalledWith('/auth/logout', session.token, {});
});
test.each(['login', 'identity', 'profile', 'storage'])('logout during %s revokes abandoned token', async (stage) => {
  const gate = deferred(); replies(stage, gate); await mount();
  let login!: Promise<void>;
  await act(() => { login = patient.login('SYNTHETIC', '012345'); });
  await untilStage(stage);
  let logout!: Promise<void>;
  await act(() => { logout = patient.logout(); });
  await act(async () => { gate.resolve(); await Promise.all([login, logout]); });
  expect(patient.session).toBeNull();
  expect(request).toHaveBeenCalledWith('/auth/logout', session.token, {});
});
test.each(['login', 'identity', 'profile', 'storage'])('unmount during %s revokes the abandoned token', async (stage) => {
  const gate = deferred(); replies(stage, gate); const view = await mount();
  let login!: Promise<void>;
  await act(() => { login = patient.login('SYNTHETIC', '012345'); });
  await untilStage(stage);
  await view.unmount();
  await act(async () => { gate.resolve(); await login; });
  expect(request).toHaveBeenCalledWith('/auth/logout', session.token, {});
  if (stage !== 'storage') expect(vault.save).not.toHaveBeenCalled();
  else expect(vault.clear).toHaveBeenCalledTimes(1);
});
test.each(['login', 'identity', 'profile', 'storage'])('superseded %s revokes only the old token', async (stage) => {
  const gate = deferred(); replies(stage, gate); await mount();
  let first!: Promise<void>;
  await act(() => { first = patient.login('SYNTHETIC', '012345'); });
  await untilStage(stage);
  const newer = { ...session, token: 'SYNTHETIC-TWO' };
  jest.mocked(request).mockImplementation(async (path) => {
    if (path === '/auth/patient/login') return { access_token: newer.token, expires_at: newer.expires };
    if (path === '/auth/me') return { data: { id: 'SYNTHETIC-ID', kind: 'patient' } };
    return { data: { id: 'SYNTHETIC-ID', status: 'AKTIF', tanggal_pulang: '2026-11-01' } };
  });
  let second!: Promise<void>;
  await act(() => { second = patient.login('SYNTHETIC', '012345'); });
  await act(async () => { gate.resolve(); await Promise.all([first, second]); });
  expect(patient.session).toEqual(newer);
  expect(request).toHaveBeenCalledWith('/auth/logout', session.token, {});
  expect(request).not.toHaveBeenCalledWith('/auth/logout', newer.token, {});
  expect(vault.clear).toHaveBeenCalledTimes(stage === 'storage' ? 1 : 0);
  expect(vault.save).toHaveBeenLastCalledWith(newer);
  if (stage === 'storage') expect(jest.mocked(vault.clear).mock.invocationCallOrder[0]).toBeLessThan(jest.mocked(vault.save).mock.invocationCallOrder[1]);
});


test('an abandoned assessment rejection cannot clear a newer patient session', async () => {
  let reject!: (reason: Error) => void;
  const held = new Promise((_resolve, fail) => { reject = fail; });
  let logins = 0;
  const newer = { ...session, token: 'SYNTHETIC-NEW-PATIENT' };
  jest.mocked(request).mockImplementation(async path => {
    if (path === '/auth/patient/login') {
      const value = ++logins === 1 ? session : newer;
      return { access_token: value.token, expires_at: value.expires };
    }
    if (path === '/auth/me') return { data: { id: 'SYNTHETIC-ID', kind: 'patient' } };
    if (path.startsWith('/participants/')) return { data: { id: 'SYNTHETIC-ID', status: 'AKTIF', tanggal_pulang: '2026-11-01' } };
    if (path === '/patient/assessments') return held;
    return {};
  });
  await mount();
  await act(() => patient.login('SYNTHETIC', '012345'));
  const body = { submission_id: 'SYNTHETIC-REQUEST' };
  const pending = patient.api('/patient/assessments', body).catch(error => error);
  expect(request).toHaveBeenCalledWith('/patient/assessments', session.token, body);
  await act(() => patient.logout());
  await act(() => patient.login('OTHER-SYNTHETIC', '654321'));
  const clearCount = jest.mocked(vault.clear).mock.calls.length;
  await act(async () => { reject(new ApiError(401, 'Old session revoked')); await pending; });
  expect(patient.session?.token).toBe(newer.token);
  expect(vault.clear).toHaveBeenCalledTimes(clearCount);
});
