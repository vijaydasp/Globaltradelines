<?php
namespace Tests\Feature;

use App\Models\AdminUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Hash};
use Tests\TestCase;

class CatalogueImportTest extends TestCase {
    private function signIn(int $role=3): void {
        $user=AdminUser::create(['name'=>'Import QA','email'=>bin2hex(random_bytes(6)).'@example.test','password_hash'=>Hash::make('Test-password-123'),'role_id'=>$role,'is_active'=>1,'auth_version'=>1]);
        $this->actingAs($user)->withSession(['auth_version'=>1]);
    }
    private function preview(string $module,string $csv): \Illuminate\Testing\TestResponse {
        return $this->post('/admin/catalogue-import/'.$module,['action'=>'preview','csv'=>UploadedFile::fake()->createWithContent('catalogue.csv',$csv)]);
    }
    private function commit(string $module): \Illuminate\Testing\TestResponse {
        return $this->post('/admin/catalogue-import/'.$module,['action'=>'commit','import_token'=>session('catalogue_import.'.$module.'.token')]);
    }
    public function test_all_templates_and_valid_classification_imports(): void {
        $this->signIn();
        foreach(['categories','brands','subcategories'] as $module)$this->get('/admin/catalogue-import/'.$module.'/template')->assertOk()->assertDownload($module.'-template.csv');
        $this->preview('categories',"title,slug,description,image_id,sort_order,status\nQA Category,qa-category,Details,2,10,published\n")->assertSee('Create new');
        $this->commit('categories')->assertSee('1 imported');$category=DB::table('categories')->where('slug','qa-category')->value('id');
        $this->preview('brands',"title,slug,image_id,website,is_featured\nQA Brand,qa-brand,2,https://example.test,1\n")->assertSee('Create new');
        $this->commit('brands')->assertSee('1 imported');$brand=DB::table('brands')->where('slug','qa-brand')->value('id');
        $this->preview('subcategories',"title,category_id,brand_ids,status\nQA Subcategory,$category,$brand,unpublished\n")->assertSee('Create new');
        $this->commit('subcategories')->assertSee('1 imported');
        $this->assertDatabaseHas('subcategories',['slug'=>'qa-subcategory','category_id'=>$category,'status'=>'unpublished']);
        $this->assertDatabaseHas('subcategory_brands',['brand_id'=>$brand]);
        $this->assertDatabaseHas('brands',['id'=>$brand,'status'=>'draft','image_id'=>2]);
        $this->assertDatabaseHas('admin_activity_logs',['action'=>'save','entity'=>'categories','entity_id'=>$category]);
    }
    public function test_invalid_rows_block_confirmation_and_report_physical_lines(): void {
        $this->signIn();
        $csv="title,slug,description,sort_order,status\nGood,good,\"Line one\nLine two\",0,draft\nBad,Invalid Slug,Text,-1,active\n";
        $this->preview('categories',$csv)->assertSee('Line 4:')->assertSee('use lowercase')->assertDontSee('Confirm &amp; import',false);
        $this->commit('categories')->assertSee('Correct the errors');
        $this->assertDatabaseMissing('categories',['slug'=>'good']);
    }
    public function test_empty_malformed_oversized_and_unsupported_csv_are_rejected(): void {
        $this->signIn();
        foreach([['','empty'],["title\n\"Unclosed",'Unclosed'],["title\n\"Closed\"junk",'Unexpected text'],["title,unknown\nA,B\n",'template headings'],["title,title\nA,B\n",'template headings'],["slug\nname\n",'Required'],["title\n\xff",'UTF-8'],["title\n",'no data rows']] as [$csv,$message])$this->preview('categories',$csv)->assertSee($message);
        $this->preview('categories',str_repeat('a',2097153))->assertSee('up to 2 MB');
        $this->preview('categories',"title\n".implode("\n",array_map(fn($i)=>'Row '.$i,range(1,1001))))->assertSee('at most 1,000');
    }
    public function test_required_typed_fields_and_media_authorization(): void {
        $this->signIn();
        foreach([
            ['categories',"title\n\"\"\n",'required'],
            ['categories',"title\n".str_repeat('a',256),'too long'],
            ['brands',"title,is_featured\nTest,yes",'use 0 or 1'],
            ['brands',"title,website\nTest,javascript:alert(1)",'https://'],
            ['brands',"title,image_id\nTest,999999999",'existing record'],
            ['categories',"title,sort_order\nTest,1.5",'whole number'],
            ['subcategories',"title,category_id\nTest,",'required'],
            ['subcategories',"title,category_id\nTest,Ambiguous name",'existing record'],
            ['subcategories',"title,category_id\nTest,999999999",'existing record'],
        ] as [$module,$csv,$error])$this->preview($module,$csv)->assertSee($error);
        $media=DB::table('media_files')->insertGetId(['title'=>'PDF','path'=>'uploads/test.pdf','original_name'=>'test.pdf','mime_type'=>'application/pdf','size_bytes'=>10]);
        $this->preview('brands',"title,image_id\nBrand,$media")->assertSee('Select an image');
        $role=DB::table('roles')->insertGetId(['title'=>'Catalogue only']);DB::table('role_permissions')->insert(['role_id'=>$role,'permission_key'=>'catalog']);$this->signIn($role);
        $this->preview('brands',"title,image_id\nBrand,2")->assertSee('Media Library permission');
    }
    public function test_duplicates_never_overwrite_relationships_and_repeated_confirm_is_safe(): void {
        $this->signIn();
        $category=DB::table('categories')->insertGetId(['title'=>'Existing category','slug'=>'existing-category']);
        $brand=DB::table('brands')->insertGetId(['title'=>'Existing brand','slug'=>'existing-brand','image_id'=>2]);
        $sub=DB::table('subcategories')->insertGetId(['title'=>'Existing sub','slug'=>'existing-sub','category_id'=>$category]);
        DB::table('subcategory_brands')->insert(['subcategory_id'=>$sub,'brand_id'=>$brand]);
        $product=DB::table('products')->insertGetId(['title'=>'Existing product','slug'=>'existing-product','category_id'=>$category,'subcategory_id'=>$sub,'brand_id'=>$brand]);
        foreach([['categories',"title,slug\nExisting category,different-slug"],['brands',"title,slug,image_id\nDifferent name,existing-brand,3"],['subcategories',"title,slug,category_id\nExisting sub,new-sub,$category"]] as [$module,$csv]) {
            $this->preview($module,$csv)->assertSee('Skip existing');$token=session('catalogue_import.'.$module.'.token');
            $this->commit($module)->assertSee('1 skipped');
            $this->post('/admin/catalogue-import/'.$module,['action'=>'commit','import_token'=>$token])->assertSee('1 skipped');
        }
        $this->assertDatabaseHas('products',['id'=>$product,'category_id'=>$category,'brand_id'=>$brand,'subcategory_id'=>$sub]);
        $this->assertDatabaseHas('brands',['id'=>$brand,'image_id'=>2,'title'=>'Existing brand']);
        $this->assertDatabaseHas('subcategory_brands',['subcategory_id'=>$sub,'brand_id'=>$brand]);
        $this->preview('categories',"title,slug\nRepeated,one\nRepeated,two")->assertSee('Duplicate name in this CSV');
        $this->preview('brands',"title,slug\nOne,same\nTwo,same")->assertSee('Duplicate slug in this CSV');
    }
    public function test_subcategory_ids_are_unambiguous_and_existing_parent_is_never_changed(): void {
        $this->signIn();
        $a=DB::table('categories')->insertGetId(['title'=>'Same name','slug'=>'parent-a']);$b=DB::table('categories')->insertGetId(['title'=>'Same name','slug'=>'parent-b']);
        $sub=DB::table('subcategories')->insertGetId(['title'=>'Child','slug'=>'child','category_id'=>$a]);
        $this->preview('subcategories',"title,slug,category_id\nNew name,child,$b")->assertSee('Skip existing');$this->commit('subcategories')->assertSee('1 skipped');
        $this->assertDatabaseHas('subcategories',['id'=>$sub,'category_id'=>$a]);
        $this->preview('subcategories',"title,category_id\nAnother child,$b")->assertSee('Create new');$this->commit('subcategories')->assertSee('1 imported');
        $this->assertDatabaseHas('subcategories',['title'=>'Another child','category_id'=>$b]);
    }
    public function test_confirmation_revalidates_references_expiration_and_tokens(): void {
        $this->signIn();$category=DB::table('categories')->insertGetId(['title'=>'Temporary','slug'=>'temporary']);
        $this->preview('subcategories',"title,category_id\nChild,$category");DB::table('categories')->where('id',$category)->delete();$this->commit('subcategories')->assertSee('1 failed');
        $this->preview('categories',"title\nNew category");$this->withSession(['catalogue_import.categories.expires'=>time()-1]);$this->commit('categories')->assertSee('expired');
        $this->preview('brands',"title\nNew brand");$this->post('/admin/catalogue-import/brands',['action'=>'commit','import_token'=>'wrong'])->assertSee('expired');
        $this->assertDatabaseMissing('brands',['title'=>'New brand']);
        $this->preview('categories',"title\nOld preview");$this->preview('categories','')->assertSee('empty');$this->assertNull(session('catalogue_import.categories'));
    }
    public function test_database_failure_rolls_back_failed_row_and_reports_successes_correctly(): void {
        $this->signIn();$this->preview('brands',"title\nFails once\nSucceeds next");$fail=true;
        DB::listen(function($query)use(&$fail):void{if($fail&&str_starts_with($query->sql,'insert into `admin_activity_logs`')){$fail=false;throw new \RuntimeException('Simulated audit failure');}});
        $this->commit('brands')->assertSee('1 imported')->assertSee('1 failed');
        $this->assertDatabaseMissing('brands',['title'=>'Fails once']);$this->assertDatabaseHas('brands',['title'=>'Succeeds next']);
    }
    public function test_permissions_and_csrf_remain_enforced(): void {
        $this->get('/admin/catalogue-import/categories')->assertRedirect('/admin/login');$this->signIn(2);
        foreach(['categories','brands','subcategories'] as $module){$this->get('/admin/catalogue-import/'.$module)->assertForbidden();$this->preview($module,"title\nForbidden")->assertForbidden();$this->get('/admin/catalogue-import/'.$module.'/template')->assertForbidden();}
        $this->signIn();$this->app['env']='local';$this->post('/admin/catalogue-import/categories',['action'=>'commit'])->assertStatus(419);
    }
}
