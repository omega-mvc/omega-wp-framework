<?php

declare(strict_types=1);

namespace Tests\Routing\Support;

use Omega\Http\FormRequest;
use WP_REST_Request;

class TestFormRequest extends FormRequest
{
    public function __construct(WP_REST_Request $request)
    {
        parent::__construct($request);

        $this->rules = ['name' => 'required'];
    }
}
