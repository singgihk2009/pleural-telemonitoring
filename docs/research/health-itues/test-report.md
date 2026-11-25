# Laporan uji fondasi Health-ITUES — 25 November 2026 WIB

Baseline `main` `38a7d80`. Semua tes penulisan dijalankan pada MySQL 8.4
disposable dengan data sintetis. Tidak ada koneksi ke `telenapas_demo`.

| Pemeriksaan | Hasil |
|---|---|
| Laravel feature/unit, Composer validate, dan Pint | PASS — 247 tes, 2.220 assertion. Empat tes opt-in existing dilewati oleh suite reguler. |
| Health-ITUES API dan database | PASS — hash katalog, RBAC dan audit preview peneliti, status pasien tertutup, isolasi preview, UUID unik, versi wajib terdaftar, serta trigger immutable. |
| Dashboard | PASS — 88 tes. Katalog 20 item tampil dalam urutan sumber; akses ditolak tanpa menampilkan item. |
| Android Jest | PASS — 159 tes. Status akhir terbuka sebagai informasi tanpa aturan hari lokal; preview sintetis terbaca dan pengiriman tetap nonaktif. |
| Lint, typecheck, OpenAPI, build dashboard, Android export | PASS. |
| Review empat screenshot asli, APK dan perangkat fisik | NOT VERIFIED — sumber yang diterima adalah DOCX; tidak ada APK atau perangkat dalam run ini. |
| Skala, pengiriman respons, skoring dan ekspor jawaban | Belum diaktifkan, menunggu keputusan pada [register](readiness.md). |

Build Android menghasilkan JavaScript dan aset. Tidak ada publikasi instrumen,
aktivasi untuk penelitian, atau deployment.
