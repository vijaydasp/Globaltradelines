<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Header settings are key/value records, so no table columns are required.
        if (!Schema::hasTable('site_settings')) {
            return;
        }

        DB::table('site_settings')->insertOrIgnore([
            ['setting_key' => 'topbar_brand_text', 'value' => 'GLOBAL TRADE LINES', 'group_name' => 'header'],
            ['setting_key' => 'topbar_location', 'value' => 'India - Global Reach', 'group_name' => 'header'],
            ['setting_key' => 'topbar_phone', 'value' => '', 'group_name' => 'header'],
            ['setting_key' => 'topbar_email', 'value' => '', 'group_name' => 'header'],
            ['setting_key' => 'facebook_url', 'value' => '', 'group_name' => 'header'],
            ['setting_key' => 'linkedin_url', 'value' => '', 'group_name' => 'header'],
            ['setting_key' => 'instagram_url', 'value' => '', 'group_name' => 'header'],
            ['setting_key' => 'whatsapp_url', 'value' => '', 'group_name' => 'header'],
            ['setting_key' => 'show_topbar_location', 'value' => '1', 'group_name' => 'header'],
            ['setting_key' => 'show_topbar_phone', 'value' => '1', 'group_name' => 'header'],
            ['setting_key' => 'show_topbar_email', 'value' => '1', 'group_name' => 'header'],
            ['setting_key' => 'show_topbar_social_icons', 'value' => '1', 'group_name' => 'header'],
        ]);
    }

    public function down(): void {
        // Settings data is intentionally retained on rollback.
    }
};
