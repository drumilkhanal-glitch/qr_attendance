<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
require_student();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark Attendance - QR & Photo Verification</title>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 20px;
            color: #333;
        }

        .header a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            padding: 25px;
        }

        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 25px;
            text-align: center;
        }

        .step {
            flex: 1;
            font-size: 12px;
            color: #999;
        }

        .step.active {
            color: #667eea;
            font-weight: 600;
        }

        .step.completed {
            color: #28a745;
        }

        .step-number {
            display: inline-block;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e0e0e0;
            color: #666;
            line-height: 30px;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .step.active .step-number {
            background: #667eea;
            color: white;
        }

        .step.completed .step-number {
            background: #28a745;
            color: white;
        }

        .controls {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 14px;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }

        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .btn-danger {
            background: #d32f2f;
            color: white;
        }

        .btn-danger:hover {
            background: #b71c1c;
        }

        .btn-secondary {
            background: #757575;
            color: white;
        }

        .btn-secondary:hover {
            background: #616161;
        }

        #video, #photoCanvas {
            width: 100%;
            border-radius: 8px;
            background: #000;
            margin-bottom: 15px;
            display: none;
            max-height: 500px;
            object-fit: cover;
        }

        #video.show {
            display: block;
        }

        #photoCanvas.show {
            display: block;
        }

        #scanCanvas {
            display: none;
        }

        .camera-placeholder {
            width: 100%;
            height: 300px;
            background: #f0f0f0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .status {
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
            display: none;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .status.info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status.success {
            background: #d4edda;
            color: #155724;
        }

        .status.error {
            background: #f8d7da;
            color: #721c24;
        }

        .status.warning {
            background: #fff3cd;
            color: #856404;
        }

        .status.show {
            display: block;
        }

        .info-box {
            background: #f8f9ff;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            margin-top: 15px;
            font-size: 13px;
            color: #555;
            line-height: 1.6;
        }

        .info-box strong {
            color: #333;
        }

        .qr-info {
            background: #e3f2fd;
            padding: 12px;
            border-radius: 8px;
            border-left: 4px solid #1976d2;
            margin-bottom: 15px;
            font-size: 13px;
            color: #1565c0;
        }

        .qr-info strong {
            display: block;
            margin-bottom: 5px;
        }

        .preview-section {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            display: none;
        }

        .preview-section.show {
            display: block;
        }

        .preview-image {
            width: 100%;
            border-radius: 8px;
            margin-bottom: 10px;
            border: 2px solid #ddd;
        }

        .preview-label {
            text-align: center;
            color: #666;
            font-size: 12px;
            margin-bottom: 10px;
        }

        .submission-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .submission-buttons .btn {
            flex: 1;
        }

        .progress-bar {
            width: 100%;
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            width: 0%;
            transition: width 0.3s ease;
        }

        .step-description {
            color: #666;
            font-size: 13px;
            text-align: center;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .hidden-section {
            display: none;
        }

        .hidden-section.show {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📱 Mark Attendance</h1>
            <a href="/student/dashboard.php">← Dashboard</a>
        </div>

        <!-- Progress Indicator -->
        <div class="progress-bar">
            <div class="progress-fill" id="progressFill"></div>
        </div>

        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step active" id="step1">
                <div class="step-number">1</div>
                <div>Scan QR</div>
            </div>
            <div class="step" id="step2">
                <div class="step-number">2</div>
                <div>Take Photo</div>
            </div>
            <div class="step" id="step3">
                <div class="step-number">3</div>
                <div>Submit</div>
            </div>
        </div>

        <!-- Main Card -->
        <div class="card">
            <!-- STEP 1: QR SCANNING -->
            <div id="step-qr" class="hidden-section show">
                <h2 style="margin-bottom: 15px; color: #333;">📷 Step 1: Scan QR Code</h2>
                
                <div class="step-description">
                    <strong>Point your camera at the QR code displayed by your teacher</strong><br>
                    The QR code will be automatically detected
                </div>

                <div class="controls">
                    <button class="btn btn-primary" onclick="startQRScanning()">📷 Start Camera</button>
                    <button class="btn btn-danger" onclick="stopQRScanning()">Stop</button>
                </div>

                <div class="camera-placeholder" id="qr-placeholder">
                    📷 Click "Start Camera" to begin
                </div>

                <video id="video" playsinline></video>
                <canvas id="scanCanvas"></canvas>

                <div id="qr-status" class="status"></div>

                <div class="info-box">
                    💡 <strong>Tip:</strong> Hold your phone steady and ensure the entire QR code is visible on screen. The code will be detected automatically.
                </div>
            </div>

            <!-- STEP 2: PHOTO CAPTURE -->
            <div id="step-photo" class="hidden-section">
                <h2 style="margin-bottom: 15px; color: #333;">📸 Step 2: Take Your Photo</h2>
                
                <div class="qr-info" id="qr-info">
                    <strong>QR Code Detected! ✅</strong>
                    <span id="qr-details"></span>
                </div>

                <div class="step-description">
                    <strong>Now open your camera to take a selfie</strong><br>
                    We need to verify you are present in the class
                </div>

                <div class="controls">
                    <button class="btn btn-primary" onclick="startPhotoCapture()">📸 Open Camera</button>
                    <button class="btn btn-secondary" onclick="goBackToQR()">← Back to QR</button>
                </div>

                <div class="camera-placeholder" id="photo-placeholder">
                    📸 Click "Open Camera" to take your photo
                </div>

                <video id="video-photo" playsinline style="width: 100%; border-radius: 8px; background: #000; margin-bottom: 15px; display: none; max-height: 500px; object-fit: cover;"></video>
                <canvas id="photoCanvas"></canvas>

                <div id="photo-status" class="status"></div>

                <div class="preview-section" id="photo-preview">
                    <div class="preview-label">📸 Your Photo Preview</div>
                    <img id="preview-image" class="preview-image" src="" alt="Preview">
                    <div class="submission-buttons">
                        <button class="btn btn-danger" onclick="retakeSelfie()">🔄 Retake</button>
                        <button class="btn btn-primary" onclick="proceedToSubmit()">✓ Continue</button>
                    </div>
                </div>

                <div class="info-box">
                    💡 <strong>Tip:</strong> Make sure your face is clearly visible in the photo for verification purposes.
                </div>
            </div>

            <!-- STEP 3: CONFIRMATION -->
            <div id="step-confirm" class="hidden-section">
                <h2 style="margin-bottom: 15px; color: #333;">✓ Step 3: Confirm Attendance</h2>
                
                <div class="qr-info">
                    <strong>Ready to submit attendance? 📝</strong>
                    Your QR code and photo have been captured
                </div>

                <div class="preview-section show" style="margin-top: 0;">
                    <div class="preview-label">📸 Your Captured Photo</div>
                    <img id="final-photo" class="preview-image" src="" alt="Final Photo" style="display: none;">
                </div>

                <div id="submit-status" class="status"></div>

                <div class="submission-buttons" style="margin-top: 20px;">
                    <button class="btn btn-secondary" onclick="goBackToPhoto()">← Back to Photo</button>
                    <button class="btn btn-primary" onclick="submitAttendance()" style="flex: 2;">✓ Submit Attendance</button>
                </div>

                <div class="info-box">
                    ✅ <strong>What happens next:</strong><br>
                    Your attendance will be recorded with your photo for verification. You'll be redirected to your dashboard.
                </div>
            </div>
        </div>
    </div>

    <script>
        let videoStreamQR = null;
        let videoStreamPhoto = null;
        let scanningActive = false;
        let capturedQRPayload = null;
        let capturedPhotoData = null;

        // QR SCANNING FUNCTIONS
        function startQRScanning() {
            try {
                const constraints = {
                    video: {
                        facingMode: 'environment',
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    },
                    audio: false
                };

                navigator.mediaDevices.getUserMedia(constraints).then(stream => {
                    videoStreamQR = stream;
                    const videoElement = document.getElementById('video');
                    videoElement.srcObject = stream;
                    videoElement.classList.add('show');
                    document.getElementById('qr-placeholder').style.display = 'none';
                    
                    scanningActive = true;
                    showStatus('qr', '📹 Camera started. Point at QR code...', 'info');
                    
                    videoElement.onloadedmetadata = () => {
                        videoElement.play();
                        scanQRCode();
                    };
                }).catch(err => {
                    showStatus('qr', '❌ Camera error: ' + err.message, 'error');
                });
            } catch (err) {
                showStatus('qr', '❌ Error: ' + err.message, 'error');
            }
        }

        function stopQRScanning() {
            scanningActive = false;
            if (videoStreamQR) {
                videoStreamQR.getTracks().forEach(track => track.stop());
                videoStreamQR = null;
            }
            const videoElement = document.getElementById('video');
            videoElement.srcObject = null;
            videoElement.classList.remove('show');
            document.getElementById('qr-placeholder').style.display = 'flex';
        }

        function scanQRCode() {
            if (!scanningActive) return;
            
            const video = document.getElementById('video');
            const canvas = document.getElementById('scanCanvas');
            const ctx = canvas.getContext('2d');
            
            if (video.readyState === video.HAVE_ENOUGH_DATA) {
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                
                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                const code = jsQR(imageData.data, imageData.width, imageData.height);
                
                if (code && code.data) {
                    capturedQRPayload = code.data;
                    showStatus('qr', '✅ QR detected! Moving to photo step...', 'success');
                    stopQRScanning();
                    setTimeout(() => goToPhotoStep(), 1000);
                    return;
                }
            }
            
            requestAnimationFrame(scanQRCode);
        }

        // STEP NAVIGATION
        function goToPhotoStep() {
            // Update steps
            document.getElementById('step1').classList.add('completed');
            document.getElementById('step2').classList.add('active');
            document.getElementById('progressFill').style.width = '50%';

            // Show QR details
            document.getElementById('qr-details').textContent = 
                capturedQRPayload.substring(0, 50) + '...';

            // Switch to photo step
            document.getElementById('step-qr').classList.remove('show');
            document.getElementById('step-photo').classList.add('show');
        }

        function goBackToQR() {
            document.getElementById('step1').classList.remove('completed');
            document.getElementById('step2').classList.remove('active');
            document.getElementById('step-photo').classList.remove('show');
            document.getElementById('step-qr').classList.add('show');
            document.getElementById('progressFill').style.width = '0%';
            capturedQRPayload = null;
        }

        function goBackToPhoto() {
            document.getElementById('step2').classList.add('active');
            document.getElementById('step3').classList.remove('active');
            document.getElementById('step-confirm').classList.remove('show');
            document.getElementById('step-photo').classList.add('show');
            document.getElementById('progressFill').style.width = '50%';
        }

        // PHOTO CAPTURE FUNCTIONS
        function startPhotoCapture() {
            try {
                const constraints = {
                    video: {
                        facingMode: 'user', // Front camera for selfie
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    },
                    audio: false
                };

                navigator.mediaDevices.getUserMedia(constraints).then(stream => {
                    videoStreamPhoto = stream;
                    const videoElement = document.getElementById('video-photo');
                    videoElement.srcObject = stream;
                    videoElement.style.display = 'block';
                    document.getElementById('photo-placeholder').style.display = 'none';
                    
                    showStatus('photo', '📸 Camera started. Tap "Capture Photo" when ready...', 'info');
                    
                    videoElement.onloadedmetadata = () => {
                        videoElement.play();
                        // Show capture button after brief delay
                        setTimeout(() => {
                            document.getElementById('photo-status').innerHTML = 
                                '<strong>📸 Ready!</strong> Point camera at your face and tap "Capture Photo"';
                            document.getElementById('photo-status').className = 'status success show';
                            addCaptureButton();
                        }, 500);
                    };
                }).catch(err => {
                    showStatus('photo', '❌ Camera error: ' + err.message, 'error');
                });
            } catch (err) {
                showStatus('photo', '❌ Error: ' + err.message, 'error');
            }
        }

        function addCaptureButton() {
            const container = document.getElementById('step-photo');
            const existingBtn = container.querySelector('#capture-btn');
            if (existingBtn) existingBtn.remove();

            const captureBtn = document.createElement('button');
            captureBtn.id = 'capture-btn';
            captureBtn.className = 'btn btn-primary';
            captureBtn.style.marginTop = '10px';
            captureBtn.textContent = '📷 Capture Photo';
            captureBtn.onclick = () => {
                capturePhotoFromVideo();
                captureBtn.remove();
            };

            const statusEl = document.getElementById('photo-status');
            statusEl.parentNode.insertBefore(captureBtn, statusEl.nextSibling);
        }

        function capturePhotoFromVideo() {
            const video = document.getElementById('video-photo');
            const canvas = document.getElementById('photoCanvas');
            const ctx = canvas.getContext('2d');

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

            capturedPhotoData = canvas.toDataURL('image/jpeg', 0.8);

            // Stop video
            if (videoStreamPhoto) {
                videoStreamPhoto.getTracks().forEach(track => track.stop());
                videoStreamPhoto = null;
            }
            video.style.display = 'none';

            // Show preview
            document.getElementById('preview-image').src = capturedPhotoData;
            document.getElementById('photo-preview').classList.add('show');
            showStatus('photo', '✅ Photo captured! Review and continue or retake.', 'success');
        }

        function retakeSelfie() {
            document.getElementById('preview-image').src = '';
            document.getElementById('photo-preview').classList.remove('show');
            document.getElementById('video-photo').style.display = 'block';
            document.getElementById('photo-placeholder').style.display = 'none';
            startPhotoCapture();
        }

        function proceedToSubmit() {
            // Update steps
            document.getElementById('step2').classList.add('completed');
            document.getElementById('step3').classList.add('active');
            document.getElementById('progressFill').style.width = '100%';

            // Show final photo
            document.getElementById('final-photo').src = capturedPhotoData;
            document.getElementById('final-photo').style.display = 'block';

            // Switch to confirmation step
            document.getElementById('step-photo').classList.remove('show');
            document.getElementById('step-confirm').classList.add('show');
        }

        // SUBMISSION
        async function submitAttendance() {
            if (!capturedQRPayload || !capturedPhotoData) {
                showStatus('submit', '❌ Missing QR or photo data', 'error');
                return;
            }

            showStatus('submit', '⏳ Submitting attendance...', 'info');

            const formData = new FormData();
            formData.append('qr_payload', capturedQRPayload);
            formData.append('photo_data', capturedPhotoData);

            try {
                const response = await fetch('/api/scan.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (response.ok) {
                    showStatus('submit', '✅ ' + data.message, 'success');
                    setTimeout(() => {
                        window.location.href = '/student/dashboard.php';
                    }, 2000);
                } else {
                    showStatus('submit', '⚠️ ' + data.message, 'error');
                }
            } catch (err) {
                showStatus('submit', '❌ Error: ' + err.message, 'error');
            }
        }

        function showStatus(step, message, type) {
            const statusId = step === 'qr' ? 'qr-status' : step === 'photo' ? 'photo-status' : 'submit-status';
            const statusEl = document.getElementById(statusId);
            statusEl.textContent = message;
            statusEl.className = 'status ' + type + ' show';
        }
    </script>
</body>
</html>
