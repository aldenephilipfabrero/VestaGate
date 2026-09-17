<?php
header('Location: gate_scanner.php');
exit;

// Get dashboard stats
$today = date('Y-m-d');
$totalStudents = $conn->query("SELECT COUNT(*) as cnt FROM students")->fetch_assoc()['cnt'];
$totalEntriesToday = $conn->query("SELECT COUNT(*) as cnt FROM entry_logs WHERE entry_date = '$today'")->fetch_assoc()['cnt'];
$pendingTickets = $conn->query("SELECT COUNT(*) as cnt FROM violation_tickets WHERE status = 'pending'")->fetch_assoc()['cnt'];
$complianceToday = $conn->query("SELECT COUNT(*) as cnt FROM entry_logs WHERE entry_date = '$today' AND overall_status = 'compliant'")->fetch_assoc()['cnt'];
$complianceRate = $totalEntriesToday > 0 ? round(($complianceToday / $totalEntriesToday) * 100, 1) : 100;
$adminAlerts = [];
$alertsTableResult = $conn->query("SHOW TABLES LIKE 'admin_notifications'");
if ($alertsTableResult && $alertsTableResult->num_rows > 0) {
    $alertsResult = $conn->query("SELECT * FROM admin_notifications WHERE is_read = 0 AND notification_type != 'parent_summon' ORDER BY created_at DESC LIMIT 20");
    if ($alertsResult) {
        while ($alert = $alertsResult->fetch_assoc()) {
            $adminAlerts[] = $alert;
        }
    }
}

// Get selected student if any
$selectedStudent = null;
$violations = [];
$todayViolations = [];

if (isset($_GET['student_id'])) {
    $sid = intval($_GET['student_id']);
    $result = $conn->query("SELECT * FROM students WHERE id = $sid");
    $selectedStudent = $result->fetch_assoc();
    
    if ($selectedStudent) {
        // Get all violations for this student
        $vResult = $conn->query("SELECT * FROM violations WHERE student_id = $sid ORDER BY violation_date DESC");
        while ($v = $vResult->fetch_assoc()) {
            $violations[] = $v;
        }
        
        // Get today's violations
        $tvResult = $conn->query("SELECT * FROM violations WHERE student_id = $sid AND violation_date = '$today'");
        while ($tv = $tvResult->fetch_assoc()) {
            $todayViolations[] = $tv;
        }
    }
}

// Get all students
$students = $conn->query("SELECT s.*, (SELECT COUNT(*) FROM violations v WHERE v.student_id = s.id) as violation_count FROM students s ORDER BY s.id");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Dress Code & ID Compliance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background:
                linear-gradient(135deg, rgba(11, 15, 22, 0.7), rgba(20, 24, 31, 0.75)),
                url('background.png') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #1f2937;
        }

        .main-container {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 20px;
            padding: 20px;
            min-height: 100vh;
            width: 100%;
            max-width: none;
            margin: 0;
        }

        .left-panel {
            background: rgba(255, 255, 255, 0.96);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .right-panel {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .info-card {
            background: #1a1a1a;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .info-card-header {
            background: linear-gradient(135deg, #8B1538, #5a1a2a);
            color: white;
            padding: 10px 15px;
            font-weight: bold;
            text-align: center;
        }

        .info-card-body {
            padding: 15px;
            color: white;
        }

        .info-row {
            margin-bottom: 8px;
            font-size: 14px;
        }

        .info-label {
            color: #bdc3c7;
        }

        .search-box {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 15px;
            gap: 10px;
            flex-wrap: wrap;
        }

        .search-box input {
            padding: 8px 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            width: 200px;
            background: #fff;
            color: #1f2937;
        }

        .action-btn {
            padding: 8px 18px;
            border: 2px solid #8B1538;
            background: white;
            color: #8B1538;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
        }

        .action-btn:hover {
            background: #8B1538;
            color: white;
        }

        .view-history-btn {
            margin-left: auto;
            background: #8B1538;
            color: white;
        }

        .view-history-btn:hover {
            background: #5a1a2a;
        }

        .student-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 13px;
        }

        .student-table th {
            background: #f8f9fa;
            padding: 10px 8px;
            text-align: left;
            border-bottom: 2px solid #8B1538;
            font-weight: 700;
            color: #8B1538;
            font-size: 12px;
            vertical-align: middle;
        }

        .student-table td {
            padding: 10px 8px;
            border-bottom: 1px solid #dee2e6;
            color: #1f2937;
            vertical-align: middle;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .student-table th:nth-child(1), .student-table td:nth-child(1) { width: 38px; }
        .student-table th:nth-child(2), .student-table td:nth-child(2) { width: 24px; }
        .student-table th:nth-child(3), .student-table td:nth-child(3) { width: 110px; }
        .student-table th:nth-child(4), .student-table td:nth-child(4) { width: 180px; }
        .student-table th:nth-child(5), .student-table td:nth-child(5) { width: 80px; }
        .student-table th:nth-child(6), .student-table td:nth-child(6) { width: 90px; }
        .student-table th:nth-child(7), .student-table td:nth-child(7) { width: 120px; }
        .student-table th:nth-child(8), .student-table td:nth-child(8) { width: 90px; }

        .student-table tr {
            cursor: pointer;
            transition: background 0.2s;
        }

        .student-table tr:hover {
            background: #f1f3f5;
        }

        .student-table tr.selected {
            background: #e8f4f8;
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }

        .status-active {
            background: #27ae60;
        }

        .violation-count {
            color: #e74c3c;
            font-weight: bold;
        }

        .violation-count.zero {
            color: #333333;
        }

        .violation-table {
            width: 100%;
            font-size: 12px;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .violation-table td {
            padding: 6px 4px;
            border-bottom: 1px solid #5a1a2a;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .violation-table td:first-child {
            width: 90px;
        }

        #adminAlertList,
        #violationsBody,
        #todayViolationsBody {
            max-height: 260px;
            overflow-y: auto;
            padding-right: 6px;
        }

        .violation-date {
            color: #95a5a6;
            width: 90px;
        }

        .modal-content {
            border-radius: 10px;
        }

        .modal-header {
            background: #8B1538;
            color: white;
            border-radius: 10px 10px 0 0;
        }

        .modal-header .btn-close {
            filter: invert(1);
        }

        .alert-message {
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .top-bar {
            background: rgba(139, 21, 56, 0.95);
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .top-bar h1 {
            color: white;
            font-size: 1.3rem;
            margin: 0;
        }

        .top-bar .team-info {
            color: #888;
            font-size: 0.8rem;
        }

        .nav-buttons {
            display: flex;
            gap: 10px;
        }

        .nav-buttons a {
            padding: 8px 15px;
            background: transparent;
            border: 1px solid #D4AF37;
            color: #D4AF37;
            text-decoration: none;
            border-radius: 5px;
            font-size: 0.85rem;
            transition: all 0.3s;
        }

        .nav-buttons a:hover {
            background: #D4AF37;
            color: #1a1a1a;
        }

        .quick-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .quick-stat {
            background: rgba(255,255,255,0.95);
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
        }

        .quick-stat .value {
            font-size: 1.8rem;
            font-weight: bold;
        }

        .quick-stat .label {
            font-size: 0.8rem;
            color: #666;
        }

        .quick-stat:nth-child(1) .value { color: #8B1538; }
        .quick-stat:nth-child(2) .value { color: #27ae60; }
        .quick-stat:nth-child(3) .value { color: #8B1538; }
        .quick-stat:nth-child(4) .value { color: #D4AF37; }

        .student-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #8B1538;
        }

        .student-table tr.multi-selected {
            background: #d4edda;
        }

        .student-table tr.multi-selected:hover {
            background: #c3e6cb;
        }

        .bulk-actions {
            display: none;
            gap: 10px;
            margin-left: 15px;
            padding-left: 15px;
            border-left: 2px solid #dee2e6;
        }

        .bulk-actions.show {
            display: flex;
        }

        .bulk-btn {
            padding: 8px 15px;
            border: 2px solid #e74c3c;
            background: white;
            color: #e74c3c;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
        }

        .bulk-btn:hover {
            background: #e74c3c;
            color: white;
        }

        .selected-count {
            padding: 8px 15px;
            background: #3498db;
            color: white;
            border-radius: 5px;
            font-weight: 500;
        }

        .alert-item {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
            font-size: 13px;
            line-height: 1.4;
        }

        .alert-item:last-child {
            border-bottom: none;
        }

        .alert-item-content {
            flex: 1;
        }

        .alert-item .meta {
            color: #bdc3c7;
            font-size: 11px;
            margin-top: 3px;
        }

        .alert-remove-btn {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.35);
            color: #f8d7da;
            border-radius: 4px;
            padding: 3px 8px;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .alert-remove-btn:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: #f8d7da;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Top Navigation Bar -->
        <div style="grid-column: 1 / -1;">
            <?php include 'admin_nav.php'; ?>

            <?php if (isset($_GET['password_updated']) && $_GET['password_updated'] == '1'): ?>
                <div class="alert-message alert-success">✓ Admin password changed successfully.</div>
            <?php elseif (isset($_GET['password_error'])): ?>
                <div class="alert-message alert-error">
                    <?php
                    if ($_GET['password_error'] === 'current') {
                        echo '⚠️ Current password is incorrect.';
                    } elseif ($_GET['password_error'] === 'match') {
                        echo '⚠️ New password and confirmation do not match.';
                    } elseif ($_GET['password_error'] === 'short') {
                        echo '⚠️ New password must be at least 6 characters.';
                    } else {
                        echo '⚠️ Unable to update the password.';
                    }
                    ?>
                </div>
            <?php endif; ?>
            
            <div class="quick-stats">
                <div class="quick-stat">
                    <div class="value"><?php echo $totalStudents; ?></div>
                    <div class="label">Total Students</div>
                </div>
                <div class="quick-stat">
                    <div class="value"><?php echo $totalEntriesToday; ?></div>
                    <div class="label">Entries Today</div>
                </div>
                <div class="quick-stat">
                    <div class="value"><?php echo $pendingTickets; ?></div>
                    <div class="label">Pending Tickets</div>
                </div>
                <div class="quick-stat">
                    <div class="value"><?php echo $complianceRate; ?>%</div>
                    <div class="label">Compliance Rate</div>
                </div>
            </div>
        </div>
        
        <!-- Left Panel - Student Table -->
        <div class="left-panel">
            <?php if(isset($_GET['error'])): ?>
                <div class="alert-message alert-error">
                    <?php 
                    if($_GET['error'] == 'duplicate_name') echo '⚠️ A student with this name already exists!';
                    elseif($_GET['error'] == 'duplicate_id') echo '⚠️ A student with this ID already exists!';
                    else echo '⚠️ An error occurred.';
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if(isset($_GET['success'])): ?>
                <div class="alert-message alert-success">✓ Student added successfully!</div>
            <?php endif; ?>
            
            <?php if(isset($_GET['deleted'])): ?>
                <div class="alert-message alert-success">✓ <?php echo intval($_GET['deleted']); ?> student(s) deleted successfully!</div>
            <?php endif; ?>
            
            <?php if(isset($_GET['grade_updated'])): ?>
                <div class="alert-message alert-success">✓ Grade updated for <?php echo intval($_GET['grade_updated']); ?> student(s)!</div>
            <?php endif; ?>
            
            <div class="search-box">
                <select id="gradeFilter" onchange="filterByGrade()" style="padding: 8px 15px; border: 1px solid #ccc; border-radius: 5px; margin-right: 10px;">
                    <option value="">All Grades</option>
                    <option value="7">Grade 7</option>
                    <option value="8">Grade 8</option>
                    <option value="9">Grade 9</option>
                    <option value="10">Grade 10</option>
                    <option value="11">Grade 11</option>
                    <option value="12">Grade 12</option>
                </select>
                <input type="text" id="searchInput" placeholder="Search" onkeyup="searchStudents()">
            </div>
            
            <div class="action-buttons">
                <button class="action-btn" data-bs-toggle="modal" data-bs-target="#addStudentModal">ADD</button>
                <button class="action-btn" onclick="selectStudent()">SELECT</button>
                <button class="action-btn" onclick="updateStudent()">UPDATE</button>
                <button class="action-btn" onclick="deleteStudent()">DELETE</button>
                <button class="action-btn view-history-btn" onclick="viewHistory()">VIEW HISTORY</button>
                
                <div class="bulk-actions" id="bulkActions">
                    <span class="selected-count" id="selectedCount">0 selected</span>
                    <button class="action-btn" onclick="showBulkGradeModal()" style="border-color: #3498db; color: #3498db;">UPDATE GRADE</button>
                    <button class="bulk-btn" onclick="deleteSelectedStudents()">DELETE SELECTED</button>
                    <button class="action-btn" onclick="clearSelection()">CLEAR</button>
                </div>
            </div>
            
            <table class="student-table" id="studentTable">
                <thead>
                    <tr>
                        <th><input type="checkbox" class="student-checkbox" id="selectAllCheckbox" onclick="toggleSelectAll(this)"></th>
                        <th></th>
                        <th>ID Number</th>
                        <th>Student</th>
                        <th>Grade</th>
                        <th>Section</th>
                        <th>RFID</th>
                        <th>Violations</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $students->fetch_assoc()): ?>
                    <tr onclick="selectRow(this, <?php echo $row['id']; ?>)" data-id="<?php echo $row['id']; ?>" 
                        class="<?php echo (isset($_GET['student_id']) && $_GET['student_id'] == $row['id']) ? 'selected' : ''; ?>">
                        <td onclick="event.stopPropagation();">
                            <input type="checkbox" class="student-checkbox row-checkbox" data-id="<?php echo $row['id']; ?>" onclick="toggleRowSelection(this, <?php echo $row['id']; ?>)">
                        </td>
                        <td>
                            <?php if(isset($_GET['student_id']) && $_GET['student_id'] == $row['id']): ?>
                                <span class="status-indicator status-active"></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['grade']); ?></td>
                        <td><?php echo htmlspecialchars($row['section']); ?></td>
                        <td><?php echo htmlspecialchars($row['rfid_tag']); ?></td>
                        <td class="violation-count <?php echo $row['violation_count'] == 0 ? 'zero' : ''; ?>">
                            <?php echo $row['violation_count']; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Right Panel - Info Cards -->
        <div class="right-panel">
            <!-- Student Info Card -->
            <div class="info-card">
                <div class="info-card-header">Info</div>
                <div class="info-card-body" id="studentInfoBody">
                    <div class="info-row" style="color: #95a5a6;">Select a student to view info</div>
                </div>
            </div>

            <!-- Admin Notifications Card -->
            <div class="info-card">
                <div class="info-card-header">Admin Alert</div>
                <div class="info-card-body" id="adminAlertList">
                    <?php if (!empty($adminAlerts)): ?>
                        <?php foreach ($adminAlerts as $alert): ?>
                            <div class="alert-item">
                                <div class="alert-item-content">
                                    <?php echo htmlspecialchars($alert['message']); ?>
                                    <div class="meta"><?php echo htmlspecialchars($alert['created_at']); ?></div>
                                </div>
                                <form method="POST" action="dismiss_admin_alert.php" onsubmit="return confirm('Remove this admin alert?');">
                                    <input type="hidden" name="alert_id" value="<?php echo (int)$alert['id']; ?>">
                                    <button type="submit" class="alert-remove-btn">Remove</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="color: #95a5a6; font-size: 13px;">No unread admin notifications</div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Violations Card -->
            <div class="info-card">
                <div class="info-card-header">Violation</div>
                <div class="info-card-body" id="violationsBody">
                    <div style="color: #95a5a6; font-size: 13px;">No violations recorded</div>
                </div>
            </div>
            
            <!-- Today's Violations Card -->
            <div class="info-card">
                <div class="info-card-header">Today's Violation</div>
                <div class="info-card-body" id="todayViolationsBody">
                    <div style="color: #95a5a6; font-size: 13px;">Select a student</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Student Modal -->
    <div class="modal fade" id="addStudentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="add_student.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Student ID</label>
                            <input type="text" name="student_id" class="form-control" placeholder="e.g., STU103" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Grade</label>
                            <select name="grade" id="add_grade" class="form-select" onchange="loadSections('add')" required>
                                <option value="">Select Grade</option>
                                <option value="7">Grade 7</option>
                                <option value="8">Grade 8</option>
                                <option value="9">Grade 9</option>
                                <option value="10">Grade 10</option>
                                <option value="11">Grade 11</option>
                                <option value="12">Grade 12</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Section</label>
                            <select name="section" id="add_section" class="form-select" required>
                                <option value="">Select Grade First</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">RFID Tag ID</label>
                            <input type="text" name="rfid_tag" class="form-control rfid-input" placeholder="Scan or type RFID ID" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Parent SMS Number</label>
                            <input type="text" name="parent_phone" class="form-control" placeholder="e.g., +63XXXXXXXXXX">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="submit" class="btn btn-primary">Save Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Update Student Modal -->
    <div class="modal fade" id="updateStudentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="update_student.php" method="POST">
                    <input type="hidden" name="id" id="update_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Student ID</label>
                            <input type="text" name="student_id" id="update_student_id" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" id="update_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Grade</label>
                            <select name="grade" id="update_grade" class="form-select" onchange="loadSections('update')" required>
                                <option value="">Select Grade</option>
                                <option value="7">Grade 7</option>
                                <option value="8">Grade 8</option>
                                <option value="9">Grade 9</option>
                                <option value="10">Grade 10</option>
                                <option value="11">Grade 11</option>
                                <option value="12">Grade 12</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Section</label>
                            <select name="section" id="update_section" class="form-select" required>
                                <option value="">Select Grade First</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">RFID Tag ID</label>
                            <input type="text" name="rfid_tag" id="update_rfid" class="form-control rfid-input" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Parent SMS Number</label>
                            <input type="text" name="parent_phone" id="update_parent_phone" class="form-control" placeholder="e.g., +63XXXXXXXXXX">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="submit" class="btn btn-primary">Update Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bulk Update Grade Modal -->
    <div class="modal fade" id="bulkGradeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Grade for Selected Students</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="bulk_update_grade.php" method="POST">
                    <input type="hidden" name="student_ids" id="bulk_student_ids">
                    <div class="modal-body">
                        <p><strong id="bulkGradeCount">0</strong> student(s) selected</p>
                        <p>Current Grade: <strong id="bulkCurrentGrade">-</strong></p>
                        <div class="mb-3">
                            <label class="form-label">New Grade</label>
                            <select name="grade" id="bulk_new_grade" class="form-select" required>
                                <option value="">Select Grade</option>
                                <option value="7">Grade 7</option>
                                <option value="8">Grade 8</option>
                                <option value="9">Grade 9</option>
                                <option value="10">Grade 10</option>
                                <option value="11">Grade 11</option>
                                <option value="12">Grade 12</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="submit" class="btn btn-primary">Update Grade</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="changePasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change Admin Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="update_admin_password.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" minlength="6" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (new URLSearchParams(window.location.search).get('settings') === '1') {
                const modal = new bootstrap.Modal(document.getElementById('changePasswordModal'));
                modal.show();
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedRowId = <?php echo isset($_GET['student_id']) ? $_GET['student_id'] : 'null'; ?>;
        let selectedStudentIds = [];
        
        // Prevent Enter key from submitting form on RFID inputs
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.rfid-input').forEach(function(input) {
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                    }
                });
            });
        });
        
        function selectRow(row, id) {
            // Remove previous selection
            document.querySelectorAll('.student-table tbody tr').forEach(r => {
                r.classList.remove('selected');
                const firstTd = r.querySelector('td:first-child');
                if (firstTd) firstTd.innerHTML = '';
            });
            
            // Add selection to clicked row
            row.classList.add('selected');
            row.querySelector('td:first-child').innerHTML = '<span class="status-indicator status-active"></span>';
            selectedRowId = id;
            
            // Load student info via AJAX (no page reload)
            loadStudentInfo(id);
        }
        
        function loadStudentInfo(id) {
            fetch('get_student_info.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update student info card
                        const student = data.student;
                        document.getElementById('studentInfoBody').innerHTML = `
                            <div class="info-row"><span class="info-label">ID Number :</span> ${student.student_id || ''}</div>
                            <div class="info-row"><span class="info-label">Student :</span> ${student.name || ''}</div>
                            <div class="info-row"><span class="info-label">Grade :</span> ${student.grade || ''}</div>
                            <div class="info-row"><span class="info-label">Section :</span> ${student.section || ''}</div>
                            <div class="info-row"><span class="info-label">RFID :</span> ${student.rfid_tag || ''}</div>
                            <div class="info-row"><span class="info-label">Parent SMS :</span> ${student.parent_phone || ''}</div>
                        `;
                        
                        // Update violations card with images
                        if (data.violations && data.violations.length > 0) {
                            let violationsHtml = '<table class="violation-table">';
                            data.violations.forEach(v => {
                                const imageIndicator = v.has_image ? ' 📷' : '';
                                const clickHandler = v.has_image ? `onclick="viewViolationImage('${v.image_url}', '${v.violation_date}', '${v.violation_type}')" style="cursor:pointer; color: #8B1538; text-decoration: underline;"` : '';
                                violationsHtml += `<tr ${clickHandler}>
                                    <td class="violation-date">${v.violation_date}</td>
                                    <td>${v.violation_type}${imageIndicator}</td>
                                </tr>`;
                            });
                            violationsHtml += '</table>';
                            document.getElementById('violationsBody').innerHTML = violationsHtml;
                        } else {
                            document.getElementById('violationsBody').innerHTML = '<div style="color: #95a5a6; font-size: 13px;">No violations recorded</div>';
                        }
                        
                        // Update today's violations card with images
                        if (data.todayViolations && data.todayViolations.length > 0) {
                            let todayHtml = '<table class="violation-table">';
                            data.todayViolations.forEach(tv => {
                                const imageIndicator = tv.has_image ? ' 📷' : '';
                                const clickHandler = tv.has_image ? `onclick="viewViolationImage('${tv.image_url}', '${tv.violation_date}', '${tv.violation_type}')" style="cursor:pointer; color: #8B1538; text-decoration: underline;"` : '';
                                todayHtml += `<tr ${clickHandler}>
                                    <td>${student.name}</td>
                                    <td>${tv.violation_type}${imageIndicator}</td>
                                </tr>`;
                            });
                            todayHtml += '</table>';
                            document.getElementById('todayViolationsBody').innerHTML = todayHtml;
                        } else {
                            document.getElementById('todayViolationsBody').innerHTML = '<div style="color: #95a5a6; font-size: 13px;">No violations today</div>';
                        }
                    }
                })
                .catch(err => {
                    console.error('Error loading student info:', err);
                });
        }
        
        // Function to view violation image in a modal
        function viewViolationImage(imagePath, violationDate, violationType) {
            const modalHtml = `
                <div class="modal fade" id="violationImageModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Violation Evidence - ${violationDate}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div style="text-align: center; margin-bottom: 15px;">
                                    <img src="${imagePath}" style="max-width: 100%; max-height: 500px; border-radius: 8px; border: 2px solid #8B1538;">
                                </div>
                                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                                    <div><strong>Violation Type:</strong> ${violationType}</div>
                                    <div><strong>Date:</strong> ${violationDate}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove old modal if exists
            const oldModal = document.getElementById('violationImageModal');
            if (oldModal) oldModal.remove();
            
            // Add and show new modal
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            new bootstrap.Modal(document.getElementById('violationImageModal')).show();
            
            // Auto-remove modal from DOM after closing
            document.getElementById('violationImageModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        }
        
        function selectStudent() {
            if (selectedRowId) {
                loadStudentInfo(selectedRowId);
            } else {
                alert('Please select a student first');
            }
        }
        
        function updateStudent() {
            if (!selectedRowId) {
                alert('Please select a student first');
                return;
            }
            
            // Fetch student data and populate modal
            fetch('get_student.php?id=' + selectedRowId)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('update_id').value = data.id;
                    document.getElementById('update_student_id').value = data.student_id;
                    document.getElementById('update_name').value = data.name;
                    document.getElementById('update_grade').value = data.grade;
                    document.getElementById('update_rfid').value = data.rfid_tag;
                    document.getElementById('update_parent_phone').value = data.parent_phone || '';
                    
                    // Load sections for the selected grade, then set the section value
                    loadSections('update', data.section);
                    
                    new bootstrap.Modal(document.getElementById('updateStudentModal')).show();
                })
                .catch(err => {
                    alert('Error loading student data');
                });
        }
        
        function deleteStudent() {
            if (!selectedRowId) {
                alert('Please select a student first');
                return;
            }
            
            if (confirm('Are you sure you want to delete this student?')) {
                window.location.href = 'delete_student.php?id=' + selectedRowId;
            }
        }
        
        function viewHistory() {
            if (!selectedRowId) {
                alert('Please select a student first');
                return;
            }
            window.location.href = 'history.php?student_id=' + selectedRowId;
        }
        
        function searchStudents() {
            applyFilters();
        }
        
        function filterByGrade() {
            applyFilters();
        }
        
        function applyFilters() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const gradeFilter = document.getElementById('gradeFilter').value;
            const rows = document.querySelectorAll('#studentTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const gradeCell = row.querySelector('td:nth-child(5)'); // Grade column
                const rowGrade = gradeCell ? gradeCell.textContent.trim() : '';
                
                const matchesSearch = text.includes(searchInput);
                const matchesGrade = gradeFilter === '' || rowGrade === gradeFilter;
                
                row.style.display = (matchesSearch && matchesGrade) ? '' : 'none';
            });
            
            // Clear selection when filters change
            clearSelection();
        }
        
        // Multi-select functions
        function toggleRowSelection(checkbox, id) {
            const row = checkbox.closest('tr');
            if (checkbox.checked) {
                if (!selectedStudentIds.includes(id)) {
                    selectedStudentIds.push(id);
                }
                row.classList.add('multi-selected');
            } else {
                selectedStudentIds = selectedStudentIds.filter(sid => sid !== id);
                row.classList.remove('multi-selected');
            }
            updateBulkActions();
        }
        
        function toggleSelectAll(checkbox) {
            const rows = document.querySelectorAll('#studentTable tbody tr');
            const checkboxes = document.querySelectorAll('.row-checkbox');
            
            selectedStudentIds = [];
            
            checkboxes.forEach(cb => {
                const row = cb.closest('tr');
                // Only select visible rows
                if (row.style.display !== 'none') {
                    cb.checked = checkbox.checked;
                    if (checkbox.checked) {
                        const id = parseInt(cb.dataset.id);
                        selectedStudentIds.push(id);
                        row.classList.add('multi-selected');
                    } else {
                        row.classList.remove('multi-selected');
                    }
                }
            });
            updateBulkActions();
        }
        
        function updateBulkActions() {
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            
            if (selectedStudentIds.length > 0) {
                bulkActions.classList.add('show');
                selectedCount.textContent = selectedStudentIds.length + ' selected';
            } else {
                bulkActions.classList.remove('show');
            }
            
            // Update select all checkbox state
            const allCheckboxes = document.querySelectorAll('.row-checkbox');
            const visibleCheckboxes = Array.from(allCheckboxes).filter(cb => cb.closest('tr').style.display !== 'none');
            const checkedCount = visibleCheckboxes.filter(cb => cb.checked).length;
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            
            if (visibleCheckboxes.length > 0 && checkedCount === visibleCheckboxes.length) {
                selectAllCheckbox.checked = true;
                selectAllCheckbox.indeterminate = false;
            } else if (checkedCount > 0) {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = true;
            } else {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = false;
            }
        }
        
        function clearSelection() {
            selectedStudentIds = [];
            document.querySelectorAll('.row-checkbox').forEach(cb => {
                cb.checked = false;
                cb.closest('tr').classList.remove('multi-selected');
            });
            document.getElementById('selectAllCheckbox').checked = false;
            document.getElementById('selectAllCheckbox').indeterminate = false;
            updateBulkActions();
        }
        
        function deleteSelectedStudents() {
            if (selectedStudentIds.length === 0) {
                alert('Please select students first');
                return;
            }
            
            if (confirm('Are you sure you want to delete ' + selectedStudentIds.length + ' student(s)?')) {
                window.location.href = 'delete_student.php?ids=' + selectedStudentIds.join(',');
            }
        }
        
        // Load sections based on selected grade
        function loadSections(prefix, preselectedSection = null) {
            const gradeSelect = document.getElementById(prefix + '_grade');
            const sectionSelect = document.getElementById(prefix + '_section');
            const grade = gradeSelect.value;
            
            // Clear current options
            sectionSelect.innerHTML = '<option value="">Loading...</option>';
            
            if (!grade) {
                sectionSelect.innerHTML = '<option value="">Select Grade First</option>';
                return;
            }
            
            fetch('get_sections.php?grade=' + grade)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        sectionSelect.innerHTML = '<option value="">Select Section</option>';
                        data.sections.forEach(section => {
                            const option = document.createElement('option');
                            option.value = section;
                            option.textContent = section;
                            if (preselectedSection && section === preselectedSection) {
                                option.selected = true;
                            }
                            sectionSelect.appendChild(option);
                        });
                    } else {
                        sectionSelect.innerHTML = '<option value="">No sections available</option>';
                    }
                })
                .catch(error => {
                    console.error('Error loading sections:', error);
                    sectionSelect.innerHTML = '<option value="">Error loading sections</option>';
                });
        }
        
        // Reset Add Student form when modal opens
        document.getElementById('addStudentModal').addEventListener('show.bs.modal', function() {
            document.getElementById('add_grade').value = '';
            document.getElementById('add_section').innerHTML = '<option value="">Select Grade First</option>';
            const parentPhoneInput = document.querySelector('#addStudentModal input[name="parent_phone"]');
            if (parentPhoneInput) {
                parentPhoneInput.value = '';
            }
        });
        
        function showBulkGradeModal() {
            if (selectedStudentIds.length === 0) {
                alert('Please select students first');
                return;
            }
            
            // Check if all selected students have the same grade
            let grades = [];
            selectedStudentIds.forEach(id => {
                const row = document.querySelector(`tr[data-id="${id}"]`);
                if (row) {
                    const gradeCell = row.querySelector('td:nth-child(5)'); // Grade column
                    if (gradeCell) {
                        grades.push(gradeCell.textContent.trim());
                    }
                }
            });
            
            // Check if all grades are the same
            const uniqueGrades = [...new Set(grades)];
            if (uniqueGrades.length > 1) {
                alert('Cannot update grade: Selected students have different grades (' + uniqueGrades.join(', ') + ').\nPlease select students with the same current grade.');
                return;
            }
            
            document.getElementById('bulk_student_ids').value = selectedStudentIds.join(',');
            document.getElementById('bulkGradeCount').textContent = selectedStudentIds.length;
            document.getElementById('bulk_new_grade').value = '';
            
            // Show current grade info in modal
            const currentGrade = uniqueGrades[0] || 'N/A';
            document.getElementById('bulkCurrentGrade').textContent = currentGrade;
            
            new bootstrap.Modal(document.getElementById('bulkGradeModal')).show();
        }
    </script>
</body>
</html>