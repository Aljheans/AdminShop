<?php
session_start();
require_once("../config/db.php");
require_once("../config/activity.php");

// ── Auth guard: must be logged in as admin or superadmin ──
if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: ../index.php?error=Please+log+in+to+access+the+dashboard");
    exit;
}

define('API_BASE_URL', getenv('API_BASE_URL') ?: 'https://gatewayv1.onrender.com');

$adminName = $_SESSION['username'] ?? 'admin';
$adminRole = $_SESSION['role']     ?? 'admin';
$isSuperadmin = ($adminRole === 'superadmin');

// ── Resolve current admin's permissions ──
$myPerms = ['can_userdata' => 1, 'can_activity' => 1, 'can_settings' => 1, 'can_sales' => 1]; // superadmin: full access
if (!$isSuperadmin) {
    $myId = $_SESSION['user_id'] ?? 0;
    $p = $conn->prepare("SELECT can_userdata, can_activity, can_settings, can_sales FROM admin_permissions WHERE user_id = :id");
    $p->execute([':id' => $myId]);
    $row = $p->fetch(PDO::FETCH_ASSOC);
    $myPerms = $row ?: ['can_userdata' => 0, 'can_activity' => 0, 'can_settings' => 0, 'can_sales' => 0];
}

/* ── API CHECK ── */
function checkApiConnection(): bool {
    $ch = curl_init(API_BASE_URL . '/ping');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code === 200;
}
$apiOnline = checkApiConnection();

/* ── STATS ── */
$totalUsers  = (int)$conn->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$totalAdmins = (int)$conn->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();

/* ── ACTIVE SECTION ── */
$section = $_GET['section'] ?? 'user-management';

// Permission gate for non-superadmins
if (!$isSuperadmin) {
    if ($section === 'settings' && !$myPerms['can_settings']) {
        $section = 'user-management';
    }
    if ($section === 'user-management' && !$myPerms['can_userdata']) {
        $section = 'admins';
    }
    if ($section === 'activity' && !$myPerms['can_activity']) {
        $section = 'admins';
    }
    $salesSections = ['sales-overview','sales-admin-salary','sales-catered','sales-denied','sales-tickets','sales-orders'];
    if (in_array($section, $salesSections) && !$myPerms['can_sales']) {
        $section = 'admins';
    }
}

/* ── USER MANAGEMENT — only role='user' rows ── */
$limit      = 5;
$page       = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($page - 1) * $limit;
$totalAll   = (int)$conn->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$totalPages = (int)ceil($totalAll / $limit);

$stmt = $conn->prepare("SELECT * FROM users WHERE role='user' ORDER BY created_at DESC LIMIT :l OFFSET :o");
$stmt->bindValue(':l', $limit,  PDO::PARAM_INT);
$stmt->bindValue(':o', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ── RESET LOG (users) ── */
$resetLog = [];
try {
    $resetLog = $conn->query("
        SELECT rl.username, rl.code, rl.reset_at
        FROM password_reset_log rl
        JOIN users u ON u.username = rl.username
        WHERE u.role = 'user'
        ORDER BY rl.reset_at DESC LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

/* ── RESET LOG (admins) ── */
$adminResetLog = [];
try {
    $adminResetLog = $conn->query("
        SELECT rl.username, rl.code, rl.reset_at
        FROM password_reset_log rl
        JOIN users u ON u.username = rl.username
        WHERE u.role IN ('admin','superadmin')
        ORDER BY rl.reset_at DESC LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

/* ── ADMINS LIST ── */
$adminsList = $conn->query("
    SELECT u.id, u.username, u.email, u.uid, u.role,
           COALESCE(p.can_userdata,0) AS can_userdata,
           COALESCE(p.can_activity,0) AS can_activity,
           COALESCE(p.can_settings,0) AS can_settings,
           COALESCE(p.can_sales,0) AS can_sales
    FROM users u
    LEFT JOIN admin_permissions p ON p.user_id = u.id
    WHERE u.role IN ('admin','superadmin')
    ORDER BY u.uid ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* ── ACTIVITY LOG ── */
$activityLog = [];
$activityPage  = max(1, (int)($_GET['apage'] ?? 1));
$activityLimit = 20;
$activityOffset = ($activityPage - 1) * $activityLimit;
$activityTotal = 0;
$activityPages = 1;
if ($section === 'activity' || $isSuperadmin || $myPerms['can_activity']) {
    try {
        $activityTotal = (int)$conn->query("SELECT COUNT(*) FROM activity_log")->fetchColumn();
        $activityPages = max(1, (int)ceil($activityTotal / $activityLimit));
        $aStmt = $conn->prepare("SELECT * FROM activity_log ORDER BY logged_at DESC LIMIT :l OFFSET :o");
        $aStmt->bindValue(':l', $activityLimit, PDO::PARAM_INT);
        $aStmt->bindValue(':o', $activityOffset, PDO::PARAM_INT);
        $aStmt->execute();
        $activityLog = $aStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// Sales section labels
$salesLabels = [
    'sales-overview'     => 'Sales Overview',
    'sales-admin-salary' => 'Admin Salary',
    'sales-catered'      => 'Total Ordered Catered',
    'sales-denied'       => 'Total Ordered Denied',
    'sales-tickets'      => 'Tickets',
    'sales-orders'       => 'Orders',
];

$settingsMsg = ''; $settingsMsgType = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='change_password') {
    $curPw=$_POST['current_password']??'';
    $newPw=$_POST['new_password']??'';
    $conPw=$_POST['confirm_password']??'';
    $row=$conn->prepare("SELECT password FROM users WHERE username=:u");
    $row->execute([':u'=>$adminName]);$row=$row->fetch(PDO::FETCH_ASSOC);
    if(!$row||!password_verify($curPw,$row['password'])){$settingsMsg='Current password is incorrect.';$settingsMsgType='error';}
    elseif(strlen($newPw)<8){$settingsMsg='New password must be at least 8 characters.';$settingsMsgType='error';}
    elseif($newPw!==$conPw){$settingsMsg='Passwords do not match.';$settingsMsgType='error';}
    else{
        $conn->prepare("UPDATE users SET password=:p WHERE username=:u")->execute([':p'=>password_hash($newPw,PASSWORD_BCRYPT),':u'=>$adminName]);
        log_activity($conn,$adminName,'Changed own password',$adminName,'');
        $settingsMsg='Password changed successfully.';$settingsMsgType='success';
    }
    $section='settings';
}

/* ── SETTINGS: DB COMMAND ── */
$dbCmdResult='';$dbCmdType='';
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='db_command') {
    $sql=trim($_POST['sql_command']??'');
    if($sql){
        try{
            $upper=strtoupper(ltrim($sql));
            if(str_starts_with($upper,'SELECT')){
                $res=$conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
                $dbCmdResult=$res?json_encode($res,JSON_PRETTY_PRINT):'(no rows)';
            }else{$conn->exec($sql);$dbCmdResult='Query executed successfully.';}
            log_activity($conn,$adminName,'Executed DB command','',$sql);
            $dbCmdType='success';
        }catch(Exception $e){$dbCmdResult='Error: '.$e->getMessage();$dbCmdType='error';}
    }
    $section='settings';
}

function imgSrc(string $url): string {
    if (empty($url)) return '';
    if (str_starts_with($url, 'data:')) return $url;
    return '../' . ltrim($url, '/');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/admindash.css">
</head>
<body>

<nav class="topnav">
  <div style="display:flex;align-items:center;gap:12px">
    <button class="sidebar-toggle" onclick="toggleSidebar()">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <span class="topnav-title">Admin Dashboard</span>
  </div>
  <div style="display:flex;align-items:center;gap:12px">
    <?php if($isSuperadmin): ?>
      <span class="superadmin-badge">⚡ Superadmin</span>
    <?php else: ?>
      <span style="font-size:13px;color:var(--muted)">👤 <?= htmlspecialchars($adminName) ?></span>
    <?php endif; ?>
    <div class="api-badge <?= $apiOnline?'':'offline' ?>">
      <span class="api-dot"></span>
      <?= $apiOnline?'API Connected':'API Offline' ?>
    </div>
  </div>
</nav>

<div class="layout">

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <nav class="sidenav">

    <?php
    $userMgmtSections = ['user-management','admins','activity'];
    $salesSections    = ['sales-overview','sales-admin-salary','sales-catered','sales-denied','sales-tickets','sales-orders'];
    $inUserMgmt = in_array($section, $userMgmtSections);
    $inSales    = in_array($section, $salesSections);
    $showUserGroup  = $isSuperadmin || $myPerms['can_userdata'] || $myPerms['can_activity'];
    $showSalesGroup = $isSuperadmin || $myPerms['can_sales'];
    ?>

    <!-- ── User Management Group ── -->
    <?php if($showUserGroup): ?>
    <div class="nav-group <?= $inUserMgmt ? 'open' : '' ?>" id="grp-user">
      <button class="nav-group-header" onclick="toggleGroup('grp-user')">
        <span class="nav-group-label">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          User Management
        </span>
        <svg class="nav-chevron" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
      </button>
      <div class="nav-group-items">
        <?php if($isSuperadmin || $myPerms['can_userdata']): ?>
        <a href="?section=user-management" class="sidenav-item sidenav-child <?= $section==='user-management'?'active':'' ?>">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
          Users
        </a>
        <?php endif; ?>
        <a href="?section=admins" class="sidenav-item sidenav-child <?= $section==='admins'?'active':'' ?>">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M6 20v-2a6 6 0 0 1 12 0v2"/></svg>
          Admins
        </a>
        <?php if($isSuperadmin || $myPerms['can_activity']): ?>
        <a href="?section=activity" class="sidenav-item sidenav-child <?= $section==='activity'?'active':'' ?>">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/></svg>
          Activity Logs
        </a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── Sales Management Group ── -->
    <?php if($showSalesGroup): ?>
    <div class="nav-group <?= $inSales ? 'open' : '' ?>" id="grp-sales">
      <button class="nav-group-header" onclick="toggleGroup('grp-sales')">
        <span class="nav-group-label">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          Sales Management
        </span>
        <svg class="nav-chevron" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
      </button>
      <div class="nav-group-items">
        <a href="?section=sales-overview" class="sidenav-item sidenav-child <?= $section==='sales-overview'?'active':'' ?>">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg>
          Sales Overview
        </a>
        <a href="?section=sales-admin-salary" class="sidenav-item sidenav-child <?= $section==='sales-admin-salary'?'active':'' ?>">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>
          Admin Salary
        </a>
        <a href="?section=sales-catered" class="sidenav-item sidenav-child <?= $section==='sales-catered'?'active':'' ?>">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          Total Ordered Catered
        </a>
        <a href="?section=sales-denied" class="sidenav-item sidenav-child <?= $section==='sales-denied'?'active':'' ?>">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
          Total Ordered Denied
        </a>
        <a href="?section=sales-tickets" class="sidenav-item sidenav-child <?= $section==='sales-tickets'?'active':'' ?>">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2z"/></svg>
          Tickets
        </a>
        <a href="?section=sales-orders" class="sidenav-item sidenav-child <?= $section==='sales-orders'?'active':'' ?>">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
          Orders
        </a>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── Settings ── -->
    <?php if($isSuperadmin || $myPerms['can_settings']): ?>
    <a href="?section=settings" class="sidenav-item <?= $section==='settings'?'active':'' ?>">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
      Settings
    </a>
    <?php endif; ?>

    <div class="sidenav-divider"></div>
    <a href="../api/logout.php" class="sidenav-item danger">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Logout
    </a>
  </nav>
</aside>

<!-- MAIN -->
<main class="main-content">

  <!-- STAT CARDS — hidden in Sales Management -->
  <?php if(!in_array($section, ['sales-overview','sales-admin-salary','sales-catered','sales-denied','sales-tickets','sales-orders'])): ?>
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon purple">
        <svg width="22" height="22" fill="none" stroke="#8b5cf6" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M6 20v-2a6 6 0 0 1 12 0v2"/></svg>
      </div>
      <div><div class="stat-label">Admins</div><div class="stat-value" id="liveAdmins">—</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon green">
        <svg width="22" height="22" fill="none" stroke="#22c55e" stroke-width="1.8" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      </div>
      <div><div class="stat-label">Users</div><div class="stat-value" id="liveUsers">—</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon amber">
        <svg width="22" height="22" fill="none" stroke="#f59e0b" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      </div>
      <div><div class="stat-label">Date</div><div class="stat-value" style="font-size:18px"><?= date('l') ?></div></div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Flash -->
  <?php if(!empty($_GET['error'])): ?>
    <div class="flash error">✗ <?= htmlspecialchars($_GET['error']) ?></div>
  <?php elseif(!empty($_GET['success'])): ?>
    <div class="flash success" id="successFlash">
      ✓ <?= htmlspecialchars($_GET['success']) ?>
      <span style="float:right;color:inherit;opacity:.7;font-size:12px" id="redirectCountdown"> — redirecting in 3s</span>
    </div>
    <script>
      let secs=3;const cd=document.getElementById("redirectCountdown");
      const t=setInterval(()=>{secs--;cd.textContent=` — redirecting in ${secs}s`;if(secs<=0){clearInterval(t);window.location.href="index.php";}},1000);
    </script>
  <?php endif; ?>

  <!-- ══════════ USER MANAGEMENT ══════════ -->
  <?php if($section==='user-management'): ?>

  <div class="card">
    <div class="card-header" style="flex-wrap:wrap;gap:10px">
      <span class="card-title">User Management</span>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <div class="search-wrap">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" id="searchInput" class="search-input" placeholder="Search users...">
        </div>
        <button class="btn btn-danger" style="font-size:12px;padding:6px 12px;white-space:nowrap" onclick="confirmLogoutAll('users')">
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Logout All Users
        </button>
        <button class="btn btn-danger" style="font-size:12px;padding:6px 12px;white-space:nowrap" onclick="confirmLogoutAll('admins')">
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Logout All Admins
        </button>
        <button class="btn btn-dark" onclick="openAddModal()" style="white-space:nowrap">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Add User
        </button>
      </div>
    </div>
    <table class="data-table" id="userTable">
      <thead><tr><th>UID</th><th>Username</th><th>Password</th><th>Email</th><th>Status</th><th>Role</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach($users as $row): ?>
      <tr>
        <td><span class="uid-badge"><?= htmlspecialchars($row['uid'] ?? '—') ?></span></td>
        <td style="font-weight:500"><?= htmlspecialchars($row['username']) ?></td>
        <td>
          <span style="color:var(--muted);font-family:'DM Mono',monospace;letter-spacing:2px;font-size:13px">********</span>
          <button class="btn-reset" style="margin-left:8px" data-id="<?= $row['id'] ?>" data-username="<?= htmlspecialchars($row['username']) ?>" onclick="resetPassword(this)">Reset</button>
        </td>
        <td style="color:var(--muted)"><?= htmlspecialchars($row['email']) ?></td>
        <td><span class="status-dot user-status" data-user="<?= $row['username'] ?>"></span></td>
        <td>
          <?php $r=$row['role'];$c=match($r){'admin'=>'role-admin','superadmin'=>'role-superadmin',default=>'role-user'}; ?>
          <span class="role-badge <?= $c ?>"><?= htmlspecialchars($r) ?></span>
        </td>
        <td>
          <?php if($row['role']!=='superadmin'): ?>
          <button class="btn-icon" title="Edit" onclick="openEditModal(<?= $row['id'] ?>,'<?= addslashes($row['username']) ?>','<?= addslashes($row['email']) ?>','<?= addslashes($row['role']) ?>')">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          </button>
          <button class="btn-icon red" title="Delete" onclick="confirmDelete(<?= $row['id'] ?>,'<?= addslashes($row['username']) ?>')">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
          </button>
          <button class="btn-icon" title="Force logout" style="color:#f59e0b"
            onclick="forceLogoutUser(<?= $row['id'] ?>,'<?= addslashes($row['username']) ?>')">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          </button>
          <?php else: ?>
          <span style="font-size:11px;color:var(--muted)">protected</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <div class="pagination">
      <?php for($i=1;$i<=$totalPages;$i++): ?>
        <a href="?section=user-management&page=<?= $i ?>" class="<?= $i==$page?'active':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <span class="card-title">🔑 Password Reset Log</span>
      <span style="font-size:12px;color:var(--muted)">Latest 20 — share code with the user</span>
    </div>
    <table class="data-table">
      <thead><tr><th>Username</th><th>Temporary Code</th><th>Reset At</th></tr></thead>
      <tbody id="resetLogBody">
      <?php if(empty($resetLog)): ?>
        <tr id="emptyRow"><td colspan="3" class="empty-state">No resets yet.</td></tr>
      <?php else: ?>
        <?php foreach($resetLog as $e): ?>
        <tr>
          <td style="font-weight:500"><?= htmlspecialchars($e['username']) ?></td>
          <td><span class="code-badge"><?= htmlspecialchars($e['code']) ?></span></td>
          <td style="color:var(--muted)"><?= htmlspecialchars($e['reset_at']) ?></td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- ══════════ ADMINS ══════════ -->
  <?php elseif($section==='admins'): ?>

  <?php $adminCount = count(array_filter($adminsList, fn($a) => $a['role']==='admin')); ?>
  <div class="card">
    <div class="card-header">
      <span class="card-title">Admins</span>
      <span style="font-size:12px;color:var(--muted)"><?= $adminCount ?>/5 admin slots used</span>
    </div>
    <table class="data-table">
      <thead>
        <tr>
          <th>UID</th><th>Username</th><th>Email</th><th>Role</th>
          <th>Users Data</th><th>Activity Logs</th><th>Settings</th><th>Sales</th>
          <th>Password</th>
          <?php if($isSuperadmin): ?><th>Save</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
      <?php foreach($adminsList as $a): ?>
      <tr>
        <td><span class="uid-badge <?= $a['role']==='superadmin'?'uid-super':'' ?>"><?= htmlspecialchars($a['uid']) ?></span></td>
        <td style="font-weight:500">
          <?= htmlspecialchars($a['username']) ?>
          <?php if($a['role']==='superadmin'): ?><span class="superadmin-badge" style="font-size:10px;padding:2px 7px;margin-left:6px">⚡ Super</span><?php endif; ?>
        </td>
        <td style="color:var(--muted)"><?= htmlspecialchars($a['email']) ?></td>
        <td><span class="role-badge <?= $a['role']==='superadmin'?'role-superadmin':'role-admin' ?>"><?= $a['role'] ?></span></td>

        <?php if($a['role']==='superadmin'): ?>
          <td><span class="perm-full">✓ Full</span></td>
          <td><span class="perm-full">✓ Full</span></td>
          <td><span class="perm-full">✓ Full</span></td>
          <td><span class="perm-full">✓ Full</span></td>
          <td><span style="font-size:11px;color:var(--muted)">protected</span></td>
          <?php if($isSuperadmin): ?><td><span style="font-size:11px;color:var(--muted)">—</span></td><?php endif; ?>
        <?php elseif($isSuperadmin): ?>
          <form method="POST" action="update_admin_permissions.php" style="display:contents">
            <input type="hidden" name="user_id" value="<?= $a['id'] ?>">
            <td><label class="checkbox-label"><input type="checkbox" name="can_userdata" value="1" <?= $a['can_userdata']?'checked':'' ?>></label></td>
            <td><label class="checkbox-label"><input type="checkbox" name="can_activity" value="1" <?= $a['can_activity']?'checked':'' ?>></label></td>
            <td><label class="checkbox-label"><input type="checkbox" name="can_settings" value="1" <?= $a['can_settings']?'checked':'' ?>></label></td>
            <td><label class="checkbox-label"><input type="checkbox" name="can_sales" value="1" <?= $a['can_sales']?'checked':'' ?>></label></td>
            <td>
              <button class="btn-reset" data-id="<?= $a['id'] ?>" data-username="<?= htmlspecialchars($a['username']) ?>" onclick="resetAdminPassword(this)">Reset</button>
            </td>
            <td><button type="submit" class="btn btn-dark" style="padding:5px 12px;font-size:12px">Save</button></td>
          </form>
        <?php else: ?>
          <td><?= $a['can_userdata'] ? '<span class="perm-on">✓ Yes</span>' : '<span class="perm-off">✗ No</span>' ?></td>
          <td><?= $a['can_activity'] ? '<span class="perm-on">✓ Yes</span>' : '<span class="perm-off">✗ No</span>' ?></td>
          <td><?= $a['can_settings'] ? '<span class="perm-on">✓ Yes</span>' : '<span class="perm-off">✗ No</span>' ?></td>
          <td><?= $a['can_sales'] ? '<span class="perm-on">✓ Yes</span>' : '<span class="perm-off">✗ No</span>' ?></td>
          <td><span style="font-size:11px;color:var(--muted)">—</span></td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php if(!$isSuperadmin): ?>
    <div style="padding:14px 20px;font-size:12px;color:var(--muted);border-top:1px solid var(--border)">
      Only the Superadmin can modify permissions. You are viewing this page in read-only mode.
    </div>
    <?php endif; ?>
  </div>

  <!-- Admin Password Reset Log -->
  <div class="card" style="margin-top:16px">
    <div class="card-header">
      <span class="card-title">🔑 Admin Password Reset Log</span>
      <span style="font-size:12px;color:var(--muted)">Latest 20 — share code with the admin</span>
    </div>
    <table class="data-table">
      <thead><tr><th>Username</th><th>Temporary Code</th><th>Reset At</th></tr></thead>
      <tbody id="adminResetLogBody">
      <?php if(empty($adminResetLog)): ?>
        <tr id="adminEmptyRow"><td colspan="3" class="empty-state">No admin resets yet.</td></tr>
      <?php else: ?>
        <?php foreach($adminResetLog as $e): ?>
        <tr>
          <td style="font-weight:500"><?= htmlspecialchars($e['username']) ?></td>
          <td><span class="code-badge"><?= htmlspecialchars($e['code']) ?></span></td>
          <td style="color:var(--muted)"><?= htmlspecialchars($e['reset_at']) ?></td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- ══════════ ACTIVITY LOGS ══════════ -->
  <?php elseif($section==='activity'): ?>

  <div class="card">
    <div class="card-header" style="flex-wrap:wrap;gap:10px">
      <span class="card-title">Activity Logs</span>
      <span style="font-size:12px;color:var(--muted)"><?= number_format($activityTotal) ?> total entries</span>
    </div>
    <?php if(empty($activityLog)): ?>
      <div style="padding:40px;text-align:center;color:var(--muted);font-size:13px">No activity recorded yet.</div>
    <?php else: ?>
    <table class="data-table">
      <thead><tr><th>#</th><th>Admin</th><th>Action</th><th>Target</th><th>Detail</th><th>Time</th></tr></thead>
      <tbody>
      <?php foreach($activityLog as $entry): ?>
      <tr>
        <td style="color:var(--muted);font-size:12px"><?= htmlspecialchars($entry['id']) ?></td>
        <td><span style="font-weight:500;font-size:13px"><?= htmlspecialchars($entry['admin']) ?></span></td>
        <td><span class="activity-action"><?= htmlspecialchars($entry['action']) ?></span></td>
        <td style="color:var(--muted)"><?= htmlspecialchars($entry['target'] ?? '—') ?></td>
        <td style="color:var(--muted);font-size:12px;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
            title="<?= htmlspecialchars($entry['detail'] ?? '') ?>">
          <?= htmlspecialchars($entry['detail'] ?? '—') ?>
        </td>
        <td style="color:var(--muted);font-size:12px;white-space:nowrap"><?= htmlspecialchars($entry['logged_at']) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php if($activityPages > 1): ?>
    <div class="pagination">
      <?php for($i=1;$i<=$activityPages;$i++): ?>
        <a href="?section=activity&apage=<?= $i ?>" class="<?= $i==$activityPage?'active':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- ══════════ SALES MANAGEMENT ══════════ -->
  <?php elseif(isset($salesLabels[$section])): ?>
  <div class="card">
    <div class="card-header">
      <span class="card-title"><?= $salesLabels[$section] ?></span>
      <span style="font-size:12px;color:var(--muted)">Coming soon</span>
    </div>
    <div style="padding:60px 32px;text-align:center;color:var(--muted)">
      <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 14px;display:block;opacity:.4"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
      <div style="font-size:14px;font-weight:500;margin-bottom:6px"><?= $salesLabels[$section] ?></div>
      <div style="font-size:13px">Content for this section hasn't been added yet.</div>
    </div>
  </div>

  <!-- ══════════ SETTINGS ══════════ -->
  <?php elseif($section==='settings'): ?>

  <div class="settings-grid">
    <div class="card">
      <div class="card-header"><span class="card-title">🔒 Change Password</span></div>
      <div style="padding:24px">
        <?php if($settingsMsg): ?>
          <div class="flash <?= $settingsMsgType ?>" style="margin-bottom:16px"><?= $settingsMsgType==='success'?'✓':'✗' ?> <?= htmlspecialchars($settingsMsg) ?></div>
        <?php endif; ?>
        <form method="POST">
          <input type="hidden" name="_action" value="change_password">
          <label class="field-label">Current Password</label>
          <input type="password" name="current_password" class="field-input" placeholder="Enter current password" required>
          <label class="field-label">New Password</label>
          <input type="password" name="new_password" class="field-input" placeholder="Min. 8 characters" required>
          <label class="field-label">Confirm New Password</label>
          <input type="password" name="confirm_password" class="field-input" placeholder="Repeat new password" required>
          <button type="submit" class="btn btn-dark" style="margin-top:14px">Update Password</button>
        </form>
      </div>
    </div>

    <?php if($isSuperadmin): ?>
    <div class="card">
      <div class="card-header"><span class="card-title">💾 Download Database</span></div>
      <div style="padding:24px">
        <p style="font-size:13.5px;color:var(--muted);margin-bottom:20px;line-height:1.6">Download a full copy of the SQLite database. Keep it secure — it contains all user data.</p>
        <a href="download_db.php" class="btn btn-dark">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Download database.db
        </a>
        <p style="margin-top:12px;font-size:12px;color:var(--muted)">Last modified: <?= date('Y-m-d H:i:s', filemtime(__DIR__.'/../database/database.db')) ?></p>
      </div>
    </div>

    <div class="card" style="grid-column:1/-1">
      <div class="card-header">
        <span class="card-title">⚡ Database Commands</span>
        <span style="font-size:12px;color:var(--muted)">Raw SQL — use with caution</span>
      </div>
      <div style="padding:24px">
        <?php if($dbCmdResult): ?>
          <div class="flash <?= $dbCmdType ?>" style="margin-bottom:16px">
            <pre style="margin:0;font-family:'DM Mono',monospace;font-size:12px;white-space:pre-wrap;word-break:break-all"><?= htmlspecialchars($dbCmdResult) ?></pre>
          </div>
        <?php endif; ?>
        <form method="POST">
          <input type="hidden" name="_action" value="db_command">
          <label class="field-label">SQL Command</label>
          <textarea name="sql_command" class="field-textarea" rows="4" placeholder="e.g. SELECT * FROM users;"><?= htmlspecialchars($_POST['sql_command']??'') ?></textarea>
          <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
            <button type="submit" class="btn btn-dark">Run</button>
            <button type="button" class="btn btn-ghost" onclick="fillSql('SELECT name, sql FROM sqlite_master WHERE type=\'table\' ORDER BY name;')">Show Tables</button>
            <button type="button" class="btn btn-ghost" onclick="fillSql('SELECT * FROM users;')">Query Users</button>
            <button type="button" class="btn btn-ghost" onclick="fillSql('SELECT * FROM user_stats;')">Query Stats</button>
            <button type="button" class="btn btn-ghost" onclick="fillSql('SELECT * FROM activity_log ORDER BY logged_at DESC LIMIT 20;')">Recent Activity</button>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <?php endif; ?>

</main>
</div>

<!-- MODALS -->
<div class="modal" id="editModal">
<div class="modal-box">
  <h3>Edit User</h3>
  <form method="post" action="edit_user.php">
    <input type="hidden" name="id" id="editId">
    <label>Username</label><input type="text" name="username" id="editUsername" required>
    <label>Email</label><input type="email" name="email" id="editEmail" required>
    <label>Role</label>
    <select name="role" id="editRole"><option value="admin">Admin</option><option value="user">User</option></select>
    <div class="modal-footer">
      <button type="button" class="btn btn-ghost" onclick="closeModal('editModal')">Cancel</button>
      <button type="submit" class="btn btn-dark">Save Changes</button>
    </div>
  </form>
</div>
</div>

<div class="modal" id="addModal">
<div class="modal-box">
  <h3>Add New User</h3>
  <form method="post" action="add_user.php">
    <label>Username</label><input type="text" name="username" placeholder="e.g. john_doe" required>
    <label>Email</label><input type="email" name="email" placeholder="e.g. john@example.com" required>
    <label>Password</label><input type="password" name="password" placeholder="Min. 8 characters" required>
    <label>Role</label>
    <select name="role" id="addRole" onchange="updateRoleNote(this)">
      <option value="user" selected>User</option>
      <option value="admin">Admin</option>
    </select>
    <p id="roleNote" style="font-size:11px;color:var(--muted);margin-top:-8px;margin-bottom:8px">A unique 4-digit UID will be assigned automatically.</p>
    <div class="modal-footer">
      <button type="button" class="btn btn-ghost" onclick="closeModal('addModal')">Cancel</button>
      <button type="submit" class="btn btn-dark">Create User</button>
    </div>
  </form>
</div>
</div>

<div class="modal" id="deleteModal">
<div class="modal-box" style="width:340px">
  <h3>Delete User</h3>
  <p style="color:var(--muted);font-size:13.5px;margin-bottom:20px">Are you sure you want to delete <strong id="deleteUsername"></strong>? This cannot be undone.</p>
  <div class="modal-footer">
    <button type="button" class="btn btn-ghost" onclick="closeModal('deleteModal')">Cancel</button>
    <a id="deleteLink" href="#" class="btn btn-danger">Delete</a>
  </div>
</div>
</div>

<div class="modal" id="logoutAllModal">
<div class="modal-box" style="width:400px">
  <h3 id="logoutAllTitle">Logout All Users</h3>
  <p style="color:var(--muted);font-size:13.5px;margin-bottom:6px" id="logoutAllDesc">
    This will force-disconnect all currently online users. They will be kicked from the game immediately.
  </p>
  <p style="font-size:13px;margin-bottom:10px">
    Type <strong id="logoutAllPhrase" style="font-family:'DM Mono',monospace;color:var(--red)"></strong> to confirm:
  </p>
  <input type="text" id="logoutAllInput" class="field-input"
    placeholder="Type the phrase above…"
    oninput="checkLogoutPhrase()"
    style="margin-bottom:4px;font-family:'DM Mono',monospace;font-size:13px">
  <p id="logoutAllMatch" style="font-size:11px;min-height:16px;margin-bottom:14px;color:var(--muted)"></p>
  <div class="modal-footer">
    <button type="button" class="btn btn-ghost" onclick="closeModal('logoutAllModal')">Cancel</button>
    <button id="logoutAllConfirmBtn" class="btn btn-danger" disabled onclick="executeLogoutAll()">
      <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Confirm Logout
    </button>
  </div>
</div>
</div>

<script>
function toggleSidebar(){
  document.getElementById('sidebar').classList.toggle('collapsed');
}
function toggleGroup(id) {
  const el = document.getElementById(id);
  el.classList.toggle('open');
}
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
async function post(url, data) {
  const res = await fetch(url, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data)});
  return res.json();
}
const si=document.getElementById("searchInput");
if(si)si.addEventListener("keyup",function(){
  const f=this.value.toLowerCase();
  document.querySelectorAll("#userTable tbody tr").forEach(r=>{r.style.display=r.innerText.toLowerCase().includes(f)?"":"none";});
});
function openEditModal(id,username,email,role){
  document.getElementById("editId").value=id;
  document.getElementById("editUsername").value=username;
  document.getElementById("editEmail").value=email;
  document.getElementById("editRole").value=role;
  document.getElementById("editModal").classList.add("open");
}
function openAddModal(){document.getElementById("addModal").classList.add("open")}
function confirmDelete(id,username){
  document.getElementById("deleteUsername").textContent=username;
  document.getElementById("deleteLink").href=`delete_user.php?id=${id}`;
  document.getElementById("deleteModal").classList.add("open");
}
document.querySelectorAll(".modal").forEach(m=>{
  m.addEventListener("click",e=>{if(e.target===m)m.classList.remove("open")});
});
function updateRoleNote(sel){
  const note=document.getElementById('roleNote');
  note.textContent=sel.value==='admin'
    ?'Admin UID will be 0001–0005. Max 5 admins allowed.'
    :'A unique 4-digit UID will be assigned automatically.';
}
function fillSql(s){document.querySelector('[name=sql_command]').value=s;}
async function resetPassword(btn){
  const userId=btn.dataset.id,username=btn.dataset.username;
  if(!confirm(`Reset password for "${username}"?`))return;
  btn.disabled=true;btn.classList.add("resetting");const orig=btn.textContent;btn.textContent="…";
  try{
    const res=await fetch("../api/reset_password.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({user_id:parseInt(userId)})});
    const raw=await res.text();let data;
    try{data=JSON.parse(raw)}catch(_){alert("Server error.");return}
    if(data.status==="success"){addLogRow(data.username,data.code,data.reset_at)}
    else{alert("Error: "+(data.message||"Failed."))}
  }catch(err){alert("Network error.")}
  finally{btn.disabled=false;btn.classList.remove("resetting");btn.textContent=orig}
}
function addLogRow(username,code,resetAt){
  const tbody=document.getElementById("resetLogBody");
  const empty=document.getElementById("emptyRow");if(empty)empty.remove();
  const tr=document.createElement("tr");tr.className="new-row";
  tr.innerHTML=`<td style="font-weight:500">${esc(username)}</td><td><span class="code-badge">${esc(code)}</span></td><td style="color:var(--muted)">${esc(resetAt)}</td>`;
  tbody.insertBefore(tr,tbody.firstChild);
  tr.scrollIntoView({behavior:"smooth",block:"center"});
}
async function resetAdminPassword(btn){
  const userId=btn.dataset.id,username=btn.dataset.username;
  if(!confirm(`Reset password for admin "${username}"?\n\nThis will generate a temporary code they must use to log in.`))return;
  btn.disabled=true;btn.classList.add("resetting");const orig=btn.textContent;btn.textContent="…";
  try{
    const res=await fetch("../api/reset_password.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({user_id:parseInt(userId)})});
    const raw=await res.text();let data;
    try{data=JSON.parse(raw)}catch(_){alert("Server error.");return}
    if(data.status==="success"){addAdminLogRow(data.username,data.code,data.reset_at);showToast(`✓ Password reset for ${data.username}`);}
    else{alert("Error: "+(data.message||"Failed."));}
  }catch(err){alert("Network error.");}
  finally{btn.disabled=false;btn.classList.remove("resetting");btn.textContent=orig;}
}
function addAdminLogRow(username,code,resetAt){
  const tbody=document.getElementById("adminResetLogBody");
  if(!tbody)return;
  const empty=document.getElementById("adminEmptyRow");if(empty)empty.remove();
  const tr=document.createElement("tr");tr.className="new-row";
  tr.innerHTML=`<td style="font-weight:500">${esc(username)}</td><td><span class="code-badge">${esc(code)}</span></td><td style="color:var(--muted)">${esc(resetAt)}</td>`;
  tbody.insertBefore(tr,tbody.firstChild);
  tr.scrollIntoView({behavior:"smooth",block:"center"});
}
function esc(s){return String(s).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;")}

(async()=>{
  try{const r=await fetch("<?= rtrim(API_BASE_URL,'/') ?>/ping");const d=await r.json();updateStats(d);}catch(_){}
})();
function updateStats(data){
  if(data.admins!==undefined)document.getElementById("liveAdmins").textContent=data.admins;
  if(data.users!==undefined)document.getElementById("liveUsers").textContent=data.users;
}
const _wsUrl = "<?= rtrim(API_BASE_URL,'/') ?>".replace(/^http/, 'ws') + '/ws';
const ws=new WebSocket(_wsUrl);
ws.onmessage=function(e){
  const data=JSON.parse(e.data);updateStats(data);
  const online=data.online_users||[];
  document.querySelectorAll(".user-status").forEach(dot=>{
    const u=dot.dataset.user;
    dot.classList.toggle("online",online.includes(u));
    dot.classList.toggle("offline",!online.includes(u));
  });
};

async function forceLogoutUser(userId, username) {
  if (!confirm(`Force logout "${username}"?\n\nThis will immediately disconnect them from the game.`)) return;
  const btn = event.currentTarget;
  const origColor = btn.style.color;
  btn.disabled = true;
  btn.style.opacity = '0.4';
  try {
    const r = await post('force_logout.php', { mode: 'single', user_id: userId });
    if (r.status === 'success') {
      const dot = document.querySelector(`.user-status[data-user="${esc(username)}"]`);
      if (dot) { dot.classList.remove('online'); dot.classList.add('offline'); }
      btn.style.color = '#22c55e';
      btn.style.opacity = '1';
      setTimeout(() => { btn.style.color = origColor; btn.disabled = false; }, 2000);
      showToast(`✓ ${r.message}`);
    } else {
      alert('Error: ' + r.message);
      btn.disabled = false;
      btn.style.opacity = '';
    }
  } catch(e) {
    alert('Network error.');
    btn.disabled = false;
    btn.style.opacity = '';
  }
}

let _logoutAllMode = '';
const LOGOUT_PHRASES = {
  users:  'Confirm Logout All Users',
  admins: 'Confirm Logout All Admins',
};

function confirmLogoutAll(mode) {
  _logoutAllMode = mode;
  const phrase = LOGOUT_PHRASES[mode];
  document.getElementById('logoutAllTitle').textContent =
    mode === 'users' ? 'Logout All Users' : 'Logout All Admins';
  document.getElementById('logoutAllDesc').textContent =
    mode === 'users'
      ? 'This will force-disconnect ALL currently online users from the game immediately.'
      : 'This will force-disconnect ALL currently online admins (except you).';
  document.getElementById('logoutAllPhrase').textContent = phrase;
  document.getElementById('logoutAllInput').value = '';
  document.getElementById('logoutAllMatch').textContent = '';
  document.getElementById('logoutAllConfirmBtn').disabled = true;
  openModal('logoutAllModal');
  setTimeout(() => document.getElementById('logoutAllInput').focus(), 120);
}

function checkLogoutPhrase() {
  const phrase   = LOGOUT_PHRASES[_logoutAllMode] || '';
  const typed    = document.getElementById('logoutAllInput').value;
  const matchEl  = document.getElementById('logoutAllMatch');
  const btn      = document.getElementById('logoutAllConfirmBtn');
  const matches  = typed === phrase;
  btn.disabled   = !matches;
  if (!typed) {
    matchEl.textContent = '';
    matchEl.style.color = '';
  } else if (matches) {
    matchEl.textContent = '✓ Phrase matches';
    matchEl.style.color = 'var(--green)';
  } else {
    matchEl.textContent = '✗ Does not match';
    matchEl.style.color = 'var(--red)';
  }
}

async function executeLogoutAll() {
  const mode = _logoutAllMode;
  const btn  = document.getElementById('logoutAllConfirmBtn');
  btn.disabled = true;
  btn.textContent = 'Working…';
  try {
    const r = await post('force_logout.php', {
      mode: mode === 'users' ? 'all_users' : 'all_admins'
    });
    closeModal('logoutAllModal');
    if (r.status === 'success') {
      showToast(`✓ ${r.message}`);
    } else {
      alert('Error: ' + r.message);
    }
  } catch(e) {
    alert('Network error: ' + e.message);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> Confirm Logout';
  }
}

function showToast(msg) {
  let t = document.getElementById('_adminToast');
  if (!t) {
    t = document.createElement('div');
    t.id = '_adminToast';
    t.style.cssText = `
      position:fixed;bottom:24px;right:24px;z-index:9999;
      background:#1a1d27;border:1px solid #2e3347;border-radius:10px;
      padding:11px 18px;font-size:13px;font-weight:500;color:#22c55e;
      box-shadow:0 8px 24px rgba(0,0,0,.4);
      opacity:0;transform:translateY(10px);
      transition:all .22s ease;pointer-events:none;
    `;
    document.body.appendChild(t);
  }
  t.textContent = msg;
  t.style.opacity = '1';
  t.style.transform = 'translateY(0)';
  clearTimeout(t._timer);
  t._timer = setTimeout(() => {
    t.style.opacity = '0';
    t.style.transform = 'translateY(10px)';
  }, 3000);
}
</script>
</html>