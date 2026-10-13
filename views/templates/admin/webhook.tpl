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
/* Webhooks: aviso discreto, métricas compactas y encabezados bien distribuidos. */
.yuju-webhook-notice{display:flex;align-items:center;gap:10px;margin:0 0 14px;padding:9px 12px;background:#fff;border:1px solid #e5e7eb;border-left:3px solid #9ca3af;border-radius:3px;color:#626b75;font-size:12px;line-height:1.4}
.yuju-webhook-notice-success{border-left-color:#7eaa8b}
.yuju-webhook-notice-warning{border-left-color:#d8ad62}
.yuju-webhook-notice-icon{display:flex;align-items:center;justify-content:center;flex:0 0 22px;width:22px;height:22px;border-radius:50%;background:#f1f5f2;color:#62856b;font-size:11px}
.yuju-webhook-notice-warning .yuju-webhook-notice-icon{background:#faf5eb;color:#a17a36}
.yuju-webhook-notice-text{display:flex;flex:1;flex-direction:column;gap:1px;min-width:0}
.yuju-webhook-notice-text strong{color:#4f5963;font-size:12px;font-weight:600}
.yuju-webhook-notice-text span{color:#7a838c}
.yuju-webhook-notice-action{flex:0 0 auto;padding:4px 7px;color:#68737d;background:#fff;border:1px solid #e0e4e8;border-radius:3px;font-size:11px;text-decoration:none}
.yuju-webhook-notice-action:hover{color:#333;background:#f8f9fa;text-decoration:none}
.yuju-webhook-stats{display:flex;flex-wrap:wrap;margin-bottom:6px}
.yuju-webhook-stats>[class*="col-"]{display:flex;margin-bottom:12px}
.yuju-webhook-stat-card{width:100%;min-height:0;margin:0!important;border:1px solid #e5e7eb!important;border-radius:4px!important;box-shadow:none!important;background:#fff}
.yuju-webhook-stat-card>.panel-body{display:flex;align-items:center;min-height:78px;padding:13px 14px!important}
.yuju-webhook-stat-card .row{display:flex;align-items:center;width:100%;margin:0}
.yuju-webhook-stat-card .yuju-stat-icon{width:42px;padding:0;text-align:left}
.yuju-webhook-stat-card .yuju-stat-icon i{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:4px;background:#f4f6f8;color:#77818c;font-size:15px}
.yuju-stat-processed .yuju-stat-icon i{background:#f1f6f2;color:#73957b}
.yuju-stat-failed .yuju-stat-icon i{background:#faf2f1;color:#b17c76}
.yuju-stat-processing .yuju-stat-icon i{background:#faf6ed;color:#ae9565}
.yuju-webhook-stat-card .yuju-stat-copy{flex:1;width:auto;padding:0;text-align:right}
.yuju-webhook-stat-card .yuju-stat-number{margin:0 0 2px;color:#343a40;font-size:24px;font-weight:600;line-height:1.15}
.yuju-webhook-stat-card .yuju-stat-copy>div:last-child{color:#7a838c;font-size:11px;line-height:1.3}
.yuju-webhook-content .yuju-config-card{margin:0 0 12px!important;overflow:hidden;border:1px solid #e2e5e9;border-radius:4px;box-shadow:none}
.yuju-webhook-content .yuju-config-card>.panel-heading{padding:12px 14px;background:#fafbfc;border-bottom:1px solid #e8eaed}
.yuju-config-card>.panel-heading .row{display:flex;align-items:center;flex-wrap:wrap;margin:0}
.yuju-config-identity{min-width:0;padding:0 12px 0 0}
.yuju-config-title{display:block;margin-bottom:4px;color:#454d55;font-size:13px;font-weight:600}
.yuju-config-url{overflow-wrap:anywhere;color:#838b94;font-size:11px;font-weight:400;line-height:1.45}
.yuju-config-url small{font-size:inherit}
.yuju-config-actions{display:flex;align-items:center;justify-content:flex-end;flex-wrap:wrap;gap:6px;padding:0;text-align:right}
.yuju-config-actions .label{padding:4px 7px;border-radius:3px;font-size:10px;font-weight:600}
.yuju-config-actions .yuju-config-action{margin:0!important;padding:5px 8px;border-radius:3px;font-size:11px;line-height:1.2}
.yuju-config-card>.panel-body{padding:12px 14px}
@media(max-width:767px){.yuju-webhook-notice{align-items:flex-start;flex-wrap:wrap}.yuju-webhook-notice-text{flex-basis:calc(100% - 40px)}.yuju-webhook-notice-action{margin-left:32px}.yuju-webhook-stats>[class*="col-"]{width:50%;float:left}.yuju-webhook-stat-card>.panel-body{padding:10px!important}.yuju-webhook-stat-card .yuju-stat-number{font-size:21px}.yuju-config-card>.panel-heading .row{display:block}.yuju-config-identity{padding:0 0 10px}.yuju-config-actions{justify-content:flex-start}.yuju-config-actions .label{margin-right:3px}}
@media(max-width:420px){.yuju-webhook-stats>[class*="col-"]{width:100%;float:none}.yuju-webhook-stat-card>.panel-body{min-height:65px}}
{/literal}
</style>
{/block}