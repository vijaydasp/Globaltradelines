<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\DB;
final class Settings {
    public static function reset(): void { self::$cache=null; }
    private static ?array $cache=null;
    public static function get(string $key,string $default=''): string {
        if (self::$cache===null) self::$cache=array_column(DB::all('SELECT setting_key, value FROM site_settings'),'value','setting_key');
        return self::$cache[$key]??$default;
    }
    public static function has(string $key): bool {
        if (self::$cache===null) self::$cache=array_column(DB::all('SELECT setting_key, value FROM site_settings'),'value','setting_key');
        return array_key_exists($key,self::$cache);
    }
    public static function put(string $key,string $value,string $group='text'): void {
        DB::run('INSERT INTO site_settings (setting_key,value,group_name) VALUES (?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[$key,$value,$group]);
        self::$cache=null;
    }
}
