{*
* Mapeo de estados de pedidos Yuju / PrestaShop.
*}
{extends file="./layout.tpl"}

{block name="content"}
<div class="panel">
    <div class="panel-heading">
        <i class="icon-exchange"></i>
        Mapeo de estados de pedidos
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-lg-12">
                {if $madkting_status_available && !$status_mappings}
                    <form method="post" action="{$import_status_url|escape:'html':'UTF-8'}" class="form-inline">
                        <input type="hidden" name="token" value="{$token|escape:'html':'UTF-8'}">
                        <button type="submit" name="submitImportMadktingStatusMappings" class="btn btn-info"
                                onclick="return confirm('Se leerán {$madkting_status_count|intval} registros de Madkting. ¿Deseas continuar?');">
                            <i class="icon-download"></i>
                            Importar mapeos de estados de Madkting ({$madkting_status_count|intval})
                        </button>
                    </form>
                {elseif !$madkting_status_available && !$status_mappings}
                    <div class="alert alert-warning">
                        No se encontraron mapeos configurados ni registros disponibles para importar.
                    </div>
                {/if}
            </div>
        </div>

        <hr>
        <h4>Mapeos configurados</h4>
        {if isset($status_mappings) && $status_mappings}
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ID estado PrestaShop</th>
                            <th>Estado PrestaShop</th>
                            <th>Estado Yuju</th>
                            <th>Activo</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$status_mappings item=mapping}
                            <tr>
                                <td>{$mapping.id|intval}</td>
                                <td>{$mapping.prestashop_status_id|intval}</td>
                                <td>
                                    {if $mapping.prestashop_status_name}
                                        {$mapping.prestashop_status_name|escape:'html':'UTF-8'}
                                    {else}
                                        <span class="text-warning">Estado no encontrado</span>
                                    {/if}
                                </td>
                                <td><code>{$mapping.yuju_status_name|escape:'html':'UTF-8'}</code></td>
                                <td>
                                    {if $mapping.is_active}
                                        <span class="label label-success">Sí</span>
                                    {else}
                                        <span class="label label-default">No</span>
                                    {/if}
                                </td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        {else}
            <div class="alert alert-warning">Todavía no hay mapeos configurados. Puedes importar los registros heredados con el botón anterior.</div>
        {/if}
    </div>
</div>
{/block}
