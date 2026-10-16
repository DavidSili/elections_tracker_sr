<?php

require __DIR__ . '/app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    $config = settings();
    $mode = $config['mode'];
    $action = $_GET['action'] ?? 'results';
    if ($action === 'history') {
        $items = [];
        foreach (array_keys(SOURCES) as $source) {
            foreach (
                glob(ROOT . '/storage/' . $mode . '/' . $source . '/history/*.json') as $file
            ) {
                $s = readJson($file);
                $items[] = [
                    'id' => $s['id'],
                    'source' => $source,
                    'capturedAt' => $s['capturedAt'],
                ];
            }
        }
        usort($items, fn($a, $b) => strtotime($a['capturedAt']) <=> strtotime($b['capturedAt']));
        echo json_encode($items, JSON_UNESCAPED_UNICODE);
        exit();
    }
    $at = $_GET['at'] ?? '';
    if (
        $at !== '' &&
        (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $at) || strtotime($at) === false)
    ) {
        throw new RuntimeException('Neispravan datum.');
    }
    $snapshots = [];
    foreach (SOURCES as $source => $name) {
        $folder = ROOT . '/storage/' . $mode . '/' . $source;
        $snapshot = $at === '' ? readJson($folder . '/latest.json', null) : null;
        if ($at !== '') {
            foreach (glob($folder . '/history/*.json') as $file) {
                $candidate = readJson($file);
                if (
                    strtotime($candidate['capturedAt']) <= strtotime($at) &&
                    (!$snapshot || strtotime($candidate['capturedAt']) > strtotime($snapshot['capturedAt']))
                ) {
                    $snapshot = $candidate;
                }
            }
        }
        $snapshots[$source] = $snapshot;
    }
    echo json_encode(
        [
            'mode' => $mode,
            'interval' => $config['interval'],
            'electionDate' => $config['electionDate'],
            'sources' => SOURCES,
            'snapshots' => $snapshots,
            'status' => readJson(ROOT . '/storage/status.json'),
        ],
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
    );
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
