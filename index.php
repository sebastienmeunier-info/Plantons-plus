<?php

declare(strict_types=1);

use Plantons\Core\Application;
use Plantons\Core\Autoloader;
use Plantons\Core\ErrorHandler;
use Plantons\Core\Request;

define('PLANTONS_ROOT', __DIR__);

$requiredExtensions = ['json', 'mbstring', 'fileinfo'];
$missingExtensions = array_values(array_filter(
    $requiredExtensions,
    static fn(string $extension): bool => !extension_loaded($extension),
));

if ($missingExtensions !== []) {
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    $items = htmlspecialchars(implode('`, `', $missingExtensions), ENT_QUOTES, 'UTF-8');
    exit("<!doctype html><html lang=\"fr\"><meta charset=\"utf-8\"><title>Configuration serveur incomplète</title><body style=\"font-family:system-ui,sans-serif;max-width:40rem;margin:4rem auto;padding:0 1rem\"><h1>Configuration serveur incomplète</h1><p>Les extensions PHP suivantes sont nécessaires au fonctionnement de l'application mais ne sont pas activées sur ce serveur :</p><p><code>{$items}</code></p><p>Sur un serveur Debian/YunoHost, installez-les puis rechargez PHP-FPM :</p><pre>sudo apt install php-mbstring php-fileinfo\nsudo systemctl reload php8.3-fpm</pre><p>Adaptez <code>php8.3-fpm</code> à la version de PHP utilisée (voir <code>php -v</code>).</p></body></html>");
}

require PLANTONS_ROOT . '/app/Core/Autoloader.php';

Autoloader::register(PLANTONS_ROOT . '/app');

$config = require PLANTONS_ROOT . '/config/config.php';
(new ErrorHandler($config))->register();

$app = new Application($config);
$app->router()->load(require PLANTONS_ROOT . '/config/routes.php');
$app->run(Request::fromGlobals());
