<?php

declare(strict_types=1);

?>
<!doctype html>
<html lang="sr-Latn">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta
            name="description"
            content="Pregled parlamentarnih izbora u Srbiji: tri izvora, projekcije mandata i istorija."
        />
        <title>Izborni pregled · Srbija 2026</title>
        <link rel="stylesheet" href="assets/style.css" />
        <script src="assets/app.js" defer></script>
    </head>
    <body>
        <header class="topbar">
            <a class="brand" href="index.php"
                ><span class="brand-icon">▥</span> izborni<span>pregled</span></a
            >
            <nav>
                <a class="active" href="index.php">Rezultati</a><a href="admin.php">Podešavanja</a>
            </nav>
            <span class="edition">SRBIJA / 2026</span>
        </header>
        <main>
            <div class="hero">
                <div>
                    <p class="eyebrow">
                        <span class="dot"></span> PARLAMENTARNI IZBORI
                        <span id="election-date">25.10.2026.</span>
                    </p>
                    <h1>Svaki glas.<br /><span>Jasna slika.</span></h1>
                    <p class="intro">
                        Pratite glasove, mandate i promene kroz vreme.
                    </p>
                </div>
                <div class="hero-mark" aria-hidden="true">
                    <span>250</span><small>MESTA U NARODNOJ SKUPŠTINI</small>
                    <div class="mini-grid"></div>
                </div>
            </div>
            <div class="notice" id="mode-notice">Učitavanje podataka…</div>
            <section class="timeline" aria-label="Istorija rezultata">
                <div>
                    <span class="eyebrow">VREME PRESEKA</span>
                    <h2 id="view-label">Najnoviji rezultati</h2>
                </div>
                <div class="timeline-controls">
                    <label>Dan <input type="date" id="history-date" /></label
                    ><label
                        >Sačuvano stanje
                        <select id="history-time">
                            <option value="">Izaberite dan</option>
                        </select></label
                    ><button class="secondary" id="live-button">↻ Najnovije</button>
                </div>
                <p id="refresh-status" aria-live="polite"></p>
            </section>
            <div id="error" class="alert error" role="alert" hidden></div>
            <div id="results" aria-live="polite"></div>
            <details class="method panel">
                <summary>Kako računamo mandate? <span>Metodologija i izvori ↗</span></summary>
                <p>
                    Narodna skupština ima 250 mesta (Ustav, član 100). Srbija je jedna izborna
                    jedinica. Liste koje osvoje najmanje 3% glasova od broja birača koji su glasali
                    učestvuju u raspodeli. Ako nijedna lista ne pređe prag, učestvuju sve liste koje
                    imaju glasove.
                </p>
                <p>
                    Koristimo D’Ontov sistem: glasovi se dele sa 1, 2, 3… do 250; najveći količnici
                    dobijaju mandate, do broja kandidata na listi. Manjinske liste učestvuju i ispod
                    praga, a njihovi količnici ispod 3% uvećavaju se za 35%. Kod jednakih količnika
                    prednost ima veći broj glasova; nerešena granična jednakost posebno je označena.
                </p>
                <p>
                    Prikaz je nezvanična projekcija na osnovu prijavljenih delimičnih rezultata, a
                    ne odluka o dodeli mandata. Procenti glasova računaju se prema broju birača koji
                    su glasali, uključujući nevažeće listiće. Grafikon prikazuje mandate. Za većinu
                    je potrebno 126 mesta.
                </p>
                <p>
                    <a
                        href="https://www.rik.parlament.gov.rs/tekst/sr/77/o-izborima-za-narodne-poslanike.php"
                        target="_blank"
                        rel="noopener"
                        >RIK · objašnjenje izbornog sistema</a
                    >
                    ·
                    <a
                        href="https://www.rik.parlament.gov.rs/tekst/sr/61/zakoni.php"
                        target="_blank"
                        rel="noopener"
                        >Propisi · čl. 128, 129 i 140 Zakona</a
                    >
                    ·
                    <a
                        href="https://www.srbija.gov.rs/tekst/330326/ustav.php"
                        target="_blank"
                        rel="noopener"
                        >Ustav Republike Srbije</a
                    >
                </p>
            </details>
        </main>
        <footer>
            <span class="brand">izborni<span>pregled</span></span
            ><span>Nezavisan prikaz · Vremenska zona: Beograd</span
            ><a href="admin.php">Administracija ↗</a>
        </footer>
    </body>
</html>
