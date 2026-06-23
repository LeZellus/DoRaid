<?php

use App\Kernel;

// Masque la version de PHP révélée par défaut (expose_php), non modifiable en .htaccess sur cet hébergement.
header_remove('X-Powered-By');

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
