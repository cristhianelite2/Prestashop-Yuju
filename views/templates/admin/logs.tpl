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
<!-- Panel de Estadísticas de Cola -->
{if isset($queue_stats)}
<div class="panel">
    <div class="panel-heading">
        <i class="icon-list"></i> Estado de Cola de Sincronización
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-info">
                    <i class="icon-info-circle"></i> 
                    <strong>Sistema de Lotes Activo:</strong> Los cambios en productos (excepto precio/stock) se procesan por lotes cada 5 minutos.
                </div>
            </div>
        </div>
        <div class="row text-center">
            <div class="col-md-2">
                <div class="panel" style="border: 2px solid #ddd;">
                    <div class="panel-body">
                        <h3 style="margin: 0; color: #666;">
                            <i class="icon-database"></i> {$queue_stats.total}
                        </h3>
                        <p style="margin: 5px 0 0 0; color: #999;">Total en Cola</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="panel" style="border: 2px solid #f0ad4e;">
                    <div class="panel-body">
                        <h3 style="margin: 0; color: #f0ad4e;">
                            <i class="icon-clock-o"></i> {$queue_stats.pending}
                        </h3>
                        <p style="margin: 5px 0 0 0; color: #999;">Pendientes</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="panel" style="border: 2px solid #5bc0de;">
                    <div class="panel-body">
                        <h3 style="margin: 0; color: #5bc0de;">
                            <i class="icon-refresh icon-spin"></i> {$queue_stats.processing}
                        </h3>
                        <p style="margin: 5px 0 0 0; color: #999;">Procesando</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="panel" style="border: 2px solid #5cb85c;">
                    <div class="panel-body">
                        <h3 style="margin: 0; color: #5cb85c;">
                            <i class="icon-check"></i> {$queue_stats.completed}
                        </h3>
                        <p style="margin: 5px 0 0 0; color: #999;">Completados</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="panel" style="border: 2px solid #d9534f;">
                    <div class="panel-body">
                        <h3 style="margin: 0; color: #d9534f;">
                            <i class="icon-exclamation-triangle"></i> {$queue_stats.failed}
                        </h3>
                        <p style="margin: 5px 0 0 0; color: #999;">Fallidos</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="panel" style="border: 2px solid #5bc0de;">
                    <div class="panel-body">
                        <h3 style="margin: 0; color: #5bc0de;">
                            <i class="icon-list"></i> {$queue_stats.queued_products}
                        </h3>
                        <p style="margin: 5px 0 0 0; color: #999;">Productos en Cola</p>
                    </div>
                </div>
            </div>
        </div>
        {if $queue_stats.pending > 0}
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-warning">
                    <i class="icon-info-circle"></i> 
                    <strong>Próximo procesamiento:</strong> {$queue_stats.pending} productos serán procesados en el próximo ciclo del cron (cada 5 minutos).
                    <br>
                    <small>Los cambios de <strong>precio y stock</strong> se sincronizan inmediatamente sin pasar por la cola.</small>
                </div>
            </div>
        </div>
        {/if}
    </div>
</div>
{/if}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-file-text"></i>
        Registros de Integración Yuju
    </div>
    <div class="panel-body">
        {if $logs && count($logs) > 0}
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Archivo de Registro</th>
                            <th>Tamaño</th>
                            <th>Última Modificación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$logs item=log}
                            <tr>
                                <td>{$log.filename|escape:'html':'UTF-8'}</td>
                                <td>{($log.size/1024)|string_format:"%.2f"|escape:'html':'UTF-8'} KB</td>
                                <td>{$log.modified|date_format:"%Y-%m-%d %H:%M:%S"|escape:'html':'UTF-8'}</td>
                                <td>
                                    <a href="{$module_dir|escape:'html':'UTF-8'}logs/{$log.filename|escape:'html':'UTF-8'}" target="_blank" class="btn btn-sm btn-default">
                                        <i class="icon-eye"></i> Ver
                                    </a>
                                    <a href="{$module_dir|escape:'html':'UTF-8'}logs/{$log.filename|escape:'html':'UTF-8'}" download class="btn btn-sm btn-primary">
                                        <i class="icon-download"></i> Descargar
                                    </a>
                                </td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        {else}
            <div class="alert alert-info">
                <h4>No se encontraron registros</h4>
                <p>Aún no se han creado archivos de registro. Los registros aparecerán aquí una vez que comiencen las actividades de sincronización.</p>
            </div>
        {/if}
        
        <div class="panel panel-default">
            <div class="panel-heading">
                <h4>Información de Registros</h4>
            </div>
            <div class="panel-body">
                <p>Los archivos de registro se crean automáticamente durante los procesos de sincronización. Contienen información detallada sobre:</p>
                <ul>
                    <li>Solicitudes y respuestas de API</li>
                    <li>Resultados de sincronización</li>
                    <li>Mensajes de error e información de depuración</li>
                    <li>Métricas de rendimiento</li>
                </ul>
                
                <div class="alert alert-warning">
                    <strong>Nota:</strong>
                    Los archivos de registro se rotan automáticamente y los archivos antiguos se eliminan según su configuración.
                </div>
            </div>
        </div>
    </div>
</div>
{/block}