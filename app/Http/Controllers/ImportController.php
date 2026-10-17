<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Core\DB;
use App\Core\HttpException;
use App\Models\Content;
use App\Services\{Auth,Validator,Media};

final class ImportController {
    private const FIELDS=['brand','category','sub_category','product','slug','image','description','badge','external_url','button_label','sort_order','is_active','is_featured'];
    public function handle(): \Illuminate\Http\Response {
        Auth::require('catalog');$preview=session('import_preview');$errors=[];$summary=null;
        if(request()->method()==='POST') {
            if((request()->post()['action']??'')==='commit') {
                if(!$preview||($preview['expires']??0)<time()||!hash_equals($preview['token'],(string)(request()->post()['import_token']??'')))throw new HttpException(422,'The preview expired. Upload the CSV again.');
                if($preview['errors'])throw new HttpException(422,'Fix the reported rows and upload the CSV again before importing.');
                $summary=['created'=>0,'updated'=>0,'errors'=>[]];
                foreach($preview['rows'] as $row) {
                    [$data,$validation]=Validator::record('products',$row['data'],$row['id']);
                    if($validation){$summary['errors'][]='Row '.$row['line'].': '.implode(' ',$validation);continue;}
                    if($row['id']&&empty(request()->post()['allow_updates'])){$summary['errors'][]='Row '.$row['line'].': skipped; updates were not enabled.';continue;}
                    try{Content::save('products',$data,$row['id'],'save',$row['version']);$summary[$row['id']?'updated':'created']++;}
                    catch(\Throwable $e){$summary['errors'][]='Row '.$row['line'].': '.($e instanceof HttpException?$e->getMessage():'Could not save. Check for a duplicate slug or changed relationships.');}
                }
                session()->forget('import_preview');$preview=null;
            }else{
                $file=cms_upload_array(request()->file('csv'));
                if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||($file['size']??0)>2097152||strtolower(pathinfo($file['name']??'',PATHINFO_EXTENSION))!=='csv')throw new HttpException(422,'Upload a CSV file up to 2 MB.');
                $fh=fopen($file['tmp_name'],'rb');$header=fgetcsv($fh,0,',','"','');if(!$header)throw new HttpException(422,'The CSV is empty.');
                $header=array_map(fn($s)=>trim(ltrim($s,"\xEF\xBB\xBF")),$header);
                if(!in_array('product',$header,true)||count($header)!==count(array_unique($header))||array_diff($header,self::FIELDS))throw new HttpException(422,'Use the exported CSV headings; product is required. Unsupported or duplicate headings were found.');
                $preview=['rows'=>[],'errors'=>[],'expires'=>time()+1800,'token'=>bin2hex(random_bytes(24))];$line=1;$seen=[];
                while(($row=fgetcsv($fh))!==false){$line++;if($line>1001){$preview['errors'][]='Use at most 1,000 rows per import.';break;}if($row===[null])continue;
                    if(count($row)!==count($header)){$preview['errors'][]="Row $line: wrong number of columns.";continue;}
                    $values=array_combine($header,$row);$title=trim($values['product']);$slug=slugify(trim($values['slug']??'')?:$title);
                    if(isset($seen[$slug])){$preview['errors'][]="Row $line: duplicate product in this CSV.";continue;}$seen[$slug]=true;
                    $existing=DB::one('SELECT id FROM products WHERE slug=?',[$slug]);$id=$existing?(int)$existing['id']:null;
                    $data=$id?Content::find('products',$id,true):[];$data['title']=$title;$data['slug']=$slug;
                    foreach(['brand'=>['brands','brand_id'],'category'=>['categories','category_id'],'sub_category'=>['subcategories','subcategory_id']] as $key=>[$table,$column])if(isset($values[$key])){
                        $value=trim($values[$key]);$matches=$value!==''?DB::all('SELECT id FROM `'.$table.'` WHERE title=? OR slug=?',[$value,$value]):[];$match=count($matches)===1?$matches[0]:null;
                        if(count($matches)>1)$preview['errors'][]="Row $line: ambiguous $key '$value'. Use its unique slug.";
                        if($value!==''&&!$match)$preview['errors'][]="Row $line: unknown $key '$value'. Create it before importing.";
                        $data[$column]=$match['id']??null;
                    }
                    foreach(['description','badge','external_url','button_label','sort_order','is_featured'] as $key)if(isset($values[$key]))$data[$key]=$values[$key];
                    if(isset($values['is_active'])){if(!in_array($values['is_active'],['0','1'],true))$preview['errors'][]="Row $line: is_active must be 0 or 1.";$data['status']=$values['is_active']==='1'?'published':'unpublished';}
                    if(isset($values['is_featured'])&&!in_array($values['is_featured'],['0','1'],true))$preview['errors'][]="Row $line: is_featured must be 0 or 1.";
                    if(!empty($values['image'])){$matches=ctype_digit($values['image'])?array_filter([Media::find((int)$values['image'])]):DB::all('SELECT * FROM media_files WHERE original_name=?',[$values['image']]);$media=count($matches)===1?array_values($matches)[0]:null;if(!$media)$preview['errors'][]="Row $line: image is missing or the filename is ambiguous. Upload it first and use its media ID.";else $data['image_id']=$media['id'];}
                    [$data,$validation]=Validator::record('products',$data,$id);
                    foreach($validation as $key=>$message)$preview['errors'][]="Row $line ($key): $message";
                    $preview['rows'][]=['line'=>$line,'id'=>$id,'data'=>$data,'version'=>$id?Content::version('products',$id):''];
                }
                fclose($fh);session()->put('import_preview',$preview);
            }
        }
        return AdminController::page('Product Import','import',compact('preview','summary','errors'));
    }
    public static function csvSafe(string $value): string {return preg_match('/^[=+@\-\t\r\n]/',$value)?"'".$value:$value;}
    public function export(): \Symfony\Component\HttpFoundation\StreamedResponse {
        Auth::require('catalog');return response()->streamDownload(function(): void {$out=fopen('php://output','w');fputcsv($out,self::FIELDS,',','"','');
        $query='SELECT p.*,b.title AS brand,c.title AS category,s.title AS sub_category,m.original_name AS image FROM products p LEFT JOIN brands b ON b.id=p.brand_id LEFT JOIN categories c ON c.id=p.category_id LEFT JOIN subcategories s ON s.id=p.subcategory_id LEFT JOIN media_files m ON m.id=p.image_id ORDER BY p.id';
        foreach(DB::run($query) as $r){$r['product']=$r['title'];$r['is_active']=$r['status']==='published'?'1':'0';$r['description']=strip_tags($r['description']??'');fputcsv($out,array_map(fn($key)=>self::csvSafe((string)($r[$key]??'')),self::FIELDS),',','"','');}fclose($out);},'products.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }
}
