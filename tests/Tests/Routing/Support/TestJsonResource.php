<?php

declare(strict_types=1);

namespace Tests\Routing\Support;

use Omega\Http\Json\JsonResource;

class TestJsonResource extends JsonResource
{
    public function toArray(): array
    {
        return ['id' => 1, 'name' => 'test'];
    }
}
