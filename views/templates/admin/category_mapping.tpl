{extends file="./layout.tpl"}

{block name="content"}
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
/* Estilos del árbol de categorías */
.category-tree {
    background: white;
    border: 1px solid #e5e5e5;
    border-radius: 4px;
    padding: 6px 8px;
    max-height: 520px;
    overflow-y: auto;
}
.category-item {
    padding: 3px 6px;
    margin: 1px 0;
    border-radius: 3px;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
    display: flex;
    align-items: center;
    gap: 4px;
    min-height: 22px;
    line-height: 1.15;
}
.category-item:hover {
    background: #f8f9fa;
}
.category-item.selected {
    background: #e3f2fd;
    border-left: 3px solid #2196f3;
}
.category-item.mapped {
    background: #e8f5e9;
    border-left: 3px solid #4caf50;
    cursor: not-allowed;
}
.category-item.mapped .category-name {
    color: #4caf50;
    font-weight: 500;
}
.category-item.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: #f5f5f5;
}
.category-children {
    margin-left: 14px;
    border-left: 2px dashed #e0e0e0;
    padding-left: 5px;
}

.category-name {
    flex: 1;
    font-size: 11px;
}
.toggle-children {
    width: 16px;
    height: 16px;
    background: #e0e0e0;
    border-radius: 2px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.2s;
}
.toggle-children:hover {
    background: #bdbdbd;
}
.toggle-children.expanded {
    background: #2196f3;
    color: white;
}

/* Estilos específicos para árbol de Yuju */
.yuju-category-wrapper .category-item {
    background: white;
}
.yuju-category-wrapper .category-item:hover {
    background: #fff3e0;
}
.yuju-category-item.selected {
    background: #fff3e0 !important;
    border-left: 3px solid #ff9800 !important;
}

/* Transición suave para iconos de folder */
.icon-folder, .icon-folder-open, .icon-file {
    transition: all 0.2s ease;
    font-size: 12px;
}

.yuju-category-selector {
    background: white;
    border: 1px solid #e5e5e5;
    border-radius: 4px;
    padding: 6px 8px;
}
.mapping-table {
    margin-top: 20px;
}
.mapping-row:hover {
    background-color: #f8f9fa;
}

.yuju-help-toggle {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    color: #495057;
    font-weight: 600;
    margin-bottom: 8px;
}

.yuju-selection-panel {
    border: 1px solid #dce3ea;
    border-radius: 6px;
    background: #f8fafc;
    padding: 8px 10px;
    margin-bottom: 12px;
}

.yuju-selection-title {
    font-size: 12px;
    font-weight: 700;
    color: #455a64;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin-bottom: 6px;
}

.yuju-selection-grid {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.yuju-selection-chip {
    flex: 1 1 260px;
    min-height: 32px;
    border: 1px solid #dde5ee;
    border-radius: 4px;
    background: #fff;
    padding: 5px 8px;
    font-size: 11px;
    line-height: 1.2;
}

.yuju-selection-chip strong {
    display: inline-block;
    margin-right: 4px;
}

.yuju-tree-search {
    margin-bottom: 8px;
}

.yuju-tree-search .input-group-addon {
    padding: 4px 8px;
}

.yuju-tree-search input.form-control {
    height: 28px;
    font-size: 12px;
    padding: 4px 8px;
}
</style>

<div class="panel">
    <div class="panel-heading">
        <i class="icon-sitemap"></i>
        Mapeo de Categorías
        <div class="panel-heading-action">
            <button class="btn btn-success btn-sm" onclick="openMappingModal()">
                <i class="icon-plus"></i> Nuevo Mapeo de Categoría
            </button>
        </div>
    </div>
    
    <div class="panel-body">
        <div class="alert alert-info">
            <i class="icon-info-circle"></i>
            <strong>Información:</strong>
            Las categorías mapeadas aparecen en la tabla. Las categorías con <i class="icon-exchange" style="color: #4caf50;"></i> ya están mapeadas y no se pueden volver a mapear.
        </div>
        
        <!-- Tabla de mapeos -->
        <div class="table-responsive mapping-table">
            <table class="table table-bordered">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th width="50">ID</th>
                        <th>Categoría PrestaShop</th>
                        <th>Categoría Yuju</th>
                        <th width="150">Fecha de Creación</th>
                        <th width="120" class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody id="mappings-tbody">
                    {if isset($category_mappings) && $category_mappings}
                        {foreach $category_mappings as $mapping}
                            <tr class="mapping-row" data-mapping-id="{$mapping.id}">
                                <td>{$mapping.id}</td>
                                <td>
                                    <i class="icon-folder"></i>
                                    <strong>{$mapping.prestashop_category_name|escape:'html':'UTF-8'}</strong>
                                    <br>
                                    <small class="text-muted">ID: {$mapping.prestashop_category_id}</small>
                                </td>
                                <td>
                                    <i class="icon-cloud"></i>
                                    <strong>{$mapping.yuju_category_name|escape:'html':'UTF-8'}</strong>
                                    <br>
                                    <small class="text-muted">ID: {$mapping.yuju_category_id}</small>
                                </td>
                                <td>
                                    {if $mapping.created_at}
                                        {$mapping.created_at|date_format:'%d/%m/%Y %H:%M'}
                                    {else}
                                        <span class="text-muted">-</span>
                                    {/if}
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-danger btn-sm" onclick="deleteMapping({$mapping.id})">
                                        <i class="icon-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        {/foreach}
                    {else}
                        <tr>
                            <td colspan="5" class="text-center text-muted">
                                <p style="padding: 30px 0;">No hay mapeos de categorías configurados.</p>
                                <button class="btn btn-success" onclick="openMappingModal()">
                                    <i class="icon-plus"></i> Crear Primer Mapeo
                                </button>
                            </td>
                        </tr>
                    {/if}
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal para crear mapeo -->
<div class="modal fade" id="categoryMappingModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="icon-sitemap"></i> Mapeo de Categoría</h4>
            </div>
            <div class="modal-body">
                <div class="yuju-help-toggle" data-toggle="collapse" data-target="#yuju-category-help" aria-expanded="false" aria-controls="yuju-category-help">
                    <i class="icon-question-circle"></i>
                    <span>Ayuda</span>
                    <i class="icon-chevron-down"></i>
                </div>
                <div id="yuju-category-help" class="collapse">
                    <div class="alert alert-warning" style="margin-bottom: 10px;">
                        <ol style="margin: 0 0 0 18px; padding: 0;">
                            <li>Selecciona una categoría de PrestaShop del árbol (izquierda)</li>
                            <li>Selecciona la categoría correspondiente de Yuju (derecha)</li>
                            <li>Las categorías con <i class="icon-exchange" style="color: #4caf50;"></i> ya están mapeadas</li>
                            <li>No se pueden mapear categorías ya mapeadas ni sus hijas</li>
                        </ol>
                    </div>
                </div>

                <div class="yuju-selection-panel" id="current-selection-summary">
                    <div class="yuju-selection-title">Selección actual</div>
                    <div class="yuju-selection-grid">
                        <div class="yuju-selection-chip">
                            <strong><i class="icon-folder-open"></i> PrestaShop:</strong>
                            <span id="selected-ps-label" class="text-muted">Sin seleccionar</span>
                        </div>
                        <div class="yuju-selection-chip">
                            <strong><i class="icon-cloud"></i> Yuju:</strong>
                            <span id="selected-yuju-label" class="text-muted">Sin seleccionar</span>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Árbol de Categorías PrestaShop -->
                    <div class="col-md-6">
                        <h5><i class="icon-folder-open"></i> Selecciona una categoría de tu Prestashop</h5>
                        <div class="input-group input-group-sm yuju-tree-search">
                            <span class="input-group-addon"><i class="icon-search"></i></span>
                            <input type="text" id="prestashop-tree-search" class="form-control" placeholder="Buscar categoría en PrestaShop...">
                            <span class="input-group-btn">
                                <button type="button" class="btn btn-default" id="prestashop-tree-clear">Limpiar</button>
                            </span>
                        </div>
                        <div class="category-tree" id="prestashop-tree">
                            {if isset($prestashop_categories) && $prestashop_categories}
                                {function name=renderTree categories=$prestashop_categories mappings=$category_mappings}
                                    {foreach $categories as $category}
                                        {assign var="is_mapped" value=false}
                                        {assign var="mapped_to" value=""}
                                        {foreach $mappings as $mapping}
                                            {if $mapping.prestashop_category_id == $category.id}
                                                {assign var="is_mapped" value=true}
                                                {assign var="mapped_to" value=$mapping.yuju_category_name}
                                                {break}
                                            {/if}
                                        {/foreach}
                                        
                                        <div class="category-wrapper" data-category-id="{$category.id}">
                                            <div class="category-item {if $is_mapped}mapped{/if}" 
                                                 data-id="{$category.id}" 
                                                 data-name="{$category.name|escape:'html':'UTF-8'}"
                                                 data-mapped="{if $is_mapped}1{else}0{/if}"
                                                 data-has-children="{if $category.children}1{else}0{/if}"
                                                 style="cursor: {if $is_mapped}not-allowed{else}pointer{/if};">
                                                
                                                {if !$is_mapped}
                                                    <input type="radio" name="ps_category" value="{$category.id}" 
                                                           data-name="{$category.name|escape:'html':'UTF-8'}"
                                                           onclick="event.stopPropagation(); selectPsCategory(this);"
                                                           style="margin-right: 8px;">
                                                {else}
                                                    <i class="icon-exchange category-icon mapped-icon" style="margin-right: 8px;"></i>
                                                {/if}
                                                
                                                {if $is_mapped}
                                                    <i class="icon-exchange" style="color: #4caf50; margin-right: 5px;"></i>
                                                {else if $category.children}
                                                    <i class="icon-folder" onclick="event.stopPropagation(); togglePsChildren(this)" style="cursor: pointer; color: #2196f3; margin-right: 5px;"></i>
                                                {else}
                                                    <i class="icon-file" style="color: #757575; margin-right: 5px;"></i>
                                                {/if}
                                                
                                                <span class="category-name" {if $category.children}onclick="event.stopPropagation(); togglePsChildrenByName(this)" style="cursor: pointer;"{/if}>{$category.name|escape:'html':'UTF-8'}</span>
                                                
                                                {if $is_mapped}
                                                    <small style="color: #4caf50;">✓</small>
                                                {/if}
                                            </div>
                                            
                                            {if $category.children}
                                                <div class="category-children" style="display: none;">
                                                    {call name=renderTree categories=$category.children mappings=$mappings}
                                                </div>
                                            {/if}
                                        </div>
                                    {/foreach}
                                {/function}
                                
                                {call name=renderTree categories=$prestashop_categories mappings=$category_mappings}
                            {else}
                                <p class="text-muted text-center">No hay categorías disponibles</p>
                            {/if}
                        </div>
                    </div>
                    
                    <!-- Selector de Categoría Yuju -->
                    <div class="col-md-6">
                        <h5><i class="icon-cloud"></i> Selecciona una categoría de yuju</h5>
                        <div class="input-group input-group-sm yuju-tree-search">
                            <span class="input-group-addon"><i class="icon-search"></i></span>
                            <input type="text" id="yuju-tree-search" class="form-control" placeholder="Buscar categoría en Yuju...">
                            <span class="input-group-btn">
                                <button type="button" class="btn btn-default" id="yuju-tree-clear">Limpiar</button>
                            </span>
                        </div>
                        <div class="yuju-category-selector">
                            <div class="category-tree" id="yuju-tree">
                                {if isset($yuju_categories)}
                                    {function name=renderYujuTree categories=$yuju_categories}
                                        {foreach $categories as $category}
                                            <div class="category-wrapper yuju-category-wrapper" data-category-id="{$category.id}">
                                                <div class="category-item yuju-category-item" 
                                                     data-id="{$category.id}" 
                                                     data-name="{$category.name|escape:'html':'UTF-8'}"
                                                     data-has-children="{if $category.children && count($category.children) > 0}1{else}0{/if}"
                                                     style="cursor: pointer;">
                                                    
                                                    {if !($category.children && count($category.children) > 0)}
                                                        <input type="radio" name="yuju_category" value="{$category.id}" 
                                                               data-name="{$category.name|escape:'html':'UTF-8'}"
                                                               onclick="event.stopPropagation(); selectYujuCategory(this);"
                                                               style="margin: 0 8px 0 0;">
                                                    {else}
                                                        <span style="display: inline-block; width: 16px; margin-right: 8px;"></span>
                                                    {/if}
                                                    
                                                    {if $category.children && count($category.children) > 0}
                                                        <i class="icon-folder" onclick="event.stopPropagation(); toggleYujuChildren(this)" style="cursor: pointer; color: #ff9800; margin-right: 5px; font-size: 16px;"></i>
                                                    {else}
                                                        <i class="icon-file" style="color: #757575; margin-right: 5px; font-size: 16px;"></i>
                                                    {/if}
                                                    
                                                    <span class="category-name" {if $category.children && count($category.children) > 0}onclick="event.stopPropagation(); toggleYujuChildrenByName(this)" style="cursor: pointer;"{/if}>{$category.name|escape:'html':'UTF-8'}</span>
                                                </div>
                                                
                                                {if $category.children && count($category.children) > 0}
                                                    <div class="category-children" style="display: none;">
                                                        {call name=renderYujuTree categories=$category.children}
                                                    </div>
                                                {/if}
                                            </div>
                                        {/foreach}
                                    {/function}
                                    
                                    {call name=renderYujuTree categories=$yuju_categories}
                                {else}
                                    <p class="text-muted text-center">No hay categorías disponibles</p>
                                {/if}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" id="btn-save-mapping" disabled>
                    <i class="icon-save"></i> Guardar Mapeo
                </button>
            </div>
        </div>
    </div>
</div>

<script>
{literal}
var selectedPsCategory = null;
var selectedYujuCategory = null;
var mappedCategories = {/literal}{$category_mappings|json_encode}{literal};
var autoOpenMappingModal = {/literal}{if $auto_open_mapping_modal}true{else}false{/if}{literal};
var prefillPsCategoryId = {/literal}{$prefill_ps_category_id|intval}{literal};
var isSavingCategoryMapping = false;

function openMappingModal() {
    $('#categoryMappingModal').modal('show');
}

function updateCurrentSelectionSummary() {
    var psLabel = selectedPsCategory ? selectedPsCategory.name : 'Sin seleccionar';
    var yujuLabel = selectedYujuCategory ? selectedYujuCategory.name : 'Sin seleccionar';

    $('#selected-ps-label').text(psLabel).toggleClass('text-muted', !selectedPsCategory);
    $('#selected-yuju-label').text(yujuLabel).toggleClass('text-muted', !selectedYujuCategory);
}

function filterCategoryTree(treeSelector, query) {
    var normalized = $.trim(query).toLowerCase();
    var $tree = $(treeSelector);
    var $wrappers = $tree.find('.category-wrapper');

    if (!normalized) {
        $wrappers.show();
        $tree.find('.category-children').hide();
        return;
    }

    $wrappers.hide();
    $wrappers.each(function() {
        var $wrapper = $(this);
        var $item = $wrapper.children('.category-item');
        var categoryName = ($item.data('name') || '').toString().toLowerCase();

        if (categoryName.indexOf(normalized) !== -1) {
            $wrapper.show();
            $wrapper.parents('.category-wrapper').show();
            $wrapper.parents('.category-children').show();
        }
    });
}

// Función para seleccionar categoría de PrestaShop
function selectPsCategory(radio) {
    selectedPsCategory = {
        id: $(radio).val(),
        name: $(radio).data('name')
    };
    
    // Limpiar selección de Yuju
    $('input[name="yuju_category"]').prop('checked', false);
    selectedYujuCategory = null;
    $('#btn-save-mapping').prop('disabled', true);
    updateCurrentSelectionSummary();
}

// Función para seleccionar categoría de Yuju
function selectYujuCategory(radio) {
    selectedYujuCategory = {
        id: $(radio).val(),
        name: $(radio).data('name')
    };
    
    // Habilitar botón de guardar si ambas categorías están seleccionadas
    if (selectedPsCategory && selectedYujuCategory) {
        $('#btn-save-mapping').prop('disabled', false);
    }
    updateCurrentSelectionSummary();
}

// Función para expandir/contraer hijos de PrestaShop
function togglePsChildren(iconElement) {
    var $wrapper = $(iconElement).closest('.category-wrapper');
    var $children = $wrapper.find('> .category-children');
    var $icon = $(iconElement);
    
    if ($children.is(':visible')) {
        $children.slideUp(200);
        $icon.removeClass('icon-folder-open').addClass('icon-folder');
    } else {
        $children.slideDown(200);
        $icon.removeClass('icon-folder').addClass('icon-folder-open');
    }
}

// Función para expandir/contraer desde el nombre de PrestaShop
function togglePsChildrenByName(nameElement) {
    var $wrapper = $(nameElement).closest('.category-wrapper');
    var $icon = $wrapper.find('> .category-item > .icon-folder, > .category-item > .icon-folder-open').first();
    
    if ($icon.length) {
        togglePsChildren($icon[0]);
    }
}

// Función para expandir/contraer hijos de Yuju
function toggleYujuChildren(iconElement) {
    var $wrapper = $(iconElement).closest('.category-wrapper');
    var $children = $wrapper.find('> .category-children');
    var $icon = $(iconElement);
    
    if ($children.is(':visible')) {
        $children.slideUp(200);
        $icon.removeClass('icon-folder-open').addClass('icon-folder');
    } else {
        $children.slideDown(200);
        $icon.removeClass('icon-folder').addClass('icon-folder-open');
    }
}

// Función para expandir/contraer desde el nombre de Yuju
function toggleYujuChildrenByName(nameElement) {
    var $wrapper = $(nameElement).closest('.category-wrapper');
    var $icon = $wrapper.find('> .category-item > .icon-folder, > .category-item > .icon-folder-open').first();
    
    if ($icon.length) {
        toggleYujuChildren($icon[0]);
    }
}

$(document).ready(function() {
    // Permitir seleccionar haciendo click en toda la fila
    $(document).on('click', '#prestashop-tree .category-item, #yuju-tree .category-item', function(e) {
        var $target = $(e.target);

        // No interferir con click directo en controles interactivos
        if ($target.is('input') || $target.is('i') || $target.closest('input, i').length) {
            return;
        }

        var $item = $(this);
        if ($item.hasClass('mapped') || $item.hasClass('disabled') || $item.data('mapped') === 1) {
            return;
        }

        var $radio = $item.find('input[type="radio"]').first();
        if ($radio.length) {
            $radio.prop('checked', true).trigger('click');
        }
    });

    // Inicializar resumen
    updateCurrentSelectionSummary();

    if (autoOpenMappingModal) {
        openMappingModal();
        if (prefillPsCategoryId > 0) {
            var $radio = $('#prestashop-tree input[name="ps_category"][value="' + prefillPsCategoryId + '"]').first();
            if ($radio.length) {
                $radio.parents('.category-children').show();
                $radio.parents('.category-wrapper').children('.category-item').find('.icon-folder').removeClass('icon-folder').addClass('icon-folder-open');
                $radio.prop('checked', true).trigger('click');
                setTimeout(function () {
                    var top = Math.max(0, $radio.offset().top - $('#prestashop-tree').offset().top - 60 + $('#prestashop-tree').scrollTop());
                    $('#prestashop-tree').animate({ scrollTop: top }, 180);
                }, 120);
            }
        }
    }

    // Buscador PrestaShop
    $('#prestashop-tree-search').on('input', function() {
        filterCategoryTree('#prestashop-tree', $(this).val());
    });
    $('#prestashop-tree-clear').on('click', function() {
        $('#prestashop-tree-search').val('');
        filterCategoryTree('#prestashop-tree', '');
    });

    // Buscador Yuju
    $('#yuju-tree-search').on('input', function() {
        filterCategoryTree('#yuju-tree', $(this).val());
    });
    $('#yuju-tree-clear').on('click', function() {
        $('#yuju-tree-search').val('');
        filterCategoryTree('#yuju-tree', '');
    });

    // Guardar mapeo (protegido contra doble/múltiple click)
    $('#btn-save-mapping').off('click.yuju-save').on('click.yuju-save', function() {
        if (isSavingCategoryMapping) {
            return;
        }

        if (!selectedPsCategory || !selectedYujuCategory) {
            showToast('Debes seleccionar ambas categorías', 'error');
            return;
        }

        isSavingCategoryMapping = true;
        
        $.ajax({
            url: {/literal}'{$ajax_url|escape:'javascript':'UTF-8'}'{literal},
            method: 'POST',
            dataType: 'json',
            data: {
                ajax: true,
                action: 'saveMapping',
                prestashop_category_id: selectedPsCategory.id,
                yuju_category_id: selectedYujuCategory.id,
                yuju_category_name: selectedYujuCategory.name
            },
            beforeSend: function() {
                $('#btn-save-mapping').prop('disabled', true).html('<i class="icon-spinner icon-spin"></i> Guardando...');
            },
            success: function(response) {
                if (response.success) {
                    showToast('Mapeo guardado exitosamente', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    isSavingCategoryMapping = false;
                    $('#btn-save-mapping').prop('disabled', false).html('<i class="icon-save"></i> Guardar Mapeo');
                    showToast('Error: ' + (response.message || 'Error desconocido'), 'error');
                }
            },
            error: function() {
                isSavingCategoryMapping = false;
                $('#btn-save-mapping').prop('disabled', false).html('<i class="icon-save"></i> Guardar Mapeo');
                showToast('Error al guardar el mapeo', 'error');
            }
        });
    });

    // Reset de selección al cerrar modal
    $('#categoryMappingModal').on('hidden.bs.modal', function() {
        selectedPsCategory = null;
        selectedYujuCategory = null;
        isSavingCategoryMapping = false;
        $('input[name="ps_category"], input[name="yuju_category"]').prop('checked', false);
        $('#btn-save-mapping').prop('disabled', true);
        $('#prestashop-tree-search, #yuju-tree-search').val('');
        filterCategoryTree('#prestashop-tree', '');
        filterCategoryTree('#yuju-tree', '');
        updateCurrentSelectionSummary();
    });
});

function deleteMapping(mappingId) {
    if (!confirm('¿Estás seguro de eliminar este mapeo?')) {
        return;
    }
    
    $.ajax({
        url: {/literal}'{$ajax_url|escape:'javascript':'UTF-8'}'{literal},
        method: 'POST',
        dataType: 'json',
        data: {
            ajax: true,
            action: 'deleteMapping',
            id: mappingId
        },
        success: function(response) {
            if (response.success) {
                showToast('Mapeo eliminado exitosamente', 'success');
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                showToast('Error: ' + (response.message || 'Error desconocido'), 'error');
            }
        },
        error: function() {
            showToast('Error al eliminar el mapeo', 'error');
        }
    });
}

function showToast(message, type) {
    type = type || 'success';
    var icon = type === 'success' ? 'check-circle' : (type === 'error' ? 'exclamation-circle' : (type === 'warning' ? 'exclamation-triangle' : 'info-circle'));
    var bgColor = type === 'success' ? '#28a745' : (type === 'error' ? '#dc3545' : (type === 'warning' ? '#ffc107' : '#17a2b8'));
    
    var toast = $('<div class="toast-notification">')
        .css({
            'position': 'fixed',
            'top': '80px',
            'right': '20px',
            'z-index': '99999',
            'min-width': '320px',
            'max-width': '450px',
            'background': '#ffffff',
            'border-radius': '8px',
            'box-shadow': '0 4px 20px rgba(0,0,0,0.2)',
            'display': 'flex',
            'align-items': 'center',
            'padding': '0',
            'overflow': 'hidden'
        });
    
    var sidebar = $('<div>')
        .css({
            'width': '6px',
            'background': bgColor,
            'align-self': 'stretch'
        });
    
    var content = $('<div>')
        .css({
            'padding': '15px',
            'flex': '1',
            'display': 'flex',
            'align-items': 'center',
            'gap': '12px'
        });
    
    var iconEl = $('<i>').addClass('icon-' + icon)
        .css({
            'font-size': '24px',
            'color': bgColor
        });
    
    var messageEl = $('<span>')
        .text(message)
        .css({
            'flex': '1',
            'color': '#333',
            'font-size': '14px'
        });
    
    var closeBtn = $('<button>')
        .html('&times;')
        .css({
            'background': 'none',
            'border': 'none',
            'font-size': '24px',
            'color': '#999',
            'cursor': 'pointer',
            'padding': '0',
            'width': '30px',
            'height': '30px'
        })
        .click(function() {
            toast.remove();
        });
    
    content.append(iconEl, messageEl, closeBtn);
    toast.append(sidebar, content);
    $('body').append(toast);
    
    setTimeout(function() {
        toast.fadeOut(300, function() {
            $(this).remove();
        });
    }, 4000);
}
{/literal}
</script>

{/block}
