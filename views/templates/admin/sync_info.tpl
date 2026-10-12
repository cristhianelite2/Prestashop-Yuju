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
            <div class="row">
                <div class="col-sm-3"><strong>Total:</strong> <span id="yuju-products-total">0</span></div>
                <div class="col-sm-3"><strong>Sincronizados:</strong> <span id="yuju-products-synced">0</span></div>
                <div class="col-sm-3"><strong>No sincronizados:</strong> <span id="yuju-products-unsynced">0</span></div>
                <div class="col-sm-3"><strong>Listos para enviar:</strong> <span id="yuju-products-syncable">0</span></div>
            </div>
            <div id="yuju-unsynced-products" class="table-responsive" style="margin-top: 15px;"></div>
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

<script type="text/javascript">
$(function() {
    var $startBtn = $('#yuju-start-report');
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
        }).fail(function() {
            if (onError) {
                onError();
            } else {
                showMessage('Fallo al conectar con Yuju. Revise la conexión e intente nuevamente.', 'alert-danger');
            }
        });
    }

    function handleReportState(response, startPolling) {
        $report.removeClass('hidden');
        if (!response) {
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
                if (startPolling) {
                    startAutoPoll();
                }
            } else if (status === 'COMPLETED' && response.report_cached === true) {
                stopAutoPoll();
                showMessage('Informe descargado y listo. Cargue la reconciliación para ver las diferencias.', 'alert-success');
                $pollBtn.hide();
                $loadBtn.show();
                $pushBtn.hide();
                if (response.product_count !== undefined) {
                    $('#yuju-products-total').text(response.product_count);
                }
            } else if (status === 'COMPLETED') {
                // El estado remoto no garantiza que el archivo local se pueda leer.
                stopAutoPoll();
                showMessage(response.message || 'Yuju terminó el informe, pero aún no está disponible en la caché local. Compruebe la descarga antes de reconciliar.', 'alert-warning');
                $pollBtn.show();
                $loadBtn.hide();
                $pushBtn.hide();
            } else if (status === 'DOWNLOAD_ERROR') {
                stopAutoPoll();
                showMessage('Informe listo pero falló la validación del descarga. Reintente el descarga.', 'alert-warning');
                $pollBtn.show();
                $loadBtn.hide();
                $pushBtn.hide();
            } else if (status === 'RATE_LIMITED') {
                stopAutoPoll();
                showMessage('Límite diario de informes alcanzado (2/día UTC).', 'alert-warning');
                $pollBtn.hide();
                $loadBtn.show();
                $pushBtn.hide();
            } else if (status === 'UNKNOWN') {
                stopAutoPoll();
                showMessage('La solicitud de informe no pudo confirmarse. Evite solicitudes duplicadas.', 'alert-warning');
            } else {
                stopAutoPoll();
                showMessage('Estado del informe desconocido.', 'alert-warning');
            }
        } else {
            stopAutoPoll();
            showMessage(
                'No se pudo iniciar el informe general de Yuju.',
                'alert-danger'
            );
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

    function renderReconciliation(report, message) {
        // Mostrar todo el catálogo de PrestaShop; conservar aparte el subconjunto accionable.
        var products = report.all_products || report.unsynced_products || [];
        var actionableProducts = report.unsynced_products || [];
        $('#yuju-products-total').text(report.total || 0);
        $('#yuju-products-synced').text(report.synced_count || 0);
        $('#yuju-products-unsynced').text(report.unsynced_count || 0);
        $('#yuju-products-syncable').text(report.syncable_count || 0);

        showMessage(
            message || ('Reconciliación cargada: ' + products.length + ' productos en la tabla. Solo los vinculados con diferencias pueden enviarse a Yuju.'),
            products.length > 0 ? 'alert-info' : 'alert-success'
        );

        var $container = $('#yuju-unsynced-products');
        $container.empty();

        if (products.length) {
            var $table = $('<table class="table table-bordered table-striped"><thead><tr>' +
                '<th>ID PS</th>' +
                '<th>ID Yuju</th>' +
                '<th>Producto</th>' +
                '<th>SKU</th>' +
                '<th>Estado</th>' +
                '<th>Diferencias</th>' +
                '</tr></thead><tbody></tbody></table>');

            $.each(products, function(index, product) {
                var diffFields = [];
                $.each(product.differences || [], function(i, d) {
                    diffFields.push(d.field || '');
                });
                var msg = product.message || '';
                if (product.uncomparable && product.uncomparable.length) {
                    if (msg) {
                        msg += ' ';
                    }
                    msg += 'No comparable: ' + product.uncomparable.join(', ');
                }

                var statusLabels = {
                    synced: 'Sin diferencias',
                    differences: 'Con diferencias',
                    not_in_yuju: 'No está en Yuju',
                    ambiguous: 'SKU ambiguo',
                    needs_review: 'Revisión manual',
                    disabled: 'Sincronización deshabilitada'
                };
                var reconciliationStatus = product.reconciliation_status || product.status || 'needs_review';
                var displayStatus = statusLabels[reconciliationStatus] || reconciliationStatus;

                var $row = $('<tr></tr>');
                // Usar .text() en todas las celdas para evitar inyectar HTML de Yuju.
                $row.append($('<td></td>').text(product.prestashop_product_id || '—'));
                $row.append($('<td></td>').text(product.yuju_product_id || '—'));
                $row.append($('<td></td>').text(product.name || '—'));
                $row.append($('<td></td>').text(product.sku || '—'));
                $row.append($('<td></td>').text(displayStatus));
                $row.append($('<td></td>').text(diffFields.length ? diffFields.join(', ') + (msg ? ' — ' + msg : '') : (msg || '—')));
                $table.find('tbody').append($row);
            });
            $container.append($table);

            var syncableCount = 0;
            $.each(actionableProducts, function(i, p) {
                if (p.syncable && p.yuju_product_id) {
                    syncableCount++;
                }
            });

            if (syncableCount > 0) {
                $pushBtn
                    .html('<i class="icon-refresh"></i> Sincronizar (PrestaShop → Yuju) (' + syncableCount + ')')
                    .show();
            } else {
                $pushBtn.hide();
            }
        } else {
            $container.html('<p class="alert alert-info">No se encontraron productos para mostrar.</p>');
            $pushBtn.hide();
        }

        $report.removeClass('hidden');
        $pollBtn.hide();
    }

    $startBtn.on('click', function() {
        setLoading($startBtn, true, 'Solicitando informe...');
        ajaxAction('startYujuProductReport', null, function(response) {
            handleReportState(response, true);
            setLoading($startBtn, false);
        }, function() {
            showMessage('Fallo al conectar con Yuju. Revise la conexión e intente nuevamente.', 'alert-danger');
            setLoading($startBtn, false);
        });
    });

    $pollBtn.on('click', function() {
        setLoading($pollBtn, true, 'Comprobando / reintentando descarga...');
        // An explicit click also retries DOWNLOAD_ERROR using the same stored task.
        ajaxAction('pollYujuProductReport', {ldelim}retry_download: true{rdelim}, function(response) {
            handleReportState(response, false);
            setLoading($pollBtn, false);
        }, function() {
            showMessage('No se pudo consultar o recuperar el informe. Inténtelo nuevamente.', 'alert-danger');
            setLoading($pollBtn, false);
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

    $pushBtn.on('click', function() {
        if (!confirm('¿Desea enviar los valores de PrestaShop a Yuju para productos vinculados con diferencias? No se crearán productos en Yuju.')) {
            return;
        }
        setLoading($pushBtn, true, 'Enviando a Yuju...');
        ajaxAction('pushDifferencesToYuju', null, function(response) {
            setLoading($pushBtn, false);
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
                    'Sincronización completada' + (failed ? ' falló ' + failed + ' veces' : ''),
                    failed ? 'alert-warning' : 'alert-success'
                );
                if (response.report) {
                    renderReconciliation(response.report, response.message);
                }
            } else {
                showMessage(
                    'Falló el envío a Yuju.',
                    'alert-danger'
                );
            }
        }, function() {
            setLoading($pushBtn, false);
        });
    });

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