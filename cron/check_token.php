<?php
/**
 * 2024 Yuju Integration - Verificación de Token
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 *
 * @author    Yuju Integration Team
 * @copyright 2024 Yuju Integration
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * 
 * NOTA: Este archivo es llamado automáticamente desde cron.php cada 12 horas.
 * No es necesario configurarlo directamente en crontab.
 */

// Verificar si ya se cargó PrestaShop (llamado desde cron.php)
if (!defined('_PS_VERSION_')) {
    // Configuración de PrestaShop (si se ejecuta directamente)
    $root_path = dirname(__FILE__, 2);
    require_once $root_path . '/../../config/config.inc.php';
    require_once $root_path . '/../../init.php';
    require_once $root_path . '/classes/YujuOAuth.php';
    require_once $root_path . '/classes/YujuApiClient.php';
    require_once $root_path . '/classes/YujuLogger.php';
    
    // Inicializar componentes
    $logger = new YujuLogger();
    $oauth = new YujuOAuth();
    
    echo "==============================================\n";
    echo "  YUJU - Verificación de Token\n";
    echo "  " . date('Y-m-d H:i:s') . "\n";
    echo "==============================================\n\n";
} else {
    // Ya fue cargado por cron.php, solo cargar las clases necesarias
    if (!class_exists('YujuOAuth')) {
        require_once dirname(__FILE__) . '/../classes/YujuOAuth.php';
    }
    if (!class_exists('YujuApiClient')) {
        require_once dirname(__FILE__) . '/../classes/YujuApiClient.php';
    }
    
    $oauth = new YujuOAuth();
}

try {
    // =============================================
    // TAREA 1: Verificar configuración OAuth
    // =============================================
    echo "[1/4] Verificando configuración OAuth...\n";
    
    if (!$oauth->isConfigured()) {
        $error = 'OAuth no está configurado. Configure Client ID y Secret Key.';
        $logger->log('error', $error);
        echo "❌ ERROR: $error\n";
        exit(1);
    }
    
    echo "✅ OAuth configurado correctamente\n\n";
    
    // =============================================
    // TAREA 2: Verificar existencia del token
    // =============================================
    echo "[2/4] Verificando token de acceso...\n";
    
    $oauth_data = $oauth->getStoredTokenData();
    
    if (!$oauth_data || empty($oauth_data['access_token'])) {
        $error = 'Token no encontrado. Realice la conexión inicial desde el panel de administración.';
        $logger->log('error', $error);
        sendNotification('Token de Yuju no encontrado', $error);
        echo "❌ ERROR: $error\n";
        exit(1);
    }
    
    echo "✅ Token encontrado\n";
    echo "   Expira: " . ($oauth_data['token_expires'] ?? 'No definido') . "\n";
    echo "   Actualizado: " . ($oauth_data['updated_at'] ?? 'No definido') . "\n\n";
    
    // =============================================
    // TAREA 3: Verificar vigencia del token
    // =============================================
    echo "[3/4] Verificando vigencia del token...\n";
    
    $token_expired = $oauth->isTokenExpired($oauth_data);
    $token_expiring_soon = $oauth->needsRenewal(86400); // 24 horas
    
    if ($token_expired) {
        $message = 'El token ha EXPIRADO. Reconecte inmediatamente desde Configuración > Yuju > OAuth.';
        $logger->log('error', $message);
        
        // Intentar enviar notificación (opcional, no detiene ejecución si falla)
        try {
            sendNotification('⚠️ Token de Yuju EXPIRADO', $message);
        } catch (Exception $e) {
            $logger->log('warning', 'No se pudo enviar email de notificación: ' . $e->getMessage());
            echo "   (Nota: Email no enviado - configure plantillas de correo)\n";
        }
        
        echo "❌ ERROR: Token EXPIRADO\n";
        echo "   Acción requerida: Reconectar ahora\n\n";
    } elseif ($token_expiring_soon) {
        $expires_at = strtotime($oauth_data['token_expires']);
        $hours_remaining = ceil(($expires_at - time()) / 3600);
        $message = "El token expirará en aproximadamente $hours_remaining horas. Reconecte pronto.";
        $logger->log('warning', $message);
        
        // Intentar enviar notificación (opcional)
        try {
            sendNotification('⚠️ Token de Yuju próximo a expirar', $message);
        } catch (Exception $e) {
            $logger->log('warning', 'No se pudo enviar email de notificación: ' . $e->getMessage());
        }
        
        echo "⚠️  ADVERTENCIA: Token expirará pronto\n";
        echo "   Tiempo restante: ~$hours_remaining horas\n\n";
    } else {
        echo "✅ Token vigente y con tiempo suficiente\n\n";
    }
    
    // =============================================
    // TAREA 4: Validar token con la API
    // =============================================
    echo "[4/4] Validando token con API de Yuju...\n";
    
    $api = new YujuApiClient();
    $test_response = $api->get('/webhook-sub');
    
    if (!$test_response['success']) {
        $http_code = $test_response['http_code'] ?? 'unknown';
        
        if ($http_code == 401) {
            $message = 'Token rechazado por la API (401 Unauthorized). Reconecte desde el panel.';
            $logger->log('error', $message);
            sendNotification('Token de Yuju inválido', $message);
            echo "❌ ERROR: Token rechazado por la API\n";
        } else {
            $message = "Error al validar token. HTTP $http_code";
            $logger->log('warning', $message, ['response' => $test_response]);
            echo "⚠️  ADVERTENCIA: $message\n";
        }
    } else {
        echo "✅ Token validado exitosamente con la API\n";
    }
    
    echo "\n==============================================\n";
    echo "  Verificación completada exitosamente\n";
    echo "==============================================\n";
    
    $logger->log('info', 'Verificación de token completada exitosamente');
    
    // Si se ejecuta directamente, terminar el script
    if (!defined('_PS_VERSION_') || basename($_SERVER['PHP_SELF']) == 'check_token.php') {
        exit(0);
    }
    
} catch (Exception $e) {
    $error_message = 'Error en verificación de token: ' . $e->getMessage();
    $logger->log('error', $error_message, ['trace' => $e->getTraceAsString()]);
    echo "\n❌ ERROR CRÍTICO: " . $e->getMessage() . "\n";
    
    // Si se ejecuta directamente, terminar con error
    if (!defined('_PS_VERSION_') || basename($_SERVER['PHP_SELF']) == 'check_token.php') {
        exit(1);
    }
}

/**
 * Envía notificación por email al administrador
 */
function sendNotification($subject, $message)
{
    global $logger;
    
    $admin_email = Configuration::get('PS_SHOP_EMAIL');
    
    if (!$admin_email) {
        $logger->log('warning', 'No se pudo enviar email: administrador no configurado');
        return false;
    }
    
    $email_vars = [
        '{subject}' => $subject,
        '{message}' => $message,
        '{shop_name}' => Configuration::get('PS_SHOP_NAME'),
        '{date}' => date('Y-m-d H:i:s'),
        '{admin_url}' => Context::getContext()->link->getAdminLink('AdminYujuConfiguration'),
        '{current_year}' => date('Y')
    ];
    
    $result = Mail::Send(
        (int) Configuration::get('PS_LANG_DEFAULT'),
        'yuju_token_notification',
        $subject,
        $email_vars,
        $admin_email,
        null,
        $admin_email,
        Configuration::get('PS_SHOP_NAME'),
        null,
        null,
        dirname(__FILE__) . '/../mails/'
    );
    
    if ($result) {
        $logger->log('info', 'Email de notificación enviado', ['to' => $admin_email]);
    } else {
        $logger->log('warning', 'Fallo al enviar email de notificación');
    }
    
    return $result;
}
