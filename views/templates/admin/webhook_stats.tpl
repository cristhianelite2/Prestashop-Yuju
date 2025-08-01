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


{block name="content"}
<div class="panel">
    <div class="panel-heading">
        <i class="icon-bar-chart"></i>
        Estadísticas de Webhooks
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-lg-3">
                <div class="info-box">
                    <div class="info-box-icon bg-blue">
                        <i class="icon-globe"></i>
                    </div>
                    <div class="info-box-content">
                        <span class="info-box-text">Total de Webhooks</span>
                        <span class="info-box-number">{$webhook_stats.total_received|default:0|escape:'html':'UTF-8'}</span>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3">
                <div class="info-box">
                    <div class="info-box-icon bg-green">
                        <i class="icon-check"></i>
                    </div>
                    <div class="info-box-content">
                        <span class="info-box-text">Procesados</span>
                        <span class="info-box-number">
                            {assign var="processed" value=0}
                            {foreach from=$webhook_stats.by_status item=status}
                                {if $status.status == 'processed'}
                                    {assign var="processed" value=$status.count}
                                {/if}
                            {/foreach}
                            {$processed|escape:'html':'UTF-8'}
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3">
                <div class="info-box">
                    <div class="info-box-icon bg-red">
                        <i class="icon-times"></i>
                    </div>
                    <div class="info-box-content">
                        <span class="info-box-text">Fallidos</span>
                        <span class="info-box-number">
                            {assign var="failed" value=0}
                            {foreach from=$webhook_stats.by_status item=status}
                                {if $status.status == 'failed'}
                                    {assign var="failed" value=$status.count}
                                {/if}
                            {/foreach}
                            {$failed|escape:'html':'UTF-8'}
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3">
                <div class="info-box">
                    <div class="info-box-icon bg-yellow">
                        <i class="icon-clock-o"></i>
                    </div>
                    <div class="info-box-content">
                        <span class="info-box-text">Procesando</span>
                        <span class="info-box-number">
                            {assign var="processing" value=0}
                            {foreach from=$webhook_stats.by_status item=status}
                                {if $status.status == 'processing'}
                                    {assign var="processing" value=$status.count}
                                {/if}
                            {/foreach}
                            {$processing|escape:'html':'UTF-8'}
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-6">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title">URL del Webhook</h4>
                    </div>
                    <div class="panel-body">
                        <div class="form-group">
                            <label>URL Actual del Webhook:</label>
                            <div class="input-group">
                                <input type="text" class="form-control" value="{$webhook_url|escape:'html':'UTF-8'}" readonly>
                                <span class="input-group-btn">
                                    <button class="btn btn-default" type="button" onclick="copyToClipboard(&quot;{$webhook_url|escape:'javascript':'UTF-8'}&quot;)">
                                        <i class="icon-copy"></i> Copiar
                                    </button>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title">Webhooks Registrados</h4>
                    </div>
                    <div class="panel-body">
                        {if $webhook_stats.registered_webhooks && count($webhook_stats.registered_webhooks) > 0}
                            <ul class="list-group">
                                {foreach from=$webhook_stats.registered_webhooks item=webhook}
                                    <li class="list-group-item">
                                        <span class="badge">{$webhook.yuju_webhook_id|escape:'html':'UTF-8'}</span>
                                        {$webhook.event_type|escape:'html':'UTF-8'}
                                    </li>
                                {/foreach}
                            </ul>
                        {else}
                            <p class="text-muted">No hay webhooks registrados</p>
                        {/if}
                    </div>
                </div>
            </div>
        </div>
        
        {if $webhook_stats.by_event_type && count($webhook_stats.by_event_type) > 0}
        <div class="row">
            <div class="col-lg-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title">Webhooks por Tipo de Evento</h4>
                    </div>
                    <div class="panel-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Tipo de Evento</th>
                                        <th>Cantidad</th>
                                        <th>Porcentaje</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {foreach from=$webhook_stats.by_event_type item=event}
                                        <tr>
                                            <td>{$event.event_type|escape:'html':'UTF-8'}</td>
                                            <td>{$event.count|escape:'html':'UTF-8'}</td>
                                            <td>
                                                {assign var="percentage" value=($event.count / $webhook_stats.total_received * 100)|round:1}
                                                <div class="progress">
                                                    <div class="progress-bar" role="progressbar" style="width: {$percentage|escape:'html':'UTF-8'}%">
                                                        {$percentage|escape:'html':'UTF-8'}%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    {/foreach}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        {/if}
        
        {if $webhook_stats.recent_webhooks && count($webhook_stats.recent_webhooks) > 0}
        <div class="row">
            <div class="col-lg-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title">Webhooks Recientes</h4>
                    </div>
                    <div class="panel-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Tipo de Evento</th>
                                        <th>ID de Entidad</th>
                                        <th>Estado</th>
                                        <th>Recibido en</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {foreach from=$webhook_stats.recent_webhooks item=webhook}
                                        <tr>
                                            <td>{$webhook.event_type|escape:'html':'UTF-8'}</td>
                                            <td>{$webhook.entity_id|escape:'html':'UTF-8'}</td>
                                            <td>
                                                {if $webhook.status == 'processed'}
                                                    <span class="label label-success">Procesado</span>
                                                {elseif $webhook.status == 'failed'}
                                                    <span class="label label-danger">Fallido</span>
                                                {else}
                                                    <span class="label label-warning">Procesando</span>
                                                {/if}
                                            </td>
                                            <td>{$webhook.received_at|escape:'html':'UTF-8'}</td>
                                        </tr>
                                    {/foreach}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        {/if}
    </div>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        showSuccessMessage('URL copiada al portapapeles');
    }, function(err) {
        console.error('Could not copy text: ', err);
    });
}
</script>

<style>
.info-box {
    display: block;
    min-height: 90px;
    background: #fff;
    width: 100%;
    box-shadow: 0 1px 1px rgba(0,0,0,0.1);
    border-radius: 2px;
    margin-bottom: 15px;
}

.info-box-icon {
    border-top-left-radius: 2px;
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
    border-bottom-left-radius: 2px;
    display: block;
    float: left;
    height: 90px;
    width: 90px;
    text-align: center;
    font-size: 45px;
    line-height: 90px;
    background: rgba(0,0,0,0.2);
}

.info-box-icon > i {
    color: #fff;
}

.info-box-content {
    padding: 5px 10px;
    margin-left: 90px;
}

.info-box-number {
    display: block;
    font-weight: bold;
    font-size: 18px;
}

.info-box-text {
    display: block;
    font-size: 14px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.bg-blue { background-color: #3c8dbc !important; }
.bg-green { background-color: #00a65a !important; }
.bg-red { background-color: #dd4b39 !important; }
.bg-yellow { background-color: #f39c12 !important; }
</style>
{/block}