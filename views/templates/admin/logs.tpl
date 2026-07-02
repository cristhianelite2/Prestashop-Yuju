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
<div class="panel panel-warning">
    <div class="panel-heading">
        <i class="icon-database"></i> Limpieza de datos históricos (solo tablas de log / auditoría)
    </div>
    <div class="panel-body">
        <p class="help-block">
            Permite borrar registros antiguos que <strong>no</strong> son necesarios para el funcionamiento del módulo
            (historial de envíos, logs de sync, webhooks, cola ya terminada, etc.).
            No modifica mapeos, tokens OAuth ni estados de productos.
        </p>
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
            <div class="col-md-12">
                <div class="alert alert-info">
                    <i class="icon-info-circle"></i> 
                    <strong>Sistema de Lotes Activo:</strong> Los cambios en productos (excepto precio/stock) se procesan por lotes cada 5 minutos.
                </div>
            </div>
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

{if isset($sync_history_errors) && count($sync_history_errors) > 0}
<div class="panel">
    <div class="panel-heading">
        <i class="icon-exclamation-triangle"></i>
        Últimos errores de envío a Yuju (historial)
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>ID PS</th>
                        <th>ID Yuju</th>
                        <th>Acción</th>
                        <th>HTTP</th>
                        <th>Mensaje</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach from=$sync_history_errors item=row}
                        <tr>
                            <td>{$row.created_at|escape:'html':'UTF-8'}</td>
                            <td>{$row.prestashop_product_id|intval}</td>
                            <td>{$row.yuju_product_id|default:'—'|escape:'html':'UTF-8'}</td>
                            <td>{$row.action|escape:'html':'UTF-8'}</td>
                            <td>{$row.http_status_code|default:'—'|escape:'html':'UTF-8'}</td>
                            <td style="white-space: pre-wrap; word-break: break-word; max-width: 420px;">{$row.error_message|escape:'html':'UTF-8'}</td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>
        <p class="help-block">
            Estos registros provienen de la tabla <code>{$db_prefix|escape:'html':'UTF-8'}yuju_product_sync_history</code>.
            Los envíos correctos no aparecen aquí.
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
        
        <div class="panel panel-default">
            <div class="panel-heading">
                <h4>Información de Registros</h4>
            </div>
            <div class="panel-body">
                <p>Los archivos de registro se crean automáticamente durante los procesos de sincronización. Contienen información detallada sobre:</p>
                <ul>
                    <li>Solicitudes y respuestas de API</li>
                    <li>Resultados de sincronización</li>
                    <li>Mensajes de error e información de depuración</li>
                    <li>Métricas de rendimiento</li>
                </ul>
                
                <div class="alert alert-warning">
                    <strong>Nota:</strong>
                    Los archivos de registro se rotan automáticamente y los archivos antiguos se eliminan según su configuración.
                </div>
            </div>
        </div>
    </div>
</div>
{/block}