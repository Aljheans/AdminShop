<?php
/**
 * config/rate_limit.php
 * Simple IP-based rate limiter backed by a SQLite table.
 *
 * Usage:
 *   require_once __DIR__ . '/../config/rate_limit.php';
 *   rate_limit('login', 5, 60);  // max 5 requests per 60-second window
 */

function rate_limit(string $endpoint = 'default', int $max = 60, int $window = 60): void {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['HTTP_X_REAL_IP']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '0.0.0.0';

    $ip = trim(explode(',', $ip)[0]);

    $dbPath = __DIR__ . '/../database/rate_limits.db';
    if (!file_exists(dirname($dbPath))) {
        mkdir(dirname($dbPath), 0777, true);
    }

    try {
        $db = new PDO('sqlite:' . $dbPath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $db->exec("
            CREATE TABLE IF NOT EXISTS rate_limit_log (
                ip           TEXT    NOT NULL,
                endpoint     TEXT    NOT NULL,
                window_start INTEGER NOT NULL,
                requests     INTEGER NOT NULL DEFAULT 1,
                PRIMARY KEY (ip, endpoint, window_start)
            )
        ");

        $db->exec("DELETE FROM rate_limit_log WHERE window_start < " . (time() - $window * 10));

        $now    = time();
        $bucket = intdiv($now, $window) * $window;

        $stmt = $db->prepare("
            INSERT INTO rate_limit_log (ip, endpoint, window_start, requests)
            VALUES (:ip, :ep, :ws, 1)
            ON CONFLICT(ip, endpoint, window_start)
            DO UPDATE SET requests = requests + 1
        ");
        $stmt->execute([':ip' => $ip, ':ep' => $endpoint, ':ws' => $bucket]);

        $sel = $db->prepare("
            SELECT requests FROM rate_limit_log
            WHERE ip = :ip AND endpoint = :ep AND window_start = :ws
        ");
        $sel->execute([':ip' => $ip, ':ep' => $endpoint, ':ws' => $bucket]);
        $count = (int)($sel->fetchColumn() ?: 0);

        if ($count > $max) {
            $retry = $bucket + $window - $now;
            header('Content-Type: application/json');
            header("Retry-After: $retry");
            http_response_code(429);
            echo json_encode([
                'status'              => 'error',
                'message'             => 'Too many requests. Please slow down.',
                'retry_after_seconds' => $retry,
            ]);
            exit;
        }
    } catch (Exception $e) {
        error_log('Rate limiter error: ' . $e->getMessage());
    }
}