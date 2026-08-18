<?php

declare(strict_types=1);

namespace Tests\Routing\Support;

class FormRequestController
{
    public function handle(TestFormRequest $request): array
    {
        return ['valid' => true];
    }
}
