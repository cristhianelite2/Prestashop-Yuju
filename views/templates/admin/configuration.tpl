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
<div class="panel">
    <div class="panel-heading">
        <i class="icon-cogs"></i>
        Configuración de Integración Yuju
    </div>
    
    <div class="panel-body">
        {if isset($oauth_status) && $oauth_status.is_connected}
            <div class="alert alert-success">
                <i class="icon-check"></i>
                Conectado exitosamente a la API de Yuju
                <br>
                <small>Conectado como: {$oauth_status.user_info.name|default:'Desconocido'|escape:'html':'UTF-8'}</small>
            </div>
        {else}
            <div class="alert alert-warning">
                <i class="icon-warning"></i>
                No conectado a la API de Yuju. Por favor configure sus credenciales y autorice la conexión.
            </div>

            <div class="panel panel-default yuju-config-collapsible" style="margin-bottom: 20px;">
                <div class="panel-heading yuju-config-collapsible__head collapsed" role="tab" id="yujuConnectionGuideHeading"
                     data-toggle="collapse" href="#yujuConnectionGuide" aria-expanded="false" aria-controls="yujuConnectionGuide"
                     style="cursor:pointer;">
                    <div class="yuju-config-collapsible__row">
                        <h4 class="panel-title" style="margin: 0;">
                            <i class="icon-book"></i> Ver tutorial de conexión Yuju + PrestaShop
                        </h4>
                        <span class="yuju-config-collapsible__chev" aria-hidden="true"><i class="icon-chevron-down"></i></span>
                    </div>
                </div>
                <div id="yujuConnectionGuide" class="panel-collapse collapse" role="tabpanel" aria-labelledby="yujuConnectionGuideHeading">
                    <div class="panel-body">
                        <p style="margin-bottom: 12px;"><strong>Paso a paso para conectar correctamente:</strong></p>
                        <ol style="padding-left: 18px; margin-bottom: 0;">
                            <li style="margin-bottom: 8px;"><strong>En Yuju:</strong> Ingrese al panel de desarrollador y cree una aplicación nueva.</li>
                            <li style="margin-bottom: 8px;"><strong>En Yuju:</strong> Complete los campos de la app usando los valores sugeridos en esta pantalla (Nombre, URL del sitio, URL de términos, URL de redirección y webhook).</li>
                            <li style="margin-bottom: 8px;"><strong>En Yuju:</strong> Guarde la app y copie el <strong>Client ID</strong> y el <strong>Client Secret</strong>.</li>
                            <li style="margin-bottom: 8px;"><strong>En PrestaShop (este módulo):</strong> Pegue el <strong>ID de Cliente</strong> y el <strong>Secreto de Cliente</strong>.</li>
                            <li style="margin-bottom: 8px;"><strong>En PrestaShop:</strong> Pulse <strong>Guardar</strong> para persistir credenciales en la base de datos.</li>
                            <li style="margin-bottom: 8px;"><strong>Conexión OAuth:</strong> Después de guardar, pulse el botón de autorización OAuth y acepte permisos en Yuju.</li>
                            <li style="margin-bottom: 8px;"><strong>Verificación:</strong> Al finalizar, el módulo mostrará estado conectado y habilitará las secciones de pruebas de conectividad/productos.</li>
                        </ol>
                    </div>
                </div>
            </div>
        {/if}
        
        {if isset($api_test_result)}
            {if $api_test_result.success}
                <div class="alert alert-success">
                    <i class="icon-check"></i>
                    Prueba de conexión API exitosa
                </div>
            {else}
                <div class="alert alert-danger">
                    <i class="icon-remove"></i>
                    Prueba de conexión API falló: {$api_test_result.error|escape:'html':'UTF-8'}
                </div>
            {/if}
        {/if}
        
        <form id="configuration_form" class="defaultForm form-horizontal" action="{$current_index|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}" method="post" enctype="multipart/form-data">

            <div class="yuju-config-toolbar">
                <button type="button" class="btn btn-default btn-sm" id="yuju-config-expand-all">
                    <i class="icon-plus-sign"></i> Expandir todas
                </button>
                <button type="button" class="btn btn-default btn-sm" id="yuju-config-collapse-all">
                    <i class="icon-minus-sign"></i> Colapsar todas
                </button>
            </div>
            
            {* Configuracion para Crear App Yuju *}
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="icon-link"></i>
                        Datos para Crear Aplicacion en Yuju
                    </h3>
                </div>
                <div class="panel-body">
                    <div class="alert alert-info">
                        <i class="icon-info-circle"></i>
                        Complete el formulario "Crear aplicacion" de Yuju con estos valores sugeridos. Todos los campos se pueden copiar con un clic.
                    </div>

                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Nombre de la app
                        </label>
                        <div class="col-lg-9">
                            <div class="input-group">
                                <input type="text" class="form-control" value="{$yuju_app_setup.app_name|escape:'html':'UTF-8'}" readonly id="app_name">
                                <span class="input-group-btn">
                                    <button class="btn btn-default yuju-copy-button" type="button" data-copy-text="{$yuju_app_setup.app_name|escape:'html':'UTF-8'}">
                                        <i class="icon-copy"></i> Copiar
                                    </button>
                                </span>
                            </div>
                            <p class="help-block">Nombre sugerido para identificar esta integracion en Yuju.</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            URL del sitio
                        </label>
                        <div class="col-lg-9">
                            <div class="input-group">
                                <input type="text" class="form-control" value="{$yuju_app_setup.site_url|escape:'html':'UTF-8'}" readonly id="site_url">
                                <span class="input-group-btn">
                                    <button class="btn btn-default yuju-copy-button" type="button" data-copy-text="{$yuju_app_setup.site_url|escape:'html':'UTF-8'}">
                                        <i class="icon-copy"></i> Copiar
                                    </button>
                                </span>
                            </div>
                            <p class="help-block">URL principal de su tienda PrestaShop.</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Email de contacto
                        </label>
                        <div class="col-lg-9">
                            <div class="input-group">
                                <input type="text" class="form-control" value="{$yuju_app_setup.contact_email|escape:'html':'UTF-8'}" readonly id="contact_email">
                                <span class="input-group-btn">
                                    <button class="btn btn-default yuju-copy-button" type="button" data-copy-text="{$yuju_app_setup.contact_email|escape:'html':'UTF-8'}">
                                        <i class="icon-copy"></i> Copiar
                                    </button>
                                </span>
                            </div>
                            <p class="help-block">Correo de contacto tecnico para la aplicacion.</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Descripcion
                        </label>
                        <div class="col-lg-9">
                            <div class="input-group">
                                <input type="text" class="form-control" value="{$yuju_app_setup.description|escape:'html':'UTF-8'}" readonly id="app_description">
                                <span class="input-group-btn">
                                    <button class="btn btn-default yuju-copy-button" type="button" data-copy-text="{$yuju_app_setup.description|escape:'html':'UTF-8'}">
                                        <i class="icon-copy"></i> Copiar
                                    </button>
                                </span>
                            </div>
                            <p class="help-block">Texto sugerido para describir la integracion.</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            URL de Términos y Condiciones
                        </label>
                        <div class="col-lg-9">
                            <div class="input-group">
                                <input type="text" class="form-control" value="{$yuju_urls.terms_conditions|escape:'html':'UTF-8'}" readonly id="terms_url">
                                <span class="input-group-btn">
                                    <button class="btn btn-default yuju-copy-button" type="button" data-copy-text="{$yuju_urls.terms_conditions|escape:'html':'UTF-8'}">
                                        <i class="icon-copy"></i> Copiar
                                    </button>
                                </span>
                            </div>
                            <p class="help-block">URL a la página de términos y condiciones del módulo (Úsela si no tiene una propia para el registro de aplicaciones Yuju)</p>
                        </div>
                    </div>
                    
                    {if $yuju_urls.auth_url}
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            URL de Autorizacion OAuth (informativa)
                        </label>
                        <div class="col-lg-9">
                            <div class="input-group">
                                <input type="text" class="form-control" value="{$yuju_urls.auth_url|escape:'html':'UTF-8'}" readonly id="auth_url">
                                <span class="input-group-btn">
                                    <button class="btn btn-default yuju-copy-button" type="button" data-copy-text="{$yuju_urls.auth_url|escape:'html':'UTF-8'}">
                                        <i class="icon-copy"></i> Copiar
                                    </button>
                                </span>
                            </div>
                            <p class="help-block">URL generada por el modulo para iniciar OAuth. No siempre se usa como campo directo al crear la app.</p>
                        </div>
                    </div>
                    {/if}
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            URL de autenticacion / URI de redireccion
                        </label>
                        <div class="col-lg-9">
                            <div class="input-group">
                                <input type="text" class="form-control" value="{$yuju_app_setup.app_auth_url|escape:'html':'UTF-8'}" readonly id="redirect_uri">
                                <span class="input-group-btn">
                                    <button class="btn btn-default yuju-copy-button" type="button" data-copy-text="{$yuju_app_setup.app_auth_url|escape:'html':'UTF-8'}">
                                        <i class="icon-copy"></i> Copiar
                                    </button>
                                </span>
                            </div>
                            <p class="help-block">Use este valor en "URL de autenticacion" y tambien en "URI/URL de redireccion" cuando Yuju lo solicite.</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            URL de Webhook
                        </label>
                        <div class="col-lg-9">
                            <div class="input-group">
                                <input type="text" class="form-control" value="{$yuju_urls.webhook_url|escape:'html':'UTF-8'}" readonly id="webhook_url">
                                <span class="input-group-btn">
                                    <button class="btn btn-default yuju-copy-button" type="button" data-copy-text="{$yuju_urls.webhook_url|escape:'html':'UTF-8'}">
                                        <i class="icon-copy"></i> Copiar
                                    </button>
                                </span>
                            </div>
                            <p class="help-block">URL para recibir webhooks de Yuju (configure esto en su aplicación Yuju)</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            URLs permitidas para autenticacion
                        </label>
                        <div class="col-lg-9">
                            <div class="input-group">
                                <input type="text" class="form-control" value="{if isset($yuju_app_setup.allowed_redirection_urls)}{$yuju_app_setup.allowed_redirection_urls|escape:'html':'UTF-8'}{/if}" readonly id="allowed_domains">
                                <span class="input-group-btn">
                                    <button class="btn btn-default yuju-copy-button" type="button" data-copy-text="{if isset($yuju_app_setup.allowed_redirection_urls)}{$yuju_app_setup.allowed_redirection_urls|escape:'html':'UTF-8'}{/if}">
                                        <i class="icon-copy"></i> Copiar
                                    </button>
                                </span>
                            </div>
                            <p class="help-block">Pegue esta lista en el campo "URLs permitidas para autenticacion". Incluye dominio y callback.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            {* API Configuration Section *}
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="icon-cloud"></i>
                        Configuración de API
                    </h3>
                </div>
                <div class="panel-body">
                    
                    {* Campo de entorno oculto, siempre en producción *}
                    <input type="hidden" name="YUJU_ENVIRONMENT" value="production">
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Configuración CRON
                        </label>
                        <div class="col-lg-9">
                            <div class="input-group" style="margin-bottom:8px;">
                                <input type="text" class="form-control" value="*/5 * * * * php {$smarty.server.DOCUMENT_ROOT}/modules/prestashopyuju/cron/cron.php" readonly>
                                <span class="input-group-btn">
                                    <button class="btn btn-default yuju-copy-button" type="button" data-copy-text="*/5 * * * * php {$smarty.server.DOCUMENT_ROOT}/modules/prestashopyuju/cron/cron.php">
                                        <i class="icon-copy"></i> Copiar
                                    </button>
                                </span>
                            </div>
                            <div class="input-group">
                                <input type="text" class="form-control" value="*/2 * * * * php {$smarty.server.DOCUMENT_ROOT}/modules/prestashopyuju/cron/process_queue.php" readonly>
                                <span class="input-group-btn">
                                    <button class="btn btn-default yuju-copy-button" type="button" data-copy-text="*/2 * * * * php {$smarty.server.DOCUMENT_ROOT}/modules/prestashopyuju/cron/process_queue.php">
                                        <i class="icon-copy"></i> Copiar
                                    </button>
                                </span>
                            </div>
                            <p class="help-block">
                                <code>cron.php</code> cada 5 min (tareas generales).
                                <code>process_queue.php</code> cada 1–2 min (cola de productos, respeta YUJU_BATCH_SIZE).
                                «Ejecutar» en el gestor lanza en segundo plano y no bloquea el backoffice.
                            </p>
                            {if isset($oauth_status) && $oauth_status.configured && $oauth_status.has_token && $oauth_status.is_connected && isset($config.YUJU_CLIENT_ID) && $config.YUJU_CLIENT_ID|trim != '' && isset($config.YUJU_CLIENT_SECRET) && $config.YUJU_CLIENT_SECRET|trim != ''}
                                <button type="button" id="yuju-open-cron-manager" class="btn btn-info btn-sm" style="margin-top:8px;" onclick="return openCronManagerModal();">
                                    <i class="icon-time"></i> Administrar crons
                                </button>
                            {/if}
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3 required">
                            ID de Cliente
                        </label>
                        <div class="col-lg-9">
                            <input type="text" name="YUJU_CLIENT_ID" value="{$config.YUJU_CLIENT_ID|escape:'html':'UTF-8'}" class="form-control" required autocomplete="off">
                            <p class="help-block">Su ID de Cliente de la aplicación Yuju</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3 required">
                            Secreto de Cliente
                        </label>
                        <div class="col-lg-9">
                            <input type="text" name="YUJU_CLIENT_SECRET" value="{$config.YUJU_CLIENT_SECRET|escape:'html':'UTF-8'}" class="form-control" required autocomplete="off">
                            <p class="help-block">Su Secreto de Cliente de la aplicación Yuju</p>
                        </div>
                    </div>
                    

                    {if (isset($yuju_hooks_status) && is_array($yuju_hooks_status) && count($yuju_hooks_status) > 0) || (isset($yuju_tables_status) && is_array($yuju_tables_status) && count($yuju_tables_status) > 0)}
                        <div class="form-group">
                            <label class="control-label col-lg-3">
                                Diagnóstico del módulo
                            </label>
                            <div class="col-lg-9">
                                <style>
                                    .yuju-diag { border: 1px solid #e1e5ea; border-radius: 6px; overflow: hidden; background: #fff; }
                                    .yuju-diag-item { border-bottom: 1px solid #eef0f3; }
                                    .yuju-diag-item:last-child { border-bottom: 0; }
                                    .yuju-diag-head { display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; cursor: pointer; user-select: none; line-height: 1.4; background: #fff; transition: background-color 0.15s ease; }
                                    .yuju-diag-head:hover { background: #f7f9fc; }
                                    .yuju-diag-head .yuju-diag-left { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; color: #2c3e50; font-size: 13px; }
                                    .yuju-diag-head .yuju-diag-left i.icon-main { color: #7b8a9a; font-size: 14px; }
                                    .yuju-diag-head .yuju-diag-right { display: inline-flex; align-items: center; gap: 8px; }
                                    .yuju-diag-pill { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; line-height: 1.4; }
                                    .yuju-diag-pill.ok { background: #e8f5e9; color: #2e7d32; }
                                    .yuju-diag-pill.warn { background: #fff3cd; color: #8a6d3b; }
                                    .yuju-diag-pill i { font-size: 10px; }
                                    .yuju-diag-caret { color: #9aa5b1; font-size: 11px; transition: transform 0.2s ease; }
                                    .yuju-diag-head[aria-expanded="true"] .yuju-diag-caret { transform: rotate(180deg); }
                                    .yuju-diag-body { padding: 10px 12px 12px; background: #fafbfc; border-top: 1px solid #eef0f3; }
                                    #yuju-hooks-panel .yuju-hooks-toolbar,
                                    #yuju-tables-panel .yuju-tables-toolbar { display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
                                    #yuju-hooks-panel .yuju-hooks-summary,
                                    #yuju-tables-panel .yuju-tables-summary { display: none; }
                                    #yuju-hooks-panel .yuju-hooks-table,
                                    #yuju-tables-panel .yuju-tables-table { margin-bottom: 6px; font-size: 12px; }
                                    #yuju-hooks-panel .yuju-hooks-table > thead > tr > th,
                                    #yuju-hooks-panel .yuju-hooks-table > tbody > tr > td,
                                    #yuju-tables-panel .yuju-tables-table > thead > tr > th,
                                    #yuju-tables-panel .yuju-tables-table > tbody > tr > td { padding: 5px 8px; vertical-align: middle; }
                                    #yuju-hooks-panel .yuju-hooks-table tbody tr.warning,
                                    #yuju-tables-panel .yuju-tables-table tbody tr.warning { background-color: #fcf8e3; }
                                    #yuju-hooks-panel .yuju-hooks-table code,
                                    #yuju-tables-panel .yuju-tables-table code { background: transparent; padding: 0; }
                                    #yuju-hooks-panel .table-responsive,
                                    #yuju-tables-panel .table-responsive { margin-top: 0 !important; }
                                    .yuju-diag-body .help-block { margin: 4px 0 0; font-size: 11px; }
                                </style>

                                <div class="yuju-diag" id="yuju-system-accordion" role="tablist">

                                    {if isset($yuju_hooks_status) && is_array($yuju_hooks_status) && count($yuju_hooks_status) > 0}
                                        <div class="yuju-diag-item">
                                            <div class="yuju-diag-head" role="tab" id="yuju-acc-hooks-heading"
                                                 data-toggle="collapse" data-target="#yuju-acc-hooks-body"
                                                 aria-expanded="{if !$yuju_hooks_all_active}true{else}false{/if}" aria-controls="yuju-acc-hooks-body">
                                                <span class="yuju-diag-left">
                                                    <i class="icon-cogs icon-main"></i>
                                                    Hooks de PrestaShop
                                                </span>
                                                <span class="yuju-diag-right yuju-acc-status">
                                                    {if $yuju_hooks_all_active}
                                                        <span class="yuju-diag-pill ok"><i class="icon-check"></i> Todos activos</span>
                                                    {else}
                                                        <span class="yuju-diag-pill warn"><i class="icon-warning"></i> Hay inactivos</span>
                                                    {/if}
                                                    <i class="icon-chevron-down yuju-diag-caret"></i>
                                                </span>
                                            </div>
                                            <div id="yuju-acc-hooks-body" class="collapse {if !$yuju_hooks_all_active}in{/if}" role="tabpanel" aria-labelledby="yuju-acc-hooks-heading">
                                                <div class="yuju-diag-body">
                                                    <div id="yuju-hooks-panel" class="yuju-hooks-panel"
                                                         data-ajax-url="{$ajax_url|escape:'html':'UTF-8'}"
                                                         data-token="{$token|escape:'html':'UTF-8'}">
                                                        <div class="yuju-hooks-toolbar">
                                                            <span class="yuju-hooks-summary">
                                                                {if $yuju_hooks_all_active}
                                                                    <span class="label label-success"><i class="icon-check"></i> Todos los hooks activos</span>
                                                                {else}
                                                                    <span class="label label-warning"><i class="icon-warning"></i> Hay hooks inactivos</span>
                                                                {/if}
                                                            </span>
                                                            <span class="yuju-hooks-actions">
                                                                <button type="button" class="btn btn-default btn-sm" id="yuju-hooks-refresh">
                                                                    <i class="icon-refresh"></i> Recargar estado
                                                                </button>
                                                                <button type="button" class="btn btn-primary btn-sm" id="yuju-hooks-activate-all" {if $yuju_hooks_all_active}style="display:none;"{/if}>
                                                                    <i class="icon-bolt"></i> Activar todos los inactivos
                                                                </button>
                                                            </span>
                                                        </div>
                                                        <div class="table-responsive" style="margin-top:10px;">
                                                            <table class="table table-striped table-condensed yuju-hooks-table" id="yuju-hooks-table">
                                                                <thead>
                                                                    <tr>
                                                                        <th style="width: 35%;">Hook</th>
                                                                        <th>Descripción</th>
                                                                        <th class="text-center" style="width: 110px;">Estado</th>
                                                                        <th class="text-center" style="width: 140px;">Acción</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    {foreach from=$yuju_hooks_status item=hook_row}
                                                                        <tr data-hook-name="{$hook_row.name|escape:'html':'UTF-8'}" class="{if !$hook_row.registered}warning{/if}">
                                                                            <td>
                                                                                <strong>{$hook_row.label|escape:'html':'UTF-8'}</strong>
                                                                                {if $hook_row.critical}
                                                                                    <span class="label label-danger" style="margin-left:6px;" title="Hook crítico para la integración">Crítico</span>
                                                                                {/if}
                                                                                <br>
                                                                                <code class="text-muted" style="font-size:11px;">{$hook_row.name|escape:'html':'UTF-8'}</code>
                                                                            </td>
                                                                            <td class="text-muted">
                                                                                {$hook_row.description|escape:'html':'UTF-8'}
                                                                            </td>
                                                                            <td class="text-center yuju-hook-status-cell">
                                                                                {if $hook_row.registered}
                                                                                    <span class="label label-success"><i class="icon-check"></i> Activo</span>
                                                                                {else}
                                                                                    <span class="label label-default"><i class="icon-remove"></i> Inactivo</span>
                                                                                {/if}
                                                                            </td>
                                                                            <td class="text-center yuju-hook-action-cell">
                                                                                {if !$hook_row.registered}
                                                                                    <button type="button" class="btn btn-success btn-xs yuju-hook-activate-btn" data-hook="{$hook_row.name|escape:'html':'UTF-8'}">
                                                                                        <i class="icon-power-off"></i> Activar
                                                                                    </button>
                                                                                {else}
                                                                                    <span class="text-muted small">—</span>
                                                                                {/if}
                                                                            </td>
                                                                        </tr>
                                                                    {/foreach}
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                        <p class="help-block" style="margin-top:8px;">
                                                            Estos hooks permiten que PrestaShop notifique al módulo Yuju cuando cambian productos, stock, pedidos o categorías. Si alguno está inactivo, los cambios no se enviarán automáticamente a Yuju.
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    {/if}

                                    {if isset($yuju_tables_status) && is_array($yuju_tables_status) && count($yuju_tables_status) > 0}
                                        <div class="yuju-diag-item">
                                            <div class="yuju-diag-head" role="tab" id="yuju-acc-tables-heading"
                                                 data-toggle="collapse" data-target="#yuju-acc-tables-body"
                                                 aria-expanded="{if !$yuju_tables_all_present}true{else}false{/if}" aria-controls="yuju-acc-tables-body">
                                                <span class="yuju-diag-left">
                                                    <i class="icon-database icon-main"></i>
                                                    Tablas del módulo
                                                </span>
                                                <span class="yuju-diag-right yuju-acc-status">
                                                    {if $yuju_tables_all_present}
                                                        <span class="yuju-diag-pill ok"><i class="icon-check"></i> Todas presentes</span>
                                                    {else}
                                                        <span class="yuju-diag-pill warn"><i class="icon-warning"></i> Faltan tablas</span>
                                                    {/if}
                                                    <i class="icon-chevron-down yuju-diag-caret"></i>
                                                </span>
                                            </div>
                                            <div id="yuju-acc-tables-body" class="collapse {if !$yuju_tables_all_present}in{/if}" role="tabpanel" aria-labelledby="yuju-acc-tables-heading">
                                                <div class="yuju-diag-body">
                                                    <div id="yuju-tables-panel" class="yuju-tables-panel"
                                                         data-ajax-url="{$ajax_url|escape:'html':'UTF-8'}"
                                                         data-token="{$token|escape:'html':'UTF-8'}">
                                                        <div class="yuju-tables-toolbar">
                                                            <span class="yuju-tables-summary">
                                                                {if $yuju_tables_all_present}
                                                                    <span class="label label-success"><i class="icon-check"></i> Todas las tablas presentes</span>
                                                                {else}
                                                                    <span class="label label-warning"><i class="icon-warning"></i> Faltan tablas requeridas</span>
                                                                {/if}
                                                            </span>
                                                            <span class="yuju-tables-actions">
                                                                <button type="button" class="btn btn-info btn-sm" id="yuju-tables-review-schema">
                                                                    <i class="icon-search"></i> Revisar las tablas
                                                                </button>
                                                                <button type="button" class="btn btn-default btn-sm" id="yuju-tables-refresh">
                                                                    <i class="icon-refresh"></i> Recargar estado
                                                                </button>
                                                                <button type="button" class="btn btn-primary btn-sm" id="yuju-tables-create-all" {if $yuju_tables_all_present}style="display:none;"{/if}>
                                                                    <i class="icon-magic"></i> Crear tablas faltantes
                                                                </button>
                                                            </span>
                                                        </div>
                                                        <div class="table-responsive" style="margin-top:10px;">
                                                            <table class="table table-striped table-condensed yuju-tables-table" id="yuju-tables-table">
                                                                <thead>
                                                                    <tr>
                                                                        <th style="width: 35%;">Tabla</th>
                                                                        <th>Descripción</th>
                                                                        <th class="text-center" style="width: 110px;">Estado</th>
                                                                        <th class="text-center" style="width: 140px;">Acción</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    {foreach from=$yuju_tables_status item=table_row}
                                                                        <tr data-table-name="{$table_row.name|escape:'html':'UTF-8'}" class="{if !$table_row.exists}warning{/if}">
                                                                            <td>
                                                                                <strong>{$table_row.label|escape:'html':'UTF-8'}</strong>
                                                                                {if $table_row.critical}
                                                                                    <span class="label label-danger" style="margin-left:6px;" title="Tabla crítica para la integración">Crítica</span>
                                                                                {/if}
                                                                                <br>
                                                                                <code class="text-muted" style="font-size:11px;">{$table_row.full_name|escape:'html':'UTF-8'}</code>
                                                                            </td>
                                                                            <td class="text-muted">
                                                                                {$table_row.description|escape:'html':'UTF-8'}
                                                                            </td>
                                                                            <td class="text-center yuju-table-status-cell">
                                                                                {if $table_row.exists}
                                                                                    <span class="label label-success"><i class="icon-check"></i> Existe</span>
                                                                                {else}
                                                                                    <span class="label label-default"><i class="icon-remove"></i> No existe</span>
                                                                                {/if}
                                                                            </td>
                                                                            <td class="text-center yuju-table-action-cell">
                                                                                {if !$table_row.exists}
                                                                                    <button type="button" class="btn btn-success btn-xs yuju-table-create-btn" data-table="{$table_row.name|escape:'html':'UTF-8'}">
                                                                                        <i class="icon-plus"></i> Crear
                                                                                    </button>
                                                                                {else}
                                                                                    <span class="text-muted small">—</span>
                                                                                {/if}
                                                                            </td>
                                                                        </tr>
                                                                    {/foreach}
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                        <p class="help-block" style="margin-top:8px;">
                                                            Use <strong>Revisar las tablas</strong> para comparar el esquema instalado con el del módulo (crear tablas o columnas faltantes con progreso visual).
                                                            También puede usar <strong>Crear</strong> en cada fila o <strong>Crear tablas faltantes</strong> solo para tablas ausentes.
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    {/if}

                                </div>
                            </div>
                        </div>
                    {/if}

                    {if isset($oauth_status) && $oauth_status.configured && $oauth_status.has_token && $oauth_status.is_connected}
                        <div class="form-group">
                            <label class="control-label col-lg-3">
                                Conectividad
                            </label>
                            <div class="col-lg-9">
                                <button type="button" id="yuju-test-connectivity" class="btn btn-info">
                                    <i class="icon-plug"></i> Probar Conectividad
                                </button>
                                <p class="help-block">Probar la conexión con la API de Yuju y mostrar las tiendas disponibles</p>
                                <div id="connectivity-result" class="alert" style="display: none; margin-top: 10px;"></div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="control-label col-lg-3">
                                Prueba de Productos
                            </label>
                            <div class="col-lg-9">
                                <button type="button" id="yuju-test-products" class="btn btn-success">
                                    <i class="icon-shopping-cart"></i> Probar API de Productos
                                </button>
                                <p class="help-block">Probar los endpoints de productos: ofertas, fichas técnicas y actualización masiva</p>
                                <div id="products-result" class="alert" style="display: none; margin-top: 10px;"></div>
                            </div>
                        </div>
                    {/if}
                </div>
            </div>
            
            {* Synchronization Settings *}
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="icon-refresh"></i>
                        Configuración de Sincronización
                    </h3>
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Seleccionar Tienda de PrestaShop
                        </label>
                        <div class="col-lg-9">
                            <select name="YUJU_PRESTASHOP_STORE_ID" class="form-control">
                                {if isset($prestashop_shops) && $prestashop_shops && is_array($prestashop_shops) && count($prestashop_shops) > 0}
                                    {foreach from=$prestashop_shops item=shop}
                                        <option value="{$shop.id_shop|escape:'html':'UTF-8'}" {if $config.YUJU_PRESTASHOP_STORE_ID == $shop.id_shop}selected{/if}>
                                            {$shop.name|escape:'html':'UTF-8'}
                                        </option>
                                    {/foreach}
                                {else}
                                    <option value="">No hay tiendas de PrestaShop disponibles</option>
                                {/if}
                            </select>
                            <p class="help-block">Seleccione la tienda de PrestaShop para conectar con Yuju</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Idioma de la Tienda
                        </label>
                        <div class="col-lg-9">
                            <select name="YUJU_STORE_LANGUAGE" class="form-control">
                                {foreach from=Language::getLanguages(false) item=language}
                                    <option value="{$language.iso_code|escape:'html':'UTF-8'}" {if $config.YUJU_STORE_LANGUAGE == $language.iso_code}selected{/if}>
                                        {$language.name|escape:'html':'UTF-8'}
                                    </option>
                                {/foreach}
                            </select>
                            <p class="help-block">Idioma principal para sincronizar con Yuju</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Habilitar Sincronización
                        </label>
                        <div class="col-lg-9">
                            <span class="switch prestashop-switch fixed-width-lg">
                                <input type="radio" name="YUJU_SYNC_ENABLED" id="sync_on" value="1" {if $config.YUJU_SYNC_ENABLED !== '0'}checked="checked"{/if}>
                                <label for="sync_on">Sí</label>
                                <input type="radio" name="YUJU_SYNC_ENABLED" id="sync_off" value="0" {if $config.YUJU_SYNC_ENABLED === '0'}checked="checked"{/if}>
                                <label for="sync_off">No</label>
                                <a class="slide-button btn"></a>
                            </span>
                            <p class="help-block">Habilitar sincronización de productos (Sí por defecto)</p>
                        </div>
                    </div>
                    
                    <div class="form-group sync-dependent">
                        <label class="control-label col-lg-3">
                            Sincronizar Precios
                        </label>
                        <div class="col-lg-9">
                            <span class="switch prestashop-switch fixed-width-lg">
                                <input type="radio" name="YUJU_SYNC_PRICES" id="sync_prices_on" value="1" {if $config.YUJU_SYNC_PRICES !== '0'}checked="checked"{/if}>
                                <label for="sync_prices_on">Sí</label>
                                <input type="radio" name="YUJU_SYNC_PRICES" id="sync_prices_off" value="0" {if $config.YUJU_SYNC_PRICES === '0'}checked="checked"{/if}>
                                <label for="sync_prices_off">No</label>
                                <a class="slide-button btn"></a>
                            </span>
                            <p class="help-block">Habilitar sincronización de precios</p>
                        </div>
                    </div>
                    
                    <div class="form-group sync-dependent">
                        <label class="control-label col-lg-3">
                            Sincronizar Stock
                        </label>
                        <div class="col-lg-9">
                            <span class="switch prestashop-switch fixed-width-lg">
                                <input type="radio" name="YUJU_SYNC_STOCK" id="sync_stock_on" value="1" {if $config.YUJU_SYNC_STOCK !== '0'}checked="checked"{/if}>
                                <label for="sync_stock_on">Sí</label>
                                <input type="radio" name="YUJU_SYNC_STOCK" id="sync_stock_off" value="0" {if $config.YUJU_SYNC_STOCK === '0'}checked="checked"{/if}>
                                <label for="sync_stock_off">No</label>
                                <a class="slide-button btn"></a>
                            </span>
                            <p class="help-block">Habilitar sincronización de inventario</p>
                        </div>
                    </div>
                    
                    <div class="form-group sync-dependent">
                        <label class="control-label col-lg-3">
                            Sincronizar Imágenes
                        </label>
                        <div class="col-lg-9">
                            <span class="switch prestashop-switch fixed-width-lg">
                                <input type="radio" name="YUJU_SYNC_IMAGES" id="sync_images_on" value="1" {if $config.YUJU_SYNC_IMAGES !== '0'}checked="checked"{/if}>
                                <label for="sync_images_on">Sí</label>
                                <input type="radio" name="YUJU_SYNC_IMAGES" id="sync_images_off" value="0" {if $config.YUJU_SYNC_IMAGES === '0'}checked="checked"{/if}>
                                <label for="sync_images_off">No</label>
                                <a class="slide-button btn"></a>
                            </span>
                            <p class="help-block">Habilitar sincronización de imágenes de productos</p>
                        </div>
                    </div>
                    
                    <div class="form-group sync-dependent">
                        <label class="control-label col-lg-3">
                            Sincronizar Órdenes
                        </label>
                        <div class="col-lg-9">
                            <span class="switch prestashop-switch fixed-width-lg">
                                <input type="radio" name="YUJU_SYNC_ORDERS" id="sync_orders_on" value="1" {if $config.YUJU_SYNC_ORDERS !== '0'}checked="checked"{/if}>
                                <label for="sync_orders_on">Sí</label>
                                <input type="radio" name="YUJU_SYNC_ORDERS" id="sync_orders_off" value="0" {if $config.YUJU_SYNC_ORDERS === '0'}checked="checked"{/if}>
                                <label for="sync_orders_off">No</label>
                                <a class="slide-button btn"></a>
                            </span>
                            <p class="help-block">Habilitar sincronización de órdenes</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Forzar Actualización
                        </label>
                        <div class="col-lg-9">
                            <button type="button" id="yuju-force-update" class="btn btn-warning">
                                <i class="icon-refresh"></i> Forzar Actualización Masiva
                            </button>
                            <p class="help-block">Esta funcionalidad forzará el envío masivo de información del producto ignorando lo ya enviado, por lo cual se recomienda un uso cauteloso.</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Limpieza de HTML en Descripción
                        </label>
                        <div class="col-lg-9">
                            <span class="switch prestashop-switch fixed-width-lg">
                                <input type="radio" name="YUJU_CLEAN_HTML" id="clean_html_on" value="1" {if $config.YUJU_CLEAN_HTML !== '0'}checked="checked"{/if}>
                                <label for="clean_html_on">Sí</label>
                                <input type="radio" name="YUJU_CLEAN_HTML" id="clean_html_off" value="0" {if $config.YUJU_CLEAN_HTML === '0'}checked="checked"{/if}>
                                <label for="clean_html_off">No</label>
                                <a class="slide-button btn"></a>
                            </span>
                            <p class="help-block">Limpiar etiquetas HTML de las descripciones antes de enviarlas a Yuju</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Enviar de nuevo (Category Bulk)
                        </label>
                        <div class="col-lg-9">
                            <span class="switch prestashop-switch fixed-width-lg">
                                <input type="radio" name="YUJU_ENABLE_BULK_RESEND_PENDING" id="bulk_resend_on" value="1" {if $config.YUJU_ENABLE_BULK_RESEND_PENDING == '1' || $config.YUJU_ENABLE_BULK_RESEND_PENDING === 1}checked="checked"{/if}>
                                <label for="bulk_resend_on">Sí</label>
                                <input type="radio" name="YUJU_ENABLE_BULK_RESEND_PENDING" id="bulk_resend_off" value="0" {if $config.YUJU_ENABLE_BULK_RESEND_PENDING != '1' && $config.YUJU_ENABLE_BULK_RESEND_PENDING !== 1}checked="checked"{/if}>
                                <label for="bulk_resend_off">No</label>
                                <a class="slide-button btn"></a>
                            </span>
                            <p class="help-block">Muestra el botón masivo «Enviar de nuevo» en Category Bulk para productos en espera ≥1h sin webhook. Desactivado por defecto.</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Frecuencia de Sincronización (segundos)
                        </label>
                        <div class="col-lg-9">
                            <input type="number" name="YUJU_SYNC_FREQUENCY" value="{$config.YUJU_SYNC_FREQUENCY|default:3600|escape:'html':'UTF-8'}" class="form-control" min="60">
                            <p class="help-block">Frecuencia de sincronización automática en segundos (3600 por defecto - 1 hora)</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Tamaño de Lote
                        </label>
                        <div class="col-lg-9">
                            <input type="number" name="YUJU_BATCH_SIZE" value="{$config.YUJU_BATCH_SIZE|default:100|escape:'html':'UTF-8'}" class="form-control" min="1" max="500">
                            <p class="help-block"><i class="icon-cubes"></i> Máximo de <strong>envíos reales a Yuju</strong> por ejecución del cron/cola (1–500). No es el tamaño del encolado masivo: puedes encolar 1.000, pero cada corrida solo manda este tope a la API. Los update sin cambios no consumen este cupo. Carriles: delete → create → update.</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            <strong>Frecuencia entre Lotes</strong>
                        </label>
                        <div class="col-lg-9">
                            <div class="input-group">
                                <input type="number" name="YUJU_BATCH_FREQUENCY" value="{$config.YUJU_BATCH_FREQUENCY|default:60|escape:'html':'UTF-8'}" class="form-control" min="30" max="3600">
                                <span class="input-group-addon">segundos</span>
                            </div>
                            <p class="help-block"><i class="icon-clock-o"></i> Tiempo de espera entre cada lote al procesar (ej: 60 segundos entre cada lote de 100)</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            <strong>Máximo Descargas Diarias del JSON (ofertas)</strong>
                        </label>
                        <div class="col-lg-9">
                            <select name="YUJU_MAX_DAILY_SYNCS" class="form-control">
                                <option value="1" {if $config.YUJU_MAX_DAILY_SYNCS == '1'}selected{/if}>1 vez al día</option>
                                <option value="2" {if $config.YUJU_MAX_DAILY_SYNCS == '2' || !$config.YUJU_MAX_DAILY_SYNCS}selected{/if}>2 veces al día (máximo API)</option>
                            </select>
                            <p class="help-block">
                                <i class="icon-info-circle"></i>
                                Endpoint <code>/products-offer-report</code>: Yuju permite generarlo <strong>cada 12 horas</strong> (máx. 2/día).
                                El JSON se guarda en <code>cache/yuju_products.json</code>.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {* products-gral-report — información general *}
            <div class="panel panel-default" id="yuju-gral-report-panel">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="icon-file-text-o"></i>
                        Reporte general de productos (revisión)
                    </h3>
                </div>
                <div class="panel-body">
                    <div class="alert alert-info">
                        Usa el endpoint <code>POST /products-gral-report</code>
                        (<a href="https://api-docs.yuju.io/docs/obtener-informacion-general" target="_blank" rel="noopener">
                            (documentación Yuju)
                        </a>.
                        Genera un JSONL con campos generales (nombre, descripción, imágenes, variaciones…).
                        <strong>Límite de la API: máximo 2 veces por día</strong> (créditos a las 00:00 UTC).
                        Cada descarga se guarda en el histórico y se puede revisar en el visualizador del monitoreo.
                    </div>

                    <div class="form-group">
                        <label class="control-label col-lg-3">Habilitar solicitud programada</label>
                        <div class="col-lg-9">
                            <span class="switch prestashop-switch fixed-width-lg">
                                <input type="radio" name="YUJU_GRAL_REPORT_ENABLED" id="gral_enabled_on" value="1" {if $config.YUJU_GRAL_REPORT_ENABLED == '1' || $config.YUJU_GRAL_REPORT_ENABLED === 1}checked="checked"{/if}>
                                <label for="gral_enabled_on">Sí</label>
                                <input type="radio" name="YUJU_GRAL_REPORT_ENABLED" id="gral_enabled_off" value="0" {if $config.YUJU_GRAL_REPORT_ENABLED != '1' && $config.YUJU_GRAL_REPORT_ENABLED !== 1}checked="checked"{/if}>
                                <label for="gral_enabled_off">No</label>
                                <a class="slide-button btn"></a>
                            </span>
                            <p class="help-block">Si está activo, <code>cron.php</code> / <code>gral_report.php</code> solicitará reportes respetando el cupo diario.</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="control-label col-lg-3">Días de la semana</label>
                        <div class="col-lg-9">
                            {assign var=wd_sel value=$gral_weekdays_selected|default:[]}
                            <label class="checkbox-inline" style="margin-right:12px;">
                                <input type="checkbox" name="YUJU_GRAL_REPORT_WEEKDAYS[]" value="1" {if isset($wd_sel[1])}checked="checked"{/if}> Lunes
                            </label>
                            <label class="checkbox-inline" style="margin-right:12px;">
                                <input type="checkbox" name="YUJU_GRAL_REPORT_WEEKDAYS[]" value="2" {if isset($wd_sel[2])}checked="checked"{/if}> Martes
                            </label>
                            <label class="checkbox-inline" style="margin-right:12px;">
                                <input type="checkbox" name="YUJU_GRAL_REPORT_WEEKDAYS[]" value="3" {if isset($wd_sel[3])}checked="checked"{/if}> Miércoles
                            </label>
                            <label class="checkbox-inline" style="margin-right:12px;">
                                <input type="checkbox" name="YUJU_GRAL_REPORT_WEEKDAYS[]" value="4" {if isset($wd_sel[4])}checked="checked"{/if}> Jueves
                            </label>
                            <label class="checkbox-inline" style="margin-right:12px;">
                                <input type="checkbox" name="YUJU_GRAL_REPORT_WEEKDAYS[]" value="5" {if isset($wd_sel[5])}checked="checked"{/if}> Viernes
                            </label>
                            <label class="checkbox-inline" style="margin-right:12px;">
                                <input type="checkbox" name="YUJU_GRAL_REPORT_WEEKDAYS[]" value="6" {if isset($wd_sel[6])}checked="checked"{/if}> Sábado
                            </label>
                            <label class="checkbox-inline" style="margin-right:12px;">
                                <input type="checkbox" name="YUJU_GRAL_REPORT_WEEKDAYS[]" value="7" {if isset($wd_sel[7])}checked="checked"{/if}> Domingo
                            </label>
                            <p class="help-block">Solo en estos días el cron solicitará el reporte programado (zona horaria de la tienda). Si no marca ninguno, no se ejecutará solo.</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="control-label col-lg-3">Veces al día (UTC)</label>
                        <div class="col-lg-9">
                            <select name="YUJU_GRAL_REPORT_MAX_DAILY" class="form-control" style="max-width:220px;">
                                <option value="1" {if $config.YUJU_GRAL_REPORT_MAX_DAILY == '1'}selected{/if}>1 vez al día</option>
                                <option value="2" {if $config.YUJU_GRAL_REPORT_MAX_DAILY == '2' || !$config.YUJU_GRAL_REPORT_MAX_DAILY}selected{/if}>2 veces al día (máximo API)</option>
                            </select>
                            <p class="help-block">
                                Cupo hoy (UTC {$gral_quota.utc_day|escape:'html':'UTF-8'}):
                                <strong>{$gral_quota.used|intval}/{$gral_quota.max|intval}</strong>
                                — restantes: {$gral_quota.remaining|intval}.
                                Con 2 veces se reparte ~cada 12h UTC.
                            </p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="control-label col-lg-3">Última solicitud / completado</label>
                        <div class="col-lg-9">
                            <p class="form-control-static">
                                Solicitud:
                                {if $config.YUJU_GRAL_REPORT_LAST_REQUEST_AT}
                                    {$config.YUJU_GRAL_REPORT_LAST_REQUEST_AT|escape:'html':'UTF-8'}
                                {else}
                                    <em>nunca</em>
                                {/if}
                                &nbsp;|&nbsp;
                                Completado:
                                {if $config.YUJU_GRAL_REPORT_LAST_COMPLETED_AT}
                                    {$config.YUJU_GRAL_REPORT_LAST_COMPLETED_AT|escape:'html':'UTF-8'}
                                {else}
                                    <em>nunca</em>
                                {/if}
                            </p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="control-label col-lg-3">Acciones</label>
                        <div class="col-lg-9">
                            <button type="button" class="btn btn-primary" id="yuju-gral-request-btn"
                                    data-ajax-url="{$ajax_url|escape:'html':'UTF-8'}"
                                    data-token="{$token|escape:'html':'UTF-8'}"
                                    {if !$gral_quota.allowed}disabled title="Cupo diario agotado"{/if}>
                                <i class="icon-cloud-download"></i> Solicitar reporte ahora
                            </button>
                            <a href="{$audit_monitoring_link|escape:'html':'UTF-8'}#yuju-gral-reports" class="btn btn-default">
                                <i class="icon-eye"></i> Ver histórico / visualizador
                            </a>
                            <span id="yuju-gral-request-status" class="text-muted" style="margin-left:10px;"></span>
                        </div>
                    </div>
                </div>
            </div>

            {* Offer Audit Settings *}
            <div class="panel panel-default" id="yuju-audit-settings-panel">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="icon-search"></i>
                        Auditoría de Ofertas
                    </h3>
                </div>
                <div class="panel-body">
                    <div class="alert alert-info">
                        Compara stock, precio e imágenes entre Yuju y PrestaShop.
                        <strong>PrestaShop es la fuente de verdad</strong>: si hay diferencias, se corrige solo en Yuju (nunca se modifican productos en PrestaShop).
                    </div>

                    <div class="form-group">
                        <label class="control-label col-lg-3">Habilitar auditoría programada</label>
                        <div class="col-lg-9">
                            <span class="switch prestashop-switch fixed-width-lg">
                                <input type="radio" name="YUJU_AUDIT_ENABLED" id="audit_enabled_on" value="1" {if $config.YUJU_AUDIT_ENABLED == '1' || $config.YUJU_AUDIT_ENABLED === 1}checked="checked"{/if}>
                                <label for="audit_enabled_on">Sí</label>
                                <input type="radio" name="YUJU_AUDIT_ENABLED" id="audit_enabled_off" value="0" {if $config.YUJU_AUDIT_ENABLED != '1' && $config.YUJU_AUDIT_ENABLED !== 1}checked="checked"{/if}>
                                <label for="audit_enabled_off">No</label>
                                <a class="slide-button btn"></a>
                            </span>
                            <p class="help-block">Si está activo, <code>cron.php</code> ejecutará la auditoría según la programación.</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="control-label col-lg-3">Habilitar interfaz gráfica</label>
                        <div class="col-lg-9">
                            <span class="switch prestashop-switch fixed-width-lg">
                                <input type="radio" name="YUJU_AUDIT_UI_ENABLED" id="audit_ui_on" value="1" {if $config.YUJU_AUDIT_UI_ENABLED !== '0'}checked="checked"{/if}>
                                <label for="audit_ui_on">Sí</label>
                                <input type="radio" name="YUJU_AUDIT_UI_ENABLED" id="audit_ui_off" value="0" {if $config.YUJU_AUDIT_UI_ENABLED === '0'}checked="checked"{/if}>
                                <label for="audit_ui_off">No</label>
                                <a class="slide-button btn"></a>
                            </span>
                            <p class="help-block">Permite ejecutar la auditoría en vivo y ver el comparativo/progreso.</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="control-label col-lg-3">Auditar stock</label>
                        <div class="col-lg-9">
                            <span class="switch prestashop-switch fixed-width-lg">
                                <input type="radio" name="YUJU_AUDIT_STOCK" id="audit_stock_on" value="1" {if $config.YUJU_AUDIT_STOCK !== '0'}checked="checked"{/if}>
                                <label for="audit_stock_on">Sí</label>
                                <input type="radio" name="YUJU_AUDIT_STOCK" id="audit_stock_off" value="0" {if $config.YUJU_AUDIT_STOCK === '0'}checked="checked"{/if}>
                                <label for="audit_stock_off">No</label>
                                <a class="slide-button btn"></a>
                            </span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="control-label col-lg-3">Auditar precio</label>
                        <div class="col-lg-9">
                            <span class="switch prestashop-switch fixed-width-lg">
                                <input type="radio" name="YUJU_AUDIT_PRICE" id="audit_price_on" value="1" {if $config.YUJU_AUDIT_PRICE !== '0'}checked="checked"{/if}>
                                <label for="audit_price_on">Sí</label>
                                <input type="radio" name="YUJU_AUDIT_PRICE" id="audit_price_off" value="0" {if $config.YUJU_AUDIT_PRICE === '0'}checked="checked"{/if}>
                                <label for="audit_price_off">No</label>
                                <a class="slide-button btn"></a>
                            </span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="control-label col-lg-3">Auditar imágenes</label>
                        <div class="col-lg-9">
                            <span class="switch prestashop-switch fixed-width-lg">
                                <input type="radio" name="YUJU_AUDIT_IMAGES" id="audit_images_on" value="1" {if $config.YUJU_AUDIT_IMAGES == '1' || $config.YUJU_AUDIT_IMAGES === 1}checked="checked"{/if}>
                                <label for="audit_images_on">Sí</label>
                                <input type="radio" name="YUJU_AUDIT_IMAGES" id="audit_images_off" value="0" {if $config.YUJU_AUDIT_IMAGES != '1' && $config.YUJU_AUDIT_IMAGES !== 1}checked="checked"{/if}>
                                <label for="audit_images_off">No</label>
                                <a class="slide-button btn"></a>
                            </span>
                            <p class="help-block">Más lento: consulta producto en Yuju para comparar imágenes.</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="control-label col-lg-3">Periodicidad</label>
                        <div class="col-lg-9">
                            <select name="YUJU_AUDIT_SCHEDULE" class="form-control">
                                <option value="hourly" {if $config.YUJU_AUDIT_SCHEDULE == 'hourly'}selected{/if}>Por hora</option>
                                <option value="daily" {if $config.YUJU_AUDIT_SCHEDULE == 'daily' || !$config.YUJU_AUDIT_SCHEDULE}selected{/if}>Diaria</option>
                                <option value="weekly" {if $config.YUJU_AUDIT_SCHEDULE == 'weekly'}selected{/if}>Semanal</option>
                            </select>
                            <p class="help-block">Periodo base sobre el que se reparte la cantidad de ejecuciones.</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="control-label col-lg-3">Veces por periodo</label>
                        <div class="col-lg-9">
                            <input type="number" name="YUJU_AUDIT_TIMES_PER_PERIOD" class="form-control" min="1" max="24"
                                   value="{$config.YUJU_AUDIT_TIMES_PER_PERIOD|default:1|escape:'html':'UTF-8'}">
                            <p class="help-block">Ej.: 2 veces al día, o 1 vez a la semana.</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="control-label col-lg-3">Última ejecución</label>
                        <div class="col-lg-9">
                            <p class="form-control-static">
                                {if $config.YUJU_AUDIT_LAST_RUN_AT}
                                    {$config.YUJU_AUDIT_LAST_RUN_AT|escape:'html':'UTF-8'}
                                {else}
                                    <em>Aún no se ha ejecutado</em>
                                {/if}
                            </p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="control-label col-lg-3">Acciones</label>
                        <div class="col-lg-9">
                            {if $config.YUJU_AUDIT_UI_ENABLED !== '0'}
                                <a href="{$audit_ui_link|escape:'html':'UTF-8'}" class="btn btn-primary">
                                    <i class="icon-play"></i> Ejecutar auditoría ahora
                                </a>
                            {else}
                                <button type="button" class="btn btn-default" disabled title="Habilite la interfaz gráfica">
                                    <i class="icon-play"></i> Ejecutar auditoría ahora
                                </button>
                            {/if}
                            <a href="{$audit_monitoring_link|escape:'html':'UTF-8'}" class="btn btn-default">
                                <i class="icon-bar-chart"></i> Ver monitoreo
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            {* Logging Settings *}
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="icon-file-text"></i>
                        Configuración de Registro
                    </h3>
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Habilitar Logs
                        </label>
                        <div class="col-lg-9">
                            <span class="switch prestashop-switch fixed-width-lg">
                                <input type="radio" name="YUJU_LOGGING_ENABLED" id="logging_on" value="1" {if $config.YUJU_LOGGING_ENABLED !== '0'}checked="checked"{/if}>
                                <label for="logging_on">Sí</label>
                                <input type="radio" name="YUJU_LOGGING_ENABLED" id="logging_off" value="0" {if $config.YUJU_LOGGING_ENABLED === '0'}checked="checked"{/if}>
                                <label for="logging_off">No</label>
                                <a class="slide-button btn"></a>
                            </span>
                            <p class="help-block">Habilitar el registro de actividades (Sí por defecto)</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Nivel de Registro
                        </label>
                        <div class="col-lg-9">
                            <select name="YUJU_LOG_LEVEL" class="form-control">
                                <option value="error" {if $config.YUJU_LOG_LEVEL == 'error'}selected{/if}>
                                    Solo errores
                                </option>
                                <option value="warning" {if $config.YUJU_LOG_LEVEL == 'warning'}selected{/if}>
                                    Advertencias y superiores
                                </option>
                                <option value="info" {if $config.YUJU_LOG_LEVEL == 'info' || !$config.YUJU_LOG_LEVEL}selected{/if}>
                                    Info, errores y advertencias (por defecto)
                                </option>
                                <option value="debug" {if $config.YUJU_LOG_LEVEL == 'debug'}selected{/if}>
                                    Debug (todo)
                                </option>
                            </select>
                            <p class="help-block">Nivel de detalle para los registros</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Retención de Registros (días)
                        </label>
                        <div class="col-lg-9">
                            <input type="number" name="YUJU_LOG_RETENTION" value="{$config.YUJU_LOG_RETENTION|default:30|escape:'html':'UTF-8'}" class="form-control" min="1">
                            <p class="help-block">Número de días para mantener archivos de registro (30 por defecto)</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="panel-footer">
                <button type="submit" value="1" id="configuration_form_submit_btn" name="submitConfiguration" class="btn btn-default pull-right">
                    <i class="process-icon-save"></i> Guardar
                </button>
            </div>
        </form>
    </div>
</div>
<script type="text/javascript">
// JavaScript functionality is now handled in admin.js
// This ensures compatibility with PrestaShop 8 module loading system

function openCronManagerModal() {
    var $modal = jQuery('#yuju-cron-manager-modal');
    if (!$modal.length) {
        var modalHtml = '' +
            '<div class="modal fade yuju-cron-manager-modal" id="yuju-cron-manager-modal" tabindex="-1" role="dialog" aria-labelledby="yuju-cron-manager-title">' +
                '<div class="modal-dialog modal-lg" role="document">' +
                    '<div class="modal-content">' +
                        '<div class="modal-header yuju-cron-modal-header">' +
                            '<h4 class="modal-title yuju-cron-modal-title" id="yuju-cron-manager-title"><i class="icon-time"></i> Administrar crons</h4>' +
                            '<button type="button" class="yuju-cron-modal-close" data-dismiss="modal" aria-label="Cerrar">&times;</button>' +
                        '</div>' +
                        '<div class="modal-body">' +
                            '<div class="alert alert-info" style="margin-bottom:12px;"><strong>Recomendación:</strong> programe <code>cron.php</code> y <code>process_queue.php</code> (cola) en crontab. Los demás son utilitarios, internos o de diagnóstico. <strong>Ejecutar</strong> desde aquí corre en segundo plano y no bloquea el BO.</div>' +
                            '<div id="yuju-cron-manager-alert" class="alert" style="display:none;"></div>' +
                            '<div class="yuju-cron-manager-filters" style="margin:0 0 12px 0; display:flex; gap:14px; flex-wrap:wrap;">' +
                                '<label style="margin:0; font-weight:600; cursor:pointer;">' +
                                    '<input type="checkbox" id="yuju-cron-filter-recommended" style="margin-right:6px; vertical-align:middle;"> Mostrar solo recomendados' +
                                '</label>' +
                                '<label style="margin:0; font-weight:600; cursor:pointer;">' +
                                    '<input type="checkbox" id="yuju-cron-filter-hide-diagnostic" style="margin-right:6px; vertical-align:middle;"> Ocultar scripts de diagnóstico' +
                                '</label>' +
                            '</div>' +
                            '<div class="table-responsive">' +
                                '<table class="table table-bordered table-striped">' +
                                    '<thead>' +
                                        '<tr>' +
                                            '<th>Cron</th>' +
                                            '<th>Uso</th>' +
                                            '<th>Necesidad</th>' +
                                            '<th>Última ejecución</th>' +
                                            '<th>Estado</th>' +
                                            '<th>Duración</th>' +
                                            '<th width="120">Acción</th>' +
                                        '</tr>' +
                                    '</thead>' +
                                    '<tbody id="yuju-cron-manager-tbody">' +
                                        '<tr><td colspan="7" class="text-center text-muted">Cargando...</td></tr>' +
                                    '</tbody>' +
                                '</table>' +
                            '</div>' +
                            '<div class="yuju-cron-output-wrap">' +
                                '<div class="yuju-cron-output-head">' +
                                    '<label for="yuju-cron-manager-output"><strong>Resultado:</strong></label>' +
                                    '<button type="button" id="yuju-cron-output-expand" class="btn btn-default btn-xs" title="Ver resultado en pantalla completa">' +
                                        '<i class="icon-resize-full"></i> Ampliar' +
                                    '</button>' +
                                '</div>' +
                                '<textarea id="yuju-cron-manager-output" class="form-control" rows="10" readonly></textarea>' +
                            '</div>' +
                        '</div>' +
                        '<div class="modal-footer">' +
                            '<button type="button" id="yuju-cron-manager-refresh" class="btn btn-default"><i class="icon-refresh"></i> Actualizar listado</button>' +
                            '<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';
        jQuery('body').append(modalHtml);
        if (!jQuery('#yuju-cron-output-fullscreen-modal').length) {
            jQuery('body').append(
                '<div class="modal fade yuju-cron-output-fullscreen-modal" id="yuju-cron-output-fullscreen-modal" tabindex="-1" role="dialog">' +
                    '<div class="modal-dialog modal-lg yuju-cron-output-fullscreen-dialog" role="document">' +
                        '<div class="modal-content">' +
                            '<div class="modal-header yuju-cron-modal-header">' +
                                '<h4 class="modal-title yuju-cron-modal-title"><i class="icon-file-text"></i> Resultado del cron</h4>' +
                                '<button type="button" class="yuju-cron-modal-close" data-dismiss="modal" aria-label="Cerrar">&times;</button>' +
                            '</div>' +
                            '<div class="modal-body" style="padding-top:12px;">' +
                                '<textarea id="yuju-cron-manager-output-fullscreen" class="form-control" readonly></textarea>' +
                            '</div>' +
                            '<div class="modal-footer">' +
                                '<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>'
            );
        }
        $modal = jQuery('#yuju-cron-manager-modal');
    }
    if ($modal.length && !$modal.parent().is('body')) {
        $modal.appendTo('body');
    }
    var $fsModal = jQuery('#yuju-cron-output-fullscreen-modal');
    if ($fsModal.length && !$fsModal.parent().is('body')) {
        $fsModal.appendTo('body');
    }
    $modal.modal('show');
    try {
        if (typeof YujuAdmin !== 'undefined' && typeof YujuAdmin.loadCronManagerData === 'function') {
            YujuAdmin.loadCronManagerData();
        }
    } catch (e) {
        // ignore
    }
    return false;
}


// Handle sync dependencies
$(document).ready(function() {
    // ============================================================
    // Secciones colapsables (chevron derecha) en Configuración
    // ============================================================
    (function initYujuConfigCollapsibleSections() {
        var $form = $('#configuration_form');
        if (!$form.length) {
            return;
        }

        var storageKey = 'yuju_config_collapsed_sections';
        var saved = {};
        try {
            saved = JSON.parse(localStorage.getItem(storageKey) || '{}') || {};
        } catch (e) {
            saved = {};
        }

        function sectionKey($panel, index) {
            var title = $.trim($panel.children('.panel-heading').find('.panel-title').first().text() || '');
            return title ? title : ('section_' + index);
        }

        function persist($head, key) {
            saved[key] = $head.hasClass('collapsed') || $head.attr('aria-expanded') === 'false';
            try {
                localStorage.setItem(storageKey, JSON.stringify(saved));
            } catch (e) {}
        }

        $form.children('.panel.panel-default').each(function (index) {
            var $panel = $(this);
            if ($panel.hasClass('yuju-config-collapsible')) {
                return;
            }

            var $heading = $panel.children('.panel-heading').first();
            var $body = $panel.children('.panel-body').first();
            if (!$heading.length || !$body.length) {
                return;
            }

            var id = 'yuju-cfg-section-' + index;
            var key = sectionKey($panel, index);
            var startCollapsed = !!saved[key];

            $panel.addClass('yuju-config-collapsible');
            $heading
                .addClass('yuju-config-collapsible__head')
                .attr({
                    role: 'button',
                    tabindex: '0',
                    'data-toggle': 'collapse',
                    'data-target': '#' + id,
                    'aria-expanded': startCollapsed ? 'false' : 'true',
                    'aria-controls': id
                });

            if (startCollapsed) {
                $heading.addClass('collapsed');
            }

            // Fila título + chevron (evita que el clearfix del BO tire el icono abajo)
            var $row = $heading.children('.yuju-config-collapsible__row');
            if (!$row.length) {
                $heading.wrapInner('<div class="yuju-config-collapsible__row"></div>');
                $row = $heading.children('.yuju-config-collapsible__row');
            }
            if (!$row.find('.yuju-config-collapsible__chev').length) {
                $row.append(
                    '<span class="yuju-config-collapsible__chev" aria-hidden="true"><i class="icon-chevron-down"></i></span>'
                );
            }

            $body.wrap('<div id="' + id + '" class="panel-collapse collapse' + (startCollapsed ? '' : ' in') + '"></div>');

            $('#' + id).on('shown.bs.collapse', function () {
                $heading.removeClass('collapsed').attr('aria-expanded', 'true');
                persist($heading, key);
            }).on('hidden.bs.collapse', function () {
                $heading.addClass('collapsed').attr('aria-expanded', 'false');
                persist($heading, key);
            });

            $heading.on('keydown', function (ev) {
                if (ev.key === 'Enter' || ev.key === ' ' || ev.keyCode === 13 || ev.keyCode === 32) {
                    ev.preventDefault();
                    $heading.trigger('click');
                }
            });
        });

        // Keep chevron state in sync for any config collapse (incl. tutorial fuera del form)
        $(document).on('shown.bs.collapse', '.yuju-config-collapsible .panel-collapse', function () {
            $(this).closest('.yuju-config-collapsible')
                .children('.panel-heading')
                .removeClass('collapsed')
                .attr('aria-expanded', 'true');
        }).on('hidden.bs.collapse', '.yuju-config-collapsible .panel-collapse', function () {
            $(this).closest('.yuju-config-collapsible')
                .children('.panel-heading')
                .addClass('collapsed')
                .attr('aria-expanded', 'false');
        });

        $('#yuju-config-expand-all').on('click', function () {
            $form.find('.yuju-config-collapsible .panel-collapse').collapse('show');
        });
        $('#yuju-config-collapse-all').on('click', function () {
            $form.find('.yuju-config-collapsible .panel-collapse').collapse('hide');
        });
    })();

    function toggleSyncDependentFields() {
        var syncEnabled = $('input[name="YUJU_SYNC_ENABLED"]:checked').val() === '1';
        $('.sync-dependent').toggle(syncEnabled);
        if (!syncEnabled) {
            $('.sync-dependent input[type="radio"][value="0"]').prop('checked', true);
        }
    }
    
    $('input[name="YUJU_SYNC_ENABLED"]').change(toggleSyncDependentFields);
    toggleSyncDependentFields();
    
    // Force update confirmation
    $('#yuju-force-update').click(function() {
        if (confirm('¿Está seguro de que desea forzar la actualización masiva? Esta acción enviará todos los productos a Yuju ignorando el estado de sincronización anterior.')) {
            // Add your force update logic here
            $(this).prop('disabled', true).html('<i class="icon-spin icon-refresh"></i> Procesando...');
        }
    });

    // ============================================================
    // Detección y activación de hooks Yuju
    // ============================================================
    (function() {
        var $panel = $('#yuju-hooks-panel');
        if (!$panel.length) {
            return;
        }

        var ajaxUrl = $panel.data('ajax-url') || '';
        var token = $panel.data('token') || '';

        function renderRows(hooks, allActive) {
            var $tbody = $('#yuju-hooks-table tbody');
            if (!$tbody.length || !hooks || !hooks.length) {
                return;
            }
            var html = '';
            for (var i = 0; i < hooks.length; i++) {
                var h = hooks[i];
                var statusHtml = h.registered
                    ? '<span class="label label-success"><i class="icon-check"></i> Activo</span>'
                    : '<span class="label label-default"><i class="icon-remove"></i> Inactivo</span>';
                var actionHtml = h.registered
                    ? '<span class="text-muted small">—</span>'
                    : '<button type="button" class="btn btn-success btn-xs yuju-hook-activate-btn" data-hook="' + h.name + '"><i class="icon-power-off"></i> Activar</button>';
                var critBadge = h.critical
                    ? ' <span class="label label-danger" style="margin-left:6px;" title="Hook crítico para la integración">Crítico</span>'
                    : '';
                html += '<tr data-hook-name="' + h.name + '"' + (h.registered ? '' : ' class="warning"') + '>' +
                    '<td><strong>' + $('<div/>').text(h.label).html() + '</strong>' + critBadge +
                        '<br><code class="text-muted" style="font-size:11px;">' + h.name + '</code></td>' +
                    '<td class="text-muted">' + $('<div/>').text(h.description).html() + '</td>' +
                    '<td class="text-center yuju-hook-status-cell">' + statusHtml + '</td>' +
                    '<td class="text-center yuju-hook-action-cell">' + actionHtml + '</td>' +
                    '</tr>';
            }
            $tbody.html(html);

            var $accPill = $('#yuju-acc-hooks-heading .yuju-diag-pill');
            if (allActive) {
                $accPill.replaceWith('<span class="yuju-diag-pill ok"><i class="icon-check"></i> Todos activos</span>');
                $('#yuju-hooks-activate-all').hide();
            } else {
                $accPill.replaceWith('<span class="yuju-diag-pill warn"><i class="icon-warning"></i> Hay inactivos</span>');
                $('#yuju-hooks-activate-all').show();
            }
        }

        function refreshHooks() {
            var $btn = $('#yuju-hooks-refresh');
            var origHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="icon-spinner icon-spin"></i> Cargando…');
            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    ajax: true,
                    action: 'getYujuHooksStatus',
                    token: token
                }
            }).done(function(r) {
                if (r && r.success && r.hooks) {
                    renderRows(r.hooks, !!r.all_active);
                }
            }).always(function() {
                $btn.prop('disabled', false).html(origHtml);
            });
        }

        $(document).on('click', '#yuju-hooks-refresh', function(e) {
            e.preventDefault();
            refreshHooks();
        });

        $(document).on('click', '.yuju-hook-activate-btn', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var hookName = $btn.data('hook');
            if (!hookName) { return; }
            $btn.prop('disabled', true).html('<i class="icon-spinner icon-spin"></i> Activando…');
            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    ajax: true,
                    action: 'registerYujuHook',
                    token: token,
                    hook: hookName
                }
            }).done(function(r) {
                if (r && r.success) {
                    refreshHooks();
                } else {
                    var msg = (r && r.message) ? r.message : 'No se pudo activar el hook.';
                    alert(msg);
                    $btn.prop('disabled', false).html('<i class="icon-power-off"></i> Activar');
                }
            }).fail(function() {
                alert('Error de red al activar el hook.');
                $btn.prop('disabled', false).html('<i class="icon-power-off"></i> Activar');
            });
        });

        $(document).on('click', '#yuju-hooks-activate-all', function(e) {
            e.preventDefault();
            var $btn = $(this);
            if (!window.confirm('¿Activar todos los hooks inactivos del módulo Yuju?')) {
                return;
            }
            var origHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="icon-spinner icon-spin"></i> Activando…');
            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    ajax: true,
                    action: 'registerAllYujuHooks',
                    token: token
                }
            }).done(function(r) {
                if (r && r.hooks) {
                    renderRows(r.hooks, !!r.all_active);
                }
                if (r && r.message) {
                    // mostrar feedback ligero
                    var $summary = $('.yuju-hooks-summary');
                    $summary.append(' <span class="text-muted small" style="margin-left:8px;">' + $('<div/>').text(r.message).html() + '</span>');
                    setTimeout(function() {
                        $summary.find('.text-muted.small').remove();
                    }, 4000);
                }
            }).fail(function() {
                alert('Error de red al activar los hooks.');
            }).always(function() {
                $btn.prop('disabled', false).html(origHtml);
            });
        });
    })();

    // ============================================================
    // Detección y creación de tablas Yuju
    // ============================================================
    (function() {
        var $panel = $('#yuju-tables-panel');
        if (!$panel.length) {
            return;
        }

        var ajaxUrl = $panel.data('ajax-url') || '';
        var token = $panel.data('token') || '';

        function renderTablesRows(tables, allPresent) {
            var $tbody = $('#yuju-tables-table tbody');
            if (!$tbody.length || !tables || !tables.length) {
                return;
            }
            var html = '';
            for (var i = 0; i < tables.length; i++) {
                var t = tables[i];
                var statusHtml = t.exists
                    ? '<span class="label label-success"><i class="icon-check"></i> Existe</span>'
                    : '<span class="label label-default"><i class="icon-remove"></i> No existe</span>';
                var actionHtml = t.exists
                    ? '<span class="text-muted small">—</span>'
                    : '<button type="button" class="btn btn-success btn-xs yuju-table-create-btn" data-table="' + t.name + '"><i class="icon-plus"></i> Crear</button>';
                var critBadge = t.critical
                    ? ' <span class="label label-danger" style="margin-left:6px;" title="Tabla crítica para la integración">Crítica</span>'
                    : '';
                html += '<tr data-table-name="' + t.name + '"' + (t.exists ? '' : ' class="warning"') + '>' +
                    '<td><strong>' + $('<div/>').text(t.label).html() + '</strong>' + critBadge +
                        '<br><code class="text-muted" style="font-size:11px;">' + t.full_name + '</code></td>' +
                    '<td class="text-muted">' + $('<div/>').text(t.description).html() + '</td>' +
                    '<td class="text-center yuju-table-status-cell">' + statusHtml + '</td>' +
                    '<td class="text-center yuju-table-action-cell">' + actionHtml + '</td>' +
                    '</tr>';
            }
            $tbody.html(html);

            var $accPill = $('#yuju-acc-tables-heading .yuju-diag-pill');
            if (allPresent) {
                $accPill.replaceWith('<span class="yuju-diag-pill ok"><i class="icon-check"></i> Todas presentes</span>');
                $('#yuju-tables-create-all').hide();
            } else {
                $accPill.replaceWith('<span class="yuju-diag-pill warn"><i class="icon-warning"></i> Faltan tablas</span>');
                $('#yuju-tables-create-all').show();
            }
        }

        function refreshTables() {
            var $btn = $('#yuju-tables-refresh');
            var origHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="icon-spinner icon-spin"></i> Cargando…');
            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    ajax: true,
                    action: 'getYujuTablesStatus',
                    token: token
                }
            }).done(function(r) {
                if (r && r.success && r.tables) {
                    renderTablesRows(r.tables, !!r.all_present);
                }
            }).always(function() {
                $btn.prop('disabled', false).html(origHtml);
            });
        }

        $(document).on('click', '#yuju-tables-refresh', function(e) {
            e.preventDefault();
            refreshTables();
        });

        $(document).on('click', '.yuju-table-create-btn', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var tableName = $btn.data('table');
            if (!tableName) { return; }
            $btn.prop('disabled', true).html('<i class="icon-spinner icon-spin"></i> Creando…');
            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    ajax: true,
                    action: 'createYujuTable',
                    token: token,
                    table: tableName
                }
            }).done(function(r) {
                if (r && r.success) {
                    refreshTables();
                } else {
                    var msg = (r && r.message) ? r.message : 'No se pudo crear la tabla.';
                    alert(msg);
                    $btn.prop('disabled', false).html('<i class="icon-plus"></i> Crear');
                }
            }).fail(function() {
                alert('Error de red al crear la tabla.');
                $btn.prop('disabled', false).html('<i class="icon-plus"></i> Crear');
            });
        });

        $(document).on('click', '#yuju-tables-create-all', function(e) {
            e.preventDefault();
            var $btn = $(this);
            if (!window.confirm('¿Crear todas las tablas faltantes del módulo Yuju? Esta acción es segura: las tablas existentes no se modifican.')) {
                return;
            }
            var origHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="icon-spinner icon-spin"></i> Creando…');
            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    ajax: true,
                    action: 'createAllYujuTables',
                    token: token
                }
            }).done(function(r) {
                if (r && r.tables) {
                    renderTablesRows(r.tables, !!r.all_present);
                }
                if (r && r.message) {
                    var $summary = $('.yuju-tables-summary');
                    $summary.append(' <span class="text-muted small" style="margin-left:8px;">' + $('<div/>').text(r.message).html() + '</span>');
                    setTimeout(function() {
                        $summary.find('.text-muted.small').remove();
                    }, 4000);
                }
                if (r && r.errors && r.errors.length) {
                    alert('Algunas operaciones fallaron:\n' + r.errors.join('\n'));
                }
            }).fail(function() {
                alert('Error de red al crear las tablas.');
            }).always(function() {
                $btn.prop('disabled', false).html(origHtml);
            });
        });

        // ========================================================
        // Revisar las tablas (esquema + columnas) con modal visual
        // ========================================================
        function ensureSchemaReviewModal() {
            if ($('#yuju-schema-review-modal').length) {
                return;
            }
            var html = '' +
                '<div class="modal fade" id="yuju-schema-review-modal" tabindex="-1" role="dialog" aria-labelledby="yuju-schema-review-title">' +
                    '<div class="modal-dialog modal-lg" role="document">' +
                        '<div class="modal-content">' +
                            '<div class="modal-header">' +
                                '<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>' +
                                '<h4 class="modal-title" id="yuju-schema-review-title"><i class="icon-database"></i> Revisar tablas del módulo</h4>' +
                            '</div>' +
                            '<div class="modal-body">' +
                                '<p class="help-block" style="margin-top:0;">Compara cada tabla con <code>sql/install.sql</code>. Si falta la tabla o alguna columna, se crea/agrega automáticamente. No elimina datos ni columnas extra.</p>' +
                                '<div class="progress" style="height:22px; margin-bottom:12px;">' +
                                    '<div id="yuju-schema-review-progress" class="progress-bar progress-bar-info progress-bar-striped active" role="progressbar" style="width:0%; min-width:2em; line-height:22px;">0%</div>' +
                                '</div>' +
                                '<div id="yuju-schema-review-summary" class="alert alert-info" style="display:none;"></div>' +
                                '<div class="yuju-schema-review-list" id="yuju-schema-review-list"></div>' +
                            '</div>' +
                            '<div class="modal-footer">' +
                                '<button type="button" class="btn btn-primary" id="yuju-schema-review-start"><i class="icon-play"></i> Iniciar revisión</button>' +
                                '<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>';
            $('body').append(html);
        }

        function statusBadge(status) {
            switch (status) {
                case 'pending': return '<span class="label label-default">En espera</span>';
                case 'reviewing': return '<span class="label label-info"><i class="icon-spinner icon-spin"></i> Revisando…</span>';
                case 'ok': return '<span class="label label-success"><i class="icon-check"></i> OK</span>';
                case 'created': return '<span class="label label-primary"><i class="icon-plus"></i> Creada</span>';
                case 'repaired': return '<span class="label label-warning"><i class="icon-wrench"></i> Reparada</span>';
                case 'error': return '<span class="label label-danger"><i class="icon-remove"></i> Error</span>';
                default: return '<span class="label label-default">' + status + '</span>';
            }
        }

        function renderSchemaList(tables) {
            var $list = $('#yuju-schema-review-list');
            var html = '';
            tables.forEach(function (t, idx) {
                html += '<div class="yuju-schema-review-item" data-table="' + t.name + '" data-index="' + idx + '">' +
                    '<div class="yuju-schema-review-item__head">' +
                        '<div class="yuju-schema-review-item__title">' +
                            '<strong>' + $('<div/>').text(t.label).html() + '</strong> ' +
                            (t.critical ? '<span class="label label-danger">Crítica</span> ' : '') +
                            '<code>' + t.full_name + '</code>' +
                        '</div>' +
                        '<div class="yuju-schema-review-item__status">' + statusBadge('pending') + '</div>' +
                    '</div>' +
                    '<div class="yuju-schema-review-item__body text-muted small">Pendiente de revisión…</div>' +
                '</div>';
            });
            $list.html(html);
        }

        function setItemState($item, status, message, actions) {
            $item.attr('data-status', status);
            $item.find('.yuju-schema-review-item__status').html(statusBadge(status));
            var body = $('<div/>').text(message || '').html();
            if (actions && actions.length) {
                body += '<ul class="yuju-schema-review-actions">';
                actions.forEach(function (a) {
                    var icon = 'icon-info-sign';
                    if (a.type === 'create_table') icon = 'icon-plus';
                    if (a.type === 'add_column') icon = 'icon-edit';
                    if (a.type === 'add_column_error') icon = 'icon-warning-sign';
                    if (a.type === 'check') icon = 'icon-ok';
                    if (a.type === 'patch') icon = 'icon-cog';
                    body += '<li><i class="' + icon + '"></i> ' + $('<div/>').text(a.detail || '').html() + '</li>';
                });
                body += '</ul>';
            }
            $item.find('.yuju-schema-review-item__body').html(body);
        }

        function updateSchemaProgress(done, total) {
            var pct = total > 0 ? Math.round((done / total) * 100) : 0;
            $('#yuju-schema-review-progress')
                .css('width', pct + '%')
                .text(pct + '%');
        }

        var schemaRunning = false;

        function runSchemaReview(tables) {
            if (schemaRunning) return;
            schemaRunning = true;
            var $startBtn = $('#yuju-schema-review-start');
            $startBtn.prop('disabled', true).html('<i class="icon-spinner icon-spin"></i> Revisando…');
            $('#yuju-schema-review-progress')
                .removeClass('progress-bar-success progress-bar-danger')
                .addClass('progress-bar-info active');
            $('#yuju-schema-review-summary').hide().removeClass('alert-success alert-warning alert-danger').addClass('alert-info');

            var totals = { ok: 0, created: 0, repaired: 0, error: 0 };
            var i = 0;

            function next() {
                if (i >= tables.length) {
                    schemaRunning = false;
                    $startBtn.prop('disabled', false).html('<i class="icon-repeat"></i> Volver a revisar');
                    $('#yuju-schema-review-progress').removeClass('active progress-bar-info')
                        .addClass(totals.error ? 'progress-bar-warning' : 'progress-bar-success');
                    var msg = 'Finalizado: ' + totals.ok + ' OK, ' +
                        totals.created + ' creadas, ' +
                        totals.repaired + ' reparadas, ' +
                        totals.error + ' con error.';
                    $('#yuju-schema-review-summary').text(msg).show()
                        .removeClass('alert-info')
                        .addClass(totals.error ? 'alert-warning' : 'alert-success');
                    refreshTables();
                    return;
                }

                var t = tables[i];
                var $item = $('.yuju-schema-review-item[data-table="' + t.name + '"]');
                setItemState($item, 'reviewing', 'Comparando esquema e intentando reparar si hace falta…', []);
                updateSchemaProgress(i, tables.length);

                $.ajax({
                    url: ajaxUrl,
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        ajax: true,
                        action: 'repairYujuTableSchema',
                        token: token,
                        table: t.name
                    }
                }).done(function (r) {
                    var report = (r && r.report) ? r.report : {};
                    var st = report.status || (r && r.success ? 'ok' : 'error');
                    if (totals[st] !== undefined) {
                        totals[st]++;
                    } else if (st === 'ok' || st === 'created' || st === 'repaired') {
                        totals[st]++;
                    } else {
                        totals.error++;
                        st = 'error';
                    }
                    setItemState($item, st, report.message || (r && r.message) || '', report.actions || []);
                }).fail(function () {
                    totals.error++;
                    setItemState($item, 'error', 'Error de red al revisar esta tabla.', []);
                }).always(function () {
                    i++;
                    updateSchemaProgress(i, tables.length);
                    setTimeout(next, 60);
                });
            }

            next();
        }

        $(document).on('click', '#yuju-tables-review-schema', function (e) {
            e.preventDefault();
            ensureSchemaReviewModal();
            var $modal = $('#yuju-schema-review-modal');
            $('#yuju-schema-review-list').html('<div class="text-center text-muted" style="padding:20px;"><i class="icon-spinner icon-spin"></i> Cargando catálogo de tablas…</div>');
            $('#yuju-schema-review-summary').hide();
            updateSchemaProgress(0, 1);
            $('#yuju-schema-review-progress').css('width', '0%').text('0%')
                .removeClass('progress-bar-success progress-bar-danger progress-bar-warning')
                .addClass('progress-bar-info');
            $modal.modal('show');

            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    ajax: true,
                    action: 'getYujuSchemaReviewCatalog',
                    token: token
                }
            }).done(function (r) {
                if (!r || !r.success || !r.tables) {
                    $('#yuju-schema-review-list').html('<div class="alert alert-danger">' +
                        $('<div/>').text((r && r.message) ? r.message : 'No se pudo cargar el catálogo.').html() +
                        '</div>');
                    return;
                }
                renderSchemaList(r.tables);
                $modal.data('schema-tables', r.tables);
            }).fail(function () {
                $('#yuju-schema-review-list').html('<div class="alert alert-danger">Error de red al cargar el catálogo.</div>');
            });
        });

        $(document).on('click', '#yuju-schema-review-start', function (e) {
            e.preventDefault();
            var tables = $('#yuju-schema-review-modal').data('schema-tables');
            if (!tables || !tables.length) {
                alert('Aún no hay catálogo de tablas cargado.');
                return;
            }
            renderSchemaList(tables);
            runSchemaReview(tables);
        });
    })();

    // products-gral-report: solicitar desde configuración
    $('#yuju-gral-request-btn').on('click', function () {
        var $btn = $(this);
        var $status = $('#yuju-gral-request-status');
        if ($btn.prop('disabled')) {
            return;
        }
        $btn.prop('disabled', true);
        $status.text('Solicitando reporte…').removeClass('text-danger text-success').addClass('text-muted');
        $.ajax({
            url: $btn.data('ajax-url'),
            type: 'POST',
            dataType: 'json',
            data: {
                ajax: 1,
                action: 'RequestGralReport',
                token: $btn.data('token')
            }
        }).done(function (resp) {
            if (!resp) {
                $status.text('Respuesta vacía').addClass('text-danger');
                return;
            }
            if (resp.success) {
                $status.text(resp.message || 'OK').removeClass('text-danger').addClass('text-success');
                if (resp.quota) {
                    $status.append(' (cupo ' + resp.quota.used + '/' + resp.quota.max + ')');
                }
            } else {
                $status.text(resp.message || 'Error').removeClass('text-success').addClass('text-danger');
                if (resp.quota && resp.quota.allowed) {
                    $btn.prop('disabled', false);
                }
            }
        }).fail(function (xhr) {
            $status.text('Error HTTP ' + xhr.status).addClass('text-danger');
            $btn.prop('disabled', false);
        });
    });
});
</script>
<div class="modal fade yuju-cron-manager-modal" id="yuju-cron-manager-modal" tabindex="-1" role="dialog" aria-labelledby="yuju-cron-manager-title">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header yuju-cron-modal-header">
                <h4 class="modal-title yuju-cron-modal-title" id="yuju-cron-manager-title">
                    <i class="icon-time"></i> Administrar crons
                </h4>
                <button type="button" class="yuju-cron-modal-close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info" style="margin-bottom:12px;"><strong>Recomendación:</strong> programe <code>cron.php</code> y <code>process_queue.php</code> (cola) en crontab. Los demás son utilitarios, internos o de diagnóstico. <strong>Ejecutar</strong> desde aquí corre en segundo plano y no bloquea el BO.</div>
                <div id="yuju-cron-manager-alert" class="alert" style="display:none;"></div>
                <div class="yuju-cron-manager-filters" style="margin:0 0 12px 0; display:flex; gap:14px; flex-wrap:wrap;">
                    <label style="margin:0; font-weight:600; cursor:pointer;">
                        <input type="checkbox" id="yuju-cron-filter-recommended" style="margin-right:6px; vertical-align:middle;"> Mostrar solo recomendados
                    </label>
                    <label style="margin:0; font-weight:600; cursor:pointer;">
                        <input type="checkbox" id="yuju-cron-filter-hide-diagnostic" style="margin-right:6px; vertical-align:middle;"> Ocultar scripts de diagnóstico
                    </label>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Cron</th>
                                <th>Uso</th>
                                <th>Necesidad</th>
                                <th>Última ejecución</th>
                                <th>Estado</th>
                                <th>Duración</th>
                                <th width="120">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="yuju-cron-manager-tbody">
                            <tr>
                                <td colspan="7" class="text-center text-muted">Cargando...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="yuju-cron-output-wrap">
                    <div class="yuju-cron-output-head">
                        <label for="yuju-cron-manager-output"><strong>Resultado:</strong></label>
                        <button type="button"
                                id="yuju-cron-output-expand"
                                class="btn btn-default btn-xs"
                                title="Ver resultado en pantalla completa">
                            <i class="icon-resize-full"></i> Ampliar
                        </button>
                    </div>
                    <textarea id="yuju-cron-manager-output" class="form-control" rows="10" readonly></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="yuju-cron-manager-refresh" class="btn btn-default">
                    <i class="icon-refresh"></i> Actualizar listado
                </button>
                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade yuju-cron-output-fullscreen-modal" id="yuju-cron-output-fullscreen-modal" tabindex="-1" role="dialog" aria-labelledby="yuju-cron-output-fullscreen-title">
    <div class="modal-dialog modal-lg yuju-cron-output-fullscreen-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header yuju-cron-modal-header">
                <h4 class="modal-title yuju-cron-modal-title" id="yuju-cron-output-fullscreen-title">
                    <i class="icon-file-text"></i> Resultado del cron
                </h4>
                <button type="button" class="yuju-cron-modal-close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body" style="padding-top:12px;">
                <textarea id="yuju-cron-manager-output-fullscreen" class="form-control" readonly></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Copy button styles are now in admin.css */
.input-group {
    margin-bottom: 5px;
}

.panel .alert {
    margin-bottom: 20px;
}

.form-group .help-block {
    margin-top: 8px;
    font-size: 12px;
    color: #666;
}

.sync-dependent {
    margin-left: 20px;
    border-left: 3px solid #ddd;
    padding-left: 15px;
}

.sync-dependent.hidden {
    display: none;
}

.yuju-cron-manager-modal .modal-header,
.yuju-cron-manager-modal .yuju-cron-modal-header,
.yuju-cron-output-fullscreen-modal .modal-header,
.yuju-cron-output-fullscreen-modal .yuju-cron-modal-header {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 12px;
    float: none !important;
    padding: 14px 18px !important;
    margin: 0 !important;
    background: #f8fafc !important;
    border-bottom: 1px solid #dde3ea !important;
}

.yuju-cron-manager-modal .yuju-cron-modal-title,
.yuju-cron-manager-modal .modal-title,
.yuju-cron-output-fullscreen-modal .yuju-cron-modal-title,
.yuju-cron-output-fullscreen-modal .modal-title {
    float: none !important;
    display: block !important;
    flex: 1 1 auto !important;
    margin: 0 !important;
    padding: 0 !important;
    font-size: 16px !important;
    font-weight: 700 !important;
    line-height: 1.35 !important;
    color: #2f3b4a !important;
}

.yuju-cron-manager-modal .yuju-cron-modal-title i,
.yuju-cron-output-fullscreen-modal .yuju-cron-modal-title i {
    margin-right: 6px;
}

.yuju-cron-manager-modal .yuju-cron-modal-close,
.yuju-cron-output-fullscreen-modal .yuju-cron-modal-close {
    float: none !important;
    position: static !important;
    order: 2;
    flex: 0 0 auto !important;
    width: 32px !important;
    height: 32px !important;
    margin: 0 !important;
    padding: 0 !important;
    border: 1px solid #d0d7de !important;
    border-radius: 6px !important;
    background: #ffffff !important;
    color: #57606a !important;
    font-size: 22px !important;
    font-weight: 400 !important;
    line-height: 28px !important;
    text-align: center !important;
    text-shadow: none !important;
    opacity: 1 !important;
    cursor: pointer;
}

.yuju-cron-manager-modal .yuju-cron-modal-close:hover,
.yuju-cron-output-fullscreen-modal .yuju-cron-modal-close:hover {
    background: #f3f4f6 !important;
    color: #24292f !important;
    border-color: #afb8c1 !important;
}

/* Anular el .close de Bootstrap 3 si quedara en el DOM */
.yuju-cron-manager-modal .modal-header > .close,
.yuju-cron-output-fullscreen-modal .modal-header > .close {
    float: none !important;
    position: static !important;
    order: 2;
    margin: 0 !important;
}

.yuju-cron-manager-modal .table > thead > tr > th {
    background: #f3f6f9;
    font-size: 12px;
    color: #4a5560;
    border-bottom: 1px solid #dbe2ea;
}

.yuju-cron-manager-modal .table > tbody > tr > td {
    vertical-align: middle;
    font-size: 12px;
}

.yuju-cron-manager-modal code {
    background: #eef2f7;
    color: #2f3b4a;
    border: 1px solid #dbe2ea;
}

#yuju-cron-manager-alert {
    margin-bottom: 12px;
}

/* No usar .form-group: el BO PS8 lo pone en flex y deja Ampliar al lado del textarea */
.yuju-cron-manager-modal .yuju-cron-output-wrap {
    display: flex !important;
    flex-direction: column !important;
    width: 100% !important;
    margin: 0;
}
.yuju-cron-manager-modal .yuju-cron-output-head {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 8px;
    width: 100% !important;
    margin: 0 0 6px 0 !important;
}
.yuju-cron-manager-modal .yuju-cron-output-head label {
    margin: 0;
}

/* Salida tipo consola */
.yuju-cron-manager-modal textarea#yuju-cron-manager-output.form-control,
textarea#yuju-cron-manager-output.form-control {
    display: block;
    width: 100%;
    box-sizing: border-box;
    white-space: pre-wrap;
    font-family: Consolas, Monaco, monospace;
    background-color: #0f172a !important;
    color: #d6e0ff !important;
    border: 1px solid #1f2a44 !important;
    font-size: 12px;
    line-height: 1.35;
}

.yuju-cron-manager-modal textarea#yuju-cron-manager-output.form-control:hover,
.yuju-cron-manager-modal textarea#yuju-cron-manager-output.form-control:focus,
textarea#yuju-cron-manager-output.form-control:hover,
textarea#yuju-cron-manager-output.form-control:focus {
    background-color: #0f172a !important;
    color: #d6e0ff !important;
    border-color: #334155 !important;
    box-shadow: none !important;
}

/* Terminal encima de Administrar crons */
.yuju-cron-output-fullscreen-modal {
    z-index: 20060 !important;
}
.modal-backdrop.yuju-cron-output-backdrop {
    z-index: 20050 !important;
}
.yuju-cron-output-fullscreen-dialog {
    width: 96%;
    max-width: 1200px;
    margin: 20px auto;
}
.yuju-cron-output-fullscreen-modal .modal-body {
    min-height: 70vh;
}
.yuju-cron-output-fullscreen-modal textarea#yuju-cron-manager-output-fullscreen.form-control {
    width: 100%;
    min-height: 70vh;
    height: 70vh;
    resize: vertical;
    white-space: pre-wrap;
    font-family: Consolas, Monaco, monospace;
    font-size: 13px;
    line-height: 1.4;
    background-color: #0f172a !important;
    color: #d6e0ff !important;
    border: 1px solid #1f2a44 !important;
}
</style>
{/block}
