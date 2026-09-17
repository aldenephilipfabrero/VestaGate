<?php
include 'config.php';

// Handle status updates
if (isset($_POST['action'])) {
    $ticketId = intval($_POST['ticket_id']);
    
    if ($_POST['action'] === 'submit') {
        $conn->query("UPDATE violation_tickets SET status = 'submitted', submitted_date = CURDATE() WHERE id = $ticketId");
    } elseif ($_POST['action'] === 'resolve') {
        $resolvedBy = $conn->real_escape_string($_POST['resolved_by'] ?? 'Admin');
        $conn->query("UPDATE violation_tickets SET status = 'resolved', resolved_by = '$resolvedBy' WHERE id = $ticketId");
    } elseif ($_POST['action'] === 'delete') {
        $conn->query("DELETE FROM violation_tickets WHERE id = $ticketId");
        header("Location: tickets.php?deleted=1");
        exit;
    } elseif ($_POST['action'] === 'delete_multiple') {
        $ids = $_POST['ticket_ids'] ?? '';
        if (!empty($ids)) {
            $idArray = array_map('intval', explode(',', $ids));
            $idList = implode(',', $idArray);
            $conn->query("DELETE FROM violation_tickets WHERE id IN ($idList)");
        }
        header("Location: tickets.php?deleted=" . count($idArray));
        exit;
    }
    
    header("Location: tickets.php?updated=1");
    exit;
}

// Filter
$filter = $_GET['filter'] ?? 'all';
$filterSql = "";
if ($filter === 'pending') $filterSql = "WHERE t.status = 'pending'";
elseif ($filter === 'submitted') $filterSql = "WHERE t.status = 'submitted'";
elseif ($filter === 'resolved') $filterSql = "WHERE t.status = 'resolved'";

// Get tickets
$tickets = $conn->query("
    SELECT t.*, s.name, s.student_id, s.grade, s.section 
    FROM violation_tickets t 
    JOIN students s ON t.student_id = s.id 
    $filterSql
    ORDER BY t.id DESC
");

// Stats
$totalPending = $conn->query("SELECT COUNT(*) as cnt FROM violation_tickets WHERE status = 'pending'")->fetch_assoc()['cnt'];
$totalSubmitted = $conn->query("SELECT COUNT(*) as cnt FROM violation_tickets WHERE status = 'submitted'")->fetch_assoc()['cnt'];
$totalResolved = $conn->query("SELECT COUNT(*) as cnt FROM violation_tickets WHERE status = 'resolved'")->fetch_assoc()['cnt'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Violation Tickets Management</title>
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
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: rgba(0,0,0,0.4);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card.active { border-color: #8B1538; }
        
        .stat-card .value { font-size: 2.5rem; font-weight: bold; }
        .stat-card .label { color: #888; font-size: 0.9rem; }
        
        .stat-card.pending .value { color: #D4AF37; }
        .stat-card.submitted .value { color: #8B1538; }
        .stat-card.resolved .value { color: #27ae60; }
        
        .content-section {
            background: rgba(0,0,0,0.4);
            border-radius: 15px;
            padding: 20px;
        }
        
        .section-title {
            font-size: 1.2rem;
            margin-bottom: 15px;
            color: #D4AF37;
        }
        
        .tickets-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .tickets-table th, .tickets-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .tickets-table th {
            color: #888;
            font-weight: normal;
            font-size: 0.85rem;
            text-transform: uppercase;
        }
        
        .tickets-table tr:hover { background: rgba(255,255,255,0.05); }
        
        .ticket-number {
            font-family: monospace;
            background: rgba(0,0,0,0.3);
            padding: 3px 8px;
            border-radius: 4px;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .status-badge.pending { background: rgba(241, 196, 15, 0.2); color: #f1c40f; }
        .status-badge.submitted { background: rgba(52, 152, 219, 0.2); color: #3498db; }
        .status-badge.resolved { background: rgba(39, 174, 96, 0.2); color: #27ae60; }
        
        .action-btn {
            padding: 5px 12px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 0.8rem;
            margin-right: 5px;
            transition: all 0.3s;
        }
        
        .action-btn.submit-btn {
            background: #3498db;
            color: white;
        }
        
        .action-btn.resolve-btn {
            background: #27ae60;
            color: white;
        }
        
        .action-btn.print-btn {
            background: transparent;
            border: 1px solid #888;
            color: #888;
        }
        
        .action-btn:hover { opacity: 0.8; }
        
        .action-btn.delete-btn {
            background: #e74c3c;
            color: white;
        }
        
        .ticket-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #8B1538;
        }
        
        .bulk-actions {
            display: none;
            background: rgba(139, 21, 56, 0.2);
            border: 1px solid #8B1538;
            border-radius: 8px;
            padding: 12px 20px;
            margin-bottom: 15px;
            align-items: center;
            gap: 15px;
        }
        
        .bulk-actions.show {
            display: flex;
        }
        
        .bulk-actions .selected-count {
            color: #8B1538;
            font-weight: bold;
        }
        
        .bulk-actions .bulk-btn {
            padding: 8px 16px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
        }
        
        .bulk-actions .bulk-delete-btn {
            background: #e74c3c;
            color: white;
        }
        
        .bulk-actions .bulk-clear-btn {
            background: transparent;
            border: 1px solid #888;
            color: #888;
        }
        
        .bulk-actions .bulk-btn:hover {
            opacity: 0.8;
        }
        
        .alert-success {
            background: rgba(39, 174, 96, 0.2);
            border: 1px solid #27ae60;
            color: #27ae60;
            padding: 10px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .modal-content {
            background: #1a1a1a;
            color: white;
        }
        
        .modal-header {
            border-bottom-color: #8B1538;
        }
        
        .modal-footer {
            border-top-color: #8B1538;
        }
        
        .form-control {
            background: rgba(0,0,0,0.3);
            border-color: #8B1538;
            color: white;
        }
        
        .form-control:focus {
            background: rgba(0,0,0,0.5);
            color: white;
            border-color: #D4AF37;
        }
    </style>
</head>
<body>
    <?php include 'admin_nav.php'; ?>
    
    <div class="main-container">
        <?php if(isset($_GET['updated'])): ?>
        <div class="alert-success">✓ Ticket status updated successfully!</div>
        <?php endif; ?>
        
        <?php if(isset($_GET['deleted'])): ?>
        <div class="alert-success">✓ <?php echo intval($_GET['deleted']) > 1 ? intval($_GET['deleted']) . ' tickets' : 'Ticket'; ?> deleted successfully!</div>
        <?php endif; ?>
        
        <div class="stats-row">
            <a href="tickets.php?filter=pending" class="stat-card pending <?php echo $filter === 'pending' ? 'active' : ''; ?>" style="text-decoration: none;">
                <div class="value"><?php echo $totalPending; ?></div>
                <div class="label">Pending Tickets</div>
            </a>
            <a href="tickets.php?filter=submitted" class="stat-card submitted <?php echo $filter === 'submitted' ? 'active' : ''; ?>" style="text-decoration: none;">
                <div class="value"><?php echo $totalSubmitted; ?></div>
                <div class="label">Submitted to Office</div>
            </a>
            <a href="tickets.php?filter=resolved" class="stat-card resolved <?php echo $filter === 'resolved' ? 'active' : ''; ?>" style="text-decoration: none;">
                <div class="value"><?php echo $totalResolved; ?></div>
                <div class="label">Resolved</div>
            </a>
        </div>
        
        <div class="content-section">
            <div class="section-title">
                <?php 
                if ($filter === 'pending') echo '⏳ Pending Tickets';
                elseif ($filter === 'submitted') echo '📋 Submitted Tickets';
                elseif ($filter === 'resolved') echo '✓ Resolved Tickets';
                else echo '📑 All Violation Tickets';
                ?>
                <a href="tickets.php" style="float: right; color: #888; font-size: 0.9rem;">Show All</a>
            </div>
            
            <div class="bulk-actions" id="bulkActions">
                <span class="selected-count"><span id="selectedCount">0</span> selected</span>
                <button type="button" class="bulk-btn bulk-delete-btn" onclick="deleteSelected()">🗑️ Delete Selected</button>
                <button type="button" class="bulk-btn bulk-clear-btn" onclick="clearSelection()">Clear Selection</button>
            </div>
            
            <table class="tickets-table">
                <thead>
                    <tr>
                        <th style="width: 40px;"><input type="checkbox" class="ticket-checkbox" id="selectAll" onclick="toggleSelectAll()"></th>
                        <th>Ticket #</th>
                        <th>Date</th>
                        <th>Student</th>
                        <th>Grade/Section</th>
                        <th>Violation</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($ticket = $tickets->fetch_assoc()): ?>
                    <tr>
                        <td><input type="checkbox" class="ticket-checkbox row-checkbox" data-id="<?php echo $ticket['id']; ?>" onclick="updateSelection()"></td>
                        <td><span class="ticket-number"><?php echo $ticket['ticket_number']; ?></span></td>
                        <td><?php echo date('M j, Y', strtotime($ticket['ticket_date'])); ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($ticket['name']); ?></strong><br>
                            <small style="color: #888;"><?php echo $ticket['student_id']; ?></small>
                        </td>
                        <td>Grade <?php echo $ticket['grade']; ?> - <?php echo htmlspecialchars($ticket['section']); ?></td>
                        <td><?php echo htmlspecialchars($ticket['violation_type']); ?></td>
                        <td><span class="status-badge <?php echo $ticket['status']; ?>"><?php echo strtoupper($ticket['status']); ?></span></td>
                        <td>
                            <?php if($ticket['status'] === 'pending'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                <input type="hidden" name="action" value="submit">
                                <button type="submit" class="action-btn submit-btn">Mark Submitted</button>
                            </form>
                            <?php elseif($ticket['status'] === 'submitted'): ?>
                            <button class="action-btn resolve-btn" onclick="showResolveModal(<?php echo $ticket['id']; ?>)">Resolve</button>
                            <?php else: ?>
                            <span style="color: #888; font-size: 0.8rem;">Resolved by: <?php echo htmlspecialchars($ticket['resolved_by'] ?? 'Admin'); ?></span>
                            <?php endif; ?>
                            <button class="action-btn print-btn" onclick="printTicket(<?php echo $ticket['id']; ?>)">🖨️</button>
                            <button class="action-btn delete-btn" onclick="deleteTicket(<?php echo $ticket['id']; ?>, '<?php echo $ticket['ticket_number']; ?>')">🗑️</button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Resolve Modal -->
    <div class="modal fade" id="resolveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Resolve Ticket</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="ticket_id" id="resolveTicketId">
                        <input type="hidden" name="action" value="resolve">
                        <div class="mb-3">
                            <label class="form-label">Resolved By</label>
                            <input type="text" name="resolved_by" class="form-control" placeholder="Enter admin name" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Mark as Resolved</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showResolveModal(ticketId) {
            document.getElementById('resolveTicketId').value = ticketId;
            new bootstrap.Modal(document.getElementById('resolveModal')).show();
        }
        
        function printTicket(ticketId) {
            window.open('print_ticket.php?id=' + ticketId, '_blank', 'width=400,height=600');
        }
        
        function deleteTicket(ticketId, ticketNumber) {
            if (confirm('Are you sure you want to delete ticket ' + ticketNumber + '? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="ticket_id" value="' + ticketId + '"><input type="hidden" name="action" value="delete">';
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            updateSelection();
        }
        
        function updateSelection() {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            const checked = document.querySelectorAll('.row-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            const selectAll = document.getElementById('selectAll');
            
            selectedCount.textContent = checked.length;
            bulkActions.classList.toggle('show', checked.length > 0);
            selectAll.checked = checkboxes.length > 0 && checked.length === checkboxes.length;
        }
        
        function clearSelection() {
            document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('selectAll').checked = false;
            updateSelection();
        }
        
        function deleteSelected() {
            const checked = document.querySelectorAll('.row-checkbox:checked');
            if (checked.length === 0) return;
            
            if (confirm('Are you sure you want to delete ' + checked.length + ' ticket(s)? This action cannot be undone.')) {
                const ids = Array.from(checked).map(cb => cb.dataset.id).join(',');
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="ticket_ids" value="' + ids + '"><input type="hidden" name="action" value="delete_multiple">';
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
