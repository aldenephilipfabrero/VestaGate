<?php
include 'config.php';

// Date range filter
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Get statistics
$totalEntries = $conn->query("SELECT COUNT(*) as cnt FROM entry_logs WHERE entry_date BETWEEN '$startDate' AND '$endDate'")->fetch_assoc()['cnt'];
$compliantEntries = $conn->query("SELECT COUNT(*) as cnt FROM entry_logs WHERE entry_date BETWEEN '$startDate' AND '$endDate' AND overall_status = 'compliant'")->fetch_assoc()['cnt'];
$totalTickets = $conn->query("SELECT COUNT(*) as cnt FROM violation_tickets WHERE ticket_date BETWEEN '$startDate' AND '$endDate'")->fetch_assoc()['cnt'];
$complianceRate = $totalEntries > 0 ? round(($compliantEntries / $totalEntries) * 100, 1) : 0;

// Daily stats for chart
$dailyStats = $conn->query("
    SELECT entry_date, 
           COUNT(*) as total,
           SUM(CASE WHEN overall_status = 'compliant' THEN 1 ELSE 0 END) as compliant,
           SUM(CASE WHEN overall_status = 'non-compliant' THEN 1 ELSE 0 END) as non_compliant
    FROM entry_logs 
    WHERE entry_date BETWEEN '$startDate' AND '$endDate'
    GROUP BY entry_date 
    ORDER BY entry_date
");
$chartData = [];
while ($row = $dailyStats->fetch_assoc()) {
    $chartData[] = $row;
}

// Top violations
$topViolations = $conn->query("
    SELECT violation_type, COUNT(*) as cnt 
    FROM violation_tickets 
    WHERE ticket_date BETWEEN '$startDate' AND '$endDate'
    GROUP BY violation_type 
    ORDER BY cnt DESC 
    LIMIT 5
");

// Students with most violations
$repeatOffenders = $conn->query("
    SELECT s.student_id, s.name, s.grade, s.section, COUNT(*) as violation_count
    FROM violation_tickets t
    JOIN students s ON t.student_id = s.id
    WHERE t.ticket_date BETWEEN '$startDate' AND '$endDate'
    GROUP BY s.id
    HAVING violation_count >= 2
    ORDER BY violation_count DESC
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compliance Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
        
        .header h1 { font-size: 1.5rem; color: #D4AF37; }
        
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
        }
        
        .filter-bar label {
            color: #888;
            font-size: 0.9rem;
        }
        
        .filter-bar input[type="date"] {
            background: rgba(0,0,0,0.3);
            border: 1px solid #8B1538;
            color: white;
            padding: 8px 12px;
            border-radius: 5px;
        }
        
        .filter-bar button {
            background: #8B1538;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            cursor: pointer;
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
        
        .stat-card .value { font-size: 2.5rem; font-weight: bold; }
        .stat-card .label { color: #888; font-size: 0.9rem; }
        
        .stat-card:nth-child(1) .value { color: #8B1538; }
        .stat-card:nth-child(2) .value { color: #27ae60; }
        .stat-card:nth-child(3) .value { color: #8B1538; }
        .stat-card:nth-child(4) .value { color: #D4AF37; }
        
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        
        .content-section {
            background: rgba(0,0,0,0.4);
            border-radius: 15px;
            padding: 20px;
        }
        
        .section-title {
            font-size: 1.1rem;
            margin-bottom: 15px;
            color: #D4AF37;
        }
        
        .chart-container {
            height: 300px;
        }
        
        .violation-list {
            list-style: none;
        }
        
        .violation-list li {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            background: rgba(0,0,0,0.3);
            border-radius: 8px;
            margin-bottom: 8px;
        }
        
        .violation-list .count {
            background: #8B1538;
            padding: 2px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
        }
        
        .offenders-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .offenders-table th, .offenders-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .offenders-table th {
            color: #888;
            font-weight: normal;
            font-size: 0.85rem;
        }
        
        .warning-badge {
            background: rgba(241, 196, 15, 0.2);
            color: #f1c40f;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
        }
        
        .export-btn {
            background: transparent;
            border: 1px solid #27ae60;
            color: #27ae60;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            margin-left: auto;
        }
        
        .export-btn:hover {
            background: #27ae60;
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'admin_nav.php'; ?>
    
    <div class="main-container">
        <form class="filter-bar" method="GET">
            <label>Date Range:</label>
            <input type="date" name="start_date" value="<?php echo $startDate; ?>">
            <span style="color: #888;">to</span>
            <input type="date" name="end_date" value="<?php echo $endDate; ?>">
            <button type="submit">Apply Filter</button>
            <button type="button" class="export-btn" onclick="exportReport()">📊 Export CSV</button>
        </form>
        
        <div class="stats-row">
            <div class="stat-card">
                <div class="value"><?php echo number_format($totalEntries); ?></div>
                <div class="label">Total Entries</div>
            </div>
            <div class="stat-card">
                <div class="value"><?php echo number_format($compliantEntries); ?></div>
                <div class="label">Compliant</div>
            </div>
            <div class="stat-card">
                <div class="value"><?php echo number_format($totalTickets); ?></div>
                <div class="label">Tickets Issued</div>
            </div>
            <div class="stat-card">
                <div class="value"><?php echo $complianceRate; ?>%</div>
                <div class="label">Compliance Rate</div>
            </div>
        </div>
        
        <div class="content-grid">
            <div class="content-section">
                <div class="section-title">📊 Daily Compliance Trend</div>
                <div class="chart-container">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
            
            <div class="content-section">
                <div class="section-title">⚠️ Top Violations</div>
                <ul class="violation-list">
                    <?php while($v = $topViolations->fetch_assoc()): ?>
                    <li>
                        <span><?php echo htmlspecialchars($v['violation_type']); ?></span>
                        <span class="count"><?php echo $v['cnt']; ?></span>
                    </li>
                    <?php endwhile; ?>
                    <?php if($totalTickets == 0): ?>
                    <li style="color: #888; text-align: center;">No violations in this period</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        
        <div class="content-section" style="margin-top: 20px;">
            <div class="section-title">🚨 Repeat Offenders (2+ violations)</div>
            <table class="offenders-table">
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Grade/Section</th>
                        <th>Violations</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($offender = $repeatOffenders->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($offender['student_id']); ?></td>
                        <td><?php echo htmlspecialchars($offender['name']); ?></td>
                        <td>Grade <?php echo $offender['grade']; ?> - <?php echo htmlspecialchars($offender['section']); ?></td>
                        <td><strong style="color: #8B1538;"><?php echo $offender['violation_count']; ?></strong></td>
                        <td>
                            <?php if($offender['violation_count'] >= 3): ?>
                            <span class="warning-badge">⚠️ Needs Attention</span>
                            <?php else: ?>
                            <span style="color: #888;">Monitor</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if($repeatOffenders->num_rows == 0): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: #888; padding: 30px;">
                            No repeat offenders in this period
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        // Chart data
        const chartData = <?php echo json_encode($chartData); ?>;
        
        if (chartData.length > 0) {
            const ctx = document.getElementById('trendChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.map(d => d.entry_date),
                    datasets: [{
                        label: 'Compliant',
                        data: chartData.map(d => d.compliant),
                        borderColor: '#27ae60',
                        backgroundColor: 'rgba(39, 174, 96, 0.1)',
                        fill: true,
                        tension: 0.4
                    }, {
                        label: 'Non-Compliant',
                        data: chartData.map(d => d.non_compliant),
                        borderColor: '#8B1538',
                        backgroundColor: 'rgba(139, 21, 56, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: { color: '#888' }
                        }
                    },
                    scales: {
                        x: {
                            ticks: { color: '#888' },
                            grid: { color: 'rgba(255,255,255,0.1)' }
                        },
                        y: {
                            ticks: { color: '#888' },
                            grid: { color: 'rgba(255,255,255,0.1)' }
                        }
                    }
                }
            });
        }
        
        function exportReport() {
            window.location.href = 'export_report.php?start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>';
        }
    </script>
</body>
</html>
