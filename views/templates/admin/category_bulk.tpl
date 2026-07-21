{*
* Vista: acciones masivas y salud de sincronización por categoría PrestaShop
*}
{extends file="./layout.tpl"}

{block name="content"}
<div class="panel yuju-cat-bulk">
    <div class="panel-heading">
        <i class="icon-sitemap"></i>
        Acciones masivas por categoría
    </div>
    <div class="panel-body yuju-cat-bulk__panel-body">
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
            <div class="yuju-cat-bulk__detail-head">
                <div class="yuju-cat-bulk__detail-head-row">
                    <a class="btn btn-default btn-sm" href="{$ajax_url|escape:'html':'UTF-8'}&amp;token={$token|escape:'html':'UTF-8'}" title="Volver al listado de categorías">
                        <i class="icon-arrow-left"></i> Regresar
                    </a>
                    <div class="yuju-cat-bulk__detail-title-wrap">
                        <strong class="yuju-cat-bulk__detail-name">{$detail.name|escape:'html':'UTF-8'}</strong>
                        {if $detail.mapping}
                            <span class="label label-success">Mapeada</span>
                            <span class="text-muted yuju-cat-bulk__detail-map-name" title="{$detail.mapping.yuju_category_name|escape:'html':'UTF-8'}">{$detail.mapping.yuju_category_name|escape:'html':'UTF-8'}</span>
                            {if isset($detail.mapping.sync_enabled) && !$detail.mapping.sync_enabled}
                                <span class="label label-warning">Sync off</span>
                            {/if}
                            <button type="button" class="btn btn-link btn-xs yuju-open-inline-map-modal" style="padding:0 4px;"
                                data-ps-category-id="{$id_category|intval}"
                                data-ps-category-name="{$detail.name|escape:'html':'UTF-8'}"
                                data-current-yuju-id="{$detail.mapping.yuju_category_id|escape:'html':'UTF-8'}"
                                title="Cambiar mapeo Yuju"><i class="icon-exchange"></i></button>
                        {else}
                            <span class="label label-warning">Sin mapeo</span>
                            {if $detail.mapping_covered_by_ancestor}
                                <span class="text-muted small">Hereda del padre #{$detail.mapping_covered_category_id|intval}</span>
                            {/if}
                            <button type="button" class="btn btn-primary btn-xs yuju-open-inline-map-modal"
                                data-ps-category-id="{$id_category|intval}"
                                data-ps-category-name="{$detail.name|escape:'html':'UTF-8'}">
                                <i class="icon-sitemap"></i> Mapear
                            </button>
                        {/if}
                    </div>
                </div>

                <div class="yuju-cat-bulk__kpis yuju-cat-bulk__kpis--filters" id="yuju-cat-kpi-filters" role="group" aria-label="Filtros por estado">
                    <button type="button" class="yuju-kpi yuju-kpi--filter yuju-kpi--total active" data-segment="all" title="Ver todos">
                        <span class="yuju-kpi__value">{$detail.stats.total|intval}</span>
                        <span class="yuju-kpi__label">Activos</span>
                    </button>
                    <button type="button" class="yuju-kpi yuju-kpi--filter yuju-kpi--ok" data-segment="synced_ok" title="Filtrar: Sincronizado (En Yuju OK)">
                        <span class="yuju-kpi__value">{$detail.stats.cnt_synced_ok|intval}</span>
                        <span class="yuju-kpi__label">Sincronizado</span>
                        <span class="yuju-kpi__hint">{$detail.stats.pct_uploaded_ok|intval}%</span>
                    </button>
                    <button type="button" class="yuju-kpi yuju-kpi--filter yuju-kpi--pending" data-segment="queued" title="Filtrar: En cola">
                        <span class="yuju-kpi__value">{if isset($detail.stats.cnt_queued)}{$detail.stats.cnt_queued|intval}{else}{$detail.stats.cnt_pending|intval}{/if}</span>
                        <span class="yuju-kpi__label">En cola</span>
                    </button>
                    <button type="button" class="yuju-kpi yuju-kpi--filter yuju-kpi--orphan" data-segment="not_sent" title="Filtrar: No enviados">
                        <span class="yuju-kpi__value">{if isset($detail.stats.cnt_not_sent)}{$detail.stats.cnt_not_sent|intval}{else}0{/if}</span>
                        <span class="yuju-kpi__label">No enviados</span>
                    </button>
                    <button type="button" class="yuju-kpi yuju-kpi--filter yuju-kpi--err" data-segment="errors" title="Filtrar: Error">
                        <span class="yuju-kpi__value">{$detail.stats.cnt_errors|intval}</span>
                        <span class="yuju-kpi__label">Error</span>
                    </button>
                    <button type="button" class="yuju-kpi yuju-kpi--filter yuju-kpi--prog" data-segment="in_progress" title="Filtrar: En espera / actualizando">
                        <span class="yuju-kpi__value">{$detail.stats.cnt_in_progress|intval}</span>
                        <span class="yuju-kpi__label">En espera</span>
                    </button>
                    <button type="button" class="yuju-kpi yuju-kpi--filter yuju-kpi--inv" data-segment="invalid" title="Filtrar: No enviables (sin ref/nombre)">
                        <span class="yuju-kpi__value">{$detail.stats.cnt_invalid|intval}</span>
                        <span class="yuju-kpi__label">No enviables</span>
                    </button>
                </div>
            </div>

            <div class="yuju-cat-bulk__segments">
                <span class="yuju-cat-bulk__segments-label">Ver listado:</span>
                <div class="btn-group" role="group" id="yuju-cat-segments" data-id-category="{$id_category|intval}">
                    <button type="button" class="btn btn-default btn-xs active" data-segment="all">Todos</button>
                    <button type="button" class="btn btn-default btn-xs" data-segment="synced_ok">Sincronizado</button>
                    <button type="button" class="btn btn-default btn-xs" data-segment="queued">En cola</button>
                    <button type="button" class="btn btn-default btn-xs" data-segment="not_sent">No enviados</button>
                    <button type="button" class="btn btn-default btn-xs" data-segment="errors">Error</button>
                    <button type="button" class="btn btn-default btn-xs" data-segment="in_progress">En espera</button>
                    <button type="button" class="btn btn-default btn-xs" data-segment="stale_creating" title="creating_in_yuju ≥1h">≥1h</button>
                    <button type="button" class="btn btn-default btn-xs" data-segment="invalid">No enviables</button>
                    <button type="button" class="btn btn-default btn-xs" data-segment="disabled">Deshabilitados</button>
                </div>
            </div>

            <div class="yuju-cat-bulk__bulk-bar well well-sm">
                <div class="yuju-cat-bulk__selected-bar" style="margin-bottom:12px;">
                    <strong><i class="icon-check-sign"></i> Seleccionados en esta página</strong>
                    <span id="yuju-cat-page-sel-count" class="text-muted" style="margin-left:8px;">0 producto(s)</span>
                </div>
                <strong>Acciones sobre los seleccionados</strong>
                <span class="help-block" style="margin:4px 0 8px;">Solo se procesan los productos marcados en esta página. Marque con el checkbox y pulse la acción.</span>
                <button type="button" class="btn btn-primary btn-sm yuju-cat-bulk-act" data-action="create"><i class="icon-cloud-upload"></i> Crear / encolar en Yuju</button>
                <button type="button" class="btn btn-default btn-sm yuju-cat-bulk-act" data-action="update"><i class="icon-refresh"></i> Actualizar en Yuju</button>
                {if isset($yuju_enable_bulk_resend_pending) && $yuju_enable_bulk_resend_pending}
                <button type="button" class="btn btn-warning btn-sm yuju-cat-bulk-act" data-action="resend_stale" title="Reenvía creación a productos en espera ≥1h sin webhook"><i class="icon-refresh"></i> Enviar de nuevo (&gt;1h)</button>
                {/if}
                <button type="button" class="btn btn-danger btn-sm yuju-cat-bulk-act" data-action="delete"><i class="icon-trash"></i> Eliminar de Yuju</button>
                <span id="yuju-cat-bulk-progress" class="text-muted" style="margin-left:12px;"></span>
                <div id="yuju-cat-bulk-progress-box" class="alert alert-info" style="display:none; margin:10px 0 0; padding:8px 12px;">
                    <i class="icon-spinner icon-spin"></i>
                    <strong id="yuju-cat-bulk-progress-title">Procesando…</strong>
                    <span id="yuju-cat-bulk-progress-detail" style="margin-left:6px;"></span>
                    <div class="progress" style="margin:8px 0 0; height:18px;">
                        <div id="yuju-cat-bulk-progress-bar" class="progress-bar progress-bar-info progress-bar-striped active" role="progressbar" style="width:0%; min-width:2em; line-height:18px; font-size:11px;">0%</div>
                    </div>
                </div>
            </div>

            <div id="yuju-cat-bulk-table-wrap">
                <table class="table table-bordered table-striped" id="yuju-cat-bulk-table">
                    <thead>
                        <tr style="background:#f8f9fa;">
                            <th width="30" class="text-center">
                                <input type="checkbox" id="yuju-cat-select-all-products" title="Seleccionar todos en esta página">
                            </th>
                            <th width="30" class="text-center">Info</th>
                            <th width="120">Referencia</th>
                            <th>Nombre</th>
                            <th width="140" class="text-center">Estado Yuju</th>
                            <th width="150" class="text-center">Última Sync</th>
                            <th width="60" class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="yuju-cat-bulk-tbody"></tbody>
                </table>
                <div id="yuju-cat-bulk-pager" class="clearfix"></div>
            </div>

            {include file='module:prestashopyuju/views/templates/admin/_partials/yuju_product_info_modal.tpl'}

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
                                            <th style="width:320px;">Producto</th>
                                            <th>Detalle del error</th>
                                        </tr>
                                    </thead>
                                    <tbody id="yuju-cat-bulk-result-errors-tbody"></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a id="yuju-cat-bulk-result-logs-btn" class="btn btn-default" href="{$yuju_logs_admin_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener" style="display:none;">
                                <i class="icon-list-alt"></i> Ver logs
                            </a>
                            <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>

        {elseif $id_category && !$detail}
            <div class="alert alert-danger">Categoría no encontrada o inactiva.</div>
            <a href="{$ajax_url|escape:'html':'UTF-8'}&amp;token={$token|escape:'html':'UTF-8'}" class="btn btn-default">Volver</a>
        {else}
            {assign var=of value=$overview_filters|default:[]}
            {assign var=filters_open value=0}
            {if (isset($of.filters_open) && $of.filters_open) || (isset($overview_filters_active) && $overview_filters_active)}
                {assign var=filters_open value=1}
            {/if}

            <div class="yuju-cat-filters" id="yuju-cat-filters" data-open="{$filters_open|intval}">
                <div class="yuju-cat-filters__toggle" id="yuju-cat-filters-toggle" role="button" tabindex="0" aria-expanded="{if $filters_open}true{else}false{/if}">
                    <div class="yuju-cat-filters__toggle-left">
                        <i class="icon-filter"></i>
                        <strong>Filtros</strong>
                        {if isset($overview_filters_active) && $overview_filters_active}
                            <span class="label label-info yuju-cat-filters__active-badge">Activos</span>
                        {/if}
                        <span class="text-muted yuju-cat-filters__hint">SKU, mapeo, estados, orden…</span>
                    </div>
                    <div class="yuju-cat-filters__toggle-right">
                        {if isset($overview_rows) && $overview_rows}
                            <span class="text-muted">{$overview_rows|@count} categoría(s)</span>
                        {/if}
                        <i class="icon-chevron-down yuju-cat-filters__chev"></i>
                    </div>
                </div>

                <div class="yuju-cat-filters__body collapse{if $filters_open} in{/if}" id="yuju-cat-filters-body">
                    <form method="get" class="yuju-cat-filters__form" action="{$ajax_url|escape:'html':'UTF-8'}" id="yuju-cat-filters-form">
                        <input type="hidden" name="controller" value="AdminYujuCategoryBulk" />
                        <input type="hidden" name="token" value="{$token|escape:'html':'UTF-8'}" />
                        <input type="hidden" name="filters_open" id="yuju_cat_filters_open" value="1" />

                        <div class="yuju-cat-filters__grid">
                            <div class="yuju-cat-filters__field">
                                <label for="yuju_cat_q">Categoría</label>
                                <input type="text" name="q" id="yuju_cat_q" class="form-control input-sm"
                                    value="{if isset($of.q)}{$of.q|escape:'html':'UTF-8'}{/if}"
                                    placeholder="Nombre de categoría…" />
                            </div>
                            <div class="yuju-cat-filters__field">
                                <label for="yuju_cat_sku">SKU / referencia</label>
                                <input type="text" name="sku" id="yuju_cat_sku" class="form-control input-sm"
                                    value="{if isset($of.sku)}{$of.sku|escape:'html':'UTF-8'}{/if}"
                                    placeholder="Ej. ALI-123" />
                            </div>
                            <div class="yuju-cat-filters__field">
                                <label for="yuju_cat_mapping">Mapeo Yuju</label>
                                <select name="mapping" id="yuju_cat_mapping" class="form-control input-sm">
                                    <option value="all"{if !isset($of.mapping) || $of.mapping == 'all'} selected="selected"{/if}>Todos</option>
                                    <option value="mapped"{if isset($of.mapping) && $of.mapping == 'mapped'} selected="selected"{/if}>Con mapeo (propio o padre)</option>
                                    <option value="own"{if isset($of.mapping) && $of.mapping == 'own'} selected="selected"{/if}>Mapeo propio</option>
                                    <option value="ancestor"{if isset($of.mapping) && $of.mapping == 'ancestor'} selected="selected"{/if}>Solo por padre</option>
                                    <option value="unmapped"{if isset($of.mapping) && $of.mapping == 'unmapped'} selected="selected"{/if}>Sin mapeo</option>
                                </select>
                            </div>
                            <div class="yuju-cat-filters__field">
                                <label for="yuju_cat_min_total">Mín. productos</label>
                                <input type="number" min="0" name="min_total" id="yuju_cat_min_total" class="form-control input-sm"
                                    value="{if isset($of.min_total) && $of.min_total}{$of.min_total|intval}{/if}"
                                    placeholder="0" />
                            </div>
                            <div class="yuju-cat-filters__field">
                                <label for="yuju_cat_sort">Ordenar por</label>
                                <select name="sort" id="yuju_cat_sort" class="form-control input-sm">
                                    <option value="total"{if !isset($of.sort) || $of.sort == 'total'} selected="selected"{/if}>Total productos</option>
                                    <option value="name"{if isset($of.sort) && $of.sort == 'name'} selected="selected"{/if}>Nombre</option>
                                    <option value="synced_ok"{if isset($of.sort) && $of.sort == 'synced_ok'} selected="selected"{/if}>Sincronizado</option>
                                    <option value="queued"{if isset($of.sort) && ($of.sort == 'queued' || $of.sort == 'pending')} selected="selected"{/if}>En cola</option>
                                    <option value="not_sent"{if isset($of.sort) && ($of.sort == 'not_sent' || $of.sort == 'orphan')} selected="selected"{/if}>No enviados</option>
                                    <option value="errors"{if isset($of.sort) && $of.sort == 'errors'} selected="selected"{/if}>Error</option>
                                    <option value="in_progress"{if isset($of.sort) && $of.sort == 'in_progress'} selected="selected"{/if}>En espera</option>
                                    <option value="invalid"{if isset($of.sort) && $of.sort == 'invalid'} selected="selected"{/if}>No enviables</option>
                                    <option value="mapped"{if isset($of.sort) && $of.sort == 'mapped'} selected="selected"{/if}>Mapeo</option>
                                    <option value="mapped"{if isset($of.sort) && $of.sort == 'mapped'} selected="selected"{/if}>Mapeo</option>
                                </select>
                            </div>
                            <div class="yuju-cat-filters__field">
                                <label for="yuju_cat_dir">Dirección</label>
                                <select name="dir" id="yuju_cat_dir" class="form-control input-sm">
                                    <option value="desc"{if !isset($of.dir) || $of.dir == 'desc'} selected="selected"{/if}>Mayor → menor</option>
                                    <option value="asc"{if isset($of.dir) && $of.dir == 'asc'} selected="selected"{/if}>Menor → mayor</option>
                                </select>
                            </div>
                        </div>

                        <div class="yuju-cat-filters__status">
                            <span class="yuju-cat-filters__status-label">Con productos en:</span>
                            <label class="yuju-cat-filters__chip{if isset($of.has_synced) && $of.has_synced} is-on{/if}">
                                <input type="checkbox" name="has_synced" value="1"{if isset($of.has_synced) && $of.has_synced} checked="checked"{/if} /> Sincronizado
                            </label>
                            <label class="yuju-cat-filters__chip{if (isset($of.has_queued) && $of.has_queued) || (isset($of.has_pending) && $of.has_pending)} is-on{/if}">
                                <input type="checkbox" name="has_queued" value="1"{if (isset($of.has_queued) && $of.has_queued) || (isset($of.has_pending) && $of.has_pending)} checked="checked"{/if} /> En cola
                            </label>
                            <label class="yuju-cat-filters__chip{if (isset($of.has_not_sent) && $of.has_not_sent) || (isset($of.has_orphan) && $of.has_orphan)} is-on{/if}">
                                <input type="checkbox" name="has_not_sent" value="1"{if (isset($of.has_not_sent) && $of.has_not_sent) || (isset($of.has_orphan) && $of.has_orphan)} checked="checked"{/if} /> No enviados
                            </label>
                            <label class="yuju-cat-filters__chip{if isset($of.has_errors) && $of.has_errors} is-on{/if}">
                                <input type="checkbox" name="has_errors" value="1"{if isset($of.has_errors) && $of.has_errors} checked="checked"{/if} /> Error
                            </label>
                            <label class="yuju-cat-filters__chip{if isset($of.has_in_progress) && $of.has_in_progress} is-on{/if}">
                                <input type="checkbox" name="has_in_progress" value="1"{if isset($of.has_in_progress) && $of.has_in_progress} checked="checked"{/if} /> En espera
                            </label>
                            <label class="yuju-cat-filters__chip{if isset($of.has_invalid) && $of.has_invalid} is-on{/if}">
                                <input type="checkbox" name="has_invalid" value="1"{if isset($of.has_invalid) && $of.has_invalid} checked="checked"{/if} /> No enviables
                            </label>
                            <label class="yuju-cat-filters__chip yuju-cat-filters__chip--warn{if isset($of.only_issues) && $of.only_issues} is-on{/if}">
                                <input type="checkbox" name="only_issues" value="1"{if isset($of.only_issues) && $of.only_issues} checked="checked"{/if} /> Solo con problemas
                            </label>
                        </div>

                        <div class="yuju-cat-filters__actions">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="icon-search"></i> Aplicar filtros</button>
                            <a class="btn btn-default btn-sm" href="{$ajax_url|escape:'html':'UTF-8'}&amp;token={$token|escape:'html':'UTF-8'}"><i class="icon-remove"></i> Limpiar</a>
                        </div>
                    </form>

                    {if isset($overview_sku_hits) && $overview_sku_hits|@count > 0}
                        <div class="yuju-cat-filters__sku-hits">
                            <strong>SKU coincidentes:</strong>
                            {foreach from=$overview_sku_hits item=hit name=skuHits}
                                <span class="label label-default" title="{$hit.name|escape:'html':'UTF-8'}">#{$hit.id_product|intval} {$hit.reference|escape:'html':'UTF-8'}</span>
                            {/foreach}
                            <span class="text-muted">— se listan las categorías donde aparecen.</span>
                        </div>
                    {/if}
                </div>
            </div>

            <p class="text-muted yuju-cat-filters__summary">
                Hasta 400 categorías con productos activos.
                {if isset($overview_filters_active) && $overview_filters_active}
                    Resultados filtrados.
                {else}
                    Orden por volumen (puede cambiarse en filtros o cabeceras).
                {/if}
                Seleccione una o varias para crear/actualizar en masa.
            </p>

            <div class="yuju-cat-bulk__mass-bar" id="yuju-cat-overview-mass">
                <div class="yuju-cat-bulk__mass-bar-left">
                    <strong id="yuju-cat-sel-count">0</strong> categoría(s) seleccionada(s)
                    <span class="text-muted" id="yuju-cat-sel-products" style="margin-left:8px;"></span>
                </div>
                <div class="yuju-cat-bulk__mass-bar-actions">
                    <button type="button" class="btn btn-success btn-sm" id="yuju-cat-mass-create" disabled>
                        <i class="icon-cloud-upload"></i> Crear / sincronizar
                    </button>
                    <button type="button" class="btn btn-info btn-sm" id="yuju-cat-mass-update" disabled>
                        <i class="icon-refresh"></i> Actualizar
                    </button>
                </div>
                <div class="yuju-cat-bulk__mass-bar-progress text-muted" id="yuju-cat-overview-progress">&nbsp;</div>
            </div>

            <div class="table-responsive yuju-cat-bulk__overview-scroll">
                <table class="table table-bordered table-hover yuju-cat-bulk__overview" id="yuju-cat-overview-table">
                    <thead>
                        <tr>
                            <th class="text-center" style="width:36px;">
                                <input type="checkbox" id="yuju-cat-check-all" title="Seleccionar todas" />
                            </th>
                            <th>
                                {if isset($overview_sort_links.name)}
                                    <a class="yuju-cat-sort{if $overview_sort_links.name.active} is-active{/if}" href="{$overview_sort_links.name.url|escape:'html':'UTF-8'}">
                                        Categoría
                                        {if $overview_sort_links.name.active}<i class="icon-caret-{if $overview_sort_links.name.dir == 'asc'}up{else}down{/if}"></i>{/if}
                                    </a>
                                {else}Categoría{/if}
                            </th>
                            <th class="text-center">
                                {if isset($overview_sort_links.total)}
                                    <a class="yuju-cat-sort{if $overview_sort_links.total.active} is-active{/if}" href="{$overview_sort_links.total.url|escape:'html':'UTF-8'}">
                                        Total
                                        {if $overview_sort_links.total.active}<i class="icon-caret-{if $overview_sort_links.total.dir == 'asc'}up{else}down{/if}"></i>{/if}
                                    </a>
                                {else}Total{/if}
                            </th>
                            <th class="text-center yuju-st-col yuju-st-col--synced">
                                {if isset($overview_sort_links.synced_ok)}
                                    <a class="yuju-cat-sort{if $overview_sort_links.synced_ok.active} is-active{/if}" href="{$overview_sort_links.synced_ok.url|escape:'html':'UTF-8'}" title="Estado: Sincronizado">
                                        Sincronizado
                                        {if $overview_sort_links.synced_ok.active}<i class="icon-caret-{if $overview_sort_links.synced_ok.dir == 'asc'}up{else}down{/if}"></i>{/if}
                                    </a>
                                {else}Sincronizado{/if}
                            </th>
                            <th class="text-center yuju-st-col yuju-st-col--queued">
                                {if isset($overview_sort_links.queued)}
                                    <a class="yuju-cat-sort{if $overview_sort_links.queued.active} is-active{/if}" href="{$overview_sort_links.queued.url|escape:'html':'UTF-8'}" title="Estado: En cola">
                                        En cola
                                        {if $overview_sort_links.queued.active}<i class="icon-caret-{if $overview_sort_links.queued.dir == 'asc'}up{else}down{/if}"></i>{/if}
                                    </a>
                                {elseif isset($overview_sort_links.pending)}
                                    <a class="yuju-cat-sort{if $overview_sort_links.pending.active} is-active{/if}" href="{$overview_sort_links.pending.url|escape:'html':'UTF-8'}" title="Estado: En cola">
                                        En cola
                                        {if $overview_sort_links.pending.active}<i class="icon-caret-{if $overview_sort_links.pending.dir == 'asc'}up{else}down{/if}"></i>{/if}
                                    </a>
                                {else}En cola{/if}
                            </th>
                            <th class="text-center yuju-st-col yuju-st-col--not-sent">
                                {if isset($overview_sort_links.not_sent)}
                                    <a class="yuju-cat-sort{if $overview_sort_links.not_sent.active} is-active{/if}" href="{$overview_sort_links.not_sent.url|escape:'html':'UTF-8'}" title="Estado: No enviado">
                                        No enviados
                                        {if $overview_sort_links.not_sent.active}<i class="icon-caret-{if $overview_sort_links.not_sent.dir == 'asc'}up{else}down{/if}"></i>{/if}
                                    </a>
                                {else}No enviados{/if}
                            </th>
                            <th class="text-center yuju-st-col yuju-st-col--error">
                                {if isset($overview_sort_links.errors)}
                                    <a class="yuju-cat-sort{if $overview_sort_links.errors.active} is-active{/if}" href="{$overview_sort_links.errors.url|escape:'html':'UTF-8'}" title="Estado: Error">
                                        Error
                                        {if $overview_sort_links.errors.active}<i class="icon-caret-{if $overview_sort_links.errors.dir == 'asc'}up{else}down{/if}"></i>{/if}
                                    </a>
                                {else}Error{/if}
                            </th>
                            <th class="text-center yuju-st-col yuju-st-col--wait">
                                {if isset($overview_sort_links.in_progress)}
                                    <a class="yuju-cat-sort{if $overview_sort_links.in_progress.active} is-active{/if}" href="{$overview_sort_links.in_progress.url|escape:'html':'UTF-8'}" title="En espera de respuesta / Actualizando">
                                        En espera
                                        {if $overview_sort_links.in_progress.active}<i class="icon-caret-{if $overview_sort_links.in_progress.dir == 'asc'}up{else}down{/if}"></i>{/if}
                                    </a>
                                {else}En espera{/if}
                            </th>
                            <th class="text-center yuju-st-col yuju-st-col--invalid">
                                {if isset($overview_sort_links.invalid)}
                                    <a class="yuju-cat-sort{if $overview_sort_links.invalid.active} is-active{/if}" href="{$overview_sort_links.invalid.url|escape:'html':'UTF-8'}" title="Sin referencia o nombre (no se pueden enviar)">
                                        No enviables
                                        {if $overview_sort_links.invalid.active}<i class="icon-caret-{if $overview_sort_links.invalid.dir == 'asc'}up{else}down{/if}"></i>{/if}
                                    </a>
                                {else}No enviables{/if}
                            </th>
                            <th class="text-center">
                                {if isset($overview_sort_links.mapped)}
                                    <a class="yuju-cat-sort{if $overview_sort_links.mapped.active} is-active{/if}" href="{$overview_sort_links.mapped.url|escape:'html':'UTF-8'}">
                                        Mapeo
                                        {if $overview_sort_links.mapped.active}<i class="icon-caret-{if $overview_sort_links.mapped.dir == 'asc'}up{else}down{/if}"></i>{/if}
                                    </a>
                                {else}Mapeo{/if}
                            </th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {if $overview_rows && $overview_rows|@count > 0}
                            {foreach from=$overview_rows item=row}
                            <tr data-category-id="{$row.id_category|intval}" data-total="{$row.total|intval}" data-mapped="{if $row.mapped_yuju}1{else}0{/if}" data-category-name="{$row.category_name|escape:'html':'UTF-8'}">
                                <td class="text-center">
                                    <input type="checkbox" class="yuju-cat-row-check" value="{$row.id_category|intval}" />
                                </td>
                                <td>
                                    {if $row.mapped_yuju}
                                        {if isset($row.mapped_via_ancestor) && $row.mapped_via_ancestor}
                                            <i class="icon-link yuju-cat-map-icon yuju-cat-map-icon--parent" title="Mapeada por herencia de padre"></i>
                                        {else}
                                            <i class="icon-ok-sign yuju-cat-map-icon yuju-cat-map-icon--ok" title="Categoría mapeada en Yuju"></i>
                                        {/if}
                                    {else}
                                        <i class="icon-warning-sign yuju-cat-map-icon yuju-cat-map-icon--no" title="Sin mapeo Yuju"></i>
                                    {/if}
                                    <span class="yuju-cat-row-name">{$row.category_name|escape:'html':'UTF-8'}</span>
                                </td>
                                <td class="text-center"><span class="badge">{$row.total|intval}</span></td>
                                <td class="text-center"><span class="yuju-st-num yuju-st-num--synced">{$row.cnt_synced_ok|intval}</span></td>
                                <td class="text-center"><span class="yuju-st-num yuju-st-num--queued">{if isset($row.cnt_queued)}{$row.cnt_queued|intval}{else}{$row.cnt_pending|intval}{/if}</span></td>
                                <td class="text-center"><span class="yuju-st-num yuju-st-num--not-sent">{if isset($row.cnt_not_sent)}{$row.cnt_not_sent|intval}{else}0{/if}</span></td>
                                <td class="text-center"><span class="yuju-st-num yuju-st-num--error">{$row.cnt_errors|intval}</span></td>
                                <td class="text-center"><span class="yuju-st-num yuju-st-num--wait">{$row.cnt_in_progress|intval}</span></td>
                                <td class="text-center"><span class="yuju-st-num yuju-st-num--invalid">{$row.cnt_invalid|intval}</span></td>
                                <td class="text-center yuju-cat-map-status">
                                    {if $row.mapped_yuju}
                                        {if isset($row.mapped_via_ancestor) && $row.mapped_via_ancestor}
                                            <span class="label label-info" title="Hereda mapeo de un padre">
                                                <i class="icon-link"></i> Padre
                                            </span>
                                        {else}
                                            <span class="label label-success" title="Mapeada en Yuju">
                                                <i class="icon-ok"></i> Sí
                                            </span>
                                        {/if}
                                    {else}
                                        <span class="label label-warning" title="Sin mapeo Yuju">
                                            <i class="icon-remove"></i> No
                                        </span>
                                    {/if}
                                </td>
                                <td class="text-nowrap">
                                    {if !$row.mapped_yuju}
                                        <button type="button" class="btn btn-primary btn-xs yuju-open-inline-map-modal"
                                            data-ps-category-id="{$row.id_category|intval}"
                                            data-ps-category-name="{$row.category_name|escape:'html':'UTF-8'}">
                                            <i class="icon-sitemap"></i> Mapear
                                        </button>
                                    {/if}
                                    <a class="btn btn-default btn-xs" href="{$ajax_url|escape:'html':'UTF-8'}&amp;token={$token|escape:'html':'UTF-8'}&amp;id_category={$row.id_category|intval}">
                                        Gestionar
                                    </a>
                                </td>
                            </tr>
                            {/foreach}
                        {else}
                            <tr><td colspan="10">No hay categorías con productos activos que coincidan con los filtros.</td></tr>
                        {/if}
                    </tbody>
                </table>
            </div>

            <div id="yuju-cat-overview-config" class="hidden" style="display:none"
                data-ajax-url="{$ajax_url|escape:'html':'UTF-8'}"
                data-token="{$token|escape:'html':'UTF-8'}"
                data-logs-url="{$yuju_logs_admin_url|escape:'html':'UTF-8'}"
                data-mapping-url="{$category_mapping_url|escape:'html':'UTF-8'}"></div>

            <div class="modal fade" id="yuju-cat-overview-result-modal" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                            <h4 class="modal-title"><i class="icon-list"></i> Resultado del envío masivo</h4>
                        </div>
                        <div class="modal-body">
                            <div id="yuju-cat-overview-result-summary" class="alert alert-info"></div>
                            <div id="yuju-cat-overview-result-actions" style="display:none; margin-top:12px;"></div>
                            <div id="yuju-cat-overview-result-errors" style="display:none; margin-top:12px;">
                                <p style="margin:0 0 6px;"><strong>Detalle (errores / omisiones / en espera)</strong></p>
                                <div id="yuju-cat-overview-result-errors-list" style="max-height:260px; overflow:auto; font-size:12px; background:#fff; border:1px solid #e0e0e0; padding:8px;"></div>
                            </div>
                            <pre id="yuju-cat-overview-result-extra" style="display:none; max-height:240px; overflow:auto; font-size:11px;"></pre>
                        </div>
                        <div class="modal-footer">
                            <a id="yuju-cat-overview-result-logs-btn" class="btn btn-default" href="#" target="_blank" rel="noopener" style="display:none;">
                                <i class="icon-list-alt"></i> Ver logs
                            </a>
                            <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>

            <script type="text/javascript">
            {literal}
            (function ($) {
                var cfg = $('#yuju-cat-overview-config');
                var ajaxUrl = cfg.data('ajaxUrl') || cfg.attr('data-ajax-url');
                var token = cfg.data('token') || cfg.attr('data-token');
                var logsUrl = cfg.data('logsUrl') || cfg.attr('data-logs-url') || '';
                var mappingUrl = cfg.data('mappingUrl') || cfg.attr('data-mapping-url') || '';
                var busy = false;
                var filtersStorageKey = 'yuju_cat_bulk_filters_open';

                function setFiltersOpen(open) {
                    var $wrap = $('#yuju-cat-filters');
                    var $body = $('#yuju-cat-filters-body');
                    var $toggle = $('#yuju-cat-filters-toggle');
                    if (open) {
                        $wrap.addClass('is-open').attr('data-open', '1');
                        $body.addClass('in').show();
                        $toggle.attr('aria-expanded', 'true');
                        $('#yuju_cat_filters_open').val('1');
                    } else {
                        $wrap.removeClass('is-open').attr('data-open', '0');
                        $body.removeClass('in').hide();
                        $toggle.attr('aria-expanded', 'false');
                        $('#yuju_cat_filters_open').val('0');
                    }
                    try {
                        window.localStorage.setItem(filtersStorageKey, open ? '1' : '0');
                    } catch (e) {}
                }

                (function initFiltersPanel() {
                    var $wrap = $('#yuju-cat-filters');
                    if (!$wrap.length) {
                        return;
                    }
                    var serverOpen = String($wrap.attr('data-open') || '0') === '1';
                    var stored = null;
                    try {
                        stored = window.localStorage.getItem(filtersStorageKey);
                    } catch (e) {}
                    // Si hay filtros activos el servidor fuerza abierto; si no, respeta localStorage
                    if (serverOpen) {
                        setFiltersOpen(true);
                    } else if (stored === '1') {
                        setFiltersOpen(true);
                    } else if (stored === '0') {
                        setFiltersOpen(false);
                    } else {
                        setFiltersOpen(false);
                    }

                    $('#yuju-cat-filters-toggle').on('click keypress', function (e) {
                        if (e.type === 'keypress' && e.which !== 13 && e.which !== 32) {
                            return;
                        }
                        e.preventDefault();
                        setFiltersOpen(!$wrap.hasClass('is-open'));
                    });

                    $('#yuju-cat-filters-form').on('change', '.yuju-cat-filters__chip input[type="checkbox"]', function () {
                        $(this).closest('.yuju-cat-filters__chip').toggleClass('is-on', this.checked);
                    });
                })();

                function selectedCategoryIds() {
                    var ids = [];
                    $('.yuju-cat-row-check:checked').each(function () {
                        var id = parseInt($(this).val(), 10) || 0;
                        if (id > 0) {
                            ids.push(id);
                        }
                    });
                    return ids;
                }

                function selectedTotals() {
                    var products = 0;
                    var unmapped = 0;
                    $('.yuju-cat-row-check:checked').each(function () {
                        var $tr = $(this).closest('tr');
                        products += parseInt($tr.attr('data-total') || '0', 10) || 0;
                        if (String($tr.attr('data-mapped') || '0') !== '1') {
                            unmapped++;
                        }
                    });
                    return { products: products, unmapped: unmapped };
                }

                function refreshMassBar() {
                    var ids = selectedCategoryIds();
                    var n = ids.length;
                    var totals = selectedTotals();
                    $('#yuju-cat-sel-count').text(n);
                    if (n > 0) {
                        $('#yuju-cat-sel-products').text('(~' + totals.products + ' productos activos; únicos al enviar)');
                        $('#yuju-cat-mass-create, #yuju-cat-mass-update').prop('disabled', busy);
                        $('#yuju-cat-overview-mass').addClass('is-active');
                    } else {
                        $('#yuju-cat-sel-products').text('Marque una o varias categorías para habilitar las acciones');
                        $('#yuju-cat-mass-create, #yuju-cat-mass-update').prop('disabled', true);
                        $('#yuju-cat-overview-mass').removeClass('is-active');
                    }
                    var all = $('.yuju-cat-row-check').length;
                    var checked = $('.yuju-cat-row-check:checked').length;
                    $('#yuju-cat-check-all').prop('checked', all > 0 && checked === all);
                    $('#yuju-cat-check-all').prop('indeterminate', checked > 0 && checked < all);
                }

                $(document).on('change', '.yuju-cat-row-check', refreshMassBar);
                $('#yuju-cat-check-all').on('change', function () {
                    var on = $(this).is(':checked');
                    $('.yuju-cat-row-check').prop('checked', on);
                    refreshMassBar();
                });

                function setBusy(on) {
                    busy = !!on;
                    $('#yuju-cat-mass-create, #yuju-cat-mass-update, #yuju-cat-check-all, .yuju-cat-row-check').prop('disabled', busy);
                    if (!busy) {
                        refreshMassBar();
                    }
                }

                function fetchAllMultiIds(categoryIds, segment, cb) {
                    var offset = 0;
                    var all = [];
                    function step() {
                        $.post(ajaxUrl, {
                            ajax: 1,
                            action: 'categoryBulkMultiProductIds',
                            token: token,
                            category_ids_json: JSON.stringify(categoryIds),
                            segment: segment || 'all',
                            offset: offset,
                            limit: 400
                        }).done(function (res) {
                            if (!res || !res.success) {
                                cb((res && res.message) ? res.message : 'No se pudieron leer los IDs.', null);
                                return;
                            }
                            all = all.concat(res.product_ids || []);
                            offset = res.next_offset || offset;
                            $('#yuju-cat-overview-progress').text('Leyendo productos… ' + all.length);
                            if (res.has_more) {
                                step();
                            } else {
                                // únicos por si acaso
                                var seen = {};
                                var uniq = [];
                                all.forEach(function (id) {
                                    id = parseInt(id, 10) || 0;
                                    if (id > 0 && !seen[id]) {
                                        seen[id] = true;
                                        uniq.push(id);
                                    }
                                });
                                cb(null, uniq);
                            }
                        }).fail(function () {
                            cb('Error de red al leer IDs de productos.', null);
                        });
                    }
                    step();
                }

                function sendChunks(productIds, bulkAction, categoryIds, onProgress, done) {
                    var chunk = 80;
                    var i = 0;
                    var lastResponse = null;
                    var preferredCat = (categoryIds && categoryIds.length === 1) ? categoryIds[0] : 0;
                    function next() {
                        if (i >= productIds.length) {
                            done(null, lastResponse);
                            return;
                        }
                        var part = productIds.slice(i, i + chunk);
                        i += chunk;
                        onProgress(Math.min(i, productIds.length), productIds.length);
                        $.post(ajaxUrl, {
                            ajax: 1,
                            action: 'categoryBulkSendChunk',
                            token: token,
                            id_category: preferredCat,
                            category_ids_json: JSON.stringify(categoryIds || []),
                            product_ids_json: JSON.stringify(part),
                            bulk_action: bulkAction,
                            bulk_delete_confirmed: '0'
                        }).done(function (res) {
                            if (!res || res.success === false) {
                                done((res && res.message) ? res.message : 'Error en lote', res || null);
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

                function escapeHtml(str) {
                    return String(str == null ? '' : str)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#39;');
                }

                function showResult(message, isError, extra, mapItems, response) {
                    response = response || {};
                    var $sum = $('#yuju-cat-overview-result-summary');
                    $sum.removeClass('alert-info alert-success alert-danger')
                        .addClass(isError ? 'alert-danger' : (mapItems && mapItems.length ? 'alert-danger' : 'alert-success'))
                        .css('white-space', 'pre-wrap')
                        .text(message || '');
                    var $actions = $('#yuju-cat-overview-result-actions');
                    $actions.empty().hide();
                    if (mapItems && mapItems.length) {
                        var html = '<p style="margin:0 0 8px;"><strong>Mapee ahora:</strong></p>';
                        mapItems.forEach(function (item) {
                            html += '<button type="button" class="btn btn-primary btn-sm yuju-open-inline-map-modal" style="margin:0 8px 8px 0;"'
                                + ' data-ps-category-id="' + parseInt(item.id, 10) + '"'
                                + ' data-ps-category-name="' + escapeHtml(item.name || '') + '"'
                                + ' data-from-result-modal="1">'
                                + '<i class="icon-sitemap"></i> Mapear ' + escapeHtml(item.name || ('ID ' + item.id))
                                + '</button>';
                        });
                        if (mappingUrl) {
                            html += '<div style="margin-top:4px;">'
                                + '<a class="btn btn-default btn-sm" href="' + escapeHtml(mappingUrl) + '" target="_blank" rel="noopener">'
                                + '<i class="icon-external-link"></i> Abrir Mapeo de Categorías</a>'
                                + '</div>';
                        }
                        $actions.html(html).show();
                        $('#yuju-cat-overview-result-modal').data('pendingMapItems', mapItems);
                    } else {
                        $('#yuju-cat-overview-result-modal').removeData('pendingMapItems');
                    }

                    var errorLines = [];
                    if (response.errors && response.errors.length) {
                        response.errors.forEach(function (line) {
                            errorLines.push(String(line));
                        });
                    }
                    if (response.previous_error_products && response.previous_error_products.length && (!response.errors || !response.errors.length)) {
                        response.previous_error_products.forEach(function (p) {
                            var line = 'Producto ID ' + (p.id_product || '?');
                            if (p.reference) {
                                line += ' [' + p.reference + ']';
                            }
                            if (p.name) {
                                line += ' — ' + p.name;
                            }
                            line += ': ' + (p.last_error || 'Error previo sin detalle');
                            errorLines.push(line);
                        });
                    }
                    if (response.waiting_notes && response.waiting_notes.length) {
                        response.waiting_notes.forEach(function (line) {
                            errorLines.push(String(line));
                        });
                    }
                    if (response.skipped_products && response.skipped_products.length) {
                        response.skipped_products.forEach(function (p) {
                            errorLines.push(
                                'Producto ID ' + (p.id_product || '?') + ': '
                                + (p.last_error || p.reason || 'Omitido')
                            );
                        });
                    }
                    var $errBox = $('#yuju-cat-overview-result-errors');
                    var $errList = $('#yuju-cat-overview-result-errors-list');
                    if (errorLines.length) {
                        var errHtml = '<ul style="margin:0; padding-left:18px;">';
                        errorLines.forEach(function (line) {
                            errHtml += '<li style="margin-bottom:6px;">' + escapeHtml(line) + '</li>';
                        });
                        errHtml += '</ul>';
                        $errList.html(errHtml);
                        $errBox.show();
                    } else {
                        $errList.empty();
                        $errBox.hide();
                    }

                    var $logsBtn = $('#yuju-cat-overview-result-logs-btn');
                    var respLogs = response.logs_url || logsUrl || '';
                    if (respLogs) {
                        $logsBtn.attr('href', respLogs).show();
                    } else {
                        $logsBtn.hide().attr('href', '#');
                    }

                    var $extra = $('#yuju-cat-overview-result-extra');
                    if (extra) {
                        $extra.show().text(typeof extra === 'string' ? extra : JSON.stringify(extra, null, 2));
                    } else {
                        $extra.hide().text('');
                    }
                    $('#yuju-cat-overview-result-modal').modal('show');
                }

                function markOverviewCategoryMapped(psId, yujuName) {
                    var $tr = $('tr[data-category-id="' + parseInt(psId, 10) + '"]');
                    if (!$tr.length) {
                        return;
                    }
                    $tr.attr('data-mapped', '1');
                    var $nameCell = $tr.children('td').eq(1);
                    $nameCell.find('.yuju-cat-map-icon').remove();
                    $nameCell.prepend(
                        '<i class="icon-ok-sign yuju-cat-map-icon yuju-cat-map-icon--ok" title="Categoría mapeada en Yuju"></i> '
                    );
                    var $mapCell = $tr.find('td.yuju-cat-map-status');
                    if ($mapCell.length) {
                        $mapCell.html('<span class="label label-success" title="Mapeada en Yuju"><i class="icon-ok"></i> Sí</span>');
                    }
                    $tr.find('.yuju-open-inline-map-modal').remove();
                    refreshMassBar();
                }

                function showResultReadyToCreate(mappedId, mappedName, remainingItems) {
                    var namesLeft = (remainingItems || []).map(function (it) {
                        return it.name + ' (ID ' + it.id + ')';
                    });
                    var msg;
                    var actionsHtml = '';
                    if (namesLeft.length) {
                        msg = 'Categoría "' + (mappedName || ('ID ' + mappedId)) + '" mapeada correctamente.\n\n'
                            + 'Aún faltan por mapear:\n• ' + namesLeft.join('\n• ')
                            + '\n\nMapee las restantes y luego cree los productos.';
                        showResult(msg, true, null, remainingItems);
                        return;
                    }
                    msg = 'Categoría "' + (mappedName || ('ID ' + mappedId)) + '" mapeada correctamente.\n\n'
                        + 'Ya puede crear/sincronizar los productos de la(s) categoría(s) seleccionada(s).';
                    var $sum = $('#yuju-cat-overview-result-summary');
                    $sum.removeClass('alert-info alert-success alert-danger')
                        .addClass('alert-success')
                        .css('white-space', 'pre-wrap')
                        .text(msg);
                    actionsHtml = '<button type="button" class="btn btn-success" id="yuju-cat-result-create-now">'
                        + '<i class="icon-cloud-upload"></i> Crear / sincronizar ahora</button>';
                    $('#yuju-cat-overview-result-actions').html(actionsHtml).show();
                    $('#yuju-cat-overview-result-extra').hide().text('');
                    $('#yuju-cat-overview-result-errors').hide();
                    $('#yuju-cat-overview-result-errors-list').empty();
                    if (logsUrl) {
                        $('#yuju-cat-overview-result-logs-btn').attr('href', logsUrl).show();
                    }
                    $('#yuju-cat-overview-result-modal').removeData('pendingMapItems');
                    $('#yuju-cat-overview-result-modal').modal('show');
                }

                $(document).on('yuju:categoryMapped', function (e, payload) {
                    payload = payload || {};
                    var psId = parseInt(payload.id, 10) || 0;
                    if (psId <= 0) {
                        return;
                    }
                    markOverviewCategoryMapped(psId, payload.yujuName || '');
                    if (!payload.fromResultModal) {
                        return;
                    }
                    var pending = $('#yuju-cat-overview-result-modal').data('pendingMapItems') || [];
                    var remaining = [];
                    pending.forEach(function (it) {
                        if (parseInt(it.id, 10) !== psId) {
                            remaining.push(it);
                        }
                    });
                    showResultReadyToCreate(psId, payload.name || '', remaining);
                });

                $(document).on('yuju:restoreResultModal', function () {
                    if ($('#yuju-cat-overview-result-modal').data('pendingMapItems')) {
                        $('#yuju-cat-overview-result-modal').modal('show');
                    }
                });

                $(document).on('click', '#yuju-cat-result-create-now', function () {
                    $('#yuju-cat-overview-result-modal').modal('hide');
                    window.setTimeout(function () {
                        runMass('create');
                    }, 280);
                });

                function runMass(bulkAction) {
                    if (busy) {
                        return;
                    }
                    var ids = selectedCategoryIds();
                    if (!ids.length) {
                        alert('Seleccione al menos una categoría.');
                        return;
                    }
                    var totals = selectedTotals();
                    var unmappedItems = [];
                    $('.yuju-cat-row-check:checked').each(function () {
                        var $tr = $(this).closest('tr');
                        if (String($tr.attr('data-mapped') || '0') !== '1') {
                            unmappedItems.push({
                                id: parseInt($(this).val(), 10) || 0,
                                name: $tr.attr('data-category-name') || ('ID ' + $(this).val())
                            });
                        }
                    });

                    // Create requiere mapeo (propio o por padre). Evita N errores por producto.
                    if (bulkAction === 'create' && unmappedItems.length > 0) {
                        var names = unmappedItems.map(function (it) {
                            return it.name + ' (ID ' + it.id + ')';
                        });
                        showResult(
                            'No se puede crear/sincronizar: hay categorías sin mapeo Yuju (ni herencia de padre):\n\n'
                            + '• ' + names.join('\n• ')
                            + '\n\nUse el botón Mapear debajo, o mapee un padre, y vuelva a intentar.',
                            true,
                            null,
                            unmappedItems
                        );
                        return;
                    }

                    var actionLabel = bulkAction === 'update' ? 'actualizar' : 'crear/sincronizar';
                    var msg = '¿' + actionLabel + ' los productos de ' + ids.length + ' categoría(s)?\n\n'
                        + 'Se procesarán los productos activos únicos (~' + totals.products + ' contando solapes).\n'
                        + 'Si ya existen en Yuju, se actualizarán (no se recrean).\n'
                        + 'Lotes grandes van a la cola del cron.';
                    if (totals.unmapped > 0 && bulkAction === 'update') {
                        msg += '\n\nNota: ' + totals.unmapped + ' categoría(s) sin mapeo; solo se actualizarán productos que ya tengan ID Yuju.';
                    }
                    if (!confirm(msg)) {
                        return;
                    }

                    setBusy(true);
                    $('#yuju-cat-overview-progress').text('Preparando…');
                    fetchAllMultiIds(ids, 'all', function (err, productIds) {
                        if (err) {
                            setBusy(false);
                            showResult(err, true);
                            $('#yuju-cat-overview-progress').text('');
                            return;
                        }
                        if (!productIds || !productIds.length) {
                            setBusy(false);
                            showResult('No hay productos activos en las categorías seleccionadas.', true);
                            $('#yuju-cat-overview-progress').text('');
                            return;
                        }
                        $('#yuju-cat-overview-progress').text('Enviando… 0 / ' + productIds.length);
                        sendChunks(productIds, bulkAction, ids, function (done, total) {
                            $('#yuju-cat-overview-progress').text('Enviando… ' + done + ' / ' + total);
                        }, function (err2, finalResponse) {
                            setBusy(false);
                            $('#yuju-cat-overview-progress').text('');
                            if (err2) {
                                var failMsg = err2;
                                var mapItemsFail = null;
                                // No concatenar URL cruda: el botón "Ver logs" del modal la abre
                                if (finalResponse && finalResponse.error_code === 'category_not_mapped') {
                                    var cid = parseInt(finalResponse.id_category || 0, 10) || 0;
                                    if (!cid && ids.length === 1) {
                                        cid = ids[0];
                                    }
                                    if (cid > 0) {
                                        var $trFail = $('tr[data-category-id="' + cid + '"]');
                                        mapItemsFail = [{
                                            id: cid,
                                            name: $trFail.attr('data-category-name') || ('Categoría ' + cid)
                                        }];
                                        failMsg += '\n\nUse el botón Mapear debajo y vuelva a intentar.';
                                    }
                                }
                                showResult(
                                    failMsg,
                                    true,
                                    finalResponse && finalResponse.details ? finalResponse.details : null,
                                    mapItemsFail,
                                    finalResponse || {}
                                );
                                return;
                            }
                            var okMsg = (finalResponse && finalResponse.message)
                                ? finalResponse.message
                                : ('Proceso finalizado: ' + productIds.length + ' producto(s).');
                            showResult(
                                okMsg,
                                false,
                                finalResponse && finalResponse.details ? finalResponse.details : null,
                                null,
                                finalResponse || {}
                            );
                            setTimeout(function () {
                                window.location.reload();
                            }, 1800);
                        });
                    });
                }

                $('#yuju-cat-mass-create').on('click', function () { runMass('create'); });
                $('#yuju-cat-mass-update').on('click', function () { runMass('update'); });
                refreshMassBar();
            })(typeof jQuery !== 'undefined' ? jQuery : $);
            {/literal}
            </script>
        {/if}
    </div>
</div>

{* Modal de mapeo inline (overview + detalle) *}
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
                    <input type="text" id="yuju-inline-map-ps-label" class="form-control" value="" readonly>
                    <input type="hidden" id="yuju-inline-map-ps-id" value="0">
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
<div id="yuju-inline-map-config" class="hidden" style="display:none"
    data-ajax-url="{$ajax_url|escape:'html':'UTF-8'}"
    data-token="{$token|escape:'html':'UTF-8'}"></div>
<script type="text/javascript">
{literal}
(function ($) {
    var mapCfg = $('#yuju-inline-map-config');
    var mapAjaxUrl = mapCfg.data('ajaxUrl') || mapCfg.attr('data-ajax-url');
    var mapToken = mapCfg.data('token') || mapCfg.attr('data-token');
    var mapFromResultModal = false;
    var mapPsName = '';
    var mapSavedOk = false;
    var mapSavedPayload = null;

    $(document).on('click', '.yuju-open-inline-map-modal', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var psId = parseInt($btn.attr('data-ps-category-id') || $btn.data('psCategoryId') || '0', 10) || 0;
        var psName = $btn.attr('data-ps-category-name') || $btn.data('psCategoryName') || '';
        if (psId <= 0) {
            return;
        }
        mapSavedOk = false;
        mapSavedPayload = null;
        mapPsName = psName;
        mapFromResultModal = String($btn.attr('data-from-result-modal') || '') === '1'
            || ($('#yuju-cat-overview-result-modal').hasClass('in') || $('#yuju-cat-overview-result-modal').is(':visible'));

        // Solo ocultar temporalmente el modal de resultado; se restaura al cerrar el mapeo
        if (mapFromResultModal) {
            $('#yuju-cat-overview-result-modal').modal('hide');
        }

        $('#yuju-inline-map-msg').removeClass('text-danger text-success').addClass('text-muted').text('');
        $('#yuju-inline-map-search').val('');
        $('#yuju-inline-map-ps-id').val(String(psId));
        $('#yuju-inline-map-ps-label').val((psName || ('Categoría ' + psId)) + ' (ID ' + psId + ')');
        $('#yuju-inline-map-save-btn').prop('disabled', false);
        var $sel = $('#yuju-inline-map-select');
        $sel.find('option').show();
        var currentYujuId = String($btn.attr('data-current-yuju-id') || $btn.data('currentYujuId') || '').trim();
        if (currentYujuId && $sel.find('option[value="' + currentYujuId.replace(/"/g, '\\"') + '"]').length) {
            $sel.val(currentYujuId);
        } else {
            $sel.val('');
        }
        window.setTimeout(function () {
            $('#yuju-inline-map-modal').modal('show');
        }, mapFromResultModal ? 320 : 0);
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

    $('#yuju-inline-map-modal').on('hidden.bs.modal', function () {
        var fromResult = mapFromResultModal;
        var saved = mapSavedOk;
        var payload = mapSavedPayload;
        mapFromResultModal = false;
        mapSavedOk = false;
        mapSavedPayload = null;

        if (saved && payload) {
            window.setTimeout(function () {
                $(document).trigger('yuju:categoryMapped', [payload]);
                // Si no venía del modal de resultado (mapeo desde tabla), no hay nada que restaurar
                if (payload.fromResultModal) {
                    // showResultReadyToCreate / showResult ya abren el modal de resultado
                }
            }, 200);
            return;
        }

        // Canceló el mapeo: devolver el modal previo intacto
        if (fromResult) {
            window.setTimeout(function () {
                $(document).trigger('yuju:restoreResultModal');
                $('#yuju-cat-overview-result-modal').modal('show');
            }, 200);
        }
    });

    $('#yuju-inline-map-save-btn').on('click', function () {
        var yujuId = $('#yuju-inline-map-select').val();
        var yujuName = '';
        var $opt = $('#yuju-inline-map-select option:selected');
        if ($opt.length) {
            yujuName = String($opt.text() || '').replace(/\s*\(ID\s+[^)]+\)\s*$/, '').trim();
        }
        var psId = parseInt($('#yuju-inline-map-ps-id').val(), 10) || 0;
        var $msg = $('#yuju-inline-map-msg');
        var $btn = $(this);
        var fromResult = mapFromResultModal;
        var psName = mapPsName;
        if (!psId) {
            $msg.removeClass('text-muted text-success').addClass('text-danger').text('Categoría PrestaShop inválida.');
            return;
        }
        if (!yujuId) {
            $msg.removeClass('text-muted text-success').addClass('text-danger').text('Seleccione una categoría Yuju.');
            return;
        }
        $btn.prop('disabled', true);
        $msg.removeClass('text-danger text-success').addClass('text-muted').text('Guardando mapeo...');
        $.post(mapAjaxUrl, {
            ajax: 1,
            action: 'saveInlineCategoryMapping',
            token: mapToken,
            prestashop_category_id: psId,
            yuju_category_id: yujuId
        }).done(function (res) {
            if (res && res.success) {
                $msg.removeClass('text-muted text-danger').addClass('text-success').text(res.message || 'Mapeo guardado.');

                // Overview: no recargar; al cerrar el modal de mapeo se restaura el de resultado
                if (fromResult || $('#yuju-cat-overview-config').length) {
                    mapSavedOk = true;
                    mapSavedPayload = {
                        id: psId,
                        name: psName,
                        yujuName: yujuName,
                        fromResultModal: fromResult
                    };
                    window.setTimeout(function () {
                        $('#yuju-inline-map-modal').modal('hide');
                        $btn.prop('disabled', false);
                    }, 350);
                    return;
                }

                // Vista detalle: sí recargar para refrescar estado de la categoría
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
})(typeof jQuery !== 'undefined' ? jQuery : $);
{/literal}
</script>

{if $id_category && $detail}
<div id="yuju-cat-bulk-config" class="hidden" style="display:none"
    data-ajax-url="{$ajax_url|escape:'html':'UTF-8'}"
    data-token="{$token|escape:'html':'UTF-8'}"
    data-id-category="{$id_category|intval}"
    data-logs-url="{$yuju_logs_admin_url|escape:'html':'UTF-8'}"
    data-product-status-url="{$product_status_url|escape:'html':'UTF-8'}"
    data-product-status-token="{$product_status_token|escape:'html':'UTF-8'}"
    data-product-admin-url="{$product_admin_url|escape:'html':'UTF-8'}"
    data-category-name="{$detail.name|escape:'html':'UTF-8'}"></div>
<script type="text/javascript">
{literal}
(function ($) {
    var cfg = $('#yuju-cat-bulk-config');
    var ajaxUrl = cfg.data('ajaxUrl') || cfg.attr('data-ajax-url');
    var token = cfg.data('token');
    var idCategory = parseInt(cfg.data('idCategory') || cfg.attr('data-id-category'), 10) || 0;
    var logsUrl = cfg.data('logsUrl') || cfg.attr('data-logs-url');
    var productStatusUrl = cfg.data('productStatusUrl') || cfg.attr('data-product-status-url');
    var productStatusToken = cfg.data('productStatusToken') || cfg.attr('data-product-status-token');
    var productAdminUrl = cfg.data('productAdminUrl') || cfg.attr('data-product-admin-url');
    var categoryName = cfg.data('categoryName') || cfg.attr('data-category-name') || '';
    var currentSegment = 'all';
    var currentPage = 1;
    var perPage = 25;
    var lastLoadedProducts = {};
    var catHistoryPage = 1;

    if (window.YujuProductInfoModal) {
        window.YujuProductInfoModal.configure({
            ajaxUrl: productStatusUrl,
            token: productStatusToken,
            productAdminUrl: productAdminUrl
        });
        window.YujuProductInfoModal.setOnChanged(function () {
            if (typeof loadProducts === 'function') {
                loadProducts();
            }
        });
        window.YujuProductInfoModal.bind();
    }


    /**
     * URL de edición de producto compatible con PrestaShop 8 (products-v2)
     * y con el BO legacy.
     */
    function buildProductAdminEditUrl(productId) {
        productId = parseInt(productId, 10) || 0;
        if (!productId || !productAdminUrl) {
            return '#';
        }
        var raw = String(productAdminUrl);
        var qIndex = raw.indexOf('?');
        var qs = qIndex >= 0 ? raw.substring(qIndex) : '';
        qs = qs
            .replace(/([?&])(id_product|updateproduct)=[^&]*/g, '$1')
            .replace(/[?&]$/, '')
            .replace(/[?&]{2,}/g, '?')
            .replace(/\?&/, '?');
        if (qs === '?' || qs === '&') {
            qs = '';
        }

        // PS 8 nuevo editor: /sell/catalog/products-v2/{id}/edit
        var v2Idx = raw.indexOf('/sell/catalog/products-v2');
        if (v2Idx !== -1) {
            var v2Base = raw.substring(0, v2Idx + '/sell/catalog/products-v2'.length).replace(/\/$/, '');
            return v2Base + '/' + productId + '/edit' + qs;
        }

        // getAdminLink suele devolver /sell/catalog/products → convertir a products-v2
        var v1Idx = raw.indexOf('/sell/catalog/products');
        if (v1Idx !== -1) {
            var before = raw.substring(0, v1Idx);
            return before + '/sell/catalog/products-v2/' + productId + '/edit' + qs;
        }

        var sep = raw.indexOf('?') >= 0 ? '&' : '?';
        return raw + sep + 'id_product=' + encodeURIComponent(String(productId)) + '&updateproduct=1';
    }

    function segmentLabel(seg) {
        var m = { all: 'todos', errors: 'error', queued: 'en cola', not_sent: 'no enviados', pending: 'en cola',
            in_progress: 'en espera', stale_creating: 'en espera ≥1h', synced_ok: 'sincronizado', invalid: 'no enviables',
            orphan_synced: 'no enviados', disabled: 'deshabilitados' };
        return m[seg] || seg;
    }

    function hasYujuId(p) {
        var y = String(p && p.yuju_product_id ? p.yuju_product_id : '').trim();
        return y !== '' && y !== '0' && y.toLowerCase() !== 'null';
    }

    function isSkuSimpleNotEditableError(err) {
        return String(err || '').toLowerCase().indexOf('sku simple is not editable') !== -1;
    }

    /** Icono de error/aviso con tooltip (no ensucia el label). */
    function statusWarnIconHtml(pid, err, fallbackTitle, opts) {
        opts = opts || {};
        var tip = String(err || fallbackTitle || 'Ver detalle del error').trim();
        var icon = opts.icon || 'icon-exclamation-circle';
        var color = opts.color || '#d9534f';
        return '<i class="' + icon + ' yuju-cat-status-open"'
            + ' style="color:' + color + ';cursor:pointer;font-size:14px;margin-left:5px;vertical-align:middle;"'
            + ' data-id="' + (parseInt(pid, 10) || 0) + '" data-focus-history="1"'
            + ' title="' + escapeHtml(tip) + '"></i>';
    }

    /** Misma presentación visual que AdminYujuProductStatus */
    function renderStatusCell(p) {
        var st = String(p && p.yuju_status ? p.yuju_status : '');
        var err = String(p && p.last_error ? p.last_error : '').trim();
        var hasId = hasYujuId(p);
        var pid = parseInt(p.id_product, 10) || 0;
        // Un solo atributo class (dos class= hace que el navegador ignore el segundo)
        var openAttrs = ' style="cursor:pointer;" data-id="' + pid + '" data-focus-history="1"';

        if ((st === 'synced' || st === 'synced_with_warnings' || st === 'synced_with_errors') && !hasId) {
            return '<span class="label label-default"><i class="icon-minus"></i> No enviado</span>';
        }
        if (st === 'synced') {
            return '<span class="label label-success yuju-cat-status-open"' + openAttrs + ' title="Ver historial e ID en Yuju">'
                + '<i class="icon-check"></i> Sincronizado</span>';
        }
        if (st === 'synced_with_warnings') {
            return '<span class="label label-success yuju-cat-status-open"' + openAttrs + ' title="Ver historial e ID en Yuju">'
                + '<i class="icon-check"></i> Sincronizado</span>'
                + statusWarnIconHtml(pid, err, 'Tiene advertencias', { icon: 'icon-exclamation-triangle', color: '#f0ad4e' });
        }
        if (st === 'synced_with_errors') {
            var htmlSw = '<span class="label label-success yuju-cat-status-open"' + openAttrs + ' title="Ver historial e ID en Yuju">'
                + '<i class="icon-check"></i> En Yuju</span>'
                + statusWarnIconHtml(pid, err, 'Error en última operación');
            // SKU simple: solo icono+tooltip. Otros errores: también una línea corta debajo.
            if (err && !isSkuSimpleNotEditableError(err)) {
                htmlSw += '<div class="text-danger" style="font-size:11px;margin-top:3px;max-width:280px;line-height:1.3;">'
                    + escapeHtml(err.length > 90 ? err.substring(0, 90) + '…' : err) + '</div>';
            }
            return htmlSw;
        }
        if (st === 'error') {
            if (hasId) {
                var htmlEy = '<span class="label label-warning yuju-cat-status-open"' + openAttrs
                    + ' title="Producto en Yuju; la última operación falló. Clic para ver historial">'
                    + '<i class="icon-cloud"></i> En Yuju · Error</span>'
                    + statusWarnIconHtml(pid, err, 'Falló la última sincronización');
                if (err && !isSkuSimpleNotEditableError(err)) {
                    htmlEy += '<div class="text-danger" style="font-size:11px;margin-top:3px;max-width:280px;line-height:1.3;">'
                        + escapeHtml(err.length > 90 ? err.substring(0, 90) + '…' : err) + '</div>';
                }
                return htmlEy;
            }
            return '<span class="label label-danger yuju-cat-status-open"' + openAttrs
                + ' title="' + escapeHtml(err || 'Error en sincronización') + '">'
                + '<i class="icon-remove"></i> Error</span>'
                + (err && !isSkuSimpleNotEditableError(err)
                    ? ('<div class="text-danger" style="font-size:11px;margin-top:3px;max-width:280px;line-height:1.3;">'
                        + escapeHtml(err.length > 90 ? err.substring(0, 90) + '…' : err) + '</div>')
                    : (isSkuSimpleNotEditableError(err) ? statusWarnIconHtml(pid, err, err) : ''));
        }
        if (st === 'queued') {
            return '<span class="label label-info yuju-cat-status-open"' + openAttrs + ' title="Ver detalle de proceso en cola">'
                + '<i class="icon-list"></i> En Cola</span>';
        }
        if (st === 'creating_in_yuju') {
            return '<span class="label label-info yuju-cat-status-open" style="cursor:pointer;" data-id="' + pid + '"'
                + ' title="' + escapeHtml(err || 'Enviado a Yuju; en espera de respuesta (webhook). Clic para ver acciones.') + '">'
                + '<i class="icon-time"></i> En espera de respuesta</span>';
        }
        if (st === 'updating_in_yuju') {
            return '<span class="label label-warning" title="' + escapeHtml(err || 'Actualizando diferencias en Yuju') + '">'
                + '<i class="icon-refresh"></i> Actualizando</span>';
        }
        if (st === 'deleting_in_yuju') {
            return '<span class="label label-warning" title="' + escapeHtml(err || 'Esperando webhook product-deleted') + '">'
                + '<i class="icon-trash"></i> Eliminando…</span>';
        }
        return '<span class="label label-default"><i class="icon-minus"></i> No enviado</span>';
    }

    function renderStatusLabelForModal(p) {
        var st = String(p && p.yuju_status ? p.yuju_status : '');
        var err = String(p && p.last_error ? p.last_error : '').trim();
        var hasId = hasYujuId(p);

        if ((st === 'synced' || st === 'synced_with_warnings' || st === 'synced_with_errors') && !hasId) {
            return '<span class="label label-default"><i class="icon-minus"></i> No enviado</span>';
        }
        if (st === 'synced') {
            return '<span class="label label-success"><i class="icon-check"></i> Sincronizado</span>';
        }
        if (st === 'synced_with_warnings') {
            return '<span class="label label-success"><i class="icon-check"></i> Sincronizado</span>'
                + '<i class="icon-exclamation-triangle" style="color:#f0ad4e;font-size:14px;margin-left:5px;" title="' + escapeHtml(err || 'Tiene advertencias') + '"></i>';
        }
        if (st === 'synced_with_errors') {
            return '<span class="label label-success"><i class="icon-check"></i> En Yuju</span>'
                + '<i class="icon-exclamation-circle" style="color:#d9534f;font-size:14px;margin-left:5px;" title="' + escapeHtml(err || 'Error en última operación') + '"></i>';
        }
        if (st === 'error') {
            if (hasId) {
                return '<span class="label label-warning" title="Producto en Yuju; la última operación falló">'
                    + '<i class="icon-cloud"></i> En Yuju · Error</span>'
                    + '<i class="icon-exclamation-circle" style="color:#d9534f;font-size:14px;margin-left:5px;vertical-align:middle;"'
                    + ' title="' + escapeHtml(err || 'Falló la última sincronización') + '"></i>';
            }
            return '<span class="label label-danger" title="' + escapeHtml(err || 'Error en sincronización') + '"><i class="icon-remove"></i> Error</span>';
        }
        if (st === 'queued') {
            return '<span class="label label-info"><i class="icon-list"></i> En Cola</span>';
        }
        if (st === 'creating_in_yuju') {
            return '<span class="label label-info"><i class="icon-time"></i> En espera de respuesta</span>';
        }
        if (st === 'updating_in_yuju') {
            return '<span class="label label-warning"><i class="icon-refresh"></i> Actualizando</span>';
        }
        if (st === 'deleting_in_yuju') {
            return '<span class="label label-warning"><i class="icon-trash"></i> Eliminando…</span>';
        }
        return '<span class="label label-default"><i class="icon-minus"></i> No enviado</span>';
    }

    function renderInfoCell(p) {
        return '<td class="text-center">'
            + '<i class="icon-info-sign yuju-cat-product-info" style="color:#2196f3;cursor:pointer;font-size:16px;" '
            + 'data-id-product="' + parseInt(p.id_product, 10) + '" title="Ver información del producto"></i>'
            + '</td>';
    }

    function renderActionsCell(p) {
        var pid = parseInt(p.id_product, 10) || 0;
        if (hasYujuId(p)) {
            return '<td class="text-center">'
                + '<i class="icon-trash yuju-cat-delete-yuju" style="color:#dc3545;cursor:pointer;font-size:16px;" '
                + 'data-id-product="' + pid + '" data-reference="' + escapeHtml(p.reference || '') + '" '
                + 'title="Eliminar de Yuju"></i>'
                + '</td>';
        }
        return '<td class="text-center"><span class="text-muted" title="Sin ID de producto en Yuju">-</span></td>';
    }

    function refreshPageSelectionUi() {
        var $cbs = $('#yuju-cat-bulk-tbody .yuju-cat-product-cb');
        var total = $cbs.length;
        var checked = $cbs.filter(':checked').length;
        $('#yuju-cat-page-sel-count').text(checked + ' producto(s)');
        $('#yuju-cat-select-all-products').prop('checked', total > 0 && checked === total);
        $('#yuju-cat-select-all-products').prop('indeterminate', checked > 0 && checked < total);
    }

    function selectedPageProductIds() {
        var ids = [];
        $('#yuju-cat-bulk-tbody .yuju-cat-product-cb:checked').each(function () {
            var id = parseInt($(this).val(), 10) || 0;
            if (id > 0) {
                ids.push(id);
            }
        });
        return ids;
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

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

    function loadProducts() {
        $('#yuju-cat-bulk-tbody').html('<tr><td colspan="7" class="text-center"><i class="icon-refresh icon-spin"></i> Cargando…</td></tr>');
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
                $('#yuju-cat-bulk-tbody').html('<tr><td colspan="7" class="text-center">Error al cargar.</td></tr>');
                return;
            }
            var rows = res.products || [];
            lastLoadedProducts = {};
            var html = '';
            if (!rows.length) {
                html = '<tr><td colspan="7">No hay productos en este segmento.</td></tr>';
            } else {
                rows.forEach(function (p) {
                    var pid = parseInt(p.id_product, 10);
                    lastLoadedProducts[pid] = p;
                    var syncTxt = (p.last_sync_at || '').toString().slice(0, 19);
                    var syncHtml = syncTxt
                        ? ('<small>' + escapeHtml(syncTxt) + '</small>')
                        : '<span class="text-muted">-</span>';
                    html += '<tr data-product-id="' + pid + '">'
                        + '<td class="text-center"><input type="checkbox" class="yuju-cat-product-cb" value="' + pid + '"></td>'
                        + renderInfoCell(p)
                        + '<td class="yuju-ps-click-toggle">' + escapeHtml(p.reference || '') + '</td>'
                        + '<td class="yuju-ps-click-toggle"><strong>' + escapeHtml(p.name || '') + '</strong></td>'
                        + '<td class="text-center">' + renderStatusCell(p) + '</td>'
                        + '<td class="text-center">' + syncHtml + '</td>'
                        + renderActionsCell(p)
                        + '</tr>';
                });
            }
            $('#yuju-cat-bulk-tbody').html(html);
            refreshPageSelectionUi();
            renderPager(res.pagination || {});
        }).fail(function () {
            $('#yuju-cat-bulk-tbody').html('<tr><td colspan="7">Error de red.</td></tr>');
        });
    }

    /**
     * Abre el MISMO modal canónico que Product Status (YujuProductInfoModal).
     * Historial / Mandar de nuevo / Buscar webhook viven solo ahí.
     */
    function showCatProductInfo(p, options) {
        if (!p) {
            return;
        }
        options = options || {};
        if (!window.YujuProductInfoModal) {
            alert('No se cargó el modal compartido de producto (yuju_product_info_modal.js).');
            return;
        }
        var processHint = '';
        var st = String(p.yuju_status || '');
        var err = String(p.last_error || '').trim();
        if (st === 'creating_in_yuju') {
            processHint = 'En espera de respuesta de Yuju (creación).';
        } else if (st === 'updating_in_yuju') {
            processHint = 'Actualizando en Yuju…';
        } else if (st === 'deleting_in_yuju') {
            processHint = 'Eliminando en Yuju…';
        } else if (st === 'queued' || st === 'syncing') {
            processHint = 'En cola / sincronizando…';
        } else if (st === 'error' && hasYujuId(p)) {
            processHint = 'Este producto SÍ está en Yuju, pero la última operación falló. Revise el historial.';
        } else if (st === 'synced_with_errors') {
            processHint = 'Producto en Yuju con error en la última sincronización. Revise el historial.';
        } else if (st === 'error') {
            processHint = 'La última sincronización falló. Revise el historial para el detalle.';
        }

        window.YujuProductInfoModal.open({
            id_product: p.id_product,
            reference: p.reference || '',
            name: p.name || '',
            category: categoryName || '',
            yuju_product_id: p.yuju_product_id || '',
            id_image: p.id_image || '',
            processHint: processHint,
            sync_status: st,
            last_error: err
        }, {
            focusHistory: !!options.focusHistory
        });
    }

    $('#yuju-cat-bulk-tbody').on('click', '.yuju-cat-product-info', function (e) {
        e.preventDefault();
        var pid = parseInt($(this).data('idProduct') || $(this).attr('data-id-product'), 10);
        showCatProductInfo(lastLoadedProducts[pid]);
    });

    $('#yuju-cat-bulk-tbody').on('click', '.yuju-cat-status-open', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var pid = parseInt($(this).attr('data-id') || '0', 10);
        var focusHistory = String($(this).attr('data-focus-history') || '') === '1';
        var p = lastLoadedProducts[pid];
        if (!p) {
            return;
        }
        // Si el label trae el error en title y el producto no lo tiene cargado, usarlo
        var titleErr = String($(this).attr('title') || '').trim();
        if (titleErr && !String(p.last_error || '').trim()) {
            p = $.extend({}, p, { last_error: titleErr });
        }
        showCatProductInfo(p, { focusHistory: focusHistory || String(p.yuju_status || '') === 'error' });
    });

    $('#yuju-cat-bulk-tbody').on('change', '.yuju-cat-product-cb', refreshPageSelectionUi);
    $('#yuju-cat-select-all-products').on('change', function () {
        var on = $(this).is(':checked');
        $('#yuju-cat-bulk-tbody .yuju-cat-product-cb').prop('checked', on);
        refreshPageSelectionUi();
    });

    $('#yuju-cat-bulk-tbody').on('click', '.yuju-cat-delete-yuju', function (e) {
        e.preventDefault();
        var pid = parseInt($(this).attr('data-id-product') || '0', 10);
        var ref = $(this).attr('data-reference') || '';
        if (!pid) {
            return;
        }
        if (!window.confirm('¿Eliminar de Yuju el producto ' + (ref || ('ID ' + pid)) + '?')) {
            return;
        }
        $('#yuju-cat-bulk-progress').text('Eliminando…');
        sendChunks([pid], 'delete', true, function () {}, function (err2, finalResponse) {
            $('#yuju-cat-bulk-progress').text('');
            if (err2) {
                showBulkResultModal(finalResponse, err2, true);
                return;
            }
            showBulkResultModal(finalResponse, 'Producto eliminado de Yuju.', false);
            loadProducts();
        });
    });

    function setSegmentFilter(segment) {
        segment = String(segment || 'all');
        currentSegment = segment;
        currentPage = 1;
        $('#yuju-cat-segments button').removeClass('active');
        $('#yuju-cat-segments button[data-segment="' + segment + '"]').addClass('active');
        $('#yuju-cat-kpi-filters .yuju-kpi--filter').removeClass('active');
        var $kpi = $('#yuju-cat-kpi-filters .yuju-kpi--filter[data-segment="' + segment + '"]');
        if ($kpi.length) {
            $kpi.addClass('active');
        } else if (segment === 'all') {
            $('#yuju-cat-kpi-filters .yuju-kpi--filter[data-segment="all"]').addClass('active');
        }
        loadProducts();
    }

    $('#yuju-cat-segments button').on('click', function () {
        setSegmentFilter($(this).data('segment'));
    });

    $('#yuju-cat-kpi-filters').on('click', '.yuju-kpi--filter', function (e) {
        e.preventDefault();
        setSegmentFilter($(this).data('segment'));
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

    function fetchAllIdsForSegment(cb, segmentOverride) {
        var offset = 0;
        var all = [];
        var segment = segmentOverride || currentSegment;
        function step() {
            $.post(ajaxUrl, {
                ajax: 1,
                action: 'categoryBulkProductIds',
                token: token,
                id_category: idCategory,
                segment: segment,
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

    /** Reenvío create (≥1h) sobre seleccionados. */
    function sendResendChunks(productIds, onProgress, done) {
        var chunk = 40;
        var i = 0;
        var totals = { success: 0, skipped: 0, errors: 0 };
        var allErrors = [];
        var lastLogs = logsUrl || '';

        onProgress(0, productIds.length, totals);

        function next() {
            if (i >= productIds.length) {
                var msg = totals.success + ' reenviado(s). '
                    + totals.skipped + ' omitido(s) (<1h o no elegibles). '
                    + totals.errors + ' error(es).';
                done(null, {
                    success: totals.errors === 0,
                    message: msg,
                    logs_url: lastLogs,
                    errors: allErrors,
                    details: {
                        success: totals.success,
                        skipped: totals.skipped,
                        errors: totals.errors,
                        bulk_action: 'resend_stale'
                    }
                });
                return;
            }
            var part = productIds.slice(i, i + chunk);
            onProgress(i, productIds.length, totals);
            $.post(ajaxUrl, {
                ajax: 1,
                action: 'categoryBulkResendChunk',
                token: token,
                id_category: idCategory,
                product_ids_json: JSON.stringify(part)
            }).done(function (res) {
                if (!res) {
                    done('Error en lote de reenvío', null);
                    return;
                }
                if (res.logs_url) {
                    lastLogs = res.logs_url;
                }
                if (res.success === false && !(res.details)) {
                    done(res.message || 'Error en lote de reenvío', res);
                    return;
                }
                var d = res.details || {};
                totals.success += parseInt(d.success, 10) || 0;
                totals.skipped += parseInt(d.skipped, 10) || 0;
                totals.errors += parseInt(d.errors, 10) || 0;
                if (res.errors && res.errors.length) {
                    allErrors = allErrors.concat(res.errors);
                }
                i += part.length;
                onProgress(Math.min(i, productIds.length), productIds.length, totals);
                next();
            }).fail(function () {
                done('Error de red al reenviar lote', {
                    success: false,
                    message: 'Error de red al reenviar lote',
                    logs_url: lastLogs,
                    errors: allErrors,
                    details: totals
                });
            });
        }
        next();
    }

    function setBulkBusy(busy, title) {
        $('.yuju-cat-bulk-act').prop('disabled', !!busy);
        var $box = $('#yuju-cat-bulk-progress-box');
        if (busy) {
            $('#yuju-cat-bulk-progress-title').text(title || 'Procesando…');
            $box.show();
        } else {
            $box.hide();
            $('#yuju-cat-bulk-progress-bar').css('width', '0%').text('0%');
            $('#yuju-cat-bulk-progress-detail').text('');
            $('#yuju-cat-bulk-progress').text('');
        }
    }

    function updateBulkProgress(done, total, totals) {
        total = parseInt(total, 10) || 0;
        done = Math.min(parseInt(done, 10) || 0, total);
        var pct = total > 0 ? Math.round((done / total) * 100) : 0;
        $('#yuju-cat-bulk-progress-bar').css('width', pct + '%').text(pct + '%');
        var detail = done + ' / ' + total;
        if (totals) {
            detail += ' · ok: ' + (totals.success || 0)
                + ' · omitidos: ' + (totals.skipped || 0)
                + ' · errores: ' + (totals.errors || 0);
        }
        $('#yuju-cat-bulk-progress-detail').text(detail);
        $('#yuju-cat-bulk-progress').html('<i class="icon-spinner icon-spin"></i> ' + detail);
    }

    function truncateText(str, maxLen) {
        var t = String(str == null ? '' : str).trim();
        maxLen = parseInt(maxLen, 10) || 20;
        if (t.length <= maxLen) {
            return t;
        }
        return t.substring(0, maxLen) + '…';
    }

    function normalizeBulkErrorItem(raw) {
        if (raw && typeof raw === 'object') {
            return {
                id_product: parseInt(raw.id_product || raw.id || 0, 10) || 0,
                reference: String(raw.reference || ''),
                name: String(raw.name || ''),
                message: String(raw.message || raw.last_error || raw.reason || '')
            };
        }
        var s = String(raw || '');
        var m = s.match(/^Producto ID\s+(\d+)\s*(?:\[([^\]]*)\])?(?:\s*[—\-]\s*([^:]+))?\s*:\s*(.*)$/i);
        if (m) {
            return {
                id_product: parseInt(m[1], 10) || 0,
                reference: String(m[2] || '').trim(),
                name: String(m[3] || '').trim(),
                message: String(m[4] || '').trim()
            };
        }
        var m2 = s.match(/producto ID\s+(\d+)/i);
        return {
            id_product: m2 ? (parseInt(m2[1], 10) || 0) : 0,
            reference: '',
            name: '',
            message: s
        };
    }

    function renderBulkErrorProductCell(item) {
        var pid = parseInt(item.id_product, 10) || 0;
        var ref = String(item.reference || '').trim();
        var name = truncateText(item.name || '', 20);
        var parts = [];
        if (pid > 0) {
            parts.push('<strong>#' + escapeHtml(String(pid)) + '</strong>');
        } else {
            parts.push('<strong>—</strong>');
        }
        if (ref) {
            parts.push('<span title="' + escapeHtml(ref) + '">' + escapeHtml(ref) + '</span>');
        }
        if (name) {
            parts.push('<span class="text-muted" title="' + escapeHtml(String(item.name || '')) + '">'
                + escapeHtml(name) + '</span>');
        }
        var html = '<div style="display:flex; align-items:center; flex-wrap:nowrap; gap:8px; white-space:nowrap;">'
            + '<span>' + parts.join(' · ') + '</span>';
        if (pid > 0 && productAdminUrl) {
            var url = buildProductAdminEditUrl(pid);
            html += '<a class="btn btn-xs btn-default" href="' + escapeHtml(url) + '" target="_blank" rel="noopener" '
                + 'title="Abrir producto en nueva pestaña" style="flex-shrink:0;">'
                + '<i class="icon-external-link"></i></a>';
        }
        html += '</div>';
        return html;
    }

    function renderBulkErrorRow(raw) {
        var item = normalizeBulkErrorItem(raw);
        return '<tr>'
            + '<td>' + renderBulkErrorProductCell(item) + '</td>'
            + '<td>' + escapeHtml(item.message || '') + '</td>'
            + '</tr>';
    }

    function showBulkResultModal(response, fallbackMessage, isError) {
        var $summary = $('#yuju-cat-bulk-result-summary');
        var $extra = $('#yuju-cat-bulk-result-extra');
        var $wrap = $('#yuju-cat-bulk-result-errors-wrap');
        var $tbody = $('#yuju-cat-bulk-result-errors-tbody');
        var $logsBtn = $('#yuju-cat-bulk-result-logs-btn');
        var msg = (response && response.message) ? String(response.message) : String(fallbackMessage || '');
        var details = response && response.details ? response.details : {};
        var errors = [];
        if (response && response.errors && response.errors.length) {
            response.errors.forEach(function (line) {
                errors.push(line);
            });
        }
        if ((!errors || !errors.length) && response && response.previous_error_products) {
            (response.previous_error_products || []).forEach(function (p) {
                errors.push({
                    id_product: p.id_product,
                    reference: p.reference || '',
                    name: p.name || '',
                    message: p.last_error || p.message || 'Error previo sin detalle'
                });
            });
        }
        if (response && response.skipped_products) {
            (response.skipped_products || []).forEach(function (p) {
                errors.push({
                    id_product: p.id_product,
                    reference: p.reference || '',
                    name: p.name || '',
                    message: p.last_error || p.reason || p.message || 'Omitido'
                });
            });
        }
        $summary
            .removeClass('alert-info alert-success alert-warning alert-danger')
            .addClass(isError ? 'alert-danger' : 'alert-success')
            .css('white-space', 'pre-wrap')
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
                html += renderBulkErrorRow(line);
            });
            $tbody.html(html);
            $wrap.show();
        } else {
            $tbody.html('');
            $wrap.hide();
        }

        var respLogs = (response && response.logs_url) ? response.logs_url : (logsUrl || '');
        if (respLogs) {
            $logsBtn.attr('href', respLogs).show();
        } else {
            $logsBtn.hide();
        }

        $('#yuju-cat-bulk-result-modal').modal('show');
    }

    $('.yuju-cat-bulk-act').on('click', function () {
        var bulkAction = $(this).data('action');
        var ids = selectedPageProductIds();

        if (!ids.length) {
            showBulkResultModal(null, 'Seleccione al menos un producto en la tabla (checkbox).', true);
            return;
        }

        if (bulkAction === 'resend_stale') {
            if (!window.confirm('¿Reenviar a Yuju los ' + ids.length + ' producto(s) seleccionado(s) en espera ≥1h sin webhook?')) {
                return;
            }
            setBulkBusy(true, 'Enviando de nuevo…');
            updateBulkProgress(0, ids.length, { success: 0, skipped: 0, errors: 0 });
            sendResendChunks(ids, function (done, total, totals) {
                updateBulkProgress(done, total, totals || null);
            }, function (err2, finalResponse) {
                setBulkBusy(false);
                var hasErr = !!(err2 || (finalResponse && finalResponse.details && parseInt(finalResponse.details.errors, 10) > 0));
                showBulkResultModal(finalResponse, err2 || (finalResponse && finalResponse.message) || 'Reenvío finalizado.', hasErr);
                loadProducts();
            });
            return;
        }

        if (bulkAction === 'delete') {
            if (!window.confirm('¿Eliminar en Yuju los ' + ids.length + ' producto(s) seleccionado(s)? Esta acción no se puede deshacer en marketplaces.')) {
                return;
            }
        } else {
            var actionLabel = bulkAction === 'update' ? 'actualizar' : 'crear/encolar';
            if (!window.confirm('¿Aplicar "' + actionLabel + '" a los ' + ids.length + ' producto(s) seleccionado(s)?')) {
                return;
            }
        }

        setBulkBusy(true, bulkAction === 'delete' ? 'Eliminando…' : 'Enviando…');
        updateBulkProgress(0, ids.length, null);
        sendChunks(ids, bulkAction, bulkAction === 'delete', function (done, total) {
            updateBulkProgress(done, total, null);
        }, function (err2, finalResponse) {
            setBulkBusy(false);
            if (err2) {
                showBulkResultModal(finalResponse, err2, true);
            } else {
                showBulkResultModal(finalResponse, 'Proceso de lotes finalizado.', false);
            }
            loadProducts();
        });
    });

    loadProducts();

})(typeof jQuery !== 'undefined' ? jQuery : $);
{/literal}
</script>
{/if}
{/block}
