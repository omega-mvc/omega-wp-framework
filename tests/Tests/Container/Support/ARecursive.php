<?php

declare(strict_types=1);

namespace Tests\Container\Support;

class ARecursive
{
	public BRecursive $b;

	public function __construct(BRecursive $b)
	{
		$this->b = $b;
	}
}
