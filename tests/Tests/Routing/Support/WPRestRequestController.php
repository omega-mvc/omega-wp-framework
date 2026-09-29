<?php

declare(strict_types=1);

namespace Tests\Routing\Support;

use WP_REST_Request;

class WPRestRequestController
{
    /** @return array<string, mixed> */
    public function handle(WP_REST_Request $request): array
    {
        /** @var array<string, mixed> $params */
        $params = $request->get_params();

        return $params;
    }
}
