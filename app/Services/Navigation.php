<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\Content;
use App\Core\DB;

final class Navigation {
    public static function reset(): void { self::$links=[]; }
    private static array $links=[];
    public static function link(array $item): ?string {
        $type=$item['link_type']??'custom';
        if($type==='custom')return safe_url($item['custom_url']??'')?($item['custom_url']?:'#'):null;
        $map=['page'=>['pages','page_id'],'product'=>['products','product_id'],'category'=>['categories','category_id'],'subcategory'=>['subcategories','subcategory_id'],'brand'=>['brands','brand_id']];
        if(!isset($map[$type]))return null;[$module,$key]=$map[$type];$id=(int)($item[$key]??0);
        $cache=$module.':'.$id;if(array_key_exists($cache,self::$links))return self::$links[$cache];
        $row=Content::find($module,$id);
        return self::$links[$cache]=(!$row||!Content::isLive($row,$module))?null:self::recordUrl($module,$row);
    }
    public static function recordUrl(string $module,array $row): string {
        return match($module){'pages'=>$row['page_type']==='home'?'/':'/'.$row['slug'].'/','products'=>'/products/'.$row['slug'].'/','brands'=>'/products/?brand='.$row['id'],'categories'=>'/products/?category='.rawurlencode($row['title']),'subcategories'=>'/products/?sub_category='.$row['id'],default=>'#'};
    }
    public static function groupItems(array $group): array {
        $mode=$group['content_mode'];$limit=(int)$group['display_limit'];
        if($mode==='manual')return Content::published('mega_menu_items','group_id=?',[$group['id']],$limit);
        $module=match($mode){'featured_products','recent_products'=>'products',default=>$mode};
        $items=Content::published($module,$mode==='featured_products'?'is_featured=1':'1=1',[],$limit,$mode==='recent_products');
        return array_map(fn($row)=>$row+['link_type'=>'custom','custom_url'=>self::recordUrl($module,$row),'target'=>'_self'],$items);
    }
    public static function renderItems(array $items,?int $parent=null,int $depth=0): void {
        if($depth>5)return;
        foreach($items as $item){
            if((int)($item['parent_id']??0)!==(int)($parent??0))continue;
            $link=self::link($item);if($link===null)continue;
            cms_view('public/nav-item',compact('item','items','link','depth'));
        }
    }
}
