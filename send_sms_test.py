import os
import serial
import time
from serial.tools import list_ports

SERIAL_PORT = os.environ.get('SMS_SERIAL_PORT', 'auto').strip() or 'auto'
BAUD_RATE = 9600
CMD = b'AT|+639637736202|Hello from SIM900A\r\n'


def detect_port():
    if SERIAL_PORT.lower() != 'auto':
        return SERIAL_PORT

    candidates = [port.device for port in list_ports.comports()]
    for port in candidates + [f'COM{i}' for i in range(1, 21)]:
        try:
            ser = serial.Serial(port, BAUD_RATE, timeout=1)
            time.sleep(1)
            ser.write(b'AT\r\n')
            time.sleep(1)
            response = ser.read_all()
            ser.close()
            if b'OK' in response.upper():
                return port
            ser.close()
        except Exception:
            continue
    return candidates[0] if candidates else 'COM3'


port = detect_port()
print(f'Testing port: {port}')

try:
    ser = serial.Serial(port, BAUD_RATE, timeout=1)
    time.sleep(2)

    ser.write(CMD)
    time.sleep(2)

    response = ser.read_all()
    print(response.decode('utf-8', errors='ignore'))
finally:
    try:
        ser.close()
    except Exception:
        pass
