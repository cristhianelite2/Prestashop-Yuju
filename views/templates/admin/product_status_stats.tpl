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
function showProductInfo(id, reference, name, category, idImage, openHistoryTab, yujuProductId, processHint) {
    console.log('showProductInfo llamada con:', id, reference, name, category, idImage, openHistoryTab, yujuProductId, processHint);
    jQuery('#modal-product-id').text(id);
    jQuery('#modal-product-reference').text(reference);
    jQuery('#modal-product-name').text(name);
    jQuery('#modal-product-category').text(category);

    var yid = (typeof yujuProductId !== 'undefined' && yujuProductId !== null && String(yujuProductId) !== '') ? String(yujuProductId).trim() : '';
    if (yid === '' || yid.toLowerCase() === 'null') {
        yid = '';
    }
    jQuery('#modal-yuju-product-id').text(yid ? yid : '—');

    var processText = (typeof processHint !== 'undefined' && processHint !== null) ? String(processHint).trim() : '';
    if (processText !== '') {
        jQuery('#modal-sync-process-text').text(processText);
        jQuery('#modal-sync-process-banner').show();
    } else {
        jQuery('#modal-sync-process-banner').hide();
    }
    
    // Generar link al producto en Back Office (con token válido)
    var productAdminBase = '{$link->getAdminLink('AdminProducts', true)|escape:'javascript':'UTF-8'}';
    var productUrl = productAdminBase + '&id_product=' + encodeURIComponent(String(id)) + '&updateproduct=1';
    jQuery('#modal-product-link').attr('href', productUrl);
    
    // Mostrar imagen del producto (idImage puede venir como número o texto)
    var imageIdNormalized = '';
    if (typeof idImage !== 'undefined' && idImage !== null) {
        imageIdNormalized = String(idImage).trim();
    }
    if (imageIdNormalized && imageIdNormalized.toLowerCase() !== 'null') {
        var imgUrl = '/img/p/' + imageIdNormalized.split('').join('/') + '/' + imageIdNormalized + '.jpg';
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
{if isset($yuju_sync_history_table_missing) && $yuju_sync_history_table_missing}
<div class="alert alert-warning" id="yuju-sync-history-missing-alert" style="margin-bottom:15px;">
    <h4 style="margin-top:0;"><i class="icon-warning"></i> Falta la tabla de historial de envíos</h4>
    <p>
        No existe la tabla <code>{$yuju_sync_history_table_name|escape:'html':'UTF-8'}</code>.
        Sin ella no se puede registrar el historial ni algunos envíos a Yuju fallarán al guardar el registro.
    </p>
    <p style="margin-bottom:0;">
        <button type="button" class="btn btn-primary" id="yuju-btn-create-sync-history">
            <i class="icon-database"></i> Crear tabla ahora
        </button>
    </p>
</div>
<script type="text/javascript">
(function () {
    var ajaxUrl = '{$ajax_url|escape:'javascript':'UTF-8'}';
    var secToken = '{$token|escape:'javascript':'UTF-8'}';
    $(document).on('click', '#yuju-btn-create-sync-history', function () {
        var $btn = $(this);
        var $alert = $('#yuju-sync-history-missing-alert');
        $btn.prop('disabled', true).html('<i class="icon-spinner icon-spin"></i> Creando…');
        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                ajax: 1,
                action: 'createSyncHistoryTable',
                token: secToken
            },
            success: function (r) {
                if (r && r.success) {
                    $alert.removeClass('alert-warning').addClass('alert-success');
                    $alert.find('h4').html('<i class="icon-check"></i> Tabla lista');
                    $alert.find('p').first().text(r.message || 'Listo.');
                    $alert.find('#yuju-btn-create-sync-history').parent().remove();
                    setTimeout(function () { window.location.reload(true); }, 1200);
                } else {
                    alert((r && r.message) ? r.message : 'No se pudo crear la tabla.');
                    $btn.prop('disabled', false).html('<i class="icon-database"></i> Crear tabla ahora');
                }
            },
            error: function () {
                alert('Error de conexión al crear la tabla.');
                $btn.prop('disabled', false).html('<i class="icon-database"></i> Crear tabla ahora');
            }
        });
    });
})();
</script>
{/if}
<div class="panel yuju-ps-toolbar">
    <div class="panel-body yuju-ps-toolbar__filters">
        <div class="yuju-ps-filters-accordion">
            <button type="button"
                    id="yuju-filters-accordion-toggle"
                    class="yuju-ps-filters-accordion__toggle{if !(isset($product_status_has_active_filters) && $product_status_has_active_filters)} collapsed{/if}"
                    data-toggle="collapse"
                    data-target="#yuju-filters-collapse"
                    aria-expanded="{if isset($product_status_has_active_filters) && $product_status_has_active_filters}true{else}false{/if}"
                    aria-controls="yuju-filters-collapse">
                <span class="yuju-ps-filters-accordion__chev" aria-hidden="true"><i class="icon-angle-down"></i></span>
                <span class="yuju-ps-filters-accordion__label"><i class="icon-filter"></i> Filtros de Búsqueda</span>
            </button>
            <div id="yuju-filters-collapse"
                 class="collapse yuju-ps-filters-accordion__collapse{if isset($product_status_has_active_filters) && $product_status_has_active_filters} in show{/if}"
                 aria-labelledby="yuju-filters-accordion-toggle">
                <div class="yuju-ps-filters-accordion__inner">
                    <div class="row yuju-ps-filters-grid">
                        <div class="col-lg-2 col-md-4 col-sm-6">
                            <div class="form-group">
                                <label class="yuju-ps-mini-label" for="prestashop-store-filter">Tienda</label>
                                <select id="prestashop-store-filter" class="form-control input-sm">
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
                        <div class="col-lg-2 col-md-4 col-sm-6">
                            <div class="form-group">
                                <label class="yuju-ps-mini-label" for="ps-category-filter">Categoría PrestaShop</label>
                                <select id="ps-category-filter" class="form-control input-sm">
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
                        <div class="col-lg-2 col-md-4 col-sm-6">
                            <div class="form-group">
                                <label class="yuju-ps-mini-label" for="yuju-category-filter">Categoría Yuju</label>
                                <select id="yuju-category-filter" class="form-control input-sm">
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
                        <div class="col-lg-2 col-md-4 col-sm-6">
                            <div class="form-group">
                                <label class="yuju-ps-mini-label" for="sync-status-filter">Estado</label>
                                <select id="sync-status-filter" class="form-control input-sm">
                                    <option value="">Todos</option>
                                    <option value="synced" {if isset($smarty.get.status) && $smarty.get.status == 'synced'}selected{/if}>Sincronizado</option>
                                    <option value="synced_with_warnings" {if isset($smarty.get.status) && $smarty.get.status == 'synced_with_warnings'}selected{/if}>Con advertencias</option>
                                    <option value="synced_with_errors" {if isset($smarty.get.status) && $smarty.get.status == 'synced_with_errors'}selected{/if}>Con errores</option>
                                    <option value="all_synced" {if isset($smarty.get.status) && $smarty.get.status == 'all_synced'}selected{/if}>Todos sincronizados</option>
                                    <option value="pending" {if isset($smarty.get.status) && $smarty.get.status == 'pending'}selected{/if}>Pendiente</option>
                                    <option value="syncing" {if isset($smarty.get.status) && $smarty.get.status == 'syncing'}selected{/if}>Sincronizando</option>
                                    <option value="queued" {if isset($smarty.get.status) && $smarty.get.status == 'queued'}selected{/if}>En cola</option>
                                    <option value="creating_in_yuju" {if isset($smarty.get.status) && $smarty.get.status == 'creating_in_yuju'}selected{/if}>Creando en Yuju…</option>
                                    <option value="updating_in_yuju" {if isset($smarty.get.status) && $smarty.get.status == 'updating_in_yuju'}selected{/if}>Actualizando en Yuju…</option>
                                    <option value="deleting_in_yuju" {if isset($smarty.get.status) && $smarty.get.status == 'deleting_in_yuju'}selected{/if}>Eliminando en Yuju…</option>
                                    <option value="error" {if isset($smarty.get.status) && $smarty.get.status == 'error'}selected{/if}>Error</option>
                                    <option value="disabled" {if isset($smarty.get.status) && $smarty.get.status == 'disabled'}selected{/if}>Deshabilitado</option>
                                    <option value="not_synced" {if isset($smarty.get.status) && $smarty.get.status == 'not_synced'}selected{/if}>No sincronizado</option>
                                    <option value="not_in_yuju" {if isset($smarty.get.status) && $smarty.get.status == 'not_in_yuju'}selected{/if}>No agregados en Yuju</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-4 col-sm-6">
                            <div class="form-group">
                                <label class="yuju-ps-mini-label" for="per-page-filter">Por página</label>
                                <select id="per-page-filter" class="form-control input-sm">
                                    <option value="10" {if isset($smarty.get.per_page) && $smarty.get.per_page == 10}selected{/if}>10</option>
                                    <option value="25" {if !isset($smarty.get.per_page) || $smarty.get.per_page == 25}selected{/if}>25</option>
                                    <option value="50" {if isset($smarty.get.per_page) && $smarty.get.per_page == 50}selected{/if}>50</option>
                                    <option value="100" {if isset($smarty.get.per_page) && $smarty.get.per_page == 100}selected{/if}>100</option>
                                    <option value="200" {if isset($smarty.get.per_page) && $smarty.get.per_page == 200}selected{/if}>200</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-4 col-sm-6">
                            <div class="form-group">
                                <label class="yuju-ps-mini-label" for="date-from-filter">Desde</label>
                                <input type="date" id="date-from-filter" class="form-control input-sm"
                                       value="{if isset($smarty.get.date_from)}{$smarty.get.date_from|escape:'html':'UTF-8'}{/if}">
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-4 col-sm-6">
                            <div class="form-group">
                                <label class="yuju-ps-mini-label" for="date-to-filter">Hasta</label>
                                <input type="date" id="date-to-filter" class="form-control input-sm"
                                       value="{if isset($smarty.get.date_to)}{$smarty.get.date_to|escape:'html':'UTF-8'}{/if}">
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-4 col-sm-6">
                            <div class="form-group">
                                <label class="yuju-ps-mini-label" for="product-search">Nombre o referencia</label>
                                <input type="text" id="product-search" class="form-control input-sm yuju-ps-search-input"
                                        style="padding: 8px;height: 28px;"
                                       placeholder="Buscar…"
                                       value="{if isset($smarty.get.search)}{$smarty.get.search|escape:'html':'UTF-8'}{/if}">
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-4 col-sm-6">
                            <div class="form-group">
                                <label class="yuju-ps-mini-label" for="yuju-id-filter">ID Yuju</label>
                                <input type="text" id="yuju-id-filter" class="form-control input-sm yuju-ps-search-input"
                                        style="padding: 8px;height: 28px;"
                                       placeholder="ID o parte del ID…"
                                       autocomplete="off"
                                       value="{if isset($smarty.get.yuju_id)}{$smarty.get.yuju_id|escape:'html':'UTF-8'}{/if}">
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-6">
                            <div class="form-group yuju-ps-checkbox-row">
                                <label class="yuju-ps-mini-label yuju-ps-mini-label--block">
                                    <input type="checkbox" id="show-all-products" value="1"
                                           {if isset($smarty.get.show_all) && $smarty.get.show_all == '1'}checked{/if}>
                                    Todos los productos
                                </label>
                                <span class="yuju-ps-help-inline" title="Por defecto solo categorías mapeadas"><i class="icon-info-circle"></i></span>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-12 yuju-ps-toolbar-row2-actions">
                            <div class="form-group yuju-ps-filter-actions">
                                <label class="yuju-ps-mini-label yuju-ps-mini-label--invisible">Acción</label>
                                <div class="yuju-ps-filter-btn-group" role="group">
                                    <button type="button" id="apply-filters-btn" class="btn btn-primary btn-sm yuju-ps-filter-btn">
                                        <i class="icon-filter"></i> Aplicar
                                    </button>
                                    <button type="button" id="reset-filters-btn" class="btn btn-default btn-sm yuju-ps-filter-btn">
                                        <i class="icon-refresh"></i> Limpiar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="panel-body yuju-ps-toolbar__bulk">
        <div class="yuju-ps-bulk-bar">
            <div class="yuju-ps-bulk-bar__left">
                <span class="yuju-ps-bulk-bar__title"><i class="icon-cogs"></i> Acciones masivas</span>
                <div id="active-filters-container" class="yuju-ps-active-filters-wrap">
                    <div id="active-filters-list"></div>
                </div>
            </div>
            <div class="yuju-ps-bulk-bar__right">
                <div class="yuju-ps-bulk-panel">
                    <div class="yuju-ps-bulk-panel__main">
                        <div class="yuju-ps-mode-grid yuju-ps-mode-grid--compact" id="yuju-bulk-mode-grid" role="radiogroup" aria-label="Acción Yuju">
                            <label class="yuju-ps-mode-card yuju-ps-mode-card--create" title="Enviar a Yuju (actualiza si ya existe)">
                                <input type="radio" name="yuju-bulk-action" value="create" checked>
                                <span class="yuju-ps-mode-card__body">
                                    <span class="yuju-ps-mode-card__icon-wrap" aria-hidden="true"><i class="icon-cloud-upload"></i></span>
                                    <span class="yuju-ps-mode-card__title">Crear</span>
                                </span>
                            </label>
                            <label class="yuju-ps-mode-card yuju-ps-mode-card--update" title="Actualizar en Yuju">
                                <input type="radio" name="yuju-bulk-action" value="update">
                                <span class="yuju-ps-mode-card__body">
                                    <span class="yuju-ps-mode-card__icon-wrap" aria-hidden="true"><i class="icon-refresh"></i></span>
                                    <span class="yuju-ps-mode-card__title">Actualizar</span>
                                </span>
                            </label>
                            <label class="yuju-ps-mode-card yuju-ps-mode-card--delete" title="Eliminar en Yuju">
                                <input type="radio" name="yuju-bulk-action" value="delete">
                                <span class="yuju-ps-mode-card__body">
                                    <span class="yuju-ps-mode-card__icon-wrap" aria-hidden="true"><i class="icon-trash"></i></span>
                                    <span class="yuju-ps-mode-card__title">Eliminar</span>
                                </span>
                            </label>
                        </div>
                    </div>
                    <div class="yuju-ps-bulk-panel__footer">
                        <div class="yuju-ps-bulk-panel__controls">
                            <button type="button" id="send-selected-products" class="btn btn-success btn-sm yuju-ps-bulk-run" disabled>
                                <span class="yuju-ps-bulk-run__label"><i class="icon-cloud-upload"></i> Enviar seleccionados</span>
                            </button>
                            {if isset($product_status_has_active_filters) && $product_status_has_active_filters}
                                <button type="button" id="send-all-filtered" class="btn btn-warning btn-sm" data-filtered-total="{if isset($pagination.total)}{$pagination.total|intval}{else}0{/if}">
                                    <i class="icon-upload"></i> Enviar filtrados ({if isset($pagination.total)}{$pagination.total|intval}{else}0{/if})
                                </button>
                            {/if}
                        </div>
                        <span id="selection-count" class="yuju-ps-selection-meta" aria-live="polite"></span>
                    </div>
                </div>
            </div>
        </div>
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
                            {assign var='yuju_link_ok' value=false}
                            {if isset($product.yuju_product_id)}
                                {assign var='_yp' value=$product.yuju_product_id|trim}
                                {if $_yp != '' && $_yp|lower != 'null' && $_yp != '0'}
                                    {assign var='yuju_link_ok' value=true}
                                {/if}
                            {/if}
                            <tr data-product-id="{$product.id_product}">
                                <td class="text-center">
                                    <input type="checkbox" class="yuju-ps-product-cb" value="{$product.id_product}" data-yuju-ps-pid="{$product.id_product|intval}">
                                </td>
                                <td class="text-center">
                                    <i class="icon-info-sign" 
                                       style="color: #2196f3; cursor: pointer; font-size: 16px;"
                                       title="Ver información del producto"
                                       onclick="showProductInfo({$product.id_product|intval}, {$product.reference|@json_encode|escape:'html':'UTF-8'}, {$product.name|@json_encode|escape:'html':'UTF-8'}, {$product.category_name|@json_encode|escape:'html':'UTF-8'}, {if isset($product.id_image)}{$product.id_image|intval}{else}null{/if}, false{if $yuju_link_ok}, {$product.yuju_product_id|@json_encode|escape:'html':'UTF-8'}{/if});"></i>
                                </td>
                                <td class="yuju-ps-click-toggle">
                                    {$product.reference|escape:'html':'UTF-8'}
                                    {if isset($product.reference_duplicate_in_catalog) && $product.reference_duplicate_in_catalog}
                                        <br><span class="label label-warning yuju-ref-dup-badge" title="Esta referencia está asignada a más de un producto en PrestaShop (catálogo completo). Corrija duplicados antes de confiar en el SKU para Yuju.">Ref. duplicada en catálogo</span>
                                    {/if}
                                </td>
                                <td class="yuju-ps-click-toggle">
                                    <strong>{$product.name|escape:'html':'UTF-8'}</strong>
                                </td>
                                <td class="text-center">
                                    {if isset($product.yuju_status)}
                                        {if ($product.yuju_status == 'synced' || $product.yuju_status == 'synced_with_warnings' || $product.yuju_status == 'synced_with_errors') && !$yuju_link_ok}
                                            <span class="label label-default">
                                                <i class="icon-minus"></i> No enviado
                                            </span>
                                        {elseif $product.yuju_status == 'synced'}
                                            <span class="label label-success" style="cursor: pointer;" title="Ver historial e ID en Yuju"
                                                  onclick="showProductInfo({$product.id_product|intval}, {$product.reference|@json_encode|escape:'html':'UTF-8'}, {$product.name|@json_encode|escape:'html':'UTF-8'}, {$product.category_name|@json_encode|escape:'html':'UTF-8'}, {if isset($product.id_image)}{$product.id_image|intval}{else}null{/if}, true{if $yuju_link_ok}, {$product.yuju_product_id|@json_encode|escape:'html':'UTF-8'}{/if});">
                                                <i class="icon-check"></i> Sincronizado
                                            </span>
                                        {elseif $product.yuju_status == 'synced_with_warnings'}
                                            <span class="label label-success" style="cursor: pointer;" title="Ver historial e ID en Yuju"
                                                  onclick="showProductInfo({$product.id_product|intval}, {$product.reference|@json_encode|escape:'html':'UTF-8'}, {$product.name|@json_encode|escape:'html':'UTF-8'}, {$product.category_name|@json_encode|escape:'html':'UTF-8'}, {if isset($product.id_image)}{$product.id_image|intval}{else}null{/if}, true{if $yuju_link_ok}, {$product.yuju_product_id|@json_encode|escape:'html':'UTF-8'}{/if});">
                                                <i class="icon-check"></i> Sincronizado
                                            </span><i class="icon-exclamation-triangle" 
                                               style="color: #f0ad4e; cursor: pointer; font-size: 14px; margin-left: 5px;" 
                                               title="{if isset($product.last_error)}{$product.last_error|escape:'html':'UTF-8'}{else}Tiene advertencias{/if}"
                                               onclick="showProductInfo({$product.id_product|intval}, {$product.reference|@json_encode|escape:'html':'UTF-8'}, {$product.name|@json_encode|escape:'html':'UTF-8'}, {$product.category_name|@json_encode|escape:'html':'UTF-8'}, {if isset($product.id_image)}{$product.id_image|intval}{else}null{/if}, true{if $yuju_link_ok}, {$product.yuju_product_id|@json_encode|escape:'html':'UTF-8'}{/if});"></i>
                                        {elseif $product.yuju_status == 'synced_with_errors'}
                                            <span class="label label-success" style="cursor: pointer;" title="Ver historial e ID en Yuju"
                                                  onclick="showProductInfo({$product.id_product|intval}, {$product.reference|@json_encode|escape:'html':'UTF-8'}, {$product.name|@json_encode|escape:'html':'UTF-8'}, {$product.category_name|@json_encode|escape:'html':'UTF-8'}, {if isset($product.id_image)}{$product.id_image|intval}{else}null{/if}, true{if $yuju_link_ok}, {$product.yuju_product_id|@json_encode|escape:'html':'UTF-8'}{/if});">
                                                <i class="icon-check"></i> Sincronizado
                                            </span><i class="icon-exclamation-circle" 
                                               style="color: #d9534f; cursor: pointer; font-size: 14px; margin-left: 5px;" 
                                               title="{if isset($product.last_error)}{$product.last_error|escape:'html':'UTF-8'}{else}Error en última operación{/if}"
                                               onclick="showProductInfo({$product.id_product|intval}, {$product.reference|@json_encode|escape:'html':'UTF-8'}, {$product.name|@json_encode|escape:'html':'UTF-8'}, {$product.category_name|@json_encode|escape:'html':'UTF-8'}, {if isset($product.id_image)}{$product.id_image|intval}{else}null{/if}, true{if $yuju_link_ok}, {$product.yuju_product_id|@json_encode|escape:'html':'UTF-8'}{/if});"></i>
                                        {elseif $product.yuju_status == 'error'}
                                            <span class="label label-danger" style="cursor: pointer;" 
                                                  title="{if isset($product.last_error)}{$product.last_error|escape:'html':'UTF-8'}{else}Error en sincronización{/if}"
                                                  onclick="showProductInfo({$product.id_product|intval}, {$product.reference|@json_encode|escape:'html':'UTF-8'}, {$product.name|@json_encode|escape:'html':'UTF-8'}, {$product.category_name|@json_encode|escape:'html':'UTF-8'}, {if isset($product.id_image)}{$product.id_image|intval}{else}null{/if}, true);">
                                                <i class="icon-remove"></i> Error
                                            </span>
                                        {elseif $product.yuju_status == 'queued'}
                                            <span class="label label-info" style="cursor: pointer;" title="Ver detalle de proceso en cola"
                                                  onclick="showProductInfo({$product.id_product|intval}, {$product.reference|@json_encode|escape:'html':'UTF-8'}, {$product.name|@json_encode|escape:'html':'UTF-8'}, {$product.category_name|@json_encode|escape:'html':'UTF-8'}, {if isset($product.id_image)}{$product.id_image|intval}{else}null{/if}, false{if $yuju_link_ok}, {$product.yuju_product_id|@json_encode|escape:'html':'UTF-8'}{/if}, {if isset($product.queue_action) && $product.queue_action == 'delete'}'eliminación'{elseif isset($product.queue_action) && $product.queue_action == 'update'}'actualización'{else}'creación'{/if});">
                                                <i class="icon-list"></i> En Cola
                                            </span>
                                        {elseif $product.yuju_status == 'creating_in_yuju'}
                                            <span class="label label-warning" title="{if isset($product.last_error)}{$product.last_error|escape:'html':'UTF-8'}{else}Esperando webhook product-created{/if}">
                                                <i class="icon-time"></i> Creando…
                                            </span>
                                        {elseif $product.yuju_status == 'updating_in_yuju'}
                                            <span class="label label-warning" title="{if isset($product.last_error)}{$product.last_error|escape:'html':'UTF-8'}{else}Actualización en curso{/if}">
                                                <i class="icon-refresh"></i> Actualizando…
                                            </span>
                                        {elseif $product.yuju_status == 'deleting_in_yuju'}
                                            <span class="label label-warning" title="{if isset($product.last_error)}{$product.last_error|escape:'html':'UTF-8'}{else}Esperando webhook product-deleted{/if}">
                                                <i class="icon-trash"></i> Eliminando…
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
                                    {if isset($yuju_link_ok) && $yuju_link_ok}
                                        <i class="icon-trash" 
                                           style="color: #dc3545; cursor: pointer; font-size: 16px;"
                                           title="Eliminar de Yuju"
                                           onclick="deleteProductFromYuju({$product.id_product}, '{$product.reference|escape:'javascript':'UTF-8'}');"></i>
                                    {else}
                                        <span class="text-muted" title="Sin ID de producto en Yuju">-</span>
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
                <div id="modal-sync-process-banner" class="alert alert-warning" style="display:none;margin-bottom:12px;">
                    <i class="icon-time"></i>
                    <strong>En proceso:</strong>
                    <span id="modal-sync-process-text"></span>
                </div>
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
                                        <tr id="modal-yuju-product-row">
                                            <td><strong>ID producto Yuju:</strong></td>
                                            <td id="modal-yuju-product-id">—</td>
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
                            <div id="sync-history-info" class="alert alert-info" style="display: none;"></div>
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

<!-- Confirmación eliminación masiva en Yuju -->
<div class="modal fade" id="yuju-bulk-delete-modal" tabindex="-1" role="dialog" aria-labelledby="yuju-bulk-delete-modal-title">
    <div class="modal-dialog yuju-delete-modal__dialog" role="document">
        <div class="modal-content yuju-delete-modal">
            <div class="modal-header yuju-delete-modal__header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
                <h4 class="modal-title yuju-delete-modal__title" id="yuju-bulk-delete-modal-title">Eliminar en Yuju</h4>
                <p class="yuju-delete-modal__subtitle">Los productos dejarán de existir en Yuju y en los canales donde estén publicados.</p>
            </div>
            <div class="modal-body yuju-delete-modal__body">
                <div class="yuju-delete-modal__risk">
                    <div class="yuju-delete-modal__risk-badge" aria-hidden="true">!</div>
                    <div class="yuju-delete-modal__risk-textwrap">
                        <strong class="yuju-delete-modal__risk-heading">Advertencia importante</strong>
                        <p class="yuju-delete-modal__risk-copy">Si eliminas un producto en Yuju, este se eliminará de los marketplaces en los que fue creado, ten mucho cuidado con esta acción.</p>
                    </div>
                </div>
                <div class="yuju-delete-modal__summary">
                    <span class="yuju-delete-modal__summary-label">Seleccionados para esta acción</span>
                    <span class="yuju-delete-modal__summary-count"><span id="yuju-delete-count">0</span></span>
                    <p class="yuju-delete-modal__summary-note">Los que no tengan ID Yuju no se borrarán en la plataforma y se omitirán.</p>
                </div>
            </div>
            <div class="modal-footer yuju-delete-modal__footer">
                <button type="button" class="btn btn-default yuju-delete-modal__btn-cancel" data-dismiss="modal">Cancelar</button>
                <button type="button" id="yuju-bulk-delete-confirm-btn" class="btn btn-danger yuju-delete-modal__btn-confirm">Eliminar en Yuju</button>
            </div>
        </div>
    </div>
</div>

<!-- SKU duplicado en PrestaShop (bloqueo envío a Yuju) -->
<div class="modal fade" id="yuju-duplicate-sku-modal" tabindex="-1" role="dialog" aria-labelledby="yuju-duplicate-sku-modal-title">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
                <h4 class="modal-title" id="yuju-duplicate-sku-modal-title">
                    <i class="icon-warning-sign text-danger"></i> Referencia (SKU) duplicada en PrestaShop
                </h4>
            </div>
            <div class="modal-body">
                <p class="text-muted">Varios productos comparten la misma referencia. Yuju requiere un SKU único por publicación; corrija las referencias en el catálogo antes de sincronizar.</p>
                <div id="yuju-duplicate-sku-modal-body"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Validación previa al envío masivo "Crear" -->
<div class="modal fade" id="yuju-create-validate-modal" tabindex="-1" role="dialog" aria-labelledby="yuju-create-validate-modal-title" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content yuju-validate-v2">
            <div class="modal-header yuju-validate-v2__header">
                <button type="button" class="close yuju-validate-v2__close-x" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="yuju-create-validate-modal-title">Validación rápida antes de crear</h4>
            </div>
            <div class="modal-body">
                <div class="yuju-validate-v2__chips">
                    <span class="label label-default yuju-validate-v2__chip" id="yuju-create-validate-selected-line" aria-live="polite"></span>
                    <span class="label label-info yuju-validate-v2__chip" id="yuju-validate-chip-progress">Iniciando…</span>
                </div>

                <div id="yuju-create-validate-error" class="alert alert-danger yuju-validate-v2__error-box" style="display:none;"></div>

                <div class="panel-group yuju-validate-v2__accordion" id="yuju-validate-accordion" role="tablist" aria-multiselectable="true">
                    <div class="panel panel-default">
                        <div class="panel-heading yuju-validate-v2__accordion-head" role="tab" id="yuju-validate-accordion-heading">
                            <h5 class="panel-title">
                                <a role="button" data-toggle="collapse" data-parent="#yuju-validate-accordion" href="#yuju-validate-accordion-body" aria-expanded="false" aria-controls="yuju-validate-accordion-body" class="collapsed yuju-validate-v2__accordion-toggle">
                                    <i class="icon-check-square-o"></i> Validaciones
                                </a>
                            </h5>
                        </div>
                        <div id="yuju-validate-accordion-body" class="panel-collapse collapse" role="tabpanel" aria-labelledby="yuju-validate-accordion-heading">
                            <div class="panel-body yuju-validate-v2__accordion-body">
                                <ol class="yuju-validate-v2__phases list-unstyled">
                                    <li class="yuju-validate-v2__phase yuju-validate-v2__phase--pending" id="yuju-phase-dup">
                                        <div class="yuju-validate-v2__phase-badge"><i class="icon-barcode"></i></div>
                                        <div class="yuju-validate-v2__phase-body">
                                            <div class="yuju-validate-v2__phase-title-row">
                                                <strong class="yuju-validate-v2__phase-title">1. SKU único en catálogo</strong>
                                                <span class="label label-default yuju-validate-v2__phase-status" id="yuju-phase-dup-state">Pendiente</span>
                                            </div>
                                            <p class="yuju-validate-v2__phase-desc text-muted small">Busca referencias repetidas en productos y combinaciones.</p>
                                        </div>
                                    </li>
                                    <li class="yuju-validate-v2__phase yuju-validate-v2__phase--pending" id="yuju-phase-rules">
                                        <div class="yuju-validate-v2__phase-badge"><i class="icon-list-alt"></i></div>
                                        <div class="yuju-validate-v2__phase-body">
                                            <div class="yuju-validate-v2__phase-title-row">
                                                <strong class="yuju-validate-v2__phase-title">2. Datos mínimos requeridos</strong>
                                                <span class="label label-default yuju-validate-v2__phase-status" id="yuju-phase-rules-state">Pendiente</span>
                                            </div>
                                            <p class="yuju-validate-v2__phase-desc text-muted small">Valida categoría mapeada, SKU final y campos obligatorios.</p>
                                        </div>
                                    </li>
                                    <li class="yuju-validate-v2__phase yuju-validate-v2__phase--pending yuju-validate-v2__phase--hidden" id="yuju-phase-send">
                                        <div class="yuju-validate-v2__phase-badge"><i class="icon-cloud-upload"></i></div>
                                        <div class="yuju-validate-v2__phase-body">
                                            <div class="yuju-validate-v2__phase-title-row">
                                                <strong class="yuju-validate-v2__phase-title">3. Envío a Yuju</strong>
                                                <span class="label label-default yuju-validate-v2__phase-status" id="yuju-phase-send-state">Pendiente</span>
                                            </div>
                                            <p class="yuju-validate-v2__phase-desc text-muted small">Envía los válidos (cola o inmediato).</p>
                                        </div>
                                    </li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="yuju-validate-v2__micro-progress">
                    <div class="progress yuju-validate-v2__progress-bar-wrap">
                        <div id="yuju-create-validate-progress-bar" class="progress-bar progress-bar-info progress-bar-striped active" role="progressbar" style="width: 0%; min-width: 2em;">
                            <span id="yuju-create-validate-progress-pct">0%</span>
                        </div>
                    </div>
                    <p class="small text-muted yuju-validate-v2__micro-line">
                        <span id="yuju-create-validate-counter">—</span>
                        <span id="yuju-validate-micro-detail"></span>
                    </p>
                </div>

                <div id="yuju-create-validate-log-wrap">
                    <h5 class="yuju-validate-v2__log-heading">Detalle</h5>
                    <div id="yuju-create-validate-log" class="yuju-validate-v2__log"></div>
                </div>
            </div>
            <div class="modal-footer yuju-validate-v2__footer">
                <p class="yuju-validate-v2__footer-hint text-muted small" id="yuju-create-validate-footer-hint"></p>
                <button type="button" class="btn btn-default btn-lg" id="yuju-create-validate-cancel">
                    <i class="icon-remove"></i> Cerrar ventana
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
                                <td>
                                    <div id="sync-detail-error-user"></div>
                                    <div id="sync-detail-error-technical" class="text-muted small" style="display: none; margin-top: 8px;"></div>
                                </td>
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
var YUJU_PS_AJAX_URL = '{/literal}{$ajax_url|default:''|escape:'javascript':'UTF-8'}{literal}';
var YUJU_PS_TOKEN = '{/literal}{$token|default:''|escape:'javascript':'UTF-8'}{literal}';
var YUJU_PS_LOGS_URL = '{/literal}{$yuju_logs_admin_url|default:''|escape:'javascript':'UTF-8'}{literal}';
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
            token: YUJU_PS_TOKEN,
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

    function yujuSetValidateModalCloseVisible(show) {
        var $btn = $('#yuju-create-validate-cancel');
        var $x = $('#yuju-create-validate-modal .yuju-validate-v2__close-x');
        if (show) {
            $btn.show();
            $x.show();
        } else {
            $btn.hide();
            $x.hide();
        }
    }

    window.yujuSetValidateProgressFinal = function() {
        $('#yuju-create-validate-progress-bar').css('width', '100%');
        $('#yuju-create-validate-progress-pct').text('100%');
    };

    window.yujuValidateModalPendingReload = false;
    window.yujuValidateModalBusy = false;
    $('#yuju-create-validate-modal').on('hidden.bs.modal', function() {
        window.yujuValidateModalBusy = false;
        yujuSetValidateModalCloseVisible(true);
        if (window.yujuValidateModalPendingReload) {
            window.yujuValidateModalPendingReload = false;
            window.location.reload(true);
        }
    });
    $(document).on('click', '#yuju-create-validate-cancel', function(e) {
        e.preventDefault();
        e.stopPropagation();
        window.yujuValidateModalBusy = false;
        $('#yuju-create-validate-modal').modal('hide');
        return false;
    });
    $(document).on('click', '#yuju-create-validate-modal .yuju-validate-v2__close-x', function(e) {
        e.preventDefault();
        $('#yuju-create-validate-cancel').trigger('click');
    });
    
    console.log('=== PRODUCTO STATUS CARGADO - v2.0 ===');
    console.log('Modal encontrado:', $('#productInfoModal').length);
    console.log('Checkboxes tabla PS:', $('#products-tbody input.yuju-ps-product-cb').length);
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
            'yuju_id': 'ID Yuju',
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
            'creating_in_yuju': 'Creando en Yuju',
            'updating_in_yuju': 'Actualizando en Yuju',
            'deleting_in_yuju': 'Eliminando en Yuju',
            'error': 'Error',
            'disabled': 'Deshabilitado',
            'not_synced': 'No sincronizado',
            'not_in_yuju': 'No agregados en Yuju'
        };
        
        var hasFilters = false;
        
        // Recorrer todos los parámetros
        ['store', 'ps_category', 'yuju_category', 'status', 'date_from', 'date_to', 'search', 'yuju_id', 'per_page', 'show_all'].forEach(function(param) {
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
                
                filtersHtml += '<span class="label label-default yuju-ps-filter-chip">' +
                    '<strong>' + filterLabels[param] + ':</strong> ' + displayValue + ' ' +
                    '<a href="#" onclick="removeFilter(\'' + param + '\'); return false;" class="yuju-ps-filter-chip-remove" title="Quitar">' +
                    '<i class="icon-remove"></i>' +
                    '</a>' +
                    '</span>';
            }
        });
        
        if (hasFilters) {
            filtersHtml = '<span class="yuju-ps-active-filters-label">Activos:</span> ' + filtersHtml;
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
        params.set('yuju_id', $('#yuju-id-filter').val() || '');
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

    var yujuIdSearchTimeout;
    $('#yuju-id-filter').on('input', function() {
        clearTimeout(yujuIdSearchTimeout);
        yujuIdSearchTimeout = setTimeout(function() {
            $('#apply-filters-btn').click();
        }, 500);
    });
    
    // Cambiar productos por página - ACTUALIZADO
    $('#per-page-filter').on('change', function() {
        $('#apply-filters-btn').click();
    });

    // Selector de "Productos por página" en la sección de paginación (debajo de la tabla)
    // Sincroniza el valor con el filtro superior y aplica filtros para recargar la vista.
    $(document).on('change', '#per-page-select', function() {
        var newValue = $(this).val() || '25';
        $('#per-page-filter').val(newValue);
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
    
    /** Solo checkboxes dentro de la tabla de productos (evita mezclar con otros paneles del BO) */
    function yujuPsProductCheckboxes() {
        return $('#products-tbody').find('input.yuju-ps-product-cb');
    }

    function yujuPsGetCheckedProductIds() {
        var ids = [];
        yujuPsProductCheckboxes().filter(':checked').each(function() {
            var raw = $(this).attr('data-yuju-ps-pid') || $(this).val();
            var v = parseInt(String(raw), 10);
            if (!isNaN(v) && v > 0) {
                ids.push(v);
            }
        });
        return ids.filter(function(id, i, a) { return a.indexOf(id) === i; });
    }

    // Checkbox "seleccionar todos" (solo filas de #products-tbody)
    $(document).on('change', '#select-all-products', function() {
        var isChecked = $(this).prop('checked');
        yujuPsProductCheckboxes().prop('checked', isChecked);
        updateSelectedProducts();
    });
    
    // Checkbox individual
    $(document).on('change', '#products-tbody input.yuju-ps-product-cb', function() {
        updateSelectedProducts();
        
        var totalCheckboxes = yujuPsProductCheckboxes().length;
        var checkedCheckboxes = yujuPsProductCheckboxes().filter(':checked').length;
        
        if (checkedCheckboxes === 0) {
            $('#select-all-products').prop('checked', false).prop('indeterminate', false);
        } else if (checkedCheckboxes === totalCheckboxes) {
            $('#select-all-products').prop('checked', true).prop('indeterminate', false);
        } else {
            $('#select-all-products').prop('checked', false).prop('indeterminate', true);
        }
    });

    // Click en Referencia/Nombre: alterna checkbox de la fila
    $(document).on('click', '#products-tbody td.yuju-ps-click-toggle', function(e) {
        // Evitar si el click fue sobre elementos interactivos
        var $t = $(e.target);
        if ($t.closest('a, button, input, label, i').length) {
            return;
        }

        var $tr = $(this).closest('tr');
        var $cb = $tr.find('input.yuju-ps-product-cb').first();
        if (!$cb.length || $cb.is(':disabled')) {
            return;
        }

        $cb.prop('checked', !$cb.prop('checked')).trigger('change');
    });
    
    function getSelectedBulkAction() {
        var $r = $('input[name="yuju-bulk-action"]:checked');
        var v = $r.length ? $r.val() : 'create';
        if (v === 'update' || v === 'delete') {
            return v;
        }
        return 'create';
    }

    $(document).on('change', 'input[name="yuju-bulk-action"]', function() {
        updateSelectedProducts();
    });

    // Enviar productos seleccionados
    $('#send-selected-products').click(function() {
        var idsNow = yujuPsGetCheckedProductIds();
        if (idsNow.length === 0) {
            showNotification('Por favor selecciona al menos un producto', 'warning');
            return;
        }

        var bulkAction = getSelectedBulkAction();
        var n = idsNow.length;

        if (bulkAction === 'delete') {
            $('#yuju-delete-count').text(n);
            $('#yuju-bulk-delete-modal').modal('show');
            return;
        }

        if (bulkAction === 'create') {
            startCreateBatchValidation(idsNow);
            return;
        }

        var confirmMessage;
        if (n > 5) {
            if (bulkAction === 'update') {
                confirmMessage = '¿Agregar ' + n + ' producto(s) a la cola para actualizar en Yuju?\n\n' +
                    'Como son más de 5 productos, se procesarán por lotes en el próximo ciclo del cron (cada 5 minutos).\n' +
                    'Si algún producto aún no existe en Yuju, se creará automáticamente.';
            } else {
                confirmMessage = '¿Agregar ' + n + ' producto(s) a la cola para crear/sincronizar en Yuju?\n\n' +
                    'Como son más de 5 productos, se procesarán por lotes en el próximo ciclo del cron (cada 5 minutos).\n' +
                    'Los que ya existan en Yuju se actualizarán desde PrestaShop.';
            }
        } else {
            if (bulkAction === 'update') {
                confirmMessage = '¿Actualizar ' + n + ' producto(s) en Yuju ahora?\n\n' +
                    'Los que no tengan ID Yuju se crearán automáticamente.';
            } else {
                confirmMessage = '¿Enviar ' + n + ' producto(s) a Yuju ahora?\n\n' +
                    'Los que ya existan en Yuju se actualizarán desde PrestaShop.';
            }
        }

        if (!confirm(confirmMessage)) {
            return;
        }

        sendProductsToYuju(idsNow, { bulk_action: bulkAction });
    });

    $('#yuju-bulk-delete-confirm-btn').on('click', function() {
        $('#yuju-bulk-delete-modal').modal('hide');
        var ids = yujuPsGetCheckedProductIds();
        if (!ids.length) {
            showNotification('No hay productos seleccionados en la tabla.', 'warning');
            return;
        }
        sendProductsToYuju(ids, { bulk_action: 'delete', bulk_delete_confirmed: '1' });
    });
    
    $(document).on('click', '#send-all-filtered', function() {
        var total = parseInt($(this).attr('data-filtered-total') || '0', 10);
        if (!confirm('¿Enviar a Yuju los ' + total + ' producto(s) del resultado filtrado (solo los que aún no tienen ID Yuju)? Se validará el lote antes de enviar. Puede tardar varios minutos.')) {
            return;
        }
        var $btn = $('#send-all-filtered');
        var restoreTotal = parseInt($btn.attr('data-filtered-total') || '0', 10);
        var restoreHtml = '<i class="icon-upload"></i> Enviar filtrados (' + restoreTotal + ')';
        $btn.prop('disabled', true).html('<i class="icon-spinner icon-spin"></i> Obteniendo listado…');

        var filterPayload = {
            ajax: true,
            action: 'getFilteredProductIds',
            token: YUJU_PS_TOKEN,
            only_without_yuju: '1',
            'filters[store]': $('#prestashop-store-filter').val() || '',
            'filters[ps_category]': $('#ps-category-filter').val() || '',
            'filters[yuju_category]': $('#yuju-category-filter').val() || '',
            'filters[status]': $('#sync-status-filter').val() || '',
            'filters[search]': $('#product-search').val() || '',
            'filters[yuju_id]': $('#yuju-id-filter').val() || '',
            'filters[date_from]': $('#date-from-filter').val() || '',
            'filters[date_to]': $('#date-to-filter').val() || '',
            'filters[show_all]': $('#show-all-products').is(':checked') ? '1' : '',
            'filters[per_page]': $('#per-page-filter').val() || '25'
        };

        $.ajax({
            url: YUJU_PS_AJAX_URL,
            method: 'POST',
            dataType: 'json',
            data: filterPayload,
            success: function(r) {
                $btn.prop('disabled', false).html(restoreHtml);
                if (!r || !r.success) {
                    showNotification((r && r.message) ? r.message : 'No se pudo obtener el listado filtrado.', 'error', r && r.logs_url ? r.logs_url : YUJU_PS_LOGS_URL);
                    return;
                }
                var ids = r.product_ids || [];
                if (!ids.length) {
                    showNotification('No hay productos sin ID Yuju en el filtro actual para enviar.', 'warning');
                    return;
                }
                startCreateBatchValidation(ids);
            },
            error: function() {
                $btn.prop('disabled', false).html(restoreHtml);
                showNotification('Error de red al obtener el listado filtrado.', 'error', YUJU_PS_LOGS_URL);
            }
        });
    });
    
    function updateSelectedProducts() {
        selectedProducts = yujuPsGetCheckedProductIds();
        
        var count = selectedProducts.length;
        console.log('Productos seleccionados:', count, selectedProducts);
        var $badge = $('#selection-count');
        if (count > 0) {
            $badge.text(count + ' seleccionado' + (count === 1 ? '' : 's')).removeClass('yuju-ps-selection-meta--empty');
        } else {
            $badge.text('Sin selección en la tabla').addClass('yuju-ps-selection-meta--empty');
        }
        $('#send-selected-products').prop('disabled', count === 0);
        
        // Texto y estilo del botón según acción y cantidad
        var $btn = $('#send-selected-products');
        var bulkAction = getSelectedBulkAction();

        $btn.removeClass('btn-success btn-info btn-danger');

        if (bulkAction === 'delete') {
            $btn.addClass('btn-danger');
            if (count > 5) {
                $btn.html('<i class="icon-list"></i> Eliminar ' + count + ' productos (cola)');
            } else if (count > 0) {
                $btn.html('<i class="icon-trash"></i> Eliminar ' + count + ' en Yuju');
            } else {
                $btn.html('<i class="icon-trash"></i> Eliminar seleccionados en Yuju');
            }
        } else if (count > 5) {
            $btn.addClass('btn-info');
            if (bulkAction === 'update') {
                $btn.html('<i class="icon-list"></i> Actualizar ' + count + ' productos (cola)');
            } else {
                $btn.html('<i class="icon-list"></i> Crear/sincronizar ' + count + ' productos (cola)');
            }
        } else if (count > 0) {
            $btn.addClass('btn-success');
            if (bulkAction === 'update') {
                $btn.html('<i class="icon-refresh"></i> Actualizar ' + count + ' en Yuju');
            } else {
                $btn.html('<i class="icon-cloud-upload"></i> Enviar ' + count + ' a Yuju');
            }
        } else {
            $btn.addClass(bulkAction === 'delete' ? 'btn-danger' : 'btn-success');
            if (bulkAction === 'delete') {
                $btn.html('<i class="icon-trash"></i> Eliminar seleccionados en Yuju');
            } else if (bulkAction === 'update') {
                $btn.html('<i class="icon-refresh"></i> Actualizar seleccionados en Yuju');
            } else {
                $btn.html('<i class="icon-cloud-upload"></i> Enviar seleccionados a Yuju');
            }
        }
        
        if (count > 0) {
            $('#send-selected-products').removeClass('disabled');
        } else {
            $('#send-selected-products').addClass('disabled');
        }
    }

    function escapeHtmlSku(str) {
        if (str == null || str === undefined) {
            return '';
        }
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function showDuplicateSkuConflictsModal(message, conflicts) {
        var $body = $('#yuju-duplicate-sku-modal-body');
        $body.empty();
        if (message) {
            $body.append(
                $('<p class="text-danger" style="margin-bottom:14px;"></p>').html('<strong>' + escapeHtmlSku(message) + '</strong>')
            );
        }
        conflicts = conflicts || [];
        conflicts.forEach(function(group) {
            var ref = group.reference || '';
            var products = group.products || [];
            var $block = $('<div class="panel panel-default" style="margin-bottom:12px;"></div>');
            $block.append(
                $('<div class="panel-heading" style="padding:8px 12px; font-size:13px;"></div>')
                    .html('<strong>SKU</strong>: ' + escapeHtmlSku(ref) + ' <span class="badge">' + products.length + '</span>')
            );
            var $ul = $('<ul class="list-group" style="margin:0;"></ul>');
            products.forEach(function(p) {
                var pid = parseInt(p.id_product, 10);
                var name = p.name != null ? String(p.name) : '';
                var $li = $('<li class="list-group-item" style="padding:8px 12px; font-size:13px;"></li>');
                $li.append($('<span class="label label-default" style="margin-right:8px;"></span>').text('ID ' + (isNaN(pid) ? '?' : pid)));
                $li.append($('<span></span>').text(name));
                $ul.append($li);
            });
            $block.append($ul);
            $body.append($block);
        });
        $('#yuju-duplicate-sku-modal').modal('show');
    }

    /**
     * Validación previa visible (modal) antes de encolar / enviar en modo "Crear".
     */
    function startCreateBatchValidation(productIds) {
        productIds = (productIds || []).map(function(id) {
            return parseInt(id, 10) || 0;
        }).filter(function(id) {
            return id > 0;
        });
        productIds = productIds.filter(function(id, i, a) { return a.indexOf(id) === i; });

        if (!productIds.length) {
            showNotification('No hay productos válidos para validar.', 'warning');
            return;
        }

        window.yujuCancelCreateValidate = false;
        window.yujuValidateModalPendingReload = false;
        window.yujuValidateModalBusy = true;
        yujuSetValidateModalCloseVisible(false);
        $('#yuju-create-validate-footer-hint').text('');
        $('#yuju-create-validate-progress-bar').addClass('progress-bar-striped active');
        $('#yuju-validate-micro-detail').text('');

        var validIds = [];
        var validCount = 0;
        var failCount = 0;
        var batchInvalidList = [];
        var invalidProducts = [];
        var lastDuplicateConflicts = [];
        var hasFatalError = false;

        function setPhaseState(phaseKey, state) {
            var $p = $('#yuju-phase-' + phaseKey);
            if (!$p.length) {
                return;
            }
            $p.removeClass('yuju-validate-v2__phase--pending yuju-validate-v2__phase--running yuju-validate-v2__phase--ok yuju-validate-v2__phase--warn yuju-validate-v2__phase--err');
            $p.addClass('yuju-validate-v2__phase--' + state);
            var $lbl = $('#yuju-phase-' + phaseKey + '-state');
            $lbl.removeClass('label-default label-info label-success label-warning label-danger');
            var map = {
                pending: ['label-default', 'Pendiente'],
                running: ['label-info', 'En curso…'],
                ok: ['label-success', 'Correcto'],
                warn: ['label-warning', 'Atención'],
                err: ['label-danger', 'Error']
            };
            var t = map[state] || map.pending;
            $lbl.addClass(t[0]).text(t[1]);
        }

        function resetPhasesUI() {
            ['dup', 'rules', 'send'].forEach(function(k) {
                setPhaseState(k, 'pending');
            });
            $('#yuju-phase-send').addClass('yuju-validate-v2__phase--hidden');
        }

        function setChip(text, tone) {
            var $c = $('#yuju-validate-chip-progress');
            $c.removeClass('label-default label-info label-success label-warning label-danger');
            if (tone === 'ok') {
                $c.addClass('label-success');
            } else if (tone === 'warn') {
                $c.addClass('label-warning');
            } else if (tone === 'err') {
                $c.addClass('label-danger');
            } else if (tone === 'muted') {
                $c.addClass('label-default');
            } else {
                $c.addClass('label-info');
            }
            $c.text(text);
        }

        function escapeHtml(value) {
            return $('<div/>').text(value == null ? '' : String(value)).html();
        }

        function buildAdminProductUrl(productId) {
            return window.location.origin + window.location.pathname.replace(/index\.php.*$/, '') +
                'index.php?controller=AdminProducts&id_product=' + encodeURIComponent(productId) + '&updateproduct';
        }

        var $modal = $('#yuju-create-validate-modal');
        $('#yuju-create-validate-log').empty();
        $('#yuju-create-validate-error').hide().text('');
        $('#yuju-create-validate-log-wrap').show();
        // Forzar acordeón cerrado (Bootstrap puede dejar estado "in" si el DOM se reusa)
        (function() {
            var $acc = $('#yuju-validate-accordion-body');
            if (!$acc.length) {
                return;
            }
            $acc.removeClass('in').addClass('collapse').css('height', '');
            try {
                $acc.collapse({ toggle: false });
            } catch (eInit) { /* ignore */ }
            try {
                $acc.collapse('hide');
            } catch (eHide) { /* ignore */ }
            $('#yuju-validate-accordion-heading a.yuju-validate-v2__accordion-toggle')
                .attr('aria-expanded', 'false')
                .addClass('collapsed');
        })();
        var nSel = productIds.length;
        $('#yuju-create-validate-selected-line').text(
            nSel === 1 ? 'Selección: 1 producto' : ('Selección: ' + nSel + ' productos')
        );
        resetPhasesUI();
        setChip('Preparando…', 'run');
        $modal.modal('show');

        var chunkSize = productIds.length > 80 ? 10 : (productIds.length > 40 ? 5 : 1);

        function appendValidateLog(kind, line, options) {
            options = options || {};
            if (hasFatalError && kind !== 'err') {
                return;
            }
            var cls = 'yuju-validate-v2__logline--ok';
            if (kind === 'err') {
                cls = 'yuju-validate-v2__logline--err';
            } else if (kind === 'warn') {
                cls = 'yuju-validate-v2__logline--warn';
            }
            var $log = $('#yuju-create-validate-log');
            if (kind === 'err') {
                hasFatalError = true;
                $('#yuju-create-validate-error').text(line).show();
                $('#yuju-create-validate-log-wrap').hide();
                $('#yuju-validate-accordion-body').collapse('show');
                $log.empty();
            }
            var $line = $('<div class="yuju-validate-v2__logline ' + cls + '"></div>');
            if (options.allowHtml) {
                $line.html(line);
            } else {
                $line.text(line);
            }
            $log.append($line);
            var el = $log.get(0);
            if (el) {
                $log.scrollTop(el.scrollHeight);
            }
        }

        function setValidateProgress(currentStep, totalSteps) {
            $('#yuju-create-validate-counter').text(
                'Paso ' + currentStep + ' de ' + totalSteps
            );
            var rawPct = totalSteps > 0 ? Math.min(100, Math.round(100 * currentStep / totalSteps)) : 0;
            // Tope al 99% mientras el proceso sigue activo; el 100% se aplica
            // solo cuando termina realmente (toast de finalización).
            var pct = Math.min(99, rawPct);
            $('#yuju-create-validate-progress-bar').css('width', pct + '%');
            $('#yuju-create-validate-progress-pct').text(pct + '%');
        }


        function refreshValidateStats() {
            // Stats removidos del modal; se conserva contador interno para lógica y resumen.
        }

        function validationFailed(msg, r) {
            if ($('#yuju-phase-rules').hasClass('yuju-validate-v2__phase--running')) {
                setPhaseState('rules', 'err');
            } else if ($('#yuju-phase-dup').hasClass('yuju-validate-v2__phase--running')) {
                setPhaseState('dup', 'err');
            }
            appendValidateLog('err', msg);
            showNotification(msg, 'error', r && r.logs_url ? r.logs_url : YUJU_PS_LOGS_URL);
            window.yujuValidateModalBusy = false;
            yujuSetValidateModalCloseVisible(true);
            setChip('Proceso interrumpido', 'err');
            $('#yuju-create-validate-footer-hint').text('Corrige el error y vuelve a intentar.');
        }

        function runBatchStep() {
            if (window.yujuCancelCreateValidate) {
                validationFailed('Validación cancelada.', {});
                return;
            }
            setPhaseState('dup', 'running');
            setChip('Bloque 1/3 · SKU único', 'run');
            $('#yuju-validate-micro-detail').text('');

            $.ajax({
                url: YUJU_PS_AJAX_URL,
                method: 'POST',
                dataType: 'json',
                data: {
                    ajax: true,
                    action: 'validateCreateBatch',
                    token: YUJU_PS_TOKEN,
                    validate_step: 'batch',
                    product_ids_json: JSON.stringify(productIds)
                },
                success: function(r) {
                    if (window.yujuCancelCreateValidate) {
                        validationFailed('Validación cancelada.', {});
                        return;
                    }
                    if (!r || !r.success) {
                        validationFailed((r && r.message) ? r.message : 'Error en la validación por lotes.', r);
                        setPhaseState('dup', 'err');
                        return;
                    }
                    setValidateProgress(r.progress_current || 1, r.progress_total || (productIds.length + 1));
                    $('#yuju-validate-micro-detail').text(r.step_detail ? (' · ' + r.step_detail) : '');

                    batchInvalidList = r.batch_invalid_ids || [];
                    lastDuplicateConflicts = r.duplicate_sku_conflicts || [];
                    var hasDup = (r.duplicate_sku_conflicts && r.duplicate_sku_conflicts.length > 0);
                    setPhaseState('dup', hasDup ? 'warn' : 'ok');
                    if (hasDup) {
                        appendValidateLog('err',
                            'Bloque 1: se encontraron ' + r.duplicate_sku_conflicts.length + ' referencia(s) repetidas en el catálogo. Los productos afectados fallarán en el bloque 2.');
                    } else {
                        appendValidateLog('ok',
                            'Bloque 1: no hay referencias duplicadas entre productos para los códigos de este lote.');
                    }

                    runProductChunk(0);
                },
                error: function() {
                    validationFailed('Error de red durante la validación de duplicados.', {});
                    setPhaseState('dup', 'err');
                }
            });
        }

        function runProductChunk(startIdx) {
            if (window.yujuCancelCreateValidate) {
                validationFailed('Validación cancelada.', {});
                return;
            }

            if (startIdx === 0) {
                setPhaseState('rules', 'running');
                setChip('Bloque 2/3 · datos requeridos', 'run');
            }

            $.ajax({
                url: YUJU_PS_AJAX_URL,
                method: 'POST',
                dataType: 'json',
                data: {
                    ajax: true,
                    action: 'validateCreateBatch',
                    token: YUJU_PS_TOKEN,
                    validate_step: 'product',
                    product_ids_json: JSON.stringify(productIds),
                    batch_invalid_ids_json: JSON.stringify(batchInvalidList),
                    product_index: startIdx,
                    chunk_size: chunkSize
                },
                success: function(r) {
                    if (window.yujuCancelCreateValidate) {
                        validationFailed('Validación cancelada.', {});
                        return;
                    }
                    if (!$('#yuju-create-validate-modal').is(':visible')) {
                        window.yujuValidateModalBusy = false;
                        yujuSetValidateModalCloseVisible(true);
                        return;
                    }
                    if (!r || !r.success) {
                        validationFailed((r && r.message) ? r.message : 'Error al validar productos.', r);
                        setPhaseState('rules', 'err');
                        return;
                    }
                    setValidateProgress(r.progress_current || 0, r.progress_total || (productIds.length + 1));
                    $('#yuju-validate-micro-detail').text(r.step_detail ? (' · ' + r.step_detail) : '');

                    var results = r.product_results || [];
                    for (var i = 0; i < results.length; i++) {
                        var row = results[i];
                        var pid = row.product_id;
                        var pname = row.product_name || ('ID ' + pid);
                        if (row.valid) {
                            validIds.push(pid);
                            validCount++;
                            appendValidateLog('ok', 'OK · ID ' + pid + ' — ' + pname);
                        } else {
                            failCount++;
                            var errs = row.errors && row.errors.length ? row.errors.join(' ') : 'No cumple reglas para Yuju.';
                            invalidProducts.push({
                                id: pid,
                                name: pname,
                                error: errs
                            });
                            appendValidateLog('warn', 'Error · ID ' + pid + ' — ' + pname + ': ' + errs);
                        }
                    }
                    refreshValidateStats();

                    if (r.done) {
                        finishValidationAndSend();
                        return;
                    }
                    runProductChunk(r.next_product_index || 0);
                },
                error: function() {
                    validationFailed('Error de red durante la validación de productos.', {});
                    setPhaseState('rules', 'err');
                }
            });
        }

        function finishValidationAndSend() {
            if (window.yujuCancelCreateValidate) {
                window.yujuValidateModalBusy = false;
                yujuSetValidateModalCloseVisible(true);
                return;
            }
            if (failCount === 0) {
                setPhaseState('rules', 'ok');
            } else if (validCount === 0) {
                setPhaseState('rules', 'err');
            } else {
                setPhaseState('rules', 'warn');
            }

            setChip('Resumen: ' + validCount + ' válido(s), ' + failCount + ' con error, de ' + productIds.length + ' seleccionado(s)', validCount ? 'ok' : 'warn');
            appendValidateLog('ok',
                'Fin del bloque 2. Se enviarán ' + validIds.length + ' producto(s) a Yuju (si hay al menos uno válido).');

            if (productIds.length > 1 && invalidProducts.length > 0) {
                var notSentSummary = invalidProducts.map(function(p) {
                    var productUrl = buildAdminProductUrl(p.id);
                    return '<li><strong>ID ' + escapeHtml(p.id) + '</strong> — ' + escapeHtml(p.name) +
                        '<br><span>Error:</span> ' + escapeHtml(p.error) +
                        ' · <a href="' + productUrl + '" target="_blank" rel="noopener noreferrer">Ver producto</a></li>';
                }).join('');
                var notSentHtml = '<strong>No se enviaron ' + invalidProducts.length + ' producto(s):</strong>' +
                    '<ul class="yuju-validate-v2__not-sent-list">' + notSentSummary + '</ul>';
                appendValidateLog('warn', notSentHtml, { allowHtml: true });
            }

            if (!validIds.length) {
                window.yujuValidateModalBusy = false;
                yujuSetValidateModalCloseVisible(true);
                showNotification('Ningún producto del lote pasó la validación; no se envió nada a Yuju.', 'warning', YUJU_PS_LOGS_URL);
                setChip('No hay productos válidos para enviar', 'warn');
                $('#yuju-create-validate-footer-hint').text('');
                if (lastDuplicateConflicts.length && typeof showDuplicateSkuConflictsModal === 'function') {
                    showDuplicateSkuConflictsModal(
                        'Hay referencias (SKU) duplicadas en PrestaShop. Corrija el catálogo antes de sincronizar.',
                        lastDuplicateConflicts
                    );
                }
                return;
            }

            $('#yuju-phase-send').removeClass('yuju-validate-v2__phase--hidden');
            setPhaseState('send', 'running');
            setChip('Bloque 3/3 · enviando a Yuju', 'run');
            window.yujuValidateModalBusy = true;
            yujuSetValidateModalCloseVisible(false);
            $('#yuju-create-validate-footer-hint').text('');
            sendProductsToYuju(validIds, {
                bulk_action: 'create',
                skip_send_button_state: true,
                from_validation_modal: true
            });
        }

        runBatchStep();
    }
    
    function sendProductsToYuju(productIds, opts) {
        opts = opts || {};
        var bulkAction = opts.bulk_action || getSelectedBulkAction();
        var $btn = $('#send-selected-products');
        var skipBtn = !!opts.skip_send_button_state;
        var fromValidationModal = !!opts.from_validation_modal;
        var busyLabel = bulkAction === 'delete'
            ? '<i class="icon-spinner icon-spin"></i> Eliminando...'
            : '<i class="icon-spinner icon-spin"></i> Enviando...';
        if (!skipBtn) {
            $btn.prop('disabled', true).html(busyLabel);
        }

        var postData = {
            ajax: true,
            action: 'sendProducts',
            token: YUJU_PS_TOKEN,
            product_ids: productIds,
            product_ids_json: JSON.stringify(productIds.map(function(id) { return parseInt(id, 10) || 0; }).filter(function(id) { return id > 0; })),
            bulk_action: bulkAction
        };
        if (opts.bulk_delete_confirmed) {
            postData.bulk_delete_confirmed = opts.bulk_delete_confirmed;
        }

        $.ajax({
            url: YUJU_PS_AJAX_URL,
            method: 'POST',
            data: postData,
            dataType: 'json',
            success: function(response) {
                if (response && response.success) {
                    if (fromValidationModal) {
                        window.yujuValidateModalPendingReload = true;
                        window.yujuValidateModalBusy = false;
                        yujuSetValidateModalCloseVisible(true);
                        $('#yuju-create-validate-progress-bar').removeClass('progress-bar-striped active');
                        var $pSendOk = $('#yuju-phase-send');
                        $pSendOk.removeClass('yuju-validate-v2__phase--hidden');
                        $pSendOk.removeClass('yuju-validate-v2__phase--pending yuju-validate-v2__phase--running yuju-validate-v2__phase--warn yuju-validate-v2__phase--err').addClass('yuju-validate-v2__phase--ok');
                        $('#yuju-phase-send-state').removeClass('label-default label-info label-warning label-danger').addClass('label-success').text('Correcto');
                        $('#yuju-validate-chip-progress').removeClass('label-default label-info label-warning label-danger').addClass('label-success').text('Envío completado');
                        $('#yuju-validate-micro-detail').text('');
                        $('#yuju-create-validate-footer-hint').text('');
                        var okMsg = response.message || 'Productos enviados correctamente.';
                        var $logOk = $('#yuju-create-validate-log');
                        $logOk.append(
                            $('<div class="yuju-validate-v2__logline yuju-validate-v2__logline--ok"></div>').text(okMsg)
                        );
                        var elOk = $logOk.get(0);
                        if (elOk) {
                            $logOk.scrollTop(elOk.scrollHeight);
                        }
                    }
                    if (opts.on_complete && typeof opts.on_complete === 'function') {
                        try {
                            opts.on_complete(response, 'ok');
                        } catch (eCb) { /* ignore */ }
                    }
                    if (fromValidationModal && typeof window.yujuSetValidateProgressFinal === 'function') {
                        window.yujuSetValidateProgressFinal();
                    }
                    showNotification(response.message || 'Productos enviados correctamente', 'success');
                    if (!fromValidationModal) {
                        setTimeout(function() {
                            location.reload(true);
                        }, 1500);
                    }
                } else {
                    if (response && response.error_code === 'duplicate_prestashop_sku' && response.duplicate_sku_conflicts && response.duplicate_sku_conflicts.length) {
                        showDuplicateSkuConflictsModal(response.message, response.duplicate_sku_conflicts);
                    }
                    var errMsg = (response && response.message) ? response.message : 'Error al procesar la solicitud';
                    if (response && response.errors && response.errors.length && errMsg.indexOf(response.errors[0]) === -1) {
                        errMsg += '\n\n' + response.errors.join('\n');
                    }
                    var detailBody = '';
                    if (response && response.details) {
                        try {
                            detailBody = JSON.stringify(response.details, null, 2);
                        } catch (e) {
                            detailBody = String(response.details);
                        }
                    }
                    showNotification(errMsg, 'error', response && response.logs_url ? response.logs_url : YUJU_PS_LOGS_URL, detailBody ? { detailBody: detailBody } : {});
                    if (fromValidationModal) {
                        window.yujuValidateModalBusy = false;
                        yujuSetValidateModalCloseVisible(true);
                        if (typeof window.yujuSetValidateProgressFinal === 'function') {
                            window.yujuSetValidateProgressFinal();
                        }
                        $('#yuju-create-validate-progress-bar').removeClass('progress-bar-striped active');
                        var $pSendErr = $('#yuju-phase-send');
                        $pSendErr.removeClass('yuju-validate-v2__phase--hidden');
                        $pSendErr.removeClass('yuju-validate-v2__phase--pending yuju-validate-v2__phase--running yuju-validate-v2__phase--ok yuju-validate-v2__phase--warn').addClass('yuju-validate-v2__phase--err');
                        $('#yuju-phase-send-state').removeClass('label-default label-info label-success label-warning').addClass('label-danger').text('Error');
                        $('#yuju-validate-chip-progress').removeClass('label-default label-info label-success label-warning').addClass('label-danger').text('Error al enviar');
                        $('#yuju-validate-micro-detail').text('');
                        $('#yuju-create-validate-footer-hint').text('Revise el mensaje. Cierre cuando termine; la página no se recargará sola.');
                        var $logErr = $('#yuju-create-validate-log');
                        $logErr.append(
                            $('<div class="yuju-validate-v2__logline yuju-validate-v2__logline--err"></div>').text(errMsg)
                        );
                        var elErr = $logErr.get(0);
                        if (elErr) {
                            $logErr.scrollTop(elErr.scrollHeight);
                        }
                    }
                    if (opts.on_complete && typeof opts.on_complete === 'function') {
                        try {
                            opts.on_complete(response, 'fail');
                        } catch (eCb2) { /* ignore */ }
                    }
                    if (!skipBtn) {
                        $btn.prop('disabled', false);
                        updateSelectedProducts();
                    }
                }
            },
            error: function(xhr, textStatus, errorThrown) {
                var lines = [];
                lines.push('Estado: ' + textStatus);
                if (errorThrown) {
                    lines.push('Mensaje: ' + errorThrown);
                }
                if (xhr && xhr.status) {
                    lines.push('HTTP: ' + xhr.status + (xhr.statusText ? ' ' + xhr.statusText : ''));
                }
                lines.push('');
                lines.push('Respuesta del servidor (recorte):');
                var raw = xhr && xhr.responseText ? String(xhr.responseText).trim() : '';
                lines.push(raw ? (raw.length > 8000 ? raw.substring(0, 8000) + '\n…' : raw) : '(vacío — revise logs PHP / servidor)');
                showNotification(
                    'No se pudo completar la petición al servidor.',
                    'error',
                    YUJU_PS_LOGS_URL,
                    { detailBody: lines.join('\n') }
                );
                if (fromValidationModal) {
                    window.yujuValidateModalBusy = false;
                    yujuSetValidateModalCloseVisible(true);
                    if (typeof window.yujuSetValidateProgressFinal === 'function') {
                        window.yujuSetValidateProgressFinal();
                    }
                    $('#yuju-create-validate-progress-bar').removeClass('progress-bar-striped active');
                    var $pSendNet = $('#yuju-phase-send');
                    $pSendNet.removeClass('yuju-validate-v2__phase--hidden');
                    $pSendNet.removeClass('yuju-validate-v2__phase--pending yuju-validate-v2__phase--running yuju-validate-v2__phase--ok yuju-validate-v2__phase--warn').addClass('yuju-validate-v2__phase--err');
                    $('#yuju-phase-send-state').removeClass('label-default label-info label-success label-warning').addClass('label-danger').text('Error');
                    $('#yuju-validate-chip-progress').removeClass('label-default label-info label-success label-warning').addClass('label-danger').text('Error de conexión al enviar');
                    $('#yuju-validate-micro-detail').text('');
                    $('#yuju-create-validate-footer-hint').text('Cierre cuando termine; no se recargará la página.');
                    var $logNet = $('#yuju-create-validate-log');
                    $logNet.append(
                        $('<div class="yuju-validate-v2__logline yuju-validate-v2__logline--err"></div>').text(lines.slice(0, 6).join(' · '))
                    );
                }
                if (opts.on_complete && typeof opts.on_complete === 'function') {
                    try {
                        opts.on_complete(null, 'network');
                    } catch (eCb2) { /* ignore */ }
                }
                if (!skipBtn) {
                    $btn.prop('disabled', false);
                    updateSelectedProducts();
                }
            }
        });
    }
    
    function sendAllFilteredProducts() {
        var $btn = $('#send-all-filtered');
        if (!$btn.length) {
            return;
        }
        var restoreTotal = parseInt($btn.attr('data-filtered-total') || '0', 10);
        var restoreHtml = '<i class="icon-upload"></i> Enviar filtrados (' + restoreTotal + ')';
        $btn.prop('disabled', true).html('<i class="icon-spinner icon-spin"></i> Procesando…');

        var filterPayload = {
            ajax: true,
            action: 'sendAllFiltered',
            token: YUJU_PS_TOKEN,
            'filters[store]': $('#prestashop-store-filter').val() || '',
            'filters[ps_category]': $('#ps-category-filter').val() || '',
            'filters[yuju_category]': $('#yuju-category-filter').val() || '',
            'filters[status]': $('#sync-status-filter').val() || '',
            'filters[search]': $('#product-search').val() || '',
            'filters[yuju_id]': $('#yuju-id-filter').val() || '',
            'filters[date_from]': $('#date-from-filter').val() || '',
            'filters[date_to]': $('#date-to-filter').val() || '',
            'filters[show_all]': $('#show-all-products').is(':checked') ? '1' : '',
            'filters[per_page]': $('#per-page-filter').val() || '25'
        };

        $.ajax({
            url: YUJU_PS_AJAX_URL,
            method: 'POST',
            data: filterPayload,
            dataType: 'json',
            success: function(response) {
                if (response && response.success) {
                    showNotification(response.message || 'Proceso iniciado correctamente', 'success');
                    setTimeout(function() {
                        location.reload(true);
                    }, 1500);
                } else {
                    if (response && response.error_code === 'duplicate_prestashop_sku' && response.duplicate_sku_conflicts && response.duplicate_sku_conflicts.length) {
                        showDuplicateSkuConflictsModal(response.message, response.duplicate_sku_conflicts);
                    }
                    var errMsg = (response && response.message) ? response.message : 'Error al iniciar proceso';
                    if (response && response.errors && response.errors.length && errMsg.indexOf(response.errors[0]) === -1) {
                        errMsg += '\n\n' + response.errors.join('\n');
                    }
                    var detailBody = '';
                    if (response && response.details) {
                        try {
                            detailBody = JSON.stringify(response.details, null, 2);
                        } catch (e) {
                            detailBody = String(response.details);
                        }
                    }
                    showNotification(errMsg, 'error', response && response.logs_url ? response.logs_url : YUJU_PS_LOGS_URL, detailBody ? { detailBody: detailBody } : {});
                    $btn.prop('disabled', false).html(restoreHtml);
                }
            },
            error: function(xhr, textStatus, errorThrown) {
                var lines = [];
                lines.push('Estado: ' + textStatus);
                if (errorThrown) {
                    lines.push('Mensaje: ' + errorThrown);
                }
                if (xhr && xhr.status) {
                    lines.push('HTTP: ' + xhr.status + (xhr.statusText ? ' ' + xhr.statusText : ''));
                }
                lines.push('');
                lines.push('Respuesta del servidor (recorte):');
                var raw = xhr && xhr.responseText ? String(xhr.responseText).trim() : '';
                lines.push(raw ? (raw.length > 8000 ? raw.substring(0, 8000) + '\n…' : raw) : '(vacío)');
                showNotification(
                    'No se pudo completar la petición al servidor.',
                    'error',
                    YUJU_PS_LOGS_URL,
                    { detailBody: lines.join('\n') }
                );
                $btn.prop('disabled', false).html(restoreHtml);
            }
        });
    }
    
    function showNotification(message, type, logsUrl, opts) {
        opts = opts || {};
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
                minWidth: '280px',
                maxWidth: 'min(520px, 92vw)'
            });

        toast.append(
            $('<div>')
                .css({ whiteSpace: 'pre-wrap', wordBreak: 'break-word' })
                .text(message || '')
        );

        var detailBody = opts.detailBody || '';
        var $pre = null;
        var $actions = $('<div>').css({ marginTop: '10px', display: 'flex', flexWrap: 'wrap', gap: '8px', alignItems: 'center' });

        if (detailBody) {
            $pre = $('<pre>')
                .css({
                    display: 'none',
                    marginTop: '8px',
                    marginBottom: 0,
                    maxHeight: '260px',
                    overflow: 'auto',
                    fontSize: '11px',
                    background: '#f6f8fa',
                    padding: '10px',
                    border: '1px solid #e1e4e8',
                    borderRadius: '4px',
                    whiteSpace: 'pre-wrap',
                    wordBreak: 'break-word'
                })
                .text(detailBody);
            var $btnDet = $('<button type="button" class="btn btn-default btn-sm"></button>').text('Ver detalles');
            $btnDet.on('click', function() {
                var wasVisible = $pre.is(':visible');
                $pre.slideToggle(120);
                $btnDet.text(wasVisible ? 'Ver detalles' : 'Ocultar detalles');
            });
            $actions.append($btnDet);
        }

        if (logsUrl && type === 'error') {
            $actions.append(
                $('<a>')
                    .attr('href', logsUrl)
                    .attr('target', '_blank')
                    .attr('rel', 'noopener noreferrer')
                    .addClass('btn btn-default btn-sm')
                    .text('Abrir logs Yuju')
            );
        }

        if ($actions.children().length) {
            toast.append($actions);
        }
        if ($pre) {
            toast.append($pre);
        }

        $('body').append(toast);
        toast.fadeIn();

        var ms = (type === 'error' && (String(message || '').length > 120 || detailBody)) ? 20000 : 5000;
        setTimeout(function() {
            toast.fadeOut(function() {
                $(this).remove();
            });
        }, ms);
    }
    
    /** Explicación en español para mensajes conocidos de la API Yuju (misma lógica que category_bulk). */
    function explainYujuSyncError(errText) {
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
                meaning: 'Este producto ya existe en Yuju y su campo «SKU simple» quedó bloqueado por el marketplace. Por eso la actualización fue rechazada aunque el HTTP sea 200.',
                fix: 'Qué hacer: 1) No cambie SKU/referencia de este producto después de creado. 2) Reintente la actualización solo con precio, stock o descripción. 3) Si necesita cambiar el SKU, elimine el producto en Yuju y créelo de nuevo con el SKU correcto.'
            };
        }
        if (e.indexOf('duplic') !== -1 && (e.indexOf('sku') !== -1 || e.indexOf('reference') !== -1)) {
            return {
                meaning: 'Hay SKU o referencias repetidas entre productos.',
                fix: 'Asegure que cada producto tenga una referencia única en PrestaShop antes de enviar.'
            };
        }
        if (e.indexOf('no está mapeada') !== -1 || (e.indexOf('categoria') !== -1 && e.indexOf('mape') !== -1)) {
            return {
                meaning: 'La categoría de PrestaShop no tiene relación válida hacia una categoría de Yuju.',
                fix: 'Asigne la categoría Yuju correspondiente en el mapeo y vuelva a sincronizar.'
            };
        }
        if (e.indexOf('required') !== -1 || e.indexOf('obligatorio') !== -1 || e.indexOf('validation') !== -1) {
            return {
                meaning: 'Faltan campos obligatorios o un valor no cumple validación.',
                fix: 'Complete los atributos requeridos (marca, categoría, precio, stock, etc.) y vuelva a sincronizar.'
            };
        }
        if (e.indexOf('timeout') !== -1 || e.indexOf('curl') !== -1) {
            return {
                meaning: 'Fallo de comunicación con la API (conectividad o tiempo de respuesta).',
                fix: 'Reintente en unos minutos. Si se repite, revise conectividad del servidor y credenciales de API.'
            };
        }
        return {
            meaning: 'Se produjo un error durante la sincronización con Yuju.',
            fix: 'Revise el detalle técnico y los logs, corrija el dato del producto y vuelva a intentar.'
        };
    }

    function isSkuSimpleNotEditableMessage(text) {
        return String(text || '').toLowerCase().indexOf('sku simple is not editable') !== -1;
    }

    /** Una línea para la tabla de historial (evita mostrar inglés en mensajes conocidos). */
    function yujuHistoryErrorOneLine(raw) {
        if (isSkuSimpleNotEditableMessage(raw)) {
            return 'El «SKU simple» en Yuju no admite cambios (producto ya existente). Pulse «Ver detalles» para la guía completa.';
        }
        return String(raw || '');
    }

    /** Mensajes de error unidos (columna guardada + cuerpo errors[] de la respuesta). */
    function collectYujuErrorStrings(errorAttr, responseStr) {
        var out = [];
        var e = String(errorAttr || '').trim();
        if (e && e !== 'null') {
            out.push(e);
        }
        try {
            if (responseStr && responseStr !== 'null' && responseStr !== '') {
                var ro = typeof responseStr === 'string' ? JSON.parse(responseStr) : responseStr;
                if (ro.errors && Array.isArray(ro.errors)) {
                    ro.errors.forEach(function(er) {
                        if (er.message && Array.isArray(er.message)) {
                            er.message.forEach(function(m) {
                                var t = String(m || '').trim();
                                if (t) {
                                    out.push(t);
                                }
                            });
                        }
                    });
                }
            }
        } catch (ex) {
            // ignorar
        }
        return out;
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
        $('#sync-history-info').hide().empty();

        $.ajax({
            url: YUJU_PS_AJAX_URL,
            method: 'POST',
            data: {
                ajax: true,
                action: 'getProductHistory',
                token: YUJU_PS_TOKEN,
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

                    if (response.history_info) {
                        $('#sync-history-info').text(response.history_info).show();
                    }

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
                                    escapeHtml(yujuHistoryErrorOneLine(record.error_message)) + '</small>';
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
                                                errorMsg += '• ' + escapeHtml(yujuHistoryErrorOneLine(msg)) + '<br>';
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
                        if (!response.history_info) {
                            tbody.append('<tr><td colspan="5" class="text-center text-muted">' +
                                'No hay historial de sincronización para este producto</td></tr>');
                        }
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

        $('#sync-detail-error-technical').hide().empty();
        $('#sync-detail-error-user').empty().css('color', '');

        var errStrings = collectYujuErrorStrings(error, response);
        var rawBundle = errStrings.join(' · ');

        if (rawBundle) {
            // Determinar si es warning o error analizando el response JSON
            var isWarning = false;

            try {
                if (response && response !== 'null' && response !== '') {
                    var responseObj = typeof response === 'string' ? JSON.parse(response) : response;

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
            } catch (e2) {
                isWarning = false;
            }

            if (isWarning) {
                $('#sync-detail-error-label').text('Advertencia:');
                $('#sync-detail-error-user').css('color', '#f0ad4e');
            } else {
                $('#sync-detail-error-label').text('Error:');
                $('#sync-detail-error-user').css('color', '#d9534f');
            }

            var exp = explainYujuSyncError(rawBundle);
            $('#sync-detail-error-user')
                .append($('<p/>').css('margin', '0 0 8px 0').text(exp.meaning))
                .append($('<p/>').css('margin', 0).text(exp.fix));

            var hideEnglishSku = errStrings.some(function(s) {
                return isSkuSimpleNotEditableMessage(s);
            });
            if (!hideEnglishSku) {
                $('#sync-detail-error-technical').text('Detalle técnico: ' + rawBundle).show();
            }

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