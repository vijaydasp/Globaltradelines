(() => {
  'use strict';
  document.querySelectorAll('[data-catalogue-import] [data-catalogue-submit]').forEach(form => {
    form.addEventListener('submit', event => {
      if (form.dataset.submitting) { event.preventDefault(); return; }
      form.dataset.submitting = '1';
      form.querySelector('button[type="submit"]').disabled = true;
      form.querySelector('[data-import-loading]').hidden = false;
    });
  });
  const root = document.querySelector('[data-bulk-product-images]');
  if (!root) return;
  const find = selector => root.querySelector(selector);
  const base = document.querySelector('meta[name="app-base"]')?.content || '';
  const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const endpoint = base + '/admin/products/bulk-images';
  const storageKey = 'gtl-bulk-images:' + root.dataset.scope;
  let listing = JSON.parse(find('[data-initial-products]').textContent), pending = {}, receipt = null;
  let busy = false, loading = false, picker = null, mediaPage = 1, mediaPages = 1, sequence = 0;
  const statuses = new Map(), filter = find('[data-product-filter]');
  const errorBox = find('[data-bulk-error]');
  const error = message => { errorBox.textContent = message; errorBox.hidden = !message; };
  const element = (tag, text, className) => { const node = document.createElement(tag); if (text !== undefined) node.textContent = text; if (className) node.className = className; return node; };
  const button = (text, action, id, mediaId) => { const node = element('button', text, 'button secondary tiny'); node.type = 'button'; node.dataset.action = action; node.dataset.product = id; if (mediaId) node.dataset.media = mediaId; return node; };
  const image = file => {
    const node = element('img'); node.alt = file.title || 'Product image'; node.loading = 'lazy';
    try { const url = new URL(file.url, location.origin); if (url.origin === location.origin) node.src = url.href; } catch {}
    return node;
  };
  try {
    const saved = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
    if (saved?.pending && typeof saved.pending === 'object' && !Array.isArray(saved.pending)) {
      Object.entries(saved.pending).forEach(([id, row]) => { if (/^[1-9][0-9]*$/.test(id) && row && Array.isArray(row.additional) && row.version && row.image_version) pending[id] = row; });
      receipt = saved.receipt || null;
    }
  } catch { error('Pending selections could not be restored. Browser storage may be unavailable.'); }
  const persist = () => {
    try { sessionStorage.setItem(storageKey, JSON.stringify({pending, receipt})); }
    catch { error('Browser storage is unavailable. Selections remain on this screen, including filtering and pagination, but will not survive a reload.'); }
  };
  const current = id => listing.products.find(product => Number(product.id) === Number(id));
  const counts = () => Object.values(pending).reduce((sum, row) => ({products: sum.products + 1, primary: sum.primary + (row.primary ? 1 : 0), additional: sum.additional + row.additional.length}), {products: 0, primary: 0, additional: 0});
  const summary = () => { const c = counts(); return `${c.products} products with pending changes · ${c.primary} primary-image drafts · ${c.additional} additional-image assignments`; };
  const updateSummary = () => { find('[data-pending-summary]').textContent = summary(); find('[data-save-images]').disabled = busy || !Object.keys(pending).length; };
  const changed = id => {
    if (pending[id] && !pending[id].primary && !pending[id].additional.length) delete pending[id];
    if (pending[id]) delete pending[id].error;
    statuses.delete(Number(id)); receipt = null; persist(); render();
  };
  const stage = product => pending[product.id] ||= {
    product_id: Number(product.id), title: product.title, version: product.version, image_version: product.image_version,
    baseline_primary: Number(product.draft_primary_id || 0), existing: product.additional.map(file => Number(file.id)), primary: null, additional: []
  };
  function choose(file) {
    const product = current(picker.id); if (!product) return;
    if (!pending[product.id] && Object.keys(pending).length >= 100) { find('[data-picker-status]').textContent = 'Save the current 100 products before adding another product.'; return; }
    const row = stage(product), id = Number(file.id);
    if (picker.mode === 'primary') {
      row.primary = id === row.baseline_primary ? null : {id, title: file.title, url: file.url};
      row.additional = row.additional.filter(item => item.id !== id);
    } else {
      if (row.existing.includes(id) || row.additional.some(item => item.id === id) || id === Number(row.primary?.id || row.baseline_primary)) {
        find('[data-picker-status]').textContent = 'That image is already assigned or selected.'; return;
      }
      if (row.additional.length >= 50) { find('[data-picker-status]').textContent = 'Save at most 50 additional selections per product at once.'; return; }
      row.additional.push({id, title: file.title, url: file.url});
    }
    changed(product.id); find('[data-picker-status]').textContent = 'Selection is pending. Use Save All Images when ready.';
    if (picker.mode === 'primary') find('[data-media-dialog]').close();
  }
  function render() {
    const tbody = find('[data-product-rows]'); tbody.replaceChildren();
    for (const product of listing.products) {
      const tr = element('tr'); tr.dataset.productRow = product.id;
      const info = element('td'), link = element('a', product.title); link.href = base + '/admin/products/' + product.id; info.append(link);
      info.append(element('small', [product.brand, product.category, product.subcategory].filter(Boolean).join(' / ') || 'No classification'));
      const primary = element('td');
      if (product.primary_id) { const thumbs = element('div', undefined, 'bulk-image-thumbs'); thumbs.append(image({url: product.primary_url, title: product.title})); primary.append(thumbs); } else primary.append(element('small', 'No current image'));
      if (Number(product.primary_id) !== Number(product.draft_primary_id)) {
        primary.append(element('small', 'Existing draft primary'));
        if (product.draft_primary_id) { const thumbs = element('div', undefined, 'bulk-image-thumbs'); thumbs.append(image({url: product.draft_primary_url, title: 'Draft primary image'})); primary.append(thumbs); }
      }
      const additional = element('td', product.additional.length + ' image(s)'), thumbs = element('div', undefined, 'bulk-image-thumbs');
      product.additional.forEach(file => thumbs.append(image(file))); additional.append(thumbs);
      const selections = element('td'), row = pending[product.id];
      if (row) {
        if (row.primary) { selections.append(element('small', 'New primary (draft)')); const preview = element('div', undefined, 'bulk-image-thumbs'); preview.append(image(row.primary)); selections.append(preview); }
        if (row.additional.length) { selections.append(element('small', row.additional.length + ' additional pending')); const preview = element('div', undefined, 'bulk-image-thumbs'); row.additional.forEach(file => preview.append(image(file))); selections.append(preview); }
        if (row.error) selections.append(element('small', row.error, 'error-text'));
      } else selections.textContent = 'No pending changes';
      const actions = element('td'); actions.append(button('Select primary', 'primary', product.id), button('Add images', 'additional', product.id));
      if (row) { actions.append(button('Cancel pending', 'cancel', product.id)); if (row.version !== product.version || row.image_version !== product.image_version) actions.append(button('Review latest version', 'rebase', product.id)); }
      const status = row ? (row.error ? 'Failed — review required' : 'Pending') : (statuses.get(Number(product.id)) || 'Unchanged');
      actions.append(element('small', status)); tr.append(info, primary, additional, selections, actions); tbody.append(tr);
    }
    if (!listing.products.length) { const tr = element('tr'), td = element('td', 'No products match these filters. Pending selections are still retained.'); td.colSpan = 5; tr.append(td); tbody.append(tr); }
    find('[data-page-label]').textContent = `Page ${listing.page} of ${listing.pages} · ${listing.total} products`;
    find('[data-product-page="previous"]').disabled = busy || loading || listing.page <= 1;
    find('[data-product-page="next"]').disabled = busy || loading || listing.page >= listing.pages;
    updateSummary();
  }
  const parseResponse = async response => {
    let result; try { result = await response.json(); } catch { throw new Error('Unexpected response. Check your session and retry; pending selections are retained.'); }
    if (!response.ok && !result.rows) throw new Error(result.error || Object.values(result.errors || {}).flat().join(' ') || result.message || 'Request failed.');
    return result;
  };
  async function loadProducts(url, push = true, clearError = true) {
    if (busy) return;
    const request = ++sequence; loading = true; render();
    try {
      const response = await fetch(url, {headers: {Accept: 'application/json'}}), data = await parseResponse(response);
      if (request !== sequence) return;
      listing = data; if (push) history.pushState(null, '', url); if (clearError) error('');
    } catch (exception) { error(exception.message); }
    finally { if (request === sequence) { loading = false; render(); } }
  }
  const filterUrl = page => { const params = new URLSearchParams(new FormData(filter)); for (const [key, value] of [...params]) if (!value) params.delete(key); if (page > 1) params.set('page', page); return endpoint + (params.size ? '?' + params : ''); };
  filter.addEventListener('submit', event => { event.preventDefault(); loadProducts(filterUrl(1)); });
  find('[data-reset-filters]').addEventListener('click', () => { filter.querySelectorAll('input,select').forEach(input => input.value = ''); loadProducts(endpoint); });
  root.querySelectorAll('[data-product-page]').forEach(node => node.addEventListener('click', () => loadProducts(filterUrl(listing.page + (node.dataset.productPage === 'next' ? 1 : -1)))));
  window.addEventListener('popstate', () => { const params = new URL(location.href).searchParams; filter.querySelectorAll('[name]').forEach(input => input.value = params.get(input.name) || ''); loadProducts(location.href, false); });
  let mediaSequence = 0;
  async function loadMedia(page = 1) {
    const request = ++mediaSequence, params = new URLSearchParams({q: find('[data-media-search] input').value, page});
    find('[data-picker-status]').textContent = 'Loading images…';
    try {
      const response = await fetch(endpoint + '/media?' + params, {headers: {Accept: 'application/json'}}), data = await parseResponse(response);
      if (request !== mediaSequence) return;
      mediaPage = data.page; mediaPages = data.pages; const choices = find('[data-media-choices]'); choices.replaceChildren();
      data.files.forEach(file => { const node = element('button'); node.type = 'button'; node.append(image(file), element('span', file.title + ' (#' + file.id + ')')); node.addEventListener('click', () => choose(file)); choices.append(node); });
      find('[data-picker-status]').textContent = data.files.length ? 'Select an image to add it to your pending changes.' : 'No images found. Upload an image or change the search.';
      find('[data-media-page-label]').textContent = `${mediaPage} / ${mediaPages}`;
      find('[data-media-page="previous"]').disabled = mediaPage <= 1; find('[data-media-page="next"]').disabled = mediaPage >= mediaPages;
    } catch (exception) { find('[data-picker-status]').textContent = exception.message; }
  }
  root.addEventListener('click', event => {
    const action = event.target.closest('[data-action]'); if (!action || busy) return;
    const id = Number(action.dataset.product), product = current(id);
    if (action.dataset.action === 'cancel') { delete pending[id]; changed(id); }
    else if (action.dataset.action === 'remove-primary') { pending[id].primary = null; changed(id); review(false); }
    else if (action.dataset.action === 'remove-additional') { pending[id].additional = pending[id].additional.filter(file => file.id !== Number(action.dataset.media)); changed(id); review(false); }
    else if (action.dataset.action === 'rebase' && product) {
      if (!window.confirm('Keep your selections and use the latest product version shown here? Review its current images before confirming.')) return;
      const row = pending[id]; row.version = product.version; row.image_version = product.image_version; row.baseline_primary = Number(product.draft_primary_id || 0); row.existing = product.additional.map(file => Number(file.id));
      if (row.primary?.id === row.baseline_primary) row.primary = null;
      row.additional = row.additional.filter(file => !row.existing.includes(file.id) && file.id !== Number(row.primary?.id || row.baseline_primary)); changed(id);
    } else if (product && ['primary', 'additional'].includes(action.dataset.action)) {
      picker = {id, mode: action.dataset.action}; find('[data-picker-purpose]').textContent = product.title + (picker.mode === 'primary' ? ' — choose a primary image draft' : ' — append additional images');
      find('[data-media-search]').reset(); find('[data-media-dialog]').showModal(); loadMedia();
    }
  });
  find('[data-media-search]').addEventListener('submit', event => { event.preventDefault(); loadMedia(); });
  root.querySelectorAll('[data-media-page]').forEach(node => node.addEventListener('click', () => loadMedia(mediaPage + (node.dataset.mediaPage === 'next' ? 1 : -1))));
  find('[data-close-media]').addEventListener('click', () => find('[data-media-dialog]').close());
  find('[data-bulk-upload]').addEventListener('change', async event => {
    const input = event.target, files = [...input.files]; if (!files.length) return;
    if (files.length > 20 || files.some(file => !/\.(jpe?g|png|webp|gif)$/i.test(file.name))) { find('[data-picker-status]').textContent = 'Choose up to 20 JPEG, PNG, WebP or GIF files.'; input.value = ''; return; }
    input.disabled = true; find('[data-close-media]').disabled = true;
    const dialog = find('[data-media-dialog]'), preventClose = event => event.preventDefault(); dialog.addEventListener('cancel', preventClose);
    find('[data-picker-status]').textContent = 'Uploading to Media Library…';
    try {
      const form = new FormData(); form.append('_token', token); files.forEach(file => form.append('files[]', file));
      const data = await new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest(); xhr.open('POST', base + '/admin/upload'); xhr.setRequestHeader('Accept', 'application/json');
        xhr.upload.onprogress = event => { if (event.lengthComputable) { const progress = find('[data-upload-progress]'); progress.hidden = false; progress.value = event.loaded / event.total * 100; } };
        xhr.onload = () => { try { const result = JSON.parse(xhr.responseText); if (!Array.isArray(result.files)) throw new Error(result.error || 'Upload failed. Check your session and file limits.'); resolve(result); } catch (exception) { reject(exception); } };
        xhr.onerror = () => reject(new Error('Upload connection failed. Check Media Library before retrying.')); xhr.send(form);
      });
      const selected = picker.mode === 'primary' ? data.files.slice(0, 1) : data.files; selected.forEach(choose);
      if (data.errors.length) error('Some uploads failed: ' + data.errors.join(' '));
      if (dialog.open) { await loadMedia(); find('[data-picker-status]').textContent = `${data.files.length} uploaded. ${selected.length} image(s) offered for pending selection. ${data.errors.join(' ')}`; }
    } catch (exception) { find('[data-picker-status]').textContent = exception.message; }
    finally { input.disabled = false; input.value = ''; find('[data-close-media]').disabled = false; find('[data-upload-progress]').hidden = true; dialog.removeEventListener('cancel', preventClose); }
  });
  function review(open = true) {
    find('[data-review-summary]').textContent = summary(); const rows = find('[data-review-rows]'); rows.replaceChildren();
    Object.values(pending).forEach(row => {
      const section = element('div', undefined, 'bulk-image-review-row'); section.append(element('strong', row.title + ' (#' + row.product_id + ')'));
      if (row.primary) section.append(element('p', 'Primary draft: ' + row.primary.title), button('Cancel primary selection', 'remove-primary', row.product_id));
      row.additional.forEach(file => section.append(element('p', 'Additional: ' + file.title), button('Remove selection', 'remove-additional', row.product_id, file.id)));
      if (row.error) section.append(element('p', row.error, 'error-text')); rows.append(section);
    });
    find('[data-confirm-images]').disabled = busy || !Object.keys(pending).length;
    if (open) find('[data-review-dialog]').showModal();
  }
  find('[data-save-images]').addEventListener('click', () => review());
  find('[data-review-pending]').addEventListener('click', () => review());
  find('[data-close-review]').addEventListener('click', () => find('[data-review-dialog]').close());
  find('[data-clear-pending]').addEventListener('click', () => { if (Object.keys(pending).length && window.confirm('Cancel all pending assignments? Uploaded Media Library files will remain.')) { pending = {}; receipt = null; persist(); render(); } });
  find('[data-confirm-images]').addEventListener('click', async () => {
    if (busy || !Object.keys(pending).length) return;
    const items = Object.values(pending).map(row => ({product_id: row.product_id, version: row.version, image_version: row.image_version, ...(row.primary ? {primary_id: row.primary.id} : {}), additional_ids: row.additional.map(file => file.id)}));
    const c = counts(); if (c.products > 100 || c.primary + c.additional > 500) { error('Save at most 100 products / 500 image selections at once. Remove excess pending selections before confirming.'); return; }
    const signature = JSON.stringify(items);
    if (!receipt || receipt.signature !== signature) { const bytes = new Uint8Array(16); crypto.getRandomValues(bytes); receipt = {id: [...bytes].map(byte => byte.toString(16).padStart(2, '0')).join(''), signature}; persist(); }
    busy = true; find('[data-review-dialog]').close();
    const controls = [...root.querySelectorAll('button,input,select')].map(node => [node, node.disabled]); controls.forEach(([node]) => node.disabled = true);
    find('[data-pending-summary]').textContent = 'Saving all image assignments…'; error('');
    try {
      const response = await fetch(endpoint, {method: 'POST', headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token}, body: JSON.stringify({confirmed: true, request_id: receipt.id, items})});
      const result = await parseResponse(response), output = find('[data-bulk-results]'); output.hidden = false; output.replaceChildren(element('h2', result.ok ? 'Image assignments saved' : 'Batch not saved'));
      output.append(element('p', `${result.total} products processed · ${result.successful} successful · ${result.failed} failed · ${result.primary} primary-image drafts updated · ${result.additional} additional images added`));
      result.rows.forEach(row => {
        output.append(element('p', 'Product #' + row.product_id + ': ' + row.message));
        if (row.status === 'saved') { delete pending[row.product_id]; statuses.set(Number(row.product_id), 'Saved'); }
        else if (pending[row.product_id]) pending[row.product_id].error = row.message;
      });
      if (result.ok) receipt = null; persist();
    } catch (exception) { error(exception.message + ' Pending selections are retained; retrying the same confirmation is safe.'); }
    finally { busy = false; controls.forEach(([node, disabled]) => node.disabled = disabled); render(); await loadProducts(location.href, false, false); }
  });
  window.addEventListener('beforeunload', event => { if (busy) { event.preventDefault(); event.returnValue = ''; } });
  render();
})();
