# Evaluasi UI/UX Android dan usulan editor instrumen

## Metode dan batas bukti

Review kode main ad90358, panduan G15halaman dan H8halaman, serta inspeksi visual
screenshot sintetis yang sudah tersimpan. Tidak ada perubahan runtime mobile pada
PR16. Tidak mengklaim screenshot lama sebagai capture baru, tidak menjalankan APK
atau menguji TalkBack/physical scaling baru. Jest/export berbeda dari native UAT.
Proposal P belum tersedia; wording final klinis belum dapat divalidasi.

Screenshot historis PR11 yang diperiksa kembali:
[VAS tanpa nilai](../../assessment/screenshots/02-vas-no-default.png),
[pilihan dan pengisi sintetis](../../assessment/screenshots/03-synthetic-questions.png).
Keduanya emulator API30/Expo Go58,640×1280,density320 menurut README asal;
bukan instrumen penelitian final. Bukti draft/quarantine PR13 dapat ditelusuri di
[README native PR13](../../stage-b/screenshots/pr13-fix/README.md); tidak diklaim
sebagai pengulangan pada PR16. Screenshot review PR15 memakai mock dan tidak
membuktikan aktivasi database: [penjelasan](../../uat/PR-15.md).

## Kesesuaian alur dan perbedaan disengaja

| Area | Referensi | Hasil review implementasi |
|---|---|---|
| Login dan orientasi | G hlm4/5 F1/F2;H hlm2 §4 | Existing kode+PIN,patient-only session,SecureStore dan acknowledgement tidak disentuh. Persistensi versi acknowledgement server masih scope terpisah;tidak mengklaim checkbox sebagai consent penelitian tertulis. |
| Beranda/hari studi | G hlm5;H hlm2 | Existing status API,serverWIB dan hari studi;tidak meramalkan kelayakan dari jam perangkat atau membuka kontrol. |
| F3 VAS | G hlm5;H hlm3 §5 | VasControl null tanpa thumb/value awal,integer0–100,tap/drag dan adjustable actions. Lanjut disabled sampai dipilih;VAS terpisah dari question catalog. Caption “sangat sesak” cocok G hlm5;H hlm3 juga memakai “sesak sangat berat”. Peneliti perlu memastikan wording final konsisten. |
| F3 gejala/pengisi | G hlm6;H hlm3 §4 | DailyAssessment merender label/opsi dari server,tidak mengandung8item hard-coded. VAS→gejala→pengisi→konfirmasi. Wajib ditandai *,opsional ditulis;filled_by global. Tidak mendukung keluarga per-item;blocker Q4 tetap jelas. |
| F3 konfirmasi/kirim | G hlm6 Kirim;H hlm2 terkunci setelah kirim | Tambahan langkah review memperlihatkan tanggal,VAS,pengisi,seluruh jawaban;locked body/UUID setelah antre. Antrean terenkripsi menunjukkan belumserver sampai receipt exact. Tidak menafsirkan jawaban. |
| F4 feedback | G hlm6;H hlm2/4 | Warna/keputusan klinis sengaja tidak aktif;diganti neutral receipt yang menyatakan bukan penilaian klinis. Tidak mengganti dengan warna berdasarkan nilai lokal. |
| Offline/draft | G hlm13;H hlm7 §9 | Antrean terenkripsi existing,credential revision,foreground auth,manual read-only quarantine. Draft usang memiliki disposal confirmed bertanggal;tidak me-retimestamp body lama. Kiriman potentially submitted mendapat warning lebih kuat. |
| Bantuan | G hlm4–8;H hlm3 | Frame HelpBar mempertahankan Hubungi perawat dan Darurat119 di bawah scroll. Nomor perawat/backend belum tersedia tetap ditandai;tidak membuka pesan WhatsApp/FCM. Jangan menelepon119 dalam pengujian. |
| F9 riwayat | G hlm7;H hlm3 | Daftar immutable observasi/date/VAS/version/author dan code/value,berhalaman;belum grafik/kalender seperti wireframe. Histori patient belum memuat label historis seperti staff;usulan UX terpisah,tidak menebak label versi sekarang. |
| F5/F7/F8/F10 | G hlm7/8;H hlm2/3/5/6 | Existing layar/placeholder edukasi,komunikasi,kejadian dan akhir dipertahankan. Tidak membuat katalog edukasi klinis,Health-ITUES,outcome atau submissions baru dari ringkasan. |
| F6 VAS ulang | G peta layar hlm4/hlm5/hlm10 | Pengisian ULANG oleh pasien yang dijadwalkan perawat,terpisah dari HARIAN. Existing daily endpoint tidak membukanya;tidak mengaktifkan jadwal4jam/klasifikasi sama atau menyisipkan pertanyaan ulang ke daily catalog. |

Visual berbeda dari wireframe: kartu bertumpuk dan opsi tombol besar menggantikan
baris tabel kecil;konten panjang scroll,langkah review tambahan,receipt netral,
riwayat berbentuk daftar. Tidak ada klaim pixel-perfect. Definisi final berlabel
panjang tetap perlu UAT native sebelum aktivasi. Delapan kandidat bukan fixture
aktif dan tidak digunakan untuk menghasilkan screenshot pasien baru.

## Aksesibilitas

Dari kode: theme.copy16sp,lineHeight24,buttonLabel16sp,minHeight52dp,
back48×48dp,VasControl touch56dp,Copy allowFontScaling,help wrap/minWidth140,
ScrollView dan adjustable accessibilityActions serta label Belum dipilih.
Ini bukti konfigurasi/test komponen, bukan pengukuran seluruh layar pada perangkat.

UAT perangkat berikutnya (NOT VERIFIED pada PR16): font scaling100/130/200%,
320/360dp portrait/landscape,TalkBack urutan dan perubahan VAS,opsi panjang,
keyboard tidak menutup tombol,help tetap bisa dijangkau,kontras/fokus/target48dp.
Uji dengan fixture sintetis,tidak memanggil119 sungguhan,tidak publikasi kandidat.

## Usulan PR UI terpisah — editor terstruktur tanpa JSON manual

Tidak diimplementasikan di PR16. Pertahankan parser/projection PR15 dan endpoint
publication yang sama;tidak membuat mekanisme publikasi kedua.

1. Halaman sumber:register dokumen/hash/versi,halaman/bagian,pilihan status bukti;
   tampilkan yang belum lengkap. Jangan menandai “approved” hanya karena file masuk.
2. Editor daftar pertanyaan:label,urutan,kode teknis,radio wajib/opsional dengan
   keadaan awal “Belum diputuskan”,tabel pilihan/value dan sitasi tiap baris.
   Sediakan panel konflik sumber,Q4 family condition dan jadwal yang unsupported.
3. Panel VAS terpisah read-only sesuai kontrak;panel WIB day-range,late policy yang
   benar-benar didukung,versi,reference,effective/expiry tanpa preset klinis.
4. Preview Android dan diff terhadap immutable version lama. Label lama tidak
   diganti;tidak cloning option IDs menjadi kode atau memilih jawaban otomatis.
5. Export paket definition/evidence untuk arsip eksternal;perubahan draft tidak
   pernah POST. Draft sensitif tetap RAM kecuali ada scope penyimpanan terpisah.
6. Review sumber lengkap,explicit checkbox dan final publish;expected_current_version
   difresh dan diperiksa server. Pertahankan RBAC,401/403,CAS409,422,rate limits,
   uncertainty setelah timeout/logout dan receipt verification existing.

Acceptance PR lanjutan:keyboard/screenreader,form invalid/konflik tetap menunggu,
JSON round-trip identik tanpa field scoring,tidak ada autoPOST,negative role tests,
concurrent reviewers/lostreceipt,no browserstorage plaintext,dan packet budget
Android. Penyimpanan evidence server/digitalapproval atau perubahan conditional
instrument membutuhkan desain kontrak terpisah;usulan UI tidak mengesahkannya.
