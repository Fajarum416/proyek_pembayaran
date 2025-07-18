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
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $status = isset($_GET['status']) ? $_GET['status'] : 'semua';
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $offset = ($page - 1) * $limit;

    $whereClauses = [];
    $havingClauses = [];
    $queryParams = [];

    if (!empty($search)) {
        $whereClauses[] = 's.name LIKE :search';
        $queryParams[':search'] = '%' . $search . '%';
    }
    $whereSql = count($whereClauses) > 0 ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

    if ($status !== 'semua') {
        if ($status === 'lunas') $havingClauses[] = 'totalPaid >= s.total_bill';
        elseif ($status === 'sebagian') $havingClauses[] = 'totalPaid > 0 AND totalPaid < s.total_bill';
        elseif ($status === 'belum') $havingClauses[] = 'totalPaid = 0 OR totalPaid IS NULL';
    }
    $havingSql = count($havingClauses) > 0 ? 'HAVING ' . implode(' AND ', $havingClauses) : '';

    try {
        $countQuery = "SELECT COUNT(*) as total FROM (SELECT s.id, s.total_bill, SUM(IFNULL(ph.amount, 0)) as totalPaid FROM students s LEFT JOIN payment_history ph ON s.id = ph.student_id $whereSql GROUP BY s.id, s.total_bill $havingSql) as filtered_students";
        $stmt = $pdo->prepare($countQuery);
        $stmt->execute($queryParams);
        $total = $stmt->fetchColumn();
        $totalPages = ceil($total / $limit);

        $dataQuery = "SELECT s.id, s.name, s.total_bill AS totalBill, SUM(IFNULL(ph.amount, 0)) as totalPaid FROM students s LEFT JOIN payment_history ph ON s.id = ph.student_id $whereSql GROUP BY s.id, s.name, s.total_bill $havingSql ORDER BY s.id LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($dataQuery);
        foreach ($queryParams as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $students = $stmt->fetchAll();

        if (count($students) > 0) {
            $studentIds = array_map(function ($s) {
                return $s['id'];
            }, $students);
            $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
            $historyQuery = "SELECT transaction_id as transactionId, student_id, payment_date as date, amount, proof_image_url as proof FROM payment_history WHERE student_id IN ($placeholders) ORDER BY payment_date DESC";
            $stmt = $pdo->prepare($historyQuery);
            $stmt->execute($studentIds);
            $history = $stmt->fetchAll();

            $historyMap = [];
            foreach ($history as $record) {
                $historyMap[$record['student_id']][] = $record;
            }
            foreach ($students as &$student) {
                $student['paymentHistory'] = $historyMap[$student['id']] ?? [];
            }
        }

        echo json_encode(['data' => $students, 'totalPages' => $totalPages, 'currentPage' => $page]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Gagal mengambil data siswa.']);
    }
}

function handle_post_student($pdo)
{
    $name = isset($_POST['name']) ? htmlspecialchars(strip_tags($_POST['name'])) : null;
    $totalBill = isset($_POST['totalBill']) ? filter_var($_POST['totalBill'], FILTER_SANITIZE_NUMBER_INT) : null;
    $amount = isset($_POST['amount']) ? filter_var($_POST['amount'], FILTER_SANITIZE_NUMBER_INT) : 0;

    if (!$name || !$totalBill) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Nama dan Total Tagihan wajib diisi.']);
        exit;
    }

    $proofPath = null;
    if (isset($_FILES['proof']) && $_FILES['proof']['error'] == 0) {
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $fileName = time() . '-' . basename($_FILES['proof']['name']);
        $targetFile = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['proof']['tmp_name'], $targetFile)) {
            $proofPath = '/uploads/' . $fileName;
        }
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO students (name, total_bill) VALUES (:name, :total_bill)");
        $stmt->execute([':name' => $name, ':total_bill' => $totalBill]);
        $studentId = $pdo->lastInsertId();

        if ($amount > 0) {
            $stmt = $pdo->prepare("INSERT INTO payment_history (student_id, payment_date, amount, proof_image_url) VALUES (:sid, :pdate, :amount, :proof)");
            $stmt->execute([':sid' => $studentId, ':pdate' => date('Y-m-d'), ':amount' => $amount, ':proof' => $proofPath]);
        }

        $pdo->commit();
        http_response_code(201);
        echo json_encode(['status' => 'success', 'message' => 'Siswa berhasil ditambahkan.']);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Gagal menambahkan siswa.']);
    }
}
