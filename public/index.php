<?php
require_once '/var/www/includes/config.php';

if (is_logged_in()) {
    header('Location: /dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    if (attempt_login($u, $p)) {
        header('Location: /dashboard.php');
        exit;
    }
    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sign In — <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:opsz,wght@9..40,300;400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0b0c10;--surface:#12141b;--border:#1e2230;
  --accent:#7c6bff;--accent-glow:rgba(124,107,255,.25);--accent2:#ff6b9d;
  --text:#e4e6ef;--muted:#5a6278;--surface2:#181b24;--danger:#f87171;
  --fh:'Syne',sans-serif;--fb:'DM Sans',sans-serif;
}
body{
  min-height:100vh;background:var(--bg);color:var(--text);
  font-family:var(--fb);display:flex;align-items:center;justify-content:center;
  position:relative;overflow:hidden;
}
/* Ambient blobs */
body::before,body::after{
  content:'';position:fixed;border-radius:50%;filter:blur(80px);pointer-events:none;
}
body::before{
  width:500px;height:500px;background:rgba(124,107,255,.1);
  top:-150px;right:-150px;
}
body::after{
  width:400px;height:400px;background:rgba(255,107,157,.07);
  bottom:-120px;left:-120px;
}
.login-wrap{
  width:100%;max-width:400px;padding:20px;position:relative;z-index:1;
  animation:fadeUp .5s ease both;
}
@keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
.login-brand{text-align:center;margin-bottom:36px}
.brand-mark{
  width:56px;height:56px;border-radius:16px;margin:0 auto 14px;
  background:linear-gradient(135deg,var(--accent),var(--accent2));
  display:flex;align-items:center;justify-content:center;
  font-family:var(--fh);font-size:22px;font-weight:800;color:#fff;
  box-shadow:0 0 32px var(--accent-glow);
}
.brand-name{font-family:var(--fh);font-size:22px;font-weight:800;letter-spacing:-.4px}
.brand-sub{font-size:13px;color:var(--muted);margin-top:4px}
.login-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:16px;padding:32px;
}
.error-box{
  background:rgba(248,113,113,.08);border:1px solid rgba(248,113,113,.2);
  color:var(--danger);border-radius:9px;padding:11px 14px;
  font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:8px;
}
.form-group{margin-bottom:18px}
label{display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:7px;text-transform:uppercase;letter-spacing:.5px}
input{
  width:100%;padding:11px 14px;border-radius:9px;border:1px solid var(--border);
  background:var(--surface2);color:var(--text);font-family:var(--fb);font-size:14px;
  outline:none;transition:border-color .15s,box-shadow .15s;
}
input:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow)}
input::placeholder{color:var(--muted)}
.btn-login{
  width:100%;padding:12px;border-radius:9px;border:none;cursor:pointer;
  background:var(--accent);color:#fff;font-family:var(--fh);font-size:15px;font-weight:700;
  letter-spacing:-.2px;transition:all .15s;margin-top:4px;
}
.btn-login:hover{background:#6b5be8;box-shadow:0 4px 24px var(--accent-glow);transform:translateY(-1px)}
.btn-login:active{transform:translateY(0)}
.hint{text-align:center;margin-top:20px;font-size:12px;color:var(--muted)}
.hint code{
  background:var(--surface2);border:1px solid var(--border);
  padding:2px 7px;border-radius:5px;font-family:monospace;font-size:12px;color:var(--text);
}
</style>
</head>
<body>
<div class="login-wrap">
  <div class="login-brand">
    <div class="brand-mark">A</div>
    <div class="brand-name"><?= APP_NAME ?></div>
    <div class="brand-sub">Sign in to your dashboard</div>
  </div>
  <div class="login-card">
    <?php if ($error): ?>
    <div class="error-box">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif ?>
    <form method="post">
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" placeholder="Enter your username" required autofocus
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="Enter your password" required>
      </div>
      <button type="submit" class="btn-login">Sign In →</button>
    </form>
  </div>
  <p class="hint">Default credentials: <code>admin</code> / <code>admin123</code></p>
</div>
</body>
</html>
