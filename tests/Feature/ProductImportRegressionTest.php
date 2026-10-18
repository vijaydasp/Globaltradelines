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
    public function test_spreadsheet_format_previews_missing_entries_and_publishes_products_after_confirmation(): void {
        $this->signIn();
        $csv=self::HEADER."\nPolycab,Electrical,Electrical Wire,Polycab Etira Wires 2.5sqmm,,,Copper wire,,,,1,yes,\nPolycab,Electrical,Electrical Wire,Polycab Prima 2.5sqmm,,,Second wire,,,,2,yes,\n";
        $this->preview($csv)->assertSee('0 validation errors')->assertSee('3 missing catalogue entries to create')->assertSee('published');
        $this->assertDatabaseMissing('brands',['title'=>'Polycab']);$this->assertDatabaseMissing('categories',['title'=>'Electrical']);$this->assertDatabaseMissing('products',['title'=>'Polycab Etira Wires 2.5sqmm']);
        $token=session('import_preview.token');$this->commit()->assertSee('2 created')->assertSee('1 brands')->assertSee('1 categories')->assertSee('1 subcategories')->assertSee('View website products');
        $category=DB::table('categories')->where('title','Electrical')->value('id');$brand=DB::table('brands')->where('title','Polycab')->value('id');$sub=DB::table('subcategories')->where('title','Electrical Wire')->value('id');
        $this->assertDatabaseHas('subcategories',['id'=>$sub,'category_id'=>$category,'status'=>'published']);
        $this->assertDatabaseHas('products',['slug'=>'polycab-etira-wires-25sqmm','status'=>'published','is_featured'=>0,'image_id'=>null,'brand_id'=>$brand,'category_id'=>$category,'subcategory_id'=>$sub]);
        $this->get('/products/?brand='.$brand.'&category='.$category.'&sub_category='.$sub)->assertOk()->assertSee('Polycab Etira Wires 2.5sqmm')->assertSee('Polycab Prima 2.5sqmm');
        $this->get('/products/polycab-etira-wires-25sqmm/')->assertOk()->assertSee('Copper wire');
        $this->assertDatabaseHas('admin_activity_logs',['entity'=>'brands','action'=>'save','entity_id'=>$brand]);
        $this->post('/admin/import',['action'=>'commit','import_token'=>$token])->assertStatus(422);
        $this->assertSame(1,DB::table('brands')->where('title','Polycab')->count());
    }
    public function test_boolean_aliases_are_trimmed_and_blanks_preserve_existing_values(): void {
        $this->signIn();
        foreach(['yes','YES',' true ','1','no','NO',' false ','0'] as $index=>$flag) {
            $this->preview("product,is_active,is_featured\nFlag $index,$flag,$flag")->assertSee('0 validation errors');$this->commit()->assertSee('1 created');
            $active=$index<4;$this->assertDatabaseHas('products',['slug'=>'flag-'.$index,'status'=>$active?'published':'unpublished','is_featured'=>$active?1:0]);
            $this->get('/products/flag-'.$index.'/')->assertStatus($active?200:404);
        }
        $id=DB::table('products')->insertGetId(['title'=>'Existing flags','slug'=>'existing-flags','status'=>'published','is_featured'=>1,'publish_at'=>'2099-01-01 00:00:00']);
        $this->preview("product,slug,is_active,is_featured\nExisting flags,existing-flags,,")->assertSee('0 validation errors');$this->commit(true)->assertSee('1 updated');
        $this->assertDatabaseHas('products',['id'=>$id,'status'=>'published','is_featured'=>1,'publish_at'=>'2099-01-01 00:00:00']);$this->get('/products/existing-flags/')->assertNotFound();
        $this->preview("product,is_active,is_featured\nBlank flags,,")->assertSee('0 validation errors');$this->commit()->assertSee('1 created');$this->assertDatabaseHas('products',['slug'=>'blank-flags','status'=>'draft','is_featured'=>0]);
        $this->preview("brand,product,is_active,is_featured\nDo not create,Invalid flags,maybe,featured")->assertSee('is_active must be')->assertSee('is_featured must be');$this->commit()->assertStatus(422);$this->assertDatabaseMissing('brands',['title'=>'Do not create']);
    }
    public function test_skipped_updates_do_not_create_classifications_and_confirmed_updates_preserve_related_data(): void {
        $this->signIn();$id=DB::table('products')->insertGetId(['title'=>'Existing','slug'=>'classified-update','image_id'=>2,'seo_title'=>'Keep SEO','status'=>'published']);
        DB::table('product_images')->insert(['product_id'=>$id,'image_id'=>3]);DB::table('product_specifications')->insert(['product_id'=>$id,'title'=>'Power','value'=>'100 W']);
        $csv="brand,category,sub_category,product,slug,image,is_active,is_featured\nNew brand,New category,New sub,Updated,classified-update,,yes,";
        $this->preview($csv)->assertSee('0 validation errors');$this->commit()->assertSee('updates were not enabled');$this->assertDatabaseMissing('brands',['title'=>'New brand']);
        $this->preview($csv);$this->commit(true)->assertSee('1 updated')->assertSee('1 brands');$this->assertDatabaseHas('products',['id'=>$id,'image_id'=>2,'seo_title'=>'Keep SEO','title'=>'Updated','status'=>'published']);
        $this->assertDatabaseHas('product_images',['product_id'=>$id,'image_id'=>3]);$this->assertDatabaseHas('product_specifications',['product_id'=>$id,'value'=>'100 W']);
    }
    public function test_failed_product_rolls_back_new_classifications_and_reports_only_committed_counts(): void {
        $this->signIn();$this->preview("brand,category,sub_category,product,is_active\nRollback brand,Rollback category,Rollback sub,Failed product,yes\nRollback brand,Rollback category,Rollback sub,Successful product,yes");
        $fail=true;DB::listen(function($query)use(&$fail):void{if($fail&&str_starts_with($query->sql,'insert into `products`')){$fail=false;throw new \RuntimeException('Simulated product save failure');}});
        $this->commit()->assertSee('1 created')->assertSee('1 not saved')->assertSee('1 brands')->assertSee('1 categories')->assertSee('1 subcategories');
        $this->assertDatabaseMissing('products',['title'=>'Failed product']);$this->assertDatabaseHas('products',['title'=>'Successful product']);$this->assertSame(1,DB::table('brands')->where('title','Rollback brand')->count());
    }
    public function test_missing_subcategory_requires_parent_and_slug_collisions_block_confirmation(): void {
        $this->signIn();$this->preview("sub_category,product,is_active\nOrphan,Product,yes")->assertSee('A category is required');$this->commit()->assertStatus(422);
        $this->preview("category,sub_category,product,is_active\nParent one,Shared child,First,yes\nParent two,Shared child,Second,yes")->assertSee('same slug');$this->commit()->assertStatus(422);
        $this->assertDatabaseMissing('categories',['title'=>'Parent one']);
        $this->preview("brand,product\nA B,One\nA-B,Two")->assertSee('same slug');$this->commit()->assertStatus(422);
        $this->preview("brand,product\n".str_repeat('A',256).',Too long')->assertSee('too long');
    }
    public function test_existing_classifications_are_reused_without_republishing_or_reparenting(): void {
        $this->signIn();$brand=DB::table('brands')->insertGetId(['title'=>'Existing brand','slug'=>'existing-brand','status'=>'draft']);$category=DB::table('categories')->insertGetId(['title'=>'Existing category','slug'=>'existing-category','status'=>'published']);
        $sub=DB::table('subcategories')->insertGetId(['title'=>'Existing sub','slug'=>'existing-sub','category_id'=>$category,'status'=>'published']);
        $this->preview("brand,category,sub_category,product,is_active\nexisting-brand,existing-category,existing-sub,Visible product,yes")->assertSee('0 validation errors');$this->commit()->assertSee('0 brands');
        $this->assertDatabaseHas('brands',['id'=>$brand,'status'=>'draft']);$this->assertDatabaseHas('products',['slug'=>'visible-product','brand_id'=>$brand,'category_id'=>$category,'subcategory_id'=>$sub]);$this->get('/products/visible-product/')->assertOk();
        $this->preview("category,sub_category,product,is_active\nDifferent parent,existing-sub,Wrong parent,yes")->assertSee('belonging to the selected category');$this->commit()->assertStatus(422);$this->assertDatabaseMissing('categories',['title'=>'Different parent']);
        DB::table('brands')->insert(['title'=>'Existing brand','slug'=>'same-name-other']);$this->preview("brand,product\nExisting brand,Ambiguous brand")->assertSee('ambiguous brand');$this->commit()->assertStatus(422);
    }
    public function test_confirmation_rechecks_new_reference_collisions_and_removed_images(): void {
        $this->signIn();$this->preview("brand,product,is_active\nPlanned brand,Collision product,yes");
        DB::table('brands')->insert(['title'=>'Another name','slug'=>'planned-brand']);$this->commit()->assertSee('classification changed')->assertSee('0 created');$this->assertDatabaseMissing('products',['title'=>'Collision product']);
        $media=DB::table('media_files')->insertGetId(['title'=>'Temporary','original_name'=>'temporary.png','path'=>'uploads/temporary.png','mime_type'=>'image/png','size_bytes'=>1]);
        $this->preview("brand,product,image,is_active\nMust roll back,Lost image,$media,yes");DB::table('media_files')->where('id',$media)->delete();$this->commit()->assertSee('image was removed');$this->assertDatabaseMissing('brands',['title'=>'Must roll back']);
        $this->preview("product\nOld preview");$this->preview('')->assertStatus(422);$this->assertNull(session('import_preview'));
    }
    public function test_product_import_permissions_and_csrf_still_apply(): void {
        $this->get('/admin/import')->assertRedirect('/admin/login');
        $user=AdminUser::create(['name'=>'Content only','email'=>'content-'.bin2hex(random_bytes(6)).'@example.test','password_hash'=>Hash::make('Test-password-123'),'role_id'=>2,'is_active'=>1,'auth_version'=>1]);$this->actingAs($user)->withSession(['auth_version'=>1]);
        $this->preview("brand,product\nForbidden brand,Forbidden product")->assertForbidden();$this->assertDatabaseMissing('brands',['title'=>'Forbidden brand']);
        $this->signIn();$this->app['env']='local';$this->preview("product\nNo CSRF")->assertStatus(419);
    }
}
