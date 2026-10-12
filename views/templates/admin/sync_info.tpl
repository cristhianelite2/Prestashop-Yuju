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
        <button type="button" class="btn btn-primary btn-sm pull-right" id="yuju-start-report" data-ajax-url="{$link->getAdminLink('AdminYujuSync')|escape:'html':'UTF-8'}">
            <i class="icon-refresh"></i> {l s='Iniciar sincronización' mod='prestashopyuju'}
        </button>
    </div>
    <div class="panel-body">
        <div id="yuju-product-sync-report" class="hidden" role="status" aria-live="polite" style="margin-bottom: 20px;">
            <div id="yuju-product-sync-message" class="alert alert-info"></div>
            <div class="row">
                <div class="col-sm-3"><strong>{l s='Total:' mod='prestashopyuju'}</strong> <span id="yuju-products-total">0</span></div>
                <div class="col-sm-3"><strong>{l s='Synchronized:' mod='prestashopyuju'}</strong> <span id="yuju-products-synced">0</span></div>
                <div class="col-sm-3"><strong>{l s='Not synchronized:' mod='prestashopyuju'}</strong> <span id="yuju-products-unsynced">0</span></div>
                <div class="col-sm-3"><strong>{l s='Ready to push:' mod='prestashopyuju'}</strong> <span id="yuju-products-syncable">0</span></div>
            </div>
            <div id="yuju-unsynced-products" class="table-responsive" style="margin-top: 15px;"></div>
            <div id="yuju-report-actions" style="margin-top: 15px;">
                <button type="button" class="btn btn-info" id="yuju-poll-report" style="display: none;">
                    <i class="icon-clock-o"></i> {l s='Check report progress' mod='prestashopyuju'}
                </button>
                <button type="button" class="btn btn-info" id="yuju-load-reconciliation" style="display: none;">
                    <i class="icon-eye"></i> {l s='View reconciliation' mod='prestashopyuju'}
                </button>
                <button type="button" class="btn btn-success" id="yuju-push-differences" style="display: none;">
                    <i class="icon-refresh"></i> {l s='Sincronizar (PrestaShop → Yuju)' mod='prestashopyuju'}
                </button>
            </div>
            <p class="text-muted" style="margin-top: 10px;">
                {l s='PrestaShop is the source of truth. Only products already linked to Yuju with differences are sent PS→Yuju. Unmatched products are listed, never created in Yuju.' mod='prestashopyuju'}
            </p>
        </div>

        <div class="row" style="margin-top: 20px;">
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
                    </div>
                </div>
            </div>
        {/if}
    </div>
</div>

{literal}
<script type="text/javascript">
$(function() {
    var $startBtn = $('#yuju-start-report');
    var $pollBtn = $('#yuju-poll-report');
    var $loadBtn = $('#yuju-load-reconciliation');
    var $pushBtn = $('#yuju-push-differences');
    var $report = $('#yuju-product-sync-report');
    var ajaxUrl = $startBtn.data('ajax-url');
    var pollTimer = null;
    var lastReconciliation = null;

    function showMessage(message, type) {
        var $message = $('#yuju-product-sync-message')
            .removeClass('alert-info alert-danger alert-success alert-warning')
            .addClass(type || 'alert-info');
        // External values HTML-escaped: use .text() to prevent XSS
        $message.text(message);
    }

    function setLoading($btn, loading, loadingText) {
        if (loading) {
            $btn.data('original-html', $btn.html());
            $btn.prop('disabled', true).html('<i class="icon-spinner icon-spin"></i> ' + loadingText);
        } else {
            $btn.prop('disabled', false).html($btn.data('original-html'));
        }
    }

    function ajaxAction(action, params, onSuccess, onError) {
        var data = {ajax: 1, action: action};
        if (params) {
            $.each(params, function(k, v) {
                data[k] = v;
            });
        }
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: data
        }).done(function(response) {
            if (onSuccess) {
                onSuccess(response);
            }
        }).fail(function() {
            if (onError) {
                onError();
            } else {
                showMessage('{l s='Request failed. Check the connection to Yuju and try again.' mod='prestashopyuju' js=1}', 'alert-danger');
            }
        });
    }

    function handleReportState(response, startPolling) {
        $report.removeClass('hidden');
        if (!response) {
            showMessage('{l s='Request failed. Check the connection to Yuju and try again.' mod='prestashopyuju' js=1}', 'alert-danger');
            return;
        }

        if (response.success) {
            var status = response.status || 'UNKNOWN';
            if (status === 'CREATED' || status === 'PROCESSING') {
                showMessage('{l s='Report in progress. The Yuju report is being generated; it may take a few minutes.' mod='prestashopyuju' js=1}', 'alert-info');
                $pollBtn.show();
                $loadBtn.hide();
                $pushBtn.hide();
                if (startPolling) {
                    startAutoPoll();
                }
            } else if (status === 'COMPLETED') {
                stopAutoPoll();
                showMessage('{l s='Report ready. Load the reconciliation to see differences.' mod='prestashopyuju' js=1}', 'alert-success');
                $pollBtn.hide();
                $loadBtn.show();
                $pushBtn.hide();
                if (response.product_count !== undefined) {
                    $('#yuju-products-total').text(response.product_count);
                }
            } else if (status === 'DOWNLOAD_ERROR') {
                stopAutoPoll();
                showMessage('{l s='Report ready but download failed validation. Retry the download.' mod='prestashopyuju' js=1}', 'alert-warning');
                $pollBtn.show();
                $loadBtn.hide();
                $pushBtn.hide();
            } else if (status === 'RATE_LIMITED') {
                stopAutoPoll();
                showMessage(response.message || '{l s='Daily report limit reached (2/day UTC).' mod='prestashopyuju' js=1}', 'alert-warning');
                $pollBtn.hide();
                $loadBtn.show();
                $pushBtn.hide();
            } else if (status === 'UNKNOWN') {
                stopAutoPoll();
                showMessage(response.message || '{l s='Report request could not be confirmed. Avoid duplicate requests.' mod='prestashopyuju' js=1}', 'alert-warning');
            } else {
                stopAutoPoll();
                showMessage(response.message || '{l s='Report status unknown.' mod='prestashopyuju' js=1}', 'alert-warning');
            }
        } else {
            stopAutoPoll();
            showMessage(
                response.message || '{l s='Could not start the Yuju general report.' mod='prestashopyuju' js=1}',
                'alert-danger'
            );
        }
    }

    function startAutoPoll() {
        stopAutoPoll();
        pollTimer = setInterval(function() {
            ajaxAction('pollYujuProductReport', null, function(response) {
                handleReportState(response, false);
            });
        }, 15000);
    }

    function stopAutoPoll() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function renderReconciliation(report, message) {
        lastReconciliation = report;
        var products = report.unsynced_products || [];
        $('#yuju-products-total').text(report.total || 0);
        $('#yuju-products-synced').text(report.synced_count || 0);
        $('#yuju-products-unsynced').text(report.unsynced_count || 0);
        $('#yuju-products-syncable').text(report.syncable_count || 0);

        showMessage(
            message || '{l s='Reconciliation loaded. Only linked products with differences can be pushed PS→Yuju.' mod='prestashopyuju' js=1}',
            products.length > 0 ? 'alert-info' : 'alert-success'
        );

        var $container = $('#yuju-unsynced-products');
        $container.empty();

        if (products.length) {
            var $table = $('<table class="table table-bordered table-striped"><thead><tr>' +
                '<th>{l s='PS ID' mod='prestashopyuju' js=1}</th>' +
                '<th>{l s='Yuju ID' mod='prestashopyuju' js=1}</th>' +
                '<th>{l s='Product' mod='prestashopyuju' js=1}</th>' +
                '<th>{l s='SKU' mod='prestashopyuju' js=1}</th>' +
                '<th>{l s='Status' mod='prestashopyuju' js=1}</th>' +
                '<th>{l s='Differences' mod='prestashopyuju' js=1}</th>' +
                '</tr></thead><tbody></tbody></table>');

            $.each(products, function(index, product) {
                var diffFields = [];
                $.each(product.differences || [], function(i, d) {
                    diffFields.push(d.field || '');
                });
                var msg = product.message || '';
                if (product.uncomparable && product.uncomparable.length) {
                    if (msg) {
                        msg += ' ';
                    }
                    msg += '{l s='Uncomparable:' mod='prestashopyuju' js=1} ' + product.uncomparable.join(', ');
                }
                var $row = $('<tr></tr>');
                // HTML-escape every cell: external Yuju values must not be injected as HTML
                $row.append($('<td></td>').text(product.prestashop_product_id || '—'));
                $row.append($('<td></td>').text(product.yuju_product_id || '—'));
                $row.append($('<td></td>').text(product.name || '—'));
                $row.append($('<td></td>').text(product.sku || '—'));
                $row.append($('<td></td>').text(product.status || '—'));
                $row.append($('<td></td>').text(diffFields.length ? diffFields.join(', ') + (msg ? ' — ' + msg : '') : (msg || '—')));
                $table.find('tbody').append($row);
            });
            $container.append($table);

            var syncableCount = 0;
            $.each(products, function(i, p) {
                if (p.syncable && p.yuju_product_id) {
                    syncableCount++;
                }
            });

            if (syncableCount > 0) {
                $pushBtn
                    .html('<i class="icon-refresh"></i> {l s='Sincronizar (PrestaShop → Yuju)' mod='prestashopyuju' js=1} (' + syncableCount + ')')
                    .show();
            } else {
                $pushBtn.hide();
            }
        } else {
            $pushBtn.hide();
        }

        $report.removeClass('hidden');
        $pollBtn.hide();
    }

    $startBtn.on('click', function() {
        setLoading($startBtn, true, '{l s='Requesting report...' mod='prestashopyuju' js=1}');
        ajaxAction('startYujuProductReport', null, function(response) {
            handleReportState(response, true);
            setLoading($startBtn, false);
        }, function() {
            showMessage('{l s='Request failed. Check the connection to Yuju and try again.' mod='prestashopyuju' js=1}', 'alert-danger');
            setLoading($startBtn, false);
        });
    });

    $pollBtn.on('click', function() {
        setLoading($pollBtn, true, '{l s='Checking...' mod='prestashopyuju' js=1}');
        ajaxAction('pollYujuProductReport', null, function(response) {
            handleReportState(response, false);
            setLoading($pollBtn, false);
        }, function() {
            setLoading($pollBtn, false);
        });
    });

    $loadBtn.on('click', function() {
        setLoading($loadBtn, true, '{l s='Loading reconciliation...' mod='prestashopyuju' js=1}');
        ajaxAction('getYujuProductSyncReport', null, function(response) {
            setLoading($loadBtn, false);
            if (response && response.success && response.report) {
                renderReconciliation(response.report, response.message);
            } else {
                showMessage(
                    (response && response.message) || '{l s='No cached completed report. Start a report first.' mod='prestashopyuju' js=1}',
                    'alert-danger'
                );
            }
        }, function() {
            setLoading($loadBtn, false);
        });
    });

    $pushBtn.on('click', function() {
        if (!confirm('{l s='Send PrestaShop values to Yuju for linked products with differences? No products will be created in Yuju.' mod='prestashopyuju' js=1}')) {
            return;
        }
        setLoading($pushBtn, true, '{l s='Pushing to Yuju...' mod='prestashopyuju' js=1}');
        ajaxAction('pushDifferencesToYuju', null, function(response) {
            setLoading($pushBtn, false);
            if (response && response.success) {
                var synced = response.synced_count || 0;
                var details = response.details || [];
                var failed = 0;
                $.each(details, function(i, d) {
                    if (d.status === 'error') {
                        failed++;
                    }
                });
                showMessage(
                    (response.message || '{l s='Push completed.' mod='prestashopyuju' js=1}') +
                    (failed ? ' ' + failed + ' {l s='failed.' mod='prestashopyuju' js=1}' : ''),
                    failed ? 'alert-warning' : 'alert-success'
                );
                if (response.report) {
                    renderReconciliation(response.report, response.message);
                }
            } else {
                showMessage(
                    (response && response.message) || '{l s='Push to Yuju failed.' mod='prestashopyuju' js=1}',
                    'alert-danger'
                );
            }
        }, function() {
            setLoading($pushBtn, false);
        });
    });

    // Auto-poll every 20s when report panel visible and polling is active
    setInterval(function() {
        if (typeof refreshSyncStatus === 'function') {
            refreshSyncStatus();
        }
    }, 30000);
});
{/literal}
</script>
{/block}