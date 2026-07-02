{*
* Vista: acciones masivas y salud de sincronización por categoría PrestaShop
*}
{extends file="./layout.tpl"}

{block name="content"}
<div class="panel yuju-cat-bulk">
    <div class="panel-heading">
        <i class="icon-sitemap"></i>
        Acciones masivas por categoría (Yuju)
    </div>
    <div class="panel-body">
        {if $yuju_sync_history_table_missing}
            <div class="alert alert-warning" id="yuju-cat-bulk-infra-alert">
                <strong>Infraestructura incompleta:</strong> falta la tabla <code>{$yuju_sync_history_table_name|escape:'html':'UTF-8'}</code>.
                <br>
                Esta tabla se usa para registrar y auditar envíos masivos a Yuju.
                <div style="margin-top:8px;">
                    <button type="button" class="btn btn-warning btn-sm" id="yuju-cat-bulk-fix-table-btn">
                        <i class="icon-wrench"></i> Crear / corregir tabla
                    </button>
                    <span class="text-muted" id="yuju-cat-bulk-fix-table-msg" style="margin-left:10px;"></span>
                </div>
            </div>
        {/if}

        {if $id_category && $detail}
            <div class="yuju-cat-bulk__toolbar">
                {if !$detail.mapping_covered_by_self_or_parent}
                    <button type="button" class="btn btn-primary" id="yuju-open-inline-map-modal">
                        <i class="icon-sitemap"></i> Mapearlo
                    </button>
                    <span class="text-muted yuju-cat-bulk__toolbar-help">Esta categoría no está mapeada en Yuju (ni por padre).</span>
                {elseif $detail.mapping_covered_by_ancestor}
                    <span class="label label-info">Hereda mapeo de categoría padre (ID {$detail.mapping_covered_category_id|intval})</span>
                {else}
                    <span class="label label-success">Categoría ya mapeada en Yuju</span>
                {/if}
            </div>

            <div class="yuju-cat-bulk__context">
                <div class="yuju-cat-bulk__context-main">
                    <div class="yuju-cat-bulk__context-kicker">Categoría seleccionada</div>
                    <span class="yuju-cat-bulk__title">{$detail.name|escape:'html':'UTF-8'}</span>
                    <p class="yuju-cat-bulk__context-help">Gestione envíos, pendientes y errores de esta categoría.</p>
                </div>
                <div class="yuju-cat-bulk__context-side">
                    {if $detail.mapping}
                        <div class="yuju-cat-bulk__map-card yuju-cat-bulk__map-card--ok">
                            <div class="yuju-cat-bulk__map-title"><i class="icon-link"></i> Mapeo Yuju</div>
                            <div class="yuju-cat-bulk__map-text"><strong>{$detail.mapping.yuju_category_name|escape:'html':'UTF-8'}</strong></div>
                            {if isset($detail.mapping.sync_enabled) && !$detail.mapping.sync_enabled}
                                <span class="label label-warning">Sync desactivada</span>
                            {else}
                                <span class="label label-success">Sync habilitada</span>
                            {/if}
                        </div>
                    {else}
                        <div class="yuju-cat-bulk__map-card yuju-cat-bulk__map-card--warn">
                            <div class="yuju-cat-bulk__map-title"><i class="icon-warning-sign"></i> Sin mapeo Yuju</div>
                            <div class="yuju-cat-bulk__map-text">No hay mapeo para esta categoría.</div>
                            <span class="label label-warning">Revisar mapeo</span>
                        </div>
                    {/if}
                </div>
            </div>

            {if !$detail.mapping_covered_by_self_or_parent}
            <div class="modal fade" id="yuju-inline-map-modal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                            <h4 class="modal-title"><i class="icon-sitemap"></i> Mapeo de Categoría</h4>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label>Categoría PrestaShop</label>
                                <input type="text" class="form-control" value="{$detail.name|escape:'html':'UTF-8'} (ID {$id_category|intval})" readonly>
                            </div>
                            <div class="form-group">
                                <label for="yuju-inline-map-select">Categoría Yuju</label>
                                <input type="text" id="yuju-inline-map-search" class="form-control" placeholder="Buscar categoría Yuju..." style="margin-bottom:8px;">
                                <select id="yuju-inline-map-select" class="form-control">
                                    <option value="">Seleccione...</option>
                                    {foreach from=$yuju_category_options item=catopt}
                                        <option value="{$catopt.id|escape:'html':'UTF-8'}">{$catopt.name|escape:'html':'UTF-8'} (ID {$catopt.id|escape:'html':'UTF-8'})</option>
                                    {/foreach}
                                </select>
                            </div>
                            <div id="yuju-inline-map-msg" class="text-muted"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" id="yuju-inline-map-save-btn">
                                <i class="icon-save"></i> Guardar mapeo
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            {/if}

            <div class="row yuju-cat-bulk__kpis">
                <div class="col-md-3 col-sm-6">
                    <div class="yuju-kpi yuju-kpi--total">
                        <div class="yuju-kpi__value">{$detail.stats.total|intval}</div>
                        <div class="yuju-kpi__label">Productos activos en categoría</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="yuju-kpi yuju-kpi--ok">
                        <div class="yuju-kpi__value">{$detail.stats.cnt_synced_ok|intval}</div>
                        <div class="yuju-kpi__label">En Yuju (OK)</div>
                        <div class="yuju-kpi__hint">{$detail.stats.pct_uploaded_ok|intval}% del total</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="yuju-kpi yuju-kpi--pending">
                        <div class="yuju-kpi__value">{$detail.stats.cnt_pending|intval}</div>
                        <div class="yuju-kpi__label">Pendientes / sin ID Yuju</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="yuju-kpi yuju-kpi--err">
                        <div class="yuju-kpi__value">{$detail.stats.cnt_errors|intval}</div>
                        <div class="yuju-kpi__label">Con error</div>
                        {if $detail.stats.cnt_errors|intval > 0}
                            <button type="button" class="btn btn-link btn-xs yuju-kpi__detail-btn" id="yuju-kpi-errors-detail-btn">
                                Ver detalles
                            </button>
                        {/if}
                    </div>
                </div>
            </div>

            <div class="modal fade" id="yuju-kpi-errors-modal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                            <h4 class="modal-title"><i class="icon-warning-sign"></i> Productos con error</h4>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted" id="yuju-kpi-errors-summary"></p>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Referencia</th>
                                            <th>Nombre</th>
                                            <th>Tipo de error</th>
                                            <th>Detalle</th>
                                        </tr>
                                    </thead>
                                    <tbody id="yuju-kpi-errors-tbody">
                                        <tr><td colspan="5" class="text-muted">Sin datos.</td></tr>
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

            <div class="row yuju-cat-bulk__kpis yuju-cat-bulk__kpis--secondary">
                <div class="col-md-4 col-sm-6">
                    <div class="yuju-kpi yuju-kpi--muted">
                        <div class="yuju-kpi__value">{$detail.stats.cnt_in_progress|intval}</div>
                        <div class="yuju-kpi__label">En proceso (API / webhook)</div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="yuju-kpi yuju-kpi--muted">
                        <div class="yuju-kpi__value">{$detail.stats.cnt_invalid|intval}</div>
                        <div class="yuju-kpi__label">No enviables (sin ref. o nombre)</div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="yuju-kpi yuju-kpi--muted">
                        <div class="yuju-kpi__value">{$detail.stats.cnt_orphan_state|intval}</div>
                        <div class="yuju-kpi__label">Estado “sincronizado” sin ID Yuju</div>
                    </div>
                </div>
            </div>

            <div class="yuju-cat-bulk__bar-wrap">
                <div class="yuju-cat-bulk__bar-title">Distribución aproximada</div>
                <div class="yuju-cat-bulk__bar">
                    {if $detail.stats.total > 0}
                        <span class="yuju-cat-bulk__seg yuju-cat-bulk__seg--ok" style="width:{$detail.bar_pct.ok|string_format:"%.2f"}%;" title="OK"></span>
                        <span class="yuju-cat-bulk__seg yuju-cat-bulk__seg--pending" style="width:{$detail.bar_pct.pending|string_format:"%.2f"}%;" title="Pendientes"></span>
                        <span class="yuju-cat-bulk__seg yuju-cat-bulk__seg--err" style="width:{$detail.bar_pct.err|string_format:"%.2f"}%;" title="Errores"></span>
                        <span class="yuju-cat-bulk__seg yuju-cat-bulk__seg--prog" style="width:{$detail.bar_pct.prog|string_format:"%.2f"}%;" title="En proceso"></span>
                        <span class="yuju-cat-bulk__seg yuju-cat-bulk__seg--inv" style="width:{$detail.bar_pct.inv|string_format:"%.2f"}%;" title="No enviables"></span>
                    {else}
                        <span class="yuju-cat-bulk__seg yuju-cat-bulk__seg--empty" style="width:100%;"></span>
                    {/if}
                </div>
                <div class="yuju-cat-bulk__legend">
                    <span><i class="yuju-dot yuju-dot--ok"></i> OK</span>
                    <span><i class="yuju-dot yuju-dot--pending"></i> Pendientes</span>
                    <span><i class="yuju-dot yuju-dot--err"></i> Errores</span>
                    <span><i class="yuju-dot yuju-dot--prog"></i> En proceso</span>
                    <span><i class="yuju-dot yuju-dot--inv"></i> No enviables</span>
                </div>
            </div>

            <div class="yuju-cat-bulk__segments">
                <span class="yuju-cat-bulk__segments-label">Ver listado:</span>
                <div class="btn-group" role="group" id="yuju-cat-segments" data-id-category="{$id_category|intval}">
                    <button type="button" class="btn btn-default btn-sm active" data-segment="all">Todos</button>
                    <button type="button" class="btn btn-default btn-sm" data-segment="errors">Solo errores</button>
                    <button type="button" class="btn btn-default btn-sm" data-segment="pending">Pendientes / cola</button>
                    <button type="button" class="btn btn-default btn-sm" data-segment="in_progress">En proceso</button>
                    <button type="button" class="btn btn-default btn-sm" data-segment="synced_ok">Ya en Yuju (OK)</button>
                    <button type="button" class="btn btn-default btn-sm" data-segment="invalid">No enviables</button>
                    <button type="button" class="btn btn-default btn-sm" data-segment="orphan_synced">Incoherencias (sin ID)</button>
                    <button type="button" class="btn btn-default btn-sm" data-segment="disabled">Deshabilitados</button>
                </div>
            </div>

            <div class="yuju-cat-bulk__bulk-bar well well-sm">
                <strong>Acciones sobre el segmento activo</strong>
                <span class="help-block" style="margin:4px 0 8px;">Se procesa por lotes en el navegador; no cierre la pestaña hasta finalizar.</span>
                <button type="button" class="btn btn-primary btn-sm yuju-cat-bulk-act" data-action="create"><i class="icon-cloud-upload"></i> Crear / encolar en Yuju</button>
                <button type="button" class="btn btn-default btn-sm yuju-cat-bulk-act" data-action="update"><i class="icon-refresh"></i> Actualizar en Yuju</button>
                <button type="button" class="btn btn-danger btn-sm yuju-cat-bulk-act" data-action="delete"><i class="icon-trash"></i> Eliminar de Yuju</button>
                <span id="yuju-cat-bulk-progress" class="text-muted" style="margin-left:12px;"></span>
            </div>

            <div id="yuju-cat-bulk-table-wrap">
                <table class="table table-bordered table-striped" id="yuju-cat-bulk-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Referencia</th>
                            <th>Nombre</th>
                            <th>Estado</th>
                            <th>ID Yuju</th>
                            <th>Última sync</th>
                        </tr>
                    </thead>
                    <tbody id="yuju-cat-bulk-tbody"></tbody>
                </table>
                <div id="yuju-cat-bulk-pager" class="clearfix"></div>
            </div>

            <div class="modal fade" id="yuju-product-error-modal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                            <h4 class="modal-title"><i class="icon-warning-sign"></i> Detalle de error del producto</h4>
                        </div>
                        <div class="modal-body">
                            <p><strong>ID:</strong> <span id="yuju-product-error-id">-</span></p>
                            <p><strong>Referencia:</strong> <span id="yuju-product-error-ref">-</span></p>
                            <p><strong>Nombre:</strong> <span id="yuju-product-error-name">-</span></p>
                            <hr style="margin:10px 0;">
                            <div><strong>Qué significa:</strong></div>
                            <div id="yuju-product-error-meaning" class="alert alert-info" style="margin-top:6px; margin-bottom:8px;">Sin diagnóstico.</div>
                            <div><strong>Cómo corregirlo:</strong></div>
                            <div id="yuju-product-error-fix" class="alert alert-warning" style="margin-top:6px; margin-bottom:8px;">Sin recomendación.</div>
                            <div><strong>Error:</strong></div>
                            <div id="yuju-product-error-text" class="well well-sm" style="white-space:pre-wrap; max-height:250px; overflow:auto; margin-top:6px;">Sin detalle</div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="yuju-cat-bulk-result-modal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                            <h4 class="modal-title"><i class="icon-tasks"></i> Resultado del proceso masivo</h4>
                        </div>
                        <div class="modal-body">
                            <div id="yuju-cat-bulk-result-summary" class="alert alert-info" style="margin-bottom:10px;"></div>
                            <div id="yuju-cat-bulk-result-extra" class="text-muted" style="margin-bottom:10px;"></div>
                            <div class="table-responsive" id="yuju-cat-bulk-result-errors-wrap" style="display:none;">
                                <table class="table table-bordered table-striped table-condensed">
                                    <thead>
                                        <tr>
                                            <th style="width:120px;">Producto</th>
                                            <th>Detalle del error</th>
                                        </tr>
                                    </thead>
                                    <tbody id="yuju-cat-bulk-result-errors-tbody"></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>

        {elseif $id_category && !$detail}
            <div class="alert alert-danger">Categoría no encontrada o inactiva.</div>
            <a href="{$ajax_url|escape:'html':'UTF-8'}&amp;token={$token|escape:'html':'UTF-8'}" class="btn btn-default">Volver</a>
        {else}
            <form method="get" class="form-inline yuju-cat-bulk__search" action="{$ajax_url|escape:'html':'UTF-8'}">
                <input type="hidden" name="controller" value="AdminYujuCategoryBulk" />
                <input type="hidden" name="token" value="{$token|escape:'html':'UTF-8'}" />
                <div class="form-group">
                    <label for="yuju_cat_q">Buscar categoría</label>
                    <input type="text" name="q" id="yuju_cat_q" class="form-control" value="{$search_q|escape:'html':'UTF-8'}" placeholder="Nombre…" />
                </div>
                <button type="submit" class="btn btn-primary">Buscar</button>
            </form>

            <p class="text-muted">Mostrando hasta 400 categorías con productos activos, ordenadas por volumen.</p>

            <div class="table-responsive">
                <table class="table table-bordered table-hover yuju-cat-bulk__overview">
                    <thead>
                        <tr>
                            <th>Categoría</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">En Yuju OK</th>
                            <th class="text-center">Pendientes</th>
                            <th class="text-center">Errores</th>
                            <th class="text-center">En proceso</th>
                            <th class="text-center">No env.</th>
                            <th class="text-center">Mapeo Yuju</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {if $overview_rows && $overview_rows|@count > 0}
                            {foreach from=$overview_rows item=row}
                            <tr>
                                <td>{$row.category_name|escape:'html':'UTF-8'}</td>
                                <td class="text-center"><span class="badge">{$row.total|intval}</span></td>
                                <td class="text-center text-success">{$row.cnt_synced_ok|intval}</td>
                                <td class="text-center">{$row.cnt_pending|intval}</td>
                                <td class="text-center text-danger">{$row.cnt_errors|intval}</td>
                                <td class="text-center">{$row.cnt_in_progress|intval}</td>
                                <td class="text-center">{$row.cnt_invalid|intval}</td>
                                <td class="text-center">
                                    {if $row.mapped_yuju}
                                        <span class="label label-success">Sí</span>
                                    {else}
                                        <span class="label label-default">No</span>
                                    {/if}
                                </td>
                                <td>
                                    <a class="btn btn-default btn-xs" href="{$ajax_url|escape:'html':'UTF-8'}&amp;token={$token|escape:'html':'UTF-8'}&amp;id_category={$row.id_category|intval}">
                                        Gestionar
                                    </a>
                                </td>
                            </tr>
                            {/foreach}
                        {else}
                            <tr><td colspan="9">No hay categorías con productos activos que coincidan.</td></tr>
                        {/if}
                    </tbody>
                </table>
            </div>
        {/if}
    </div>
</div>

{if $id_category && $detail}
<div id="yuju-cat-bulk-config" class="hidden" style="display:none"
    data-ajax-url="{$ajax_url|escape:'html':'UTF-8'}"
    data-token="{$token|escape:'html':'UTF-8'}"
    data-id-category="{$id_category|intval}"
    data-logs-url="{$yuju_logs_admin_url|escape:'html':'UTF-8'}"></div>
<script type="text/javascript">
{literal}
(function ($) {
    var cfg = $('#yuju-cat-bulk-config');
    var ajaxUrl = cfg.data('ajaxUrl') || cfg.attr('data-ajax-url');
    var token = cfg.data('token');
    var idCategory = parseInt(cfg.data('idCategory') || cfg.attr('data-id-category'), 10) || 0;
    var logsUrl = cfg.data('logsUrl') || cfg.attr('data-logs-url');
    var currentSegment = 'all';
    var currentPage = 1;
    var perPage = 25;

    function segmentLabel(seg) {
        var m = { all: 'todos', errors: 'errores', pending: 'pendientes', in_progress: 'en proceso',
            synced_ok: 'OK en Yuju', invalid: 'no enviables', orphan_synced: 'incoherencias', disabled: 'deshabilitados' };
        return m[seg] || seg;
    }

    function hasYujuId(p) {
        var y = String(p && p.yuju_product_id ? p.yuju_product_id : '').trim();
        return y !== '' && y !== '0' && y.toLowerCase() !== 'null';
    }

    function renderStatusCell(p) {
        var st = String(p && p.yuju_status ? p.yuju_status : 'pending');
        var err = String(p && p.last_error ? p.last_error : '').trim();
        var hasId = hasYujuId(p);

        if (st === 'error') {
            var txt = hasId ? 'creado en Yuju (con error)' : 'error';
            if (err !== '') {
                return '<a href="#" class="yuju-error-link label label-danger"'
                    + ' data-id="' + parseInt(p.id_product, 10) + '"'
                    + ' data-ref="' + escapeHtml(p.reference || '') + '"'
                    + ' data-name="' + escapeHtml(p.name || '') + '"'
                    + ' data-error="' + escapeHtml(err) + '">'
                    + escapeHtml(txt) + '</a>';
            }
            return '<span class="label label-danger">' + escapeHtml(txt) + '</span>';
        }
        if (st === 'synced_with_errors') {
            return '<span class="label label-warning">sincronizado con errores</span>';
        }
        if (st === 'synced' || st === 'synced_with_warnings') {
            return '<span class="label label-success">sincronizado</span>';
        }
        if (st === 'queued' || st === 'pending') {
            return '<span class="label label-default">pendiente</span>';
        }
        if (st === 'creating_in_yuju' || st === 'updating_in_yuju' || st === 'deleting_in_yuju' || st === 'syncing') {
            return '<span class="label label-info">en proceso</span>';
        }
        if (st === 'disabled') {
            return '<span class="label label-default">deshabilitado</span>';
        }
        return '<span class="label label-default">' + escapeHtml(st || '—') + '</span>';
    }

    function explainSyncError(errText) {
        var raw = String(errText || '').trim();
        var e = raw.toLowerCase();
        if (!e) {
            return {
                meaning: 'No se recibió detalle técnico del error.',
                fix: 'Revise los logs y reintente. Si persiste, vuelva a enviar el producto para capturar un mensaje más detallado.'
            };
        }

        if (e.indexOf('sku simple is not editable') !== -1) {
            return {
                meaning: 'Este producto ya existe en Yuju y su campo "SKU simple" quedó bloqueado por el marketplace. Por eso la actualización fue rechazada aunque el HTTP sea 200.',
                fix: 'Qué hacer: 1) No cambie SKU/referencia de este producto después de creado. 2) Reintente la actualización solo con precio/stock/descripción. 3) Si necesita cambiar SKU, elimine el producto en Yuju y créelo nuevamente con el SKU correcto. Prevención: mantenga SKU estable desde el primer envío.'
            };
        }
        if (e.indexOf('duplic') !== -1 && (e.indexOf('sku') !== -1 || e.indexOf('reference') !== -1)) {
            return {
                meaning: 'Hay SKU/referencias repetidas entre productos.',
                fix: 'Asegure que cada producto tenga una referencia única en PrestaShop antes de enviar.'
            };
        }
        if (e.indexOf('no está mapeada') !== -1 || (e.indexOf('categoria') !== -1 && e.indexOf('mape') !== -1)) {
            return {
                meaning: 'La categoría de PrestaShop no tiene relación válida hacia una categoría de Yuju.',
                fix: 'Abra "Mapearlo" y asigne la categoría Yuju correspondiente. Luego reintente el envío.'
            };
        }
        if (e.indexOf('required') !== -1 || e.indexOf('obligatorio') !== -1 || e.indexOf('validation') !== -1) {
            return {
                meaning: 'Faltan campos obligatorios o un valor no cumple validación.',
                fix: 'Complete los atributos requeridos (marca, categoría, precio, stock, etc.) y vuelva a sincronizar.'
            };
        }
        if (e.indexOf('timeout') !== -1 || e.indexOf('curl') !== -1 || e.indexOf('http') !== -1) {
            return {
                meaning: 'Fallo de comunicación con la API (conectividad o tiempo de respuesta).',
                fix: 'Reintente en unos minutos. Si se repite, revise conectividad del servidor y credenciales de API.'
            };
        }

        return {
            meaning: 'Se produjo un error durante la sincronización con Yuju.',
            fix: 'Revise el detalle técnico y logs. Corrija el dato del producto y vuelva a intentar.'
        };
    }

    function loadProducts() {
        $('#yuju-cat-bulk-tbody').html('<tr><td colspan="6"><i class="icon-refresh icon-spin"></i> Cargando…</td></tr>');
        $.post(ajaxUrl, {
            ajax: 1,
            action: 'categoryBulkProducts',
            token: token,
            id_category: idCategory,
            segment: currentSegment,
            page: currentPage,
            per_page: perPage
        }).done(function (res) {
            if (!res || !res.success) {
                $('#yuju-cat-bulk-tbody').html('<tr><td colspan="6">Error al cargar.</td></tr>');
                return;
            }
            var rows = res.products || [];
            var html = '';
            if (!rows.length) {
                html = '<tr><td colspan="6">No hay productos en este segmento.</td></tr>';
            } else {
                rows.forEach(function (p) {
                    var yid = p.yuju_product_id ? String(p.yuju_product_id) : '—';
                    html += '<tr><td>' + parseInt(p.id_product, 10) + '</td><td>' + escapeHtml(p.reference || '') + '</td><td>' + escapeHtml(p.name || '') + '</td><td>' + renderStatusCell(p) + '</td><td>' + escapeHtml(yid) + '</td><td>' + escapeHtml((p.last_sync_at || '').toString().slice(0, 19)) + '</td></tr>';
                });
            }
            $('#yuju-cat-bulk-tbody').html(html);
            renderPager(res.pagination || {});
        }).fail(function () {
            $('#yuju-cat-bulk-tbody').html('<tr><td colspan="6">Error de red.</td></tr>');
        });
    }

    $('#yuju-cat-bulk-tbody').on('click', '.yuju-error-link', function (e) {
        e.preventDefault();
        var detail = $(this).data('error') || '';
        var exp = explainSyncError(detail);
        $('#yuju-product-error-id').text($(this).data('id') || '-');
        $('#yuju-product-error-ref').text($(this).data('ref') || '-');
        $('#yuju-product-error-name').text($(this).data('name') || '-');
        $('#yuju-product-error-meaning').text(exp.meaning || 'Sin diagnóstico.');
        $('#yuju-product-error-fix').text(exp.fix || 'Sin recomendación.');
        $('#yuju-product-error-text').text(detail || 'Sin detalle');
        $('#yuju-product-error-modal').modal('show');
    });

    function renderPager(p) {
        var total = p.total || 0;
        var pages = p.total_pages || 1;
        var pg = p.current_page || 1;
        if (total === 0) {
            $('#yuju-cat-bulk-pager').html('');
            return;
        }
        var h = '<div class="pull-left text-muted">' + (p.from || 0) + '–' + (p.to || 0) + ' de ' + total + '</div>';
        h += '<ul class="pagination pagination-sm pull-right">';
        if (pg > 1) {
            h += '<li><a href="#" data-page="' + (pg - 1) + '">&laquo;</a></li>';
        }
        h += '<li class="disabled"><span>' + pg + ' / ' + pages + '</span></li>';
        if (pg < pages) {
            h += '<li><a href="#" data-page="' + (pg + 1) + '">&raquo;</a></li>';
        }
        h += '</ul>';
        $('#yuju-cat-bulk-pager').html(h);
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    $('#yuju-cat-segments button').on('click', function () {
        $('#yuju-cat-segments button').removeClass('active');
        $(this).addClass('active');
        currentSegment = $(this).data('segment');
        currentPage = 1;
        loadProducts();
    });

    $('#yuju-cat-bulk-pager').on('click', 'a[data-page]', function (e) {
        e.preventDefault();
        currentPage = parseInt($(this).data('page'), 10) || 1;
        loadProducts();
    });

    $('#yuju-cat-bulk-fix-table-btn').on('click', function () {
        var $btn = $(this);
        var $msg = $('#yuju-cat-bulk-fix-table-msg');
        if (!$btn.length) {
            return;
        }
        $btn.prop('disabled', true);
        $msg.text('Creando/corrigiendo infraestructura…');
        $.post(ajaxUrl, {
            ajax: 1,
            action: 'createSyncHistoryTable',
            token: token
        }).done(function (res) {
            if (res && res.success) {
                $msg.text(res.message || 'Listo. Recargando…');
                window.setTimeout(function () {
                    window.location.reload();
                }, 700);
                return;
            }
            $btn.prop('disabled', false);
            $msg.text((res && res.message) ? res.message : 'No se pudo crear/corregir la tabla.');
        }).fail(function () {
            $btn.prop('disabled', false);
            $msg.text('Error de red al crear/corregir la tabla.');
        });
    });

    $('#yuju-open-inline-map-modal').on('click', function () {
        $('#yuju-inline-map-msg').removeClass('text-danger text-success').addClass('text-muted').text('');
        $('#yuju-inline-map-search').val('');
        var $sel = $('#yuju-inline-map-select');
        $sel.find('option').show();
        $('#yuju-inline-map-select').val('');
        $('#yuju-inline-map-modal').modal('show');
    });

    $('#yuju-inline-map-search').on('input', function () {
        var q = String($(this).val() || '').toLowerCase().trim();
        var $sel = $('#yuju-inline-map-select');
        var current = $sel.val();
        $sel.find('option').each(function () {
            var $opt = $(this);
            var v = String($opt.attr('value') || '');
            if (v === '') {
                $opt.show();
                return;
            }
            var txt = String($opt.text() || '').toLowerCase();
            $opt.toggle(q === '' || txt.indexOf(q) !== -1);
        });
        if (current && !$sel.find('option[value="' + current.replace(/"/g, '\\"') + '"]:visible').length) {
            $sel.val('');
        }
    });

    $('#yuju-inline-map-save-btn').on('click', function () {
        var yujuId = $('#yuju-inline-map-select').val();
        var $msg = $('#yuju-inline-map-msg');
        var $btn = $(this);
        if (!yujuId) {
            $msg.removeClass('text-muted text-success').addClass('text-danger').text('Seleccione una categoría Yuju.');
            return;
        }
        $btn.prop('disabled', true);
        $msg.removeClass('text-danger text-success').addClass('text-muted').text('Guardando mapeo...');
        $.post(ajaxUrl, {
            ajax: 1,
            action: 'saveInlineCategoryMapping',
            token: token,
            prestashop_category_id: idCategory,
            yuju_category_id: yujuId
        }).done(function (res) {
            if (res && res.success) {
                $msg.removeClass('text-muted text-danger').addClass('text-success').text(res.message || 'Mapeo guardado.');
                window.setTimeout(function () {
                    window.location.reload();
                }, 600);
                return;
            }
            $btn.prop('disabled', false);
            $msg.removeClass('text-muted text-success').addClass('text-danger').text((res && res.message) ? res.message : 'No se pudo guardar el mapeo.');
        }).fail(function () {
            $btn.prop('disabled', false);
            $msg.removeClass('text-muted text-success').addClass('text-danger').text('Error de red al guardar el mapeo.');
        });
    });

    $('#yuju-kpi-errors-detail-btn').on('click', function () {
        var $tbody = $('#yuju-kpi-errors-tbody');
        $('#yuju-kpi-errors-summary').text('Cargando errores...');
        $tbody.html('<tr><td colspan="5"><i class="icon-refresh icon-spin"></i> Cargando...</td></tr>');
        $('#yuju-kpi-errors-modal').modal('show');
        $.post(ajaxUrl, {
            ajax: 1,
            action: 'categoryBulkErrorDetails',
            token: token,
            id_category: idCategory
        }).done(function (res) {
            if (!res || !res.success) {
                $('#yuju-kpi-errors-summary').text('No se pudieron cargar los detalles.');
                $tbody.html('<tr><td colspan="5">Error al consultar productos con error.</td></tr>');
                return;
            }
            var rows = res.products || [];
            $('#yuju-kpi-errors-summary').text('Total con error: ' + (res.total || rows.length));
            if (!rows.length) {
                $tbody.html('<tr><td colspan="5" class="text-muted">No hay productos con error.</td></tr>');
                return;
            }
            var html = '';
            rows.forEach(function (p) {
                var err = (p.last_error || '').toString();
                if (err.length > 220) {
                    err = err.substring(0, 220) + '...';
                }
                html += '<tr>'
                    + '<td>' + parseInt(p.id_product, 10) + '</td>'
                    + '<td>' + escapeHtml(p.reference || '') + '</td>'
                    + '<td>' + escapeHtml(p.name || '') + '</td>'
                    + '<td>' + escapeHtml(p.error_type || 'Error de sincronización') + '</td>'
                    + '<td>' + escapeHtml(err || 'Sin detalle') + '</td>'
                    + '</tr>';
            });
            $tbody.html(html);
        }).fail(function () {
            $('#yuju-kpi-errors-summary').text('Error de red al consultar los detalles.');
            $tbody.html('<tr><td colspan="5">Error de red.</td></tr>');
        });
    });

    function fetchAllIdsForSegment(cb) {
        var offset = 0;
        var all = [];
        function step() {
            $.post(ajaxUrl, {
                ajax: 1,
                action: 'categoryBulkProductIds',
                token: token,
                id_category: idCategory,
                segment: currentSegment,
                offset: offset,
                limit: 400
            }).done(function (res) {
                if (!res || !res.success) {
                    cb('No se pudieron leer los IDs.', null);
                    return;
                }
                all = all.concat(res.product_ids || []);
                offset = res.next_offset || offset;
                if (res.has_more) {
                    $('#yuju-cat-bulk-progress').text('Leyendo IDs… ' + all.length);
                    step();
                } else {
                    cb(null, all);
                }
            }).fail(function () {
                cb('Error de red al leer IDs.', null);
            });
        }
        step();
    }

    function sendChunks(productIds, bulkAction, deleteConfirmed, onProgress, done) {
        var chunk = 80;
        var i = 0;
        var lastResponse = null;
        function next() {
            if (i >= productIds.length) {
                done(null, lastResponse);
                return;
            }
            var part = productIds.slice(i, i + chunk);
            i += chunk;
            onProgress(i, productIds.length);
            $.post(ajaxUrl, {
                ajax: 1,
                action: 'categoryBulkSendChunk',
                token: token,
                id_category: idCategory,
                product_ids_json: JSON.stringify(part),
                bulk_action: bulkAction,
                bulk_delete_confirmed: deleteConfirmed ? '1' : '0'
            }).done(function (res) {
                if (!res || res.success === false) {
                    done(res && res.message ? res.message : 'Error en lote', res || null);
                    return;
                }
                lastResponse = res;
                next();
            }).fail(function () {
                done('Error de red al enviar lote', null);
            });
        }
        next();
    }

    function showBulkResultModal(response, fallbackMessage, isError) {
        var $summary = $('#yuju-cat-bulk-result-summary');
        var $extra = $('#yuju-cat-bulk-result-extra');
        var $wrap = $('#yuju-cat-bulk-result-errors-wrap');
        var $tbody = $('#yuju-cat-bulk-result-errors-tbody');
        var msg = (response && response.message) ? String(response.message) : String(fallbackMessage || '');
        var details = response && response.details ? response.details : {};
        var errors = response && response.errors ? response.errors : [];
        $summary
            .removeClass('alert-info alert-success alert-warning alert-danger')
            .addClass(isError ? 'alert-danger' : 'alert-success')
            .text(msg || (isError ? 'El proceso terminó con errores.' : 'Proceso completado.'));

        var extraParts = [];
        if (details && typeof details === 'object') {
            if (typeof details.success !== 'undefined') extraParts.push('Exitosos: ' + details.success);
            if (typeof details.queued !== 'undefined') extraParts.push('En cola: ' + details.queued);
            if (typeof details.skipped !== 'undefined') extraParts.push('Omitidos: ' + details.skipped);
            if (typeof details.errors !== 'undefined') extraParts.push('Errores: ' + details.errors);
        }
        $extra.text(extraParts.join(' | '));

        if (errors && errors.length) {
            var html = '';
            errors.forEach(function (line) {
                var raw = String(line || '');
                var m = raw.match(/^Producto ID\\s+(\\d+)\\s*:\\s*(.*)$/i);
                var pid = m ? m[1] : '-';
                var detail = m ? m[2] : raw;
                html += '<tr><td>' + escapeHtml(pid) + '</td><td>' + escapeHtml(detail) + '</td></tr>';
            });
            $tbody.html(html);
            $wrap.show();
        } else {
            $tbody.html('');
            $wrap.hide();
        }

        $('#yuju-cat-bulk-result-modal').modal('show');
    }

    $('.yuju-cat-bulk-act').on('click', function () {
        var bulkAction = $(this).data('action');
        if (bulkAction === 'delete') {
            if (!window.confirm('¿Eliminar en Yuju todos los productos del segmento "' + segmentLabel(currentSegment) + '"? Esta acción no se puede deshacer en marketplaces.')) {
                return;
            }
        } else {
            if (!window.confirm('¿Aplicar "' + bulkAction + '" a todos los productos del segmento "' + segmentLabel(currentSegment) + '"? Puede tardar varios minutos.')) {
                return;
            }
        }
        $('#yuju-cat-bulk-progress').text('Preparando…');
        fetchAllIdsForSegment(function (err, ids) {
            if (err) {
                showBulkResultModal(null, err, true);
                $('#yuju-cat-bulk-progress').text('');
                return;
            }
            if (!ids.length) {
                showBulkResultModal(null, 'No hay productos en este segmento.', true);
                $('#yuju-cat-bulk-progress').text('');
                return;
            }
            sendChunks(ids, bulkAction, bulkAction === 'delete', function (done, total) {
                $('#yuju-cat-bulk-progress').text('Enviando… ' + Math.min(done, total) + ' / ' + total);
            }, function (err2, finalResponse) {
                if (err2) {
                    if (finalResponse && finalResponse.logs_url) {
                        finalResponse.message = (finalResponse.message || err2) + ' Revise logs: ' + finalResponse.logs_url;
                    }
                    showBulkResultModal(finalResponse, err2, true);
                } else {
                    showBulkResultModal(finalResponse, 'Proceso de lotes finalizado.', false);
                    window.location.reload();
                }
                $('#yuju-cat-bulk-progress').text('');
            });
        });
    });

    loadProducts();
})(typeof jQuery !== 'undefined' ? jQuery : $);
{/literal}
</script>
{/if}
{/block}
