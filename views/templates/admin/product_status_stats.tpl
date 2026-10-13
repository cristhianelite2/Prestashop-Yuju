{block name="content"}
<div class="panel panel-default yuju-module">
    <div class="panel-heading">
        <i class="icon-bar-chart"></i>
        Estado de productos por categoría
    </div>
    <div class="panel-body">
        <p class="text-muted">Resumen de productos activos agrupados por cada categoría de PrestaShop. Un producto que pertenece a varias categorías aparece en cada una de ellas.</p>
        {if isset($stats.category_stats) && count($stats.category_stats) > 0}
            <div class="table-responsive">
                <table class="table table-striped table-condensed">
                    <thead>
                        <tr>
                            <th>Categoría</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Sincronizados</th>
                            <th class="text-center">Pendientes</th>
                            <th class="text-center">Errores</th>
                            <th class="text-center">Porcentaje de sincronización</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$stats.category_stats item=cat_stat}
                            <tr>
                                <td>
                                    <strong>{$cat_stat.category_name|escape:'html':'UTF-8'}</strong>
                                    <br><small class="text-muted">ID: {$cat_stat.category_id|intval}</small>
                                </td>
                                <td class="text-center">{$cat_stat.total|intval}</td>
                                <td class="text-center"><span class="text-success">{$cat_stat.synced|intval}</span></td>
                                <td class="text-center"><span class="text-warning">{$cat_stat.pending|intval}</span></td>
                                <td class="text-center"><span class="text-danger">{$cat_stat.errors|intval}</span></td>
                                <td class="text-center">
                                    {assign var="cat_sync_rate" value=0}
                                    {if $cat_stat.total > 0}
                                        {assign var="cat_sync_rate" value=($cat_stat.synced / $cat_stat.total * 100)}
                                    {/if}
                                    <span class="{if $cat_sync_rate >= 90}text-success{elseif $cat_sync_rate >= 70}text-warning{else}text-danger{/if}">
                                        {$cat_sync_rate|string_format:"%.1f"|escape:'html':'UTF-8'}%
                                    </span>
                                </td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        {else}
            <p class="text-muted">No hay estadísticas de categorías disponibles.</p>
        {/if}
    </div>
</div>
{/block}
