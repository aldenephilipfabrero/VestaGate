import serial
import time

port = 'COM9'

with serial.Serial(port, 9600, timeout=2, write_timeout=2) as s:
    time.sleep(3)
    print('--- reading startup ---')
    startup = s.read(4096).decode('utf-8', 'ignore')
    print(startup)

    print('--- sending TEST ---')
    s.write(b'TEST\r\n')
    time.sleep(4)
    print(s.read(4096).decode('utf-8', 'ignore'))

    print('--- sending SMS command ---')
    s.write(b'AT|+639171234567|Bridge test message\r\n')
    time.sleep(15)
    print(s.read(4096).decode('utf-8', 'ignore'))
