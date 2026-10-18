<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Core\{DB,HttpException};
use App\Models\{Content,Modules};
use App\Services\{Auth,Validator};
use Illuminate\Support\Facades\Cache;

final class CatalogueImportController {
    private const FIELDS=[
        'categories'=>['title','slug','description','image_id','icon','sort_order','status','publish_at'],
        'brands'=>['title','slug','description','image_id','website','is_featured','sort_order','status','publish_at'],
        'subcategories'=>['title','slug','category_id','description','image_id','icon','brand_ids','sort_order','status','publish_at'],
    ];

    private function authorize(string $module): void {
        Auth::require('catalog');
        if(!isset(self::FIELDS[$module]))throw new HttpException(404,'Import unavailable.');
    }

    public function template(string $module): \Symfony\Component\HttpFoundation\StreamedResponse {
        $this->authorize($module);
        return response()->streamDownload(function()use($module):void{
            $out=fopen('php://output','w');fputcsv($out,self::FIELDS[$module],',','"','');fclose($out);
        },$module.'-template.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }

    public function handle(string $module): \Illuminate\Http\Response {
        $this->authorize($module);
        $key='catalogue_import.'.$module;$preview=session($key);$summary=null;$error='';
        if(request()->isMethod('post')) {
            try {
                if(request('action')==='commit') {
                    $token=request('import_token');$previous=session($key.'_result');
                    if(is_string($token)&&$previous&&hash_equals($previous['token'],$token))$summary=$previous['summary'];
                    else {
                        if(!is_string($token)||!$preview||$preview['expires']<time()||!hash_equals($preview['token'],$token))throw new HttpException(422,'The preview expired. Upload the CSV again.');
                        if($preview['errors']||!$preview['rows'])throw new HttpException(422,'Correct the errors and upload the CSV again before confirming.');
                        $lock=Cache::lock('catalogue-import-'.$module,120);
                        if(!$lock->get())throw new HttpException(409,'Another import is running. Please retry this confirmation.');
                        try{$summary=$this->commit($module,$preview['rows']);}finally{$lock->release();}
                        session()->put($key.'_result',['token'=>$token,'summary'=>$summary]);
                        session()->forget($key);$preview=null;
                    }
                } else {
                    // A failed replacement upload must not leave a previous preview actionable.
                    session()->forget($key);$preview=null;
                    $preview=$this->preview($module);session()->put($key,$preview);
                }
            }catch(HttpException $exception){$error=$exception->getMessage();}
        }
        $label=Modules::get($module)['label'];$fields=self::FIELDS[$module];
        return AdminController::page($label.' Import','catalogue-import',compact('module','label','fields','preview','summary','error'));
    }

    private function preview(string $module): array {
        $file=request()->file('csv');
        if(!$file instanceof \Illuminate\Http\UploadedFile||!$file->isValid()||$file->getSize()>2097152||strtolower($file->getClientOriginalExtension())!=='csv')throw new HttpException(422,'Upload a CSV file up to 2 MB.');
        $raw=file_get_contents($file->getRealPath());
        if(!mb_check_encoding($raw,'UTF-8')||str_contains($raw,"\0"))throw new HttpException(422,'Use a UTF-8 CSV file without binary data.');
        $raw=preg_replace('/^\xEF\xBB\xBF/','',$raw);
        if(trim($raw)==='')throw new HttpException(422,'The CSV is empty.');
        $this->checkSyntax($raw);
        $stream=fopen('php://temp','w+');fwrite($stream,$raw);rewind($stream);
        try {
            $header=fgetcsv($stream,0,',','"','');
            $header=array_map(fn($value)=>trim((string)$value),$header?:[]);
            $required=$module==='subcategories'?['title','category_id']:['title'];
            if(array_diff($required,$header)||count($header)!==count(array_unique($header))||array_diff($header,self::FIELDS[$module]))throw new HttpException(422,'Use the template headings. Required: '.implode(', ',$required).'. Duplicate and unsupported headings are rejected.');
            $preview=['rows'=>[],'errors'=>[],'token'=>bin2hex(random_bytes(24)),'expires'=>time()+1800];
            $line=1+preg_match_all('/\r\n|\r|\n/',substr($raw,0,ftell($stream)));$seenSlugs=[];$seenTitles=[];$count=0;
            while(!feof($stream)) {
                $start=ftell($stream);$values=fgetcsv($stream,0,',','"','');if($values===false)break;
                $rowLine=$line;$line+=preg_match_all('/\r\n|\r|\n/',substr($raw,$start,ftell($stream)-$start));
                if($values===[null])continue;
                if(++$count>1000){$preview['errors'][]='Use at most 1,000 data rows per import.';break;}
                $row=['line'=>$rowLine,'input'=>[],'data'=>[],'errors'=>[],'duplicate'=>null];
                if(count($values)!==count($header))$row['errors'][]='Wrong number of columns.';
                else {
                    $row['input']=array_map('trim',array_combine($header,$values));
                    [$row['data'],$row['errors'],$row['duplicate']]=$this->validate($module,$row['input']);
                    $slug=$row['data']['slug']??'';$title=mb_strtolower($row['data']['title']??'');
                    $titleKey=($module==='subcategories'?($row['data']['category_id']??'').':':'').$title;
                    if($slug!==''&&isset($seenSlugs[$slug]))$row['errors'][]='Duplicate slug in this CSV (first at line '.$seenSlugs[$slug].').';
                    if($title!==''&&isset($seenTitles[$titleKey]))$row['errors'][]='Duplicate name in this CSV (first at line '.$seenTitles[$titleKey].').';
                    $seenSlugs[$slug]??=$rowLine;$seenTitles[$titleKey]??=$rowLine;
                }
                foreach($row['errors'] as $message)$preview['errors'][]='Line '.$rowLine.': '.$message;
                $preview['rows'][]=$row;
            }
            if(!$preview['rows'])$preview['errors'][]='The CSV has no data rows.';
            return $preview;
        }finally{fclose($stream);}
    }

    /** Reject unterminated quotes and text after a quoted field before using PHP's CSV parser. */
    private function checkSyntax(string $raw): void {
        $state='start';$line=1;$length=strlen($raw);
        for($i=0;$i<$length;$i++) {
            $c=$raw[$i];
            if($state==='quoted') {
                if($c==='"'){if($i+1<$length&&$raw[$i+1]==='"')$i++;else $state='closed';}
            }elseif($c===','||$c==="\r"||$c==="\n")$state='start';
            elseif($c==='"') {if($state!=='start')throw new HttpException(422,'Malformed CSV quoting at line '.$line.'.');$state='quoted';}
            elseif($state==='closed')throw new HttpException(422,'Unexpected text after a quoted field at line '.$line.'.');
            else $state='plain';
            if($c==="\n")$line++;
        }
        if($state==='quoted')throw new HttpException(422,'Unclosed quoted field near line '.$line.'.');
    }

    private function validate(string $module,array $input): array {
        $errors=[];$input['status']=$input['status']??'draft';
        if(($input['slug']??'')!==''&&!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$input['slug']))$errors[]='slug: use lowercase letters, numbers and single hyphens.';
        if(isset($input['is_featured'])&&!in_array($input['is_featured'],['0','1'],true))$errors[]='is_featured: use 0 or 1.';
        if($module==='subcategories') {
            $brands=$input['brand_ids']??'';
            if($brands!==''&&!preg_match('/^[1-9][0-9]*(?:;[1-9][0-9]*)*$/',$brands))$errors[]='brand_ids: use existing IDs separated by semicolons.';
            $input['brand_ids']=$brands===''?[]:explode(';',$brands);
        }
        if(($input['image_id']??'')!==''&&(!ctype_digit((string)$input['image_id'])||!DB::one('SELECT id FROM media_files WHERE id=?',[$input['image_id']]))) {
            $errors[]='image_id: Choose an existing record.';$input['image_id']='';
        }
        [$data,$validation]=Validator::record($module,$input);
        $duplicate=null;
        if(!empty($data['slug'])&&!empty($data['title'])) {
            $params=[$data['slug'],$data['title']];$name='title=?';
            if($module==='subcategories'){$name.=' AND category_id=?';$params[]=$data['category_id']??null;}
            $duplicate=DB::one('SELECT id,title FROM `'.$module.'` WHERE slug=? OR ('.$name.') ORDER BY id LIMIT 1',$params);
            if($duplicate)unset($validation['slug']);
        }
        if(!empty($data['image_id'])&&!Auth::can('media'))$errors[]='image_id: Media Library permission is required to assign an image.';
        foreach($validation as $field=>$message)$errors[]=$field.': '.$message;
        return [$data,$errors,$duplicate];
    }

    private function commit(string $module,array $rows): array {
        $summary=['total'=>count($rows),'imported'=>0,'skipped'=>0,'failed'=>0,'rows'=>[]];
        foreach($rows as $row) {
            try {
                $result=DB::transaction(function()use($module,$row):array{
                    [$data,$errors,$duplicate]=$this->validate($module,$row['input']);
                    if($errors)return ['status'=>'failed','message'=>implode(' ',$errors)];
                    if($duplicate)return ['status'=>'skipped','message'=>'Existing record #'.$duplicate['id'].' was preserved.'];
                    $id=Content::save($module,$data,null,'save');
                    return ['status'=>'imported','message'=>'Created record #'.$id.'.'];
                });
            }catch(\Throwable $exception){report($exception);$result=['status'=>'failed','message'=>'The record was not saved. Check for changed references or duplicate values, then retry this row.'];}
            $summary[$result['status']]++;$summary['rows'][]=['line'=>$row['line'],'title'=>$row['data']['title']??'']+$result;
        }
        return $summary;
    }
}
