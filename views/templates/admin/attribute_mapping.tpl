{*
* 2024 Yuju Integration
*
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
                <button type="button" class="btn btn-primary" id="add-attribute-mapping">
                    <i class="icon-plus"></i> Nuevo mapeo
                </button>
            </span>
        </div>
        <div class="panel-body">
            {if isset($yuju_attributes_count) && $yuju_attributes_count == 0}
                <div class="alert alert-warning yuju-attr-cache-empty">
                    <strong>Sin datos de atributos Yuju en caché.</strong>
                    Comprueba que exista el archivo <code>config/yuju_allowed_values.json</code> en el módulo (valores según la <a href="https://api-docs.yuju.io/docs/colores-y-categorias" target="_blank" rel="noopener">documentación de Yuju</a>).
                </div>
            {/if}
            <div class="table-responsive">
                <table class="table table-striped" id="attribute-mappings-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>PrestaShop</th>
                            <th>Yuju</th>
                            <th>Clasificación</th>
                            <th>Activo</th>
                            <th>Creado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        {if $attribute_mappings && count($attribute_mappings) > 0}
                            {foreach from=$attribute_mappings item=mapping}
                                <tr data-mapping-id="{$mapping.id_mapping|intval}">
                                    <td>{$mapping.id_mapping|intval}</td>
                                    <td>
                                        <strong>{$mapping.prestashop_attribute_name|escape:'html':'UTF-8'}</strong>
                                        {if $mapping.prestashop_attribute_group_name}
                                            <br><span class="text-muted small">Grupo: {$mapping.prestashop_attribute_group_name|escape:'html':'UTF-8'}</span>
                                        {/if}
                                    </td>
                                    <td>
                                        <strong>{$mapping.yuju_attribute_name|escape:'html':'UTF-8'}</strong>
                                    </td>
                                    <td>
                                        <span class="label label-default">
                                            {if $mapping.attribute_type == 'color'}Color
                                            {elseif $mapping.attribute_type == 'size'}Talla
                                            {elseif $mapping.attribute_type == 'material'}Material
                                            {else}Genérico{/if}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="form-group" style="margin:0;">
                                            <div class="switch prestashop-switch fixed-width-lg">
                                                <input type="radio" name="mapping_active_{$mapping.id_mapping|intval}" id="mapping_active_{$mapping.id_mapping|intval}_on" value="1" {if $mapping.is_active}checked="checked"{/if} class="toggle-mapping" data-mapping-id="{$mapping.id_mapping|intval}">
                                                <label for="mapping_active_{$mapping.id_mapping|intval}_on">Sí</label>
                                                <input type="radio" name="mapping_active_{$mapping.id_mapping|intval}" id="mapping_active_{$mapping.id_mapping|intval}_off" value="0" {if !$mapping.is_active}checked="checked"{/if} class="toggle-mapping" data-mapping-id="{$mapping.id_mapping|intval}">
                                                <label for="mapping_active_{$mapping.id_mapping|intval}_off">No</label>
                                                <a class="slide-button btn"></a>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{$mapping.created_at|escape:'html':'UTF-8'}</td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-default btn-sm edit-mapping" data-mapping-id="{$mapping.id_mapping|intval}" title="Editar">
                                                <i class="icon-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm delete-mapping" data-mapping-id="{$mapping.id_mapping|intval}" title="Eliminar">
                                                <i class="icon-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            {/foreach}
                        {else}
                            <tr>
                                <td colspan="7" class="text-center text-muted">
                                    <p>No hay mapeos configurados.</p>
                                    <button type="button" class="btn btn-primary" id="add-first-mapping">
                                        <i class="icon-plus"></i> Crear primer mapeo
                                    </button>
                                </td>
                            </tr>
                        {/if}
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="attribute-mapping-modal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content yuju-attr-modal">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Mapeo de atributos</h4>
                </div>
                <div class="modal-body">
                    <form id="attribute-mapping-form">
                        <input type="hidden" id="mapping-id" name="id_mapping" value="">

                        <div class="yuju-attr-mapper">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="yuju-attr-card">
                                        <div class="yuju-attr-card-head">
                                            <i class="icon-archive"></i> PrestaShop <small class="text-muted">(valor del atributo)</small>
                                        </div>
                                        <div class="form-group">
                                            <label for="prestashop-attribute-group">Grupo *</label>
                                            <select class="form-control" id="prestashop-attribute-group" required>
                                                <option value="">Seleccionar grupo…</option>
                                                {if $prestashop_attribute_groups}
                                                    {foreach from=$prestashop_attribute_groups item=group}
                                                        <option value="{$group.id|intval}">{$group.name|escape:'html':'UTF-8'}</option>
                                                    {/foreach}
                                                {/if}
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="prestashop-attribute">Atributo (valor) *</label>
                                            <select class="form-control" id="prestashop-attribute" name="prestashop_attribute_id" required>
                                                <option value="">Primero elija un grupo…</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="yuju-attr-card yuju-attr-card-yuju">
                                        <div class="yuju-attr-card-head">
                                            <i class="icon-cloud"></i> Yuju
                                        </div>
                                        <div class="form-group">
                                            <label for="yuju-attribute-group">Tipo de valor en Yuju</label>
                                            <select class="form-control" id="yuju-attribute-group">
                                                {if $yuju_attribute_groups}
                                                    {foreach from=$yuju_attribute_groups item=group}
                                                        <option value="{$group.id|escape:'html':'UTF-8'}">{$group.name|escape:'html':'UTF-8'}</option>
                                                    {/foreach}
                                                {/if}
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="yuju-attribute">Valor Yuju *</label>
                                            <select class="form-control" id="yuju-attribute" name="yuju_attribute_id" required>
                                                <option value="">Seleccionar valor Yuju…</option>
                                                {if isset($yuju_attributes_for_modal) && $yuju_attributes_for_modal}
                                                    {foreach from=$yuju_attributes_for_modal item=ya}
                                                        <option value="{$ya.id|escape:'html':'UTF-8'}" data-yuju-type="{$ya.type|escape:'html':'UTF-8'}">{$ya.name|escape:'html':'UTF-8'} ({$ya.id|escape:'html':'UTF-8'})</option>
                                                    {/foreach}
                                                {/if}
                                            </select>
                                            <p class="help-block small yuju-attr-hint text-muted" style="margin-top:6px;">
                                                Solo se listan valores del <strong>tipo de valor en Yuju</strong> elegido arriba (según <code>config/yuju_allowed_values.json</code>).
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="save-attribute-mapping">Guardar mapeo</button>
                </div>
            </div>
        </div>
    </div>

    <script type="text/javascript">
        var ajaxUrl = '{$ajax_url|escape:'javascript':'UTF-8'}';
        var adminToken = '{$token|default:''|escape:'javascript':'UTF-8'}';
        {literal}
        $(document).ready(function() {

            function normalizeResponse(response) {
                if (typeof response === 'object' && response !== null) {
                    return response;
                }
                if (typeof response === 'string') {
                    try {
                        return JSON.parse(response);
                    } catch (e) {
                        return { success: false, message: 'Respuesta no válida del servidor.', raw: response };
                    }
                }
                return { success: false, message: 'Respuesta vacía del servidor.' };
            }

            var yujuAttrSelectBackup = null;

            function captureYujuAttributeSelectBackup() {
                yujuAttrSelectBackup = $('#yuju-attribute').html();
            }

            function restoreYujuAttributeSelectFromBackup() {
                if (yujuAttrSelectBackup) {
                    $('#yuju-attribute').html(yujuAttrSelectBackup);
                }
            }

            function yujuOptionValueExists($sel, val) {
                if (val === null || val === undefined || val === '') {
                    return false;
                }
                var found = false;
                $sel.find('option').each(function() {
                    if ($(this).val() === String(val)) {
                        found = true;
                        return false;
                    }
                });
                return found;
            }

            function applyYujuAttributeFilter(preserveValue) {
                var groupId = $('#yuju-attribute-group').val();
                if (groupId === null || groupId === undefined) {
                    groupId = '';
                }
                groupId = String(groupId);
                var prev = preserveValue !== undefined ? preserveValue : $('#yuju-attribute').val();
                restoreYujuAttributeSelectFromBackup();
                var $sel = $('#yuju-attribute');
                if (!groupId) {
                    $sel.find('option').not(':first').remove();
                    $sel.val('');
                    $('.yuju-attr-hint').text('Seleccione un tipo de valor en Yuju para listar opciones.');
                    return;
                }
                $sel.find('option').not(':first').each(function() {
                    var $o = $(this);
                    var t = ($o.attr('data-yuju-type') || '').toString();
                    var show = (t === groupId);
                    if (!show) {
                        $o.remove();
                    }
                });
                var n = $sel.find('option').length - 1;
                if (prev && yujuOptionValueExists($sel, prev)) {
                    $sel.val(String(prev));
                } else {
                    $sel.val('');
                }
                $('.yuju-attr-hint').text(n ? (n + ' opción(es) para el tipo seleccionado.') : 'Ningún valor para este tipo.');
            }

            captureYujuAttributeSelectBackup();

            function loadPrestashopAttributes(groupId) {
                var attributeSelect = $('#prestashop-attribute');
                attributeSelect.html('<option value="">Cargando…</option>');
                if (!groupId) {
                    attributeSelect.html('<option value="">Seleccionar grupo…</option>');
                    return $.Deferred().resolve().promise();
                }
                return $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        ajax: true,
                        action: 'getPrestashopAttributes',
                        group_id: groupId,
                        token: adminToken
                    }
                }).done(function(response) {
                    var data = normalizeResponse(response);
                    attributeSelect.empty().append('<option value="">Seleccionar valor…</option>');
                    if (data.success && data.data && data.data.length) {
                        $.each(data.data, function(i, a) {
                            attributeSelect.append('<option value="' + a.id + '">' + $('<div>').text(a.name).html() + '</option>');
                        });
                    } else {
                        attributeSelect.append('<option value="" disabled>Sin valores en este grupo</option>');
                    }
                }).fail(function(xhr) {
                    console.error(xhr.responseText);
                    attributeSelect.html('<option value="">Error al cargar</option>');
                });
            }

            function loadYujuAttributes(groupId) {
                if ($('#yuju-attribute option').length > 1) {
                    applyYujuAttributeFilter();
                    return $.Deferred().resolve().promise();
                }
                var gid = (groupId !== undefined && groupId !== null && groupId !== '') ? groupId : ($('#yuju-attribute-group').val() || '');
                var attributeSelect = $('#yuju-attribute');
                attributeSelect.html('<option value="">Cargando…</option>');
                $('.yuju-attr-hint').text('');
                return $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        ajax: true,
                        action: 'getYujuAttributes',
                        group_id: gid,
                        q: '',
                        token: adminToken
                    }
                }).done(function(response) {
                    var data = normalizeResponse(response);
                    restoreYujuAttributeSelectFromBackup();
                    attributeSelect = $('#yuju-attribute');
                    if (attributeSelect.find('option').length > 1) {
                        applyYujuAttributeFilter();
                        return;
                    }
                    attributeSelect.empty().append('<option value="">Seleccionar valor Yuju…</option>');
                    if (data.success && data.data && data.data.length) {
                        $.each(data.data, function(i, attr) {
                            var id = attr.id;
                            var label = (attr.name || id) + (id !== undefined && id !== '' ? ' (' + id + ')' : '');
                            var typeAttr = attr.type ? ' data-yuju-type="' + $('<div>').text(attr.type).html() + '"' : '';
                            attributeSelect.append('<option value="' + $('<div>').text(id).html() + '"' + typeAttr + '>' + $('<div>').text(label).html() + '</option>');
                        });
                    } else {
                        attributeSelect.append('<option value="" disabled>No hay valores para este tipo</option>');
                        $('.yuju-attr-hint').text('Elija otro tipo de valor en Yuju o revise config/yuju_allowed_values.json.');
                    }
                    applyYujuAttributeFilter();
                }).fail(function(xhr) {
                    console.error(xhr.responseText);
                    restoreYujuAttributeSelectFromBackup();
                    applyYujuAttributeFilter();
                });
            }

            function resetAttributeMappingModal() {
                $('#mapping-id').val('');
                $('#prestashop-attribute-group').val('');
                $('#prestashop-attribute').html('<option value="">Primero elija un grupo…</option>');
                var $yGrpReset = $('#yuju-attribute-group');
                if ($yGrpReset.find('option').length) {
                    $yGrpReset.prop('selectedIndex', 0);
                }
                restoreYujuAttributeSelectFromBackup();
                applyYujuAttributeFilter();
                $('.modal-title').text('Nuevo mapeo de atributos');
            }

            function openNewMappingModal() {
                resetAttributeMappingModal();
                $('#attribute-mapping-modal').modal('show');
            }

            $('#add-attribute-mapping, #add-first-mapping').on('click', function() {
                openNewMappingModal();
            });

            $('#prestashop-attribute-group').on('change', function() {
                loadPrestashopAttributes($(this).val());
            });

            $('#yuju-attribute-group').on('change', function() {
                applyYujuAttributeFilter();
            });

            $('#attribute-mapping-modal').on('shown.bs.modal', function() {
                if (!$('#mapping-id').val()) {
                    restoreYujuAttributeSelectFromBackup();
                    applyYujuAttributeFilter();
                }
            });

            $('#save-attribute-mapping').on('click', function() {
                var formData = $('#attribute-mapping-form').serialize();
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: formData + '&ajax=true&action=saveMapping&token=' + encodeURIComponent(adminToken),
                    success: function(response) {
                        var data = normalizeResponse(response);
                        if (data.success) {
                            $('#attribute-mapping-modal').modal('hide');
                            location.reload();
                        } else {
                            alert(data.message || 'No se pudo guardar.');
                        }
                    },
                    error: function(xhr) {
                        console.error(xhr.responseText);
                        alert('Error de conexión al guardar.');
                    }
                });
            });

            $('.edit-mapping').on('click', function() {
                var mappingId = $(this).data('mapping-id');
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        ajax: true,
                        action: 'getMapping',
                        id_mapping: mappingId,
                        token: adminToken
                    },
                    success: function(response) {
                        var data = normalizeResponse(response);
                        if (!data.success || !data.data) {
                            alert(data.message || 'No se pudo cargar el mapeo.');
                            return;
                        }
                        var m = data.data;
                        var $yGrpEdit = $('#yuju-attribute-group');
                        $('#mapping-id').val(m.id_mapping);
                        $('#prestashop-attribute-group').val(m.prestashop_attribute_group_id);
                        var ygrp = String(m.yuju_attribute_group_id || '').trim();
                        if (ygrp === 'all') {
                            ygrp = '';
                        }
                        if (ygrp && $yGrpEdit.find('option').filter(function() { return $(this).val() === ygrp; }).length) {
                            $yGrpEdit.val(ygrp);
                        } else if ($yGrpEdit.find('option').length) {
                            $yGrpEdit.prop('selectedIndex', 0);
                        }
                        $('.modal-title').text('Editar mapeo de atributos');
                        $('#attribute-mapping-modal').modal('show');
                        $.when(
                            loadPrestashopAttributes(m.prestashop_attribute_group_id)
                        ).always(function() {
                            $('#prestashop-attribute').val(String(m.prestashop_attribute_id));
                            restoreYujuAttributeSelectFromBackup();
                            var yType = String($yGrpEdit.val() || '');
                            var yid = String(m.yuju_attribute_id);
                            if (yid && !yujuOptionValueExists($('#yuju-attribute'), yid)) {
                                var yLabel = yid;
                                $('#yuju-attribute').append(
                                    '<option value="' + $('<div>').text(yid).html() + '" data-yuju-type="' + $('<div>').text(yType).html() + '">' +
                                    $('<div>').text(yLabel).html() + '</option>'
                                );
                            }
                            captureYujuAttributeSelectBackup();
                            applyYujuAttributeFilter(yid);
                        });
                    },
                    error: function(xhr) {
                        console.error(xhr.responseText);
                        alert('Error de conexión.');
                    }
                });
            });

            $('.delete-mapping').on('click', function() {
                var mappingId = $(this).data('mapping-id');
                if (!confirm('¿Eliminar este mapeo?')) {
                    return;
                }
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        ajax: true,
                        action: 'deleteMapping',
                        id_mapping: mappingId,
                        token: adminToken
                    },
                    success: function(response) {
                        var data = normalizeResponse(response);
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.message || 'Error al eliminar.');
                        }
                    },
                    error: function() {
                        alert('Error de conexión.');
                    }
                });
            });

            $(document).on('change', '.toggle-mapping', function() {
                var mappingId = $(this).data('mapping-id');
                var isActive = $(this).val();
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        ajax: true,
                        action: 'toggleMapping',
                        id_mapping: mappingId,
                        status: isActive,
                        token: adminToken
                    },
                    success: function(response) {
                        var data = normalizeResponse(response);
                        if (!data.success) {
                            alert(data.message || 'Error');
                            location.reload();
                        }
                    },
                    error: function() {
                        alert('Error de conexión.');
                        location.reload();
                    }
                });
            });

        });
        {/literal}
    </script>
{/block}
