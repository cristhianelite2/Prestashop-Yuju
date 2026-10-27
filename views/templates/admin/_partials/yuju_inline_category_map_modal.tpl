{*
 * Modal único de mapeo PrestaShop → Yuju.
 * Compartido por AdminYujuCategoryBulk y AdminYujuProductStatus.
 *}
<div class="modal fade" id="yuju-inline-map-modal" tabindex="-1" role="dialog" aria-labelledby="yuju-inline-map-title" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="yuju-inline-map-title"><i class="icon-sitemap"></i> Mapeo de categoría</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="yuju-inline-map-ps-label">Categoría PrestaShop</label>
                    <input type="text" id="yuju-inline-map-ps-label" class="form-control" value="" readonly>
                    <div id="yuju-inline-map-ps-select-wrap" style="display:none;margin-top:8px;">
                        <select id="yuju-inline-map-ps-select" class="form-control"></select>
                        <p class="help-block">El producto tiene varias categorías; elige cuál quieres mapear.</p>
                    </div>
                    <input type="hidden" id="yuju-inline-map-ps-id" value="0">
                </div>
                <div class="form-group">
                    <label for="yuju-inline-map-select">Categoría Yuju</label>
                    <input type="search" id="yuju-inline-map-search" class="form-control" placeholder="Buscar categoría Yuju…" autocomplete="off" style="margin-bottom:8px;">
                    <select id="yuju-inline-map-select" class="form-control">
                        <option value="">Seleccione…</option>
                        {foreach from=$yuju_category_options item=catopt}
                            <option value="{$catopt.id|escape:'html':'UTF-8'}">{$catopt.name|escape:'html':'UTF-8'} (ID {$catopt.id|escape:'html':'UTF-8'})</option>
                        {/foreach}
                    </select>
                </div>
                <div id="yuju-inline-map-msg" class="text-muted" role="status" aria-live="polite"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="yuju-inline-map-save-btn"><i class="icon-save"></i> Guardar mapeo</button>
            </div>
        </div>
    </div>
</div>
<div id="yuju-inline-map-config" class="hidden" style="display:none"
    data-ajax-url="{if isset($ajax_url)}{$ajax_url|escape:'html':'UTF-8'}{elseif isset($yuju_product_info_ajax_url)}{$yuju_product_info_ajax_url|escape:'html':'UTF-8'}{/if}"
    data-token="{if isset($token)}{$token|escape:'html':'UTF-8'}{elseif isset($yuju_product_info_token)}{$yuju_product_info_token|escape:'html':'UTF-8'}{/if}"></div>
