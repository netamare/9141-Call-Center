# 🚨 9141 Call Center — Event Escalation & Response Monitoring System

A multilingual web-based emergency and public-service reporting platform designed to receive, classify, monitor, escalate, and track citizen reports in real time.

## ✨ Key Features

### 📞 Citizen Reporting
- Submit incidents online
- Four major service categories
- Location and GPS support
- Phone number validation
- Photo, video, audio, and document attachments
- Tracking code for every report

### 🖥️ Management Dashboard
- Real-time event monitoring
- Event statistics
- Department assignment
- Escalation management
- SLA/overdue monitoring
- Search and filtering
- CSV reporting
- Audit trail

### 👥 User Roles
- Administrator
- Call Center Operator
- Supervisor
- Department Officer
- Camera / Control Room Operator

### 🤖 AI Detection
- Video/image analysis
- Vehicle detection
- People and crowd analysis
- Traffic congestion detection
- Suspicious activity analysis
- Detection results mapped to service categories

### 📹 Camera Monitoring
- Live camera streams
- HLS streaming
- MJPEG/HTTP streams
- Camera management
- Camera health monitoring
- Browser webcam testing
- RTSP → HLS support

### 🗺️ GPS & Live Map
- GPS coordinates for incidents
- Interactive incident map
- Category-based markers
- Priority indicators
- Department-based visibility
- Automatic map updates

### 🌍 Multilingual Support

The system supports:

🇬🇧 English  
🟢 Afaan Oromoo  
🇪🇹 Amharic  
🇸🇦 Arabic  
🇪🇷 Tigrinya  
🇸🇴 Somali  
🟡 Afar

### 🔔 Notifications
- Real-time notifications
- Urgent alerts
- SMS notification support
- Notification history
- Browser alarm for critical events

### 🔐 Security
- Session-based authentication
- Role-based access control
- CSRF protection
- Prepared SQL statements
- Login brute-force protection
- Honeypot spam protection
- Secure file upload handling
- Idle-session timeout

## 🏛️ Main Service Categories

| Category | Description |
|---|---|
| 🚨 Illegal Acts | Reports related to illegal activities |
| 🛡️ Security | Security-related incidents |
| 🏢 Service Delivery | Public service complaints |
| 🚑 Accident / Disaster | Accidents and emergency situations |

## 🛠️ Technologies

- PHP
- MySQL
- HTML5
- CSS3
- JavaScript
- Chart.js
- Leaflet.js
- hls.js
- Python
- Pillow
- FFmpeg
- XAMPP / Apache

## 📂 Project Structure

```text
9141-Call-Center/
│
├── admin/
│   ├── dashboard.php
│   ├── monitoring.php
│   ├── analytics.php
│   ├── reports.php
│   ├── users.php
│   ├── departments.php
│   ├── cameras.php
│   ├── cameras_manage.php
│   ├── live_map.php
│   └── settings.php
│
├── ai/
│   └── detect.py
│
├── assets/
│   ├── css/
│   ├── js/
│   └── logo/
│
├── includes/
│   ├── security.php
│   ├── notifications.php
│   ├── cameras.php
│   ├── maps.php
│   └── lang.php
│
├── lang/
│   ├── en.php
│   ├── om.php
│   ├── am.php
│   ├── ar.php
│   ├── ti.php
│   ├── so.php
│   └── aa.php
│
├── uploads/
│
├── index.php
├── track.php
├── config.php
├── schema.sql
└── README.md