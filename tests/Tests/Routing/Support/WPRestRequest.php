<?php

declare(strict_types=1);

namespace Tests\Routing\Support;

class WPRestRequest
{
    /**
     * Request parameters exposed by get_params().
     *
     * @var array<string, mixed>
     */
    public array $params = [];

    /**
     * @param array<string, mixed> $params Request parameters
     */
    public function __construct(array $params = [])
    {
        $this->params = $params;
    }

    public function getParam(string $key): mixed
    {
        return $this->params[$key] ?? null;
    }

    /** @return array<string, mixed> */
    public function getParams(): array
    {
        return $this->params;
    }

    /** @return array<string, mixed> */
    public function get_params(): array
    {
        return $this->params;
    }
}
