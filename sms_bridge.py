import json
import os
import time
from flask import Flask, jsonify, request
from flask_cors import CORS
import serial
from serial import SerialException
from serial.tools import list_ports

SERIAL_PORT = os.environ.get("SMS_SERIAL_PORT", "COM9")
BAUDRATE = 9600
STARTUP_WAIT_SECONDS = 2.0
WRITE_TIMEOUT = 2
READ_TIMEOUT = 0.5

app = Flask(__name__)
CORS(app)


def detect_serial_port():
    preferred = str(SERIAL_PORT or "").strip()
    candidates = []
    if preferred and preferred.lower() != "auto":
        candidates.append(preferred)

    ports = [port.device for port in list_ports.comports()]
    for port in ports:
        if port not in candidates:
            candidates.append(port)

    for port in [f"COM{i}" for i in range(1, 21)]:
        if port not in candidates:
            candidates.append(port)

    for port in candidates:
        try:
            with serial.Serial(port=port, baudrate=BAUDRATE, timeout=0.5, write_timeout=WRITE_TIMEOUT) as ser:
                time.sleep(1.0)
                ser.write(b"AT\r\n")
                ser.flush()
                deadline = time.time() + 2.5
                data = ""
                while time.time() < deadline:
                    chunk = ser.readline()
                    if chunk:
                        data += chunk.decode("utf-8", errors="ignore")
                    if "OK" in data.upper():
                        return port
        except Exception:
            continue

    return preferred if preferred and preferred.lower() != "auto" else ports[0] if ports else SERIAL_PORT


def get_active_port():
    if str(SERIAL_PORT).strip().lower() == "auto":
        detected = detect_serial_port()
        return detected
    return str(SERIAL_PORT).strip()


def send_modem_command(command_text):
    command_text = str(command_text).strip()
    if not command_text:
        return False, {"error": "empty_command"}

    port = get_active_port()
    payload = (command_text + "\r\n").encode("ascii", errors="ignore")
    last_exc = None

    for attempt in range(3):
        try:
            with serial.Serial(
                port=port,
                baudrate=BAUDRATE,
                timeout=READ_TIMEOUT,
                write_timeout=WRITE_TIMEOUT,
            ) as ser:
                time.sleep(STARTUP_WAIT_SECONDS)
                try:
                    ser.reset_input_buffer()
                    ser.reset_output_buffer()
                except Exception:
                    pass

                ser.write(payload)
                ser.flush()

                responses = []
                deadline = time.time() + 15.0
                while time.time() < deadline:
                    try:
                        chunk = ser.read(4096)
                    except Exception:
                        chunk = b''

                    if chunk:
                        try:
                            text = chunk.decode("utf-8", errors="ignore")
                        except Exception:
                            text = str(chunk)

                        for line in text.splitlines():
                            line = line.strip()
                            if line:
                                responses.append(line)

                    joined = "\n".join(responses).upper()
                    if "+CMGS:" in joined or "+CMS ERROR" in joined or "SMS SENT SUCCESSFULLY" in joined or "ERROR:SMS_FAILED" in joined or "ERROR" in joined:
                        break

                    if len(responses) > 0 and responses[-1].endswith("OK"):
                        time.sleep(0.2)

                joined = "\n".join(responses).upper()
                success = "+CMGS:" in joined or "+CMS" in joined or "SMS SENT SUCCESSFULLY" in joined
                return True, {
                    "success": success,
                    "serial_port": port,
                    "responses": responses,
                    "message": "sms_sent" if success else "no_sms_confirmation",
                    "raw_response": joined,
                }
        except (SerialException, OSError) as exc:
            last_exc = exc
            time.sleep(0.5 + attempt)

    return False, {"success": False, "serial_port": port, "error": repr(last_exc)}


@app.route("/health", methods=["GET"])
def health():
    port = get_active_port()
    ok, payload = send_modem_command("TEST")
    if ok:
        return jsonify({"status": "running", "serial_connected": True, "serial_port": port, "baudrate": BAUDRATE, "payload": payload})
    return jsonify({"status": "running", "serial_connected": False, "serial_port": port, "baudrate": BAUDRATE, "payload": payload})


@app.route("/sms", methods=["POST"])
def sms():
    data = request.get_json(silent=True) or {}
    if isinstance(data, dict) and "command" in data:
        command = data.get("command")
    else:
        phone = str(data.get("phone", "") or "").strip()
        message = str(data.get("message", "") or "").strip()
        if not phone or not message:
            return jsonify({"success": False, "error": "bad_request"}), 400
        command = f"AT|{phone}|{message}"

    ok, payload = send_modem_command(command)
    if ok:
        status_code = 200 if payload.get("success") else 500
        return jsonify(payload), status_code
    return jsonify(payload), 500


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5020, debug=False)
