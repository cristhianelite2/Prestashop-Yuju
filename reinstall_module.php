<?php
/**
 * Script para reinstalar el módulo Yuju y actualizar traducciones
 * Ejecutar desde la línea de comandos o navegador web
 */

// Configuración de PrestaShop
require_once dirname(__FILE__) . '/../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../init.php';

// Verificar que estamos en modo de desarrollo o admin
if (!defined('_PS_ADMIN_DIR_')) {
    die('Este script solo puede ejecutarse desde el área de administración.');
}

echo "<h2>Reinstalación del Módulo Yuju</h2>";
echo "<p>Iniciando proceso de reinstalación...</p>";

try {
    // Cargar el módulo
    $module = Module::getInstanceByName('prestashopyuju');
    
    if (!$module) {
        echo "<p style='color: red;'>Error: No se pudo cargar el módulo.</p>";
        exit;
    }
    
    echo "<p>Módulo cargado correctamente.</p>";
    
    // Desinstalar el módulo
    echo "<p>Desinstalando módulo...</p>";
    if ($module->uninstall()) {
        echo "<p style='color: green;'>Módulo desinstalado correctamente.</p>";
    } else {
        echo "<p style='color: orange;'>Advertencia: Problemas durante la desinstalación.</p>";
    }
    
    // Limpiar caché de módulos
    echo "<p>Limpiando caché...</p>";
    Module::clearCache();
    
    // Reinstalar el módulo
    echo "<p>Reinstalando módulo...</p>";
    if ($module->install()) {
        echo "<p style='color: green;'>Módulo reinstalado correctamente.</p>";
    } else {
        echo "<p style='color: red;'>Error: No se pudo reinstalar el módulo.</p>";
        exit;
    }
    
    // Limpiar caché de traducciones
    echo "<p>Limpiando caché de traducciones...</p>";
    Tools::clearCache();
    
    echo "<h3 style='color: green;'>¡Reinstalación completada exitosamente!</h3>";
    echo "<p>El módulo Yuju ha sido reinstalado con las nuevas traducciones en español.</p>";
    echo "<p><a href='" . Context::getContext()->link->getAdminLink('AdminModules') . "&configure=prestashopyuju'>Ir a la configuración del módulo</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error durante la reinstalación: " . $e->getMessage() . "</p>";
}