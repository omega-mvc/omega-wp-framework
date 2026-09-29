<?php

declare(strict_types=1);

namespace Tests\Routing\Support;

class UntypedParamController
{
    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    public function handle($value = 'default'): array
    {
        return ['value' => $value];
    }
}
