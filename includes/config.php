<?php
define('ADMIN_USERNAME', getenv('ADMIN_USERNAME') ?: 'admin');
define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD') ?: 'admin123');
define('APP_NAME', 'AdminPanel');

session_name('admin_session');
if (session_status() === PHP_SESSION_NONE) session_start();

function is_logged_in(): bool {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: /index.php');
        exit;
    }
}

function attempt_login(string $username, string $password): bool {
    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['login_time'] = time();
        return true;
    }
    return false;
}

function logout(): void {
    session_destroy();
    header('Location: /index.php');
    exit;
}

function get_users(): array {
    return [
        ['id'=>1,'name'=>'Alice Reyes',   'email'=>'alice@example.com', 'role'=>'Admin', 'status'=>'Active',  'joined'=>'2024-01-15'],
        ['id'=>2,'name'=>'Ben Santos',    'email'=>'ben@example.com',   'role'=>'Editor','status'=>'Active',  'joined'=>'2024-02-20'],
        ['id'=>3,'name'=>'Cara Dela Cruz','email'=>'cara@example.com',  'role'=>'Viewer','status'=>'Inactive','joined'=>'2024-03-05'],
        ['id'=>4,'name'=>'Dan Morales',   'email'=>'dan@example.com',   'role'=>'Editor','status'=>'Active',  'joined'=>'2024-04-11'],
        ['id'=>5,'name'=>'Eva Tan',       'email'=>'eva@example.com',   'role'=>'Viewer','status'=>'Active',  'joined'=>'2024-05-22'],
        ['id'=>6,'name'=>'Felix Lim',     'email'=>'felix@example.com', 'role'=>'Viewer','status'=>'Inactive','joined'=>'2024-06-30'],
        ['id'=>7,'name'=>'Grace Uy',      'email'=>'grace@example.com', 'role'=>'Editor','status'=>'Active',  'joined'=>'2024-07-18'],
        ['id'=>8,'name'=>'Hank Aquino',   'email'=>'hank@example.com',  'role'=>'Viewer','status'=>'Active',  'joined'=>'2024-08-03'],
    ];
}
