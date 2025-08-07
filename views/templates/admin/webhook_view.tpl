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
<div class="yuju-webhook-detail-container">
    <!-- Header Section -->
    <div class="detail-header">
        <div class="header-content">
            <div class="header-left">
                <div class="webhook-icon">
                    <i class="icon-eye"></i>
                </div>
                <div class="header-info">
                    <h1 class="detail-title">Detalles del Webhook</h1>
                    <p class="detail-subtitle">ID: {$webhook_log.id|escape:'html':'UTF-8'}</p>
                </div>
            </div>
            <div class="header-right">
                <div class="status-indicator {if $webhook_log.status == 'processed'}success{elseif $webhook_log.status == 'failed'}danger{else}warning{/if}">
                    <i class="{if $webhook_log.status == 'processed'}icon-check{elseif $webhook_log.status == 'failed'}icon-times{else}icon-clock-o{/if}"></i>
                    <span>
                        {if $webhook_log.status == 'processed'}Procesado
                        {elseif $webhook_log.status == 'failed'}Fallido
                        {else}Procesando{/if}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="detail-content">
        <div class="row detail-row">
            <div class="col-lg-6">
                <div class="detail-card">
                    <div class="card-header">
                        <i class="icon-info-circle"></i>
                        <h4>Información Básica</h4>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <label>ID:</label>
                                <span class="info-value">{$webhook_log.id|escape:'html':'UTF-8'}</span>
                            </div>
                            
                            <div class="info-item">
                                <label>Tipo de Evento:</label>
                                <span class="event-badge">{$webhook_log.event_type|escape:'html':'UTF-8'}</span>
                            </div>
                            
                            <div class="info-item">
                                <label>ID de Entidad:</label>
                                <span class="info-value">{$webhook_log.entity_id|default:'-'|escape:'html':'UTF-8'}</span>
                            </div>
                            
                            <div class="info-item">
                                <label>Estado:</label>
                                <span class="status-badge {if $webhook_log.status == 'processed'}success{elseif $webhook_log.status == 'failed'}danger{else}warning{/if}">
                                    <i class="{if $webhook_log.status == 'processed'}icon-check{elseif $webhook_log.status == 'failed'}icon-times{else}icon-clock-o{/if}"></i>
                                    {if $webhook_log.status == 'processed'}Procesado
                                    {elseif $webhook_log.status == 'failed'}Fallido
                                    {else}Procesando{/if}
                                </span>
                            </div>
                            
                            <div class="info-item">
                                <label>Recibido en:</label>
                                <span class="info-value timestamp">{$webhook_log.received_at|escape:'html':'UTF-8'}</span>
                            </div>
                            
                            <div class="info-item">
                                <label>Procesado en:</label>
                                <span class="info-value timestamp">{$webhook_log.processed_at|default:'-'|escape:'html':'UTF-8'}</span>
                            </div>
                        </div>
                        
                        {if $webhook_log.error_message}
                        <div class="error-message">
                            <div class="error-header">
                                <i class="icon-exclamation-triangle"></i>
                                <strong>Mensaje de Error</strong>
                            </div>
                            <div class="error-content">
                                {$webhook_log.error_message|escape:'html':'UTF-8'}
                            </div>
                        </div>
                        {/if}
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="detail-card">
                    <div class="card-header">
                        <i class="icon-cogs"></i>
                        <h4>Acciones</h4>
                    </div>
                    <div class="card-body">
                        <div class="actions-grid">
                            {if $webhook_log.status == 'failed'}
                            <button type="button" class="action-btn retry-btn" onclick="retryWebhook({$webhook_log.id|escape:'javascript':'UTF-8'})">
                                <div class="btn-icon">
                                    <i class="icon-refresh"></i>
                                </div>
                                <div class="btn-content">
                                    <span class="btn-title">Reintentar Webhook</span>
                                    <span class="btn-subtitle">Procesar nuevamente este webhook</span>
                                </div>
                            </button>
                            {/if}
                            
                            <button type="button" class="action-btn export-btn" onclick="exportWebhookLog({$webhook_log.id|escape:'javascript':'UTF-8'})">
                                <div class="btn-icon">
                                    <i class="icon-download"></i>
                                </div>
                                <div class="btn-content">
                                    <span class="btn-title">Exportar Registro</span>
                                    <span class="btn-subtitle">Descargar datos del webhook</span>
                                </div>
                            </button>
                            
                            <a href="{$link->getAdminLink('AdminYujuWebhook')|escape:'html':'UTF-8'}" class="action-btn back-btn">
                                <div class="btn-icon">
                                    <i class="icon-arrow-left"></i>
                                </div>
                                <div class="btn-content">
                                    <span class="btn-title">Volver a la Lista</span>
                                    <span class="btn-subtitle">Regresar al listado de webhooks</span>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row detail-row">
            <div class="col-lg-6">
                <div class="detail-card">
                    <div class="card-header">
                        <i class="icon-list-alt"></i>
                        <h4>Encabezados de Solicitud</h4>
                    </div>
                    <div class="card-body">
                        {if $webhook_log.headers_decoded}
                            <div class="headers-list">
                                {foreach from=$webhook_log.headers_decoded key=header_name item=header_value}
                                    <div class="header-item">
                                        <div class="header-name">{$header_name|escape:'html':'UTF-8'}</div>
                                        <div class="header-value">{$header_value|escape:'html':'UTF-8'}</div>
                                    </div>
                                {/foreach}
                            </div>
                        {else}
                            <div class="empty-state">
                                <i class="icon-info-circle"></i>
                                <p>No hay encabezados disponibles</p>
                            </div>
                        {/if}
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="detail-card">
                    <div class="card-header">
                        <i class="icon-code"></i>
                        <h4>Datos de Respuesta</h4>
                    </div>
                    <div class="card-body">
                        {if $webhook_log.response_decoded}
                            <div class="code-container">
                                <div class="code-header">
                                    <span class="code-label">JSON Response</span>
                                    <button class="copy-code-btn" onclick="copyToClipboard('response-code')">
                                        <i class="icon-copy"></i>
                                    </button>
                                </div>
                                <pre id="response-code" class="code-block">{$webhook_log.response|escape:'html':'UTF-8'}</pre>
                            </div>
                        {else}
                            <div class="empty-state">
                                <i class="icon-info-circle"></i>
                                <p>No hay datos de respuesta disponibles</p>
                            </div>
                        {/if}
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row detail-row">
            <div class="col-lg-12">
                <div class="detail-card payload-card">
                    <div class="card-header">
                        <i class="icon-file-code-o"></i>
                        <h4>Webhook Payload</h4>
                        <div class="payload-tabs">
                            <button class="tab-btn active" onclick="switchTab('json')" id="json-tab">
                                <i class="icon-code"></i> JSON
                            </button>
                            <button class="tab-btn" onclick="switchTab('parsed')" id="parsed-tab">
                                <i class="icon-list"></i> Datos Parseados
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        {if $webhook_log.payload_decoded}
                            <div class="payload-content">
                                <div class="tab-content active" id="json-content">
                                    <div class="code-container">
                                        <div class="code-header">
                                            <span class="code-label">Formatted JSON</span>
                                            <button class="copy-code-btn" onclick="copyToClipboard('payload-code')">
                                                <i class="icon-copy"></i> Copiar
                                            </button>
                                        </div>
                                        <pre id="payload-code" class="code-block json-code">{$webhook_log.payload|escape:'html':'UTF-8'}</pre>
                                    </div>
                                </div>
                                
                                <div class="tab-content" id="parsed-content">
                                    <div class="parsed-data">
                                        {function name=displayArray data=$webhook_log.payload_decoded level=0}
                                            {foreach from=$data key=key item=value}
                                                <div class="data-item" style="margin-left: {$level*20|escape:'html':'UTF-8'}px;">
                                                    <div class="data-key">{$key|escape:'html':'UTF-8'}</div>
                                                    <div class="data-value">
                                                        {if is_array($value)}
                                                            <span class="array-indicator">
                                                                <i class="icon-folder"></i> Array/Object
                                                            </span>
                                                        {else}
                                                            <span class="value-content">{$value|escape:'html':'UTF-8'}</span>
                                                        {/if}
                                                    </div>
                                                </div>
                                                {if is_array($value) && $level < 3}
                                                    {call name=displayArray data=$value level=$level+1}
                                                {/if}
                                            {/foreach}
                                        {/function}
                                        
                                        {call name=displayArray data=$webhook_log.payload_decoded level=0}
                                    </div>
                                </div>
                            </div>
                        {else}
                            <div class="invalid-payload">
                                <div class="warning-header">
                                    <i class="icon-exclamation-triangle"></i>
                                    <strong>JSON Payload Inválido</strong>
                                </div>
                                <div class="raw-payload">
                                    <pre class="code-block">{$webhook_log.payload|escape:'html':'UTF-8'}</pre>
                                </div>
                            </div>
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

function switchTab(tabName) {
    // Remove active class from all tabs and contents
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    // Add active class to selected tab and content
    document.getElementById(tabName + '-tab').classList.add('active');
    document.getElementById(tabName + '-content').classList.add('active');
}

function copyToClipboard(elementId) {
    const element = document.getElementById(elementId);
    const text = element.textContent || element.innerText;
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function() {
            showSuccessMessage('Código copiado al portapapeles');
        }).catch(function() {
            fallbackCopyTextToClipboard(text);
        });
    } else {
        fallbackCopyTextToClipboard(text);
    }
}

function fallbackCopyTextToClipboard(text) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    textArea.style.top = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        document.execCommand('copy');
        showSuccessMessage('Código copiado al portapapeles');
    } catch (err) {
        showErrorMessage('Error al copiar código');
    }
    
    document.body.removeChild(textArea);
}

function showSuccessMessage(message) {
    // Implementation depends on your notification system
    alert(message);
}

function showErrorMessage(message) {
    // Implementation depends on your notification system
    alert(message);
}

// Pretty print JSON and initialize
$(document).ready(function() {
    if (typeof prettyPrint !== 'undefined') {
        prettyPrint();
    }
});
</script>

<style>
/* Conservative Webhook Detail Styles for PrestaShop Integration */
.panel {
    margin-bottom: 20px;
    background-color: #fff;
    border: 1px solid transparent;
    border-radius: 4px;
    -webkit-box-shadow: 0 1px 1px rgba(0,0,0,.05);
    box-shadow: 0 1px 1px rgba(0,0,0,.05);
}

.panel-body {
    padding: 15px;
}

.panel-heading {
    padding: 10px 15px;
    border-bottom: 1px solid transparent;
    border-top-left-radius: 3px;
    border-top-right-radius: 3px;
}

.panel-default {
    border-color: #ddd;
}

.panel-default > .panel-heading {
    color: #333;
    background-color: #f5f5f5;
    border-color: #ddd;
}

.panel-primary {
    border-color: #337ab7;
}

.panel-primary > .panel-heading {
    color: #fff;
    background-color: #337ab7;
    border-color: #337ab7;
}

.panel-success {
    border-color: #d6e9c6;
}

.panel-success > .panel-heading {
    color: #3c763d;
    background-color: #dff0d8;
    border-color: #d6e9c6;
}

.panel-info {
    border-color: #bce8f1;
}

.panel-info > .panel-heading {
    color: #31708f;
    background-color: #d9edf7;
    border-color: #bce8f1;
}

.panel-warning {
    border-color: #faebcc;
}

.panel-warning > .panel-heading {
    color: #8a6d3b;
    background-color: #fcf8e3;
    border-color: #faebcc;
}

.panel-danger {
    border-color: #ebccd1;
}

.panel-danger > .panel-heading {
    color: #a94442;
    background-color: #f2dede;
    border-color: #ebccd1;
}

.panel-title {
    margin-top: 0;
    margin-bottom: 0;
    font-size: 16px;
    color: inherit;
}

.btn {
    display: inline-block;
    padding: 6px 12px;
    margin-bottom: 0;
    font-size: 14px;
    font-weight: normal;
    line-height: 1.42857143;
    text-align: center;
    white-space: nowrap;
    vertical-align: middle;
    cursor: pointer;
    border: 1px solid transparent;
    border-radius: 4px;
    text-decoration: none;
}

.btn-primary {
    color: #fff;
    background-color: #337ab7;
    border-color: #2e6da4;
}

.btn-primary:hover {
    color: #fff;
    background-color: #286090;
    border-color: #204d74;
    text-decoration: none;
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
    text-decoration: none;
}

.btn-success {
    color: #fff;
    background-color: #5cb85c;
    border-color: #4cae4c;
}

.btn-success:hover {
    color: #fff;
    background-color: #449d44;
    border-color: #398439;
    text-decoration: none;
}

.btn-info {
    color: #fff;
    background-color: #5bc0de;
    border-color: #46b8da;
}

.btn-info:hover {
    color: #fff;
    background-color: #31b0d5;
    border-color: #269abc;
    text-decoration: none;
}

.btn-warning {
    color: #fff;
    background-color: #f0ad4e;
    border-color: #eea236;
}

.btn-warning:hover {
    color: #fff;
    background-color: #ec971f;
    border-color: #d58512;
    text-decoration: none;
}

.btn-danger {
    color: #fff;
    background-color: #d9534f;
    border-color: #d43f3a;
}

.btn-danger:hover {
    color: #fff;
    background-color: #c9302c;
    border-color: #ac2925;
    text-decoration: none;
}

.table {
    width: 100%;
    max-width: 100%;
    margin-bottom: 20px;
    background-color: transparent;
    border-collapse: collapse;
    border-spacing: 0;
}

.table > thead > tr > th,
.table > tbody > tr > th,
.table > tfoot > tr > th,
.table > thead > tr > td,
.table > tbody > tr > td,
.table > tfoot > tr > td {
    padding: 8px;
    line-height: 1.42857143;
    vertical-align: top;
    border-top: 1px solid #ddd;
}

.table > thead > tr > th {
    vertical-align: bottom;
    border-bottom: 2px solid #ddd;
    font-weight: bold;
}

.table-striped > tbody > tr:nth-of-type(odd) {
    background-color: #f9f9f9;
}

.table-bordered {
    border: 1px solid #ddd;
}

.table-bordered > thead > tr > th,
.table-bordered > tbody > tr > th,
.table-bordered > tfoot > tr > th,
.table-bordered > thead > tr > td,
.table-bordered > tbody > tr > td,
.table-bordered > tfoot > tr > td {
    border: 1px solid #ddd;
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

.label-default {
    background-color: #777;
}

.label-primary {
    background-color: #337ab7;
}

.label-success {
    background-color: #5cb85c;
}

.label-info {
    background-color: #5bc0de;
}

.label-warning {
    background-color: #f0ad4e;
}

.label-danger {
    background-color: #d9534f;
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border: 1px solid transparent;
    border-radius: 4px;
}

.alert-success {
    color: #3c763d;
    background-color: #dff0d8;
    border-color: #d6e9c6;
}

.alert-info {
    color: #31708f;
    background-color: #d9edf7;
    border-color: #bce8f1;
}

.alert-warning {
    color: #8a6d3b;
    background-color: #fcf8e3;
    border-color: #faebcc;
}

.alert-danger {
    color: #a94442;
    background-color: #f2dede;
    border-color: #ebccd1;
}

.well {
    min-height: 20px;
    padding: 19px;
    margin-bottom: 20px;
    background-color: #f5f5f5;
    border: 1px solid #e3e3e3;
    border-radius: 4px;
    -webkit-box-shadow: inset 0 1px 1px rgba(0,0,0,.05);
    box-shadow: inset 0 1px 1px rgba(0,0,0,.05);
}

.well-sm {
    padding: 9px;
    border-radius: 3px;
}

.well-lg {
    padding: 24px;
    border-radius: 6px;
}

code {
    padding: 2px 4px;
    font-size: 90%;
    color: #c7254e;
    background-color: #f9f2f4;
    border-radius: 4px;
    font-family: Menlo,Monaco,Consolas,"Courier New",monospace;
}

pre {
    display: block;
    padding: 9.5px;
    margin: 0 0 10px;
    font-size: 13px;
    line-height: 1.42857143;
    color: #333;
    word-break: break-all;
    word-wrap: break-word;
    background-color: #f5f5f5;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-family: Menlo,Monaco,Consolas,"Courier New",monospace;
}

.text-muted {
    color: #777;
}

.text-primary {
    color: #337ab7;
}

.text-success {
    color: #3c763d;
}

.text-info {
    color: #31708f;
}

.text-warning {
    color: #8a6d3b;
}

.text-danger {
    color: #a94442;
}

.nav-tabs {
    border-bottom: 1px solid #ddd;
}

.nav-tabs > li {
    float: left;
    margin-bottom: -1px;
}

.nav-tabs > li > a {
    margin-right: 2px;
    line-height: 1.42857143;
    border: 1px solid transparent;
    border-radius: 4px 4px 0 0;
    padding: 10px 15px;
    color: #337ab7;
    text-decoration: none;
    background: none;
    cursor: pointer;
    display: block;
}

.nav-tabs > li > a:hover {
    border-color: #eee #eee #ddd;
    background-color: #eee;
}

.nav-tabs > li.active > a,
.nav-tabs > li.active > a:hover,
.nav-tabs > li.active > a:focus {
    color: #555;
    cursor: default;
    background-color: #fff;
    border: 1px solid #ddd;
    border-bottom-color: transparent;
}

.tab-content {
    padding: 15px;
    border: 1px solid #ddd;
    border-top: none;
    background-color: #fff;
}

.tab-pane {
    display: none;
}

.tab-pane.active {
    display: block;
}

/* Custom styles for better integration */
.dl-horizontal dt {
    float: left;
    width: 160px;
    overflow: hidden;
    clear: left;
    text-align: right;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-weight: bold;
}

.dl-horizontal dd {
    margin-left: 180px;
}

.form-control {
    display: block;
    width: 100%;
    height: 34px;
    padding: 6px 12px;
    font-size: 14px;
    line-height: 1.42857143;
    color: #555;
    background-color: #fff;
    background-image: none;
    border: 1px solid #ccc;
    border-radius: 4px;
    -webkit-box-shadow: inset 0 1px 1px rgba(0,0,0,.075);
    box-shadow: inset 0 1px 1px rgba(0,0,0,.075);
}

.form-control:focus {
    border-color: #66afe9;
    outline: 0;
    -webkit-box-shadow: inset 0 1px 1px rgba(0,0,0,.075), 0 0 8px rgba(102,175,233,.6);
    box-shadow: inset 0 1px 1px rgba(0,0,0,.075), 0 0 8px rgba(102,175,233,.6);
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

/* Success/Error Messages */
.message {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 15px 20px;
    border-radius: 4px;
    color: white;
    font-weight: bold;
    z-index: 1000;
    opacity: 0;
    transform: translateX(100%);
    transition: all 0.3s ease;
}

.message.show {
    opacity: 1;
    transform: translateX(0);
}

.message.success {
    background-color: #5cb85c;
    border-color: #4cae4c;
}

.message.error {
    background-color: #d9534f;
    border-color: #d43f3a;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .dl-horizontal dt {
        float: none;
        width: auto;
        text-align: left;
    }
    
    .dl-horizontal dd {
        margin-left: 0;
    }
    
    .nav-tabs > li {
        float: none;
    }
    
    .nav-tabs > li > a {
        margin-right: 0;
        margin-bottom: 3px;
        border-radius: 4px;
    }
    
    .nav-tabs > li.active > a {
        border-bottom-color: #ddd;
    }
}
</style>
{/block}