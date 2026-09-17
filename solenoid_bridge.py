import time
from flask import Flask, jsonify, request
from flask_cors import CORS
import serial
from serial import SerialException

import os

SERIAL_PORT = os.environ.get("SOLENOID_SERIAL_PORT", "COM4")
BAUDRATE = 9600
STARTUP_WAIT_SECONDS = 2.0
WRITE_TIMEOUT = 2
READ_TIMEOUT = 0.5

app = Flask(__name__)
CORS(app)

def write_serial(command_bytes, read_lines=True):
    last_exc = None
    for attempt in range(3):
        try:
            with serial.Serial(
                port=SERIAL_PORT,
                baudrate=BAUDRATE,
                timeout=READ_TIMEOUT,
                write_timeout=WRITE_TIMEOUT,
            ) as ser:
                print(f"DEBUG: Opened serial {SERIAL_PORT} @ {BAUDRATE}")
                time.sleep(STARTUP_WAIT_SECONDS)
                try:
                    ser.reset_input_buffer()
                    ser.reset_output_buffer()
                except Exception:
                    pass

                ser.write(command_bytes)
                ser.flush()

                responses = []
                if read_lines:
                    deadline = time.time() + 2.0
                    while time.time() < deadline:
                        line = ser.readline()
                        if line:
                            try:
                                text = line.decode('utf-8', errors='ignore').strip()
                                if text:
                                    responses.append(text)
                                    if 'OK' in text:
                                        return True, responses
                            except Exception:
                                pass
                return True, responses
        except (SerialException, OSError) as exc:
            last_exc = exc
            print(f"WARN: write_serial attempt {attempt + 1}/3 failed: {repr(exc)}")
            time.sleep(0.5 + attempt)

    return False, repr(last_exc)


def send_open_command(open_ms):
    command = f"OPEN {int(open_ms)}\r\n".encode("ascii", errors="ignore")
    wrote, error = write_serial(command, read_lines=True)
    if not wrote:
        return False, [f"SERIAL_WRITE_EXCEPTION: {error}"]

    return True, error if isinstance(error, list) else []


@app.route("/health", methods=["GET"])
def health():
    connected, _ = write_serial(b"TEST\r\n", read_lines=False)
    return jsonify({
        "status": "running",
        "serial_connected": connected,
        "serial_port": SERIAL_PORT,
        "baudrate": BAUDRATE,
    })


@app.route("/open", methods=["POST"])
def open_gate():
    data = request.get_json(silent=True) or {}
    open_ms = data.get("open_ms", 3000)

    try:
        open_ms = int(open_ms)
    except (TypeError, ValueError):
        open_ms = 3000

    if open_ms <= 0:
        open_ms = 3000

    ok, responses = send_open_command(open_ms)
    return jsonify({"success": ok, "responses": responses, "open_ms": open_ms})


@app.route("/pulse", methods=["POST"])
def pulse_pins():
    data = request.get_json(silent=True) or {}
    pins = data.get('pins') or []
    value = int(data.get('value', 1))
    duration = int(data.get('duration', 3000))

    if not isinstance(pins, list) or len(pins) == 0:
        return jsonify({'success': False, 'error': 'no_pins'}), 400

    ok, responses = True, []

    # Send SET commands for each pin with basic retry handling
    try:
        for p in pins:
            cmd = f"SET {int(p)} {int(value)}\r\n".encode('ascii')
            wrote, error = write_serial(cmd, read_lines=True)
            if not wrote:
                return jsonify({'success': False, 'error': 'SERIAL_WRITE_EXCEPTION', 'exception': error})
        # wait duration
        time.sleep(max(0.05, duration/1000.0))
        # clear pins
        for p in pins:
            cmd = f"SET {int(p)} 0\r\n".encode('ascii')
            wrote, error = write_serial(cmd, read_lines=True)
            if not wrote:
                return jsonify({'success': False, 'error': 'SERIAL_WRITE_EXCEPTION', 'exception': error})
    except Exception as e:
        return jsonify({'success': False, 'error': 'SERIAL_WRITE_EXCEPTION', 'exception': repr(e)})

    return jsonify({'success': True, 'responses': responses, 'pins': pins, 'duration': duration})


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5010, debug=False)
