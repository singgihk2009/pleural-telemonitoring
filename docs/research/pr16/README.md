# PR16 — Kandidat Daily Assessment, belum boleh dipublikasikan

Tanggal review: 9 November 2026 UTC/WIB. Base `main` **ad90358**, PR11–15 sudah
terintegrasi. Branch `feature/pr-16-instrument-finalization`.

**Status: Menunggu kelengkapan/persetujuan.** PR ini tidak memfinalkan instrumen
klinis atau melakukan publikasi. Lampiran aktual adalah Panduan Visual dan Panduan
Sederhana; keduanya panduan teknis turunan, bukan proposal penelitian asli.
Proposal belum ditemukan dalam repository/lampiran yang tersedia. Karena itu,
Tahap A membaca proposal asli **belum dapat diverifikasi**, bukan dinyatakan selesai.
Permintaan klarifikasi telah disampaikan. Pernyataan pengguna bahwa proposal telah
disetujui tidak diubah menjadi nomor persetujuan atau isi proposal yang belum dilihat.

- [Matriks dokumen versus implementasi](traceability.md)
- [Keputusan/pertanyaan untuk peneliti](decisions.md)
- [Kandidat JSON — DO NOT PUBLISH](daily-assessment.candidate.DO-NOT-PUBLISH.json)
- [Evaluasi UI/UX Android dan rencana editor tanpa JSON](android-ux.md)
- [Pengujian dan UAT](validation.md)

## Arti kandidat JSON

Top-level memakai `definition` dan `evidence` PR15. Setiap pertanyaan memiliki
label sumber, sitasi definisi/required/pilihan, dan kode teknis usulan Q1–Q8.
Kode ini hanya penanda urutan audit, **belum kode instrumen yang disetujui**.
TIDAK/YA menyalin label Tidak/Ya dari G hlm6 menjadi kode teknis usulan; tidak
mengasumsikan bahwa mapping tersebut sudah disahkan. Tidak ada nilai default
pasien, skor atau interpretasi klinis.

JSON secara sintaks valid, tetapi **sengaja tidak valid untuk publikasi**:

- `definition.version: null`: tidak mengarang versi penelitian final.
- Semua `required: null`: sumber belum menetapkan wajib/opsional. Jangan mengganti
  null menjadi false, karena false berarti keputusan opsional.
- Nyeri dada Q2 `options: []`: konflik pilihan tidak diselesaikan secara sepihak.
- `schedule.late_policy: null`: same-day-only didukung implementasi tetapi kebijakan
  kiriman lewat tengah malam belum disahkan dari dokumen yang tersedia.

Jadwal hari1–30/Asia-Jakarta memiliki sumber G/H/S; pencantuman bukan persetujuan
calendar effective/expiry. VAS adalah field terpisah `vas_mm`, tidak dibuat sebagai
pertanyaan single-choice. Pengisi adalah enum global `filled_by`, tidak mengganti
syarat keluarga Q4. Tidak ada approval_reference, waktu efektif atau expiry palsu.
Field tersebut diisi terpisah oleh peneliti pada UI PR15 setelah keputusan lengkap.

Jangan mengirim file ini ke endpoint publikasi atau memakai untuk seeding. PR15
menolak kandidat ini tanpa perubahan validator. Tes regresi memastikan pemberian
versi/kebijakan teknis saja, bahkan blanket required=true, tidak membuat kandidat
lulus. Tidak ada runtime yang mengimpor atau mengaktifkannya. Evidence bukan bukti
persetujuan digital. Setelah keputusan lengkap, buat paket versi baru yang ditinjau,
arsipkan mapping/sumber pada referensi persetujuan aktual, dan lakukan publikasi
manual melalui workflow existing. Jangan menyunting publication atau riwayat lama.

Tidak ada API, schema, auth, antrean offline, `.env`, kredensial, konfigurasi server,
klasifikasi, notifikasi, FCM atau WhatsApp yang diubah. `telenapas_demo` tidak diakses.
