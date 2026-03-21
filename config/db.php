<?php
require_once __DIR__ . '/env.php';   // load .env before anything else

$dbFile = __DIR__ . "/../database/database.db";

if (!file_exists(dirname($dbFile))) {
    mkdir(dirname($dbFile), 0777, true);
}

$conn = new PDO("sqlite:$dbFile");
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$conn->exec("
CREATE TABLE IF NOT EXISTS users (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    username       TEXT UNIQUE NOT NULL,
    email          TEXT UNIQUE NOT NULL,
    password       TEXT NOT NULL,
    role           TEXT NOT NULL DEFAULT 'user',
    uid            TEXT UNIQUE,
    is_online      INTEGER DEFAULT 0,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME DEFAULT CURRENT_TIMESTAMP
)
");

$conn->exec("
CREATE TABLE IF NOT EXISTS refresh_tokens (
    id      INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    token   TEXT NOT NULL,
    expiry  DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)
");

$conn->exec("
CREATE TABLE IF NOT EXISTS user_stats (
    id       INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id  INTEGER NOT NULL UNIQUE,
    coins    INTEGER NOT NULL DEFAULT 0,
    shards   INTEGER NOT NULL DEFAULT 0,
    level    INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)
");

$conn->exec("
CREATE TABLE IF NOT EXISTS admin_permissions (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id      INTEGER NOT NULL UNIQUE,
    can_userdata INTEGER NOT NULL DEFAULT 0,
    can_activity INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)
");

$conn->exec("
CREATE TABLE IF NOT EXISTS password_resets (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL UNIQUE,
    otp_hash   TEXT NOT NULL,
    expires_at DATETIME NOT NULL,
    used       INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)
");

$conn->exec("
CREATE TABLE IF NOT EXISTS password_reset_log (
    id       INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id  INTEGER NOT NULL,
    username TEXT NOT NULL,
    code     TEXT NOT NULL,
    reset_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)
");

$conn->exec("
CREATE TABLE IF NOT EXISTS activity_log (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    admin     TEXT NOT NULL,
    action    TEXT NOT NULL,
    target    TEXT,
    detail    TEXT,
    logged_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)
");

// ── Migrations: safely add new columns to existing DB ──
// ── Item Groups ──
$conn->exec("
CREATE TABLE IF NOT EXISTS item_groups (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    title      TEXT NOT NULL,
    image_url  TEXT NOT NULL DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)
");

// ── Item Variants ──
$conn->exec("
CREATE TABLE IF NOT EXISTS inventory_item_variants (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id    INTEGER NOT NULL,
    label      TEXT NOT NULL,
    max_slots  INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE
)
");

// ── Variant Sub-options ──
$conn->exec("
CREATE TABLE IF NOT EXISTS variant_suboptions (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    variant_id INTEGER NOT NULL,
    label      TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (variant_id) REFERENCES inventory_item_variants(id) ON DELETE CASCADE
)
");

// ── Orders (full purchase workflow with receipt, admin, screenshot) ──
$conn->exec("
CREATE TABLE IF NOT EXISTS orders (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    receipt_id   TEXT UNIQUE NOT NULL,
    user_id      INTEGER NOT NULL,
    admin_id     INTEGER NOT NULL,
    item_id      INTEGER NOT NULL,
    variant_id   INTEGER NOT NULL,
    suboption    TEXT NOT NULL DEFAULT '',
    item_title   TEXT NOT NULL DEFAULT '',
    variant_label TEXT NOT NULL DEFAULT '',
    price        DECIMAL(10,2) NOT NULL DEFAULT 0,
    screenshot   TEXT NOT NULL DEFAULT '',
    status       TEXT NOT NULL DEFAULT 'reviewing',
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)    REFERENCES users(id),
    FOREIGN KEY (admin_id)   REFERENCES users(id),
    FOREIGN KEY (item_id)    REFERENCES inventory_items(id),
    FOREIGN KEY (variant_id) REFERENCES inventory_item_variants(id)
)
");

// ── Migration: add max_slots + price + slots_used to existing variant rows ──
try { $conn->exec("ALTER TABLE inventory_item_variants ADD COLUMN max_slots INTEGER NOT NULL DEFAULT 1"); } catch(Exception $e) {}
try { $conn->exec("ALTER TABLE inventory_item_variants ADD COLUMN price DECIMAL(10,2) NOT NULL DEFAULT 0"); } catch(Exception $e) {}
try { $conn->exec("ALTER TABLE inventory_item_variants ADD COLUMN slots_used INTEGER NOT NULL DEFAULT 0"); } catch(Exception $e) {}

// ── Purchases ──
$conn->exec("
CREATE TABLE IF NOT EXISTS purchases (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL,
    item_id     INTEGER NOT NULL,
    variant_id  INTEGER NOT NULL,
    suboption   TEXT NOT NULL DEFAULT '',
    price_paid  DECIMAL(10,2) NOT NULL DEFAULT 0,
    status      TEXT NOT NULL DEFAULT 'active',
    purchased_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)   REFERENCES users(id),
    FOREIGN KEY (item_id)   REFERENCES inventory_items(id),
    FOREIGN KEY (variant_id) REFERENCES inventory_item_variants(id)
)
");

// ── Legacy Item Variants (kept for reference, replaced above) ──
$conn->exec("
CREATE TABLE IF NOT EXISTS inventory_item_variants_LEGACY (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id    INTEGER NOT NULL,
    label      TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE
)
");

// ── Inventory Items ──
$conn->exec("
CREATE TABLE IF NOT EXISTS inventory_items (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    group_id     INTEGER NOT NULL,
    title        TEXT NOT NULL,
    description1 TEXT NOT NULL DEFAULT '',
    description2 TEXT NOT NULL DEFAULT '',
    stock        INTEGER NOT NULL DEFAULT 0,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES item_groups(id) ON DELETE CASCADE
)
");

$migrations = [
    // Original user migrations
    "ALTER TABLE users ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP",
    "ALTER TABLE users ADD COLUMN last_activity DATETIME DEFAULT CURRENT_TIMESTAMP",
    "ALTER TABLE users ADD COLUMN uid TEXT",
    "ALTER TABLE admin_permissions ADD COLUMN can_settings INTEGER NOT NULL DEFAULT 0",
    "ALTER TABLE admin_permissions ADD COLUMN can_sales INTEGER NOT NULL DEFAULT 0",
    "ALTER TABLE admin_permissions ADD COLUMN can_sales_catered INTEGER NOT NULL DEFAULT 0",
    "ALTER TABLE admin_permissions ADD COLUMN can_sales_denied INTEGER NOT NULL DEFAULT 0",
    "ALTER TABLE admin_permissions ADD COLUMN can_sales_tickets INTEGER NOT NULL DEFAULT 0",
    "ALTER TABLE admin_permissions ADD COLUMN can_sales_orders INTEGER NOT NULL DEFAULT 0",
];
foreach ($migrations as $sql) {
    try { $conn->exec($sql); } catch (Exception $e) {}
}

// ── Generate UIDs for existing users that don't have one ──
$missing = $conn->query("SELECT id, role FROM users WHERE uid IS NULL")->fetchAll(PDO::FETCH_ASSOC);
foreach ($missing as $u) {
    if ($u['role'] === 'admin') {
        $uid = '0001';
    } else {
        // Generate unique 4-digit code not already used
        do {
            $uid = str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
            $exists = $conn->prepare("SELECT COUNT(*) FROM users WHERE uid = :uid");
            $exists->execute([':uid' => $uid]);
        } while ($exists->fetchColumn() > 0);
    }
    $conn->prepare("UPDATE users SET uid = :uid WHERE id = :id")->execute([':uid' => $uid, ':id' => $u['id']]);
}

// ── Ensure user_stats rows exist for all users ──
$conn->exec("
    INSERT OR IGNORE INTO user_stats (user_id)
    SELECT id FROM users WHERE role = 'user'
");

// ── Superuser: superadmin ──
$stmt = $conn->query("SELECT COUNT(*) as c FROM users WHERE username='superadmin'");
if ($stmt->fetch(PDO::FETCH_ASSOC)['c'] == 0) {
    $password = password_hash(getenv('SUPERADMIN_PASSWORD') ?: 'SuperAdmin!123', PASSWORD_BCRYPT);
    $conn->prepare("
        INSERT INTO users (username, email, password, role, uid)
        VALUES ('superadmin', 'superadmin@system.local', :pw, 'superadmin', '0000')
    ")->execute([':pw' => $password]);
}

// ── Default admin (if no other admin exists) ──
$stmt = $conn->query("SELECT COUNT(*) as c FROM users WHERE username='admin'");
if ($stmt->fetch(PDO::FETCH_ASSOC)['c'] == 0) {
    $password = password_hash(getenv('ADMIN_PASSWORD') ?: 'ChangeMe!', PASSWORD_BCRYPT);
    $conn->prepare("
        INSERT INTO users (username, email, password, role, uid)
        VALUES ('admin', 'admin@example.com', :pw, 'admin', '0001')
    ")->execute([':pw' => $password]);
}
?>