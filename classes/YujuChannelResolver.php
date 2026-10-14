<?php
/**
 * Resuelve y persiste los datos del canal de una orden Yuju.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/../config/config.php';

class YujuChannelResolver
{
    const CACHE_KEY = 'YUJU_ACCOUNT_INFO_CACHE';
    const UPDATED_KEY = 'YUJU_ACCOUNT_INFO_UPDATED_AT';
    const LAST_ATTEMPT_KEY = 'YUJU_ACCOUNT_INFO_LAST_ATTEMPT';

    public static function getCachedAccount()
    {
        $raw = YujuConfig::get(self::CACHE_KEY, '');
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    public static function storeAccount(array $response)
    {
        $payload = $response;
        if (isset($response['data']) && is_array($response['data'])) {
            $payload = $response['data'];
        }

        if (empty($payload['id_account']) && empty($payload['channels']) && empty($response['id_account'])) {
            return false;
        }

        $channels = [];
        if (!empty($payload['channels']) && is_array($payload['channels'])) {
            foreach ($payload['channels'] as $channel) {
                if (!is_array($channel)) {
                    continue;
                }
                $channels[] = [
                    'id_channel' => isset($channel['id_channel']) ? (string) $channel['id_channel'] : '',
                    'name' => trim((string) ($channel['name'] ?? '')),
                    'generic_name' => trim((string) ($channel['generic_name'] ?? '')),
                ];
            }
        }

        $normalized = [
            'id_account' => $payload['id_account'] ?? null,
            'account_name' => $payload['account_name'] ?? null,
            'id_shop' => $payload['id_shop'] ?? null,
            'shop_name' => $payload['shop_name'] ?? null,
            'channels' => $channels,
        ];

        YujuConfig::set(self::CACHE_KEY, $normalized, 'json');
        YujuConfig::set(self::UPDATED_KEY, date('Y-m-d H:i:s'), 'string');

        $map = YujuConfig::get('YUJU_CHANNEL_MARKETPLACE_MAP', []);
        if (is_string($map)) {
            $decoded = json_decode($map, true);
            $map = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($map)) {
            $map = [];
        }
        foreach ($channels as $channel) {
            $id = (string) ($channel['id_channel'] ?? '');
            $generic = (string) ($channel['generic_name'] ?? '');
            $slug = preg_replace('/[^a-z0-9]/', '', strtolower($generic));
            if ($id !== '' && $slug !== '' && (!isset($map[$id]) || $map[$id] === '' || strpos((string) $map[$id], 'channel') === 0)) {
                $map[$id] = $slug;
            }
        }
        YujuConfig::set('YUJU_CHANNEL_MARKETPLACE_MAP', $map, 'json');

        return true;
    }

    public static function findNameInCache($idChannel)
    {
        $idChannel = trim((string) $idChannel);
        if ($idChannel === '') {
            return '';
        }
        $account = self::getCachedAccount();
        $channels = isset($account['channels']) && is_array($account['channels']) ? $account['channels'] : [];
        foreach ($channels as $channel) {
            if (!is_array($channel) || (string) ($channel['id_channel'] ?? '') !== $idChannel) {
                continue;
            }
            $name = trim((string) ($channel['name'] ?? ''));
            if ($name !== '') {
                return $name;
            }
            $generic = trim((string) ($channel['generic_name'] ?? ''));
            return $generic;
        }
        return '';
    }

    public static function resolveName($idChannel)
    {
        $name = self::findNameInCache($idChannel);
        if ($name !== '') {
            return $name;
        }

        // No consultar /account más de una vez por hora si Yuju está fallando o el canal no aparece.
        $lastAttempt = (string) YujuConfig::get(self::LAST_ATTEMPT_KEY, '');
        if ($lastAttempt !== '' && (time() - strtotime($lastAttempt)) < 3600) {
            return '';
        }
        YujuConfig::set(self::LAST_ATTEMPT_KEY, date('Y-m-d H:i:s'), 'string');

        try {
            require_once dirname(__FILE__) . '/YujuApiClient.php';
            $api = new YujuApiClient();
            $response = $api->getAccount();
            if (is_array($response) && (empty($response['success']) === false || isset($response['id_account']) || isset($response['data']['id_account']))) {
                self::storeAccount($response);
            }
        } catch (Throwable $e) {
            // La vista de pedidos no debe fallar si Yuju no está disponible.
        }

        return self::findNameInCache($idChannel);
    }

    public static function resolveOrderMetadata($yujuOrderId, $idChannel = '', $channelName = '')
    {
        $yujuOrderId = trim((string) $yujuOrderId);
        $idChannel = trim((string) $idChannel);
        $channelName = trim((string) $channelName);

        if ($yujuOrderId !== '' && ($idChannel === '' || $channelName === '')) {
            $attemptKey = 'YUJU_ORDER_CHANNEL_TRY_' . strtoupper(substr(md5($yujuOrderId), 0, 12));
            $lastAttempt = (string) YujuConfig::get($attemptKey, '');
            $attemptedRecently = $lastAttempt !== '' && (time() - (int) strtotime($lastAttempt)) < 86400;

            if (!$attemptedRecently) {
                YujuConfig::set($attemptKey, date('Y-m-d H:i:s'), 'string');
                try {
                    require_once dirname(__FILE__) . '/YujuApiClient.php';
                    $api = new YujuApiClient();
                    $response = $api->getOrder($yujuOrderId, $idChannel !== '' ? $idChannel : null);
                    if (is_array($response)) {
                        $payload = $response['data'] ?? $response;
                        if (isset($payload['order_details']) && is_array($payload['order_details'])) {
                            $payload = array_merge($payload['order_details'], $payload);
                        }
                        if (isset($payload['orders'][0]) && is_array($payload['orders'][0])) {
                            $payload = $payload['orders'][0];
                        }
                        if ($idChannel === '') {
                            $idChannel = trim((string) ($payload['id_channel'] ?? $payload['channel_id'] ?? ''));
                        }
                        if ($channelName === '') {
                            $channelName = trim((string) ($payload['channel_name'] ?? ($payload['channel']['name'] ?? '')));
                        }
                    }
                } catch (Throwable $e) {
                    // El detalle de pedido puede no estar disponible sin id_channel.
                }
            }
        }

        if ($channelName === '' && $idChannel !== '') {
            $channelName = self::resolveName($idChannel);
        }

        return ['id_channel' => $idChannel, 'channel_name' => $channelName];
    }

    public static function persistOrderMetadata($yujuOrderId, $idChannel, $channelName = '')
    {
        $yujuOrderId = trim((string) $yujuOrderId);
        $idChannel = trim((string) $idChannel);
        $channelName = trim((string) $channelName);
        if ($yujuOrderId === '' || ($idChannel === '' && $channelName === '')) {
            return false;
        }

        if ($channelName === '' && $idChannel !== '') {
            $channelName = self::resolveName($idChannel);
        }

        try {
            $table = _DB_PREFIX_ . 'yuju_order_mapping';
            $columns = Db::getInstance()->executeS('SHOW COLUMNS FROM `' . bqSQL($table) . '`');
            $available = [];
            if (is_array($columns)) {
                foreach ($columns as $column) {
                    $available[$column['Field']] = true;
                }
            }
            $sets = [];
            if (isset($available['id_channel']) && $idChannel !== '') {
                $sets[] = '`id_channel` = "' . pSQL($idChannel) . '"';
            }
            if (isset($available['channel_name']) && $channelName !== '') {
                $sets[] = '`channel_name` = "' . pSQL($channelName) . '"';
            }
            if (empty($sets)) {
                return false;
            }
            $sets[] = '`updated_at` = "' . pSQL(date('Y-m-d H:i:s')) . '"';
            return Db::getInstance()->execute(
                'UPDATE `' . bqSQL($table) . '` SET ' . implode(', ', $sets)
                . ' WHERE `yuju_order_id` = "' . pSQL($yujuOrderId) . '"'
            );
        } catch (Throwable $e) {
            return false;
        }
    }
}
