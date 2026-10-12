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
<div class="panel panel-default yuju-sync-info">
    <div class="panel-heading">
        <i class="icon-refresh"></i>
        {l s='Synchronization Status' mod='prestashopyuju'}
        <button type="button" class="btn btn-primary btn-sm pull-right" id="yuju-inspect-products" data-ajax-url="{$link->getAdminLink('AdminYujuSync')|escape:'html':'UTF-8'}">
            <i class="icon-refresh"></i> {l s='Start synchronization' mod='prestashopyuju'}
        </button>
    </div>
    <div class="panel-body">
        <div id="yuju-product-sync-report" class="hidden" role="status" aria-live="polite" style="margin-bottom: 20px;">
            <div id="yuju-product-sync-message" class="alert alert-info"></div>
            <div class="row">
                <div class="col-sm-4"><strong>{l s='Yuju products:' mod='prestashopyuju'}</strong> <span id="yuju-products-total">0</span></div>
                <div class="col-sm-4"><strong>{l s='Synchronized:' mod='prestashopyuju'}</strong> <span id="yuju-products-synced">0</span></div>
                <div class="col-sm-4"><strong>{l s='Not synchronized:' mod='prestashopyuju'}</strong> <span id="yuju-products-unsynced">0</span></div>
            </div>
            <div id="yuju-unsynced-products" class="table-responsive" style="margin-top: 15px;"></div>
            <button type="button" class="btn btn-success" id="yuju-sync-unsynced-products" style="display: none; margin-top: 15px;">
                <i class="icon-refresh"></i> {l s='Synchronize missing products' mod='prestashopyuju'}
            </button>
        </div>

        <div class="row">
            <div class="col-md-3">
                <div class="sync-status-card">
                    <h4>
                        {if $is_sync_running}
                            <span class="label label-warning">
                                <i class="icon-spinner icon-spin"></i> {l s='Running' mod='prestashopyuju'}
                            </span>
                        {else}
                            <span class="label label-success">
                                <i class="icon-check"></i> {l s='Idle' mod='prestashopyuju'}
                            </span>
                        {/if}
                    </h4>
                    <p class="text-muted">{l s='Current Status' mod='prestashopyuju'}</p>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="sync-status-card">
                    <h4>{if $last_sync_date}{$last_sync_date|date_format:"%Y-%m-%d %H:%M"|escape:'html':'UTF-8'}{else}{l s='Never' mod='prestashopyuju'}{/if}</h4>
                    <p class="text-muted">{l s='Last Sync' mod='prestashopyuju'}</p>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="sync-status-card">
                    <h4>
                        {if $auto_sync_enabled}
                            <span class="label label-success">{l s='Enabled' mod='prestashopyuju'}</span>
                        {else}
                            <span class="label label-default">{l s='Disabled' mod='prestashopyuju'}</span>
                        {/if}
                    </h4>
                    <p class="text-muted">{l s='Auto Sync' mod='prestashopyuju'}</p>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="sync-status-card">
                    <h4>{if isset($sync_stats.total_synced)}{$sync_stats.total_synced|intval}{else}0{/if}</h4>
                    <p class="text-muted">{l s='Total Synced' mod='prestashopyuju'}</p>
                </div>
            </div>
        </div>
        
        {if isset($sync_stats)}
            <div class="row" style="margin-top: 20px;">
                <div class="col-md-12">
                    <h5>{l s='Sync Statistics' mod='prestashopyuju'}</h5>
                    <div class="row">
                        <div class="col-md-2">
                            <div class="text-center">
                                <strong>{if isset($sync_stats.products_synced)}{$sync_stats.products_synced|intval}{else}0{/if}</strong>
                                <br><small>{l s='Products' mod='prestashopyuju'}</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="text-center">
                                <strong>{if isset($sync_stats.categories_synced)}{$sync_stats.categories_synced|intval}{else}0{/if}</strong>
                                <br><small>{l s='Categories' mod='prestashopyuju'}</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="text-center">
                                <strong>{if isset($sync_stats.stock_synced)}{$sync_stats.stock_synced|intval}{else}0{/if}</strong>
                                <br><small>{l s='Stock Updates' mod='prestashopyuju'}</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="text-center">
                                <strong>{if isset($sync_stats.price_synced)}{$sync_stats.price_synced|intval}{else}0{/if}</strong>
                                <br><small>{l s='Price Updates' mod='prestashopyuju'}</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="text-center">
                                <strong>{if isset($sync_stats.errors)}{$sync_stats.errors|intval}{else}0{/if}</strong>
                                <br><small class="text-danger">{l s='Errors' mod='prestashopyuju'}</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="text-center">
                                <strong>{if isset($sync_stats.success_rate)}{$sync_stats.success_rate|string_format:"%.1f"|escape:'html':'UTF-8'}%{else}0%{/if}</strong>
                                <br><small>{l s='Success Rate' mod='prestashopyuju'}</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        {/if}
        
        {if $is_sync_running}
            <div class="row" style="margin-top: 20px;">
                <div class="col-md-12">
                    <div class="alert alert-info">
                        <i class="icon-info-circle"></i>
                        {l s='A synchronization process is currently running. Please wait for it to complete before starting a new sync.' mod='prestashopyuju'}
                        <br>
                        <button type="button" class="btn btn-warning btn-sm" onclick="stopSync()" style="margin-top: 10px;">
                            <i class="icon-stop"></i> {l s='Stop Sync' mod='prestashopyuju'}
                        </button>
                    </div>
                </div>
            </div>
        {/if}
    </div>
</div>

<script type="text/javascript">
$(function() {
    var $inspectButton = $('#yuju-inspect-products');
    var $syncButton = $('#yuju-sync-unsynced-products');
    var $report = $('#yuju-product-sync-report');
    var ajaxUrl = $inspectButton.data('ajax-url');

    function getYujuSyncErrorGuidance(message, endpoint) {
        var signatureError = /IncompleteSignatureException|Authorization header requires ['"](?:Credential|Signature|SignedHeaders)['"] parameter/i.test(message || '');

        if (!signatureError || endpoint !== 'GET /products') {
            return null;
        }

        return {
            title: 'No se pudo consultar el listado de productos.',
            explanation: 'GET /products devolvió un error al procesar Authorization. Si «Probar Conectividad» acaba de pasar, el token funciona con GET /account y GET /webhook-sub; eso no confirma qué autenticación o permisos exige el endpoint de productos. La documentación pública consultada no describe GET /products como listado de catálogo.',
            steps: [
                'No cambies el Client ID ni el Secret, ni regeneres el token solo por este error: la prueba ya confirmó conexión con otros endpoints de Yuju.',
                'Si necesitas comparar ID, SKU, stock y precio, puedes usar GET /products-offer-report. El reporte está limitado a una solicitud cada 12 horas y no contiene todos los datos para crear un producto completo en PrestaShop.',
                'Para obtener datos generales y variaciones, existe POST /products-gral-report. Es asíncrono: se consulta el estado con id_task y tiene un máximo de dos solicitudes al día. PrestaShop tendría que adaptarse a ese flujo antes de importar el catálogo completo.',
                'Si necesitas el listado directo, consulta con Yuju qué endpoint, método, esquema de autorización y permisos deben usarse. Comparte método, URL, error HTTP, hora y Request ID si está disponible; nunca envíes tokens, Client Secret ni código OAuth.'
            ],
            offerReportUrl: 'https://api-docs.yuju.io/docs/obtener-oferta-de-productos',
            generalReportUrl: 'https://api-docs.yuju.io/docs/obtener-informacion-general',
            supportUrl: 'https://help.yuju.io/es/',
            supportEmail: 'mailto:soporte@yuju.io'
        };
    }

    function renderProductSyncMessage(message, isError, hasUnsyncedProducts) {
        var $message = $('#yuju-product-sync-message')
            .toggleClass('alert-danger', !!isError)
            .toggleClass('alert-info', !isError && hasUnsyncedProducts)
            .toggleClass('alert-success', !isError && !hasUnsyncedProducts);
        var guidance = isError ? getYujuSyncErrorGuidance(message, 'GET /products') : null;

        if (!guidance) {
            $message.text(message);
            return;
        }

        $message.empty()
            .append($('<strong></strong>').text(guidance.title))
            .append($('<p></p>').text(guidance.explanation));

        var $steps = $('<ol></ol>');
        $.each(guidance.steps, function(index, step) {
            $steps.append($('<li></li>').text(step));
        });
        $message.append($steps);

        var $links = $('<p></p>');
        $links.append($('<a></a>')
            .attr('href', guidance.offerReportUrl)
            .attr('target', '_blank')
            .attr('rel', 'noopener noreferrer')
            .text('Reporte de oferta (SKU/stock/precio)'));
        $links.append(document.createTextNode(' · '));
        $links.append($('<a></a>')
            .attr('href', guidance.generalReportUrl)
            .attr('target', '_blank')
            .attr('rel', 'noopener noreferrer')
            .text('Reporte de datos generales'));
        $links.append(document.createTextNode(' · '));
        $links.append($('<a></a>')
            .attr('href', guidance.supportUrl)
            .attr('target', '_blank')
            .attr('rel', 'noopener noreferrer')
            .text('Centro de soporte de Yuju'));
        $links.append(document.createTextNode(' · '));
        $links.append($('<a></a>').attr('href', guidance.supportEmail).text('Escribir a soporte de Yuju'));
        $message.append($links);
    }

    function renderProductReport(report, message, isError) {
        var products = report.unsynced_products || [];
        $('#yuju-products-total').text(report.total || 0);
        $('#yuju-products-synced').text(report.synced_count || 0);
        $('#yuju-products-unsynced').text(report.unsynced_count || 0);
        renderProductSyncMessage(message, isError, products.length > 0);

        var $table = $('<table class="table table-bordered table-striped"><thead><tr>' +
            '<th>{l s='Yuju ID' mod='prestashopyuju' js=1}</th>' +
            '<th>{l s='Product' mod='prestashopyuju' js=1}</th>' +
            '<th>{l s='SKU' mod='prestashopyuju' js=1}</th>' +
            '</tr></thead><tbody></tbody></table>');
        $.each(products, function(index, product) {
            var $row = $('<tr></tr>');
            $row.append($('<td></td>').text(product.id));
            $row.append($('<td></td>').text(product.name || '—'));
            $row.append($('<td></td>').text(product.sku || '—'));
            $table.find('tbody').append($row);
        });

        $('#yuju-unsynced-products').empty();
        if (products.length) {
            $('#yuju-unsynced-products').append($table);
            $syncButton
                .html('<i class="icon-refresh"></i> {l s='Synchronize missing products' mod='prestashopyuju' js=1} (' + products.length + ')')
                .show();
        } else {
            $syncButton.hide();
        }
        $report.removeClass('hidden');
    }

    function requestProductReport(action, button, loadingText, successHandler) {
        var originalHtml = button.html();
        button.prop('disabled', true).html('<i class="icon-spinner icon-spin"></i> ' + loadingText);
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {ldelim}ajax: 1, action: action{rdelim}
        }).done(function(response) {
            if (!response || !response.success) {
                var errorMessage = response && response.message ? response.message : '{l s='Could not retrieve products from Yuju.' mod='prestashopyuju' js=1}';
                if (response && response.report) {
                    renderProductReport(response.report, errorMessage, true);
                } else {
                    $syncButton.hide();
                    $('#yuju-unsynced-products').empty();
                    $('#yuju-product-sync-report').removeClass('hidden');
                    renderProductSyncMessage(errorMessage, true, false);
                }
                return;
            }
            successHandler(response);
        }).fail(function() {
            $('#yuju-product-sync-report').removeClass('hidden');
            renderProductSyncMessage('{l s='Request failed. Check the connection to Yuju and try again.' mod='prestashopyuju' js=1}', true, false);
        }).always(function() {
            button.prop('disabled', false).html(originalHtml);
        });
    }

    $inspectButton.on('click', function() {
        requestProductReport(
            'inspectYujuProducts',
            $inspectButton,
            '{l s='Retrieving products...' mod='prestashopyuju' js=1}',
            function(response) {
                renderProductReport(response.report, '{l s='Product synchronization status updated.' mod='prestashopyuju' js=1}', false);
            }
        );
    });

    $syncButton.on('click', function() {
        requestProductReport(
            'syncUnsyncedProducts',
            $syncButton,
            '{l s='Synchronizing products...' mod='prestashopyuju' js=1}',
            function(response) {
                renderProductReport(response.report, response.message, false);
            }
        );
    });
});

function stopSync() {
    if (confirm('{l s='Are you sure you want to stop the current synchronization?' mod='prestashopyuju' js=1}')) {
        $.ajax({
            url: '{$link->getAdminLink('AdminYujuSync')|escape:'html':'UTF-8'}',
            type: 'POST',
            data: {
                action: 'stopSync',
                ajax: true
            },
            success: function(response) {
                if (response.success) {
                    showSuccessMessage('{l s='Synchronization stopped successfully' mod='prestashopyuju' js=1}');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showErrorMessage(response.message || '{l s='Failed to stop synchronization' mod='prestashopyuju' js=1}');
                }
            },
            error: function() {
                showErrorMessage('{l s='An error occurred while stopping the synchronization' mod='prestashopyuju' js=1}');
            }
        });
    }
}

// Auto-refresh sync status every 30 seconds
setInterval(function() {
    if (typeof refreshSyncStatus === 'function') {
        refreshSyncStatus();
    }
}, 30000);
</script>
{/block}
