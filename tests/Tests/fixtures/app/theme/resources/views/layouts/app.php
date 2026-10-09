<?php

/** @var callable(mixed): string $e */
/** @var string $content */

echo '[layout:';
echo isset($title) && is_scalar($title) ? $e($title) : '';
echo ':' . (isset($name) && is_scalar($name) ? $e($name) : '');
echo ']' . $content;
echo "[/layout]\n";
