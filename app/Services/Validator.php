<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\DB;
use App\Models\Modules;

final class Validator {
    public static function record(string $module,array $input,?int $id=null): array {
        $schema=Modules::get($module); $data=[]; $errors=[];
        foreach($schema['fields'] as $key=>$field) {
            $type=$field['type']; $raw=$input[$key]??($type==='checkbox'?0:($field['default']??''));
            if(in_array($type,['multirelation','permissions'],true)) {
                $values=is_array($raw)?$raw:[];
                if($type==='permissions') $data[$key]=array_values(array_intersect(array_keys(Modules::permissions()),$values));
                else {
                    $data[$key]=array_values(array_unique(array_filter(array_map('intval',$values))));
                    foreach($data[$key] as $value) if(!DB::one('SELECT id FROM `'.$field['table'].'` WHERE id=?',[$value])) $errors[$key]='Choose an existing record.';
                }
                continue;
            }
            if(is_array($raw)) {$errors[$key]='Invalid value.'; continue;}
            $value=trim((string)$raw);
            if($type==='number'&&$value==='')$value=(string)($field['default']??0);
            if($type==='slug') $value=slugify($value?:($input['title']??''));
            if($type==='checkbox') $value=(int)($value==='1');
            if($type==='relation') {
                $value=$value===''?null:filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
                if($value===false || ($value&&!DB::one('SELECT id FROM `'.$field['table'].'` WHERE id=?',[$value]))) $errors[$key]='Choose an existing record.';
            }
            if(!empty($field['required'])&&($value===null||$value==='')) $errors[$key]='This field is required.';
            if(is_string($value)&&mb_strlen($value)>($field['max']??(in_array($type,['textarea','richtext'])?100000:($type==='url'?2048:255)))) $errors[$key]='This value is too long.';
            if($value!==''&&$value!==null) {
                if($type==='email'&&!filter_var($value,FILTER_VALIDATE_EMAIL)) $errors[$key]='Enter a valid email address.';
                if($type==='url'&&!safe_url($value)) $errors[$key]='Use an https:// link or a path beginning with /.';
                if(in_array($key,['external_image_url','video_url'],true)&&!safe_url($value,true))$errors[$key]='Use an HTTP(S) media URL or a local website path.';
                if($key==='canonical_url'&&!preg_match('~^(https?://|/(?!/))~i',$value))$errors[$key]='A canonical URL must use HTTP(S) or a local website path.';
                if($type==='icon'&&!preg_match('/^[a-zA-Z0-9 -]*$/',$value)) $errors[$key]='Use only icon class names, such as fa-solid fa-box.';
                if($type==='select'&&!array_key_exists($value,$field['options'])) $errors[$key]='Choose a valid option.';
                if($type==='number') {
                    $value=filter_var($value,FILTER_VALIDATE_INT);
                    if($value===false||$value<($field['min']??0)||$value>($field['max']??1000000)) $errors[$key]='Enter a whole number between '.($field['min']??0).' and '.($field['max']??1000000).'.';
                }
                if($type==='datetime') {
                    $date=\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$value)?:\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value);
                    if(!$date || array_filter(\DateTimeImmutable::getLastErrors()?:[])) $errors[$key]='Enter a valid date and time.';
                    else $value=$date->format('Y-m-d H:i:s');
                }
            } elseif($type==='datetime') $value=null;
            if($type==='richtext') $value=Sanitizer::html($value);
            if($type==='password') {
                if(($value!==''||!$id)&&strlen($value)<12) $errors[$key]='Use a password of at least 12 characters.';
                if(strlen($value)>72) $errors[$key]='Use no more than 72 bytes.';
            }
            if(!empty($field['unique'])&&DB::one('SELECT id FROM `'.$module.'` WHERE `'.$key.'`=? AND id<>?',[$value,$id??0])) $errors[$key]='This value is already in use.';
            if($type==='relation' && ($field['table']??'')==='media_files' && $value) {
                $mime=DB::value('SELECT mime_type FROM media_files WHERE id=?',[$value]);
                if($key==='file_id'&&$module==='brochures'&&$mime!=='application/pdf') $errors[$key]='Select a PDF document.';
                elseif(str_contains($key,'video')&&!str_starts_with($mime,'video/')) $errors[$key]='Select an MP4 or WebM video.';
                elseif($key!=='file_id'&&!str_contains($key,'video')&&!str_starts_with($mime,'image/')) $errors[$key]='Select an image.';
            }
            $data[$key]=$value;
        }
        if($module==='pages') {
            if(in_array($data['slug']??'', ['admin','media','assets','enquiry','search','sitemap.xml','robots.txt','product','install'],true)) $errors['slug']='This address is reserved.';
            if(($data['page_type']??'')==='home'&&DB::one("SELECT id FROM pages WHERE page_type='home' AND id<>?",[$id??0])) $errors['page_type']='There is already a homepage. Edit that page instead.';
        }
        if($module==='products'&&!empty($data['subcategory_id'])) {
            $parent=DB::value('SELECT category_id FROM subcategories WHERE id=?',[$data['subcategory_id']]);
            if((int)$parent!==(int)($data['category_id']??0)) $errors['subcategory_id']='Select a subcategory belonging to the selected category.';
        }
        if($module==='hero_slides'&&!empty($data['section_id'])&&DB::value('SELECT section_type FROM page_sections WHERE id=?',[$data['section_id']])!=='hero') $errors['section_id']='Choose a Hero Slider section.';
        if($module==='navigation_items'&&!empty($data['parent_id'])) {
            $seen=[$id??0]; $parent=$data['parent_id'];
            while($parent) {
                if(in_array((int)$parent,$seen,true)) {$errors['parent_id']='A menu cannot be nested inside itself.'; break;}
                $seen[]=(int)$parent;
                $p=DB::one('SELECT * FROM navigation_items WHERE id=?',[$parent]);
                if(!$p||(int)$p['menu_id']!==(int)$data['menu_id']) {$errors['parent_id']='Choose a parent from the same menu.'; break;}
                if($p['menu_type']==='link') {$errors['parent_id']='The parent must be a dropdown or mega menu.'; break;}
                $parent=$p['parent_id'];
                if(count($seen)>6) {$errors['parent_id']='Use at most five menu levels.'; break;}
            }
        }
        if($module==='navigation_items'&&$id&&($data['menu_type']??'')==='link'&&(DB::value('SELECT COUNT(*) FROM navigation_items WHERE parent_id=?',[$id])||DB::value('SELECT COUNT(*) FROM mega_menu_groups WHERE navigation_item_id=?',[$id]))) $errors['menu_type']='Remove child items and mega menu groups before changing to a normal link.';
        if($module==='mega_menu_groups'&&!empty($data['navigation_item_id'])&&DB::value('SELECT menu_type FROM navigation_items WHERE id=?',[$data['navigation_item_id']])!=='mega') $errors['navigation_item_id']='Choose an item with the Mega menu style.';
        if(isset($data['link_type'])) {
            $column=['page'=>'page_id','category'=>'category_id','subcategory'=>'subcategory_id','brand'=>'brand_id','product'=>'product_id','custom'=>'custom_url'][$data['link_type']]??'custom_url';
            if(empty($data[$column])) $errors[$column]='Choose the destination for this link.';
            foreach(['page_id','category_id','subcategory_id','brand_id','product_id','custom_url'] as $col) if($col!==$column) $data[$col]=$col==='custom_url'?'':null;
        }
        return [$data,$errors];
    }
}
