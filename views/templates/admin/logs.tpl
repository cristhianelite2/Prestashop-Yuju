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
        <i class="icon-file-text"></i>
        {l s='Yuju Integration Logs' mod='prestashopyuju'}
    </div>
    <div class="panel-body">
        {if $logs && count($logs) > 0}
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>{l s='Log File' mod='prestashopyuju'}</th>
                            <th>{l s='Size' mod='prestashopyuju'}</th>
                            <th>{l s='Last Modified' mod='prestashopyuju'}</th>
                            <th>{l s='Actions' mod='prestashopyuju'}</th>
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
                                        <i class="icon-eye"></i> {l s='View' mod='prestashopyuju'}
                                    </a>
                                    <a href="{$module_dir|escape:'html':'UTF-8'}logs/{$log.filename|escape:'html':'UTF-8'}" download class="btn btn-sm btn-primary">
                                        <i class="icon-download"></i> {l s='Download' mod='prestashopyuju'}
                                    </a>
                                </td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        {else}
            <div class="alert alert-info">
                <h4>{l s='No logs found' mod='prestashopyuju'}</h4>
                <p>{l s='No log files have been created yet. Logs will appear here once synchronization activities begin.' mod='prestashopyuju'}</p>
            </div>
        {/if}
        
        <div class="panel panel-default">
            <div class="panel-heading">
                <h4>{l s='Log Information' mod='prestashopyuju'}</h4>
            </div>
            <div class="panel-body">
                <p>{l s='Log files are automatically created during synchronization processes. They contain detailed information about:' mod='prestashopyuju'}</p>
                <ul>
                    <li>{l s='API requests and responses' mod='prestashopyuju'}</li>
                    <li>{l s='Synchronization results' mod='prestashopyuju'}</li>
                    <li>{l s='Error messages and debugging information' mod='prestashopyuju'}</li>
                    <li>{l s='Performance metrics' mod='prestashopyuju'}</li>
                </ul>
                
                <div class="alert alert-warning">
                    <strong>{l s='Note:' mod='prestashopyuju'}</strong>
                    {l s='Log files are automatically rotated and old files are deleted according to your configuration settings.' mod='prestashopyuju'}
                </div>
            </div>
        </div>
    </div>
</div>