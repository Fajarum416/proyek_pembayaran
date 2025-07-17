// register-admin.js

// Skrip ini hanya untuk dijalankan sekali untuk membuat pengguna admin pertama.
// Jalankan dari terminal: node register-admin.js

const mysql = require('mysql2/promise');
const bcrypt = require('bcryptjs');
require('dotenv').config();

// --- UBAH DETAIL DI BAWAH INI ---
const newUsername = 'admin';
const newPassword = 'ymiid123';
// --------------------------------

async function createAdmin() {
    console.log('Menghubungkan ke database...');
    let connection;
    try {
        connection = await mysql.createConnection({
            host: process.env.DB_HOST,
            user: process.env.DB_USER,
            password: process.env.DB_PASSWORD,
            database: process.env.DB_NAME,
        });
        console.log('Koneksi berhasil.');

        console.log(`Mencari pengguna dengan username: ${newUsername}...`);
        const [users] = await connection.query('SELECT * FROM users WHERE username = ?', [newUsername]);

        if (users.length > 0) {
            console.log('Pengguna dengan username ini sudah ada. Proses dibatalkan.');
            return;
        }

        console.log('Username tersedia. Membuat hash password...');
        const salt = await bcrypt.genSalt(10);
        const hashedPassword = await bcrypt.hash(newPassword, salt);
        console.log('Hash berhasil dibuat.');

        console.log('Menyimpan pengguna baru ke database...');
        await connection.query(
            'INSERT INTO users (username, password) VALUES (?, ?)',
            [newUsername, hashedPassword]
        );

        console.log('===================================================');
        console.log('Pengguna admin berhasil dibuat!');
        console.log(`Username: ${newUsername}`);
        console.log(`Password: ${newPassword}`);
        console.log('===================================================');
        console.log('Anda sekarang bisa login menggunakan akun ini.');
        console.log('Disarankan untuk menghapus file register-admin.js ini setelah selesai.');

    } catch (error) {
        console.error('Terjadi kesalahan:', error);
    } finally {
        if (connection) {
            await connection.end();
            console.log('Koneksi database ditutup.');
        }
    }
}

createAdmin();
