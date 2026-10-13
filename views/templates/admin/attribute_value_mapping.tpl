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
<div class="panel panel-default yuju-module">
    <div class="panel-heading">
        <i class="icon-cogs"></i>
        {l s='Mapeo de valores de atributos' mod='prestashopyuju'}: {$mapping.prestashop_attribute_name|escape:'html':'UTF-8'}
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-12">
                <p class="help-block">
                    {l s='Mapea los valores de atributos de PrestaShop con los valores de Yuju para' mod='prestashopyuju'} 
                    <strong>{$mapping.prestashop_attribute_name|escape:'html':'UTF-8'}</strong>
                </p>
            </div>
        </div>
        
        <form method="post" action="{$current_index|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}&manageValues&id_mapping={$mapping.id_mapping|escape:'html':'UTF-8'}">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>{l s='Valor de PrestaShop' mod='prestashopyuju'}</th>
                            <th>{l s='Valor de Yuju' mod='prestashopyuju'}</th>
                            <th>{l s='Estado' mod='prestashopyuju'}</th>
                            <th>{l s='Acciones' mod='prestashopyuju'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {if $ps_values && count($ps_values) > 0}
                            {foreach from=$ps_values item=ps_value}
                                {assign var="mapped_value" value=""}
                                {assign var="is_mapped" value=false}
                                {foreach from=$existing_mappings item=mapping_item}
                                    {if $mapping_item.prestashop_value_id == $ps_value.id_attribute}
                                        {assign var="mapped_value" value=$mapping_item.yuju_value_id}
                                        {assign var="is_mapped" value=true}
                                        {break}
                                    {/if}
                                {/foreach}
                                
                                <tr>
                                    <td>
                                        <strong>{$ps_value.name|escape:'html':'UTF-8'}</strong>
                                        <br><small class="text-muted">ID: {$ps_value.id_attribute|escape:'html':'UTF-8'}</small>
                                    </td>
                                    <td>
                                        <select name="yuju_value[{$ps_value.id_attribute|escape:'html':'UTF-8'}]" class="form-control">
                                            <option value="">{l s='-- Selecciona un valor de Yuju --' mod='prestashopyuju'}</option>
                                            {if $yuju_values && count($yuju_values) > 0}
                                                {foreach from=$yuju_values item=yuju_value}
                                                    <option value="{$yuju_value.yuju_value_id|escape:'html':'UTF-8'}" 
                                                        {if $mapped_value == $yuju_value.yuju_value_id}selected{/if}>
                                                        {$yuju_value.value_name|escape:'html':'UTF-8'}
                                                    </option>
                                                {/foreach}
                                            {/if}
                                        </select>
                                    </td>
                                    <td>
                                        {if $is_mapped}
                                            <span class="label label-success">
                                                <i class="icon-check"></i> {l s='Mapeado' mod='prestashopyuju'}
                                            </span>
                                        {else}
                                            <span class="label label-warning">
                                                <i class="icon-warning"></i> {l s='Sin mapear' mod='prestashopyuju'}
                                            </span>
                                        {/if}
                                    </td>
                                    <td>
                                        {if $is_mapped}
                                            <button type="button" class="btn btn-danger btn-xs" 
                                                onclick="removeMapping({$ps_value.id_attribute|escape:'javascript':'UTF-8'})">
                                                <i class="icon-trash"></i> {l s='Eliminar' mod='prestashopyuju'}
                                            </button>
                                        {/if}
                                    </td>
                                </tr>
                            {/foreach}
                        {else}
                            <tr>
                                <td colspan="4" class="text-center text-muted">
                                    {l s='No se encontraron valores de atributos de PrestaShop para este atributo.' mod='prestashopyuju'}
                                </td>
                            </tr>
                        {/if}
                    </tbody>
                </table>
            </div>
            
            <div class="panel-footer">
                <div class="btn-group">
                    <button type="submit" name="submitValueMapping" class="btn btn-primary">
                        <i class="icon-save"></i> {l s='Guardar mapeos' mod='prestashopyuju'}
                    </button>
                    <a href="{$current_index|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}" class="btn btn-default">
                        <i class="icon-arrow-left"></i> {l s='Volver al mapeo de atributos' mod='prestashopyuju'}
                    </a>
                </div>
                
                <div class="btn-group pull-right">
                    <button type="button" class="btn btn-info" onclick="autoMapValues()">
                        <i class="icon-magic"></i> {l s='Mapear automáticamente por nombre' mod='prestashopyuju'}
                    </button>
                    <button type="button" class="btn btn-warning" onclick="clearAllMappings()">
                        <i class="icon-eraser"></i> {l s='Borrar todo' mod='prestashopyuju'}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script type="text/javascript">
function removeMapping(psValueId) {
    if (confirm('{l s='¿Seguro que deseas eliminar este mapeo?' mod='prestashopyuju' js=1}')) {
        $('select[name="yuju_value[' + psValueId + ']"]').val('');
        showSuccessMessage('{l s='Mapeo eliminado. Haz clic en Guardar para aplicar los cambios.' mod='prestashopyuju' js=1}');
    }
}

function autoMapValues() {
    if (confirm('{l s='Esto mapeará automáticamente los valores con nombres similares. ¿Deseas continuar?' mod='prestashopyuju' js=1}')) {
        var mapped = 0;
        
        $('select[name^="yuju_value["]').each(function() {
            var select = $(this);
            var psValueName = select.closest('tr').find('td:first strong').text().toLowerCase().trim();
            
            select.find('option').each(function() {
                var yujuValueName = $(this).text().toLowerCase().trim();
                if (yujuValueName && psValueName && yujuValueName === psValueName) {
                    select.val($(this).val());
                    mapped++;
                    return false;
                }
            });
        });
        
        if (mapped > 0) {
            showSuccessMessage(mapped + ' {l s='valores mapeados automáticamente. Haz clic en Guardar para aplicar los cambios.' mod='prestashopyuju' js=1}');
        } else {
            showInfoMessage('{l s='No se encontraron mapeos automáticos por similitud de nombres.' mod='prestashopyuju' js=1}');
        }
    }
}

function clearAllMappings() {
    if (confirm('{l s='¿Seguro que deseas borrar todos los mapeos?' mod='prestashopyuju' js=1}')) {
        $('select[name^="yuju_value["]').val('');
        showSuccessMessage('{l s='Se borraron todos los mapeos. Haz clic en Guardar para aplicar los cambios.' mod='prestashopyuju' js=1}');
    }
}
</script>
{/block}