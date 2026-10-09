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

require_once dirname(__FILE__) . '/../../classes/YujuOAuth.php';

class PrestashopyujuOAuthModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        try {
            $code = Tools::getValue('code');
            $state = Tools::getValue('state');
            $error = Tools::getValue('error');

            if ($error) {
                $this->handleAuthError($error);
                return;
            }

            if (!$code) {
                throw new Exception('Código de autorización no recibido');
            }

            // Validar state si está presente (CSRF protection)
            $saved_state = Context::getContext()->cookie->yuju_oauth_state;
            if ($saved_state && $state && $state !== $saved_state) {
                throw new Exception('Estado OAuth inválido (posible ataque CSRF)');
            }
            if ($saved_state) {
                Context::getContext()->cookie->yuju_oauth_state = '';
            }

            $oauth = new YujuOAuth();

            // Yuju redirige con `code` (y opcionalmente `state`); lo cambia por el token con client_id + secret_key.
            // exchangeCodeForToken() lanza una excepción si falla.
            $oauth->exchangeCodeForToken($code, $state);
            $this->handleAuthSuccess();
        } catch (Exception $e) {
            $this->handleAuthError($e->getMessage());
        }
    }

    private function handleAuthSuccess()
    {
        $this->context->smarty->assign([
            'success' => true,
            'message' => 'Autorización exitosa. Puedes cerrar esta ventana.',
        ]);

        $this->setTemplate('module:prestashopyuju/views/templates/front/oauth_callback.tpl');
    }

    private function handleAuthError($error_message)
    {
        $this->context->smarty->assign([
            'success' => false,
            'error' => $error_message,
        ]);

        $this->setTemplate('module:prestashopyuju/views/templates/front/oauth_callback.tpl');
    }
}