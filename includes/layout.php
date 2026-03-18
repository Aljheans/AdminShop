<?php
$nav_items = [
    ['slug'=>'dashboard','label'=>'Overview', 'icon'=>'grid'],
    ['slug'=>'users',    'label'=>'Users',    'icon'=>'users'],
    ['slug'=>'settings', 'label'=>'Settings', 'icon'=>'settings'],
];
$current = basename($_SERVER['PHP_SELF'], '.php');

function nav_icon(string $name): string {
    $icons = [
        'grid'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',
        'users'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'settings' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
        'logout'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
    ];
    return $icons[$name] ?? '';
}

$username = $_SESSION['username'] ?? 'admin';
$initials = strtoupper(substr($username, 0, 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($page_title ?? 'Dashboard') ?> — <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:opsz,wght@9..40,300;400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0b0c10;
  --surface:#12141b;
  --surface2:#181b24;
  --border:#1e2230;
  --accent:#7c6bff;
  --accent-glow:rgba(124,107,255,.25);
  --accent2:#ff6b9d;
  --text:#e4e6ef;
  --muted:#5a6278;
  --muted2:#3d4459;
  --success:#34d399;
  --warning:#fbbf24;
  --danger:#f87171;
  --sidebar:240px;
  --radius:12px;
  --fh:'Syne',sans-serif;
  --fb:'DM Sans',sans-serif;
}
html,body{height:100%}
body{background:var(--bg);color:var(--text);font-family:var(--fb);font-size:14px;display:flex;line-height:1.5}

/* Sidebar */
.sidebar{
  width:var(--sidebar);position:fixed;inset:0 auto 0 0;
  background:var(--surface);border-right:1px solid var(--border);
  display:flex;flex-direction:column;z-index:100;
}
.s-logo{
  padding:26px 22px 22px;display:flex;align-items:center;gap:10px;
  font-family:var(--fh);font-size:18px;font-weight:800;letter-spacing:-.4px;
  border-bottom:1px solid var(--border);
}
.s-logo-mark{
  width:32px;height:32px;border-radius:9px;flex-shrink:0;
  background:linear-gradient(135deg,var(--accent),var(--accent2));
  display:flex;align-items:center;justify-content:center;
  font-size:15px;font-weight:800;color:#fff;
  box-shadow:0 0 16px var(--accent-glow);
}
.s-nav{flex:1;padding:16px 12px;display:flex;flex-direction:column;gap:3px}
.s-nav a{
  display:flex;align-items:center;gap:11px;padding:10px 13px;
  border-radius:9px;color:var(--muted);text-decoration:none;
  font-size:13.5px;font-weight:500;transition:all .15s;
  position:relative;
}
.s-nav a svg{width:16px;height:16px;flex-shrink:0;transition:all .15s}
.s-nav a:hover{background:rgba(124,107,255,.07);color:var(--text)}
.s-nav a.active{background:rgba(124,107,255,.13);color:var(--accent)}
.s-nav a.active svg{filter:drop-shadow(0 0 4px var(--accent))}
.s-nav a.active::before{
  content:'';position:absolute;left:-12px;top:25%;bottom:25%;
  width:3px;border-radius:0 3px 3px 0;
  background:var(--accent);box-shadow:0 0 8px var(--accent);
}
.s-footer{padding:14px 12px;border-top:1px solid var(--border)}
.s-user{
  display:flex;align-items:center;gap:10px;padding:10px 12px;
  border-radius:9px;background:var(--surface2);margin-bottom:8px;
}
.s-avatar{
  width:30px;height:30px;border-radius:50%;flex-shrink:0;
  background:linear-gradient(135deg,var(--accent),var(--accent2));
  display:flex;align-items:center;justify-content:center;
  font-family:var(--fh);font-size:11px;font-weight:700;color:#fff;
}
.s-uname{font-size:12.5px;font-weight:500;line-height:1.2}
.s-urole{font-size:11px;color:var(--muted)}
.s-logout{
  display:flex;align-items:center;gap:9px;width:100%;padding:9px 13px;
  border-radius:9px;background:none;border:none;cursor:pointer;
  color:var(--muted);font-family:var(--fb);font-size:13px;font-weight:500;
  transition:all .15s;
}
.s-logout:hover{background:rgba(248,113,113,.08);color:var(--danger)}
.s-logout svg{width:15px;height:15px}

/* Main */
.main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{
  height:60px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;padding:0 28px;gap:12px;
  background:rgba(11,12,16,.85);backdrop-filter:blur(12px);
  position:sticky;top:0;z-index:50;
}
.topbar-title{font-family:var(--fh);font-size:18px;font-weight:700;flex:1;letter-spacing:-.3px}
.topbar-time{font-size:12px;color:var(--muted)}
.content{padding:28px;flex:1}

/* Cards */
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius)}
.card-body{padding:22px}
.card-header{padding:18px 22px 0;display:flex;align-items:center;justify-content:space-between}
.card-title{font-family:var(--fh);font-size:14px;font-weight:700;letter-spacing:-.2px}
.card-sub{font-size:12px;color:var(--muted);margin-top:2px}

/* Buttons */
.btn{
  display:inline-flex;align-items:center;gap:7px;
  padding:8px 16px;border-radius:8px;border:none;cursor:pointer;
  font-family:var(--fb);font-size:13px;font-weight:500;transition:all .15s;
  text-decoration:none;
}
.btn-primary{background:var(--accent);color:#fff;box-shadow:0 0 0 0 var(--accent-glow)}
.btn-primary:hover{background:#6b5be8;box-shadow:0 0 16px var(--accent-glow)}
.btn-ghost{background:var(--surface2);color:var(--text);border:1px solid var(--border)}
.btn-ghost:hover{background:var(--border)}
.btn-danger{background:rgba(248,113,113,.1);color:var(--danger);border:1px solid rgba(248,113,113,.2)}
.btn-danger:hover{background:rgba(248,113,113,.2)}
.btn svg{width:14px;height:14px}

/* Badge */
.badge{display:inline-flex;align-items:center;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:500}
.badge-success{background:rgba(52,211,153,.1);color:var(--success)}
.badge-danger {background:rgba(248,113,113,.1);color:var(--danger)}
.badge-accent {background:rgba(124,107,255,.1);color:var(--accent)}
.badge-warning{background:rgba(251,191,36,.1);color:var(--warning)}

/* Table */
.table-wrap{overflow-x:auto;border-radius:0 0 var(--radius) var(--radius)}
table{width:100%;border-collapse:collapse}
thead th{
  padding:11px 16px;text-align:left;font-size:11px;font-weight:600;
  color:var(--muted);text-transform:uppercase;letter-spacing:.6px;
  border-bottom:1px solid var(--border);background:var(--surface2);
}
tbody td{padding:13px 16px;border-bottom:1px solid var(--border);font-size:13.5px}
tbody tr:last-child td{border-bottom:none}
tbody tr:hover td{background:rgba(255,255,255,.02)}

/* Forms */
.form-group{margin-bottom:18px}
label{display:block;font-size:12.5px;font-weight:500;color:var(--muted);margin-bottom:7px}
input[type=text],input[type=email],input[type=password],select,textarea{
  width:100%;padding:10px 14px;border-radius:9px;border:1px solid var(--border);
  background:var(--surface2);color:var(--text);font-family:var(--fb);font-size:14px;
  outline:none;transition:border-color .15s;
}
input:focus,select:focus,textarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow)}
textarea{resize:vertical;min-height:90px}
select option{background:var(--surface2)}

/* Toggle */
.toggle-row{display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:1px solid var(--border)}
.toggle-row:last-child{border-bottom:none}
.toggle-info .t-label{font-size:14px;font-weight:500}
.toggle-info .t-desc{font-size:12px;color:var(--muted);margin-top:2px}
.toggle{position:relative;width:42px;height:24px;flex-shrink:0}
.toggle input{opacity:0;width:0;height:0;position:absolute}
.toggle-track{
  position:absolute;inset:0;border-radius:24px;cursor:pointer;
  background:var(--muted2);transition:background .2s;
}
.toggle input:checked+.toggle-track{background:var(--accent);box-shadow:0 0 10px var(--accent-glow)}
.toggle-track::after{
  content:'';position:absolute;top:3px;left:3px;
  width:18px;height:18px;border-radius:50%;background:#fff;
  transition:transform .2s;
}
.toggle input:checked+.toggle-track::after{transform:translateX(18px)}

/* Misc */
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
.mt-6{margin-top:24px}
.mt-4{margin-top:16px}
.flex-between{display:flex;align-items:center;justify-content:space-between}
.flex-gap{display:flex;align-items:center;gap:10px}
.divider{height:1px;background:var(--border);margin:20px 0}
.empty{text-align:center;padding:48px 20px;color:var(--muted)}

/* Stat card */
.stat{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius);padding:20px;position:relative;overflow:hidden;
}
.stat-label{font-size:12px;color:var(--muted);font-weight:500;margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px}
.stat-value{font-family:var(--fh);font-size:30px;font-weight:800;line-height:1;letter-spacing:-1px}
.stat-change{font-size:12px;margin-top:8px}
.stat-change.up{color:var(--success)}
.stat-change.down{color:var(--danger)}
.stat-icon{
  position:absolute;right:18px;top:18px;
  width:40px;height:40px;border-radius:10px;
  display:flex;align-items:center;justify-content:center;opacity:.9;
}
.stat-icon svg{width:20px;height:20px}

@media(max-width:768px){
  .sidebar{transform:translateX(-100%)}
  .main{margin-left:0}
  .grid-4{grid-template-columns:1fr 1fr}
  .grid-2{grid-template-columns:1fr}
}
</style>
</head>
<body>

<aside class="sidebar">
  <div class="s-logo">
    <div class="s-logo-mark">A</div>
    <?= APP_NAME ?>
  </div>
  <nav class="s-nav">
    <?php foreach ($nav_items as $item):
      $href = $item['slug'] === 'dashboard' ? '/dashboard.php' : "/{$item['slug']}.php";
      $active = ($current === $item['slug'] || ($current === 'dashboard' && $item['slug'] === 'dashboard')) ? 'active' : '';
    ?>
    <a href="<?= $href ?>" class="<?= $active ?>">
      <?= nav_icon($item['icon']) ?>
      <?= $item['label'] ?>
    </a>
    <?php endforeach ?>
  </nav>
  <div class="s-footer">
    <div class="s-user">
      <div class="s-avatar"><?= $initials ?></div>
      <div>
        <div class="s-uname"><?= htmlspecialchars($username) ?></div>
        <div class="s-urole">Administrator</div>
      </div>
    </div>
    <form action="/logout.php" method="post">
      <button type="submit" class="s-logout">
        <?= nav_icon('logout') ?> Sign out
      </button>
    </form>
  </div>
</aside>

<div class="main">
  <header class="topbar">
    <div class="topbar-title"><?= htmlspecialchars($page_title ?? 'Dashboard') ?></div>
    <div class="topbar-time" id="clock"></div>
  </header>
  <div class="content">
