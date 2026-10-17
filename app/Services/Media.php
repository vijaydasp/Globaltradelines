<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\DB;
use App\Core\HttpException;
use App\Models\Modules;
use App\Models\Content;

final class Media {
    public static function reset(): void { self::$cache=[]; }
    private static array $cache=[];
    public static function find(int $id): ?array { return self::$cache[$id]??=DB::one('SELECT * FROM media_files WHERE id=?',[$id]); }
    public static function url(int $id): string { $m=self::find($id); return !$m?'':cms_url(str_starts_with($m['path'],'/assets/')?$m['path']:'/media/'.$id); }
    public static function upload(\Illuminate\Http\UploadedFile $upload,?int $replace=null): int {
        Auth::require('media');
        if(!$upload->isValid())throw new HttpException(422,'The upload did not complete.');
        $file=cms_upload_array($upload);
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new HttpException(422,'The upload did not complete. Check the file size and try again.');
        $max=max(1,min(100,(int)cms_config('UPLOAD_MAX_MB','25')))*1024*1024;
        if(($file['size']??0)>$max) throw new HttpException(422,'The file is too large or is not a valid upload.');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $allowed=['image/jpeg'=>['jpg','jpeg'],'image/png'=>['png'],'image/webp'=>['webp'],'image/gif'=>['gif'],'application/pdf'=>['pdf'],'video/mp4'=>['mp4'],'video/webm'=>['webm']];
        $extension=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
        if(!isset($allowed[$mime])||!in_array($extension,$allowed[$mime],true)) throw new HttpException(422,'Use a JPEG, PNG, WebP, GIF, PDF, MP4 or WebM file with matching file contents.');
        $head=file_get_contents($file['tmp_name'],false,null,0,1024);
        if(str_starts_with($mime,'image/')) {
            $size=@getimagesize($file['tmp_name']);
            if(!$size||$size[0]*$size[1]>60000000) throw new HttpException(422,'The image is invalid or exceeds 60 megapixels.');
        }
        if($mime==='application/pdf'&&!str_starts_with($head,'%PDF-')) throw new HttpException(422,'Invalid PDF file.');
        if($mime==='video/mp4'&&substr($head,4,4)!=='ftyp') throw new HttpException(422,'Invalid MP4 file.');
        if($mime==='video/webm'&&substr($head,0,4)!=="\x1A\x45\xDF\xA3") throw new HttpException(422,'Invalid WebM file.');
        $old=$replace?self::find($replace):null;
        if($replace&&!$old) throw new HttpException(404,'Media not found.');
        if($old&&$old['mime_type']!==$mime) throw new HttpException(422,'A replacement must have the same file type.');
        $folder='uploads/'.gmdate('Y/m');
        if(!is_dir(storage_path('app/private/'.$folder))) mkdir(storage_path('app/private/'.$folder),0750,true);
        $relative=$folder.'/'.bin2hex(random_bytes(20)).'.'.$allowed[$mime][0];
        $target=storage_path('app/private/'.$relative);
        \Illuminate\Support\Facades\Storage::disk('private')->putFileAs($folder,$upload,basename($relative));
        $data=['path'=>$relative,'original_name'=>mb_substr(basename($file['name']),0,255),'mime_type'=>$mime,'size_bytes'=>filesize($target),'sha256'=>hash_file('sha256',$target),'updated_by'=>Auth::id()];
        try {
            if($replace) {DB::update('media_files',$replace,$data); $id=$replace;}
            else $id=DB::insert('media_files',$data+['title'=>mb_substr(pathinfo($file['name'],PATHINFO_FILENAME),0,255),'alt_text'=>'']);
        } catch(\Throwable $e) {unlink($target); throw $e;}
        if($old) self::unlink($old['path']);
        unset(self::$cache[$id]); Audit::record($replace?'replace':'upload','media_files',$id,$data['original_name']);
        return $id;
    }
    public static function usage(int $id,bool $publicOnly=false): array {
        $uses=[];
        foreach(Modules::all() as $module=>$def) foreach($def['fields'] as $key=>$field) {
            if(($field['type']??'')!=='relation'||($field['table']??'')!=='media_files') continue;
            foreach(DB::all('SELECT id FROM `'.$module.'` WHERE `'.$key.'`=?',[$id]) as $row) {
                if(!$publicOnly||Content::accessible($module,(int)$row['id'])) $uses[]=$def['label'].' #'.$row['id'];
            }
        }
        foreach(['header_logo','footer_logo','favicon','alternate_logo','social_image'] as $key) if(setting($key)===(string)$id) $uses[]='Website settings';
        if(!$publicOnly) {
            foreach(DB::all('SELECT entity,entity_id,payload FROM content_drafts') as $draft) {
                $payload=json_decode($draft['payload'],true);
                foreach(Modules::get($draft['entity'])['fields'] as $key=>$field) if(($field['table']??'')==='media_files'&&(int)($payload[$key]??0)===$id) $uses[]='Draft '.$draft['entity'].' #'.$draft['entity_id'];
            }
        }
        return array_values(array_unique($uses));
    }
    public static function delete(int $id): void {
        if(self::usage($id)) throw new HttpException(422,'This file is still used by content. Remove those references before deleting it.');
        $m=self::find($id); if(!$m) return;
        DB::run('DELETE FROM media_files WHERE id=?',[$id]); self::unlink($m['path']); unset(self::$cache[$id]); Audit::record('delete','media_files',$id,'Deleted media');
    }
    private static function unlink(string $path): void {
        $root=realpath(storage_path('app/private/uploads')); $file=realpath(storage_path('app/private/'.$path));
        if($root&&$file&&str_starts_with($file,$root.DIRECTORY_SEPARATOR)&&is_file($file)) unlink($file);
    }
    public static function serve(int $id,bool $force=false): \Symfony\Component\HttpFoundation\BinaryFileResponse {
        $m=self::find($id);
        if(!$m||(!Auth::can('media')&&!$force&&!self::usage($id,true)))throw new HttpException(404,'File not found.');
        if(str_starts_with($m['path'],'/assets/'))cms_redirect($m['path']);
        $root=realpath(storage_path('app/private/uploads'));$file=realpath(storage_path('app/private/'.$m['path']));
        if(!$root||!$file||!str_starts_with($file,$root.DIRECTORY_SEPARATOR))throw new HttpException(404,'File not found.');
        $response=response()->file($file,['Content-Type'=>$m['mime_type'],'Cache-Control'=>'private, max-age=0, must-revalidate']);
        $name=preg_replace('/[^a-zA-Z0-9._-]/','_',$m['original_name']);
        $response->setContentDisposition(str_starts_with($m['mime_type'],'image/')||str_starts_with($m['mime_type'],'video/')?'inline':'attachment',$name);
        return $response;
    }
}
