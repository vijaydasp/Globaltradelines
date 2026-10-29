<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('site_settings')) return;
        DB::table('site_settings')->insertOrIgnore([
            ['setting_key'=>'show_facebook_icon','value'=>'1','group_name'=>'header'],
            ['setting_key'=>'show_linkedin_icon','value'=>'1','group_name'=>'header'],
            ['setting_key'=>'show_instagram_icon','value'=>'1','group_name'=>'header'],
            ['setting_key'=>'show_whatsapp_icon','value'=>'1','group_name'=>'header'],
        ]);
    }

    public function down(): void {}
};
