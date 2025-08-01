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
        <i class="icon-eye"></i>
        Detalles del Webhook - ID: {$webhook_log.id|escape:'html':'UTF-8'}
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-lg-6">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title">Información Básica</h4>
                    </div>
                    <div class="panel-body">
                        <dl class="dl-horizontal">
                            <dt>ID:</dt>
                            <dd>{$webhook_log.id|escape:'html':'UTF-8'}</dd>
                            
                            <dt>Tipo de Evento:</dt>
                            <dd><span class="label label-info">{$webhook_log.event_type|escape:'html':'UTF-8'}</span></dd>
                            
                            <dt>ID de Entidad:</dt>
                            <dd>{$webhook_log.entity_id|default:'-'|escape:'html':'UTF-8'}</dd>
                            
                            <dt>Estado:</dt>
                            <dd>
                                {if $webhook_log.status == 'processed'}
                                    <span class="label label-success">Procesado</span>
                                {elseif $webhook_log.status == 'failed'}
                                    <span class="label label-danger">Fallido</span>
                                {else}
                                    <span class="label label-warning">Procesando</span>
                                {/if}
                            </dd>
                            
                            <dt>Recibido en:</dt>
                            <dd>{$webhook_log.received_at|escape:'html':'UTF-8'}</dd>
                            
                            <dt>Procesado en:</dt>
                            <dd>{$webhook_log.processed_at|default:'-'|escape:'html':'UTF-8'}</dd>
                        </dl>
                        
                        {if $webhook_log.error_message}
                        <div class="alert alert-danger">
                            <strong>Mensaje de Error:</strong><br>
                            {$webhook_log.error_message|escape:'html':'UTF-8'}
                        </div>
                        {/if}
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title">Acciones</h4>
                    </div>
                    <div class="panel-body">
                        {if $webhook_log.status == 'failed'}
                        <button type="button" class="btn btn-warning" onclick="retryWebhook({$webhook_log.id|escape:'javascript':'UTF-8'})">
                            <i class="icon-refresh"></i> Reintentar Webhook
                        </button>
                        {/if}
                        
                        <button type="button" class="btn btn-info" onclick="exportWebhookLog({$webhook_log.id|escape:'javascript':'UTF-8'})">
                            <i class="icon-download"></i> Exportar Registro
                        </button>
                        
                        <a href="{$link->getAdminLink('AdminYujuWebhook')|escape:'html':'UTF-8'}" class="btn btn-default">
                            <i class="icon-arrow-left"></i> Volver a la Lista
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-6">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title">Encabezados de Solicitud</h4>
                    </div>
                    <div class="panel-body">
                        {if $webhook_log.headers_decoded}
                            <div class="table-responsive">
                                <table class="table table-striped table-condensed">
                                    <thead>
                                        <tr>
                                            <th>Encabezado</th>
                                            <th>Valor</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {foreach from=$webhook_log.headers_decoded key=header_name item=header_value}
                                            <tr>
                                                <td><strong>{$header_name|escape:'html':'UTF-8'}</strong></td>
                                                <td><code>{$header_value|escape:'html':'UTF-8'}</code></td>
                                            </tr>
                                        {/foreach}
                                    </tbody>
                                </table>
                            </div>
                        {else}
                            <p class="text-muted">No hay encabezados disponibles</p>
                        {/if}
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title">Datos de Respuesta</h4>
                    </div>
                    <div class="panel-body">
                        {if $webhook_log.response_decoded}
                            <pre class="prettyprint lang-json">{$webhook_log.response|escape:'html':'UTF-8'}</pre>
                        {else}
                            <p class="text-muted">No hay datos de respuesta disponibles</p>
                        {/if}
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title">{l s='Webhook Payload' mod='prestashopyuju'}</h4>
                    </div>
                    <div class="panel-body">
                        {if $webhook_log.payload_decoded}
                            <div class="row">
                                <div class="col-lg-6">
                                    <h5>{l s='Formatted JSON' mod='prestashopyuju'}</h5>
                                    <pre class="prettyprint lang-json">{$webhook_log.payload|escape:'html':'UTF-8'}</pre>
                                </div>
                                <div class="col-lg-6">
                                    <h5>{l s='Parsed Data' mod='prestashopyuju'}</h5>
                                    <div class="table-responsive">
                                        <table class="table table-striped table-condensed">
                                            {function name=displayArray data=$webhook_log.payload_decoded level=0}
                                                {foreach from=$data key=key item=value}
                                                    <tr>
                                                        <td style="padding-left: {$level*20|escape:'html':'UTF-8'}px;">
                                            <strong>{$key|escape:'html':'UTF-8'}:</strong>
                                                        </td>
                                                        <td>
                                                            {if is_array($value)}
                                                                <em>{l s='Array/Object' mod='prestashopyuju'}</em>
                                                            {else}
                                                                <code>{$value|escape:'html':'UTF-8'}</code>
                                                            {/if}
                                                        </td>
                                                    </tr>
                                                    {if is_array($value) && $level < 3}
                                                        {call name=displayArray data=$value level=$level+1}
                                                    {/if}
                                                {/foreach}
                                            {/function}
                                            
                                            {call name=displayArray data=$webhook_log.payload_decoded level=0}
                                        </table>
                                    </div>
                                </div>
                            </div>
                        {else}
                            <div class="alert alert-warning">
                                {l s='Invalid JSON payload' mod='prestashopyuju'}
                            </div>
                            <pre>{$webhook_log.payload|escape:'html':'UTF-8'}</pre>
                        {/if}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function retryWebhook(webhookId) {
    if (confirm('¿Está seguro de que desea reintentar este webhook?')) {
        $.ajax({
            url: '{$link->getAdminLink('AdminYujuWebhook')|escape:'javascript':'UTF-8'}',
            type: 'POST',
            data: {
                ajax: true,
                action: 'retryWebhook',
                webhook_id: webhookId
            },
            success: function(response) {
                if (response.success) {
                    showSuccessMessage('Webhook reintentado exitosamente');
                    location.reload();
                } else {
                    showErrorMessage(response.error || 'Error al reintentar webhook');
                }
            },
            error: function() {
                showErrorMessage('Error al reintentar webhook');
            }
        });
    }
}

function exportWebhookLog(webhookId) {
    window.open('{$link->getAdminLink('AdminYujuWebhook')|escape:'javascript':'UTF-8'}&action=exportWebhookLog&webhook_id=' + webhookId, '_blank');
}

// Pretty print JSON
$(document).ready(function() {
    if (typeof prettyPrint !== 'undefined') {
        prettyPrint();
    }
});
</script>

<style>
.dl-horizontal dt {
    width: 120px;
}

.dl-horizontal dd {
    margin-left: 140px;
}

pre {
    max-height: 400px;
    overflow-y: auto;
    background-color: #f5f5f5;
    border: 1px solid #ccc;
    border-radius: 4px;
    padding: 10px;
    font-size: 12px;
}

code {
    background-color: #f9f2f4;
    color: #c7254e;
    padding: 2px 4px;
    border-radius: 3px;
}

.table-condensed td {
    padding: 4px 8px;
}
</style>
{/block}