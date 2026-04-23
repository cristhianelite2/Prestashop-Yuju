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

<script type="text/javascript">
function showProductInfo(id, reference, name, category, idImage, openHistoryTab) {
    console.log('showProductInfo llamada con:', id, reference, name, category, idImage, openHistoryTab);
    jQuery('#modal-product-id').text(id);
    jQuery('#modal-product-reference').text(reference);
    jQuery('#modal-product-name').text(name);
    jQuery('#modal-product-category').text(category);
    
    // Generar link al producto en PrestaShop admin
    var productUrl = window.location.origin + window.location.pathname.replace(/index\.php.*$/, '') + 
                     'index.php?controller=AdminProducts&id_product=' + id + '&updateproduct';
    jQuery('#modal-product-link').attr('href', productUrl);
    
    // Mostrar imagen del producto
    if (idImage && idImage != 'null' && idImage != '') {
        var imgUrl = '/img/p/' + idImage.split('').join('/') + '/' + idImage + '.jpg';
        jQuery('#modal-product-image').attr('src', imgUrl).show();
    } else {
        jQuery('#modal-product-image').hide();
    }
    
    // Si se pide abrir el tab de historial directamente
    if (openHistoryTab === true) {
        // Primero mostrar el modal
        jQuery('#productInfoModal').modal('show');
        // Esperar a que el modal esté completamente visible
        jQuery('#productInfoModal').on('shown.bs.modal', function() {
            // Activar el tab de historial
            jQuery('a[href="#tab-sync-history"]').tab('show');
            // Cargar el historial inmediatamente después de cambiar de tab
            setTimeout(function() {
                if (typeof loadProductHistory === 'function') {
                    currentHistoryPage = 1;
                    loadProductHistory(1);
                }
            }, 100);
            // Remover el listener para evitar que se ejecute múltiples veces
            jQuery(this).off('shown.bs.modal');
        });
    } else {
        jQuery('#productInfoModal').modal('show');
    }
}
</script>

{block name="content"}
<!-- Panel de Control y Filtros -->
<div class="panel">
    <div class="panel-heading">
        <a href="#" onclick="toggleFiltersAccordion(); return false;" style="text-decoration: none; color: inherit; display: block;">
            <i class="icon-filter"></i> Filtros de Búsqueda 
            <i id="filters-accordion-icon" class="icon-chevron-down pull-right"></i>
        </a>
    </div>
    <div class="panel-body" id="filters-accordion-content" style="display: none;">
        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    <label>Tienda:</label>
                    <select id="prestashop-store-filter" class="form-control">
                        <option value="">Todas</option>
                        {if isset($prestashop_stores)}
                            {foreach $prestashop_stores as $store}
                                <option value="{$store.id_shop}" {if $store.selected}selected{/if}>
                                    {$store.name|escape:'html':'UTF-8'}
                                </option>
                            {/foreach}
                        {/if}
                    </select>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="form-group">
                    <label>Categoría PrestaShop:</label>
                    <select id="ps-category-filter" class="form-control">
                        <option value="">Todas</option>
                        {if isset($mapped_categories)}
                            {foreach $mapped_categories as $cat}
                                <option value="{$cat.prestashop_category_id}" 
                                    {if isset($smarty.get.ps_category) && $smarty.get.ps_category == $cat.prestashop_category_id}selected{/if}>
                                    {$cat.prestashop_category_name|escape:'html':'UTF-8'}
                                </option>
                            {/foreach}
                        {/if}
                    </select>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="form-group">
                    <label>Categoría Yuju:</label>
                    <select id="yuju-category-filter" class="form-control">
                        <option value="">Todas</option>
                        {if isset($yuju_categories)}
                            {foreach $yuju_categories as $cat}
                                <option value="{$cat.yuju_category_id}" 
                                    {if isset($smarty.get.yuju_category) && $smarty.get.yuju_category == $cat.yuju_category_id}selected{/if}>
                                    {$cat.yuju_category_name|escape:'html':'UTF-8'}
                                </option>
                            {/foreach}
                        {/if}
                    </select>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="form-group">
                    <label>Estado:</label>
                    <select id="sync-status-filter" class="form-control">
                        <option value="">Todos</option>
                        <option value="synced" {if isset($smarty.get.status) && $smarty.get.status == 'synced'}selected{/if}>Sincronizado</option>
                        <option value="synced_with_warnings" {if isset($smarty.get.status) && $smarty.get.status == 'synced_with_warnings'}selected{/if}>Con advertencias</option>
                        <option value="synced_with_errors" {if isset($smarty.get.status) && $smarty.get.status == 'synced_with_errors'}selected{/if}>Con errores</option>
                        <option value="all_synced" {if isset($smarty.get.status) && $smarty.get.status == 'all_synced'}selected{/if}>Todos sincronizados</option>
                        <option value="pending" {if isset($smarty.get.status) && $smarty.get.status == 'pending'}selected{/if}>Pendiente</option>
                        <option value="syncing" {if isset($smarty.get.status) && $smarty.get.status == 'syncing'}selected{/if}>Sincronizando</option>
                        <option value="queued" {if isset($smarty.get.status) && $smarty.get.status == 'queued'}selected{/if}>En cola</option>
                        <option value="error" {if isset($smarty.get.status) && $smarty.get.status == 'error'}selected{/if}>Error</option>
                        <option value="disabled" {if isset($smarty.get.status) && $smarty.get.status == 'disabled'}selected{/if}>Deshabilitado</option>
                        <option value="not_synced" {if isset($smarty.get.status) && $smarty.get.status == 'not_synced'}selected{/if}>No sincronizado</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    <label>Fecha desde:</label>
                    <input type="date" id="date-from-filter" class="form-control" 
                           value="{if isset($smarty.get.date_from)}{$smarty.get.date_from|escape:'html':'UTF-8'}{/if}">
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="form-group">
                    <label>Fecha hasta:</label>
                    <input type="date" id="date-to-filter" class="form-control" 
                           value="{if isset($smarty.get.date_to)}{$smarty.get.date_to|escape:'html':'UTF-8'}{/if}">
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="form-group">
                    <label>Productos por página:</label>
                    <select id="per-page-filter" class="form-control">
                        <option value="10" {if isset($smarty.get.per_page) && $smarty.get.per_page == 10}selected{/if}>10</option>
                        <option value="25" {if !isset($smarty.get.per_page) || $smarty.get.per_page == 25}selected{/if}>25</option>
                        <option value="50" {if isset($smarty.get.per_page) && $smarty.get.per_page == 50}selected{/if}>50</option>
                        <option value="100" {if isset($smarty.get.per_page) && $smarty.get.per_page == 100}selected{/if}>100</option>
                        <option value="200" {if isset($smarty.get.per_page) && $smarty.get.per_page == 200}selected{/if}>200</option>
                    </select>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="show-all-products" value="1" 
                               {if isset($smarty.get.show_all) && $smarty.get.show_all == '1'}checked{/if}>
                        Mostrar todos los productos
                    </label>
                    <div class="help-block" style="font-size: 11px; margin-top: 5px;">
                        <i class="icon-info-circle"></i> Por defecto solo se muestran productos de categorías mapeadas
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-9">
                <div class="form-group">
                    <label>Buscar por nombre o referencia:</label>
                    <input type="text" id="product-search" class="form-control" 
                           placeholder="Ingrese nombre o referencia del producto..." 
                           value="{if isset($smarty.get.search)}{$smarty.get.search|escape:'html':'UTF-8'}{/if}">
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="form-group">
                    <label style="display: block;">Acciones:</label>
                    <button type="button" id="apply-filters-btn" class="btn btn-primary" style="width: 48%; margin-right: 2%;">
                        <i class="icon-filter"></i> Aplicar
                    </button>
                    <button type="button" id="reset-filters-btn" class="btn btn-default" style="width: 48%;">
                        <i class="icon-refresh"></i> Limpiar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Panel de Acciones -->
<div class="panel">
    <div class="panel-heading">
        <i class="icon-cogs"></i> Acciones Masivas
    </div>
    <div class="panel-body">
        <!-- Filtros Aplicados -->
        <div id="active-filters-container" style="margin-bottom: 15px;">
            <div id="active-filters-list" style="display: inline-block;">
                <!-- Los filtros activos se generarán aquí dinámicamente -->
            </div>
        </div>
        
        <button type="button" id="send-selected-products" class="btn btn-success" disabled>
            <i class="icon-cloud-upload"></i> Enviar Seleccionados a Yuju
        </button>
        
        <button type="button" id="send-all-filtered" class="btn btn-warning">
            <i class="icon-upload"></i> Enviar Todos los Filtrados
        </button>
        
        <span id="selection-count" style="margin-left: 15px; font-weight: bold;"></span>
    </div>
</div>

<!-- Tabla de Productos -->
<div class="panel">
    <div class="panel-heading">
        <i class="icon-shopping-cart"></i> Productos de PrestaShop
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="products-table">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th width="30">
                            <input type="checkbox" id="select-all-products">
                        </th>
                        <th width="30">Info</th>
                        <th width="120">Referencia</th>
                        <th>Nombre</th>
                        <th width="100">Estado Yuju</th>
                        <th width="150">Última Sync</th>
                        <th width="60">Acciones</th>
                    </tr>
                </thead>
                <tbody id="products-tbody">
                    {if isset($products) && count($products) > 0}
                        {foreach $products as $product}
                            <tr data-product-id="{$product.id_product}">
                                <td class="text-center">
                                    <input type="checkbox" class="product-checkbox" value="{$product.id_product}">
                                </td>
                                <td class="text-center">
                                    <i class="icon-info-sign" 
                                       style="color: #2196f3; cursor: pointer; font-size: 16px;"
                                       title="Ver información del producto"
                                       onclick="showProductInfo({$product.id_product}, '{$product.reference|escape:'javascript':'UTF-8'}', '{$product.name|escape:'javascript':'UTF-8'}', '{$product.category_name|escape:'javascript':'UTF-8'}', '{if isset($product.id_image)}{$product.id_image}{else}null{/if}');"></i>
                                </td>
                                <td>{$product.reference|escape:'html':'UTF-8'}</td>
                                <td>
                                    <strong>{$product.name|escape:'html':'UTF-8'}</strong>
                                </td>
                                <td class="text-center">
                                    {if isset($product.yuju_status)}
                                        {if $product.yuju_status == 'synced'}
                                            <span class="label label-success">
                                                <i class="icon-check"></i> Sincronizado
                                            </span>
                                        {elseif $product.yuju_status == 'synced_with_warnings'}
                                            <span class="label label-success">
                                                <i class="icon-check"></i> Sincronizado
                                            </span><i class="icon-exclamation-triangle" 
                                               style="color: #f0ad4e; cursor: pointer; font-size: 14px; margin-left: 5px;" 
                                               title="{if isset($product.last_error)}{$product.last_error|escape:'html':'UTF-8'}{else}Tiene advertencias{/if}"
                                               onclick="showProductInfo({$product.id_product}, '{$product.reference|escape:'javascript':'UTF-8'}', '{$product.name|escape:'javascript':'UTF-8'}', '{$product.category_name|escape:'javascript':'UTF-8'}', '{if isset($product.id_image)}{$product.id_image}{else}null{/if}', true);"></i>
                                        {elseif $product.yuju_status == 'synced_with_errors'}
                                            <span class="label label-success">
                                                <i class="icon-check"></i> Sincronizado
                                            </span><i class="icon-exclamation-circle" 
                                               style="color: #d9534f; cursor: pointer; font-size: 14px; margin-left: 5px;" 
                                               title="{if isset($product.last_error)}{$product.last_error|escape:'html':'UTF-8'}{else}Error en última operación{/if}"
                                               onclick="showProductInfo({$product.id_product}, '{$product.reference|escape:'javascript':'UTF-8'}', '{$product.name|escape:'javascript':'UTF-8'}', '{$product.category_name|escape:'javascript':'UTF-8'}', '{if isset($product.id_image)}{$product.id_image}{else}null{/if}', true);"></i>
                                        {elseif $product.yuju_status == 'error'}
                                            <span class="label label-danger" style="cursor: pointer;" 
                                                  title="{if isset($product.last_error)}{$product.last_error|escape:'html':'UTF-8'}{else}Error en sincronización{/if}"
                                                  onclick="showProductInfo({$product.id_product}, '{$product.reference|escape:'javascript':'UTF-8'}', '{$product.name|escape:'javascript':'UTF-8'}', '{$product.category_name|escape:'javascript':'UTF-8'}', '{if isset($product.id_image)}{$product.id_image}{else}null{/if}', true);">
                                                <i class="icon-remove"></i> Error
                                            </span>
                                        {elseif $product.yuju_status == 'queued'}
                                            <span class="label label-info">
                                                <i class="icon-list"></i> En Cola
                                            </span>
                                        {else}
                                            <span class="label label-default">
                                                <i class="icon-minus"></i> No enviado
                                            </span>
                                        {/if}
                                    {else}
                                        <span class="label label-default">
                                            <i class="icon-minus"></i> No enviado
                                        </span>
                                    {/if}
                                </td>
                                <td class="text-center">
                                    {if isset($product.last_sync_at) && $product.last_sync_at}
                                        <small>{$product.last_sync_at|date_format:'%d/%m/%Y %H:%M'}</small>
                                    {else}
                                        <span class="text-muted">-</span>
                                    {/if}
                                </td>
                                <td class="text-center">
                                    {if isset($product.yuju_product_id) && $product.yuju_product_id && $product.yuju_product_id != '' && $product.yuju_product_id != 'null'}
                                        <i class="icon-trash" 
                                           style="color: #dc3545; cursor: pointer; font-size: 16px;"
                                           title="Eliminar de Yuju"
                                           onclick="deleteProductFromYuju({$product.id_product}, '{$product.reference|escape:'javascript':'UTF-8'}');"></i>
                                    {else}
                                        <span class="text-muted" title="Producto no sincronizado en Yuju">-</span>
                                    {/if}
                                </td>
                            </tr>
                        {/foreach}
                    {else}
                        <tr>
                            <td colspan="7" class="text-center text-muted">
                                <p style="padding: 30px 0;">No hay productos para mostrar</p>
                            </td>
                        </tr>
                    {/if}
                </tbody>
            </table>
        </div>
        
        <!-- Paginación -->
        {if isset($pagination) && $pagination.total_pages > 1}
            <div class="row" style="margin-top: 20px;">
                <div class="col-md-6">
                    <p class="text-muted">
                        Mostrando {$pagination.from} a {$pagination.to} de {$pagination.total} productos
                    </p>
                </div>
                <div class="col-md-6">
                    <nav class="pull-right">
                        <ul class="pagination">
                            {if $pagination.current_page > 1}
                                <li><a href="#" data-page="1">&laquo; Primera</a></li>
                                <li><a href="#" data-page="{$pagination.current_page - 1}">&lsaquo; Anterior</a></li>
                            {/if}
                            
                            {for $i=max(1, $pagination.current_page - 2) to min($pagination.total_pages, $pagination.current_page + 2)}
                                <li {if $i == $pagination.current_page}class="active"{/if}>
                                    <a href="#" data-page="{$i}">{$i}</a>
                                </li>
                            {/for}
                            
                            {if $pagination.current_page < $pagination.total_pages}
                                <li><a href="#" data-page="{$pagination.current_page + 1}">Siguiente &rsaquo;</a></li>
                                <li><a href="#" data-page="{$pagination.total_pages}">Última &raquo;</a></li>
                            {/if}
                        </ul>
                    </nav>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12">
                    <div class="form-inline pull-right">
                        <label style="margin-right: 5px;">Productos por página:</label>
                        <select id="per-page-select" class="form-control input-sm">
                            <option value="10" {if $pagination.per_page == 10}selected{/if}>10</option>
                            <option value="25" {if $pagination.per_page == 25}selected{/if}>25</option>
                            <option value="50" {if $pagination.per_page == 50}selected{/if}>50</option>
                            <option value="100" {if $pagination.per_page == 100}selected{/if}>100</option>
                        </select>
                    </div>
                </div>
            </div>
        {/if}
            </div>
        </div>
    </div>
</div>

<!-- Modal de Información del Producto -->
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
                <!-- Tabs de navegación -->
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
                
                <!-- Contenido de los tabs -->
                <div class="tab-content" style="margin-top: 15px;">
                    <!-- Tab: Información del Producto -->
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
                                        <tr>
                                            <td><strong>Ver en PrestaShop:</strong></td>
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
                    
                    <!-- Tab: Historial de Sincronización -->
                    <div class="tab-pane" id="tab-sync-history">
                        <div id="sync-history-loading" style="text-align: center; padding: 20px;">
                            <i class="icon-spinner icon-spin" style="font-size: 24px;"></i>
                            <p>Cargando historial...</p>
                        </div>
                        
                        <div id="sync-history-content" style="display: none;">
                            <!-- Tabla de historial -->
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped table-sm">
                                    <thead>
                                        <tr>
                                            <th width="160">Fecha</th>
                                            <th width="80">Acción</th>
                                            <th width="80">Estado</th>
                                            <th width="80">Duración</th>
                                            <th>Detalles</th>
                                        </tr>
                                    </thead>
                                    <tbody id="sync-history-tbody">
                                    </tbody>
                                </table>
                            </div>
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

<!-- Modal de Detalles de Sincronización -->
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
                                <td id="sync-detail-error"></td>
                            </tr>
                        </table>
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

<script>
{literal}
// Función para eliminar producto de Yuju (ámbito global)
function deleteProductFromYuju(productId, productReference) {
    if (!confirm('¿Estás seguro de eliminar el producto "' + productReference + '" de Yuju?\n\nEsta acción:\n- Eliminará el producto de Yuju\n- Eliminará los registros de sincronización\n- NO eliminará el producto de PrestaShop\n\n¿Deseas continuar?')) {
        return;
    }
    
    // Buscar el ID del registro de estado
    $.ajax({
        url: '{/literal}{$ajax_url|escape:'javascript':'UTF-8'}{literal}',
        type: 'POST',
        dataType: 'json',
        data: {
            ajax: true,
            action: 'getProductStatusId',
            product_id: productId
        },
        success: function(response) {
            if (response.success && response.status_id) {
                // Redirigir al proceso de eliminación
                var deleteUrl = '{/literal}{$current_index|escape:'javascript':'UTF-8'}&token={$token|escape:'javascript':'UTF-8'}{literal}&id=' + response.status_id + '&deleteyuju_product_status';
                window.location.href = deleteUrl;
            } else {
                alert('Error: No se encontró el registro de estado del producto');
            }
        },
        error: function() {
            alert('Error al obtener el ID del registro de estado');
        }
    });
}

$(document).ready(function() {
    var selectedProducts = [];
    
    console.log('=== PRODUCTO STATUS CARGADO - v2.0 ===');
    console.log('Modal encontrado:', $('#productInfoModal').length);
    console.log('Checkboxes encontrados:', $('.product-checkbox').length);
    console.log('Botón enviar encontrado:', $('#send-selected-products').length);
    
    // Función para mostrar filtros activos
    function displayActiveFilters() {
        var params = new URLSearchParams(window.location.search);
        var filtersHtml = '';
        
        // Mapeo de nombres de filtros
        var filterLabels = {
            'store': 'Tienda',
            'ps_category': 'Categoría PS',
            'yuju_category': 'Categoría Yuju',
            'status': 'Estado',
            'date_from': 'Desde',
            'date_to': 'Hasta',
            'search': 'Búsqueda',
            'per_page': 'Por página',
            'show_all': 'Mostrar todos'
        };
        
        // Mapeo de valores de estado
        var statusLabels = {
            'synced': 'Sincronizado',
            'synced_with_warnings': 'Con advertencias',
            'synced_with_errors': 'Con errores',
            'all_synced': 'Todos sincronizados',
            'pending': 'Pendiente',
            'syncing': 'Sincronizando',
            'queued': 'En cola',
            'error': 'Error',
            'disabled': 'Deshabilitado',
            'not_synced': 'No sincronizado'
        };
        
        var hasFilters = false;
        
        // Recorrer todos los parámetros
        ['store', 'ps_category', 'yuju_category', 'status', 'date_from', 'date_to', 'search', 'per_page', 'show_all'].forEach(function(param) {
            var value = params.get(param);
            if (value && value !== '' && value !== '25') { // Ignorar per_page si es el valor por defecto
                hasFilters = true;
                var displayValue = value;
                
                // Obtener el texto del select para mejor visualización
                if (param === 'store') {
                    displayValue = $('#prestashop-store-filter option[value="' + value + '"]').text();
                } else if (param === 'ps_category') {
                    displayValue = $('#ps-category-filter option[value="' + value + '"]').text();
                } else if (param === 'yuju_category') {
                    displayValue = $('#yuju-category-filter option[value="' + value + '"]').text();
                } else if (param === 'status') {
                    displayValue = statusLabels[value] || value;
                } else if (param === 'show_all' && value === '1') {
                    displayValue = 'Sí';
                }
                
                filtersHtml += '<span class="label label-info" style="margin-right: 5px; margin-bottom: 5px; display: inline-block; padding: 5px 10px; font-size: 12px;">' +
                    '<strong>' + filterLabels[param] + ':</strong> ' + displayValue + ' ' +
                    '<a href="#" onclick="removeFilter(\'' + param + '\'); return false;" style="color: white; margin-left: 5px; text-decoration: none;">' +
                    '<i class="icon-remove"></i>' +
                    '</a>' +
                    '</span>';
            }
        });
        
        if (hasFilters) {
            filtersHtml = '<strong style="margin-right: 10px;">Filtros activos:</strong>' + filtersHtml;
        }
        
        $('#active-filters-list').html(filtersHtml);
    }
    
    // Función para eliminar un filtro específico
    window.removeFilter = function(filterName) {
        var params = new URLSearchParams(window.location.search);
        params.delete(filterName);
        
        // Si se elimina per_page, restaurar al valor por defecto
        if (filterName === 'per_page') {
            params.set('per_page', '25');
        }
        
        // Resetear a la primera página
        params.set('page', '1');
        
        window.location.href = window.location.pathname + '?' + params.toString();
    };
    
    // Mostrar filtros activos al cargar la página
    displayActiveFilters();
    
    // Toggle acordeón de filtros
    window.toggleFiltersAccordion = function() {
        var content = $('#filters-accordion-content');
        var icon = $('#filters-accordion-icon');
        
        if (content.is(':visible')) {
            content.slideUp();
            icon.removeClass('icon-chevron-down').addClass('icon-chevron-right');
        } else {
            content.slideDown();
            icon.removeClass('icon-chevron-right').addClass('icon-chevron-down');
        }
    };
    
    // Abrir acordeón si hay filtros aplicados
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('ps_category') || urlParams.get('yuju_category') || urlParams.get('status') || 
        urlParams.get('date_from') || urlParams.get('date_to') || urlParams.get('search')) {
        $('#filters-accordion-content').show();
        $('#filters-accordion-icon').removeClass('icon-chevron-right').addClass('icon-chevron-down');
    }
    
    // Aplicar filtros - recarga la página con parámetros
    $('#apply-filters-btn').click(function() {
        var params = new URLSearchParams(window.location.search);
        
        params.set('store', $('#prestashop-store-filter').val() || '');
        params.set('ps_category', $('#ps-category-filter').val() || '');
        params.set('yuju_category', $('#yuju-category-filter').val() || '');
        params.set('status', $('#sync-status-filter').val() || '');
        params.set('date_from', $('#date-from-filter').val() || '');
        params.set('date_to', $('#date-to-filter').val() || '');
        params.set('search', $('#product-search').val() || '');
        params.set('per_page', $('#per-page-filter').val() || '25');
        params.set('show_all', $('#show-all-products').is(':checked') ? '1' : '');
        params.set('page', '1'); // Reset to first page
        
        // Mantener token y controller
        window.location.href = window.location.pathname + '?' + params.toString();
    });
    
    // Limpiar filtros
    $('#reset-filters-btn').click(function() {
        var params = new URLSearchParams(window.location.search);
        
        // Solo mantener controller y token
        var controller = params.get('controller');
        var token = params.get('token');
        
        var newUrl = window.location.pathname + '?controller=' + controller;
        if (token) {
            newUrl += '&token=' + token;
        }
        
        window.location.href = newUrl;
    });
    
    // Búsqueda de productos con debounce
    var searchTimeout;
    $('#product-search').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            $('#apply-filters-btn').click();
        }, 500);
    });
    
    // Cambiar productos por página - ACTUALIZADO
    $('#per-page-filter').on('change', function() {
        $('#apply-filters-btn').click();
    });
    
    // Paginación
    $(document).on('click', '.pagination a', function(e) {
        e.preventDefault();
        var page = parseInt($(this).data('page'));
        if (page) {
            var params = new URLSearchParams(window.location.search);
            params.set('page', page);
            
            window.location.href = window.location.pathname + '?' + params.toString();
        }
    });
    
    // Checkbox "seleccionar todos"
    $(document).on('change', '#select-all-products', function() {
        var isChecked = $(this).prop('checked');
        console.log('Select all clicked:', isChecked);
        console.log('Checkboxes antes:', $('.product-checkbox').length);
        
        $('.product-checkbox').each(function() {
            $(this).prop('checked', isChecked);
        });
        
        console.log('Checkboxes marcados después:', $('.product-checkbox:checked').length);
        updateSelectedProducts();
    });
    
    // Checkbox individual
    $(document).on('change', '.product-checkbox', function() {
        console.log('Checkbox individual changed');
        updateSelectedProducts();
        
        // Actualizar el checkbox "seleccionar todos"
        var totalCheckboxes = $('.product-checkbox').length;
        var checkedCheckboxes = $('.product-checkbox:checked').length;
        
        if (checkedCheckboxes === 0) {
            $('#select-all-products').prop('checked', false).prop('indeterminate', false);
        } else if (checkedCheckboxes === totalCheckboxes) {
            $('#select-all-products').prop('checked', true).prop('indeterminate', false);
        } else {
            $('#select-all-products').prop('checked', false).prop('indeterminate', true);
        }
    });
    
    // Enviar productos seleccionados
    $('#send-selected-products').click(function() {
        if (selectedProducts.length === 0) {
            showNotification('Por favor selecciona al menos un producto', 'warning');
            return;
        }
        
        var confirmMessage;
        if (selectedProducts.length > 5) {
            confirmMessage = '¿Agregar ' + selectedProducts.length + ' producto(s) a la cola de sincronización?\n\n' +
                           'Como son más de 5 productos, se procesarán por lotes en el próximo ciclo del cron (cada 5 minutos).\n' +
                           'Los productos quedarán con estado "En Cola".';
        } else {
            confirmMessage = '¿Enviar ' + selectedProducts.length + ' producto(s) a Yuju inmediatamente?';
        }
        
        if (!confirm(confirmMessage)) {
            return;
        }
        
        sendProductsToYuju(selectedProducts);
    });
    
    // Enviar todos los filtrados
    $('#send-all-filtered').click(function() {
        if (!confirm('¿Enviar TODOS los productos filtrados a Yuju? Esta operación puede tardar varios minutos.')) {
            return;
        }
        
        sendAllFilteredProducts();
    });
    
    function updateSelectedProducts() {
        selectedProducts = [];
        $('.product-checkbox:checked').each(function() {
            selectedProducts.push($(this).val());
        });
        
        var count = selectedProducts.length;
        console.log('Productos seleccionados:', count, selectedProducts);
        $('#selection-count').text(count > 0 ? count + ' producto(s) seleccionado(s)' : '');
        $('#send-selected-products').prop('disabled', count === 0);
        
        // Cambiar texto del botón según cantidad
        var $btn = $('#send-selected-products');
        if (count > 5) {
            $btn.html('<i class="icon-list"></i> Agregar ' + count + ' Productos a Cola');
            $btn.removeClass('btn-success').addClass('btn-info');
        } else if (count > 0) {
            $btn.html('<i class="icon-cloud-upload"></i> Enviar ' + count + ' Productos a Yuju');
            $btn.removeClass('btn-info').addClass('btn-success');
        } else {
            $btn.html('<i class="icon-cloud-upload"></i> Enviar Productos Seleccionados a Yuju');
            $btn.removeClass('btn-info').addClass('btn-success');
        }
        
        if (count > 0) {
            $('#send-selected-products').removeClass('disabled');
        } else {
            $('#send-selected-products').addClass('disabled');
        }
    }
    
    function sendProductsToYuju(productIds) {
        var $btn = $('#send-selected-products');
        $btn.prop('disabled', true).html('<i class="icon-spinner icon-spin"></i> Enviando...');
        
        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: {
                ajax: true,
                action: 'sendProducts',
                product_ids: productIds
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showNotification(response.message || 'Productos enviados correctamente', 'success');
                    // Recargar la página actual manteniendo los parámetros
                    setTimeout(function() {
                        location.reload(true);
                    }, 1500);
                } else {
                    showNotification(response.message || 'Error al enviar productos', 'error');
                    $btn.prop('disabled', false).html('<i class="icon-cloud-upload"></i> Enviar Productos Seleccionados a Yuju');
                }
            },
            error: function() {
                showNotification('Error de conexión', 'error');
                $btn.prop('disabled', false).html('<i class="icon-cloud-upload"></i> Enviar Productos Seleccionados a Yuju');
            }
        });
    }
    
    function sendAllFilteredProducts() {
        var $btn = $('#send-all-filtered');
        $btn.prop('disabled', true).html('<i class="icon-spinner icon-spin"></i> Procesando...');
        
        var filters = {
            store: $('#prestashop-store-filter').val(),
            category: $('#category-filter').val(),
            status: $('#sync-status-filter').val(),
            search: $('#product-search').val()
        };
        
        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: {
                ajax: true,
                action: 'sendAllFiltered',
                filters: filters
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showNotification(response.message || 'Proceso iniciado correctamente', 'success');
                    // Recargar la página actual manteniendo los parámetros
                    setTimeout(function() {
                        location.reload(true);
                    }, 1500);
                } else {
                    showNotification(response.message || 'Error al iniciar proceso', 'error');
                    $btn.prop('disabled', false).html('<i class="icon-upload"></i> Enviar Todos los Filtrados');
                }
            },
            error: function() {
                showNotification('Error de conexión', 'error');
                $btn.prop('disabled', false).html('<i class="icon-upload"></i> Enviar Todos los Filtrados');
            }
        });
    }
    
    function showNotification(message, type) {
        var colors = {
            success: '#4caf50',
            error: '#f44336',
            info: '#2196f3',
            warning: '#ff9800'
        };
        
        var toast = $('<div>')
            .css({
                position: 'fixed',
                top: '20px',
                right: '20px',
                background: 'white',
                padding: '15px 20px',
                borderRadius: '4px',
                boxShadow: '0 2px 8px rgba(0,0,0,0.2)',
                borderLeft: '4px solid ' + (colors[type] || colors.info),
                zIndex: 99999,
                minWidth: '250px'
            })
            .text(message);
        
        $('body').append(toast);
        toast.fadeIn();
        
        setTimeout(function() {
            toast.fadeOut(function() {
                $(this).remove();
            });
        }, 4000);
    }
    
    // Variable global para la página actual del historial
    var currentHistoryPage = 1;
    
    // Cargar historial de sincronización cuando se abre el tab
    $('#tab-history-link').on('shown.bs.tab', function() {
        currentHistoryPage = 1; // Reset a la primera página
        loadProductHistory(1);
    });
    
    // Función para cargar historial con paginación
    function loadProductHistory(page) {
        var productId = $('#modal-product-id').text();
        
        if (!productId) {
            return;
        }
        
        $('#sync-history-loading').show();
        $('#sync-history-content').hide();
        $('#sync-history-error').hide();
        
        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: {
                ajax: true,
                action: 'getProductHistory',
                product_id: productId,
                page: page
            },
            dataType: 'json',
            success: function(response) {
                $('#sync-history-loading').hide();
                
                if (response.success) {
                    // Renderizar historial
                    var tbody = $('#sync-history-tbody');
                    tbody.empty();
                    
                    if (response.history && response.history.length > 0) {
                        $.each(response.history, function(index, record) {
                            var statusClass = record.status === 'success' ? 'success' : 
                                            record.status === 'error' ? 'danger' : 'warning';
                            var statusIcon = record.status === 'success' ? 'icon-check' : 'icon-remove';
                            var actionLabel = record.action === 'create' ? 'Crear' : 
                                            record.action === 'update' ? 'Actualizar' : 'Eliminar';
                            
                            // Parsear errores/warnings de Yuju si existen
                            var errorMsg = '';
                            if (record.error_message) {
                                var messageLabel = record.status === 'success' ? 'Advertencia' : 'Error';
                                var messageClass = record.status === 'success' ? 'text-warning' : 'text-danger';
                                errorMsg = '<br><small class="' + messageClass + '"><strong>' + messageLabel + ':</strong> ' + 
                                    escapeHtml(record.error_message) + '</small>';
                            }
                            
                            // Intentar extraer errores adicionales del response_data
                            if (record.response_data) {
                                try {
                                    var responseObj = typeof record.response_data === 'string' 
                                        ? JSON.parse(record.response_data) 
                                        : record.response_data;
                                    
                                    if (responseObj.errors && Array.isArray(responseObj.errors) && responseObj.errors.length > 0) {
                                        var firstError = responseObj.errors[0];
                                        if (firstError.message && Array.isArray(firstError.message)) {
                                            errorMsg += '<br><small class="text-danger"><strong>Detalles:</strong><br>';
                                            firstError.message.forEach(function(msg) {
                                                errorMsg += '• ' + escapeHtml(msg) + '<br>';
                                            });
                                            errorMsg += '</small>';
                                        }
                                    }
                                } catch (e) {
                                    // Ignorar errores de parseo
                                }
                            }
                            
                            // Crear botón para ver detalles
                            var detailsBtn = '<button class="btn btn-xs btn-info view-sync-details" ' +
                                'data-request="' + escapeHtml(record.request_data || '') + '" ' +
                                'data-response="' + escapeHtml(record.response_data || '') + '" ' +
                                'data-http-status="' + (record.http_status_code || 'N/A') + '" ' +
                                'data-duration="' + parseFloat(record.sync_duration).toFixed(3) + 's" ' +
                                'data-error="' + escapeHtml(record.error_message || '') + '" ' +
                                'style="margin-top: 5px;">' +
                                '<i class="icon-search"></i> Ver detalles' +
                                '</button>';
                            
                            var row = '<tr>' +
                                '<td><small>' + record.created_at + '</small></td>' +
                                '<td><span class="label label-info">' + actionLabel + '</span></td>' +
                                '<td><span class="label label-' + statusClass + '"><i class="' + statusIcon + '"></i></span></td>' +
                                '<td>' + parseFloat(record.sync_duration).toFixed(3) + 's</td>' +
                                '<td>' +
                                    '<strong>ID Yuju:</strong> ' + (record.yuju_product_id || 'N/A') + '<br>' +
                                    '<strong>HTTP:</strong> ' + (record.http_status_code || 'N/A') +
                                    errorMsg +
                                    '<br>' + detailsBtn +
                                '</td>' +
                                '</tr>';
                            
                            tbody.append(row);
                        });
                        
                        // Renderizar paginación
                        if (response.pagination) {
                            var pagination = response.pagination;
                            var paginationHtml = '<div class="text-center" style="margin-top: 15px;">';
                            paginationHtml += '<p class="text-muted">Mostrando registros del historial (Página ' + pagination.current_page + ' de ' + pagination.total_pages + ', Total: ' + pagination.total_records + ')</p>';
                            
                            if (pagination.total_pages > 1) {
                                paginationHtml += '<div class="btn-group">';
                                
                                // Botón anterior
                                if (pagination.current_page > 1) {
                                    paginationHtml += '<button class="btn btn-default btn-sm history-page-btn" data-page="' + (pagination.current_page - 1) + '">' +
                                        '<i class="icon-angle-left"></i> Anterior</button>';
                                }
                                
                                // Botones de páginas
                                for (var i = 1; i <= pagination.total_pages; i++) {
                                    var activeClass = i === pagination.current_page ? 'btn-primary' : 'btn-default';
                                    paginationHtml += '<button class="btn btn-sm history-page-btn ' + activeClass + '" data-page="' + i + '">' + i + '</button>';
                                }
                                
                                // Botón siguiente
                                if (pagination.current_page < pagination.total_pages) {
                                    paginationHtml += '<button class="btn btn-default btn-sm history-page-btn" data-page="' + (pagination.current_page + 1) + '">' +
                                        'Siguiente <i class="icon-angle-right"></i></button>';
                                }
                                
                                paginationHtml += '</div>';
                            }
                            paginationHtml += '</div>';
                            
                            tbody.append('<tr><td colspan="5">' + paginationHtml + '</td></tr>');
                        }
                        
                        $('#sync-history-content').show();
                    } else {
                        tbody.append('<tr><td colspan="5" class="text-center text-muted">' +
                            'No hay historial de sincronización para este producto</td></tr>');
                        $('#sync-history-content').show();
                    }
                } else {
                    $('#sync-history-error-message').text(response.message || 'Error al cargar historial');
                    $('#sync-history-error').show();
                }
            },
            error: function() {
                $('#sync-history-loading').hide();
                $('#sync-history-error-message').text('Error de conexión al cargar historial');
                $('#sync-history-error').show();
            }
        });
    }
    
    // Manejar click en botones de paginación del historial
    $(document).on('click', '.history-page-btn', function() {
        var page = parseInt($(this).data('page'));
        if (page) {
            loadProductHistory(page);
        }
    });
    
    // Manejar click en "Ver detalles" de sincronización
    $(document).on('click', '.view-sync-details', function() {
        var $btn = $(this);
        var request = $btn.data('request');
        var response = $btn.data('response');
        var httpStatus = $btn.data('http-status');
        var duration = $btn.data('duration');
        var error = $btn.data('error');
        
        // Formatear JSON para mejor legibilidad
        try {
            if (request && request !== 'null' && request !== '') {
                var requestObj = typeof request === 'string' ? JSON.parse(request) : request;
                $('#sync-detail-request').text(JSON.stringify(requestObj, null, 2));
            } else {
                $('#sync-detail-request').text('No hay datos de request disponibles');
            }
        } catch (e) {
            $('#sync-detail-request').text(request || 'Error al parsear request');
        }
        
        try {
            if (response && response !== 'null' && response !== '') {
                var responseObj = typeof response === 'string' ? JSON.parse(response) : response;
                $('#sync-detail-response').text(JSON.stringify(responseObj, null, 2));
            } else {
                $('#sync-detail-response').text('No hay datos de response disponibles');
            }
        } catch (e) {
            $('#sync-detail-response').text(response || 'Error al parsear response');
        }
        
        $('#sync-detail-http-status').text(httpStatus);
        $('#sync-detail-duration').text(duration);
        
        if (error && error !== 'null' && error !== '') {
            // Determinar si es warning o error analizando el response JSON
            var isWarning = false;
            
            try {
                if (response && response !== 'null' && response !== '') {
                    var responseObj = typeof response === 'string' ? JSON.parse(response) : response;
                    
                    // Es warning si:
                    // 1. HTTP status es 200 Y
                    // 2. El array "errors" está vacío Y
                    // 3. El array "success" tiene elementos con "warning"
                    if (httpStatus == 200 || httpStatus == '200') {
                        if (responseObj.success && Array.isArray(responseObj.success) && responseObj.success.length > 0) {
                            var hasWarnings = responseObj.success.some(function(item) {
                                return item.warning && Array.isArray(item.warning) && item.warning.length > 0;
                            });
                            
                            var hasNoErrors = !responseObj.errors || 
                                            (Array.isArray(responseObj.errors) && responseObj.errors.length === 0);
                            
                            isWarning = hasWarnings && hasNoErrors;
                        }
                    }
                }
            } catch (e) {
                // Si hay error parseando, mantener como error
                isWarning = false;
            }
            
            if (isWarning) {
                $('#sync-detail-error-label').text('Advertencia:');
                $('#sync-detail-error').css('color', '#f0ad4e'); // Color warning (amarillo/naranja)
            } else {
                $('#sync-detail-error-label').text('Error:');
                $('#sync-detail-error').css('color', '#d9534f'); // Color error (rojo)
            }
            
            $('#sync-detail-error').text(error);
            $('#sync-detail-error-row').show();
        } else {
            $('#sync-detail-error-row').hide();
        }
        
        $('#syncDetailsModal').modal('show');
    });
    
    // Función auxiliar para escapar HTML
    function escapeHtml(text) {
        if (!text) return '';
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    // Inicializar estado de productos seleccionados
    updateSelectedProducts();
});
{/literal}
</script>

{/block}