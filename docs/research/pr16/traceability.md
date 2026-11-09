# Matriks keterlacakan — proposal/panduan versus PR11–15

## Register sumber dan cakupan pembacaan

| ID | Dokumen yang tersedia | Identitas/batas bukti |
|---|---|---|
| P | Proposal penelitian asli yang disebut permintaan PR16 | **Tidak ditemukan**. Tidak memiliki hash/halaman/isi yang bisa diulas. Tidak menyebut panduan sederhana sebagai proposal. Perbandingan terhadap P masih BLOCKED. |
| H | [Panduan Sederhana IT](../../design/TeleNapas_Panduan_Sederhana_IT.pdf),21Okt2026 | Seluruh8halaman dibaca (teks dan render). SHA256 `18e0ebb3c2cd3aec185f7dd94c65a0203d9f463397c6a85517e8deb6432bedb2`. H hlm1 menyebut prototipe ringkas dan kebutuhan konfirmasi; hlm8 checklist tanpa bukti tanda tangan. |
| G | [Panduan Visual IT](../../design/TeleNapas_Panduan_Visual_IT.pdf),spesifikasi14Okt2026 | Seluruh15halaman dibandingkan melalui teks dan render; hash `0a564e84de7f45acf15d176de4db3167dda7d0f0047cd9d216219d3039516a1b`; lampiran PR16 identik byte dengan repository. G hlm5/6 detail F3. |
| S | SRS v1.0-draft3Nov2026 dan Data Dictionary lampiran sebelumnya | SHA256 `811cc0d5a97def3888354b58b5e173036074acb6b80953310139d10f388bf522`. A§1/3/4/6, B entri_harian/jawaban_gejala/pengaturan, §13/14. §13 kolom persetujuan kosong; §14 menyatakan protokol/final instrument belum dilampirkan. H kini tersedia; P tetap belum tersedia. |
| R | [requirements-v1.0](../requirements-v1.0.md) | Persetujuan VAS absolut yang disampaikan pengguna pada PR02; tidak mengesahkan delta/engine. |
| T | [Matriks PR15](../instrument-traceability-pr15.md) | Audit sebelumnya; H yang sebelumnya belum tersedia kini dibaca langsung. Ini addendum, bukan mengganti bukti historis. |
| C | main ad90358: InstrumentPublicationController, DailyInstrument, AssessmentController; OpenAPI; DailyAssessment/VasControl/offline; PR15 parser/editor | Kontrak rekayasa yang diuji, bukan sumber persetujuan klinis. |
| U | Permintaan PR15/PR16 | Pengguna menyetujui panduan sebagai dasar dan menyebut proposal telah disetujui; tidak memberikan isi P, mapping final, nomor referensi atau kalender. Tidak mengarang identitas penandatangan/etik. |

Halaman adalah nomor halaman PDF, bukan urutan paragraf hasil ekstraksi. Referensi
bibliografi H hlm8 bukan proposal, persetujuan instrumen TeleNapas atau validasi
klinis VAS sesak populasi ini; tidak ditelusuri untuk mengisi keputusan yang kosong.

## Item, pilihan, wajib/opsional, mapping

Q1–Q8 adalah ID audit usulan berdasarkan urutan G, bukan kode klinis final.
H hlm3 §4 berisi9komponen: VAS +8gejala, bukan9pertanyaan gejala.
S B jawaban_gejala mensyaratkan katalog disetujui tetapi tidak menetapkan katalog.

| ID | Label kandidat / pilihan sumber | Bukti G / H | Status required dan mapping / implementasi |
|---|---|---|---|
| VAS (terpisah) | 0=tidak sesak;100=sesak sangat berat;integer0–100mm;tanpa default | G hlm5 F3;H hlm3 §5;S A FR03/B vas_mm | VAS wajib dalam kontrak C, tidak menjadi single-choice `questions`. Android null sampai interaksi; API menolak bukan integer/di luar batas. Tidak menginfer klasifikasi. |
| Q1 | Sesak saat istirahat;Tidak/Ya | G hlm6 baris1;H hlm3 §4 item2 | required belum ditetapkan. Q1/TIDAK/YA hanya mapping teknis usulan; kontrak single-choice cocok secara bentuk. |
| Q2 | Nyeri dada;gambar Ringan/Hebat versus catatan Tidak/Ringan-sedang/Hebat (usulan/perlu konfirmasi) | G hlm6 baris2 dan catatan;H hlm3 item3 hanya nama | **KONFLIK belum selesai**. H tidak memilih versi jawaban. required null/options kosong; tidak menetapkan mapping. |
| Q3 | Bibir/ujung jari kebiruan;Tidak/Ya | G hlm6 baris3;H hlm3 item4 “Bibir atau ujung jari kebiruan” | Bedanya “/” versus “atau” dicatat; tidak mendefinisikan pengukuran. required/mapping belum disahkan. |
| Q4 | Penurunan kesadaran, bingung (keluarga);Tidak/Ya | G hlm6 baris4/catatan khusus keluarga;H hlm3 item5 “Penurunan kesadaran atau bingung”;pengisi global pasien/keluarga | **Kesenjangan semantik**: apakah keluarga wajib untuk item ini, skip saat pasien, atau aturan lain? C hanya satu filled_by global dan boolean required, tidak punya conditional author. Jangan membuang syarat G karena H meringkas. required null. |
| Q5 | Batuk darah;Tidak/Ya | G hlm6 baris5;H hlm3 item6 | required/mapping belum final; tidak memicu alert. |
| Q6 | Demam;Tidak/Ya | G hlm6 baris6;H hlm3 item7 | Tidak ada ambang suhu/metode ukur di form. required/mapping belum final; jangan menambahkan angka. |
| Q7 | Batuk bertambah;Tidak/Ya | G hlm6 baris7;H hlm3 item8 | Pembanding/periode tidak dinyatakan. required/mapping belum final; jangan mengartikan “sejak kemarin” sendiri. |
| Q8 | Bekas tusukan nyeri/rembes/bengkak;Tidak/Ya | G hlm6 baris8;H hlm3 item9 “Bekas tusukan nyeri, rembes, atau bengkak” | Satu item gabungan dalam G. Arti jawaban/penetapan OR dan pemisahan item perlu peneliti;required/mapping belum final. Tidak memecah menjadi3item otomatis. |

H tidak menyediakan pilihan jawaban masing-masing gejala, urutan opsi, required,
skor atau kode mesin. Binary choices berasal G, bukan diasumsikan dari H. Setiap
opsi dalam kandidat mencantumkan sumber G dan status mapping teknis usulan.

## Jadwal, aturan pengisian, provenance dan perbedaan

| Aspek | Bukti dokumen | Kontrak implementasi / status keputusan |
|---|---|---|
| Hari0 pulang, pengamatan1–30 | H hlm2 §3/hlm7 §9;G hlm3/5;S A§1/4/B responden | C hari dari tanggal_pulang WIB,server otoritatif;kelompok intervensi saja mendapat akun;kontrol tetap dikelola peneliti. Tidak ada perubahan enrollment/baseline. |
| Satu HARIAN per tanggal WIB, terkunci | H hlm2 Aturan tanggal;S B entri_harian | C unique participant/date,immutable triggers,serverUTC,tampilanAsia/Jakarta;tidak mengedit riwayat. |
| Pengisi pasien/keluarga | H hlm3 akhir §4;G hlm6;S FR03/B | C filled_by wajib PASIEN/KELUARGA untuk seluruh formulir;tidak per-item. Q4 tetap blocker. |
| Offline, tanggal penerimaan | H hlm2 §3 dan hlm7 §9;G hlm13;S FR04/D05 | H “dikirim saat internet tersedia” tidak mengesahkan keterlambatan lintas tanggal. C same_day_only,quarantine jika date/version/status berubah;tidak menggeser tanggal/UUID/payload. Keputusan late_policy belum ada. |
| UUID/device time | H hlm7 §9;G hlm5/13;S B entri_harian | S/G menyebut lokal/klien;C status server menyediakan UUID lalu antrean mempertahankan original. Recovery exact UUID+same owner+fingerprint;perbedaan teknis dicatat,tidak mengganti generator. |
| Kelengkapan/pilihan | H hlm2 server memeriksa kelengkapan;G hlm6;S B jawaban_gejala | Tidak menetapkan item mana required. C boolean per-item,single-choice dengan generated option_id dan nilai katalogimmutable;tidak menerima free-text,skor,conditional logic. |
| Jenis ULANG | H hlm3 §5/hlm4 contoh4jam;S A§1/B tipe | Schema menyimpan HARIAN/ULANG;endpoint daily C hanya HARIAN. Tidak membuka ULANG atau jadwal4jam dalam PR16. |
| Form akhir | H hlm2/3/5/6 hari30;G hlm8 penutupan hari31;S A§4 batas perlu diputuskan | Konflik hari30 versus akses hari31/batas akhir belum final. Bukan daily questions. Tidak menciptakan outcome/Health-ITUES items/scoring dari ringkasan. |
| Calendar effect/expiry, versi, approval | H hlm1/8 meminta konfirmasi;S§13 kosong;P tidak tersedia | Tidak ada versi final,approvalref atau tanggal aktual. C publication butuh semuanya,server aktor/waktu,expected_current_version,konfirmasi eksplisit;tidak mengisi pengganti. |
| VAS warna/delta/SLA | H hlm4/7;G hlm9/10/14;S D01/02/BR01–06 | R mengesahkan hanya band absolut0–39/40–69/70–100 inklusif sebagai requirement, bukan aktivasi engine. Delta,gejala prioritas,2hari kosong,4jam ulang,30menit/2jam respons tidak diaktifkan. |
| Riwayat | G hlm7 F9;H hlm3 F9;S FR07/B versi | C staff historical labels dari versi entry-linked;patient riwayat kode/value,tanggal,VAS,versi;tidak mengganti label dengan latest atau menghitung kepatuhan. |
| Akses/audit | H hlm1 §2/hlm7–8 §9;S A§2/6 | Existing patient-only scope,denial kombinasi assessor/admin,pemisahan CONTACT/CLINICAL_READ,audit redacted,CAS/immutable tetap berlaku. Reviewer tidak diberi bypass. |

## Validasi teknis (bukan persetujuan klinis)

C membatasi version1–40,question.code1–48 uppercase,option.value1–32,
label300/150,1–50questions,1–20options;kode/value unik,boolean required;
WIB first/last1–30 berurutan,same_day_only;future effective,expiry>effective.
PR15 memerlukan evidence peritem/perrequired/perpilihan dan budget antrean Android
16KiB yang konservatif. Teks sumber/centang tidak diautentikasi otomatis.

Kandidat mempertahankan null/opsi kosong untuk keputusan hilang dan harus ditolak
validator existing. Tidak mengubah kontrak supaya draf lulus. Paket final baru
boleh dibuat setelah [keputusan](decisions.md) lengkap; proposal tetap prasyarat
bukti untuk menyatakan review Tahap A selesai.
