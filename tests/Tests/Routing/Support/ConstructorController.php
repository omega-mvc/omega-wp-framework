<?php

declare(strict_types=1);

namespace Tests\Routing\Support;

class ConstructorController
{
    public function __construct(
        public int $id = 0,
    ) {
    }

    public function handle(): array
    {
        return ['id' => $this->id];
    }
}
