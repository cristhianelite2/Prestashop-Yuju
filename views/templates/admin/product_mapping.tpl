{*
* 2024 Yuju Integration - Mapeo de Campos de Productos
*}

{extends file="./layout.tpl"}

{block name="content"}
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<div class="alert alert-info">
    <i class="icon-info-circle"></i>
    <strong>Información:</strong>
    Los campos con fondo amarillo son obligatorios según la API de Yuju y no se pueden eliminar. Son necesarios para sincronizar productos con los marketplaces.
</div>

<style>
.mapping-row {
    background: white;
    padding: 8px;
    margin-bottom: 5px;
    border-radius: 3px;
    border: 1px solid #e5e5e5;
    transition: all 0.2s;
}
.mapping-row:hover {
    box-shadow: 0 1px 4px rgba(0,0,0,0.1);
}
.mapping-header {
    background: #f8f9fa;
    padding: 8px 10px;
    border-radius: 3px 3px 0 0;
    border: 1px solid #dee2e6;
    font-weight: 600;
    color: #495057;
    font-size: 13px;
}
.select2-container {
    width: 100% !important;
}
.select2-container--default .select2-selection--single {
    height: 32px;
    border: 1px solid #ced4da;
    border-radius: 3px;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 30px;
    padding-left: 8px;
    padding-right: 48px;
    font-size: 13px;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 30px;
    right: 6px;
    width: 18px;
}
/* Separar la X del chevron para evitar clics accidentales */
.select2-container--default .select2-selection--single .select2-selection__clear {
    position: absolute;
    right: 30px;
    margin-right: 0;
    padding: 0 4px;
    z-index: 2;
}
/* Ocultar el select original cuando Select2 está activo */
.select2-hidden-accessible {
    display: none !important;
}
.btn-remove-mapping {
    background: white;
    border: 1px solid #dc3545;
    color: #dc3545;
    padding: 5px 10px;
    font-size: 12px;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s;
}
.btn-remove-mapping:hover {
    background: #dc3545;
    color: white;
}
.default-value-input {
    border: 1px solid #ced4da;
    border-radius: 3px;
    padding: 6px 8px;
    width: 100%;
    font-size: 12px;
    height: 28px;
}
.tooltip-info {
    color: #6c757d;
    cursor: help;
    margin-left: 5px;
}
.required-mapping {
    border-left: 3px solid #ff9800;
}
.required-mapping .yuju-field:disabled {
    background-color: #f5f5f5;
    cursor: not-allowed;
}
.btn-remove-mapping.disabled {
    background-color: #ccc;
    cursor: not-allowed;
    opacity: 0.6;
}
.btn-group .btn {
    white-space: nowrap;
}
.dropdown-toggle {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.dropdown-toggle i {
    margin-right: 3px;
}
</style>

<div class="panel">
    <div class="panel-heading">
        <i class="icon-exchange"></i>
        Campos Mapeados
        <span class="panel-heading-action">
            <button class="btn btn-success btn-sm" onclick="addNewMapping(); return false;">
                <i class="icon-plus"></i> Nuevo Mapeo de Campo
            </button>
        </span>
    </div>
    
    <div class="panel-body" style="padding: 10px;">
        <div id="mappings-container">
            <!-- Header row -->
            <div class="row mapping-header">
                <div class="col-md-3">Campo en Prestashop</div>
                <div class="col-md-3">Campo en Madkting</div>
                <div class="col-md-3">Valor por Defecto</div>
                <div class="col-md-3">Acciones</div>
            </div>
            
            <!-- Mappings will be loaded here -->
            <div id="mappings-list"></div>
        </div>
        
        <div class="row" style="margin-top: 10px;">
            <div class="col-md-6 text-left">
                <button
                    type="button"
                    class="btn btn-warning btn-sm"
                    onclick="if (confirm('¿Desea recargar los valores por defecto? Esta acción reemplazará los mapeos actuales.')) { window.location.href='{$link->getAdminLink('AdminYujuProductMapping')|escape:'javascript':'UTF-8'}&loadDefaults=1'; } return false;"
                >
                    <i class="icon-refresh"></i> Recargar valores por defecto
                </button>
            </div>
            <div class="col-md-6 text-right">
                <button class="btn btn-primary btn-sm" onclick="saveMappings()">
                    <i class="icon-save"></i> Guardar Mapeos
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal for adding new mapping -->
<div class="modal fade" id="addMappingModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="icon-plus"></i> Mapeo de Campos</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Selecciona un campo de prestashop</label>
                    <select id="modal-prestashop-field" class="form-control">
                        <option value="">Seleccionar campo</option>
                        {foreach $prestashop_fields as $field}
                            <option value="{$field.id|escape:'html':'UTF-8'}">{$field.name|escape:'html':'UTF-8'}</option>
                        {/foreach}
                    </select>
                </div>
                <div class="form-group">
                    <label>Selecciona un campo de yuju</label>
                    <select id="modal-yuju-field" class="form-control">
                        <option value="">Seleccionar campo</option>
                        {foreach $yuju_fields as $field}
                            <option value="{$field.id|escape:'html':'UTF-8'}">{$field.name|escape:'html':'UTF-8'}</option>
                        {/foreach}
                    </select>
                </div>
                <div class="form-group">
                    <button type="button" class="btn btn-default btn-block" onclick="addAnotherMapping()">
                        <i class="icon-plus"></i> Agregar Mapeo
                    </button>
                </div>
                <div id="modal-mappings-preview"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" onclick="saveModalMappings()">
                    <i class="icon-save"></i> Guardar Mapeos
                </button>
            </div>
        </div>
    </div>
</div>

<script>
{literal}
function showToast(message, type) {
    type = type || 'success';
    var icon = type === 'success' ? 'check-circle' : (type === 'error' ? 'exclamation-circle' : 'info-circle');
    var bgColor = type === 'success' ? '#28a745' : (type === 'error' ? '#dc3545' : '#17a2b8');
    var iconColor = '#ffffff';
    
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
            'overflow': 'hidden',
            'animation': 'slideInRight 0.3s ease-out'
        });
    
    var iconContainer = $('<div class="toast-icon">')
        .css({
            'background-color': bgColor,
            'padding': '16px',
            'display': 'flex',
            'align-items': 'center',
            'justify-content': 'center',
            'min-width': '50px'
        })
        .append($('<i class="icon-' + icon + '">').css({
            'color': iconColor,
            'font-size': '24px'
        }));
    
    var contentContainer = $('<div class="toast-content">')
        .css({
            'flex': '1',
            'padding': '16px 20px',
            'color': '#333',
            'font-size': '14px',
            'line-height': '1.4'
        })
        .text(message);
    
    var closeBtn = $('<button type="button" class="toast-close">')
        .css({
            'background': 'none',
            'border': 'none',
            'color': '#999',
            'font-size': '20px',
            'padding': '0 16px',
            'cursor': 'pointer',
            'line-height': '1'
        })
        .html('&times;')
        .on('click', function() {
            toast.fadeOut(300, function() { $(this).remove(); });
        });
    
    toast.append(iconContainer).append(contentContainer).append(closeBtn);
    $('body').append(toast);
    
    setTimeout(function() {
        toast.fadeOut(400, function() {
            $(this).remove();
        });
    }, 4000);
}

var prestashopFields = {/literal}{$prestashop_fields|json_encode}{literal};
var yujuFields = {/literal}{$yuju_fields|json_encode}{literal};
var currentMappings = {/literal}{$product_mappings|json_encode}{literal};
var mappingsCounter = 0;
var modalMappings = [];

// Campos obligatorios de Yuju según API (no se pueden eliminar)
var requiredYujuFields = [
    'sku_simple', 'sku', 'name', 'description', 'id_category', 'stock', 'price',
    'brand', 'shipping', 'dimensions_unit', 'shipping_width', 'shipping_depth',
    'shipping_height', 'weight_unit', 'weight', 'images'
];

// Default mappings basados en campos obligatorios de la API de Yuju
var defaultMappings = [
    // Campos obligatorios
    { ps: 'name', yuju: 'name', default: '' },
    { ps: 'reference', yuju: 'sku', default: '' },
    { ps: 'reference', yuju: 'sku_simple', default: '' },
    { ps: 'description', yuju: 'description', default: 'Descripcion no disponible' },
    { ps: 'id_category_default', yuju: 'id_category', default: '' },
    { ps: 'quantity', yuju: 'stock', default: '' },
    { ps: 'price', yuju: 'price', default: '' },
    { ps: 'manufacturer_name', yuju: 'brand', default: 'Global-Laptops' },
    { ps: 'available_for_order', yuju: 'shipping', default: '1' },
    { ps: 'unit_dimension', yuju: 'dimensions_unit', default: 'cm' },
    { ps: 'width', yuju: 'shipping_width', default: '8' },
    { ps: 'depth', yuju: 'shipping_depth', default: '35' },
    { ps: 'height', yuju: 'shipping_height', default: '44' },
    { ps: 'unit_weight', yuju: 'weight_unit', default: 'kg' },
    { ps: 'weight', yuju: 'weight', default: '1' },
    { ps: 'images', yuju: 'images', default: '' },
    // Campos opcionales
    { ps: 'condition', yuju: 'condition', default: 'new' },
    { ps: 'ean13', yuju: 'ean', default: '' },
    { ps: 'upc', yuju: 'upc', default: '' }
];

function validateYujuDuplicates() {
    var yujuFieldsUsed = {};
    var hasDuplicates = false;
    
    // Resetear todos los bordes
    $('.mapping-row').each(function() {
        $(this).css('border', '');
        $(this).find('.yuju-field').css('border-color', '');
    });
    
    // Verificar duplicados
    $('.mapping-row').each(function() {
        var $row = $(this);
        var yujuField = $row.find('.yuju-field').val();
        
        if (yujuField) {
            if (yujuFieldsUsed[yujuField]) {
                // Marcar ambas filas como duplicadas
                $row.css('border', '2px solid #dc3545');
                $row.find('.yuju-field').css('border-color', '#dc3545');
                yujuFieldsUsed[yujuField].css('border', '2px solid #dc3545');
                yujuFieldsUsed[yujuField].find('.yuju-field').css('border-color', '#dc3545');
                hasDuplicates = true;
            } else {
                yujuFieldsUsed[yujuField] = $row;
            }
        }
    });
    
    return !hasDuplicates;
}

$(document).ready(function() {
    initializeSelect2();
    loadMappings();
    
    // Validar duplicados en tiempo real
    $(document).on('change', '.yuju-field', function() {
        validateYujuDuplicates();
    });
    
    // Reinicializar Select2 cada vez que se abre el modal
    $('#addMappingModal').on('shown.bs.modal', function() {
        // Destruir Select2 si existe
        if ($('#modal-prestashop-field').data('select2')) {
            $('#modal-prestashop-field').select2('destroy');
        }
        if ($('#modal-yuju-field').data('select2')) {
            $('#modal-yuju-field').select2('destroy');
        }
        
        // Inicializar Select2 con las opciones del DOM
        $('#modal-prestashop-field').select2({
            placeholder: 'Seleccionar campo',
            allowClear: true,
            width: '100%',
            dropdownParent: $('#addMappingModal')
        });
        
        $('#modal-yuju-field').select2({
            placeholder: 'Seleccionar campo',
            allowClear: true,
            width: '100%',
            dropdownParent: $('#addMappingModal')
        });
    });
    
    // Limpiar el modal cuando se cierra
    $('#addMappingModal').on('hidden.bs.modal', function() {
        // Reset data
        modalMappings = [];
        $('#modal-mappings-preview').html('');
        
        // Resetear valores
        $('#modal-prestashop-field').val('');
        $('#modal-yuju-field').val('');
    });
});

function initializeSelect2() {
    $('.select2').select2({
        placeholder: 'Seleccionar campo',
        allowClear: true,
        width: '100%'
    });
}

function loadMappings() {
    var html = '';
    if (currentMappings && currentMappings.length > 0) {
        currentMappings.forEach(function(mapping, index) {
            html += renderMappingRow(mapping, index);
        });
    }
    $('#mappings-list').html(html);
    initializeSelect2();
}

function renderMappingRow(mapping, index) {
    var psFieldName = getFieldName(mapping.prestashop_field, prestashopFields);
    var yujuFieldName = getFieldName(mapping.yuju_field, yujuFields);
    var defaultValue = mapping.default_value || '';
    var isRequired = requiredYujuFields.includes(mapping.yuju_field);
    var showDefaultValue = defaultValue !== '';
    
    var rowClass = 'row mapping-row' + (isRequired ? ' required-mapping' : '');
    var html = '<div class="' + rowClass + '" data-index="' + index + '" data-required="' + isRequired + '">';
    
    // Columna 1: Campo PrestaShop
    html += '<div class="col-md-3">';
    html += '<select class="form-control select2 ps-field" name="mappings[' + index + '][ps_field]" data-index="' + index + '">';
    html += renderFieldOptions(prestashopFields, mapping.prestashop_field);
    html += '</select></div>';
    
    // Columna 2: Campo Yuju
    html += '<div class="col-md-3">';
    html += '<select class="form-control select2 yuju-field" name="mappings[' + index + '][yuju_field]" data-index="' + index + '"';
    if (isRequired) {
        html += ' disabled title="Campo obligatorio - No se puede cambiar"';
    }
    html += '>';
    html += renderFieldOptions(yujuFields, mapping.yuju_field);
    html += '</select></div>';
    
    // Columna 3: Valor por Defecto (colapsable)
    html += '<div class="col-md-3">';
    html += '<div id="default-value-container-' + index + '" style="display: ' + (showDefaultValue ? 'block' : 'none') + '; max-width: 250px;">';
    html += '<input type="text" class="form-control input-sm default-value-input" name="mappings[' + index + '][default_value]" ';
    html += 'value="' + defaultValue + '" placeholder="Valor si está vacío" style="font-size: 12px; height: 28px; padding: 4px 8px;">';
    html += '</div></div>';
    
    // Columna 4: Acciones (Dropdown)
    html += '<div class="col-md-3">';
    html += '<div class="btn-group">';
    html += '<button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" style="display: inline-flex; align-items: center; white-space: nowrap;">';
    html += '<i class="icon-cog" style="margin-right: 5px;"></i> Acciones <span class="caret" style="margin-left: 5px;"></span></button>';
    html += '<ul class="dropdown-menu" role="menu">';
    
    // Opción: Agregar/Quitar valor por defecto
    html += '<li><a href="#" onclick="toggleDefaultValueField(' + index + '); return false;">';
    html += '<i class="icon-edit"></i> <span id="default-toggle-text-' + index + '">';
    html += (showDefaultValue ? 'Quitar' : 'Agregar') + ' Valor por Defecto</span></a></li>';
    
    // Opción: Eliminar (solo si no es obligatorio)
    if (!isRequired) {
        html += '<li class="divider"></li>';
        html += '<li><a href="#" onclick="removeMapping(' + index + '); return false;">';
        html += '<i class="icon-times"></i> Eliminar Mapeo</a></li>';
    } else {
        html += '<li class="divider"></li>';
        html += '<li class="disabled"><a href="#"><i class="icon-lock"></i> Campo Obligatorio</a></li>';
    }
    
    html += '</ul></div></div>';
    html += '</div>';
    
    return html;
}

function renderFieldOptions(fields, selectedValue) {
    var html = '<option value="">Selecciona un campo</option>';
    fields.forEach(function(field) {
        var selected = field.id == selectedValue ? 'selected' : '';
        html += '<option value="' + field.id + '" ' + selected + '>' + field.name + '</option>';
    });
    return html;
}

function getFieldName(fieldId, fieldsArray) {
    var field = fieldsArray.find(f => f.id == fieldId);
    return field ? field.name : fieldId;
}

function toggleDefaultValueField(index) {
    var container = $('#default-value-container-' + index);
    var text = $('#default-toggle-text-' + index);
    
    if (container.is(':visible')) {
        container.slideUp(200);
        // Limpiar el valor cuando se oculta
        container.find('input').val('');
        text.text('Agregar Valor por Defecto');
    } else {
        container.slideDown(200);
        text.text('Quitar Valor por Defecto');
        // Enfocar el input
        setTimeout(function() {
            container.find('input').focus();
        }, 250);
    }
}

function addNewMapping() {
    // Just show the modal, initialization is handled by the event listener
    $('#addMappingModal').modal('show');
}

function addAnotherMapping() {
    var psField = $('#modal-prestashop-field').val();
    var yujuField = $('#modal-yuju-field').val();
    
    if (!psField || !yujuField) {
        showToast('Debe seleccionar ambos campos', 'error');
        return;
    }
    
    modalMappings.push({
        prestashop_field: psField,
        yuju_field: yujuField,
        default_value: ''
    });
    
    updateModalPreview();
    
    // Reset selects
    $('#modal-prestashop-field').val('').trigger('change');
    $('#modal-yuju-field').val('').trigger('change');
}

function updateModalPreview() {
    var html = '<div class="alert alert-info"><strong>Mapeos agregados:</strong><ul>';
    modalMappings.forEach(function(mapping) {
        var psName = getFieldName(mapping.prestashop_field, prestashopFields);
        var yujuName = getFieldName(mapping.yuju_field, yujuFields);
        html += '<li>' + psName + ' → ' + yujuName + '</li>';
    });
    html += '</ul></div>';
    $('#modal-mappings-preview').html(html);
}

function saveModalMappings() {
    // Si hay campos seleccionados y no se agregaron a modalMappings, agregarlos automáticamente
    var psField = $('#modal-prestashop-field').val();
    var yujuField = $('#modal-yuju-field').val();
    
    if (psField && yujuField) {
        // Verificar si ya existe en modalMappings
        var exists = modalMappings.some(function(m) {
            return m.prestashop_field === psField && m.yuju_field === yujuField;
        });
        
        if (!exists) {
            modalMappings.push({
                prestashop_field: psField,
                yuju_field: yujuField,
                default_value: ''
            });
        }
    }
    
    if (modalMappings.length === 0) {
        showToast('Debe seleccionar al menos un mapeo', 'error');
        return;
    }
    
    // Agregar los mapeos visualmente
    var html = '';
    modalMappings.forEach(function(mapping) {
        html += renderMappingRow(mapping, mappingsCounter++);
    });
    
    $('#mappings-list').append(html);
    initializeSelect2();
    
    // Cerrar el modal
    $('#addMappingModal').modal('hide');
    
    // Guardar automáticamente en la base de datos
    saveMappings();
}

function removeMapping(index) {
    if (confirm('¿Está seguro de eliminar este mapeo?')) {
        $('[data-index="' + index + '"]').fadeOut(300, function() {
            $(this).remove();
            showToast('Mapeo eliminado', 'success');
        });
    }
}

function saveMappings() {
    var mappings = [];
    var yujuFieldsUsed = [];
    var hasDuplicates = false;
    
    $('.mapping-row').each(function() {
        var $row = $(this);
        var psField = $row.find('.ps-field').val();
        var yujuField = $row.find('.yuju-field').val();
        var defaultValue = $row.find('.default-value-input').val() || '';
        
        if (psField && yujuField) {
            // Verificar si el campo de Yuju ya fue usado
            if (yujuFieldsUsed.indexOf(yujuField) !== -1) {
                hasDuplicates = true;
                $row.css('border', '2px solid #dc3545');
                $row.find('.yuju-field').css('border-color', '#dc3545');
            } else {
                yujuFieldsUsed.push(yujuField);
                $row.css('border', '');
                $row.find('.yuju-field').css('border-color', '');
            }
            
            mappings.push({
                prestashop_field: psField,
                yuju_field: yujuField,
                default_value: defaultValue
            });
        }
    });
    
    if (hasDuplicates) {
        showToast('Error: Hay campos de Yuju duplicados. Cada campo de Yuju solo puede mapearse una vez.', 'error');
        return;
    }
    
    if (mappings.length === 0) {
        showToast('No hay mapeos para guardar', 'error');
        return;
    }
    
    console.log('Guardando mapeos:', mappings);
    
    $.ajax({
        url: {/literal}'{$ajax_url|escape:'javascript':'UTF-8'}'{literal},
        method: 'POST',
        dataType: 'json',
        data: {
            ajax: true,
            action: 'saveMappings',
            mappings: JSON.stringify(mappings)
        },
        beforeSend: function() {
            $('.btn-primary').prop('disabled', true).html('<i class="icon-spinner icon-spin"></i> Guardando...');
        },
        success: function(response) {
            console.log('Respuesta del servidor:', response);
            $('.btn-primary').prop('disabled', false).html('<i class="icon-save"></i> Guardar Mapeos');
            
            if (response.success) {
                showToast('Mapeos guardados exitosamente', 'success');
            } else {
                showToast('Error al guardar: ' + (response.message || 'Error desconocido'), 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX:', xhr.responseText);
            $('.btn-primary').prop('disabled', false).html('<i class="icon-save"></i> Guardar Mapeos');
            showToast('Error de conexión: ' + error, 'error');
        }
    });
}
{/literal}
</script>
{/block}
