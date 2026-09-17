# Semaphore SMS Setup

The school gate system sends SMS through Semaphore when `SMS_METHOD` is set to `semaphore` (the default).

## Configure the API key

Set the `SEMAPHORE_API_KEY` environment variable for the PHP/Apache process. Do not commit the key to `config.php`.

For a temporary PowerShell session:

```powershell
$env:SEMAPHORE_API_KEY = 'your-semaphore-api-key'
```

The default configuration uses:

- Endpoint: `https://api.semaphore.co/api/v4/messages`
- Sender name: `VestaBNHS` (currently pending approval)
- Timeout: 15 seconds

The sender name must be approved and follow Semaphore's sender-name rules. You can override the endpoint, sender name, or timeout in `config.php` if needed.

## Test sending

From the project directory, run:

```powershell
php test_sms_gateway.php +639509069013 "Test SMS from school gate"
```

The script uses the same `sendSmsNotification()` path as gate scans. A successful 2xx response containing a Semaphore message record is treated as sent/queued.

## Switch providers

Set `SMS_METHOD` in `config.php` to `bridge` or `arduino` to use the existing local gateway instead.