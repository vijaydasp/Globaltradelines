<?php
namespace Tests\Feature;
use App\Models\AdminUser;
use Illuminate\Support\Facades\{Auth,DB,Hash,Mail};
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class FrameworkTest extends TestCase {
    private function admin(int $role=1): AdminUser {return AdminUser::create(['name'=>'Framework Test','email'=>bin2hex(random_bytes(5)).'@example.test','password_hash'=>Hash::make('Test-only-password-123'),'role_id'=>$role,'is_active'=>1,'auth_version'=>1]);}
    public function test_guest_redirect_and_laravel_blade_login(): void {
        $this->get('/admin')->assertStatus(303)->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk()->assertViewIs('admin.login')->assertSee('Sign in to your workspace');
        $this->get('/storage/test-access.json')->assertNotFound();
    }
    public function test_eloquent_identity_and_role_restrict_content_editor(): void {
        $user=$this->admin(2);$this->actingAs($user)->withSession(['auth_version'=>1]);
        $this->get('/admin/pages')->assertOk();
        $this->get('/admin/products')->assertForbidden();
        $this->post('/admin/admin_users/new',['name'=>'Unauthorized'])->assertForbidden();
    }
    public function test_password_version_change_invalidates_existing_session(): void {
        $user=$this->admin();$this->actingAs($user)->withSession(['auth_version'=>1]);
        DB::table('admin_users')->where('id',$user->id)->update(['auth_version'=>2]);
        $this->get('/admin')->assertStatus(303)->assertRedirect('/admin/login');
        $this->assertGuest();
    }
    public function test_password_reset_token_is_single_use(): void {
        $user=$this->admin();$token=bin2hex(random_bytes(32));
        DB::table('password_resets')->insert(['token_hash'=>hash('sha256',$token),'user_id'=>$user->id,'expires_at'=>now()->addMinutes(30)]);
        $this->post('/admin/reset-password',['token'=>$token,'password'=>'Changed-test-password-123'])->assertStatus(303);
        $this->assertTrue(Hash::check('Changed-test-password-123',$user->fresh()->password_hash));
        $this->assertSame(2,$user->fresh()->auth_version);
        $this->post('/admin/reset-password',['token'=>$token,'password'=>'Another-test-password-123'])->assertSee('invalid or has expired');
    }
    public function test_mail_uses_configured_laravel_transport(): void {
        config(['cms.mail_enabled'=>true,'mail.default'=>'smtp','mail.from.address'=>'sender@example.test']);
        Mail::shouldReceive('raw')->once()->andReturnNull();
        $this->assertSame('accepted',\App\Services\Mail::send('receiver@example.test','Enquiry','Stored in the CMS'));
        config(['cms.mail_enabled'=>false]);
        $this->assertSame('disabled',\App\Services\Mail::send('receiver@example.test','Enquiry','Stored in the CMS'));
    }
    public function test_private_upload_requires_public_reference_and_supports_range(): void {
        $user=$this->admin();$this->actingAs($user)->withSession(['auth_version'=>1]);
        $file=UploadedFile::fake()->createWithContent('test.png',file_get_contents(public_path('assets/img/global-trade-lines-favicon-48.png')));
        $upload=$this->postJson('/admin/upload',['files'=>[$file]])->assertOk();
        $id=$upload->json('files.0.id');$path=DB::table('media_files')->where('id',$id)->value('path');
        try{
            Auth::logout();session()->invalidate();
            $this->get('/media/'.$id)->assertNotFound();
            DB::table('products')->insert(['title'=>'Range test','slug'=>'range-test','status'=>'published','image_id'=>$id]);
            $this->get('/media/'.$id,['Range'=>'bytes=0-7'])->assertStatus(206)->assertHeader('Content-Length','8');
            $this->head('/media/'.$id)->assertOk();
        }finally{\Illuminate\Support\Facades\Storage::disk('private')->delete($path);}
    }
    public function test_existing_cms_migration_and_seed_preserve_business_content(): void {
        $id=DB::table('products')->insertGetId(['title'=>'Preserve this product','slug'=>'preserve-this-product','status'=>'published']);
        $migration=require database_path('migrations/2026_10_16_000001_create_cms_tables.php');$migration->up();
        (new \Database\Seeders\DatabaseSeeder)->run();
        $this->assertSame('Preserve this product',DB::table('products')->where('id',$id)->value('title'));
        $this->assertSame(11,DB::table('pages')->count());
    }
    public function test_homepage_section_can_be_deleted_with_its_slides_and_items(): void {
        $user=$this->admin();$this->actingAs($user)->withSession(['auth_version'=>1]);
        $page=(int)DB::table('pages')->where('page_type','home')->value('id');
        $section=DB::table('page_sections')->insertGetId(['page_id'=>$page,'title'=>'Disposable section','section_type'=>'hero','status'=>'published']);
        $slide=DB::table('hero_slides')->insertGetId(['section_id'=>$section,'title'=>'Disposable slide','status'=>'published']);
        $item=DB::table('section_items')->insertGetId(['section_id'=>$section,'title'=>'Disposable item','status'=>'published']);
        foreach([['page_sections',$section],['hero_slides',$slide],['section_items',$item]] as [$entity,$entityId]) {
            DB::table('content_drafts')->insert(['entity'=>$entity,'entity_id'=>$entityId,'payload'=>'{}','editor_id'=>$user->id]);
            DB::table('content_revisions')->insert(['entity'=>$entity,'entity_id'=>$entityId,'payload'=>'{}','editor_id'=>$user->id]);
        }
        $this->get('/admin/homepage')->assertOk()->assertSee('Delete');
        $this->post('/admin/page_sections/'.$section.'/delete')->assertRedirect('/admin/homepage?page_id='.$page);
        $this->assertDatabaseMissing('page_sections',['id'=>$section]);
        $this->assertDatabaseMissing('hero_slides',['id'=>$slide]);
        $this->assertDatabaseMissing('section_items',['id'=>$item]);
        $this->assertDatabaseMissing('content_drafts',['entity'=>'hero_slides','entity_id'=>$slide]);
        $this->assertDatabaseMissing('content_revisions',['entity'=>'section_items','entity_id'=>$item]);
    }
    public function test_published_features_section_hides_its_internal_section_name_without_feature_items(): void {
        $page=(int)DB::table('pages')->where('page_type','home')->value('id');
        DB::table('page_sections')->insert(['page_id'=>$page,'title'=>'Internal features section name','section_type'=>'features','status'=>'published']);
        $this->get('/')->assertOk()->assertDontSee('Internal features section name');
    }
}
