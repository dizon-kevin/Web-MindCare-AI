<?php
session_start();
require_once '../config/dbcon.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Create Assessment · Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <!-- Fonts and Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>
        :root {
            --brand-grad: linear-gradient(135deg, #6C63FF 0%, #00C9A7 100%);
            --bg-soft: #f5f7fb;
            --card-bg: #ffffff;
            --muted: #6b7280;
            --ring: rgba(108, 99, 255, 0.25);
        }
        html, body {
            height: 100%;
            background:
                radial-gradient(60rem 60rem at 0% 0%, rgba(108, 99, 255, .07), transparent 40%),
                radial-gradient(50rem 50rem at 100% 0%, rgba(0, 201, 167, .08), transparent 40%),
                var(--bg-soft);
            font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            color: #111827;
        }
         /* Fix: prevent hero overlay from blocking clicks on the Back to Dashboard link */
      .hero { z-index: 0; }
      .hero::after { pointer-events: none; z-index: -1; }

      /* Optional: ensure the right-side action area is above any backgrounds */
      .hero .text-end { position: relative; z-index: 1; }

        /* Hero header */
        .hero {
            background: var(--brand-grad);
            color: white;
            border-radius: 24px;
            padding: 28px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.08);
        }
        .hero::after {
            content: "";
            position: absolute; inset: -40px -20px auto auto;
            width: 240px; height: 240px;
            background: radial-gradient(circle at center, rgba(255,255,255,.25), transparent 60%);
            transform: rotate(15deg);
        }
        .hero .badge-soft {
            background: rgba(255,255,255,0.18);
            border: 1px solid rgba(255,255,255,0.35);
            color: #fff;
        }

        /* Card */
        .card-elevated {
            background: var(--card-bg);
            border: 1px solid rgba(17,24,39,.06);
            border-radius: 18px;
            box-shadow: 0 8px 30px rgba(2,10,20,.05);
        }
        .section-title {
            font-weight: 700;
            letter-spacing: .3px;
        }
        .help-text {
            color: var(--muted);
        }

        /* Buttons */
        .btn-gradient {
            background: var(--brand-grad);
            color: white;
            border: none;
            border-radius: 12px;
            transition: transform .05s ease, box-shadow .2s ease, opacity .2s ease;
        }
        .btn-gradient:hover { opacity: .95; }
        .btn-gradient:active { transform: translateY(1px); }
        .btn-soft {
            background: #eef2ff;
            color: #3b82f6;
            border: 1px solid #e0e7ff;
        }

        /* Inputs */
        .form-control, .form-select, textarea {
            border-radius: 12px !important;
            border-color: #e5e7eb;
        }
        .form-control:focus, .form-select:focus, textarea:focus {
            box-shadow: 0 0 0 .25rem var(--ring);
            border-color: #c7c9ff;
        }

        /* Question builder */
        .q-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
        }
        .q-actions .btn {
            padding: .35rem .6rem;
            border-radius: 10px;
        }
        .q-row[data-highlight="true"] {
            animation: pulseRow .8s ease;
        }
        @keyframes pulseRow {
            0% { box-shadow: 0 0 0 0 rgba(108, 99, 255, 0.25); }
            100% { box-shadow: 0 0 0 10px rgba(108, 99, 255, 0); }
        }

        /* Counter + progress */
        .count-chip {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            color: #0f172a;
            border-radius: 999px;
            padding: .35rem .7rem;
            font-weight: 600;
            font-size: .85rem;
        }
        .progress-slim {
            height: 8px;
            border-radius: 999px;
        }

        /* Live results */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
        }
        @media (max-width: 992px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 576px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
        .stat-card {
            background: #ffffff;
            border: 1px solid rgba(17,24,39,.06);
            border-radius: 14px;
            padding: 14px;
        }
        .stat-kpi {
            font-weight: 800;
            font-size: 1.25rem;
        }
        .badge-dot {
            position: relative;
            padding-left: .9rem;
        }
        .badge-dot::before {
            content: "";
            width: .45rem; height: .45rem;
            border-radius: 50%;
            background: currentColor;
            position: absolute; left: .35rem; top: 50%; transform: translateY(-50%);
        }

        .results-toolbar .form-control, .results-toolbar .form-select {
            border-radius: 999px !important;
            padding-left: 2.25rem;
        }
        .input-with-icon {
            position: relative;
        }
        .input-with-icon i {
            position: absolute;
            left: .75rem; top: 50%; transform: translateY(-50%);
            color: #9ca3af;
        }

        /* Table */
        .table-modern thead th {
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb !important;
            color: #334155;
            font-weight: 700;
        }
        .table-modern tbody tr:hover {
            background: #fafafa;
        }
        .score-bar {
            background: #eef2ff;
            height: 8px;
            border-radius: 999px;
            overflow: hidden;
        }
        .score-bar > span {
            display: block; height: 100%;
            background: var(--brand-grad);
        }

        /* Skeleton loader */
        .sk {
            position: relative;
            overflow: hidden;
            background: #f3f4f6;
            border-radius: 8px;
            height: 14px;
        }
        .sk::after {
            content: "";
            position: absolute; inset: 0;
            transform: translateX(-100%);
            background: linear-gradient(90deg, transparent, rgba(255,255,255,.55), transparent);
            animation: shimmer 1.2s infinite;
        }
        @keyframes shimmer {
            100% { transform: translateX(100%); }
        }

        /* Toasts */
        .toast-container {
            z-index: 1080;
        }

        .sticky-actions {
            position: sticky;
            bottom: 0;
            background: linear-gradient(180deg, rgba(255,255,255,0) 0%, #fff 24%, #fff 100%);
            padding-top: .5rem;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Hero -->
        <div class="hero mb-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between">
                <div class="me-3">
                    <div class="d-inline-flex align-items-center gap-2 mb-2">
                        <span class="badge badge-soft rounded-pill"><i class="bi bi-shield-lock"></i> Admin</span>
                        <span class="badge badge-soft rounded-pill"><i class="bi bi-lightning-charge"></i> Builder</span>
                    </div>
                    <h1 class="h3 fw-bold mb-1">Create New Assessment</h1>
                    <p class="mb-0 opacity-75">Craft engaging assessments with at-a-glance results and live insights.</p>
                </div>
                <div class="text-end">
                    <a href="../dashboard/admin_dashboard.php" class="btn btn-light rounded-pill">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Builder + Live Results Grid -->
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card-elevated p-3 p-sm-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h2 class="h5 section-title mb-0">Assessment Builder</h2>
                            <small class="help-text">Add between 10 and 15 questions per set.</small>
                        </div>
                        <span class="count-chip"><span id="qCount">1</span>/15</span>
                    </div>

                    <form id="assessmentForm" method="POST" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Assessment Title</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Fundamentals of Mental Health" required>
                            <div class="invalid-feedback">Please provide a title.</div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Description <span class="text-muted">(optional)</span></label>
                            <textarea name="description" class="form-control" rows="3" placeholder="A short description for admins and students..."></textarea>
                        </div>

                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h3 class="h6 fw-bold mb-0"><i class="bi bi-list-task me-1"></i> Questions</h3>
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" id="addQuestionBtn" class="btn btn-soft btn-sm">
                                    <i class="bi bi-plus-lg"></i> Add Question
                                </button>
                                <button type="button" id="addBulkBtn" class="btn btn-outline-secondary btn-sm">
                                    +5 Quick
                                </button>
                            </div>
                        </div>

                        <div class="mb-2">
                            <div class="progress progress-slim">
                                <div id="qProgress" class="progress-bar" role="progressbar" style="width: 6%" aria-valuenow="1" aria-valuemin="0" aria-valuemax="15"></div>
                            </div>
                            <small class="help-text">Minimum 10 questions required to save.</small>
                        </div>

                        <div id="questionsList" class="mb-3">
                            <div class="q-row" data-index="1">
                                <input type="text" name="questions[]" class="form-control" placeholder="Question 1" required>
                                <div class="q-actions btn-group">
                                    <button type="button" class="btn btn-outline-secondary" data-action="up" title="Move up"><i class="bi bi-arrow-up"></i></button>
                                    <button type="button" class="btn btn-outline-secondary" data-action="down" title="Move down"><i class="bi bi-arrow-down"></i></button>
                                    <button type="button" class="btn btn-outline-danger" data-action="remove" title="Remove"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                        </div>

                        <div class="sticky-actions pt-2">
                            <div class="d-flex flex-wrap gap-2">
                                <button type="submit" id="saveBtn" class="btn btn-gradient px-4">
                                    <span class="spinner-border spinner-border-sm me-2 d-none" id="saveSpinner"></span>
                                    <i class="bi bi-check2-circle me-1"></i> Save Assessment
                                </button>
                                <a href="../dashboard/admin_dashboard.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle"></i> Cancel
                                </a>
                            </div>
                        </div>
                    </form>

                </div>
            </div>

            <!-- Live Results -->
            <div class="col-lg-6">
                <div class="card-elevated p-3 p-sm-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h2 class="h5 section-title mb-0">Live Assessment Results</h2>
                            <small class="help-text">Auto-refreshes every 5 seconds.</small>
                        </div>
                        <div class="text-end">
                            <span class="badge rounded-pill bg-light text-secondary border">
                                <span id="refreshIndicator" class="badge-dot text-success">Live</span>
                                <span class="ms-2" id="resultsLastUpdated">Updating...</span>
                            </span>
                        </div>
                    </div>

                    <!-- KPIs -->
                    <div class="stats-grid mb-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <small class="text-muted">Total Attempts</small>
                                <i class="bi bi-people text-primary"></i>
                            </div>
                            <div class="stat-kpi mt-1" id="kpiTotal">0</div>
                        </div>
                        <div class="stat-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <small class="text-muted">Average Score</small>
                                <i class="bi bi-graph-up-arrow text-success"></i>
                            </div>
                            <div class="stat-kpi mt-1"><span id="kpiAvg">0</span>%</div>
                        </div>
                        <div class="stat-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <small class="text-muted">Good</small>
                                <span class="badge rounded-pill bg-success-subtle text-success">GOOD</span>
                            </div>
                            <div class="stat-kpi mt-1" id="kpiGood">0</div>
                        </div>
                        <div class="stat-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <small class="text-muted">Fair/Bad</small>
                                <span class="badge rounded-pill bg-warning-subtle text-warning">FAIR/BAD</span>
                            </div>
                            <div class="stat-kpi mt-1"><span id="kpiFair">0</span>/<span id="kpiBad">0</span></div>
                        </div>
                    </div>

                    <!-- Toolbar -->
                    <div class="results-toolbar row g-2 align-items-center mb-2">
                        <div class="col-12 col-md-7">
                            <div class="input-with-icon">
                                <i class="bi bi-search"></i>
                                <input id="searchInput" type="text" class="form-control" placeholder="Search by user or assessment...">
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <select id="categoryFilter" class="form-select">
                                <option value="">All Categories</option>
                                <option value="good">Good</option>
                                <option value="fair">Fair</option>
                                <option value="bad">Bad</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-2 text-md-end">
                            <button id="refreshBtn" class="btn btn-outline-primary w-100">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-modern align-middle">
                            <thead>
                                <tr>
                                    <th>User Name</th>
                                    <th>Assessment</th>
                                    <th style="width: 180px;">Score</th>
                                    <th>Category</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody id="resultsBody">
                                <!-- Skeleton rows -->
                                <tr>
                                    <td><div class="sk" style="width: 140px;"></div></td>
                                    <td><div class="sk" style="width: 200px;"></div></td>
                                    <td><div class="sk" style="width: 160px;"></div></td>
                                    <td><div class="sk" style="width: 80px;"></div></td>
                                    <td><div class="sk" style="width: 120px;"></div></td>
                                </tr>
                                <tr>
                                    <td><div class="sk" style="width: 120px;"></div></td>
                                    <td><div class="sk" style="width: 220px;"></div></td>
                                    <td><div class="sk" style="width: 160px;"></div></td>
                                    <td><div class="sk" style="width: 80px;"></div></td>
                                    <td><div class="sk" style="width: 120px;"></div></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div id="emptyResults" class="text-center text-muted d-none py-4">
                        <div class="display-6 mb-2">🧪</div>
                        <div>No results yet</div>
                        <small>Check back after participants take the assessment.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toasts -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="appToast" class="toast align-items-center text-bg-dark border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="toastMessage">Saved successfully</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS (for toasts and tooltips) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // ========= Utilities =========
        function escapeHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
        function categoryBadge(cat) {
            var c = (cat || '').toLowerCase();
            var cls = 'bg-secondary';
            var txt = 'text-white';
            if (c === 'good') { cls = 'bg-success-subtle'; txt = 'text-success'; }
            else if (c === 'fair') { cls = 'bg-warning-subtle'; txt = 'text-warning'; }
            else if (c === 'bad') { cls = 'bg-danger-subtle'; txt = 'text-danger'; }
            return '<span class="badge rounded-pill ' + cls + ' ' + txt + '">' + escapeHtml(cat || '-') + '</span>';
        }
        function showToast(msg) {
            document.getElementById('toastMessage').textContent = msg;
            var toast = new bootstrap.Toast(document.getElementById('appToast'));
            toast.show();
        }

        // ========= Question Builder =========
        const MAX_Q = 15;
        const MIN_Q = 10;

        const questionsList = document.getElementById('questionsList');
        const qCountEl = document.getElementById('qCount');
        const qProgressEl = document.getElementById('qProgress');
        const addQuestionBtn = document.getElementById('addQuestionBtn');
        const addBulkBtn = document.getElementById('addBulkBtn');
        const saveBtn = document.getElementById('saveBtn');
        const saveSpinner = document.getElementById('saveSpinner');

        function updateQuestionMeta() {
            const rows = questionsList.querySelectorAll('.q-row');
            const count = rows.length;
            qCountEl.textContent = count;
            const percent = Math.min(100, (count / MAX_Q) * 100);
            qProgressEl.style.width = percent + '%';
            qProgressEl.setAttribute('aria-valuenow', String(count));

            // Enable/disable add buttons
            addQuestionBtn.disabled = count >= MAX_Q;
            addBulkBtn.disabled = count >= MAX_Q;

            // Update placeholders to reflect numbering
            rows.forEach((row, idx) => {
                const input = row.querySelector('input[name="questions[]"]');
                if (input) input.placeholder = 'Question ' + (idx + 1);
                row.dataset.index = (idx + 1);
            });
        }

        function createQuestionRow(value = '', highlight = false) {
            const current = questionsList.querySelectorAll('.q-row').length;
            if (current >= MAX_Q) return;

            const row = document.createElement('div');
            row.className = 'q-row';
            row.dataset.index = String(current + 1);
            if (highlight) row.setAttribute('data-highlight', 'true');

            const input = document.createElement('input');
            input.type = 'text';
            input.name = 'questions[]';
            input.className = 'form-control';
            input.placeholder = 'Question ' + (current + 1);
            input.required = true;
            input.value = value;

            const actions = document.createElement('div');
            actions.className = 'q-actions btn-group';

            const btnUp = document.createElement('button');
            btnUp.type = 'button';
            btnUp.className = 'btn btn-outline-secondary';
            btnUp.title = 'Move up';
            btnUp.innerHTML = '<i class="bi bi-arrow-up"></i>';
            btnUp.addEventListener('click', () => moveRow(row, -1));

            const btnDown = document.createElement('button');
            btnDown.type = 'button';
            btnDown.className = 'btn btn-outline-secondary';
            btnDown.title = 'Move down';
            btnDown.innerHTML = '<i class="bi bi-arrow-down"></i>';
            btnDown.addEventListener('click', () => moveRow(row, +1));

            const btnRemove = document.createElement('button');
            btnRemove.type = 'button';
            btnRemove.className = 'btn btn-outline-danger';
            btnRemove.title = 'Remove';
            btnRemove.innerHTML = '<i class="bi bi-trash"></i>';
            btnRemove.addEventListener('click', () => {
                if (questionsList.querySelectorAll('.q-row').length <= 1) {
                    showToast('At least one question row must remain.');
                    return;
                }
                row.remove();
                updateQuestionMeta();
            });

            actions.appendChild(btnUp);
            actions.appendChild(btnDown);
            actions.appendChild(btnRemove);

            row.appendChild(input);
            row.appendChild(actions);
            questionsList.appendChild(row);

            updateQuestionMeta();
            setTimeout(() => row.removeAttribute('data-highlight'), 900);
        }

        function moveRow(row, direction) {
            const sibling = direction < 0 ? row.previousElementSibling : row.nextElementSibling;
            if (!sibling) return;
            if (direction < 0) questionsList.insertBefore(row, sibling);
            else questionsList.insertBefore(sibling, row);
            updateQuestionMeta();
        }

        addQuestionBtn.addEventListener('click', () => createQuestionRow('', true));
        addBulkBtn.addEventListener('click', () => {
            let toAdd = Math.min(5, MAX_Q - questionsList.querySelectorAll('.q-row').length);
            for (let i = 0; i < toAdd; i++) createQuestionRow('', i === 0);
            if (toAdd === 0) showToast('Maximum of ' + MAX_Q + ' questions reached.');
        });

        // ========= Save handling =========
        $('#assessmentForm').on('submit', function(e) {
            e.preventDefault();
            const rows = questionsList.querySelectorAll('.q-row');
            if (rows.length < MIN_Q) {
                showToast('Please add at least ' + MIN_Q + ' questions before saving.');
                return;
            }
            // Basic HTML5 validation
            if (!this.checkValidity()) {
                this.classList.add('was-validated');
                return;
            }

            // Submit
            saveBtn.disabled = true;
            saveSpinner.classList.remove('d-none');

            $.post('save_assessment.php', $(this).serialize())
                .done(function(response) {
                    showToast('Assessment saved successfully.');
                    setTimeout(() => {
                        window.location.href = '../dashboard/admin_dashboard.php';
                    }, 900);
                })
                .fail(function() {
                    showToast('Failed to save assessment. Please try again.');
                })
                .always(function() {
                    saveBtn.disabled = false;
                    saveSpinner.classList.add('d-none');
                });
        });

        // ========= Live Results =========
        let lastRowsRaw = [];
        let polling = null;

        const resultsBody = $('#resultsBody');
        const emptyResults = $('#emptyResults');
        const lastUpdatedEl = $('#resultsLastUpdated');
        const refreshIndicator = $('#refreshIndicator');

        const kpiTotal = $('#kpiTotal');
        const kpiAvg = $('#kpiAvg');
        const kpiGood = $('#kpiGood');
        const kpiFair = $('#kpiFair');
        const kpiBad = $('#kpiBad');

        const searchInput = $('#searchInput');
        const categoryFilter = $('#categoryFilter');
        const refreshBtn = $('#refreshBtn');

        function computeKPIs(rows) {
            const total = rows.length;
            let sum = 0;
            let good = 0, fair = 0, bad = 0;
            rows.forEach(r => {
                const s = parseInt(r.score, 10) || 0;
                sum += s;
                const c = (r.category || '').toLowerCase();
                if (c === 'good') good++;
                else if (c === 'fair') fair++;
                else if (c === 'bad') bad++;
            });
            const avg = total ? Math.round((sum / total)) : 0;
            kpiTotal.text(total);
            kpiAvg.text(avg);
            kpiGood.text(good);
            kpiFair.text(fair);
            kpiBad.text(bad);
        }

        function renderRows(rows) {
            if (!rows || rows.length === 0) {
                resultsBody.html('');
                emptyResults.removeClass('d-none');
                computeKPIs([]);
                lastUpdatedEl.text('No data');
                return;
            }
            emptyResults.addClass('d-none');

            let html = '';
            for (let i = 0; i < rows.length; i++) {
                const r = rows[i];
                const score = Math.max(0, Math.min(100, parseInt(r.score, 10) || 0));
                html += '<tr>' +
                    '<td>' + escapeHtml(r.user_name) + '</td>' +
                    '<td>' + escapeHtml(r.assessment_title) + '</td>' +
                    '<td>' +
                        '<div class="d-flex align-items-center gap-2">' +
                            '<div class="score-bar flex-grow-1"><span style="width: ' + score + '%"></span></div>' +
                            '<small class="text-muted" style="width:38px; text-align:right;">' + score + '%</small>' +
                        '</div>' +
                    '</td>' +
                    '<td>' + categoryBadge(r.category) + '</td>' +
                    '<td><small class="text-muted">' + escapeHtml(r.created_at) + '</small></td>' +
                '</tr>';
            }
            resultsBody.html(html);
            computeKPIs(rows);
            const now = new Date();
            lastUpdatedEl.text('Last updated ' + now.toLocaleTimeString());
        }

        function filterRows(rows) {
            const term = (searchInput.val() || '').toLowerCase().trim();
            const cat = (categoryFilter.val() || '').toLowerCase().trim();
            return rows.filter(r => {
                const hay = (String(r.user_name || '') + ' ' + String(r.assessment_title || '')).toLowerCase();
                const matchesTerm = term ? hay.includes(term) : true;
                const matchesCat = cat ? (String(r.category || '').toLowerCase() === cat) : true;
                return matchesTerm && matchesCat;
            });
        }

        function fetchResults() {
            refreshIndicator.removeClass('text-muted').addClass('text-success').text('Live');
            $.getJSON('get_assessment_results.php', function(rows) {
                lastRowsRaw = Array.isArray(rows) ? rows : [];
                const filtered = filterRows(lastRowsRaw);
                renderRows(filtered);
            }).fail(function() {
                resultsBody.html('<tr><td colspan="5" class="text-center text-danger">Failed to load results</td></tr>');
                refreshIndicator.removeClass('text-success').addClass('text-muted').text('Offline');
            });
        }

        function startPolling() {
            if (polling) clearInterval(polling);
            fetchResults();
            polling = setInterval(fetchResults, 5000);
        }

        refreshBtn.on('click', function() {
            fetchResults();
        });
        searchInput.on('input', function() {
            renderRows(filterRows(lastRowsRaw));
        });
        categoryFilter.on('change', function() {
            renderRows(filterRows(lastRowsRaw));
        });

        // Initial state
        updateQuestionMeta();
        startPolling();
    </script>
</body>
</html>