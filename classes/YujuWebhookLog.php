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

class YujuWebhookLog extends ObjectModel
{
    /** @var int */
    public $id;

    /** @var string */
    public $event_type;

    /** @var string */
    public $entity_id;

    /** @var string */
    public $payload;

    /** @var string */
    public $headers;

    /** @var string */
    public $status;

    /** @var string */
    public $response;

    /** @var string */
    public $error_message;

    /** @var string */
    public $received_at;

    /** @var string */
    public $processed_at;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table' => 'yuju_webhook_logs',
        'primary' => 'id',
        'fields' => [
            'event_type' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isCleanHtml',
                'required' => true,
                'size' => 100,
            ],
            'entity_id' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isCleanHtml',
                'size' => 255,
            ],
            'payload' => [
                'type' => self::TYPE_HTML,
                'validate' => 'isCleanHtml',
            ],
            'headers' => [
                'type' => self::TYPE_HTML,
                'validate' => 'isCleanHtml',
            ],
            'status' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'size' => 20,
            ],
            'response' => [
                'type' => self::TYPE_HTML,
                'validate' => 'isCleanHtml',
            ],
            'error_message' => [
                'type' => self::TYPE_HTML,
                'validate' => 'isCleanHtml',
            ],
            'received_at' => [
                'type' => self::TYPE_DATE,
                'validate' => 'isDate',
                'required' => true,
            ],
            'processed_at' => [
                'type' => self::TYPE_DATE,
                'validate' => 'isDate',
            ],
        ],
    ];
}
