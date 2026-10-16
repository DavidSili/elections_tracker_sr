<?php

declare(strict_types=1);

function check(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function registry(array $ids): array
{
    return array_map(
        fn($id) => [
            'id' => $id,
            'name' => $id,
            'color' => '#123456',
            'minority' => false,
            'candidates' => 250,
        ],
        $ids,
    );
}
