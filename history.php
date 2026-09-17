<?php
include 'config.php';

$student = null;
$violations = [];

if (isset($_GET['student_id'])) {
    $sid = intval($_GET['student_id']);
    $result = $conn->query("SELECT * FROM students WHERE id = $sid");
    $student = $result->fetch_assoc();
    
    if ($student) {
        $vResult = $conn->query("SELECT * FROM violations WHERE student_id = $sid ORDER BY violation_date DESC");
        while ($v = $vResult->fetch_assoc()) {
            $violations[] = $v;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Violation History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: url('background.png') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
        }
        
        .history-container {
            max-width: 800px;
            margin: 50px auto;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        
        .header {
            background: linear-gradient(135deg, #8B1538, #5a1a2a);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .back-btn {
            background: #8B1538;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
        }
        
        .back-btn:hover {
            background: #5a1a2a;
            color: white;
        }
    </style>
</head>
<body>
    <div class="history-container">
        <?php if($student): ?>
        <div class="header">
            <h4>Violation History</h4>
            <p class="mb-0"><?php echo htmlspecialchars($student['student_id']); ?> - <?php echo htmlspecialchars($student['name']); ?></p>
            <small>Grade <?php echo $student['grade']; ?> - <?php echo htmlspecialchars($student['section']); ?></small>
        </div>
        
        <?php if(!empty($violations)): ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Violation Type</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($violations as $v): ?>
                <tr>
                    <td><?php echo $v['violation_date']; ?></td>
                    <td><?php echo htmlspecialchars($v['violation_type']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="alert alert-success">No violations recorded for this student.</div>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="alert alert-warning">No student selected.</div>
        <?php endif; ?>
        
        <a href="index.php<?php echo $student ? '?student_id='.$student['id'] : ''; ?>" class="back-btn">← Back to Dashboard</a>
    </div>
</body>
</html>
