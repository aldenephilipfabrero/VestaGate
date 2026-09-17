import json
import urllib.request

payload = json.dumps({'phone': '+639171234567', 'message': 'HTTP bridge test'}).encode()
req = urllib.request.Request('http://127.0.0.1:5020/sms', data=payload, headers={'Content-Type': 'application/json'}, method='POST')
try:
    with urllib.request.urlopen(req, timeout=30) as r:
        print('HTTP', r.status)
        print(r.read().decode())
except Exception as e:
    print('ERROR', repr(e))
