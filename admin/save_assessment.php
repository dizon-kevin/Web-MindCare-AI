<?php
session_start();
require_once '../config/dbcon.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit("Unauthorized");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $desc = trim($_POST['description']);
    $questions = $_POST['questions'] ?? [];

    if (count($questions) < 10) {
        exit("Please add at least 10 questions.");
    }

    // Create initial assessment
    $stmt = $conn->prepare("INSERT INTO assessments (title, description, created_by) VALUES (?, ?, ?)");
    if (!$stmt) die("SQL Error (assessment insert): " . $conn->error);
    $stmt->bind_param("ssi", $title, $desc, $_SESSION['user_id']);
    $stmt->execute();
    $assessment_id = $stmt->insert_id;

    $questionCounter = 0;
    foreach ($questions as $q) {
        $q = trim($q);
        if (!empty($q)) {
            // Count how many questions already in this set
            $countQ = $conn->query("SELECT COUNT(*) FROM assessment_questions WHERE assessment_id = $assessment_id")->fetch_row()[0];

            // If >=15, make a new assessment set automatically
            if ($countQ >= 15) {
                $titleSet = $title . " (Set " . (floor($countQ / 15) + 1) . ")";
                $stmtNew = $conn->prepare("INSERT INTO assessments (title, description, created_by) VALUES (?, ?, ?)");
                $stmtNew->bind_param("ssi", $titleSet, $desc, $_SESSION['user_id']);
                $stmtNew->execute();
                $assessment_id = $stmtNew->insert_id;
            }

            // Insert question
            $stmtQ = $conn->prepare("INSERT INTO assessment_questions (assessment_id, question_text) VALUES (?, ?)");
            $stmtQ->bind_param("is", $assessment_id, $q);
            $stmtQ->execute();
            $question_id = $stmtQ->insert_id;

            // Insert default options with values
            $options = [
                ['Not at all', 0],
                ['Several days', 1],
                ['More than half the days', 2],
                ['Nearly every day', 3]
            ];
            foreach ($options as $opt) {
                $stmtO = $conn->prepare("INSERT INTO question_options (question_id, option_text, option_value) VALUES (?, ?, ?)");
                $stmtO->bind_param("isi", $question_id, $opt[0], $opt[1]);
                $stmtO->execute();
            }

            $questionCounter++;
        }
    }

    exit("Assessment created successfully with $questionCounter questions!");
} else {
    http_response_code(405);
    exit("Method Not Allowed");
}
?>
