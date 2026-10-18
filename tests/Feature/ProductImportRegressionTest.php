<?php
namespace Tests\Feature;

use App\Models\{AdminUser,Content};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Hash};
use Tests\TestCase;

class ProductImportRegressionTest extends TestCase {
    private const HEADER='brand,category,sub_category,product,slug,image,description,badge,external_url,button_label,sort_order,is_active,is_featured';
    private function signIn(): void {$user=AdminUser::create(['name'=>'Product import QA','email'=>bin2hex(random_bytes(6)).'@example.test','password_hash'=>Hash::make('Test-password-123'),'role_id'=>3,'is_active'=>1,'auth_version'=>1]);$this->actingAs($user)->withSession(['auth_version'=>1]);}
    private function preview(string $csv): \Illuminate\Testing\TestResponse {return $this->post('/admin/import',['action'=>'preview','csv'=>UploadedFile::fake()->createWithContent('products.csv',$csv)]);}
    private function commit(bool $updates=false): \Illuminate\Testing\TestResponse {return $this->post('/admin/import',['action'=>'commit','import_token'=>session('import_preview.token'),'allow_updates'=>$updates?1:0]);}
    public function test_header_format_and_full_header_import_remain_compatible(): void {
        $this->signIn();$this->assertStringStartsWith(self::HEADER,$this->get('/admin/products/export')->assertOk()->streamedContent());
        $this->preview(self::HEADER."\n,,,Regression product,regression-product,2,Description,Badge,https://example.test,Details,0,1,1\n")->assertSee('0 validation errors');
        $this->assertLessThanOrEqual(time()+1800,session('import_preview.expires'));$this->assertGreaterThan(time()+1700,session('import_preview.expires'));
        $this->commit()->assertSee('1 created');$this->assertDatabaseHas('products',['slug'=>'regression-product','image_id'=>2,'status'=>'published']);
    }
    public function test_updates_require_confirmation_and_empty_image_preserves_all_related_records(): void {
        $this->signIn();$id=DB::table('products')->insertGetId(['title'=>'Original','slug'=>'preserved','image_id'=>2,'status'=>'published','seo_title'=>'Retained SEO']);
        DB::table('product_images')->insert(['product_id'=>$id,'image_id'=>3]);DB::table('product_specifications')->insert(['product_id'=>$id,'title'=>'Power','value'=>'100 W']);
        $csv="product,slug,image\nChanged,preserved,\n";$this->preview($csv)->assertSee('0 validation errors');$this->commit()->assertSee('updates were not enabled');$this->assertDatabaseHas('products',['id'=>$id,'title'=>'Original']);
        $this->preview($csv);$this->commit(true)->assertSee('1 updated');$this->assertDatabaseHas('products',['id'=>$id,'image_id'=>2,'seo_title'=>'Retained SEO','title'=>'Changed']);
        $this->assertDatabaseHas('product_images',['product_id'=>$id,'image_id'=>3]);$this->assertDatabaseHas('product_specifications',['product_id'=>$id,'value'=>'100 W']);
    }
    public function test_media_resolution_and_ambiguous_filename_handling_are_unchanged(): void {
        $this->signIn();$filename=DB::table('media_files')->where('id',2)->value('original_name');
        $this->preview("product,image\nFilename product,$filename")->assertSee('0 validation errors');$this->commit()->assertSee('1 created');
        DB::table('media_files')->insert(['title'=>'Ambiguous','original_name'=>$filename,'path'=>'uploads/qa-ambiguous.png','mime_type'=>'image/png','size_bytes'=>1]);
        $this->preview("product,image\nAmbiguous product,$filename")->assertSee('filename is ambiguous');$this->commit()->assertStatus(422);
        $this->preview("product,image\nID product,2")->assertSee('0 validation errors');$this->commit()->assertSee('1 created');
    }
    public function test_limits_expiry_and_concurrent_update_checks_remain(): void {
        $this->signIn();$this->preview(str_repeat('x',2097153))->assertStatus(422);$this->preview("product\n".implode("\n",array_map(fn($i)=>'Limited '.$i,range(1,1001))))->assertSee('at most 1,000');
        $this->preview("product\nExpired product");$preview=session('import_preview');$preview['expires']=time()-1;$this->withSession(['import_preview'=>$preview]);$this->commit()->assertStatus(422);
        $id=DB::table('products')->insertGetId(['title'=>'Concurrent original','slug'=>'concurrent']);$this->preview("product,slug\nImport edit,concurrent");DB::table('products')->where('id',$id)->update(['title'=>'Other editor']);$this->commit(true)->assertSee('Someone changed this record');$this->assertDatabaseHas('products',['id'=>$id,'title'=>'Other editor']);
    }
}
