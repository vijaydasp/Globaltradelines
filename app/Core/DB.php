<?php
namespace App\Core;
use Illuminate\Support\Facades\DB as LaravelDB;

/** Array-shaped repository interface backed by Laravel's connection and query builder. */
final class DB {
    public static function connection(): \Illuminate\Database\Connection {return LaravelDB::connection();}
    public static function run(string $sql,array $params=[]): array|bool {return preg_match('/^\s*(SELECT|SHOW|DESCRIBE)\b/i',$sql)?self::all($sql,$params):LaravelDB::statement($sql,$params);}
    public static function all(string $sql,array $params=[]): array {return array_map(fn($row)=>(array)$row,LaravelDB::select($sql,$params));}
    public static function one(string $sql,array $params=[]): ?array {$row=LaravelDB::selectOne($sql,$params);return $row===null?null:(array)$row;}
    public static function value(string $sql,array $params=[]): mixed {$row=self::one($sql,$params);return $row?reset($row):false;}
    public static function insert(string $table,array $data): int {return (int)LaravelDB::table($table)->insertGetId($data);}
    public static function update(string $table,int $id,array $data): void {LaravelDB::table($table)->where('id',$id)->update($data);}
    public static function transaction(callable $callback): mixed {return LaravelDB::transaction($callback);}
}
