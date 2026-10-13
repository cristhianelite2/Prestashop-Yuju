{*
 * Vista independiente de estadísticas de productos por categoría.
 * No comparte la plantilla del resumen general de productos.
 *}
{extends file="./layout.tpl"}

{block name="content"}
<div class="panel panel-default yuju-module yuju-category-status">
    <div class="panel-heading">
        <i class="icon-sitemap"></i>
        Estado de productos por categoría
    </div>
    <div class="panel-body">
        <p class="text-muted">
            Resumen de productos activos agrupados por cada categoría de PrestaShop.
            Los productos que pertenecen a varias categorías se contabilizan en cada una de ellas.
        </p>

        {if isset($stats.category_stats) && count($stats.category_stats) > 0}
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Categoría</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Sincronizados</th>
                            <th class="text-center">Pendientes</th>
                            <th class="text-center">Errores</th>
                            <th style="min-width:190px;">Porcentaje de sincronización</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$stats.category_stats item=cat_stat}
                            {assign var="cat_sync_rate" value=0}
                            {if $cat_stat.total > 0}
                                {assign var="cat_sync_rate" value=($cat_stat.synced / $cat_stat.total * 100)}
                            {/if}
                            {if $cat_sync_rate > 100}{assign var="cat_sync_rate" value=100}{/if}
                            <tr>
                                <td>
                                    <strong>{$cat_stat.category_name|escape:'html':'UTF-8'}</strong>
                                    <br><small class="text-muted">ID de categoría: {$cat_stat.category_id|intval}</small>
                                </td>
                                <td class="text-center">{$cat_stat.total|intval}</td>
                                <td class="text-center"><span class="text-success">{$cat_stat.synced|intval}</span></td>
                                <td class="text-center"><span class="text-warning">{$cat_stat.pending|intval}</span></td>
                                <td class="text-center"><span class="text-danger">{$cat_stat.errors|intval}</span></td>
                                <td>
                                    <div class="progress" style="margin-bottom:4px;">
                                        <div class="progress-bar {if $cat_sync_rate >= 90}progress-bar-success{elseif $cat_sync_rate >= 70}progress-bar-warning{else}progress-bar-danger{/if}"
                                             role="progressbar"
                                             aria-valuenow="{$cat_sync_rate|string_format:'%.1f'|escape:'html':'UTF-8'}"
                                             aria-valuemin="0" aria-valuemax="100"
                                             style="width:{$cat_sync_rate|string_format:'%.1f'|escape:'html':'UTF-8'}%">
                                        </div>
                                    </div>
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
            <div class="alert alert-info">
                No hay estadísticas de categorías disponibles.
            </div>
        {/if}
    </div>
</div>
<style>
.yuju-category-status .progress { height: 18px; }
.yuju-category-status .progress-bar { min-width: 0; }
</style>
{/block}
