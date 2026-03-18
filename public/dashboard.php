<?php
require_once '/var/www/includes/config.php';
require_login();

$page_title = 'Overview';
$users = get_users();
$total_users  = count($users);
$active_users = count(array_filter($users, fn($u) => $u['status'] === 'Active'));
$admins       = count(array_filter($users, fn($u) => $u['role'] === 'Admin'));
$editors      = count(array_filter($users, fn($u) => $u['role'] === 'Editor'));

$recent = array_slice(array_reverse($users), 0, 5);

require '/var/www/includes/layout.php';
?>

<!-- Stats -->
<div class="grid-4" style="margin-bottom:24px">
  <div class="stat">
    <div class="stat-label">Total Users</div>
    <div class="stat-value"><?= $total_users ?></div>
    <div class="stat-change up">↑ All time</div>
    <div class="stat-icon" style="background:rgba(124,107,255,.12);color:var(--accent)">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    </div>
  </div>
  <div class="stat">
    <div class="stat-label">Active Users</div>
    <div class="stat-value"><?= $active_users ?></div>
    <div class="stat-change up">↑ <?= round($active_users/$total_users*100) ?>% of total</div>
    <div class="stat-icon" style="background:rgba(52,211,153,.12);color:var(--success)">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
    </div>
  </div>
  <div class="stat">
    <div class="stat-label">Editors</div>
    <div class="stat-value"><?= $editors ?></div>
    <div class="stat-change" style="color:var(--muted)">Role group</div>
    <div class="stat-icon" style="background:rgba(251,191,36,.12);color:var(--warning)">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
    </div>
  </div>
  <div class="stat">
    <div class="stat-label">Admins</div>
    <div class="stat-value"><?= $admins ?></div>
    <div class="stat-change" style="color:var(--muted)">Super access</div>
    <div class="stat-icon" style="background:rgba(255,107,157,.12);color:var(--accent2)">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
    </div>
  </div>
</div>

<!-- Recent Users + Quick Actions -->
<div class="grid-2">
  <div class="card">
    <div class="card-header" style="margin-bottom:0">
      <div>
        <div class="card-title">Recent Users</div>
        <div class="card-sub">Latest registered accounts</div>
      </div>
      <a href="/users.php" class="btn btn-ghost" style="font-size:12px;padding:6px 12px">View all</a>
    </div>
    <div class="table-wrap" style="margin-top:12px">
      <table>
        <thead>
          <tr>
            <th>Name</th>
            <th>Role</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent as $u): ?>
          <tr>
            <td>
              <div style="font-weight:500"><?= htmlspecialchars($u['name']) ?></div>
              <div style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($u['email']) ?></div>
            </td>
            <td>
              <?php
                $rc = match($u['role']) { 'Admin'=>'badge-accent', 'Editor'=>'badge-warning', default=>'badge-success' };
              ?>
              <span class="badge <?= $rc ?>"><?= $u['role'] ?></span>
            </td>
            <td>
              <span class="badge <?= $u['status']==='Active' ? 'badge-success' : 'badge-danger' ?>">
                <?= $u['status'] ?>
              </span>
            </td>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  </div>

  <div style="display:flex;flex-direction:column;gap:16px">
    <!-- Quick Actions -->
    <div class="card card-body">
      <div class="card-title">Quick Actions</div>
      <div class="card-sub" style="margin-bottom:18px">Common tasks at a glance</div>
      <div style="display:flex;flex-direction:column;gap:10px">
        <a href="/users.php" class="btn btn-ghost" style="justify-content:flex-start">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          Manage Users
        </a>
        <a href="/settings.php" class="btn btn-ghost" style="justify-content:flex-start">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
          App Settings
        </a>
        <a href="/logout.php" class="btn btn-danger" style="justify-content:flex-start" onclick="return confirm('Sign out?')">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Sign Out
        </a>
      </div>
    </div>

    <!-- System Info -->
    <div class="card card-body">
      <div class="card-title">System Info</div>
      <div class="card-sub" style="margin-bottom:14px">Server environment</div>
      <div style="display:flex;flex-direction:column;gap:10px;font-size:13px">
        <div class="flex-between"><span style="color:var(--muted)">PHP Version</span><span><?= phpversion() ?></span></div>
        <div class="flex-between"><span style="color:var(--muted)">Server</span><span>Apache</span></div>
        <div class="flex-between"><span style="color:var(--muted)">Session</span><span><span class="badge badge-success">Active</span></span></div>
        <div class="flex-between"><span style="color:var(--muted)">Logged in as</span><span><?= htmlspecialchars($_SESSION['username']) ?></span></div>
      </div>
    </div>
  </div>
</div>

<?php require '/var/www/includes/layout_end.php'; ?>
