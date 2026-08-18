<?php

declare(strict_types=1);

namespace Tests\Routing\Support;

use WP_REST_Request;

class WPRestRequestController
{
    public function handle(WP_REST_Request $request): array
    {
        return $request->getParams();
    }
}
