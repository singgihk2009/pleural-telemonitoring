# PR-18 — Kesenjangan desain dan implementasi

Basis audit `main` pada `38a7d80`. Sumber pembanding adalah [Panduan Visual](../../design/TeleNapas_Panduan_Visual_IT.pdf), [Panduan Sederhana](../../design/TeleNapas_Panduan_Sederhana_IT.pdf), [spesifikasi penelitian](../../research/requirements-v1.0.md), [Data Dictionary](../../database/data-dictionary-v1.0.md), [OpenAPI](../../api/openapi.yaml), serta [review PR #16](../../research/pr16/README.md), [PR #15](../PR-15.md), dan [PR #17](../PR-17.md). Proposal asli dan lampiran instrumen final tidak tersedia di checkout. Persetujuan desain dicatat sebagai dasar UI; keputusan instrumen tetap mengikuti [checklist peneliti](../../research/pr16/decisions.md).

**FUNCTIONAL** berarti jalur utama terhubung dan dapat diuji. **PARTIAL** berarti sebagian desain bekerja. **PLACEHOLDER** berarti ada layar dengan tindakan nonaktif. **BLOCKED** berarti pekerjaan menunggu keputusan, sumber, atau prasyarat eksternal. **NOT IMPLEMENTED** berarti belum ada alur operasional.

## Android, F1–F10

| Fitur dari panduan | Baseline | PR-18 | Temuan |
|---|---|---|---|
| F1 login kode/PIN, pemulihan sesi, logout | FUNCTIONAL | FUNCTIONAL | Sanctum dan kredensial Android; pengujian perangkat PR-18 masih diperlukan. |
| F2 pernyataan awal dan konfirmasi baca | PARTIAL | PARTIAL | Aplikasi menyimpan status lokal. Receipt baca yang dapat diaudit server belum tersedia. |
| Beranda, navigasi, hari penelitian dan penutupan | PARTIAL | PARTIAL | Navigasi dan hari profil tersedia; kelayakan F3 tetap divalidasi server. Penutupan dan akses F10 memerlukan keputusan. |
| F3 VAS 0–100 mm, tanpa nilai awal | FUNCTIONAL | FUNCTIONAL | Slider dengan aksesibilitas adjustable; kontrol presisi per 1 mm setelah nilai pertama dipilih. |
| F3 daftar dari API, pilihan, wajib/opsional dan pengisi | FUNCTIONAL | FUNCTIONAL | Radio dengan status terpilih, pengosongan pilihan opsional, dan urutan dari versi aktif. Kelayakan pengisian oleh keluarga berasal dari keputusan yang masih terbuka. |
| F3 review, konfirmasi, receipt dan satu entri per WIB | FUNCTIONAL | FUNCTIONAL | Langkah diberi konteks, VAS dan pengisi terlihat pada review, pengiriman tetap eksplisit dan idempoten. |
| F3 draf dan antrean terenkripsi, sinkron, rekonsiliasi | FUNCTIONAL | FUNCTIONAL | Jalur existing dengan status lokal, retry dan receipt, serta karantina perubahan versi/tanggal. Validasi perangkat masih diperlukan. |
| F3 instrumen penelitian aktual | BLOCKED | BLOCKED | Paket sintetis tersedia di UAT terisolasi. Q1–Q8 belum memiliki keputusan publikasi lengkap. |
| F4 umpan balik hijau/kuning/merah dan anjuran | PLACEHOLDER | BLOCKED | Layar informasi tersedia. Perlu SOP, aturan versi, endpoint dan uji klinis. |
| F5 kontak dan darurat | PARTIAL | PARTIAL | Dialer 119 memerlukan konfirmasi. Nomor perawat dari server dan pesan dua arah masih placeholder. |
| F6 VAS ulang dan jadwal perawat | PLACEHOLDER | BLOCKED | Perlu kontrak jadwal, otorisasi dan SOP tindak lanjut. |
| F7 materi edukasi offline | PLACEHOLDER | BLOCKED | Empat kategori tersedia; konten ditinjau, versi, dan cache media belum ada. |
| F8 laporan IGD/rawat/tindakan ulang | PLACEHOLDER | PLACEHOLDER | UI nonaktif; belum ada submit pasien atau verifikasi peneliti. |
| F9 riwayat server dan label historis | PARTIAL | PARTIAL | Pagination dan receipt berfungsi. PR-18 memakai label immutable yang terhubung ke entri. Grafik 30 hari, garis baseline dan kalender lengkap masih terbuka. |
| F10 outcome akhir dan Health-ITUES | PLACEHOLDER | BLOCKED | Butuh versi Indonesia, jadwal, alur pembutaan, aturan input dan skoring yang disahkan. |
| Notifikasi, reminder dan pesan layar kunci | NOT IMPLEMENTED | BLOCKED | Perlu SOP, persetujuan, kebijakan privasi dan kontrak pengiriman. |

## Dashboard, data dan operasi

| Fitur dari panduan | Baseline | PR-18 | Temuan |
|---|---|---|---|
| Login staf, RBAC, pembutaan dan audit | FUNCTIONAL | FUNCTIONAL | Peneliti, perawat, penilai dan IT terikat scope server; kesiapan UI lintas semua peran belum lengkap. |
| Enrollment, kelompok, consent, baseline, status dan akses PIN | FUNCTIONAL | FUNCTIONAL | Form dan workflow PIN tersedia dengan kontrol server dan riwayat immutable. |
| Peserta, pencarian, ringkasan dan riwayat asesmen | FUNCTIONAL | FUNCTIONAL | Filter WIB dan tipe, linked label serta akses audit. |
| Review instrumen, sumber, urutan dan jadwal | PARTIAL | FUNCTIONAL | Form, opsi, keputusan eksplisit, sumber, validasi, pratinjau dan impor paket opsional. |
| Publikasi, receipt, riwayat versi, provenance | FUNCTIONAL | FUNCTIONAL | Endpoint existing, konfirmasi, recheck versi, kondisi ketidakpastian, audit dan immutable. |
| Akses IT berbasis grant dan minimisasi data | PARTIAL | PARTIAL | Enforcement API ada. Workflow operasional lengkap lintas seluruh peran belum tersedia. |
| Dashboard perawat, antrean prioritas, waktu respons, tindak lanjut | PARTIAL | PARTIAL | Pembacaan data yang diberi izin tersedia lewat API; tindakan dan triase operasional menunggu SOP. |
| Dashboard penilai, outcome dan verifikasi kekambuhan | PARTIAL | PARTIAL | Batas akses dan sebagian pembacaan ada. Form outcome dan alur rekam medis masih diperlukan. |
| Dashboard peneliti, kepatuhan, ekspor dan laporan | PARTIAL | PARTIAL | Enrollment dan riwayat berfungsi; garis baseline/agregat kepatuhan dan ekspor riset belum tersedia. |
| Pengaturan ambang, jam pemantauan dan reminder | NOT IMPLEMENTED | BLOCKED | Data Dictionary mendeskripsikan model. Editor instrumen tidak mengaktifkan klasifikasi, reminder, FCM atau WhatsApp. |
| MySQL, migrasi, audit, status API | FUNCTIONAL | FUNCTIONAL | MySQL 8.4 dan kontrak API berjalan; PR-18 tidak menambah migrasi. |
| UAT terisolasi dan APK | PARTIAL | PARTIAL | Guard PR #17, template DB dan profil APK terpisah siap ditinjau. DNS, TLS, deployment, penandatanganan dan pengujian perangkat masih harus dijalankan. |
| Integrasi eksternal | NOT IMPLEMENTED | NOT IMPLEMENTED | SIMRS/SATUSEHAT di luar cakupan panduan. |

## Roadmap

1. Review checklist D16 dengan peneliti dan arsipkan proposal, pemetaan Q1–Q8, kebijakan waktu, dan referensi nyata. Buat versi kandidat baru setelah seluruh keputusan terkait Daily Assessment lengkap.
2. Administrator menyiapkan UAT privat sesuai [runbook](deployment.md). Jalankan matriks PR #17 dan verifikasi TalkBack, ukuran teks besar, VAS 0/100, restart offline, reconnect, riwayat dan audit dari APK synthetic.
3. Selesaikan kalender dan grafik F9 serta indikator kepatuhan tanpa skor klinis. Tentukan versi dan audit pernyataan awal F2.
4. Tinjau kontrak dan persetujuan untuk edukasi, kontak/pesan, F8 dan outcome. Implementasi F4/F6, klasifikasi serta notifikasi menunggu SOP yang disetujui.
