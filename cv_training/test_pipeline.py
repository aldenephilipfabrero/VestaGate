"""Test full pipeline - camera to detection"""
import requests
import json

# Step 1: Get image from camera server
print("Fetching frame from camera server...")
try:
    cam_response = requests.get('http://127.0.0.1:5001/snapshot_base64', timeout=5)
    cam_data = cam_response.json()
    img_base64 = cam_data.get('image', '')
    print(f"Got image data: {len(img_base64)} bytes")
except Exception as e:
    print(f"Camera error: {e}")
    exit(1)

# Step 2: Send to detection server
print("\nSending to detection server...")
try:
    det_response = requests.post(
        'http://127.0.0.1:5000/detect',
        json={'image': img_base64},
        timeout=15
    )
    print(f"Status: {det_response.status_code}")
    result = det_response.json()
    print("\n=== DETECTION RESULT ===")
    print(json.dumps(result, indent=2))
except Exception as e:
    print(f"Detection error: {e}")
