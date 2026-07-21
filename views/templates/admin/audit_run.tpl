{*
* Auditoría visual de productos Yuju vs PrestaShop
*}
{extends file="./layout.tpl"}

{block name="content"}
<div class="panel yuju-audit-run-panel yuju-module">
    <div class="panel-heading">
        <i class="icon-search"></i> Auditoría de productos
        <span class="badge" style="margin-left:8px;">PS &rarr; Yuju</span>
        <span id="yuju-audit-timer" class="badge" style="margin-left:8px; display:none;">⏱ 0m 00s</span>
    </div>
    <div class="panel-body">
        {if isset($audit_load_error) && $audit_load_error}
            <div class="alert alert-warning">
                <strong>Aviso al cargar infraestructura:</strong> {$audit_load_error|escape:'html':'UTF-8'}
            </div>
        {/if}

        {if !$audit_ui_enabled}
            <div class="alert alert-warning">
                La interfaz gráfica está deshabilitada.
                Actívala en <a href="{$config_link|escape:'html':'UTF-8'}">Configuración &rarr; Auditoría</a>.
            </div>
        {else}
            <div class="row" style="margin-bottom:12px;">
                <div class="col-md-7">
                    <label class="checkbox-inline">
                        <input type="checkbox" id="yuju-audit-field-stock" {if $audit_config.stock}checked="checked"{/if}> Stock
                    </label>
                    <label class="checkbox-inline">
                        <input type="checkbox" id="yuju-audit-field-price" {if $audit_config.price}checked="checked"{/if}> Precio
                    </label>
                    <label class="checkbox-inline">
                        <input type="checkbox" id="yuju-audit-field-images" {if $audit_config.images}checked="checked"{/if}> Imágenes
                    </label>
                    <label class="checkbox-inline" title="Si está activo, cada diferencia se corrige en Yuju (API). Desactivado = solo comparar JSON local vs PS.">
                        <input type="checkbox" id="yuju-audit-apply-fixes"> Corregir en Yuju
                    </label>
                </div>
                <div class="col-md-5 text-right">
                    <a href="{$monitoring_link|escape:'html':'UTF-8'}#yuju-gral-reports" class="btn btn-default btn-sm">
                        <i class="icon-bar-chart"></i> Histórico / visualizador
                    </a>
                </div>
            </div>
            <div class="well well-sm" style="margin-bottom:15px;">
                <div class="row">
                    <div class="{if $gral_reports|@count > 0}col-md-6{else}col-md-12{/if}">
                        <p style="margin:0 0 8px 0;">
                            <strong>Nueva auditoría</strong>
                            <small class="text-muted" id="yuju-audit-quota-label">
                                Ofertas: cada {$offer_quota.interval_hours|default:12|intval}h
                                · General: {$gral_quota.used|intval}/{$gral_quota.max|intval} UTC
                            </small>
                        </p>
                        <button type="button" class="btn btn-primary" id="yuju-audit-start-btn">
                            <i class="icon-cloud-download"></i> Solicitar reporte y auditar
                        </button>
                        <button type="button" class="btn btn-warning" id="yuju-audit-pause-btn" style="display:none;">
                            <i class="icon-pause"></i> Pausar
                        </button>
                        <button type="button" class="btn btn-success" id="yuju-audit-resume-btn" style="display:none;">
                            <i class="icon-play"></i> Continuar
                        </button>
                    </div>
                    {if $saved_reports|@count > 0 || $gral_reports|@count > 0}
                        <div class="col-md-6">
                            <p style="margin:0 0 8px 0;"><strong>Reanalizar reporte guardado</strong></p>
                            <div class="input-group">
                                <select id="yuju-audit-report-select" class="form-control">
                                    <option value="">— Elija un reporte completado —</option>
                                    {assign var=reports_list value=$saved_reports|default:$gral_reports}
                                    {foreach from=$reports_list item=rep}
                                        <option value="{$rep.id|intval}" data-type="{$rep.report_type|default:'gral'|escape:'html':'UTF-8'}">
                                            #{$rep.id|intval}
                                            · {if isset($rep.report_type) && $rep.report_type == 'offer'}OFERTA{else}GENERAL{/if}
                                            · {$rep.requested_at|escape:'html':'UTF-8'}
                                            · {$rep.products_count|intval} prod.
                                        </option>
                                    {/foreach}
                                </select>
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-warning" id="yuju-audit-reanalyze-btn">
                                        <i class="icon-refresh"></i> Reanalizar
                                    </button>
                                </span>
                            </div>
                        </div>
                    {/if}
                </div>
            </div>

            <div id="yuju-audit-progress-wrap" style="display:none; margin-bottom:15px;">
                <div class="progress" style="height:24px; margin-bottom:8px;">
                    <div id="yuju-audit-progress-bar" class="progress-bar progress-bar-info progress-bar-striped active"
                         role="progressbar" style="width:0%; min-width:2em; line-height:24px; font-size:12px;">0%</div>
                </div>
                <p id="yuju-audit-progress-text" class="help-block" style="margin:0;">Preparando...</p>
            </div>

            <div class="row yuju-audit-kpi" id="yuju-audit-kpis" style="display:none; margin-bottom:15px;">
                <div class="col-sm-2">
                    <div class="well well-sm text-center yuju-kpi-filter" data-filter="total" style="cursor:pointer;" title="Ver todos">
                        <strong id="kpi-total">0</strong><br><small>Total</small>
                    </div>
                </div>
                <div class="col-sm-2">
                    <div class="well well-sm text-center text-success yuju-kpi-filter" data-filter="matched" style="cursor:pointer;" title="Filtrar sincronizados">
                        <strong id="kpi-matched">0</strong><br><small>Sincronizados</small>
                    </div>
                </div>
                <div class="col-sm-2">
                    <div class="well well-sm text-center text-warning yuju-kpi-filter" data-filter="diff" style="cursor:pointer;" title="Filtrar con diferencias">
                        <strong id="kpi-diff">0</strong><br><small>Con diferencias</small>
                    </div>
                </div>
                <div class="col-sm-2">
                    <div class="well well-sm text-center text-primary yuju-kpi-filter" data-filter="diff_fixed" style="cursor:pointer;" title="Filtrar corregidos">
                        <strong id="kpi-fixed">0</strong><br><small>Corregidos</small>
                    </div>
                </div>
                <div class="col-sm-2">
                    <div class="well well-sm text-center text-danger yuju-kpi-filter" data-filter="diff_error" style="cursor:pointer;" title="Filtrar errores">
                        <strong id="kpi-errors">0</strong><br><small>Errores</small>
                    </div>
                </div>
                <div class="col-sm-2">
                    <div class="well well-sm text-center yuju-kpi-filter" data-filter="not_found" style="cursor:pointer;" title="Filtrar no encontrados">
                        <strong id="kpi-notfound">0</strong><br><small>No encontrados</small>
                    </div>
                </div>
            </div>

            <div class="row" id="yuju-audit-table-tools" style="display:none; margin-bottom:10px;">
                <div class="col-sm-6">
                    <div class="input-group">
                        <input type="text" id="yuju-audit-search" class="form-control" placeholder="Buscar SKU o nombre…">
                        <span class="input-group-btn">
                            <button type="button" class="btn btn-default" id="yuju-audit-search-btn"><i class="icon-search"></i></button>
                        </span>
                    </div>
                    <small class="text-muted" id="yuju-audit-filter-label">Filtro: todos</small>
                </div>
                <div class="col-sm-6 text-right">
                    <button type="button" class="btn btn-default" id="yuju-audit-export-btn" disabled>
                        <i class="icon-download"></i> Descargar Excel (CSV)
                    </button>
                </div>
            </div>

            <h4 style="margin-top:0;">Actividad en vivo</h4>
            <div class="table-responsive" style="max-height:420px; overflow:auto;">
                <table class="table table-bordered table-striped table-condensed" id="yuju-audit-diff-table">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Nombre</th>
                            <th class="text-center">Stock<br><small class="text-muted">PS / Yuju</small></th>
                            <th class="text-center">Precio<br><small class="text-muted">PS / Yuju</small></th>
                            <th class="text-center">Imágenes<br><small class="text-muted">PS / Yuju</small></th>
                            <th class="text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="yuju-audit-empty">
                            <td colspan="6" class="text-center text-muted">Ejecute o reanalice para ver el comparativo.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="modal fade" id="yujuAuditErrorModal" tabindex="-1" role="dialog">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                            <h4 class="modal-title">Detalle del error Yuju</h4>
                        </div>
                        <div class="modal-body">
                            <pre id="yuju-audit-error-json" style="max-height:480px; overflow:auto; background:#f7f7f7; padding:12px; font-size:12px;"></pre>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>

            <script type="application/json" id="yuju-audit-config">{$audit_js_config nofilter}</script>
            <script type="text/javascript" src="{$module_dir|escape:'html':'UTF-8'}views/js/audit_run.js?v=20260717f"></script>
        {/if}
    </div>
</div>
{/block}
