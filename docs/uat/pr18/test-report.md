# Laporan pengujian PR-18 — 24 November 2026 WIB

Baseline `main` `38a7d80`. Pengujian Laravel menggunakan MySQL 8.4 pada
container disposable terpisah, dengan schema baru dan fixture sintetis.
`telenapas_demo` tidak dihubungi. Loader tidak mengaktifkan instrumen apa pun.

| Pemeriksaan | Hasil aktual |
|---|---|
| Feature dan unit Laravel, termasuk publikasi, RBAC, audit, provenance, idempotensi, dan label historis pasien | PASS — 245 lulus, 2.195 assertion. Empat tes migrasi opt-in dilewati dalam suite reguler. |
| Empat tes migrasi opt-in pada schema disposable terpisah | PASS — keempatnya dijalankan dan lulus. |
| Konkurensi kredensial/PIN dan pengiriman Daily Assessment | PASS — kedua harness penulis MySQL lulus. |
| Dashboard Vitest | PASS — 88 lulus pada run final. Form tanpa JSON, urutan, pratinjau, konfirmasi, receipt, history, draft PR #16, dan visibilitas PIN tercakup. |
| Android Jest dan offline queue | PASS — 159 lulus. VAS eksplisit dan kontrol presisi, pilihan wajib/opsional, review, history, restore, retry, dan rekonsiliasi tercakup. |
| Lint, typecheck, OpenAPI, Composer validate, dan Pint | PASS. |
| Build dashboard melalui Vite dengan URL UAT | PASS. |
| Konfigurasi Expo dan Android export untuk synthetic UAT | PASS — package dan scheme sesuai, konfigurasi menolak URL APK lama, dan ekspor menghasilkan bundle Android serta assets. |
| Template Compose MySQL UAT | PASS — `docker compose config --quiet` dengan input dummy. |
| APK bertanda tangan, perangkat fisik, TalkBack, dan UAT bersama peneliti | NOT VERIFIED — tidak ada build EAS, emulator/perangkat, atau deployment. |

Dalam satu run paralel, tes Android Access menemukan bahwa PIN yang baru
diterbitkan dapat dirender sebelum listener tab tersembunyi terpasang. Guard
kini dipasang sebelum browser menampilkan PIN, dan respons pada tab tersembunyi
menunggu verifikasi ulang saat tab kembali aktif. Run final workspace lulus
dengan regresi tambahan untuk penerbitan saat tab tersembunyi.

Riwayat pasien menampilkan label dari versi yang terhubung ke entri; hasil
receipt dan payload pengiriman tetap memakai kontrak existing. Kandidat
`DO-NOT-PUBLISH` dan pengaman synthetic UAT tetap identik dengan baseline.
