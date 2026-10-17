<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class DatabaseSeeder extends Seeder {
    public function run(): void {
        if(DB::table('site_settings')->exists()){$this->command?->info('Existing CMS content preserved; seed data was not reapplied.');return;}
        DB::transaction(fn()=>DB::unprepared(file_get_contents(database_path('schema/seed-v1.sql'))));
    }
}
