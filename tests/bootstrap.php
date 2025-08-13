<?php

declare(strict_types=1);

// Define constant to indicate we're in a test environment
define('PHPUNIT_RUNNING', true);

// Load the composer autoloader
require dirname(__DIR__).'/vendor/autoload.php';
