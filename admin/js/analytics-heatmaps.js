(function () {
    'use strict';
    window.TahmirHeatmaps = function (app, boot, state, request) {
        var q=function(s){return app.querySelector(s);}, page=q('[data-heat-page]'), select=q('[data-heat-layout]'), frame=q('[data-heat-frame]'), overlay=q('[data-heat-overlay]'), position=q('[data-heat-position]');
        var result=null, current=null, generation=0, loaded=false;
        function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
        function status(text){q('[data-heat-status]').textContent=text;}
        function pages(){var previous=page.value,paths=(state.report.pages||[]).map(function(p){return p.page_path;});(state.pages||[]).forEach(function(p){if(p.status==='publish'&&p.url){try{paths.push(new URL(p.url,location.href).pathname);}catch(e){/* Unsupported permalink. */}}});page.innerHTML='<option value="">Choose a page</option>'+Array.from(new Set(paths)).map(function(p){return '<option value="'+esc(p)+'">'+esc(p)+'</option>';}).join('');page.value=previous;}
        function signature(element){var input=element.tagName+'|'+String(element.textContent||'').replace(/\s+/g,' ').trim().slice(0,200)+'|'+(element.getAttribute('href')||''),hash=2166136261;for(var i=0;i<input.length;i++)hash=Math.imul(hash^input.charCodeAt(i),16777619);return(hash>>>0).toString(16);}
        function clear(){loaded=false;overlay.innerHTML='';q('[data-heat-scroll]').hidden=true;q('[data-heat-position-wrap]').hidden=true;}
        function details(layout){q('[data-heat-details]').innerHTML='<div class="tc-heat-details">'+layout.scroll.map(function(row){return '<div><b>'+Number(row.percent)+'%</b>reached '+Number(row.depth)+'% of page<br>'+Number(row.views)+' / '+Number(layout.views)+' page views</div>';}).join('')+'</div>';}
        function paint(){
            if(!current||!loaded)return;
            overlay.innerHTML='';
            var doc;try{doc=frame.contentDocument;if(!doc||!doc.body)throw new Error();}catch(e){status('This page does not allow a preview. Scroll percentages remain available below.');return;}
            var dh=Math.max(doc.documentElement.scrollHeight,frame.clientHeight), offset=Number(position.value||0);
            // Preserve the recorded viewport; expanding the iframe would distort vh-based layouts.
            if(Math.abs(dh-current.document_height)>Math.max(20,current.document_height*.02)){
                status('The current page height differs from the recorded layout. Overlay hidden to avoid misleading positions. Scroll percentages remain available below.');return;
            }
            frame.contentWindow.scrollTo({top:offset,behavior:'instant'});
            var mode=q('[data-heat-mode]').value, mapped=0, max=Math.max.apply(null,current.elements.map(function(e){return Number(e.clicks);}).concat([1]));
            if(mode==='scroll'){
                overlay.innerHTML=current.scroll.map(function(row){var hue=230-Number(row.percent)*2.3;return '<div class="tc-heat-band" style="top:'+((row.depth-10)/100*dh-offset)+'px;height:'+dh/10+'px;background:hsl('+hue+',90%,52%)"></div>';}).join('')+'<div class="tc-heat-fold" style="top:'+(current.fold/100*dh-offset)+'px">Initial visible fold · '+current.fold+'% of page</div>';
                status(current.views+' sampled page views · '+current.width+' × '+current.height+' · percentages show visible reach, not reading.');return;
            }
            current.elements.forEach(function(item){
                var matches;try{matches=doc.querySelectorAll(item.selector);}catch(e){return;}
                if(matches.length!==1)return;
                var el=matches[0], rect=el.getBoundingClientRect();
                if(signature(el)!==item.signature||!rect.width||!rect.height||el.closest('[data-tahmir-private],input,textarea,select,[contenteditable]'))return;
                if(Math.abs(rect.width-item.width)>Math.max(4,item.width*.05)||Math.abs(rect.height-item.height)>Math.max(4,item.height*.05))return;
                for(var ancestor=el;ancestor;ancestor=ancestor.parentElement){var anchored=frame.contentWindow.getComputedStyle(ancestor).position;if(anchored==='fixed'||anchored==='sticky')return;}
                mapped+=Number(item.clicks);
                var heat=document.createElement('div');heat.className='tc-heat-element';
                heat.style.cssText='left:'+rect.left+'px;top:'+rect.top+'px;width:'+rect.width+'px;height:'+rect.height+'px;--heat-color:hsl('+(230-Number(item.clicks)/max*230)+',90%,52%)';
                heat.innerHTML='<span>'+Number(item.clicks)+' clicks</span>';overlay.appendChild(heat);
            });
            status(mapped+' of '+current.clicks+' sampled clicks mapped · '+current.views+' page views · '+(current.clicks-mapped)+' unmatched (changed, hidden, fixed or unavailable elements).'+(result.truncated?' Sample capped at 4,000 recent events.':''));
        }
        function choose(){
            clear();current=(result&&result.layouts||[])[Number(select.value)];if(!current)return;
            details(current);
            if(current.revision!==result.revision){status('This sample belongs to an older page revision. Overlay hidden; measured scroll percentages are shown below.');return;}
            q('[data-heat-stage]').style.width=current.width+'px';q('[data-heat-stage]').style.height=current.height+'px';
            frame.style.width=current.width+'px';frame.style.height=current.height+'px';
            position.max=Math.max(0,current.document_height-current.height);position.value=0;
            q('[data-heat-scroll]').hidden=false;q('[data-heat-position-wrap]').hidden=false;
            status('Loading a read-only preview at the recorded screen size…');
            if(boot.demo){frame.removeAttribute('src');frame.srcdoc=boot.demoHeatmap.html;}
            else{frame.removeAttribute('srcdoc');frame.src=result.url;}
        }
        frame.addEventListener('load',function(){
            if(!current)return;
            loaded=true;
            try{if(frame.contentDocument.fonts)frame.contentDocument.fonts.ready.then(paint);else paint();}catch(e){paint();}
        });
        function apply(data){result=data;select.innerHTML=(data.layouts||[]).map(function(l,i){return '<option value="'+i+'">'+l.width+' × '+l.height+' · '+l.views+' page views · layout '+(i+1)+'</option>';}).join('');if(!data.layouts.length){status('No compatible heatmap data yet. New visits will record page layouts; older click coordinates are not mixed into the map.');return;}choose();}
        q('[data-heat-load]').addEventListener('click',function(){
            clear();q('[data-heat-details]').innerHTML='';if(!page.value){status('Choose a page first.');return;}
            if(boot.demo){apply(boot.demoHeatmap);return;}
            var token=++generation;status('Loading the recent sample for this page…');
            request('ugentic_conversion_heatmap',{path:page.value,days:q('#tc-range').value}).done(function(data){if(token===generation)apply(data);}).fail(function(error){if(token===generation)status(error.message||'Unable to load this heatmap.');});
        });
        page.addEventListener('change',function(){generation++;result=null;current=null;clear();select.innerHTML='';q('[data-heat-details]').innerHTML='';status('Select Load heatmap to inspect this page.');});
        q('#tc-range').addEventListener('change',function(){generation++;result=null;current=null;clear();q('[data-heat-details]').innerHTML='';status('Period changed. Load the heatmap again.');});
        select.addEventListener('change',choose);position.addEventListener('input',paint);q('[data-heat-mode]').addEventListener('change',paint);
        app.addEventListener('click',function(event){if(event.target.closest('[data-tab="heatmaps"]'))pages();});
        pages();
    };
}());
