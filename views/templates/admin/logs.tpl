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
<div class="panel panel-info" id="yuju-bulk-offer-task-panel">
    <div class="panel-heading">
        <i class="icon-tasks"></i> Estado de tareas masivas de ofertas Yuju
        <span class="pull-right">
            <button type="button" class="btn btn-default btn-xs" id="yuju-refresh-bulk-offer-tasks">
                <i class="icon-refresh"></i> <span class="yuju-refresh-label">Actualizar estado</span>
            </button>
        </span>
    </div>
    <div class="panel-body">
        <div class="form-inline" style="margin-bottom:15px;">
            <label for="yuju-recover-task-id" style="margin-right:6px;">Recuperar tarea existente de Yuju:</label>
            <input type="text" class="form-control" id="yuju-recover-task-id" placeholder="ID de tarea (ej. 322954981)" style="max-width:220px;">
            <button type="button" class="btn btn-primary" id="yuju-recover-bulk-offer-task">
                <i class="icon-download"></i> Recuperar del servidor Yuju
            </button>
            <span id="yuju-recover-task-message" style="margin-left:8px;"></span>
            <p class="help-block">Consulta el ID en Yuju y lo agrega al historial local; no vuelve a enviar ofertas.</p>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-condensed" id="yuju-bulk-offer-task-table">
                <thead>
                    <tr>
                        <th>Agregada</th><th>ID tarea Yuju</th><th>Estado</th>
                        <th class="text-right">SKU</th><th class="text-right">Correctos</th>
                        <th class="text-right">Errores</th><th>Última consulta</th><th>Detalle</th>
                    </tr>
                </thead>
                <tbody id="yuju-bulk-offer-task-tbody">
                    {if isset($bulk_offer_tasks) && $bulk_offer_tasks|@count > 0}
                        {foreach from=$bulk_offer_tasks item=task}
                            <tr>
                                <td>{$task.submitted_at|default:'—'|escape:'html':'UTF-8'}</td>
                                <td><code>{$task.id_task|escape:'html':'UTF-8'}</code></td>
                                <td>{$task.status|default:'CREATED'|escape:'html':'UTF-8'}</td>
                                <td class="text-right">{$task.sku_count|default:0|intval}</td>
                                <td class="text-right">{$task.success_count|default:0|intval}</td>
                                <td class="text-right">{$task.errors_count|default:0|intval}</td>
                                <td>{$task.last_checked_at|default:$task.updated_at|default:'—'|escape:'html':'UTF-8'}</td>
                                <td>
                                    {if isset($task.details) && $task.details|@count > 0}
                                        <button type="button" class="btn btn-xs btn-warning yuju-bulk-task-details" data-task-id="{$task.id_task|escape:'html':'UTF-8'}">Ver detalles ({$task.details|@count})</button>
                                    {else}
                                        {$task.message|default:'—'|escape:'html':'UTF-8'}
                                        {if isset($task.last_check_error) && $task.last_check_error}<br><small class="text-danger">{$task.last_check_error|escape:'html':'UTF-8'}</small>{/if}
                                    {/if}
                                </td>
                            </tr>
                        {/foreach}
                    {else}
                        <tr class="yuju-bulk-task-empty"><td colspan="8" class="text-center text-muted">Todavía no hay tareas masivas registradas.</td></tr>
                    {/if}
                </tbody>
            </table>
        </div>
        <pre id="yuju-bulk-offer-task-details" style="display:none; max-height:320px; overflow:auto; white-space:pre-wrap;"></pre>
    </div>
</div>
<script type="text/javascript">
(function ($) {
    var endpoint = '{$cleanup_ajax_url|escape:'javascript':'UTF-8'}';
    var busy = false;
    function esc(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }
    function renderTasks(tasks) {
        var $body = $('#yuju-bulk-offer-task-tbody');
        if (!tasks || !tasks.length) {
            $body.html('<tr class="yuju-bulk-task-empty"><td colspan="8" class="text-center text-muted">Todavía no hay tareas masivas registradas.</td></tr>');
            return;
        }
        var html = '';
        tasks.slice().reverse().forEach(function (task) {
            var details = Array.isArray(task.details) ? task.details : [];
            var errors = parseInt(task.errors_count, 10) || 0;
            var detailLabel = details.length
                ? 'Ver detalles (' + details.length + ')'
                : (errors > 0 ? 'Ver respuesta de Yuju (' + errors + ' errores)' : 'Ver respuesta de Yuju');
            var detailCell = (details.length || errors > 0 || task.api_response)
                ? '<button type="button" class="btn btn-xs ' + (errors > 0 ? 'btn-warning' : 'btn-default') + ' yuju-bulk-task-details" data-task-id="' + esc(task.id_task) + '">' + esc(detailLabel) + '</button>'
                : esc(task.message || '—');
            if (errors > 0 && !details.length) {
                detailCell += '<br><small class="text-warning">Yuju reportó ' + errors + ' errores, pero no se identificaron detalles por SKU. Abra la respuesta de Yuju para ver qué información entregó la API.</small>';
            }
            if (task.last_check_error) detailCell += '<br><small class="text-danger">' + esc(task.last_check_error) + '</small>';
            html += '<tr><td>' + esc(task.submitted_at || '—') + '</td>'
                + '<td><code>' + esc(task.id_task || '') + '</code></td>'
                + '<td>' + esc(task.status || 'CREATED') + '</td>'
                + '<td class="text-right">' + esc(task.sku_count || 0) + '</td>'
                + '<td class="text-right">' + esc(task.success_count || 0) + '</td>'
                + '<td class="text-right">' + esc(task.errors_count || 0) + '</td>'
                + '<td>' + esc(task.last_checked_at || task.updated_at || '—') + '</td>'
                + '<td>' + detailCell + '</td></tr>';
        });
        $body.html(html);
    }
    function setRefreshButtonState(state) {
        var $button = $('#yuju-refresh-bulk-offer-tasks');
        var $icon = $button.find('i');
        var labels = {
            loading: 'Obteniendo...',
            updated: 'Actualizado',
            error: 'Error al actualizar',
            idle: 'Actualizar estado'
        };
        $button.find('span.yuju-refresh-label').text(labels[state] || labels.idle);
        $icon.removeClass('icon-refresh icon-check icon-warning-sign icon-spin');
        if (state === 'loading') {
            $icon.addClass('icon-refresh icon-spin');
            $button.prop('disabled', true);
        } else if (state === 'updated') {
            $icon.addClass('icon-check');
            $button.prop('disabled', false);
        } else if (state === 'error') {
            $icon.addClass('icon-warning-sign');
            $button.prop('disabled', false);
        } else {
            $icon.addClass('icon-refresh');
            $button.prop('disabled', false);
        }
    }
    function refreshTasks(showStatus) {
        if (busy) return;
        busy = true;
        if (showStatus) setRefreshButtonState('loading');
        $.ajax({
            url: endpoint, type: 'POST', dataType: 'json', timeout: 60000,
            data: {ldelim}ajax: 1, action: 'refreshBulkOfferTasks'{rdelim}
        }).done(function (response) {
            if (response && response.success) {
                renderTasks(response.tasks || []);
                if (showStatus) setRefreshButtonState('updated');
            } else if (showStatus) {
                setRefreshButtonState('error');
            }
        }).fail(function () {
            if (showStatus) setRefreshButtonState('error');
        }).always(function () { busy = false; });
    }
    $(function () {
        $('#yuju-refresh-bulk-offer-tasks').on('click', function () { refreshTasks(true); });
        $('#yuju-recover-bulk-offer-task').on('click', function () {
            var id = $.trim($('#yuju-recover-task-id').val() || '');
            var $button = $(this);
            var $message = $('#yuju-recover-task-message');
            if (!id) {
                $message.removeClass('text-success').addClass('text-danger').text('Escriba el ID de tarea.');
                return;
            }
            $button.prop('disabled', true);
            $message.removeClass('text-success text-danger').text('Consultando Yuju...');
            $.ajax({
                url: endpoint, type: 'POST', dataType: 'json', timeout: 60000,
                data: {ldelim}ajax: 1, action: 'recoverBulkOfferTask', task_id: id{rdelim}
            }).done(function (response) {
                if (response && response.success) {
                    renderTasks(response.tasks || []);
                    $message.removeClass('text-danger').addClass('text-success').text(response.message || 'Tarea recuperada.');
                } else {
                    $message.removeClass('text-success').addClass('text-danger').text((response && response.message) || 'No se pudo recuperar la tarea.');
                }
            }).fail(function (xhr) {
                var message = 'Falló la consulta de recuperación.';
                if (xhr.responseJSON && xhr.responseJSON.message) message = xhr.responseJSON.message;
                $message.removeClass('text-success').addClass('text-danger').text(message);
            }).always(function () { $button.prop('disabled', false); });
        });
        $(document).on('click', '.yuju-bulk-task-details', function () {
            var id = String($(this).attr('data-task-id') || '');
            $.ajax({
                url: endpoint, type: 'POST', dataType: 'json',
                data: {ldelim}ajax: 1, action: 'refreshBulkOfferTasks'{rdelim}
            }).done(function (response) {
                var task = null;
                (response.tasks || []).some(function (candidate) {
                    if (String(candidate.id_task) === id) { task = candidate; return true; }
                    return false;
                });
                var output = 'No se encontró el detalle de la tarea.';
                if (task) {
                    var diagnostic = {
                        id_task: task.id_task,
                        status: task.status,
                        total_rows: task.total_rows,
                        found_skus: task.found_skus,
                        success_count: task.success_count,
                        errors_count: task.errors_count,
                        submitted_offers: task.submitted_offers || [],
                        sku_details: Array.isArray(task.details) ? task.details : [],
                        submission_response: task.submission_response || null,
                        latest_status_response: task.api_response || null,
                        last_check_error: task.last_check_error || null,
                        history: task.history || []
                    };
                    output = (Array.isArray(task.details) && task.details.length)
                        ? 'Detalles por SKU y respuestas de la tarea:\n\n' + JSON.stringify(diagnostic, null, 2)
                        : 'Yuju reporta ' + (parseInt(task.errors_count, 10) || 0) + ' error(es), pero no se reconocieron detalles por SKU. El diagnóstico incluye los valores enviados, la respuesta al crear la tarea y la última respuesta de estado de Yuju. Si la API no incluye la causa individual, no es posible deducirla sin otra respuesta de Yuju.\n\n' + JSON.stringify(diagnostic, null, 2);
                }
                $('#yuju-bulk-offer-task-details').text(output).show();
            });
        });
        if ($('#yuju-bulk-offer-task-panel').length) {
            refreshTasks(false);
            window.setInterval(function () { refreshTasks(false); }, 15000);
        }
    });
})(window.jQuery);
</script>
<div class="panel panel-warning">
    <div class="panel-heading">
        <i class="icon-database"></i> Limpieza de datos históricos (solo tablas de log / auditoría)
    </div>
    <div class="panel-body">
        <button type="button" class="btn btn-warning" id="yuju-open-cleanup-modal" data-toggle="modal" data-target="#yujuCleanupModal">
            <i class="icon-trash"></i> Abrir asistente de limpieza…
        </button>
    </div>
</div>

<div class="modal fade" id="yujuCleanupModal" tabindex="-1" role="dialog" aria-labelledby="yujuCleanupModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="yujuCleanupModalLabel">
                    <i class="icon-warning-sign"></i> Limpieza de registros antiguos en base de datos
                </h4>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    Se intentará eliminar primero las filas anteriores al umbral. Si una tabla seleccionada no tiene filas antiguas,
                    se limpiará toda esa tabla (solo del ámbito permitido).
                </div>
                <div class="form-inline" style="margin-bottom:15px;">
                    <label for="yuju-cleanup-days">Eliminar registros más antiguos que</label>
                    <input type="number" id="yuju-cleanup-days" class="form-control input-sm" value="30" min="1" max="3650" style="width:90px; margin: 0 8px;">
                    <span>días</span>
                    <button type="button" class="btn btn-default btn-sm" id="yuju-cleanup-refresh">
                        <i class="icon-refresh"></i> Actualizar estadísticas
                    </button>
                </div>
                <div id="yuju-cleanup-loading" class="text-center text-muted" style="display:none;padding:20px;">
                    <i class="icon-spinner icon-spin"></i> Cargando…
                </div>
                <div id="yuju-cleanup-error" class="alert alert-danger" style="display:none;"></div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-condensed" id="yuju-cleanup-table" style="display:none;">
                        <thead>
                            <tr>
                                <th width="36"><input type="checkbox" id="yuju-cleanup-check-all" checked title="Seleccionar todas"></th>
                                <th>Origen</th>
                                <th>Tabla</th>
                                <th class="text-right">Filas (ámbito)</th>
                                <th class="text-right">Peso tabla (aprox.)</th>
                                <th>Último registro</th>
                                <th class="text-right text-danger">A borrar (&lt; umbral)</th>
                            </tr>
                        </thead>
                        <tbody id="yuju-cleanup-tbody"></tbody>
                    </table>
                </div>
                <p class="help-block text-muted" id="yuju-cleanup-cutoff-note"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-danger" id="yuju-cleanup-execute" disabled>
                    <i class="icon-trash"></i> Eliminar seleccionados
                </button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
(function () {
    var ajaxUrl = '{$cleanup_ajax_url|escape:'javascript':'UTF-8'}';
    var secToken = '{$cleanup_token|escape:'javascript':'UTF-8'}';

    function formatBytes(bytes) {
        bytes = parseInt(bytes, 10) || 0;
        if (bytes === 0) { return '0 B'; }
        var k = 1024, u = ['B', 'KB', 'MB', 'GB'], i = Math.floor(Math.log(bytes) / Math.log(k));
        return (bytes / Math.pow(k, i)).toFixed(2) + ' ' + u[i];
    }

    function loadCleanupStats() {
        var days = parseInt($('#yuju-cleanup-days').val(), 10) || 30;
        $('#yuju-cleanup-loading').show();
        $('#yuju-cleanup-error').hide().text('');
        $('#yuju-cleanup-table').hide();
        $('#yuju-cleanup-execute').prop('disabled', true);

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                ajax: 1,
                action: 'getCleanupStats',
                token: secToken,
                days: days
            },
            success: function (res) {
                $('#yuju-cleanup-loading').hide();
                if (!res || !res.success) {
                    $('#yuju-cleanup-error').text('No se pudieron cargar las estadísticas.').show();
                    return;
                }
                var tbody = $('#yuju-cleanup-tbody').empty();
                var totalDel = 0;
                (res.targets || []).forEach(function (t) {
                    var rowDel = parseInt(t.older_than_cutoff_count, 10) || 0;
                    var rowCount = parseInt(t.row_count, 10) || 0;
                    var canPurge = t.exists && rowCount > 0;
                    if (canPurge) {
                        totalDel += rowDel;
                    }
                    var disabled = (!t.exists || rowCount < 1) ? ' disabled' : '';
                    var checked = canPurge ? ' checked' : '';
                    var chk = '<input type="checkbox" class="yuju-cleanup-row-check" value="' + String(t.id).replace(/"/g, '&quot;') + '"' + checked + disabled + '>';
                    var last = t.last_record_at ? String(t.last_record_at) : '—';
                    var desc = t.description ? '<br><small class="text-muted">' + $('<div/>').text(t.description).html() + '</small>' : '';
                    tbody.append(
                        '<tr data-target-id="' + String(t.id).replace(/"/g, '&quot;') + '">' +
                        '<td class="text-center">' + chk + '</td>' +
                        '<td><strong>' + $('<div/>').text(t.label || '').html() + '</strong>' + desc + '</td>' +
                        '<td><code>' + $('<div/>').text(t.table_full || '').html() + '</code></td>' +
                        '<td class="text-right">' + (t.exists ? rowCount : '—') + '</td>' +
                        '<td class="text-right">' + (t.exists ? formatBytes(t.size_bytes) : '—') + '</td>' +
                        '<td><small>' + $('<div/>').text(last).html() + '</small></td>' +
                        '<td class="text-right text-danger"><strong>' + (t.exists ? rowDel : '—') + '</strong></td>' +
                        '</tr>'
                    );
                });
                $('#yuju-cleanup-cutoff-note').text(
                    'Umbral: registros anteriores a ' + (res.cutoff || '') +
                    ' (~' + (res.days || days) + ' días). Peso = tamaño total de la tabla en MySQL (aprox.).'
                );
                $('#yuju-cleanup-table').show();
                syncSelectAll();
            },
            error: function () {
                $('#yuju-cleanup-loading').hide();
                $('#yuju-cleanup-error').text('Error de conexión.').show();
            }
        });
    }

    function syncSelectAll() {
        var $rows = $('.yuju-cleanup-row-check:not(:disabled)');
        var $checked = $('.yuju-cleanup-row-check:not(:disabled):checked');
        $('#yuju-cleanup-check-all').prop('checked', $rows.length > 0 && $rows.length === $checked.length);
        var any = $('.yuju-cleanup-row-check:checked').length > 0;
        $('#yuju-cleanup-execute').prop('disabled', !any);
    }

    $(document).on('change', '.yuju-cleanup-row-check', function () { syncSelectAll(); });

    $('#yuju-cleanup-check-all').on('change', function () {
        var on = $(this).is(':checked');
        $('.yuju-cleanup-row-check:not(:disabled)').prop('checked', on);
    });

    $('#yuju-cleanup-refresh').on('click', function (e) {
        e.preventDefault();
        loadCleanupStats();
    });

    $('#yujuCleanupModal').on('shown.bs.modal', function () {
        loadCleanupStats();
    });

    $('#yuju-cleanup-execute').on('click', function () {
        var days = parseInt($('#yuju-cleanup-days').val(), 10) || 30;
        var targets = [];
        $('.yuju-cleanup-row-check:checked').each(function () {
            targets.push($(this).val());
        });
        if (targets.length === 0) {
            alert('Seleccione al menos una tabla con registros.');
            return;
        }
        if (!confirm('¿Eliminar definitivamente los registros seleccionados? Si no hay filas antiguas por umbral, se limpiará la tabla completa seleccionada. Esta acción no se puede deshacer.')) {
            return;
        }
        var $btn = $(this).prop('disabled', true);
        var fd = new FormData();
        fd.append('ajax', '1');
        fd.append('action', 'purgeCleanupData');
        fd.append('token', secToken);
        fd.append('days', String(days));
        targets.forEach(function (id) { fd.append('targets[]', id); });

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (res) {
                $btn.prop('disabled', false);
                if (res && res.success) {
                    var parts = (res.deleted_summary && res.deleted_summary.length)
                        ? res.deleted_summary
                        : [];
                    if (!parts.length && res.deleted) {
                        Object.keys(res.deleted).forEach(function (k) {
                            parts.push(k + ': ' + res.deleted[k]);
                        });
                    }
                    alert('Limpieza completada. Filas eliminadas por tabla:\n' + parts.join('\n'));
                    $('#yujuCleanupModal').modal('hide');
                    window.location.reload();
                } else {
                    alert((res && res.errors && res.errors.length) ? res.errors.join('\n') : 'Error al eliminar.');
                }
            },
            error: function () {
                $btn.prop('disabled', false);
                alert('Error de conexión al eliminar.');
            }
        });
    });
})();
</script>

<!-- Panel de Estadísticas de Cola -->
{if isset($queue_stats)}
<div class="panel">
    <div class="panel-heading">
        <i class="icon-list"></i> Estado de Cola de Sincronización
    </div>
    <div class="panel-body">
        <div class="row">
        </div>
        <div class="row text-center">
            <div class="col-md-2">
                <div class="panel" style="border: 2px solid #ddd;">
                    <div class="panel-body">
                        <h3 style="margin: 0; color: #666;">
                            <i class="icon-database"></i> {$queue_stats.total}
                        </h3>
                        <p style="margin: 5px 0 0 0; color: #999;">Total en Cola</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="panel" style="border: 2px solid #f0ad4e;">
                    <div class="panel-body">
                        <h3 style="margin: 0; color: #f0ad4e;">
                            <i class="icon-clock-o"></i> {$queue_stats.pending}
                        </h3>
                        <p style="margin: 5px 0 0 0; color: #999;">Pendientes</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="panel" style="border: 2px solid #5bc0de;">
                    <div class="panel-body">
                        <h3 style="margin: 0; color: #5bc0de;">
                            <i class="icon-refresh icon-spin"></i> {$queue_stats.processing}
                        </h3>
                        <p style="margin: 5px 0 0 0; color: #999;">Procesando</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="panel" style="border: 2px solid #5cb85c;">
                    <div class="panel-body">
                        <h3 style="margin: 0; color: #5cb85c;">
                            <i class="icon-check"></i> {$queue_stats.completed}
                        </h3>
                        <p style="margin: 5px 0 0 0; color: #999;">Completados</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="panel" style="border: 2px solid #d9534f;">
                    <div class="panel-body">
                        <h3 style="margin: 0; color: #d9534f;">
                            <i class="icon-exclamation-triangle"></i> {$queue_stats.failed}
                        </h3>
                        <p style="margin: 5px 0 0 0; color: #999;">Fallidos</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="panel" style="border: 2px solid #5bc0de;">
                    <div class="panel-body">
                        <h3 style="margin: 0; color: #5bc0de;">
                            <i class="icon-list"></i> {$queue_stats.queued_products}
                        </h3>
                        <p style="margin: 5px 0 0 0; color: #999;">Productos en Cola</p>
                    </div>
                </div>
            </div>
        </div>
        {if $queue_stats.pending > 0}
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-warning">
                    <i class="icon-info-circle"></i> 
                    <strong>Próximo procesamiento:</strong> {$queue_stats.pending} productos serán procesados en el próximo ciclo del cron (cada 5 minutos).
                    <br>
                    <small>Los cambios de <strong>precio y stock</strong> se sincronizan inmediatamente sin pasar por la cola.</small>
                </div>
            </div>
        </div>
        {/if}
    </div>
</div>
{/if}

{if isset($sync_history) && count($sync_history) > 0}
<div class="panel">
    <div class="panel-heading">
        <i class="icon-history"></i>
        Últimos 100 intentos de envío a Yuju (historial)
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table table-striped table-bordered table-condensed">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>ID PS</th>
                        <th>ID Yuju</th>
                        <th>Acción</th>
                        <th>Estado</th>
                        <th>HTTP</th>
                        <th>Mensaje / error</th>
                        <th>Origen</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach from=$sync_history item=row}
                        <tr class="{if $row.status == 'error'}danger{elseif $row.status == 'success' || $row.status == 'synced'}success{elseif $row.status == 'warning' || $row.status == 'synced_with_warnings'}warning{/if}">
                            <td>{$row.created_at|escape:'html':'UTF-8'}</td>
                            <td>{$row.prestashop_product_id|intval}</td>
                            <td>{$row.yuju_product_id|default:'—'|escape:'html':'UTF-8'}</td>
                            <td>{$row.action|escape:'html':'UTF-8'}</td>
                            <td>{$row.status|default:'—'|escape:'html':'UTF-8'}</td>
                            <td>{$row.http_status_code|default:'—'|escape:'html':'UTF-8'}</td>
                            <td style="white-space: pre-wrap; word-break: break-word; max-width: 420px;">{$row.error_message|default:'—'|escape:'html':'UTF-8'}</td>
                            <td>{$row.created_by|default:'—'|escape:'html':'UTF-8'}</td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>
        <p class="help-block">
            Estos registros provienen de <code>{$db_prefix|escape:'html':'UTF-8'}yuju_product_sync_history</code> e incluyen intentos correctos y fallidos.
            Si esta tabla no existe o está vacía, no se mostrarán filas.
        </p>
    </div>
</div>
{/if}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-file-text"></i>
        Registros de Integración Yuju
    </div>
    <div class="panel-body">
        {if $logs && count($logs) > 0}
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Archivo de Registro</th>
                            <th>Tamaño</th>
                            <th>Última Modificación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$logs item=log}
                            <tr>
                                <td>{$log.filename|escape:'html':'UTF-8'}</td>
                                <td>{($log.size/1024)|string_format:"%.2f"|escape:'html':'UTF-8'} KB</td>
                                <td>{$log.modified|date_format:"%Y-%m-%d %H:%M:%S"|escape:'html':'UTF-8'}</td>
                                <td>
                                    <a href="{$module_dir|escape:'html':'UTF-8'}logs/{$log.filename|escape:'html':'UTF-8'}" target="_blank" class="btn btn-sm btn-default">
                                        <i class="icon-eye"></i> Ver
                                    </a>
                                    <a href="{$module_dir|escape:'html':'UTF-8'}logs/{$log.filename|escape:'html':'UTF-8'}" download class="btn btn-sm btn-primary">
                                        <i class="icon-download"></i> Descargar
                                    </a>
                                </td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        {else}
            <div class="alert alert-info">
                <h4>No se encontraron registros</h4>
                <p>Aún no se han creado archivos de registro. Los registros aparecerán aquí una vez que comiencen las actividades de sincronización.</p>
            </div>
        {/if}
        
    </div>
</div>
{/block}