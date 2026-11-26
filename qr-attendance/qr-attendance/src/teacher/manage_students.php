<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_teacher();

$teacherId = (int)$_SESSION['teacher_id'];
$pdo = db();

$message = '';
$messageType = '';

// Handle add/remove student
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $classSubjectId = (int)($_POST['class_subject_id'] ?? 0);
    $studentId = (int)($_POST['student_id'] ?? 0);
    $studentIds = $_POST['student_ids'] ?? [];

    if ($action === 'add_student' && $classSubjectId > 0 && $studentId > 0) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO student_enrollment (student_id, class_subject_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$studentId, $classSubjectId]);
            $message = 'Student added to class successfully';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Student already enrolled in this class';
            $messageType = 'error';
        }
    } elseif ($action === 'add_multiple' && $classSubjectId > 0 && !empty($studentIds)) {
        $addedCount = 0;
        $skippedCount = 0;
        
        foreach ($studentIds as $id) {
            $id = (int)$id;
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO student_enrollment (student_id, class_subject_id)
                    VALUES (?, ?)
                ");
                $stmt->execute([$id, $classSubjectId]);
                $addedCount++;
            } catch (Exception $e) {
                $skippedCount++;
            }
        }
        
        $message = $addedCount . ' student' . ($addedCount !== 1 ? 's' : '') . ' added successfully';
        if ($skippedCount > 0) {
            $message .= ' (' . $skippedCount . ' already enrolled)';
        }
        $messageType = 'success';
    } elseif ($action === 'remove_student' && $classSubjectId > 0 && $studentId > 0) {
        try {
            $stmt = $pdo->prepare("
                DELETE FROM student_enrollment 
                WHERE student_id = ? AND class_subject_id = ?
            ");
            $stmt->execute([$studentId, $classSubjectId]);
            $message = 'Student removed from class';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Error removing student';
            $messageType = 'error';
        }
    }
}

// Get all classes
$stmt = $pdo->query("SELECT id, class_name, subject_name FROM class_subject ORDER BY class_name, subject_name");
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all students
$stmt = $pdo->query("SELECT id, student_no, full_name FROM student WHERE is_active = TRUE ORDER BY full_name");
$allStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected class details
$selectedClassId = (int)($_GET['class_id'] ?? ($classes[0]['id'] ?? 0));
$selectedClass = null;
$enrolledStudents = [];
$enrolledCount = 0;

if ($selectedClassId) {
    // Get class info
    $stmt = $pdo->prepare("SELECT * FROM class_subject WHERE id = ?");
    $stmt->execute([$selectedClassId]);
    $selectedClass = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get enrolled students
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_no, s.full_name, s.email, s.phone
        FROM student s
        JOIN student_enrollment se ON se.student_id = s.id
        WHERE se.class_subject_id = ?
        ORDER BY s.student_no
    ");
    $stmt->execute([$selectedClassId]);
    $enrolledStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $enrolledCount = count($enrolledStudents);
}

// Get available students (not enrolled in this class)
$availableStudents = [];
$enrolledIds = array_column($enrolledStudents, 'id');
foreach ($allStudents as $student) {
    if (!in_array($student['id'], $enrolledIds)) {
        $availableStudents[] = $student;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Student Enrollment</title>
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

        .alert {
            padding: 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            display: none;
            font-size: 0.95rem;
            border-left: 4px solid;
            animation: slideDown 0.3s ease;
        }

        .alert.show {
            display: block;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-color: #10b981;
        }

        .alert-error {
            background: #fee2e2;
            color: #7f1d1d;
            border-color: #dc2626;
        }

        .class-selector {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .class-selector h3 {
            margin-bottom: 1rem;
            color: #111827;
            font-size: 1rem;
            font-weight: 600;
        }

        .class-selector select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            font-size: 0.95rem;
            background: white;
            cursor: pointer;
            transition: all 0.2s;
        }

        .class-selector select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
        }

        .card h2 {
            font-size: 1.25rem;
            margin-bottom: 1.5rem;
            color: #111827;
            font-weight: 700;
        }

        /* Search Section */
        .search-section {
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .search-box {
            position: relative;
            margin-bottom: 1rem;
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
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .btn {
            padding: 0.75rem 1.25rem;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-1px);
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        .btn:disabled {
            background: #d1d5db;
            cursor: not-allowed;
            transform: none;
        }

        /* Results Counter */
        .results-counter {
            padding: 0.75rem 1rem;
            background: #f3f4f6;
            border-radius: 0.5rem;
            font-size: 0.9rem;
            color: #6b7280;
            margin-bottom: 1rem;
        }

        /* Student List */
        .student-list {
            max-height: 600px;
            overflow-y: auto;
        }

        .student-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            margin-bottom: 0.75rem;
            transition: all 0.2s;
            background: #f9fafb;
        }

        .student-item:hover {
            background: #f0f1ff;
            border-color: #667eea;
        }

        .student-item.selected {
            background: #dbeafe;
            border-color: #3b82f6;
        }

        .student-checkbox {
            width: 1.25rem;
            height: 1.25rem;
            cursor: pointer;
            accent-color: #667eea;
        }

        .student-info {
            flex: 1;
        }

        .student-no {
            color: #667eea;
            font-weight: 700;
            font-size: 0.9rem;
        }

        .student-name {
            color: #111827;
            font-weight: 600;
            display: block;
            margin-top: 0.25rem;
        }

        /* Enrolled Section */
        .enrolled-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
        }

        .enrolled-count {
            font-size: 1.1rem;
        }

        .enrolled-list {
            max-height: 600px;
            overflow-y: auto;
        }

        .enrolled-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
            border-radius: 0.5rem;
            margin-bottom: 0.75rem;
            transition: all 0.2s;
        }

        .enrolled-item:hover {
            background: #fff0f6;
        }

        .enrolled-info {
            flex: 1;
        }

        .enrolled-no {
            color: #667eea;
            font-weight: 700;
            font-size: 0.9rem;
        }

        .enrolled-name {
            color: #111827;
            font-weight: 600;
            display: block;
            margin-top: 0.25rem;
        }

        .btn-remove {
            background: #dc2626;
            color: white;
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }

        .btn-remove:hover {
            background: #b91c1c;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #9ca3af;
        }

        .empty-state-icon {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
            opacity: 0.5;
        }

        .empty-state p {
            font-size: 0.95rem;
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
            .grid {
                grid-template-columns: 1fr;
            }

            .header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .filter-buttons {
                justify-content: center;
            }

            .enrolled-header {
                flex-direction: column;
                gap: 0.75rem;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>Manage Student Enrollment</h1>
            <a href="/teacher/dashboard.php">Back to Dashboard</a>
        </div>

        <!-- Alert -->
        <?php if ($message): ?>
            <div class="alert <?= $messageType === 'success' ? 'alert-success' : 'alert-error' ?> show">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Class Selector -->
        <div class="class-selector">
            <h3>Select a Class/Subject</h3>
            <form method="get">
                <select name="class_id" onchange="this.form.submit()" required>
                    <option value="">-- Choose Class --</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?= $class['id'] ?>" <?= $class['id'] == $selectedClassId ? 'selected' : '' ?>>
                            <?= htmlspecialchars($class['class_name'] . ' - ' . $class['subject_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if ($selectedClassId && $selectedClass): ?>
            <div class="grid">
                <!-- Add Students Card -->
                <div class="card">
                    <h2>Add Students</h2>

                    <!-- Search Section -->
                    <div class="search-section">
                        <div class="search-box">
                            <input 
                                type="text" 
                                id="searchInput" 
                                placeholder="Search by name or ID..."
                                onkeyup="filterStudents()"
                            >
                        </div>

                        <!-- Filter Buttons -->
                        <div class="filter-buttons">
                            <button class="btn btn-success" onclick="selectAllStudents()">Select All</button>
                            <button class="btn btn-secondary" onclick="deselectAllStudents()">Deselect All</button>
                            <button class="btn btn-secondary" onclick="resetSearch()">Reset</button>
                        </div>

                        <!-- Selection Info -->
                        <div class="selection-info" id="selectionInfo">
                            <span id="selectionText">0 students selected</span>
                        </div>

                        <!-- Results Counter -->
                        <div class="results-counter">
                            Showing <strong id="visibleCount"><?= count($availableStudents) ?></strong> of 
                            <strong id="totalCount"><?= count($availableStudents) ?></strong> available
                        </div>
                    </div>

                    <!-- Enroll Selected Button -->
                    <?php if (!empty($availableStudents)): ?>
                        <form method="post" id="bulkEnrollForm" style="margin-bottom: 1rem;">
                            <input type="hidden" name="action" value="add_multiple">
                            <input type="hidden" name="class_subject_id" value="<?= $selectedClassId ?>">
                            <button type="submit" class="btn btn-success" style="width: 100%;" id="enrollBtn" disabled>
                                Enroll Selected Students
                            </button>
                        </form>
                    <?php endif; ?>

                    <!-- Student List -->
                    <?php if (empty($availableStudents)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">✓</div>
                            <p>All students are already enrolled in this class</p>
                        </div>
                    <?php else: ?>
                        <div class="student-list" id="studentList">
                            <?php foreach ($availableStudents as $student): ?>
                                <label class="student-item" id="student-<?= $student['id'] ?>">
                                    <input 
                                        type="checkbox" 
                                        class="student-checkbox" 
                                        name="student_ids[]" 
                                        value="<?= $student['id'] ?>"
                                        form="bulkEnrollForm"
                                        onchange="updateSelection(); updateEnrollBtn(); updateHeaderCheckbox()"
                                    >
                                    <div class="student-info">
                                        <span class="student-no"><?= htmlspecialchars($student['student_no']) ?></span>
                                        <span class="student-name"><?= htmlspecialchars($student['full_name']) ?></span>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Enrolled Students Card -->
                <div class="card">
                    <h2>Enrolled Students</h2>

                    <div class="enrolled-header">
                        <span><?= htmlspecialchars($selectedClass['class_name'] . ' - ' . $selectedClass['subject_name']) ?></span>
                        <span class="enrolled-count"><?= $enrolledCount ?> Enrolled</span>
                    </div>

                    <?php if (empty($enrolledStudents)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">No students</div>
                            <p>Add students from the left panel</p>
                        </div>
                    <?php else: ?>
                        <div class="enrolled-list">
                            <?php foreach ($enrolledStudents as $student): ?>
                                <div class="enrolled-item">
                                    <div class="enrolled-info">
                                        <span class="enrolled-no"><?= htmlspecialchars($student['student_no']) ?></span>
                                        <span class="enrolled-name"><?= htmlspecialchars($student['full_name']) ?></span>
                                    </div>
                                    <form method="post" style="margin: 0;">
                                        <input type="hidden" name="action" value="remove_student">
                                        <input type="hidden" name="class_subject_id" value="<?= $selectedClassId ?>">
                                        <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                                        <button type="submit" class="btn btn-remove" 
                                                onclick="return confirm('Remove this student?')">
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="card" style="text-align: center; padding: 3rem;">
                <div class="empty-state">
                    <div class="empty-state-icon"></div>
                    <p>Select a class from above to manage enrollment</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Filter students based on search
        function filterStudents() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const list = document.getElementById('studentList');
            const items = list.querySelectorAll('.student-item');
            let visibleCount = 0;

            items.forEach(item => {
                const studentNo = item.querySelector('.student-no').textContent.toLowerCase();
                const studentName = item.querySelector('.student-name').textContent.toLowerCase();

                if (studentNo.includes(filter) || studentName.includes(filter)) {
                    item.style.display = '';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            document.getElementById('visibleCount').textContent = visibleCount;
            updateEnrollBtn();
        }

        // Select all visible students
        function selectAllStudents() {
            const list = document.getElementById('studentList');
            const checkboxes = list.querySelectorAll('.student-checkbox');
            
            checkboxes.forEach(checkbox => {
                if (checkbox.closest('.student-item').style.display !== 'none') {
                    checkbox.checked = true;
                }
            });

            updateSelection();
            updateEnrollBtn();
        }

        // Deselect all students
        function deselectAllStudents() {
            const checkboxes = document.querySelectorAll('.student-checkbox');
            checkboxes.forEach(checkbox => checkbox.checked = false);
            updateSelection();
            updateEnrollBtn();
        }

        // Reset search and selections
        function resetSearch() {
            document.getElementById('searchInput').value = '';
            deselectAllStudents();
            filterStudents();
        }

        // Update selection display
        function updateSelection() {
            const checkboxes = document.querySelectorAll('.student-checkbox:checked');
            const selectionInfo = document.getElementById('selectionInfo');
            const selectionText = document.getElementById('selectionText');
            const count = checkboxes.length;

            if (count > 0) {
                selectionInfo.classList.add('active');
                selectionText.textContent = count + ' student' + (count !== 1 ? 's' : '') + ' selected';
            } else {
                selectionInfo.classList.remove('active');
            }

            // Update row backgrounds
            document.querySelectorAll('.student-item').forEach(item => {
                const checkbox = item.querySelector('.student-checkbox');
                if (checkbox.checked) {
                    item.classList.add('selected');
                } else {
                    item.classList.remove('selected');
                }
            });
        }

        // Enable/disable enroll button
        function updateEnrollBtn() {
            const checkboxes = document.querySelectorAll('.student-checkbox:checked');
            const btn = document.getElementById('enrollBtn');
            if (btn) {
                btn.disabled = checkboxes.length === 0;
                btn.textContent = checkboxes.length > 0 
                    ? 'Enroll ' + checkboxes.length + ' Student' + (checkboxes.length !== 1 ? 's' : '')
                    : 'Enroll Selected Students';
            }
        }

        // Update header checkbox
        function updateHeaderCheckbox() {
            updateSelection();
        }

        // Initialize
        window.addEventListener('load', function() {
            updateEnrollBtn();
        });
    </script>
</body>
</html>
