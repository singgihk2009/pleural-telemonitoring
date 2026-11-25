# Edukasi pasien TeleNapas

## Baseline dan kesenjangan

Baseline `main` pada `9abf949` memiliki empat placeholder F7 pada Android.
Tidak ditemukan tabel, API atau layar administrasi katalog edukasi.
Implementasi ini memakai shell, kartu, navigasi terlindungi, autentikasi,
kebijakan peneliti dan audit TeleNapas. Katalog terpisah dari Daily Assessment,
antrean offline, Health-ITUES dan outcome klinis.

## Desain dan operasi katalog

`education_topics` menyimpan kode, pointer revisi terbaru dan pointer revisi
publik. `education_revisions` menyimpan snapshot versi, urutan, status, aktor,
waktu UTC dan referensi persetujuan. `education_references` menyimpan sumber
berurutan per revisi, termasuk maksimal satu YouTube. Trigger menolak perubahan
atau penghapusan snapshot. Simpan draf setelah publikasi untuk mempertahankan
versi publik yang sudah direview. Arsip mengosongkan pointer publik segera.
Publikasi, pengarsipan, pointer dan audit ditulis dalam satu transaksi. CAS
`expected_version` menolak perubahan dari tampilan yang kedaluwarsa.

API pasien menyaring publikasi pada query dan tidak memberikan histori, aktor
atau bukti persetujuan. API peneliti memakai otorisasi enrollment existing,
termasuk deny-first untuk kombinasi role IT dan penilai. Mutasi memeriksa kembali
akun dan token dalam transaksi. Payload audit memuat kode operasi/topik, tanpa
data pasien. Pelacakan pembukaan atau tontonan pasien belum diaktifkan.

Aplikasi memvalidasi HTTPS kembali saat handoff. YouTube memakai host pasti
dan URL video, lalu Android memilih penangan tautan yang tersedia. TeleNapas
meneruskan URL sesuai katalog tanpa menambahkan identitas, token atau parameter
kesehatan. Artikel, DOI dan PDF juga dibuka eksternal. API tidak mengambil
konten URL, dan aplikasi tidak menyimpan file video atau dokumen. Saat offline
katalog menampilkan status dan memuat ulang saat koneksi pulih. Metadata tidak
dipersistenkan. [Kontrak OpenAPI](../api/openapi.yaml)

## Siapkan draf dan tinjau

1. Di database development atau UAT MySQL disposable, gunakan proses migrasi
   existing dengan akun migrasi. Migrasi tidak mengisi atau memublikasikan
   katalog. Dari `apps/api` pada instance terisolasi, jalankan:

   ```sh
   php artisan telenapas:import-education-drafts
   ```

   Import menambah kode yang belum ada dari
   [fixture](../../apps/api/resources/education/research-baseline.json).
   Import ulang mempertahankan topik dan riwayat yang sudah dikelola peneliti.
2. Login sebagai peneliti sintetis. Buka **Materi Penelitian → Edukasi Pasien**.
   Tinjau EDU-01–04 dan urutan. Deskripsi awal sengaja kosong dan harus berasal
   dari peneliti. EDU-02 memiliki satu URL YouTube persis dari sumber. Cakupan
   kedua teknik pernapasan menunggu review konten. Tiga topik lain tanpa video.
3. Buka dan verifikasi sumber secara manual. EDU-04 mempertahankan URL awal
   `https://share.google/8F7HRhBspoyXaj19q`. Pemeriksaan dari cloud mengembalikan
   HTTP 403 pada proxy dan tujuan belum dapat dibuktikan. Catat URL kanonik
   HTTPS yang diperiksa pada revisi EDU-04. API menolak publikasi tautan
   `share.google` tanpa tujuan kanonik. URL awal tetap tersimpan pada histori.
4. Masukkan deskripsi yang ditinjau, cek pratinjau dan urutan, simpan draf.
   Tinjau kembali dan masukkan referensi persetujuan aktual sebelum klik
   **Publikasikan materi**. Periksa status dan histori. Jangan memasukkan
   instruksi klinis baru atau klaim bahwa sumber telah disetujui.
5. Periksa tampilan pasien dengan akun sintetis dan tanpa data asli. Ubah
   materi dari dashboard, publikasikan revisi, lalu muat ulang Android yang
   memiliki fitur ini untuk memastikan konten berubah tanpa rebuild.
   Arsipkan topik contoh di UAT dan periksa visibilitas pasien.

## Checklist UAT

| Kriteria | Skenario dan bukti yang diharapkan |
|---|---|
| EDU-UAT-01 | Import empat topik. Dashboard menunjukkan draf, sumber dan versi awal. Ulangi import setelah edit dan cocokkan versi. |
| EDU-UAT-02–03 | Publikasikan dua topik sintetis dengan urutan berbeda. Periksa daftar Android, lalu arsip dan pastikan detail hilang. Draf baru tidak mengganti publikasi yang masih aktif. |
| EDU-UAT-04–07 | Pada perangkat dengan YouTube, tekan **Tonton Video** pada EDU-02 dan periksa tujuan. Ulangi tanpa YouTube untuk browser fallback. Gunakan Kembali pada kedua perangkat dan periksa TeleNapas. |
| EDU-UAT-08–09 | Topik tanpa video tidak menampilkan tombol video. Buka artikel, DOI dan PDF satu per satu, lalu periksa penangan eksternal. |
| EDU-UAT-10–11 | Uji skema berbahaya, host palsu, tautan pendek yang belum ditinjau, kegagalan Linking, serta koneksi offline dan pulih. |
| EDU-UAT-12 | Setelah publikasi revisi baru, muat ulang Android dengan APK yang sama dan cek urutan serta referensi baru. |
| EDU-UAT-13–14 | Gunakan akun staf tanpa wewenang serta akun pasien. Pastikan mutasi ditolak dan periksa audit operasi yang berwenang. |
| EDU-UAT-15–16 | Catat argumen Linking yang dikirim persis sesuai katalog. Pastikan tidak ada data pribadi tambahan, file media atau pemanggilan API YouTube. |
| EDU-UAT-17 | Jalankan suite auth, Daily Assessment, Health-ITUES dan sinkronisasi offline. Ulangi alur penting pada APK UAT setelah uji perangkat tersedia. |

## Hasil pengujian — 25 November 2026 WIB

| Pemeriksaan | Hasil |
|---|---|
| Laravel, MySQL 8.4 disposable, Composer validate, Pint | PASS — 248 tes dan 2.271 assertion. Empat tes migrasi opt-in existing dilewati pada suite reguler. Migrasi baru berhasil diterapkan pada instance uji baru. |
| Katalog API | PASS — empat draf, impor ulang, CAS, review/publish eksplisit, urutan, versi publik tetap saat draf diedit, arsip, histori immutable, RBAC dan audit. Uji juga menolak URL palsu dan menahan short link tanpa tujuan kanonik. |
| Dashboard | PASS — 89 tes, termasuk pratinjau dan konfirmasi publikasi dengan referensi persetujuan. |
| Android Jest | PASS — 166 tes. Mock Linking memverifikasi URL YouTube, DOI, dan PDF diteruskan apa adanya. Error handoff, video opsional, kembali, offline dan reconnect diuji. |
| Lint, typecheck, OpenAPI, build dashboard dan ekspor Android | PASS. Ekspor menghasilkan JavaScript dan aset. |
| Routing YouTube, browser fallback, kembali dari aplikasi eksternal, perubahan katalog pada APK sama | NOT VERIFIED pada perangkat. Uji dilakukan pada API dan komponen melalui mock native. |
| EDU-04 dan persetujuan isi | NOT VERIFIED. Proxy menolak pemeriksaan redirect asli. Tujuan kanonik sintetis hanya dipakai oleh tes. |

Pengujian tidak mengakses `telenapas_demo`, mengunduh video, atau memublikasikan
materi pada lingkungan operasional. Deployment dan review peneliti terpisah.
