<?php
spl_autoload_register(function ($class) {
    // Replace namespace separator with directory separator
    $file = __DIR__ . '/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
