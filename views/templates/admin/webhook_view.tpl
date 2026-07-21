{*
* 2024 Yuju Integration
*}

<div class="panel yuju-webhook-view">
    <div class="panel-heading yuju-webhook-view__heading">
        <div class="yuju-webhook-view__heading-left">
            <i class="icon-link"></i>
            <span>Detalles del Webhook</span>
            <span class="yuju-pill yuju-pill--id">ID {$webhook_log.id|escape:'html':'UTF-8'}</span>
        </div>
        <div class="yuju-webhook-view__heading-right">
            <span class="yuju-pill {if $webhook_log.status == 'processed'}yuju-pill--ok{elseif $webhook_log.status == 'failed'}yuju-pill--error{else}yuju-pill--warn{/if}">
                {if $webhook_log.status == 'processed'}Procesado{elseif $webhook_log.status == 'failed'}Fallido{else}Procesando{/if}
            </span>
        </div>
    </div>

    <div class="panel-body">
        <div class="row">
            <div class="col-md-7">
                <div class="yuju-card">
                    <h4 class="yuju-card__title"><i class="icon-info-circle"></i> Información</h4>
                    <table class="table table-condensed yuju-meta-table">
                        <tr>
                            <th>ID</th>
                            <td>{$webhook_log.id|escape:'html':'UTF-8'}</td>
                        </tr>
                        <tr>
                            <th>Tipo de evento</th>
                            <td><code>{$webhook_log.event_type|escape:'html':'UTF-8'}</code></td>
                        </tr>
                        <tr>
                            <th>ID entidad</th>
                            <td>{$webhook_log.entity_id|default:'-'|escape:'html':'UTF-8'}</td>
                        </tr>
                        <tr>
                            <th>Recibido</th>
                            <td>{$webhook_log.received_at|escape:'html':'UTF-8'}</td>
                        </tr>
                        <tr>
                            <th>Procesado</th>
                            <td>{$webhook_log.processed_at|default:'-'|escape:'html':'UTF-8'}</td>
                        </tr>
                    </table>

                    {if $webhook_log.error_message}
                        <div class="alert alert-danger yuju-alert-compact">
                            <strong><i class="icon-warning-sign"></i> Error</strong><br>
                            {$webhook_log.error_message|escape:'html':'UTF-8'}
                        </div>
                    {/if}
                </div>
            </div>

            <div class="col-md-5">
                <div class="yuju-card">
                    <h4 class="yuju-card__title"><i class="icon-cogs"></i> Acciones</h4>
                    <div class="yuju-actions">
                        {if $webhook_log.status == 'failed'}
                            <button type="button" class="btn btn-primary btn-block" onclick="retryWebhook({$webhook_log.id|escape:'javascript':'UTF-8'})">
                                <i class="icon-refresh"></i> Reintentar webhook
                            </button>
                        {/if}
                        <button type="button" class="btn btn-default btn-block" onclick="exportWebhookLog({$webhook_log.id|escape:'javascript':'UTF-8'})">
                            <i class="icon-download"></i> Exportar registro
                        </button>
                        {if $ps_order_link}
                            <a href="{$ps_order_link|escape:'html':'UTF-8'}" class="btn btn-success btn-block" target="_blank">
                                <i class="icon-shopping-cart"></i> Ver pedido en PrestaShop
                            </a>
                        {/if}
                        <a href="{$link->getAdminLink('AdminYujuWebhook')|escape:'html':'UTF-8'}" class="btn btn-link btn-block yuju-back-link">
                            <i class="icon-arrow-left"></i> Volver al listado
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {if !empty($is_order_webhook) && !empty($order_process)}
        <div class="row">
            <div class="col-md-12">
                <div class="yuju-card yuju-order-process">
                    <h4 class="yuju-card__title"><i class="icon-check-square-o"></i> Proceso de creación de la orden</h4>
                    {if $order_process.message}
                        <p class="text-muted" style="margin-bottom:12px;">{$order_process.message|escape:'html':'UTF-8'}</p>
                    {/if}
                    {if !$order_process.has_details}
                        <div class="alert alert-info" style="margin-bottom:0;">
                            Este webhook de orden no incluye detalle de pasos en la respuesta (posiblemente actualización o evento no procesado como creación).
                        </div>
                    {else}
                        <div class="row yuju-process-steps">
                            {foreach from=$order_process.steps key=step_key item=step}
                                <div class="col-sm-6 col-md-3">
                                    <div class="yuju-process-step {if $step.ok}yuju-process-step--ok{elseif $step.error}yuju-process-step--error{else}yuju-process-step--pending{/if}">
                                        <div class="yuju-process-step__icon">
                                            {if $step.ok}
                                                <i class="icon-ok"></i>
                                            {elseif $step.error}
                                                <i class="icon-remove"></i>
                                            {else}
                                                <i class="icon-minus"></i>
                                            {/if}
                                        </div>
                                        <div class="yuju-process-step__body">
                                            <strong>{$step.label|escape:'html':'UTF-8'}</strong>
                                            <div class="small">
                                                {if $step.ok}
                                                    ID {$step.id|escape:'html':'UTF-8'}
                                                    {if $step.status} · {$step.status|escape:'html':'UTF-8'}{/if}
                                                {elseif $step.error}
                                                    Error
                                                {else}
                                                    No creado
                                                {/if}
                                            </div>
                                            {if $step.extra}
                                                <div class="text-muted small">{$step.extra|escape:'html':'UTF-8'}</div>
                                            {/if}
                                            {if $step.error}
                                                <div class="text-danger small" style="margin-top:4px;">{$step.error|escape:'html':'UTF-8'}</div>
                                            {/if}
                                        </div>
                                    </div>
                                </div>
                            {/foreach}
                        </div>
                        {if $order_process.carrier_id || $order_process.marketplace_slug}
                            <p class="help-block" style="margin-top:12px;margin-bottom:0;">
                                {if $order_process.carrier_id}Carrier Yuju ID: <code>{$order_process.carrier_id|intval}</code>{/if}
                                {if $order_process.marketplace_slug}
                                    {if $order_process.carrier_id} · {/if}
                                    Marketplace: <code>{$order_process.marketplace_slug|escape:'html':'UTF-8'}</code>
                                {/if}
                            </p>
                        {/if}
                    {/if}
                </div>
            </div>
        </div>
        {/if}

        <div class="row">
            <div class="col-md-6">
                <div class="yuju-card">
                    <h4 class="yuju-card__title"><i class="icon-list-alt"></i> Encabezados</h4>
                    {if $webhook_log.headers_decoded}
                        <table class="table table-striped table-bordered yuju-mini-table">
                            <thead>
                                <tr>
                                    <th>Header</th>
                                    <th>Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                {foreach from=$webhook_log.headers_decoded key=header_name item=header_value}
                                    <tr>
                                        <td><code>{$header_name|escape:'html':'UTF-8'}</code></td>
                                        <td>{$header_value|escape:'html':'UTF-8'}</td>
                                    </tr>
                                {/foreach}
                            </tbody>
                        </table>
                    {else}
                        <p class="text-muted">Sin encabezados disponibles.</p>
                    {/if}
                </div>
            </div>
            <div class="col-md-6">
                <div class="yuju-card">
                    <h4 class="yuju-card__title"><i class="icon-code"></i> Respuesta</h4>
                    <div class="yuju-code-wrap">
                        <button class="btn btn-default btn-xs yuju-copy-btn" onclick="copyToClipboard('response-code')">
                            <i class="icon-copy"></i> Copiar
                        </button>
                        <pre id="response-code" class="yuju-code-block yuju-code-block--json">{$webhook_log.response_pretty|default:'-'|escape:'html':'UTF-8'}</pre>
                    </div>
                </div>
            </div>
        </div>

        <div class="yuju-card">
            <h4 class="yuju-card__title"><i class="icon-file-text-o"></i> Payload JSON</h4>
            <div class="yuju-code-wrap">
                <button class="btn btn-default btn-xs yuju-copy-btn" onclick="copyToClipboard('payload-code')">
                    <i class="icon-copy"></i> Copiar
                </button>
                <pre id="payload-code" class="yuju-code-block yuju-code-block--json">{$webhook_log.payload_pretty|default:'-'|escape:'html':'UTF-8'}</pre>
            </div>
        </div>
    </div>
</div>

<script>
function retryWebhook(webhookId) {
    if (!confirm('¿Desea reintentar este webhook?')) {
        return;
    }
    $.ajax({
        url: '{$link->getAdminLink('AdminYujuWebhook')|escape:'javascript':'UTF-8'}',
        type: 'POST',
        dataType: 'json',
        data: {
            ajax: true,
            action: 'retryWebhook',
            webhook_id: webhookId
        },
        success: function(response) {
            if (response && response.success) {
                alert('Webhook reintentado exitosamente');
                location.reload();
            } else {
                alert((response && response.error) ? response.error : 'Error al reintentar webhook');
            }
        },
        error: function() {
            alert('Error de conexión al reintentar webhook');
        }
    });
}

function exportWebhookLog(webhookId) {
    window.open('{$link->getAdminLink('AdminYujuWebhook')|escape:'javascript':'UTF-8'}&action=exportWebhookLog&webhook_id=' + webhookId, '_blank');
}

function copyToClipboard(elementId) {
    var el = document.getElementById(elementId);
    if (!el) {
        return;
    }
    var text = el.textContent || el.innerText || '';
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function() {
            alert('Copiado');
        }).catch(function() {
            fallbackCopy(text);
        });
        return;
    }
    fallbackCopy(text);
}

function fallbackCopy(text) {
    var textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    textarea.select();
    try {
        document.execCommand('copy');
        alert('Copiado');
    } catch (e) {
        alert('No se pudo copiar');
    }
    document.body.removeChild(textarea);
}

(function() {
    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function highlightJson(jsonText) {
        // Tokeniza strings (con escapes), números, booleanos, null y delimitadores.
        // Distingue claves (string seguido de ':') de strings normales.
        var pattern = /("(?:\\u[a-fA-F0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(?:true|false|null)\b|-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?)/g;
        return escapeHtml(jsonText).replace(pattern, function(match) {
            var cls = 'yuju-json-number';
            if (/^"/.test(match)) {
                if (/:$/.test(match)) {
                    cls = 'yuju-json-key';
                } else {
                    cls = 'yuju-json-string';
                }
            } else if (/true|false/.test(match)) {
                cls = 'yuju-json-boolean';
            } else if (/null/.test(match)) {
                cls = 'yuju-json-null';
            }
            return '<span class="' + cls + '">' + match + '</span>';
        });
    }

    function tryFormatBlock(blockId) {
        var el = document.getElementById(blockId);
        if (!el) {
            return;
        }
        var raw = (el.textContent || el.innerText || '').trim();
        if (!raw || raw === '-') {
            return;
        }
        try {
            JSON.parse(raw);
        } catch (e) {
            // Si no es JSON válido, dejamos el texto plano original.
            return;
        }
        el.innerHTML = highlightJson(raw);
    }

    document.addEventListener('DOMContentLoaded', function() {
        tryFormatBlock('response-code');
        tryFormatBlock('payload-code');
    });
})();
</script>

<style>
.yuju-webhook-view__heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.yuju-webhook-view__heading-left {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
}

.yuju-pill {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
}

.yuju-pill--id {
    background: #f0f2f5;
    color: #4a4a4a;
}

.yuju-pill--ok {
    background: #dff5e6;
    color: #1f7a3e;
}

.yuju-pill--error {
    background: #fde7e9;
    color: #a63640;
}

.yuju-pill--warn {
    background: #fff3d6;
    color: #8c6d1f;
}

.yuju-card {
    border: 1px solid #e5e5e5;
    border-radius: 8px;
    padding: 14px;
    margin-bottom: 15px;
    background: #fff;
}

.yuju-card__title {
    margin: 0 0 12px;
    font-size: 16px;
    font-weight: 600;
}

.yuju-meta-table th {
    width: 140px;
    color: #666;
}

.yuju-meta-table td {
    word-break: break-word;
}

.yuju-alert-compact {
    margin-top: 10px;
    margin-bottom: 0;
    padding: 10px 12px;
}

.yuju-actions .btn {
    margin-bottom: 8px;
}

.yuju-back-link {
    text-align: left;
    padding-left: 0;
}

.yuju-mini-table {
    margin-bottom: 0;
    font-size: 12px;
}

.yuju-code-wrap {
    position: relative;
}

.yuju-copy-btn {
    position: absolute;
    top: 8px;
    right: 8px;
    z-index: 2;
}

.yuju-code-block {
    max-height: 320px;
    overflow: auto;
    background: #0f172a;
    color: #e2e8f0;
    border-radius: 6px;
    border: 1px solid #1e293b;
    padding: 12px;
    margin: 0;
    font-size: 12px;
    line-height: 1.55;
    white-space: pre-wrap;
    word-break: break-word;
    font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
    tab-size: 2;
}

.yuju-code-block--json .yuju-json-key {
    color: #ff3b8a;
    font-weight: 600;
}

.yuju-code-block--json .yuju-json-string {
    color: #94a3b8;
}

.yuju-code-block--json .yuju-json-number {
    color: #ff8a3d;
    font-weight: 600;
}

.yuju-code-block--json .yuju-json-boolean {
    color: #ae81ff;
    font-weight: 700;
}

.yuju-code-block--json .yuju-json-null {
    color: #ff4d6d;
    font-weight: 600;
    font-style: italic;
}

.yuju-process-step {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    border: 1px solid #e5e5e5;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 12px;
    min-height: 88px;
    background: #fafafa;
}

.yuju-process-step__icon {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 14px;
}

.yuju-process-step--ok {
    border-color: #b7e0c2;
    background: #f3fbf5;
}

.yuju-process-step--ok .yuju-process-step__icon {
    background: #dff5e6;
    color: #1f7a3e;
}

.yuju-process-step--error {
    border-color: #f0c2c7;
    background: #fff7f8;
}

.yuju-process-step--error .yuju-process-step__icon {
    background: #fde7e9;
    color: #a63640;
}

.yuju-process-step--pending .yuju-process-step__icon {
    background: #f0f2f5;
    color: #777;
}
</style>