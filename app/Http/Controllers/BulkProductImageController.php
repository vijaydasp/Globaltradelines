<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Core\{DB,HttpException};
use App\Models\Content;
use App\Services\{Auth,Media,Validator};
use Illuminate\Support\Facades\{Cache,DB as LaravelDB,Validator as LaravelValidator};

final class BulkProductImageController {
    private const IMAGE_TYPES=['image/jpeg','image/png','image/webp','image/gif'];

    private function authorize(): void {Auth::require('catalog');Auth::require('media');}
    private function scope(): string {return hash('sha256',cms_base_path().'|'.Auth::id().'|'.session('auth_version').'|'.csrf_token());}

    public function index(): \Illuminate\Http\Response|\Illuminate\Http\JsonResponse {
        $this->authorize();
        $filters=LaravelValidator::make(request()->query(),[
            'q'=>'nullable|string|max:200','brand_id'=>'nullable|integer|min:1','category_id'=>'nullable|integer|min:1',
            'subcategory_id'=>'nullable|integer|min:1','images'=>'nullable|in:with,without','page'=>'nullable|integer|min:1',
        ])->validate();
        $query=LaravelDB::table('products as p');
        if(!empty($filters['q']))$query->where('p.title','like','%'.$filters['q'].'%');
        foreach(['brand_id','category_id','subcategory_id'] as $key)if(!empty($filters[$key]))$query->where('p.'.$key,$filters[$key]);
        if(!empty($filters['images'])) {
            $condition='(p.image_id IS NOT NULL OR EXISTS (SELECT 1 FROM product_images pi WHERE pi.product_id=p.id))';
            $query->whereRaw(($filters['images']==='without'?'NOT ':'').$condition);
        }
        $total=$query->count();$perPage=20;$pages=max(1,(int)ceil($total/$perPage));$page=min((int)($filters['page']??1),$pages);
        $rows=$query->leftJoin('brands as b','b.id','=','p.brand_id')->leftJoin('categories as c','c.id','=','p.category_id')
            ->leftJoin('subcategories as s','s.id','=','p.subcategory_id')
            ->leftJoin('content_drafts as d',fn($join)=>$join->on('d.entity_id','=','p.id')->where('d.entity','products'))
            ->select('p.*','b.title as brand_name','c.title as category_name','s.title as subcategory_name','d.payload as draft_payload')
            ->orderBy('p.id')->offset(($page-1)*$perPage)->limit($perPage)->get();
        $children=LaravelDB::table('product_images')->whereIn('product_id',$rows->pluck('id'))->orderBy('id')->get()->groupBy('product_id');
        $products=[];
        foreach($rows as $row) {
            $raw=(array)$row;$draft=json_decode($raw['draft_payload']??'null',true)?:[];
            unset($raw['brand_name'],$raw['category_name'],$raw['subcategory_name'],$raw['draft_payload']);
            $effective=array_replace($raw,$draft);$additional=($children[$row->id]??collect())->map(fn($item)=>(array)$item)->all();
            $products[]=['id'=>$row->id,'title'=>$row->title,'brand'=>$row->brand_name,'category'=>$row->category_name,'subcategory'=>$row->subcategory_name,
                'primary_id'=>$row->image_id,'draft_primary_id'=>$effective['image_id'],'primary_url'=>Media::url((int)$row->image_id),
                'draft_primary_url'=>Media::url((int)$effective['image_id']),'version'=>hash('sha256',json_encode($effective)),
                'image_version'=>hash('sha256',json_encode($additional)),
                'additional'=>array_map(fn($item)=>['id'=>$item['image_id'],'title'=>$item['title'],'url'=>Media::url((int)$item['image_id'])],$additional)];
        }
        $listing=['products'=>$products,'total'=>$total,'page'=>$page,'pages'=>$pages];
        if(request()->expectsJson())return response()->json($listing);
        $choices=[];
        foreach(['brands','categories','subcategories'] as $module)$choices[$module]=DB::all('SELECT id,title FROM `'.$module.'` ORDER BY title,id LIMIT 1000');
        $scope=$this->scope();
        return AdminController::page('Bulk Product Images','bulk-product-images',compact('listing','filters','choices','scope'));
    }

    public function media(): \Illuminate\Http\JsonResponse {
        $this->authorize();
        $input=LaravelValidator::make(request()->query(),['q'=>'nullable|string|max:200','page'=>'nullable|integer|min:1'])->validate();
        $query=LaravelDB::table('media_files')->whereIn('mime_type',self::IMAGE_TYPES);
        if(!empty($input['q']))$query->where(fn($q)=>$q->where('title','like','%'.$input['q'].'%')->orWhere('original_name','like','%'.$input['q'].'%'));
        $total=$query->count();$page=min((int)($input['page']??1),max(1,(int)ceil($total/24)));
        $rows=$query->orderByDesc('id')->offset(($page-1)*24)->limit(24)->get(['id','title','original_name']);
        return response()->json(['files'=>$rows->map(fn($row)=>['id'=>$row->id,'title'=>$row->title,'name'=>$row->original_name,'url'=>Media::url((int)$row->id)]),'page'=>$page,'pages'=>max(1,(int)ceil($total/24))]);
    }

    public function save(): \Illuminate\Http\JsonResponse {
        $this->authorize();
        if(strlen(request()->getContent())>262144)throw new HttpException(413,'Save at most 100 products and 500 image selections per request.');
        $input=LaravelValidator::make(request()->all(),[
            'confirmed'=>'required|accepted','request_id'=>['required','string','regex:/^[a-f0-9]{32}$/'],
            'items'=>'required|array|min:1|max:100','items.*.product_id'=>'required|integer|min:1|distinct',
            'items.*.version'=>['required','string','regex:/^[a-f0-9]{64}$/'],
            'items.*.image_version'=>['required','string','regex:/^[a-f0-9]{64}$/'],
            'items.*.primary_id'=>'sometimes|required|integer|min:1',
            'items.*.additional_ids'=>'present|array|max:50','items.*.additional_ids.*'=>'required|integer|min:1',
        ])->validate();
        $items=$input['items'];$selectionCount=0;
        foreach($items as $item)$selectionCount+=count($item['additional_ids'])+(isset($item['primary_id'])?1:0);
        if($selectionCount>500)throw new HttpException(422,'Save at most 500 image selections at once.');
        $receiptKey='bulk-images-result:'.$this->scope().':'.$input['request_id'];$digest=hash('sha256',json_encode($items));
        $lock=Cache::lock('bulk-images-save:'.Auth::id(),120);
        if(!$lock->get())throw new HttpException(409,'An image save is already running. Please wait and retry.');
        try {
            if($receipt=Cache::get($receiptKey)) {
                if(!hash_equals($receipt['digest'],$digest))throw new HttpException(409,'This confirmation was already used for different changes. Review and confirm again.');
                return response()->json($receipt['result']);
            }
            try{$result=DB::transaction(fn()=>$this->apply($items));}
            catch(\Throwable $exception){report($exception);$result=$this->failure($items,[],'The batch could not be saved and was rolled back. No image assignments were changed. Retry after checking the connection.');}
            if($result['ok'])Cache::put($receiptKey,['digest'=>$digest,'result'=>$result],3600);
            return response()->json($result,$result['ok']?200:422);
        }finally{$lock->release();}
    }

    private function apply(array $items): array {
        $ids=array_column($items,'product_id');sort($ids,SORT_NUMERIC);
        $products=LaravelDB::table('products')->whereIn('id',$ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        LaravelDB::table('content_drafts')->where('entity','products')->whereIn('entity_id',$ids)->orderBy('entity_id')->lockForUpdate()->get();
        $mediaIds=[];foreach($items as $item){$mediaIds=array_merge($mediaIds,$item['additional_ids']);if(isset($item['primary_id']))$mediaIds[]=$item['primary_id'];}
        $media=LaravelDB::table('media_files')->whereIn('id',array_unique($mediaIds))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $children=LaravelDB::table('product_images')->whereIn('product_id',$ids)->orderBy('id')->lockForUpdate()->get()->groupBy('product_id');
        $errors=[];$effective=[];$additions=[];$skipped=[];
        foreach($items as $item) {
            $id=$item['product_id'];$messages=[];
            if(!isset($products[$id]))$messages[]='Product no longer exists.';
            else {
                $effective[$id]=Content::find('products',(int)$id,true);
                if(!hash_equals(Content::version('products',(int)$id),$item['version']))$messages[]='Product or draft changed. Reload this product and review the selections.';
                $existing=($children[$id]??collect())->map(fn($row)=>(array)$row)->all();
                if(!hash_equals(hash('sha256',json_encode($existing)),$item['image_version']))$messages[]='Additional images changed. Reload this product and review the selections.';
            }
            if(count(array_unique($item['additional_ids']))!==count($item['additional_ids']))$messages[]='The same additional image was selected more than once.';
            if(!isset($item['primary_id'])&&!$item['additional_ids'])$messages[]='Select at least one image change.';
            $selected=$item['additional_ids'];if(isset($item['primary_id']))$selected[]=$item['primary_id'];
            foreach(array_unique($selected) as $mediaId) {
                if(!isset($media[$mediaId])||!in_array($media[$mediaId]->mime_type,self::IMAGE_TYPES,true))$messages[]='Media #'.$mediaId.' is missing or is not a supported image.';
            }
            $additions[$id]=[];$skipped[$id]=0;
            if(!$messages) {
                $existing=$children[$id]??collect();$imageIds=$existing->pluck('image_id')->map(fn($value)=>(int)$value)->all();
                $primary=(int)($item['primary_id']??$effective[$id]['image_id']);$order=(int)($existing->max('sort_order')??-10);
                foreach($item['additional_ids'] as $mediaId) {
                    if(in_array((int)$mediaId,$imageIds,true)||(int)$mediaId===$primary){$skipped[$id]++;continue;}
                    $order+=10;
                    [$data,$validation]=Validator::record('product_images',['product_id'=>$id,'image_id'=>$mediaId,'title'=>$media[$mediaId]->title,'sort_order'=>$order]);
                    foreach($validation as $field=>$message)$messages[]=$field.': '.$message;
                    $additions[$id][]=$data;$imageIds[]=(int)$mediaId;
                }
            }
            if($messages)$errors[$id]=implode(' ',$messages);
        }
        if($errors)return $this->failure($items,$errors,'Not saved because another product needs review. No assignments in this batch were changed.');
        $result=['ok'=>true,'total'=>count($items),'successful'=>0,'failed'=>0,'primary'=>0,'additional'=>0,'rows'=>[]];
        foreach($items as $item) {
            $id=(int)$item['product_id'];$row=['product_id'=>$id,'status'=>'saved','primary'=>0,'additional'=>0,'skipped'=>$skipped[$id],'message'=>'Saved.'];
            if(isset($item['primary_id'])&&(int)$effective[$id]['image_id']!==(int)$item['primary_id']) {
                $data=$effective[$id];$data['image_id']=(int)$item['primary_id'];
                // Saving only the working copy preserves published fields and all other pending edits.
                Content::save('products',$data,$id,'draft',$item['version']);$row['primary']=1;
            }
            foreach($additions[$id] as $data){Content::save('product_images',$data,null,'save');$row['additional']++;}
            $row['message']=$row['primary']?'Primary image saved as a draft; publish it from the product editor.':'Image assignments saved.';
            if($row['skipped'])$row['message'].=' '.$row['skipped'].' existing/primary image selections skipped.';
            $result['successful']++;$result['primary']+=$row['primary'];$result['additional']+=$row['additional'];$result['rows'][]=$row;
        }
        return $result;
    }

    private function failure(array $items,array $errors,string $message): array {
        return ['ok'=>false,'total'=>count($items),'successful'=>0,'failed'=>count($items),'primary'=>0,'additional'=>0,
            'rows'=>array_map(fn($item)=>['product_id'=>(int)$item['product_id'],'status'=>'failed','primary'=>0,'additional'=>0,'message'=>$errors[$item['product_id']]??$message],$items)];
    }
}
