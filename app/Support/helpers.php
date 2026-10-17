<?php
use Illuminate\Http\Exceptions\HttpResponseException;

function cms_config(string $key,string $default=''): string {
    $map=['APP_URL'=>'app.url','APP_KEY'=>'app.key','DB_DATABASE'=>'database.connections.'.config('database.default').'.database','DB_HOST'=>'database.connections.mysql.host','DB_PORT'=>'database.connections.mysql.port','DB_USERNAME'=>'database.connections.mysql.username','DB_PASSWORD'=>'database.connections.mysql.password','UPLOAD_MAX_MB'=>'cms.upload_max_mb','TURNSTILE_SECRET'=>'cms.turnstile_secret','MAIL_ENABLED'=>'cms.mail_enabled','MAIL_FROM'=>'mail.from.address'];
    $value=config($map[$key]??$key,$default);return is_bool($value)?($value?'true':'false'):(string)$value;
}
function cms_base_path(): string { return rtrim((string)parse_url(config('app.url'),PHP_URL_PATH),'/'); }
function cms_url(string $path=''): string {
    if(preg_match('~^(https?://|mailto:|tel:|#)~i',$path))return $path;
    $base=cms_base_path();if($base!==''&&($path===$base||str_starts_with($path,$base.'/')))return $path;
    return $base.'/'.ltrim($path,'/');
}
function absolute_url(string $path=''): string {return rtrim(config('app.url'),'/').'/'.ltrim($path,'/');}
function cms_redirect(string $path): never {throw new HttpResponseException(redirect()->to(cms_url($path),303));}
function cms_view(string $name,array $data=[]): void {echo view(str_replace('/','.',$name),$data)->render();}
function flash(string $message,string $type='success'): void {$items=session('cms_flashes',[]);$items[]=compact('message','type');session()->flash('cms_flashes',$items);}
function flashes(): array {return session()->pull('cms_flashes',[]);}
function json_response(array $data,int $status=200): never {throw new HttpResponseException(response()->json($data,$status));}
function slugify(string $text): string {return Illuminate\Support\Str::slug($text);}
function setting(string $key,string $default=''): string {return App\Services\Settings::get($key,$default);}
function text(string $key,string $default=''): string {return e(setting('text.'.$key,$default));}
function safe_url(string $value,bool $media=false): bool {
    if($value==='')return true;
    if(preg_match('/[\x00-\x20\x7f\\\\]/',$value)||str_starts_with($value,'//'))return false;
    if(preg_match('~^https?://~i',$value))return filter_var($value,FILTER_VALIDATE_URL)!==false;
    if(!$media&&preg_match('~^(mailto:[^@]+@[^@]+|tel:[+0-9().-]+|#[a-zA-Z0-9_-]*)$~',$value))return true;
    return str_starts_with($value,'/')&&!str_contains(rawurldecode($value),'..');
}
function media_url(mixed $id): string {return $id?App\Services\Media::url((int)$id):'';}
function media_img(mixed $id,string $alt='',string $class='',bool $lazy=true): string {
    if(!$id||!($item=App\Services\Media::find((int)$id))||!str_starts_with($item['mime_type'],'image/'))return '';
    return '<img src="'.e(media_url($id)).'" alt="'.e($item['alt_text']?:$alt).'" class="'.e($class).'"'.($lazy?' loading="lazy"':'').' decoding="async">';
}
function rich(mixed $value): string {return App\Services\Sanitizer::html((string)$value);}
function request_path(): string {return '/'.trim(request()->path(),'/');}
function cms_upload_array(?Illuminate\Http\UploadedFile $file): array {return !$file?[]:['name'=>$file->getClientOriginalName(),'size'=>$file->getSize(),'error'=>$file->getError(),'tmp_name'=>$file->getPathname(),'type'=>$file->getMimeType()];}
