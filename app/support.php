<?php

declare(strict_types=1);

const ROOT = __DIR__ . '/..';
const SOURCES = [
    'rik' => 'Republička izborna komisija',
    'studenti' => 'Studentska lista',
    'sns' => 'Srpska napredna stranka',
];
function env(string $key, string $default = ''): string
{
    return getenv($key) === false ? $default : getenv($key);
}
function readJson(string $path, mixed $default = []): mixed
{
    return is_file($path)
        ? json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)
        : $default;
}
function writeJson(string $path, mixed $data): void
{
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0700, true);
    }
    $temp = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
    if (
        file_put_contents(
            $temp,
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
            LOCK_EX,
        ) === false ||
        !rename($temp, $path)
    ) {
        throw new RuntimeException('Neuspešan upis podataka.');
    }
}
function locked(string $name, callable $work): mixed
{
    $lock = fopen(ROOT . '/storage/' . $name . '.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) {
        throw new RuntimeException('Skladište nije dostupno.');
    }
    try {
        return $work();
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
function defaultLists(): array
{
    return [
        [
            'id' => 'studenti',
            'name' => 'Studentska lista',
            'color' => '#299b79',
            'minority' => false,
            'candidates' => 250,
        ],
        [
            'id' => 'sns',
            'name' => 'SNS – koalicija',
            'color' => '#446be0',
            'minority' => false,
            'candidates' => 250,
        ],
        [
            'id' => 'opozicija',
            'name' => 'Opoziciona lista A',
            'color' => '#b18ad4',
            'minority' => false,
            'candidates' => 250,
        ],
        [
            'id' => 'koalicija',
            'name' => 'Koalicija B',
            'color' => '#eda557',
            'minority' => false,
            'candidates' => 250,
        ],
        [
            'id' => 'manjina',
            'name' => 'Manjinska lista (primer)',
            'color' => '#d8738c',
            'minority' => true,
            'candidates' => 30,
        ],
        [
            'id' => 'ostali',
            'name' => 'Lista C',
            'color' => '#8c99aa',
            'minority' => false,
            'candidates' => 250,
        ],
    ];
}
function settings(): array
{
    return readJson(ROOT . '/storage/settings.json', [
        'interval' => 10,
        'electionDate' => '2026-10-25',
        'mode' => 'demo',
        'sources' => ['rik' => [], 'studenti' => [], 'sns' => []],
        'lists' => defaultLists(),
    ]);
}
function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
function startSession(): void
{
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Strict',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        throw new RuntimeException('Sesija je istekla. Osvežite stranicu.');
    }
}
