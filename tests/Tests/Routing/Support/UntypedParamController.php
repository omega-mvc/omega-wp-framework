<?php

declare(strict_types=1);

namespace Tests\Routing\Support;

class UntypedParamController
{
    public function handle($value = 'default'): array
    {
        return ['value' => $value];
    }
}
