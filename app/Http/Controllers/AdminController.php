<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Core\DB;
use App\Core\HttpException;
use App\Models\Modules;
use App\Models\Content;
use App\Models\SettingFields;
use App\Services\{Auth,Audit,Validator,Media,Settings};

final class AdminController {
    public static function page(string $title,string $template,array $data=[]): \Illuminate\Http\Response {return response()->view('admin.layout',['title'=>$title,'template'=>$template,'data'=>$data]);}
    public function authentication(): \Illuminate\Http\Response {
        $path=request_path();
        if(Auth::user())cms_redirect('/admin');
        $error='';$message='';
        if(request()->method()==='POST') {
            
            if($path==='/admin/login') {
                if(Auth::login((string)(request()->post()['email']??''),(string)(request()->post()['password']??'')))cms_redirect('/admin');
                $error='The email or password is incorrect.';
            } elseif($path==='/admin/forgot-password') {
                \App\Services\Mail::requestReset((string)(request()->post()['email']??''));
                $message='If the account exists and email is configured, a reset link has been sent.';
            } else {
                $token=(string)(request()->post()['token']??'');$password=(string)(request()->post()['password']??'');
                if(strlen($password)<12||strlen($password)>72)$error='Use a password of 12–72 characters.';
                else {
                    $ok=DB::transaction(function()use($token,$password):bool{
                        $reset=DB::one('SELECT * FROM password_resets WHERE token_hash=? AND expires_at>UTC_TIMESTAMP() FOR UPDATE',[hash('sha256',$token)]);
                        if(!$reset)return false;
                        DB::run('UPDATE admin_users SET password_hash=?,auth_version=auth_version+1 WHERE id=?',[\Illuminate\Support\Facades\Hash::make($password),$reset['user_id']]);
                        DB::run('DELETE FROM password_resets WHERE user_id=?',[$reset['user_id']]);
                        Audit::record('password_reset','admin_users',(int)$reset['user_id'],'Email password reset');return true;
                    });
                    if($ok){flash('Password updated. Sign in with your new password.');cms_redirect('/admin/login');}$error='This reset link is invalid or has expired.';
                }
            }
        }
        return response()->view('admin.login',compact('path','error','message'));
    }
    public function password(): \Illuminate\Http\Response {
        $error='';
        if(request()->method()==='POST') {
            $u=Auth::user();$new=(string)(request()->post()['new_password']??'');
            if(!\Illuminate\Support\Facades\Hash::check((string)(request()->post()['current_password']??''),$u['password_hash']))$error='The current password is incorrect.';
            elseif(strlen($new)<12||strlen($new)>72)$error='Use 12–72 characters.';
            else{DB::run('UPDATE admin_users SET password_hash=?,auth_version=auth_version+1 WHERE id=?',[\Illuminate\Support\Facades\Hash::make($new),$u['id']]);Audit::record('password_change','admin_users',(int)$u['id'],'Changed own password');Auth::clear();flash('Password updated. Please sign in again.');cms_redirect('/admin/login');}
        }
        return self::page('Change password','password',compact('error'));
    }
    public function dashboard(): \Illuminate\Http\Response {
        $stats=[];
        foreach(['products','brands','categories','subcategories','pages','gallery_items','enquiries','career_jobs'] as $module) {
            $def=Modules::get($module);if(!Auth::can($def['permission']))continue;
            $where=match($module){'pages'=>Content::liveWhere('pages'),'career_jobs'=>Content::liveWhere('career_jobs')." AND (deadline IS NULL OR deadline>=UTC_TIMESTAMP())",default=>'1=1'};
            $stats[$module]=['label'=>$module==='pages'?'Published pages':($module==='career_jobs'?'Active job vacancies':$def['label']),'count'=>(int)DB::value('SELECT COUNT(*) FROM `'.$module.'` WHERE '.$where)];
        }
        $recent=Auth::can('enquiries')?DB::all('SELECT * FROM enquiries ORDER BY id DESC LIMIT 5'):[];
        $new=Auth::can('enquiries')?(int)DB::value("SELECT COUNT(*) FROM enquiries WHERE status='new'"):0;
        $updates=[];
        foreach(DB::all('SELECT * FROM admin_activity_logs ORDER BY id DESC LIMIT 50') as $log) if(isset(Modules::all()[$log['entity']])&&Auth::can(Modules::get($log['entity'])['permission'])){$updates[]=$log;if(count($updates)===6)break;}
        return self::page('Overview','dashboard',compact('stats','recent','new','updates'));
    }
    public function listing(string $module): \Illuminate\Http\Response {
        $def=Modules::get($module);$where=['1=1'];$params=[];$q=trim((string)(request()->query()['q']??''));$label=Modules::labelField($module);
        if($module==='admin_activity_logs')$label='summary';
        if($q!==''){$where[]='`'.$label.'` LIKE ?';$params[]='%'.$q.'%';}
        foreach($def['fields'] as $key=>$field) if(($field['type']==='relation'||$key==='status')&&!empty(request()->query()[$key])&&!is_array(request()->query()[$key])){$where[]='`'.$key.'`=?';$params[]=request()->query()[$key];}
        if($module==='enquiries')foreach(['from'=>'>=','to'=>'<='] as $key=>$operator)if(preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)(request()->query()[$key]??''))){$where[]='created_at '.$operator.' ?';$params[]=request()->query()[$key].($key==='to'?' 23:59:59':' 00:00:00');}
        if($module==='media_files'&&!empty(request()->query()['type'])){$where[]='mime_type LIKE ?';$params[]=request()->query()['type'].'/%';}
        $sql=implode(' AND ',$where);$total=(int)DB::value('SELECT COUNT(*) FROM `'.$module.'` WHERE '.$sql,$params);$page=max(1,(int)(request()->query()['page']??1));$perPage=24;$offset=($page-1)*$perPage;
        $order=isset($def['fields']['sort_order'])?'sort_order,id':'id DESC';
        $rows=DB::all('SELECT * FROM `'.$module.'` WHERE '.$sql.' ORDER BY '.$order.' LIMIT '.$perPage.' OFFSET '.$offset,$params);
        $drafts=array_column(DB::all('SELECT entity_id FROM content_drafts WHERE entity=?',[$module]),'entity_id');
        return self::page($def['label'],$module==='media_files'?'media':'listing',compact('module','def','rows','drafts','total','page','perPage','q'));
    }
    public function edit(string $module,?string $id=null): \Illuminate\Http\Response {
        $id=$id===null||$id==='new'?null:(int)$id;
        $def=Modules::get($module);
        if(!empty($def['read_only'])||(!$id&&!empty($def['no_create'])))throw new HttpException(403,'This action is unavailable.');
        $record=$id?Content::find($module,$id,true):[];
        if($id&&!$record)throw new HttpException(404,'Record not found.');
        if(!$id)foreach($def['fields'] as $key=>$field)$record[$key]=$field['type']==='relation'?(request()->query()[$key]??''):($field['default']??'');
        $errors=[];
        if(request()->method()==='POST') {
            [$data,$errors]=Validator::record($module,request()->post(),$id);
            if(!$errors) {
                try{$saved=Content::save($module,$data,$id,(string)(request()->post()['intent']??'save'),(string)(request()->post()['_version']??''));flash((request()->post()['intent']??'')==='draft'?'Draft saved. The live website is unchanged.':'Changes saved.');cms_redirect('/admin/'.$module.'/'.$saved);}
                catch(\PDOException $e){if(in_array($e->getCode(),['23000','22001'],true))$errors['_global']='A value conflicts with another record or is too long. Check the fields and try again.';else throw $e;}
            }
            $record=array_replace($record,request()->post());
        }
        $version=$id?Content::version($module,$id):'';
        $revisions=$id&&!empty($def['drafts'])?DB::all('SELECT r.id,r.created_at,u.name FROM content_revisions r LEFT JOIN admin_users u ON u.id=r.editor_id WHERE entity=? AND entity_id=? ORDER BY r.id DESC LIMIT 20',[$module,$id]):[];
        return self::page(($id?'Edit ':'Add ').rtrim($def['label'],'s'),'edit',compact('module','def','record','errors','id','version','revisions'));
    }
    public function action(string $module,int $id,string $action): \Illuminate\Http\Response {
        if(request()->method()!=='POST')throw new HttpException(405,'Submit the action form.');
        $def=Modules::get($module);if(!empty($def['read_only']))throw new HttpException(403,'This record is read-only.');
        if($action==='delete') {
            try {Content::delete($module,$id);flash('Record deleted.');}
            catch(\PDOException $e){if($e->getCode()==='23000')flash('This record still has related items. Move or remove them first.','error');else throw $e;}
            cms_redirect('/admin/'.$module);
        }
        if($action==='restore') {
            $revision=DB::one('SELECT payload FROM content_revisions WHERE id=? AND entity=? AND entity_id=?',[(int)(request()->post()['revision_id']??0),$module,$id]);
            if(!$revision||empty($def['drafts']))throw new HttpException(404,'Revision not found.');
            [$data,$errors]=Validator::record($module,json_decode($revision['payload'],true),$id);
            if($errors)throw new HttpException(422,'This revision references removed or invalid content. Update the record manually.');
            Content::save($module,$data,$id,'draft');flash('Revision restored as a draft. Review it before publishing.');cms_redirect('/admin/'.$module.'/'.$id);
        }
        if(!empty($def['no_duplicate']))throw new HttpException(403,'This record cannot be duplicated.');
        $record=Content::find($module,$id,true);if(!$record)throw new HttpException(404,'Record not found.');
        $record['title']=($record['title']??'').' (copy)';
        if(isset($record['slug']))$record['slug']=slugify($record['slug'].'-copy-'.bin2hex(random_bytes(3)));
        if(($record['page_type']??'')==='home')$record['page_type']='content';
        if(isset($record['status']))$record['status']='draft';
        if(isset($record['is_active']))$record['is_active']=0;
        [$data,$errors]=Validator::record($module,$record);if($errors)throw new HttpException(422,'This record needs valid fields before it can be duplicated.');
        $new=Content::save($module,$data,null,'save');
        if($module==='pages') {
            foreach(DB::all('SELECT * FROM page_sections WHERE page_id=?',[$id]) as $section){$old=(int)$section['id'];$section['page_id']=$new;$sectionId=Content::save('page_sections',$section,null,'save');
                foreach(['section_items','hero_slides'] as $child)foreach(DB::all('SELECT * FROM `'.$child.'` WHERE section_id=?',[$old]) as $item){$item['section_id']=$sectionId;Content::save($child,$item,null,'save');}
            }
        }
        flash('Copy created for editing.');cms_redirect('/admin/'.$module.'/'.$new);
    }
    public function reorder(string $module): never {
        if(request()->method()!=='POST')throw new HttpException(405,'Use the reorder form.');
        if(!isset(Modules::get($module)['fields']['sort_order']))throw new HttpException(422,'This list cannot be reordered.');
        $ids=array_values(array_unique(array_map('intval',is_array(request()->post()['ids']??null)?request()->post()['ids']:[])));
        if(!$ids||count($ids)>1000)throw new HttpException(422,'Invalid ordering.');
        DB::transaction(function()use($module,$ids):void{foreach($ids as $index=>$id){DB::update($module,$id,['sort_order'=>$index*10,'updated_by'=>Auth::id()]);DB::run('UPDATE content_drafts SET payload=JSON_SET(payload,\'$.sort_order\',?) WHERE entity=? AND entity_id=?',[$index*10,$module,$id]);}Audit::record('reorder',$module,null,'Reordered items');});
        json_response(['ok'=>true,'message'=>'Order saved.']);
    }
    public function builder(string $kind): \Illuminate\Http\Response {
        Auth::require($kind==='homepage'?'content':'navigation');
        $page=null;$records=[];
        if($kind==='homepage'){
            $page=!empty(request()->query()['page_id'])?Content::find('pages',(int)request()->query()['page_id'],true):DB::one("SELECT * FROM pages WHERE page_type='home' LIMIT 1");
            if(!$page)throw new HttpException(404,'Page not found.');
            $records=DB::all('SELECT * FROM page_sections WHERE page_id=? ORDER BY sort_order,id',[$page['id']]);
            $records=array_map(fn($r)=>Content::find('page_sections',(int)$r['id'],true),$records);
        }elseif($kind==='navigation')$records=DB::all('SELECT * FROM navigation_menus ORDER BY sort_order,id');
        else $records=DB::all('SELECT * FROM footer_columns ORDER BY sort_order,id');
        return self::page(['homepage'=>'Visual page editor','navigation'=>'Navbar & Menus','footer'=>'Footer Management'][$kind],'builder',compact('kind','page','records'));
    }
    public function settings(string $group): \Illuminate\Http\Response {
        Auth::require($group==='header'||$group==='footer'?'navigation':'settings');
        $groups=SettingFields::groups();if(!isset($groups[$group]))throw new HttpException(404,'Settings not found.');
        [$label,$fields]=$groups[$group];
        if($group==='text')foreach(DB::all("SELECT setting_key FROM site_settings WHERE group_name='text' ORDER BY setting_key") as $row)$fields[$row['setting_key']]=[ucwords(str_replace('_',' ',substr($row['setting_key'],5))),'text'];
        $values=[];foreach($fields as $key=>$field)$values[$key]=setting($key);
        $errors=[];
        if(request()->method()==='POST'){
            foreach($fields as $key=>[$fieldLabel,$type]){
                $val=request()->post()[str_replace('.','_',$key)]??'';if(is_array($val)){$errors[$key]='Invalid value.';continue;}$val=trim((string)$val);
                if($type==='checkbox')$val=$val==='1'?'1':'0';
                if(mb_strlen($val)>10000)$errors[$key]='This value is too long.';
                if($type==='email'&&$val&&!filter_var($val,FILTER_VALIDATE_EMAIL))$errors[$key]='Enter a valid email.';
                if($type==='url'&&!safe_url($val))$errors[$key]='Use a valid URL.';
                if($type==='color'&&!preg_match('/^#[0-9a-f]{6}$/i',$val))$errors[$key]='Choose a valid color.';
                if($type==='year'&&(!ctype_digit($val)||(int)$val<1800||(int)$val>3000))$errors[$key]='Enter a founding year between 1800 and 3000.';
                if($type==='analytics'&&$val&&!preg_match('/^G-[A-Z0-9]+$/',$val))$errors[$key]='Use a Google Analytics measurement ID beginning G-.';
                if($type==='map'&&$val&&(!str_starts_with($val,'https://')||!safe_url($val)||!in_array(strtolower((string)parse_url($val,PHP_URL_HOST)),['www.google.com','maps.google.com','www.google.co.in','maps.google.co.in','www.openstreetmap.org'],true)))$errors[$key]='Use a Google Maps or OpenStreetMap HTTPS embed URL.';
                if($type==='mobile'&&!in_array($val,['toggle','expanded'],true))$errors[$key]='Choose a valid behavior.';
                if($type==='media'&&$val&&(!ctype_digit($val)||!str_starts_with(Media::find((int)$val)['mime_type']??'','image/')))$errors[$key]='Select an image.';
                $values[$key]=$val;
            }
            if(!$errors){DB::transaction(function()use($values,$group):void{DB::insert('content_revisions',['entity'=>'settings_'.$group,'entity_id'=>0,'payload'=>json_encode(DB::all('SELECT * FROM site_settings WHERE group_name=?',[$group])),'editor_id'=>Auth::id()]);foreach($values as $key=>$val)Settings::put($key,$val,$group);Audit::record('settings','site_settings',null,'Updated '.$group.' settings');});flash('Website settings saved.');cms_redirect('/admin/settings/'.$group);}
        }
        return self::page($label,'settings',compact('group','fields','values','errors'));
    }
    public function upload(): never {
        Auth::require('media');if(request()->method()!=='POST')throw new HttpException(405,'Use the upload form.');
        $albumId=(int)(request()->post()['album_id']??0);$productId=(int)(request()->post()['product_id']??0);
        if($albumId){Auth::require('content');if(!Content::find('gallery_albums',$albumId))throw new HttpException(422,'Choose an existing gallery album.');}
        if($productId){Auth::require('catalog');if(!Content::find('products',$productId))throw new HttpException(422,'Choose an existing product.');}
        $files=request()->file('files');if(!$files)throw new HttpException(422,'Choose at least one file.');
        $results=[];$errors=[];
        $files=is_array($files)?$files:[$files];
        foreach($files as $file){
            $name=$file->getClientOriginalName();
            try{
                if(($albumId||$productId)&&$file->isValid()&&!str_starts_with($file->getMimeType()?:'','image/'))throw new HttpException(422,'Select image files for this batch.');
                $id=Media::upload($file,!empty(request()->post()['replace_id'])?(int)request()->post()['replace_id']:null);$uploaded=Media::find($id);
                if($albumId)Content::save('gallery_items',['title'=>$uploaded['title'],'album_id'=>$albumId,'image_id'=>$id,'status'=>'draft'],null,'save');
                if($productId)Content::save('product_images',['title'=>$uploaded['title'],'product_id'=>$productId,'image_id'=>$id],null,'save');
                $results[]=['id'=>$id,'title'=>$uploaded['title'],'url'=>Media::url($id)];
            }
            catch(HttpException $e){$errors[]=$name.': '.$e->getMessage();}
        }
        if(str_contains(request()->header('Accept', ''),'application/json'))json_response(['files'=>$results,'errors'=>$errors],$errors?422:200);
        if($results)flash(count($results).' file(s) uploaded.'.($albumId?' Gallery entries were saved as drafts. Review and publish each entry.':''));foreach($errors as $error)flash($error,'error');cms_redirect($albumId?'/admin/gallery_items?album_id='.$albumId:($productId?'/admin/product_images?product_id='.$productId:'/admin/media_files'));
    }
    public function export(string $module): \Symfony\Component\HttpFoundation\StreamedResponse {
        if(!in_array($module,['products','enquiries'],true))throw new HttpException(404,'Export unavailable.');
        if($module==='products')return (new ImportController())->export();
        return response()->streamDownload(function(): void {
            $out=fopen('php://output','w');$keys=['id','name','email','phone','company','requirement','message','status','notification_status','created_at'];fputcsv($out,$keys,',','"','');
            foreach(DB::run('SELECT * FROM enquiries ORDER BY id DESC') as $row)fputcsv($out,array_map(fn($key)=>ImportController::csvSafe((string)$row[$key]),$keys),',','"','');fclose($out);
        },'enquiries.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }
    public function options(): never {\App\Services\Options::requireAccess((string)request('table'));json_response(['options'=>\App\Services\Options::search((string)request('table'),(string)request('q',''))]);}
    public function logout(): never {Auth::clear();cms_redirect('/admin/login');}
    public function search(): \Illuminate\Http\Response {
        $q=trim((string)(request()->query()['q']??''));$results=[];
        if($q!=='')foreach(Modules::all() as $module=>$def){if(!Auth::can($def['permission'])||!empty($def['read_only']))continue;$field=Modules::labelField($module);foreach(DB::all('SELECT id,`'.$field.'` AS label FROM `'.$module.'` WHERE `'.$field.'` LIKE ? LIMIT 10',['%'.$q.'%']) as $row)$results[]=$row+['module'=>$module,'type'=>$def['label']];}
        return self::page('Search your website','search',compact('q','results'));
    }
}
