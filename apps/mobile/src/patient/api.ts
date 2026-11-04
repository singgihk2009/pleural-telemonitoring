// No patient data, PINs, tokens or response bodies are logged.
export function apiBase(): string {
  const base = process.env.EXPO_PUBLIC_API_BASE_URL ?? '';
  const url = base.replace(/\/$/, '');
  if (
    !/^https:\/\/[^\s]+\/api\/v1$/.test(url) &&
    !(__DEV__ && /^http:\/\/(localhost|127\.0\.0\.1|10\.0\.2\.2)(:\d+)?\/api\/v1$/.test(url))
  ) {
    throw new ApiError(0, 'Alamat layanan belum dikonfigurasi. Hubungi tim penelitian.');
  }
  return url;
}
export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
  ) {
    super(message);
  }
}
export async function request<T>(path: string, token?: string, body?: object): Promise<T> {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 15000);
  try {
    const response = await fetch(`${apiBase()}${path}`, {
      method: body ? 'POST' : 'GET',
      signal: controller.signal,
      cache: 'no-store',
      credentials: 'omit',
      headers: {
        Accept: 'application/json',
        ...(body ? { 'Content-Type': 'application/json' } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      ...(body ? { body: JSON.stringify(body) } : {}),
    });
    if (!response.ok)
      throw new ApiError(
        response.status,
        response.status === 429
          ? 'Terlalu banyak percobaan. Tunggu sebelum mencoba lagi.'
          : response.status === 401
            ? 'Kode atau PIN tidak sesuai, atau sesi berakhir. Silakan masuk kembali.'
            : response.status === 403
              ? 'Akses tidak diizinkan. Hubungi tim penelitian.'
              : 'Layanan belum dapat memproses permintaan. Coba lagi.',
      );
    return (await response.json()) as T;
  } catch (error) {
    if (error instanceof ApiError) throw error;
    throw new ApiError(0, 'Tidak dapat terhubung. Periksa koneksi internet lalu coba lagi.');
  } finally {
    clearTimeout(timeout);
  }
}
export type Session = { token: string; expires: string };
export type Profile = { id: string; status: string; tanggal_pulang: string };
export function studyDay(discharge: string, now = new Date()): number | null {
  // Existing Laravel immutable_date responses serialize as UTC midnight.
  if (!/^\d{4}-\d{2}-\d{2}(T00:00:00(?:\.0+)?Z)?$/.test(discharge)) return null;
  discharge = discharge.slice(0, 10);
  const instant = Date.parse(`${discharge}T00:00:00Z`);
  if (!Number.isFinite(instant) || new Date(instant).toISOString().slice(0, 10) !== discharge)
    return null;
  const wib = new Date(now.getTime() + 7 * 3600000).toISOString().slice(0, 10);
  return Math.floor((Date.parse(`${wib}T00:00:00Z`) - instant) / 86400000);
}
