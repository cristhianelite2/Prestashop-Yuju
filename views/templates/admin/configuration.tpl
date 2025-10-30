{*
* 2024 Yuju Integration
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
*}

{extends file="./layout.tpl"}

{block name="content"}
<div class="panel">
    <div class="panel-heading">
        <i class="icon-cogs"></i>
        Configuración de Integración Yuju
    </div>
    
    <div class="panel-body">
        {if isset($oauth_status) && $oauth_status.is_connected}
            <div class="alert alert-success">
                <i class="icon-check"></i>
                Conectado exitosamente a la API de Yuju
                <br>
                <small>Conectado como: {$oauth_status.user_info.name|default:'Desconocido'|escape:'html':'UTF-8'}</small>
            </div>
        {else}
            <div class="alert alert-warning">
                <i class="icon-warning"></i>
                No conectado a la API de Yuju. Por favor configure sus credenciales y autorice la conexión.
            </div>
        {/if}
        
        {if isset($api_test_result)}
            {if $api_test_result.success}
                <div class="alert alert-success">
                    <i class="icon-check"></i>
                    Prueba de conexión API exitosa
                </div>
            {else}
                <div class="alert alert-danger">
                    <i class="icon-remove"></i>
                    Prueba de conexión API falló: {$api_test_result.error|escape:'html':'UTF-8'}
                </div>
            {/if}
        {/if}
        
        <form id="configuration_form" class="defaultForm form-horizontal" action="{$current_index|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}" method="post" enctype="multipart/form-data">
            
            {* URLs Importantes Section *}
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="icon-link"></i>
                        URLs Importantes
                    </h3>
                </div>
                <div class="panel-body">
                    <div class="alert alert-info">
                        <i class="icon-info-circle"></i>
                        Estas URLs son requeridas para configurar su aplicación Yuju. Haga clic para copiarlas fácilmente.
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            URL de Términos y Condiciones
                        </label>
                        <div class="col-lg-9">
                            <div class="input-group">
                                <input type="text" class="form-control" value="{$yuju_urls.terms_conditions|escape:'html':'UTF-8'}" readonly id="terms_url">
                                <span class="input-group-btn">
                                    <button class="btn btn-default yuju-copy-button" type="button" data-copy-text="{$yuju_urls.terms_conditions|escape:'html':'UTF-8'}">
                                        <i class="icon-copy"></i> Copiar
                                    </button>
                                </span>
                            </div>
                            <p class="help-block">URL a la página de términos y condiciones del módulo (Úsela si no tiene una propia para el registro de aplicaciones Yuju)</p>
                        </div>
                    </div>
                    
                    {if $yuju_urls.auth_url}
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            URL de Autenticación
                        </label>
                        <div class="col-lg-9">
                            <div class="input-group">
                                <input type="text" class="form-control" value="{$yuju_urls.auth_url|escape:'html':'UTF-8'}" readonly id="auth_url">
                                <span class="input-group-btn">
                                    <button class="btn btn-default yuju-copy-button" type="button" data-copy-text="{$yuju_urls.auth_url|escape:'html':'UTF-8'}">
                                        <i class="icon-copy"></i> Copiar
                                    </button>
                                </span>
                            </div>
                            <p class="help-block">URL para autenticación OAuth con Yuju</p>
                        </div>
                    </div>
                    {/if}
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            URI de Redirección
                        </label>
                        <div class="col-lg-9">
                            <div class="input-group">
                                <input type="text" class="form-control" value="{$yuju_urls.redirect_uri|escape:'html':'UTF-8'}" readonly id="redirect_uri">
                                <span class="input-group-btn">
                                    <button class="btn btn-default yuju-copy-button" type="button" data-copy-text="{$yuju_urls.redirect_uri|escape:'html':'UTF-8'}">
                                        <i class="icon-copy"></i> Copiar
                                    </button>
                                </span>
                            </div>
                            <p class="help-block">URL de callback para autenticación OAuth (configure esto en su aplicación Yuju)</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            URL de Webhook
                        </label>
                        <div class="col-lg-9">
                            <div class="input-group">
                                <input type="text" class="form-control" value="{$yuju_urls.webhook_url|escape:'html':'UTF-8'}" readonly id="webhook_url">
                                <span class="input-group-btn">
                                    <button class="btn btn-default yuju-copy-button" type="button" data-copy-text="{$yuju_urls.webhook_url|escape:'html':'UTF-8'}">
                                        <i class="icon-copy"></i> Copiar
                                    </button>
                                </span>
                            </div>
                            <p class="help-block">URL para recibir webhooks de Yuju (configure esto en su aplicación Yuju)</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Dominios Permitidos
                        </label>
                        <div class="col-lg-9">
                            <div class="input-group">
                                <input type="text" class="form-control" value="{if isset($yuju_urls.combined_domains)}{$yuju_urls.combined_domains|escape:'html':'UTF-8'}{/if}" readonly id="allowed_domains">
                                <span class="input-group-btn">
                                    <button class="btn btn-default yuju-copy-button" type="button" data-copy-text="{if isset($yuju_urls.combined_domains)}{$yuju_urls.combined_domains|escape:'html':'UTF-8'}{/if}">
                                        <i class="icon-copy"></i> Copiar
                                    </button>
                                </span>
                            </div>
                            <p class="help-block">Dominios permitidos para autenticación (configure estos en la configuración de su aplicación Yuju). Incluye el dominio principal y la URI de redirección.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            {* API Configuration Section *}
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="icon-cloud"></i>
                        Configuración de API
                    </h3>
                </div>
                <div class="panel-body">
                    
                    {* CRON Configuration Alert *}
                    <div class="alert alert-info">
                        <h4><i class="icon-time"></i> Configuración de Tareas Automáticas (CRON)</h4>
                        <p><strong>Para que el módulo funcione correctamente, configure el siguiente CRON:</strong></p>
                        
                        <div style="background: #f5f5f5; padding: 15px; border-radius: 4px; margin: 10px 0;">
                            <p style="margin: 5px 0;"><strong>Frecuencia:</strong> Cada 5 minutos</p>
                            <p style="margin: 5px 0;"><strong>Comando CLI:</strong></p>
                            <code style="display: block; background: #fff; padding: 10px; border: 1px solid #ddd; border-radius: 3px; margin: 5px 0;">
                                */5 * * * * php {$smarty.server.DOCUMENT_ROOT}/modules/prestashopyuju/cron/cron.php
                            </code>
                            
                            <p style="margin: 15px 0 5px 0;"><strong>O mediante URL:</strong></p>
                            <div class="input-group" style="margin: 5px 0;">
                                <input type="text" class="form-control" value="https://{$smarty.server.HTTP_HOST}/modules/prestashopyuju/cron/cron.php" readonly id="cron_url">
                                <span class="input-group-btn">
                                    <button class="btn btn-default yuju-copy-button" type="button" data-copy-text="https://{$smarty.server.HTTP_HOST}/modules/prestashopyuju/cron/cron.php">
                                        <i class="icon-copy"></i> Copiar
                                    </button>
                                </span>
                            </div>
                            <code style="display: block; background: #fff; padding: 10px; border: 1px solid #ddd; border-radius: 3px; margin: 5px 0;">
                                */5 * * * * curl "https://{$smarty.server.HTTP_HOST}/modules/prestashopyuju/cron/cron.php"
                            </code>
                        </div>
                        
                        <p style="margin-top: 15px;"><strong>Tareas automáticas que se ejecutan:</strong></p>
                        <ul style="margin-left: 20px;">
                            <li><strong>Verificación de Token:</strong> Cada 12 horas - Verifica que el token de Yuju no haya expirado</li>
                            <li><em>Más tareas se agregarán en futuras actualizaciones...</em></li>
                        </ul>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Entorno
                        </label>
                        <div class="col-lg-9">
                            <select name="YUJU_ENVIRONMENT" class="form-control">
                                <option value="sandbox" {if $config.YUJU_ENVIRONMENT == 'sandbox'}selected{/if}>
                                    Sandbox (Pruebas)
                                </option>
                                <option value="production" {if $config.YUJU_ENVIRONMENT == 'production' || !$config.YUJU_ENVIRONMENT}selected{/if}>
                                    Producción
                                </option>
                            </select>
                            <p class="help-block">Seleccione el entorno de Yuju al que conectarse</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3 required">
                            ID de Cliente
                        </label>
                        <div class="col-lg-9">
                            <input type="text" name="YUJU_CLIENT_ID" value="{$config.YUJU_CLIENT_ID|escape:'html':'UTF-8'}" class="form-control" required>
                            <p class="help-block">Su ID de Cliente de la aplicación Yuju</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3 required">
                            Secreto de Cliente
                        </label>
                        <div class="col-lg-9">
                            <input type="password" name="YUJU_CLIENT_SECRET" value="{$config.YUJU_CLIENT_SECRET|escape:'html':'UTF-8'}" class="form-control" required>
                            <p class="help-block">Su Secreto de Cliente de la aplicación Yuju</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Secreto de Webhook
                        </label>
                        <div class="col-lg-9">
                            <input type="text" name="YUJU_WEBHOOK_SECRET" value="{$config.YUJU_WEBHOOK_SECRET|escape:'html':'UTF-8'}" class="form-control">
                            <p class="help-block">Clave secreta para verificación de firma de webhook</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Conectividad
                        </label>
                        <div class="col-lg-9">
                            <button type="button" id="yuju-test-connectivity" class="btn btn-info">
                                <i class="icon-plug"></i> Probar Conectividad
                            </button>
                            <p class="help-block">Probar la conexión con la API de Yuju y mostrar las tiendas disponibles</p>
                            <div id="connectivity-result" class="alert" style="display: none; margin-top: 10px;"></div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Prueba de Productos
                        </label>
                        <div class="col-lg-9">
                            <button type="button" id="yuju-test-products" class="btn btn-success">
                                <i class="icon-shopping-cart"></i> Probar API de Productos
                            </button>
                            <p class="help-block">Probar los endpoints de productos: ofertas, fichas técnicas y actualización masiva</p>
                            <div id="products-result" class="alert" style="display: none; margin-top: 10px;"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            {* Synchronization Settings *}
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="icon-refresh"></i>
                        Configuración de Sincronización
                    </h3>
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Seleccionar Tienda de PrestaShop
                        </label>
                        <div class="col-lg-9">
                            <select name="YUJU_PRESTASHOP_STORE_ID" class="form-control">
                                {if isset($prestashop_shops) && $prestashop_shops}
                                    {foreach from=$prestashop_shops item=shop}
                                        <option value="{$shop.id|escape:'html':'UTF-8'}" {if $config.YUJU_PRESTASHOP_STORE_ID == $shop.id}selected{/if}>
                                            {$shop.name|escape:'html':'UTF-8'}
                                        </option>
                                    {/foreach}
                                {else}
                                    <option value="">No hay tiendas de PrestaShop disponibles</option>
                                {/if}
                            </select>
                            <p class="help-block">Seleccione la tienda de PrestaShop para conectar con Yuju</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Idioma de la Tienda
                        </label>
                        <div class="col-lg-9">
                            <select name="YUJU_STORE_LANGUAGE" class="form-control">
                                {foreach from=Language::getLanguages(false) item=language}
                                    <option value="{$language.iso_code|escape:'html':'UTF-8'}" {if $config.YUJU_STORE_LANGUAGE == $language.iso_code}selected{/if}>
                                        {$language.name|escape:'html':'UTF-8'}
                                    </option>
                                {/foreach}
                            </select>
                            <p class="help-block">Idioma principal para sincronizar con Yuju</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Habilitar Sincronización
                        </label>
                        <div class="col-lg-9">
                            <span class="switch prestashop-switch fixed-width-lg">
                                <input type="radio" name="YUJU_SYNC_ENABLED" id="sync_on" value="1" {if $config.YUJU_SYNC_ENABLED !== '0'}checked="checked"{/if}>
                                <label for="sync_on">Sí</label>
                                <input type="radio" name="YUJU_SYNC_ENABLED" id="sync_off" value="0" {if $config.YUJU_SYNC_ENABLED === '0'}checked="checked"{/if}>
                                <label for="sync_off">No</label>
                                <a class="slide-button btn"></a>
                            </span>
                            <p class="help-block">Habilitar sincronización de productos (Sí por defecto)</p>
                        </div>
                    </div>
                    
                    <div class="form-group sync-dependent">
                        <label class="control-label col-lg-3">
                            Sincronizar Precios
                        </label>
                        <div class="col-lg-9">
                            <span class="switch prestashop-switch fixed-width-lg">
                                <input type="radio" name="YUJU_SYNC_PRICES" id="sync_prices_on" value="1" {if $config.YUJU_SYNC_PRICES !== '0'}checked="checked"{/if}>
                                <label for="sync_prices_on">Sí</label>
                                <input type="radio" name="YUJU_SYNC_PRICES" id="sync_prices_off" value="0" {if $config.YUJU_SYNC_PRICES === '0'}checked="checked"{/if}>
                                <label for="sync_prices_off">No</label>
                                <a class="slide-button btn"></a>
                            </span>
                            <p class="help-block">Habilitar sincronización de precios</p>
                        </div>
                    </div>
                    
                    <div class="form-group sync-dependent">
                        <label class="control-label col-lg-3">
                            Sincronizar Stock
                        </label>
                        <div class="col-lg-9">
                            <span class="switch prestashop-switch fixed-width-lg">
                                <input type="radio" name="YUJU_SYNC_STOCK" id="sync_stock_on" value="1" {if $config.YUJU_SYNC_STOCK !== '0'}checked="checked"{/if}>
                                <label for="sync_stock_on">Sí</label>
                                <input type="radio" name="YUJU_SYNC_STOCK" id="sync_stock_off" value="0" {if $config.YUJU_SYNC_STOCK === '0'}checked="checked"{/if}>
                                <label for="sync_stock_off">No</label>
                                <a class="slide-button btn"></a>
                            </span>
                            <p class="help-block">Habilitar sincronización de inventario</p>
                        </div>
                    </div>
                    
                    <div class="form-group sync-dependent">
                        <label class="control-label col-lg-3">
                            Sincronizar Imágenes
                        </label>
                        <div class="col-lg-9">
                            <span class="switch prestashop-switch fixed-width-lg">
                                <input type="radio" name="YUJU_SYNC_IMAGES" id="sync_images_on" value="1" {if $config.YUJU_SYNC_IMAGES !== '0'}checked="checked"{/if}>
                                <label for="sync_images_on">Sí</label>
                                <input type="radio" name="YUJU_SYNC_IMAGES" id="sync_images_off" value="0" {if $config.YUJU_SYNC_IMAGES === '0'}checked="checked"{/if}>
                                <label for="sync_images_off">No</label>
                                <a class="slide-button btn"></a>
                            </span>
                            <p class="help-block">Habilitar sincronización de imágenes de productos</p>
                        </div>
                    </div>
                    
                    <div class="form-group sync-dependent">
                        <label class="control-label col-lg-3">
                            Sincronizar Órdenes
                        </label>
                        <div class="col-lg-9">
                            <span class="switch prestashop-switch fixed-width-lg">
                                <input type="radio" name="YUJU_SYNC_ORDERS" id="sync_orders_on" value="1" {if $config.YUJU_SYNC_ORDERS !== '0'}checked="checked"{/if}>
                                <label for="sync_orders_on">Sí</label>
                                <input type="radio" name="YUJU_SYNC_ORDERS" id="sync_orders_off" value="0" {if $config.YUJU_SYNC_ORDERS === '0'}checked="checked"{/if}>
                                <label for="sync_orders_off">No</label>
                                <a class="slide-button btn"></a>
                            </span>
                            <p class="help-block">Habilitar sincronización de órdenes</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Forzar Actualización
                        </label>
                        <div class="col-lg-9">
                            <button type="button" id="yuju-force-update" class="btn btn-warning">
                                <i class="icon-refresh"></i> Forzar Actualización Masiva
                            </button>
                            <p class="help-block">Esta funcionalidad forzará el envío masivo de información del producto ignorando lo ya enviado, por lo cual se recomienda un uso cauteloso.</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Limpieza de HTML en Descripción
                        </label>
                        <div class="col-lg-9">
                            <span class="switch prestashop-switch fixed-width-lg">
                                <input type="radio" name="YUJU_CLEAN_HTML" id="clean_html_on" value="1" {if $config.YUJU_CLEAN_HTML !== '0'}checked="checked"{/if}>
                                <label for="clean_html_on">Sí</label>
                                <input type="radio" name="YUJU_CLEAN_HTML" id="clean_html_off" value="0" {if $config.YUJU_CLEAN_HTML === '0'}checked="checked"{/if}>
                                <label for="clean_html_off">No</label>
                                <a class="slide-button btn"></a>
                            </span>
                            <p class="help-block">Limpiar etiquetas HTML de las descripciones antes de enviarlas a Yuju</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Frecuencia de Sincronización (segundos)
                        </label>
                        <div class="col-lg-9">
                            <input type="number" name="YUJU_SYNC_FREQUENCY" value="{$config.YUJU_SYNC_FREQUENCY|default:3600|escape:'html':'UTF-8'}" class="form-control" min="60">
                            <p class="help-block">Frecuencia de sincronización automática en segundos (3600 por defecto - 1 hora)</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Tamaño de Lote
                        </label>
                        <div class="col-lg-9">
                            <input type="number" name="YUJU_BATCH_SIZE" value="{$config.YUJU_BATCH_SIZE|default:100|escape:'html':'UTF-8'}" class="form-control" min="1" max="500">
                            <p class="help-block"><i class="icon-cubes"></i> Cantidad de productos a procesar por lote (ej: 100 productos por envío)</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            <strong>Frecuencia entre Lotes</strong>
                        </label>
                        <div class="col-lg-9">
                            <div class="input-group">
                                <input type="number" name="YUJU_BATCH_FREQUENCY" value="{$config.YUJU_BATCH_FREQUENCY|default:60|escape:'html':'UTF-8'}" class="form-control" min="30" max="3600">
                                <span class="input-group-addon">segundos</span>
                            </div>
                            <p class="help-block"><i class="icon-clock-o"></i> Tiempo de espera entre cada lote al procesar (ej: 60 segundos entre cada lote de 100)</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            <strong>Máximo Descargas Diarias del JSON</strong>
                        </label>
                        <div class="col-lg-9">
                            <select name="YUJU_MAX_DAILY_SYNCS" class="form-control">
                                <option value="1" {if $config.YUJU_MAX_DAILY_SYNCS == '1'}selected{/if}>1 vez al día</option>
                                <option value="2" {if $config.YUJU_MAX_DAILY_SYNCS == '2' || !$config.YUJU_MAX_DAILY_SYNCS}selected{/if}>2 veces al día (recomendado)</option>
                                <option value="3" {if $config.YUJU_MAX_DAILY_SYNCS == '3'}selected{/if}>3 veces al día</option>
                                <option value="4" {if $config.YUJU_MAX_DAILY_SYNCS == '4'}selected{/if}>4 veces al día</option>
                                <option value="5" {if $config.YUJU_MAX_DAILY_SYNCS == '5'}selected{/if}>5 veces al día (máximo)</option>
                            </select>
                            <p class="help-block">
                                <i class="icon-info-circle"></i> <strong>Límite de descargas del JSON desde Yuju</strong> (cada 24h ÷ valor = intervalo).<br>
                                <strong>IMPORTANTE:</strong> El endpoint <code>/products-offer-report</code> devuelve una URL con el JSON que debe descargarse.<br>
                                Ej: 2 veces/día = descarga cada 12 horas. El JSON se guarda localmente en <code>cache/yuju_products.json</code>.<br>
                                <strong>Yuju limita</strong> las descargas a máximo cada 3 horas.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            {* Logging Settings *}
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="icon-file-text"></i>
                        Configuración de Registro
                    </h3>
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Habilitar Logs
                        </label>
                        <div class="col-lg-9">
                            <span class="switch prestashop-switch fixed-width-lg">
                                <input type="radio" name="YUJU_LOGGING_ENABLED" id="logging_on" value="1" {if $config.YUJU_LOGGING_ENABLED !== '0'}checked="checked"{/if}>
                                <label for="logging_on">Sí</label>
                                <input type="radio" name="YUJU_LOGGING_ENABLED" id="logging_off" value="0" {if $config.YUJU_LOGGING_ENABLED === '0'}checked="checked"{/if}>
                                <label for="logging_off">No</label>
                                <a class="slide-button btn"></a>
                            </span>
                            <p class="help-block">Habilitar el registro de actividades (Sí por defecto)</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Nivel de Registro
                        </label>
                        <div class="col-lg-9">
                            <select name="YUJU_LOG_LEVEL" class="form-control">
                                <option value="error" {if $config.YUJU_LOG_LEVEL == 'error'}selected{/if}>
                                    Solo errores
                                </option>
                                <option value="warning" {if $config.YUJU_LOG_LEVEL == 'warning'}selected{/if}>
                                    Advertencias y superiores
                                </option>
                                <option value="info" {if $config.YUJU_LOG_LEVEL == 'info' || !$config.YUJU_LOG_LEVEL}selected{/if}>
                                    Info, errores y advertencias (por defecto)
                                </option>
                                <option value="debug" {if $config.YUJU_LOG_LEVEL == 'debug'}selected{/if}>
                                    Debug (todo)
                                </option>
                            </select>
                            <p class="help-block">Nivel de detalle para los registros</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Retención de Registros (días)
                        </label>
                        <div class="col-lg-9">
                            <input type="number" name="YUJU_LOG_RETENTION" value="{$config.YUJU_LOG_RETENTION|default:30|escape:'html':'UTF-8'}" class="form-control" min="1">
                            <p class="help-block">Número de días para mantener archivos de registro (30 por defecto)</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="panel-footer">
                <button type="submit" value="1" id="configuration_form_submit_btn" name="submitConfiguration" class="btn btn-default pull-right">
                    <i class="process-icon-save"></i> Guardar
                </button>
                
                {if isset($oauth_status) && !$oauth_status.is_connected && $config.YUJU_CLIENT_ID && $config.YUJU_CLIENT_SECRET}
                    <a href="{$oauth_auth_url|escape:'html':'UTF-8'}" class="btn btn-primary">
                        <i class="icon-key"></i> Autorizar con Yuju
                    </a>
                {/if}
                
                {if $config.YUJU_CLIENT_ID && $config.YUJU_CLIENT_SECRET}
                    <button type="submit" name="testConnection" class="btn btn-info">
                        <i class="icon-check"></i> Probar Conexión API
                    </button>
                {/if}
            </div>
        </form>
    </div>
</div>
{/block}

<script type="text/javascript">
// JavaScript functionality is now handled in admin.js
// This ensures compatibility with PrestaShop 8 module loading system


// Handle sync dependencies
$(document).ready(function() {
    function toggleSyncDependentFields() {
        var syncEnabled = $('input[name="YUJU_SYNC_ENABLED"]:checked').val() === '1';
        $('.sync-dependent').toggle(syncEnabled);
        if (!syncEnabled) {
            $('.sync-dependent input[type="radio"][value="0"]').prop('checked', true);
        }
    }
    
    $('input[name="YUJU_SYNC_ENABLED"]').change(toggleSyncDependentFields);
    toggleSyncDependentFields();
    
    // Force update confirmation
    $('#yuju-force-update').click(function() {
        if (confirm('¿Está seguro de que desea forzar la actualización masiva? Esta acción enviará todos los productos a Yuju ignorando el estado de sincronización anterior.')) {
            // Add your force update logic here
            $(this).prop('disabled', true).html('<i class="icon-spin icon-refresh"></i> Procesando...');
        }
    });
});
</script>

<style>
/* Copy button styles are now in admin.css */
.input-group {
    margin-bottom: 5px;
}

.panel .alert {
    margin-bottom: 20px;
}

.form-group .help-block {
    margin-top: 8px;
    font-size: 12px;
    color: #666;
}

.sync-dependent {
    margin-left: 20px;
    border-left: 3px solid #ddd;
    padding-left: 15px;
}

.sync-dependent.hidden {
    display: none;
}
</style>

<script type="text/javascript">
$(document).ready(function() {
    // Inicializar YujuAdmin con la configuración necesaria
    if (typeof YujuAdmin !== 'undefined') {
        YujuAdmin.init({
            ajaxUrl: '{$ajax_url|escape:'javascript':'UTF-8'}',
            token: '{$token|escape:'javascript':'UTF-8'}'
        });
        console.log('YujuAdmin initialized successfully');
    } else {
        console.error('YujuAdmin object not found. Check if admin.js is loaded.');
    }
});
</script>
