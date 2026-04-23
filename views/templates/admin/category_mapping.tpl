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
    padding: 15px;
    max-height: 400px;
    overflow-y: auto;
}
.category-item {
    padding: 8px 12px;
    margin: 4px 0;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
    display: flex;
    align-items: center;
    gap: 8px;
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
    margin-left: 24px;
    border-left: 2px dashed #e0e0e0;
    padding-left: 8px;
}

.category-name {
    flex: 1;
    font-size: 14px;
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
    font-size: 16px;
}

.yuju-category-selector {
    background: white;
    border: 1px solid #e5e5e5;
    border-radius: 4px;
    padding: 15px;
}
.mapping-table {
    margin-top: 20px;
}
.mapping-row:hover {
    background-color: #f8f9fa;
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
                <div class="alert alert-warning">
                    <i class="icon-info-circle"></i>
                    <strong>Instrucciones:</strong>
                    <ol style="margin: 10px 0 0 20px;">
                        <li>Selecciona una categoría de PrestaShop del árbol (izquierda)</li>
                        <li>Selecciona la categoría correspondiente de Yuju (derecha)</li>
                        <li>Las categorías con <i class="icon-exchange" style="color: #4caf50;"></i> ya están mapeadas</li>
                        <li>No se pueden mapear categorías ya mapeadas ni sus hijas</li>
                    </ol>
                </div>
                
                <div class="row">
                    <!-- Árbol de Categorías PrestaShop -->
                    <div class="col-md-6">
                        <h5><i class="icon-folder-open"></i> Selecciona una categoría de tu Prestashop</h5>
                        <div class="category-tree" id="prestashop-tree" style="max-height: 450px;">
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
                        <div class="yuju-category-selector">
                            <div class="category-tree" id="yuju-tree" style="max-height: 450px;">
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

function openMappingModal() {
    $('#categoryMappingModal').modal('show');
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
    // Guardar mapeo
    $('#btn-save-mapping').click(function() {
        if (!selectedPsCategory || !selectedYujuCategory) {
            showToast('Debes seleccionar ambas categorías', 'error');
            return;
        }
        
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
                $('#btn-save-mapping').prop('disabled', false).html('<i class="icon-save"></i> Guardar Mapeo');
                
                if (response.success) {
                    showToast('Mapeo guardado exitosamente', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showToast('Error: ' + (response.message || 'Error desconocido'), 'error');
                }
            },
            error: function() {
                $('#btn-save-mapping').prop('disabled', false).html('<i class="icon-save"></i> Guardar Mapeo');
                showToast('Error al guardar el mapeo', 'error');
            }
        });
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
