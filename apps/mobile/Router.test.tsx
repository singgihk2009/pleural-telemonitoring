import NetInfo from '@react-native-community/netinfo';
import { act } from '@testing-library/react-native';
import { renderRouter, screen, fireEvent } from 'expo-router/testing-library';
import * as SecureStore from 'expo-secure-store';
import RootLayout from './src/app/_layout';
import Index from './src/app/index';
import Ack from './src/app/acknowledgement';
import Daily from './src/app/daily';
import Contact from './src/app/contact';
import Education from './src/app/education';
import Final from './src/app/final';

const routes = {
  _layout: RootLayout,
  index: Index,
  acknowledgement: Ack,
  daily: Daily,
  contact: Contact,
  education: Education,
  final: Final,
};
const mockFetch = jest.fn();
const wibToday = new Date(Date.now() + 7 * 3600000).toISOString().slice(0, 10);
function reply(data: object, status = 200) {
  return { ok: status < 400, status, json: async () => data };
}
function loginReplies(date = wibToday) {
  mockFetch
    .mockResolvedValueOnce(
      reply({
        access_token: 'SYNTHETIC-TOKEN',
        expires_at: new Date(Date.now() + 3600000).toISOString(),
        token_type: 'Bearer',
      }),
    )
    .mockResolvedValueOnce(reply({ data: { id: 'SYNTHETIC-ID', kind: 'patient' } }))
    .mockResolvedValueOnce(
      reply({ data: { id: 'SYNTHETIC-ID', status: 'AKTIF', tanggal_pulang: date } }),
    );
}
async function enter() {
  await fireEvent.changeText(screen.getByLabelText('Kode responden'), 'SYNTHETIC-CODE');
  await fireEvent.changeText(screen.getByLabelText('PIN 6 digit'), '012345');
  await fireEvent.press(screen.getByRole('button', { name: 'Masuk' }));
}
beforeEach(() => {
  jest.clearAllMocks();
  mockFetch.mockReset();
  globalThis.fetch = mockFetch;
  process.env.EXPO_PUBLIC_API_BASE_URL = 'http://localhost:8000/api/v1';
  // apiBase reads the environment at call time, allowing deterministic test configuration.
  jest.mocked(SecureStore.getItemAsync).mockResolvedValue(null);
});

test('protected deep links go to login; help remains accessible', async () => {
  await renderRouter(routes, { initialUrl: '/daily' });
  expect(await screen.findByText('Selamat datang')).toBeTruthy();
  expect(screen).toHavePathname('/');
  await fireEvent.press(screen.getByRole('button', { name: 'Hubungi perawat' }));
  expect(await screen.findByRole('header', { name: 'Hubungi perawat' })).toBeTruthy();
  expect(screen.getByRole('button', { name: 'Darurat 119' })).toBeTruthy();
  expect(mockFetch).not.toHaveBeenCalled();
});
test('invalid PIN never reaches the server', async () => {
  await renderRouter(routes, { initialUrl: '/' });
  await screen.findByText('Selamat datang');
  await fireEvent.press(screen.getByRole('button', { name: 'Masuk' }));
  expect(screen.getByRole('alert')).toHaveTextContent(/PIN tepat 6 digit/);
  expect(mockFetch).not.toHaveBeenCalled();
});
test('patient login, secure persistence and acknowledgement precede home', async () => {
  loginReplies();
  await renderRouter(routes, { initialUrl: '/' });
  await screen.findByText('Selamat datang');
  await enter();
  expect(await screen.findByText('Pernyataan awal')).toBeTruthy();
  expect(screen.getByRole('button', { name: 'Lanjut' })).toBeDisabled();
  await fireEvent.press(screen.getByRole('checkbox'));
  await fireEvent.press(screen.getByRole('button', { name: 'Lanjut' }));
  expect(await screen.findByText('Hari 0 dari 30')).toBeTruthy();
  expect(screen.getByRole('button', { name: 'Isi tingkat sesak hari ini' })).toBeDisabled();
  expect(mockFetch.mock.calls[0][0]).toContain('/auth/patient/login');
  expect(JSON.parse(mockFetch.mock.calls[0][1].body)).toEqual({
    code: 'SYNTHETIC-CODE',
    pin: '012345',
  });
  expect(SecureStore.setItemAsync).toHaveBeenCalledWith(
    'telenapas.patient.session.v1',
    expect.not.stringContaining('012345'),
    expect.any(Object),
  );
  expect(SecureStore.setItemAsync).toHaveBeenCalledWith(
    expect.any(String),
    expect.not.stringContaining('SYNTHETIC-CODE'),
    expect.any(Object),
  );
});
test.each([401, 429, 503])(
  'login status %s reports safe errors without storing token',
  async (status) => {
    mockFetch.mockResolvedValue(reply({ message: 'SECRET SERVER DETAILS' }, status));
    await renderRouter(routes, { initialUrl: '/' });
    await screen.findByText('Selamat datang');
    await enter();
    expect(
      await screen.findByText(
        status === 429
          ? /Terlalu banyak percobaan/
          : status === 401
            ? /Kode atau PIN tidak sesuai/
            : /Layanan belum dapat/,
      ),
    ).toBeTruthy();
    expect(SecureStore.setItemAsync).not.toHaveBeenCalled();
    expect(screen.queryByText('SECRET SERVER DETAILS')).toBeNull();
    expect(screen.getByLabelText('PIN 6 digit')).toHaveProp('value', '');
  },
);
test('network failure is recoverable and does not persist credentials', async () => {
  mockFetch.mockRejectedValue(new Error('sensitive host error'));
  await renderRouter(routes, { initialUrl: '/' });
  await screen.findByText('Selamat datang');
  await enter();
  expect(await screen.findByText(/Tidak dapat terhubung/)).toBeTruthy();
  expect(SecureStore.setItemAsync).not.toHaveBeenCalled();
});
test('staff tokens are rejected and revoked without secure persistence', async () => {
  mockFetch
    .mockResolvedValueOnce(
      reply({
        access_token: 'SYN-STAFF',
        expires_at: new Date(Date.now() + 3600000).toISOString(),
      }),
    )
    .mockResolvedValueOnce(reply({ data: { id: 'SYN-STAFF', kind: 'staff' } }))
    .mockResolvedValueOnce(reply({}));
  await renderRouter(routes, { initialUrl: '/' });
  await screen.findByText('Selamat datang');
  await enter();
  expect(await screen.findByText('Akun ini bukan akun pasien.')).toBeTruthy();
  expect(SecureStore.setItemAsync).not.toHaveBeenCalled();
  expect(mockFetch.mock.calls[2][0]).toContain('/auth/logout');
});
test('expired persisted session is cleared before network access', async () => {
  jest
    .mocked(SecureStore.getItemAsync)
    .mockResolvedValue(JSON.stringify({ token: 'SYN-EXPIRED', expires: '2020-01-01T00:00:00Z' }));
  await renderRouter(routes, { initialUrl: '/' });
  expect(await screen.findByText('Sesi berakhir. Silakan masuk kembali.')).toBeTruthy();
  expect(SecureStore.deleteItemAsync).toHaveBeenCalled();
  expect(mockFetch).not.toHaveBeenCalled();
});
test('day 31 closes daily navigation and leaves final form available', async () => {
  const discharge = new Date(Date.parse(wibToday + 'T00:00:00Z') - 31 * 86400000)
    .toISOString()
    .slice(0, 10);
  loginReplies(discharge);
  await renderRouter(routes, { initialUrl: '/' });
  await screen.findByText('Selamat datang');
  await enter();
  await screen.findByText('Pernyataan awal');
  await fireEvent.press(screen.getByRole('checkbox'));
  await fireEvent.press(screen.getByRole('button', { name: 'Lanjut' }));
  expect(await screen.findByText('Masa pemantauan 30 hari sudah selesai')).toBeTruthy();
  expect(screen.queryByRole('button', { name: 'Isi tingkat sesak hari ini' })).toBeNull();
  await fireEvent.press(screen.getByRole('button', { name: 'Buka formulir akhir' }));
  expect(await screen.findByText('Health-ITUES')).toBeTruthy();
  expect(screen.getByRole('button', { name: 'Kirim penilaian · belum tersedia' })).toBeDisabled();
});

test('secure storage failure revokes the issued token and keeps login closed', async () => {
  loginReplies();
  mockFetch.mockResolvedValueOnce(reply({}));
  jest
    .mocked(SecureStore.setItemAsync)
    .mockRejectedValueOnce(new Error('SYNTHETIC storage failure'));
  await renderRouter(routes, { initialUrl: '/' });
  await screen.findByText('Selamat datang');
  await enter();
  expect(await screen.findByText(/Tidak dapat menyimpan sesi dengan aman/)).toBeTruthy();
  expect(mockFetch.mock.calls[3][0]).toContain('/auth/logout');
  expect(screen.queryByText('Pernyataan awal')).toBeNull();
});
test('logout clears secure storage and requests server revocation', async () => {
  loginReplies();
  mockFetch.mockResolvedValueOnce(reply({}));
  await renderRouter(routes, { initialUrl: '/' });
  await screen.findByText('Selamat datang');
  await enter();
  await screen.findByText('Pernyataan awal');
  await fireEvent.press(screen.getByRole('checkbox'));
  await fireEvent.press(screen.getByRole('button', { name: 'Lanjut' }));
  await screen.findByText('Hari 0 dari 30');
  await fireEvent.press(screen.getByRole('button', { name: 'Keluar' }));
  expect(await screen.findByText('Selamat datang')).toBeTruthy();
  expect(SecureStore.deleteItemAsync).toHaveBeenCalled();
  expect(mockFetch.mock.calls[3][0]).toContain('/auth/logout');
});
test('restored revoked token clears secure storage and protected screens', async () => {
  jest.mocked(SecureStore.getItemAsync).mockResolvedValue(
    JSON.stringify({
      token: 'SYN-REVOKED',
      expires: new Date(Date.now() + 3600000).toISOString(),
    }),
  );
  mockFetch.mockResolvedValue(reply({}, 401));
  await renderRouter(routes, { initialUrl: '/daily' });
  expect(await screen.findByText(/Kode atau PIN tidak sesuai/)).toBeTruthy();
  expect(SecureStore.deleteItemAsync).toHaveBeenCalled();
  expect(screen).toHavePathname('/');
});

test('active session expiration returns to login and clears secure storage', async () => {
  mockFetch
    .mockResolvedValueOnce(
      reply({
        access_token: 'SYN-SHORT',
        expires_at: new Date(Date.now() + 900).toISOString(),
        token_type: 'Bearer',
      }),
    )
    .mockResolvedValueOnce(reply({ data: { id: 'SYNTHETIC-ID', kind: 'patient' } }))
    .mockResolvedValueOnce(
      reply({ data: { id: 'SYNTHETIC-ID', status: 'AKTIF', tanggal_pulang: wibToday } }),
    );
  await renderRouter(routes, { initialUrl: '/' });
  await screen.findByText('Selamat datang');
  await enter();
  await screen.findByText('Pernyataan awal');
  expect(
    await screen.findByText('Sesi berakhir. Silakan masuk kembali.', {}, { timeout: 2500 }),
  ).toBeTruthy();
  expect(SecureStore.deleteItemAsync).toHaveBeenCalled();
});

test('day-12 navigation refuses assessment without an approved server instrument', async () => {
  const discharge = new Date(Date.parse(wibToday + 'T00:00:00Z') - 12 * 86400000).toISOString().slice(0, 10);
  loginReplies(discharge);
  mockFetch.mockResolvedValueOnce(reply({ data: { available: false, can_submit: false, date_wib: wibToday, study_day: 12, questionnaire: null, submitted: null } }));
  await renderRouter(routes, { initialUrl: '/' });
  await screen.findByText('Selamat datang'); await enter();
  await screen.findByText('Pernyataan awal'); await fireEvent.press(screen.getByRole('checkbox'));
  await fireEvent.press(screen.getByRole('button', { name: 'Lanjut' })); await screen.findByText('Hari 12 dari 30');
  await fireEvent.press(screen.getByRole('button', { name: 'Isi tingkat sesak hari ini' }));
  await screen.findByText(/Assessment belum tersedia/);
  expect(screen.queryByRole('adjustable')).toBeNull();
  expect(screen.queryByRole('button', { name: 'Kirim isian' })).toBeNull();
  expect(mockFetch).toHaveBeenCalledTimes(4);
  expect(mockFetch.mock.calls[3][0]).toMatch(/patient\/assessments\/status$/);
});

test('public internet validation does not block a reachable local API; disconnection does', async () => {
  await renderRouter(routes, { initialUrl: '/' });
  await screen.findByText('Selamat datang');
  const listener = jest.mocked(NetInfo.addEventListener).mock.calls[0][0];
  await act(() => listener({ isConnected: true, isInternetReachable: false } as never));
  expect(screen.getByRole('button', { name: 'Masuk' })).not.toBeDisabled();
  await act(() => listener({ isConnected: false, isInternetReachable: false } as never));
  expect(screen.getByRole('button', { name: 'Masuk' })).toBeDisabled();
  expect(screen.getByText(/Anda sedang offline/)).toBeTruthy();
  expect(screen.getByRole('button', { name: 'Darurat 119' })).not.toBeDisabled();
});
