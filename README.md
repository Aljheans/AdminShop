# PHP Admin Panel

A clean, dark-themed PHP admin dashboard with login, overview, users, and settings pages. Ready to deploy on [Render](https://render.com).

## Features
- 🔐 Secure login with session management
- 📊 Overview dashboard with live stats
- 👥 Users page with search & role filtering
- ⚙️ Settings page with toggles and forms
- 🚀 One-click deploy to Render via Docker

## Default Credentials
| Username | Password  |
|----------|-----------|
| `admin`  | `admin123`|

---

## Deploy to Render

### Option 1 — render.yaml (Blueprint)
1. Push this repo to GitHub
2. Go to [render.com](https://render.com) → **New** → **Blueprint**
3. Connect your repo — Render will read `render.yaml` automatically
4. Click **Apply** — done!

### Option 2 — Manual Web Service
1. Push this repo to GitHub
2. Go to Render → **New** → **Web Service**
3. Connect your repo
4. Set **Environment** to `Docker`
5. Add environment variables:
   - `ADMIN_USERNAME` → your username
   - `ADMIN_PASSWORD` → your password
6. Click **Deploy**

---

## Changing Credentials
Update them in Render's **Environment** tab:
- `ADMIN_USERNAME`
- `ADMIN_PASSWORD`

No redeploy needed — the app reads them at runtime.

---

## Folder Structure
```
admin-panel/
├── Dockerfile              # Docker build for Render
├── render.yaml             # Render Blueprint config
├── README.md
├── includes/
│   ├── config.php          # Auth helpers + data
│   ├── layout.php          # Sidebar + topbar (header)
│   └── layout_end.php      # Closing tags + clock JS
└── public/                 # Apache web root
    ├── .htaccess
    ├── index.php           # Login page
    ├── logout.php
    ├── dashboard.php       # Overview
    ├── users.php           # Users management
    └── settings.php        # App settings
```

## Connecting a Real Database
The user data in `includes/config.php` is a static array for demo purposes.
To use a real database:
1. Add a `DATABASE_URL` env var in Render
2. Replace `get_users()` in `config.php` with a PDO query
3. Implement actual CRUD in `users.php`

Render supports **PostgreSQL** add-ons natively.
