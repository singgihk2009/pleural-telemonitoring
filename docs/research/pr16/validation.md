# PR16 — Validasi dan UAT

## Isolasi dan perubahan

Base main ad90358. Perubahan hanya dokumen,salinan panduan sederhana,kandidat JSON
yang diblokir,dan tes negatif dashboard. Tidak ada controller,migration,factory,
seeder,session/queue/API/mobile runtime atau konfigurasi lingkungan yang diubah.
Tidak ada INSERT/UPDATE/DELETE/truncate/reset/seed di `telenapas_demo`;server UAT
tidak dihubungi. `.env`,kredensial,server dan volume existing dipertahankan.

Gunakan Node24,pnpm10.28.2,PHP8.4,MySQL8.4. Pengujian penulisan hanya pada schema
lokal baru **pleural_pr16_test** dan **pleural_pr16_stage_b_integration_test**.
Migrasi diterapkan pada schema kosong;tidak ada rollback migration pada tugas ini.
Suite API menggunakan transaksi fixture;integration guard memerlukan opt-in,
APP_ENV=testing,nama schema spesifik dan kondisi kosong,kemudian membersihkan
fixture dengan transaksi. Jangan mengarahkannya ke database penelitian/demo.

## Reproduksi

Dengan konfigurasi test terisolasi yang sudah disiapkan (bukan mengganti .env):

```sh
pnpm test
pnpm lint
pnpm typecheck
pnpm validate:openapi
pnpm build
CI=1 pnpm build:mobile
# Dari apps/api; pastikan host/akun hanya untuk DB test lokal.
APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=pleural_pr16_test composer test
composer validate --strict
composer lint
APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=pleural_pr16_stage_b_integration_test \
  TELENAPAS_STAGE_B_UAT=1 BCRYPT_ROUNDS=4 php tests/Integration/stage_b_workflow.php
```

Nama schema contoh adalah schema lokal baru saat run ini;jangan menganggap nama
saja cukup untuk isolasi. Verifikasi koneksi/host,test guards dan keadaan kosong
sebelum eksekusi. Tidak ada perintah publish/seeding kandidat JSON.

## UAT peneliti sebelum paket final

1. Buka register dan matriks;pastikan proposal benar-benar tersedia,versi/approval
   yang dimaksud teridentifikasi. Jangan menafsirkan panduan sebagai P.
2. Jawab D16-01–07/09 dengan sumber;review peritem/perpilihan/perrequired termasuk
   konflik Q2 dan keluarga Q4. Tetap menunggu bila satu keputusan belum lengkap.
3. Buka kandidat pada review PR15 di lingkungan test:harus menolak nullversion,
   nullrequired,opsiQ2kosong,latepolicynull. Tidak ada POST/row otomatis.
4. Hanya setelah lengkap,buat paket versi baru dengan reference/time aktual dan
   arsip evidence. Validasi kontrak/budget tanpa mengubah validator atau clinical
   assumptions. Klik publikasi eksplisit hanya setelah peneliti menyetujuinya.
5. Pada schema sintetis:future→unavailable,effective→available,required/options/VAS
   divalidasi;submit→exactUUIDhistory/replay→staff historical labels/provenance;
   expiry menolak entry baru tetapi receipt lama tetap terbaca.
6. Pastikan perubahan versi tidak memutasi entry/options lama;quarantine tidak
   melakukan POST otomatis setelah reconciliationGETgagal;draftusang hanya dibuang
   setelah konfirmasi,tidak diretimestamp;logout/revision membatasi akses local.
7. Lakukan checklist native di android-ux.md dengan fixture test,bukan data nyata.
   Tidak ada aktivasi serverUAT/deployment/merge dalam PR16.

Status proposal/paket final tetap terpisah dari tes rekayasa. Test fixture yang publishable bukan bukti kandidat klinis sah.

## Hasil aktual — 9 November 2026

| Pemeriksaan | Status / bukti |
|---|---|
| Sintaks JSON kandidat | PASS;JSON.parse dalam tes artefak,top-level definition/evidence |
| Kandidat ditolak sebagai publishable | PASS;3tes baru mencakup penolakan berlapis,source mapping,hashPDF;validator existing tidak diubah |
| Dashboard Vitest | PASS;84tes/6files |
| Android Jest | PASS;158tes/8suites,termasuk session/offline quarantine/draft/assessment |
| Laravel/MySQL8.4 | PASS;244tes/2150assertions;4tes lifecycle opt-in SKIPPED,tak dijalankan ulang karena tidak ada perubahan migration dan tugas melarang rollback |
| Stage B synthetic integration | PASS;publikasi eksplisit test-only→patient login/revision→ingestion→exactUUIDreconciliation/replay→scoped dashboard filters/historical labels→expiry→logout;one entry/audit,zero clinicaleffects |
| Histori versi/provenance | PASS;existing DailyAssessmentTest linked-version labels,immutable history,replay/version rejection dan InstrumentPublicationTest provenance/expiry lulus dalam suite lengkap |
| pnpm lint/typecheck | PASS;referensi tipe Node hanya pada tes artefak;tidak ada dependency/lockfile/runtime change |
| OpenAPI validation | PASS;kontrak dan komponen publication existing tidak diubah |
| Dashboard build / Expo Android JS export | PASS;bukan native APK |
| Composer strict/Pint | PASS;106PHPfiles |
| Review dokumen G/H/S/kamus/C | PASS untuk dokumen tersedia;G15halaman,H8halaman dibaca dan render diperiksa;hash dicatat |
| Review proposal P lengkap | NOT VERIFIED;file proposal tidak tersedia,dua lampiran aktual adalah panduan |
| Paket instrumen final / persetujuan final | NOT VERIFIED;null/opsi kosong sengaja dipertahankan,DO NOT PUBLISH |
| Native APK/device,TalkBack/scaling aktual | NOT VERIFIED;hanya review kode dan screenshot sintetis historis PR11/13,bukan capture native baru |
| Hosted GitHub Actions | NOT VERIFIED pada penyiapan laporan;hasil lokal tidak diklaim sebagai hostedCI |
| Aktivasi/publikasi di serverUAT | NOT PERFORMED;dilarang dalam tugas ini |

Kesimpulan teknis:regresi yang relevan lulus tanpa mengganti provenance/riwayat
versi lama. Kesimpulan penelitian:belum siap publikasi;selesaikan decisions.md dan
lengkapi proposal asli. Tidak ada hasil tes sintetis yang dianggap validasi klinis.
