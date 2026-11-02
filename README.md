# Jaxy - Mini Social Platform

A lightweight social platform built with **Core PHP + MySQL** and a **Python (Flask) internal API**, featuring user authentication, tweet posting, user profiles with avatar uploads, invite codes, and password reset via email (PHPMailer).

The PHP layer serves the user-facing application, and the Flask layer provides internal API endpoints that can be called by tooling or other services.

## ✨ Features

**User-facing (PHP):**
- User registration & login (secure password hashing)
- Tweet posting and exploring
- User profiles with avatar uploads
- Password reset via PHPMailer (email)
- Invite code system
- Responsive frontend

**Internal API (Python / Flask):**
- Separate process exposing REST endpoints
- Uses PyMySQL to talk to the same MySQL database

## 🛠 Tech Stack
- **Backend (main app):** PHP (Core), MySQL
- **Backend (internal API):** Python 3, Flask, PyMySQL
- **Dependencies:** Composer (PHP), pip + requirements.txt (Python), PHPMailer
- **Frontend:** HTML, CSS, JavaScript
- **Environment:** Linux (Apache/Nginx) — developed inside a Linux VM on macOS

## 📁 Project Structure (simplified)
