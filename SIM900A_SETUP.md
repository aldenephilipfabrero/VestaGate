# SIM900A SMS Gateway Setup Guide

## Overview
The school gate system now supports direct SMS sending via a SIM900A GSM module connected to Arduino. This eliminates the need for third-party SMS APIs—SMS is sent directly using your DITO SIM card.

## Hardware Requirements
- **Arduino board** (Uno, Mega, Pro Micro, or Nano)
- **SIM900A GSM module** with antenna
- **SIM900A power supply** (5V, 2A minimum—not from Arduino)
- **DITO SIM card** (activated, has SMS credits)
- **USB cable** (for Arduino programming and server communication)
- **Jumper wires** for wiring
- **Voltage divider** (for RX line: 5V → 3.3V conversion using resistors)

## Hardware Wiring

### Voltage Divider for SIM900A RX (5V to 3.3V)
SIM900A expects 3.3V on its RX pin, so use a voltage divider:
```
Arduino TX (pin 1 or software serial TX)
    ↓
    [10kΩ resistor]
    ↓
    +--→ SIM900A RX
    ↓
    [20kΩ resistor]
    ↓
    GND
```

This produces ~3.3V from 5V output.

### Complete Wiring (SoftwareSerial on pins 10, 11)
| Arduino | SIM900A |
|---------|---------|
| Pin 10  | TX      |
| Pin 11  | RX (via voltage divider) |
| GND     | GND     |
| 5V PSU  | VCC (via separate power supply) |

**Important**: Do NOT connect SIM900A VCC directly to Arduino 5V. Use a dedicated 5V, 2A power supply.

## Software Setup

### 1. Upload Arduino Sketch
1. Open Arduino IDE
2. Load [`arduino/sim900a_sms_gateway.ino`](../arduino/sim900a_sms_gateway.ino)
3. Select your board and COM port
4. Click "Upload"
5. Open Serial Monitor (9600 baud) to verify:
   ```
   SIM900A SMS Gateway Started
   SIM900A ready
   ```

### 2. Configure School Gate System
Edit `config.php` to enable Arduino SMS:

```php
if (!defined('SMS_ENABLE')) {
    define('SMS_ENABLE', true);  // Enable SMS sending
}

if (!defined('SMS_ARDUINO_PORT')) {
    define('SMS_ARDUINO_PORT', 'COM3');  // Change to your Arduino COM port
}
```

**Find Arduino COM port:**
- Windows: Arduino IDE → Tools → Port (shows as COM3, COM4, etc.)
- Linux: `/dev/ttyUSB0` or `/dev/ttyACM0`
- macOS: `/dev/cu.usbserial-*`

### 3. Run Database Migration
```bash
cd c:\wamp64\www\school_gate
php db_migrate.php
```

This adds the `admin_notifications` table and `parent_phone` column if not present.

## Testing

### Test 1: Arduino Serial Monitor
1. Open Arduino IDE Serial Monitor (9600 baud)
2. Send test command:
   ```
   AT|+639171234567|Test message from school gate
   ```
3. Expected response:
   ```
   OK
   ```

### Test 2: From PHP
Create a test file `test_sms.php`:

```php
<?php
include 'config.php';
include 'process_scan.php';

$result = sendSmsNotification('+639171234567', 'Test SMS from school gate system');

if ($result) {
    echo "SMS sent successfully!\n";
} else {
    echo "SMS failed. Check error logs.\n";
}
?>
```

Run from terminal:
```bash
cd c:\wamp64\www\school_gate
php test_sms.php
```

### Test 3: Via Gate Scanner
1. Open [http://localhost/school_gate/gate_scanner.php](http://localhost/school_gate/gate_scanner.php)
2. Add a student with parent phone number (e.g., +639171234567)
3. Scan student's RFID card
4. Check if parent received "Proof of entry" SMS

## Troubleshooting

### "SIM900A not responding"
- Verify power supply voltage (5V) and current (2A)
- Check SIM card is installed and activated
- Verify antenna is connected
- Try unplugging/replugging SIM900A

### Serial port not found
- Windows: Check Device Manager for Arduino COM port
- Linux: Run `ls /dev/tty*` to find port
- Update `SMS_ARDUINO_PORT` in `config.php`

### SMS not sending
- Check Arduino Serial Monitor for AT command errors
- Verify SIM has SMS credits (DITO balance)
- Ensure phone number format is correct: `+639XXXXXXXXX`
- Check PHP error logs: `C:\wamp\logs\php_error.log`

### Timeout errors
- Increase `SMS_ARDUINO_TIMEOUT_SEC` in `config.php` (default: 10 seconds)
- Check serial connection (USB cable, COM port)

### SIM900A module not receiving power
- Verify external 5V power supply (not Arduino USB)
- Check voltage at SIM900A VCC pin with multimeter
- Test with another power supply

## Serial Protocol

### Command Format
```
AT|[phone_number]|[message]
```

**Example:**
```
AT|+639171234567|Proof of entry: John Doe entered at 08:00 on 2026-07-27.
```

### Response Codes
- `OK` - SMS sent successfully
- `ERROR` - SMS failed (check Arduino logs)

## Power Consumption
- **Idle**: ~50-100mA
- **Sending SMS**: ~1000-1500mA (2A peak)
- **During network search**: ~500-800mA

Ensure your power supply can handle peak current during SMS sending.

## Cost Savings
- **Third-party API**: $0.05-0.10 per SMS
- **DITO SIM**: ~₱1-2 per SMS (network carrier rate)
- **Monthly estimate** (100 students, 20 school days): ₱2,000-4,000 vs $100-200 with API

## Safety Notes
⚠️ **Power Supply:**
- Use a dedicated 5V, 2A power supply (not Arduino USB power)
- Improper power can damage SIM900A module
- Use a power bank or external PSU with proper connectors

⚠️ **Antenna:**
- Always connect antenna before powering SIM900A
- Keep antenna away from metal objects
- Poor signal = slower SMS, higher power draw

⚠️ **SIM Card:**
- Ensure SIM has active service with SMS support
- Monitor DITO balance monthly
- Keep SIM in a safe location

## Future Enhancements
- Add SMS delivery status tracking
- Implement SMS receiving (for parent replies)
- Add multi-SMS support for longer messages
- Store SMS logs in database
- Add SMS rate limiting to prevent SMS spam

## Support
For issues:
1. Check `C:\wamp\logs\php_error.log` for PHP errors
2. Use Arduino Serial Monitor to test AT commands
3. Verify hardware connections with multimeter
4. Check DITO SIM balance and signal strength

---
**Last Updated:** July 27, 2026
**Status:** Production Ready
