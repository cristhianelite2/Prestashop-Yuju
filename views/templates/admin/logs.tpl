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
        <i class="icon-file-text"></i>
        Visor de Logs - Yuju Integration
    </div>

    <div class="panel-body">
        {if $current_action === 'view'}
            {* Vista de archivo individual *}
            <div class="row">
                <div class="col-lg-12">
                    <div class="btn-toolbar" style="margin-bottom: 20px;">
                        <a href="{$current_index|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}" class="btn btn-default">
                            <i class="icon-arrow-left"></i> Volver a la lista
                        </a>
                        {if !$view_is_json}
                        <div class="btn-group pull-right">
                            <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown">
                                Líneas: <span class="current-lines">{$view_lines}</span> <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a href="#" data-lines="100">100</a></li>
                                <li><a href="#" data-lines="500">500</a></li>
                                <li><a href="#" data-lines="1000">1000</a></li>
                                <li><a href="#" data-lines="2000">2000</a></li>
                                <li><a href="#" data-lines="5000">5000</a></li>
                            </ul>
                        </div>
                        {/if}
                        <a href="{$current_index|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}&action=view&file={$view_filename|escape:'html':'UTF-8'}&lines={$view_lines}" class="btn btn-info" target="_blank">
                            <i class="icon-refresh"></i> Recargar
                        </a>
                        <a href="{$current_index|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}&action=view&file={$view_filename|escape:'html':'UTF-8'}&lines={$view_lines}&download=1" class="btn btn-default">
                            <i class="icon-download"></i> Descargar
                        </a>
                        {if $view_is_json}
                        <a href="{$current_index|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}&action=oauth" class="btn btn-default">
                            <i class="icon-time"></i> Historial de intentos
                        </a>
                        {/if}
                    </div>

                    {* Estadísticas del archivo *}
                    {if $view_is_json}
                        <div class="row" style="margin-bottom: 15px;">
                            <div class="col-lg-3">
                                <div class="panel {if $log_stats.errors}panel-danger{elseif $log_stats.info}panel-success{else}panel-warning{/if}">
                                    <div class="panel-body text-center">
                                        <h3>
                                            {if $log_stats.errors}
                                                <span class="label label-danger">ERROR</span>
                                            {elseif $log_stats.info}
                                                <span class="label label-success">OK</span>
                                            {else}
                                                <span class="label label-warning">?</span>
                                            {/if}
                                        </h3>
                                        <small>Estado del intento OAuth</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3">
                                <div class="panel panel-default">
                                    <div class="panel-body text-center">
                                        <h3 class="text-primary">{$log_stats.lines}</h3>
                                        <small>Campos JSON</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    {else}
                        <div class="row" style="margin-bottom: 15px;">
                            <div class="col-lg-3">
                                <div class="panel panel-default">
                                    <div class="panel-body text-center">
                                        <h3 class="text-primary">{$log_stats.lines}</h3>
                                        <small>Líneas mostradas</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3">
                                <div class="panel panel-danger">
                                    <div class="panel-body text-center">
                                        <h3>{$log_stats.errors}</h3>
                                        <small>Errores</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3">
                                <div class="panel panel-warning">
                                    <div class="panel-body text-center">
                                        <h3>{$log_stats.warnings}</h3>
                                        <small>Advertencias</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3">
                                <div class="panel panel-info">
                                    <div class="panel-body text-center">
                                        <h3>{$log_stats.info}</h3>
                                        <small>Info</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    {/if}

                    <div class="row" style="margin-bottom: 15px;">
                        <div class="col-lg-6">
                            <strong>Archivo:</strong> {$view_filename|escape:'html':'UTF-8'}
                            {if $view_is_json}<span class="label label-info">OAuth JSON</span>{else}<span class="label label-default">Log</span>{/if}<br>
                            <strong>Tamaño:</strong> {$log_stats.size_human}<br>
                            <strong>Última modificación:</strong> {$log_stats.modified}
                        </div>
                    </div>

                    {* Contenido del log *}
                    <div class="panel panel-default">
                        <div class="panel-body" style="padding: 0; max-height: 70vh; overflow: auto;">
                            <pre id="log-content" data-json="{if $view_is_json}1{else}0{/if}" style="margin: 0; padding: 15px; font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace; font-size: 12px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word; background: #1e1e1e; color: #d4d4d4; border: none;">{foreach $log_content as $line}{$line|escape:'html':'UTF-8'}
{/foreach}</pre>
                        </div>
                    </div>

                    <div class="text-center" style="margin-top: 10px; color: #999; font-size: 12px;">
                        {if $view_is_json}
                            Detalle completo del intento OAuth registrado en archivo.
                        {else}
                            Mostrando las últimas {$view_lines} líneas de {$log_stats.lines} totales (aprox.)
                        {/if}
                    </div>
                </div>
            </div>

<script type="text/javascript">
{literal}
// Resaltado de sintaxis simple para logs de texto
document.addEventListener('DOMContentLoaded', function() {
    var pre = document.getElementById('log-content');
    if (!pre) return;

    // Los JSON ya llegan formateados; no re-resaltar para no romperlos.
    if (pre.getAttribute('data-json') === '1') return;

    var text = pre.textContent;
    var lines = text.split('\n');
    var html = '';

    lines.forEach(function(line) {
        var color = '';
        var lower = line.toLowerCase();

        if (lower.indexOf(' error') !== -1 || lower.indexOf('critical') !== -1 || lower.indexOf('exception') !== -1 || lower.indexOf('fatal') !== -1) {
            color = '#ff6b6b'; // rojo para errores
        } else if (lower.indexOf(' warning') !== -1 || lower.indexOf(' warn') !== -1) {
            color = '#ffd93d'; // amarillo para warnings
        } else if (lower.indexOf(' info') !== -1) {
            color = '#74c0fc'; // azul para info
        } else if (lower.indexOf(' debug') !== -1) {
            color = '#868e96'; // gris para debug
        }

        // Escapar para no inyectar HTML desde el contenido del log
        var safe = line
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

        // Resaltar timestamps
        safe = safe.replace(/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/, '<span style="color:#adb5bd">$1</span>');
        // Resaltar IPs
        safe = safe.replace(/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/g, '<span style="color:#ffa94d">$1</span>');
        // Resaltar URLs
        safe = safe.replace(/(https?:\/\/[^\s]+)/g, '<span style="color:#74c0fc"><u>$1</u></span>');

        if (color) {
            html += '<span style="color:' + color + '">' + safe + '</span>\n';
        } else {
            html += safe + '\n';
        }
    });

    pre.innerHTML = html;
});

// Dropdown para cambiar líneas (data-lines está en el <a>, dentro de .dropdown-menu)
document.querySelectorAll('.dropdown-menu a[data-lines]').forEach(function(link) {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        var lines = this.getAttribute('data-lines');
        var url = window.location.href.replace(/lines=\d+/, 'lines=' + lines);
        if (!url.match(/lines=\d+/)) {
            url += (url.indexOf('?') === -1 ? '?' : '&') + 'lines=' + lines;
        }
        window.location.href = url;
    });
});
{/literal}
</script>

        {else}
            {* Lista de archivos de log (100% por archivo, no hay lectura de base de datos) *}
            <div class="alert alert-info">
                <i class="icon-info-circle"></i>
                Los archivos de log están protegidos por .htaccess y no son accesibles directamente por URL.
                Usa este visor para leerlos de forma segura. Los intentos de conexión OAuth se guardan como JSON.
            </div>

            {if $oauth_group}
                {* Grupo único con todos los intentos OAuth *}
                <div class="panel panel-warning yuju-oauth-group">
                    <div class="panel-heading">
                        <i class="icon-lock"></i> Intentos de conexión OAuth
                        <span class="badge pull-right">{$oauth_group.total|intval} intento{if $oauth_group.total != 1}s{/if}</span>
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-lg-2 col-md-3 col-sm-4 col-xs-6">
                                <div class="text-center yuju-oauth-stat">
                                    <h3 class="text-danger">{$oauth_group.failed|intval}</h3>
                                    <small>Intentos fallidos</small>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-3 col-sm-4 col-xs-6">
                                <div class="text-center yuju-oauth-stat">
                                    <h3 class="text-success">{$oauth_group.success|intval}</h3>
                                    <small>Exitosos</small>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-3 col-sm-4 col-xs-6">
                                <div class="text-center yuju-oauth-stat">
                                    <h3 class="text-muted">{$oauth_group.pending|intval}</h3>
                                    <small>Sin resultado</small>
                                </div>
                            </div>
                            <div class="col-lg-6 col-md-12">
                                {if $oauth_group.latest}
                                    <strong>Último intento:</strong>
                                    {if $oauth_group.latest.is_failed}
                                        <span class="label label-danger">FALLIDO</span>
                                    {elseif $oauth_group.latest.status == 'success'}
                                        <span class="label label-success">EXITOSO</span>
                                    {else}
                                        <span class="label label-warning">{$oauth_group.latest.status|escape:'html':'UTF-8'|upper}</span>
                                    {/if}
                                    <small class="text-muted">{$oauth_group.latest.created_at|escape:'html':'UTF-8'}</small><br>
                                    {if $oauth_group.latest.http_code}
                                        <small><strong>HTTP:</strong> {$oauth_group.latest.http_code|intval}</small>
                                    {/if}
                                    {if $oauth_group.latest.curl_error}
                                        <small class="text-danger"><strong>cURL:</strong> {$oauth_group.latest.curl_error|escape:'html':'UTF-8'}</small>
                                    {elseif $oauth_group.latest.message}
                                        <small class="text-muted">{$oauth_group.latest.message|escape:'html':'UTF-8'}</small>
                                    {/if}
                                {/if}
                            </div>
                        </div>

                        <hr style="margin: 15px 0;">

                        <a href="{$current_index|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}&action=view&file={$oauth_group.latest.full_path|escape:'html':'UTF-8'}" class="btn btn-primary">
                            <i class="icon-eye-open"></i> Ver último intento
                        </a>
                        <a href="{$current_index|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}&action=oauth" class="btn btn-default">
                            <i class="icon-time"></i> Ver anteriores ({$oauth_group.total|intval} en total)
                        </a>
                        <a href="{$current_index|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}&action=view&file={$oauth_group.latest.full_path|escape:'html':'UTF-8'}&download=1" class="btn btn-default">
                            <i class="icon-download"></i> Descargar último
                        </a>
                    </div>
                </div>
            {/if}

            <h4><i class="icon-file"></i> Otros archivos de log ({$logs|@count})</h4>

            {if $logs|@count > 0}
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Archivo</th>
                                <th>Tipo</th>
                                <th>Ubicación</th>
                                <th>Tamaño</th>
                                <th>Modificado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $logs as $log}
                                <tr class="{if $log.is_error}danger{elseif $log.type == 'json'}info{/if}">
                                    <td>
                                        <strong>{$log.filename|escape:'html':'UTF-8'}</strong>
                                        {if $log.type == 'json'}<br><small class="text-muted">Intento OAuth</small>{/if}
                                    </td>
                                    <td>
                                        {if $log.type == 'json'}
                                            <span class="label label-info">OAuth JSON</span>
                                        {else}
                                            <span class="label label-default">LOG</span>
                                        {/if}
                                    </td>
                                    <td><code>{$log.subdir|escape:'html':'UTF-8'}</code></td>
                                    <td>{$log.size_human|escape:'html':'UTF-8'}</td>
                                    <td>{$log.modified|escape:'html':'UTF-8'}</td>
                                    <td>
                                        <a href="{$current_index|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}&action=view&file={$log.full_path|escape:'html':'UTF-8'}" class="btn btn-xs btn-default" title="Ver">
                                            <i class="icon-eye-open"></i> Ver
                                        </a>
                                        <a href="{$current_index|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}&action=view&file={$log.full_path|escape:'html':'UTF-8'}&download=1" class="btn btn-xs btn-default" title="Descargar">
                                            <i class="icon-download"></i>
                                        </a>
                                    </td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
            {else}
                <div class="alert alert-warning">
                    <i class="icon-warning-sign"></i>
                    No se encontraron archivos de log en <code>{$module_dir|escape:'html':'UTF-8'}logs/</code>
                </div>
            {/if}

<hr style="margin: 30px 0;">

        {/if}

        {if $current_action === 'oauth'}
            {* Historial agrupado de intentos OAuth *}
            <div class="row">
                <div class="col-lg-12">
                    <div class="btn-toolbar" style="margin-bottom: 20px;">
                        <a href="{$current_index|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}" class="btn btn-default">
                            <i class="icon-arrow-left"></i> Volver a la lista
                        </a>
                    </div>

                    <div class="row" style="margin-bottom: 15px;">
                        <div class="col-lg-3">
                            <div class="panel panel-default">
                                <div class="panel-body text-center">
                                    <h3 class="text-primary">{$oauth_total|intval}</h3>
                                    <small>Intentos registrados</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="panel panel-danger">
                                <div class="panel-body text-center">
                                    <h3>{$oauth_failed|intval}</h3>
                                    <small>Intentos fallidos</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="panel panel-success">
                                <div class="panel-body text-center">
                                    <h3>{$oauth_success|intval}</h3>
                                    <small>Exitosos</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="panel panel-default">
                                <div class="panel-body text-center">
                                    <h3 class="text-muted">{$oauth_page|intval}/{$oauth_pages|intval}</h3>
                                    <small>Página</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h4><i class="icon-lock"></i> Historial de intentos OAuth</h4>

                    {if $oauth_attempts|@count > 0}
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Estado</th>
                                        <th>Fecha</th>
                                        <th>HTTP</th>
                                        <th>Mensaje / Error</th>
                                        <th>Client ID</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {foreach $oauth_attempts as $attempt}
                                        <tr class="{if $attempt.is_failed}danger{elseif $attempt.status == 'success'}success{else}warning{/if}">
                                            <td><strong>{$attempt.attempt_id|escape:'html':'UTF-8'}</strong></td>
                                            <td>
                                                {if $attempt.is_failed}
                                                    <span class="label label-danger">FALLIDO</span>
                                                {elseif $attempt.status == 'success'}
                                                    <span class="label label-success">EXITOSO</span>
                                                {else}
                                                    <span class="label label-warning">{$attempt.status|escape:'html':'UTF-8'|upper}</span>
                                                {/if}
                                            </td>
                                            <td>{$attempt.created_at|escape:'html':'UTF-8'}</td>
                                            <td>{$attempt.http_code|intval}</td>
                                            <td>
                                                {if $attempt.curl_error}
                                                    <small class="text-danger">{$attempt.curl_error|escape:'html':'UTF-8'}</small>
                                                {elseif $attempt.message}
                                                    <small>{$attempt.message|escape:'html':'UTF-8'}</small>
                                                {else}
                                                    <small class="text-muted">—</small>
                                                {/if}
                                            </td>
                                            <td><small><code>{$attempt.client_id|escape:'html':'UTF-8'}</code></small></td>
                                            <td>
                                                <a href="{$current_index|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}&action=view&file={$attempt.full_path|escape:'html':'UTF-8'}" class="btn btn-xs btn-default" title="Ver detalle">
                                                    <i class="icon-eye-open"></i> Ver
                                                </a>
                                                <a href="{$current_index|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}&action=view&file={$attempt.full_path|escape:'html':'UTF-8'}&download=1" class="btn btn-xs btn-default" title="Descargar">
                                                    <i class="icon-download"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    {/foreach}
                                </tbody>
                            </table>
                        </div>

                        {if $oauth_pages > 1}
                            <nav class="text-center">
                                <ul class="pagination">
                                    <li class="{if $oauth_page <= 1}disabled{/if}">
                                        <a href="{$current_index|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}&action=oauth&page={$oauth_page_prev}">&laquo;</a>
                                    </li>
                                    {foreach $oauth_pager as $p}
                                        <li class="{if $p == $oauth_page}active{/if}">
                                            <a href="{$current_index|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}&action=oauth&page={$p}">{$p}</a>
                                        </li>
                                    {/foreach}
                                    <li class="{if $oauth_page >= $oauth_pages}disabled{/if}">
                                        <a href="{$current_index|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}&action=oauth&page={$oauth_page_next}">&raquo;</a>
                                    </li>
                                </ul>
                            </nav>
                        {/if}
                    {else}
                        <div class="alert alert-warning">
                            <i class="icon-warning-sign"></i>
                            No se han registrado intentos de conexión OAuth todavía.
                        </div>
                    {/if}
                </div>
            </div>

        {/if}
    </div>
</div>
{/block}

<style>
/* Log viewer specific styles */
#log-content {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace !important;
}

#log-content span {
    display: block;
    padding: 2px 0;
    border-bottom: 1px solid #2d2d2d;
}

#log-content span:last-child {
    border-bottom: none;
}

.table > tbody > tr.danger > td {
    background-color: #fdf2f2 !important;
}

.table > tbody > tr.warning > td {
    background-color: #fffbf2 !important;
}

.table > tbody > tr.info > td {
    background-color: #f2f8fd !important;
}

.btn-toolbar .btn-group {
    margin-left: 10px;
}

/* Grupo de intentos OAuth */
.yuju-oauth-group .yuju-oauth-stat {
    padding: 5px 0 10px;
    border-right: 1px solid #eee;
}

.yuju-oauth-group .yuju-oauth-stat h3 {
    margin: 0 0 5px;
    font-size: 26px;
}

@media (max-width: 768px) {
    .yuju-oauth-group .yuju-oauth-stat {
        border-right: none;
        border-bottom: 1px solid #eee;
    }
}

.panel-body .pre-scrollable {
    max-height: 70vh;
    overflow: auto;
}

/* Responsive */
@media (max-width: 768px) {
    .btn-toolbar .pull-right {
        float: none !important;
        margin-top: 10px;
    }
}
</style>
