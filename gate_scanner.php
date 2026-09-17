<?php
session_start();
include 'config.php';

// Get system settings
$settings = [];
$settingsResult = $conn->query("SELECT setting_key, setting_value FROM system_settings");
while ($s = $settingsResult->fetch_assoc()) {
    $settings[$s['setting_key']] = $s['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gate Scanner - RFID Compliance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: white;
        }
        
        .header {
            background: rgba(139,21,56,0.4);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #8B1538;
        }
        
        .header h1 {
            font-size: 1.5rem;
            color: #D4AF37;
        }
        
        .header .team {
            font-size: 0.8rem;
            color: #888;
        }
        
        .main-container {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 20px;
            padding: 20px;
            height: calc(100vh - 80px);
            width: 100%;
            max-width: none;
            margin: 0;
        }
        
        .camera-section {
            background: rgba(0,0,0,0.4);
            border-radius: 15px;
            padding: 20px;
            display: flex;
            flex-direction: column;
        }
        
        .camera-feed {
            flex: 1;
            background: #000;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            min-height: 400px;
        }
        
        .camera-feed video,
        .camera-feed img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        /* Detection boxes canvas overlay */
        #detectionCanvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 5;
        }
        
        /* Status indicator */
        .detection-status {
            position: absolute;
            bottom: 50px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.8);
            color: #fff;
            padding: 10px 25px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: bold;
            z-index: 10;
            display: none;
        }
        
        .detection-status.scanning {
            display: block;
            background: rgba(255, 255, 0, 0.9);
            color: #000;
            animation: pulse-status 0.5s ease-in-out infinite;
        }
        
        .detection-status.compliant {
            display: block;
            background: rgba(39, 174, 96, 0.9);
            color: #fff;
        }
        
        .detection-status.violation {
            display: block;
            background: rgba(233, 69, 96, 0.9);
            color: #fff;
            animation: pulse-status 0.3s ease-in-out infinite;
        }
        
        @keyframes pulse-status {
            0%, 100% { transform: translateX(-50%) scale(1); }
            50% { transform: translateX(-50%) scale(1.05); }
        }
        
        .camera-source-toggle {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(0,0,0,0.7);
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8rem;
            cursor: pointer;
            z-index: 10;
        }
        
        .camera-source-toggle:hover {
            background: rgba(233,69,96,0.7);
        }
        
        .camera-overlay {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(0,0,0,0.7);
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        
        .camera-overlay.recording {
            color: #8B1538;
        }
        
        .camera-overlay.recording::before {
            content: '●';
            margin-right: 8px;
            animation: blink 1s infinite;
        }
        
        @keyframes blink {
            50% { opacity: 0; }
        }
        
        .scan-section {
            margin-top: 20px;
            text-align: center;
        }
        
        .rfid-input {
            width: 100%;
            padding: 15px 20px;
            font-size: 1.2rem;
            border: 2px solid #8B1538;
            border-radius: 10px;
            background: rgba(0,0,0,0.5);
            color: white;
            text-align: center;
            letter-spacing: 3px;
        }
        
        .rfid-input:focus {
            outline: none;
            border-color: #D4AF37;
            box-shadow: 0 0 20px rgba(212,175,55,0.3);
        }
        
        .rfid-input::placeholder {
            color: #666;
            letter-spacing: normal;
        }
        
        .control-panel {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .status-card {
            background: rgba(0,0,0,0.4);
            border-radius: 15px;
            padding: 20px;
        }
        
        .status-card h3 {
            font-size: 1rem;
            color: #888;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .student-info {
            text-align: center;
        }
        
        .student-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: #333;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #666;
            border: 3px solid #8B1538;
        }
        
        .student-name {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .student-details {
            color: #888;
            font-size: 0.9rem;
        }
        
        .compliance-status {
            margin-top: 20px;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        .compliance-status.compliant {
            background: rgba(39, 174, 96, 0.2);
            border: 2px solid #27ae60;
            color: #27ae60;
        }
        
        .compliance-status.non-compliant {
            background: rgba(139, 21, 56, 0.2);
            border: 2px solid #8B1538;
            color: #8B1538;
        }
        
        .compliance-status.waiting {
            background: rgba(241, 196, 15, 0.2);
            border: 2px solid #f1c40f;
            color: #f1c40f;
        }
        
        .check-items {
            margin-top: 15px;
        }
        
        .check-item {
            display: flex;
            align-items: center;
            padding: 10px;
            background: rgba(0,0,0,0.3);
            border-radius: 8px;
            margin-bottom: 8px;
        }
        
        .check-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-weight: bold;
        }
        
        .check-icon.pass {
            background: #27ae60;
        }
        
        .check-icon.fail {
            background: #8B1538;
        }
        
        .check-icon.pending {
            background: #666;
        }
        
        .recent-scans {
            flex: 1;
            overflow-y: auto;
            max-height: 250px;
        }
        
        .scan-entry {
            display: flex;
            align-items: center;
            padding: 10px;
            background: rgba(0,0,0,0.3);
            border-radius: 8px;
            margin-bottom: 8px;
        }
        
        .scan-entry .time {
            color: #888;
            font-size: 0.8rem;
            width: 60px;
        }
        
        .scan-entry .name {
            flex: 1;
            font-size: 0.9rem;
        }
        
        .scan-entry .status-badge {
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
        .status-badge.pass {
            background: rgba(39, 174, 96, 0.3);
            color: #27ae60;
        }
        
        .status-badge.fail {
            background: rgba(139, 21, 56, 0.3);
            color: #8B1538;
        }
        
        .gate-status {
            text-align: center;
            padding: 15px;
            border-radius: 10px;
            background: rgba(39, 174, 96, 0.2);
            border: 2px solid #27ae60;
        }
        
        .gate-status .icon {
            font-size: 2rem;
            margin-bottom: 5px;
        }
        
        .nav-link-admin {
            color: #D4AF37;
            text-decoration: none;
            padding: 8px 15px;
            border: 1px solid #D4AF37;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .nav-link-admin:hover {
            background: #D4AF37;
            color: #1a1a1a;
        }
        
        .datetime {
            text-align: center;
            padding: 10px;
            background: rgba(0,0,0,0.3);
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .datetime .date {
            font-size: 1.1rem;
            color: #D4AF37;
        }
        
        .datetime .time {
            font-size: 1.8rem;
            font-weight: bold;
        }
        
        .ticket-alert {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(233, 69, 96, 0.95);
            padding: 40px 60px;
            border-radius: 15px;
            text-align: center;
            z-index: 1000;
            display: none;
            animation: pulse 0.5s ease;
        }
        
        @keyframes pulse {
            0%, 100% { transform: translate(-50%, -50%) scale(1); }
            50% { transform: translate(-50%, -50%) scale(1.05); }
        }
        
        .ticket-alert h2 {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .ticket-alert .ticket-number {
            font-size: 1.5rem;
            font-family: monospace;
            background: rgba(0,0,0,0.3);
            padding: 10px 20px;
            border-radius: 8px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <?php include 'admin_nav.php'; ?>
    
    <div class="main-container">
        <!-- Camera & Scanner Section -->
        <div class="camera-section">
            <div class="camera-feed" id="cameraFeed">
                <video id="video" autoplay playsinline style="display: none;"></video>
                <img id="tapoFeed" src="" alt="Tapo Camera" style="display: none;">
                
                <!-- Detection Boxes Canvas -->
                <canvas id="detectionCanvas"></canvas>
                
                <!-- Detection Status Label -->
                <div class="detection-status" id="detectionStatus">SCANNING...</div>
                
                <div class="camera-overlay recording" id="cameraOverlay">LIVE - Computer Vision Active</div>
                <div class="camera-source-toggle" id="sourceToggle" onclick="toggleCameraSource()">📷 Switch Camera</div>
                
                <!-- Detection Legend -->
                <div style="position: absolute; top: 10px; left: 10px; background: rgba(0,0,0,0.8); padding: 8px 12px; border-radius: 8px; font-size: 11px; z-index: 10;">
                    <div style="color: #fff; font-weight: bold; margin-bottom: 5px;">🎯 Detection Colors:</div>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <span style="color: #FF4500;">● Person</span>
                        <span style="color: #00FF00;">● ID Lace</span>
                        <span style="color: #00BFFF;">● Polo</span>
                        <span style="color: #FFD700;">● Shoes</span>
                        <span style="color: #FF1493;">● Skirt</span>
                        <span style="color: #9400D3;">● Pants</span>
                    </div>
                </div>
            </div>
            
            <div class="scan-section">
                <input type="text" class="rfid-input" id="rfidInput" placeholder="Scan RFID Card..." autofocus>
                <p style="margin-top: 10px; color: #666; font-size: 0.9rem;">Position student in front of camera, then scan RFID card</p>
                <div style="margin-top: 10px; font-size: 0.8rem; color: #888;">
                    Camera: <span id="cameraStatus">Checking...</span>
                </div>
                <!-- Debug Panel -->
                <div id="debugPanel" style="margin-top: 15px; padding: 10px; background: #1a1a1a; border-radius: 5px; font-size: 0.75rem; color: #aaa; max-height: 150px; overflow-y: auto;">
                    <div style="color: #D4AF37; font-weight: bold;">📊 Debug Info:</div>
                    <div id="debugInfo">Waiting for scan...</div>
                </div>
            </div>
        </div>
        
        <!-- Control Panel -->
        <div class="control-panel">
            <div class="datetime">
                <div class="date" id="currentDate"></div>
                <div class="time" id="currentTime"></div>
            </div>
            
            <div class="gate-status">
                <div class="icon">🚪</div>
                <div>Gate Status: <strong>OPEN</strong></div>
                <small style="color: #888;">Non-compliant students receive violation tickets</small>
            </div>
            
            <div class="status-card">
                <h3>Current Scan</h3>
                <div class="student-info" id="studentInfo">
                    <div class="student-photo">👤</div>
                    <div class="student-name">Waiting for scan...</div>
                    <div class="student-details">Scan RFID to identify student</div>
                </div>
                
                <div class="compliance-status waiting" id="complianceStatus">
                    AWAITING SCAN
                </div>
                
                <div class="check-items" id="checkItems">
                    <div class="check-item">
                        <div class="check-icon pending">?</div>
                        <span>RFID Authentication</span>
                    </div>
                    <div class="check-item">
                        <div class="check-icon pending">?</div>
                        <span>Uniform Compliance</span>
                    </div>
                    <div class="check-item">
                        <div class="check-icon pending">?</div>
                        <span>ID Lanyard Visible</span>
                    </div>
                </div>
            </div>
            
            <div class="status-card" style="flex: 1;">
                <h3>Recent Entries</h3>
                <div class="recent-scans" id="recentScans">
                    <div style="color: #666; text-align: center; padding: 20px;">No entries yet today</div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="ticket-alert" id="ticketAlert">
        <h2>⚠️ VIOLATION TICKET ISSUED</h2>
        <div class="ticket-number" id="ticketNumber">VT-000000</div>
        <p>Please proceed to the Discipline Office</p>
    </div>

    <script>
        // Canvas for capturing video frames
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        // Camera configuration
        const TAPO_CAMERA_SERVER = 'http://localhost:5001';  // Camera server URL
        let currentCameraSource = 'tapo';  // 'tapo' or 'webcam'
        let tapoConnected = false;
        let availableCameras = [];
        let selectedLocalCameraIndex = 0;
        
        async function getAvailableCameraDevices() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
                return [];
            }

            try {
                const devices = await navigator.mediaDevices.enumerateDevices();
                return devices.filter(device => device.kind === 'videoinput');
            } catch (err) {
                console.log('Unable to enumerate camera devices:', err);
                return [];
            }
        }

        async function refreshAvailableCameraDevices() {
            availableCameras = await getAvailableCameraDevices();

            if (availableCameras.length === 0 && navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                try {
                    const probeStream = await navigator.mediaDevices.getUserMedia({ video: true });
                    probeStream.getTracks().forEach(track => track.stop());
                    availableCameras = await getAvailableCameraDevices();
                } catch (err) {
                    console.log('Unable to probe local camera devices:', err);
                }
            }

            return availableCameras;
        }

        // Initialize camera - try Tapo first, fallback to the first available local camera device
        async function initCamera() {
            const tapoAvailable = await checkTapoCamera();
            
            if (tapoAvailable) {
                useTapoCamera();
                return;
            }

            availableCameras = await refreshAvailableCameraDevices();
            if (availableCameras.length > 0) {
                await useWebcam(selectedLocalCameraIndex);
            } else {
                // Device labels may be hidden until camera permission is granted.
                await useWebcam();
            }
        }
        
        // Check if Tapo camera server is running
        async function checkTapoCamera() {
            try {
                const response = await fetch(TAPO_CAMERA_SERVER + '/status', {
                    method: 'GET',
                    mode: 'cors'
                });
                const data = await response.json();
                tapoConnected = data.connected;
                return data.connected;
            } catch (err) {
                console.log('Tapo camera server not available:', err.message);
                return false;
            }
        }
        
        // Use Tapo IP camera
        function useTapoCamera() {
            currentCameraSource = 'tapo';
            document.getElementById('video').style.display = 'none';
            const tapoImg = document.getElementById('tapoFeed');
            tapoImg.src = TAPO_CAMERA_SERVER + '/video_feed';
            tapoImg.style.display = 'block';
            document.getElementById('cameraOverlay').textContent = 'LIVE - Tapo C560WS (YOLO Active)';
            document.getElementById('cameraStatus').innerHTML = '🟢 Tapo C560WS Connected';
            document.getElementById('sourceToggle').textContent = '📷 Use Webcam';
            console.log('Using Tapo camera');
        }
        
        // Use browser webcam or other available local camera device
        async function useWebcam(cameraIndex = 0) {
            currentCameraSource = 'webcam';
            document.getElementById('tapoFeed').style.display = 'none';
            document.getElementById('tapoFeed').src = '';

            const video = document.getElementById('video');
            if (video.srcObject) {
                video.srcObject.getTracks().forEach(track => track.stop());
                video.srcObject = null;
            }

            const candidateCameras = availableCameras.length > 0 ? availableCameras : [];
            const preferredCamera = candidateCameras[Math.min(cameraIndex, Math.max(candidateCameras.length - 1, 0))] || null;
            const constraintAttempts = [];

            if (preferredCamera && preferredCamera.deviceId) {
                constraintAttempts.push({ video: { deviceId: { exact: preferredCamera.deviceId }, width: 1280, height: 720 } });
            }

            constraintAttempts.push(
                { video: { facingMode: { ideal: 'environment' }, width: 1280, height: 720 } },
                { video: { facingMode: { ideal: 'user' }, width: 1280, height: 720 } },
                { video: { width: 1280, height: 720 } }
            );

            let stream = null;
            let resolvedCameraName = 'Webcam';

            for (let i = 0; i < constraintAttempts.length; i++) {
                const constraints = constraintAttempts[i];
                try {
                    stream = await navigator.mediaDevices.getUserMedia(constraints);
                    if (preferredCamera) {
                        resolvedCameraName = preferredCamera.label || `Camera ${cameraIndex + 1}`;
                    } else {
                        resolvedCameraName = i === 0 ? 'Webcam' : 'External Camera';
                    }
                    break;
                } catch (err) {
                    console.log('Constraint attempt failed:', constraints, err);
                    stream = null;
                }
            }

            if (!stream) {
                if (availableCameras.length > 1 && cameraIndex < availableCameras.length - 1) {
                    selectedLocalCameraIndex = cameraIndex + 1;
                    await useWebcam(selectedLocalCameraIndex);
                    return;
                }

                if (availableCameras.length === 0) {
                    availableCameras = await refreshAvailableCameraDevices();
                }

                if (availableCameras.length > 1) {
                    selectedLocalCameraIndex = 0;
                    await useWebcam(selectedLocalCameraIndex);
                    return;
                }

                showNoCameraMessage();
                return;
            }

            video.srcObject = stream;
            video.style.display = 'block';
            document.getElementById('cameraOverlay').textContent = 'LIVE - ' + resolvedCameraName + ' (YOLO Active)';
            document.getElementById('cameraStatus').innerHTML = '🟢 ' + resolvedCameraName + ' Active';
            document.getElementById('sourceToggle').textContent = '📷 Use Tapo Camera';
            console.log('Using local camera:', resolvedCameraName);
        }
        
        // Toggle between camera sources
        async function toggleCameraSource() {
            if (currentCameraSource === 'tapo') {
                document.getElementById('tapoFeed').src = '';
                availableCameras = await refreshAvailableCameraDevices();
                if (availableCameras.length > 0) {
                    selectedLocalCameraIndex = 0;
                    await useWebcam(selectedLocalCameraIndex);
                } else {
                    alert('No local camera detected.\n\nPlease check that a webcam or USB camera is connected.');
                    document.getElementById('cameraStatus').innerHTML = '🔴 No fallback camera found';
                }
            } else {
                const video = document.getElementById('video');
                if (video.srcObject) {
                    video.srcObject.getTracks().forEach(track => track.stop());
                    video.srcObject = null;
                }

                availableCameras = await refreshAvailableCameraDevices();
                if (availableCameras.length > 0) {
                    const nextLocalCameraIndex = selectedLocalCameraIndex + 1;
                    if (nextLocalCameraIndex < availableCameras.length) {
                        selectedLocalCameraIndex = nextLocalCameraIndex;
                        await useWebcam(selectedLocalCameraIndex);
                    } else if (await checkTapoCamera()) {
                        useTapoCamera();
                    } else {
                        selectedLocalCameraIndex = 0;
                        await useWebcam(selectedLocalCameraIndex);
                    }
                } else {
                    if (await checkTapoCamera()) {
                        useTapoCamera();
                    } else {
                        alert('No camera detected.\n\nPlease check that a webcam or USB camera is connected.');
                        document.getElementById('cameraStatus').innerHTML = '🔴 No camera found';
                    }
                }
            }
        }
        
        // Show no camera message
        function showNoCameraMessage() {
            document.getElementById('video').style.display = 'none';
            document.getElementById('tapoFeed').style.display = 'none';
            document.getElementById('cameraOverlay').textContent = 'CAMERA FEED UNAVAILABLE';
            document.getElementById('cameraStatus').innerHTML = '🔴 No camera available';
            document.getElementById('sourceToggle').style.display = 'block';
        }
        
        // Capture current video frame as base64
        async function captureFrame() {
            if (currentCameraSource === 'tapo' && tapoConnected) {
                // Get frame from Tapo camera server
                try {
                    const response = await fetch(TAPO_CAMERA_SERVER + '/snapshot_base64');
                    const data = await response.json();
                    return data.image;
                } catch (err) {
                    console.log('Failed to capture from Tapo:', err);
                    return null;
                }
            } else {
                // Capture from webcam video element
                const video = document.getElementById('video');
                if (!video || !video.srcObject) {
                    return null;
                }
                canvas.width = video.videoWidth || 640;
                canvas.height = video.videoHeight || 480;
                ctx.drawImage(video, 0, 0);
                return canvas.toDataURL('image/jpeg', 0.8);
            }
        }
        
        // Update datetime
        function updateDateTime() {
            const now = new Date();
            document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', { 
                weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
            });
            document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', minute: '2-digit', second: '2-digit' 
            });
        }
        
        // RFID Input handler
        const rfidInput = document.getElementById('rfidInput');
        let scanTimeout;
        let detectionBoxes = [];  // Store current detection boxes
        
        // Initialize detection canvas
        const detectionCanvas = document.getElementById('detectionCanvas');
        const detectionCtx = detectionCanvas.getContext('2d');
        
        // Resize canvas to match camera feed
        function resizeCanvas() {
            const cameraFeed = document.getElementById('cameraFeed');
            const rect = cameraFeed.getBoundingClientRect();
            detectionCanvas.width = rect.width;
            detectionCanvas.height = rect.height;
            console.log('Canvas resized:', detectionCanvas.width, 'x', detectionCanvas.height);
        }
        
        window.addEventListener('resize', resizeCanvas);
        // Resize on load and periodically
        setTimeout(resizeCanvas, 500);
        setTimeout(resizeCanvas, 1000);
        setTimeout(resizeCanvas, 2000);
        
        rfidInput.addEventListener('input', function() {
            clearTimeout(scanTimeout);
            scanTimeout = setTimeout(() => {
                if (this.value.length >= 4) {
                    processRFIDScan(this.value);
                }
            }, 500);
        });
        
        rfidInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (this.value.length >= 4) {
                    processRFIDScan(this.value);
                }
            }
        });
        
        // Draw detection boxes on canvas
        function drawDetectionBoxes(boxes, isCompliant) {
            resizeCanvas();
            detectionCtx.clearRect(0, 0, detectionCanvas.width, detectionCanvas.height);
            
            console.log('Drawing', boxes.length, 'boxes on canvas', detectionCanvas.width, 'x', detectionCanvas.height);
            
            if (boxes.length === 0) {
                console.log('No boxes to draw!');
                return;
            }
            
            const canvasWidth = detectionCanvas.width;
            const canvasHeight = detectionCanvas.height;
            
            // Define distinct colors for each class (backup if server doesn't provide)
            const classColors = {
                'id_lace': '#00FF00',   // Bright Green
                'polo': '#00BFFF',      // Deep Sky Blue  
                'shoes': '#FFD700',     // Gold/Yellow
                'skirt': '#FF1493',     // Deep Pink
                'pants': '#9400D3',     // Dark Violet
                'person': '#FF4500'     // Orange Red
            };
            
            boxes.forEach(box => {
                // Convert percentage to pixels
                const x = (box.x / 100) * canvasWidth;
                const y = (box.y / 100) * canvasHeight;
                const width = (box.width / 100) * canvasWidth;
                const height = (box.height / 100) * canvasHeight;
                
                // Get color (from server or fallback)
                const color = box.color || classColors[box.class] || '#FFFFFF';
                
                // Draw thick border box with glow effect
                detectionCtx.shadowColor = color;
                detectionCtx.shadowBlur = 10;
                detectionCtx.strokeStyle = color;
                detectionCtx.lineWidth = 4;
                detectionCtx.strokeRect(x, y, width, height);
                
                // Reset shadow for label
                detectionCtx.shadowBlur = 0;
                
                // Draw corner brackets for style
                const cornerSize = Math.min(width, height) * 0.2;
                detectionCtx.lineWidth = 5;
                
                // Top-left corner
                detectionCtx.beginPath();
                detectionCtx.moveTo(x, y + cornerSize);
                detectionCtx.lineTo(x, y);
                detectionCtx.lineTo(x + cornerSize, y);
                detectionCtx.stroke();
                
                // Top-right corner
                detectionCtx.beginPath();
                detectionCtx.moveTo(x + width - cornerSize, y);
                detectionCtx.lineTo(x + width, y);
                detectionCtx.lineTo(x + width, y + cornerSize);
                detectionCtx.stroke();
                
                // Bottom-left corner
                detectionCtx.beginPath();
                detectionCtx.moveTo(x, y + height - cornerSize);
                detectionCtx.lineTo(x, y + height);
                detectionCtx.lineTo(x + cornerSize, y + height);
                detectionCtx.stroke();
                
                // Bottom-right corner
                detectionCtx.beginPath();
                detectionCtx.moveTo(x + width - cornerSize, y + height);
                detectionCtx.lineTo(x + width, y + height);
                detectionCtx.lineTo(x + width, y + height - cornerSize);
                detectionCtx.stroke();
                
                // Draw label background with rounded corners
                const label = `${box.class.toUpperCase()} ${box.confidence}%`;
                detectionCtx.font = 'bold 14px Arial';
                const textWidth = detectionCtx.measureText(label).width;
                const labelHeight = 24;
                const labelY = y > labelHeight + 5 ? y - labelHeight - 5 : y + height + 5;
                const labelX = x;
                
                // Label background
                detectionCtx.fillStyle = color;
                detectionCtx.beginPath();
                detectionCtx.roundRect(labelX, labelY, textWidth + 16, labelHeight, 4);
                detectionCtx.fill();
                
                // Label text (black for contrast)
                detectionCtx.fillStyle = '#000000';
                detectionCtx.fillText(label, labelX + 8, labelY + 17);
            });
            
            // Store boxes for animation
            detectionBoxes = boxes;
        }
        
        // Clear detection boxes
        function clearDetectionBoxes() {
            detectionCtx.clearRect(0, 0, detectionCanvas.width, detectionCanvas.height);
            detectionBoxes = [];
        }
        
        // Update detection status display
        function setDetectionStatus(state, text) {
            const statusEl = document.getElementById('detectionStatus');
            statusEl.className = 'detection-status';
            if (state) {
                statusEl.classList.add(state);
                statusEl.textContent = text || state.toUpperCase();
            }
        }
        
        // Process RFID scan
        async function processRFIDScan(rfid) {
            rfidInput.value = '';
            rfidInput.disabled = true;
            
            // Clear previous boxes and show scanning status
            clearDetectionBoxes();
            setDetectionStatus('scanning', '🔍 SCANNING...');
            
            // Update UI to processing state
            updateCheckItem(0, 'pending', '...');
            updateCheckItem(1, 'pending', '...');
            updateCheckItem(2, 'pending', '...');
            
            document.getElementById('complianceStatus').className = 'compliance-status waiting';
            document.getElementById('complianceStatus').textContent = 'PROCESSING...';
            
            // Capture camera frame for YOLO detection
            const imageData = await captureFrame();
            
            try {
                const response = await fetch('process_scan.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        rfid: rfid,
                        image: imageData  // Send camera image for YOLO detection
                    })
                });
                
                const data = await response.json();
                
                // Debug: log full response and show in debug panel
                console.log('=== SCAN RESPONSE ===');
                console.log('Full data:', data);
                console.log('Compliance:', data.compliance);
                console.log('Debug info:', data.compliance?.debug);
                console.log('Boxes:', data.compliance?.boxes);
                console.log('=====================');
                
                // Update debug panel
                const debugEl = document.getElementById('debugInfo');
                if (debugEl && data.compliance) {
                    const c = data.compliance;
                    debugEl.innerHTML = `
                        <div><b>Mode:</b> ${c.detection_mode || 'unknown'}</div>
                        <div><b>Person:</b> ${c.person_detected ? '✓ YES' : '✗ NO'}</div>
                        <div><b>Uniform:</b> ${c.uniform_status}</div>
                        <div><b>ID:</b> ${c.id_visible}</div>
                        <div><b>Shoes:</b> ${c.shoes_status}</div>
                        <div><b>Boxes:</b> ${c.boxes?.length || 0}</div>
                        <div><b>HTTP:</b> ${c.debug?.http_code || 'N/A'}</div>
                        <div style="color: ${c.overall_status === 'compliant' ? '#4ade80' : '#ef4444'}"><b>Overall:</b> ${c.overall_status}</div>
                    `;
                }
                
                if (data.success) {
                    // Update student info
                    document.getElementById('studentInfo').innerHTML = `
                        <div class="student-photo">${data.student.name.charAt(0)}</div>
                        <div class="student-name">${data.student.name}</div>
                        <div class="student-details">
                            ${data.student.student_id} | Grade ${data.student.grade} - ${data.student.section}
                        </div>
                    `;
                    
                    // Process vision detection with bounding boxes
                    await processVisionDetection(data);
                    
                } else {
                    // Unknown RFID
                    document.getElementById('studentInfo').innerHTML = `
                        <div class="student-photo" style="border-color: #8B1538;">❓</div>
                        <div class="student-name" style="color: #8B1538;">Unknown RFID</div>
                        <div class="student-details">${rfid}</div>
                    `;
                    
                    updateCheckItem(0, 'fail', '✗');
                    updateCheckItem(1, 'pending', '-');
                    updateCheckItem(2, 'pending', '-');
                    
                    document.getElementById('complianceStatus').className = 'compliance-status non-compliant';
                    document.getElementById('complianceStatus').textContent = 'UNREGISTERED RFID';
                    setDetectionStatus('violation', '✗ UNKNOWN RFID');
                }
                
            } catch (err) {
                console.error('Error:', err);
                alert('Error processing scan');
            }
            
            rfidInput.disabled = false;
            rfidInput.focus();
            
            // Clear detection boxes after delay
            setTimeout(() => {
                clearDetectionBoxes();
                setDetectionStatus('', '');
            }, 5000);
        }
        
        // Process vision detection results from YOLO model
        async function processVisionDetection(data) {
            // Show detection mode in console
            const detectionMode = data.compliance?.detection_mode || 'unknown';
            console.log('=== VISION DETECTION ===');
            console.log('Detection mode:', detectionMode);
            console.log('Person detected:', data.compliance?.person_detected);
            console.log('Boxes count:', data.compliance?.boxes?.length || 0);
            console.log('Boxes:', JSON.stringify(data.compliance?.boxes, null, 2));
            console.log('========================');
            
            // Check for various failure modes
            if (detectionMode === 'connection_failed' || detectionMode === 'server_error') {
                setDetectionStatus('violation', '⚠️ DETECTION SERVER ERROR');
                updateCheckItem(1, 'fail', '!');
                updateCheckItem(2, 'fail', '!');
                document.getElementById('complianceStatus').className = 'compliance-status non-compliant';
                document.getElementById('complianceStatus').textContent = '✗ DETECTION FAILED - CHECK SERVER';
                addRecentScan(data.student.name, data.entry_time, false);
                return;
            }
            
            // Check if person was detected
            const personDetected = data.compliance?.person_detected === true;
            
            // Get detection boxes
            const boxes = data.compliance?.boxes || [];
            
            // Step 1: RFID Authentication (instant)
            updateCheckItem(0, 'pass', '✓');

            // Let the UI paint the RFID pass state before opening the gate.
            await sleep(150);
            await openGateAfterRFID();
            
            if (!personDetected || detectionMode === 'no_person') {
                // No person in frame
                setDetectionStatus('violation', '⚠️ NO STUDENT DETECTED');
                updateCheckItem(1, 'fail', '✗');
                updateCheckItem(2, 'fail', '✗');
                
                document.getElementById('complianceStatus').className = 'compliance-status non-compliant';
                document.getElementById('complianceStatus').textContent = '✗ NO STUDENT IN FRAME - SCAN AGAIN';
                
                // Add to recent scans as failed
                addRecentScan(data.student.name, data.entry_time, false);
                return;
            }
            
            setDetectionStatus('scanning', '🔍 ANALYZING...');
            
            await sleep(500);
            
            // Draw detection boxes on canvas
            const isCompliant = data.compliance.overall_status === 'compliant';
            drawDetectionBoxes(boxes, isCompliant);
            
            await sleep(500);
            
            // Step 2: Uniform detection (from YOLO model)
            const uniformOk = data.compliance.uniform_status === 'compliant';
            updateCheckItem(1, uniformOk ? 'pass' : 'fail', uniformOk ? '✓' : '✗');
            
            await sleep(400);
            
            // Step 3: ID Lanyard detection (from YOLO model)
            const idOk = data.compliance.id_visible === 'yes';
            updateCheckItem(2, idOk ? 'pass' : 'fail', idOk ? '✓' : '✗');
            
            await sleep(300);
            
            // Final status
            const modeLabel = (detectionMode === 'trained' || detectionMode === 'trained_model') ? ' (YOLO)' : ' (SIM)';
            document.getElementById('complianceStatus').className = 'compliance-status ' + (isCompliant ? 'compliant' : 'non-compliant');
            document.getElementById('complianceStatus').textContent = (isCompliant ? '✓ COMPLIANT - ACCESS GRANTED' : '✗ NON-COMPLIANT - TICKET ISSUED') + modeLabel;
            
            // Update detection status
            if (isCompliant) {
                setDetectionStatus('compliant', '✓ COMPLIANT');
            } else {
                setDetectionStatus('violation', '✗ VIOLATION DETECTED');
            }
            
            // Show ticket alert if non-compliant
            if (!isCompliant && data.ticket) {
                showTicketAlert(data.ticket.ticket_number, data.ticket.ticket_id);
            }
            
            // Add to recent scans
            addRecentScan(data.student.name, data.entry_time, isCompliant);
        }

        async function openGateAfterRFID() {
            try {
                const response = await fetch('open_gate.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({})
                });

                const result = await response.json();
                if (!result.success) {
                    console.error('Gate open failed:', result);
                }
            } catch (err) {
                console.error('Gate open error:', err);
            }
        }
        
        function updateCheckItem(index, status, icon) {
            const items = document.querySelectorAll('.check-item');
            const iconEl = items[index].querySelector('.check-icon');
            iconEl.className = 'check-icon ' + status;
            iconEl.textContent = icon;
        }
        
        function showTicketAlert(ticketNumber, ticketId) {
            const alert = document.getElementById('ticketAlert');
            document.getElementById('ticketNumber').textContent = ticketNumber;
            alert.style.display = 'block';
            
            // Direct print via Python server - NO popups
            directPrintTicket(ticketId);
            
            setTimeout(() => {
                alert.style.display = 'none';
            }, 4000);
        }
        
        // Direct print function - sends to Python print server
        async function directPrintTicket(ticketId) {
            try {
                // First, fetch ticket data from PHP
                const ticketResponse = await fetch('get_ticket_data.php?id=' + ticketId);
                const ticketData = await ticketResponse.json();
                
                if (!ticketData.success) {
                    console.error('Failed to get ticket data:', ticketData.error);
                    return;
                }
                
                // Send to Python print server for direct printing
                const printResponse = await fetch('http://localhost:5001/print_ticket', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(ticketData.ticket)
                });
                
                const printResult = await printResponse.json();
                
                if (printResult.success) {
                    console.log('✓ Ticket printed directly:', printResult.message);
                } else {
                    console.error('Print failed:', printResult.error);
                    // Fallback to browser print if direct print fails
                    fallbackBrowserPrint(ticketId);
                }
            } catch (err) {
                console.error('Direct print error:', err);
                // Fallback to browser print
                fallbackBrowserPrint(ticketId);
            }
        }
        
        // Fallback browser print if Python server unavailable
        function fallbackBrowserPrint(ticketId) {
            console.log('Using fallback browser print...');
            const printWindow = window.open('print_ticket.php?id=' + ticketId + '&autoprint=1', 'PrintTicket', 'width=350,height=500');
            if (printWindow) printWindow.focus();
        }
        
        function addRecentScan(name, time, compliant) {
            const container = document.getElementById('recentScans');
            
            // Remove "no entries" message if present
            if (container.querySelector('div[style]')) {
                container.innerHTML = '';
            }
            
            const entry = document.createElement('div');
            entry.className = 'scan-entry';
            entry.innerHTML = `
                <span class="time">${time}</span>
                <span class="name">${name}</span>
                <span class="status-badge ${compliant ? 'pass' : 'fail'}">${compliant ? 'PASS' : 'FAIL'}</span>
            `;
            
            container.insertBefore(entry, container.firstChild);
            
            // Keep only last 10 entries
            while (container.children.length > 10) {
                container.removeChild(container.lastChild);
            }
        }
        
        function sleep(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        }
        
        // Initialize
        initCamera();
        updateDateTime();
        setInterval(updateDateTime, 1000);
        
        // Load recent scans
        fetch('get_recent_scans.php')
            .then(r => r.json())
            .then(data => {
                if (data.length > 0) {
                    document.getElementById('recentScans').innerHTML = '';
                    data.forEach(scan => {
                        addRecentScan(scan.name, scan.time, scan.compliant);
                    });
                }
            });
    </script>
</body>
</html>
