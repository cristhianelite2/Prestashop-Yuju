<?php
/**
 * 2024 Yuju Integration.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    Yuju Integration Team
 * @copyright 2024 Yuju Integration
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Puente de telemetría del módulo hacia el monitor Yuju.
 *
 * Reporta únicamente metadatos: método/endpoint/estado/duración de peticiones,
 * contadores de sincronización y errores genéricos. Nunca envía cuerpos de
 * peticiones, productos, pedidos, datos de clientes ni credenciales.
 */
class YujuMonitor
{
    /** Tipos de evento que el puente puede reportar. */
    private const EMITTABLE_TYPES = ['api.request', 'sync.product', 'sync.order', 'sync.error', 'sync.run'];

    /**
     * Reporta un evento de telemetría al monitor configurado.
     *
     * @param string $type     Uno de self::EMITTABLE_TYPES.
     * @param array  $metadata Metadatos opcionales del evento.
     *
     * @return bool true si se envió y el monitor respondió 2xx
     */
    public static function emit($type, array $metadata = [])
    {
        if (!function_exists('curl_init') || !in_array($type, self::EMITTABLE_TYPES, true)) {
            return false;
        }

        $baseUrl = trim((string) Configuration::get('YUJU_MONITOR_URL'));
        $token = trim((string) Configuration::get('YUJU_MONITOR_TOKEN'));
        if ($baseUrl === '' || $token === '') {
            return false;
        }

        $payload = array_merge($metadata, ['type' => $type]);
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            return false;
        }

        return self::postJson(rtrim($baseUrl, '/') . '/api/events', $body, $token, 2);
    }

    /**
     * Conecta el módulo con el monitor: intercambia una llave temporal por un token.
     *
     * La llave es una huella SHA-256 de "yuju" más la fecha/hora del servidor
     * (formato d/m/y H:i). El monitor la valida en una ventana de ±2 minutos y
     * responde con un token de instalación que el módulo guardará y usará para
     * enviar la telemetría.
     *
     * @param string $monitorUrl        URL base del monitor (https://...).
     * @param string $shopUrl           URL pública de la tienda.
     * @param string $shopName          Nombre de la tienda.
     * @param string $moduleVersion     Versión del módulo.
     * @param string $prestashopVersion Versión de PrestaShop.
     *
     * @return array{success: bool, token?: string, installation?: array|null, message?: string}
     */
    public static function handshake($monitorUrl, $shopUrl, $shopName, $moduleVersion, $prestashopVersion)
    {
        if (!function_exists('curl_init')) {
            return ['success' => false, 'message' => 'cURL no está disponible en este servidor.'];
        }

        $baseUrl = rtrim(trim((string) $monitorUrl), '/');
        if ($baseUrl === '' || !filter_var($baseUrl, FILTER_VALIDATE_URL) || parse_url($baseUrl, PHP_URL_SCHEME) !== 'https') {
            return ['success' => false, 'message' => 'La URL del monitor debe ser una dirección https:// válida.'];
        }

        $key = hash('sha256', 'yuju' . date('d/m/y H:i'));
        $payload = json_encode([
            'key' => $key,
            'url' => $shopUrl,
            'name' => $shopName,
            'module_version' => $moduleVersion,
            'prestashop_version' => $prestashopVersion,
        ], JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            return ['success' => false, 'message' => 'No fue posible preparar la solicitud de conexión.'];
        }

        try {
            $curl = curl_init($baseUrl . '/api/connect');
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $response = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            curl_close($curl);

            if ($response === false) {
                return ['success' => false, 'message' => 'No se pudo contactar el monitor' . ($error !== '' ? ': ' . $error : '.')];
            }

            $decoded = json_decode($response, true);

            if ($status >= 200 && $status < 300 && is_array($decoded) && !empty($decoded['token'])) {
                return [
                    'success' => true,
                    'token' => $decoded['token'],
                    'installation' => isset($decoded['installation']) && is_array($decoded['installation']) ? $decoded['installation'] : null,
                ];
            }

            $message = is_array($decoded) && !empty($decoded['message'])
                ? (string) $decoded['message']
                : 'El monitor respondió con el código HTTP ' . $status . '.';

            return ['success' => false, 'message' => $message];
        } catch (Exception $exception) {
            return ['success' => false, 'message' => 'No se pudo contactar el monitor: ' . $exception->getMessage()];
        }
    }

    /**
     * Envía una petición JSON autenticada y devuelve si el monitor respondió 2xx.
     */
    private static function postJson($url, $body, $token, $timeout)
    {
        try {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $token,
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 1,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $response = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            return $response !== false && $status >= 200 && $status < 300;
        } catch (Exception $exception) {
            // La telemetría nunca debe interrumpir una sincronización.
            return false;
        }
    }
}