<?php

declare(strict_types=1);

namespace Tests\Container\Support;

class C
{
    public B $b;

    public function __construct(B $b)
    {
        $this->b = $b;
    }
}
