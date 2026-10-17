<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Core\{DB,HttpException};
use App\Models\{Content,Modules};
use App\Services\{Auth,Navigation,RateLimit,Mail};

final class PublicController {
    public function show(?string $slug=null): \Illuminate\Http\Response {
        $page=$slug===null?DB::one("SELECT * FROM pages WHERE page_type='home' AND ".Content::liveWhere('pages').' LIMIT 1'):DB::one('SELECT * FROM pages WHERE slug=? AND '.Content::liveWhere('pages'),[$slug]);
        if(!$page)throw new HttpException(404,setting('text.not_found','Page not found'));
        return $this->page($page);
    }
    public function showProduct(string $slug): \Illuminate\Http\Response {
        $product=DB::one('SELECT * FROM products WHERE slug=? AND '.Content::liveWhere('products'),[$slug]);
        if(!$product)throw new HttpException(404,'Product not found.');return $this->product($product);
    }
    public function download(int $id): \Symfony\Component\HttpFoundation\BinaryFileResponse {
        $item=Content::find('brochures',$id);if(!$item||!Content::isLive($item,'brochures'))throw new HttpException(404,'Brochure not found.');return \App\Services\Media::serve((int)$item['file_id'],true);
    }
    public function robots(): \Illuminate\Http\Response {
        return response("User-agent: *\n".(setting('robots_index')==='1'?"Disallow: ".cms_base_path()."/admin/\nDisallow: ".cms_base_path()."/media/\n":"Disallow: /\n").'Sitemap: '.absolute_url('sitemap.xml')."\n",200,['Content-Type'=>'text/plain; charset=UTF-8']);
    }
    public function preview(string $module,int $id): \Illuminate\Http\Response {
        if(!in_array($module,['pages','products'],true))throw new HttpException(404,'Preview not found.');
        Auth::require(Modules::get($module)['permission']);Content::$preview=true;
        $record=Content::find($module,$id,true);if(!$record)throw new HttpException(404,'Record not found.');
        return ($module==='products'?$this->product($record):$this->page($record))->withHeaders(['Cache-Control'=>'no-store, private','X-Robots-Tag'=>'noindex, nofollow']);
    }
    public static function render(string $template,array $data,array $meta=[]): \Illuminate\Http\Response {
        return response()->view('public.layout',['template'=>$template,'data'=>$data,'page'=>$meta,'preview'=>Content::$preview]);
    }
    private function page(array $page): \Illuminate\Http\Response {
        $sections=Content::published('page_sections','page_id=?',[$page['id']]);
        $data=compact('page','sections');$template=$page['page_type'];
        if($template==='products')$data+=$this->catalogue();
        if($template==='brands')$data['brands']=Content::published('brands');
        if($template==='careers')$data['jobs']=Content::published('career_jobs','deadline IS NULL OR deadline>=UTC_TIMESTAMP()');
        if($template==='gallery')$data['albums']=Content::published('gallery_albums');
        if($template==='brochures')$data+=$this->brochures();
        if($template==='legal')$template='content';
        if($template==='contact'){$data['form_errors']=session()->pull('enquiry_errors',[]);$data['form_values']=session()->pull('enquiry_values',request()->query());}
        return self::render($template,$data,$page);
    }
    private function catalogue(): array {
        $brands=Content::published('brands');$categories=Content::published('categories');$subcategories=Content::published('subcategories');
        $filters=[];
        foreach(['brand'=>$brands,'category'=>$categories,'sub_category'=>$subcategories] as $key=>$records){$value=(string)(request()->query()[$key]??'');$filters[$key]=null;if($value!=='')foreach($records as $record)if($value===(string)$record['id']||strcasecmp($value,$record['title'])===0||$value===$record['slug']){$filters[$key]=$record;break;}}
        $brandCategories=$categories;
        if($filters['brand']){
            $brandId=$filters['brand']['id'];
            $categoryIds=array_column(DB::all('SELECT DISTINCT category_id FROM products WHERE brand_id=? AND '.Content::liveWhere('products'),[$brandId]),'category_id');
            $brandCategories=array_values(array_filter($categories,fn($c)=>in_array($c['id'],$categoryIds)));
            $subcategoryIds=array_column(DB::all('SELECT subcategory_id FROM subcategory_brands WHERE brand_id=? UNION SELECT subcategory_id FROM products WHERE brand_id=? AND '.Content::liveWhere('products'),[$brandId,$brandId]),'subcategory_id');
            $subcategories=array_values(array_filter($subcategories,fn($s)=>in_array($s['id'],$subcategoryIds)));
        }
        $q=trim(mb_substr((string)(request()->query()['q']??''),0,200));$view=(string)(request()->query()['view']??'products');if(!in_array($view,['products','brands','categories','sub_categories'],true))$view='products';
        $where=[Content::liveWhere('products','p')];$params=[];
        foreach(['brand'=>'brand_id','category'=>'category_id','sub_category'=>'subcategory_id'] as $key=>$column){if(!empty(request()->query()[$key])){if($filters[$key]){$where[]='p.'.$column.'=?';$params[]=$filters[$key]['id'];}else $where[]='1=0';}}
        if($q!==''){$where[]='(p.title LIKE ? OR p.description LIKE ? OR b.title LIKE ? OR c.title LIKE ? OR s.title LIKE ?)';for($i=0;$i<5;$i++)$params[]='%'.$q.'%';}
        $join=' FROM products p LEFT JOIN brands b ON b.id=p.brand_id AND '.Content::liveWhere('brands','b').' LEFT JOIN categories c ON c.id=p.category_id AND '.Content::liveWhere('categories','c').' LEFT JOIN subcategories s ON s.id=p.subcategory_id AND '.Content::liveWhere('subcategories','s');
        $sql=implode(' AND ',$where);$total=(int)DB::value('SELECT COUNT(*)'.$join.' WHERE '.$sql,$params);$number=max(1,(int)(request()->query()['page']??1));$perPage=12;
        $products=DB::all('SELECT p.*,b.title AS brand_name,c.title AS category_name,s.title AS subcategory_name'.$join.' WHERE '.$sql.' ORDER BY p.sort_order,p.id LIMIT '.$perPage.' OFFSET '.(($number-1)*$perPage),$params);
        return compact('brands','categories','brandCategories','subcategories','filters','q','view','total','number','perPage','products');
    }
    private function product(array $product): \Illuminate\Http\Response {
        $relations=[];foreach(['brands'=>'brand_id','categories'=>'category_id','subcategories'=>'subcategory_id'] as $module=>$key)if($product[$key]){$row=Content::find($module,(int)$product[$key]);if($row&&Content::isLive($row,$module))$relations[$module]=$row;}
        $images=Content::published('product_images','product_id=?',[$product['id']]);$specifications=Content::published('product_specifications','product_id=?',[$product['id']]);
        $related=Content::published('products','category_id <=> ? AND id<>?',[$product['category_id'],$product['id']],4);
        return self::render('product',compact('product','relations','images','specifications','related'),$product+['page_type'=>'product']);
    }
    private function brochures(): array {
        $brands=Content::published('brands');$categories=Content::published('categories');$where=[Content::liveWhere('brochures')];$params=[];$q=trim((string)(request()->query()['q']??''));
        foreach(['brand'=>'brand_id','category'=>'category_id'] as $key=>$column)if(!empty(request()->query()[$key])){$where[]=$column.'=?';$params[]=(int)request()->query()[$key];}
        if($q!==''){$where[]='(title LIKE ? OR description LIKE ?)';$params[]='%'.$q.'%';$params[]='%'.$q.'%';}
        $sql=implode(' AND ',$where);$number=max(1,(int)(request()->query()['page']??1));$perPage=12;$total=(int)DB::value('SELECT COUNT(*) FROM brochures WHERE '.$sql,$params);
        $brochures=DB::all('SELECT * FROM brochures WHERE '.$sql.' ORDER BY sort_order,id LIMIT '.$perPage.' OFFSET '.(($number-1)*$perPage),$params);
        return compact('brands','categories','q','number','perPage','total','brochures');
    }
    public function search(): \Illuminate\Http\Response {
        $q=trim(mb_substr((string)(request()->query()['q']??''),0,200));$number=max(1,(int)(request()->query()['page']??1));$perPage=15;$results=[];$total=0;
        if($q!==''){
            $parts=[];$params=[];foreach(['products','brands','categories','subcategories','pages'] as $module){$description=$module==='pages'?'subtitle':'description';$parts[]="SELECT id,title,slug,$description AS description,'$module' AS module".($module==='pages'?',page_type':",'' AS page_type").' FROM `'.$module.'` WHERE '.Content::liveWhere($module).' AND (title LIKE ? OR '.$description.' LIKE ?)';$params[]='%'.$q.'%';$params[]='%'.$q.'%';}
            $union=implode(' UNION ALL ',$parts);$total=(int)DB::value('SELECT COUNT(*) FROM ('.$union.') search_results',$params);
            $results=DB::all('SELECT * FROM ('.$union.') search_results ORDER BY title LIMIT '.$perPage.' OFFSET '.(($number-1)*$perPage),$params);
        }
        return self::render('search',compact('q','number','perPage','total','results'),['title'=>setting('text.search'),'noindex'=>1]);
    }
    public function enquiry(): never {
        if(request()->method()!=='POST')throw new HttpException(405,'Submit the enquiry form.');
        \Illuminate\Support\Facades\Validator::make(request()->post(),['name'=>'required|string|max:150','email'=>'required|string|email|max:254','message'=>'required|string|max:10000'])->validate();
        RateLimit::hit('enquiry:'.(request()->ip() ?? ''),5,3600);$errors=[];$data=[];
        foreach(['name'=>150,'email'=>254,'phone'=>50,'company'=>200,'requirement'=>255,'message'=>10000] as $key=>$max){$value=request()->post()[$key]??'';if(is_array($value))$value='';$value=trim((string)$value);$data[$key]=$value;if(mb_strlen($value)>$max)$errors[$key]='This field is too long.';}
        foreach(['name','email','message'] as $key)if($data[$key]==='')$errors[$key]='This field is required.';
        if(!filter_var($data['email'],FILTER_VALIDATE_EMAIL))$errors['email']='Enter a valid email address.';
        if(!empty(request()->post()['website_url'])||time()-(int)(session('form_started',time()))<2)$errors['_form']='Please wait a moment and try again.';
        if(setting('turnstile_site_key')!=='') {
            $secret=cms_config('TURNSTILE_SECRET');$token=(string)(request()->post()['cf-turnstile-response']??'');$result=[];
            if($secret&&$token){$ch=curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(['secret'=>$secret,'response'=>$token,'remoteip'=>request()->ip() ?? '']),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>true]);$raw=curl_exec($ch);curl_close($ch);$result=json_decode($raw?:'{}',true)?:[];}
            if(empty($result['success'])||($result['hostname']??'')!==parse_url(cms_config('APP_URL'),PHP_URL_HOST)||($result['action']??'')!=='quote')$errors['_form']='Please complete the anti-spam check and try again.';
        }
        if($errors){session()->put(['enquiry_errors'=>$errors,'enquiry_values'=>$data]);cms_redirect('/contact/#contact-form-card');}
        $data['ip_hash']=hash_hmac('sha256',request()->ip() ?? '',cms_config('APP_KEY'));$id=DB::insert('enquiries',$data);
        $status=Mail::send(setting('notification_email'),'New website enquiry #'.$id,"A new enquiry is available in your CMS.\n".absolute_url('admin/enquiries/'.$id));
        DB::update('enquiries',$id,['notification_status'=>$status]);session()->put('form_started',time());flash(setting('text.enquiry_success'),'quote-success');cms_redirect('/contact/#contact-form-card');
    }
    public function sitemap(): \Illuminate\Http\Response {
        $xml='<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach(['pages','products'] as $module)foreach(DB::run('SELECT * FROM '.$module.' WHERE '.Content::liveWhere($module).' AND noindex=0 ORDER BY id') as $row)$xml.='<url><loc>'.e(absolute_url(ltrim(Navigation::recordUrl($module,$row),'/'))).'</loc><lastmod>'.gmdate('c',strtotime($row['updated_at'].' UTC')).'</lastmod></url>';
        return response($xml.'</urlset>',200,['Content-Type'=>'application/xml; charset=UTF-8']);
    }
}
