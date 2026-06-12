<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

// Temporary: capture fatal errors in prod to diagnose 500
if (($_SERVER['APP_ENV'] ?? 'dev') === 'prod' && !($_SERVER['APP_DEBUG'] ?? false)) {
    register_shutdown_function(function () {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            file_put_contents('/tmp/doraidprod.log',
                date('Y-m-d H:i:s') . ' FATAL: ' . $error['message']
                . ' in ' . $error['file'] . ':' . $error['line'] . PHP_EOL,
                FILE_APPEND
            );
        }
    });
    set_exception_handler(function (\Throwable $e) {
        file_put_contents('/tmp/doraidprod.log',
            date('Y-m-d H:i:s') . ' EXCEPTION: ' . get_class($e) . ': ' . $e->getMessage()
            . ' in ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL
            . $e->getTraceAsString() . PHP_EOL,
            FILE_APPEND
        );
        throw $e;
    });
}

return static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
