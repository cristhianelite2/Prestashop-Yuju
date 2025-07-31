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

{* Basic AJAX layout template for Yuju module *}
{if isset($content)}
    {$content|escape:'html':'UTF-8'}
{else}
    {if isset($json)}
        {$json|escape:'html':'UTF-8'}
    {else}
        {if isset($errors) && count($errors)}
            <div class="alert alert-danger">
                <ul>
                    {foreach from=$errors item=error}
                        <li>{$error|escape:'html':'UTF-8'}</li>
                    {/foreach}
                </ul>
            </div>
        {/if}
        
        {if isset($confirmations) && count($confirmations)}
            <div class="alert alert-success">
                <ul>
                    {foreach from=$confirmations item=confirmation}
                        <li>{$confirmation|escape:'html':'UTF-8'}</li>
                    {/foreach}
                </ul>
            </div>
        {/if}
        
        {if isset($warnings) && count($warnings)}
            <div class="alert alert-warning">
                <ul>
                    {foreach from=$warnings item=warning}
                        <li>{$warning|escape:'html':'UTF-8'}</li>
                    {/foreach}
                </ul>
            </div>
        {/if}
        
        {if isset($informations) && count($informations)}
            <div class="alert alert-info">
                <ul>
                    {foreach from=$informations item=information}
                        <li>{$information|escape:'html':'UTF-8'}</li>
                    {/foreach}
                </ul>
            </div>
        {/if}
    {/if}
{/if}