<?php
/**
 * Autoloader personalizado para el módulo Yuju
 */

spl_autoload_register(function ($class) {
    // Solo maneja clases que empiecen con 'Yuju'
    if (strpos($class, 'Yuju') !== 0) {
        return;
    }

    // Convierte el nombre de la clase a una ruta de archivo
    $file = __DIR__ . '/classes/' . $class . '.php';

    // Carga el archivo si existe
    if (file_exists($file)) {
        require_once $file;
    }
});