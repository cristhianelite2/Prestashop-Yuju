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
        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-md-3">
                <div class="panel panel-default">
                    <div class="panel-body text-center">
                        <div class="row">
                            <div class="col-xs-3">
                                <i class="icon-list icon-3x text-primary"></i>
                            </div>
                            <div class="col-xs-9 text-right">
                                <div class="huge">{$webhook_stats.total_received|default:0|escape:'html':'UTF-8'}</div>
                                <div>Total de Webhooks</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="panel panel-success">
                    <div class="panel-body text-center">
                        <div class="row">
                            <div class="col-xs-3">
                                <i class="icon-check icon-3x"></i>
                            </div>
                            <div class="col-xs-9 text-right">
                                <div class="huge">
                                    {assign var="processed" value=0}
                                    {if $webhook_stats.by_status}
                                        {foreach from=$webhook_stats.by_status item=status}
                                            {if $status.status == 'processed'}
                                                {assign var="processed" value=$status.count}
                                            {/if}
                                        {/foreach}
                                    {/if}
                                    {$processed|escape:'html':'UTF-8'}
                                </div>
                                <div>Procesados</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="panel panel-danger">
                    <div class="panel-body text-center">
                        <div class="row">
                            <div class="col-xs-3">
                                <i class="icon-times icon-3x"></i>
                            </div>
                            <div class="col-xs-9 text-right">
                                <div class="huge">
                                    {assign var="failed" value=0}
                                    {if $webhook_stats.by_status}
                                        {foreach from=$webhook_stats.by_status item=status}
                                            {if $status.status == 'failed'}
                                                {assign var="failed" value=$status.count}
                                            {/if}
                                        {/foreach}
                                    {/if}
                                    {$failed|escape:'html':'UTF-8'}
                                </div>
                                <div>Fallidos</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="panel panel-warning">
                    <div class="panel-body text-center">
                        <div class="row">
                            <div class="col-xs-3">
                                <i class="icon-clock-o icon-3x"></i>
                            </div>
                            <div class="col-xs-9 text-right">
                                <div class="huge">
                                    {assign var="processing" value=0}
                                    {if $webhook_stats.by_status}
                                        {foreach from=$webhook_stats.by_status item=status}
                                            {if $status.status == 'processing'}
                                                {assign var="processing" value=$status.count}
                                            {/if}
                                        {/foreach}
                                    {/if}
                                    {$processing|escape:'html':'UTF-8'}
                                </div>
                                <div>Procesando</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Configuration and Registered Webhooks Section -->
        <div class="row">
            <div class="col-md-6">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <i class="icon-cog"></i> URL del Webhook
                    </div>
                    <div class="panel-body">
                        <div class="form-group">
                            <label class="control-label">URL Actual del Webhook:</label>
                            <div class="input-group">
                                <input type="text" class="form-control" value="{$webhook_url|escape:'html':'UTF-8'}" readonly>
                                <span class="input-group-btn">
                                    <button class="btn btn-default" type="button" onclick="copyToClipboard('{$webhook_url|escape:'javascript':'UTF-8'}')">
                                        <i class="icon-copy"></i> Copiar
                                    </button>
                                </span>
                            </div>
                            <p class="help-block">
                                <i class="icon-info-circle"></i>
                                Esta URL debe configurarse en Yuju para recibir webhooks
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <i class="icon-list-alt"></i> Webhooks Registrados
                        <span class="badge pull-right">
                            {if $webhook_stats.registered_webhooks}
                                {count($webhook_stats.registered_webhooks)}
                            {else}
                                0
                            {/if}
                        </span>
                    </div>
                    <div class="panel-body">
                        {if $webhook_stats.registered_webhooks && count($webhook_stats.registered_webhooks) > 0}
                            <div class="list-group">
                                {foreach from=$webhook_stats.registered_webhooks item=webhook}
                                    <div class="list-group-item">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <h5 class="list-group-item-heading">{$webhook.event_type|escape:'html':'UTF-8'}</h5>
                                                <p class="list-group-item-text">ID: {$webhook.yuju_webhook_id|escape:'html':'UTF-8'}</p>
                                            </div>
                                            <div class="col-md-4 text-right">
                                                <span class="label label-success">
                                                    <i class="icon-check"></i> Activo
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                {/foreach}
                            </div>
                        {else}
                            <div class="alert alert-info text-center">
                                <i class="icon-info-circle"></i>
                                No hay webhooks registrados
                            </div>
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
        
        <!-- Recent Webhooks Section -->
        {if $webhook_stats.recent_webhooks && count($webhook_stats.recent_webhooks) > 0}
        <div class="row">
            <div class="col-lg-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <i class="icon-clock-o"></i> Actividad Reciente
                        <span class="pull-right">
                            <small><i class="icon-refresh"></i> Actualizado hace 2 min</small>
                        </span>
                    </div>
                    <div class="panel-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Estado</th>
                                        <th>Tipo de Evento</th>
                                        <th>Entidad ID</th>
                                        <th>Fecha</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {foreach from=$webhook_stats.recent_webhooks item=webhook}
                                        <tr>
                                            <td>
                                                {if $webhook.status == 'processed'}
                                                    <i class="icon-check text-success"></i>
                                                {elseif $webhook.status == 'failed'}
                                                    <i class="icon-times text-danger"></i>
                                                {else}
                                                    <i class="icon-clock-o text-warning"></i>
                                                {/if}
                                            </td>
                                            <td>{$webhook.event_type|escape:'html':'UTF-8'}</td>
                                            <td><code>{$webhook.entity_id|escape:'html':'UTF-8'}</code></td>
                                            <td>{$webhook.received_at|escape:'html':'UTF-8'}</td>
                                            <td>
                                                {if $webhook.status == 'processed'}
                                                    <span class="label label-success">
                                                        <i class="icon-check"></i> Procesado
                                                    </span>
                                                {elseif $webhook.status == 'failed'}
                                                    <span class="label label-danger">
                                                        <i class="icon-exclamation-triangle"></i> Fallido
                                                    </span>
                                                {else}
                                                    <span class="label label-warning">
                                                        <i class="icon-spinner icon-spin"></i> Procesando
                                                    </span>
                                                {/if}
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
    </div>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        showSuccessMessage('URL copiada al portapapeles');
    }, function(err) {

    });
}
</script>

<style>
/* Conservative Webhook Styles for PrestaShop Integration */
.huge {
    font-size: 40px;
    font-weight: bold;
}

.panel-body .row {
    margin-bottom: 0;
}

.panel-body .col-xs-3 {
    padding-right: 0;
}

.panel-body .col-xs-9 {
    padding-left: 5px;
}

.text-primary {
    color: #337ab7;
}

.icon-3x {
    font-size: 3em;
}

.badge {
    background-color: #777;
}

.list-group-item-heading {
    margin-top: 0;
    margin-bottom: 5px;
}

.list-group-item-text {
    margin-bottom: 0;
    line-height: 1.3;
}

.table-responsive {
    border: none;
}

.table-striped > tbody > tr:nth-of-type(odd) {
    background-color: #f9f9f9;
}

code {
    padding: 2px 4px;
    font-size: 90%;
    color: #c7254e;
    background-color: #f9f2f4;
    border-radius: 4px;
}

.text-success {
    color: #3c763d;
}

.text-danger {
    color: #a94442;
}

.text-warning {
    color: #8a6d3b;
}

.label {
    display: inline;
    padding: .2em .6em .3em;
    font-size: 75%;
    font-weight: bold;
    line-height: 1;
    color: #fff;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: .25em;
}

.label-success {
    background-color: #5cb85c;
}

.label-danger {
    background-color: #d9534f;
}

.label-warning {
    background-color: #f0ad4e;
}

.label-default {
    background-color: #777;
}

.alert-info {
    color: #31708f;
    background-color: #d9edf7;
    border-color: #bce8f1;
}

.help-block {
    display: block;
    margin-top: 5px;
    margin-bottom: 10px;
    color: #737373;
}

.input-group {
    position: relative;
    display: table;
    border-collapse: separate;
}

.input-group .form-control {
    position: relative;
    z-index: 2;
    float: left;
    width: 100%;
    margin-bottom: 0;
}

.input-group-btn {
    position: relative;
    font-size: 0;
    white-space: nowrap;
    width: 1%;
    vertical-align: middle;
    display: table-cell;
}

.input-group .form-control,
.input-group-btn {
    display: table-cell;
}

.input-group .form-control:not(:first-child):not(:last-child),
.input-group-btn:not(:first-child):not(:last-child) {
    border-radius: 0;
}

.input-group .form-control:first-child,
.input-group-btn:first-child > .btn {
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
}

.input-group .form-control:last-child,
.input-group-btn:last-child > .btn {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
}

.btn-default {
    color: #333;
    background-color: #fff;
    border-color: #ccc;
}

.btn-default:hover {
    color: #333;
    background-color: #e6e6e6;
    border-color: #adadad;
}

/* Custom styles for better integration */
.panel-heading {
    padding: 10px 15px;
    border-bottom: 1px solid transparent;
    border-top-left-radius: 3px;
    border-top-right-radius: 3px;
}

.panel-body {
    padding: 15px;
}

.panel-default {
    border-color: #ddd;
}

.panel-default > .panel-heading {
    color: #333;
    background-color: #f5f5f5;
    border-color: #ddd;
}

.panel-success {
    border-color: #d6e9c6;
}

.panel-success > .panel-heading {
    color: #3c763d;
    background-color: #dff0d8;
    border-color: #d6e9c6;
}

.panel-danger {
    border-color: #ebccd1;
}

.panel-danger > .panel-heading {
    color: #a94442;
    background-color: #f2dede;
    border-color: #ebccd1;
}

.panel-warning {
    border-color: #faebcc;
}

.panel-warning > .panel-heading {
    color: #8a6d3b;
    background-color: #fcf8e3;
    border-color: #faebcc;
}
</style>
{/block}