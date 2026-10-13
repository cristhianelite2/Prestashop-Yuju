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
        {l s='Detalles del registro de sincronización' mod='prestashopyuju'} - ID: {$sync_log.id_log|escape:'html':'UTF-8'}
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-6">
                <h4>{l s='Información general' mod='prestashopyuju'}</h4>
                <table class="table table-striped">
                    <tr>
                        <td><strong>{l s='ID del registro' mod='prestashopyuju'}</strong></td>
                        <td>{$sync_log.id_log|escape:'html':'UTF-8'}</td>
                    </tr>
                    <tr>
                        <td><strong>{l s='Tipo de sincronización' mod='prestashopyuju'}</strong></td>
                        <td>
                            <span class="label label-info">{$sync_log.sync_type|escape:'html':'UTF-8'}</span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>{l s='Dirección de sincronización' mod='prestashopyuju'}</strong></td>
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
                                    <i class="icon-exchange"></i> {l s='Bidireccional' mod='prestashopyuju'}
                                </span>
                            {/if}
                        </td>
                    </tr>
                    <tr>
                        <td><strong>{l s='Estado' mod='prestashopyuju'}</strong></td>
                        <td>
                            {if $sync_log.success}
                                <span class="label label-success">
                                    <i class="icon-check"></i> {l s='Correcto' mod='prestashopyuju'}
                                </span>
                            {else}
                                <span class="label label-danger">
                                    <i class="icon-remove"></i> {l s='Fallido' mod='prestashopyuju'}
                                </span>
                            {/if}
                        </td>
                    </tr>
                    <tr>
                        <td><strong>{l s='Iniciado el' mod='prestashopyuju'}</strong></td>
                        <td>{$sync_log.created_at|date_format:"%Y-%m-%d %H:%M:%S"|escape:'html':'UTF-8'}</td>
                    </tr>
                    <tr>
                        <td><strong>{l s='Tiempo de ejecución' mod='prestashopyuju'}</strong></td>
                        <td>
                            {if $sync_log.execution_time}
                                <strong>{$sync_log.execution_time|string_format:"%.2f"|escape:'html':'UTF-8'} {l s='segundos' mod='prestashopyuju'}</strong>
                            {else}
                                <span class="text-muted">{l s='N/A' mod='prestashopyuju'}</span>
                            {/if}
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="col-md-6">
                <h4>{l s='Estadísticas' mod='prestashopyuju'}</h4>
                <table class="table table-striped">
                    <tr>
                        <td><strong>{l s='Productos sincronizados' mod='prestashopyuju'}</strong></td>
                        <td>
                            <span class="badge badge-primary">{$sync_log.products_synced|intval}</span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>{l s='Categorías sincronizadas' mod='prestashopyuju'}</strong></td>
                        <td>
                            <span class="badge badge-info">{if isset($sync_log.categories_synced)}{$sync_log.categories_synced|intval}{else}0{/if}</span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>{l s='Actualizaciones de existencias' mod='prestashopyuju'}</strong></td>
                        <td>
                            <span class="badge badge-warning">{if isset($sync_log.stock_synced)}{$sync_log.stock_synced|intval}{else}0{/if}</span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>{l s='Actualizaciones de precios' mod='prestashopyuju'}</strong></td>
                        <td>
                            <span class="badge badge-success">{if isset($sync_log.price_synced)}{$sync_log.price_synced|intval}{else}0{/if}</span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>{l s='Cantidad de errores' mod='prestashopyuju'}</strong></td>
                        <td>
                            <span class="badge badge-danger">{$sync_log.error_count|intval}</span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>{l s='Porcentaje de éxito' mod='prestashopyuju'}</strong></td>
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
                    <h4>{l s='Detalles de sincronización' mod='prestashopyuju'}</h4>
                    <div class="well">
                        <pre style="max-height: 300px; overflow-y: auto;">{$sync_log.sync_details|escape:'html':'UTF-8'}</pre>
                    </div>
                </div>
            </div>
        {/if}
        
        {if isset($sync_log.error_details) && $sync_log.error_details}
            <div class="row" style="margin-top: 20px;">
                <div class="col-md-12">
                    <h4 class="text-danger">{l s='Detalles del error' mod='prestashopyuju'}</h4>
                    <div class="alert alert-danger">
                        <pre style="max-height: 300px; overflow-y: auto;">{$sync_log.error_details|escape:'html':'UTF-8'}</pre>
                    </div>
                </div>
            </div>
        {/if}
        
        {if isset($sync_log.processed_items) && count($sync_log.processed_items) > 0}
            <div class="row" style="margin-top: 20px;">
                <div class="col-md-12">
                    <h4>{l s='Elementos procesados' mod='prestashopyuju'}</h4>
                    <div class="table-responsive">
                        <table class="table table-striped table-condensed">
                            <thead>
                                <tr>
                                    <th>{l s='Tipo de elemento' mod='prestashopyuju'}</th>
                                    <th>{l s='ID del elemento' mod='prestashopyuju'}</th>
                                    <th>{l s='Nombre del elemento' mod='prestashopyuju'}</th>
                                    <th>{l s='Estado' mod='prestashopyuju'}</th>
                                    <th>{l s='Mensaje' mod='prestashopyuju'}</th>
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
                                                    <i class="icon-check"></i> {l s='Correcto' mod='prestashopyuju'}
                                                </span>
                                            {elseif $item.status == 'error'}
                                                <span class="label label-danger">
                                                    <i class="icon-remove"></i> {l s='Error' mod='prestashopyuju'}
                                                </span>
                                            {elseif $item.status == 'skipped'}
                                                <span class="label label-warning">
                                                    <i class="icon-minus"></i> {l s='Omitido' mod='prestashopyuju'}
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
                        <i class="icon-arrow-left"></i> {l s='Volver a los registros de sincronización' mod='prestashopyuju'}
                    </a>
                    
                    {if !$sync_log.success}
                        <button type="button" class="btn btn-warning" onclick="retrySync({$sync_log.id_log|escape:'javascript':'UTF-8'})">
                            <i class="icon-refresh"></i> {l s='Reintentar sincronización' mod='prestashopyuju'}
                        </button>
                    {/if}
                    
                    <button type="button" class="btn btn-info" onclick="exportLog({$sync_log.id_log|escape:'javascript':'UTF-8'})">
                        <i class="icon-download"></i> {l s='Exportar registro' mod='prestashopyuju'}
                    </button>
                    
                    <button type="button" class="btn btn-danger" onclick="deleteLog({$sync_log.id_log|escape:'javascript':'UTF-8'})">
                        <i class="icon-trash"></i> {l s='Eliminar registro' mod='prestashopyuju'}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
function retrySync(logId) {
    if (confirm('{l s='¿Seguro que deseas reintentar esta sincronización?' mod='prestashopyuju' js=1}')) {
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
                    showSuccessMessage('{l s='Se inició el reintento de sincronización correctamente' mod='prestashopyuju' js=1}');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showErrorMessage(response.message || '{l s='No se pudo reintentar la sincronización' mod='prestashopyuju' js=1}');
                }
            },
            error: function() {
                showErrorMessage('{l s='Ocurrió un error al reintentar la sincronización' mod='prestashopyuju' js=1}');
            }
        });
    }
}

function exportLog(logId) {
    window.open('{$link->getAdminLink('AdminYujuSync')|escape:'html':'UTF-8'}&action=exportLog&id_log=' + logId, '_blank');
}

function deleteLog(logId) {
    if (confirm('{l s='¿Seguro que deseas eliminar este registro? Esta acción no se puede deshacer.' mod='prestashopyuju' js=1}')) {
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
                    showSuccessMessage('{l s='Registro eliminado correctamente' mod='prestashopyuju' js=1}');
                    setTimeout(function() {
                        window.location.href = '{$link->getAdminLink('AdminYujuSync')|escape:'html':'UTF-8'}';
                    }, 1500);
                } else {
                    showErrorMessage(response.message || '{l s='No se pudo eliminar el registro' mod='prestashopyuju' js=1}');
                }
            },
            error: function() {
                showErrorMessage('{l s='Ocurrió un error al eliminar el registro' mod='prestashopyuju' js=1}');
            }
        });
    }
}
</script>
{/block}