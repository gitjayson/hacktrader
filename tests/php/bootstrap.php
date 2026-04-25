<?php
declare(strict_types=1);

// Bootstrap for PHPUnit. Loads source files we want to test.
//
// We deliberately don't include api.php or any other file that has
// top-level side effects (session_start(), reading $_GET, etc.) — those
// would crash the test runner. Instead we test the includable libraries
// directly (api_auth.php) and refactored helpers as they're extracted.

require_once __DIR__ . '/../../api_auth.php';
