<?php
require_once '/var/www/includes/config.php';
require_login();

$page_title = 'Settings';
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // In production, persist these to a DB or .env
    $saved = true;
}

require '/var/www/includes/layout.php';
?>

<?php if ($saved): ?>
<div style="background:rgba(52,211,153,.1);border:1px solid rgba(52,211,153,.25);color:var(--success);border-radius:9px;padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;gap:8px;font-size:13px">
  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
  Settings saved successfully.
</div>
<?php endif ?>

<div class="grid-2" style="align-items:start">

  <!-- General Settings -->
  <div style="display:flex;flex-direction:column;gap:16px">
    <div class="card card-body">
      <div class="card-title">General</div>
      <div class="card-sub" style="margin-bottom:20px">Basic application settings</div>
      <form method="post">
        <div class="form-group">
          <label>Application Name</label>
          <input type="text" name="app_name" value="<?= APP_NAME ?>">
        </div>
        <div class="form-group">
          <label>Admin Email</label>
          <input type="email" name="admin_email" placeholder="admin@yoursite.com">
        </div>
        <div class="form-group">
          <label>Timezone</label>
          <select name="timezone">
            <option>Asia/Manila</option>
            <option>UTC</option>
            <option>America/New_York</option>
            <option>Europe/London</option>
            <option>Asia/Tokyo</option>
          </select>
        </div>
        <div class="form-group">
          <label>Site Description</label>
          <textarea name="description" placeholder="Short description of your app…"></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Save General</button>
      </form>
    </div>

    <!-- Change Password -->
    <div class="card card-body">
      <div class="card-title">Change Password</div>
      <div class="card-sub" style="margin-bottom:20px">Update admin credentials</div>
      <form method="post">
        <div class="form-group">
          <label>Current Password</label>
          <input type="password" name="current_password" placeholder="••••••••">
        </div>
        <div class="form-group">
          <label>New Password</label>
          <input type="password" name="new_password" placeholder="••••••••">
        </div>
        <div class="form-group">
          <label>Confirm New Password</label>
          <input type="password" name="confirm_password" placeholder="••••••••">
        </div>
        <button type="submit" class="btn btn-primary">Update Password</button>
      </form>
    </div>
  </div>

  <!-- Toggles & Info -->
  <div style="display:flex;flex-direction:column;gap:16px">
    <div class="card card-body">
      <div class="card-title">Notifications</div>
      <div class="card-sub" style="margin-bottom:4px">Control email & system alerts</div>
      <div>
        <div class="toggle-row">
          <div class="toggle-info">
            <div class="t-label">Email Notifications</div>
            <div class="t-desc">Receive alerts via email</div>
          </div>
          <label class="toggle">
            <input type="checkbox" checked>
            <span class="toggle-track"></span>
          </label>
        </div>
        <div class="toggle-row">
          <div class="toggle-info">
            <div class="t-label">New User Alerts</div>
            <div class="t-desc">Notify when a user registers</div>
          </div>
          <label class="toggle">
            <input type="checkbox" checked>
            <span class="toggle-track"></span>
          </label>
        </div>
        <div class="toggle-row">
          <div class="toggle-info">
            <div class="t-label">Login Alerts</div>
            <div class="t-desc">Alert on new admin login</div>
          </div>
          <label class="toggle">
            <input type="checkbox">
            <span class="toggle-track"></span>
          </label>
        </div>
        <div class="toggle-row">
          <div class="toggle-info">
            <div class="t-label">Weekly Reports</div>
            <div class="t-desc">Receive weekly digest</div>
          </div>
          <label class="toggle">
            <input type="checkbox" checked>
            <span class="toggle-track"></span>
          </label>
        </div>
      </div>
    </div>

    <div class="card card-body">
      <div class="card-title">Security</div>
      <div class="card-sub" style="margin-bottom:4px">Access & session controls</div>
      <div>
        <div class="toggle-row">
          <div class="toggle-info">
            <div class="t-label">Two-Factor Auth</div>
            <div class="t-desc">Require 2FA for admin login</div>
          </div>
          <label class="toggle">
            <input type="checkbox">
            <span class="toggle-track"></span>
          </label>
        </div>
        <div class="toggle-row">
          <div class="toggle-info">
            <div class="t-label">Session Timeout</div>
            <div class="t-desc">Auto-logout after 30 minutes</div>
          </div>
          <label class="toggle">
            <input type="checkbox" checked>
            <span class="toggle-track"></span>
          </label>
        </div>
        <div class="toggle-row">
          <div class="toggle-info">
            <div class="t-label">Maintenance Mode</div>
            <div class="t-desc">Lock site for public visitors</div>
          </div>
          <label class="toggle">
            <input type="checkbox">
            <span class="toggle-track"></span>
          </label>
        </div>
      </div>
    </div>

    <div class="card card-body">
      <div class="card-title">Danger Zone</div>
      <div class="card-sub" style="margin-bottom:16px">Irreversible actions</div>
      <div style="display:flex;flex-direction:column;gap:10px">
        <button class="btn btn-danger" style="justify-content:flex-start" onclick="alert('Connect a database to implement data export.')">
          Export All Data
        </button>
        <button class="btn btn-danger" style="justify-content:flex-start" onclick="if(confirm('This will clear all session data. Continue?')) alert('Session data cleared (demo).')">
          Clear All Sessions
        </button>
      </div>
    </div>
  </div>
</div>

<?php require '/var/www/includes/layout_end.php'; ?>
