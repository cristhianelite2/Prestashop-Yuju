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

<div class="panel panel-default yuju-module">
    <div class="panel-heading">
        <i class="icon-bar-chart"></i>
        {l s='Product Status Statistics' mod='prestashopyuju'}
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-3">
                <div class="product-stat-card text-center">
                    <h3 class="text-primary">{if isset($stats.total_products)}{$stats.total_products|intval}{else}0{/if}</h3>
                    <p class="text-muted">{l s='Total Products' mod='prestashopyuju'}</p>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="product-stat-card text-center">
                    <h3 class="text-success">{if isset($stats.synced_products)}{$stats.synced_products|intval}{else}0{/if}</h3>
                    <p class="text-muted">{l s='Synced' mod='prestashopyuju'}</p>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="product-stat-card text-center">
                    <h3 class="text-warning">{if isset($stats.pending_products)}{$stats.pending_products|intval}{else}0{/if}</h3>
                    <p class="text-muted">{l s='Pending' mod='prestashopyuju'}</p>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="product-stat-card text-center">
                    <h3 class="text-danger">{if isset($stats.error_products)}{$stats.error_products|intval}{else}0{/if}</h3>
                    <p class="text-muted">{l s='Errors' mod='prestashopyuju'}</p>
                </div>
            </div>
        </div>
        
        <div class="row" style="margin-top: 20px;">
            <div class="col-md-6">
                <div class="product-stat-card">
                    <h5>{l s='Sync Success Rate' mod='prestashopyuju'}</h5>
                    <div class="progress">
                        {assign var="sync_rate" value=0}
                        {if isset($stats.total_products) && $stats.total_products > 0}
                            {assign var="sync_rate" value=($stats.synced_products / $stats.total_products * 100)}
                        {/if}
                        <div class="progress-bar progress-bar-success" role="progressbar" 
                             style="width: {$sync_rate|string_format:"%.1f"|escape:'html':'UTF-8'}%">
                            {$sync_rate|string_format:"%.1f"|escape:'html':'UTF-8'}%
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="product-stat-card">
                    <h5>{l s='Last Sync' mod='prestashopyuju'}</h5>
                    <p class="text-info">
                        <strong>
                            {if isset($stats.last_sync_date)}
                                {$stats.last_sync_date|date_format:"%Y-%m-%d %H:%M"|escape:'html':'UTF-8'}
                            {else}
                                {l s='Never' mod='prestashopyuju'}
                            {/if}
                        </strong>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="row" style="margin-top: 20px;">
            <div class="col-md-12">
                <h5>{l s='Product Status by Category' mod='prestashopyuju'}</h5>
                {if isset($stats.category_stats) && count($stats.category_stats) > 0}
                    <div class="table-responsive">
                        <table class="table table-striped table-condensed">
                            <thead>
                                <tr>
                                    <th>{l s='Category' mod='prestashopyuju'}</th>
                                    <th class="text-center">{l s='Total' mod='prestashopyuju'}</th>
                                    <th class="text-center">{l s='Synced' mod='prestashopyuju'}</th>
                                    <th class="text-center">{l s='Pending' mod='prestashopyuju'}</th>
                                    <th class="text-center">{l s='Errors' mod='prestashopyuju'}</th>
                                    <th class="text-center">{l s='Sync Rate' mod='prestashopyuju'}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {foreach from=$stats.category_stats item=cat_stat}
                                    <tr>
                                        <td>
                                            <strong>{$cat_stat.category_name|escape:'html':'UTF-8'}</strong>
                                            <br><small class="text-muted">ID: {$cat_stat.category_id|escape:'html':'UTF-8'}</small>
                                        </td>
                                        <td class="text-center">{$cat_stat.total|intval}</td>
                                        <td class="text-center">
                                            <span class="text-success">{$cat_stat.synced|intval}</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="text-warning">{$cat_stat.pending|intval}</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="text-danger">{$cat_stat.errors|intval}</span>
                                        </td>
                                        <td class="text-center">
                                            {assign var="cat_sync_rate" value=0}
                                            {if $cat_stat.total > 0}
                                                {assign var="cat_sync_rate" value=($cat_stat.synced / $cat_stat.total * 100)}
                                            {/if}
                                            <span class="{if $cat_sync_rate >= 90}text-success{elseif $cat_sync_rate >= 70}text-warning{else}text-danger{/if}">
                                                {$cat_sync_rate|string_format:"%.1f"|escape:'html':'UTF-8'}%
                                            </span>
                                        </td>
                                    </tr>
                                {/foreach}
                            </tbody>
                        </table>
                    </div>
                {else}
                    <p class="text-muted">{l s='No category statistics available.' mod='prestashopyuju'}</p>
                {/if}
            </div>
        </div>
        
        <div class="row" style="margin-top: 20px;">
            <div class="col-md-6">
                <h5>{l s='Recent Sync Activity' mod='prestashopyuju'}</h5>
                {if isset($stats.recent_syncs) && count($stats.recent_syncs) > 0}
                    <div class="table-responsive">
                        <table class="table table-striped table-condensed">
                            <thead>
                                <tr>
                                    <th>{l s='Product' mod='prestashopyuju'}</th>
                                    <th>{l s='Status' mod='prestashopyuju'}</th>
                                    <th>{l s='Time' mod='prestashopyuju'}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {foreach from=$stats.recent_syncs item=sync}
                                    <tr>
                                        <td>
                                            <strong>{$sync.product_name|escape:'html':'UTF-8'}</strong>
                                            <br><small class="text-muted">ID: {$sync.product_id|escape:'html':'UTF-8'}</small>
                                        </td>
                                        <td>
                                            {if $sync.sync_status == 'synced'}
                                                <span class="label label-success">
                                                    <i class="icon-check"></i> {l s='Synced' mod='prestashopyuju'}
                                                </span>
                                            {elseif $sync.sync_status == 'pending'}
                                                <span class="label label-warning">
                                                    <i class="icon-clock-o"></i> {l s='Pending' mod='prestashopyuju'}
                                                </span>
                                            {elseif $sync.sync_status == 'error'}
                                                <span class="label label-danger">
                                                    <i class="icon-remove"></i> {l s='Error' mod='prestashopyuju'}
                                                </span>
                                            {else}
                                                <span class="label label-default">
                                                    <i class="icon-question"></i> {l s='Unknown' mod='prestashopyuju'}
                                                </span>
                                            {/if}
                                        </td>
                                        <td>
                                            {if $sync.last_sync_attempt}
                                                {$sync.last_sync_attempt|date_format:"%H:%M:%S"|escape:'html':'UTF-8'}
                                            {else}
                                                <span class="text-muted">Never</span>
                                            {/if}
                                        </td>
                                    </tr>
                                {/foreach}
                            </tbody>
                        </table>
                    </div>
                {else}
                    <p class="text-muted">{l s='No recent sync activity.' mod='prestashopyuju'}</p>
                {/if}
            </div>
            
            <div class="col-md-6">
                <h5>{l s='Sync Errors' mod='prestashopyuju'}</h5>
                {if isset($stats.recent_errors) && count($stats.recent_errors) > 0}
                    <div class="table-responsive">
                        <table class="table table-striped table-condensed">
                            <thead>
                                <tr>
                                    <th>{l s='Product' mod='prestashopyuju'}</th>
                                    <th>{l s='Error' mod='prestashopyuju'}</th>
                                    <th>{l s='Time' mod='prestashopyuju'}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {foreach from=$stats.recent_errors item=error}
                                    <tr>
                                        <td>
                                            <strong>{$error.product_name|escape:'html':'UTF-8'}</strong>
                                            <br><small class="text-muted">ID: {$error.product_id|escape:'html':'UTF-8'}</small>
                                        </td>
                                        <td>
                                            <small class="text-danger">
                                                {$error.error_message|escape:'html':'UTF-8'|truncate:50}
                                            </small>
                                        </td>
                                        <td>
                                            {if $error.error_date}
                                                {$error.error_date|date_format:"%H:%M:%S"|escape:'html':'UTF-8'}
                                            {else}
                                                <span class="text-muted">Unknown</span>
                                            {/if}
                                        </td>
                                    </tr>
                                {/foreach}
                            </tbody>
                        </table>
                    </div>
                {else}
                    <p class="text-muted">{l s='No recent sync errors.' mod='prestashopyuju'}</p>
                {/if}
            </div>
        </div>
    </div>
</div>

<style>
.product-stat-card {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 4px;
    margin-bottom: 10px;
}

.product-stat-card h3 {
    margin: 0 0 5px 0;
    font-weight: bold;
}

.product-stat-card h5 {
    margin: 0 0 10px 0;
    color: #333;
}
</style>