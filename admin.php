<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
startSession();
header('Cache-Control: no-store');
$error = '';
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrf();
        $action = $_POST['action'] ?? '';
        if ($action === 'login') {
            $ip = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
            locked('login', function () use ($ip) {
                $path = ROOT . '/storage/login-' . $ip . '.json';
                $attempts = readJson($path, ['count' => 0, 'start' => time()]);
                if (time() - $attempts['start'] > 900) {
                    $attempts = ['count' => 0, 'start' => time()];
                }
                if ($attempts['count'] >= 8) {
                    throw new RuntimeException('Pokušajte ponovo za 15 minuta.');
                }
                $attempts['count']++;
                writeJson($path, $attempts);
                if (
                    !env('ADMIN_PASSWORD_HASH') ||
                    !password_verify($_POST['password'] ?? '', env('ADMIN_PASSWORD_HASH'))
                ) {
                    throw new RuntimeException('Neispravna lozinka.');
                }
                writeJson($path, ['count' => 0, 'start' => time()]);
                session_regenerate_id(true);
                $_SESSION['admin'] = true;
            });
        } elseif (empty($_SESSION['admin'])) {
            throw new RuntimeException('Prijavite se.');
        } elseif ($action === 'logout') {
            $_SESSION = [];
            session_destroy();
            header('Location: admin.php');
            exit();
        } elseif ($action === 'save') {
            $config = settings();
            $interval = filter_var($_POST['interval'] ?? '', FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1, 'max_range' => 1440],
            ]);
            if (!$interval) {
                throw new RuntimeException('Interval mora biti 1–1440 minuta.');
            }
            $date = $_POST['electionDate'] ?? '';
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$parsed || $parsed->format('Y-m-d') !== $date) {
                throw new RuntimeException('Neispravan datum.');
            }
            $config['interval'] = $interval;
            $config['electionDate'] = $date;
            $config['mode'] = ($_POST['mode'] ?? '') === 'live' ? 'live' : 'demo';
            foreach (SOURCES as $id => $name) {
                $urls = array_values(
                    array_filter(array_map('trim', explode("\n", $_POST['urls'][$id] ?? ''))),
                );
                if (count($urls) > 5) {
                    throw new RuntimeException('Najviše pet URL-ova po izvoru.');
                }
                foreach ($urls as $url) {
                    publicUrl($url);
                }
                $config['sources'][$id] = $urls;
            }
            $lists = [];
            $ids = [];
            foreach ($_POST['list'] ?? [] as $row) {
                if (trim($row['id'] ?? '') === '' && trim($row['name'] ?? '') === '') {
                    continue;
                }
                $id = trim($row['id']);
                $name = trim($row['name']);
                if (
                    !preg_match('/^[a-z0-9_-]{1,40}$/', $id) ||
                    isset($ids[$id]) ||
                    !$name ||
                    mb_strlen($name) > 160 ||
                    !preg_match('/^#[0-9a-fA-F]{6}$/', $row['color'] ?? '')
                ) {
                    throw new RuntimeException('Proverite jedinstveni ID, naziv i boju liste.');
                }
                $n = filter_var($row['candidates'] ?? '', FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1, 'max_range' => 250],
                ]);
                if (!$n) {
                    throw new RuntimeException('Broj kandidata mora biti 1–250.');
                }
                $ids[$id] = true;
                $lists[] = [
                    'id' => $id,
                    'name' => $name,
                    'color' => $row['color'],
                    'minority' => isset($row['minority']),
                    'candidates' => $n,
                ];
            }
            if (!$lists || count($lists) > 50) {
                throw new RuntimeException('Unesite između 1 i 50 lista.');
            }
            $config['lists'] = $lists;
            locked('settings', fn() => writeJson(ROOT . '/storage/settings.json', $config));
            $notice =
                'Podešavanja su sačuvana. Novi registar se primenjuje pri sledećem preuzimanju; istorija čuva stari.';
        } elseif ($action === 'demo') {
            generateDemo(settings());
            $notice = 'Novi test rezultati su sačuvani u demo istoriji.';
        } elseif ($action === 'refresh') {
            set_time_limit(1800);
            collect(true);
            $notice = 'Provera je završena. Status svakog izvora nalazi se ispod.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
$config = settings();
$admin = !empty($_SESSION['admin']);
$editableLists = array_merge($config['lists'], [
    ['id' => '', 'name' => '', 'color' => '#64748b', 'minority' => false, 'candidates' => 250],
]);
$adminActions = [
    'refresh' => 'Proveri izvore sada',
    'demo' => 'Generiši nove test podatke',
    'logout' => 'Odjavi se',
];
$status = readJson(ROOT . '/storage/status.json');

require __DIR__ . '/app/admin-view.php';
