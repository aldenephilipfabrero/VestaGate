# Arduino SIM900A SMS Implementation - Summary

## What Was Implemented

This implementation enables the school gate system to send SMS notifications directly via SIM900A GSM module, eliminating third-party SMS API dependencies.

### Three SMS Features Enabled:

1. **Parent Entry Notification**
   - Sent when student enters school
   - Message: "Proof of entry: [Name] ([ID]) entered school at [Time] on [Date]."
   - Recipients: Parents (via parent_phone field)

2. **Admin Uniform Violation Alert**
   - Triggered when student exceeds 3 improper uniform detections in 7 days
   - Message: "[Name] ([ID]) has been detected [N] times with improper uniform in the last 7 days."
   - Recipients: Admin dashboard (unread notifications panel)
   - Anti-spam: 24-hour cooldown per student, daily deduplication

3. **Frequent Violation Detection**
   - Automatic counting of violations per student
   - Threshold: 3+ violations in 7-day rolling window
   - Admin notification only triggers once per day per student

## Files Created/Modified

### Modified Files:

1. **config.php**
   - Replaced HTTP API constants with Arduino serial configuration
   - **Removed**: `SMS_API_URL`, `SMS_API_KEY`
   - **Added**: 
     - `SMS_METHOD = 'arduino'`
     - `SMS_ARDUINO_PORT = 'COM5'` (change to your Arduino port)
     - `SMS_ARDUINO_BAUDRATE = 9600`
     - `SMS_ARDUINO_TIMEOUT_SEC = 10`

2. **process_scan.php**
   - Replaced HTTP SMS sending with Arduino serial communication
   - **Function Changes**:
     - `sendSmsNotification()`: Now sends AT commands over serial instead of HTTP
     - **New Function**: `sendSmsViaArduino()` - Handles serial port I/O

### New Files Created:

1. **arduino/sim900a_sms_gateway.ino** (114 lines)
   - Arduino sketch for SIM900A module
   - Listens for commands on USB serial port
   - Sends AT commands to SIM900A
   - Command format: `AT|[phone]|[message]`
   - Returns: `OK` on success, `ERROR` on failure

2. **SIM900A_SETUP.md** (Detailed Setup Guide)
   - Hardware requirements and wiring instructions
   - Voltage divider circuit for RX line (5V → 3.3V)
   - Software configuration steps
   - Testing procedures
   - Troubleshooting guide
   - Cost savings analysis

3. **test_sms_gateway.php** (Test Script)
   - Command-line tool for testing SMS sending
   - Usage: `php test_sms_gateway.php +639171234567 "Test message"`
   - Validates configuration
   - Shows detailed error messages if sending fails

## How It Works

### Data Flow:

```
Student RFID Scan
    ↓
process_scan.php reads scan data
    ↓
Entry logged to database
    ↓
Computer vision detects uniform violations
    ↓
Violation stored; check parent_phone
    ↓
sendSmsNotification(parent_phone, "Proof of entry...")
    ↓
Send AT command over serial to Arduino
Command: "AT|+639171234567|Proof of entry...\r\n"
    ↓
Arduino receives command via USB serial port
    ↓
Arduino parses: phone="+639171234567", message="Proof of entry..."
    ↓
Arduino sends to SIM900A: AT+CMGF=1 (text mode)
                         AT+CMGS="+639171234567"
                         [message]
                         Ctrl+Z
    ↓
SIM900A processes AT commands
    ↓
SMS sent via DITO network
    ↓
Arduino returns "OK" to PHP via serial
    ↓
Parent receives SMS on their phone
```

### Admin Notification Flow:

```
Computer Vision detects improper uniform
    ↓
maybeNotifyFrequentUniformViolation() called
    ↓
Count violations in last 7 days: SELECT COUNT(*) WHERE student_id=X AND violation_type LIKE '%Improper Uniform%' AND violation_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ↓
If count >= 3 (UNIFORM_ALERT_THRESHOLD):
    Check cooldown: SELECT admin_notifications WHERE student_id=X AND notification_type='uniform_repeat' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    
    If no active cooldown:
        queueAdminNotification() inserts record to admin_notifications table
        dedupe_key = "uniform_repeat_[student_id]_[YYYY-MM-DD]" (prevents duplicate on same day)
    ↓
Admin opens dashboard (index.php)
    ↓
Unread notifications displayed in right-side "Admin Alert" card
    ↓
Admin clicks notification to read (marks as read, hides from unread count)
```

## Configuration Steps

### Step 1: Update config.php
Change the Arduino COM port to match your system:
```php
if (!defined('SMS_ARDUINO_PORT')) {
    define('SMS_ARDUINO_PORT', 'COM5');  // Change COM5 to your Arduino's COM port
}
```

**Find your Arduino COM port:**
- Windows: Open Arduino IDE → Tools → Port (shows COM3, COM4, etc.)
- Record the port number

### Step 2: Upload Arduino Sketch
1. Open Arduino IDE
2. File → Open → `arduino/sim900a_sms_gateway.ino`
3. Select Tools → Board (your Arduino model)
4. Select Tools → Port (your Arduino COM port)
5. Click Upload button
6. Wait for "Upload complete" message

### Step 3: Hardware Setup
1. Connect SIM900A to Arduino via SoftwareSerial (pins 10, 11)
2. Use voltage divider on RX line (5V → 3.3V)
3. Connect separate 5V/2A power supply to SIM900A (NOT Arduino USB power)
4. Insert DITO SIM card in SIM900A
5. Connect antenna to SIM900A
6. Power on SIM900A (may take 10-20 seconds to connect to network)

### Step 4: Run Database Migration
```bash
cd c:\wamp64\www\school_gate
php db_migrate.php
```

This creates the `admin_notifications` table if not present.

### Step 5: Verify SMS_ENABLE
In config.php, ensure:
```php
if (!defined('SMS_ENABLE')) {
    define('SMS_ENABLE', true);  // Must be true
}
```

## Testing

### Test 1: Quick Command-Line Test
```bash
php test_sms_gateway.php +639171234567 "Test SMS"
```

Expected output: `✓ SMS sent successfully!`

### Test 2: Gate Scanner Test
1. Open gate_scanner.php
2. Add new student with parent phone: +639171234567
3. Scan student's RFID card
4. Check if parent received SMS within 30 seconds

### Test 3: Admin Alert Test
1. Add student with improper uniform
2. Scan same student 3+ times with improper uniform in 7 days
3. On 3rd violation, admin notification appears in dashboard
4. Notification appears only once per day per student (24-hour cooldown)

## Troubleshooting

| Symptom | Solution |
|---------|----------|
| "SMS failed" in test | Verify Arduino COM port in config.php matches Device Manager |
| Arduino not responding | Check power supply (5V, 2A), antenna connection |
| SMS not arriving | Check DITO SIM balance, signal strength (antenna position) |
| Serial timeout errors | Increase `SMS_ARDUINO_TIMEOUT_SEC` in config.php |
| Parent phone not saving | Run `php db_migrate.php` to add parent_phone column |
| Admin alerts not showing | Check that `admin_notifications` table exists |

## Validation Checklist

- [ ] config.php syntax valid (no PHP errors)
- [ ] process_scan.php syntax valid (no PHP errors)
- [ ] Arduino sketch uploaded successfully
- [ ] SIM900A module powered and connected
- [ ] DITO SIM card inserted and activated
- [ ] Antenna connected to SIM900A
- [ ] Serial cable connected (Arduino USB port shows in Device Manager)
- [ ] SMS_ARDUINO_PORT matches your COM port
- [ ] `php test_sms_gateway.php` returns OK
- [ ] Database migration executed successfully
- [ ] Gate scanner test sends and receives SMS

## Rollback Instructions

If you need to revert to HTTP API (not recommended):

1. Restore original config.php:
   ```php
   define('SMS_METHOD', 'http');
   define('SMS_API_URL', 'https://your-sms-provider.com/api');
   define('SMS_API_KEY', 'your-api-key');
   ```

2. Restore original sendSmsNotification() in process_scan.php (use HTTP curl)

3. Disconnect Arduino hardware

## Cost Savings

- **API-based**: $0.05-0.10 per SMS
- **SIM900A+DITO**: ~₱1-2 per SMS (one-time hardware cost ~₱800-1200)
- **Break-even**: ~8,000-12,000 SMS messages

## Next Steps

1. ✅ Update config.php with Arduino serial settings
2. ✅ Rewrite sendSmsNotification() for serial communication
3. ✅ Create Arduino sketch for SIM900A
4. ⏳ **YOUR ACTION**: Upload Arduino sketch and test
5. ⏳ Monitor SMS sending in production

---

**Implementation Date:** July 27, 2026  
**Status:** Ready for Hardware Testing  
**Support:** See SIM900A_SETUP.md for detailed guidance
