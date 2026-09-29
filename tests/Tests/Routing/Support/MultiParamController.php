<?php

declare(strict_types=1);

namespace Tests\Routing\Support;

class MultiParamController
{
    /** @return array<string, mixed> */
    public function handle(TestFormRequest $request, int $id, string $sort = 'name'): array
    {
        return ['id' => $id, 'sort' => $sort];
    }
}
