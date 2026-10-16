<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require __DIR__ . '/../app/bootstrap.php';
try {
    switch ($argv[1] ?? '') {
        case 'password':
            echo 'Unesite novu admin lozinku (unos je vidljiv samo u vašem terminalu): ';
            $pass = trim(fgets(STDIN));
            if (strlen($pass) < 12) {
                throw new RuntimeException('Najmanje 12 znakova.');
            }
            $path = ROOT . '/.env';
            $content = is_file($path)
                ? file_get_contents($path)
                : file_get_contents(ROOT . '/.env.example');
            $line = "ADMIN_PASSWORD_HASH='" . password_hash($pass, PASSWORD_DEFAULT) . "'";
            $content = preg_match('/^ADMIN_PASSWORD_HASH=.*$/m', $content)
                ? preg_replace_callback('/^ADMIN_PASSWORD_HASH=.*$/m', fn() => $line, $content)
                : $content . "\n" . $line . "\n";
            file_put_contents($path, $content, LOCK_EX);
            echo "Lozinka je sačuvana u .env.\n";
            break;
        case 'demo':
            $days = (int) ($argv[2] ?? 1);
            if ($days < 1 || $days > 7) {
                throw new RuntimeException('Broj dana mora biti 1–7.');
            }
            $config = settings();
            for ($d = $days - 1; $d >= 0; $d--) {
                generateDemo($config, date(DATE_ATOM, time() - $d * 86400));
            }
            echo "Generisani test podaci za $days dana, sva tri izvora.\n";
            break;
        case 'refresh':
            echo json_encode(
                collect(in_array('--force', $argv, true)),
                JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT,
            ) . "\n";
            break;
        default:
            echo "php bin/console.php password\n"
                . "php bin/console.php demo [1–7 dana]\n"
                . "php bin/console.php refresh [--force]\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
