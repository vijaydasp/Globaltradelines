<?php use App\Models\Content;use App\Services\Navigation;?>
<?php foreach(Content::published('footer_columns',"placement='bottom'") as $column):
    if($column['content_mode']==='contact') {
        $links=[];
        foreach(['address','phone','email','business_hours'] as $key) {
            $value=setting($key);if($value==='')continue;
            $href=match($key){'address'=>setting('map_link'),'phone'=>'tel:'.preg_replace('/[^+0-9]/','',$value),'email'=>'mailto:'.$value,default=>''};
            $links[]=['title'=>$value,'link_type'=>'custom','custom_url'=>$href,'target'=>'_self'];
        }
    } else {
        $links=$column['content_mode']==='manual'
            ?Content::published('footer_menu_items','column_id=?',[$column['id']],(int)$column['display_limit'])
            :array_map(fn($r)=>$r+['link_type'=>'custom','custom_url'=>Navigation::recordUrl($column['content_mode'],$r),'target'=>'_self'],Content::published($column['content_mode'],'1=1',[],(int)$column['display_limit']));
    }
    $visible=[];foreach($links as $link){$href=Navigation::link($link);if($column['content_mode']==='contact'&&$link['custom_url']==='')$href=null;if($href!==null||$column['content_mode']==='contact')$visible[]=[$link,$href];}
    if(!$visible)continue;
?><span class="cms-footer-legal"><?php foreach($visible as $index=>[$link,$href]):if($index)echo ' &nbsp;·&nbsp; ';if($href):?><a href="<?=e(cms_url($href))?>" target="<?=e($link['target'])?>" rel="noopener noreferrer"><?=e($link['title'])?></a><?php else:?><?=e($link['title'])?><?php endif;endforeach?></span><?php endforeach?>
