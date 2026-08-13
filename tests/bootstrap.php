<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/Tests/Routing/helper.php';

if (!class_exists('WP_Error', false)) {
    class_alias(\Tests\Routing\Support\WPError::class, 'WP_Error');
}

if (!class_exists('WP_REST_Request', false)) {
    class_alias(\Tests\Routing\Support\WPRestRequest::class, 'WP_REST_Request');
}

if (!class_exists('WP_REST_Response', false)) {
    class_alias(\Tests\Routing\Support\WPRestResponse::class, 'WP_REST_Response');
}
