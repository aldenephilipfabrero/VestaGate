<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');
$isAdminLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

if ($isAdminLoggedIn) {
    $navItems = [
        ['href' => 'gate_scanner.php', 'label' => '🚪 Gate Scanner'],
        ['href' => 'compliance_monitor.php', 'label' => '📊 Live Monitor'],
        ['href' => 'attendance_monitor.php', 'label' => '📋 Attendance'],
        ['href' => 'tickets.php', 'label' => '🎫 Tickets'],
        ['href' => 'reports.php', 'label' => '📈 Reports'],
        ['href' => 'admin_dashboard.php', 'label' => '🏠 Admin Dashboard'],
        ['href' => 'admin_dashboard.php?settings=1', 'label' => '⚙ Settings'],
        ['href' => 'logout.php', 'label' => '🔒 Logout'],
    ];
} else {
    $navItems = [
        ['href' => 'gate_scanner.php', 'label' => '🚪 Gate Scanner'],
        ['href' => 'compliance_monitor.php', 'label' => '📊 Live Monitor'],
        ['href' => 'admin_login.php', 'label' => '🏠 Admin Dashboard'],
    ];
}
?>
<style>
    body .site-header {
        background: rgba(139, 21, 56, 0.95);
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
    }

    body .site-header h1 {
        margin: 0;
        font-size: 1.3rem;
        color: white;
    }

    body .site-header .site-team {
        color: #888;
        font-size: 0.8rem;
    }

    body .site-nav-links {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    body .site-nav-links a {
        padding: 8px 15px;
        background: transparent;
        border: 1px solid #D4AF37;
        color: #D4AF37;
        text-decoration: none;
        border-radius: 5px;
        font-size: 0.85rem;
        transition: all 0.3s;
    }

    body .site-nav-links a:hover,
    body .site-nav-links a.active {
        background: #D4AF37;
        color: #1a1a1a;
    }
</style>
<div class="site-header">
    <div>
        <h1>BACO NATIONAL HIGH SCHOOL</h1>
        <div class="site-team">Computer Vision-Based Dress Code & ID Compliance System</div>
    </div>
    <div class="site-nav-links">
        <?php foreach ($navItems as $item): ?>
            <?php $isActive = ($item['href'] === $currentPage || ($item['href'] === 'admin_dashboard.php' && $currentPage === 'admin_dashboard.php') || ($item['href'] === 'admin_login.php' && $currentPage === 'admin_login.php')); ?>
            <a href="<?php echo htmlspecialchars($item['href']); ?>" class="<?php echo $isActive ? 'active' : ''; ?>"><?php echo htmlspecialchars($item['label']); ?></a>
        <?php endforeach; ?>
    </div>
</div>
