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

<div class="yuju-module">
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-cogs"></i>
            {l s='Configuración OAuth de Yuju' mod='prestashopyuju'}
        </div>
        
        <div class="panel-body">
            {if isset($oauth_status) && $oauth_status.is_connected}
                <div class="alert alert-success">
                    <i class="icon-check"></i>
                    {l s='Conexión exitosa con la API de Yuju' mod='prestashopyuju'}
                    <br>
                    <small>{l s='Conectado como:' mod='prestashopyuju'} {$oauth_status.user_info.name|default:'Desconocido'|escape:'html':'UTF-8'}</small>
                </div>
                
                <div class="row">
                    <div class="col-lg-6">
                        <h4>{l s='Detalles de conexión' mod='prestashopyuju'}</h4>
                        <dl class="dl-horizontal">
                            <dt>{l s='Entorno:' mod='prestashopyuju'}</dt>
                            <dd><span class="label label-info">{$oauth_status.environment|upper|escape:'html':'UTF-8'}</span></dd>
                            
                            <dt>{l s='ID de cliente:' mod='prestashopyuju'}</dt>
                            <dd><code>{$oauth_status.client_id|escape:'html':'UTF-8'}</code></dd>
                            
                            <dt>{l s='Conectado el:' mod='prestashopyuju'}</dt>
                            <dd>{$oauth_status.connected_at|escape:'html':'UTF-8'}</dd>
                            
                            <dt>{l s='El token vence:' mod='prestashopyuju'}</dt>
                            <dd>{$oauth_status.expires_at|escape:'html':'UTF-8'}</dd>
                        </dl>
                    </div>
                    
                    <div class="col-lg-6">
                        <h4>{l s='Estadísticas de la API' mod='prestashopyuju'}</h4>
                        {if isset($api_stats)}
                            <dl class="dl-horizontal">
                                <dt>{l s='Solicitudes totales:' mod='prestashopyuju'}</dt>
                                <dd>{$api_stats.total_requests|default:0|escape:'html':'UTF-8'}</dd>
                                
                                <dt>{l s='Exitosas:' mod='prestashopyuju'}</dt>
                                <dd>{$api_stats.successful_requests|default:0|escape:'html':'UTF-8'}</dd>
                                
                                <dt>{l s='Fallidas:' mod='prestashopyuju'}</dt>
                                <dd>{$api_stats.failed_requests|default:0|escape:'html':'UTF-8'}</dd>
                                
                                <dt>{l s='Última solicitud:' mod='prestashopyuju'}</dt>
                                <dd>{$api_stats.last_request|default:'-'|escape:'html':'UTF-8'}</dd>
                            </dl>
                        {/if}
                    </div>
                </div>
                
                <div class="panel-footer">
                    <button type="button" class="btn btn-warning" onclick="if(confirm('{l s='¿Seguro que deseas revocar el token de OAuth?' mod='prestashopyuju'}')) { window.location.href = '{$current_index|escape:'javascript':'UTF-8'}&token={$token|escape:'javascript':'UTF-8'}&revokeToken=1'; }">
                        <i class="icon-unlink"></i> {l s='Revocar token' mod='prestashopyuju'}
                    </button>
                    
                    <button type="button" class="btn btn-info" id="yuju-test-api">
                        <i class="icon-check"></i> {l s='Probar conexión' mod='prestashopyuju'}
                    </button>
                </div>
            {else}
                <div class="alert alert-warning">
                    <i class="icon-warning"></i>
                    {l s='No estás conectado a la API de Yuju. Guarda tu ID de cliente y secreto, y ejecuta la prueba de conectividad; también puedes conectar desde Yuju (Configuraciones > Aplicaciones > Conectar).' mod='prestashopyuju'}
                </div>
                
                <form action="{$current_index|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}" method="post" class="form-horizontal">
                    <div class="form-group">
                        <label class="control-label col-lg-3 required">
                            {l s='Entorno' mod='prestashopyuju'}
                        </label>
                        <div class="col-lg-9">
                            <select name="environment" class="form-control" required>
                                <option value="sandbox" {if $oauth_status.environment == 'sandbox'}selected{/if}>
                                    {l s='Pruebas (entorno sandbox)' mod='prestashopyuju'}
                                </option>
                                <option value="production" {if $oauth_status.environment == 'production'}selected{/if}>
                                    {l s='Producción' mod='prestashopyuju'}
                                </option>
                            </select>
                            <p class="help-block">{l s='Selecciona el entorno de Yuju al que deseas conectarte' mod='prestashopyuju'}</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3 required">
                            {l s='ID de cliente' mod='prestashopyuju'}
                        </label>
                        <div class="col-lg-9">
                            <input type="text" name="client_id" value="{$oauth_status.client_id|escape:'html':'UTF-8'}" class="form-control" required>
                            <p class="help-block">{l s='ID de cliente de tu aplicación de Yuju' mod='prestashopyuju'}</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3 required">
                            {l s='Secreto de cliente' mod='prestashopyuju'}
                        </label>
                        <div class="col-lg-9">
                            <input type="password" name="client_secret" value="{$oauth_status.client_secret|escape:'html':'UTF-8'}" class="form-control" required>
                            <p class="help-block">{l s='Secreto de cliente de tu aplicación de Yuju' mod='prestashopyuju'}</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Secreto de Webhook
                        </label>
                        <div class="col-lg-9">
                            <input type="text" name="webhook_secret" value="{$oauth_status.webhook_secret|escape:'html':'UTF-8'}" class="form-control">
                            <p class="help-block">Clave secreta para validación de webhook (opcional pero recomendado)</p>
                        </div>
                    </div>
                    
                    <div class="panel-footer">
                        <button type="submit" name="submitOAuthConfig" class="btn btn-primary">
                            <i class="icon-save"></i> {l s='Guardar configuración' mod='prestashopyuju'}
                        </button>
                        <p class="help-block">
                            La conexión se completa desde Yuju (Configuraciones &gt; Aplicaciones &gt; Conectar):
                            Yuju redirige a la URL de Autenticación con el parámetro <code>code</code> y el módulo
                            lo cambia por el token automáticamente.
                        </p>
                    </div>
                </form>
            {/if}
        </div>
    </div>
    
    {* General Configuration Panel *}
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-wrench"></i>
            {l s='Configuración general' mod='prestashopyuju'}
        </div>
        
        <div class="panel-body">
            <form action="{$current_index|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}" method="post" class="form-horizontal">
                <div class="form-group">
                    <label class="control-label col-lg-3">
                        {l s='Sincronización automática' mod='prestashopyuju'}
                    </label>
                    <div class="col-lg-9">
                        <span class="switch prestashop-switch fixed-width-lg">
                            <input type="radio" name="auto_sync" id="auto_sync_on" value="1" {if Configuration::get('YUJU_AUTO_SYNC')}checked="checked"{/if}>
                            <label for="auto_sync_on">{l s='Sí' mod='prestashopyuju'}</label>
                            <input type="radio" name="auto_sync" id="auto_sync_off" value="0" {if !Configuration::get('YUJU_AUTO_SYNC')}checked="checked"{/if}>
                            <label for="auto_sync_off">{l s='No' mod='prestashopyuju'}</label>
                            <a class="slide-button btn"></a>
                        </span>
                        <p class="help-block">{l s='Activar la sincronización automática cuando se actualicen productos' mod='prestashopyuju'}</p>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="control-label col-lg-3">
                        {l s='Frecuencia de sincronización' mod='prestashopyuju'}
                    </label>
                    <div class="col-lg-9">
                        <select name="sync_frequency" class="form-control">
                            <option value="5" {if Configuration::get('YUJU_SYNC_FREQUENCY') == '5'}selected{/if}>{l s='Cada 5 minutos' mod='prestashopyuju'}</option>
                            <option value="15" {if Configuration::get('YUJU_SYNC_FREQUENCY') == '15'}selected{/if}>{l s='Cada 15 minutos' mod='prestashopyuju'}</option>
                            <option value="30" {if Configuration::get('YUJU_SYNC_FREQUENCY') == '30'}selected{/if}>{l s='Cada 30 minutos' mod='prestashopyuju'}</option>
                            <option value="60" {if Configuration::get('YUJU_SYNC_FREQUENCY') == '60'}selected{/if}>{l s='Cada hora' mod='prestashopyuju'}</option>
                            <option value="360" {if Configuration::get('YUJU_SYNC_FREQUENCY') == '360'}selected{/if}>{l s='Cada 6 horas' mod='prestashopyuju'}</option>
                            <option value="1440" {if Configuration::get('YUJU_SYNC_FREQUENCY') == '1440'}selected{/if}>{l s='Diariamente' mod='prestashopyuju'}</option>
                        </select>
                        <p class="help-block">{l s='Frecuencia con la que se ejecutará la sincronización automática' mod='prestashopyuju'}</p>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="control-label col-lg-3">
                        {l s='Tamaño del lote' mod='prestashopyuju'}
                    </label>
                    <div class="col-lg-9">
                        <input type="number" name="batch_size" value="{Configuration::get('YUJU_BATCH_SIZE')|default:50|escape:'html':'UTF-8'}" class="form-control" min="1" max="500">
                        <p class="help-block">{l s='Número de elementos que se procesan por lote (1-500)' mod='prestashopyuju'}</p>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="control-label col-lg-3">
                        {l s='Nivel de registro' mod='prestashopyuju'}
                    </label>
                    <div class="col-lg-9">
                        <select name="log_level" class="form-control">
                            <option value="error" {if Configuration::get('YUJU_LOG_LEVEL') == 'error'}selected{/if}>{l s='Solo errores' mod='prestashopyuju'}</option>
                            <option value="warning" {if Configuration::get('YUJU_LOG_LEVEL') == 'warning'}selected{/if}>{l s='Advertencias y errores' mod='prestashopyuju'}</option>
                            <option value="info" {if Configuration::get('YUJU_LOG_LEVEL') == 'info'}selected{/if}>{l s='Información y niveles superiores' mod='prestashopyuju'}</option>
                            <option value="debug" {if Configuration::get('YUJU_LOG_LEVEL') == 'debug'}selected{/if}>{l s='Depuración (todos los mensajes)' mod='prestashopyuju'}</option>
                        </select>
                        <p class="help-block">{l s='Nivel de detalle de los registros' mod='prestashopyuju'}</p>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="control-label col-lg-3">
                        {l s='Retención de registros' mod='prestashopyuju'}
                    </label>
                    <div class="col-lg-9">
                        <select name="log_retention" class="form-control">
                            <option value="7" {if Configuration::get('YUJU_LOG_RETENTION') == '7'}selected{/if}>{l s='7 días' mod='prestashopyuju'}</option>
                            <option value="30" {if Configuration::get('YUJU_LOG_RETENTION') == '30'}selected{/if}>{l s='30 días' mod='prestashopyuju'}</option>
                            <option value="90" {if Configuration::get('YUJU_LOG_RETENTION') == '90'}selected{/if}>{l s='90 días' mod='prestashopyuju'}</option>
                            <option value="365" {if Configuration::get('YUJU_LOG_RETENTION') == '365'}selected{/if}>{l s='1 año' mod='prestashopyuju'}</option>
                        </select>
                        <p class="help-block">{l s='Tiempo durante el que se conservarán los archivos de registro' mod='prestashopyuju'}</p>
                    </div>
                </div>
                
                <div class="panel-footer">
                    <button type="submit" name="submitGeneralConfig" class="btn btn-primary">
                        <i class="icon-save"></i> {l s='Guardar ajustes' mod='prestashopyuju'}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Configuration for YujuAdmin
var yujuAdminConfig = {
    ajaxUrl: '{$current_index|escape:'javascript':'UTF-8'}&token={$token|escape:'javascript':'UTF-8'}',
    token: '{$token|escape:'javascript':'UTF-8'}',
    refreshInterval: 5000
};
</script>