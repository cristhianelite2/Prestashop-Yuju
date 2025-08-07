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
            <i class="icon-tags"></i>
            Mapeo de Atributos
            <span class="panel-heading-action">
                <button type="button" class="btn btn-default" id="refresh-yuju-attributes">
                    <i class="icon-refresh"></i> Actualizar Atributos Yuju
                </button>
                <button type="button" class="btn btn-primary" id="add-attribute-mapping">
                    <i class="icon-plus"></i> Nuevo Mapeo
                </button>
            </span>
        </div>
        <div class="panel-body">
            <div class="table-responsive">
                <table class="table table-striped" id="attribute-mappings-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Atributo PrestaShop</th>
                            <th>Atributo Yuju</th>
                            <th>Tipo de Campo</th>

                            <th>Activo</th>
                            <th>Fecha Creación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        {if $attribute_mappings && count($attribute_mappings) > 0}
                            {foreach from=$attribute_mappings item=mapping}
                                <tr data-mapping-id="{$mapping.id_mapping}">
                                    <td>{$mapping.id_mapping}</td>
                                    <td>
                                        <strong>{$mapping.prestashop_attribute_name|default:$mapping.prestashop_attribute_id}</strong>
                                        {if $mapping.prestashop_attribute_group_name}
                                            <br><small class="text-muted">Grupo: {$mapping.prestashop_attribute_group_name}</small>
                                        {/if}
                                    </td>
                                    <td>
                                        <strong>{$mapping.yuju_attribute_name|default:$mapping.yuju_attribute_id}</strong>
                                        {if $mapping.yuju_attribute_type}
                                            <br><small class="text-muted">Tipo: {$mapping.yuju_attribute_type}</small>
                                        {/if}
                                    </td>
                                    <td>
                                        <span class="badge badge-info">
                                            {if $mapping.field_type == 'Select'}Seleccionar
                                            {elseif $mapping.field_type == 'Text'}Texto
                                            {elseif $mapping.field_type == 'Number'}Número
                                            {elseif $mapping.field_type == 'Boolean'}Verdadero/Falso
                                            {elseif $mapping.field_type == 'Date'}Fecha
                                            {elseif $mapping.field_type == 'Color'}Color
                                            {else}{$mapping.field_type}{/if}
                                        </span>
                                    </td>

                                    <td>
                                        <div class="form-group">
                                            <div class="switch prestashop-switch fixed-width-lg">
                                                <input type="radio" name="mapping_active_{$mapping.id_mapping}" id="mapping_active_{$mapping.id_mapping}_on" value="1" {if $mapping.is_active}checked="checked"{/if} class="toggle-mapping" data-mapping-id="{$mapping.id_mapping}">
                                                <label for="mapping_active_{$mapping.id_mapping}_on">Sí</label>
                                                <input type="radio" name="mapping_active_{$mapping.id_mapping}" id="mapping_active_{$mapping.id_mapping}_off" value="0" {if !$mapping.is_active}checked="checked"{/if} class="toggle-mapping" data-mapping-id="{$mapping.id_mapping}">
                                                <label for="mapping_active_{$mapping.id_mapping}_off">No</label>
                                                <a class="slide-button btn"></a>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{$mapping.created_at|date_format:"%d/%m/%Y %H:%M"}</td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-default btn-sm edit-mapping" data-mapping-id="{$mapping.id_mapping}" title="Editar">
                                                <i class="icon-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm delete-mapping" data-mapping-id="{$mapping.id_mapping}" title="Eliminar">
                                                <i class="icon-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            {/foreach}
                        {else}
                            <tr>
                                <td colspan="8" class="text-center">
                                    <p class="text-muted">No hay mapeos de atributos configurados.</p>
                                    <button type="button" class="btn btn-primary" id="add-first-mapping">
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

    <!-- Modal para crear/editar mapeo -->
    <div class="modal fade" id="attribute-mapping-modal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title">Mapeo de Atributos</h4>
                </div>
                <div class="modal-body">
                    <form id="attribute-mapping-form">
                        <input type="hidden" id="mapping-id" name="id_mapping" value="">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="prestashop-attribute-group">Grupo de Atributos PrestaShop *</label>
                                    <select class="form-control" id="prestashop-attribute-group" name="prestashop_attribute_group_id" required>
                                        <option value="">Seleccionar grupo...</option>
                                        {if $prestashop_attribute_groups}
                                            {foreach from=$prestashop_attribute_groups item=group}
                                                <option value="{$group.id}">{$group.name}</option>
                                            {/foreach}
                                        {/if}
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="prestashop-attribute">Atributo PrestaShop *</label>
                                    <select class="form-control" id="prestashop-attribute" name="prestashop_attribute_id" required>
                                        <option value="">Seleccionar atributo...</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="yuju-attribute-group">Grupo de Atributos Yuju *</label>
                                    <select class="form-control" id="yuju-attribute-group" name="yuju_attribute_group_id" required>
                                        <option value="">Seleccionar grupo...</option>
                                        {if $yuju_attribute_groups}
                                            {foreach from=$yuju_attribute_groups item=group}
                                                <option value="{$group.id}">{$group.name}</option>
                                            {/foreach}
                                        {/if}
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="yuju-attribute">Atributo Yuju *</label>
                                    <select class="form-control" id="yuju-attribute" name="yuju_attribute_id" required>
                                        <option value="">Seleccionar atributo...</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="field-type">Tipo de Campo *</label>
                                    <select class="form-control" id="field-type" name="field_type" required>
                                        <option value="">Seleccionar tipo...</option>
                                        {if $field_types}
                                            {foreach from=$field_types item=type}
                                                <option value="{$type.id}">{$type.name}</option>
                                            {/foreach}
                                        {/if}
                                    </select>
                                </div>
                            </div>

                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="transformation-rule">Regla de Transformación *</label>
                                    <select class="form-control" id="transformation-rule" name="transformation_rule" required>
                                        {if $transformation_rules}
                                            {foreach from=$transformation_rules item=rule}
                                                <option value="{$rule.id}">{$rule.name}</option>
                                            {/foreach}
                                        {/if}
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="default-value">Valor por Defecto</label>
                                    <input type="text" class="form-control" id="default-value" name="default_value" placeholder="Valor si el campo origen está vacío">
                                </div>
                            </div>
                        </div>

                        <div class="row" id="custom-transformation-row" style="display: none;">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="custom-transformation">Código PHP Personalizado</label>
                                    <textarea class="form-control" id="custom-transformation" name="custom_transformation" rows="4" placeholder="Código PHP para transformación personalizada"></textarea>
                                    <small class="help-block">Solo se usa si la regla de transformación es 'custom'</small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label">Campo Requerido</label>
                                    <div class="switch prestashop-switch fixed-width-lg">
                                        <input type="radio" name="is_required" id="is_required_on" value="1">
                                        <label for="is_required_on">Sí</label>
                                        <input type="radio" name="is_required" id="is_required_off" value="0" checked="checked">
                                        <label for="is_required_off">No</label>
                                        <a class="slide-button btn"></a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label">Activo</label>
                                    <div class="switch prestashop-switch fixed-width-lg">
                                        <input type="radio" name="is_active" id="is_active_on" value="1" checked="checked">
                                        <label for="is_active_on">Sí</label>
                                        <input type="radio" name="is_active" id="is_active_off" value="0">
                                        <label for="is_active_off">No</label>
                                        <a class="slide-button btn"></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="save-attribute-mapping">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <script type="text/javascript">
        $(document).ready(function() {
            var ajaxUrl = '{$ajax_url}';

            // Abrir modal para nuevo mapeo
            $('#add-attribute-mapping, #add-first-mapping').click(function() {
                $('#attribute-mapping-form')[0].reset();
                $('#mapping-id').val('');
                $('.modal-title').text('Nuevo Mapeo de Atributos');
                $('#attribute-mapping-modal').modal('show');
            });

            // Cargar atributos cuando se selecciona un grupo de PrestaShop
            $('#prestashop-attribute-group').change(function() {
                var groupId = $(this).val();
                var attributeSelect = $('#prestashop-attribute');
                
                attributeSelect.html('<option value="">Cargando...</option>');
                
                if (groupId) {
                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        data: {
                            ajax: true,
                            action: 'getPrestashopAttributes',
                            group_id: groupId
                        },
                        success: function(response) {
                            var data = JSON.parse(response);
                            attributeSelect.html('<option value="">Seleccionar atributo...</option>');
                            
                            if (data.success && data.data) {
                                $.each(data.data, function(index, attribute) {
                                    attributeSelect.append('<option value="' + attribute.id + '">' + attribute.name + '</option>');
                                });
                            }
                        },
                        error: function() {
                            attributeSelect.html('<option value="">Error cargando atributos</option>');
                        }
                    });
                } else {
                    attributeSelect.html('<option value="">Seleccionar atributo...</option>');
                }
            });

            // Cargar atributos cuando se selecciona un grupo de Yuju
            $('#yuju-attribute-group').change(function() {
                var groupId = $(this).val();
                var attributeSelect = $('#yuju-attribute');
                
                attributeSelect.html('<option value="">Cargando...</option>');
                
                if (groupId) {
                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        data: {
                            ajax: true,
                            action: 'getYujuAttributes',
                            group_id: groupId
                        },
                        success: function(response) {
                            var data = JSON.parse(response);
                            attributeSelect.html('<option value="">Seleccionar atributo...</option>');
                            
                            if (data.success && data.data) {
                                $.each(data.data, function(index, attribute) {
                                    attributeSelect.append('<option value="' + attribute.id + '">' + attribute.name + '</option>');
                                });
                            }
                        },
                        error: function() {
                            attributeSelect.html('<option value="">Error cargando atributos</option>');
                        }
                    });
                } else {
                    attributeSelect.html('<option value="">Seleccionar atributo...</option>');
                }
            });

            // Mostrar/ocultar campo de transformación personalizada
            $('#transformation-rule').change(function() {
                if ($(this).val() === 'custom') {
                    $('#custom-transformation-row').show();
                } else {
                    $('#custom-transformation-row').hide();
                }
            });

            // Guardar mapeo
            $('#save-attribute-mapping').click(function() {
                var formData = $('#attribute-mapping-form').serialize();
                
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: formData + '&ajax=true&action=saveMapping',
                    success: function(response) {
                        var data = JSON.parse(response);
                        
                        if (data.success) {
                            $('#attribute-mapping-modal').modal('hide');
                            location.reload();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    },
                    error: function() {
                        alert('Error de conexión');
                    }
                });
            });

            // Editar mapeo
            $('.edit-mapping').click(function() {
                var mappingId = $(this).data('mapping-id');
                
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        ajax: true,
                        action: 'getMapping',
                        id_mapping: mappingId
                    },
                    success: function(response) {
                        var data = JSON.parse(response);
                        
                        if (data.success && data.data) {
                            var mapping = data.data;
                            
                            $('#mapping-id').val(mapping.id_mapping);
                            $('#prestashop-attribute-group').val(mapping.prestashop_attribute_group_id).trigger('change');
                            $('#yuju-attribute-group').val(mapping.yuju_attribute_group_id).trigger('change');
                            $('#field-type').val(mapping.field_type);

                            $('#transformation-rule').val(mapping.transformation_rule).trigger('change');
                            $('#custom-transformation').val(mapping.custom_transformation);
                            $('#default-value').val(mapping.default_value);
                            
                            if (mapping.is_required == 1) {
                                $('#is_required_on').prop('checked', true);
                            } else {
                                $('#is_required_off').prop('checked', true);
                            }
                            
                            if (mapping.is_active == 1) {
                                $('#is_active_on').prop('checked', true);
                            } else {
                                $('#is_active_off').prop('checked', true);
                            }
                            
                            // Cargar atributos específicos después de un delay
                            setTimeout(function() {
                                $('#prestashop-attribute').val(mapping.prestashop_attribute_id);
                                $('#yuju-attribute').val(mapping.yuju_attribute_id);
                            }, 500);
                            
                            $('.modal-title').text('Editar Mapeo de Atributos');
                            $('#attribute-mapping-modal').modal('show');
                        } else {
                            alert('Error: ' + data.message);
                        }
                    },
                    error: function() {
                        alert('Error de conexión');
                    }
                });
            });

            // Eliminar mapeo
            $('.delete-mapping').click(function() {
                var mappingId = $(this).data('mapping-id');
                
                if (confirm('¿Está seguro de que desea eliminar este mapeo?')) {
                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        data: {
                            ajax: true,
                            action: 'deleteMapping',
                            id_mapping: mappingId
                        },
                        success: function(response) {
                            var data = JSON.parse(response);
                            
                            if (data.success) {
                                location.reload();
                            } else {
                                alert('Error: ' + data.message);
                            }
                        },
                        error: function() {
                            alert('Error de conexión');
                        }
                    });
                }
            });

            // Toggle activo/inactivo
            $('.toggle-mapping').change(function() {
                var mappingId = $(this).data('mapping-id');
                var isActive = $(this).val();
                
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        ajax: true,
                        action: 'toggleMapping',
                        id_mapping: mappingId,
                        is_active: isActive
                    },
                    success: function(response) {
                        var data = JSON.parse(response);
                        
                        if (!data.success) {
                            alert('Error: ' + data.message);
                            location.reload();
                        }
                    },
                    error: function() {
                        alert('Error de conexión');
                        location.reload();
                    }
                });
            });

            // Actualizar atributos Yuju
            $('#refresh-yuju-attributes').click(function() {
                var btn = $(this);
                btn.prop('disabled', true).html('<i class="icon-refresh icon-spin"></i> Actualizando...');
                
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        ajax: true,
                        action: 'refreshYujuAttributes'
                    },
                    success: function(response) {
                        var data = JSON.parse(response);
                        
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    },
                    error: function() {
                        alert('Error de conexión');
                    },
                    complete: function() {
                        btn.prop('disabled', false).html('<i class="icon-refresh"></i> Actualizar Atributos Yuju');
                    }
                });
            });
        });
    </script>
{/block}