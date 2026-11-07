import type { LocalState } from './core';
export const stateText: { [state in LocalState]: string } = {
  DRAFT: 'Draf terenkripsi pada perangkat', PENDING_SYNC: 'Belum ada tanda terima server',
  SYNCING: 'Menunggu konfirmasi server', SYNCED: 'Tanda terima server diverifikasi',
  RECONCILIATION_REQUIRED: 'Perlu rekonsiliasi; data lokal dipertahankan', REJECTED: 'Validasi ditolak; tinjau isian lokal',
};
export const reasonText: { [reason: string]: string } = {
  RECEIPT_MISMATCH: 'Tanda terima tidak cocok. Hentikan dan hubungi tim penelitian.',
  RETENTION_REVIEW: 'Draf lama memerlukan peninjauan; tidak dikirim otomatis.',
  VERSION_DATE_OR_STATUS_CHANGED: 'Versi, tanggal, atau kelayakan berubah. Isian lama tetap disimpan.',
  CONFLICT_CHECK_HISTORY: 'Ada konflik. Periksa riwayat server sebelum melanjutkan.',
  VALIDATION_REVIEW: 'Jawaban perlu ditinjau. Isian yang sudah diantrekan tidak diubah otomatis.',
  AUTH_REQUIRED: 'Masuk kembali untuk memvalidasi akses.',
  DELIVERY_UNCERTAIN: 'Layanan belum mengonfirmasi penyimpanan. Percobaan ulang memakai isian yang sama.',
};
