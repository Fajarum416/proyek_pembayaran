Saya punya sebuah repository Git lama yang berisi beberapa branch 
untuk dua sistem pembayaran berbeda (Jepang dan Korea). 
Saya ingin memisahkan keduanya menjadi 2 repository baru yang bersih.

---

## Kondisi Repository Lama Saya:

Branch yang ada saat ini:
- `master` → kode awal/legacy
- `Pengembangan-pembayaran-jepang` → development Jepang
- `Publis-Hosting-jepang` → production Jepang
- `Pengembangan-pembayaran-korea` → development Korea
- `Publish-Hosting-korea` → production Korea

URL repo lama:
[git@github.com:Fajarum416/proyek_pembayaran.git]

---

## Target Akhir yang Saya Inginkan:

### Repo Baru 1: `payment-japan`
URL: [git@github.com:Fajarum416/payment-japan.git]
Branch:
- `main` → isinya dari `Pengembangan-pembayaran-jepang`
- `develop` → sama dengan main sebagai starting point

### Repo Baru 2: `payment-korea`
URL: [git@github.com:Fajarum416/payment-korea.git]
Branch:
- `main` → isinya dari `Pengembangan-pembayaran-korea`
- `develop` → sama dengan main sebagai starting point

---

## Yang Saya Butuhkan:

1. Berikan perintah Git lengkap step-by-step di terminal untuk:
   - Migrasi kode Japan ke repo baru
   - Migrasi kode Korea ke repo baru
   - Membersihkan branch yang tidak relevan di masing-masing repo
   - Verifikasi hasil akhir

2. Jika ada potensi error di setiap step, 
   sebutkan error yang mungkin muncul dan cara mengatasinya.

3. Setelah selesai, jelaskan workflow Git harian 
   yang harus saya ikuti untuk kedua repo baru ini.