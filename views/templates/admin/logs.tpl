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
                        <a href="{$current_index|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}&action=view&file={$view_filename|escape:'html':'UTF-8'}&lines={$view_lines}" class="btn btn-info" target="_blank">
                            <i class="icon-refresh"></i> Recargar
                        </a>
                        <a href="{$current_index|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}&action=view&file={$view_filename|escape:'html':'UTF-8'}&lines={$view_lines}&download=1" class="btn btn-default">
                            <i class="icon-download"></i> Descargar
                        </a>
                    </div>
                    
                    {* Estadísticas del archivo *}
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
                    
                    <div class="row" style="margin-bottom: 15px;">
                        <div class="col-lg-6">
                            <strong>Archivo:</strong> {$view_filename|escape:'html':'UTF-8'}<br>
                            <strong>Tamaño:</strong> {$log_stats.size_human}<br>
                            <strong>Última modificación:</strong> {$log_stats.modified}
                        </div>
                    </div>
                    
                    {* Contenido del log *}
                    <div class="panel panel-default">
                        <div class="panel-body" style="padding: 0; max-height: 70vh; overflow: auto;">
                            <pre id="log-content" style="margin: 0; padding: 15px; font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace; font-size: 12px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word; background: #1e1e1e; color: #d4d4d4; border: none;">{foreach $log_content as $line}{$line|escape:'html':'UTF-8'}
{/foreach}</pre>
                        </div>
                    </div>
                    
                    <div class="text-center" style="margin-top: 10px; color: #999; font-size: 12px;">
                        Mostrando las últimas {$view_lines} líneas de {$log_stats.lines} totales (aprox.)
                    </div>
                </div>
            </div>
            
            <script type="text/javascript">
            // Resaltado de sintaxis simple para logs
            document.addEventListener('DOMContentLoaded', function() {
                var pre = document.getElementById('log-content');
                if (!pre) return;
                
                var text = pre.textContent;
                var lines = text.split('\n');
                var html = '';
                
                lines.forEach(function(line) {
                    var color = '';
                    var lower = line.toLowerCase();
                    
                    if (lower.indexOf('] error') !== -1 || lower.indexOf('] error') !== -1 || lower.indexOf('exception') !== -1 || lower.indexOf('fatal') !== -1) {
                        color = '#ff6b6b'; // rojo para errores
                    } else if (lower.indexOf('] warning') !== -1 || lower.indexOf('] warn') !== -1) {
                        color = '#ffd93d'; // amarillo para warnings
                    } else if (lower.indexOf('] info') !== -1) {
                        color = '#74c0fc'; // azul para info
                    } else if (lower.indexOf('] debug') !== -1) {
                        color = '#868e96'; // gris para debug
                    }
                    
                    // Resaltar timestamps
                    line = line.replace(/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/, '<span style="color:#adb5bd">$1</span>');
                    // Resaltar IPs
                    line = line.replace(/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/g, '<span style="color:#ffa94d">$1</span>');
                    // Resaltar URLs
                    line = line.replace(/(https?:\/\/[^\s]+)/g, '<span style="color:#74c0fc"><u>$1</u></span>');
                    
                    if (color) {
                        html += '<span style="color:' + color + '">' + line + '</span>\n';
                    } else {
                        html += line + '\n';
                    }
                });
                
                pre.innerHTML = html;
            });
            
            // Dropdown para cambiar líneas
            document.querySelectorAll('.dropdown-menu[data-lines] a').forEach(function(link) {
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
            </script>
            
        {else}
            {* Lista de archivos de log *}
            <div class="alert alert-info">
                <i class="icon-info-circle"></i>
                Los archivos de log están protegidos por .htaccess y no son accesibles directamente por URL.
                Usa este visor para leerlos de forma segura.
            </div>
            
            {* Logs de archivo *}
            <h4><i class="icon-file"></i> Logs en archivos ({$logs|@count})</h4>
            
            {if $logs|@count > 0}
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Archivo</th>
                                <th>Ubicación</th>
                                <th>Tamaño</th>
                                <th>Modificado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $logs as $log}
                                <tr>
                                    <td><strong>{$log.filename|escape:'html':'UTF-8'}</strong></td>
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
            
            {* Logs de base de datos *}
            <h4><i class="icon-database"></i> Logs en Base de Datos (últimos 50)</h4>
            
            {if $db_logs|@count > 0}
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Nivel</th>
                                <th>Mensaje</th>
                                <th>Contexto</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $db_logs as $log}
                                <tr class="{if $log.level == 'error'}danger{elseif $log.level == 'warning'}warning{elseif $log.level == 'info'}info{/if}">
                                    <td>{$log.created_at|escape:'html':'UTF-8'}</td>
                                    <td>
                                        <span class="label label-{if $log.level == 'error'}danger{elseif $log.level == 'warning'}warning{elseif $log.level == 'info'}info{/if}">
                                            {$log.level|upper}
                                        </span>
                                    </td>
                                    <td>{$log.message|escape:'html':'UTF-8'|truncate:100}</td>
                                    <td>
                                        {if $log.context}
                                            <pre style="margin: 5px 0; max-height: 150px; overflow: auto; font-size: 11px;">{$log.context|json_encode|escape:'html':'UTF-8'}</pre>
                                        {/if}
                                    </td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
            {else}
                <div class="alert alert-info">
                    <i class="icon-info-circle"></i>
                    No hay logs en la base de datos.
                </div>
            {/if}
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