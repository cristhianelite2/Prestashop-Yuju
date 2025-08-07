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
        <i class="icon-dashboard"></i>
        Panel de Control de Integración Yuju
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-12">
                <h3>Bienvenido a la Integración Yuju</h3>
                <p>Este módulo le permite sincronizar su tienda PrestaShop con la plataforma Yuju.</p>
                
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
{/block}