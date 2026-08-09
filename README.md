# School Clinic Management System

A lightweight web app for managing a school clinic. Built with HTML, Tailwind CSS (CDN), vanilla JavaScript, PHP, and MySQL.

## Stack

- **Frontend:** HTML, Tailwind CSS CDN, vanilla JS (`js/app.js`, `js/api.js`)
- **Backend:** PHP (`backend/index.php`), PDO, MySQL
- **Server:** Apache + PHP + MySQL (tested with XAMPP)

## Setup

1. Copy or clone this project into your web root (e.g. `c:\xampp\htdocs\clinic-system`).
2. Start **Apache** and **MySQL** in XAMPP.
3. Open `http://localhost/clinic-system/` in your browser.
4. The database (`clinic_system`) and tables are created automatically on first request.

## Demo login

- **Username:** `admin`
- **Password:** `admin123`

The default admin account is created automatically when the app first loads and no users exist.

## Project structure

```
clinic-system/
├── index.html              # Main frontend
├── js/
│   ├── app.js              # UI and module logic
│   └── api.js              # API client
├── backend/
│   ├── index.php           # API router
│   ├── auth.php            # JWT auth and login
│   ├── database.php        # DB layer
│   ├── schema_mysql.sql    # MySQL schema
│   └── ...
├── public/records/         # Runtime folder for record group folders
├── assets/                 # Favicon, etc.
├── archive/                # Unused React/Vite build files
└── README.md
```

## Notes

- `archive/` contains the unused React + Vite build files, configs, and package manifest. The active frontend is the vanilla JS version in `index.html`.
- For local development without XAMPP, you can use `php -S localhost:8000 -t .` from the project root.
