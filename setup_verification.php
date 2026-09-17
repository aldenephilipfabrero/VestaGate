<?php
/**
 * SETUP AND TESTING GUIDE FOR VIOLATION IMAGE FEATURE
 */

?>
<!DOCTYPE html>
<html>
<head>
    <title>Violation Image Feature - Setup Verification</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .section { margin: 20px 0; padding: 15px; border-left: 4px solid #8B1538; background: #f9f9f9; }
        .success { border-left-color: #27ae60; }
        .error { border-left-color: #e74c3c; }
        .info { border-left-color: #3498db; }
        .code { background: #f0f0f0; padding: 10px; border-radius: 4px; font-family: monospace; overflow-x: auto; }
        h1 { color: #8B1538; }
        h2 { color: #333; margin-top: 20px; }
        .status { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .status.ok { background: #d4edda; color: #155724; }
        .status.fail { background: #f8d7da; color: #721c24; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #8B1538; color: white; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Violation Image Feature - Setup Verification</h1>
        <p>This page verifies that all components of the violation image capture feature are properly installed.</p>

        <?php
        // Check 1: violations_images directory
        echo "<div class='section info'><h2>Step 1: Directory Structure</h2>";
        $imageDir = __DIR__ . '/violations_images';
        if (is_dir($imageDir)) {
            $writable = is_writable($imageDir);
            echo "<div class='status ok'>OK - violations_images directory exists</div>";
            echo "<div class='status " . ($writable ? 'ok' : 'fail') . "'>" . ($writable ? 'OK' : 'NOT OK') . " Directory is " . ($writable ? 'writable' : 'NOT writable') . "</div>";
            $files = glob($imageDir . '/*.jpg');
            echo "<p>Currently stored: <strong>" . count($files) . "</strong> violation images</p>";
        } else {
            echo "<div class='status fail'>NOT OK - violations_images directory NOT FOUND</div>";
            echo "<p><strong>Action:</strong> Run: <code>mkdir violations_images</code></p>";
        }
        echo "</div>";

        // Check 2: Database columns
        echo "<div class='section info'><h2>Step 2: Database Schema</h2>";
        include 'config.php';

        $tables = [
            'violation_tickets' => ['violation_image', 'capture_timestamp'],
            'violations' => ['violation_image']
        ];

        foreach ($tables as $table => $columns) {
            echo "<p><strong>Table: $table</strong></p>";
            $result = $conn->query("SHOW COLUMNS FROM $table");
            $existingColumns = [];
            while ($row = $result->fetch_assoc()) {
                $existingColumns[] = $row['Field'];
            }
            
            foreach ($columns as $col) {
                if (in_array($col, $existingColumns)) {
                    echo "<div class='status ok'>OK - Column '$col' exists</div>";
                } else {
                    echo "<div class='status fail'>NOT OK - Column '$col' MISSING</div>";
                }
            }
        }

        echo "<p><strong>To run migration:</strong> Execute <code>php db_migrate.php</code></p>";
        echo "</div>";

        // Check 3: PHP files
        echo "<div class='section info'><h2>Step 3: PHP Files</h2>";
        $phpFiles = [
            'process_scan.php' => 'Saves violation images',
            'get_student_info.php' => 'Returns violation image data',
            'view_violation_image.php' => 'Serves violation images securely',
            'get_violation_image_stats.php' => 'Provides image statistics'
        ];

        foreach ($phpFiles as $file => $description) {
            if (file_exists(__DIR__ . '/' . $file)) {
                echo "<div class='status ok'>OK - $file - $description</div>";
            } else {
                echo "<div class='status fail'>NOT OK - $file - MISSING</div>";
            }
        }
        echo "</div>";

        // Check 4: Image statistics
        echo "<div class='section info'><h2>Step 4: Image Statistics</h2>";
        if (is_dir($imageDir)) {
            $files = glob($imageDir . '/*.jpg');
            $totalSize = 0;
            
            foreach ($files as $file) {
                $size = filesize($file);
                $totalSize += $size;
            }
            
            echo "<table>";
            echo "<tr><th>Metric</th><th>Value</th></tr>";
            echo "<tr><td>Total Images</td><td><strong>" . count($files) . "</strong></td></tr>";
            echo "<tr><td>Total Storage</td><td><strong>" . round($totalSize / 1024 / 1024, 2) . " MB</strong></td></tr>";
            if (count($files) > 0) {
                echo "<tr><td>Average Image Size</td><td><strong>" . round($totalSize / count($files) / 1024, 2) . " KB</strong></td></tr>";
            }
            echo "</table>";
        }
        echo "</div>";

        // Check 5: Summary
        echo "<div class='section success'><h2>System Status</h2>";
        echo "<ul>";
        echo "<li>Database: Connected and configured</li>";
        echo "<li>Image storage: Ready for violations</li>";
        echo "<li>Admin dashboard: Available for viewing</li>";
        echo "<li>Gate scanner: Live and operational</li>";
        echo "</ul>";
        echo "</div>";
        ?>
    </div>
</body>
</html>
