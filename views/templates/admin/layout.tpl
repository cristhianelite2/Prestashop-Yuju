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
                        <li class="{if $current_controller == 'AdminYuju'}active{/if}">
                            <a href="{$link->getAdminLink('AdminYuju')|escape:'html':'UTF-8'}">
                                <i class="icon-dashboard"></i>
                                Panel de Control
                            </a>
                        </li>
                        <li class="{if $current_controller == 'AdminYujuConfiguration'}active{/if}">
                            <a href="{$link->getAdminLink('AdminYujuConfiguration')|escape:'html':'UTF-8'}">
                                <i class="icon-cogs"></i>
                                Configuración
                            </a>
                        </li>
                        <li class="{if $current_controller == 'AdminYujuProductMapping'}active{/if}">
                            <a href="{$link->getAdminLink('AdminYujuProductMapping')|escape:'html':'UTF-8'}">
                                <i class="icon-shopping-cart"></i>
                                Mapeo de Campos
                            </a>
                        </li>
                        <li class="{if $current_controller == 'AdminYujuCategoryMapping'}active{/if}">
                            <a href="{$link->getAdminLink('AdminYujuCategoryMapping')|escape:'html':'UTF-8'}">
                                <i class="icon-folder"></i>
                                Mapeo de Categorías
                            </a>
                        </li>
                        <li class="{if $current_controller == 'AdminYujuAttributeMapping'}active{/if}">
                            <a href="{$link->getAdminLink('AdminYujuAttributeMapping')|escape:'html':'UTF-8'}">
                                <i class="icon-tags"></i>
                                Mapeo de Atributos
                            </a>
                        </li>
                        <li class="{if $current_controller == 'AdminYujuProductStatus'}active{/if}">
                            <a href="{$link->getAdminLink('AdminYujuProductStatus')|escape:'html':'UTF-8'}">
                                <i class="icon-info-circle"></i>
                                Estado de Productos
                            </a>
                        </li>
                        <li class="{if $current_controller == 'AdminYujuWebhook'}active{/if}">
                            <a href="{$link->getAdminLink('AdminYujuWebhook')|escape:'html':'UTF-8'}">
                                <i class="icon-link"></i>
                                Webhooks
                            </a>
                        </li>
                        <li class="{if $current_controller == 'AdminYujuLogs'}active{/if}">
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