<?php

declare(strict_types=1);

namespace Tests\Container\Support;

class E
{
	public string $message;

	public ?A $a;

	public function __construct(string $message = '', ?A $a = null)
	{
		$this->message = $message;
		$this->a       = $a;
	}
}
