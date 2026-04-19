# Penjelasan Struktur Branch Proyek Pembayaran

Halo tim! 👋 (Khususnya untuk kamu yang baru bergabung / Junior Programmer),

Dokumen / *Issue* ini dibuat untuk memberikan ringkasan mengenai alur kerja repositori kita saat ini dan apa saja perbedaan utama antar *branch*, khususnya perbedaan riwayat pengembangan antara sistem pembayaran **Jepang** dan **Korea**. Boleh dibaca agar kamu tidak bingung ketika ingin melakukan update atau berpindah antar *branch* ya!

---

## 📌 Ringkasan Perbedaan Utama (Jepang vs Korea)
Hal yang paling penting untuk kamu ketahui tentang silsilah kode di repositori ini:
Branch pengembangan **Jepang** adalah pondasi dari sistem "baru" kita. Branch pengembangan **Korea** ternyata dibangun *berkelanjutan* (langsung berdasar dari *commit* terakhir) branch Jepang. Namun, untuk branch Korea kita memutuskan merombak total UX/UI, sistem struktur database, dan tampilan dashboard agar lebih spesifik untuk kebutuhan Korea.

---

## 🌿 Detail Karakteristik Masing-Masing Branch

### 1. 🇯🇵 `Pengembangan-pembayaran-jepang`
Ini merupakan branch murni tempat pengembangan fitur sistem pembayaran *flow* Jepang difokuskan.
**Fitur utama yang membedakan branch ini:**
- Penambahan sistem popup model baru untuk *insert* data pembayaran.
- Terdapat sistem kategori pembayaran yang spesifik (contoh: "Lunas Tahap 1", "Lunas Tahap 2").
- Memiliki fitur untuk menyesuaikan *setting* parameter batas nominal tertentu.
- Sistem filter yang diperbarui menjadi tampilan *dropdown* modern.
- Desain ulang perataan letak (kolom) untuk hasil pencetakan Export PDF & CSV.

### 2. 🚀 `Publis-Hosting-jepang`
Branch ini adalah versi *Production* (siap rilis) dari hasil kerja keras di branch `Pengembangan-pembayaran-jepang`.
**Fungsi utama branch ini:**
- Kode fitur utamanya 100% mengikuti (dan di *merge* dari) pengembangan jepang.
- Tedapat modifikasi minor pada script koneksi `db.php` untuk menyesuaikan kredensial supaya aplikasinya dapat berjalan normal ketika ditempatkan di *server hosting* (bukan *localhost* lagi).

### 3. 🇰🇷 `Pengembangan-pembayaran-korea`
Branch ini awalnya dibraket dari kode Jepang yang sudah matang, namun dimodifikasi sangat drastis untuk kebutuhan model pembayaran Korea.
**Fitur utama & perubahan di branch ini:**
- **Perombakan Keseluruhan:** Mengganti total UX/UI secara keseluruhan (termasuk visualisasi Navigasi Halaman dan Filtering) dan menyesuaikan arsitekturnya sampai pada tahap skema *database*.
- **Desain Ulang Dasbor:** *Layout* dasbor jauh berbeda dengan model Jepang.
- **Fleksibilitas Mengatur Tanggal Pembayaran:** Menambahkan fitur di mana tanggal pembayaran *record* bisa dimanipulasi dengan bebas (di branch Jepang atau versi lama, tanggal pembayaran biasanya otomatis dikunci ke tanggal input pada hari itu juga).
- Terdapat *file sql* migrasi independen untuk setup, yaitu `pembayaran_korea_db.sql`.

### 4. 🚀 `Publish-Hosting-korea`
Sama halnya dengan *hosting jepang*, branch ini digunakan sebagai versi rilis (production) khusus sistem alur Korea.
**Fungsi utama dari branch ini:**
- Memboyong pembaruan terbaru dari *development* Korea.
- Melakukan modifikasi spesifik *hosting* pada file frontend (`index.html`, `login.html`) dan juga kredensial integrasi databasenya (`db.php`).

### 5. 👑 `master`
Branch fondasi. Berisi versi awal (primitif) dari sistem pembayaran sebelum terjadinya pemisahan secara eksplisit ke Jepang atau Korea. Jika kamu mendapati eksperimen API seperti `get_data_api.php`, sistem login awal, hingga file test export CSV mentah, filenya bermuara di sini. Terdapat juga skrip sekuritas primitif seperti *JWT Secret Key* dan setup URL.

---

## 🚦 Panduan Singkat untuk Junior Programmer

1. **Memulai *Task* Baru:**
   - **Jika tugasmu menyangkut pembayaran Jepang:** Lakukan `git checkout Pengembangan-pembayaran-jepang` dan buatlah pekerjaanmu pada atau dari branch ini.
   - **Jika tugasmu menyangkut pembayaran Korea:** Lakukan `git checkout Pengembangan-pembayaran-korea`.
2. **Jangan *Commit* Terburu-buru ke *Hosting*:**
   Jangan pernah melakukan edit atau pengerjaan perombakan kode secara *direct* (langsung) di branch siap rilis seperti `Publis-Hosting-jepang` atau `Publish-Hosting-korea`. Kedua branch tersebut sebaiknya hanya digunakan untuk merilis final *merge* yang siap disebarkan ke publik dan digabungkan.

Semoga panduan di *issue* ini mempermudah gambaranmu mengenai di mana kamu harus mengambil langkah dan meletakkan kode. Tetap semangat merajut kode! 💻✨
