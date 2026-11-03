export type Group = 'INTERVENSI' | 'KONTROL'
export type Status = 'AKTIF' | 'SELESAI' | 'DROP_OUT'
export type Contact = {
  initials: string | null
  patient_contact: string | null
  family_contact: string | null
}
export type Participant = {
  id: string
  kode: string
  kelompok: Group
  tanggal_pulang: string
  vas_baseline_mm: number
  tanggal_consent: string
  versi_consent: string
  status: Status
  updated_at: string | null
}
export type Enrollment = Participant & {
  alasan_drop_out: string | null
  contact: Contact | null
  baseline_locked: boolean
}
export type Results = {
  data: Participant[]
  meta: {
    total: number
    intervensi: number
    kontrol: number
    aktif: number
    current_page: number
    last_page: number
  }
}
export type Session = { token: string; expiresAt: string }
export class ApiError extends Error {
  status: number
  fields: Record<string, string[]>
  constructor(status: number, fields: Record<string, string[]> = {}) {
    super('Permintaan tidak dapat diselesaikan.')
    this.status = status
    this.fields = fields
  }
}
const base = (import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api/v1').replace(
  /\/$/,
  '',
)
export async function request<T>(
  path: string,
  token: string | null,
  method = 'GET',
  data?: unknown,
  signal?: AbortSignal,
): Promise<T> {
  const response = await fetch(`${base}${path}`, {
    method,
    signal,
    credentials: 'omit',
    cache: 'no-store',
    headers: {
      Accept: 'application/json',
      ...(data !== undefined ? { 'Content-Type': 'application/json' } : {}),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    ...(data !== undefined ? { body: JSON.stringify(data) } : {}),
  })
  const body = await response.json().catch(() => ({}))
  if (!response.ok) throw new ApiError(response.status, body.errors || {})
  return body as T
}
export function errorMessage(error: unknown): string {
  if (!(error instanceof ApiError))
    return 'Tidak dapat terhubung ke layanan. Periksa koneksi dan coba lagi.'
  if (error.status === 409)
    return 'Data telah diperbarui oleh peneliti lain. Tutup editor dan muat ulang sebelum menyimpan kembali.'
  if (error.status === 422) return 'Periksa kembali isian yang ditandai.'
  if (error.status === 403) return 'Akun ini tidak memiliki izin untuk tindakan tersebut.'
  if (error.status === 429) return 'Terlalu banyak percobaan masuk. Tunggu sebelum mencoba lagi.'
  if (error.status === 401) return 'Sesi telah berakhir. Silakan masuk kembali.'
  return 'Layanan belum dapat memproses permintaan. Coba lagi nanti.'
}
