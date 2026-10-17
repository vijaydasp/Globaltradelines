<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up(): void {
        if(DB::getDriverName()!=='mysql')throw new RuntimeException('The CMS schema requires MySQL or MariaDB.');
        $sql=file_get_contents(database_path('schema/cms-v1.sql'));
        preg_match_all('/CREATE TABLE\s+`?([a-z_]+)`?\s*\(/i',$sql,$matches);
        $tables=$matches[1];$existing=array_values(array_filter($tables,fn($table)=>Schema::hasTable($table)));
        if($existing){
            if(count($existing)!==count($tables)||!DB::table('installation')->exists())throw new RuntimeException('Partial or unrecognized CMS schema. Use an empty database or a complete backup of the installed native CMS.');
            preg_match_all('/CREATE TABLE\s+`([a-z_]+)`\s*\((.*?)\) ENGINE=/s',$sql,$definitions,PREG_SET_ORDER);
            foreach($definitions as $definition){preg_match_all('/^\s*`([a-z_]+)`\s/m',$definition[2],$columns);if(!Schema::hasColumns($definition[1],$columns[1]))throw new RuntimeException('Existing CMS schema is missing columns in '.$definition[1].'.');}
            return; // Adopt the compatible native schema without rewriting content or passwords.
        }
        DB::unprepared($sql);
    }
    public function down(): void {throw new RuntimeException('CMS data rollback is intentionally non-destructive. Restore a database backup to revert this installation.');}
};
