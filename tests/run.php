<?php

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$checks = 0;
require __DIR__ . '/helpers.php';

// A known D'Hondt example: quotients allocate 3, 2, 0 seats.
$lists = registry(['a', 'b', 'c']);
$result = allocate(['a' => 100000, 'b' => 80000, 'c' => 30000], 210000, $lists, 5);
check($result['seats'] === ['a' => 3, 'b' => 2, 'c' => 0], 'D’Hondt example failed');

// Threshold is based on all voters who voted, including invalid ballots.
$result = allocate(['a' => 9000, 'b' => 290], 10000, registry(['a', 'b']));
check($result['seats']['b'] === 0, 'Below-threshold list must be excluded');
$result = allocate(['a' => 9000, 'b' => 300], 10000, registry(['a', 'b']));
check($result['seats']['b'] > 0, 'Exactly 3% must qualify');

// Minority quotients below 3% are boosted 35%; above threshold they are not.
$lists = registry(['a', 'b']);
$lists[1]['minority'] = true;
$result = allocate(['a' => 9000, 'b' => 200], 10000, $lists);
check($result['seats']['b'] === 7, 'Minority boost failed');
$boosted = allocate(['a' => 9000, 'b' => 400], 10000, $lists);
$ordinary = allocate(['a' => 9000, 'b' => 400], 10000, registry(['a', 'b']));
check($boosted === $ordinary, 'Minority boost must stop at threshold');

$result = allocate(['a' => 200, 'b' => 100], 10000, registry(['a', 'b']));
check(
    array_sum($result['seats']) === 250 && $result['seats']['b'] > 0,
    'No list above threshold exception',
);

$lists = registry(['a', 'b']);
$lists[0]['candidates'] = 1;
$result = allocate(['a' => 9000, 'b' => 1000], 10000, $lists);
check($result['seats'] === ['a' => 1, 'b' => 249], 'Candidate capacity must be respected');
$result = allocate(['a' => 100, 'b' => 50], 150, registry(['a', 'b']), 2);
check($result['seats']['a'] === 2, 'Tied quotient must favor higher raw votes');
$result = allocate(['a' => 100, 'b' => 100], 200, registry(['a', 'b']), 1);
check(
    $result['unallocated'] === 1 && $result['warning'] !== null,
    'Unresolved tie must be disclosed',
);

$data = [
    'reportedAt' => date(DATE_ATOM),
    'ballotsCast' => 10000,
    'invalidBallots' => 1000,
    'registeredVoters' => 20000,
    'processedStations' => 8,
    'totalStations' => 10,
    'lists' => [['id' => 'a', 'votes' => 6000], ['id' => 'b', 'votes' => 3000]],
];
check(
    array_sum(validateResult($data, registry(['a', 'b']))['allocation']['seats']) === 250,
    'Valid report',
);
foreach (['sum', 'unknown', 'duplicate', 'missing', 'negative', 'future', 'stations'] as $case) {
    $bad = $data;
    switch ($case) {
        case 'sum':
            $bad['ballotsCast']++;
            break;
        case 'unknown':
            $bad['lists'][0]['id'] = 'unknown';
            break;
        case 'duplicate':
            $bad['lists'][1]['id'] = 'a';
            break;
        case 'missing':
            unset($bad['invalidBallots']);
            break;
        case 'negative':
            $bad['lists'][0]['votes'] = -1;
            break;
        case 'future':
            $bad['reportedAt'] = date(DATE_ATOM, time() + 86400);
            break;
        case 'stations':
            $bad['processedStations'] = 11;
            break;
    }
    $rejected = false;
    try {
        validateResult($bad, registry(['a', 'b']));
    } catch (RuntimeException $exception) {
        $rejected = true;
    }
    check($rejected, 'Invalid report not rejected: ' . $case);
}

foreach (
    [
        'http://127.0.0.1',
        'http://169.254.169.254',
        'file:///etc/passwd',
        'https://user:pass@example.com',
    ] as $url
) {
    $rejected = false;
    try {
        publicUrl($url);
    } catch (RuntimeException $exception) {
        $rejected = true;
    }
    check($rejected, 'Unsafe URL not rejected');
}

$testMode = '_test-' . bin2hex(random_bytes(6));
check(
    htmlText('<nav>Ignore</nav><table><tr><td>Lista A</td><td>123</td>'
        . '<td>456</td></tr></table><script>instruction()</script>') === 'Lista A 123 456',
    'HTML extraction must preserve cell boundaries and remove scripts and navigation',
);
$testConfig = ['mode' => $testMode, 'lists' => registry(['a', 'b'])];
$testFolder = ROOT . '/storage/' . $testMode . '/rik';
try {
    $valid = validateResult($data, $testConfig['lists']);
    check(saveSnapshot('rik', $valid, $testConfig), 'First snapshot must be saved');
    check(!saveSnapshot('rik', $valid, $testConfig), 'Identical report must not duplicate history');
    $original = readJson($testFolder . '/latest.json');
    $data['lists'][0]['votes']++;
    $data['ballotsCast']++;
    check(
        saveSnapshot('rik', validateResult($data, $testConfig['lists']), $testConfig),
        'Changed result must create a new snapshot',
    );
    check(count(glob($testFolder . '/history/*.json')) === 2, 'Two distinct states expected');
    check(
        readJson($testFolder . '/history/' . $original['id'] . '.json') === $original,
        'Historical snapshot must remain immutable',
    );
} finally {
    // Only remove the unique test directory and the files this test created.
    foreach (glob($testFolder . '/history/*.json') as $file) {
        unlink($file);
    }
    if (is_file($testFolder . '/latest.json')) {
        unlink($testFolder . '/latest.json');
    }
    rmdir($testFolder . '/history');
    rmdir($testFolder);
    rmdir(dirname($testFolder));
}

echo "Passed {$checks} election, input-validation and snapshot checks.\n";
