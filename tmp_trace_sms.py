import serial
import time

s = serial.Serial('COM9', 9600, timeout=2, write_timeout=2)
time.sleep(2)
s.reset_input_buffer()
s.reset_output_buffer()
s.write(b'AT|+639171234567|Bridge test message\r\n')
start = time.time()
data = b''
while time.time() - start < 20:
    chunk = s.read(1024)
    if chunk:
        data += chunk
        print(chunk.decode('utf-8', 'ignore'), end='')
    if b'ERROR' in data or b'+CMGS:' in data or (b'OK' in data and b'AT+CMGF' in data):
        break
    time.sleep(0.2)
s.close()
print('\n---DONE---')
