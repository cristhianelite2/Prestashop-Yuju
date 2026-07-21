{*
* Monitoreo de auditorías de ofertas
*}
{extends file="./layout.tpl"}

{block name="content"}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<div class="panel">
    <div class="panel-heading">
        <i class="icon-bar-chart"></i> Monitoreo de Auditoría
        <a href="{$audit_link|escape:'html':'UTF-8'}" class="btn btn-primary btn-xs pull-right" style="margin-top:-3px;">
            <i class="icon-play"></i> Ejecutar auditoría
        </a>
    </div>
    <div class="panel-body">
        <div class="row yuju-audit-monitor-kpis" style="margin-bottom:20px;">
            <div class="col-sm-2">
                <div class="well well-sm text-center">
                    <strong>{$audit_totals.runs_count|intval}</strong><br><small>Auditorías</small>
                </div>
            </div>
            <div class="col-sm-2">
                <div class="well well-sm text-center text-success">
                    <strong>{$audit_totals.sum_matched|intval}</strong><br><small>Sincronizados</small>
                </div>
            </div>
            <div class="col-sm-2">
                <div class="well well-sm text-center text-warning">
                    <strong>{$audit_totals.sum_diff|intval}</strong><br><small>Diferencias</small>
                </div>
            </div>
            <div class="col-sm-2">
                <div class="well well-sm text-center text-primary">
                    <strong>{$audit_totals.sum_fixed|intval}</strong><br><small>Corregidos</small>
                </div>
            </div>
            <div class="col-sm-2">
                <div class="well well-sm text-center text-danger">
                    <strong>{$audit_totals.sum_errors|intval}</strong><br><small>Errores</small>
                </div>
            </div>
            <div class="col-sm-2">
                <div class="well well-sm text-center">
                    <strong>{$audit_totals.fix_rate|escape:'html':'UTF-8'}%</strong><br><small>Tasa corrección</small>
                </div>
            </div>
        </div>

        <div class="row" style="margin-bottom:25px;">
            <div class="col-md-8">
                <h4>Evolución (últimas ejecuciones)</h4>
                <canvas id="yujuAuditEvolutionChart" height="120"></canvas>
            </div>
            <div class="col-md-4">
                <h4>Tipos de diferencia</h4>
                <canvas id="yujuAuditDiffTypesChart" height="200"></canvas>
            </div>
        </div>

        <h4>Historial de auditorías</h4>
        <div class="table-responsive">
            <table class="table table-striped table-bordered" id="yuju-audit-runs-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Inicio</th>
                        <th>Duración</th>
                        <th>Origen</th>
                        <th>Estado</th>
                        <th>Total</th>
                        <th>OK</th>
                        <th>Diff</th>
                        <th>Corregidos</th>
                        <th>Errores</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    {if $audit_runs|@count == 0}
                        <tr><td colspan="11" class="text-center text-muted">Aún no hay auditorías registradas.</td></tr>
                    {else}
                        {foreach from=$audit_runs item=run}
                            <tr>
                                <td>#{$run.id|intval}</td>
                                <td>{$run.started_at|escape:'html':'UTF-8'}</td>
                                <td>
                                    {if isset($run.duration_human) && $run.duration_human}
                                        {$run.duration_human|escape:'html':'UTF-8'}
                                    {elseif isset($run.duration_seconds)}
                                        {$run.duration_seconds|intval}s
                                    {else}
                                        —
                                    {/if}
                                </td>
                                <td>{$run.trigger|escape:'html':'UTF-8'} / {$run.mode|escape:'html':'UTF-8'}</td>
                                <td>{$run.status|escape:'html':'UTF-8'}</td>
                                <td>{$run.total|intval}</td>
                                <td>{$run.matched|intval}</td>
                                <td>{$run.diff_found|intval}</td>
                                <td>{$run.fixed|intval}</td>
                                <td>{$run.errors|intval}</td>
                                <td>
                                    <button type="button" class="btn btn-default btn-xs yuju-audit-view-detail"
                                            data-run-id="{$run.id|intval}">
                                        Detalle
                                    </button>
                                </td>
                            </tr>
                        {/foreach}
                    {/if}
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="yujuAuditDetailModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Detalle de auditoría <span id="yuju-audit-detail-id"></span></h4>
            </div>
            <div class="modal-body">
                <div class="form-inline" style="margin-bottom:10px;">
                    <label>Filtrar resultado:&nbsp;</label>
                    <select id="yuju-audit-detail-filter" class="form-control input-sm">
                        <option value="">Todos</option>
                        <option value="diff_fixed">Corregidos</option>
                        <option value="diff_error">Errores</option>
                        <option value="not_found">No encontrados</option>
                        <option value="matched">Sincronizados</option>
                    </select>
                </div>
                <div class="table-responsive" style="max-height:420px; overflow:auto;">
                    <table class="table table-condensed table-bordered">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Resultado</th>
                                <th>Stock PS/Yuju</th>
                                <th>Precio PS/Yuju</th>
                                <th>Imgs PS/Yuju</th>
                                <th>Mensaje</th>
                            </tr>
                        </thead>
                        <tbody id="yuju-audit-detail-tbody">
                            <tr><td colspan="6" class="text-center">Cargando…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

{* Histórico + visualizador: ofertas (12h) y general (2/día UTC) *}
<div class="panel" id="yuju-gral-reports" style="margin-top:20px;">
    <div class="panel-heading">
        <i class="icon-file-text-o"></i> Reportes Yuju guardados
        <small class="text-muted">(offer cada 12h · gral máx. 2/día UTC)</small>
        <a href="{$config_link|escape:'html':'UTF-8'}" class="btn btn-default btn-xs pull-right" style="margin-top:-3px;">
            <i class="icon-cog"></i> Configurar
        </a>
    </div>
    <div class="panel-body">
        <div class="row" style="margin-bottom:15px;">
            <div class="col-sm-2">
                <div class="well well-sm text-center">
                    <strong>{$gral_totals.reports_count|intval}</strong><br><small>En histórico</small>
                </div>
            </div>
            <div class="col-sm-2">
                <div class="well well-sm text-center text-success">
                    <strong>{$gral_totals.completed|intval}</strong><br><small>Completados</small>
                </div>
            </div>
            <div class="col-sm-2">
                <div class="well well-sm text-center text-warning">
                    <strong>{$gral_totals.processing|intval}</strong><br><small>En progreso</small>
                </div>
            </div>
            <div class="col-sm-3">
                <div class="well well-sm text-center">
                    <strong>Ofertas</strong><br>
                    <small>
                        {if isset($offer_quota) && !$offer_quota.allowed}
                            Faltan {$offer_quota.remaining_human|default:'…'|escape:'html':'UTF-8'}
                        {else}
                            cada {if isset($offer_quota.interval_hours)}{$offer_quota.interval_hours|intval}{else}12{/if}h
                        {/if}
                    </small>
                </div>
            </div>
            <div class="col-sm-3">
                <div class="well well-sm text-center">
                    <strong>{$gral_quota.used|intval}/{$gral_quota.max|intval}</strong><br>
                    <small>General UTC {$gral_quota.utc_day|escape:'html':'UTF-8'}</small>
                </div>
            </div>
        </div>

        <h4>Histórico de descargas</h4>
        <div class="table-responsive">
            <table class="table table-striped table-bordered" id="yuju-gral-history-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tipo</th>
                        <th>Solicitado</th>
                        <th>Origen</th>
                        <th>Estado</th>
                        <th>Productos</th>
                        <th>Tamaño</th>
                        <th>Completado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    {if $gral_history|@count == 0}
                        <tr><td colspan="9" class="text-center text-muted">Aún no hay reportes guardados.</td></tr>
                    {else}
                        {foreach from=$gral_history item=rep}
                            <tr data-report-id="{$rep.id|intval}">
                                <td>#{$rep.id|intval}</td>
                                <td>
                                    {if isset($rep.report_type) && $rep.report_type == 'offer'}
                                        <span class="label label-info">OFERTA</span>
                                    {else}
                                        <span class="label label-primary">GENERAL</span>
                                    {/if}
                                </td>
                                <td>{$rep.requested_at|escape:'html':'UTF-8'}</td>
                                <td>{$rep.trigger|escape:'html':'UTF-8'}</td>
                                <td>
                                    <span class="label label-{if $rep.status == 'completed'}success{elseif $rep.status == 'processing' || $rep.status == 'pending'}warning{else}danger{/if}">
                                        {$rep.status|escape:'html':'UTF-8'}
                                    </span>
                                </td>
                                <td>{$rep.products_count|intval}</td>
                                <td>{if $rep.file_size}{($rep.file_size/1024)|string_format:"%.1f"} KB{else}—{/if}</td>
                                <td>{$rep.completed_at|default:'—'|escape:'html':'UTF-8'}</td>
                                <td>
                                    {if $rep.status == 'completed'}
                                        <button type="button" class="btn btn-primary btn-xs yuju-gral-open-viewer"
                                                data-report-id="{$rep.id|intval}"
                                                data-report-type="{if isset($rep.report_type)}{$rep.report_type|escape:'html':'UTF-8'}{else}gral{/if}">
                                            <i class="icon-eye"></i> Visualizar
                                        </button>
                                    {elseif ($rep.status == 'processing' || $rep.status == 'pending') && (!isset($rep.report_type) || $rep.report_type != 'offer')}
                                        <button type="button" class="btn btn-default btn-xs yuju-gral-poll"
                                                data-report-id="{$rep.id|intval}">
                                            <i class="icon-refresh"></i> Consultar estado
                                        </button>
                                    {else}
                                        <small class="text-muted">{$rep.error_message|default:''|escape:'html':'UTF-8'|truncate:40}</small>
                                    {/if}
                                </td>
                            </tr>
                        {/foreach}
                    {/if}
                </tbody>
            </table>
        </div>

        <div id="yuju-gral-viewer" style="display:none; margin-top:20px;">
            <h4>
                Visualizador
                <small class="text-muted" id="yuju-gral-viewer-meta"></small>
            </h4>
            <div class="form-inline" style="margin-bottom:10px;">
                <input type="text" id="yuju-gral-search" class="form-control" placeholder="Buscar ID, SKU o nombre…" style="min-width:260px;">
                <button type="button" class="btn btn-default" id="yuju-gral-search-btn"><i class="icon-search"></i></button>
                <button type="button" class="btn btn-default" id="yuju-gral-prev" disabled>&laquo; Anterior</button>
                <button type="button" class="btn btn-default" id="yuju-gral-next" disabled>Siguiente &raquo;</button>
                <span class="text-muted" id="yuju-gral-page-info" style="margin-left:8px;"></span>
            </div>
            <div class="table-responsive" style="max-height:480px; overflow:auto;">
                <table class="table table-condensed table-bordered" id="yuju-gral-viewer-table">
                    <thead id="yuju-gral-viewer-thead">
                        <tr>
                            <th></th>
                            <th>ID</th>
                            <th>SKU</th>
                            <th>Nombre</th>
                            <th>Imágenes</th>
                            <th>Variaciones</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="yuju-gral-viewer-tbody">
                        <tr><td colspan="7" class="text-center text-muted">Seleccione un reporte completado.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="yujuGralProductModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Detalle producto (JSONL)</h4>
            </div>
            <div class="modal-body">
                <pre id="yuju-gral-product-json" style="max-height:520px; overflow:auto; background:#f7f7f7; padding:12px; font-size:12px;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
(function ($) {
    var ajaxUrl = '{$ajax_url|escape:'javascript':'UTF-8'}';
    var chartData = {$audit_chart_json nofilter};

    if (typeof Chart !== 'undefined' && chartData) {
        var eCtx = document.getElementById('yujuAuditEvolutionChart');
        if (eCtx) {
            new Chart(eCtx, {
                type: 'line',
                data: {
                    labels: chartData.labels || [],
                    datasets: [
                        { label: 'Sincronizados', data: chartData.matched || [], borderColor: '#5cb85c', tension: 0.2 },
                        { label: 'Diferencias', data: chartData.diff || [], borderColor: '#f0ad4e', tension: 0.2 },
                        { label: 'Corregidos', data: chartData.fixed || [], borderColor: '#337ab7', tension: 0.2 }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'bottom' } },
                    scales: { y: { beginAtZero: true } }
                }
            });
        }

        var dCtx = document.getElementById('yujuAuditDiffTypesChart');
        if (dCtx) {
            var dt = chartData.diff_types || {};
            new Chart(dCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Stock', 'Precio', 'Imágenes'],
                    datasets: [{
                        data: [dt.stock || 0, dt.price || 0, dt.images || 0],
                        backgroundColor: ['#5bc0de', '#f0ad4e', '#d9534f']
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }
    }

    var currentRunId = null;

    function loadDetail(runId, resultFilter) {
        $('#yuju-audit-detail-tbody').html('<tr><td colspan="6" class="text-center">Cargando…</td></tr>');
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                ajax: 1,
                action: 'getAuditRunDetail',
                run_id: runId,
                result: resultFilter || '',
                limit: 300
            }
        }).done(function (resp) {
            if (!resp || !resp.success) {
                $('#yuju-audit-detail-tbody').html(
                    '<tr><td colspan="6" class="text-danger">' +
                    ((resp && resp.message) ? resp.message : 'Error') +
                    '</td></tr>'
                );
                return;
            }
            var rows = resp.details || [];
            if (!rows.length) {
                $('#yuju-audit-detail-tbody').html(
                    '<tr><td colspan="6" class="text-center text-muted">Sin detalle para este filtro.</td></tr>'
                );
                return;
            }
            function fmtAuditPrice(v) {
                if (v == null || v === '') return '—';
                var n = Number(v);
                if (isNaN(n)) return String(v);
                return '$' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            var html = '';
            rows.forEach(function (r) {
                html += '<tr>' +
                    '<td>' + (r.sku || '-') + '</td>' +
                    '<td>' + (r.result || '-') + '</td>' +
                    '<td>' + (r.ps_stock != null ? r.ps_stock : '-') + ' / ' + (r.yuju_stock != null ? r.yuju_stock : '-') + '</td>' +
                    '<td>' + fmtAuditPrice(r.ps_price) + ' / ' + fmtAuditPrice(r.yuju_price) + '</td>' +
                    '<td>' + (r.ps_images_count != null ? r.ps_images_count : '-') + ' / ' + (r.yuju_images_count != null ? r.yuju_images_count : '-') + '</td>' +
                    '<td>' + (r.message || '') + '</td>' +
                    '</tr>';
            });
            $('#yuju-audit-detail-tbody').html(html);
        }).fail(function () {
            $('#yuju-audit-detail-tbody').html(
                '<tr><td colspan="6" class="text-danger">Error de comunicación</td></tr>'
            );
        });
    }

    $(document).on('click', '.yuju-audit-view-detail', function () {
        currentRunId = $(this).data('run-id');
        $('#yuju-audit-detail-id').text('#' + currentRunId);
        $('#yuju-audit-detail-filter').val('');
        $('#yujuAuditDetailModal').modal('show');
        loadDetail(currentRunId, '');
    });

    $('#yuju-audit-detail-filter').on('change', function () {
        if (currentRunId) {
            loadDetail(currentRunId, $(this).val());
        }
    });

    // Visualizador products-offer-report / products-gral-report
    var gralReportId = null;
    var gralReportType = 'gral';
    var gralOffset = 0;
    var gralLimit = 50;
    var gralTotal = 0;

    function isOfferReportType(t) {
        return String(t || '') === 'offer';
    }

    function viewerColspan() {
        return isOfferReportType(gralReportType) ? 6 : 7;
    }

    function setViewerHeaders(reportType) {
        gralReportType = reportType || 'gral';
        if (isOfferReportType(gralReportType)) {
            $('#yuju-gral-viewer-thead').html(
                '<tr>' +
                    '<th>ID</th><th>SKU</th><th>Nombre</th>' +
                    '<th>Stock</th><th>Precio</th><th></th>' +
                '</tr>'
            );
        } else {
            $('#yuju-gral-viewer-thead').html(
                '<tr>' +
                    '<th></th><th>ID</th><th>SKU</th><th>Nombre</th>' +
                    '<th>Imágenes</th><th>Variaciones</th><th></th>' +
                '</tr>'
            );
        }
    }

    function formatOfferPrice(price) {
        if (price === null || price === undefined || price === '') {
            return '—';
        }
        var n = Number(price);
        if (isNaN(n)) {
            return String(price);
        }
        return n.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    }

    function loadGralPage() {
        if (!gralReportId) {
            return;
        }
        var cols = viewerColspan();
        $('#yuju-gral-viewer-tbody').html('<tr><td colspan="' + cols + '" class="text-center">Cargando…</td></tr>');
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                ajax: 1,
                action: 'browseGralProducts',
                report_id: gralReportId,
                offset: gralOffset,
                limit: gralLimit,
                search: $('#yuju-gral-search').val() || ''
            }
        }).done(function (resp) {
            if (!resp || !resp.success) {
                $('#yuju-gral-viewer-tbody').html(
                    '<tr><td colspan="' + cols + '" class="text-danger">' + ((resp && resp.message) || 'Error') + '</td></tr>'
                );
                return;
            }
            if (resp.report_type) {
                setViewerHeaders(resp.report_type);
                cols = viewerColspan();
            }
            gralTotal = resp.total || 0;
            var from = gralTotal ? (gralOffset + 1) : 0;
            var to = Math.min(gralOffset + gralLimit, gralTotal);
            $('#yuju-gral-page-info').text(from + '–' + to + ' de ' + gralTotal);
            $('#yuju-gral-prev').prop('disabled', gralOffset <= 0);
            $('#yuju-gral-next').prop('disabled', gralOffset + gralLimit >= gralTotal);
            var typeLabel = isOfferReportType(gralReportType)
                ? 'OFERTA (stock / precio)'
                : 'GENERAL (imágenes / variaciones)';
            $('#yuju-gral-viewer-meta').text('Reporte #' + gralReportId + ' · ' + typeLabel);

            var products = resp.products || [];
            if (!products.length) {
                $('#yuju-gral-viewer-tbody').html(
                    '<tr><td colspan="' + cols + '" class="text-center text-muted">Sin resultados</td></tr>'
                );
                return;
            }
            var html = '';
            var offerMode = isOfferReportType(gralReportType);
            products.forEach(function (p) {
                if (offerMode) {
                    html += '<tr>' +
                        '<td>' + (p.id || '') + '</td>' +
                        '<td>' + (p.sku || '') + '</td>' +
                        '<td>' + (p.name || '') + '</td>' +
                        '<td>' + (p.stock !== null && p.stock !== undefined ? p.stock : '—') + '</td>' +
                        '<td>' + formatOfferPrice(p.price) + '</td>' +
                        '<td><button type="button" class="btn btn-default btn-xs yuju-gral-product-detail" data-line="' + p.line + '">JSON</button></td>' +
                        '</tr>';
                } else {
                    var thumb = p.thumb
                        ? '<img src="' + String(p.thumb).replace(/"/g, '&quot;') + '" alt="" style="width:36px;height:36px;object-fit:cover;">'
                        : '';
                    html += '<tr>' +
                        '<td>' + thumb + '</td>' +
                        '<td>' + (p.id || '') + '</td>' +
                        '<td>' + (p.sku || '') + '</td>' +
                        '<td>' + (p.name || '') + '</td>' +
                        '<td>' + (p.images_count || 0) + '</td>' +
                        '<td>' + (p.variations_count || 0) + '</td>' +
                        '<td><button type="button" class="btn btn-default btn-xs yuju-gral-product-detail" data-line="' + p.line + '">JSON</button></td>' +
                        '</tr>';
                }
            });
            $('#yuju-gral-viewer-tbody').html(html);
        }).fail(function () {
            $('#yuju-gral-viewer-tbody').html(
                '<tr><td colspan="' + viewerColspan() + '" class="text-danger">Error de comunicación</td></tr>'
            );
        });
    }

    $(document).on('click', '.yuju-gral-open-viewer', function () {
        gralReportId = $(this).data('report-id');
        setViewerHeaders($(this).data('report-type') || 'gral');
        gralOffset = 0;
        $('#yuju-gral-viewer').show();
        $('#yuju-gral-search').val('');
        loadGralPage();
        if ($('#yuju-gral-viewer').offset()) {
            $('html, body').animate({ scrollTop: $('#yuju-gral-viewer').offset().top - 80 }, 300);
        }
    });

    $('#yuju-gral-search-btn').on('click', function () {
        gralOffset = 0;
        loadGralPage();
    });
    $('#yuju-gral-search').on('keypress', function (e) {
        if (e.which === 13) {
            gralOffset = 0;
            loadGralPage();
        }
    });
    $('#yuju-gral-prev').on('click', function () {
        gralOffset = Math.max(0, gralOffset - gralLimit);
        loadGralPage();
    });
    $('#yuju-gral-next').on('click', function () {
        gralOffset += gralLimit;
        loadGralPage();
    });

    $(document).on('click', '.yuju-gral-product-detail', function () {
        var line = $(this).data('line');
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                ajax: 1,
                action: 'getGralProductDetail',
                report_id: gralReportId,
                line: line
            }
        }).done(function (resp) {
            if (!resp || !resp.success) {
                alert((resp && resp.message) || 'No se pudo cargar el producto');
                return;
            }
            $('#yuju-gral-product-json').text(JSON.stringify(resp.product, null, 2));
            $('#yujuGralProductModal').modal('show');
        });
    });

    $(document).on('click', '.yuju-gral-poll', function () {
        var $btn = $(this);
        var id = $btn.data('report-id');
        $btn.prop('disabled', true).text('Consultando…');
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: { ajax: 1, action: 'pollGralReport', report_id: id }
        }).done(function (resp) {
            alert((resp && resp.message) || 'Listo');
            window.location.reload();
        }).fail(function () {
            alert('Error al consultar estado');
            $btn.prop('disabled', false).html('<i class="icon-refresh"></i> Consultar estado');
        });
    });
})(jQuery);
</script>
{/block}
