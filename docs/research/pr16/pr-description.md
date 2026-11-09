## PR-16: review instrumen dan kandidat yang belum boleh dipublikasikan

Base main ad90358 (PR15 sudah merge). Menambahkan matriks dokumen versus kontrak
PR11–15, daftar keputusan peneliti, kandidat definition/evidence, evaluasi Android,
dan usulan editor terstruktur untuk PR UI terpisah.

Lampiran aktual adalah panduan visual 15 halaman dan sederhana 8 halaman, bukan
proposal asli. Panduan sederhana kini diarsipkan beserta hash. Review proposal
lengkap belum terverifikasi. Kandidat mempertahankan null pada version, required
dan late_policy serta pilihan nyeri dada kosong. Tidak memilih konflik atau
membuat referensi persetujuan. Q1–Q8/TIDAK/YA hanya mapping teknis usulan.

Tiga regresi artefak membuktikan kandidat ditolak validator PR15 existing, evidence
cocok dengan kandidat, dan hash PDF sesuai sumber. Tidak ada perubahan runtime,
kontrak, migration, dependencies, .env atau konfigurasi server.

### Validasi

- PASS: dashboard 84 tes, Android/Jest 158, Laravel/MySQL 244 (2150 assertions).
- PASS: integrasi Stage B dari publikasi sintetis sampai asesmen, exact UUID,
  historical scoped labels, expiry dan logout.
- PASS: lint, TypeScript, OpenAPI, build dashboard, Expo Android JS export,
  Composer strict dan Pint.
- Empat lifecycle opt-in skipped; tidak menjalankan rollback migration.
- NOT VERIFIED: proposal asli, paket klinis final, native APK/device baru,
  dan hosted GitHub Actions.

Dokumentasi lengkap: docs/research/pr16/README.md dan validation.md.

Tidak mengakses telenapas_demo, memublikasikan kandidat, mengaktifkan klasifikasi
atau notifikasi, melakukan deployment atau merge. Menunggu review dan keputusan
peneliti.
