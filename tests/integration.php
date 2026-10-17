<?php
declare(strict_types=1);
/** Destructive fixtures are allowed only in a database whose name ends in _test. */
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
define('ROOT',base_path());
use App\Core\DB;
use App\Models\Modules;
use App\Models\Content;
use App\Services\{Sanitizer,Validator};
if(PHP_SAPI!=='cli'||!str_ends_with(cms_config('DB_DATABASE'),'_test')){fwrite(STDERR,"Tests require a dedicated *_test database.\n");exit(2);}
$accessFile=ROOT.'/storage/test-access.json';
$access=is_file($accessFile)?json_decode(file_get_contents($accessFile),true):['email'=>getenv('TEST_ADMIN_EMAIL'),'password'=>getenv('TEST_ADMIN_PASSWORD')];
if(!$access['email']||!$access['password']){fwrite(STDERR,"Set TEST_ADMIN_EMAIL and TEST_ADMIN_PASSWORD for an installed test database.\n");exit(2);}
$base=rtrim(cms_config('APP_URL'),'/');$cookie=ROOT.'/storage/integration-cookies.txt';$anon=ROOT.'/storage/integration-anon-cookies.txt';@unlink($cookie);@unlink($anon);$created=[];$passed=0;$failed=0;$suffix=bin2hex(random_bytes(4));
function check(bool $condition,string $label):void{global $passed,$failed;if($condition){$passed++;echo "PASS $label\n";}else{$failed++;echo "FAIL $label\n";}}
function req(string $path,?array $post=null,bool $anonymous=false,array $headers=[]):array{global $base,$cookie,$anon;$curl=curl_init($base.$path);curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIEJAR=>$anonymous?$anon:$cookie,CURLOPT_COOKIEFILE=>$anonymous?$anon:$cookie,CURLOPT_TIMEOUT=>20,CURLOPT_HTTPHEADER=>$headers]);if($post!==null){curl_setopt($curl,CURLOPT_POST,true);$multipart=false;foreach($post as $v)if($v instanceof CURLFile)$multipart=true;curl_setopt($curl,CURLOPT_POSTFIELDS,$multipart?$post:http_build_query($post));}$raw=curl_exec($curl);if($raw===false)throw new RuntimeException(curl_error($curl));$status=curl_getinfo($curl,CURLINFO_HTTP_CODE);$header=substr($raw,0,curl_getinfo($curl,CURLINFO_HEADER_SIZE));$body=substr($raw,curl_getinfo($curl,CURLINFO_HEADER_SIZE));curl_close($curl);return compact('status','header','body');}
function token(array $response):string{if(preg_match('/name="_token" value="([a-zA-Z0-9]+)"/',$response['body'],$match))return $match[1];throw new RuntimeException('Missing CSRF token in response '.$response['status']);}
function edit(string $module,?int $id,array $data,string $intent='save'):array{$r=req('/admin/'.$module.'/'.($id??'new'));$data['_token']=token($r);$data['intent']=$intent;if(preg_match('/name="_version" value="([a-f0-9]*)"/',$r['body'],$m))$data['_version']=$m[1];return req('/admin/'.$module.'/'.($id??'new'),$data);}
function insert(string $module,array $data):int{global $created;$id=DB::insert($module,$data);$created[]=[$module,$id];return $id;}
try {
    DB::run('DELETE FROM rate_limits');
    check(str_contains(Sanitizer::html('<p>Hello <strong>world</strong><img src=x onerror=alert(1)><script>alert(1)</script><a href="javascript:alert(1)">bad</a></p>'),'<strong>world</strong>'),'Rich text preserves safe formatting');
    $sanitized=Sanitizer::html('<p onclick="x()">Hi</p><svg onload="x()"></svg><a href="javascript:x()">X</a>');
    check(!str_contains($sanitized,'onclick')&&!str_contains($sanitized,'javascript:')&&!str_contains($sanitized,'<svg'),'Rich text strips scripts, handlers and unsafe URLs');
    check(!safe_url('javascript:alert(1)')&&!safe_url('//evil.test')&&!safe_url('/%2e%2e/secrets')&&safe_url('/products/?brand=2'),'URL scheme and traversal checks');
    $r=req('/admin',null,true);check($r['status']===303&&str_contains($r['header'],'/admin/login'),'Anonymous admin access redirects to login');
    $r=req('/admin/login');$csrf=token($r);$r=req('/admin/login',['_token'=>$csrf,'email'=>$access['email'],'password'=>$access['password']]);check($r['status']===303,'Administrator login');
    $r=req('/admin');check($r['status']===200&&str_contains($r['body'],'Welcome back'),'Dashboard reads real statistics');
    foreach(Modules::all() as $module=>$def){$r=req('/admin/'.$module);check($r['status']===200,'Admin list '.$module);}
    foreach(['/admin/homepage','/admin/navigation','/admin/footer','/admin/import','/admin/settings/company','/admin/settings/contact','/admin/settings/header','/admin/settings/footer','/admin/settings/appearance','/admin/settings/seo','/admin/settings/integrations','/admin/settings/text','/admin/search?q=home','/admin/password'] as $path){$r=req($path);check($r['status']===200,'Admin screen '.$path);}
    foreach(['/','/about/','/products/','/products/?view=categories','/products/?view=sub_categories','/brands/','/careers/','/gallery/','/brochures/','/contact/','/search/?q=Home','/sitemap.xml','/robots.txt'] as $path){$r=req($path,null,true);check($r['status']===200,'Public route '.$path);check(!preg_match('/\{[%{]/',$r['body']),'No legacy template tags '.$path);}
    check(req('/privacy-policy/',null,true)['status']===404,'Unavailable legal content remains unpublished');
    $r=req('/admin/pages/new',['title'=>'No CSRF']);check($r['status']===419,'CSRF required for mutations');
    $pageData=['title'=>'QA page '.$suffix,'slug'=>'qa-page-'.$suffix,'page_type'=>'content','hero_heading'=>'Original live heading','body'=>'<p>Original live body</p>','status'=>'published'];
    $r=edit('pages',null,$pageData,'publish');$pageId=(int)DB::value('SELECT id FROM pages WHERE slug=?',[$pageData['slug']]);if($pageId)$created[]=['pages',$pageId];check($r['status']===303&&$pageId>0,'Page creation persists in MySQL');
    $r=req('/'.$pageData['slug'].'/',null,true);check($r['status']===200&&str_contains($r['body'],'Original live heading'),'Published page renders');
    $draftData=$pageData;$draftData['hero_heading']='Unpublished secret heading';$r=edit('pages',$pageId,$draftData,'draft');check($r['status']===303,'Save draft');
    check(!str_contains(req('/'.$pageData['slug'].'/',null,true)['body'],'Unpublished secret heading'),'Draft changes do not leak into live page');
    check(str_contains(req('/admin/preview/pages/'.$pageId)['body'],'Unpublished secret heading'),'Authenticated draft preview');
    check(req('/admin/preview/pages/'.$pageId,null,true)['status']===303,'Anonymous preview is blocked');
    $r=edit('pages',$pageId,$draftData,'publish');check($r['status']===303&&str_contains(req('/'.$pageData['slug'].'/',null,true)['body'],'Unpublished secret heading'),'Publishing promotes draft');
    check((int)DB::value('SELECT COUNT(*) FROM content_revisions WHERE entity=? AND entity_id=?',['pages',$pageId])>0,'Revision history saved');
    $r=edit('pages',$pageId,$draftData,'unpublish');check($r['status']===303&&req('/'.$pageData['slug'].'/',null,true)['status']===404,'Unpublish removes public page');
    $future=$pageData;$future['publish_at']=gmdate('Y-m-d\TH:i',time()+86400);$r=edit('pages',$pageId,$future,'publish');check($r['status']===303&&req('/'.$pageData['slug'].'/',null,true)['status']===404,'Scheduled publication remains hidden until date');
    $brand=insert('brands',['title'=>'QA Brand '.$suffix,'slug'=>'qa-brand-'.$suffix,'status'=>'published']);$category=insert('categories',['title'=>'QA Category '.$suffix,'slug'=>'qa-category-'.$suffix,'status'=>'published']);$sub=insert('subcategories',['title'=>'QA Subcategory '.$suffix,'slug'=>'qa-sub-'.$suffix,'category_id'=>$category,'status'=>'published']);
    $productData=['title'=>'QA Product '.$suffix,'slug'=>'qa-product-'.$suffix,'description'=>'<p>Technical product description</p>','brand_id'=>$brand,'category_id'=>$category,'subcategory_id'=>$sub,'status'=>'published','is_featured'=>1,'button_label'=>'Learn More'];
    $r=edit('products',null,$productData,'publish');$product=(int)DB::value('SELECT id FROM products WHERE slug=?',[$productData['slug']]);if($product)$created[]=['products',$product];check($r['status']===303&&$product>0,'Product editor creates classified product');
    check(str_contains(req('/products/?brand='.$brand.'&category='.$category.'&sub_category='.$sub,null,true)['body'],$productData['title']),'Combined catalogue filters');
    check(str_contains(req('/products/'.$productData['slug'].'/',null,true)['body'],'Technical product description'),'Product detail page');
    check(str_contains(req('/search/?q='.rawurlencode($suffix),null,true)['body'],$productData['title']),'Search includes published product');
    check(req('/products/?q='.rawurlencode("' OR 1=1 --"),null,true)['status']===200,'Search SQL injection is treated as text');
    $bad=$productData;$bad['category_id']='';$r=edit('products',$product,$bad);check($r['status']===200&&str_contains($r['body'],'Select a subcategory belonging'),'Subcategory relationship validation');
    $menu=insert('navigation_menus',['title'=>'QA Menu '.$suffix,'location'=>'header']);
    $parent=insert('navigation_items',['menu_id'=>$menu,'title'=>'QA Dropdown '.$suffix,'menu_type'=>'dropdown','custom_url'=>'/products/']);
    $child=insert('navigation_items',['menu_id'=>$menu,'parent_id'=>$parent,'title'=>'QA Link '.$suffix,'link_type'=>'product','product_id'=>$product]);
    check(str_contains(req('/',null,true)['body'],'QA Link '.$suffix),'Database navigation items render');
    [$data,$errors]=Validator::record('navigation_items',['menu_id'=>$menu,'parent_id'=>$child,'title'=>'Cycle','menu_type'=>'dropdown','link_type'=>'custom','custom_url'=>'/products/'],$parent);check(isset($errors['parent_id']),'Menu cycles rejected');
    $r=req('/admin/navigation');$csrf=token($r);$r=req('/admin/navigation_items/reorder',['_token'=>$csrf,'ids'=>[$child,$parent]],false,['Accept: application/json']);check($r['status']===200&&(int)DB::value('SELECT sort_order FROM navigation_items WHERE id=?',[$parent])===10,'Menu reorder persists');
    $fake=ROOT.'/storage/invalid-upload.jpg';file_put_contents($fake,'<?php echo "unsafe";');$r=req('/admin/upload',['_token'=>$csrf,'files[]'=>new CURLFile($fake,'image/jpeg','invalid.jpg')],false,['Accept: application/json']);check($r['status']===422,'Disguised executable upload rejected');unlink($fake);
    $r=req('/admin/upload',['_token'=>$csrf,'files[]'=>new CURLFile(ROOT.'/public/assets/img/global-trade-lines-favicon-48.png','image/png','qa-'.$suffix.'.png')],false,['Accept: application/json']);$uploaded=json_decode($r['body'],true);$media=(int)($uploaded['files'][0]['id']??0);if($media)$created[]=['media_files',$media];check($r['status']===200&&$media>0,'Valid image upload persists');
    check(req('/media/'.$media,null,true)['status']===404,'Unreferenced uploads are not publicly served');
    DB::update('products',$product,['image_id'=>$media]);check(req('/media/'.$media,null,true)['status']===200,'Published image is served');
    $r=req('/admin/media_files/'.$media.'/delete',['_token'=>$csrf]);check($r['status']===422,'Referenced media cannot be deleted');
    $r=req('/admin/products/'.$product);preg_match('/name="_version" value="([a-zA-Z0-9]+)"/',$r['body'],$vm);DB::update('products',$product,['badge'=>'Concurrent change']);$r=req('/admin/products/'.$product,$productData+['_token'=>token($r),'_version'=>$vm[1],'intent'=>'publish']);check($r['status']===409,'Concurrent edits cannot overwrite newer changes');
    $editorPassword=bin2hex(random_bytes(16));$editor=insert('admin_users',['name'=>'QA Editor','email'=>'editor-'.$suffix.'@example.test','password_hash'=>password_hash($editorPassword,PASSWORD_DEFAULT),'role_id'=>2,'is_active'=>1]);
    $r=req('/admin/login',null,true);$r=req('/admin/login',['_token'=>token($r),'email'=>'editor-'.$suffix.'@example.test','password'=>$editorPassword],true);check($r['status']===303,'Content editor login');
    check(req('/admin/pages',null,true)['status']===200,'Editor can manage pages');check(req('/admin/products',null,true)['status']===403,'Editor blocked from catalogue');check(req('/admin/admin_users',null,true)['status']===403,'Editor blocked from user management');
    $r=req('/admin/pages',null,true);$editorToken=token($r);check(req('/admin/products/'.$product.'/delete',['_token'=>$editorToken],true)['status']===403,'Mutation permissions enforced server-side');
    $r=req('/contact/');$formToken=token($r);sleep(2);$r=req('/enquiry/',['_token'=>$formToken,'name'=>'QA Enquiry '.$suffix,'email'=>'visitor@example.test','message'=>'Testing secure enquiry storage','requirement'=>'QA '.$suffix,'website_url'=>'']);$enquiry=(int)DB::value('SELECT id FROM enquiries WHERE name=?',['QA Enquiry '.$suffix]);if($enquiry)$created[]=['enquiries',$enquiry];check($r['status']===303&&$enquiry>0,'Contact form stores enquiry');check(DB::value('SELECT notification_status FROM enquiries WHERE id=?',[$enquiry])==='disabled','Disabled mail status recorded accurately');
    $csv=ROOT.'/storage/qa-import.csv';$fp=fopen($csv,'w');fputcsv($fp,['brand','category','sub_category','product','description','is_active','is_featured']);fputcsv($fp,['QA Brand '.$suffix,'QA Category '.$suffix,'QA Subcategory '.$suffix,'QA Imported '.$suffix,'Import description','1','0']);fclose($fp);
    $r=req('/admin/import');$r=req('/admin/import',['_token'=>token($r),'action'=>'preview','csv'=>new CURLFile($csv,'text/csv','products.csv')]);check($r['status']===200&&str_contains($r['body'],'0 validation errors'),'CSV validates and previews');
    if(preg_match('/name="import_token" value="([a-zA-Z0-9]+)"/',$r['body'],$im)){$r=req('/admin/import',['_token'=>token($r),'action'=>'commit','import_token'=>$im[1],'allow_updates'=>1]);$imported=(int)DB::value('SELECT id FROM products WHERE slug=?',[slugify('QA Imported '.$suffix)]);if($imported)$created[]=['products',$imported];check($r['status']===200&&$imported>0,'CSV commit persists product');}else check(false,'CSV commit token present');unlink($csv);
    check(req('/admin/products/export')['status']===200,'Product CSV export');check(req('/admin/enquiries/export')['status']===200,'Enquiry CSV export');
    $heroSection=insert('page_sections',['page_id'=>$pageId,'title'=>'QA hero parent','section_type'=>'hero','status'=>'draft']);
    $album=insert('gallery_albums',['title'=>'QA album parent','slug'=>'qa-album-'.$suffix,'status'=>'published']);
    $mega=insert('navigation_items',['menu_id'=>$menu,'title'=>'QA mega parent','menu_type'=>'mega','custom_url'=>'/products/']);
    $megaGroup=insert('mega_menu_groups',['navigation_item_id'=>$mega,'title'=>'QA group parent','content_mode'=>'manual']);
    $footerColumn=insert('footer_columns',['title'=>'QA footer parent','content_mode'=>'manual']);
    $pdf=ROOT.'/storage/qa-document.pdf';file_put_contents($pdf,"%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Count 0 /Kids [] >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");
    $r=req('/admin/upload',['_token'=>$csrf,'files[]'=>new CURLFile($pdf,'application/pdf','qa-'.$suffix.'.pdf')],false,['Accept: application/json']);$pdfResult=json_decode($r['body'],true);$pdfId=(int)($pdfResult['files'][0]['id']??0);if($pdfId)$created[]=['media_files',$pdfId];check($pdfId>0,'PDF upload validation');unlink($pdf);
    $crudCases=[
        'navigation_menus'=>['title'=>'Menu','location'=>'header','is_active'=>1],
        'navigation_items'=>['title'=>'Menu link','menu_id'=>$menu,'menu_type'=>'link','link_type'=>'custom','custom_url'=>'/products/','is_active'=>1],
        'mega_menu_groups'=>['title'=>'Menu group','navigation_item_id'=>$mega,'content_mode'=>'categories','display_limit'=>3,'is_active'=>1],
        'mega_menu_items'=>['title'=>'Manual menu link','group_id'=>$megaGroup,'link_type'=>'product','product_id'=>$product,'is_active'=>1],
        'page_sections'=>['title'=>'Section','page_id'=>$pageId,'section_type'=>'cta','heading'=>'Call to action','button_label'=>'Browse','button_url'=>'/products/'],
        'hero_slides'=>['title'=>'Hero slide','section_id'=>$heroSection,'external_image_url'=>'https://example.test/image.jpg','duration'=>4],
        'section_items'=>['title'=>'Statistic item','section_id'=>$heroSection,'stat_value'=>12,'stat_source'=>'manual','prefix'=>'$','suffix'=>'+'],
        'product_images'=>['title'=>'Product image','product_id'=>$product,'image_id'=>$media],
        'product_specifications'=>['title'=>'Specification','product_id'=>$product,'value'=>'230 V'],
        'gallery_albums'=>['title'=>'Gallery album','slug'=>'crud-album-'.$suffix,'platform'=>'images'],
        'gallery_items'=>['title'=>'Gallery item','album_id'=>$album,'image_id'=>$media,'caption'=>'Caption'],
        'brochures'=>['title'=>'Brochure','file_id'=>$pdfId,'image_id'=>$media,'brand_id'=>$brand,'category_id'=>$category],
        'career_jobs'=>['title'=>'Job','department'=>'QA','location'=>'Test location','description'=>'<p>Fixture</p>','apply_email'=>'jobs@example.test'],
        'enquiry_notes'=>['title'=>'Internal note','enquiry_id'=>$enquiry],
        'footer_columns'=>['title'=>'Footer column','placement'=>'bottom','content_mode'=>'manual','is_active'=>1],
        'footer_menu_items'=>['title'=>'Footer link','column_id'=>$footerColumn,'link_type'=>'custom','custom_url'=>'/products/','is_active'=>1],
        'social_links'=>['title'=>'Social link','icon'=>'fa-brands fa-linkedin','custom_url'=>'https://example.test/','is_active'=>1],
        'roles'=>['title'=>'Custom role','permissions'=>['enquiries']],
        'admin_users'=>['name'=>'QA Managed User','email'=>'managed-'.$suffix.'@example.test','password'=>bin2hex(random_bytes(16)),'role_id'=>4,'is_active'=>1],
        'brands'=>['title'=>'Managed brand','slug'=>'crud-brand-'.$suffix,'website'=>'https://example.test/'],
        'categories'=>['title'=>'Managed category','slug'=>'crud-category-'.$suffix,'icon'=>'fa-solid fa-box'],
        'subcategories'=>['title'=>'Managed subcategory','slug'=>'crud-subcategory-'.$suffix,'category_id'=>$category,'brand_ids'=>[$brand]],
    ];
    foreach($crudCases as $module=>$values){$label=Modules::labelField($module);$values[$label].=' '.$suffix;$intent=!empty(Modules::get($module)['drafts'])?'publish':'save';$r=edit($module,null,$values,$intent);$newId=0;if(preg_match('~Location: [^\r\n]*/admin/'.$module.'/(\d+)~i',$r['header'],$match))$newId=(int)$match[1];if($newId)$created[]=[$module,$newId];check($r['status']===303&&$newId>0,'CRUD create '.$module);if(!$newId)continue;$values[$label].=' revised';$r=edit($module,$newId,$values,$intent);check($r['status']===303&&DB::value('SELECT `'.$label.'` FROM `'.$module.'` WHERE id=?',[$newId])===$values[$label],'CRUD update '.$module);if($module==='brochures'){check(req('/brochures/'.$newId.'/pdf/',null,true)['status']===200,'Published brochure download');edit($module,$newId,$values,'unpublish');check(req('/brochures/'.$newId.'/pdf/',null,true)['status']===404,'Unpublished brochure download blocked');}$r=req('/admin/'.$module.'/'.$newId.'/delete',['_token'=>$csrf]);check($r['status']===303&&!DB::one('SELECT id FROM `'.$module.'` WHERE id=?',[$newId]),'CRUD delete '.$module);}
    $r=req('/admin/upload',['_token'=>$csrf,'album_id'=>$album,'files[]'=>new CURLFile(ROOT.'/public/assets/img/global-trade-lines-favicon-48.png','image/png','batch-'.$suffix.'.png')],false,['Accept: application/json']);$batch=json_decode($r['body'],true);$batchId=(int)($batch['files'][0]['id']??0);$galleryId=(int)DB::value('SELECT id FROM gallery_items WHERE image_id=? AND album_id=?',[$batchId,$album]);if($batchId)$created[]=['media_files',$batchId];if($galleryId)$created[]=['gallery_items',$galleryId];check($r['status']===200&&$galleryId>0&&DB::value('SELECT status FROM gallery_items WHERE id=?',[$galleryId])==='draft','Batch gallery upload creates draft entries');
    $settings=DB::all("SELECT setting_key,value FROM site_settings WHERE group_name='appearance'");$appearance=array_column($settings,'value','setting_key');$r=req('/admin/settings/appearance');$newAppearance=$appearance;$newAppearance['primary_color']='#123456';$r=req('/admin/settings/appearance',$newAppearance+['_token'=>token($r)]);check($r['status']===303&&str_contains(req('/',null,true)['body'],'#123456'),'Appearance settings reach public CSS');$r=req('/admin/settings/appearance');req('/admin/settings/appearance',$appearance+['_token'=>token($r)]);
    $r=req('/admin/products/'.$product.'/duplicate',['_token'=>$csrf]);$copy=(int)DB::value('SELECT id FROM products WHERE title=? ORDER BY id DESC LIMIT 1',[$productData['title'].' (copy)']);if($copy)$created[]=['products',$copy];check($r['status']===303&&$copy>0&&DB::value('SELECT status FROM products WHERE id=?',[$copy])==='draft','Duplicate product is a draft');
    $revision=(int)DB::value("SELECT id FROM content_revisions WHERE entity='pages' AND entity_id=? ORDER BY id LIMIT 1",[$pageId]);$r=req('/admin/pages/'.$pageId.'/restore',['_token'=>$csrf,'revision_id'=>$revision]);check($r['status']===303&&DB::one("SELECT entity_id FROM content_drafts WHERE entity='pages' AND entity_id=?",[$pageId])!==null,'Page revision restores as draft');
    $r=req('/admin/admin_users/1/delete',['_token'=>$csrf]);check($r['status']===422,'Active administrator cannot delete self');
    check(req('/storage/test-access.json',null,true)['status']===404,'Private filesystem files cannot be read over HTTP');
    $r=req('/admin/options?table=products&q='.rawurlencode($productData['title']));$options=json_decode($r['body'],true);check($r['status']===200&&in_array($product,array_column($options['options']??[],'id')),'Searchable relationship picker finds matching records');
    $latest=insert('products',['title'=>'Latest QA '.$suffix,'slug'=>'latest-qa-'.$suffix,'status'=>'published','sort_order'=>999999]);$recent=\App\Services\Navigation::groupItems(['content_mode'=>'recent_products','display_limit'=>1]);check((int)($recent[0]['id']??0)===$latest,'Recent products are selected before display limit');
    $r=req('/admin/logout',['_token'=>$csrf]);check($r['status']===303&&req('/admin')['status']===303,'Logout clears authenticated access');
    $r=req('/admin/login');$r=req('/admin/login',['_token'=>token($r),'email'=>$access['email'],'password'=>'deliberately-incorrect-password']);check($r['status']===200&&str_contains($r['body'],'The email or password is incorrect.')&&req('/admin')['status']===303,'Incorrect password is rejected');
}catch(Throwable $e){$failed++;echo 'FAIL Unexpected exception: '.$e->getMessage()."\n";}
finally{
    foreach(array_reverse($created) as [$module,$id]){try{if($module==='media_files'){DB::run('UPDATE products SET image_id=NULL WHERE image_id=?',[$id]);$mediaRow=DB::one('SELECT path FROM media_files WHERE id=?',[$id]);if($mediaRow&&str_starts_with($mediaRow['path'],'uploads/'))@unlink(storage_path('app/private/'.$mediaRow['path']));}DB::run('DELETE FROM content_drafts WHERE entity=? AND entity_id=?',[$module,$id]);DB::run('DELETE FROM content_revisions WHERE entity=? AND entity_id=?',[$module,$id]);DB::run('DELETE FROM `'.$module.'` WHERE id=?',[$id]);}catch(Throwable $e){echo 'Cleanup notice: '.$module.' #'.$id.' '.$e->getMessage()."\n";}}
    @unlink($cookie);@unlink($anon);
}
echo "\n$passed passed; $failed failed.\n";exit($failed?1:0);
