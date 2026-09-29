<?php

declare(strict_types=1);

/*
 * PHPUnit's process isolation (#[RunInSeparateProcess]) re-launches a test in a child
 * process whose template restores the Composer autoloader only through the
 * PHPUNIT_COMPOSER_INSTALL constant. Since PHPUnit 10 the default of
 * TestCase::$preserveGlobalState is false, so no other file is replayed into the child and
 * the child would otherwise start without an autoloader at all.
 *
 * PHPUnit's own binary defines this constant before booting; the Pest binary does not, so
 * tests/bootstrap.php has to define it.
 *
 * It cannot be defined any earlier than that: PHPUnit\TextUI\Application::preload()
 * require_once's every class map file whose class name starts with "PHPUnit\", and for
 * PHPUnit\Event\Code\ThrowableBuilder the merged class map points at the PHPUnit copy
 * rather than the Pest override that BootOverrides already declared, which would make the
 * whole run die with "Cannot redeclare class". The bootstrap file is loaded inside
 * PHPUnit's Application::handle(), i.e. after preload() and before
 * SeparateProcessTestRunner::run(), which is the only window that works.
 *
 * This file only defines a constant and has no side effect, so it does not trip the
 * PSR1.Files.SideEffects sniff that tests/bootstrap.php would.
 */
if (!defined('PHPUNIT_COMPOSER_INSTALL')) {
    define('PHPUNIT_COMPOSER_INSTALL', dirname(__DIR__) . '/vendor/autoload.php');
}
