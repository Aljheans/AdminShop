<?php
require_once '/var/www/includes/config.php';
require_login();

$page_title = 'Users';
$users = get_users();

// Simple search filter
$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $users = array_filter($users, function($u) use ($search) {
        return stripos($u['name'], $search) !== false
            || stripos($u['email'], $search) !== false
            || stripos($u['role'], $search) !== false;
    });
}

// Role filter
$role_filter = $_GET['role'] ?? '';
if ($role_filter !== '') {
    $users = array_filter($users, fn($u) => $u['role'] === $role_filter);
}

require '/var/www/includes/layout.php';
?>

<div class="flex-between" style="margin-bottom:20px">
  <div>
    <div style="font-size:13px;color:var(--muted)"><?= count($users) ?> user<?= count($users)!==1?'s':'' ?> found</div>
  </div>
  <div class="flex-gap">
    <!-- Search -->
    <form method="get" style="display:flex;gap:8px;align-items:center">
      <?php if ($role_filter): ?>
        <input type="hidden" name="role" value="<?= htmlspecialchars($role_filter) ?>">
      <?php endif ?>
      <input type="text" name="q" placeholder="Search users…"
             value="<?= htmlspecialchars($search) ?>"
             style="width:220px;padding:8px 14px;font-size:13px">
      <button type="submit" class="btn btn-ghost" style="padding:8px 14px">Search</button>
      <?php if ($search || $role_filter): ?>
        <a href="/users.php" class="btn btn-ghost" style="padding:8px 12px">✕ Clear</a>
      <?php endif ?>
    </form>
  </div>
</div>

<!-- Role filter tabs -->
<div class="flex-gap" style="margin-bottom:16px;gap:6px">
  <?php
    $roles = [''=>'All','Admin'=>'Admin','Editor'=>'Editor','Viewer'=>'Viewer'];
    foreach ($roles as $val => $label):
      $active = $role_filter === $val ? 'btn-primary' : 'btn-ghost';
      $href = '/users.php?' . http_build_query(array_filter(['role'=>$val,'q'=>$search]));
  ?>
  <a href="<?= $href ?>" class="btn <?= $active ?>" style="padding:6px 14px;font-size:12px"><?= $label ?></a>
  <?php endforeach ?>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>User</th>
          <th>Role</th>
          <th>Status</th>
          <th>Joined</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($users)): ?>
        <tr><td colspan="6" class="empty">No users match your search.</td></tr>
        <?php else: ?>
        <?php foreach ($users as $u): ?>
        <tr>
          <td style="color:var(--muted);font-size:12px"><?= $u['id'] ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-family:var(--fh);font-size:12px;font-weight:700;color:#fff;flex-shrink:0">
                <?= strtoupper(substr($u['name'],0,1)) ?>
              </div>
              <div>
                <div style="font-weight:500"><?= htmlspecialchars($u['name']) ?></div>
                <div style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($u['email']) ?></div>
              </div>
            </div>
          </td>
          <td>
            <?php $rc = match($u['role']) { 'Admin'=>'badge-accent', 'Editor'=>'badge-warning', default=>'badge-success' }; ?>
            <span class="badge <?= $rc ?>"><?= $u['role'] ?></span>
          </td>
          <td>
            <span class="badge <?= $u['status']==='Active' ? 'badge-success' : 'badge-danger' ?>">
              <?= $u['status'] ?>
            </span>
          </td>
          <td style="color:var(--muted);font-size:13px"><?= $u['joined'] ?></td>
          <td>
            <div class="flex-gap" style="gap:6px">
              <button class="btn btn-ghost" style="padding:5px 10px;font-size:12px"
                onclick="alert('Edit user: <?= htmlspecialchars($u['name']) ?>\n(Connect a database to implement full CRUD)')">
                Edit
              </button>
              <button class="btn btn-danger" style="padding:5px 10px;font-size:12px"
                onclick="if(confirm('Delete <?= htmlspecialchars(addslashes($u['name'])) ?>?')) alert('Connect a database to persist deletions.')">
                Delete
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach ?>
        <?php endif ?>
      </tbody>
    </table>
  </div>
</div>

<?php require '/var/www/includes/layout_end.php'; ?>
