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
<div class="panel panel-default yuju-dashboard-panel yuju-webhook-shell">
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
{literal}
/* Webhooks comparte la presentación sobria del panel principal de Yuju. */
.yuju-webhook-container{margin:0;padding:0;background:transparent}
.yuju-webhook-shell{margin-bottom:15px;border:1px solid #ddd;border-radius:4px;box-shadow:0 1px 3px rgba(0,0,0,.08);overflow:hidden}
.yuju-webhook-shell>.panel-heading{position:relative;padding:6px 10px;color:#555;background:#f8f9fa;border-bottom:1px solid #ddd;border-radius:0;font-size:13px;font-weight:600;line-height:1.4}
.yuju-webhook-shell>.panel-heading>i{margin-right:4px;color:#777}
.yuju-webhook-shell>.panel-heading .label{font-size:10px;font-weight:600;vertical-align:middle}
.yuju-webhook-shell>.panel-body.yuju-webhook-content{padding:12px;background:#fff}
.yuju-webhook-content{overflow:visible;color:#555}
.yuju-webhook-content>.alert{margin-bottom:12px;border-radius:3px;box-shadow:none}
.yuju-webhook-content .panel{margin-bottom:12px;border:1px solid #ddd;border-radius:4px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
.yuju-webhook-content .panel-heading{padding:6px 10px;color:#555;background:#f8f9fa;border-bottom:1px solid #ddd;font-size:13px;font-weight:600}
.yuju-webhook-content .panel-body{padding:12px;background:#fff}
.yuju-webhook-content .table{margin-bottom:0}
.yuju-webhook-content .table>thead>tr>th{background:#f8f9fa;color:#555;border-bottom:1px solid #ddd;font-weight:600}
.yuju-webhook-content .pagination>li>a,.yuju-webhook-content .pagination>li>span{border-radius:2px}
.yuju-webhook-header{margin:0 0 12px;padding:0 0 10px;background:transparent;border:0;border-bottom:1px solid #eee;border-radius:0;box-shadow:none;backdrop-filter:none}
.yuju-page-title{display:flex;align-items:center;gap:8px;margin:0;color:#444;font-size:18px;font-weight:600}
.yuju-page-title i{color:#777;font-size:16px;background:none;-webkit-text-fill-color:currentColor}
.yuju-page-subtitle{margin:5px 0 0;color:#777;font-size:12px;font-weight:400}
.yuju-status-indicator{display:inline-flex;align-items:center;gap:6px;padding:4px 8px;background:#f8f9fa;border:1px solid #e5e5e5;border-radius:3px}
.status-dot{width:7px;height:7px;border-radius:50%;animation:none}
.status-dot.status-active{background:#5cb85c;box-shadow:none}
.status-text{color:#555;font-size:12px;font-weight:500}
.yuju-order-search-bar{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin:0 0 12px;padding:9px 10px;background:#f8f9fa;border:1px solid #e5e5e5;border-radius:3px}
.yuju-order-search-bar label{margin:0 4px 0 0;color:#555;font-size:12px;font-weight:600}
.yuju-order-search-bar .form-control{display:inline-block;width:160px;height:30px;padding:4px 8px;font-size:12px}
.yuju-order-search-bar .btn{padding:5px 10px;font-size:12px;line-height:1.3}
.yuju-order-search-bar .checkbox-inline{margin:0 8px 0 0;padding-top:0;font-size:12px;font-weight:400}
@media(max-width:768px){.yuju-webhook-shell>.panel-body.yuju-webhook-content{padding:9px}.yuju-page-title{font-size:16px}.yuju-webhook-content .panel-body{padding:9px}.yuju-order-search-bar .form-control{width:100%}}
{/literal}
</style>
{/block}