<!doctype html>
<html lang="sr-Latn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Podešavanja · Izborni pregled</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/admin.js" defer></script>
</head>
<body>
    <header class="topbar">
        <a class="brand" href="index.php">
            <span class="brand-icon">▥</span> izborni<span>pregled</span>
        </a>
        <nav>
            <a href="index.php">Rezultati</a>
            <a class="active" href="admin.php">Podešavanja</a>
        </nav>
        <span class="edition">SRBIJA / 2026</span>
    </header>
    <main class="admin">
        <p class="eyebrow">KONTROLNI PANEL</p>
        <h1>Podešavanja praćenja</h1>
        <p class="intro">Izvori, ritam osvežavanja i izgled izbornog pregleda.</p>

        <?php if ($error) : ?>
            <p class="alert error" role="alert"><?= h($error) ?></p>
        <?php endif; ?>

        <?php if ($notice) : ?>
            <p class="alert" role="status"><?= h($notice) ?></p>
        <?php endif; ?>

        <?php if (!$admin) : ?>
            <form method="post" class="panel login">
                <h2>Prijava administratora</h2>
                <p>Pristup podešavanjima je zaštićen lozinkom.</p>
                <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
                <input type="hidden" name="action" value="login">
                <label>
                    Lozinka
                    <input type="password" name="password" required autocomplete="current-password">
                </label>
                <button>Prijavi se →</button>
                <?php if (!env('ADMIN_PASSWORD_HASH')) : ?>
                    <p>Prvo postavite lozinku komandom <code>php bin/console.php password</code>.</p>
                <?php endif; ?>
            </form>
        <?php else : ?>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
                <input type="hidden" name="action" value="save">

                <section class="panel">
                    <h2>01 <span>Opšta podešavanja</span></h2>
                    <div class="form-grid">
                        <label>
                            Osvežavanje (minuta)
                            <input type="number" name="interval" min="1" max="1440"
                                value="<?= h($config['interval']) ?>" required>
                        </label>
                        <label>
                            Dan izbora
                            <input type="date" name="electionDate"
                                value="<?= h($config['electionDate']) ?>" required>
                        </label>
                        <label>
                            Režim podataka
                            <select name="mode">
                                <option value="demo" <?= $config['mode'] === 'demo' ? 'selected' : '' ?>>
                                    Demo · izmišljeni podaci
                                </option>
                                <option value="live" <?= $config['mode'] === 'live' ? 'selected' : '' ?>>
                                    Uživo · preuzimanje izvora
                                </option>
                            </select>
                        </label>
                    </div>
                    <p class="hint">
                        Demo i stvarni rezultati imaju zasebnu istoriju. Pozadinski proces u Dockeru
                        proverava interval svakog minuta.
                    </p>
                </section>

                <section class="panel">
                    <h2>02 <span>Izvori rezultata</span></h2>
                    <p>
                        Jedan URL po redu, najviše pet po izvoru. Prvi ispravan zbirni nacionalni izveštaj
                        ima prednost. Izveštaji se ne sabiraju. HTML i tekst parsira AI;
                        JSON u dokumentovanom formatu čita se direktno.
                    </p>
                    <div class="form-grid">
                        <?php foreach (SOURCES as $id => $name) : ?>
                            <label>
                                <?= h($name) ?>
                                <textarea name="urls[<?= h($id) ?>]" rows="5"
                                    placeholder="https://…"><?= h(implode("\n", $config['sources'][$id])) ?></textarea>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="hint">
                        Ključ: OPENAI_API_KEY u .env · model: <?= h(env('OPENAI_MODEL', 'gpt-4.1-mini')) ?>.
                        PDF i sadržaj koji se učitava samo kroz JavaScript zahtevaju poseban adapter.
                    </p>
                </section>

                <section class="panel">
                    <h2>03 <span>Izborne liste i boje</span></h2>
                    <p>
                        Manjinski status i broj kandidata unesite prema odluci RIK-a.
                        Demo nazivi su primeri, a ne zvaničan spisak lista.
                    </p>
                    <div class="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID za parsiranje</th>
                                    <th>Naziv liste</th>
                                    <th>Boja</th>
                                    <th>Kandidati</th>
                                    <th>Manjinska</th>
                                </tr>
                            </thead>
                            <tbody id="list-rows">
                                <?php foreach ($editableLists as $i => $list) : ?>
                                    <tr>
                                        <td>
                                            <input aria-label="ID liste" name="list[<?= $i ?>][id]"
                                                value="<?= h($list['id']) ?>">
                                        </td>
                                        <td>
                                            <input aria-label="Naziv liste" name="list[<?= $i ?>][name]"
                                                value="<?= h($list['name']) ?>">
                                        </td>
                                        <td>
                                            <input aria-label="Boja liste" type="color"
                                                name="list[<?= $i ?>][color]" value="<?= h($list['color']) ?>">
                                        </td>
                                        <td>
                                            <input aria-label="Broj kandidata" type="number" min="1" max="250"
                                                name="list[<?= $i ?>][candidates]"
                                                value="<?= h($list['candidates']) ?>">
                                        </td>
                                        <td>
                                            <input aria-label="Manjinski status" type="checkbox"
                                                name="list[<?= $i ?>][minority]"
                                                <?= $list['minority'] ? 'checked' : '' ?>>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <button type="button" class="secondary" id="add-list">+ Dodaj listu</button>
                    <p class="hint">
                        Za uklanjanje liste ispraznite i ID i naziv. Istorijski prikazi čuvaju tadašnje
                        boje i registar.
                    </p>
                </section>

                <div class="actions">
                    <button>Sačuvaj podešavanja</button>
                    <a href="index.php">Pogledaj rezultate ↗</a>
                </div>
            </form>

            <section class="panel">
                <h2>04 <span>Preuzimanje i test podaci</span></h2>
                <div class="actions">
                    <?php foreach ($adminActions as $action => $label) : ?>
                        <form method="post">
                            <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
                            <input type="hidden" name="action" value="<?= h($action) ?>">
                            <button class="secondary"><?= h($label) ?></button>
                        </form>
                    <?php endforeach; ?>
                </div>
                <?php foreach (SOURCES as $id => $name) : ?>
                    <p>
                        <strong><?= h($name) ?>:</strong>
                        <?= h($status[$id]['message'] ?? 'Preuzimanje još nije pokrenuto.') ?>
                        <small><?= h($status[$id]['checkedAt'] ?? '') ?></small>
                    </p>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>
    <footer>Izborni pregled · Nezavisan prikaz podataka</footer>
</body>
</html>
