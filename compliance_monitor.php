<?php
include 'config.php';

$today = date('Y-m-d');

// Get today's stats
$totalToday = $conn->query("SELECT COUNT(*) as cnt FROM entry_logs WHERE entry_date = '$today'")->fetch_assoc()['cnt'];
$compliantToday = $conn->query("SELECT COUNT(*) as cnt FROM entry_logs WHERE entry_date = '$today' AND overall_status = 'compliant'")->fetch_assoc()['cnt'];
$nonCompliantToday = $totalToday - $compliantToday;
$complianceRate = $totalToday > 0 ? round(($compliantToday / $totalToday) * 100, 1) : 0;

// Get recent entries
$recentEntries = $conn->query("
    SELECT e.*, s.name, s.student_id, s.grade, s.section 
    FROM entry_logs e 
    JOIN students s ON e.student_id = s.id 
    WHERE e.entry_date = '$today'
    ORDER BY e.id DESC 
    LIMIT 50
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Compliance Monitor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            font-size: 2.5rem;
            font-weight: bold;
        }
        
        .stat-card .label {
            color: #888;
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        
        .stat-card.total .value { color: #8B1538; }
        .stat-card.compliant .value { color: #27ae60; }
        .stat-card.non-compliant .value { color: #8B1538; }
        .stat-card.rate .value { color: #D4AF37; }
        
        .content-section {
            background: rgba(0,0,0,0.4);
            border-radius: 15px;
            padding: 20px;
        }
        
        .section-title {
            font-size: 1.2rem;
            margin-bottom: 15px;
            color: #D4AF37;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .live-badge {
            background: #8B1538;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .entries-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .entries-table th, .entries-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            color: white;
        }
        
        .entries-table th {
            color: white;
            font-weight: normal;
            font-size: 0.85rem;
            text-transform: uppercase;
        }
        
        .entries-table tr:hover {
            background: rgba(255,255,255,0.05);
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .status-badge.compliant {
            background: rgba(39, 174, 96, 0.2);
            color: white;
        }
        
        .status-badge.non-compliant {
            background: rgba(139, 21, 56, 0.2);
            color: white;
        }
        
        .check-yes { color: white; }
        .check-no { color: white; }
        
        .refresh-btn {
            background: transparent;
            border: 1px solid #D4AF37;
            color: #D4AF37;
            padding: 5px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .refresh-btn:hover {
            background: #D4AF37;
            color: #1a1a1a;
        }
    </style>
</head>
<body>
    <?php include 'admin_nav.php'; ?>
    
    <div class="main-container">
        <div class="stats-row">
            <div class="stat-card total">
                <div class="value" id="totalEntries"><?php echo $totalToday; ?></div>
                <div class="label">Total Entries Today</div>
            </div>
            <div class="stat-card compliant">
                <div class="value" id="compliantCount"><?php echo $compliantToday; ?></div>
                <div class="label">Compliant</div>
            </div>
            <div class="stat-card non-compliant">
                <div class="value" id="nonCompliantCount"><?php echo $nonCompliantToday; ?></div>
                <div class="label">Non-Compliant</div>
            </div>
            <div class="stat-card rate">
                <div class="value" id="complianceRate"><?php echo $complianceRate; ?>%</div>
                <div class="label">Compliance Rate</div>
            </div>
        </div>
        
        <div class="content-section">
            <div class="section-title">
                <span><span class="live-badge">● LIVE</span> Today's Entry Log - <?php echo date('F j, Y'); ?></span>
                <button class="refresh-btn" onclick="location.reload()">↻ Refresh</button>
            </div>
            
            <table class="entries-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Grade/Section</th>
                        <th>Uniform</th>
                        <th>ID Visible</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="entriesBody">
                    <?php while($entry = $recentEntries->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo date('H:i:s', strtotime($entry['entry_time'])); ?></td>
                        <td><?php echo htmlspecialchars($entry['student_id']); ?></td>
                        <td><?php echo htmlspecialchars($entry['name']); ?></td>
                        <td>Grade <?php echo $entry['grade']; ?> - <?php echo htmlspecialchars($entry['section']); ?></td>
                        <td class="<?php echo $entry['uniform_status'] == 'compliant' ? 'check-yes' : 'check-no'; ?>">
                            <?php echo $entry['uniform_status'] == 'compliant' ? '✓ OK' : '✗ Violation'; ?>
                        </td>
                        <td class="<?php echo $entry['id_visible'] == 'yes' ? 'check-yes' : 'check-no'; ?>">
                            <?php echo $entry['id_visible'] == 'yes' ? '✓ Yes' : '✗ No'; ?>
                        </td>
                        <td>
                            <span class="status-badge <?php echo $entry['overall_status']; ?>">
                                <?php echo strtoupper($entry['overall_status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if($totalToday == 0): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: white; padding: 40px;">
                            No entries recorded yet today. Start scanning at the gate!
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        // Auto-refresh every 10 seconds
        setTimeout(() => location.reload(), 10000);
    </script>
</body>
</html>
