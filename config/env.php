<?php
/**
 * config/env.php
 * Loads variables from the nearest .env file into the process environment.
 * Variables already set in the environment (e.g. via Docker / Render) take
 * precedence over values in the file — the file is only a fallback.
 */

function load_env(string $dir = __DIR__): void {
    for ($i = 0; $i < 3; $i++) {
        $file = $dir . '/.env';
        if (file_exists($file)) break;
        $dir  = dirname($dir);
        $file = '';
    }

    if (empty($file) || !file_exists($file)) return;

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!str_contains($line, '=')) continue;

        [$name, $value] = explode('=', $line, 2);
        $name  = trim($name);
        $value = trim($value);

        if (($pos = strpos($value, ' #')) !== false) {
            $value = substr($value, 0, $pos);
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($name) === false) {
            putenv("$name=$value");
            $_ENV[$name]    = $value;
            $_SERVER[$name] = $value;
        }
    }
}

load_env(__DIR__);