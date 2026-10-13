{*
 * Modal único de información / historial de producto Yuju.
 * Usado por AdminYujuProductStatus y AdminYujuCategoryBulk (misma UI, mismos IDs).
 *}
<div id="yuju-product-info-modal-cfg" class="hidden" style="display:none;"
    data-ajax-url="{if isset($yuju_product_info_ajax_url)}{$yuju_product_info_ajax_url|escape:'html':'UTF-8'}{elseif isset($ajax_url)}{$ajax_url|escape:'html':'UTF-8'}{/if}"
    data-token="{if isset($yuju_product_info_token)}{$yuju_product_info_token|escape:'html':'UTF-8'}{elseif isset($token)}{$token|escape:'html':'UTF-8'}{/if}"
    data-product-admin-url="{if isset($product_admin_url)}{$product_admin_url|escape:'html':'UTF-8'}{elseif isset($link)}{$link->getAdminLink('AdminProducts', true)|escape:'html':'UTF-8'}{/if}"
></div>

<!-- Modal de Información del Producto (canónico) -->
<div class="modal fade" id="productInfoModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">
                    <i class="icon-info-sign"></i> Información del Producto
                </h4>
            </div>
            <div class="modal-body">
                <div id="modal-sync-process-banner" class="alert alert-warning" style="display:none;margin-bottom:12px;">
                    <i class="icon-time"></i>
                    <strong>En proceso:</strong>
                    <span id="modal-sync-process-text"></span>
                </div>
                <ul class="nav nav-tabs" role="tablist">
                    <li class="active">
                        <a href="#tab-product-info" role="tab" data-toggle="tab">
                            <i class="icon-info"></i> Información
                        </a>
                    </li>
                    <li>
                        <a href="#tab-sync-history" role="tab" data-toggle="tab" id="tab-history-link">
                            <i class="icon-time"></i> Historial de Sincronización
                        </a>
                    </li>
                </ul>

                <div class="tab-content" style="margin-top: 15px;">
                    <div class="tab-pane active" id="tab-product-info">
                        <div class="row">
                            <div class="col-md-4 text-center">
                                <img id="modal-product-image" src="" alt="Imagen del producto" class="img-thumbnail" style="max-width: 100%; max-height: 200px;">
                            </div>
                            <div class="col-md-8">
                                <table class="table table-bordered">
                                    <tbody>
                                        <tr>
                                            <td width="150"><strong>ID del Producto:</strong></td>
                                            <td id="modal-product-id"></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Referencia:</strong></td>
                                            <td id="modal-product-reference"></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Nombre:</strong></td>
                                            <td id="modal-product-name"></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Categoría:</strong></td>
                                            <td id="modal-product-category"></td>
                                        </tr>
                                        <tr id="modal-yuju-product-row">
                                            <td><strong>ID producto Yuju:</strong></td>
                                            <td id="modal-product-yuju-id-cell">
                                                <span id="modal-yuju-product-id">—</span>
                                                <span id="modal-yuju-in-yuju-badge" class="label label-success" style="display:none;margin-left:6px;">
                                                    <i class="icon-cloud"></i> Sí está en Yuju
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Estado sync:</strong></td>
                                            <td id="modal-product-sync-status">—</td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div id="modal-product-last-error" class="alert alert-danger" style="display:none;margin-top:10px;margin-bottom:0;">
                                    <strong><i class="icon-warning-sign"></i> Detalle del error:</strong>
                                    <div id="modal-product-last-error-text" style="margin-top:6px;white-space:pre-wrap;word-break:break-word;"></div>
                                </div>
                                <table class="table table-bordered" style="margin-top:10px;margin-bottom:0;">
                                    <tbody>
                                        <tr>
                                            <td width="150"><strong>Ver en PrestaShop:</strong></td>
                                            <td>
                                                <a id="modal-product-link" href="#" target="_blank" class="btn btn-sm btn-primary">
                                                    <i class="icon-external-link"></i> Abrir Producto
                                                </a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane" id="tab-sync-history">
                        <div id="sync-history-loading" style="text-align: center; padding: 20px;">
                            <i class="icon-spinner icon-spin" style="font-size: 24px;"></i>
                            <p>Cargando historial...</p>
                        </div>

                        <div id="sync-history-content" style="display: none;">
                            <div id="sync-history-info" class="alert alert-info" style="display: none;"></div>
                            <div id="sync-history-pending-create" style="display: none; margin-bottom: 15px;"></div>

                            <div id="sync-history-latest" class="yuju-hist-latest" style="display:none;"></div>

                            <div id="sync-history-older-wrap" class="yuju-hist-older" style="display:none;">
                                <div class="panel-group" id="sync-history-older-accordion" style="margin-bottom:0;">
                                    <div class="panel panel-default yuju-hist-older__panel">
                                        <div class="panel-heading yuju-hist-older__head" role="tab">
                                            <a class="yuju-hist-older__toggle collapsed" role="button"
                                                data-toggle="collapse"
                                                data-parent="#sync-history-older-accordion"
                                                href="#sync-history-older-body"
                                                aria-expanded="false"
                                                aria-controls="sync-history-older-body">
                                                <i class="icon-chevron-down yuju-hist-older__chev"></i>
                                                <strong>Historial anterior</strong>
                                                <span class="badge" id="sync-history-older-count">0</span>
                                                <span class="text-muted yuju-hist-older__hint">pulsa para expandir</span>
                                            </a>
                                        </div>
                                        <div id="sync-history-older-body" class="panel-collapse collapse" role="tabpanel">
                                            <div class="panel-body yuju-hist-older__body" style="padding:0;">
                                                <div class="table-responsive">
                                                    <table class="table table-bordered table-striped table-sm" style="margin:0;">
                                                        <thead>
                                                            <tr>
                                                                <th width="160">Fecha</th>
                                                                <th width="90">Acción</th>
                                                                <th width="80">Estado</th>
                                                                <th width="80">Duración</th>
                                                                <th>Detalles</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="sync-history-tbody"></tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div id="sync-history-empty" class="text-muted text-center" style="display:none; padding:12px;">
                                Sin registros de historial.
                            </div>
                            <div id="sync-history-pager" style="display:none; margin-top:8px;"></div>
                        </div>

                        <div id="sync-history-error" class="alert alert-warning" style="display: none;">
                            <i class="icon-warning-sign"></i>
                            <span id="sync-history-error-message"></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    <i class="icon-remove"></i> Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Detalles de Sincronización (canónico) -->
<div class="modal fade" id="syncDetailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">
                    <i class="icon-file-text"></i> Detalles de Sincronización
                </h4>
            </div>
            <div class="modal-body">
                <div class="row" style="margin-bottom: 12px;">
                    <div class="col-md-12">
                        <h5><i class="icon-exchange"></i> Resumen de la actualización</h5>
                        <table class="table table-bordered table-condensed" style="margin-bottom:0;">
                            <tr>
                                <td width="150"><strong>Origen:</strong></td>
                                <td id="sync-detail-origin">—</td>
                            </tr>
                            <tr>
                                <td><strong>Actualizaciones:</strong></td>
                                <td id="sync-detail-updates">—</td>
                            </tr>
                            <tr>
                                <td><strong>Enviado a Yuju:</strong></td>
                                <td id="sync-detail-sent-summary" style="word-break:break-word;">—</td>
                            </tr>
                            <tr>
                                <td><strong>Respuesta Yuju:</strong></td>
                                <td id="sync-detail-response-summary" style="word-break:break-word;">—</td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <h5><i class="icon-upload"></i> Request (Enviado a Yuju)</h5>
                        <pre id="sync-detail-request" style="max-height: 400px; overflow: auto; background: #f5f5f5; padding: 10px; border: 1px solid #ddd; font-size: 11px;"></pre>
                    </div>
                    <div class="col-md-6">
                        <h5><i class="icon-download"></i> Response (Respuesta de Yuju)</h5>
                        <pre id="sync-detail-response" style="max-height: 400px; overflow: auto; background: #f5f5f5; padding: 10px; border: 1px solid #ddd; font-size: 11px;"></pre>
                    </div>
                </div>
                <div class="row" style="margin-top: 15px;">
                    <div class="col-md-12">
                        <h5><i class="icon-info"></i> Información Adicional</h5>
                        <table class="table table-bordered">
                            <tr>
                                <td width="150"><strong>HTTP Status:</strong></td>
                                <td id="sync-detail-http-status"></td>
                            </tr>
                            <tr>
                                <td><strong>Duración:</strong></td>
                                <td id="sync-detail-duration"></td>
                            </tr>
                            <tr id="sync-detail-error-row" style="display: none;">
                                <td><strong id="sync-detail-error-label">Error:</strong></td>
                                <td>
                                    <div id="sync-detail-error-user"></div>
                                    <div id="sync-detail-error-technical" class="text-muted small" style="display: none; margin-top: 8px;"></div>
                                    <div id="sync-detail-error-actions" style="margin-top: 12px; display: none;">
                                        <button type="button" class="btn btn-warning btn-sm" id="sync-detail-goto-error-btn">
                                            <i class="icon-warning-sign"></i> Ver error guardado
                                        </button>
                                        <button type="button" class="btn btn-default btn-sm" id="sync-detail-copy-error-btn" style="margin-left:6px;">
                                            <i class="icon-copy"></i> Copiar detalle
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-warning" id="sync-detail-goto-error-btn-footer" style="display:none; float:left;">
                    <i class="icon-warning-sign"></i> Ver error guardado
                </button>
                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
