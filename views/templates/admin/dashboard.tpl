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
<div class="yuju-dashboard">
    <div class="yuju-dashboard-hero">
        <div class="row">
            <div class="col-md-8">
                <h2 class="yuju-hero-title">
                    <i class="icon-dashboard"></i>
                    Panel de Control Yuju
                </h2>
                <p class="yuju-hero-subtitle">
                    Administre de forma centralizada la conexión, sincronización y salud de su integración con Yuju.
                </p>
            </div>
            <div class="col-md-4 text-right yuju-hero-actions">
                <a href="{$link->getAdminLink('AdminYujuConfiguration')|escape:'html':'UTF-8'}" class="btn btn-primary">
                    <i class="icon-cogs"></i> Configuración
                </a>
                <a href="{$link->getAdminLink('AdminYujuLogs')|escape:'html':'UTF-8'}" class="btn btn-default">
                    <i class="icon-file-text"></i> Registros
                </a>
            </div>
        </div>
    </div>

    <div class="yuju-connection-banner {if $oauth_status.is_connected && $oauth_status.has_token}is-connected{else}is-disconnected{/if}">
        <div class="yuju-connection-icon">
            <i class="icon-{if $oauth_status.is_connected && $oauth_status.has_token}check-circle{else}warning{/if}"></i>
        </div>
        <div class="yuju-connection-content">
            <h4>Estado OAuth</h4>
            {if $oauth_status.is_connected && $oauth_status.has_token}
                <p>Conexión activa con Yuju API.</p>
                {if $oauth_status.client_id}
                    <small>Client ID: {$oauth_status.client_id|escape:'html':'UTF-8'}</small>
                {/if}
            {else}
                <p>La cuenta no está conectada a Yuju API.</p>
                <a href="{$link->getAdminLink('AdminYujuConfiguration')|escape:'html':'UTF-8'}">Configurar conexión ahora</a>
            {/if}
        </div>
    </div>

    <div class="panel panel-default yuju-dashboard-panel" id="yuju-account-panel">
        <div class="panel-heading">
            <i class="icon-building"></i>
            Información de Yuju
            <span class="panel-heading-action">
                <button type="button" class="btn btn-default btn-sm" id="yuju-refresh-account-btn">
                    <i class="icon-refresh"></i> Actualizar
                </button>
            </span>
        </div>
        <div class="panel-body">
            <div id="yuju-account-alert" class="alert" style="display:none; margin-bottom:12px;"></div>

            <div class="yuju-account-meta" id="yuju-account-meta">
                <div class="yuju-account-meta__item">
                    <span class="yuju-account-meta__label">Cuenta</span>
                    <span class="yuju-account-meta__value" id="yuju-acc-account-name">{if $yuju_account_info.account_name}{$yuju_account_info.account_name|escape:'html':'UTF-8'}{else}<span class="text-muted">—</span>{/if}</span>
                    <span class="yuju-account-meta__id" id="yuju-acc-id-account">{if $yuju_account_info.id_account}ID {$yuju_account_info.id_account|escape:'html':'UTF-8'}{else}&nbsp;{/if}</span>
                </div>
                <div class="yuju-account-meta__item">
                    <span class="yuju-account-meta__label">Tienda</span>
                    <span class="yuju-account-meta__value" id="yuju-acc-shop-name">{if $yuju_account_info.shop_name}{$yuju_account_info.shop_name|escape:'html':'UTF-8'}{else}<span class="text-muted">—</span>{/if}</span>
                    <span class="yuju-account-meta__id" id="yuju-acc-id-shop">{if $yuju_account_info.id_shop}ID {$yuju_account_info.id_shop|escape:'html':'UTF-8'}{else}&nbsp;{/if}</span>
                </div>
                <div class="yuju-account-meta__item yuju-account-meta__item--muted">
                    <span class="yuju-account-meta__label">Actualizado</span>
                    <span class="yuju-account-meta__value yuju-account-meta__value--sm" id="yuju-acc-updated-at">{if $yuju_account_info.updated_at}{$yuju_account_info.updated_at|escape:'html':'UTF-8'}{else}<span class="text-muted">Nunca</span>{/if}</span>
                </div>
            </div>

            <p class="yuju-account-meta__hint">
                Canales conectados según <code>GET /account</code>
            </p>

            <div class="table-responsive">
                <table class="table table-striped" id="yuju-channels-table">
                    <thead>
                        <tr>
                            <th style="width:120px;">ID canal</th>
                            <th>Nombre</th>
                            <th>Canal</th>
                        </tr>
                    </thead>
                    <tbody id="yuju-channels-tbody">
                        {if $yuju_account_info.channels && count($yuju_account_info.channels) > 0}
                            {foreach from=$yuju_account_info.channels item=channel}
                                <tr>
                                    <td><code>{$channel.id_channel|escape:'html':'UTF-8'}</code></td>
                                    <td>{$channel.name|escape:'html':'UTF-8'}</td>
                                    <td>{$channel.generic_name|escape:'html':'UTF-8'}</td>
                                </tr>
                            {/foreach}
                        {else}
                            <tr class="yuju-channels-empty">
                                <td colspan="3" class="text-center text-muted">
                                    Sin datos. Pulse «Actualizar» para cargar la información de Yuju.
                                </td>
                            </tr>
                        {/if}
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row yuju-kpi-row">
        <div class="col-md-3 col-sm-6">
            <div class="yuju-kpi-card kpi-info">
                <div class="yuju-kpi-icon"><i class="icon-cube"></i></div>
                <div class="yuju-kpi-data">
                    <span class="yuju-kpi-value">{$product_stats.total|escape:'html':'UTF-8'}</span>
                    <span class="yuju-kpi-label">Total Productos</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="yuju-kpi-card kpi-success">
                <div class="yuju-kpi-icon"><i class="icon-check"></i></div>
                <div class="yuju-kpi-data">
                    <span class="yuju-kpi-value">{$product_stats.synced|escape:'html':'UTF-8'}</span>
                    <span class="yuju-kpi-label">Sincronizados</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="yuju-kpi-card kpi-warning">
                <div class="yuju-kpi-icon"><i class="icon-clock-o"></i></div>
                <div class="yuju-kpi-data">
                    <span class="yuju-kpi-value">{$product_stats.pending|escape:'html':'UTF-8'}</span>
                    <span class="yuju-kpi-label">Pendientes</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="yuju-kpi-card kpi-danger">
                <div class="yuju-kpi-icon"><i class="icon-exclamation-triangle"></i></div>
                <div class="yuju-kpi-data">
                    <span class="yuju-kpi-value">{$product_stats.errors|escape:'html':'UTF-8'}</span>
                    <span class="yuju-kpi-label">Con Error</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="panel panel-default yuju-dashboard-panel">
                <div class="panel-heading">
                    <i class="icon-bell"></i>
                    Actividad de Webhooks y Órdenes
                </div>
                <div class="panel-body">
                    <div class="row yuju-mini-stats">
                        <div class="col-sm-3 text-center">
                            <h3 class="text-info">{$webhook_stats.recent_24h|escape:'html':'UTF-8'}</h3>
                            <p>Webhooks (24h)</p>
                        </div>
                        <div class="col-sm-3 text-center">
                            <h3 class="text-primary">{$webhook_stats.orders_7d|escape:'html':'UTF-8'}</h3>
                            <p>Órdenes (7 días)</p>
                        </div>
                        <div class="col-sm-3 text-center">
                            <h3 class="text-success">{$webhook_stats.success_7d|escape:'html':'UTF-8'}</h3>
                            <p>Procesados OK</p>
                        </div>
                        <div class="col-sm-3 text-center">
                            <h3 class="text-danger">{$webhook_stats.errors_7d|escape:'html':'UTF-8'}</h3>
                            <p>Con errores</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel panel-default yuju-dashboard-panel">
                <div class="panel-heading">
                    <i class="icon-refresh"></i>
                    Elementos Sincronizados (Última Semana)
                </div>
                <div class="panel-body">
                    <div class="row yuju-mini-stats">
                        <div class="col-sm-4 text-center">
                            <h3 class="text-primary">{$recent_syncs.products|escape:'html':'UTF-8'}</h3>
                            <p>Productos</p>
                        </div>
                        <div class="col-sm-4 text-center">
                            <h3 class="text-info">{$recent_syncs.categories|escape:'html':'UTF-8'}</h3>
                            <p>Categorías</p>
                        </div>
                        <div class="col-sm-4 text-center">
                            <h3 class="text-success">{$recent_syncs.attributes|escape:'html':'UTF-8'}</h3>
                            <p>Atributos</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="panel panel-default yuju-dashboard-panel">
                <div class="panel-heading">
                    <i class="icon-heartbeat"></i>
                    Salud del Módulo
                </div>
                <div class="panel-body yuju-health-list">
                    <div class="yuju-health-item">
                        <span>Errores de sincronización (7 días)</span>
                        <strong class="text-danger">{$dashboard_stats.errors_last_week|escape:'html':'UTF-8'}</strong>
                    </div>
                    <div class="yuju-health-item">
                        <span>Advertencias (7 días)</span>
                        <strong class="text-warning">{$dashboard_stats.warnings_last_week|escape:'html':'UTF-8'}</strong>
                    </div>
                    <div class="yuju-health-item">
                        <span>Última sincronización</span>
                        <strong>
                            {if $last_sync_date}
                                {$last_sync_date|date_format:"%d/%m/%Y %H:%M"}
                            {else}
                                <span class="text-muted">Nunca</span>
                            {/if}
                        </strong>
                    </div>
                    <div class="yuju-health-item">
                        <span>Versión del módulo</span>
                        <strong>v{$module_version|escape:'html':'UTF-8'}</strong>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script type="text/javascript">
(function ($) {
    function escapeHtml(v) {
        return $('<div/>').text(v == null ? '' : String(v)).html();
    }

    function showAlert(type, msg) {
        var $a = $('#yuju-account-alert');
        $a.removeClass('alert-success alert-danger alert-info alert-warning')
            .addClass('alert-' + (type || 'info'))
            .html(msg)
            .show();
    }

    function fillValue($el, value, emptyHtml) {
        emptyHtml = emptyHtml || '<span class="text-muted">—</span>';
        if (value === null || value === undefined || value === '') {
            $el.html(emptyHtml);
        } else {
            $el.text(String(value));
        }
    }

    function fillId($el, value, prefix) {
        prefix = prefix || 'ID ';
        if (value === null || value === undefined || value === '') {
            $el.html('&nbsp;');
        } else {
            $el.text(prefix + String(value));
        }
    }

    function renderAccount(account) {
        if (!account) return;
        fillValue($('#yuju-acc-account-name'), account.account_name);
        fillId($('#yuju-acc-id-account'), account.id_account);
        fillValue($('#yuju-acc-shop-name'), account.shop_name);
        fillId($('#yuju-acc-id-shop'), account.id_shop);
        fillValue($('#yuju-acc-updated-at'), account.updated_at, '<span class="text-muted">Nunca</span>');

        var channels = account.channels || [];
        var $tbody = $('#yuju-channels-tbody');
        if (!channels.length) {
            $tbody.html(
                '<tr class="yuju-channels-empty"><td colspan="3" class="text-center text-muted">' +
                'Sin canales en la respuesta.</td></tr>'
            );
            return;
        }
        var html = '';
        channels.forEach(function (ch) {
            html += '<tr>'
                + '<td><code>' + escapeHtml(ch.id_channel) + '</code></td>'
                + '<td>' + escapeHtml(ch.name) + '</td>'
                + '<td>' + escapeHtml(ch.generic_name) + '</td>'
                + '</tr>';
        });
        $tbody.html(html);
    }

    $(function () {
        var ajaxUrl = '{$ajax_url|escape:'javascript':'UTF-8'}';
        var token = '{$token|escape:'javascript':'UTF-8'}';

        $('#yuju-refresh-account-btn').on('click', function () {
            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="icon-spinner icon-spin"></i> Actualizando…');
            showAlert('info', 'Consultando <code>GET /account</code>…');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                dataType: 'json',
                timeout: 60000,
                data: {
                    ajax: 1,
                    action: 'refreshAccountInfo',
                    token: token
                }
            }).done(function (resp) {
                if (resp && resp.success) {
                    renderAccount(resp.account);
                    showAlert('success', resp.message || 'Información actualizada.');
                } else {
                    showAlert('danger', (resp && resp.message) ? resp.message : 'No se pudo actualizar.');
                    if (resp && resp.account) {
                        renderAccount(resp.account);
                    }
                }
            }).fail(function (xhr) {
                var msg = 'Error de comunicación';
                if (xhr && xhr.status) msg += ' (HTTP ' + xhr.status + ')';
                showAlert('danger', msg);
            }).always(function () {
                $btn.prop('disabled', false).html('<i class="icon-refresh"></i> Actualizar');
            });
        });
    });
})(window.jQuery);
</script>
{/block}