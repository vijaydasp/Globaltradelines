(() => {
  'use strict';
  const base = document.querySelector('meta[name="app-base"]')?.content || '';
  const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const notify = (message) => { const el = document.getElementById('live-toast'); if (!el) return; el.textContent = message; el.hidden = false; clearTimeout(el.timer); el.timer = setTimeout(() => el.hidden = true, 5500); };
  try { document.documentElement.dataset.theme = localStorage.getItem('gtl-theme') || 'light'; } catch {}
  document.getElementById('theme-toggle')?.addEventListener('click', () => { const theme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark'; document.documentElement.dataset.theme = theme; try { localStorage.setItem('gtl-theme', theme); } catch {} });
  document.querySelector('.sidebar-toggle')?.addEventListener('click', function () { const open = document.getElementById('sidebar').classList.toggle('open'); this.setAttribute('aria-expanded', String(open)); });
  document.querySelectorAll('[data-dismiss]').forEach(button => button.addEventListener('click', () => button.parentElement.remove()));
  let dirty = false;
  document.querySelectorAll('[data-unsaved]').forEach(form => { form.addEventListener('input', () => dirty = true); form.addEventListener('change', () => dirty = true); form.addEventListener('submit', () => { dirty = false; }); });
  window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
  document.querySelectorAll('[data-confirm]').forEach(form => form.addEventListener('submit', async event => {
    if (form.dataset.confirmed) return;
    event.preventDefault(); const dialog = document.getElementById('confirm-dialog');
    dialog.querySelector('[data-confirm-message]').textContent = form.dataset.confirm;
    dialog.showModal();
    const confirmed = await new Promise(resolve => dialog.addEventListener('close', () => resolve(dialog.returnValue === 'confirm'), {once:true}));
    if (confirmed) { form.dataset.confirmed = '1'; form.requestSubmit(); }
  }));
  const tabs = document.querySelectorAll('[data-tab]');
  function showTab(id) { tabs.forEach(tab => { const selected = tab.dataset.tab === id; tab.classList.toggle('active', selected); tab.setAttribute('aria-selected', String(selected)); document.getElementById(tab.dataset.tab).hidden = !selected; }); }
  if (tabs.length) { showTab(tabs[0].dataset.tab); tabs.forEach(tab => tab.addEventListener('click', () => showTab(tab.dataset.tab))); document.querySelector('.editor-form')?.addEventListener('invalid', event => { const section = event.target.closest('.editor-section'); if(section) showTab(section.id); }, true); }
  document.querySelectorAll('.rich-editor').forEach(editor => {
    const area = editor.querySelector('.rich-area'), source = editor.querySelector('textarea');
    area.addEventListener('input', () => { source.value = area.innerHTML; dirty = true; });
    area.addEventListener('paste', event => { event.preventDefault(); document.execCommand('insertText', false, event.clipboardData.getData('text/plain')); });
    editor.querySelectorAll('[data-command]').forEach(button => { button.addEventListener('mousedown', event => event.preventDefault()); button.addEventListener('click', () => { let value = button.dataset.value || null; if(button.dataset.command === 'createLink') { value = window.prompt('Enter an https:// link or a path beginning with /'); if (!value || !/^(https?:\/\/|\/(?!\/)|mailto:)/i.test(value)) return; } area.focus(); document.execCommand(button.dataset.command, false, value); source.value = area.innerHTML; dirty = true; }); });
  });
  const title = document.querySelector('[name="title"]'), slug = document.querySelector('[data-slug]');
  if (title && slug && !slug.value) { let changed = false; slug.addEventListener('input', () => changed = true); title.addEventListener('input', () => { if (!changed) slug.value = title.value.normalize('NFKD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,''); }); }
  document.querySelectorAll('.select-search').forEach(input => {
    let timer, controller;
    input.addEventListener('input', () => {
      const select = document.getElementById(input.dataset.select), query = input.value.toLowerCase();
      if (!input.dataset.table) { Array.from(select.options).forEach(option => option.hidden = option.value !== '' && !option.text.toLowerCase().includes(query)); return; }
      clearTimeout(timer); controller?.abort();
      timer=setTimeout(async()=>{controller=new AbortController();try{const response=await fetch(base+'/admin/options?'+new URLSearchParams({table:input.dataset.table,q:input.value}),{signal:controller.signal,headers:{Accept:'application/json'}});const result=await response.json();if(!response.ok)throw new Error(result.error||'Could not load choices.');const selected=Array.from(select.selectedOptions).filter(option=>option.value);const choices=new Map(result.options.map(option=>[String(option.id),option.label]));selected.forEach(option=>choices.set(option.value,option.text));const ids=selected.map(option=>option.value);select.replaceChildren();if(!select.multiple)select.add(new Option('Choose an option','',false,ids.length===0));choices.forEach((label,id)=>select.add(new Option(label,id,false,ids.includes(id))));}catch(error){if(error.name!=='AbortError')notify(error.message);}},250);
    });
  });
  document.querySelectorAll('[name="link_type"]').forEach(select => { const update = () => { const fields = {custom:'custom_url',page:'page_id',category:'category_id',subcategory:'subcategory_id',brand:'brand_id',product:'product_id'}; Object.values(fields).forEach(key => { const el = select.form.querySelector('[data-field="'+key+'"]'); if(el) el.hidden = key !== fields[select.value]; }); }; select.addEventListener('change',update); update(); });
  document.querySelectorAll('[data-copy]').forEach(button => button.addEventListener('click', async () => { const value = new URL(button.dataset.copy,location.origin).href; try {await navigator.clipboard.writeText(value);notify('Media URL copied.');}catch{window.prompt('Copy this URL:',value);} }));
  async function upload(files, replace, progress) {
    const form = new FormData(); form.append('_token', token); if(replace) form.append('replace_id', replace); Array.from(files).forEach(file => form.append('files[]', file));
    return await new Promise((resolve,reject) => { const xhr = new XMLHttpRequest(); xhr.open('POST',base+'/admin/upload'); xhr.setRequestHeader('Accept','application/json'); if(progress)xhr.upload.onprogress = event => { if(event.lengthComputable){progress.hidden=false;progress.value=event.loaded/event.total*100;} }; xhr.onload=()=>{try{const result=JSON.parse(xhr.responseText);if(result.error)reject(new Error(result.error));else resolve(result);}catch{reject(new Error('Upload failed. Check your session and file limits.'));}};xhr.onerror=()=>reject(new Error('Connection failed. Try again.'));xhr.send(form); });
  }
  document.querySelectorAll('[data-upload-zone]').forEach(form => {
    const input=form.querySelector('[type=file]'), status=form.querySelector('.upload-status');
    input.addEventListener('change',()=>{status.textContent = Array.from(input.files).map(file=>file.name).join(', ');});
    form.addEventListener('dragover',event=>{event.preventDefault();form.classList.add('dragover');});form.addEventListener('dragleave',()=>form.classList.remove('dragover'));
    form.addEventListener('drop',event=>{event.preventDefault();form.classList.remove('dragover');input.files=event.dataTransfer.files;input.dispatchEvent(new Event('change'));});
    form.addEventListener('submit',async event=>{event.preventDefault();const button=form.querySelector('button');button.disabled=true;status.textContent='Uploading…';try{const result=await upload(input.files,null,form.querySelector('progress'));status.textContent=(result.files.length+' file(s) uploaded.\n'+result.errors.join('\n'));if(!result.errors.length)location.reload();}catch(error){status.textContent=error.message;}finally{button.disabled=false;}});
  });
  document.querySelectorAll('[data-inline-upload],[data-replace-media]').forEach(input=>input.addEventListener('change',async()=>{if(!input.files.length)return;input.disabled=true;notify('Uploading…');try{const result=await upload(input.files,input.dataset.replaceMedia);if(result.errors.length)throw new Error(result.errors.join(' '));if(input.dataset.inlineUpload){const select=document.getElementById(input.dataset.inlineUpload);result.files.forEach(file=>{const option=new Option(file.title,file.id,true,true);select.add(option);});select.dispatchEvent(new Event('change',{bubbles:true}));notify('File uploaded and selected. Save your changes to use it.');}else location.reload();}catch(error){notify(error.message);}finally{input.disabled=false;input.value='';}}));
  document.querySelectorAll('[data-media-select]').forEach(select=>select.addEventListener('change',()=>{const preview=document.querySelector('[data-for="'+select.id+'"]');if(!preview)return;preview.replaceChildren();if(select.value){const img=new Image();img.src=base+'/media/'+select.value;img.alt='Selected media';img.onerror=()=>img.remove();preview.append(img);}}));
  document.querySelectorAll('[data-reorder]').forEach(list=>{
    let dragged=null;
    const children=()=>Array.from(list.children).filter(el=>el.dataset.id);
    const save=async()=>{const data=new FormData();data.append('_token',token);children().forEach(el=>data.append('ids[]',el.dataset.id));try{const response=await fetch(base+'/admin/'+list.dataset.reorder+'/reorder',{method:'POST',body:data,headers:{Accept:'application/json'}});const result=await response.json();if(!response.ok)throw new Error(result.error||'Order could not be saved.');notify(result.message);}catch(error){notify(error.message+' Reload to restore the saved order.');}};
    list.addEventListener('dragstart',event=>{const item=event.target.closest('[draggable]');if(item?.parentElement!==list)return;event.stopPropagation();dragged=item;item.classList.add('dragging');event.dataTransfer.effectAllowed='move';event.dataTransfer.setData('text/plain',item.dataset.id);});
    list.addEventListener('dragover',event=>{if(!dragged)return;event.preventDefault();event.stopPropagation();const item=event.target.closest('[draggable]');if(item&&item!==dragged&&item.parentElement===list){const box=item.getBoundingClientRect();const horizontal=list.classList.contains('mega-builder');const after=horizontal?event.clientX>box.left+box.width/2:event.clientY>box.top+box.height/2;list.insertBefore(dragged,after?item.nextSibling:item);}});
    list.addEventListener('drop',event=>{if(!dragged)return;event.preventDefault();event.stopPropagation();dragged.classList.remove('dragging');dragged=null;save();});
    list.addEventListener('dragend',()=>{dragged?.classList.remove('dragging');dragged=null;});
    list.addEventListener('click',event=>{const button=event.target.closest('[data-move]');if(!button||button.closest('[data-reorder]')!==list)return;event.preventDefault();const item=button.closest('[draggable]');if(item?.parentElement!==list)return;const items=children(),index=items.indexOf(item);if(button.dataset.move==='up'&&index>0){list.insertBefore(item,items[index-1]);save();}if(button.dataset.move==='down'&&index<items.length-1){list.insertBefore(items[index+1],item);save();}});
  });
})();
