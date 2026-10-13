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
        Detalles del registro de sincronización - ID: {$sync_log.id_log|escape:'html':'UTF-8'}
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-6">
                <h4>Información general</h4>
                <table class="table table-striped">
                    <tr>
                        <td><strong>ID del registro</strong></td>
                        <td>{$sync_log.id_log|escape:'html':'UTF-8'}</td>
                    </tr>
                    <tr>
                        <td><strong>Tipo de sincronización</strong></td>
                        <td>
                            <span class="label label-info">{$sync_log.sync_type|escape:'html':'UTF-8'}</span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Dirección de sincronización</strong></td>
                        <td>
                            {if $sync_log.sync_direction == 'prestashop_to_yuju'}
                                <span class="label label-primary">
                                    <i class="icon-arrow-right"></i> PrestaShop → Yuju
                                </span>
                            {elseif $sync_log.sync_direction == 'yuju_to_prestashop'}
                                <span class="label label-success">
                                    <i class="icon-arrow-left"></i> Yuju → PrestaShop
                                </span>
                            {else}
                                <span class="label label-default">
                                    <i class="icon-exchange"></i> Bidireccional
                                </span>
                            {/if}
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Estado</strong></td>
                        <td>
                            {if $sync_log.success}
                                <span class="label label-success">
                                    <i class="icon-check"></i> Correcto
                                </span>
                            {else}
                                <span class="label label-danger">
                                    <i class="icon-remove"></i> Fallido
                                </span>
                            {/if}
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Iniciado el</strong></td>
                        <td>{$sync_log.created_at|date_format:"%Y-%m-%d %H:%M:%S"|escape:'html':'UTF-8'}</td>
                    </tr>
                    <tr>
                        <td><strong>Tiempo de ejecución</strong></td>
                        <td>
                            {if $sync_log.execution_time}
                                <strong>{$sync_log.execution_time|string_format:"%.2f"|escape:'html':'UTF-8'} segundos</strong>
                            {else}
                                <span class="text-muted">N/A</span>
                            {/if}
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="col-md-6">
                <h4>Estadísticas</h4>
                <table class="table table-striped">
                    <tr>
                        <td><strong>Productos sincronizados</strong></td>
                        <td>
                            <span class="badge badge-primary">{$sync_log.products_synced|intval}</span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Categorías sincronizadas</strong></td>
                        <td>
                            <span class="badge badge-info">{if isset($sync_log.categories_synced)}{$sync_log.categories_synced|intval}{else}0{/if}</span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Actualizaciones de existencias</strong></td>
                        <td>
                            <span class="badge badge-warning">{if isset($sync_log.stock_synced)}{$sync_log.stock_synced|intval}{else}0{/if}</span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Actualizaciones de precios</strong></td>
                        <td>
                            <span class="badge badge-success">{if isset($sync_log.price_synced)}{$sync_log.price_synced|intval}{else}0{/if}</span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Cantidad de errores</strong></td>
                        <td>
                            <span class="badge badge-danger">{$sync_log.error_count|intval}</span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Porcentaje de éxito</strong></td>
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
                    <h4>Detalles de sincronización</h4>
                    <div class="well">
                        <pre style="max-height: 300px; overflow-y: auto;">{$sync_log.sync_details|escape:'html':'UTF-8'}</pre>
                    </div>
                </div>
            </div>
        {/if}
        
        {if isset($sync_log.error_details) && $sync_log.error_details}
            <div class="row" style="margin-top: 20px;">
                <div class="col-md-12">
                    <h4 class="text-danger">Detalles del error</h4>
                    <div class="alert alert-danger">
                        <pre style="max-height: 300px; overflow-y: auto;">{$sync_log.error_details|escape:'html':'UTF-8'}</pre>
                    </div>
                </div>
            </div>
        {/if}
        
        {if isset($sync_log.processed_items) && count($sync_log.processed_items) > 0}
            <div class="row" style="margin-top: 20px;">
                <div class="col-md-12">
                    <h4>Elementos procesados</h4>
                    <div class="table-responsive">
                        <table class="table table-striped table-condensed">
                            <thead>
                                <tr>
                                    <th>Tipo de elemento</th>
                                    <th>ID del elemento</th>
                                    <th>Nombre del elemento</th>
                                    <th>Estado</th>
                                    <th>Mensaje</th>
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
                                                    <i class="icon-check"></i> Correcto
                                                </span>
                                            {elseif $item.status == 'error'}
                                                <span class="label label-danger">
                                                    <i class="icon-remove"></i> Error
                                                </span>
                                            {elseif $item.status == 'skipped'}
                                                <span class="label label-warning">
                                                    <i class="icon-minus"></i> Omitido
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
                        <i class="icon-arrow-left"></i> Volver a los registros de sincronización
                    </a>
                    
                    {if !$sync_log.success}
                        <button type="button" class="btn btn-warning" onclick="retrySync({$sync_log.id_log|escape:'javascript':'UTF-8'})">
                            <i class="icon-refresh"></i> Reintentar sincronización
                        </button>
                    {/if}
                    
                    <button type="button" class="btn btn-info" onclick="exportLog({$sync_log.id_log|escape:'javascript':'UTF-8'})">
                        <i class="icon-download"></i> Exportar registro
                    </button>
                    
                    <button type="button" class="btn btn-danger" onclick="deleteLog({$sync_log.id_log|escape:'javascript':'UTF-8'})">
                        <i class="icon-trash"></i> Eliminar registro
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
function retrySync(logId) {
    if (confirm('¿Seguro que deseas reintentar esta sincronización?')) {
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
                    showSuccessMessage('Se inició el reintento de sincronización correctamente');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showErrorMessage(response.message || 'No se pudo reintentar la sincronización');
                }
            },
            error: function() {
                showErrorMessage('Ocurrió un error al reintentar la sincronización');
            }
        });
    }
}

function exportLog(logId) {
    window.open('{$link->getAdminLink('AdminYujuSync')|escape:'html':'UTF-8'}&action=exportLog&id_log=' + logId, '_blank');
}

function deleteLog(logId) {
    if (confirm('¿Seguro que deseas eliminar este registro? Esta acción no se puede deshacer.')) {
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
                    showSuccessMessage('Registro eliminado correctamente');
                    setTimeout(function() {
                        window.location.href = '{$link->getAdminLink('AdminYujuSync')|escape:'html':'UTF-8'}';
                    }, 1500);
                } else {
                    showErrorMessage(response.message || 'No se pudo eliminar el registro');
                }
            },
            error: function() {
                showErrorMessage('Ocurrió un error al eliminar el registro');
            }
        });
    }
}
</script>
{/block}