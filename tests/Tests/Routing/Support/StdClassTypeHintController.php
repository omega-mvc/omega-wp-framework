<?php

declare(strict_types=1);

namespace Tests\Routing\Support;

class StdClassTypeHintController
{
    public function handle(\stdClass $dep): array
    {
        return ['ok' => true];
    }
}
