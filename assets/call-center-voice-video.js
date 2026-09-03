/**
 * Call Center Operator — Voice & Video recording
 * IDs must match admin/new_event.php
 */

// ---- Voice ----
let mediaRecorder = null;
let audioChunks = [];
let recordingTimer = null;
let recordingSeconds = 0;
let audioStream = null;

function mediaAccessHelp(err) {
    var name = (err && err.name) ? err.name : '';
    var msg = '';
    if (!window.isSecureContext && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
        msg = 'Browser microphone/camera only works on HTTPS or localhost.\n\n'
            + 'Fayyadami: https://... ykn http://localhost/...\n'
            + 'IP qofa (fkn http://192.168.x.x) hin hojjetu — HTTPS barbaada.';
    } else if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
        msg = 'Permission denied.\n\n'
            + '1) Address bar irratti icon 🔒 ykn 🎤 tuqi\n'
            + '2) Microphone / Camera = Allow\n'
            + '3) Page refresh (Ctrl+F5) godhi\n\n'
            + 'Chrome: Settings → Privacy → Site settings → Microphone/Camera';
    } else if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
        msg = 'No microphone or camera found on this device.\n'
            + 'Mic/camera hin argamne.\n\n'
            + 'Check:\n'
            + '• PC/laptop mic connected & enabled in Windows\n'
            + '• Settings → Privacy → Microphone → On\n'
            + '• Or use Upload file under the record button';
    } else if (name === 'NotReadableError' || name === 'TrackStartError') {
        msg = 'Device is in use by another app (Zoom, Teams, Skype…).\nApp biraa cufuu ykn mic/camera free taasisuu.';
    } else if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        msg = 'This browser does not support recording.\nChrome / Edge / Firefox haaraa fayyadami.';
    } else {
        msg = 'Camera/microphone access failed: ' + (name || 'unknown') + '\n' + (err && err.message ? err.message : '');
    }
    return msg;
}

async function startVoiceRecord() {
    try {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            throw { name: 'NotSupportedError', message: 'getUserMedia missing' };
        }
        audioStream = await navigator.mediaDevices.getUserMedia({ audio: true });
        mediaRecorder = new MediaRecorder(audioStream);
        audioChunks = [];
        recordingSeconds = 0;

        mediaRecorder.ondataavailable = function (e) {
            if (e.data && e.data.size > 0) audioChunks.push(e.data);
        };

        mediaRecorder.onstop = function () {
            const blob = new Blob(audioChunks, { type: 'audio/webm' });
            const url = URL.createObjectURL(blob);
            var audio = document.getElementById('voiceAudio');
            var playback = document.getElementById('voicePlayback');
            if (audio) audio.src = url;
            if (playback) playback.style.display = 'block';

            var reader = new FileReader();
            reader.onloadend = function () {
                var b64 = reader.result;
                if (b64.indexOf(',') !== -1) b64 = b64.split(',')[1];
                var input = document.getElementById('voiceFileInput');
                if (input) input.value = b64 || '';
            };
            reader.readAsDataURL(blob);

            if (audioStream) {
                audioStream.getTracks().forEach(function (t) { t.stop(); });
                audioStream = null;
            }
            updateVoiceStatus('✓ Recorded', 'ready');
        };

        mediaRecorder.start();
        updateVoiceUI('recording');
        updateVoiceStatus('🔴 Recording…', 'recording');
        startRecordingTimer();
    } catch (err) {
        console.error(err);
        alert(mediaAccessHelp(err));
        updateVoiceStatus('Microphone error', 'error');
    }
}

function stopVoiceRecord() {
    if (mediaRecorder && mediaRecorder.state === 'recording') {
        mediaRecorder.stop();
        clearInterval(recordingTimer);
        updateVoiceUI('stopped');
    }
}

function cancelVoiceRecord() {
    if (mediaRecorder && mediaRecorder.state === 'recording') {
        mediaRecorder.stop();
    }
    clearInterval(recordingTimer);
    audioChunks = [];
    var playback = document.getElementById('voicePlayback');
    var input = document.getElementById('voiceFileInput');
    if (playback) playback.style.display = 'none';
    if (input) input.value = '';
    if (audioStream) {
        audioStream.getTracks().forEach(function (t) { t.stop(); });
        audioStream = null;
    }
    updateVoiceUI('ready');
    updateVoiceStatus('Ready', 'ready');
    recordingSeconds = 0;
    var timer = document.getElementById('voiceTimer');
    if (timer) timer.textContent = '00:00';
}

function updateVoiceUI(state) {
    var startBtn = document.getElementById('startRecordBtn');
    var stopBtn = document.getElementById('stopRecordBtn');
    var cancelBtn = document.getElementById('cancelRecordBtn');
    if (!startBtn) return;
    if (state === 'recording') {
        startBtn.style.display = 'none';
        if (stopBtn) stopBtn.style.display = 'inline-block';
        if (cancelBtn) cancelBtn.style.display = 'inline-block';
    } else {
        startBtn.style.display = 'inline-block';
        if (stopBtn) stopBtn.style.display = 'none';
        if (cancelBtn) cancelBtn.style.display = 'none';
    }
}

function updateVoiceStatus(msg, cls) {
    var el = document.getElementById('voiceStatus');
    if (!el) return;
    el.textContent = msg;
    el.className = 'voice-status ' + (cls || '');
}

function startRecordingTimer() {
    clearInterval(recordingTimer);
    recordingTimer = setInterval(function () {
        recordingSeconds++;
        var m = Math.floor(recordingSeconds / 60);
        var s = recordingSeconds % 60;
        var timer = document.getElementById('voiceTimer');
        if (timer) {
            timer.textContent =
                String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        }
    }, 1000);
}

// ---- Video ----
let videoStream = null;
let videoRecorder = null;
let videoChunks = [];

async function startVideoCapture() {
    try {
        videoStream = await navigator.mediaDevices.getUserMedia({
            video: { width: { ideal: 1280 }, height: { ideal: 720 }, facingMode: 'user' },
            audio: true
        });
        var videoEl = document.getElementById('liveVideo');
        if (videoEl) {
            videoEl.srcObject = videoStream;
            videoEl.onloadedmetadata = function () { videoEl.play(); };
        }

        videoChunks = [];
        var options = { mimeType: 'video/webm;codecs=vp9', videoBitsPerSecond: 2500000 };
        if (!MediaRecorder.isTypeSupported(options.mimeType)) {
            options.mimeType = 'video/webm;codecs=vp8';
        }
        if (!MediaRecorder.isTypeSupported(options.mimeType)) {
            options.mimeType = 'video/webm';
        }
        if (!MediaRecorder.isTypeSupported(options.mimeType)) {
            options = {};
        }

        videoRecorder = new MediaRecorder(videoStream, options);
        videoRecorder.ondataavailable = function (e) {
            if (e.data && e.data.size > 0) videoChunks.push(e.data);
        };
        videoRecorder.onstop = function () {
            var blob = new Blob(videoChunks, { type: 'video/webm' });
            var url = URL.createObjectURL(blob);
            var player = document.getElementById('videoPlayer');
            var playback = document.getElementById('videoPlayback');
            if (player) player.src = url;
            if (playback) playback.style.display = 'block';

            var reader = new FileReader();
            reader.onloadend = function () {
                var b64 = reader.result;
                if (b64.indexOf(',') !== -1) b64 = b64.split(',')[1];
                var input = document.getElementById('videoFileInput');
                if (input) input.value = b64 || '';
            };
            reader.readAsDataURL(blob);

            if (videoStream) {
                videoStream.getTracks().forEach(function (t) { t.stop(); });
            }
            updateVideoStatus('✓ Recorded', 'ready');
        };

        videoRecorder.start();
        updateVideoUI('recording');
        updateVideoStatus('🔴 Recording…', 'recording');
    } catch (err) {
        console.error(err);
        alert(mediaAccessHelp(err));
        updateVideoStatus('Camera error', 'error');
    }
}

function stopVideoCapture() {
    if (videoRecorder && videoRecorder.state === 'recording') {
        videoRecorder.stop();
        updateVideoUI('stopped');
    }
}

function cancelVideoCapture() {
    if (videoRecorder && videoRecorder.state === 'recording') {
        videoRecorder.stop();
    }
    videoChunks = [];
    var playback = document.getElementById('videoPlayback');
    var input = document.getElementById('videoFileInput');
    var live = document.getElementById('liveVideo');
    if (playback) playback.style.display = 'none';
    if (input) input.value = '';
    if (live) live.srcObject = null;
    if (videoStream) {
        videoStream.getTracks().forEach(function (t) { t.stop(); });
        videoStream = null;
    }
    updateVideoUI('ready');
    updateVideoStatus('Ready', 'ready');
}

function updateVideoUI(state) {
    var startBtn = document.getElementById('startVideoBtn');
    var stopBtn = document.getElementById('stopVideoBtn');
    var cancelBtn = document.getElementById('cancelVideoBtn');
    if (!startBtn) return;
    if (state === 'recording') {
        startBtn.style.display = 'none';
        if (stopBtn) stopBtn.style.display = 'inline-block';
        if (cancelBtn) cancelBtn.style.display = 'inline-block';
    } else {
        startBtn.style.display = 'inline-block';
        if (stopBtn) stopBtn.style.display = 'none';
        if (cancelBtn) cancelBtn.style.display = 'none';
    }
}

function updateVideoStatus(msg, cls) {
    var el = document.getElementById('videoStatus');
    if (!el) return;
    el.textContent = msg;
    el.className = 'video-status ' + (cls || '');
}

/**
 * Fallback: pick audio/video file from disk → base64 into hidden field
 * (when mic/camera hardware is missing)
 */
function loadMediaFileAsBase64(fileInput, hiddenId, mediaElId, playbackId, statusId) {
    var file = fileInput && fileInput.files && fileInput.files[0];
    if (!file) return;
    var maxMb = 40;
    if (file.size > maxMb * 1024 * 1024) {
        alert('File too large (max ' + maxMb + ' MB).');
        fileInput.value = '';
        return;
    }
    var reader = new FileReader();
    reader.onloadend = function () {
        var b64 = reader.result || '';
        if (b64.indexOf(',') !== -1) b64 = b64.split(',')[1];
        var hidden = document.getElementById(hiddenId);
        if (hidden) hidden.value = b64;
        var media = document.getElementById(mediaElId);
        if (media) {
            media.src = URL.createObjectURL(file);
        }
        var playback = document.getElementById(playbackId);
        if (playback) playback.style.display = 'block';
        var status = document.getElementById(statusId);
        if (status) {
            status.textContent = '✓ File loaded: ' + file.name;
            status.className = (status.className || '').replace(/\berror\b/g, '') + ' ready';
        }
    };
    reader.onerror = function () {
        alert('Could not read file.');
    };
    reader.readAsDataURL(file);
}

// Form validation
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('eventForm');
    if (!form) return;
    form.addEventListener('submit', function (e) {
        var methodEl = document.getElementById('registrationMethod');
        var method = methodEl ? methodEl.value : 'manual';

        // Active category: only enabled select is submitted
        var cat = form.querySelector('select[name="category_id"]:not([disabled])');
        if (!cat || !cat.value) {
            e.preventDefault();
            alert('Please select a category / Mee gosa taatee filadhu.');
            return false;
        }

        if (method === 'voice') {
            var v = document.getElementById('voiceFileInput');
            if (!v || !v.value) {
                e.preventDefault();
                alert('Please record voice first / Mee dura sagalee galmeessi.');
                return false;
            }
        } else if (method === 'live_stream') {
            var vid = document.getElementById('videoFileInput');
            if (!vid || !vid.value) {
                e.preventDefault();
                alert('Please record video first / Mee dura viidiyoo galmeessi.');
                return false;
            }
        } else {
            var desc = document.getElementById('descField');
            if (!desc || !desc.value.trim()) {
                e.preventDefault();
                alert('Please enter a description.');
                return false;
            }
        }
        return true;
    });
});

window.addEventListener('beforeunload', function () {
    clearInterval(recordingTimer);
    if (mediaRecorder && mediaRecorder.state === 'recording') mediaRecorder.stop();
    if (videoRecorder && videoRecorder.state === 'recording') videoRecorder.stop();
    if (audioStream) audioStream.getTracks().forEach(function (t) { t.stop(); });
    if (videoStream) videoStream.getTracks().forEach(function (t) { t.stop(); });
});
