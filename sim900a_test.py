import argparse
import time
import sys

try:
    import serial
except ImportError:
    print("pyserial is required. Install with: python -m pip install pyserial")
    sys.exit(1)


def candidate_ports():
    ports = []
    for i in range(1, 21):
        ports.append(f"COM{i}")
    for i in range(0, 20):
        ports.append(f"/dev/ttyUSB{i}")
        ports.append(f"/dev/ttyACM{i}")
    # add a few likely extras
    ports.extend(["COM5", "COM6", "COM7", "COM8", "COM9", "COM10"])
    seen = []
    result = []
    for p in ports:
        if p not in seen:
            seen.append(p)
            result.append(p)
    return result


def read_all(ser, label="", wait=0.5):
    time.sleep(wait)
    data = ser.read_all()
    if data:
        text = data.decode("utf-8", errors="ignore")
        print(f"{label} {text}")
    else:
        print(f"{label} (no data)")


def open_port(port, baud):
    ser = serial.Serial(port, baud, timeout=1)
    time.sleep(2)
    return ser


def try_probe(port):
    try:
        ser = open_port(port, 9600)
    except Exception:
        return None

    try:
        ser.write(b"AT\r")
        time.sleep(1)
        data = ser.read_all()
        if b"OK" in data.upper():
            print(f"FOUND MODEM ON {port}")
            return ser
        ser.close()
        return None
    except Exception:
        try:
            ser.close()
        except Exception:
            pass
        return None


def select_port():
    for port in candidate_ports():
        ser = try_probe(port)
        if ser is not None:
            return ser
    return None


def run_test(phone_number):
    ser = select_port()
    if ser is None:
        print("No SIM900A modem detected on standard COM/tty ports.")
        print("Check wiring, power, and the actual port name.")
        return 1

    print("=== START ===")

    def send(cmd):
        ser.write((cmd + "\r").encode("ascii"))
        time.sleep(0.75)
        read_all(ser, f"{cmd}:", 0.5)

    send("AT")
    send("AT+CPIN?")
    send("AT+CREG?")
    send("AT+CSQ")
    send("AT+CMGF=1")
    ser.write((f'AT+CMGS="{phone_number}"\r').encode("ascii"))
    time.sleep(2)
    read_all(ser, "PROMPT:", 0.5)

    # Real SMS text followed by ASCII 26 = Ctrl+Z
    ser.write(b"Hello from SIM900A")
    time.sleep(0.5)
    ser.write(b"\x1A")
    time.sleep(3)
    read_all(ser, "RESULT:", 0.5)

    ser.close()
    print("=== DONE ===")
    return 0


def main():
    parser = argparse.ArgumentParser(description="Probe and test a connected SIM900A GSM modem")
    parser.add_argument("phone", nargs="?", default="+639637736202", help="Destination phone number")
    args = parser.parse_args()
    return run_test(args.phone)


if __name__ == "__main__":
    sys.exit(main())
