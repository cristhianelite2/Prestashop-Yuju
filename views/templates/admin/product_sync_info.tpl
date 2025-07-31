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

<div class="panel panel-default yuju-product-sync-info">
    <div class="panel-heading">
        <i class="icon-cloud"></i>
        {l s='Yuju Synchronization Status' mod='prestashopyuju'}
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-6">
                <strong>{l s='Product ID:' mod='prestashopyuju'}</strong> {$product_id|escape:'html':'UTF-8'}
            </div>
            <div class="col-md-6">
                <strong>{l s='Yuju Product ID:' mod='prestashopyuju'}</strong> 
                {if $yuju_product_id}
                    {$yuju_product_id|escape:'html':'UTF-8'}
                {else}
                    <span class="text-muted">{l s='Not synchronized' mod='prestashopyuju'}</span>
                {/if}
            </div>
        </div>
        
        <div class="row" style="margin-top: 10px;">
            <div class="col-md-12">
                <strong>{l s='Sync Status:' mod='prestashopyuju'}</strong>
                {if $sync_status}
                    {if $sync_status.status == 'synced'}
                        <span class="label label-success">
                            <i class="icon-check"></i> {l s='Synchronized' mod='prestashopyuju'}
                        </span>
                    {elseif $sync_status.status == 'pending'}
                        <span class="label label-warning">
                            <i class="icon-clock-o"></i> {l s='Pending' mod='prestashopyuju'}
                        </span>
                    {elseif $sync_status.status == 'error'}
                        <span class="label label-danger">
                            <i class="icon-exclamation-triangle"></i> {l s='Error' mod='prestashopyuju'}
                        </span>
                    {else}
                        <span class="label label-default">
                            <i class="icon-question"></i> {l s='Unknown' mod='prestashopyuju'}
                        </span>
                    {/if}
                    
                    {if $sync_status.last_sync}
                        <br><small class="text-muted">
                            {l s='Last sync:' mod='prestashopyuju'} {$sync_status.last_sync|date_format:"%Y-%m-%d %H:%M:%S"|escape:'html':'UTF-8'}
                        </small>
                    {/if}
                    
                    {if $sync_status.error_message}
                        <br><small class="text-danger">
                            {l s='Error:' mod='prestashopyuju'} {$sync_status.error_message|escape:'html':'UTF-8'}
                        </small>
                    {/if}
                {else}
                    <span class="label label-default">
                        <i class="icon-minus"></i> {l s='Not synchronized' mod='prestashopyuju'}
                    </span>
                {/if}
            </div>
        </div>
        
        <div class="row" style="margin-top: 15px;">
            <div class="col-md-12">
                <button type="button" class="btn btn-primary btn-sm" onclick="syncProductToYuju({$product_id|intval})">
                    <i class="icon-refresh"></i> {l s='Sync to Yuju' mod='prestashopyuju'}
                </button>
                
                {if $yuju_product_id}
                    <button type="button" class="btn btn-info btn-sm" onclick="viewYujuProduct('{$yuju_product_id|escape:'html':'UTF-8'}')">
                        <i class="icon-external-link"></i> {l s='View in Yuju' mod='prestashopyuju'}
                    </button>
                {/if}
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
function syncProductToYuju(productId) {
    if (confirm('{l s='Are you sure you want to sync this product to Yuju?' mod='prestashopyuju' js=1}')) {
        $.ajax({
            url: '{$link->getAdminLink('AdminYujuSync')|escape:'html':'UTF-8'}',
            type: 'POST',
            data: {
                action: 'syncProduct',
                product_id: productId,
                ajax: true
            },
            success: function(response) {
                if (response.success) {
                    showSuccessMessage('{l s='Product sync initiated successfully' mod='prestashopyuju' js=1}');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showErrorMessage(response.message || '{l s='Failed to sync product' mod='prestashopyuju' js=1}');
                }
            },
            error: function() {
                showErrorMessage('{l s='An error occurred while syncing the product' mod='prestashopyuju' js=1}');
            }
        });
    }
}

function viewYujuProduct(yujuProductId) {
    var yujuUrl = 'https://app.yuju.io/products/' + yujuProductId;
    window.open(yujuUrl, '_blank');
}
</script>