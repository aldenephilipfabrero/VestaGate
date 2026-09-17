<?php
/**
 * Violation Image Statistics
 * Shows storage usage and image statistics for admin reference
 */

include 'config.php';

header('Content-Type: application/json');

$imageDir = __DIR__ . '/violations_images';

if (!is_dir($imageDir)) {
    echo json_encode([
        'success' => false,
        'message' => 'Image directory not found',
        'error' => 'Please ensure violations_images directory exists'
    ]);
    exit;
}

// Get image statistics
$files = glob($imageDir . '/*.jpg');
$totalImages = count($files);
$totalSize = 0;
$imagesByDate = [];
$imagesByStudent = [];

foreach ($files as $file) {
    if (is_file($file)) {
        $size = filesize($file);
        $totalSize += $size;
        
        // Extract date from filename: violation_{ID}_{YYYYMMDDHHMMSS}_{microtime}.jpg
        $filename = basename($file);
        preg_match('/violation_(\d+)_(\d{8})/', $filename, $matches);
        
        if (count($matches) >= 3) {
            $studentId = $matches[1];
            $dateTime = $matches[2];
            $date = substr($dateTime, 0, 4) . '-' . substr($dateTime, 4, 2) . '-' . substr($dateTime, 6, 2);
            
            // Count by date
            if (!isset($imagesByDate[$date])) {
                $imagesByDate[$date] = 0;
            }
            $imagesByDate[$date]++;
            
            // Count by student
            if (!isset($imagesByStudent[$studentId])) {
                $imagesByStudent[$studentId] = 0;
            }
            $imagesByStudent[$studentId]++;
        }
    }
}

// Get student details for top violators
$topViolators = [];
arsort($imagesByStudent);
$top10 = array_slice($imagesByStudent, 0, 10, true);

foreach ($top10 as $studentId => $count) {
    $result = $conn->query("SELECT name, student_id, grade, section FROM students WHERE id = $studentId");
    if ($result && $result->num_rows > 0) {
        $student = $result->fetch_assoc();
        $topViolators[] = [
            'student_id' => $student['student_id'],
            'name' => $student['name'],
            'grade' => $student['grade'],
            'section' => $student['section'],
            'image_count' => $count
        ];
    }
}

echo json_encode([
    'success' => true,
    'statistics' => [
        'total_images' => $totalImages,
        'total_size_bytes' => $totalSize,
        'total_size_mb' => round($totalSize / (1024 * 1024), 2),
        'average_image_size_kb' => $totalImages > 0 ? round($totalSize / $totalImages / 1024, 2) : 0
    ],
    'by_date' => $imagesByDate,
    'top_violators' => $topViolators,
    'directory_writable' => is_writable($imageDir)
], JSON_PRETTY_PRINT);
?>
