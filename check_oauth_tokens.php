<?php
/**
 * Script para verificar los datos en la tabla yuju_oauth_tokens
 */

// Detectar si estamos en el contexto web o CLI
$is_web = isset($_SERVER['HTTP_HOST']);

if ($is_web) {
    echo "<pre>";
}

// Buscar el archivo de configuración de PrestaShop
$config_paths = [
    './config/config.inc.php',
    '../config/config.inc.php',
    '../../config/config.inc.php',
    '../../../config/config.inc.php'
];

$config_found = false;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $config_found = true;
        echo "Configuración cargada desde: {$path}\n";
        break;
    }
}

if (!$config_found) {
    echo "ERROR: No se pudo encontrar el archivo de configuración de PrestaShop.\n";
    exit;
}

// Verificar si la tabla existe
$table_name = _DB_PREFIX_ . 'yuju_oauth_tokens';
$table_exists = Db::getInstance()->executeS("SHOW TABLES LIKE '{$table_name}'");

echo "<h1>Verificación de la tabla {$table_name}</h1>";

if (empty($table_exists)) {
    echo "<p>ERROR: La tabla {$table_name} no existe.</p>";
    exit;
}

// Verificar estructura de la tabla
echo "<h2>Estructura de la tabla:</h2>";
echo "<pre>";
$columns = Db::getInstance()->executeS("SHOW COLUMNS FROM `{$table_name}`");
print_r($columns);
echo "</pre>";

// Verificar registros en la tabla
echo "<h2>Registros en la tabla:</h2>";
$records = Db::getInstance()->executeS("SELECT * FROM `{$table_name}`");

if (empty($records)) {
    echo "<p>No hay registros en la tabla.</p>";
} else {
    echo "<p>Total de registros: " . count($records) . "</p>";
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr>";
    foreach ($columns as $column) {
        echo "<th>{$column['Field']}</th>";
    }
    echo "</tr>";
    
    foreach ($records as $record) {
        echo "<tr>";
        foreach ($columns as $column) {
            $field = $column['Field'];
            $value = $record[$field];
            
            // Ocultar información sensible
            if (in_array($field, ['client_secret', 'access_token', 'refresh_token'])) {
                if (!empty($value)) {
                    $value = substr($value, 0, 10) . '...' . substr($value, -5);
                }
            }
            
            echo "<td>{$value}</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
}

// Verificar si hay campos nulos o vacíos en los registros
echo "<h2>Análisis de campos:</h2>";
$fields_to_check = ['client_id', 'client_secret', 'access_token', 'refresh_token', 'token_expires', 'expires_at'];

foreach ($records as $index => $record) {
    echo "<h3>Registro #{$record['id']}:</h3>";
    echo "<ul>";
    
    foreach ($fields_to_check as $field) {
        $status = "";
        if (!isset($record[$field])) {
            $status = "<span style='color:red'>CAMPO NO EXISTE</span>";
        } elseif (is_null($record[$field])) {
            $status = "<span style='color:orange'>NULL</span>";
        } elseif (empty($record[$field])) {
            $status = "<span style='color:orange'>VACÍO</span>";
        } else {
            $status = "<span style='color:green'>OK</span>";
        }
        
        echo "<li>{$field}: {$status}</li>";
    }
    
    echo "</ul>";
}