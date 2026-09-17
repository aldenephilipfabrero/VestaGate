"""
Tapo Camera Server - RTSP Stream Proxy + Direct Printing
Connects Tapo C560WS camera to the web interface
Handles direct printing of violation tickets
"""

import os
os.environ['KMP_DUPLICATE_LIB_OK'] = 'TRUE'
os.environ['OMP_NUM_THREADS'] = '1'

from flask import Flask, Response, jsonify, request
from flask_cors import CORS
import cv2
import threading
import time
import base64
import numpy as np
import subprocess
import tempfile

# Windows printing support
try:
    import win32print
    import win32ui
    from PIL import Image, ImageDraw, ImageFont, ImageWin
    WIN32_PRINT_AVAILABLE = True
except ImportError:
    WIN32_PRINT_AVAILABLE = False
    print("⚠️  win32print not available. Install with: pip install pywin32 pillow")

app = Flask(__name__)
CORS(app)

# =============================================================================
# CAMERA CONFIGURATION - UPDATE THESE VALUES
# =============================================================================
CAMERA_CONFIG = {
    'ip': '192.168.254.133',     # Your Tapo camera IP address
    'username': os.getenv('TAPO_CAMERA_USERNAME', ''),
    'password': os.getenv('TAPO_CAMERA_PASSWORD', ''),
    'stream': 'stream2',         # stream1 (HD) or stream2 (SD - recommended for detection)
    'port': 554                  # RTSP port (default: 554)
}
# =============================================================================

class TapoCameraStream:
    def __init__(self, config):
        self.config = config
        self.rtsp_url = self._build_rtsp_url()
        self.frame = None
        self.lock = threading.Lock()
        self.running = False
        self.connected = False
        self.last_frame_time = 0
        self.fps = 0
        self.cap = None
        
    def _build_rtsp_url(self):
        """Build RTSP URL for Tapo camera - try multiple formats"""
        # URL encode password in case of special characters
        from urllib.parse import quote
        password = quote(self.config['password'], safe='')
        username = quote(self.config['username'], safe='')
        
        # Tapo C560WS RTSP formats to try:
        # Format 1: Standard Tapo format
        # Format 2: With /h264 suffix
        # Format 3: Onvif style
        stream = self.config['stream']
        ip = self.config['ip']
        port = self.config['port']
        
        # Primary format for Tapo cameras
        url = f"rtsp://{username}:{password}@{ip}:{port}/{stream}"
        print(f"[Camera] RTSP URL: rtsp://{username}:****@{ip}:{port}/{stream}")
        return url
    
    def get_alternate_urls(self):
        """Get list of alternate RTSP URLs to try"""
        from urllib.parse import quote
        password = quote(self.config['password'], safe='')
        username = quote(self.config['username'], safe='')
        ip = self.config['ip']
        port = self.config['port']
        
        return [
            f"rtsp://{username}:{password}@{ip}:{port}/stream1",
            f"rtsp://{username}:{password}@{ip}:{port}/stream2", 
            f"rtsp://{username}:{password}@{ip}:{port}/h264_stream",
            f"rtsp://{username}:{password}@{ip}:{port}/live/ch00_0",
            f"rtsp://{username}:{password}@{ip}:{port}/onvif1",
            f"rtsp://{username}:{password}@{ip}:8554/stream1",
        ]
    
    def start(self):
        """Start the camera capture thread"""
        if self.running:
            return
        self.running = True
        self.thread = threading.Thread(target=self._capture_loop, daemon=True)
        self.thread.start()
        print(f"[Camera] Starting stream from {self.config['ip']}")
        
    def stop(self):
        """Stop the camera capture"""
        self.running = False
        if self.cap:
            self.cap.release()
            
    def _capture_loop(self):
        """Main capture loop - runs in background thread"""
        reconnect_delay = 5
        frame_count = 0
        fps_start_time = time.time()
        
        # Try main URL first, then alternates
        urls_to_try = [self.rtsp_url] + self.get_alternate_urls()
        current_url_index = 0
        
        while self.running:
            try:
                current_url = urls_to_try[current_url_index]
                # Hide password in log
                safe_url = current_url.split('@')[1] if '@' in current_url else current_url
                print(f"[Camera] Trying RTSP: ...@{safe_url}")
                
                # OpenCV RTSP settings for better stability
                self.cap = cv2.VideoCapture(current_url, cv2.CAP_FFMPEG)
                self.cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)  # Minimize buffer delay
                self.cap.set(cv2.CAP_PROP_FPS, 15)
                
                if not self.cap.isOpened():
                    print(f"[Camera] Failed with this URL format")
                    self.connected = False
                    # Try next URL format
                    current_url_index = (current_url_index + 1) % len(urls_to_try)
                    if current_url_index == 0:
                        print(f"[Camera] Tried all URLs. Waiting {reconnect_delay}s before retry...")
                        time.sleep(reconnect_delay)
                    else:
                        time.sleep(1)
                    continue
                    
                print("[Camera] ✓ Connected successfully!")
                self.connected = True
                self.rtsp_url = current_url  # Remember working URL
                
                # Capture frames
                while self.running and self.cap.isOpened():
                    ret, frame = self.cap.read()
                    
                    if not ret:
                        print("[Camera] Frame read failed, reconnecting...")
                        break
                    
                    # Update frame with thread safety
                    with self.lock:
                        self.frame = frame
                        self.last_frame_time = time.time()
                    
                    # Calculate FPS
                    frame_count += 1
                    elapsed = time.time() - fps_start_time
                    if elapsed >= 1.0:
                        self.fps = frame_count / elapsed
                        frame_count = 0
                        fps_start_time = time.time()
                    
                    # Small delay to prevent CPU overload
                    time.sleep(0.01)
                    
            except Exception as e:
                print(f"[Camera] Error: {e}")
                self.connected = False
                
            finally:
                if self.cap:
                    self.cap.release()
                    self.cap = None
                    
            if self.running:
                print(f"[Camera] Reconnecting in {reconnect_delay}s...")
                time.sleep(reconnect_delay)
                
    def get_frame(self):
        """Get the current frame"""
        with self.lock:
            return self.frame.copy() if self.frame is not None else None
            
    def get_frame_jpeg(self, quality=80):
        """Get current frame as JPEG bytes"""
        frame = self.get_frame()
        if frame is None:
            return None
        ret, jpeg = cv2.imencode('.jpg', frame, [cv2.IMWRITE_JPEG_QUALITY, quality])
        return jpeg.tobytes() if ret else None
        
    def get_frame_base64(self, quality=80):
        """Get current frame as base64 string"""
        jpeg = self.get_frame_jpeg(quality)
        if jpeg is None:
            return None
        return base64.b64encode(jpeg).decode('utf-8')


# Initialize camera
camera = TapoCameraStream(CAMERA_CONFIG)


@app.route('/video_feed')
def video_feed():
    """MJPEG stream endpoint for browser"""
    def generate():
        while True:
            jpeg = camera.get_frame_jpeg(quality=70)
            if jpeg:
                yield (b'--frame\r\n'
                       b'Content-Type: image/jpeg\r\n\r\n' + jpeg + b'\r\n')
            else:
                # Send placeholder if no frame
                time.sleep(0.1)
    
    return Response(generate(),
                    mimetype='multipart/x-mixed-replace; boundary=frame')


@app.route('/snapshot')
def snapshot():
    """Get single frame as JPEG"""
    jpeg = camera.get_frame_jpeg(quality=90)
    if jpeg:
        return Response(jpeg, mimetype='image/jpeg')
    return jsonify({'error': 'No frame available'}), 503


@app.route('/snapshot_base64')
def snapshot_base64():
    """Get single frame as base64 (for YOLO detection)"""
    b64 = camera.get_frame_base64(quality=85)
    if b64:
        return jsonify({
            'image': f'data:image/jpeg;base64,{b64}',
            'timestamp': time.time()
        })
    return jsonify({'error': 'No frame available'}), 503


@app.route('/status')
def status():
    """Camera status endpoint"""
    return jsonify({
        'connected': camera.connected,
        'fps': round(camera.fps, 1),
        'camera_ip': CAMERA_CONFIG['ip'],
        'stream': CAMERA_CONFIG['stream'],
        'last_frame_age': time.time() - camera.last_frame_time if camera.last_frame_time else None
    })


@app.route('/config', methods=['GET', 'POST'])
def config():
    """Get or update camera configuration"""
    global camera, CAMERA_CONFIG
    
    if request.method == 'POST':
        data = request.get_json()
        if data:
            # Update config
            if 'ip' in data:
                CAMERA_CONFIG['ip'] = data['ip']
            if 'username' in data:
                CAMERA_CONFIG['username'] = data['username']
            if 'password' in data:
                CAMERA_CONFIG['password'] = data['password']
            if 'stream' in data:
                CAMERA_CONFIG['stream'] = data['stream']
            
            # Restart camera with new config
            camera.stop()
            time.sleep(1)
            camera = TapoCameraStream(CAMERA_CONFIG)
            camera.start()
            
            return jsonify({'success': True, 'message': 'Camera configuration updated'})
    
    return jsonify({
        'ip': CAMERA_CONFIG['ip'],
        'username': CAMERA_CONFIG['username'],
        'stream': CAMERA_CONFIG['stream'],
        'port': CAMERA_CONFIG['port']
    })


@app.route('/')
def index():
    """Simple test page"""
    return f'''
    <!DOCTYPE html>
    <html>
    <head>
        <title>Tapo Camera Server</title>
        <style>
            body {{ font-family: Arial; background: #1a1a1a; color: white; padding: 20px; }}
            .container {{ max-width: 800px; margin: 0 auto; }}
            h1 {{ color: #D4AF37; }}
            .status {{ padding: 15px; background: #2d2d2d; border-radius: 10px; margin: 20px 0; }}
            .status.connected {{ border-left: 4px solid #27ae60; }}
            .status.disconnected {{ border-left: 4px solid #8B1538; }}
            img {{ max-width: 100%; border-radius: 10px; }}
            .info {{ color: #888; font-size: 0.9em; }}
        </style>
    </head>
    <body>
        <div class="container">
            <h1>📷 Tapo C560WS Camera Server</h1>
            <div class="status" id="status">Checking status...</div>
            <h3>Live Feed</h3>
            <img src="/video_feed" alt="Camera Feed" onerror="this.src=''; this.alt='Camera not connected'">
            <div class="info" style="margin-top: 20px;">
                <p><strong>Endpoints:</strong></p>
                <ul>
                    <li><code>/video_feed</code> - MJPEG stream for browsers</li>
                    <li><code>/snapshot</code> - Single JPEG frame</li>
                    <li><code>/snapshot_base64</code> - Base64 frame for YOLO</li>
                    <li><code>/status</code> - Camera status JSON</li>
                </ul>
            </div>
        </div>
        <script>
            fetch('/status')
                .then(r => r.json())
                .then(data => {{
                    const el = document.getElementById('status');
                    el.className = 'status ' + (data.connected ? 'connected' : 'disconnected');
                    el.innerHTML = data.connected 
                        ? `✓ Connected to ${{data.camera_ip}} | Stream: ${{data.stream}} | FPS: ${{data.fps}}`
                        : `✗ Disconnected - Camera IP: ${{data.camera_ip}}`;
                }});
        </script>
    </body>
    </html>
    '''


# =============================================================================
# DIRECT PRINTING FUNCTIONALITY
# =============================================================================

def get_default_printer():
    """Get the default printer name"""
    if WIN32_PRINT_AVAILABLE:
        return win32print.GetDefaultPrinter()
    return None

def print_ticket_direct(ticket_data):
    """Print ticket directly to default printer without any dialogs"""
    if not WIN32_PRINT_AVAILABLE:
        return False, "win32print not available"
    
    try:
        printer_name = get_default_printer()
        if not printer_name:
            return False, "No default printer found"
        
        # Create ticket image (58mm = ~164 pixels at 72 DPI, use 200 for better quality)
        width = 200
        height = 350
        
        # Create white image
        img = Image.new('RGB', (width, height), 'white')
        draw = ImageDraw.Draw(img)
        
        # Try to load fonts, fallback to default
        try:
            font_bold = ImageFont.truetype("arialbd.ttf", 12)
            font_normal = ImageFont.truetype("arial.ttf", 10)
            font_small = ImageFont.truetype("arial.ttf", 8)
            font_mono = ImageFont.truetype("consola.ttf", 11)
        except:
            font_bold = ImageFont.load_default()
            font_normal = font_bold
            font_small = font_bold
            font_mono = font_bold
        
        y = 10
        
        # Header
        draw.text((width//2, y), "Baco National High School", font=font_bold, fill='black', anchor='mt')
        y += 15
        draw.text((width//2, y), "VIOLATION TICKET", font=font_bold, fill='black', anchor='mt')
        y += 18
        
        # Dashed line
        draw.line([(10, y), (width-10, y)], fill='black', width=1)
        y += 8
        
        # Ticket number
        draw.rectangle([(10, y), (width-10, y+22)], fill='#eeeeee', outline='black')
        draw.text((width//2, y+11), ticket_data.get('ticket_number', 'VT-000000'), font=font_mono, fill='black', anchor='mm')
        y += 30
        
        # Info rows
        info_items = [
            ('Date:', ticket_data.get('date', '')),
            ('Time:', ticket_data.get('time', '')),
            ('ID:', ticket_data.get('student_id', '')),
            ('Name:', ticket_data.get('name', '')),
            ('Gr/Sec:', f"G{ticket_data.get('grade', '')}-{ticket_data.get('section', '')}")
        ]
        
        for label, value in info_items:
            draw.text((15, y), label, font=font_normal, fill='black')
            draw.text((60, y), str(value)[:20], font=font_normal, fill='black')
            y += 14
        
        y += 5
        
        # Violation box
        draw.rectangle([(10, y), (width-10, y+40)], fill='#f5f5f5', outline='black')
        draw.text((width//2, y+8), "VIOLATION", font=font_small, fill='black', anchor='mt')
        
        # Word wrap violation type
        violation = ticket_data.get('violation_type', 'Unknown')
        if len(violation) > 25:
            violation = violation[:25] + '...'
        draw.text((width//2, y+24), violation, font=font_bold, fill='black', anchor='mt')
        y += 48
        
        # Footer
        draw.line([(10, y), (width-10, y)], fill='black', width=1)
        y += 5
        draw.text((width//2, y), "Submit to DISCIPLINE OFFICE", font=font_small, fill='black', anchor='mt')
        y += 12
        draw.text((width//2, y), f"[{ticket_data.get('ticket_number', '')}]", font=font_small, fill='black', anchor='mt')
        
        # Print the image
        hdc = win32ui.CreateDC()
        hdc.CreatePrinterDC(printer_name)
        
        hdc.StartDoc('Violation Ticket')
        hdc.StartPage()
        
        # Scale to fit printer
        printer_width = hdc.GetDeviceCaps(110)  # PHYSICALWIDTH
        printer_height = hdc.GetDeviceCaps(111)  # PHYSICALHEIGHT
        
        # Calculate scaling
        scale_x = printer_width / width
        scale_y = printer_height / height
        scale = min(scale_x, scale_y, 2.0)  # Don't scale up more than 2x
        
        new_width = int(width * scale)
        new_height = int(height * scale)
        
        dib = ImageWin.Dib(img)
        dib.draw(hdc.GetHandleOutput(), (0, 0, new_width, new_height))
        
        hdc.EndPage()
        hdc.EndDoc()
        hdc.DeleteDC()
        
        return True, f"Printed to {printer_name}"
        
    except Exception as e:
        return False, str(e)


@app.route('/print_ticket', methods=['POST'])
def print_ticket():
    """Direct print endpoint - prints ticket without any UI"""
    try:
        data = request.get_json()
        
        if not data:
            return jsonify({'success': False, 'error': 'No data provided'}), 400
        
        success, message = print_ticket_direct(data)
        
        return jsonify({
            'success': success,
            'message': message,
            'printer': get_default_printer() if WIN32_PRINT_AVAILABLE else None
        })
        
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/printer_status')
def printer_status():
    """Check printer availability"""
    if not WIN32_PRINT_AVAILABLE:
        return jsonify({
            'available': False,
            'error': 'win32print not installed. Run: pip install pywin32 pillow'
        })
    
    try:
        default_printer = get_default_printer()
        printers = [p[2] for p in win32print.EnumPrinters(win32print.PRINTER_ENUM_LOCAL | win32print.PRINTER_ENUM_CONNECTIONS)]
        
        return jsonify({
            'available': True,
            'default_printer': default_printer,
            'all_printers': printers
        })
    except Exception as e:
        return jsonify({
            'available': False,
            'error': str(e)
        })


if __name__ == '__main__':
    print("=" * 60)
    print("TAPO C560WS CAMERA SERVER + PRINT SERVICE")
    print("=" * 60)
    print(f"\nCamera IP: {CAMERA_CONFIG['ip']}")
    print(f"Stream: {CAMERA_CONFIG['stream']}")
    print("\n⚠️  Update CAMERA_CONFIG in this file with your camera details!")
    print("   - IP address of your Tapo camera")
    print("   - Username/password (set in Tapo app > Camera Settings > Advanced)")
    
    # Print status
    if WIN32_PRINT_AVAILABLE:
        print(f"\n✓ Direct printing available")
        print(f"  Default printer: {get_default_printer()}")
    else:
        print(f"\n⚠️  Direct printing NOT available")
        print(f"  Install with: pip install pywin32 pillow")
    print()
    
    # Start camera capture in background thread (non-blocking)
    camera_thread = threading.Thread(target=camera.start, daemon=True)
    camera_thread.start()
    
    print("Starting server on http://localhost:5001")
    print("Endpoints:")
    print("  /video_feed      - MJPEG stream")
    print("  /snapshot        - Single JPEG frame")
    print("  /print_ticket    - Direct ticket printing (POST)")
    print("  /printer_status  - Check printer status")
    print()
    
    app.run(host='0.0.0.0', port=5001, debug=False, threaded=True)
