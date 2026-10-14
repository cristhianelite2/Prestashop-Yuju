{*
* 2024 Yuju Integration
*}

{extends file="./layout.tpl"}

{block name="content"}
<div class="panel" id="order-field-mapping-panel">
    <div class="panel-heading"><i class="icon-random"></i> Mapeo general de campos de órdenes</div>
    <div class="panel-body">
        <p class="help-block">
            Personaliza cualquier ruta del pedido antes de crear cliente, dirección, carrito y orden. Escribe una ruta destino como
            <code>customer.first_name</code>, <code>shipping_address.address_line_1</code>, <code>items.0.quantity</code> o <code>currency</code>.
            En la plantilla puedes combinar texto fijo con comodines, por ejemplo <code>Nombre: %customer.last_name%</code>.
            Los comodines se buscan en el payload original de Yuju y, si no existen allí, en los datos adaptados. Tipos numéricos inválidos se convierten al valor predeterminado (0 por defecto); texto vacío usa <code>not found</code> por defecto.
        </p>
        <form id="field-mapping-form" class="well">
            <input type="hidden" id="field-mapping-id" value="0">
            <div class="row">
                <div class="col-lg-3 col-md-4 form-group">
                    <label for="field-mapping-target">Campo destino</label>
                    <input class="form-control" id="field-mapping-target" placeholder="customer.first_name" required>
                </div>
                <div class="col-lg-4 col-md-5 form-group">
                    <label for="field-mapping-template">Plantilla / valor</label>
                    <input class="form-control" id="field-mapping-template" placeholder="Nombre: %customer.last_name%" required>
                </div>
                <div class="col-lg-2 col-md-3 form-group">
                    <label for="field-mapping-type">Tipo</label>
                    <select class="form-control" id="field-mapping-type">
                        <option value="string">Texto</option>
                        <option value="integer">Entero</option>
                        <option value="decimal">Decimal</option>
                        <option value="boolean">Booleano (0/1)</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3 form-group">
                    <label for="field-mapping-default">Valor por defecto</label>
                    <input class="form-control" id="field-mapping-default" placeholder="not found / 0">
                </div>
                <div class="col-lg-1 col-md-2 form-group">
                    <label>&nbsp;</label>
                    <button class="btn btn-primary btn-block" type="submit" id="save-field-mapping"><i class="icon-save"></i></button>
                </div>
            </div>
            <p class="help-block small">Para combinar valores: <code>Nombre: %customer.last_name%</code>. Ejemplos de rutas de origen: <code>customer.first_name</code>, <code>shipping_address.city</code>, <code>items.0.price</code>, <code>id</code>. Los campos destino usan puntos para separar niveles.</p>
            <button type="button" class="btn btn-default btn-sm" id="cancel-field-mapping-edit" style="display:none;">Cancelar edición</button>
            <div id="field-mapping-alert" class="alert" style="display:none;margin-top:10px;"></div>
        </form>
        <div class="table-responsive">
            <table class="table table-striped" id="field-mappings-table">
                <thead><tr><th>Destino</th><th>Plantilla</th><th>Tipo</th><th>Valor por defecto</th><th>Activo</th><th>Acciones</th></tr></thead>
                <tbody>
                    {if $field_mappings && count($field_mappings) > 0}
                        {foreach from=$field_mappings item=fm}
                            <tr>
                                <td><code>{$fm.target_path|escape:'html':'UTF-8'}</code></td>
                                <td><code>{$fm.template|escape:'html':'UTF-8'}</code></td>
                                <td>{$fm.field_type|escape:'html':'UTF-8'}</td>
                                <td>{$fm.default_value|escape:'html':'UTF-8'}</td>
                                <td>
                                    <input type="checkbox" class="toggle-field-mapping" data-id="{$fm.id|intval}" {if $fm.is_active}checked="checked"{/if}>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-default btn-xs edit-field-mapping"
                                        data-id="{$fm.id|intval}"
                                        data-target="{$fm.target_path|escape:'html':'UTF-8'}"
                                        data-template="{$fm.template|escape:'html':'UTF-8'}"
                                        data-type="{$fm.field_type|escape:'html':'UTF-8'}"
                                        data-default="{$fm.default_value|escape:'html':'UTF-8'}"><i class="icon-edit"></i> Editar</button>
                                    <button type="button" class="btn btn-danger btn-xs delete-field-mapping" data-id="{$fm.id|intval}"><i class="icon-trash"></i></button>
                                </td>
                            </tr>
                        {/foreach}
                    {else}
                        <tr class="field-mappings-empty"><td colspan="6" class="text-center text-muted">Aún no hay reglas generales configuradas.</td></tr>
                    {/if}
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-heading">
        <i class="icon-exchange"></i>
        Mapeo de estados de pedido
        <span class="panel-heading-action">
            <button type="button" class="btn btn-primary" id="add-status-mapping">
                <i class="icon-plus"></i> Nuevo mapeo
            </button>
        </span>
    </div>
    <div class="panel-body">
        <p class="help-block">
            Define cómo se traducen los estados de Yuju a los de PrestaShop al sincronizar órdenes.
            Estados oficiales
            (<a href="https://api-docs.yuju.io/docs/estados-de-un-pedido" target="_blank" rel="noopener">documentación Yuju</a>):
            {foreach from=$yuju_documented_statuses key=status_key item=status_label name=docst}
                <code>{$status_key|escape:'html':'UTF-8'}</code>{if !$smarty.foreach.docst.last}, {/if}
            {/foreach}.
            Al abrir esta pantalla se completan automáticamente los mapeos faltantes con valores por defecto (sin sobrescribir los que ya configuraste).
        </p>

        <div class="table-responsive">
            <table class="table table-striped" id="status-mappings-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Estado Yuju</th>
                        <th>Estado PrestaShop</th>
                        <th>Activo</th>
                        <th>Actualizado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    {if $status_mappings && count($status_mappings) > 0}
                        {foreach from=$status_mappings item=mapping}
                            <tr data-mapping-id="{$mapping.id|intval}">
                                <td>{$mapping.id|intval}</td>
                                <td>
                                    <code>{$mapping.yuju_status_name|escape:'html':'UTF-8'}</code>
                                    {if isset($yuju_order_statuses[$mapping.yuju_status_name])}
                                        <br><span class="text-muted small">{$yuju_order_statuses[$mapping.yuju_status_name]|escape:'html':'UTF-8'}</span>
                                    {/if}
                                </td>
                                <td>
                                    {if $mapping.prestashop_status_color}
                                        <span class="label" style="background-color:{$mapping.prestashop_status_color|escape:'html':'UTF-8'};color:#fff;">
                                            {if $mapping.prestashop_status_name}{$mapping.prestashop_status_name|escape:'html':'UTF-8'}{else}Estado #{$mapping.prestashop_status_id|intval}{/if}
                                        </span>
                                    {else}
                                        <strong>{if $mapping.prestashop_status_name}{$mapping.prestashop_status_name|escape:'html':'UTF-8'}{else}Estado #{$mapping.prestashop_status_id|intval}{/if}</strong>
                                    {/if}
                                    <br><span class="text-muted small">ID {$mapping.prestashop_status_id|intval}</span>
                                </td>
                                <td>
                                    <div class="form-group" style="margin:0;">
                                        <div class="switch prestashop-switch fixed-width-lg">
                                            <input type="radio" name="mapping_active_{$mapping.id|intval}" id="mapping_active_{$mapping.id|intval}_on" value="1" {if $mapping.is_active}checked="checked"{/if} class="toggle-status-mapping" data-mapping-id="{$mapping.id|intval}">
                                            <label for="mapping_active_{$mapping.id|intval}_on">Sí</label>
                                            <input type="radio" name="mapping_active_{$mapping.id|intval}" id="mapping_active_{$mapping.id|intval}_off" value="0" {if !$mapping.is_active}checked="checked"{/if} class="toggle-status-mapping" data-mapping-id="{$mapping.id|intval}">
                                            <label for="mapping_active_{$mapping.id|intval}_off">No</label>
                                            <a class="slide-button btn"></a>
                                        </div>
                                    </div>
                                </td>
                                <td>{$mapping.updated_at|escape:'html':'UTF-8'}</td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-default btn-sm edit-status-mapping"
                                            data-mapping-id="{$mapping.id|intval}"
                                            data-yuju-status="{$mapping.yuju_status_name|escape:'html':'UTF-8'}"
                                            data-ps-status="{$mapping.prestashop_status_id|intval}"
                                            data-is-active="{$mapping.is_active|intval}"
                                            title="Editar">
                                            <i class="icon-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm delete-status-mapping" data-mapping-id="{$mapping.id|intval}" title="Eliminar">
                                            <i class="icon-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        {/foreach}
                    {else}
                        <tr>
                            <td colspan="6" class="text-center text-muted">No hay mapeos configurados.</td>
                        </tr>
                    {/if}
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="status-mapping-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title" id="status-mapping-modal-title">Nuevo mapeo de estado</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="status-mapping-id" value="0">
                <div class="form-group">
                    <label for="yuju-status-name">Estado Yuju</label>
                    <select id="yuju-status-name" class="form-control">
                        <option value="">Seleccionar…</option>
                        <optgroup label="Estados oficiales Yuju">
                            {foreach from=$yuju_documented_statuses key=status_key item=status_label}
                                <option value="{$status_key|escape:'html':'UTF-8'}">{$status_label|escape:'html':'UTF-8'} ({$status_key|escape:'html':'UTF-8'})</option>
                            {/foreach}
                        </optgroup>
                        <optgroup label="Aliases / otros">
                            {foreach from=$yuju_order_statuses key=status_key item=status_label}
                                {if !isset($yuju_documented_statuses[$status_key])}
                                    <option value="{$status_key|escape:'html':'UTF-8'}">{$status_label|escape:'html':'UTF-8'} ({$status_key|escape:'html':'UTF-8'})</option>
                                {/if}
                            {/foreach}
                        </optgroup>
                    </select>
                </div>
                <div class="form-group">
                    <label for="prestashop-status-id">Estado PrestaShop</label>
                    <select id="prestashop-status-id" class="form-control">
                        <option value="">Seleccionar…</option>
                        {foreach from=$prestashop_order_states item=ps_state}
                            <option value="{$ps_state.id|intval}">{$ps_state.name|escape:'html':'UTF-8'} (ID {$ps_state.id|intval})</option>
                        {/foreach}
                    </select>
                </div>
                <div class="form-group">
                    <label>Activo</label>
                    <div class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="status_mapping_active" id="status_mapping_active_on" value="1" checked="checked">
                        <label for="status_mapping_active_on">Sí</label>
                        <input type="radio" name="status_mapping_active" id="status_mapping_active_off" value="0">
                        <label for="status_mapping_active_off">No</label>
                        <a class="slide-button btn"></a>
                    </div>
                </div>
                <div id="status-mapping-alert" class="alert" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="save-status-mapping">
                    <i class="icon-save"></i> Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
(function() {
    var ajaxUrl = '{$ajax_url|escape:'javascript':'UTF-8'}';
    var adminToken = '{$token|escape:'javascript':'UTF-8'}';

    function showAlert(msg, ok) {
        var $a = $('#status-mapping-alert');
        $a.removeClass('alert-success alert-danger')
            .addClass(ok ? 'alert-success' : 'alert-danger')
            .text(msg)
            .show();
    }

    function resetModal() {
        $('#status-mapping-id').val('0');
        $('#yuju-status-name').val('');
        $('#prestashop-status-id').val('');
        $('#status_mapping_active_on').prop('checked', true);
        $('#status-mapping-alert').hide().text('');
        $('#status-mapping-modal-title').text('Nuevo mapeo de estado');
    }

    $('#add-status-mapping').on('click', function() {
        resetModal();
        $('#status-mapping-modal').modal('show');
    });

    $(document).on('click', '.edit-status-mapping', function() {
        resetModal();
        var $btn = $(this);
        $('#status-mapping-id').val($btn.data('mapping-id'));
        $('#yuju-status-name').val($btn.data('yuju-status'));
        $('#prestashop-status-id').val(String($btn.data('ps-status')));
        if (parseInt($btn.data('is-active'), 10)) {
            $('#status_mapping_active_on').prop('checked', true);
        } else {
            $('#status_mapping_active_off').prop('checked', true);
        }
        $('#status-mapping-modal-title').text('Editar mapeo de estado');
        $('#status-mapping-modal').modal('show');
    });

    $('#save-status-mapping').on('click', function() {
        var payload = {
            ajax: true,
            action: 'saveMapping',
            token: adminToken,
            id_mapping: $('#status-mapping-id').val(),
            yuju_status_name: $('#yuju-status-name').val(),
            prestashop_status_id: $('#prestashop-status-id').val(),
            is_active: $('input[name="status_mapping_active"]:checked').val()
        };
        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: payload
        }).done(function(res) {
            if (res && res.success) {
                window.location.reload();
            } else {
                showAlert((res && res.message) ? res.message : 'Error al guardar', false);
            }
        }).fail(function() {
            showAlert('Error de comunicación con el servidor', false);
        });
    });

    $(document).on('change', '.toggle-status-mapping', function() {
        var id = $(this).data('mapping-id');
        var isActive = $(this).val();
        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                ajax: true,
                action: 'toggleMapping',
                token: adminToken,
                id_mapping: id,
                is_active: isActive
            }
        });
    });

    $(document).on('click', '.delete-status-mapping', function() {
        if (!confirm('¿Eliminar este mapeo de estado?')) {
            return;
        }
        var id = $(this).data('mapping-id');
        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                ajax: true,
                action: 'deleteMapping',
                token: adminToken,
                id_mapping: id
            }
        }).done(function(res) {
            if (res && res.success) {
                window.location.reload();
            } else {
                alert((res && res.message) ? res.message : 'No se pudo eliminar');
            }
        });
    });

    function showFieldAlert(msg, ok) {
        $('#field-mapping-alert').removeClass('alert-success alert-danger')
            .addClass(ok ? 'alert-success' : 'alert-danger').text(msg).show();
    }
    function resetFieldForm() {
        $('#field-mapping-id').val('0');
        $('#field-mapping-target').val('');
        $('#field-mapping-template').val('');
        $('#field-mapping-type').val('string');
        $('#field-mapping-default').val('');
        $('#save-field-mapping').html('<i class="icon-save"></i>');
        $('#cancel-field-mapping-edit').hide();
    }
    $('#cancel-field-mapping-edit').on('click', resetFieldForm);
    $(document).on('click', '.edit-field-mapping', function() {
        var b = $(this);
        $('#field-mapping-id').val(b.data('id'));
        $('#field-mapping-target').val(b.attr('data-target'));
        $('#field-mapping-template').val(b.attr('data-template'));
        $('#field-mapping-type').val(b.attr('data-type'));
        $('#field-mapping-default').val(b.attr('data-default'));
        $('#save-field-mapping').html('<i class="icon-save"></i> Actualizar');
        $('#cancel-field-mapping-edit').show();
        $('#field-mapping-panel').get(0).scrollIntoView({behavior:'smooth', block:'start'});
    });
    $('#field-mapping-form').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: ajaxUrl, method: 'POST', dataType: 'json',
            data: {
                ajax: true, action: 'saveFieldMapping', token: adminToken,
                id_field_mapping: $('#field-mapping-id').val(),
                target_path: $('#field-mapping-target').val(),
                template: $('#field-mapping-template').val(),
                field_type: $('#field-mapping-type').val(),
                default_value: $('#field-mapping-default').val(),
                is_active: 1
            }
        }).done(function(res) {
            if (res && res.success) { window.location.reload(); }
            else { showFieldAlert((res && res.message) || 'No se pudo guardar el mapeo.', false); }
        }).fail(function() { showFieldAlert('Error de comunicación al guardar el mapeo.', false); });
    });
    $(document).on('change', '.toggle-field-mapping', function() {
        $.ajax({
            url: ajaxUrl, method: 'POST', dataType: 'json',
            data: {ajax:true, action:'toggleFieldMapping', token:adminToken,
                id_field_mapping:$(this).data('id'), is_active:$(this).is(':checked') ? 1 : 0}
        }).fail(function() { alert('No se pudo cambiar el estado del mapeo.'); });
    });
    $(document).on('click', '.delete-field-mapping', function() {
        if (!confirm('¿Eliminar esta regla de mapeo de campos?')) return;
        $.ajax({
            url: ajaxUrl, method: 'POST', dataType: 'json',
            data: {ajax:true, action:'deleteFieldMapping', token:adminToken, id_field_mapping:$(this).data('id')}
        }).done(function(res) {
            if (res && res.success) window.location.reload();
            else alert((res && res.message) || 'No se pudo eliminar el mapeo.');
        }).fail(function() { alert('Error de comunicación al eliminar el mapeo.'); });
    });

})();
</script>
{/block}
