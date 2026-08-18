<?php

declare(strict_types=1);

namespace Tests\Routing\Support;

class ConstructorStringController
{
    public function __construct(
        public int $id = 0,
    ) {
    }

    public function withString(): string
    {
        return '<p>html output</p>';
    }

    public function handle(): array
    {
        return ['id' => $this->id];
    }

    public function returnsNull(): void
    {
    }
}
