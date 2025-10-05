<?php
session_start();
require_once '../config/dbcon.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$rows = [];
$sql = "
    SELECT 
        ar.id,
        ar.score,
        ar.category,
        ar.created_at,
        u.name AS user_name,
        a.title AS assessment_title
    FROM assessment_results ar
    JOIN users u ON u.id = ar.user_id
    JOIN assessments a ON a.id = ar.assessment_id
    ORDER BY ar.created_at DESC
    LIMIT 100
";
$res = $conn->query($sql);
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $rows[] = [
            'id' => (int)($r['id'] ?? 0),
            'score' => (int)($r['score'] ?? 0),
            'category' => $r['category'] ?? '',
            'created_at' => $r['created_at'] ?? '',
            'user_name' => $r['user_name'] ?? '',
            'assessment_title' => $r['assessment_title'] ?? ''
        ];
    }
}

echo json_encode($rows);
