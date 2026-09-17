<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'config.php';

$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedGrade = isset($_GET['grade']) && $_GET['grade'] !== '' ? trim($_GET['grade']) : '';
$selectedSection = isset($_GET['section']) && $_GET['section'] !== '' ? trim($_GET['section']) : '';

$studentFilter = '';
if ($selectedGrade !== '') {
    $studentFilter .= " AND s.grade = '" . $conn->real_escape_string($selectedGrade) . "'";
}
if ($selectedSection !== '') {
    $studentFilter .= " AND s.section = '" . $conn->real_escape_string($selectedSection) . "'";
}

$overallSummary = $conn->query("
    SELECT
        COUNT(DISTINCT s.id) AS total_students,
        COUNT(DISTINCT CASE WHEN e.entry_date = '$selectedDate' THEN s.id END) AS present_students
    FROM students s
    LEFT JOIN entry_logs e ON e.student_id = s.id AND e.entry_date = '$selectedDate'
    WHERE 1=1 $studentFilter
")->fetch_assoc();

$totalStudents = (int)($overallSummary['total_students'] ?? 0);
$presentStudents = (int)($overallSummary['present_students'] ?? 0);
$absentStudents = max($totalStudents - $presentStudents, 0);
$attendanceRate = $totalStudents > 0 ? round(($presentStudents / $totalStudents) * 100, 1) : 0;

$gradeSummary = $conn->query("
    SELECT
        s.grade,
        COUNT(DISTINCT s.id) AS total_students,
        COUNT(DISTINCT CASE WHEN e.entry_date = '$selectedDate' THEN s.id END) AS present_students
    FROM students s
    LEFT JOIN entry_logs e ON e.student_id = s.id AND e.entry_date = '$selectedDate'
    WHERE 1=1 $studentFilter
    GROUP BY s.grade
    ORDER BY CAST(s.grade AS UNSIGNED), s.grade
");

$sectionSummary = $conn->query("
    SELECT
        s.grade,
        s.section,
        COUNT(DISTINCT s.id) AS total_students,
        COUNT(DISTINCT CASE WHEN e.entry_date = '$selectedDate' THEN s.id END) AS present_students
    FROM students s
    LEFT JOIN entry_logs e ON e.student_id = s.id AND e.entry_date = '$selectedDate'
    WHERE 1=1 $studentFilter
    GROUP BY s.grade, s.section
    ORDER BY CAST(s.grade AS UNSIGNED), s.section
");

$attendanceSheet = $conn->query("
    SELECT
        s.id AS student_id_pk,
        s.grade,
        s.section,
        s.student_id,
        s.name,
        MAX(CASE WHEN e.entry_date = '$selectedDate' THEN 1 ELSE 0 END) AS present_flag
    FROM students s
    LEFT JOIN entry_logs e ON e.student_id = s.id AND e.entry_date = '$selectedDate'
    WHERE 1=1 $studentFilter
    GROUP BY s.id, s.grade, s.section, s.student_id, s.name
    ORDER BY CAST(s.grade AS UNSIGNED), s.section, s.name
");

$gradeOptions = $conn->query("SELECT DISTINCT grade FROM students ORDER BY CAST(grade AS UNSIGNED), grade");
$sectionOptions = $conn->query("SELECT DISTINCT grade, section FROM students ORDER BY CAST(grade AS UNSIGNED), section");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Monitoring</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            color: white;
        }

        .header {
            background: rgba(139,21,56,0.4);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #8B1538;
        }

        .header h1 {
            margin: 0;
            font-size: 1.5rem;
            color: #D4AF37;
        }

        .nav-links a {
            color: #888;
            text-decoration: none;
            margin-left: 20px;
            padding: 8px 15px;
            border-radius: 5px;
            transition: all 0.3s;
        }

        .nav-links a:hover, .nav-links a.active {
            background: rgba(212,175,55,0.2);
            color: #D4AF37;
        }

        .main-container {
            padding: 20px;
            width: 100%;
            max-width: none;
            margin: 0;
        }

        .filter-bar {
            background: rgba(0,0,0,0.4);
            border-radius: 15px;
            padding: 15px 20px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-bar label {
            color: #cfcfcf;
            font-size: 0.9rem;
            margin-bottom: 0;
        }

        .filter-bar input[type="date"],
        .filter-bar select {
            background: rgba(0,0,0,0.3);
            border: 1px solid #8B1538;
            color: white;
            padding: 8px 12px;
            border-radius: 5px;
        }

        .filter-bar select option {
            color: #000;
        }

        .filter-bar button {
            background: #8B1538;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            cursor: pointer;
        }

        .filter-bar .secondary-btn {
            background: transparent;
            color: #D4AF37;
            border: 1px solid #D4AF37;
        }

        .action-btn {
            background: transparent;
            color: #D4AF37;
            border: 1px solid #D4AF37;
            padding: 8px 18px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .action-btn:hover {
            background: #D4AF37;
            color: #1a1a1a;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: rgba(0,0,0,0.4);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
        }

        .stat-card .value {
            font-size: 2.4rem;
            font-weight: bold;
        }

        .stat-card .label {
            color: #888;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .stat-card.total .value { color: #8B1538; }
        .stat-card.present .value { color: #27ae60; }
        .stat-card.absent .value { color: #e74c3c; }
        .stat-card.rate .value { color: #D4AF37; }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1.3fr;
            gap: 20px;
        }

        .content-section {
            background: rgba(0,0,0,0.4);
            border-radius: 15px;
            padding: 20px;
        }

        .section-title {
            color: #D4AF37;
            font-size: 1.1rem;
            margin-bottom: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px 10px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: left;
        }

        th {
            color: #888;
            font-size: 0.85rem;
            text-transform: uppercase;
            font-weight: 600;
        }

        .attendance-pill {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .attendance-pill.good {
            background: rgba(39, 174, 96, 0.15);
            color: #7ae0a3;
        }

        .attendance-pill.low {
            background: rgba(231, 76, 60, 0.15);
            color: #ff9b93;
        }

        .grade-total {
            font-weight: 700;
            color: #D4AF37;
        }

        @media print {
            .header,
            .filter-bar,
            .stats-row {
                display: none !important;
            }

            body {
                background: #fff;
                color: #000;
            }

            .content-section {
                background: #fff;
                color: #000;
                border: 1px solid #ddd;
                box-shadow: none;
            }

            th, td {
                color: #000;
                border-color: #ddd;
            }
        }
    </style>
</head>
<body>
    <?php include 'admin_nav.php'; ?>

    <div class="main-container">
        <form class="filter-bar" method="GET">
            <label for="date">Attendance Date</label>
            <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($selectedDate); ?>">

            <label for="grade">Year Level</label>
            <select id="grade" name="grade" onchange="updateSectionOptions()">
                <option value="">All Year Levels</option>
                <?php while ($gradeOption = $gradeOptions->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($gradeOption['grade']); ?>" <?php echo ($selectedGrade === $gradeOption['grade']) ? 'selected' : ''; ?>>
                        Grade <?php echo htmlspecialchars($gradeOption['grade']); ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label for="section">Section</label>
            <select id="section" name="section">
                <option value="">All Sections</option>
                <?php
                    $sectionOptionsData = [];
                    while ($sectionOption = $sectionOptions->fetch_assoc()) {
                        $sectionOptionsData[] = $sectionOption;
                    }
                    foreach ($sectionOptionsData as $sectionOption):
                        $selected = ($selectedGrade === $sectionOption['grade'] && $selectedSection === $sectionOption['section']) ? 'selected' : '';
                        if ($selectedGrade !== '' && $sectionOption['grade'] !== $selectedGrade) {
                            continue;
                        }
                ?>
                    <option value="<?php echo htmlspecialchars($sectionOption['section']); ?>" data-grade="<?php echo htmlspecialchars($sectionOption['grade']); ?>" <?php echo $selected; ?>>
                        Grade <?php echo htmlspecialchars($sectionOption['grade']); ?> - <?php echo htmlspecialchars($sectionOption['section']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit">View Attendance</button>
            <button type="button" class="secondary-btn action-btn" onclick="document.getElementById('section').value=''; document.getElementById('grade').value=''; this.form.submit();">Reset</button>
            <button type="button" class="action-btn" onclick="window.print()">Print Sheet</button>
            <button type="button" class="action-btn" onclick="exportAttendanceCsv()">Export Excel</button>
        </form>

        <div class="stats-row">
            <div class="stat-card total">
                <div class="value"><?php echo $totalStudents; ?></div>
                <div class="label">Total Students</div>
            </div>
            <div class="stat-card present">
                <div class="value"><?php echo $presentStudents; ?></div>
                <div class="label">Present</div>
            </div>
            <div class="stat-card absent">
                <div class="value"><?php echo $absentStudents; ?></div>
                <div class="label">Absent</div>
            </div>
            <div class="stat-card rate">
                <div class="value"><?php echo $attendanceRate; ?>%</div>
                <div class="label">Attendance Rate</div>
            </div>
        </div>

        <div class="content-grid">
            <div class="content-section">
                <div class="section-title">By Year Level</div>
                <table>
                    <thead>
                        <tr>
                            <th>Year Level</th>
                            <th>Total</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($gradeSummary && $gradeSummary->num_rows > 0): ?>
                            <?php while ($grade = $gradeSummary->fetch_assoc()): ?>
                                <?php
                                    $gradeTotal = (int)($grade['total_students'] ?? 0);
                                    $gradePresent = (int)($grade['present_students'] ?? 0);
                                    $gradeAbsent = max($gradeTotal - $gradePresent, 0);
                                    $gradeRate = $gradeTotal > 0 ? round(($gradePresent / $gradeTotal) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td class="grade-total">Grade <?php echo htmlspecialchars($grade['grade']); ?></td>
                                    <td><?php echo $gradeTotal; ?></td>
                                    <td><?php echo $gradePresent; ?></td>
                                    <td><?php echo $gradeAbsent; ?></td>
                                    <td>
                                        <span class="attendance-pill <?php echo $gradeRate >= 75 ? 'good' : 'low'; ?>"><?php echo $gradeRate; ?>%</span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="color: #95a5a6;">No student records found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="content-section">
                <div class="section-title">By Section</div>
                <table>
                    <thead>
                        <tr>
                            <th>Year</th>
                            <th>Section</th>
                            <th>Total</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($sectionSummary && $sectionSummary->num_rows > 0): ?>
                            <?php while ($section = $sectionSummary->fetch_assoc()): ?>
                                <?php
                                    $sectionTotal = (int)($section['total_students'] ?? 0);
                                    $sectionPresent = (int)($section['present_students'] ?? 0);
                                    $sectionAbsent = max($sectionTotal - $sectionPresent, 0);
                                    $sectionRate = $sectionTotal > 0 ? round(($sectionPresent / $sectionTotal) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td>Grade <?php echo htmlspecialchars($section['grade']); ?></td>
                                    <td><?php echo htmlspecialchars($section['section']); ?></td>
                                    <td><?php echo $sectionTotal; ?></td>
                                    <td><?php echo $sectionPresent; ?></td>
                                    <td><?php echo $sectionAbsent; ?></td>
                                    <td>
                                        <span class="attendance-pill <?php echo $sectionRate >= 75 ? 'good' : 'low'; ?>"><?php echo $sectionRate; ?>%</span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="color: #95a5a6;">No section records found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="content-section" style="margin-top: 20px;">
            <div class="section-title">Student Attendance Sheet - <?php echo htmlspecialchars(date('F j, Y', strtotime($selectedDate))); ?></div>
            <table id="attendanceSheetTable">
                <thead>
                    <tr>
                        <th>Year Level</th>
                        <th>Section</th>
                        <th>Student ID</th>
                        <th>Student Name</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($attendanceSheet && $attendanceSheet->num_rows > 0): ?>
                        <?php while ($row = $attendanceSheet->fetch_assoc()): ?>
                            <?php
                                $presentFlag = (int)($row['present_flag'] ?? 0);
                                $statusText = $presentFlag === 1 ? 'Present' : 'Absent';
                            ?>
                            <tr>
                                <td>Grade <?php echo htmlspecialchars($row['grade']); ?></td>
                                <td><?php echo htmlspecialchars($row['section']); ?></td>
                                <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo $statusText; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="color: #95a5a6;">No attendance records found for this date.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function updateSectionOptions() {
            const gradeSelect = document.getElementById('grade');
            const sectionSelect = document.getElementById('section');
            const selectedGrade = gradeSelect.value;
            const currentSection = sectionSelect.value;

            Array.from(sectionSelect.options).forEach(option => {
                if (option.value === '') {
                    option.style.display = 'block';
                    return;
                }

                const matchesGrade = !selectedGrade || option.dataset.grade === selectedGrade;
                option.style.display = matchesGrade ? 'block' : 'none';
            });

            if (selectedGrade) {
                const validCurrent = currentSection && Array.from(sectionSelect.options).some(option => option.value === currentSection && option.dataset.grade === selectedGrade);
                if (!validCurrent) {
                    sectionSelect.value = '';
                }
            } else {
                sectionSelect.value = '';
            }
        }

        function exportAttendanceCsv() {
            const table = document.getElementById('attendanceSheetTable');
            if (!table) return;

            const rows = Array.from(table.querySelectorAll('tr')).map(row => {
                return Array.from(row.children).map(cell => {
                    const text = (cell.textContent || '').replace(/\r?\n/g, ' ').trim();
                    return '"' + text.replace(/"/g, '""') + '"';
                }).join(',');
            }).join('\n');

            const csvContent = '\uFEFF' + rows;
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', 'attendance_sheet_<?php echo htmlspecialchars(str_replace('-', '', $selectedDate)); ?>.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateSectionOptions();
        });
    </script>
</body>
</html>
