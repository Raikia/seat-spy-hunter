<?php

require dirname(__DIR__, 3) . '/vendor/autoload.php';

spl_autoload_register(function (string $class): void {
    $prefix = 'Raikia\\SeatSpyHunter\\';

    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $path = dirname(__DIR__) . '/src/' . $relative . '.php';

    if (is_file($path)) {
        require $path;
    }
});
