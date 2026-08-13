<?php

declare(strict_types=1);

namespace Tests\Container\Support;

class B
{
	public A $a;

	public function __construct(A $a)
	{
		$this->a = $a;
	}
}
