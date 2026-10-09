<?php

/** @var callable(mixed): string $e */

echo 'Hello, ' . (isset($name) && is_scalar($name) ? $e($name) : '') . "!\n";
