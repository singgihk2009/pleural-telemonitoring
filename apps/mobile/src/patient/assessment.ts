export type Question = {
  code: string; label: string; required: boolean;
  options: { id: number; label: string }[];
};
export type Assessment = {
  id: string; date_wib: string; study_day: number; vas_mm: number;
  type: string; filled_by: string; received_at: string; device_created_at: string;
  version: string | null; answers: { code: string; option_id: number; value: string }[];
};
export type AssessmentStatus = {
  available: boolean; can_submit: boolean; submission_id: string; date_wib: string; study_day: number;
  questionnaire: { version: string; questions: Question[] } | null;
  submitted: Assessment | null;
};
export type Submission = {
  submission_id: string; version: string; date_wib: string; vas_mm: number;
  filled_by: 'PASIEN' | 'KELUARGA'; device_created_at: string;
  answers: { code: string; option_id: number }[];
};
export function assessmentError(error: unknown): string {
  const status = (error as { status?: number })?.status;
  if (status === 422) return 'Jawaban tidak valid. Muat ulang formulir dan periksa isian.';
  if (status === 409) return 'Versi, tanggal, atau status pengisian berubah. Muat ulang status sebelum melanjutkan.';
  if (status === 401 || status === 403) return 'Sesi berakhir atau akses dicabut. Silakan masuk kembali.';
  if (status === 429) return 'Terlalu banyak permintaan. Tunggu lalu coba lagi dengan isian yang sama.';
  return 'Hasil pengiriman belum dapat dipastikan. Periksa koneksi dan coba lagi dengan isian yang sama. Jangan membuat pengiriman baru.';
}
