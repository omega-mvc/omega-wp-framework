<?php

declare(strict_types=1);

namespace Tests\Routing\Support;

class WPRestRequest
{
    public function getParam(string $key): mixed
    {
        return null;
    }

    public function getParams(): array
    {
        return [];
    }
}
