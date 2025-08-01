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
<div class="panel panel-default yuju-sync-info">
    <div class="panel-heading">
        <i class="icon-refresh"></i>
        {l s='Synchronization Status' mod='prestashopyuju'}
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-3">
                <div class="sync-status-card">
                    <h4>
                        {if $is_sync_running}
                            <span class="label label-warning">
                                <i class="icon-spinner icon-spin"></i> {l s='Running' mod='prestashopyuju'}
                            </span>
                        {else}
                            <span class="label label-success">
                                <i class="icon-check"></i> {l s='Idle' mod='prestashopyuju'}
                            </span>
                        {/if}
                    </h4>
                    <p class="text-muted">{l s='Current Status' mod='prestashopyuju'}</p>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="sync-status-card">
                    <h4>{if $last_sync_date}{$last_sync_date|date_format:"%Y-%m-%d %H:%M"|escape:'html':'UTF-8'}{else}{l s='Never' mod='prestashopyuju'}{/if}</h4>
                    <p class="text-muted">{l s='Last Sync' mod='prestashopyuju'}</p>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="sync-status-card">
                    <h4>
                        {if $auto_sync_enabled}
                            <span class="label label-success">{l s='Enabled' mod='prestashopyuju'}</span>
                        {else}
                            <span class="label label-default">{l s='Disabled' mod='prestashopyuju'}</span>
                        {/if}
                    </h4>
                    <p class="text-muted">{l s='Auto Sync' mod='prestashopyuju'}</p>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="sync-status-card">
                    <h4>{if isset($sync_stats.total_synced)}{$sync_stats.total_synced|intval}{else}0{/if}</h4>
                    <p class="text-muted">{l s='Total Synced' mod='prestashopyuju'}</p>
                </div>
            </div>
        </div>
        
        {if isset($sync_stats)}
            <div class="row" style="margin-top: 20px;">
                <div class="col-md-12">
                    <h5>{l s='Sync Statistics' mod='prestashopyuju'}</h5>
                    <div class="row">
                        <div class="col-md-2">
                            <div class="text-center">
                                <strong>{if isset($sync_stats.products_synced)}{$sync_stats.products_synced|intval}{else}0{/if}</strong>
                                <br><small>{l s='Products' mod='prestashopyuju'}</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="text-center">
                                <strong>{if isset($sync_stats.categories_synced)}{$sync_stats.categories_synced|intval}{else}0{/if}</strong>
                                <br><small>{l s='Categories' mod='prestashopyuju'}</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="text-center">
                                <strong>{if isset($sync_stats.stock_synced)}{$sync_stats.stock_synced|intval}{else}0{/if}</strong>
                                <br><small>{l s='Stock Updates' mod='prestashopyuju'}</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="text-center">
                                <strong>{if isset($sync_stats.price_synced)}{$sync_stats.price_synced|intval}{else}0{/if}</strong>
                                <br><small>{l s='Price Updates' mod='prestashopyuju'}</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="text-center">
                                <strong>{if isset($sync_stats.errors)}{$sync_stats.errors|intval}{else}0{/if}</strong>
                                <br><small class="text-danger">{l s='Errors' mod='prestashopyuju'}</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="text-center">
                                <strong>{if isset($sync_stats.success_rate)}{$sync_stats.success_rate|string_format:"%.1f"|escape:'html':'UTF-8'}%{else}0%{/if}</strong>
                                <br><small>{l s='Success Rate' mod='prestashopyuju'}</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        {/if}
        
        {if $is_sync_running}
            <div class="row" style="margin-top: 20px;">
                <div class="col-md-12">
                    <div class="alert alert-info">
                        <i class="icon-info-circle"></i>
                        {l s='A synchronization process is currently running. Please wait for it to complete before starting a new sync.' mod='prestashopyuju'}
                        <br>
                        <button type="button" class="btn btn-warning btn-sm" onclick="stopSync()" style="margin-top: 10px;">
                            <i class="icon-stop"></i> {l s='Stop Sync' mod='prestashopyuju'}
                        </button>
                    </div>
                </div>
            </div>
        {/if}
    </div>
</div>

<script type="text/javascript">
function stopSync() {
    if (confirm('{l s='Are you sure you want to stop the current synchronization?' mod='prestashopyuju' js=1}')) {
        $.ajax({
            url: '{$link->getAdminLink('AdminYujuSync')|escape:'html':'UTF-8'}',
            type: 'POST',
            data: {
                action: 'stopSync',
                ajax: true
            },
            success: function(response) {
                if (response.success) {
                    showSuccessMessage('{l s='Synchronization stopped successfully' mod='prestashopyuju' js=1}');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showErrorMessage(response.message || '{l s='Failed to stop synchronization' mod='prestashopyuju' js=1}');
                }
            },
            error: function() {
                showErrorMessage('{l s='An error occurred while stopping the synchronization' mod='prestashopyuju' js=1}');
            }
        });
    }
}

// Auto-refresh sync status every 30 seconds
setInterval(function() {
    if (typeof refreshSyncStatus === 'function') {
        refreshSyncStatus();
    }
}, 30000);
</script>
{/block}