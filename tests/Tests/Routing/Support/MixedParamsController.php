<?php

declare(strict_types=1);

namespace Tests\Routing\Support;

class MixedParamsController
{
    public function handle(\stdClass $dep, int $page = 1): array
    {
        return ['dep' => $dep, 'page' => $page];
    }
}
