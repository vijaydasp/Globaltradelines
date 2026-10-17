<?php
declare(strict_types=1);
namespace App\Models;
use App\Core\DB;
use App\Core\HttpException;
use App\Services\Auth;
use App\Services\Audit;

final class Content {
    public static bool $preview=false;
    public static function find(string $module,int $id,bool $draft=false): ?array {
        Modules::get($module);
        $row=ContentRecord::forModule($module)->newQuery()->find($id)?->getAttributes();
        if(!$row) return null;
        foreach(Modules::get($module)['fields'] as $key=>$field) {
            if($field['type']==='multirelation') $row[$key]=array_column(DB::all('SELECT `'.$field['target'].'` FROM `'.$field['junction'].'` WHERE `'.$field['owner'].'`=?',[$id]),$field['target']);
            if($field['type']==='permissions') $row[$key]=array_column(DB::all('SELECT permission_key FROM role_permissions WHERE role_id=?',[$id]),'permission_key');
        }
        if($draft&&($pending=DB::one('SELECT payload FROM content_drafts WHERE entity=? AND entity_id=?',[$module,$id]))) $row=array_replace($row,json_decode($pending['payload'],true,512,JSON_THROW_ON_ERROR));
        return $row;
    }
    public static function version(string $module,int $id): string { return hash('sha256',json_encode(self::find($module,$id,true))); }
    public static function liveWhere(string $module,string $alias=''): string {
        $fields=Modules::get($module)['fields']; $a=$alias!==''?$alias.'.':'';
        if(isset($fields['status'])&&$module!=='enquiries') return $a."status='published' AND (".$a.'publish_at IS NULL OR '.$a.'publish_at<=UTC_TIMESTAMP())';
        if(isset($fields['is_active'])) return $a.'is_active=1';
        return '1=1';
    }
    public static function isLive(array $row,string $module): bool {
        if(isset($row['status'])&&$module!=='enquiries'&&($row['status']!=='published'||(!empty($row['publish_at'])&&strtotime($row['publish_at'].' UTC')>time()))) return false;
        return !isset($row['is_active']) || (bool)$row['is_active'];
    }
    public static function published(string $module,string $where='1=1',array $params=[],int $limit=500,bool $recent=false): array {
        $def=Modules::get($module);
        $preview=self::$preview&&Auth::can($def['permission']);
        $order=$recent?'id DESC':(isset($def['fields']['sort_order'])?'sort_order,':'').'id';
        $rows=DB::all('SELECT * FROM `'.$module.'` WHERE '.($preview?'1=1':self::liveWhere($module)).' AND ('.$where.') ORDER BY '.$order.' LIMIT '.max(1,min(1000,$limit)),$params);
        if($preview) $rows=array_map(fn($r)=>self::find($module,(int)$r['id'],true),$rows);
        return $rows;
    }
    public static function accessible(string $module,int $id,array $seen=[]): bool {
        if(isset($seen[$module.':'.$id])) return false;
        $seen[$module.':'.$id]=true;
        $row=self::find($module,$id);
        if(!$row||!self::isLive($row,$module)) return false;
        $parents=['page_sections'=>['pages','page_id'],'hero_slides'=>['page_sections','section_id'],'section_items'=>['page_sections','section_id'],'product_images'=>['products','product_id'],'product_specifications'=>['products','product_id'],'gallery_items'=>['gallery_albums','album_id'],'navigation_items'=>['navigation_menus','menu_id'],'mega_menu_groups'=>['navigation_items','navigation_item_id'],'mega_menu_items'=>['mega_menu_groups','group_id'],'footer_menu_items'=>['footer_columns','column_id']];
        if($module==='navigation_items'&&!empty($row['parent_id'])&&!self::accessible('navigation_items',(int)$row['parent_id'],$seen)) return false;
        if(isset($parents[$module])) {[$parent,$key]=$parents[$module]; return !empty($row[$key])&&self::accessible($parent,(int)$row[$key],$seen);}
        return true;
    }
    public static function save(string $module,array $data,?int $id,string $intent,string $version=''): int {
        $def=Modules::get($module); Auth::require($def['permission']);
        return DB::transaction(function() use($module,$data,$id,$intent,$version,$def): int {
            if($module==='admin_users') DB::all('SELECT id FROM roles WHERE is_super=1 FOR UPDATE');
            if($id) {
                if(!DB::one('SELECT id FROM `'.$module.'` WHERE id=? FOR UPDATE',[$id])) throw new HttpException(404,'Record not found.');
                if($version!==''&&!hash_equals(self::version($module,$id),$version)) throw new HttpException(409,'Someone changed this record while you were editing. Reload it before saving.');
            }
            if($module==='admin_users') self::protectAdmin($id,$data);
            if(!empty($def['drafts'])) {
                if($intent==='publish') $data['status']='published';
                if($intent==='unpublish') $data['status']='unpublished';
                if($intent==='draft') {
                    if(!$id) { $insert=self::columns($module,$data); $insert['status']='draft'; $id=DB::insert($module,$insert); }
                    DB::run('INSERT INTO content_drafts (entity,entity_id,payload,editor_id) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),editor_id=VALUES(editor_id)',[$module,$id,json_encode($data,JSON_THROW_ON_ERROR),Auth::id()]);
                    Audit::record('draft',$module,$id,'Saved draft'); return $id;
                }
                if($id) DB::insert('content_revisions',['entity'=>$module,'entity_id'=>$id,'payload'=>json_encode(self::find($module,$id),JSON_THROW_ON_ERROR),'editor_id'=>Auth::id()]);
            }
            $fields=self::columns($module,$data);
            if($module==='admin_users') {
                if(!empty($data['password'])) $fields['password_hash']=\Illuminate\Support\Facades\Hash::make($data['password']);
                if($id) $fields['auth_version']=(int)DB::value('SELECT auth_version FROM admin_users WHERE id=?',[$id])+1;
                $fields['email']=strtolower($fields['email']);
            }
            $fields['updated_by']=Auth::id();
            if($id) DB::update($module,$id,$fields); else $id=DB::insert($module,$fields);
            foreach($def['fields'] as $key=>$field) {
                if($field['type']==='multirelation') {
                    DB::run('DELETE FROM `'.$field['junction'].'` WHERE `'.$field['owner'].'`=?',[$id]);
                    foreach($data[$key]??[] as $value) DB::insert($field['junction'],[$field['owner']=>$id,$field['target']=>$value]);
                }
                if($field['type']==='permissions') {
                    DB::run('DELETE FROM role_permissions WHERE role_id=?',[$id]);
                    foreach($data[$key]??[] as $value) DB::insert('role_permissions',['role_id'=>$id,'permission_key'=>$value]);
                }
            }
            DB::run('DELETE FROM content_drafts WHERE entity=? AND entity_id=?',[$module,$id]);
            Audit::record($intent,$module,$id,(string)($data['title']??$data['name']??'Saved record'));
            if($module==='admin_users'&&$id===Auth::id()) session()->put('auth_version',$fields['auth_version']);
            return $id;
        });
    }
    private static function columns(string $module,array $data): array { return array_intersect_key($data,array_flip(Modules::columns($module))); }
    private static function protectAdmin(?int $id,array $data): void {
        if(!$id) return;
        $user=DB::one('SELECT u.*,r.is_super FROM admin_users u JOIN roles r ON u.role_id=r.id WHERE u.id=? FOR UPDATE',[$id]);
        if(!$user) throw new HttpException(404,'User not found.');
        if($id===Auth::id()&&empty($data['is_active'])) throw new HttpException(422,'You cannot disable your own account.');
        if($user['is_super']&&$user['is_active']&&(!(int)DB::value('SELECT is_super FROM roles WHERE id=?',[$data['role_id']])||empty($data['is_active']))) {
            if((int)DB::value('SELECT COUNT(*) FROM admin_users u JOIN roles r ON r.id=u.role_id WHERE r.is_super=1 AND u.is_active=1')<=1) throw new HttpException(422,'Keep at least one active Super Administrator.');
        }
    }
    public static function delete(string $module,int $id): void {
        $def=Modules::get($module); Auth::require($def['permission']);
        if(!self::find($module,$id)) throw new HttpException(404,'Record not found.');
        if($module==='pages'&&DB::value('SELECT page_type FROM pages WHERE id=?',[$id])==='home') throw new HttpException(422,'The homepage cannot be deleted.');
        if($module==='roles'&&DB::value('SELECT is_super FROM roles WHERE id=?',[$id])) throw new HttpException(422,'The Super Administrator role cannot be deleted.');
        if($module==='media_files') \App\Services\Media::delete($id);
        else DB::transaction(function()use($module,$id): void {
            if($module==='admin_users'){DB::all('SELECT id FROM roles WHERE is_super=1 FOR UPDATE');self::protectAdmin($id,['role_id'=>0,'is_active'=>0]);}
            DB::run('DELETE FROM `'.$module.'` WHERE id=?',[$id]);
            DB::run('DELETE FROM content_drafts WHERE entity=? AND entity_id=?',[$module,$id]);
            DB::run('DELETE FROM content_revisions WHERE entity=? AND entity_id=?',[$module,$id]);
            if($module==='enquiries') DB::run('DELETE FROM content_revisions WHERE entity=? AND entity_id=?',[$module,$id]);
            Audit::record('delete',$module,$id,'Deleted record');
        });
    }
}
