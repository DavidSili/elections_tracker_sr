<?php

declare(strict_types=1);

/** Articles 128,129,140. Integer cross multiplication avoids floating quotient ties. */
function allocate(array $votes, int $ballots, array $lists, int $total = 250): array
{
    if ($ballots <= 0) {
        return [
            'seats' => array_fill_keys(array_column($lists, 'id'), 0),
            'unallocated' => $total,
            'warning' => null,
        ];
    }
    $anyAbove = false;
    foreach ($votes as $v) {
        if ($v * 100 >= $ballots * 3) {
            $anyAbove = true;
        }
    }
    $seats = [];
    $weights = [];
    $raw = [];
    $limits = [];
    foreach ($lists as $l) {
        $id = $l['id'];
        $v = $votes[$id] ?? 0;
        $above = $v * 100 >= $ballots * 3;
        $seats[$id] = 0;
        $raw[$id] = $v;
        $limits[$id] = $l['candidates'];
        if ($v > 0 && ($above || $l['minority'] || !$anyAbove)) {
            $weights[$id] = $v * ($l['minority'] && !$above ? 135 : 100);
        }
    }
    for ($i = 0; $i < $total; $i++) {
        $best = null;
        $ties = [];
        foreach ($weights as $id => $weight) {
            if ($seats[$id] >= $limits[$id]) {
                continue;
            }
            $cmp =
                $best === null
                    ? 1
                    : $weight * ($seats[$best] + 1) <=> $weights[$best] * ($seats[$id] + 1);
            if ($cmp > 0 || ($cmp === 0 && $raw[$id] > $raw[$best])) {
                $best = $id;
                $ties = [$id];
            } elseif ($cmp === 0 && $raw[$id] === $raw[$best]) {
                $ties[] = $id;
            }
        }
        if ($best === null) {
            return [
                'seats' => $seats,
                'unallocated' => $total - $i,
                'warning' => 'Nedovoljan broj kandidata.',
            ];
        }
        if (count($ties) > 1 && $total - $i < count($ties)) {
            return [
                'seats' => $seats,
                'unallocated' => $total - $i,
                'warning' => 'Jednaki količnici i broj glasova na granici: potrebna odluka RIK-a.',
            ];
        }
        $seats[$best]++;
    }
    return ['seats' => $seats, 'unallocated' => 0, 'warning' => null];
}
function validateResult(array $data, array $lists): array
{
    foreach (
        ['ballotsCast', 'invalidBallots', 'registeredVoters', 'processedStations', 'totalStations'] as $key
    ) {
        if (
            !isset($data[$key]) ||
            !is_int($data[$key]) ||
            $data[$key] < 0 ||
            $data[$key] > 10000000
        ) {
            throw new RuntimeException('Nedostaje ili nije ispravno polje: ' . $key);
        }
    }
    if (
        $data['ballotsCast'] > $data['registeredVoters'] ||
        $data['processedStations'] > $data['totalStations'] ||
        !$data['totalStations']
    ) {
        throw new RuntimeException('Nelogični zbirni podaci.');
    }
    $ids = array_column($lists, 'id');
    $votes = [];
    foreach ($data['lists'] ?? [] as $row) {
        if (
            !in_array($row['id'] ?? '', $ids, true) ||
            isset($votes[$row['id']]) ||
            !is_int($row['votes'] ?? null) ||
            $row['votes'] < 0 ||
            $row['votes'] > 10000000
        ) {
            throw new RuntimeException(
                'Nepoznata lista ili neispravni glasovi. Proverite registar lista.',
            );
        }
        $votes[$row['id']] = $row['votes'];
    }
    if (
        count($votes) !== count($ids) ||
        array_sum($votes) + $data['invalidBallots'] !== $data['ballotsCast']
    ) {
        throw new RuntimeException(
            'Zbir glasova i nevažećih listića ne odgovara broju birača koji su glasali ili nedostaju liste.',
        );
    }
    if (
        !isset($data['reportedAt']) ||
        !is_string($data['reportedAt']) ||
        !preg_match(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|[+-]\d{2}:\d{2})$/',
            $data['reportedAt'],
        ) ||
        strtotime($data['reportedAt']) === false ||
        strtotime($data['reportedAt']) > time() + 300
    ) {
        throw new RuntimeException('Neispravno vreme objave izvora.');
    }
    $data['allocation'] = allocate($votes, $data['ballotsCast'], $lists);
    return $data;
}
function saveSnapshot(
    string $source,
    array $result,
    array $config,
    array $evidence = [],
    ?string $at = null,
): bool {
    $mode = $config['mode'];
    $folder = ROOT . '/storage/' . $mode . '/' . $source;
    return locked('snapshots', function () use (
        $folder,
        $result,
        $config,
        $evidence,
        $at,
        $source,
        $mode,
    ) {
        $fingerprint = hash('sha256', json_encode([$result, $config['lists']]));
        $latest = readJson($folder . '/latest.json');
        if (($latest['fingerprint'] ?? '') === $fingerprint) {
            return false;
        }
        $time = $at ?? date(DATE_ATOM);
        $id = (new DateTimeImmutable($time))->format('Ymd\THis.u') . '-' . bin2hex(random_bytes(4));
        $snapshot = [
            'id' => $id,
            'source' => $source,
            'mode' => $mode,
            'capturedAt' => $time,
            'fingerprint' => $fingerprint,
            'result' => $result,
            'registry' => $config['lists'],
            'evidence' => $evidence,
        ];
        writeJson($folder . '/history/' . $id . '.json', $snapshot);
        writeJson($folder . '/latest.json', $snapshot);
        return true;
    });
}
function generateDemo(array $config, ?string $at = null): void
{
    $config['mode'] = 'demo';
    $listIds = array_column($config['lists'], 'id');
    if (!in_array('studenti', $listIds, true) || !in_array('sns', $listIds, true)) {
        throw new RuntimeException('Demo scenariji zahtevaju liste sa ID-evima studenti i sns.');
    }
    $otherIds = array_values(array_diff($listIds, ['studenti', 'sns']));

    foreach (SOURCES as $source => $name) {
        // Basis points refer to all ballots cast, matching the public percentages.
        $studentShare = ($source === 'studenti' ? 5500 : ($source === 'sns' ? 4000 : 4750))
            + random_int(-30, 30);
        $snsShare = ($source === 'sns' ? 5500 : ($source === 'studenti' ? 4000 : 4750))
            + random_int(-30, 30);
        $ballotsCast = random_int(2200000, 2800000);
        $invalid = (int) round($ballotsCast * random_int(40, 60) / 10000);
        $votes = [
            'studenti' => (int) round($ballotsCast * $studentShare / 10000),
            'sns' => (int) round($ballotsCast * $snsShare / 10000),
        ];
        $remaining = $ballotsCast - $invalid - array_sum($votes);
        $weightTotal = 0;
        foreach ($otherIds as $rank => $id) {
            $weightTotal += 0.78 ** $rank;
        }
        foreach ($otherIds as $rank => $id) {
            // Other lists descend in registry order, independently of the two leaders.
            $votes[$id] = (int) floor($remaining * (0.78 ** $rank) / $weightTotal);
        }
        $roundingRemainder = $ballotsCast - $invalid - array_sum($votes);
        if ($otherIds) {
            $votes[$otherIds[0]] += $roundingRemainder;
        } else {
            $invalid += $roundingRemainder;
        }
        $rows = array_map(
            fn ($id) => ['id' => $id, 'votes' => $votes[$id]],
            $listIds,
        );
        $data = [
            'reportedAt' => $at ?? date(DATE_ATOM),
            'ballotsCast' => $ballotsCast,
            'invalidBallots' => $invalid,
            'registeredVoters' => 6500000,
            'processedStations' => random_int(3000, 8100),
            'totalStations' => 8273,
            'lists' => $rows,
        ];
        saveSnapshot($source, validateResult($data, $config['lists']), $config, [], $at);
    }
}
