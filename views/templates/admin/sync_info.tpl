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
        Estado de sincronización
        <button type="button" class="btn btn-primary btn-sm pull-right" id="yuju-start-report" data-ajax-url="{$link->getAdminLink('AdminYujuSync')|escape:'html':'UTF-8'}">
            <i class="icon-refresh"></i> Iniciar sincronización
        </button>
    </div>
    <div class="panel-body">
        <div id="yuju-product-sync-report" class="hidden" role="status" aria-live="polite" style="margin-bottom: 20px;">
            <div id="yuju-product-sync-message" class="alert alert-info"></div>
            <ol id="yuju-sync-steps" class="yuju-sync-steps hidden" aria-label="Progreso de la sincronización">
                <li data-step="1" class="is-pending"><span class="yuju-step-icon"><i class="icon-circle-o"></i></span><strong>Paso 1 de 4 · Solicitar el informe general a Yuju</strong><span class="yuju-step-detail"></span></li>
                <li data-step="2" class="is-pending"><span class="yuju-step-icon"><i class="icon-circle-o"></i></span><strong>Paso 2 de 4 · Esperar y descargar el informe general</strong><span class="yuju-step-detail"></span></li>
                <li data-step="3" class="is-pending"><span class="yuju-step-icon"><i class="icon-circle-o"></i></span><strong>Paso 3 de 4 · Obtener stock y precio (se reutiliza hasta 12 h)</strong><span class="yuju-step-detail"></span></li>
                <li data-step="4" class="is-pending"><span class="yuju-step-icon"><i class="icon-circle-o"></i></span><strong>Paso 4 de 4 · Combinar informes y detectar diferencias</strong><span class="yuju-step-detail"></span></li>
            </ol>
            <div class="row">
                <div class="col-xs-6 col-sm-2"><strong>Total:</strong> <span id="yuju-products-total">0</span></div>
                <div class="col-xs-6 col-sm-2"><strong>Sincronizados:</strong> <span id="yuju-products-synced">0</span></div>
                <div class="col-xs-6 col-sm-2"><strong>No están en Yuju:</strong> <span id="yuju-products-unsynced">0</span></div>
                <div class="col-xs-6 col-sm-2"><strong>Con diferencias:</strong> <span id="yuju-products-differences">0</span></div>
                <div class="col-xs-6 col-sm-2"><strong>Diferencias urgentes:</strong> <span id="yuju-products-urgent-differences">0</span></div>
                <div class="col-xs-6 col-sm-2"><strong>Diferencias no urgentes:</strong> <span id="yuju-products-non-urgent-differences">0</span></div>
            </div>
            <div id="yuju-unsynced-products" class="yuju-reconciliation" style="margin-top: 15px;"></div>
            <div id="yuju-report-actions" style="margin-top: 15px;">
                <button type="button" class="btn btn-info" id="yuju-poll-report" style="display: none;">
                    <i class="icon-clock-o"></i> Ver progreso del informe
                </button>
                <button type="button" class="btn btn-info" id="yuju-load-reconciliation" style="display: none;">
                    <i class="icon-eye"></i> Ver reconciliación
                </button>
                <button type="button" class="btn btn-success" id="yuju-push-differences" style="display: none;">
                    <i class="icon-refresh"></i> Sincronizar (PrestaShop → Yuju)
                </button>
            </div>
            <p class="text-muted" style="margin-top: 10px;">
                PrestaShop es la fuente de verdad. Solo los productos already vinculados con diferencias se envían a PS→Yuju. Los productos sin emparejar se listan, nunca se crean en Yuju.
            </p>
        </div>

        <div class="modal fade" id="yuju-payload-debug-modal" tabindex="-1" role="dialog" aria-labelledby="yuju-payload-debug-title">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title" id="yuju-payload-debug-title">JSON de depuración del producto</h4>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted">Vista de solo lectura. <code>mapped_payload</code> es el resultado del mapeo activo; <code>yuju_report_product</code> es el registro original del reporte cacheado, si se encontró.</p>
                        <pre id="yuju-payload-debug-json" style="max-height:60vh; overflow:auto; white-space:pre-wrap; overflow-wrap:anywhere;">Selecciona el icono JSON de un producto.</pre>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row" style="margin-top: 20px;">
            <div class="col-md-3">
                <div class="sync-status-card">
                    <h4>
                        {if $is_sync_running}
                            <span class="label label-warning">
                                <i class="icon-spinner icon-spin"></i> Ejecutándose
                            </span>
                        {else}
                            <span class="label label-success">
                                <i class="icon-check"></i> Inactivo
                            </span>
                        {/if}
                    </h4>
                    <p class="text-muted">Estado actual</p>
                </div>
            </div>

            <div class="col-md-3">
                <div class="sync-status-card">
                    <h4>{if $last_sync_date}{$last_sync_date|date_format:"%Y-%m-%d %H:%M"|escape:'html':'UTF-8'}{else}Never{/if}</h4>
                    <p class="text-muted">Última sincronización</p>
                </div>
            </div>

            <div class="col-md-3">
                <div class="sync-status-card">
                    <h4>
                        {if $auto_sync_enabled}
                            <span class="label label-success">Habilitado</span>
                        {else}
                            <span class="label label-default">Deshabilitado</span>
                        {/if}
                    </h4>
                    <p class="text-muted">Sincronización automática</p>
                </div>
            </div>

            <div class="col-md-3">
                <div class="sync-status-card">
                    <h4>{if isset($sync_stats.total_synced)}{$sync_stats.total_synced|intval}{else}0{/if}</h4>
                    <p class="text-muted">Total sincronizado</p>
                </div>
            </div>
        </div>

        {if isset($sync_stats)}
            <div class="row" style="margin-top: 20px;">
                <div class="col-md-12">
                    <h5>Estadísticas de sincronización</h5>
                    <div class="row">
                        <div class="col-md-2">
                            <div class="text-center">
                                <strong>{if isset($sync_stats.products_synced)}{$sync_stats.products_synced|intval}{else}0{/if}</strong>
                                <br><small>Productos</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="text-center">
                                <strong>{if isset($sync_stats.categories_synced)}{$sync_stats.categories_synced|intval}{else}0{/if}</strong>
                                <br><small>Categorías</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="text-center">
                                <strong>{if isset($sync_stats.stock_synced)}{$sync_stats.stock_synced|intval}{else}0{/if}</strong>
                                <br><small>Actualizaciones de stock</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="text-center">
                                <strong>{if isset($sync_stats.price_synced)}{$sync_stats.price_synced|intval}{else}0{/if}</strong>
                                <br><small>Actualizaciones de precio</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="text-center">
                                <strong>{if isset($sync_stats.errors)}{$sync_stats.errors|intval}{else}0{/if}</strong>
                                <br><small class="text-danger">Errores</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="text-center">
                                <strong>{if isset($sync_stats.success_rate)}{$sync_stats.success_rate|string_format:"%.1f"|escape:'html':'UTF-8'}%{/if}</strong>
                                <br><small>Tasa de éxito</small>
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
                        Un proceso de sincronización está en ejecución. Espere a que complete antes de iniciar uno nuevo.
                    </div>
                </div>
            </div>
        {/if}
    </div>
</div>

<style type="text/css">
    .yuju-reconciliation { width: 100%; min-width: 0; }
    .yuju-reconciliation .form-control { max-width: 100%; }
    .yuju-sync-steps { list-style: none; margin: 0 0 15px; padding: 10px 15px; border: 1px solid #d8dee5; border-radius: 4px; background: #fafbfc; }
    .yuju-sync-steps li { padding: 5px 0; line-height: 1.5; color: #7a8590; }
    .yuju-sync-steps li.is-running { color: #1b6ec2; }
    .yuju-sync-steps li.is-done { color: #3c8f3c; }
    .yuju-sync-steps li.is-error { color: #c0392b; }
    .yuju-step-icon { display: inline-block; width: 22px; }
    .yuju-step-detail { display: block; margin-left: 22px; font-size: 12px; font-weight: normal; }
    .yuju-reconciliation-table-wrap { width: 100%; margin-top: 12px; border: 1px solid #d8dee5; border-radius: 4px; overflow-x: auto; }
    .yuju-reconciliation-table thead th { text-transform: none; }
    .yuju-reconciliation-table { width: 100%; min-width: 1120px; margin-bottom: 0; table-layout: fixed; font-size: 12px; }
    .yuju-reconciliation-table thead th { background: #f3f6f9; color: #344054; font-weight: 600; vertical-align: middle; white-space: normal; }
    .yuju-reconciliation-table th, .yuju-reconciliation-table td { padding: 8px 9px !important; line-height: 1.45; vertical-align: top !important; overflow-wrap: anywhere; word-break: normal; }
    .yuju-reconciliation-table th:nth-child(1), .yuju-reconciliation-table td:nth-child(1) { width: 38px; text-align: center; }
    .yuju-reconciliation-table th:nth-child(2), .yuju-reconciliation-table td:nth-child(2) { width: 70px; }
    .yuju-reconciliation-table th:nth-child(3), .yuju-reconciliation-table td:nth-child(3) { width: 90px; }
    .yuju-reconciliation-table th:nth-child(4), .yuju-reconciliation-table td:nth-child(4) { width: 22%; }
    .yuju-reconciliation-table th:nth-child(5), .yuju-reconciliation-table td:nth-child(5) { width: 14%; }
    .yuju-reconciliation-table th:nth-child(6), .yuju-reconciliation-table td:nth-child(6) { width: 110px; }
    .yuju-reconciliation-table th:nth-child(7), .yuju-reconciliation-table td:nth-child(7) { width: 18%; }
    .yuju-reconciliation-table th:nth-child(8), .yuju-reconciliation-table td:nth-child(8) { width: 76px; text-align: center; }
    .yuju-reconciliation-table th:nth-child(9), .yuju-reconciliation-table td:nth-child(9) { width: 22%; }
    .yuju-reconciliation-table tbody tr:hover { background: #f7fafc; }
    .yuju-reconciliation-table td .btn { white-space: nowrap; }
    @media (max-width: 767px) {
        .yuju-reconciliation-table { min-width: 1050px; }
        .yuju-reconciliation-table th, .yuju-reconciliation-table td { padding: 6px !important; }
    }
</style>

<script type="text/javascript">
$(function() {
    var $startBtn = $('#yuju-start-report');
    var workflowRunning = false;
    var $pollBtn = $('#yuju-poll-report');
    var $loadBtn = $('#yuju-load-reconciliation');
    var $pushBtn = $('#yuju-push-differences');
    var $report = $('#yuju-product-sync-report');
    var ajaxUrl = $startBtn.data('ajax-url');
    var pollTimer = null;

    function showMessage(message, type) {
        var $message = $('#yuju-product-sync-message')
            .removeClass('alert-info alert-danger alert-success alert-warning')
            .addClass(type || 'alert-info');
        $message.text(message);
    }

    var currentStep = 1;
    var waitStartedAt = 0;
    var waitTicker = null;

    function setStep(step, state, detail) {
        var icon = 'icon-circle-o';
        if (state === 'running') icon = 'icon-spinner icon-spin';
        else if (state === 'done') icon = 'icon-check';
        else if (state === 'error') icon = 'icon-times';
        var $li = $('#yuju-sync-steps li[data-step="' + step + '"]');
        $li.removeClass('is-pending is-running is-done is-error').addClass('is-' + state);
        $li.find('.yuju-step-icon i').attr('class', icon);
        if (detail !== undefined) {
            $li.find('.yuju-step-detail').text(detail);
        }
    }

    function resetSteps() {
        for (var i = 1; i <= 4; i++) {
            setStep(i, 'pending', '');
        }
        $('#yuju-sync-steps').removeClass('hidden');
    }

    function setStartLabel(text) {
        $startBtn.prop('disabled', true).html('<i class="icon-spinner icon-spin"></i> ' + text);
    }

    function pad2(n) {
        return (n < 10 ? '0' : '') + n;
    }

    function startWaitTicker() {
        if (waitTicker) return;
        waitStartedAt = Date.now();
        waitTicker = setInterval(function() {
            var seconds = Math.floor((Date.now() - waitStartedAt) / 1000);
            setStep(2, 'running', 'Yuju está generando el informe (transcurrido ' + pad2(Math.floor(seconds / 60)) + ':' + pad2(seconds % 60) + '). Se consulta cada 15 s; puede tardar varios minutos.');
        }, 1000);
    }

    function stopWaitTicker() {
        if (waitTicker) {
            clearInterval(waitTicker);
            waitTicker = null;
        }
    }

    // Ends the one-button workflow. When failedStep is given, that step is marked as failed.
    function endWorkflow(failedStep, message) {
        var wasRunning = workflowRunning;
        workflowRunning = false;
        stopAutoPoll();
        stopWaitTicker();
        if (wasRunning && failedStep) {
            setStep(failedStep, 'error', message || 'No se pudo completar este paso.');
        }
        $startBtn.prop('disabled', false).html($startBtn.data('original-html') || '<i class="icon-refresh"></i> Iniciar sincronización');
    }

    function setLoading($btn, loading, loadingText) {
        if (loading) {
            $btn.data('original-html', $btn.html());
            $btn.prop('disabled', true).html('<i class="icon-spinner icon-spin"></i> ' + loadingText);
        } else {
            $btn.prop('disabled', false).html($btn.data('original-html'));
        }
    }

    function ajaxAction(action, params, onSuccess, onError) {
        var data = {ldelim}ajax: 1, action: action{rdelim};
        if (params) {
            $.each(params, function(k, v) {
                data[k] = v;
            });
        }
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: data
        }).done(function(response) {
            if (onSuccess) {
                onSuccess(response);
            }
        }).fail(function(xhr, status, error) {
            if (onError) {
                onError(xhr, status, error);
            } else {
                showMessage('Fallo al conectar con Yuju. Revise la conexión e intente nuevamente.', 'alert-danger');
            }
        });
    }

    function handleReportState(response, startPolling) {
        $report.removeClass('hidden');
        if (!response) {
            endWorkflow(currentStep, 'Sin respuesta de Yuju.');
            showMessage('Fallo al conectar con Yuju. Revise la conexión e intente nuevamente.', 'alert-danger');
            return;
        }

        if (response.success) {
            var status = response.status || 'UNKNOWN';
            if (status === 'CREATED' || status === 'PROCESSING') {
                showMessage('Informe en progreso. El informe de Yuju está siendo generado; puede tardar unos minutos.', 'alert-info');
                $pollBtn.show();
                $loadBtn.hide();
                $pushBtn.hide();
                if (workflowRunning) {
                    if (currentStep === 1) {
                        setStep(1, 'done', response.reused ? 'Se reutilizó la tarea de informe existente (no se consumió otra solicitud).' : 'Solicitud enviada a Yuju.');
                    }
                    currentStep = 2;
                    setStep(2, 'running', 'Yuju está generando el informe...');
                    setStartLabel('Paso 2 de 4: esperando el informe general...');
                    startWaitTicker();
                }
                if (startPolling) {
                    startAutoPoll();
                }
            } else if (status === 'COMPLETED' && response.report_cached === true) {
                stopAutoPoll();
                stopWaitTicker();
                $pollBtn.hide();
                $loadBtn.hide();
                $pushBtn.hide();
                if (response.product_count !== undefined) $('#yuju-products-total').text(response.product_count);
                if (workflowRunning) {
                    if (currentStep === 1) {
                        setStep(1, 'done', 'Ya había un informe general disponible; no se solicitó otro.');
                    }
                    setStep(2, 'done', (response.product_count !== undefined ? response.product_count + ' productos' : 'Informe') + ' descargados y validados.');
                    finishCombinedWorkflow();
                } else {
                    showMessage('Informe general descargado. Inicie el proceso completo para descargar stock/precio y reconciliar.', 'alert-success');
                    $loadBtn.show();
                }
            } else if (status === 'COMPLETED') {
                // El estado remoto no garantiza que el archivo local se pueda leer.
                var notCachedMessage = response.message || 'Yuju terminó el informe, pero aún no está disponible en la caché local. Compruebe la descarga antes de reconciliar.';
                endWorkflow(currentStep, notCachedMessage);
                showMessage(notCachedMessage, 'alert-warning');
                $pollBtn.show();
                $loadBtn.hide();
                $pushBtn.hide();
            } else if (status === 'DOWNLOAD_ERROR') {
                var downloadErrorMessage = 'Informe listo pero falló la validación de la descarga. Reintente la descarga.';
                endWorkflow(currentStep, downloadErrorMessage);
                showMessage(downloadErrorMessage, 'alert-warning');
                $pollBtn.show();
                $loadBtn.hide();
                $pushBtn.hide();
            } else if (status === 'RATE_LIMITED') {
                endWorkflow(currentStep, 'Límite diario de informes alcanzado (2/día UTC).');
                showMessage('Límite diario de informes alcanzado (2/día UTC).', 'alert-warning');
                $pollBtn.hide();
                $loadBtn.show();
                $pushBtn.hide();
            } else if (status === 'UNKNOWN') {
                endWorkflow(currentStep, 'La solicitud no pudo confirmarse; no se repetirá para no gastar cuota.');
                showMessage('La solicitud de informe no pudo confirmarse. Evite solicitudes duplicadas.', 'alert-warning');
            } else {
                endWorkflow(currentStep, 'Estado del informe desconocido.');
                showMessage('Estado del informe desconocido.', 'alert-warning');
            }
        } else {
            var failMessage = response.message || 'No se pudo iniciar el informe general de Yuju.';
            endWorkflow(currentStep, failMessage);
            showMessage(failMessage, response.status === 'RATE_LIMITED' ? 'alert-warning' : 'alert-danger');
        }
    }

    function startAutoPoll() {
        stopAutoPoll();
        pollTimer = setInterval(function() {
            ajaxAction('pollYujuProductReport', null, function(response) {
                handleReportState(response, false);
            });
        }, 15000);
    }

    function stopAutoPoll() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    var reconciliationProducts = [];
    var reconciliationActionableProducts = [];
    var reconciliationPage = 1;
    var reconciliationPageSize = 100;
    var reconciliationFilter = 'differences';
    var reconciliationUrgencyFilter = 'all';
    var reconciliationSelectedProducts = {};

    function getProductStatus(product) {
        return product.reconciliation_status || product.status || 'needs_review';
    }

    function isUrgentDifference(field) {
        var parts = String(field || '').toLowerCase().split('.');
        var normalized = parts[parts.length - 1].replace(/[\s-]/g, '_');
        return $.inArray(normalized, ['stock', 'price']) !== -1;
    }

    function getUrgentDifferences(product) {
        return $.grep(product.differences || [], function(difference) { return isUrgentDifference(difference.field); });
    }

    function getNonUrgentDifferences(product) {
        return $.grep(product.differences || [], function(difference) { return !isUrgentDifference(difference.field); });
    }

    function canSelectUrgentUpdate(product) {
        return getProductStatus(product) === 'differences' && !!product.yuju_product_id &&
            !!product.prestashop_product_id && getUrgentDifferences(product).length > 0;
    }

    function getSelectedProductIds() {
        return Object.keys(reconciliationSelectedProducts).filter(function(id) { return reconciliationSelectedProducts[id] === true; });
    }

    function updatePushButton() {
        var selectedCount = getSelectedProductIds().length;
        if (selectedCount > 0) {
            var label = '<i class="icon-refresh"></i> Actualizar urgentes seleccionados (' + selectedCount + ')';
            $pushBtn.html(label).show();
            $('#yuju-push-differences-top').html(label).show();
        } else {
            $pushBtn.hide();
            $('#yuju-push-differences-top').hide();
        }
    }

    function renderReconciliationTable() {
        var statusLabels = {
            synced: 'Correctamente sincronizado',
            differences: 'Con diferencias',
            not_in_yuju: 'No está en Yuju',
            ambiguous: 'SKU ambiguo',
            needs_review: 'Revisión manual',
            disabled: 'Sincronización deshabilitada'
        };
        var filteredProducts = $.grep(reconciliationProducts, function(product) {
            var statusMatches = reconciliationFilter === 'all' || getProductStatus(product) === reconciliationFilter;
            var urgentCount = getUrgentDifferences(product).length;
            var nonUrgentCount = getNonUrgentDifferences(product).length;
            var urgencyMatches = reconciliationUrgencyFilter === 'all' ||
                (reconciliationUrgencyFilter === 'urgent' && urgentCount > 0) ||
                (reconciliationUrgencyFilter === 'non_urgent' && nonUrgentCount > 0);
            return statusMatches && urgencyMatches;
        });
        var totalPages = Math.max(1, Math.ceil(filteredProducts.length / reconciliationPageSize));
        reconciliationPage = Math.min(Math.max(1, reconciliationPage), totalPages);
        var startIndex = (reconciliationPage - 1) * reconciliationPageSize;
        var pageProducts = filteredProducts.slice(startIndex, startIndex + reconciliationPageSize);
        var $container = $('#yuju-reconciliation-results');
        $container.empty();

        var $table = $('<table class="table table-bordered table-striped yuju-reconciliation-table"><thead><tr>' +
            '<th><input type="checkbox" id="yuju-select-all-urgent" aria-label="Seleccionar todos los productos con diferencias urgentes"></th>' +
            '<th>ID PS</th><th>ID Yuju</th><th>Producto</th><th>SKU</th><th>Estado</th><th>Prioridad</th><th>Depurar</th><th>Diferencias</th>' +
            '</tr></thead><tbody></tbody></table>');

        $.each(pageProducts, function(index, product) {
            var diffFields = [];
            $.each(product.differences || [], function(i, difference) {
                diffFields.push(difference.field || '');
            });
            var msg = product.message || '';
            if (product.uncomparable && product.uncomparable.length) {
                msg += (msg ? ' ' : '') + 'No comparable: ' + product.uncomparable.join(', ');
            }

            var status = getProductStatus(product);
            var urgentDifferences = getUrgentDifferences(product);
            var nonUrgentDifferences = getNonUrgentDifferences(product);
            var productId = String(product.prestashop_product_id || '');
            var selectable = canSelectUrgentUpdate(product);
            var $row = $('<tr></tr>');
            var $selectCell = $('<td></td>');
            var $select = $('<input type="checkbox" class="yuju-select-urgent-product" aria-label="Seleccionar actualización urgente">')
                .prop('disabled', !selectable)
                .prop('checked', selectable && reconciliationSelectedProducts[productId] === true)
                .on('change', function() {
                    if ($(this).is(':checked')) reconciliationSelectedProducts[productId] = true;
                    else delete reconciliationSelectedProducts[productId];
                    renderReconciliationTable();
                });
            $selectCell.append($select);
            $row.append($selectCell);
            // Treat all report values as text; never inject Yuju-provided HTML.
            $row.append($('<td></td>').text(product.prestashop_product_id || '—'));
            $row.append($('<td></td>').text(product.yuju_product_id || '—'));
            $row.append($('<td></td>').text(product.name || '—'));
            $row.append($('<td></td>').text(product.sku || '—'));
            $row.append($('<td></td>').text(statusLabels[status] || status));
            var priorityText = [];
            if (urgentDifferences.length) priorityText.push('Urgentes: ' + urgentDifferences.map(function(d) { return d.field; }).join(', '));
            if (nonUrgentDifferences.length) priorityText.push('No urgentes: ' + nonUrgentDifferences.map(function(d) { return d.field; }).join(', '));
            $row.append($('<td></td>').text(priorityText.length ? priorityText.join(' | ') : '—'));
            var $debugCell = $('<td></td>');
            var $debugButton = $('<button type="button" class="btn btn-default btn-xs" title="Ver JSON mapeado y datos del reporte Yuju"><i class="icon-code"></i> JSON</button>');
            $debugButton.on('click', function() {
                var $button = $(this);
                $button.prop('disabled', true);
                $('#yuju-payload-debug-title').text('JSON de depuración — producto PrestaShop ' + productId);
                $('#yuju-payload-debug-json').text('Cargando payload y reporte cacheado...');
                $('#yuju-payload-debug-modal').modal('show');
                ajaxAction('GetYujuProductPayload', {ldelim}product_id: productId{rdelim}, function(response) {
                    if (response && response.success && response.debug) {
                        $('#yuju-payload-debug-json').text(JSON.stringify(response.debug, null, 2));
                    } else {
                        $('#yuju-payload-debug-json').text((response && response.message) || 'No se pudo obtener el JSON de depuración.');
                    }
                }, function(error) {
                    $('#yuju-payload-debug-json').text(error || 'No se pudo obtener el JSON de depuración.');
                });
                // Re-enable when the modal closes; ajaxAction reports failures via its error callback.
                $('#yuju-payload-debug-modal').one('hidden.bs.modal', function() {
                    $button.prop('disabled', false);
                });
            });
            $debugCell.append($debugButton);
            $row.append($debugCell);
            $row.append($('<td></td>').text(diffFields.length ? diffFields.join(', ') + (msg ? ' — ' + msg : '') : (msg || '—')));
            $table.find('tbody').append($row);
        });

        if (pageProducts.length) {
            $container.append($table);
            var selectableInFilter = $.grep(filteredProducts, canSelectUrgentUpdate);
            var allSelectableChecked = selectableInFilter.length > 0 && $.grep(selectableInFilter, function(product) {
                return reconciliationSelectedProducts[String(product.prestashop_product_id)] === true;
            }).length === selectableInFilter.length;
            $('#yuju-select-all-urgent').prop('checked', allSelectableChecked).prop('disabled', selectableInFilter.length === 0).on('change', function() {
                var checked = $(this).is(':checked');
                $.each(selectableInFilter, function(i, product) {
                    var id = String(product.prestashop_product_id);
                    if (checked) reconciliationSelectedProducts[id] = true;
                    else delete reconciliationSelectedProducts[id];
                });
                renderReconciliationTable();
            });
        } else {
            $container.append($('<p class="alert alert-info"></p>').text('No hay productos para el filtro seleccionado.'));
        }

        var firstItem = filteredProducts.length ? startIndex + 1 : 0;
        var lastItem = Math.min(startIndex + reconciliationPageSize, filteredProducts.length);
        var $pagination = $('<div class="clearfix" style="margin-top:10px;"></div>');
        $pagination.append($('<span class="text-muted"></span>').text(
            'Mostrando ' + firstItem + '–' + lastItem + ' de ' + filteredProducts.length + ' productos'
        ));
        var $nav = $('<div class="pull-right"></div>');
        var $previous = $('<button type="button" class="btn btn-default btn-sm">Anterior</button>')
            .prop('disabled', reconciliationPage <= 1)
            .on('click', function() {
                reconciliationPage--;
                renderReconciliationTable();
            });
        var $next = $('<button type="button" class="btn btn-default btn-sm" style="margin-left:5px;">Siguiente</button>')
            .prop('disabled', reconciliationPage >= totalPages)
            .on('click', function() {
                reconciliationPage++;
                renderReconciliationTable();
            });
        $nav.append($previous).append($('<span style="margin:0 8px;"></span>').text('Página ' + reconciliationPage + ' de ' + totalPages)).append($next);
        $pagination.append($nav);
        $container.append($pagination);

        updatePushButton();
    }

    function renderReconciliation(report, message) {
        reconciliationProducts = report.all_products || report.unsynced_products || [];
        reconciliationActionableProducts = report.unsynced_products || [];
        reconciliationPage = 1;
        reconciliationPageSize = 100;
        reconciliationFilter = 'differences';
        reconciliationUrgencyFilter = 'all';
        reconciliationSelectedProducts = {};

        var notInYujuCount = 0;
        var syncedCount = 0;
        var differencesCount = 0;
        var urgentDifferencesCount = 0;
        var nonUrgentDifferencesCount = 0;
        $.each(reconciliationProducts, function(index, product) {
            var status = getProductStatus(product);
            if (status === 'not_in_yuju') {
                notInYujuCount++;
            } else if (status === 'synced') {
                syncedCount++;
            } else if (status === 'differences') {
                differencesCount++;
                if (getUrgentDifferences(product).length > 0) {
                    urgentDifferencesCount++;
                }
                if (getNonUrgentDifferences(product).length > 0) {
                    nonUrgentDifferencesCount++;
                }
            }
        });

        $('#yuju-products-total').text(report.total !== undefined ? report.total : reconciliationProducts.length);
        $('#yuju-products-synced').text(syncedCount);
        $('#yuju-products-unsynced').text(notInYujuCount);
        $('#yuju-products-differences').text(differencesCount);
        $('#yuju-products-urgent-differences').text(urgentDifferencesCount);
        $('#yuju-products-non-urgent-differences').text(nonUrgentDifferencesCount);

        var $container = $('#yuju-unsynced-products');
        $container.empty();
        var $filters = $('<div class="row" style="margin:15px 0;"></div>');
        var $statusColumn = $('<div class="col-sm-6"></div>');
        $statusColumn.append('<label for="yuju-reconciliation-filter">Mostrar productos</label>');
        var $statusSelect = $('<select id="yuju-reconciliation-filter" class="form-control">' +
            '<option value="differences">Solo con diferencias (predeterminado)</option>' +
            '<option value="not_in_yuju">En PrestaShop, no en Yuju</option>' +
            '<option value="synced">Correctamente sincronizados</option>' +
            '<option value="ambiguous">SKU ambiguo</option>' +
            '<option value="needs_review">Revisión manual</option>' +
            '<option value="disabled">Sincronización deshabilitada</option>' +
            '<option value="all">Todos los productos</option>' +
            '</select>').val(reconciliationFilter);
        $statusSelect.on('change', function() {
            reconciliationFilter = $(this).val();
            reconciliationPage = 1;
            renderReconciliationTable();
        });
        $statusColumn.append($statusSelect);
        var $pageSizeColumn = $('<div class="col-sm-3"></div>');
        $pageSizeColumn.append('<label for="yuju-reconciliation-page-size">Productos por página</label>');
        var $pageSizeSelect = $('<select id="yuju-reconciliation-page-size" class="form-control">' +
            '<option value="20">20</option><option value="50">50</option>' +
            '<option value="100" selected="selected">100</option><option value="500">500</option>' +
            '</select>');
        $pageSizeSelect.on('change', function() {
            reconciliationPageSize = parseInt($(this).val(), 10) || 100;
            reconciliationPage = 1;
            renderReconciliationTable();
        });
        $pageSizeColumn.append($pageSizeSelect);
        var $resetColumn = $('<div class="col-sm-3" style="padding-top:25px;"></div>');
        $('<button type="button" class="btn btn-default">Restablecer filtros</button>').on('click', function() {
            reconciliationFilter = 'differences';
            reconciliationPageSize = 100;
            reconciliationPage = 1;
            reconciliationUrgencyFilter = 'all';
            reconciliationSelectedProducts = {};
            $statusSelect.val(reconciliationFilter);
            $pageSizeSelect.val(String(reconciliationPageSize));
            $urgencySelect.val(reconciliationUrgencyFilter);
            renderReconciliationTable();
        }).appendTo($resetColumn);
        var $urgencyColumn = $('<div class="col-sm-6" style="margin-top:10px;"></div>');
        $urgencyColumn.append('<label for="yuju-reconciliation-urgency">Tipo de diferencia</label>');
        var $urgencySelect = $('<select id="yuju-reconciliation-urgency" class="form-control">' +
            '<option value="all">Todas las diferencias</option>' +
            '<option value="urgent">Solo urgentes (stock y precio)</option>' +
            '<option value="non_urgent">Solo no urgentes (otros campos)</option>' +
            '</select>').val(reconciliationUrgencyFilter);
        $urgencySelect.on('change', function() {
            reconciliationUrgencyFilter = $(this).val();
            reconciliationPage = 1;
            renderReconciliationTable();
        });
        $urgencyColumn.append($urgencySelect);
        $filters.append($statusColumn).append($pageSizeColumn).append($resetColumn).append($urgencyColumn);
        $container.append($filters).append('<p class="text-muted">Selecciona productos vinculados con diferencias urgentes. La actualización enviará únicamente stock y precio; los demás campos no se modificarán.</p>');
        $container.append('<div class="clearfix" style="margin: 10px 0;"><button type="button" class="btn btn-success" id="yuju-push-differences-top" style="display:none;"><i class="icon-refresh"></i> Actualizar urgentes seleccionados (0)</button></div>');
        $container.append('<div id="yuju-reconciliation-results" class="table-responsive yuju-reconciliation-table-wrap"></div>');

        showMessage(
            message || ('Reconciliación cargada: ' + reconciliationProducts.length + ' productos de PrestaShop. La vista inicial muestra solo los que tienen diferencias.'),
            reconciliationProducts.length ? 'alert-info' : 'alert-success'
        );
        renderReconciliationTable();
        $report.removeClass('hidden');
        $pollBtn.hide();
    }

    function finishCombinedWorkflow() {
        currentStep = 3;
        setStep(3, 'running', 'Comprobando si ya hay stock/precio guardado (válido 12 horas) o descargándolo de Yuju...');
        setStartLabel('Paso 3 de 4: obteniendo stock y precio...');
        showMessage('Informe general listo. Obteniendo stock y precio...', 'alert-info');
        ajaxAction('downloadYujuOfferReport', null, function(offerResponse) {
            if (!offerResponse || !offerResponse.success) {
                var offerMessage = offerResponse && offerResponse.message ? offerResponse.message : 'No se pudo descargar el reporte de stock/precio. La reconciliación no se ejecutó.';
                endWorkflow(3, offerMessage);
                showMessage(offerMessage, 'alert-danger');
                return;
            }
            setStep(3, 'done', (offerResponse.reused ? 'Reutilizado (guardado hace menos de 12 h): ' : 'Descargado de Yuju y guardado: ') + (offerResponse.products_count || 0) + ' productos.');
            currentStep = 4;
            setStep(4, 'running', 'Combinando el catálogo general con stock/precio y comparando con PrestaShop...');
            setStartLabel('Paso 4 de 4: combinando y detectando diferencias...');
            showMessage('Combinando los reportes guardados y detectando diferencias...', 'alert-info');
            ajaxAction('getYujuProductSyncReport', null, function(reportResponse) {
                if (reportResponse && reportResponse.success && reportResponse.report) {
                    var compared = (reportResponse.report.all_products || reportResponse.report.unsynced_products || []).length;
                    setStep(4, 'done', compared + ' productos comparados.');
                    endWorkflow();
                    renderReconciliation(reportResponse.report, 'Sincronización de informes completada: catálogo general + stock/precio.');
                    showMessage('Proceso terminado: informe general y stock/precio combinados y reconciliados.', 'alert-success');
                } else {
                    var reportMessage = reportResponse && reportResponse.message ? reportResponse.message : 'Los reportes se descargaron, pero no se pudo completar la reconciliación.';
                    endWorkflow(4, reportMessage);
                    showMessage(reportMessage, 'alert-danger');
                }
            }, function() {
                endWorkflow(4, 'Falló la petición de reconciliación.');
                showMessage('Los reportes se descargaron, pero falló la petición de reconciliación.', 'alert-danger');
            });
        }, function(xhr, status, error) {
            var detail = 'No se pudo completar la petición de stock/precio.';
            if (xhr && xhr.status) {
                detail += ' HTTP ' + xhr.status + '.';
            }
            if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                detail += ' ' + xhr.responseJSON.message;
            } else if (xhr && xhr.responseText) {
                // Preserve a short diagnostic for timeouts, PHP errors, and non-JSON responses.
                var responseText = String(xhr.responseText).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                if (responseText) {
                    detail += ' Respuesta del servidor: ' + responseText.substring(0, 500);
                }
            } else if (error) {
                detail += ' ' + String(error);
            }
            if (status === 'timeout') {
                detail += ' La petición agotó el tiempo de espera.';
            }
            endWorkflow(3, detail);
            showMessage(detail + ' La reconciliación no se ejecutó.', 'alert-danger');
        });
    }

    $startBtn.on('click', function() {
        if (workflowRunning) return;
        workflowRunning = true;
        $report.removeClass('hidden');
        resetSteps();
        currentStep = 1;
        setStep(1, 'running', 'Enviando la solicitud a Yuju (si ya hay un informe vigente se reutiliza)...');
        setLoading($startBtn, true, 'Paso 1 de 4: solicitando informe general...');
        ajaxAction('startYujuProductReport', null, function(response) {
            handleReportState(response, true);
        }, function() {
            endWorkflow(1, 'No se pudo conectar con Yuju.');
            showMessage('Fallo al conectar con Yuju. Revise la conexión e intente nuevamente.', 'alert-danger');
        });
    });
    $pollBtn.on('click', function() {
        if (workflowRunning) return;
        workflowRunning = true;
        $report.removeClass('hidden');
        resetSteps();
        setStep(1, 'done', 'Tarea de informe existente.');
        currentStep = 2;
        setStep(2, 'running', 'Consultando el estado del informe (también reintenta una descarga fallida)...');
        setLoading($startBtn, true, 'Paso 2 de 4: consultando el informe...');
        setLoading($pollBtn, true, 'Comprobando / reintentando descarga...');
        // An explicit click also retries DOWNLOAD_ERROR using the same stored task.
        ajaxAction('pollYujuProductReport', {ldelim}retry_download: true{rdelim}, function(response) {
            setLoading($pollBtn, false);
            handleReportState(response, response && response.success && (response.status === 'CREATED' || response.status === 'PROCESSING'));
        }, function() {
            setLoading($pollBtn, false);
            endWorkflow(2, 'No se pudo consultar o recuperar el informe.');
            showMessage('No se pudo consultar o recuperar el informe. Inténtelo nuevamente.', 'alert-danger');
        });
    });
    $loadBtn.on('click', function() {
        setLoading($loadBtn, true, 'Cargando reconciliación...');
        ajaxAction('getYujuProductSyncReport', null, function(response) {
            setLoading($loadBtn, false);
            if (response && response.success && response.report) {
                renderReconciliation(response.report, response.message);
            } else {
                var message = response && response.message
                    ? response.message
                    : 'No hay informe completado en caché. Primero inicie un informe.';
                showMessage(message, 'alert-danger');
                if (response && response.status === 'DOWNLOAD_ERROR') {
                    $pollBtn.show();
                    $loadBtn.hide();
                    $pushBtn.hide();
                }
            }
        }, function() {
            setLoading($loadBtn, false);
            showMessage('No se pudo cargar la reconciliación. Compruebe la conexión y vuelva a intentarlo.', 'alert-danger');
        });
    });

    function pushSelectedDifferences($button) {
        var selectedIds = getSelectedProductIds();
        if (!selectedIds.length) {
            showMessage('Selecciona al menos un producto con diferencias urgentes.', 'alert-warning');
            return;
        }
        if (!confirm('Se actualizarán en Yuju únicamente stock y precio de ' + selectedIds.length + ' producto(s) seleccionado(s). Los demás campos no se modificarán. ¿Continuar?')) return;
        setLoading($button, true, 'Actualizando campos urgentes...');
        ajaxAction('pushDifferencesToYuju', {ldelim}selected_product_ids: JSON.stringify(selectedIds), urgent_only: 1{rdelim}, function(response) {
            setLoading($button, false);
            if (response && response.success) {
                var synced = response.synced_count || 0;
                var details = response.details || [];
                var failed = 0;
                $.each(details, function(i, d) {
                    if (d.status === 'error') {
                        failed++;
                    }
                });
                showMessage(
                    'Actualización urgente finalizada. Productos actualizados: ' + synced + (failed ? '; errores: ' + failed : '') + '. La tabla se vuelve a validar con el reporte actual.',
                    failed ? 'alert-warning' : 'alert-success'
                );
                if (response.report) {
                    renderReconciliation(response.report, response.message);
                }
            } else {
                // Do not hide the server's actual reason behind a generic message.
                var errorMessage = response && response.message
                    ? response.message
                    : 'Falló el envío a Yuju. El servidor no devolvió el motivo del error.';
                var errorDetails = [];
                if (response && response.errors && response.errors.length) {
                    $.each(response.errors, function(i, item) {
                        if (item && (item.message || item.reason)) {
                            errorDetails.push((item.prestashop_product_id ? 'Producto PS #' + item.prestashop_product_id + ': ' : '') + (item.message || item.reason));
                        }
                    });
                }
                if (errorDetails.length) {
                    errorMessage += ' ' + errorDetails.join(' | ');
                }
                if (window.console && typeof window.console.error === 'function') {
                    window.console.error('[PrestashopYuju] pushDifferencesToYuju failed', response);
                }
                showMessage(errorMessage, 'alert-danger');
            }
        }, function(xhr, status, error) {
            setLoading($button, false);
            var errorMessage = 'Falló la comunicación al enviar a Yuju.';
            if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                errorMessage = xhr.responseJSON.message;
            } else if (xhr && xhr.responseText) {
                errorMessage += ' Respuesta HTTP: ' + xhr.status + '.';
            }
            if (window.console && typeof window.console.error === 'function') {
                window.console.error('[PrestashopYuju] pushDifferencesToYuju AJAX error', status, error, xhr);
            }
            showMessage(errorMessage, 'alert-danger');
        });
    }

    $pushBtn.on('click', function() { pushSelectedDifferences($pushBtn); });
    $(document).on('click', '#yuju-push-differences-top', function() { pushSelectedDifferences($(this)); });

    // Automatically load a validated local report when opening AdminYujuSync,
    // so the user does not need to click "Ver reconciliación" on every visit.
    ajaxAction('getYujuProductSyncReport', null, function(response) {
        if (response && response.success && response.report) {
            renderReconciliation(response.report, response.message || 'Informe local validado y reconciliación cargada.');
            return;
        }

        // Keep the panel visible with actionable feedback instead of silently showing zeroes.
        $report.removeClass('hidden');
        showMessage(
            response && response.message
                ? response.message
                : 'No hay un informe local validado disponible. Inicie un informe para cargar la reconciliación.',
            'alert-info'
        );
        $loadBtn.hide();
        $pushBtn.hide();
    });

    // Auto-poll every 20s when report panel visible and polling is active
    setInterval(function() {
        if (typeof refreshSyncStatus === 'function') {
            refreshSyncStatus();
        }
    }, 30000);
});
</script>
{/block}