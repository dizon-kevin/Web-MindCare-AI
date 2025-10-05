<?php
session_start();
require_once '../config/dbcon.php';

// Simple flash helper compatible with manage_users.php
function set_flash($type, $message) {
  $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}
function redirect_back() {
  header('Location: manage_users.php');
  exit();
}

// Enforce POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  set_flash('error', 'Invalid request method.');
  redirect_back();
}

// Enforce admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  set_flash('error', 'Unauthorized.');
  redirect_back();
}

// Validate CSRF
$csrf = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
  set_flash('error', 'Security token mismatch. Please try again.');
  redirect_back();
}

// Validate user ID
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
  set_flash('error', 'Invalid user ID.');
  redirect_back();
}

// Optional: prevent deleting the currently logged-in account
$currentUserId = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
if ($currentUserId === $id) {
  set_flash('error', 'You cannot delete your own account while logged in.');
  redirect_back();
}

// Perform deletion
$stmt = $conn->prepare('DELETE FROM users WHERE id = ? LIMIT 1');
if (!$stmt) {
  set_flash('error', 'Database error (prepare): ' . $conn->error);
  redirect_back();
}
$stmt->bind_param('i', $id);
$stmt->execute();

if ($stmt->errno) {
  // MySQL FK constraint error code
  if ($stmt->errno === 1451) {
    set_flash('error', 'Cannot delete user: related records exist.');
  } else {
    set_flash('error', 'Database error: ' . $stmt->error);
  }
  $stmt->close();
  redirect_back();
}

if ($stmt->affected_rows > 0) {
  set_flash('success', 'User deleted successfully.');
} else {
  set_flash('error', 'User not found or already deleted.');
}
$stmt->close();

redirect_back();