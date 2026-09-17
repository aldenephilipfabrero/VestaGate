<?php
include 'config.php';

if (!isset($_GET['id'])) {
    echo "No ticket specified";
    exit;
}

$id = intval($_GET['id']);
$result = $conn->query("
    SELECT t.*, s.name, s.student_id, s.grade, s.section 
    FROM violation_tickets t 
    JOIN students s ON t.student_id = s.id 
    WHERE t.id = $id
");

$ticket = $result->fetch_assoc();

if (!$ticket) {
    echo "Ticket not found";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ticket - <?php echo $ticket['ticket_number']; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        @page {
            size: 58mm auto;
            margin: 0;
        }
        
        body {
            font-family: 'Arial Narrow', Arial, sans-serif;
            padding: 3px;
            background: white;
            color: black;
            font-size: 10px;
            width: 58mm;
        }
        
        .ticket {
            border: 1px solid #000;
            padding: 5px;
            width: 100%;
        }
        
        .header {
            text-align: center;
            border-bottom: 1px dashed #000;
            padding-bottom: 3px;
            margin-bottom: 3px;
        }
        
        .header h1 {
            font-size: 11px;
            margin-bottom: 1px;
        }
        
        .header h2 {
            font-size: 10px;
            color: #000;
            font-weight: bold;
        }
        
        .ticket-number {
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            margin: 3px 0;
            padding: 3px;
            background: #eee;
            font-family: monospace;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 1px 0;
            font-size: 9px;
            border-bottom: 1px dotted #ccc;
        }
        
        .info-row .label {
            font-weight: bold;
        }
        
        .violation-box {
            background: #f5f5f5;
            border: 1px solid #000;
            padding: 4px;
            margin: 4px 0;
            text-align: center;
        }
        
        .violation-box h3 {
            font-size: 9px;
            margin-bottom: 2px;
        }
        
        .violation-box p {
            font-weight: bold;
            font-size: 10px;
        }
        
        .footer {
            text-align: center;
            margin-top: 4px;
            padding-top: 3px;
            border-top: 1px dashed #000;
            font-size: 8px;
        }
        
        .footer p {
            margin-bottom: 1px;
        }
        
        .barcode {
            text-align: center;
            margin-top: 3px;
            font-family: monospace;
            font-size: 8px;
            letter-spacing: 1px;
        }
        
        @media print {
            body { padding: 0; width: 58mm; }
            .ticket { border-width: 1px; }
            .no-print { display: none !important; }
        }
        
        @media screen {
            body { width: auto; max-width: 220px; margin: 10px auto; }
        }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="header">
            <h1>Baco National High School</h1>
            <h2>VIOLATION TICKET</h2>
        </div>
        
        <div class="ticket-number"><?php echo $ticket['ticket_number']; ?></div>
        
        <div class="info-section">
            <div class="info-row">
                <span class="label">Date:</span>
                <span><?php echo date('m/d/Y', strtotime($ticket['ticket_date'])); ?></span>
            </div>
            <div class="info-row">
                <span class="label">Time:</span>
                <span><?php echo date('h:i A', strtotime($ticket['created_at'])); ?></span>
            </div>
            <div class="info-row">
                <span class="label">ID:</span>
                <span><?php echo htmlspecialchars($ticket['student_id']); ?></span>
            </div>
            <div class="info-row">
                <span class="label">Name:</span>
                <span><?php echo htmlspecialchars($ticket['name']); ?></span>
            </div>
            <div class="info-row">
                <span class="label">Gr/Sec:</span>
                <span>G<?php echo $ticket['grade']; ?>-<?php echo htmlspecialchars($ticket['section']); ?></span>
            </div>
        </div>
        
        <div class="violation-box">
            <h3>VIOLATION</h3>
            <p><?php echo htmlspecialchars($ticket['violation_type']); ?></p>
        </div>
        
        <div class="footer">
            <p><strong>Submit to DISCIPLINE OFFICE</strong></p>
        </div>
        
        <div class="barcode">
            [<?php echo $ticket['ticket_number']; ?>]
        </div>
    </div>
    
    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print()" style="padding: 10px 30px; font-size: 1rem; cursor: pointer;">🖨️ Print Ticket</button>
    </div>
    
    <?php if(isset($_GET['autoprint'])): ?>
    <script>
        // Auto-print when page loads
        window.onload = function() {
            // Give time for content to render
            setTimeout(function() {
                try {
                    window.print();
                } catch(e) {
                    console.log('Print failed:', e);
                }
            }, 300);
        };
        
        // Close window after printing (or after timeout)
        window.onafterprint = function() {
            setTimeout(function() {
                window.close();
            }, 500);
        };
        
        // Fallback: close after 10 seconds if print dialog was cancelled
        setTimeout(function() {
            window.close();
        }, 10000);
    </script>
    <?php endif; ?>
</body>
</html>
