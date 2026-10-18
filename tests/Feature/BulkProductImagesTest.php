<?php
namespace Tests\Feature;

use App\Models\{AdminUser,Content};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Hash,Cache,Storage};
use Tests\TestCase;

class BulkProductImagesTest extends TestCase {
    private const URL='/admin/products/bulk-images';
    private function signIn(int $role=3): void {$user=AdminUser::create(['name'=>'Image QA','email'=>bin2hex(random_bytes(6)).'@example.test','password_hash'=>Hash::make('Test-password-123'),'role_id'=>$role,'is_active'=>1,'auth_version'=>1]);$this->actingAs($user)->withSession(['auth_version'=>1]);}
    private function product(string $slug): int {return DB::table('products')->insertGetId(['title'=>'QA '.$slug,'slug'=>$slug,'status'=>'published','image_id'=>2,'description'=>'<p>Keep this description</p>','seo_title'=>'Keep SEO']);}
    private function item(int $id,array $changes=[]): array {
        $images=DB::table('product_images')->where('product_id',$id)->orderBy('id')->get()->map(fn($row)=>(array)$row)->all();
        return array_replace(['product_id'=>$id,'version'=>Content::version('products',$id),'image_version'=>hash('sha256',json_encode($images)),'additional_ids'=>[]],$changes);
    }
    private function save(array $items,?string $token=null): \Illuminate\Testing\TestResponse {return $this->postJson(self::URL,['confirmed'=>true,'request_id'=>$token??bin2hex(random_bytes(16)),'items'=>$items]);}
    public function test_primary_drafts_and_additional_images_save_together_without_losing_content(): void {
        $this->signIn();$a=$this->product('batch-one');$b=$this->product('batch-two');
        DB::table('product_specifications')->insert(['product_id'=>$a,'title'=>'Voltage','value'=>'230 V']);
        $draft=Content::find('products',$a);$draft['title']='Keep pending title';Content::save('products',$draft,$a,'draft');
        $live=(array)DB::table('products')->where('id',$a)->first();
        $this->save([$this->item($a,['primary_id'=>3,'additional_ids'=>[4]]),$this->item($b,['additional_ids'=>[3]])])->assertOk()->assertJsonPath('successful',2)->assertJsonPath('primary',1)->assertJsonPath('additional',2);
        $this->assertSame($live,(array)DB::table('products')->where('id',$a)->first());
        $this->assertSame('Keep pending title',Content::find('products',$a,true)['title']);$this->assertSame(3,Content::find('products',$a,true)['image_id']);
        $this->assertDatabaseHas('product_specifications',['product_id'=>$a,'value'=>'230 V']);$this->assertDatabaseHas('product_images',['product_id'=>$a,'image_id'=>4]);
        $this->assertDatabaseHas('admin_activity_logs',['entity'=>'product_images','action'=>'save']);
    }
    public function test_listing_versions_match_content_repository_and_filters_paginate(): void {
        $this->signIn();$category=DB::table('categories')->insertGetId(['title'=>'Filter','slug'=>'filter']);
        for($i=0;$i<22;$i++)DB::table('products')->insert(['title'=>'Paged '.$i,'slug'=>'paged-'.$i,'category_id'=>$category,'image_id'=>$i===0?2:null]);
        $response=$this->getJson(self::URL.'?q=Paged&category_id='.$category)->assertOk()->assertJsonPath('total',22)->assertJsonCount(20,'products');
        foreach($response->json('products') as $product)$this->assertSame(Content::version('products',$product['id']),$product['version']);
        $this->getJson(self::URL.'?q=Paged&page=2')->assertJsonCount(2,'products');
        $this->getJson(self::URL.'?q=Paged&images=with')->assertJsonPath('total',1);$this->getJson(self::URL.'?q=Paged&images=without')->assertJsonPath('total',21);
        $this->get(self::URL)->assertOk()->assertSee('Save All Images');$this->getJson(self::URL.'/media')->assertOk()->assertJsonStructure(['files','page','pages']);
    }
    public function test_upload_does_not_associate_until_confirmation(): void {
        $this->signIn();$id=$this->product('unassociated');$before=DB::table('product_images')->count();
        $file=UploadedFile::fake()->createWithContent('pending.png',file_get_contents(public_path('assets/img/global-trade-lines-favicon-48.png')));
        $response=$this->postJson('/admin/upload',['files'=>[$file]])->assertOk();$media=$response->json('files.0.id');$path=DB::table('media_files')->where('id',$media)->value('path');
        try{$this->assertSame($before,DB::table('product_images')->count());$this->assertDatabaseHas('products',['id'=>$id,'image_id'=>2]);$this->save([$this->item($id,['additional_ids'=>[$media]])])->assertOk()->assertJsonPath('additional',1);}finally{Storage::disk('private')->delete($path);}
    }
    public function test_invalid_identifiers_duplicate_selections_and_nonimages_abort_entire_batch(): void {
        $this->signIn();$a=$this->product('valid-item');$b=$this->product('invalid-item');
        foreach([['primary_id'=>999999999],['additional_ids'=>[3,3]]] as $change)$this->save([$this->item($a,['additional_ids'=>[4]]),$this->item($b,$change)])->assertStatus(422)->assertJsonPath('successful',0)->assertJsonPath('additional',0);
        $pdf=DB::table('media_files')->insertGetId(['title'=>'PDF','path'=>'uploads/test.pdf','original_name'=>'test.pdf','mime_type'=>'application/pdf','size_bytes'=>10]);
        $this->save([$this->item($a,['primary_id'=>$pdf])])->assertStatus(422)->assertJsonPath('failed',1);
        $missing=$this->item($a,['additional_ids'=>[3]]);$missing['product_id']=999999999;$this->save([$missing])->assertStatus(422)->assertJsonPath('rows.0.message','Product no longer exists.');
        $this->assertDatabaseMissing('product_images',['product_id'=>$a]);
    }
    public function test_conflicting_product_or_additional_images_require_review(): void {
        $this->signIn();$id=$this->product('conflict');$item=$this->item($id,['primary_id'=>3]);DB::table('products')->where('id',$id)->update(['title'=>'Concurrent title']);
        $this->save([$item])->assertStatus(422)->assertSee('Product or draft changed');
        $item=$this->item($id,['additional_ids'=>[4]]);DB::table('product_images')->insert(['product_id'=>$id,'image_id'=>3]);$this->save([$item])->assertStatus(422)->assertSee('Additional images changed');
        $this->assertDatabaseMissing('content_drafts',['entity'=>'products','entity_id'=>$id]);
    }
    public function test_replays_and_existing_associations_are_safe(): void {
        $this->signIn();$id=$this->product('replay');DB::table('product_images')->insert(['product_id'=>$id,'image_id'=>3]);
        $item=$this->item($id,['primary_id'=>4,'additional_ids'=>[3,4,5]]);$token=bin2hex(random_bytes(16));
        $first=$this->save([$item],$token)->assertOk()->assertJsonPath('additional',1)->assertJsonPath('rows.0.skipped',2)->json();
        $this->save([$item],$token)->assertOk()->assertExactJson($first);$this->assertSame(2,DB::table('product_images')->where('product_id',$id)->count());
        $item['primary_id']=6;$this->save([$item],$token)->assertStatus(409);
    }
    public function test_database_error_rolls_back_every_product_and_all_audits(): void {
        $this->signIn();$a=$this->product('rollback-one');$b=$this->product('rollback-two');$items=[$this->item($a,['primary_id'=>3]),$this->item($b,['additional_ids'=>[4]])];
        $before=DB::table('admin_activity_logs')->count();$fail=true;
        DB::listen(function($query)use(&$fail):void{if($fail&&str_starts_with($query->sql,'insert into `product_images`')){$fail=false;throw new \RuntimeException('Simulated database failure');}});
        $this->save($items)->assertStatus(422)->assertJsonPath('successful',0)->assertJsonPath('failed',2);
        $this->assertDatabaseMissing('content_drafts',['entity'=>'products','entity_id'=>$a]);$this->assertDatabaseMissing('product_images',['product_id'=>$b]);$this->assertSame($before,DB::table('admin_activity_logs')->count());
        $this->save($items)->assertOk()->assertJsonPath('successful',2);
    }
    public function test_all_additional_image_fields_are_validated_before_any_write(): void {
        $this->signIn();$a=$this->product('preflight-one');$b=$this->product('preflight-two');
        DB::table('product_images')->insert(['product_id'=>$b,'image_id'=>3,'sort_order'=>100000]);
        $writes=0;DB::listen(function($query)use(&$writes):void{if(preg_match('/^(insert|update|delete) /i',$query->sql))$writes++;});
        $this->save([$this->item($a,['primary_id'=>3]),$this->item($b,['additional_ids'=>[4]])])->assertStatus(422)->assertSee('sort_order');
        $this->assertSame(0,$writes);$this->assertDatabaseMissing('content_drafts',['entity'=>'products','entity_id'=>$a]);
    }
    public function test_request_limits_permissions_and_csrf(): void {
        $this->get(self::URL)->assertRedirect('/admin/login');$this->signIn(2);$this->get(self::URL)->assertForbidden();$this->getJson(self::URL.'/media')->assertForbidden();$this->save([])->assertForbidden();
        $role=DB::table('roles')->insertGetId(['title'=>'Catalog without media']);DB::table('role_permissions')->insert(['role_id'=>$role,'permission_key'=>'catalog']);$this->signIn($role);$this->get(self::URL)->assertForbidden();
        $this->signIn();$id=$this->product('limits');$item=$this->item($id,['primary_id'=>3]);$this->save([$item,$item])->assertStatus(422);$this->save(array_fill(0,101,$item))->assertStatus(422);
        $this->postJson(self::URL,['items'=>[$item]])->assertStatus(422);$this->app['env']='local';$this->save([$item])->assertStatus(419);
    }
}
