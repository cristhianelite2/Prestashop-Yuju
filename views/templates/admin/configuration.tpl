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

<div class="panel">
    <div class="panel-heading">
        <i class="icon-cogs"></i>
        {l s='Yuju Integration Configuration' mod='prestashopyuju'}
    </div>
    
    <div class="panel-body">
        {if isset($oauth_status) && $oauth_status.is_connected}
            <div class="alert alert-success">
                <i class="icon-check"></i>
                {l s='Successfully connected to Yuju API' mod='prestashopyuju'}
                <br>
                <small>{l s='Connected as:' mod='prestashopyuju'} {$oauth_status.user_info.name|default:'Unknown'|escape:'html':'UTF-8'}</small>
            </div>
        {else}
            <div class="alert alert-warning">
                <i class="icon-warning"></i>
                {l s='Not connected to Yuju API. Please configure your credentials and authorize the connection.' mod='prestashopyuju'}
            </div>
        {/if}
        
        {if isset($api_test_result)}
            {if $api_test_result.success}
                <div class="alert alert-success">
                    <i class="icon-check"></i>
                    {l s='API connection test successful' mod='prestashopyuju'}
                </div>
            {else}
                <div class="alert alert-danger">
                    <i class="icon-remove"></i>
                    {l s='API connection test failed:' mod='prestashopyuju'} {$api_test_result.error|escape:'html':'UTF-8'}
                </div>
            {/if}
        {/if}
        
        <form id="configuration_form" class="defaultForm form-horizontal" action="{$current_index|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}" method="post" enctype="multipart/form-data">
            
            {* API Configuration Section *}
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="icon-cloud"></i>
                        {l s='API Configuration' mod='prestashopyuju'}
                    </h3>
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            {l s='Environment' mod='prestashopyuju'}
                        </label>
                        <div class="col-lg-9">
                            <select name="YUJU_ENVIRONMENT" class="form-control">
                                <option value="sandbox" {if $config.YUJU_ENVIRONMENT == 'sandbox'}selected{/if}>
                                    {l s='Sandbox (Testing)' mod='prestashopyuju'}
                                </option>
                                <option value="production" {if $config.YUJU_ENVIRONMENT == 'production'}selected{/if}>
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
                            <input type="text" name="YUJU_CLIENT_ID" value="{$config.YUJU_CLIENT_ID|escape:'html':'UTF-8'}" class="form-control" required>
                            <p class="help-block">{l s='Your Yuju application Client ID' mod='prestashopyuju'}</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3 required">
                            {l s='Client Secret' mod='prestashopyuju'}
                        </label>
                        <div class="col-lg-9">
                            <input type="password" name="YUJU_CLIENT_SECRET" value="{$config.YUJU_CLIENT_SECRET|escape:'html':'UTF-8'}" class="form-control" required>
                            <p class="help-block">{l s='Your Yuju application Client Secret' mod='prestashopyuju'}</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            {l s='Webhook Secret' mod='prestashopyuju'}
                        </label>
                        <div class="col-lg-9">
                            <input type="text" name="YUJU_WEBHOOK_SECRET" value="{$config.YUJU_WEBHOOK_SECRET|escape:'html':'UTF-8'}" class="form-control">
                            <p class="help-block">{l s='Secret key for webhook signature verification' mod='prestashopyuju'}</p>
                        </div>
                    </div>
                </div>
            </div>
            
            {* Synchronization Settings *}
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="icon-refresh"></i>
                        {l s='Synchronization Settings' mod='prestashopyuju'}
                    </h3>
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            {l s='Enable Auto Sync' mod='prestashopyuju'}
                        </label>
                        <div class="col-lg-9">
                            <span class="switch prestashop-switch fixed-width-lg">
                                <input type="radio" name="YUJU_AUTO_SYNC" id="auto_sync_on" value="1" {if $config.YUJU_AUTO_SYNC}checked="checked"{/if}>
                                <label for="auto_sync_on">{l s='Yes' mod='prestashopyuju'}</label>
                                <input type="radio" name="YUJU_AUTO_SYNC" id="auto_sync_off" value="0" {if !$config.YUJU_AUTO_SYNC}checked="checked"{/if}>
                                <label for="auto_sync_off">{l s='No' mod='prestashopyuju'}</label>
                                <a class="slide-button btn"></a>
                            </span>
                            <p class="help-block">{l s='Automatically sync data when changes occur' mod='prestashopyuju'}</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            {l s='Sync Frequency (seconds)' mod='prestashopyuju'}
                        </label>
                        <div class="col-lg-9">
                            <input type="number" name="YUJU_SYNC_FREQUENCY" value="{$config.YUJU_SYNC_FREQUENCY|default:300|escape:'html':'UTF-8'}" class="form-control" min="60">
                            <p class="help-block">{l s='How often to run background sync (minimum 60 seconds)' mod='prestashopyuju'}</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            {l s='Batch Size' mod='prestashopyuju'}
                        </label>
                        <div class="col-lg-9">
                            <input type="number" name="YUJU_BATCH_SIZE" value="{$config.YUJU_BATCH_SIZE|default:50|escape:'html':'UTF-8'}" class="form-control" min="1" max="500">
                            <p class="help-block">{l s='Number of items to process per batch (1-500)' mod='prestashopyuju'}</p>
                        </div>
                    </div>
                </div>
            </div>
            
            {* Logging Settings *}
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="icon-file-text"></i>
                        {l s='Logging Settings' mod='prestashopyuju'}
                    </h3>
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            {l s='Log Level' mod='prestashopyuju'}
                        </label>
                        <div class="col-lg-9">
                            <select name="YUJU_LOG_LEVEL" class="form-control">
                                <option value="error" {if $config.YUJU_LOG_LEVEL == 'error'}selected{/if}>
                                    {l s='Error only' mod='prestashopyuju'}
                                </option>
                                <option value="warning" {if $config.YUJU_LOG_LEVEL == 'warning'}selected{/if}>
                                    {l s='Warning and above' mod='prestashopyuju'}
                                </option>
                                <option value="info" {if $config.YUJU_LOG_LEVEL == 'info'}selected{/if}>
                                    {l s='Info and above' mod='prestashopyuju'}
                                </option>
                                <option value="debug" {if $config.YUJU_LOG_LEVEL == 'debug'}selected{/if}>
                                    {l s='Debug (all)' mod='prestashopyuju'}
                                </option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            {l s='Log Retention (days)' mod='prestashopyuju'}
                        </label>
                        <div class="col-lg-9">
                            <input type="number" name="YUJU_LOG_RETENTION" value="{$config.YUJU_LOG_RETENTION|default:30|escape:'html':'UTF-8'}" class="form-control" min="1">
                            <p class="help-block">{l s='Number of days to keep log files' mod='prestashopyuju'}</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="panel-footer">
                <button type="submit" value="1" id="configuration_form_submit_btn" name="submitConfiguration" class="btn btn-default pull-right">
                    <i class="process-icon-save"></i> {l s='Save' mod='prestashopyuju'}
                </button>
                
                {if isset($oauth_status) && !$oauth_status.is_connected && $config.YUJU_CLIENT_ID && $config.YUJU_CLIENT_SECRET}
                    <a href="{$oauth_auth_url|escape:'html':'UTF-8'}" class="btn btn-primary">
                        <i class="icon-key"></i> {l s='Authorize with Yuju' mod='prestashopyuju'}
                    </a>
                {/if}
                
                {if $config.YUJU_CLIENT_ID && $config.YUJU_CLIENT_SECRET}
                    <button type="submit" name="testConnection" class="btn btn-info">
                        <i class="icon-check"></i> {l s='Test API Connection' mod='prestashopyuju'}
                    </button>
                {/if}
            </div>
        </form>
    </div>
</div>

<script type="text/javascript">
$(document).ready(function() {
    // Form validation
    $('#configuration_form').on('submit', function(e) {
        var clientId = $('input[name="YUJU_CLIENT_ID"]').val();
        var clientSecret = $('input[name="YUJU_CLIENT_SECRET"]').val();
        
        if (!clientId || !clientSecret) {
            e.preventDefault();
            alert('{l s='Please fill in both Client ID and Client Secret' mod='prestashopyuju'}');
            return false;
        }
    });
});
</script>