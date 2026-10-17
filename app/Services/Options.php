<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{DB,HttpException};
use App\Models\Modules;
final class Options {
    public static function search(string $table,string $query='',array $selected=[]): array {
        Modules::get($table);$label='t.`'.Modules::labelField($table).'`';$join='';
        if($table==='page_sections'){$label="CONCAT(t.title,' — ',p.title)";$join=' LEFT JOIN pages p ON p.id=t.page_id';}
        if($table==='navigation_items'){$label="CONCAT(t.title,' — ',p.title)";$join=' LEFT JOIN navigation_menus p ON p.id=t.menu_id';}
        if($table==='subcategories'){$label="CONCAT(t.title,' — ',p.title)";$join=' LEFT JOIN categories p ON p.id=t.category_id';}
        $params=[];$where='1=1';if($query!==''){$where=$label.' LIKE ?';$params[]='%'.mb_substr($query,0,200).'%';}
        $rows=DB::all('SELECT t.id,'.$label.' AS label FROM `'.$table.'` t'.$join.' WHERE '.$where.' ORDER BY label,t.id LIMIT 100',$params);
        $included=array_column($rows,'id');foreach(array_unique(array_map('intval',$selected)) as $id)if($id>0&&!in_array($id,$included)){if($row=DB::one('SELECT t.id,'.$label.' AS label FROM `'.$table.'` t'.$join.' WHERE t.id=?',[$id]))$rows[]=$row;}
        return $rows;
    }
    public static function requireAccess(string $table): void {
        Auth::require();Modules::get($table);
        foreach(Modules::all() as $definition){if(!Auth::can($definition['permission']))continue;foreach($definition['fields'] as $field)if(in_array($field['type'],['relation','multirelation'],true)&&($field['table']??'')===$table)return;}
        throw new HttpException(403,'Your role cannot browse these records.');
    }
}
