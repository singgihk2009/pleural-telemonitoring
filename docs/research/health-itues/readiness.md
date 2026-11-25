# Health-ITUES — register keputusan dan UAT

## Keputusan

| ID | Status | Keputusan atau bukti yang dibutuhkan |
|---|---|---|
| HITU-01 | Substansi dikonfirmasi | RDR-04 bagian 1–2, 20 pernyataan dalam empat subskala. Empat screenshot dirujuk; file asli belum diterima untuk pencocokan. Persetujuan disampaikan oleh developer. |
| HITU-02 | Terbuka | Target responden dan pihak yang boleh mengisi. S01, S03, S04 dan S13 menyebut pekerjaan atau pemberian layanan. Konfirmasi juga apakah `[Sistem/Aplikasi]` tetap literal. |
| HITU-03 | Terbuka | Label pilihan, kode mesin, urutan, wajib/opsional, nilai kosong dan apakah pengisian parsial diterima. |
| HITU-04 | Terbuka | Rumus total/subskala, item terbalik bila ada, aturan missing, interpretasi dan referensi validasi yang berlaku. |
| HITU-05 | Terbuka | Jendela buka/tutup, zona waktu, kelayakan, pengiriman ulang/koreksi, identitas versi publikasi dan referensi persetujuan tertulis. |
| HITU-06 | Terbuka | Otorisasi penyimpanan dan ekspor respons, relasi identitas berdasarkan target, pseudonim, variabel ekspor, penerima, retensi dan audit. |

Bukti isi tidak menetapkan validasi ilmiah independen. Outcome klinis,
readmission dan Daily Assessment mengikuti keputusan dan kontrak masing-masing.

## Fondasi dan batas operasi

API peneliti menampilkan katalog baca-saja dengan versi, identitas HITU-01
dan checksum DOCX. Akses melalui Gate peneliti existing dan menghasilkan audit
berkode tetap, tanpa konten respons. Android menampilkan status pengisian
tertutup tanpa aturan hari lokal. Pratinjau di Android hanya dikirim dalam
`APP_ENV=uat`, flag synthetic UAT aktif, dan schema MySQL
`telenapas_synthetic_uat_test`; akun pasien sintetis dipakai untuk uji UI.

Migrasi aditif menyediakan registry versi immutable dan penyimpanan respons
immutable dengan UUID unik, foreign key versi, dan JSON object jawaban mentah.
Migrasi tidak mengisi tabel. Version registry dan response store belum dihubungkan
ke pengiriman operasional. Pemilihan relasi responden ditunda agar schema tidak
mengunci kelompok, aktor, atau denominator yang belum disepakati. Tabel historis
`usability` memiliki asumsi kelompok dan pengisi yang tidak diterapkan di modul ini.

Setelah keputusan disahkan, kontrak pengiriman harus memvalidasi identitas
responden, jendela/versi, kode dan jenis nilai terhadap definisi immutable.
Gunakan UUID yang sama untuk retry payload identik, tolak perubahan payload
pada UUID itu, dan tulis respons serta audit dalam satu transaksi. Aturan satu
respons per orang atau koreksi menunggu HITU-05. Untuk ekspor, kontrak awal
memisahkan metadata versi dari jawaban mentah per kode; pseudonim, kolom kosong,
skor, dan akses pengekspor menunggu HITU-03/04/06. Endpoint kirim dan ekspor
jawaban belum dibuka.

## UAT sintetis

| Skenario | Harapan |
|---|---|
| Review peneliti | Login staf sintetis dengan role peneliti, buka Health-ITUES, cocokkan S01–S20 dan empat kelompok dengan DOCX. Audit review tercatat. |
| RBAC | Pasien, IT dan kombinasi peneliti+IT tidak membuka endpoint review. Status pasien biasa tidak berisi preview. |
| Android UAT terisolasi | Login peserta sintetis. Buka informasi formulir akhir pada hari mana pun. Cocokkan susunan, wording literal, ukuran font besar dan pembaca layar. Tombol pengiriman nonaktif. |
| Penyimpanan teknis | Pada schema disposable, uji FK versi, idempotency key dan trigger immutable memakai payload sintetis. Migrasi tidak mengisi registry atau respons. |
| UAT lanjutan setelah keputusan | Uji pilihan dan missing, kelayakan dan jadwal, receipt/replay, audit transaksi, skoring manual dan ekspor sesuai RBAC. |

Gunakan [runbook UAT](../../uat/PR-17.md) untuk isolasi database dan peserta
sintetis. UAT Health-ITUES tidak memerlukan publikasi Daily Assessment.
