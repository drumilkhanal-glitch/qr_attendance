<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_teacher();

$pdo = db();

// Get all enrollments with student and class/subject names
$stmt = $pdo->prepare("
    SELECT 
        se.id,
        se.student_id,
        s.full_name as student_name,
        se.class_subject_id,
        CONCAT(cs.class_name, ' - ', cs.subject_name) as class_subject_name,
        se.enrolled_at
    FROM student_enrollment se
    JOIN student s ON s.id = se.student_id
    JOIN class_subject cs ON cs.id = se.class_subject_id
    ORDER BY s.full_name ASC, cs.class_name ASC
");
$stmt->execute();
$enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Enrollment</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 1rem;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #111827;
        }

        .header a {
            color: white;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }

        .header a:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.875rem;
            margin-top: 0.5rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
            padding: 2rem;
        }

        .card h2 {
            font-size: 1.3rem;
            color: #111827;
            margin-bottom: 1rem;
            font-weight: 700;
        }

        /* Search and Filter Section */
        .search-section {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .search-row {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
        }

        .search-box input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            font-size: 0.95rem;
            transition: all 0.2s;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-box input::placeholder {
            color: #9ca3af;
        }

        /* Filter Buttons */
        .filter-buttons {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.75rem 1.25rem;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .btn:hover {
            background: #5568d3;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        .btn-success {
            background: #10b981;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-danger {
            background: #ef4444;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        /* Checkbox in Header */
        .select-all-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .checkbox-wrapper input[type="checkbox"] {
            width: 1.25rem;
            height: 1.25rem;
            cursor: pointer;
            accent-color: #667eea;
        }

        .checkbox-wrapper label {
            cursor: pointer;
            font-weight: 600;
            color: #374151;
        }

        /* Table Styles */
        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.95rem;
            border-bottom: 2px solid #e5e7eb;
        }

        table td {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        table tbody tr {
            transition: background-color 0.2s;
        }

        table tbody tr:hover {
            background: #f8f9ff;
        }

        table tbody tr:nth-child(even) {
            background: #f9fafb;
        }

        table tbody tr.selected {
            background: #dbeafe;
        }

        /* Checkboxes in Table */
        .table-checkbox {
            width: 1.25rem;
            height: 1.25rem;
            cursor: pointer;
            accent-color: #667eea;
        }

        .id-cell {
            background: #e3f2fd;
            color: #1976d2;
            font-weight: 600;
            border-radius: 0.375rem;
            padding: 0.5rem 0.75rem;
            text-align: center;
            font-size: 0.85rem;
            min-width: 50px;
            display: inline-block;
        }

        .student-name {
            color: #667eea;
            font-weight: 600;
        }

        .class-subject {
            color: #374151;
            font-weight: 500;
        }

        .enrolled-at {
            color: #6b7280;
            font-size: 0.85rem;
        }

        /* Results Counter */
        .results-counter {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            margin-bottom: 1rem;
            color: #6b7280;
            font-size: 0.9rem;
        }

        .results-counter strong {
            color: #111827;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #9ca3af;
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state p {
            font-size: 1rem;
            margin-bottom: 1rem;
        }

        /* Selection Info */
        .selection-info {
            display: none;
            background: #dbeafe;
            border: 1px solid #93c5fd;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            color: #1e40af;
            font-weight: 600;
        }

        .selection-info.active {
            display: block;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .search-row {
                flex-direction: column;
            }

            .filter-buttons {
                justify-content: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 0.9rem;
            }

            table th,
            table td {
                padding: 0.75rem;
            }

            .card {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>Student Enrollment</h1>
            <a href="/teacher/dashboard.php">Back to Dashboard</a>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= count($enrollments) ?></div>
                <div class="stat-label">Total Enrollments</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                    $uniqueStudents = count(array_unique(array_column($enrollments, 'student_id')));
                    echo $uniqueStudents;
                    ?>
                </div>
                <div class="stat-label">Unique Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                    $uniqueClasses = count(array_unique(array_column($enrollments, 'class_subject_id')));
                    echo $uniqueClasses;
                    ?>
                </div>
                <div class="stat-label">Classes</div>
            </div>
        </div>

        <!-- Enrollment Table Card -->
        <div class="card">
            <h2>All Student Enrollments</h2>

            <!-- Selection Info -->
            <div class="selection-info" id="selectionInfo">
                <span id="selectionText">0 items selected</span>
            </div>

            <!-- Search and Filter Section -->
            <div class="search-section">
                <div class="search-row">
                    <div class="search-box">
                        <input 
                            type="text" 
                            id="searchInput" 
                            placeholder="Search by student name or class subject..." 
                            onkeyup="filterTable()"
                        >
                    </div>
                </div>

                <div class="filter-buttons">
                    <button class="btn btn-success" onclick="selectAll()">Select All</button>
                    <button class="btn btn-danger" onclick="deselectAll()">Deselect All</button>
                    <button class="btn btn-secondary" onclick="resetFilters()">Reset Filters</button>
                </div>
            </div>

            <!-- Results Counter -->
            <div class="results-counter">
                <div>
                    Showing <strong id="visibleCount"><?= count($enrollments) ?></strong> of 
                    <strong id="totalCount"><?= count($enrollments) ?></strong> enrollments
                </div>
            </div>

            <?php if (empty($enrollments)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <p>No enrollments found</p>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table id="enrollmentTable">
                        <thead>
                            <tr>
                                <th style="width: 50px;">
                                    <div class="checkbox-wrapper">
                                        <input 
                                            type="checkbox" 
                                            id="selectAllCheckbox" 
                                            class="table-checkbox"
                                            onchange="toggleSelectAll(this)"
                                        >
                                    </div>
                                </th>
                                <th style="width: 80px;">ID</th>
                                <th>Student Name</th>
                                <th>Class & Subject</th>
                                <th>Enrolled At</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php foreach ($enrollments as $enrollment): ?>
                                <tr class="enrollment-row" data-enrollment-id="<?= (int)$enrollment['id'] ?>">
                                    <td>
                                        <input 
                                            type="checkbox" 
                                            class="table-checkbox row-checkbox"
                                            onchange="updateSelectionInfo(); updateHeaderCheckbox()"
                                            data-student-id="<?= (int)$enrollment['student_id'] ?>"
                                            data-class-id="<?= (int)$enrollment['class_subject_id'] ?>"
                                        >
                                    </td>
                                    <td>
                                        <span class="id-cell"><?= (int)$enrollment['id'] ?></span>
                                    </td>
                                    <td>
                                        <span class="student-name">
                                            <?= htmlspecialchars($enrollment['student_name']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="class-subject">
                                            <?= htmlspecialchars($enrollment['class_subject_name']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="enrolled-at">
                                            <?= date('M d, Y', strtotime($enrollment['enrolled_at'])) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Filter table by search input
        function filterTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('enrollmentTable');
            const tbody = table.getElementsByTagName('tbody')[0];
            const rows = tbody.getElementsByTagName('tr');
            let visibleCount = 0;

            for (let i = 0; i < rows.length; i++) {
                const studentName = rows[i].getElementsByTagName('td')[2].textContent.toLowerCase();
                const classSubject = rows[i].getElementsByTagName('td')[3].textContent.toLowerCase();

                if (studentName.includes(filter) || classSubject.includes(filter)) {
                    rows[i].style.display = '';
                    visibleCount++;
                } else {
                    rows[i].style.display = 'none';
                }
            }

            updateCounter(visibleCount);
        }

        // Select all visible rows
        function selectAll() {
            const tbody = document.getElementById('tableBody');
            const checkboxes = tbody.querySelectorAll('input.row-checkbox');
            
            checkboxes.forEach(checkbox => {
                if (checkbox.closest('tr').style.display !== 'none') {
                    checkbox.checked = true;
                }
            });

            updateSelectionInfo();
            updateHeaderCheckbox();
        }

        // Deselect all rows
        function deselectAll() {
            const checkboxes = document.querySelectorAll('input.row-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });

            updateSelectionInfo();
            updateHeaderCheckbox();
        }

        // Toggle header checkbox
        function toggleSelectAll(checkbox) {
            const tbody = document.getElementById('tableBody');
            const checkboxes = tbody.querySelectorAll('input.row-checkbox');
            
            if (checkbox.checked) {
                checkboxes.forEach(cb => {
                    if (cb.closest('tr').style.display !== 'none') {
                        cb.checked = true;
                    }
                });
            } else {
                checkboxes.forEach(cb => cb.checked = false);
            }

            updateSelectionInfo();
        }

        // Update selection info
        function updateSelectionInfo() {
            const checkboxes = document.querySelectorAll('input.row-checkbox:checked');
            const selectionInfo = document.getElementById('selectionInfo');
            const selectionText = document.getElementById('selectionText');
            const count = checkboxes.length;

            if (count > 0) {
                selectionInfo.classList.add('active');
                selectionText.textContent = count + ' item' + (count !== 1 ? 's' : '') + ' selected';
            } else {
                selectionInfo.classList.remove('active');
            }

            // Update row backgrounds
            document.querySelectorAll('tr.enrollment-row').forEach(row => {
                const checkbox = row.querySelector('input.row-checkbox');
                if (checkbox.checked) {
                    row.classList.add('selected');
                } else {
                    row.classList.remove('selected');
                }
            });
        }

        // Update header checkbox state
        function updateHeaderCheckbox() {
            const allCheckboxes = document.querySelectorAll('input.row-checkbox');
            const checkedCheckboxes = document.querySelectorAll('input.row-checkbox:checked');
            const headerCheckbox = document.getElementById('selectAllCheckbox');

            if (checkedCheckboxes.length === allCheckboxes.length && allCheckboxes.length > 0) {
                headerCheckbox.checked = true;
                headerCheckbox.indeterminate = false;
            } else if (checkedCheckboxes.length > 0) {
                headerCheckbox.indeterminate = true;
            } else {
                headerCheckbox.checked = false;
                headerCheckbox.indeterminate = false;
            }
        }

        // Reset filters
        function resetFilters() {
            document.getElementById('searchInput').value = '';
            deselectAll();
            filterTable();
        }

        // Update counter
        function updateCounter(visibleCount) {
            const total = document.querySelectorAll('input.row-checkbox').length;
            document.getElementById('visibleCount').textContent = visibleCount;
            document.getElementById('totalCount').textContent = total;
        }

        // Initialize
        window.addEventListener('load', function() {
            updateCounter(document.querySelectorAll('tr.enrollment-row:not([style*="display: none"])').length);
        });
    </script>
</body>
</html>
