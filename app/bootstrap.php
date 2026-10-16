<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
date_default_timezone_set('Europe/Belgrade');

foreach (is_file(ROOT . '/.env') ? file(ROOT . '/.env', FILE_IGNORE_NEW_LINES) : [] as $line) {
    if (!$line || str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
        continue;
    }
    [$key, $value] = explode('=', $line, 2);
    if (getenv(trim($key)) === false) {
        putenv(trim($key) . '=' . trim(trim($value), "\"'"));
    }
}

require_once __DIR__ . '/elections.php';
require_once __DIR__ . '/collector.php';
