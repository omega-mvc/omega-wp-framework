<?php

declare(strict_types=1);

namespace Tests\Container\Support;

class BRecursive
{
	public ARecursive $a;

	public function __construct(ARecursive $a)
	{
		$this->a = $a;
	}
}
