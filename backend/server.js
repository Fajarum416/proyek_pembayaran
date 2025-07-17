// server.js

// -----------------------------------------------------------------------------
// 1. IMPORT DEPENDENSI
// -----------------------------------------------------------------------------
const express = require('express'); 
const cors = require('cors'); 
const mysql = require('mysql2/promise'); 
const multer = require('multer'); 
const path = require('path'); 
const fs = require('fs');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const { Parser } = require('json2csv');
const PDFDocument = require('pdfkit'); // Dependensi untuk ekspor PDF
require('dotenv').config();

// -----------------------------------------------------------------------------
// 2. KONFIGURASI APLIKASI
// -----------------------------------------------------------------------------
const app = express();
const PORT = process.env.PORT || 3000;

// Middleware
app.use(cors());
app.use(express.json());
app.use('/uploads', express.static(path.join(__dirname, 'uploads')));

// -----------------------------------------------------------------------------
// 3. KONFIGURASI DATABASE
// -----------------------------------------------------------------------------
const dbPool = mysql.createPool({
    host: process.env.DB_HOST,
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_NAME,
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
});

dbPool.getConnection().then(conn => {
    console.log('Koneksi ke database MySQL berhasil!');
    conn.release();
}).catch(err => {
    console.error('Gagal terhubung ke database:', err);
    process.exit(1);
});

// -----------------------------------------------------------------------------
// 4. KONFIGURASI UPLOAD FILE (MULTER)
// -----------------------------------------------------------------------------
const uploadDir = 'uploads';
if (!fs.existsSync(uploadDir)) fs.mkdirSync(uploadDir);

const storage = multer.diskStorage({
    destination: (req, file, cb) => cb(null, uploadDir),
    filename: (req, file, cb) => cb(null, Date.now() + '-' + file.originalname)
});
const upload = multer({ storage });

// -----------------------------------------------------------------------------
// 5. MIDDLEWARE OTENTIKASI
// -----------------------------------------------------------------------------
const protect = (req, res, next) => {
    const authHeader = req.headers.authorization;
    if (authHeader && authHeader.startsWith('Bearer ')) {
        const token = authHeader.split(' ')[1];
        jwt.verify(token, process.env.JWT_SECRET, (err, decoded) => {
            if (err) {
                return res.status(401).json({ message: 'Token tidak valid atau kedaluwarsa' });
            }
            req.user = decoded;
            next();
        });
    } else {
        res.status(401).json({ message: 'Akses ditolak, tidak ada token' });
    }
};

// -----------------------------------------------------------------------------
// 6. API ROUTES (ENDPOINT)
// -----------------------------------------------------------------------------

// === Rute Otentikasi (Publik) ===
app.post('/api/register', async (req, res) => {
    const { username, password } = req.body;
    if (!username || !password) {
        return res.status(400).json({ message: 'Username dan password diperlukan' });
    }
    try {
        const salt = await bcrypt.genSalt(10);
        const hashedPassword = await bcrypt.hash(password, salt);
        const [result] = await dbPool.query(
            'INSERT INTO users (username, password) VALUES (?, ?)',
            [username, hashedPassword]
        );
        res.status(201).json({ message: 'User berhasil dibuat', userId: result.insertId });
    } catch (error) {
        if (error.code === 'ER_DUP_ENTRY') {
            return res.status(409).json({ message: 'Username sudah digunakan' });
        }
        res.status(500).json({ message: 'Internal Server Error' });
    }
});

app.post('/api/login', async (req, res) => {
    const { username, password } = req.body;
    try {
        const [users] = await dbPool.query('SELECT * FROM users WHERE username = ?', [username]);
        if (users.length === 0) {
            return res.status(401).json({ message: 'Username atau password salah' });
        }
        const user = users[0];
        const isMatch = await bcrypt.compare(password, user.password);
        if (!isMatch) {
            return res.status(401).json({ message: 'Username atau password salah' });
        }
        const token = jwt.sign({ id: user.id, username: user.username }, process.env.JWT_SECRET, {
            expiresIn: '1d'
        });
        res.json({ message: 'Login berhasil', token });
    } catch (error) {
        res.status(500).json({ message: 'Internal Server Error' });
    }
});

// === Rute Terproteksi (Membutuhkan Login) ===
app.get('/api/students', protect, async (req, res) => {
    // Logika sama seperti sebelumnya
    const page = parseInt(req.query.page) || 1;
    const limit = parseInt(req.query.limit) || 10;
    const offset = (page - 1) * limit;
    const status = req.query.status || 'semua';
    const search = req.query.search || '';

    let whereClauses = [];
    let havingClauses = [];
    let queryParams = [];

    if (search) {
        whereClauses.push('s.name LIKE ?');
        queryParams.push(`%${search}%`);
    }
    const whereSql = whereClauses.length > 0 ? `WHERE ${whereClauses.join(' AND ')}` : '';

    if (status !== 'semua') {
        if (status === 'lunas') havingClauses.push('totalPaid >= s.total_bill');
        else if (status === 'sebagian') havingClauses.push('totalPaid > 0 AND totalPaid < s.total_bill');
        else if (status === 'belum') havingClauses.push('totalPaid = 0 OR totalPaid IS NULL');
    }
    const havingSql = havingClauses.length > 0 ? `HAVING ${havingClauses.join(' AND ')}` : '';

    try {
        const countQuery = `
            SELECT COUNT(*) as total FROM (
                SELECT s.id, s.total_bill, SUM(IFNULL(ph.amount, 0)) as totalPaid
                FROM students s
                LEFT JOIN payment_history ph ON s.id = ph.student_id
                ${whereSql}
                GROUP BY s.id, s.total_bill
                ${havingSql}
            ) as filtered_students
        `;
        const [[{ total }]] = await dbPool.query(countQuery, queryParams);
        const totalPages = Math.ceil(total / limit);

        const dataQuery = `
            SELECT s.id, s.name, s.total_bill AS totalBill, SUM(IFNULL(ph.amount, 0)) as totalPaid
            FROM students s
            LEFT JOIN payment_history ph ON s.id = ph.student_id
            ${whereSql}
            GROUP BY s.id, s.name, s.total_bill
            ${havingSql}
            ORDER BY s.id
            LIMIT ? OFFSET ?
        `;
        const [students] = await dbPool.query(dataQuery, [...queryParams, limit, offset]);

        if (students.length === 0) {
            return res.json({ data: [], totalPages: 0, currentPage: 1 });
        }

        const studentIds = students.map(s => s.id);
        const [history] = await dbPool.query(
            'SELECT transaction_id as transactionId, student_id, payment_date as date, amount, proof_image_url as proof FROM payment_history WHERE student_id IN (?) ORDER BY payment_date DESC',
            [studentIds]
        );

        const historyMap = {};
        for (const record of history) {
            if (!historyMap[record.student_id]) historyMap[record.student_id] = [];
            record.amount = parseFloat(record.amount);
            historyMap[record.student_id].push(record);
        }

        const results = students.map(student => ({
            ...student,
            totalBill: parseFloat(student.totalBill),
            paymentHistory: historyMap[student.id] || []
        }));

        res.json({ data: results, totalPages, currentPage: page });
    } catch (error) {
        console.error('Error fetching students:', error);
        res.status(500).json({ message: 'Internal Server Error' });
    }
});

app.get('/api/dashboard', protect, async (req, res) => {
    // Logika sama seperti sebelumnya
    try {
        const query = `
            SELECT SUM(total_bill) as totalBill, (SELECT SUM(amount) FROM payment_history) as totalPaid 
            FROM students
        `;
        const [[dashboardData]] = await dbPool.query(query);
        const totalBill = parseFloat(dashboardData.totalBill) || 0;
        const totalPaid = parseFloat(dashboardData.totalPaid) || 0;
        res.json({ totalTagihan: totalBill, totalTerbayar: totalPaid, totalSisa: totalBill - totalPaid });
    } catch (error) {
        res.status(500).json({ message: 'Internal Server Error' });
    }
});

// [POST], [PUT], [DELETE] untuk students dan history tetap sama...
app.post('/api/students', protect, upload.single('proof'), async (req, res) => {
    const { name, totalBill, amount } = req.body;
    const proofFile = req.file;
    const connection = await dbPool.getConnection();
    try {
        await connection.beginTransaction();
        const [studentResult] = await connection.query('INSERT INTO students (name, total_bill) VALUES (?, ?)', [name, totalBill]);
        const studentId = studentResult.insertId;
        if (amount && Number(amount) > 0) {
            await connection.query('INSERT INTO payment_history (student_id, payment_date, amount, proof_image_url) VALUES (?, ?, ?, ?)', [studentId, new Date(), Number(amount), proofFile ? `/uploads/${proofFile.filename}` : null]);
        }
        await connection.commit();
        res.status(201).json({ message: 'Siswa berhasil ditambahkan' });
    } catch (error) {
        await connection.rollback();
        res.status(500).json({ message: 'Gagal menambahkan siswa' });
    } finally {
        connection.release();
    }
});

// [PUT] Mengupdate data siswa 
app.put('/api/students/:id', protect, upload.single('proof'), async (req, res) => {
    const { id } = req.params;
    const { totalBill, amountToAdd } = req.body;
    const proofFile = req.file;

    // [FIX] Fungsi untuk membersihkan format mata uang di backend
    const cleanAndParse = (value) => {
        if (!value || typeof value !== 'string') return 0;
        // Hapus semua karakter yang bukan angka (seperti titik atau 'Rp')
        const cleaned = value.replace(/[^0-9]/g, '');
        return parseInt(cleaned, 10) || 0;
    };

    const numericAmount = cleanAndParse(amountToAdd);
    const numericTotalBill = cleanAndParse(totalBill);

    // Validasi: pastikan ada sesuatu yang diupdate
    if (numericTotalBill <= 0 && numericAmount <= 0 && !proofFile) {
        return res.status(400).json({ message: 'Tidak ada data untuk diupdate.' });
    }

    const connection = await dbPool.getConnection();
    try {
        await connection.beginTransaction();
        
        // 1. Update total tagihan jika ada dan valid
        if (totalBill !== undefined && totalBill !== null && totalBill !== '') {
            await connection.query(
                'UPDATE students SET total_bill = ? WHERE id = ?',
                [numericTotalBill, id]
            );
        }

        // 2. Tambah pembayaran baru ke riwayat jika ada
        if (numericAmount > 0) {
            await connection.query(
                'INSERT INTO payment_history (student_id, payment_date, amount, proof_image_url) VALUES (?, ?, ?, ?)',
                [id, new Date(), numericAmount, proofFile ? `/uploads/${proofFile.filename}` : null]
            );
        }

        await connection.commit();
        res.json({ message: 'Data siswa berhasil diupdate' });
    } catch (error) {
        await connection.rollback();
        console.error(`Error updating student ${id}:`, error);
        res.status(500).json({ message: 'Gagal mengupdate data siswa' });
    } finally {
        connection.release();
    }
});

app.delete('/api/students/:id', protect, async (req, res) => {
    const { id } = req.params;
    try {
        await dbPool.query('DELETE FROM students WHERE id = ?', [id]);
        res.json({ message: 'Data siswa berhasil dihapus' });
    } catch (error) {
        res.status(500).json({ message: 'Gagal menghapus data siswa' });
    }
});

app.delete('/api/history/:transactionId', protect, async (req, res) => {
    const { transactionId } = req.params;
    try {
        const [rows] = await dbPool.query('SELECT proof_image_url FROM payment_history WHERE transaction_id = ?', [transactionId]);
        if (rows.length > 0 && rows[0].proof_image_url) {
            const filePath = path.join(__dirname, rows[0].proof_image_url);
            if (fs.existsSync(filePath)) fs.unlinkSync(filePath);
        }
        await dbPool.query('DELETE FROM payment_history WHERE transaction_id = ?', [transactionId]);
        res.json({ message: 'Riwayat pembayaran berhasil dihapus' });
    } catch (error) {
        res.status(500).json({ message: 'Gagal menghapus riwayat pembayaran' });
    }
});

// [GET] Endpoint untuk ekspor CSV
app.get('/api/export/csv', protect, async (req, res) => {
    try {
        const [rows] = await dbPool.query(`
            SELECT 
                s.id as 'ID Siswa', s.name as 'Nama', s.total_bill as 'Total Tagihan',
                SUM(IFNULL(ph.amount, 0)) as 'Total Terbayar',
                (s.total_bill - SUM(IFNULL(ph.amount, 0))) as 'Sisa Tagihan'
            FROM students s
            LEFT JOIN payment_history ph ON s.id = ph.student_id
            GROUP BY s.id, s.name, s.total_bill
            ORDER BY s.id
        `);

        const dataToExport = rows.map(row => {
            const totalPaid = parseFloat(row['Total Terbayar']);
            const totalBill = parseFloat(row['Total Tagihan']);
            let status = 'Belum Bayar';
            if (totalPaid >= totalBill) status = 'Lunas';
            else if (totalPaid > 0) status = 'Bayar Sebagian';
            return { ...row, Status: status };
        });

        const fields = ['ID Siswa', 'Nama', 'Total Tagihan', 'Total Terbayar', 'Sisa Tagihan', 'Status'];
        const json2csvParser = new Parser({ fields });
        const csv = json2csvParser.parse(dataToExport);

        res.header('Content-Type', 'text/csv');
        res.attachment('laporan-pembayaran.csv');
        res.send(csv);

    } catch (error) {
        console.error('Error exporting to CSV:', error);
        res.status(500).json({ message: 'Gagal mengekspor data' });
    }
});

// [GET] Endpoint baru untuk ekspor PDF dengan desain
app.get('/api/export/pdf', protect, async (req, res) => {
    try {
        const [rows] = await dbPool.query(`
            SELECT 
                s.id, s.name, s.total_bill, SUM(IFNULL(ph.amount, 0)) as totalPaid
            FROM students s
            LEFT JOIN payment_history ph ON s.id = ph.student_id
            GROUP BY s.id, s.name, s.total_bill
            ORDER BY s.id
        `);

        const doc = new PDFDocument({ margin: 30, size: 'A4' });
        
        res.setHeader('Content-Type', 'application/pdf');
        res.setHeader('Content-Disposition', 'attachment; filename=laporan-pembayaran.pdf');
        
        doc.pipe(res);

        // --- Helper Functions untuk Desain PDF ---
        const generateHeader = (doc) => {
            doc.fillColor('#444444')
               .fontSize(20)
               .font('Helvetica-Bold')
               .text('LAPORAN PEMBAYARAN SISWA', { align: 'center' });
            doc.fontSize(10)
               .font('Helvetica')
               .text('LPK YAMAGUCHI INDONESIA', { align: 'center' });
            doc.moveDown();
            doc.fontSize(8)
               .text(`Tanggal Cetak: ${new Date().toLocaleDateString('id-ID', {day: '2-digit', month: 'long', year: 'numeric'})}`, { align: 'center' });
            doc.moveDown(2);
        };

        const generateFooter = (doc) => {
            doc.fontSize(8)
               .text('Laporan ini dibuat secara otomatis oleh Sistem Aplikasi Pembayaran.', 50, 780, { align: 'center', width: 500 });
        };

        const generateTableRow = (doc, y, c1, c2, c3, c4, c5, c6) => {
            doc.fontSize(9)
               .text(c1, 40, y)
               .text(c2, 80, y, { width: 140 })
               .text(c3, 220, y, { width: 90, align: 'right' })
               .text(c4, 310, y, { width: 90, align: 'right' })
               .text(c5, 400, y, { width: 90, align: 'right' })
               .text(c6, 490, y, { width: 70, align: 'center' });
        };

        const generateTableHeader = (doc, y) => {
            doc.rect(35, y - 5, 530, 20).fill('#2563EB');
            doc.fillColor('#FFFFFF').font('Helvetica-Bold');
            generateTableRow(doc, y, 'No', 'Nama Siswa', 'Total Tagihan', 'Total Bayar', 'Sisa Tagihan', 'Status');
        };

        // --- Mulai membuat konten PDF ---
        generateHeader(doc);

        const tableTop = 150;
        const formatCurrency = (amount) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount);

        generateTableHeader(doc, tableTop);
        
        let y = tableTop + 20;
        rows.forEach((row, index) => {
            const totalPaid = parseFloat(row.totalPaid);
            const totalBill = parseFloat(row.total_bill);
            const remaining = totalBill - totalPaid;
            
            let status = 'Belum Bayar';
            let rowColor = '#FF0000'; // Merah muda untuk 'Belum Bayar'

            if (totalPaid >= totalBill) {
                status = 'Lunas';
                rowColor = '#FFFFFF'; // Putih untuk 'Lunas'
            } else if (totalPaid > 0) {
                status = 'Bayar Sebagian';
                rowColor = '#FFFBEB'; // Oranye/kuning muda untuk 'Bayar Sebagian'
            }

            const rowY = y - 5;
            // Gambar latar belakang baris dengan warna yang sesuai
            doc.rect(35, rowY, 530, 20).fill(rowColor);
            
            // Atur warna teks menjadi hitam untuk setiap baris data
            doc.fillColor('#000000').font('Helvetica');
            generateTableRow(doc, y, 
                index + 1, 
                row.name, 
                formatCurrency(totalBill), 
                formatCurrency(totalPaid), 
                formatCurrency(remaining), 
                status
            );
            
            y += 20;
            if (y > 750) {
                doc.addPage();
                generateHeader(doc);
                y = 150;
                generateTableHeader(doc, y);
                y += 20;
            }
        });

        generateFooter(doc);
        
        doc.end();

    } catch (error) {
        console.error('Error exporting to PDF:', error);
        res.status(500).json({ message: 'Gagal mengekspor data' });
    }
});

// -----------------------------------------------------------------------------
// 7. JALANKAN SERVER
// -----------------------------------------------------------------------------
app.listen(PORT, () => {
    console.log(`Server berjalan di http://localhost:${PORT}`);
});
