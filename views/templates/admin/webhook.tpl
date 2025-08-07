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
    <div class="panel-body">
        {$content}
    </div>
</div>

<style>
.yuju-webhook-container {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    padding: 20px;
    margin: -20px;
}

.yuju-webhook-header {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 25px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    backdrop-filter: blur(10px);
}

.yuju-page-title {
    color: #2c3e50;
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 15px;
}

.yuju-page-title i {
    background: linear-gradient(45deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-size: 2.2rem;
}

.yuju-page-subtitle {
    color: #7f8c8d;
    font-size: 1.1rem;
    margin: 10px 0 0 0;
    font-weight: 400;
}

.yuju-status-indicator {
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(46, 204, 113, 0.1);
    padding: 12px 20px;
    border-radius: 25px;
    border: 2px solid rgba(46, 204, 113, 0.3);
}

.status-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

.status-dot.status-active {
    background: #2ecc71;
    box-shadow: 0 0 0 0 rgba(46, 204, 113, 0.7);
}

.status-text {
    color: #27ae60;
    font-weight: 600;
    font-size: 0.9rem;
}

@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(46, 204, 113, 0.7);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(46, 204, 113, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(46, 204, 113, 0);
    }
}

.yuju-webhook-content {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 15px;
    padding: 0;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    backdrop-filter: blur(10px);
    overflow: hidden;
}

/* Override default panel styles */
.yuju-webhook-content .panel {
    background: transparent;
    border: none;
    box-shadow: none;
    margin: 0;
}

.yuju-webhook-content .panel-heading {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 20px 30px;
    font-size: 1.2rem;
    font-weight: 600;
}

.yuju-webhook-content .panel-body {
    padding: 30px;
    background: transparent;
}

/* Responsive Design */
@media (max-width: 768px) {
    .yuju-webhook-container {
        padding: 10px;
        margin: -10px;
    }
    
    .yuju-webhook-header {
        padding: 20px;
        text-align: center;
    }
    
    .yuju-page-title {
        font-size: 2rem;
        justify-content: center;
    }
    
    .yuju-status-indicator {
        justify-content: center;
        margin-top: 15px;
    }
}
</style>
{/block}