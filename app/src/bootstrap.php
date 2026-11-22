<?php

declare(strict_types=1);

// Runtime setup shared by public/index.php, bin/*.php and tests.
// Dependency wiring is added here in T10.

require dirname(__DIR__) . '/vendor/autoload.php';

App\Config\Env::load(dirname(__DIR__));

// date.timezone in php.ini belongs to the image; this makes UTC a property of the code.
date_default_timezone_set('UTC');
