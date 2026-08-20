<?php

declare(strict_types=1);

namespace Tests\Routing\Support;

class MultiParamController
{
    public function handle(TestFormRequest $request, int $id, string $sort = 'name'): array
    {
        return ['id' => $id, 'sort' => $sort];
    }
}
