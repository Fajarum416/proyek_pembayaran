<?php

/**
 * Header ini WAJIB ada.
 * Fungsinya adalah untuk memberitahu browser atau aplikasi Flutter
 * bahwa konten yang akan dikirim adalah dalam format JSON, bukan HTML.
 */
header('Content-Type: application/json');

// Membuat sebuah array sederhana di PHP.
// Ini mensimulasikan data yang nanti akan kita ambil dari database.
$data = [
    'pesan' => 'Halo! Ini adalah data pertama dari API Anda.',
    'status' => 'sukses',
    'timestamp' => time() // Menambahkan waktu saat ini untuk menunjukkan data ini dinamis
];

/**
 * Ini adalah fungsi paling penting.
 * json_encode() akan mengubah array PHP di atas menjadi sebuah
 * string berformat JSON yang bisa dibaca oleh Flutter.
 */
echo json_encode($data);

/*
 * Hasil yang diharapkan saat Anda membuka file ini di browser adalah:
 * {"pesan":"Halo! Ini adalah data pertama dari API Anda.","status":"sukses","timestamp":1721722800}
 * (Angka timestamp akan berbeda-beda)
*/
