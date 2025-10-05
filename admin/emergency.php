<?php
session_start();
require_once '../config/dbcon.php';

// Upload function
function uploadLogo($fileKey, $folder) {
    if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] == 0) {
        $target_dir = "../uploads/$folder/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $file_name = time() . "_" . basename($_FILES[$fileKey]['name']);
        $target_file = $target_dir . $file_name;
        if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $target_file)) {
            return "uploads/$folder/" . $file_name;
        }
    }
    return null;
}

// Add Emergency Contact
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_emergency'])) {
    $name = $_POST['name'];
    $contact_number = $_POST['contact_number'];
    $logo = uploadLogo('logo', 'emergency');
    $stmt = $conn->prepare("INSERT INTO emergency_numbers (name, contact_number, logo) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $contact_number, $logo);
    $stmt->execute();
    header("Location: emergency.php");
    exit();
}

// Edit Emergency Contact
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_emergency'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $contact_number = $_POST['contact_number'];
    $logo = uploadLogo('logo', 'emergency');

    if ($logo) {
        $stmt = $conn->prepare("UPDATE emergency_numbers SET name=?, contact_number=?, logo=? WHERE id=?");
        $stmt->bind_param("sssi", $name, $contact_number, $logo, $id);
    } else {
        $stmt = $conn->prepare("UPDATE emergency_numbers SET name=?, contact_number=? WHERE id=?");
        $stmt->bind_param("ssi", $name, $contact_number, $id);
    }
    $stmt->execute();
    header("Location: emergency.php");
    exit();
}

// Delete Emergency Contact
if (isset($_GET['delete_emergency'])) {
    $id = $_GET['delete_emergency'];
    $stmt = $conn->prepare("DELETE FROM emergency_numbers WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: emergency.php");
    exit();
}

// Add Facebook Page
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_facebook'])) {
    $name = $_POST['fb_name'];
    $page_link = $_POST['page_link'];
    $logo = uploadLogo('fb_logo', 'facebook');
    $stmt = $conn->prepare("INSERT INTO facebook_pages (name, page_link, logo) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $page_link, $logo);
    $stmt->execute();
    header("Location: emergency.php");
    exit();
}

// Edit Facebook Page
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_facebook'])) {
    $id = $_POST['id'];
    $name = $_POST['fb_name'];
    $page_link = $_POST['page_link'];
    $logo = uploadLogo('fb_logo', 'facebook');

    if ($logo) {
        $stmt = $conn->prepare("UPDATE facebook_pages SET name=?, page_link=?, logo=? WHERE id=?");
        $stmt->bind_param("sssi", $name, $page_link, $logo, $id);
    } else {
        $stmt = $conn->prepare("UPDATE facebook_pages SET name=?, page_link=? WHERE id=?");
        $stmt->bind_param("ssi", $name, $page_link, $id);
    }
    $stmt->execute();
    header("Location: emergency.php");
    exit();
}

// Delete Facebook Page
if (isset($_GET['delete_facebook'])) {
    $id = $_GET['delete_facebook'];
    $stmt = $conn->prepare("DELETE FROM facebook_pages WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: emergency.php");
    exit();
}

// Fetch data
$emergency_contacts = $conn->query("SELECT * FROM emergency_numbers");
$facebook_pages = $conn->query("SELECT * FROM facebook_pages");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Emergency & Facebook Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        :root {
            --brand-primary: #6C63FF;
            --brand-danger: #FF6B6B;
            --card-radius: 16px;
            --soft-bg: #F8FAFF;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.05);
            --shadow-md: 0 8px 24px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
        }

        body {
            background: var(--soft-bg);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
        }

        .app-toolbar {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(231, 234, 240, 0.7);
            position: sticky;
            top: 0;
            z-index: 1020;
            padding: 1rem 0;
            transition: var(--transition);
        }

        .page-title {
            font-weight: 700;
            letter-spacing: .2px;
        }

        .card {
            border: 1px solid rgba(231, 234, 240, 0.7);
            border-radius: var(--card-radius);
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }

        .card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .card-header {
            border-bottom: none;
            border-top-left-radius: var(--card-radius);
            border-top-right-radius: var(--card-radius);
            padding: 1.25rem;
            background: transparent;
        }

        .card-header.emergency {
            background: linear-gradient(135deg, rgba(255,107,107,0.1), rgba(255,107,107,0.05));
            color: var(--brand-danger);
            border-bottom: 1px solid rgba(255,107,107,0.2);
        }

        .card-header.facebook {
            background: linear-gradient(135deg, rgba(108,99,255,0.1), rgba(108,99,255,0.05));
            color: var(--brand-primary);
            border-bottom: 1px solid rgba(108,99,255,0.2);
        }

        .card-body {
            padding: 1.5rem;
        }

        .subtle {
            color: #6c757d;
            font-size: .925rem;
        }

        .table {
            --bs-table-hover-bg: rgba(108,99,255,0.02);
        }

        .table > :not(caption) > * > * {
            vertical-align: middle;
            padding: 1rem;
            border-bottom-color: rgba(108,99,255,0.1);
        }

        .table > thead > tr > th {
            background: rgba(108,99,255,0.03);
            color: var(--brand-primary);
            font-weight: 600;
            border-bottom: none;
        }

        .avatar {
            width: 42px;
            height: 42px;
            object-fit: cover;
            border-radius: 12px;
            border: 2px solid rgba(108,99,255,0.1);
            background: white;
            transition: var(--transition);
        }

        .avatar:hover {
            transform: scale(1.1);
            border-color: var(--brand-primary);
        }

        .empty-state {
            text-align: center;
            color: #6c757d;
            padding: 3rem 1rem;
            background: rgba(108,99,255,0.02);
            border-radius: 16px;
            border: 1px dashed rgba(108,99,255,0.2);
        }

        .badge {
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            font-weight: 500;
        }

        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: var(--shadow-md);
        }

        .modal-header {
            border-bottom: 1px solid rgba(108,99,255,0.1);
            padding: 1.25rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid rgba(108,99,255,0.1);
            padding: 1.25rem;
        }

        .input-hint {
            font-size: .85rem;
            color: #6c757d;
        }

        .input-icon {
            width: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(108,99,255,0.05);
            border: 1px solid rgba(108,99,255,0.2);
            border-right: 0;
            border-top-left-radius: 12px;
            border-bottom-left-radius: 12px;
            color: var(--brand-primary);
            transition: var(--transition);
        }

        .input-with-icon:focus-within .input-icon {
            background: rgba(108,99,255,0.1);
            color: var(--brand-primary);
        }

        .input-with-icon .form-control {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            border-color: rgba(108,99,255,0.2);
            transition: var(--transition);
        }

        .input-with-icon .form-control:focus {
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 4px rgba(108,99,255,0.1);
        }

        .form-divider {
            border-top: 1px dashed rgba(108,99,255,0.2);
            margin: 1.5rem 0;
        }

        .filter-input {
            max-width: 360px;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            border-color: rgba(108,99,255,0.2);
            background: rgba(255,255,255,0.9);
            transition: var(--transition);
        }

        .filter-input:focus {
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 4px rgba(108,99,255,0.1);
        }

        .btn {
            border-radius: 12px;
            padding: 0.75rem 1.25rem;
            font-weight: 600;
            transition: var(--transition);
        }

        .btn-icon {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--brand-primary);
            border-color: var(--brand-primary);
        }

        .btn-primary:hover {
            background: #5B52FF;
            border-color: #5B52FF;
            transform: translateY(-1px);
        }

        .btn-danger {
            background: var(--brand-danger);
            border-color: var(--brand-danger);
        }

        .btn-danger:hover {
            background: #FF5252;
            border-color: #FF5252;
            transform: translateY(-1px);
        }

        .btn-outline-warning {
            color: #F59E0B;
            border-color: #F59E0B;
        }

        .btn-outline-warning:hover {
            background: #F59E0B;
            color: white;
            transform: translateY(-1px);
        }

        .btn-outline-danger {
            color: var(--brand-danger);
            border-color: var(--brand-danger);
        }

        .btn-outline-danger:hover {
            background: var(--brand-danger);
            color: white;
            transform: translateY(-1px);
        }

        .file-preview {
            display: none;
            margin-top: .5rem;
            gap: .75rem;
            align-items: center;
        }
        .file-preview img {
            width: 44px;
            height: 44px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            background: #fff;
        }

        @media (min-width: 992px) {
            .eq-h {
                display: grid;
                grid-template-rows: auto 1fr;
                height: 100%;
            }
        }

        /* Mobile responsiveness */
        @media (max-width: 992px) {
            .filter-input { max-width: 100%; }
            .eq-h { display: block; }
        }
        @media (max-width: 576px) {
            .page-title { flex: 1 1 auto; }
            .app-toolbar .container { gap: 8px; }
            .avatar { width: 36px; height: 36px; }
            .file-preview img { width: 36px; height: 36px; }
            td.text-end, th.text-end { text-align: left !important; }
            td.text-end { white-space: normal; display: flex; flex-direction: column; gap: .5rem; align-items: stretch; }
            td.text-end .btn { width: 100%; }
        }
    </style>
</head>
<body>

<!-- Toolbar -->
<div class="app-toolbar py-2">
    <div class="container d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <a href="../dashboard/admin_dashboard.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>
            <span class="page-title ms-1">Emergency & Facebook Management</span>
        </div>
        <div class="subtle d-none d-md-block">
            Keep records organized. All entries are aligned, clean, and easy to scan.
        </div>
    </div>
</div>

<div class="container my-4">
    <div class="row g-4">
        <!-- Emergency Contacts -->
        <div class="col-12 col-lg-6">
            <div class="card eq-h shadow-sm">
                <div class="card-header emergency d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-shield-exclamation fs-5"></i>
                        <div>
                            <div class="fw-semibold">Emergency Contacts</div>
                            <div class="subtle">Add and manage hotline numbers</div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Form -->
                    <form method="POST" enctype="multipart/form-data" class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label mb-1">Name</label>
                            <div class="d-flex input-with-icon">
                                <span class="input-icon"><i class="bi bi-person-lines-fill"></i></span>
                                <input type="text" name="name" class="form-control" placeholder="e.g., Police Hotline" required>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label mb-1">Contact Number</label>
                            <div class="d-flex input-with-icon">
                                <span class="input-icon"><i class="bi bi-telephone"></i></span>
                                <input type="tel" name="contact_number" class="form-control" placeholder="e.g., 911 or +63 9XX XXX XXXX" required>
                            </div>
                        </div>
                        <div class="col-12 col-md-8">
                            <label class="form-label mb-1">Logo (optional)</label>
                            <input type="file" id="emg-logo" name="logo" class="form-control" accept="image/*">
                            <div id="emg-logo-preview" class="file-preview">
                                <img alt="Preview" />
                                <small class="text-muted"></small>
                            </div>
                            <div class="input-hint">Recommended square image, PNG/JPG, max ~2MB.</div>
                        </div>
                        <div class="col-12 col-md-4 d-flex align-items-end">
                            <button type="submit" name="add_emergency" class="btn btn-danger w-100 btn-icon">
                                <i class="bi bi-plus-circle"></i> Add Contact
                            </button>
                        </div>
                    </form>

                    <div class="form-divider"></div>

                    <!-- Filters -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="subtle">All Contacts</div>
                        <input type="search" id="filter-emergency" class="form-control form-control-sm filter-input" placeholder="Filter by name or number...">
                    </div>

                    <!-- Table -->
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:64px">Logo</th>
                                    <th>Name</th>
                                    <th>Contact Number</th>
                                    <th style="width:140px" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="emergency-tbody">
                            <?php if ($emergency_contacts && $emergency_contacts->num_rows > 0): ?>
                                <?php while ($row = $emergency_contacts->fetch_assoc()) { ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($row['logo'])) { ?>
                                                <img src="../<?= htmlspecialchars($row['logo']) ?>" class="avatar" alt="Logo">
                                            <?php } else { ?>
                                                <span class="text-muted"><i class="bi bi-image"></i></span>
                                            <?php } ?>
                                        </td>
                                        <td class="fw-medium"><?= htmlspecialchars($row['name']) ?></td>
                                        <td>
                                            <span class="badge text-bg-light border">
                                                <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($row['contact_number']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-outline-warning btn-icon me-1"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editEmergencyModal"
                                                data-id="<?= (int)$row['id'] ?>"
                                                data-name="<?= htmlspecialchars($row['name']) ?>"
                                                data-contact="<?= htmlspecialchars($row['contact_number']) ?>"
                                                data-logo="<?= htmlspecialchars($row['logo'] ?? '') ?>"
                                                data-bs-toggle-tooltip
                                                title="Edit">
                                                <i class="bi bi-pencil-square"></i> Edit
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger btn-icon"
                                                data-bs-toggle="modal"
                                                data-bs-target="#deleteConfirmModal"
                                                data-href="emergency.php?delete_emergency=<?= (int)$row['id'] ?>"
                                                data-bs-toggle-tooltip
                                                title="Delete">
                                                <i class="bi bi-trash3"></i> Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php } ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4">
                                        <div class="empty-state">
                                            <i class="bi bi-journal-x fs-3 d-block mb-2"></i>
                                            No emergency contacts yet. Add your first one above.
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Facebook Pages -->
        <div class="col-12 col-lg-6">
            <div class="card eq-h shadow-sm">
                <div class="card-header facebook d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-facebook fs-5"></i>
                        <div>
                            <div class="fw-semibold">Facebook Pages</div>
                            <div class="subtle">Save official page links</div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Form -->
                    <form method="POST" enctype="multipart/form-data" class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label mb-1">Page Name</label>
                            <div class="d-flex input-with-icon">
                                <span class="input-icon"><i class="bi bi-badge-ad"></i></span>
                                <input type="text" name="fb_name" class="form-control" placeholder="e.g., City Government" required>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label mb-1">Page Link</label>
                            <div class="d-flex input-with-icon">
                                <span class="input-icon"><i class="bi bi-link-45deg"></i></span>
                                <input type="url" name="page_link" class="form-control" placeholder="https://facebook.com/yourpage" required>
                            </div>
                        </div>
                        <div class="col-12 col-md-8">
                            <label class="form-label mb-1">Logo (optional)</label>
                            <input type="file" id="fb-logo" name="fb_logo" class="form-control" accept="image/*">
                            <div id="fb-logo-preview" class="file-preview">
                                <img alt="Preview" />
                                <small class="text-muted"></small>
                            </div>
                            <div class="input-hint">Prefer square image. PNG/JPG.</div>
                        </div>
                        <div class="col-12 col-md-4 d-flex align-items-end">
                            <button type="submit" name="add_facebook" class="btn btn-primary w-100 btn-icon">
                                <i class="bi bi-plus-circle"></i> Add Page
                            </button>
                        </div>
                    </form>

                    <div class="form-divider"></div>

                    <!-- Filters -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="subtle">All Pages</div>
                        <input type="search" id="filter-facebook" class="form-control form-control-sm filter-input" placeholder="Filter by name or link...">
                    </div>

                    <!-- Table -->
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:64px">Logo</th>
                                    <th>Page Name</th>
                                    <th>Page Link</th>
                                    <th style="width:140px" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="facebook-tbody">
                            <?php if ($facebook_pages && $facebook_pages->num_rows > 0): ?>
                                <?php while ($row = $facebook_pages->fetch_assoc()) { ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($row['logo'])) { ?>
                                                <img src="../<?= htmlspecialchars($row['logo']) ?>" class="avatar" alt="Logo">
                                            <?php } else { ?>
                                                <span class="text-muted"><i class="bi bi-image"></i></span>
                                            <?php } ?>
                                        </td>
                                        <td class="fw-medium"><?= htmlspecialchars($row['name']) ?></td>
                                        <td>
                                            <a class="text-decoration-none" href="<?= htmlspecialchars($row['page_link']) ?>" target="_blank" rel="noopener">
                                                <span class="badge text-bg-primary-subtle border">
                                                    <i class="bi bi-box-arrow-up-right me-1"></i> Visit
                                                </span>
                                            </a>
                                        </td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-outline-warning btn-icon me-1"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editFacebookModal"
                                                data-id="<?= (int)$row['id'] ?>"
                                                data-name="<?= htmlspecialchars($row['name']) ?>"
                                                data-link="<?= htmlspecialchars($row['page_link']) ?>"
                                                data-logo="<?= htmlspecialchars($row['logo'] ?? '') ?>"
                                                data-bs-toggle-tooltip
                                                title="Edit">
                                                <i class="bi bi-pencil-square"></i> Edit
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger btn-icon"
                                                data-bs-toggle="modal"
                                                data-bs-target="#deleteConfirmModal"
                                                data-href="emergency.php?delete_facebook=<?= (int)$row['id'] ?>"
                                                data-bs-toggle-tooltip
                                                title="Delete">
                                                <i class="bi bi-trash3"></i> Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php } ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4">
                                        <div class="empty-state">
                                            <i class="bi bi-journal-x fs-3 d-block mb-2"></i>
                                            No Facebook pages yet. Add your first one above.
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Emergency Modal -->
<div class="modal fade" id="editEmergencyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2 text-danger"></i>Edit Emergency Contact</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="emergency-id">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" id="emergency-name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Number</label>
                        <input type="text" name="contact_number" id="emergency-contact" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Logo</label>
                        <input type="file" id="emg-edit-logo" name="logo" class="form-control" accept="image/*">
                        <div id="emg-edit-logo-preview" class="file-preview">
                            <img alt="Preview" />
                            <small class="text-muted"></small>
                        </div>
                        <div class="input-hint">Leave empty to keep current logo.</div>
                        <div class="mt-2" id="emg-current-logo" style="display:none;">
                            <div class="small text-muted mb-1">Current:</div>
                            <img src="" alt="Current logo" class="avatar">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_emergency" class="btn btn-danger">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Facebook Modal -->
<div class="modal fade" id="editFacebookModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Facebook Page</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="facebook-id">
                    <div class="mb-3">
                        <label class="form-label">Page Name</label>
                        <input type="text" name="fb_name" id="facebook-name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Page Link</label>
                        <input type="url" name="page_link" id="facebook-link" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Logo</label>
                        <input type="file" id="fb-edit-logo" name="fb_logo" class="form-control" accept="image/*">
                        <div id="fb-edit-logo-preview" class="file-preview">
                            <img alt="Preview" />
                            <small class="text-muted"></small>
                        </div>
                        <div class="input-hint">Leave empty to keep current logo.</div>
                        <div class="mt-2" id="fb-current-logo" style="display:none;">
                            <div class="small text-muted mb-1">Current:</div>
                            <img src="" alt="Current logo" class="avatar">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_facebook" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                This action cannot be undone. Are you sure you want to delete this item?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="confirmDeleteBtn" class="btn btn-danger">Yes, Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Tooltips
    document.querySelectorAll('[data-bs-toggle-tooltip]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    // Row filters
    function bindFilter(inputId, tableBodyId) {
        const input = document.getElementById(inputId);
        const tbody = document.getElementById(tableBodyId);
        if (!input || !tbody) return;

        input.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            tbody.querySelectorAll('tr').forEach(function (tr) {
                const text = tr.innerText.toLowerCase();
                tr.style.display = text.includes(q) ? '' : 'none';
            });
        });
    }
    bindFilter('filter-emergency', 'emergency-tbody');
    bindFilter('filter-facebook', 'facebook-tbody');

    // Delete confirmation modal
    const deleteModal = document.getElementById('deleteConfirmModal');
    let deleteHref = null;
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            deleteHref = button?.getAttribute('data-href') || null;
        });
        document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
            if (deleteHref) window.location.href = deleteHref;
        });
    }

    // Image preview binding
    function bindImagePreview(inputId, previewWrapperId) {
        const input = document.getElementById(inputId);
        const wrapper = document.getElementById(previewWrapperId);
        if (!input || !wrapper) return;

        const img = wrapper.querySelector('img');
        const info = wrapper.querySelector('small');

        input.addEventListener('change', function () {
            const file = this.files && this.files[0];
            if (!file) {
                wrapper.style.display = 'none';
                return;
            }
            const reader = new FileReader();
            reader.onload = function (e) {
                img.src = e.target.result;
                info.textContent = file.name.length > 28 ? file.name.slice(0, 25) + '...' : file.name;
                wrapper.style.display = 'flex';
            };
            reader.readAsDataURL(file);
        });
    }

    bindImagePreview('emg-logo', 'emg-logo-preview');
    bindImagePreview('fb-logo', 'fb-logo-preview');
    bindImagePreview('emg-edit-logo', 'emg-edit-logo-preview');
    bindImagePreview('fb-edit-logo', 'fb-edit-logo-preview');

    // Populate Edit Emergency Modal
    const emergencyModal = document.getElementById('editEmergencyModal');
    if (emergencyModal) {
        emergencyModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('emergency-id').value = button.getAttribute('data-id');
            document.getElementById('emergency-name').value = button.getAttribute('data-name');
            document.getElementById('emergency-contact').value = button.getAttribute('data-contact');

            const logo = button.getAttribute('data-logo');
            const current = document.getElementById('emg-current-logo');
            if (logo) {
                current.style.display = 'block';
                current.querySelector('img').src = '../' + logo;
            } else {
                current.style.display = 'none';
            }
            // reset preview on open
            const editPreview = document.getElementById('emg-edit-logo-preview');
            if (editPreview) {
                editPreview.style.display = 'none';
                editPreview.querySelector('img').src = '';
                editPreview.querySelector('small').textContent = '';
            }
            const fileInput = document.getElementById('emg-edit-logo');
            if (fileInput) fileInput.value = '';
        });
    }

    // Populate Edit Facebook Modal
    const facebookModal = document.getElementById('editFacebookModal');
    if (facebookModal) {
        facebookModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('facebook-id').value = button.getAttribute('data-id');
            document.getElementById('facebook-name').value = button.getAttribute('data-name');
            document.getElementById('facebook-link').value = button.getAttribute('data-link');

            const logo = button.getAttribute('data-logo');
            const current = document.getElementById('fb-current-logo');
            if (logo) {
                current.style.display = 'block';
                current.querySelector('img').src = '../' + logo;
            } else {
                current.style.display = 'none';
            }
            // reset preview on open
            const editPreview = document.getElementById('fb-edit-logo-preview');
            if (editPreview) {
                editPreview.style.display = 'none';
                editPreview.querySelector('img').src = '';
                editPreview.querySelector('small').textContent = '';
            }
            const fileInput = document.getElementById('fb-edit-logo');
            if (fileInput) fileInput.value = '';
        });
    }
</script>

</body>
</html>