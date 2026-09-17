# 🔧 Developer's Technical Guide: Violation Image System

## System Architecture

```
Gate Scanner                Violation Detection           Admin Dashboard
     ↓                              ↓                             ↓
Captures Image          Analyzes Compliance           Retrieves Images
  (Base64)              Creates Violation            Displays in Modal
     ↓                              ↓                             ↓
    process_scan.php → saveViolationImage() → Database → get_student_info.php
                             ↓                                    ↓
                    violations_images/                    view_violation_image.php
                       (JPEG Files)
```

---

## Database Schema

### New Columns

**`violation_tickets` table:**
```sql
ALTER TABLE violation_tickets ADD COLUMN violation_image VARCHAR(500) NULL DEFAULT NULL;
ALTER TABLE violation_tickets ADD COLUMN capture_timestamp DATETIME DEFAULT CURRENT_TIMESTAMP;
```

**`violations` table:**
```sql
ALTER TABLE violations ADD COLUMN violation_image VARCHAR(500) NULL DEFAULT NULL;
```

### Query Examples

**Get violations with images:**
```sql
SELECT * FROM violations WHERE violation_image IS NOT NULL AND student_id = 5;
```

**Get image stats:**
```sql
SELECT student_id, COUNT(*) as image_count FROM violation_tickets 
WHERE violation_image IS NOT NULL 
GROUP BY student_id 
ORDER BY image_count DESC;
```

**Get images by date:**
```sql
SELECT violation_date, COUNT(*) as count FROM violations 
WHERE violation_image IS NOT NULL 
GROUP BY violation_date 
ORDER BY violation_date DESC;
```

---

## File Processing Flow

### 1. Image Capture (gate_scanner.php)
```javascript
// Capture frame from camera/Tapo
async function captureFrame() {
    // Returns Base64 string
    return canvas.toDataURL('image/jpeg', 0.8);
}

// Send to backend
fetch('process_scan.php', {
    method: 'POST',
    body: JSON.stringify({
        rfid: rfidValue,
        image: imageData  // Base64
    })
});
```

### 2. Image Processing (process_scan.php)
```php
// Decode Base64 → Binary
$imageBinary = base64_decode($imageData, true);

// Save to file
$filepath = "violations_images/violation_{id}_{timestamp}.jpg"
file_put_contents($filepath, $imageBinary);

// Store path in database
INSERT INTO violation_tickets (violation_image) VALUES ('violations_images/...')
```

### 3. Image Retrieval (get_student_info.php)
```php
// Fetch from database
SELECT * FROM violations WHERE student_id = $id

// Add image metadata
$violation['has_image'] = !empty($violation['violation_image']);
$violation['image_url'] = $violation['violation_image'];

// Return to frontend
echo json_encode(['violations' => $violations]);
```

### 4. Image Display (index.php)
```javascript
// Show image in modal
viewViolationImage(imagePath, date, type)

// Bootstrap modal shows image
<img src="${imagePath}">
```

---

## API Endpoints

### `process_scan.php`
**Type:** POST  
**Input:**
```json
{
  "rfid": "ABC123456",
  "image": "data:image/jpeg;base64,/9j/4AAQSkZJRg..."
}
```

**Output:**
```json
{
  "success": true,
  "student": { ... },
  "compliance": { ... },
  "ticket": {
    "ticket_id": 123,
    "ticket_number": "VT-20260423-0001",
    "image": "violations_images/violation_5_20260423105030_12345678.jpg"
  }
}
```

### `get_student_info.php`
**Type:** GET  
**URL:** `get_student_info.php?id=5`  
**Output:**
```json
{
  "success": true,
  "student": { ... },
  "violations": [
    {
      "id": 1,
      "violation_type": "Improper Uniform",
      "violation_image": "violations_images/violation_5_20260423105030_12345678.jpg",
      "has_image": true,
      "image_url": "violations_images/violation_5_20260423105030_12345678.jpg"
    }
  ]
}
```

### `view_violation_image.php`
**Type:** GET  
**URL:** `view_violation_image.php?path=violations_images/violation_5_20260423105030_12345678.jpg`  
**Returns:** JPEG image file with proper headers

### `get_violation_image_stats.php`
**Type:** GET  
**Output:**
```json
{
  "success": true,
  "statistics": {
    "total_images": 156,
    "total_size_bytes": 15728640,
    "total_size_mb": 15.0,
    "average_image_size_kb": 100.8
  },
  "by_date": {
    "2026-04-23": 12,
    "2026-04-22": 8
  },
  "top_violators": [
    {
      "student_id": "STU001",
      "name": "John Doe",
      "grade": "10",
      "image_count": 5
    }
  ]
}
```

---

## Directory Structure

```
school_gate/
├── violations_images/              # Image storage directory
│   ├── violation_5_20260423105030_12345678.jpg
│   ├── violation_7_20260423101500_87654321.jpg
│   └── violation_3_20260422154530_11223344.jpg
├── process_scan.php               # Modified: Added saveViolationImage()
├── get_student_info.php           # Modified: Returns image data
├── index.php                      # Modified: Added viewViolationImage()
├── view_violation_image.php       # New: Serves images
├── get_violation_image_stats.php  # New: Statistics API
├── db_migrate.php                 # New: Database migration
└── setup_verification.php         # New: Setup verification
```

---

## Error Handling

### Image Save Failures
```php
$imagePath = saveViolationImage($imageData, $studentId, $violationType);

if ($imagePath === null) {
    // Log to database but don't fail violation creation
    // Violation ticket created without image
    $imagePath = NULL;
}

// Insert with NULL image path
INSERT INTO violation_tickets (..., violation_image) VALUES (..., NULL);
```

### Image Load Failures
```javascript
if (!data.violations[i].has_image) {
    // Don't show camera icon
    // Violation still displays without image
}

// Graceful degradation - system works without images
```

---

## Performance Considerations

### Image Encoding/Decoding
- **Frontend:** Base64 encoding adds ~33% overhead
- **Backend:** Base64 decoding: ~100-200ms
- **File Write:** ~50-100ms per image
- **Total:** ~300-500ms per violation

### Disk I/O
- Sequential writes recommended (no random access)
- Average file: 100KB
- Batch operations recommended for cleanup

### Memory Usage
```php
// Efficient: Stream file data
readfile($filepath);  // Low memory

// Inefficient: Load entire file
$data = file_get_contents($filepath);  // High memory for large files
```

---

## Security Implementation

### Path Validation
```php
// Prevent directory traversal
$imagePath = str_replace(['../', '..\\', '\\'], '', $imagePath);

// Ensure path is in allowed directory
if (strpos($imagePath, 'violations_images/') !== 0) {
    http_response_code(403);
    die('Access denied');
}
```

### File Type Validation
```php
// Ensure it's a JPEG from violations_images
$filename = basename($imagePath);
if (!preg_match('/^violation_\d+_\d+_\d+\.jpg$/', $filename)) {
    http_response_code(400);
    die('Invalid filename');
}
```

### Real Path Validation
```php
// Prevent symlink attacks
$realPath = realpath($fullPath);
$allowedDir = realpath(__DIR__ . '/violations_images');

if (strpos($realPath, $allowedDir) !== 0) {
    http_response_code(403);
    die('Invalid path');
}
```

---

## Maintenance & Operations

### Disk Space Monitoring
```bash
# Check directory size
du -sh violations_images/

# Count images
find violations_images/ -type f -name "*.jpg" | wc -l

# Total size in MB
du -sb violations_images/ | awk '{print $1/1024/1024 " MB"}'
```

### Image Archival Strategy
```bash
# Move images older than 90 days to archive
find violations_images/ -name "*.jpg" -mtime +90 \
  -exec mv {} violations_images/archive/ \;

# Delete images older than 1 year
find violations_images/ -name "*.jpg" -mtime +365 -delete
```

### Database Cleanup
```sql
-- Remove orphaned image references
UPDATE violations SET violation_image = NULL 
WHERE violation_image NOT IN (
  SELECT CONCAT('violations_images/', violation_image) 
  FROM (SELECT * FROM violations) v
);
```

### Cron Job Example
```bash
# /etc/cron.d/school_gate_maintenance

# Archive old images (weekly)
0 3 * * 0 find /var/www/school_gate/violations_images -name "*.jpg" -mtime +90 -exec gzip {} \;

# Delete very old images (monthly)  
0 4 1 * * find /var/www/school_gate/violations_images -name "*.jpg.gz" -mtime +365 -delete

# Database cleanup (weekly)
0 5 * * 0 mysql -u root school_gate < /var/www/school_gate/cleanup.sql
```

---

## Troubleshooting Guide

### Images Not Saving

**Check 1:** Directory Permissions
```bash
ls -la violations_images/
# Should show: drwxr-xr-x
# If not: chmod 755 violations_images
```

**Check 2:** PHP Error Logs
```bash
tail -f /var/log/php-errors.log
# Look for "saveViolationImage" errors
```

**Check 3:** Disk Space
```bash
df -h
# Ensure partition is not full
```

**Check 4:** Base64 Decoding
```php
// Test base64 decoding
$test = base64_decode($imageData);
if ($test === false) {
    // Decoding failed
}
```

### Images Not Loading

**Check 1:** Database Path
```sql
SELECT violation_image FROM violations WHERE violation_image IS NOT NULL LIMIT 1;
-- Should return: violations_images/violation_...jpg
```

**Check 2:** File Exists
```bash
ls -la violations_images/violation_5_20260423105030_12345678.jpg
# Should exist and be readable
```

**Check 3:** Browser Cache
```
Clear browser cache or open in incognito mode
```

### Performance Issues

**Check 1:** Image File Size
```bash
ls -lh violations_images/ | sort -k5 -h | tail
# Look for unusually large files
```

**Check 2:** Disk I/O
```bash
iostat -x 5 5
# Look for high %util, high await time
```

**Check 3:** Memory Usage
```php
// Check memory during image operations
echo memory_get_usage() / 1024 / 1024 . " MB";
```

---

## Logging & Debugging

### Enable Debug Logging
```php
// In process_scan.php
error_log("Image data received: " . strlen($imageData) . " bytes");
error_log("Saving to: " . $imagePath);
error_log("Save result: " . ($imagePath ? 'SUCCESS' : 'FAILED'));
```

### Check JavaScript Console
```javascript
// In browser console (F12)
console.log('Image path:', imagePath);
console.log('Has image:', data.violations[0].has_image);
console.log('Full violations data:', data.violations);
```

---

## Future Enhancements

### Possible Improvements
1. Image compression optimization
2. Thumbnail generation for faster loading
3. Image annotation/markup tools
4. Batch export functionality
5. Integration with cloud storage
6. ML-based violation auto-classification
7. Image watermarking with metadata
8. Real-time image sync to backup

### Implementation Example: Image Thumbnails
```php
// Generate thumbnail when saving original
$thumbnail = imagecreatefromjpeg($fullPath);
$thumb = imagescale($thumbnail, 200, 150);
imagejpeg($thumb, $thumbPath, 85);
imagedestroy($thumbnail);
imagedestroy($thumb);
```

---

## References

- **Base64 Encoding:** https://www.php.net/manual/en/function.base64-decode.php
- **File Operations:** https://www.php.net/manual/en/function.file-put-contents.php
- **Image Handling:** https://www.php.net/manual/en/ref.image.php
- **Security Best Practices:** https://owasp.org/www-community/attacks/Path_Traversal

---

**Last Updated:** April 23, 2026  
**Version:** 1.0  
**Status:** Production Ready
