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
        Mapeo de Categorías
    </div>
    <div class="panel-body">
        <div class="alert alert-info">
            <p><strong>Información:</strong> Mapee las categorías de PrestaShop con las categorías de Yuju para una sincronización correcta.</p>
            <p><strong>Nota:</strong> Las categorías padre deben estar sincronizadas antes que las categorías hijas.</p>
        </div>
        
        {if isset($sync_blocked) && $sync_blocked}
            <div class="alert alert-warning">
                <p><strong>¡Atención!</strong> La sincronización está bloqueada. Algunas categorías padre no están sincronizadas.</p>
            </div>
        {/if}
        
        <div class="row">
            <div class="col-lg-12">
                <button type="button" class="btn btn-primary" id="btn-open-mapping-modal">
                    <i class="icon-plus"></i> Nuevo Mapeo de Categoría
                </button>
                <button type="button" class="btn btn-success" id="btn-sync-categories">
                    <i class="icon-refresh"></i> Sincronizar Categorías
                </button>
                <button type="button" class="btn btn-warning" id="btn-refresh-yuju-categories">
                    <i class="icon-download"></i> Actualizar Categorías de Yuju
                </button>
            </div>
        </div>
        
        <br>
        
        <div class="table-responsive">
            <table class="table table-striped" id="category-mapping-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Categoría PrestaShop</th>
                        <th>Categoría Padre</th>
                        <th>Categoría Yuju</th>
                        <th>Estado de Sincronización</th>
                        <th>Última Sincronización</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    {if isset($category_mappings) && $category_mappings}
                        {foreach from=$category_mappings item=mapping}
                            <tr data-mapping-id="{$mapping.id}">
                                <td>{$mapping.id}</td>
                                <td>
                                    <strong>{$mapping.prestashop_category_name|escape:'html':'UTF-8'}</strong>
                                    <br><small>ID: {$mapping.prestashop_category_id}</small>
                                </td>
                                <td>
                                    {if $mapping.parent_category_name}
                                        {$mapping.parent_category_name|escape:'html':'UTF-8'}
                                        {if !$mapping.parent_synced}
                                            <span class="label label-warning">Padre no sincronizado</span>
                                        {/if}
                                    {else}
                                        <span class="text-muted">Categoría raíz</span>
                                    {/if}
                                </td>
                                <td>
                                    {if $mapping.yuju_category_name}
                                        <strong>{$mapping.yuju_category_name|escape:'html':'UTF-8'}</strong>
                                        <br><small>ID: {$mapping.yuju_category_id}</small>
                                    {else}
                                        <span class="text-muted">No mapeada</span>
                                    {/if}
                                </td>
                                <td>
                                    {if $mapping.sync_status == 'synced'}
                                        <span class="label label-success">Sincronizada</span>
                                    {elseif $mapping.sync_status == 'pending'}
                                        <span class="label label-warning">Pendiente</span>
                                    {elseif $mapping.sync_status == 'error'}
                                        <span class="label label-danger">Error</span>
                                    {else}
                                        <span class="label label-default">No sincronizada</span>
                                    {/if}
                                </td>
                                <td>
                                    {if $mapping.last_sync_date}
                                        {$mapping.last_sync_date|date_format:'%d/%m/%Y %H:%M'}
                                    {else}
                                        <span class="text-muted">Nunca</span>
                                    {/if}
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-default btn-sm btn-edit-mapping" data-mapping-id="{$mapping.id}">
                                            <i class="icon-edit"></i> Editar
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm btn-delete-mapping" data-mapping-id="{$mapping.id}">
                                            <i class="icon-trash"></i> Eliminar
                                        </button>
                                        {if $mapping.sync_status != 'synced'}
                                            <button type="button" class="btn btn-success btn-sm btn-sync-single" data-mapping-id="{$mapping.id}">
                                                <i class="icon-refresh"></i> Sincronizar
                                            </button>
                                        {/if}
                                    </div>
                                </td>
                            </tr>
                        {/foreach}
                    {else}
                        <tr>
                            <td colspan="7" class="text-center">
                                <p class="text-muted">No hay mapeos de categorías configurados.</p>
                                <button type="button" class="btn btn-primary" id="btn-create-first-mapping">
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

{* Modal para mapeo de categorías *}
<div class="modal fade" id="category-mapping-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">Mapeo de Categoría</h4>
            </div>
            <div class="modal-body">
                <form id="category-mapping-form">
                    <input type="hidden" id="mapping-id" name="mapping_id" value="">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="panel panel-default">
                                <div class="panel-heading">
                                    <h4 class="panel-title">Categoría PrestaShop</h4>
                                </div>
                                <div class="panel-body">
                                    <div class="form-group">
                                        <label for="prestashop-category">Seleccionar Categoría:</label>
                                        <select id="prestashop-category" name="prestashop_category_id" class="form-control" required>
                                            <option value="">Seleccionar categoría...</option>
                                            {if isset($prestashop_categories)}
                                                {foreach from=$prestashop_categories item=category}
                                                    <option value="{$category.id}">
                                                        {$category.name|escape:'html':'UTF-8'}
                                                    </option>
                                                {/foreach}
                                            {/if}
                                        </select>
                                    </div>
                                    <div id="prestashop-category-info" class="well" style="display: none;">
                                        <h5>Información de la Categoría:</h5>
                                        <p><strong>Nombre:</strong> <span id="ps-category-name"></span></p>
                                        <p><strong>Descripción:</strong> <span id="ps-category-description"></span></p>
                                        <p><strong>Categoría Padre:</strong> <span id="ps-category-parent"></span></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="panel panel-default">
                                <div class="panel-heading">
                                    <h4 class="panel-title">Categoría Yuju</h4>
                                </div>
                                <div class="panel-body">
                                    <div class="form-group">
                                        <label for="yuju-category">Seleccionar Categoría:</label>
                                        <select id="yuju-category" name="yuju_category_id" class="form-control" required>
                                            <option value="">Seleccionar categoría...</option>
                                            {if isset($yuju_categories)}
                                                {foreach from=$yuju_categories item=category}
                                                    <option value="{$category.id}" data-parent="{$category.parent_id}">
                                                        {if $category.level > 0}
                                                            {for $i=1 to $category.level}--{/for}
                                                        {/if}
                                                        {$category.name|escape:'html':'UTF-8'}
                                                    </option>
                                                {/foreach}
                                            {/if}
                                        </select>
                                    </div>
                                    <div id="yuju-category-info" class="well" style="display: none;">
                                        <h5>Información de la Categoría:</h5>
                                        <p><strong>Nombre:</strong> <span id="yuju-category-name"></span></p>
                                        <p><strong>Descripción:</strong> <span id="yuju-category-description"></span></p>
                                        <p><strong>Categoría Padre:</strong> <span id="yuju-category-parent"></span></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning" id="parent-sync-warning" style="display: none;">
                        <strong>¡Atención!</strong> La categoría padre seleccionada no está sincronizada. Debe sincronizar la categoría padre primero.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-save-mapping">Guardar Mapeo</button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
$(document).ready(function() {
    // Abrir modal para nuevo mapeo
    $('#btn-open-mapping-modal, #btn-create-first-mapping').click(function() {
        $('#category-mapping-modal').modal('show');
        $('#category-mapping-form')[0].reset();
        $('#mapping-id').val('');
        $('#prestashop-category-info, #yuju-category-info').hide();
    });
    
    // Mostrar información de categoría PrestaShop
    $('#prestashop-category').change(function() {
        var categoryId = $(this).val();
        if (categoryId) {
            // Aquí se haría una llamada AJAX para obtener información de la categoría
            $('#prestashop-category-info').show();
            checkParentSyncStatus();
        } else {
            $('#prestashop-category-info').hide();
        }
    });
    
    // Mostrar información de categoría Yuju
    $('#yuju-category').change(function() {
        var categoryId = $(this).val();
        if (categoryId) {
            // Aquí se haría una llamada AJAX para obtener información de la categoría
            $('#yuju-category-info').show();
        } else {
            $('#yuju-category-info').hide();
        }
    });
    
    // Verificar estado de sincronización de categoría padre
    function checkParentSyncStatus() {
        var prestashopCategoryId = $('#prestashop-category').val();
        if (prestashopCategoryId) {
            // Llamada AJAX para verificar si la categoría padre está sincronizada
            $.ajax({
                url: '{$ajax_url}',
                type: 'POST',
                data: {
                    action: 'checkParentSyncStatus',
                    category_id: prestashopCategoryId
                },
                success: function(response) {
                    if (response.parent_not_synced) {
                        $('#parent-sync-warning').show();
                    } else {
                        $('#parent-sync-warning').hide();
                    }
                }
            });
        }
    }
    
    // Guardar mapeo
    $('#btn-save-mapping').click(function() {
        var formData = $('#category-mapping-form').serialize();
        
        $.ajax({
            url: '{$ajax_url}',
            type: 'POST',
            data: formData + '&action=saveMapping',
            success: function(response) {
                if (response.success) {
                    $('#category-mapping-modal').modal('hide');
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('Error al guardar el mapeo');
            }
        });
    });
    
    // Editar mapeo
    $('.btn-edit-mapping').click(function() {
        var mappingId = $(this).data('mapping-id');
        
        $.ajax({
            url: '{$ajax_url}',
            type: 'POST',
            data: {
                action: 'getMapping',
                mapping_id: mappingId
            },
            success: function(response) {
                if (response.success) {
                    $('#mapping-id').val(response.mapping.id);
                    $('#prestashop-category').val(response.mapping.prestashop_category_id);
                    $('#yuju-category').val(response.mapping.yuju_category_id);
                    $('#category-mapping-modal').modal('show');
                }
            }
        });
    });
    
    // Eliminar mapeo
    $('.btn-delete-mapping').click(function() {
        if (confirm('¿Está seguro de que desea eliminar este mapeo?')) {
            var mappingId = $(this).data('mapping-id');
            
            $.ajax({
                url: '{$ajax_url}',
                type: 'POST',
                data: {
                    action: 'deleteMapping',
                    mapping_id: mappingId
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                }
            });
        }
    });
    
    // Sincronizar categoría individual
    $('.btn-sync-single').click(function() {
        var mappingId = $(this).data('mapping-id');
        var button = $(this);
        
        button.prop('disabled', true).html('<i class="icon-spinner icon-spin"></i> Sincronizando...');
        
        $.ajax({
            url: '{$ajax_url}',
            type: 'POST',
            data: {
                action: 'syncSingleCategory',
                mapping_id: mappingId
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                    button.prop('disabled', false).html('<i class="icon-refresh"></i> Sincronizar');
                }
            },
            error: function() {
                alert('Error al sincronizar la categoría');
                button.prop('disabled', false).html('<i class="icon-refresh"></i> Sincronizar');
            }
        });
    });
    
    // Sincronizar todas las categorías
    $('#btn-sync-categories').click(function() {
        if (confirm('¿Está seguro de que desea sincronizar todas las categorías mapeadas?')) {
            var button = $(this);
            button.prop('disabled', true).html('<i class="icon-spinner icon-spin"></i> Sincronizando...');
            
            $.ajax({
                url: '{$ajax_url}',
                type: 'POST',
                data: {
                    action: 'syncAllCategories'
                },
                success: function(response) {
                    if (response.success) {
                        alert('Sincronización completada: ' + response.message);
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                        button.prop('disabled', false).html('<i class="icon-refresh"></i> Sincronizar Categorías');
                    }
                },
                error: function() {
                    alert('Error al sincronizar las categorías');
                    button.prop('disabled', false).html('<i class="icon-refresh"></i> Sincronizar Categorías');
                }
            });
        }
    });
    
    // Actualizar categorías de Yuju
    $('#btn-refresh-yuju-categories').click(function() {
        var button = $(this);
        button.prop('disabled', true).html('<i class="icon-spinner icon-spin"></i> Actualizando...');
        
        $.ajax({
            url: '{$ajax_url}',
            type: 'POST',
            data: {
                action: 'refreshYujuCategories'
            },
            success: function(response) {
                if (response.success) {
                    alert('Categorías de Yuju actualizadas correctamente');
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                    button.prop('disabled', false).html('<i class="icon-download"></i> Actualizar Categorías de Yuju');
                }
            },
            error: function() {
                alert('Error al actualizar las categorías de Yuju');
                button.prop('disabled', false).html('<i class="icon-download"></i> Actualizar Categorías de Yuju');
            }
        });
    });
});
</script>
{/block}