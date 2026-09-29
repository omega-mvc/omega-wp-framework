<?php

declare(strict_types=1);

namespace Tests\Routing\Support;

class StubController
{
    /** @return array<string, mixed> */
    public function handle(): array
    {
        return ['ok' => true];
    }

    public function withString(): string
    {
        return '<p>html output</p>';
    }

    public function returnsNull(): void
    {
    }

    /** @return array<string, mixed> */
    public function withDefault(int $page = 1, string $sort = 'name'): array
    {
        return ['page' => $page, 'sort' => $sort];
    }
}
