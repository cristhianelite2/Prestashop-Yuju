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

<div class="panel">
    <div class="panel-heading">
        <i class="icon-dashboard"></i>
        {l s='Yuju Integration Dashboard' mod='prestashopyuju'}
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-12">
                <h3>{l s='Welcome to Yuju Integration' mod='prestashopyuju'}</h3>
                <p>{l s='This module allows you to synchronize your PrestaShop store with the Yuju platform.' mod='prestashopyuju'}</p>
                
                <div class="alert alert-info">
                    <h4>{l s='Module Information' mod='prestashopyuju'}</h4>
                    <ul>
                        <li><strong>{l s='Module Name:' mod='prestashopyuju'}</strong> {$module_name|escape:'html':'UTF-8'}</li>
                        <li><strong>{l s='Version:' mod='prestashopyuju'}</strong> {$module_version|escape:'html':'UTF-8'}</li>
                        <li><strong>{l s='Status:' mod='prestashopyuju'}</strong> {l s='Active' mod='prestashopyuju'}</li>
                    </ul>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h4>{l s='Configuration' mod='prestashopyuju'}</h4>
                            </div>
                            <div class="panel-body">
                                <p>{l s='Configure your Yuju API settings and synchronization options.' mod='prestashopyuju'}</p>
                                <a href="{$link->getAdminLink('AdminYujuConfiguration')|escape:'html':'UTF-8'}" class="btn btn-primary">
                                    {l s='Configure' mod='prestashopyuju'}
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h4>{l s='Synchronization' mod='prestashopyuju'}</h4>
                            </div>
                            <div class="panel-body">
                                <p>{l s='Manage product, category, and order synchronization.' mod='prestashopyuju'}</p>
                                <a href="{$link->getAdminLink('AdminYujuSync')|escape:'html':'UTF-8'}" class="btn btn-primary">
                                    {l s='Synchronize' mod='prestashopyuju'}
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h4>{l s='Logs' mod='prestashopyuju'}</h4>
                            </div>
                            <div class="panel-body">
                                <p>{l s='View synchronization logs and troubleshoot issues.' mod='prestashopyuju'}</p>
                                <a href="{$link->getAdminLink('AdminYujuLogs')|escape:'html':'UTF-8'}" class="btn btn-primary">
                                    {l s='View Logs' mod='prestashopyuju'}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>