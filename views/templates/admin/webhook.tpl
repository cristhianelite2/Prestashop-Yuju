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
<div class="yuju-webhook-container">
<div class="panel yuju-webhook-shell">
    <div class="panel-heading">
        <i class="icon-link"></i>
        Gestión de Webhooks
        {if isset($webhook_status)}
            <span class="pull-right">
                <span class="label {if $webhook_status == 'active'}label-success{elseif $webhook_status == 'error'}label-danger{else}label-warning{/if}">
                    <i class="icon-{if $webhook_status == 'active'}check{elseif $webhook_status == 'error'}times{else}clock-o{/if}"></i>
                    {if $webhook_status == 'active'}Activo{elseif $webhook_status == 'error'}Error{else}Inactivo{/if}
                </span>
            </span>
        {/if}
    </div>
    <div class="panel-body yuju-webhook-content">
        {$content}
    </div>
</div>
</div>

<style>
/* Estilo alineado con el resto del módulo Yuju: neutro, compacto y sin degradados. */
.yuju-webhook-container {
    min-height: 0;
    margin: 0;
    padding: 0;
    background: transparent;
}
.yuju-webhook-header {
    margin: 0 0 15px;
    padding: 0 0 14px;
    background: transparent;
    border: 0;
    border-bottom: 1px solid #eee;
    border-radius: 0;
    box-shadow: none;
    backdrop-filter: none;
}
.yuju-page-title {
    display: flex;
    align-items: center;
    gap: 9px;
    margin: 0;
    color: #444;
    font-size: 20px;
    font-weight: 500;
}
.yuju-page-title i {
    color: #777;
    font-size: 18px;
    background: none;
    -webkit-text-fill-color: currentColor;
}
.yuju-page-subtitle {
    margin: 6px 0 0;
    color: #777;
    font-size: 13px;
    font-weight: 400;
}
.yuju-status-indicator {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 5px 9px;
    background: #f7f7f7;
    border: 1px solid #e5e5e5;
    border-radius: 3px;
}
.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    animation: none;
}
.status-dot.status-active {
    background: #5cb85c;
    box-shadow: none;
}
.status-text {
    color: #555;
    font-size: 12px;
    font-weight: 500;
}
@keyframes pulse {
    0%, 70%, 100% { box-shadow: none; }
}
.yuju-webhook-content {
    overflow: visible;
    padding: 0;
    background: transparent;
    border: 0;
    border-radius: 0;
    box-shadow: none;
    backdrop-filter: none;
}
.yuju-webhook-content .panel {
    margin-bottom: 15px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 3px;
    box-shadow: none;
}
.yuju-webhook-content .panel-heading {
    padding: 10px 15px;
    color: #555;
    background: #f7f7f7;
    border-bottom: 1px solid #ddd;
    font-size: 14px;
    font-weight: 500;
}
.yuju-webhook-content .panel-body {
    padding: 15px;
    background: #fff;
}
.yuju-order-search-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin: 0 0 12px;
    padding: 10px 12px;
    background: #fafafa;
    border: 1px solid #e5e5e5;
    border-radius: 3px;
}
.yuju-order-search-bar label {
    margin: 0 4px 0 0;
    color: #555;
    font-size: 12px;
    font-weight: 500;
}
.yuju-order-search-bar .form-control {
    display: inline-block;
    width: 160px;
    height: 30px;
    padding: 4px 8px;
    font-size: 12px;
}
.yuju-order-search-bar .btn {
    padding: 5px 10px;
    font-size: 12px;
    line-height: 1.3;
}
.yuju-order-search-bar .checkbox-inline {
    margin: 0 8px 0 0;
    padding-top: 0;
    font-size: 12px;
    font-weight: normal;
}
@media (max-width: 768px) {
    .yuju-webhook-header { padding-bottom: 10px; }
    .yuju-page-title { font-size: 18px; }
    .yuju-webhook-content .panel-body { padding: 10px; }
    .yuju-order-search-bar .form-control { width: 100%; }
}
</style>
{/block}