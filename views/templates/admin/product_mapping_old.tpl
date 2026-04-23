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
<div class="panel">
    <div class="panel-heading">
        <i class="icon-shopping-cart"></i>
        Mapeo de campos
        <span class="panel-heading-action">
            <a class="list-toolbar-btn" href="#" onclick="addProductMapping(); return false;">
                <span title="Agregar nuevo mapeo" data-toggle="tooltip">
                    <i class="process-icon-new"></i>
                </span>
            </a>
            <a class="list-toolbar-btn" href="#" onclick="refreshYujuFields(); return false;">
                <span title="Actualizar campos de Yuju" data-toggle="tooltip">
                    <i class="process-icon-refresh"></i>
                </span>
            </a>
        </span>
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-lg-12">
                <div class="table-responsive">
                    <table class="table table-striped" id="product-mappings-table">
                        <thead>
                            <tr>
                                <th>Campo PrestaShop</th>
                                <th>Campo Yuju</th>
                                <th>Tipo</th>
                                <th>Transformación</th>
                                <th>Requerido</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {if $product_mappings && count($product_mappings) > 0}
                                {foreach from=$product_mappings item=mapping}
                                    <tr data-mapping-id="{$mapping.id_mapping}">
                                        <td>
                                            {if $mapping.prestashop_field == 'name'}Nombre
                                            {elseif $mapping.prestashop_field == 'reference'}Referencia
                                            {elseif $mapping.prestashop_field == 'description'}Descripción
                                            {elseif $mapping.prestashop_field == 'description_short'}Descripción Corta
                                            {elseif $mapping.prestashop_field == 'price'}Precio Final
                                            {elseif $mapping.prestashop_field == 'quantity'}Cantidad
                                            {elseif $mapping.prestashop_field == 'weight'}Peso del paquete
                                            {elseif $mapping.prestashop_field == 'width'}Ancho del paquete
                                            {elseif $mapping.prestashop_field == 'height'}Alto del paquete
                                            {elseif $mapping.prestashop_field == 'depth'}Profundidad del paquete
                                            {elseif $mapping.prestashop_field == 'manufacturer'}Fabricante
                                            {elseif $mapping.prestashop_field == 'condition'}Condición
                                            {elseif $mapping.prestashop_field == 'ean13'}EAN13
                                            {elseif $mapping.prestashop_field == 'wholesale_price'}Precio Mayorista
                                            {else}{$mapping.prestashop_field}{/if}
                                        </td>
                                        <td>
                                            {if $mapping.yuju_field == 'name'}Nombre (Yuju) - REQUERIDO
                                            {elseif $mapping.yuju_field == 'sku'}SKU (Yuju) - REQUERIDO
                                            {elseif $mapping.yuju_field == 'sku_simple'}SKU Simple (Yuju) - REQUERIDO
                                            {elseif $mapping.yuju_field == 'description'}Descripción (Yuju) - REQUERIDO
                                            {elseif $mapping.yuju_field == 'images'}Imágenes (Yuju) - REQUERIDO
                                            {elseif $mapping.yuju_field == 'price'}Precio (Yuju) - REQUERIDO
                                            {elseif $mapping.yuju_field == 'stock_quantity'}Stock (Yuju) - REQUERIDO
                                            {elseif $mapping.yuju_field == 'brand'}Marca (Yuju) - REQUERIDO
                                            {elseif $mapping.yuju_field == 'condition'}Condición (Yuju) - REQUERIDO
                                            {elseif $mapping.yuju_field == 'shipping_method'}Método de envío (Yuju) - REQUERIDO
                                            {elseif $mapping.yuju_field == 'shipping_price'}Precio de envío (Yuju) - REQUERIDO
                                            {elseif $mapping.yuju_field == 'dimension_unit'}Unidad de dimensión (Yuju) - REQUERIDO
                                            {elseif $mapping.yuju_field == 'height'}Altura (Yuju) - REQUERIDO
                                            {elseif $mapping.yuju_field == 'width'}Ancho (Yuju) - REQUERIDO
                                            {elseif $mapping.yuju_field == 'depth'}Profundidad (Yuju) - REQUERIDO
                                            {elseif $mapping.yuju_field == 'weight_unit'}Unidad de peso (Yuju) - REQUERIDO
                                            {elseif $mapping.yuju_field == 'weight'}Peso (Yuju) - REQUERIDO
                                            {elseif $mapping.yuju_field == 'mercadolibre_template'}Plantilla de MercadoLibre (Yuju) - REQUERIDO
                                            {elseif $mapping.yuju_field == 'short_description'}Descripción Corta (Yuju)
                                            {elseif $mapping.yuju_field == 'barcode'}Código de barras (Yuju)
                                            {elseif $mapping.yuju_field == 'status'}Estado (Yuju)
                                            {elseif $mapping.yuju_field == 'cost_price'}Precio de costo (Yuju)
                                            {else}{$mapping.yuju_field}{/if}
                                        </td>
                                        <td>
                                            <span class="badge badge-info">
                                                {if $mapping.field_type == 'string'}Cadena de texto
                                                {elseif $mapping.field_type == 'integer'}Número entero
                                                {elseif $mapping.field_type == 'decimal'}Número decimal
                                                {elseif $mapping.field_type == 'boolean'}Verdadero/Falso
                                                {elseif $mapping.field_type == 'date'}Fecha
                                                {elseif $mapping.field_type == 'datetime'}Fecha y Hora
                                                {elseif $mapping.field_type == 'array'}Lista
                                                {elseif $mapping.field_type == 'object'}Objeto
                                                {else}{$mapping.field_type}{/if}
                                            </span>
                                        </td>

                                        <td>
                                            {if $mapping.transformation_rule == 'none'}Ninguna
                                            {elseif $mapping.transformation_rule == 'uppercase'}Mayúsculas
                                            {elseif $mapping.transformation_rule == 'lowercase'}Minúsculas
                                            {elseif $mapping.transformation_rule == 'capitalize'}Capitalizar
                                            {elseif $mapping.transformation_rule == 'strip_html'}Eliminar HTML
                                            {elseif $mapping.transformation_rule == 'strip_tags'}Eliminar Etiquetas
                                            {elseif $mapping.transformation_rule == 'trim'}Recortar Espacios
                                            {elseif $mapping.transformation_rule == 'number_format'}Formato de Número
                                            {elseif $mapping.transformation_rule == 'date_format'}Formato de Fecha
                                            {elseif $mapping.transformation_rule == 'boolean_convert'}Convertir a Booleano
                                            {elseif $mapping.transformation_rule == 'currency_convert'}Convertir Moneda
                                            {elseif $mapping.transformation_rule == 'custom'}Código PHP Personalizado
                                            {else}{$mapping.transformation_rule}{/if}
                                        </td>
                                        <td>
                                            {if $mapping.is_required}
                                                <span class="badge badge-success">Sí</span>
                                            {else}
                                                <span class="badge badge-light">No</span>
                                            {/if}
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-default btn-xs" onclick="editProductMapping({$mapping.id_mapping})" title="Editar">
                                                    <i class="icon-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-default btn-xs" onclick="deleteProductMapping({$mapping.id_mapping})" title="Eliminar">
                                                    <i class="icon-trash"></i>
                                                </button>
                                                {if $mapping.is_active}
                                                    <button type="button" class="btn btn-warning btn-xs" onclick="toggleMappingStatus({$mapping.id_mapping}, 0)" title="Desactivar">
                                                        <i class="icon-remove"></i>
                                                    </button>
                                                {else}
                                                    <button type="button" class="btn btn-success btn-xs" onclick="toggleMappingStatus({$mapping.id_mapping}, 1)" title="Activar">
                                                        <i class="icon-check"></i>
                                                    </button>
                                                {/if}
                                            </div>
                                        </td>
                                    </tr>
                                {/foreach}
                            {else}
                                <tr>
                                    <td colspan="9" class="text-center">
                                        <p>No hay mapeos de productos configurados.</p>
                                        <button type="button" class="btn btn-primary" onclick="addProductMapping()">
                                            <i class="icon-plus"></i> Agregar primer mapeo
                                        </button>
                                    </td>
                                </tr>
                            {/if}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para agregar/editar mapeo -->
<div class="modal fade" id="productMappingModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Mapeo de campos</h4>
            </div>
            <div class="modal-body">
                <form id="productMappingForm">
                    <input type="hidden" id="mapping_id" name="id" value="">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="prestashop_field">Campo PrestaShop *</label>
                                <select class="form-control" id="prestashop_field" name="prestashop_field" required>
                                    <option value="">Seleccionar campo...</option>
                                    {if $prestashop_fields}
                                        {foreach from=$prestashop_fields item=field}
                                            <option value="{$field.id}">{$field.name}</option>
                                        {/foreach}
                                    {/if}
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="yuju_field">Campo Yuju *</label>
                                <select class="form-control" id="yuju_field" name="yuju_field" required>
                                    <option value="">Seleccionar campo...</option>
                                    {if $yuju_fields}
                                        {foreach from=$yuju_fields item=field}
                                            <option value="{$field.id}">{$field.name}</option>
                                        {/foreach}
                                    {/if}
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="field_type">Tipo de Campo *</label>
                                <select class="form-control" id="field_type" name="field_type" required>
                                    <option value="string">Cadena de texto</option>
                                    <option value="integer">Número entero</option>
                                    <option value="decimal">Número decimal</option>
                                    <option value="boolean">Verdadero/Falso</option>
                                    <option value="date">Fecha</option>
                                    <option value="array">Lista</option>
                                    <option value="object">Objeto</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="transformation_rule">Regla de Transformación *</label>
                                <select class="form-control" id="transformation_rule" name="transformation_rule" required>
                                    <option value="none">Ninguna</option>
                                    <option value="uppercase">Mayúsculas</option>
                                    <option value="lowercase">Minúsculas</option>
                                    <option value="capitalize">Capitalizar</option>
                                    <option value="trim">Recortar espacios</option>
                                    <option value="custom">Personalizada</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row" id="custom_transformation_row" style="display: none;">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="custom_transformation">Transformación Personalizada</label>
                                <textarea class="form-control" id="custom_transformation" name="custom_transformation" rows="3" placeholder="Código PHP para transformación personalizada..."></textarea>
                                <small class="help-block">Ejemplo: return strtoupper($value);</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="default_value">Valor por Defecto</label>
                                <input type="text" class="form-control" id="default_value" name="default_value" placeholder="Valor por defecto si está vacío...">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <div>
                                    <label class="checkbox-inline">
                                        <input type="checkbox" id="is_required" name="is_required" value="1"> Campo requerido
                                    </label>
                                    <label class="checkbox-inline">
                                        <input type="checkbox" id="is_active" name="is_active" value="1" checked> Activo
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="saveProductMapping()">Guardar</button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
function addProductMapping() {
    $('#productMappingForm')[0].reset();
    $('#mapping_id').val('');
    $('#is_active').prop('checked', true);
    $('#productMappingModal .modal-title').text('Agregar Mapeo de Producto');
    $('#productMappingModal').modal('show');
}

function editProductMapping(id) {
    $.ajax({
        url: '{$ajax_url}',
        type: 'POST',
        data: {
            ajax: true,
            action: 'GetMapping',
            id_mapping: id,
            token: '{$token}'
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                var mapping = response.data;
                $('#mapping_id').val(mapping.id_mapping);
                $('#prestashop_field').val(mapping.prestashop_field);
                $('#yuju_field').val(mapping.yuju_field);
                $('#field_type').val(mapping.field_type);

                $('#transformation_rule').val(mapping.transformation_rule);
                $('#custom_transformation').val(mapping.custom_transformation);
                $('#default_value').val(mapping.default_value);
                $('#is_required').prop('checked', mapping.is_required == 1);
                $('#is_active').prop('checked', mapping.is_active == 1);
                
                if (mapping.transformation_rule === 'custom') {
                    $('#custom_transformation_row').show();
                }
                
                $('#productMappingModal .modal-title').text('Editar Mapeo de campos');
                $('#productMappingModal').modal('show');
            } else {
                showNotification('error', response.message || 'Error al cargar el mapeo');
            }
        },
        error: function() {
            showNotification('error', 'Error de conexión');
        }
    });
}

function saveProductMapping() {
    var formData = {
        ajax: true,
        action: 'SaveMapping',
        token: '{$token}',
        id_mapping: $('#mapping_id').val(),
        prestashop_field: $('#prestashop_field').val(),
        yuju_field: $('#yuju_field').val(),
        field_type: $('#field_type').val(),
        sync_direction: 'prestashop_to_yuju',
        transformation_rule: $('#transformation_rule').val(),
        custom_transformation: $('#custom_transformation').val(),
        default_value: $('#default_value').val(),
        is_required: $('#is_required').is(':checked') ? 1 : 0,
        is_active: $('#is_active').is(':checked') ? 1 : 0
    };
    
    $.ajax({
        url: '{$ajax_url}',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showNotification('success', response.message);
                $('#productMappingModal').modal('hide');
                location.reload();
            } else {
                showNotification('error', response.message);
            }
        },
        error: function() {
            showNotification('error', 'Error de conexión');
        }
    });
}

function deleteProductMapping(id) {
    if (confirm('¿Está seguro de que desea eliminar este mapeo?')) {
        $.ajax({
            url: '{$ajax_url}',
            type: 'POST',
            data: {
                ajax: true,
                action: 'DeleteMapping',
                id_mapping: id,
                token: '{$token}'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showNotification('success', response.message);
                    $('tr[data-mapping-id="' + id + '"]').fadeOut(function() {
                        $(this).remove();
                    });
                } else {
                    showNotification('error', response.message);
                }
            },
            error: function() {
                showNotification('error', 'Error de conexión');
            }
        });
    }
}

function toggleMappingStatus(id, status) {
    $.ajax({
        url: '{$ajax_url}',
        type: 'POST',
        data: {
            ajax: true,
            action: 'ToggleStatus',
            id: id,
            status: status,
            token: '{$token}'
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showNotification('success', response.message);
                location.reload();
            } else {
                showNotification('error', response.message);
            }
        },
        error: function() {
            showNotification('error', 'Error de conexión');
        }
    });
}

function refreshYujuFields() {
    $.ajax({
        url: '{$ajax_url}',
        type: 'POST',
        data: {
            ajax: true,
            action: 'RefreshYujuFields',
            token: '{$token}'
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showNotification('success', response.message);
                // Actualizar el select de campos Yuju
                var yujuSelect = $('#yuju_field');
                yujuSelect.empty().append('<option value="">Seleccionar campo...</option>');
                if (response.fields) {
                    $.each(response.fields, function(index, field) {
                        yujuSelect.append('<option value="' + field.name + '" data-type="' + field.type + '">' + field.label + ' (' + field.name + ')</option>');
                    });
                }
            } else {
                showNotification('error', response.message);
            }
        },
        error: function() {
            showNotification('error', 'Error de conexión');
        }
    });
}

function showNotification(type, message) {
    var alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    var notification = '<div class="alert ' + alertClass + ' alert-dismissible" role="alert">' +
        '<button type="button" class="close" data-dismiss="alert">&times;</button>' +
        message + '</div>';
    
    $('.panel-body').prepend(notification);
    
    setTimeout(function() {
        $('.alert').fadeOut();
    }, 5000);
}

$(document).ready(function() {
    // Mostrar/ocultar transformación personalizada
    $('#transformation_rule').change(function() {
        if ($(this).val() === 'custom') {
            $('#custom_transformation_row').show();
        } else {
            $('#custom_transformation_row').hide();
        }
    });
    
    // Auto-detectar tipo de campo basado en el campo Yuju seleccionado
    $('#yuju_field').change(function() {
        var selectedOption = $(this).find('option:selected');
        var fieldType = selectedOption.data('type');
        if (fieldType) {
            $('#field_type').val(fieldType);
        }
    });
});
</script>
{/block}