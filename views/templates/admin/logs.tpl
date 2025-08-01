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