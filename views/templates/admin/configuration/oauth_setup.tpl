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
            {l s='Yuju OAuth Configuration' mod='prestashopyuju'}
        </div>
        
        <div class="panel-body">
            {if isset($oauth_status) && $oauth_status.is_connected}
                <div class="alert alert-success">
                    <i class="icon-check"></i>
                    {l s='Successfully connected to Yuju API' mod='prestashopyuju'}
                    <br>
                    <small>{l s='Connected as:' mod='prestashopyuju'} {$oauth_status.user_info.name|default:'Unknown'|escape:'html':'UTF-8'}</small>
                </div>
                
                <div class="row">
                    <div class="col-lg-6">
                        <h4>{l s='Connection Details' mod='prestashopyuju'}</h4>
                        <dl class="dl-horizontal">
                            <dt>{l s='Environment:' mod='prestashopyuju'}</dt>
                            <dd><span class="label label-info">{$oauth_status.environment|upper|escape:'html':'UTF-8'}</span></dd>
                            
                            <dt>{l s='Client ID:' mod='prestashopyuju'}</dt>
                            <dd><code>{$oauth_status.client_id|escape:'html':'UTF-8'}</code></dd>
                            
                            <dt>{l s='Connected At:' mod='prestashopyuju'}</dt>
                            <dd>{$oauth_status.connected_at|escape:'html':'UTF-8'}</dd>
                            
                            <dt>{l s='Token Expires:' mod='prestashopyuju'}</dt>
                            <dd>{$oauth_status.expires_at|escape:'html':'UTF-8'}</dd>
                        </dl>
                    </div>
                    
                    <div class="col-lg-6">
                        <h4>{l s='API Statistics' mod='prestashopyuju'}</h4>
                        {if isset($api_stats)}
                            <dl class="dl-horizontal">
                                <dt>{l s='Total Requests:' mod='prestashopyuju'}</dt>
                                <dd>{$api_stats.total_requests|default:0|escape:'html':'UTF-8'}</dd>
                                
                                <dt>{l s='Successful:' mod='prestashopyuju'}</dt>
                                <dd>{$api_stats.successful_requests|default:0|escape:'html':'UTF-8'}</dd>
                                
                                <dt>{l s='Failed:' mod='prestashopyuju'}</dt>
                                <dd>{$api_stats.failed_requests|default:0|escape:'html':'UTF-8'}</dd>
                                
                                <dt>{l s='Last Request:' mod='prestashopyuju'}</dt>
                                <dd>{$api_stats.last_request|default:'-'|escape:'html':'UTF-8'}</dd>
                            </dl>
                        {/if}
                    </div>
                </div>
                
                <div class="panel-footer">
                    <button type="button" class="btn btn-warning" onclick="if(confirm('{l s='Are you sure you want to revoke the OAuth token?' mod='prestashopyuju'}')) { window.location.href = '{$current_index|escape:'javascript':'UTF-8'}&token={$token|escape:'javascript':'UTF-8'}&revokeToken=1'; }">
                        <i class="icon-unlink"></i> {l s='Revoke Token' mod='prestashopyuju'}
                    </button>
                    
                    <button type="button" class="btn btn-info" id="yuju-test-api">
                        <i class="icon-check"></i> {l s='Test Connection' mod='prestashopyuju'}
                    </button>
                </div>
            {else}
                <div class="alert alert-warning">
                    <i class="icon-warning"></i>
                    {l s='Not connected to Yuju API. Please configure your credentials and authorize the connection.' mod='prestashopyuju'}
                </div>
                
                <form action="{$current_index|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}" method="post" class="form-horizontal">
                    <div class="form-group">
                        <label class="control-label col-lg-3 required">
                            {l s='Environment' mod='prestashopyuju'}
                        </label>
                        <div class="col-lg-9">
                            <select name="environment" class="form-control" required>
                                <option value="sandbox" {if $oauth_status.environment == 'sandbox'}selected{/if}>
                                    {l s='Sandbox (Testing)' mod='prestashopyuju'}
                                </option>
                                <option value="production" {if $oauth_status.environment == 'production'}selected{/if}>
                                    {l s='Production' mod='prestashopyuju'}
                                </option>
                            </select>
                            <p class="help-block">{l s='Select the Yuju environment to connect to' mod='prestashopyuju'}</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3 required">
                            {l s='Client ID' mod='prestashopyuju'}
                        </label>
                        <div class="col-lg-9">
                            <input type="text" name="client_id" value="{$oauth_status.client_id|escape:'html':'UTF-8'}" class="form-control" required>
                            <p class="help-block">{l s='Your Yuju application Client ID' mod='prestashopyuju'}</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3 required">
                            {l s='Client Secret' mod='prestashopyuju'}
                        </label>
                        <div class="col-lg-9">
                            <input type="password" name="client_secret" value="{$oauth_status.client_secret|escape:'html':'UTF-8'}" class="form-control" required>
                            <p class="help-block">{l s='Your Yuju application Client Secret' mod='prestashopyuju'}</p>
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
                            <i class="icon-save"></i> {l s='Save Configuration' mod='prestashopyuju'}
                        </button>
                        
                        {if isset($oauth_url) && $oauth_url}
                            <a href="{$oauth_url|escape:'html':'UTF-8'}" class="btn btn-success" target="_blank">
                                <i class="icon-key"></i> {l s='Authorize with Yuju' mod='prestashopyuju'}
                            </a>
                        {/if}
                    </div>
                </form>
            {/if}
        </div>
    </div>
    
    {* General Configuration Panel *}
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-wrench"></i>
            {l s='General Settings' mod='prestashopyuju'}
        </div>
        
        <div class="panel-body">
            <form action="{$current_index|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}" method="post" class="form-horizontal">
                <div class="form-group">
                    <label class="control-label col-lg-3">
                        {l s='Auto Sync' mod='prestashopyuju'}
                    </label>
                    <div class="col-lg-9">
                        <span class="switch prestashop-switch fixed-width-lg">
                            <input type="radio" name="auto_sync" id="auto_sync_on" value="1" {if Configuration::get('YUJU_AUTO_SYNC')}checked="checked"{/if}>
                            <label for="auto_sync_on">{l s='Yes' mod='prestashopyuju'}</label>
                            <input type="radio" name="auto_sync" id="auto_sync_off" value="0" {if !Configuration::get('YUJU_AUTO_SYNC')}checked="checked"{/if}>
                            <label for="auto_sync_off">{l s='No' mod='prestashopyuju'}</label>
                            <a class="slide-button btn"></a>
                        </span>
                        <p class="help-block">{l s='Enable automatic synchronization when products are updated' mod='prestashopyuju'}</p>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="control-label col-lg-3">
                        {l s='Sync Frequency' mod='prestashopyuju'}
                    </label>
                    <div class="col-lg-9">
                        <select name="sync_frequency" class="form-control">
                            <option value="5" {if Configuration::get('YUJU_SYNC_FREQUENCY') == '5'}selected{/if}>{l s='Every 5 minutes' mod='prestashopyuju'}</option>
                            <option value="15" {if Configuration::get('YUJU_SYNC_FREQUENCY') == '15'}selected{/if}>{l s='Every 15 minutes' mod='prestashopyuju'}</option>
                            <option value="30" {if Configuration::get('YUJU_SYNC_FREQUENCY') == '30'}selected{/if}>{l s='Every 30 minutes' mod='prestashopyuju'}</option>
                            <option value="60" {if Configuration::get('YUJU_SYNC_FREQUENCY') == '60'}selected{/if}>{l s='Every hour' mod='prestashopyuju'}</option>
                            <option value="360" {if Configuration::get('YUJU_SYNC_FREQUENCY') == '360'}selected{/if}>{l s='Every 6 hours' mod='prestashopyuju'}</option>
                            <option value="1440" {if Configuration::get('YUJU_SYNC_FREQUENCY') == '1440'}selected{/if}>{l s='Daily' mod='prestashopyuju'}</option>
                        </select>
                        <p class="help-block">{l s='How often to run automatic synchronization' mod='prestashopyuju'}</p>
                    </div>
                </div>
                
                <hr>
                <h3 class="text-primary">
                    <i class="icon-refresh"></i> Configuración de Sincronización Masiva
                </h3>
                <p class="help-block">Configure cómo se procesan las actualizaciones de stock y precio hacia Yuju</p>
                
                <div class="form-group">
                    <label class="control-label col-lg-3">
                        <strong>{l s='Tamaño de Lote' mod='prestashopyuju'}</strong>
                    </label>
                    <div class="col-lg-9">
                        <input type="number" name="batch_size" value="{Configuration::get('YUJU_BATCH_SIZE')|default:100|escape:'html':'UTF-8'}" class="form-control" min="1" max="500">
                        <p class="help-block">
                            <i class="icon-cubes"></i> Máximo de envíos reales a Yuju por ejecución (no el tamaño del encolado). Tope 500.
                        </p>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="control-label col-lg-3">
                        <strong>{l s='Frecuencia de Sincronización' mod='prestashopyuju'}</strong>
                    </label>
                    <div class="col-lg-9">
                        <div class="input-group">
                            <input type="number" name="batch_frequency" value="{Configuration::get('YUJU_BATCH_FREQUENCY')|default:60|escape:'html':'UTF-8'}" class="form-control" min="30" max="3600">
                            <span class="input-group-addon">segundos</span>
                        </div>
                        <p class="help-block">
                            <i class="icon-clock-o"></i> {l s='Tiempo de espera entre cada lote (ej: 60 segundos entre cada lote de 100)' mod='prestashopyuju'}
                        </p>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="control-label col-lg-3">
                        <strong>{l s='Máximo de Actualizaciones Diarias' mod='prestashopyuju'}</strong>
                    </label>
                    <div class="col-lg-9">
                        <select name="max_daily_syncs" class="form-control">
                            <option value="1" {if Configuration::get('YUJU_MAX_DAILY_SYNCS') == '1'}selected{/if}>1 vez al día</option>
                            <option value="2" {if Configuration::get('YUJU_MAX_DAILY_SYNCS') == '2'}selected{/if}>2 veces al día</option>
                            <option value="3" {if Configuration::get('YUJU_MAX_DAILY_SYNCS') == '3'}selected{/if}>3 veces al día</option>
                            <option value="4" {if Configuration::get('YUJU_MAX_DAILY_SYNCS') == '4'}selected{/if}>4 veces al día</option>
                            <option value="5" {if Configuration::get('YUJU_MAX_DAILY_SYNCS') == '5' || !Configuration::get('YUJU_MAX_DAILY_SYNCS')}selected{/if}>5 veces al día (máximo)</option>
                        </select>
                        <p class="help-block">
                            <i class="icon-calendar"></i> {l s='Límite de sincronizaciones completas por día (máximo 5)' mod='prestashopyuju'}
                        </p>
                    </div>
                </div>
                
                <hr>
                <div class="form-group">
                    <label class="control-label col-lg-3">
                        {l s='Log Level' mod='prestashopyuju'}
                    </label>
                    <div class="col-lg-9">
                        <select name="log_level" class="form-control">
                            <option value="error" {if Configuration::get('YUJU_LOG_LEVEL') == 'error'}selected{/if}>{l s='Error only' mod='prestashopyuju'}</option>
                            <option value="warning" {if Configuration::get('YUJU_LOG_LEVEL') == 'warning'}selected{/if}>{l s='Warning and above' mod='prestashopyuju'}</option>
                            <option value="info" {if Configuration::get('YUJU_LOG_LEVEL') == 'info'}selected{/if}>{l s='Info and above' mod='prestashopyuju'}</option>
                            <option value="debug" {if Configuration::get('YUJU_LOG_LEVEL') == 'debug'}selected{/if}>{l s='Debug (all)' mod='prestashopyuju'}</option>
                        </select>
                        <p class="help-block">{l s='Level of detail for logging' mod='prestashopyuju'}</p>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="control-label col-lg-3">
                        {l s='Log Retention' mod='prestashopyuju'}
                    </label>
                    <div class="col-lg-9">
                        <select name="log_retention" class="form-control">
                            <option value="7" {if Configuration::get('YUJU_LOG_RETENTION') == '7'}selected{/if}>{l s='7 days' mod='prestashopyuju'}</option>
                            <option value="30" {if Configuration::get('YUJU_LOG_RETENTION') == '30'}selected{/if}>{l s='30 days' mod='prestashopyuju'}</option>
                            <option value="90" {if Configuration::get('YUJU_LOG_RETENTION') == '90'}selected{/if}>{l s='90 days' mod='prestashopyuju'}</option>
                            <option value="365" {if Configuration::get('YUJU_LOG_RETENTION') == '365'}selected{/if}>{l s='1 year' mod='prestashopyuju'}</option>
                        </select>
                        <p class="help-block">{l s='How long to keep log files' mod='prestashopyuju'}</p>
                    </div>
                </div>
                
                <div class="panel-footer">
                    <button type="submit" name="submitGeneralConfig" class="btn btn-primary">
                        <i class="icon-save"></i> {l s='Save Settings' mod='prestashopyuju'}
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