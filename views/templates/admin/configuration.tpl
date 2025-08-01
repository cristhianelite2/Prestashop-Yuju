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
                            <p class="help-block">URL a la página de términos y condiciones del módulo (úsela si no tiene una propia para el registro de aplicaciones Yuju)</p>
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
                            {foreach from=$yuju_urls.allowed_domains item=domain name=domains}
                                <div class="input-group" style="margin-bottom: 5px;">
                                    <input type="text" class="form-control" value="{$domain|escape:'html':'UTF-8'}" readonly id="domain_{$smarty.foreach.domains.index}">
                                    <span class="input-group-btn">
                                        <button class="btn btn-default yuju-copy-button" type="button" data-copy-text="{$domain|escape:'html':'UTF-8'}">
                                            <i class="icon-copy"></i> Copiar
                                        </button>
                                    </span>
                                </div>
                            {/foreach}
                            <p class="help-block">Dominios permitidos para autenticación (configure estos en la configuración de su aplicación Yuju)</p>
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
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Entorno
                        </label>
                        <div class="col-lg-9">
                            <select name="YUJU_ENVIRONMENT" class="form-control">
                                <option value="sandbox" {if $config.YUJU_ENVIRONMENT == 'sandbox'}selected{/if}>
                                    Sandbox (Pruebas)
                                </option>
                                <option value="production" {if $config.YUJU_ENVIRONMENT == 'production'}selected{/if}>
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
                            Habilitar Sincronización Automática
                        </label>
                        <div class="col-lg-9">
                            <span class="switch prestashop-switch fixed-width-lg">
                                <input type="radio" name="YUJU_AUTO_SYNC" id="auto_sync_on" value="1" {if $config.YUJU_AUTO_SYNC}checked="checked"{/if}>
                                <label for="auto_sync_on">Sí</label>
                                <input type="radio" name="YUJU_AUTO_SYNC" id="auto_sync_off" value="0" {if !$config.YUJU_AUTO_SYNC}checked="checked"{/if}>
                                <label for="auto_sync_off">No</label>
                                <a class="slide-button btn"></a>
                            </span>
                            <p class="help-block">Sincronizar datos automáticamente cuando ocurran cambios</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Frecuencia de Sincronización (segundos)
                        </label>
                        <div class="col-lg-9">
                            <input type="number" name="YUJU_SYNC_FREQUENCY" value="{$config.YUJU_SYNC_FREQUENCY|default:300|escape:'html':'UTF-8'}" class="form-control" min="60">
                            <p class="help-block">Con qué frecuencia ejecutar la sincronización en segundo plano (mínimo 60 segundos)</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Tamaño de Lote
                        </label>
                        <div class="col-lg-9">
                            <input type="number" name="YUJU_BATCH_SIZE" value="{$config.YUJU_BATCH_SIZE|default:50|escape:'html':'UTF-8'}" class="form-control" min="1" max="500">
                            <p class="help-block">Número de elementos a procesar por lote (1-500)</p>
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
                                <option value="info" {if $config.YUJU_LOG_LEVEL == 'info'}selected{/if}>
                                    Información y superiores
                                </option>
                                <option value="debug" {if $config.YUJU_LOG_LEVEL == 'debug'}selected{/if}>
                                    Debug (todo)
                                </option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Retención de Registros (días)
                        </label>
                        <div class="col-lg-9">
                            <input type="number" name="YUJU_LOG_RETENTION" value="{$config.YUJU_LOG_RETENTION|default:30|escape:'html':'UTF-8'}" class="form-control" min="1">
                            <p class="help-block">Número de días para mantener archivos de registro</p>
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
$(document).ready(function() {
    // Form validation
    $('#configuration_form').on('submit', function(e) {
        var clientId = $('input[name="YUJU_CLIENT_ID"]').val();
        var clientSecret = $('input[name="YUJU_CLIENT_SECRET"]').val();
        
        if (!clientId || !clientSecret) {
            e.preventDefault();
            alert('Por favor complete tanto el ID de Cliente como el Secreto de Cliente');
            return false;
        }
    });
    
    // Copy functionality is now handled by admin.js
    
    // Initialize YujuAdmin
    if (typeof YujuAdmin !== 'undefined') {
        YujuAdmin.init({
            ajaxUrl: '{$ajax_url|escape:'javascript':'UTF-8'}',
            token: '{$token|escape:'javascript':'UTF-8'}'
        });
    }
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
</style>