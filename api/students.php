<?php
// api/students.php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt.php';

$user = validate_jwt_from_request();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handle_get_students($pdo);
        break;
    case 'POST':
        handle_post_student($pdo);
        break;
    default:
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Metode request tidak diizinkan.']);
        break;
}

function handle_get_students($pdo)
{
    // --- PERUBAHAN DEFAULT SORTING ---
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at'; // Default sort by created_at
    $order = isset($_GET['order']) ? $_GET['order'] : 'desc'; // Default order descending
    $offset = ($page - 1) * $limit;

    // Validasi untuk keamanan
    $allowed_sort_columns = ['name', 'created_at'];
    if (!in_array($sort, $allowed_sort_columns)) {
        $sort = 'created_at';
    }
    $order = strtolower($order) === 'asc' ? 'ASC' : 'DESC';

    try {
        $whereClause = '';
        $params = [];
        if (!empty($search)) {
            $whereClause = " WHERE name LIKE :search";
            $params[':search'] = '%' . $search . '%';
        }

        // --- QUERY UNTUK MENGHITUNG TOTAL DATA ---
        $countQuery = "SELECT COUNT(id) FROM students" . $whereClause;
        $stmtCount = $pdo->prepare($countQuery);
        $stmtCount->execute($params);
        $totalRecords = $stmtCount->fetchColumn();
        $totalPages = ceil($totalRecords / $limit);

        // --- QUERY UTAMA DENGAN SORTING DAN PAGINATION ---
        $queryStudents = "SELECT id, name, created_at FROM students" . $whereClause . " ORDER BY $sort $order LIMIT :limit OFFSET :offset";

        $stmtStudents = $pdo->prepare($queryStudents);
        foreach ($params as $key => &$val) {
            $stmtStudents->bindParam($key, $val);
        }
        $stmtStudents->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmtStudents->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmtStudents->execute();
        $students = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);

        if (count($students) > 0) {
            $studentIds = array_map(fn($s) => $s['id'], $students);
            $placeholders = implode(',', array_fill(0, count($studentIds), '?'));

            $queryInvoices = "SELECT id, student_id, description, amount FROM invoices WHERE student_id IN ($placeholders)";
            $stmtInvoices = $pdo->prepare($queryInvoices);
            $stmtInvoices->execute($studentIds);
            $invoices = $stmtInvoices->fetchAll(PDO::FETCH_ASSOC);

            $queryHistory = "SELECT transaction_id as transactionId, student_id, invoice_id, payment_date as date, amount, proof_image_url as proof FROM payment_history WHERE student_id IN ($placeholders) ORDER BY payment_date DESC";
            $stmtHistory = $pdo->prepare($queryHistory);
            $stmtHistory->execute($studentIds);
            $history = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

            $historyMap = [];
            foreach ($history as $h) {
                $historyMap[$h['invoice_id']][] = $h;
            }

            $invoiceMap = [];
            foreach ($invoices as $inv) {
                $inv['payments'] = $historyMap[$inv['id']] ?? [];
                $invoiceMap[$inv['student_id']][] = $inv;
            }

            foreach ($students as &$student) {
                $student['invoices'] = $invoiceMap[$student['id']] ?? [];
            }
        }

        // --- MENGEMBALIKAN DATA PAGINASI KE FRONTEND ---
        echo json_encode([
            'data' => $students,
            'totalPages' => $totalPages,
            'currentPage' => $page
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Gagal mengambil data siswa.', 'error_detail' => $e->getMessage()]);
    }
}

function handle_post_student($pdo)
{
    $data = json_decode(file_get_contents("php://input"));
    $name = isset($data->name) ? htmlspecialchars(strip_tags($data->name)) : null;

    if (!$name) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Nama wajib diisi.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO students (name) VALUES (:name)");
        $stmt->execute([':name' => $name]);

        http_response_code(201);
        echo json_encode(['status' => 'success', 'message' => 'Siswa berhasil ditambahkan.']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Gagal menambahkan siswa.']);
    }
}