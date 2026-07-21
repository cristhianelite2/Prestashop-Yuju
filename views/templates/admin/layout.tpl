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

<div class="yuju-admin-layout">
    {assign var='yuju_current_controller' value=$current_controller|default:$smarty.get.controller|default:''}
    {assign var='yuju_is_mapping_section' value=false}
    {if $yuju_current_controller == 'AdminYujuProductMapping' || $yuju_current_controller == 'AdminYujuProductMappingController'
        || $yuju_current_controller == 'AdminYujuCategoryMapping' || $yuju_current_controller == 'AdminYujuCategoryMappingController'
        || $yuju_current_controller == 'AdminYujuAttributeMapping' || $yuju_current_controller == 'AdminYujuAttributeMappingController'
        || $yuju_current_controller == 'AdminYujuOrderStatusMapping' || $yuju_current_controller == 'AdminYujuOrderStatusMappingController'}
        {assign var='yuju_is_mapping_section' value=true}
    {/if}
    <div class="row">
        <!-- Menú lateral izquierdo -->
        <div class="col-md-3">
            <div class="panel yuju-sidebar">
                <div class="panel-heading">
                    <i class="icon-cogs"></i>
                    Integración Yuju
                </div>
                <div class="panel-body">
                    <ul class="nav nav-pills nav-stacked yuju-nav">
                        <li class="{if $yuju_current_controller == 'AdminYuju' || $yuju_current_controller == 'AdminYujuController'}active{/if}">
                            <a href="{$link->getAdminLink('AdminYuju')|escape:'html':'UTF-8'}">
                                <i class="icon-dashboard"></i>
                                Panel de Control
                            </a>
                        </li>
                        <li class="{if $yuju_current_controller == 'AdminYujuConfiguration' || $yuju_current_controller == 'AdminYujuConfigurationController'}active{/if}">
                            <a href="{$link->getAdminLink('AdminYujuConfiguration')|escape:'html':'UTF-8'}">
                                <i class="icon-cogs"></i>
                                Configuración
                            </a>
                        </li>

                        <li class="yuju-nav-group{if $yuju_is_mapping_section} open active-group{/if}">
                            <a href="#yuju-nav-mapeos" class="yuju-nav-toggle" data-toggle="collapse" aria-expanded="{if $yuju_is_mapping_section}true{else}false{/if}">
                                <i class="icon-random"></i>
                                <span>Mapeos</span>
                                <i class="icon-angle-down yuju-nav-caret pull-right"></i>
                            </a>
                            <ul id="yuju-nav-mapeos" class="nav yuju-nav-submenu collapse{if $yuju_is_mapping_section} in{/if}">
                                <li class="{if $yuju_current_controller == 'AdminYujuProductMapping' || $yuju_current_controller == 'AdminYujuProductMappingController'}active{/if}">
                                    <a href="{$link->getAdminLink('AdminYujuProductMapping')|escape:'html':'UTF-8'}">
                                        <i class="icon-shopping-cart"></i>
                                        Campos
                                    </a>
                                </li>
                                <li class="{if $yuju_current_controller == 'AdminYujuCategoryMapping' || $yuju_current_controller == 'AdminYujuCategoryMappingController'}active{/if}">
                                    <a href="{$link->getAdminLink('AdminYujuCategoryMapping')|escape:'html':'UTF-8'}">
                                        <i class="icon-folder"></i>
                                        Categorías
                                    </a>
                                </li>
                                <li class="{if $yuju_current_controller == 'AdminYujuAttributeMapping' || $yuju_current_controller == 'AdminYujuAttributeMappingController'}active{/if}">
                                    <a href="{$link->getAdminLink('AdminYujuAttributeMapping')|escape:'html':'UTF-8'}">
                                        <i class="icon-tags"></i>
                                        Atributos
                                    </a>
                                </li>
                                <li class="{if $yuju_current_controller == 'AdminYujuOrderStatusMapping' || $yuju_current_controller == 'AdminYujuOrderStatusMappingController'}active{/if}">
                                    <a href="{$link->getAdminLink('AdminYujuOrderStatusMapping')|escape:'html':'UTF-8'}">
                                        <i class="icon-exchange"></i>
                                        Estados
                                    </a>
                                </li>
                            </ul>
                        </li>

                        <li class="{if $yuju_current_controller == 'AdminYujuProductStatus' || $yuju_current_controller == 'AdminYujuProductStatusController'}active{/if}">
                            <a href="{$link->getAdminLink('AdminYujuProductStatus')|escape:'html':'UTF-8'}">
                                <i class="icon-info-circle"></i>
                                Estado de Productos
                            </a>
                        </li>
                        <li class="{if $yuju_current_controller == 'AdminYujuCategoryBulk' || $yuju_current_controller == 'AdminYujuCategoryBulkController'}active{/if}">
                            <a href="{$link->getAdminLink('AdminYujuCategoryBulk')|escape:'html':'UTF-8'}">
                                <i class="icon-sitemap"></i>
                                Acciones por categoría
                            </a>
                        </li>
                        <li class="{if $yuju_current_controller == 'AdminYujuAudit' || $yuju_current_controller == 'AdminYujuAuditController'}active{/if}">
                            <a href="{$link->getAdminLink('AdminYujuAudit')|escape:'html':'UTF-8'}">
                                <i class="icon-search"></i>
                                Auditoría
                            </a>
                        </li>
                        <li class="{if $yuju_current_controller == 'AdminYujuAuditMonitoring' || $yuju_current_controller == 'AdminYujuAuditMonitoringController'}active{/if}">
                            <a href="{$link->getAdminLink('AdminYujuAuditMonitoring')|escape:'html':'UTF-8'}">
                                <i class="icon-bar-chart"></i>
                                Monitoreo Auditoría
                            </a>
                        </li>
                        <li class="{if $yuju_current_controller == 'AdminYujuWebhook' || $yuju_current_controller == 'AdminYujuWebhookController'}active{/if}">
                            <a href="{$link->getAdminLink('AdminYujuWebhook')|escape:'html':'UTF-8'}">
                                <i class="icon-link"></i>
                                Webhooks
                            </a>
                        </li>
                        <li class="{if $yuju_current_controller == 'AdminYujuLogs' || $yuju_current_controller == 'AdminYujuLogsController'}active{/if}">
                            <a href="{$link->getAdminLink('AdminYujuLogs')|escape:'html':'UTF-8'}">
                                <i class="icon-file-text"></i>
                                Registros
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Contenido principal -->
        <div class="col-md-9">
            {block name="content"}
                <!-- El contenido específico de cada página se insertará aquí -->
            {/block}
        </div>
    </div>
</div>
