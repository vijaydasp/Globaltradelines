(() => {
  'use strict';
  const toggle=document.getElementById('menuToggle');
  toggle?.addEventListener('click',()=>toggle.setAttribute('aria-expanded',String(document.querySelector('.primary-nav')?.classList.contains('open'))));
  document.querySelectorAll('[data-product-image]').forEach(button=>button.addEventListener('click',()=>{let main=document.querySelector('.product-detail-media img');if(!main){main=new Image();document.querySelector('.product-detail-media').replaceChildren(main);}main.src=button.dataset.productImage;main.alt=button.getAttribute('aria-label')||'';}));
  const lightbox=document.getElementById('gallery-lightbox');
  document.querySelectorAll('[data-lightbox]').forEach(link=>link.addEventListener('click',event=>{if(!lightbox)return;event.preventDefault();const img=lightbox.querySelector('img');img.src=link.href;img.alt=link.querySelector('img')?.alt||'';lightbox.showModal();}));
  lightbox?.addEventListener('click',event=>{if(event.target===lightbox)lightbox.close();});
  const search=document.getElementById('searchOverlay');
  document.getElementById('searchOpen')?.addEventListener('click',()=>setTimeout(()=>document.getElementById('searchInput')?.focus(),30));
  search?.querySelector('.search-close')?.addEventListener('click',()=>document.getElementById('searchOpen')?.focus());
  document.querySelectorAll('.primary-nav .dropdown').forEach(item=>{item.addEventListener('focusin',()=>item.classList.add('cms-keyboard-open'));item.addEventListener('focusout',event=>{if(!item.contains(event.relatedTarget))item.classList.remove('cms-keyboard-open');});});
})();
