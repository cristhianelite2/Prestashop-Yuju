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
        <i class="icon-refresh"></i>
        {l s='Yuju Synchronization' mod='prestashopyuju'}
    </div>
    
    <div class="panel-body">
        {if isset($sync_status) && $sync_status.is_running}
            <div class="alert alert-info">
                <i class="icon-refresh icon-spin"></i>
                {l s='Synchronization is currently running...' mod='prestashopyuju'}
                <div class="progress" style="margin-top: 10px;">
                    <div class="progress-bar" role="progressbar" style="width: {$sync_status.progress|default:0|escape:'html':'UTF-8'}%">
                        {$sync_status.progress|default:0|escape:'html':'UTF-8'}%
                    </div>
                </div>
                <button type="button" class="btn btn-danger btn-sm" onclick="stopSync()">
                    <i class="icon-stop"></i> {l s='Stop Sync' mod='prestashopyuju'}
                </button>
            </div>
        {/if}
        
        {if isset($last_sync)}
            <div class="alert alert-success">
                <i class="icon-info"></i>
                {l s='Last synchronization:' mod='prestashopyuju'} {$last_sync.date|escape:'html':'UTF-8'}
                <br>
                <small>
                    {l s='Products:' mod='prestashopyuju'} {$last_sync.products_synced|default:0|escape:'html':'UTF-8'} |
                    {l s='Categories:' mod='prestashopyuju'} {$last_sync.categories_synced|default:0|escape:'html':'UTF-8'} |
                    {l s='Stock:' mod='prestashopyuju'} {$last_sync.stock_synced|default:0|escape:'html':'UTF-8'} |
                    {l s='Prices:' mod='prestashopyuju'} {$last_sync.prices_synced|default:0|escape:'html':'UTF-8'}
                </small>
            </div>
        {/if}
        
        <div class="row">
            <div class="col-lg-6">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">
                            <i class="icon-download"></i>
                            {l s='Full Synchronization' mod='prestashopyuju'}
                        </h3>
                    </div>
                    <div class="panel-body">
                        <p>{l s='Synchronize all data between PrestaShop and Yuju. This may take several minutes.' mod='prestashopyuju'}</p>
                        
                        <form id="full_sync_form">
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" name="sync_products" value="1" checked>
                                    {l s='Products' mod='prestashopyuju'}
                                </label>
                            </div>
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" name="sync_categories" value="1" checked>
                                    {l s='Categories' mod='prestashopyuju'}
                                </label>
                            </div>
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" name="sync_stock" value="1" checked>
                                    {l s='Stock' mod='prestashopyuju'}
                                </label>
                            </div>
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" name="sync_prices" value="1" checked>
                                    {l s='Prices' mod='prestashopyuju'}
                                </label>
                            </div>
                            
                            <button type="button" class="btn btn-primary btn-block" onclick="startFullSync()" {if isset($sync_status) && $sync_status.is_running}disabled{/if}>
                                <i class="icon-download"></i> {l s='Start Full Sync' mod='prestashopyuju'}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">
                            <i class="icon-refresh"></i>
                            {l s='Incremental Synchronization' mod='prestashopyuju'}
                        </h3>
                    </div>
                    <div class="panel-body">
                        <p>{l s='Synchronize only items that have changed since the last sync.' mod='prestashopyuju'}</p>
                        
                        <form id="incremental_sync_form">
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" name="sync_products" value="1" checked>
                                    {l s='Products' mod='prestashopyuju'}
                                </label>
                            </div>
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" name="sync_categories" value="1" checked>
                                    {l s='Categories' mod='prestashopyuju'}
                                </label>
                            </div>
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" name="sync_stock" value="1" checked>
                                    {l s='Stock' mod='prestashopyuju'}
                                </label>
                            </div>
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" name="sync_prices" value="1" checked>
                                    {l s='Prices' mod='prestashopyuju'}
                                </label>
                            </div>
                            
                            <button type="button" class="btn btn-success btn-block" onclick="startIncrementalSync()" {if isset($sync_status) && $sync_status.is_running}disabled{/if}>
                                <i class="icon-refresh"></i> {l s='Start Incremental Sync' mod='prestashopyuju'}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        {* Sync Statistics *}
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <i class="icon-bar-chart"></i>
                    {l s='Synchronization Statistics' mod='prestashopyuju'}
                </h3>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-lg-3">
                        <div class="text-center">
                            <h4>{$stats.total_syncs|default:0|escape:'html':'UTF-8'}</h4>
                            <p class="text-muted">{l s='Total Syncs' mod='prestashopyuju'}</p>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="text-center">
                            <h4>{$stats.successful_syncs|default:0|escape:'html':'UTF-8'}</h4>
                            <p class="text-muted">{l s='Successful' mod='prestashopyuju'}</p>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="text-center">
                            <h4>{$stats.failed_syncs|default:0|escape:'html':'UTF-8'}</h4>
                            <p class="text-muted">{l s='Failed' mod='prestashopyuju'}</p>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="text-center">
                            <h4>{$stats.items_synced|default:0|escape:'html':'UTF-8'}</h4>
                            <p class="text-muted">{l s='Items Synced' mod='prestashopyuju'}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        {* Recent Sync Logs *}
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <i class="icon-list"></i>
                    {l s='Recent Sync Logs' mod='prestashopyuju'}
                </h3>
            </div>
            <div class="panel-body">
                {if isset($recent_logs) && count($recent_logs) > 0}
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>{l s='Date' mod='prestashopyuju'}</th>
                                    <th>{l s='Type' mod='prestashopyuju'}</th>
                                    <th>{l s='Status' mod='prestashopyuju'}</th>
                                    <th>{l s='Items' mod='prestashopyuju'}</th>
                                    <th>{l s='Duration' mod='prestashopyuju'}</th>
                                    <th>{l s='Actions' mod='prestashopyuju'}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {foreach from=$recent_logs item=log}
                                    <tr>
                                        <td>{$log.date_add|escape:'html':'UTF-8'}</td>
                                        <td>
                                            <span class="label label-info">{$log.sync_type|escape:'html':'UTF-8'}</span>
                                        </td>
                                        <td>
                                            {if $log.status == 'completed'}
                                                <span class="label label-success">{l s='Completed' mod='prestashopyuju'}</span>
                                            {elseif $log.status == 'failed'}
                                                <span class="label label-danger">{l s='Failed' mod='prestashopyuju'}</span>
                                            {elseif $log.status == 'running'}
                                                <span class="label label-warning">{l s='Running' mod='prestashopyuju'}</span>
                                            {else}
                                                <span class="label label-default">{$log.status|escape:'html':'UTF-8'}</span>
                                            {/if}
                                        </td>
                                        <td>{$log.items_processed|default:0|escape:'html':'UTF-8'}</td>
                                        <td>{$log.duration|default:'-'|escape:'html':'UTF-8'}</td>
                                        <td>
                                            <a href="{$current_index|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}&action=viewLog&id_log={$log.id_log|escape:'html':'UTF-8'}" class="btn btn-default btn-xs">
                                                <i class="icon-eye"></i> {l s='View' mod='prestashopyuju'}
                                            </a>
                                        </td>
                                    </tr>
                                {/foreach}
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="text-center">
                        <a href="{$current_index|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}&action=viewAllLogs" class="btn btn-default">
                            <i class="icon-list"></i> {l s='View All Logs' mod='prestashopyuju'}
                        </a>
                        <button type="button" class="btn btn-warning" onclick="cleanLogs()">
                            <i class="icon-trash"></i> {l s='Clean Old Logs' mod='prestashopyuju'}
                        </button>
                    </div>
                {else}
                    <p class="text-muted text-center">{l s='No sync logs available' mod='prestashopyuju'}</p>
                {/if}
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
var syncStatusUrl = '{$current_index|escape:'javascript':'UTF-8'}&token={$token|escape:'javascript':'UTF-8'}&ajax=1&action=getSyncStatus';
var stopSyncUrl = '{$current_index|escape:'javascript':'UTF-8'}&token={$token|escape:'javascript':'UTF-8'}&ajax=1&action=stopSync';
var startSyncUrl = '{$current_index|escape:'javascript':'UTF-8'}&token={$token|escape:'javascript':'UTF-8'}&ajax=1&action=startSync';
var cleanLogsUrl = '{$current_index|escape:'javascript':'UTF-8'}&token={$token|escape:'javascript':'UTF-8'}&ajax=1&action=cleanLogs';

function startFullSync() {
    var formData = $('#full_sync_form').serialize();
    formData += '&sync_mode=full';
    
    if (confirm('{l s='Are you sure you want to start a full synchronization? This may take several minutes.' mod='prestashopyuju'}')) {
        $.ajax({
            url: startSyncUrl,
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.message || '{l s='Error starting synchronization' mod='prestashopyuju'}');
                }
            },
            error: function() {
                alert('{l s='Error starting synchronization' mod='prestashopyuju'}');
            }
        });
    }
}

function startIncrementalSync() {
    var formData = $('#incremental_sync_form').serialize();
    formData += '&sync_mode=incremental';
    
    $.ajax({
        url: startSyncUrl,
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.message || '{l s='Error starting synchronization' mod='prestashopyuju'}');
            }
        },
        error: function() {
            alert('{l s='Error starting synchronization' mod='prestashopyuju'}');
        }
    });
}

function stopSync() {
    if (confirm('{l s='Are you sure you want to stop the current synchronization?' mod='prestashopyuju'}')) {
        $.ajax({
            url: stopSyncUrl,
            type: 'POST',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.message || '{l s='Error stopping synchronization' mod='prestashopyuju'}');
                }
            },
            error: function() {
                alert('{l s='Error stopping synchronization' mod='prestashopyuju'}');
            }
        });
    }
}

function cleanLogs() {
    if (confirm('{l s='Are you sure you want to clean old logs? This action cannot be undone.' mod='prestashopyuju'}')) {
        $.ajax({
            url: cleanLogsUrl,
            type: 'POST',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert(response.message || '{l s='Logs cleaned successfully' mod='prestashopyuju'}');
                    location.reload();
                } else {
                    alert(response.message || '{l s='Error cleaning logs' mod='prestashopyuju'}');
                }
            },
            error: function() {
                alert('{l s='Error cleaning logs' mod='prestashopyuju'}');
            }
        });
    }
}

// Auto-refresh sync status if sync is running
{if isset($sync_status) && $sync_status.is_running}
setInterval(function() {
    $.ajax({
        url: syncStatusUrl,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success && !response.data.is_running) {
                location.reload();
            } else if (response.success && response.data.progress) {
                $('.progress-bar').css('width', response.data.progress + '%').text(response.data.progress + '%');
            }
        }
    });
}, 5000); // Check every 5 seconds
{/if}
</script>