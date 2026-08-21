<?php

declare(strict_types=1);

namespace Tests\Container\Support;

class F
{
    public string $message;

    public function __construct(string $message)
    {
        $this->message = $message;
    }
}
