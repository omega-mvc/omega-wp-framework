<?php

declare(strict_types=1);

namespace Tests\Routing\Support;

class NoDefaultController
{
    public function handle(int $id): array
    {
        return ['id' => $id];
    }
}
