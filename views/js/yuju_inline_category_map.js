/**
 * Modal único de mapeo de categorías Yuju, compartido entre vistas.
 */
(function ($, window, document) {
    'use strict';
    if (!$) return;
    $(function () {
        var $modal = $('#yuju-inline-map-modal'), $cfg = $('#yuju-inline-map-config');
        if (!$modal.length || !$cfg.length) return;
        if ($modal.parent()[0] !== document.body) $modal.appendTo(document.body);

        var $msg=$('#yuju-inline-map-msg'), $search=$('#yuju-inline-map-search'), $select=$('#yuju-inline-map-select');
        var $psId=$('#yuju-inline-map-ps-id'), $psLabel=$('#yuju-inline-map-ps-label');
        var $psWrap=$('#yuju-inline-map-ps-select-wrap'), $psSelect=$('#yuju-inline-map-ps-select'), $save=$('#yuju-inline-map-save-btn');
        var defaults={ajaxUrl:$cfg.attr('data-ajax-url')||'',token:$cfg.attr('data-token')||''},ctx={},savedPayload=null;

        function message(type,text){$msg.removeClass('text-muted text-danger text-success').addClass('text-'+type).text(text||'');}
        function applyCategory(cat){
            if(!cat)return;
            var id=parseInt(cat.id,10)||0;
            ctx.psName=cat.name||'';
            $psId.val(String(id));
            $psLabel.val((cat.name||('Categoría '+id))+' (ID '+id+')');
            $search.val('');
            $select.find('option').prop('hidden',false);
            $select.val(cat.currentYujuId && $select.find('option').filter(function(){return String(this.value)===String(cat.currentYujuId);}).length ? String(cat.currentYujuId) : '');
        }
        function open(opts){
            opts=opts||{};
            ctx={ajaxUrl:opts.ajaxUrl||defaults.ajaxUrl,token:opts.token||defaults.token,productId:parseInt(opts.productId,10)||0,fromResultModal:!!opts.fromResultModal,psName:''};
            savedPayload=null;
            var cats=(opts.psCategories&&opts.psCategories.length)?opts.psCategories:[{id:opts.psId,name:opts.psName,currentYujuId:opts.currentYujuId}];
            message('muted','');$save.prop('disabled',false).html('<i class="icon-save"></i> Guardar mapeo');
            $psSelect.empty().data('cats',cats);
            $.each(cats, function (_, cat) {
                $('<option>').val(cat.id).text((cat.name || ('Categoría ' + cat.id)) + ' (ID ' + cat.id + ')' + (cat.currentYujuId ? ' · ya mapeada' : '')).appendTo($psSelect);
            });
            $psWrap.toggle(cats.length>1);$('#yuju-inline-map-ps-label').toggle(cats.length<=1);
            var start=$.grep(cats,function(cat){return !cat.currentYujuId;})[0]||cats[0];
            $psSelect.val(String(start.id));applyCategory(start);
            if(ctx.fromResultModal)$('#yuju-cat-overview-result-modal').modal('hide');
            window.setTimeout(function(){$modal.modal({backdrop:true,keyboard:true,show:true});},ctx.fromResultModal?320:0);
        }
        $(document).on('click.yujuInlineMap','.yuju-open-inline-map-modal',function(e){
            e.preventDefault();var $b=$(this),id=parseInt($b.attr('data-ps-category-id')||$b.data('psCategoryId')||'0',10)||0;
            if(id<=0)return;var $result=$('#yuju-cat-overview-result-modal');
            open({psId:id,psName:$b.attr('data-ps-category-name')||$b.data('psCategoryName')||'',currentYujuId:$b.attr('data-current-yuju-id')||$b.data('currentYujuId')||'',fromResultModal:String($b.attr('data-from-result-modal')||'')==='1'||$result.hasClass('in')||$result.is(':visible')});
        });
        $psSelect.on('change.yujuInlineMap',function(){var id=parseInt($(this).val(),10),cat=$.grep($psSelect.data('cats')||[],function(c){return parseInt(c.id,10)===id;})[0];if(cat)applyCategory(cat);});
        $search.on('input.yujuInlineMap',function(){
            var q=String($(this).val()||'').toLowerCase().trim(),current=$select.val();
            $select.find('option').each(function(){this.hidden=!!this.value&&q!==''&&String(this.text||'').toLowerCase().indexOf(q)===-1;});
            if(current&&$select.find('option:selected')[0]&&$select.find('option:selected')[0].hidden)$select.val('');
        });
        $modal.off('.yujuInlineMapStack')
            .on('show.bs.modal.yujuInlineMapStack', function () {
                if (this.parentNode !== document.body) $(this).appendTo(document.body);
                var maxZ = 1040;
                $('.modal:visible').not(this).each(function () {
                    maxZ = Math.max(maxZ, parseInt($(this).css('z-index'), 10) || 1050);
                });
                this.style.setProperty('z-index', String(maxZ + 20), 'important');
                // Marca los fondos que ya existían ANTES de que Bootstrap cree el fondo nuevo.
                $('body > .modal-backdrop').addClass('yuju-preexisting-modal-backdrop');
            })
            .on('shown.bs.modal.yujuInlineMapStack', function () {
                var $newBackdrop = $('body > .modal-backdrop').not('.yuju-preexisting-modal-backdrop').last();
                if ($newBackdrop.length) {
                    $newBackdrop.addClass('yuju-inline-map-backdrop');
                    $newBackdrop[0].style.setProperty('z-index', String((parseInt($(this).css('z-index'), 10) || 1070) - 10), 'important');
                }
                $('body').addClass('modal-open');
            })
            .on('hidden.bs.modal.yujuInlineMapStack', function () {
                $('body > .modal-backdrop.yuju-inline-map-backdrop').remove();
                $('body > .modal-backdrop.yuju-preexisting-modal-backdrop').removeClass('yuju-preexisting-modal-backdrop');
                if ($('.modal.in:visible').length) $('body').addClass('modal-open');
                else $('body').removeClass('modal-open');
                var payload = savedPayload;
                savedPayload = null;
                if (payload) window.setTimeout(function () { $(document).trigger('yuju:categoryMapped', [payload]); }, 100);
                else if (ctx.fromResultModal) window.setTimeout(function () {
                    $(document).trigger('yuju:restoreResultModal');
                    $('#yuju-cat-overview-result-modal').modal('show');
                }, 200);
            });
        $save.off('click.yujuInlineMap').on('click.yujuInlineMap',function(){
            var psId=parseInt($psId.val(),10)||0,yujuId=String($select.val()||'');
            var yujuName=String($select.find('option:selected').text()||'').replace(/\s*\(ID\s+[^)]+\)\s*$/,'').trim();
            if(!psId){message('danger','Categoría PrestaShop inválida.');return;}
            if(!yujuId){message('danger','Selecciona una categoría Yuju.');return;}
            var data={ajax:1,action:'saveInlineCategoryMapping',token:ctx.token,prestashop_category_id:psId,yuju_category_id:yujuId};
            if(ctx.productId)data.product_id=ctx.productId;
            $save.prop('disabled',true).html('<i class="icon-spinner icon-spin"></i> Guardando…');message('muted','Guardando mapeo…');
            $.ajax({url:ctx.ajaxUrl,method:'POST',dataType:'json',data:data}).done(function(res){
                if(!res||!res.success){message('danger',(res&&res.message)||'No se pudo guardar el mapeo.');$save.prop('disabled',false).html('<i class="icon-save"></i> Guardar mapeo');return;}
                message('success',res.message||'Mapeo guardado.');
                savedPayload={id:psId,name:ctx.psName,yujuId:yujuId,yujuName:res.yuju_category_name||yujuName,productId:ctx.productId,fromResultModal:ctx.fromResultModal};
                window.setTimeout(function(){$modal.modal('hide');},300);
            }).fail(function(){message('danger','Error de red al guardar el mapeo.');$save.prop('disabled',false).html('<i class="icon-save"></i> Guardar mapeo');});
        });
        window.YujuInlineCategoryMap={open:open};
    });
})(window.jQuery,window,document);
