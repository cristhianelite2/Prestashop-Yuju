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
<div id="yuju-alerts"></div>
<div class="panel">
    <div class="panel-heading">
        <i class="icon-dashboard"></i>
        Panel de Control de Integración Yuju
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-12">
                <h3>Bienvenido a la Integración Yuju</h3>
                <p>Este módulo le permite sincronizar su tienda PrestaShop con la plataforma Yuju.</p>

                <!-- Actualización del módulo -->
                <div class="panel {if isset($update_status.update_available) && $update_status.update_available}panel-warning{else}panel-default{/if}" id="yuju-update-panel">
                    <div class="panel-heading">
                        <h4>
                            <i class="icon-cloud-download"></i>
                            Actualizaciones del módulo
                            {if isset($update_status.update_available) && $update_status.update_available}
                                <span class="label label-warning" id="yuju-update-badge">Disponible</span>
                            {else}
                                <span class="label label-success" id="yuju-update-badge">Al día</span>
                            {/if}
                        </h4>
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-8">
                                <p>
                                    <strong>Versión instalada:</strong>
                                    <span id="yuju-local-version">v{$module_version|escape:'html':'UTF-8'}</span>
                                    &nbsp;|&nbsp;
                                    <strong>Remota ({if isset($update_status.branch)}{$update_status.branch|escape:'html':'UTF-8'}{else}main{/if}):</strong>
                                    <span id="yuju-remote-version">v{if isset($update_status.remote_version)}{$update_status.remote_version|escape:'html':'UTF-8'}{else}{$module_version|escape:'html':'UTF-8'}{/if}</span>
                                </p>
                                <p class="text-muted" style="margin-bottom: 8px;">
                                    <small>
                                        Última comprobación:
                                        <span id="yuju-last-check">
                                            {if isset($update_status.last_check) && $update_status.last_check}
                                                {$update_status.last_check|escape:'html':'UTF-8'}
                                            {else}
                                                Nunca
                                            {/if}
                                        </span>
                                        {if isset($update_status.remote_message) && $update_status.remote_message}
                                            &nbsp;·&nbsp;
                                            <span id="yuju-remote-message">{$update_status.remote_message|escape:'html':'UTF-8'}</span>
                                        {else}
                                            <span id="yuju-remote-message"></span>
                                        {/if}
                                    </small>
                                </p>
                                <p id="yuju-update-message" class="{if isset($update_status.update_available) && $update_status.update_available}text-warning{else}text-success{/if}">
                                    {if isset($update_status.check_error) && $update_status.check_error}
                                        <span class="text-danger">{$update_status.check_error|escape:'html':'UTF-8'}</span>
                                    {elseif isset($update_status.update_available) && $update_status.update_available}
                                        Hay una nueva versión en GitHub. Puede actualizar el módulo con un clic.
                                    {else}
                                        El módulo está actualizado con la rama principal del repositorio.
                                    {/if}
                                </p>
                                {if isset($update_status.repo_url)}
                                    <p>
                                        <a href="{$update_status.repo_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener noreferrer">
                                            <i class="icon-github"></i> Ver repositorio en GitHub
                                        </a>
                                    </p>
                                {/if}
                            </div>
                            <div class="col-md-4 text-right">
                                <button type="button" class="btn btn-default" id="yuju-check-update" style="margin-bottom: 8px;">
                                    <i class="icon-refresh"></i> Buscar actualizaciones
                                </button>
                                <br>
                                <button type="button"
                                        class="btn btn-warning"
                                        id="yuju-perform-update"
                                        {if !isset($update_status.update_available) || !$update_status.update_available}style="display:none;"{/if}>
                                    <i class="icon-download"></i> Actualizar módulo
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Estadísticas de la última semana -->
                <div class="row" style="margin-bottom: 20px;">
                    <div class="col-md-3">
                        <div class="panel panel-danger">
                            <div class="panel-heading">
                                <h4><i class="icon-exclamation-triangle"></i> Errores (Última Semana)</h4>
                            </div>
                            <div class="panel-body text-center">
                                <h2 class="text-danger">{$dashboard_stats.errors_last_week|escape:'html':'UTF-8'}</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="panel panel-warning">
                            <div class="panel-heading">
                                <h4><i class="icon-warning"></i> Advertencias (Última Semana)</h4>
                            </div>
                            <div class="panel-body text-center">
                                <h2 class="text-warning">{$dashboard_stats.warnings_last_week|escape:'html':'UTF-8'}</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="panel panel-info">
                            <div class="panel-heading">
                                <h4><i class="icon-calendar"></i> Última Sincronización</h4>
                            </div>
                            <div class="panel-body text-center">
                                {if $last_sync_date}
                                    <small>{$last_sync_date|date_format:"%d/%m/%Y %H:%M"}</small>
                                {else}
                                    <small class="text-muted">Nunca</small>
                                {/if}
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="panel panel-success">
                            <div class="panel-heading">
                                <h4><i class="icon-info"></i> Estado del Módulo</h4>
                            </div>
                            <div class="panel-body text-center">
                                <span class="label label-success">Activo</span><br>
                                <small>v{$module_version|escape:'html':'UTF-8'}</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Elementos sincronizados en la última semana -->
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4><i class="icon-sync"></i> Elementos Sincronizados (Última Semana)</h4>
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-4 text-center">
                                <h3 class="text-primary">{$recent_syncs.products|escape:'html':'UTF-8'}</h3>
                                <p><strong>Productos</strong></p>
                            </div>
                            <div class="col-md-4 text-center">
                                <h3 class="text-info">{$recent_syncs.categories|escape:'html':'UTF-8'}</h3>
                                <p><strong>Categorías</strong></p>
                            </div>
                            <div class="col-md-4 text-center">
                                <h3 class="text-success">{$recent_syncs.attributes|escape:'html':'UTF-8'}</h3>
                                <p><strong>Atributos</strong></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h4>Configuración</h4>
                            </div>
                            <div class="panel-body">
                                <p>Configure sus ajustes de API de Yuju y opciones de sincronización.</p>
                                <a href="{$link->getAdminLink('AdminYujuConfiguration')|escape:'html':'UTF-8'}" class="btn btn-primary">
                                    Configurar
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h4>Sincronización</h4>
                            </div>
                            <div class="panel-body">
                                <p>Gestione la sincronización de productos, categorías y pedidos.</p>
                                <a href="{$link->getAdminLink('AdminYujuSync')|escape:'html':'UTF-8'}" class="btn btn-primary">
                                    Sincronizar
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h4>Registros</h4>
                            </div>
                            <div class="panel-body">
                                <p>Vea los registros de sincronización y solucione problemas.</p>
                                <a href="{$link->getAdminLink('AdminYujuLogs')|escape:'html':'UTF-8'}" class="btn btn-primary">
                                    Ver Registros
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
(function ($) {
    var ajaxUrl = '{$ajax_url|escape:'javascript':'UTF-8'}';
    var token = '{$admin_yuju_token|escape:'javascript':'UTF-8'}';

    function applyUpdateStatus(data) {
        if (!data) {
            return;
        }

        $('#yuju-local-version').text('v' + (data.local_version || ''));
        $('#yuju-remote-version').text('v' + (data.remote_version || ''));
        $('#yuju-last-check').text(data.last_check || 'Nunca');

        if (data.remote_message) {
            $('#yuju-remote-message').text(' · ' + data.remote_message);
        } else {
            $('#yuju-remote-message').text('');
        }

        var $badge = $('#yuju-update-badge');
        var $panel = $('#yuju-update-panel');
        var $msg = $('#yuju-update-message');
        var $btnUpdate = $('#yuju-perform-update');

        if (data.update_available) {
            $badge.removeClass('label-success').addClass('label-warning').text('Disponible');
            $panel.removeClass('panel-default').addClass('panel-warning');
            $msg.removeClass('text-success').addClass('text-warning')
                .text('Hay una nueva versión en GitHub. Puede actualizar el módulo con un clic.');
            $btnUpdate.show();
        } else {
            $badge.removeClass('label-warning').addClass('label-success').text('Al día');
            $panel.removeClass('panel-warning').addClass('panel-default');
            $msg.removeClass('text-warning').addClass('text-success')
                .text('El módulo está actualizado con la rama principal del repositorio.');
            $btnUpdate.hide();
        }
    }

    $('#yuju-check-update').on('click', function () {
        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="icon-refresh icon-spin"></i> Comprobando...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                ajax: 1,
                action: 'CheckUpdate',
                token: token
            },
            success: function (response) {
                if (response.success) {
                    applyUpdateStatus(response.data);
                    if (window.YujuAdmin && typeof YujuAdmin.showAlert === 'function') {
                        YujuAdmin.showAlert(response.message, 'success');
                    } else {
                        alert(response.message);
                    }
                } else {
                    $('#yuju-update-message').removeClass('text-success text-warning').addClass('text-danger')
                        .text(response.message || 'Error al comprobar actualizaciones');
                    if (window.YujuAdmin && typeof YujuAdmin.showAlert === 'function') {
                        YujuAdmin.showAlert(response.message || 'Error', 'error');
                    } else {
                        alert(response.message || 'Error');
                    }
                }
            },
            error: function () {
                alert('No se pudo comprobar actualizaciones. Intente de nuevo.');
            },
            complete: function () {
                $btn.prop('disabled', false).html('<i class="icon-refresh"></i> Buscar actualizaciones');
            }
        });
    });

    $('#yuju-perform-update').on('click', function () {
        if (!confirm('¿Desea actualizar el módulo desde GitHub (rama main)? Se reemplazarán los archivos del módulo. La configuración y los logs se conservarán.')) {
            return;
        }

        var $btn = $(this);
        var $checkBtn = $('#yuju-check-update');
        $btn.prop('disabled', true).html('<i class="icon-refresh icon-spin"></i> Actualizando...');
        $checkBtn.prop('disabled', true);

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'json',
            timeout: 180000,
            data: {
                ajax: 1,
                action: 'PerformUpdate',
                token: token
            },
            success: function (response) {
                if (response.success) {
                    if (window.YujuAdmin && typeof YujuAdmin.showAlert === 'function') {
                        YujuAdmin.showAlert(response.message, 'success');
                    } else {
                        alert(response.message);
                    }
                    window.location.reload();
                } else {
                    alert(response.message || 'Error al actualizar el módulo');
                    $btn.prop('disabled', false).html('<i class="icon-download"></i> Actualizar módulo');
                    $checkBtn.prop('disabled', false);
                }
            },
            error: function () {
                alert('Error de conexión durante la actualización. Verifique permisos del servidor y vuelva a intentarlo.');
                $btn.prop('disabled', false).html('<i class="icon-download"></i> Actualizar módulo');
                $checkBtn.prop('disabled', false);
            }
        });
    });
})(jQuery);
</script>
{/block}
