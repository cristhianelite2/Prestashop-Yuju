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
<div class="panel panel-default yuju-module">
    <div class="panel-heading">
        <i class="icon-file-text"></i>
        {l s='Sync Log Details' mod='prestashopyuju'} - ID: {$sync_log.id_log|escape:'html':'UTF-8'}
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-6">
                <h4>{l s='General Information' mod='prestashopyuju'}</h4>
                <table class="table table-striped">
                    <tr>
                        <td><strong>{l s='Log ID' mod='prestashopyuju'}</strong></td>
                        <td>{$sync_log.id_log|escape:'html':'UTF-8'}</td>
                    </tr>
                    <tr>
                        <td><strong>{l s='Sync Type' mod='prestashopyuju'}</strong></td>
                        <td>
                            <span class="label label-info">{$sync_log.sync_type|escape:'html':'UTF-8'}</span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>{l s='Sync Direction' mod='prestashopyuju'}</strong></td>
                        <td>
                            {if $sync_log.sync_direction == 'prestashop_to_yuju'}
                                <span class="label label-primary">
                                    <i class="icon-arrow-right"></i> {l s='PrestaShop → Yuju' mod='prestashopyuju'}
                                </span>
                            {elseif $sync_log.sync_direction == 'yuju_to_prestashop'}
                                <span class="label label-success">
                                    <i class="icon-arrow-left"></i> {l s='Yuju → PrestaShop' mod='prestashopyuju'}
                                </span>
                            {else}
                                <span class="label label-default">
                                    <i class="icon-exchange"></i> {l s='Bidirectional' mod='prestashopyuju'}
                                </span>
                            {/if}
                        </td>
                    </tr>
                    <tr>
                        <td><strong>{l s='Status' mod='prestashopyuju'}</strong></td>
                        <td>
                            {if $sync_log.success}
                                <span class="label label-success">
                                    <i class="icon-check"></i> {l s='Success' mod='prestashopyuju'}
                                </span>
                            {else}
                                <span class="label label-danger">
                                    <i class="icon-remove"></i> {l s='Failed' mod='prestashopyuju'}
                                </span>
                            {/if}
                        </td>
                    </tr>
                    <tr>
                        <td><strong>{l s='Started At' mod='prestashopyuju'}</strong></td>
                        <td>{$sync_log.created_at|date_format:"%Y-%m-%d %H:%M:%S"|escape:'html':'UTF-8'}</td>
                    </tr>
                    <tr>
                        <td><strong>{l s='Execution Time' mod='prestashopyuju'}</strong></td>
                        <td>
                            {if $sync_log.execution_time}
                                <strong>{$sync_log.execution_time|string_format:"%.2f"|escape:'html':'UTF-8'} {l s='seconds' mod='prestashopyuju'}</strong>
                            {else}
                                <span class="text-muted">{l s='N/A' mod='prestashopyuju'}</span>
                            {/if}
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="col-md-6">
                <h4>{l s='Statistics' mod='prestashopyuju'}</h4>
                <table class="table table-striped">
                    <tr>
                        <td><strong>{l s='Products Synced' mod='prestashopyuju'}</strong></td>
                        <td>
                            <span class="badge badge-primary">{$sync_log.products_synced|intval}</span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>{l s='Categories Synced' mod='prestashopyuju'}</strong></td>
                        <td>
                            <span class="badge badge-info">{if isset($sync_log.categories_synced)}{$sync_log.categories_synced|intval}{else}0{/if}</span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>{l s='Stock Updates' mod='prestashopyuju'}</strong></td>
                        <td>
                            <span class="badge badge-warning">{if isset($sync_log.stock_synced)}{$sync_log.stock_synced|intval}{else}0{/if}</span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>{l s='Price Updates' mod='prestashopyuju'}</strong></td>
                        <td>
                            <span class="badge badge-success">{if isset($sync_log.price_synced)}{$sync_log.price_synced|intval}{else}0{/if}</span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>{l s='Error Count' mod='prestashopyuju'}</strong></td>
                        <td>
                            <span class="badge badge-danger">{$sync_log.error_count|intval}</span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>{l s='Success Rate' mod='prestashopyuju'}</strong></td>
                        <td>
                            {assign var="total_items" value=($sync_log.products_synced + $sync_log.error_count)}
                            {if $total_items > 0}
                                {assign var="success_rate" value=($sync_log.products_synced / $total_items * 100)}
                                <span class="{if $success_rate >= 90}text-success{elseif $success_rate >= 70}text-warning{else}text-danger{/if}">
                                    <strong>{$success_rate|string_format:"%.1f"|escape:'html':'UTF-8'}%</strong>
                                </span>
                            {else}
                                <span class="text-muted">N/A</span>
                            {/if}
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        {if isset($sync_log.sync_details) && $sync_log.sync_details}
            <div class="row" style="margin-top: 20px;">
                <div class="col-md-12">
                    <h4>{l s='Sync Details' mod='prestashopyuju'}</h4>
                    <div class="well">
                        <pre style="max-height: 300px; overflow-y: auto;">{$sync_log.sync_details|escape:'html':'UTF-8'}</pre>
                    </div>
                </div>
            </div>
        {/if}
        
        {if isset($sync_log.error_details) && $sync_log.error_details}
            <div class="row" style="margin-top: 20px;">
                <div class="col-md-12">
                    <h4 class="text-danger">{l s='Error Details' mod='prestashopyuju'}</h4>
                    <div class="alert alert-danger">
                        <pre style="max-height: 300px; overflow-y: auto;">{$sync_log.error_details|escape:'html':'UTF-8'}</pre>
                    </div>
                </div>
            </div>
        {/if}
        
        {if isset($sync_log.processed_items) && count($sync_log.processed_items) > 0}
            <div class="row" style="margin-top: 20px;">
                <div class="col-md-12">
                    <h4>{l s='Processed Items' mod='prestashopyuju'}</h4>
                    <div class="table-responsive">
                        <table class="table table-striped table-condensed">
                            <thead>
                                <tr>
                                    <th>{l s='Item Type' mod='prestashopyuju'}</th>
                                    <th>{l s='Item ID' mod='prestashopyuju'}</th>
                                    <th>{l s='Item Name' mod='prestashopyuju'}</th>
                                    <th>{l s='Status' mod='prestashopyuju'}</th>
                                    <th>{l s='Message' mod='prestashopyuju'}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {foreach from=$sync_log.processed_items item=item}
                                    <tr>
                                        <td>
                                            <span class="label label-default">{$item.item_type|escape:'html':'UTF-8'}</span>
                                        </td>
                                        <td>{$item.item_id|escape:'html':'UTF-8'}</td>
                                        <td>{$item.item_name|escape:'html':'UTF-8'}</td>
                                        <td>
                                            {if $item.status == 'success'}
                                                <span class="label label-success">
                                                    <i class="icon-check"></i> {l s='Success' mod='prestashopyuju'}
                                                </span>
                                            {elseif $item.status == 'error'}
                                                <span class="label label-danger">
                                                    <i class="icon-remove"></i> {l s='Error' mod='prestashopyuju'}
                                                </span>
                                            {elseif $item.status == 'skipped'}
                                                <span class="label label-warning">
                                                    <i class="icon-minus"></i> {l s='Skipped' mod='prestashopyuju'}
                                                </span>
                                            {else}
                                                <span class="label label-info">
                                                    <i class="icon-info"></i> {$item.status|escape:'html':'UTF-8'}
                                                </span>
                                            {/if}
                                        </td>
                                        <td>
                                            {if $item.message}
                                                <small>{$item.message|escape:'html':'UTF-8'|truncate:100}</small>
                                            {else}
                                                <span class="text-muted">-</span>
                                            {/if}
                                        </td>
                                    </tr>
                                {/foreach}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        {/if}
        
        <div class="row" style="margin-top: 20px;">
            <div class="col-md-12">
                <div class="btn-group">
                    <a href="{$link->getAdminLink('AdminYujuSync')|escape:'html':'UTF-8'}" class="btn btn-default">
                        <i class="icon-arrow-left"></i> {l s='Back to Sync Logs' mod='prestashopyuju'}
                    </a>
                    
                    {if !$sync_log.success}
                        <button type="button" class="btn btn-warning" onclick="retrySync({$sync_log.id_log|escape:'javascript':'UTF-8'})">
                            <i class="icon-refresh"></i> {l s='Retry Sync' mod='prestashopyuju'}
                        </button>
                    {/if}
                    
                    <button type="button" class="btn btn-info" onclick="exportLog({$sync_log.id_log|escape:'javascript':'UTF-8'})">
                        <i class="icon-download"></i> {l s='Export Log' mod='prestashopyuju'}
                    </button>
                    
                    <button type="button" class="btn btn-danger" onclick="deleteLog({$sync_log.id_log|escape:'javascript':'UTF-8'})">
                        <i class="icon-trash"></i> {l s='Delete Log' mod='prestashopyuju'}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
function retrySync(logId) {
    if (confirm('{l s='Are you sure you want to retry this sync operation?' mod='prestashopyuju' js=1}')) {
        $.ajax({
            url: '{$link->getAdminLink('AdminYujuSync')|escape:'html':'UTF-8'}',
            type: 'POST',
            data: {
                action: 'retrySync',
                id_log: logId,
                ajax: true
            },
            success: function(response) {
                if (response.success) {
                    showSuccessMessage('{l s='Sync retry initiated successfully' mod='prestashopyuju' js=1}');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showErrorMessage(response.message || '{l s='Failed to retry sync' mod='prestashopyuju' js=1}');
                }
            },
            error: function() {
                showErrorMessage('{l s='An error occurred while retrying the sync' mod='prestashopyuju' js=1}');
            }
        });
    }
}

function exportLog(logId) {
    window.open('{$link->getAdminLink('AdminYujuSync')|escape:'html':'UTF-8'}&action=exportLog&id_log=' + logId, '_blank');
}

function deleteLog(logId) {
    if (confirm('{l s='Are you sure you want to delete this log? This action cannot be undone.' mod='prestashopyuju' js=1}')) {
        $.ajax({
            url: '{$link->getAdminLink('AdminYujuSync')|escape:'html':'UTF-8'}',
            type: 'POST',
            data: {
                action: 'deleteLog',
                id_log: logId,
                ajax: true
            },
            success: function(response) {
                if (response.success) {
                    showSuccessMessage('{l s='Log deleted successfully' mod='prestashopyuju' js=1}');
                    setTimeout(function() {
                        window.location.href = '{$link->getAdminLink('AdminYujuSync')|escape:'html':'UTF-8'}';
                    }, 1500);
                } else {
                    showErrorMessage(response.message || '{l s='Failed to delete log' mod='prestashopyuju' js=1}');
                }
            },
            error: function() {
                showErrorMessage('{l s='An error occurred while deleting the log' mod='prestashopyuju' js=1}');
            }
        });
    }
}
</script>
{/block}