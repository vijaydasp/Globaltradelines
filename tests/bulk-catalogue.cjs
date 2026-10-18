/* Optional browser regression suite. Use an isolated installed *_test database.
   Set TEST_BASE_URL, TEST_ADMIN_EMAIL, TEST_ADMIN_PASSWORD and PLAYWRIGHT_PACKAGE.
   Run: node tests/bulk-catalogue.cjs (no application dependency is required). */
const {chromium}=require(process.env.PLAYWRIGHT_PACKAGE||'playwright');
const fs=require('fs'),path=require('path'),assert=require('assert');
const root=path.join(__dirname,'..'),base=process.env.TEST_BASE_URL||'http://127.0.0.1:8013';
const config=fs.readFileSync(path.join(root,'.env'),'utf8');
if(!/^DB_DATABASE=.*_test\s*$/m.test(config)||!['127.0.0.1','localhost','[::1]'].includes(new URL(base).hostname))throw new Error('Use a local server backed by a dedicated *_test database.');
const email=process.env.TEST_ADMIN_EMAIL,password=process.env.TEST_ADMIN_PASSWORD;
if(!email||!password)throw new Error('Set TEST_ADMIN_EMAIL and TEST_ADMIN_PASSWORD for the isolated test installation.');
const fixture='Bulk QA '+Date.now(),slug=fixture.toLowerCase().replaceAll(' ','-'),endpoint='/admin/products/bulk-images';
(async()=>{
 const browser=await chromium.launch({headless:true}),context=await browser.newContext({viewport:{width:1440,height:1000}}),page=await context.newPage();
 const checks=[],errors=[],created=[],products=[],uploaded=[];
 let csrf='',lastPayload=null;
 page.on('pageerror',error=>errors.push(error.message));
 const check=(condition,label)=>{assert(condition,label);checks.push(label);console.log('PASS '+label);};
 const goto=async route=>{const response=await page.goto(base+route,{waitUntil:'networkidle'});assert.equal(response.status(),200,'HTTP '+route);};
 const token=html=>{const value=html.match(/name="_token" value="([a-zA-Z0-9]+)"/)?.[1];assert(value,'CSRF token');return value;};
 const json=async route=>{const response=await context.request.get(base+route,{headers:{Accept:'application/json'}});assert.equal(response.status(),200);return response.json();};
 const listing=()=>json(endpoint+'?q='+encodeURIComponent(fixture));
 const row=id=>page.locator('[data-product-row="'+id+'"]');
 const summary=async text=>{await page.waitForFunction(value=>document.querySelector('[data-pending-summary]').textContent.includes(value),text);};
 const filter=async values=>{
  for(const [name,value] of Object.entries(values)){const field=page.locator('[data-product-filter] [name="'+name+'"]');if(name==='q')await field.fill(value);else await field.selectOption(String(value));}
  const response=page.waitForResponse(r=>r.url().includes(endpoint+'?')&&r.request().method()==='GET'&&!r.url().includes('/media'));
  await page.locator('[data-product-filter] button[type="submit"]').click();await response;
  await page.waitForFunction(()=>!document.querySelector('[data-product-page="next"]').disabled||document.querySelector('[data-page-label]').textContent.includes('Page 1 of 1'));
 };
 const select=async(id,mode,media)=>{
  await row(id).locator('[data-action="'+mode+'"]').click();
  await page.locator('[data-media-choices] button').filter({hasText:new RegExp('\\(#'+media+'\\)$')}).click();
  if(mode==='additional')await page.locator('[data-close-media]').click();
 };
 const create=async(module,data)=>{
  const initial=await context.request.get(base+'/admin/'+module+'/new');
  const response=await context.request.post(base+'/admin/'+module+'/new',{form:{_token:token(await initial.text()),intent:'save',...data},maxRedirects:0});
  assert.equal(response.status(),303,'Create '+module);const id=Number(response.headers().location.split('/').pop());assert(id);created.push([module,id]);return id;
 };
 const remove=async(module,id)=>{const response=await context.request.post(base+'/admin/'+module+'/'+id+'/delete',{form:{_token:csrf},maxRedirects:0});assert.equal(response.status(),303,'Cleanup '+module);};
 try {
  await goto('/admin/login');await page.getByLabel('Email address').fill(email);await page.getByLabel('Password',{exact:true}).fill(password);
  await Promise.all([page.waitForURL(base+'/admin'),page.getByRole('button',{name:'Sign in to your workspace'}).click()]);csrf=token(await page.content());
  const ids={};
  for(const module of ['categories','brands','subcategories']) {
   await goto('/admin/'+module);check(await page.getByRole('link',{name:'Import CSV'}).count()===1,module+' listing exposes importer');
   await page.getByRole('link',{name:'Import CSV'}).click();
   const template=await context.request.get(base+'/admin/catalogue-import/'+module+'/template');check(template.status()===200&&(await template.text()).startsWith('title,slug,'),module+' template downloads');
   const csv='title,slug,status'+(module==='subcategories'?',category_id,brand_ids':'')+'\n'+fixture+' '+module+','+slug+'-'+module+',published'+(module==='subcategories'?','+ids.categories+','+ids.brands:'')+'\n';
   await page.locator('input[name="csv"]').setInputFiles({name:module+'.csv',mimeType:'text/csv',buffer:Buffer.from(csv)});
   await Promise.all([page.waitForNavigation(),page.getByRole('button',{name:'Validate & preview'}).click()]);check((await page.locator('[data-catalogue-import]').textContent()).includes('Create new'),module+' preview validates');
   await Promise.all([page.waitForNavigation(),page.getByRole('button',{name:'Confirm & import'}).click()]);check((await page.locator('[role="status"]').first().textContent()).includes('1 imported'),module+' confirmation imports');
   const options=await json('/admin/options?table='+module+'&q='+encodeURIComponent(fixture));assert.equal(options.options.length,1);ids[module]=Number(options.options[0].id);created.push([module,ids[module]]);
  }
  const csv='product,slug,image,category,brand,sub_category,is_active\n'+Array.from({length:25},(_,i)=>[fixture+' Product '+String(i).padStart(2,'0'),slug+'-product-'+i,2,slug+'-categories',slug+'-brands',slug+'-subcategories',1].join(',')).join('\n');
  await context.request.get(base+'/admin/import');
  const preview=await context.request.post(base+'/admin/import',{multipart:{_token:csrf,action:'preview',csv:{name:'products.csv',mimeType:'text/csv',buffer:Buffer.from(csv)}}});
  const previewHtml=await preview.text();assert(previewHtml.includes('0 validation errors'));const importToken=previewHtml.match(/name="import_token" value="([a-z0-9]+)"/)?.[1];assert(importToken);
  const imported=await context.request.post(base+'/admin/import',{form:{_token:csrf,action:'commit',import_token:importToken}});check((await imported.text()).includes('25 created'),'Existing product CSV creates paginated fixtures');
  const first=await listing(),second=await json(endpoint+'?q='+encodeURIComponent(fixture)+'&page=2');products.push(...first.products,...second.products);assert.equal(products.length,25);
  const a=Number(products[0].id),b=Number(products[20].id);
  await create('product_images',{product_id:a,image_id:3,title:'Existing image',sort_order:0});
  const spec=await create('product_specifications',{product_id:a,title:'Voltage',value:'230 V',sort_order:0});
  await goto(endpoint+'?q='+encodeURIComponent(fixture));
  await select(a,'primary',4);await select(a,'primary',6);await select(a,'primary',4);await select(a,'additional',5);
  await row(a).locator('[data-action="additional"]').click();
  await page.evaluate(()=>{const send=XMLHttpRequest.prototype.send;XMLHttpRequest.prototype.send=function(body){if(body instanceof FormData)window.bulkUploadKeys=[...body.keys()];return send.call(this,body);};});
  const upload=page.waitForResponse(r=>r.url().endsWith('/admin/upload')&&r.request().method()==='POST');
  await page.locator('[data-bulk-upload]').setInputFiles(path.join(root,'public/assets/img/global-trade-lines-favicon-48.png'));
  const uploadResponse=await upload;const media=Number((await uploadResponse.json()).files[0].id);uploaded.push(media);
  const uploadKeys=await page.evaluate(()=>window.bulkUploadKeys);check(uploadKeys.includes('files[]')&&!uploadKeys.some(key=>['product_id','album_id','replace_id'].includes(key)),'Upload sends no immediate association or replacement');
  await summary('2 additional-image assignments');await page.locator('[data-close-media]').click();
  let current=(await listing()).products.find(p=>Number(p.id)===a);check(current.primary_id===2&&current.draft_primary_id===2&&current.additional.length===1,'Selections and uploads do not change product associations before confirmation');
  await page.locator('[data-product-page="next"]').click();await row(b).waitFor();await select(b,'primary',3);await summary('2 products with pending changes');
  await filter({q:fixture+' Product 00',category_id:ids.categories,brand_id:ids.brands,subcategory_id:ids.subcategories,images:'with'});await row(a).waitFor();await summary('2 primary-image drafts');
  await filter({images:'without'});await page.getByText('No products match these filters.',{exact:false}).waitFor();await summary('2 products with pending changes');
  await filter({q:fixture,images:''});await row(a).waitFor();await page.reload({waitUntil:'networkidle'});await summary('2 additional-image assignments');checks.push('Pending changes survive pagination, search, all classification filters, image filters and reload');
  await page.locator('[data-save-images]').click();await page.locator('[data-review-rows] [data-action="remove-additional"][data-media="5"]').click();await summary('1 additional-image assignments');await page.locator('[data-close-review]').click();await select(a,'additional',5);checks.push('Primary selections can change and individual pending images can be cancelled');
  await page.route('**/admin/products/bulk-images',async route=>{if(route.request().method()!=='POST')return route.continue();const body=route.request().postDataJSON();body.items[0].additional_ids=[999999999];await route.continue({postData:JSON.stringify(body)});});
  await page.locator('[data-save-images]').click();await page.locator('[data-confirm-images]').click();await page.getByRole('heading',{name:'Batch not saved'}).waitFor();await summary('2 products with pending changes');
  check((await page.locator('[data-bulk-results]').textContent()).includes('0 successful'),'Invalid assignment rejects the whole batch and retains pending changes');
  current=(await listing()).products.find(p=>Number(p.id)===a);check(current.draft_primary_id===2&&current.additional.length===1,'Rejected batch preserves existing associations');await page.unroute('**/admin/products/bulk-images');
  let submissions=0;page.on('request',request=>{if(request.url()===base+endpoint&&request.method()==='POST'){submissions++;lastPayload=request.postDataJSON();}});
  await page.locator('[data-save-images]').click();await page.locator('[data-confirm-images]').evaluate(button=>{button.click();button.click();});
  await page.getByRole('heading',{name:'Image assignments saved',exact:true}).waitFor();await summary('0 products with pending changes');
  check(submissions===1,'Double clicking confirmation submits once');check((await page.locator('[data-bulk-results]').textContent()).includes('2 successful')&&(await page.locator('[data-bulk-results]').textContent()).includes('2 additional images added'),'Save All Images reports accurate per-product and assignment totals');
  const replay=await context.request.post(base+endpoint,{headers:{Accept:'application/json','X-CSRF-TOKEN':csrf},data:lastPayload});check(replay.status()===200&&(await replay.json()).additional===2,'Repeating the same confirmation returns the saved receipt');
  current=(await listing()).products.find(p=>Number(p.id)===a);check(current.primary_id===2&&current.draft_primary_id===4&&current.additional.length===3&&current.additional.some(file=>Number(file.id)===media),'Primary draft and appended images preserve the current primary and existing image');
  const specPage=await context.request.get(base+'/admin/product_specifications/'+spec);check((await specPage.text()).includes('230 V'),'Existing specification remains intact');
  await select(a,'additional',6);
  await page.route('**/admin/products/bulk-images',async route=>{if(route.request().method()!=='POST')return route.continue();const response=await route.fetch();assert.equal(response.status(),200);await route.abort('connectionfailed');});
  await page.locator('[data-save-images]').click();await page.locator('[data-confirm-images]').click();await page.locator('[data-bulk-error]').filter({hasText:'Pending selections are retained'}).waitFor();await summary('1 products with pending changes');
  check((await listing()).products.find(p=>Number(p.id)===a).additional.length===4,'Lost response leaves committed assignments intact and pending selections available');
  await page.unroute('**/admin/products/bulk-images');await page.locator('[data-save-images]').click();await page.locator('[data-confirm-images]').click();await summary('0 products with pending changes');
  check((await listing()).products.find(p=>Number(p.id)===a).additional.length===4,'Retry after a lost response does not duplicate image associations');
  for(const width of [1440,390]){await page.setViewportSize({width,height:900});await page.reload({waitUntil:'networkidle'});check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Bulk manager fits viewport '+width);await page.screenshot({path:path.join(root,'storage/bulk-images-'+width+'.png'),fullPage:true});}
  check(errors.length===0,'No uncaught browser JavaScript errors');
 } catch(error) {console.error('Browser check failed:',error);throw error;} finally {
  try {
   for(const product of products){const response=await context.request.get(base+'/admin/product_images?product_id='+product.id);const html=await response.text();const ids=[...html.matchAll(/href="[^"\s]*\/admin\/product_images\/(\d+)"/g)].map(match=>Number(match[1]));for(const id of new Set(ids))await remove('product_images',id);}
   for(const [module,id] of [...created].reverse().filter(([module])=>module==='product_specifications'))await remove(module,id);
   for(const product of products)await remove('products',product.id);
   for(const [module,id] of [...created].reverse().filter(([module])=>!['product_images','product_specifications'].includes(module)))await remove(module,id);
   for(const id of uploaded)await remove('media_files',id);
  } finally {fs.writeFileSync(path.join(root,'storage/bulk-catalogue-report.json'),JSON.stringify({checks,errors},null,2));console.log(JSON.stringify({checks,errors},null,2));await browser.close();}
 }
})().catch(error=>{console.error(error);process.exitCode=1;});
