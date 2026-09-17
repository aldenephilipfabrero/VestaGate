<?php
/**
 * View Violation Image
 * Secure file serving for violation images with authentication
 */

include 'config.php';

// Get image path from request
$imagePath = isset($_GET['path']) ? $_GET['path'] : '';

if (empty($imagePath)) {
    http_response_code(400);
    die('No image path provided');
}

// Sanitize path to prevent directory traversal
$imagePath = str_replace(['../', '..\\', '\\'], '', $imagePath);

// Only allow violations_images directory
if (strpos($imagePath, 'violations_images/') !== 0) {
    http_response_code(403);
    die('Access denied');
}

// Build full file path
$fullPath = __DIR__ . '/' . $imagePath;

// Verify file exists and is in the correct directory
$realPath = realpath($fullPath);
$allowedDir = realpath(__DIR__ . '/violations_images');

if (!$realPath || strpos($realPath, $allowedDir) !== 0 || !file_exists($realPath)) {
    http_response_code(404);
    die('Image not found');
}

// Serve the image with appropriate headers
$mimeType = 'image/jpeg'; // All violation images are JPEG
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($realPath));
header('Cache-Control: public, max-age=3600'); // Cache for 1 hour
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');

// Output the file
readfile($realPath);
exit;
?>
