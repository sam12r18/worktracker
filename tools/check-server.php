<?php

declare(strict_types=1);

$requiredExtensions = ['ctype','curl','dom','fileinfo','filter','hash','mbstring','openssl','pcre','pdo','session','tokenizer','xml','pdo_mysql'];
$failures = [];
$warnings = [];

if (version_compare(PHP_VERSION, '8.3.0', '<')) {
    $failures[] = 'PHP 8.3+ is required for the Laravel 13 deployment baseline. Current: '.PHP_VERSION;
}

foreach ($requiredExtensions as $extension) {
    if (! extension_loaded($extension)) {
        $failures[] = "Missing PHP extension: {$extension}";
    }
}

foreach (['proc_open','shell_exec'] as $fn) {
    if (! function_exists($fn)) {
        $warnings[] = "{$fn} is disabled. WorkTracker runtime does not require it, but some Composer/deployment workflows may.";
    }
}

printf("WorkTracker server preflight\nPHP: %s\nSAPI: %s\n\n", PHP_VERSION, PHP_SAPI);
foreach ($warnings as $warning) echo "WARN: {$warning}\n";
foreach ($failures as $failure) echo "FAIL: {$failure}\n";
if ($failures === []) echo "OK: PHP runtime requirements passed.\n";

exit($failures === [] ? 0 : 1);
