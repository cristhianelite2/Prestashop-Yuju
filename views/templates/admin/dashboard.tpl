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

    {* Actualizaciones desde GitHub (rama main) *}
    <div class="panel panel-default yuju-dashboard-panel" id="yuju-update-panel">
        <div class="panel-heading">
            <i class="icon-cloud-download"></i>
            Actualizaciones del módulo
            <span class="label {if isset($update_status.update_available) && $update_status.update_available}label-warning{else}label-success{/if}" id="yuju-update-badge">
                {if isset($update_status.update_available) && $update_status.update_available}Disponible{else}Al día{/if}
            </span>
        </div>
        <div class="panel-body">
            <div id="yuju-update-alert" class="alert alert-danger" style="{if isset($update_status.check_error) && $update_status.check_error}display:block;{else}display:none;{/if} margin-bottom:12px;">{if isset($update_status.check_error)}{$update_status.check_error|escape:'html':'UTF-8'}{/if}</div>
            <div class="row">
                <div class="col-md-8">
                    <p>
                        <strong>Versión instalada:</strong>
                        <span id="yuju-local-version">v{$module_version|escape:'html':'UTF-8'}</span>
                        &nbsp;|&nbsp;
                        <strong>Versión en main:</strong>
                        <span id="yuju-remote-version">v{if isset($update_status.remote_version)}{$update_status.remote_version|escape:'html':'UTF-8'}{else}{$module_version|escape:'html':'UTF-8'}{/if}</span>
                    </p>
                    <p id="yuju-update-message" class="{if isset($update_status.check_error) && $update_status.check_error}text-danger{elseif isset($update_status.update_available) && $update_status.update_available}text-warning{else}text-success{/if}">
                        {if isset($update_status.check_error) && $update_status.check_error}
                            No se pudo comprobar la rama main.
                        {elseif isset($update_status.update_available) && $update_status.update_available}
                            Hay cambios disponibles en GitHub. Puede obtenerlos desde este panel.
                        {else}
                            No se detectan cambios pendientes respecto de la rama main.
                        {/if}
                    </p>
                    <p class="text-muted" style="margin-bottom:8px;">
                        <small>
                            Última comprobación:
                            <span id="yuju-update-last-check">{if isset($update_status.last_check) && $update_status.last_check}{$update_status.last_check|escape:'html':'UTF-8'}{else}Nunca{/if}</span>
                            {if isset($update_status.remote_message) && $update_status.remote_message}
                                &nbsp;·&nbsp;<span id="yuju-update-remote-message">{$update_status.remote_message|escape:'html':'UTF-8'}</span>
                            {else}
                                &nbsp;·&nbsp;<span id="yuju-update-remote-message"></span>
                            {/if}
                        </small>
                    </p>
                    <p>
                        <a href="https://github.com/cristhianelite2/Prestashop-Yuju/commits/main" target="_blank" rel="noopener noreferrer">
                            <i class="icon-github"></i> Ver cambios de main en GitHub
                        </a>
                    </p>
                </div>
                <div class="col-md-4 text-right">
                    <button type="button" class="btn btn-default" id="yuju-check-update" style="margin-bottom:8px;">
                        <i class="icon-refresh"></i> Buscar actualizaciones
                    </button>
                    <br>
                    <button type="button" class="btn btn-warning" id="yuju-perform-update" {if !isset($update_status.update_available) || !$update_status.update_available}style="display:none;"{/if}>
                        <i class="icon-download"></i> Obtener actualizaciones
                    </button>
                </div>
            </div>
            <p class="help-block" style="margin-top:10px;">
                Descarga archivos del commit detectado en <code>main</code>. La configuración guardada en PrestaShop y los directorios locales <code>logs</code>, <code>cache</code> y <code>exports</code> se conservan.
            </p>
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

        function showUpdateAlert(type, message) {
            $('#yuju-update-alert')
                .removeClass('alert-success alert-danger alert-info alert-warning')
                .addClass('alert-' + type)
                .text(message || '')
                .show();
        }

        function applyUpdateStatus(data) {
            if (!data) return;

            $('#yuju-local-version').text('v' + (data.local_version || '—'));
            $('#yuju-remote-version').text('v' + (data.remote_version || data.local_version || '—'));
            $('#yuju-update-last-check').text(data.last_check || 'Nunca');
            $('#yuju-update-remote-message').text(data.remote_message || '');

            var $panel = $('#yuju-update-panel');
            var $badge = $('#yuju-update-badge');
            var $message = $('#yuju-update-message');
            var available = !!data.update_available;

            if (data.check_error) {
                $badge.removeClass('label-success label-warning').addClass('label-danger').text('Error');
                $panel.removeClass('panel-warning').addClass('panel-default');
                $message.removeClass('text-success text-warning').addClass('text-danger')
                    .text('No se pudo comprobar la rama main.');
                $('#yuju-perform-update').hide();
                showUpdateAlert('danger', data.check_error);
                return;
            }

            $('#yuju-update-alert').hide();
            if (available) {
                $badge.removeClass('label-success label-danger').addClass('label-warning').text('Disponible');
                $panel.removeClass('panel-default').addClass('panel-warning');
                $message.removeClass('text-success text-danger').addClass('text-warning')
                    .text('Hay cambios disponibles en GitHub. Puede obtenerlos desde este panel.');
                $('#yuju-perform-update').show();
            } else {
                $badge.removeClass('label-warning label-danger').addClass('label-success').text('Al día');
                $panel.removeClass('panel-warning').addClass('panel-default');
                $message.removeClass('text-warning text-danger').addClass('text-success')
                    .text('No se detectan cambios pendientes respecto de la rama main.');
                $('#yuju-perform-update').hide();
            }
        }

        $('#yuju-check-update').on('click', function () {
            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="icon-refresh icon-spin"></i> Comprobando…');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                dataType: 'json',
                timeout: 60000,
                data: { ajax: 1, action: 'checkUpdate', token: token }
            }).done(function (response) {
                if (response && response.success) {
                    applyUpdateStatus(response.data);
                    showUpdateAlert('success', response.message || 'Comprobación terminada.');
                } else {
                    showUpdateAlert('danger', response && response.message ? response.message : 'No se pudieron comprobar las actualizaciones.');
                }
            }).fail(function (xhr) {
                var message = 'Error de comunicación al consultar GitHub.';
                if (xhr && xhr.status) message += ' (HTTP ' + xhr.status + ')';
                showUpdateAlert('danger', message);
            }).always(function () {
                $btn.prop('disabled', false).html('<i class="icon-refresh"></i> Buscar actualizaciones');
            });
        });

        $('#yuju-perform-update').on('click', function () {
            if (!window.confirm('¿Obtener e instalar los archivos más recientes de la rama main? Se reemplazarán archivos del módulo. La configuración de PrestaShop y los directorios logs, cache y exports se conservarán.')) {
                return;
            }

            var $btn = $(this);
            var $checkBtn = $('#yuju-check-update');
            $btn.prop('disabled', true).html('<i class="icon-refresh icon-spin"></i> Obteniendo…');
            $checkBtn.prop('disabled', true);
            showUpdateAlert('info', 'Descargando y aplicando los archivos de GitHub. No cierre esta página.');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                dataType: 'json',
                timeout: 300000,
                data: { ajax: 1, action: 'performUpdate', token: token }
            }).done(function (response) {
                if (response && response.success) {
                    showUpdateAlert('success', response.message || 'Actualización completada.');
                    $('#yuju-update-message').text(response.message || 'Actualización completada.');
                    $btn.html('<i class="icon-check"></i> Actualización completada');
                    window.setTimeout(function () { window.location.reload(); }, 1400);
                } else {
                    showUpdateAlert('danger', response && response.message ? response.message : 'No se pudo aplicar la actualización.');
                    $btn.prop('disabled', false).html('<i class="icon-download"></i> Obtener actualizaciones');
                    $checkBtn.prop('disabled', false);
                }
            }).fail(function (xhr) {
                var message = 'Falló la descarga o instalación de la actualización.';
                if (xhr && xhr.status) message += ' (HTTP ' + xhr.status + ')';
                showUpdateAlert('danger', message);
                $btn.prop('disabled', false).html('<i class="icon-download"></i> Obtener actualizaciones');
                $checkBtn.prop('disabled', false);
            });
        });

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