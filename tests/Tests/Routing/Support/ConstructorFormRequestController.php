<?php

declare(strict_types=1);

namespace Tests\Routing\Support;

class ConstructorFormRequestController
{
    public function __construct(
        public int $id = 0,
    ) {
    }

    public function handle(TestFormRequest $request): array
    {
        return ['id' => $this->id, 'valid' => true];
    }
}
