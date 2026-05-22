# Mediflow — Medical Unit Management System

<p align="center">
  <img src="app/assets/images/logo.png" alt="Mediflow Banner" width="100" height="100"/>
</p>

<p align="center">
  <a href="#">
    <img title="Mediflow" src="https://img.shields.io/badge/Mediflow-Medical Unit System-green?colorA=%23ff0000&colorB=%23017e40&style=for-the-badge"/>
  </a>
  <a href="https://github.com/Lexcoded3">
    <img title="GitHub" src="https://img.shields.io/badge/GitHub-Lexcoded3-black?style=for-the-badge&logo=github"/>
  </a>
</p>

---

## 📌 Overview

**Mediflow** is a fully integrated medical unit management system designed to streamline the complete patient journey — from reception to discharge. Built with a clean, modern UI, Mediflow connects every department in real time, eliminating paperwork and reducing operational bottlenecks in busy medical environments.

Patients can track their own progress using a unique **Patient ID** via a dedicated tracking page — no login required.

---

## 🏥 System Flow
Patient Arrives → Reception
↓
Triage (vitals & priority assessment)
↓
Consultation (doctor review & orders)
↓
┌─────────────┬──────────────┐
│     Lab     │     Scan     │
│  (tests)    │  (imaging)   │
└─────────────┴──────────────┘
↓
Pharmacy (drug dispensing & auto logging)
↓
Billing (auto-generated from services)
↓
Patient Discharged



---

## ⚡ Features

- ✅ Reception — patient registration & queue management
- ✅ Triage — vitals capture & priority scoring
- ✅ Consultation — doctor notes, orders & referrals
- ✅ Laboratory — test requests, results & feedback
- ✅ Scan/Imaging — scan requests & result uploads
- ✅ Pharmacy — prescription management & auto drug logging
- ✅ Billing — auto-generated bills from all department activity
- ✅ Patient self-tracking via unique Patient ID
- ✅ Fully integrated department-to-department flow
- ✅ Clean, modern responsive UI

---

## 🛠️ Built With

<p align="center">
  <img src="https://img.shields.io/badge/PHP-Backend-blue?style=for-the-badge&logo=php"/>
  <img src="https://img.shields.io/badge/Tailwind CSS-Styling-38B2AC?style=for-the-badge&logo=tailwind-css"/>
  <img src="https://img.shields.io/badge/StarCodeCSS-UI Framework-purple?style=for-the-badge"/>
  <img src="https://img.shields.io/badge/JavaScript-Frontend-yellow?style=for-the-badge&logo=javascript"/>
  <img src="https://img.shields.io/badge/HTML5-Markup-E34F26?style=for-the-badge&logo=html5"/>
  <img src="https://img.shields.io/badge/MySQL-Database-orange?style=for-the-badge&logo=mysql"/>
</p>

---

## 🔐 Environment Variables

| Variable | Description |
|---|---|
| `DB_HOST` | Database host |
| `DB_NAME` | Database name |
| `DB_USER` | Database username |
| `DB_PASS` | Database password |
| `MAPBOX_TOKEN` | Mapbox API token for map features |

---

## 🚀 Installation

```bash
# Clone the repository
git clone https://github.com/Lexcoded3/Mediflow.git

# Navigate to project directory
cd Mediflow

# Configure your database
# Import the SQL file from /database folder

# Set your environment variables
# Add your Mapbox token in app/assets/js/pages/leaflet-map.init.js

# Run on XAMPP or any PHP server
# Visit http://localhost/Mediflow
```

---

## 🔍 Patient Tracking

Patients can check their current status at any point using their unique **Patient ID** on the self-service tracking page — no account or login needed. Status updates in real time as they move through departments.

---

## 💬 Contact & Support

- **GitHub:** [@Lexcoded3](https://github.com/Lexcoded3)
- **Email:** your@email.com
- **WhatsApp:** [Chat](https://wa.me/256777777861)

---

## ⚠️ License & Usage

This project is the intellectual property of **BYTESTORM/ Lexcoded3**. Unauthorized redistribution or modification is strictly prohibited. Commercial inquiries welcome.

---

## ⚖️ BYTESTORM © 2026 — All Rights Reserved
