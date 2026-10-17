<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\DB;
use App\Core\HttpException;
final class RateLimit {
    public static function hit(string $key,int $limit,int $seconds): void {
        $bucket=hash_hmac('sha256',$key,cms_config('APP_KEY'));
        DB::run('INSERT INTO rate_limits (bucket,attempts,expires_at) VALUES (?,1,?) ON DUPLICATE KEY UPDATE attempts=IF(expires_at<?,1,attempts+1), expires_at=IF(expires_at<?,VALUES(expires_at),expires_at)',[$bucket,time()+$seconds,time(),time()]);
        if((int)DB::value('SELECT attempts FROM rate_limits WHERE bucket=?',[$bucket])>$limit) throw new HttpException(429,'Too many attempts. Please try again later.');
        if(random_int(1,100)===1) DB::run('DELETE FROM rate_limits WHERE expires_at<?',[time()-86400]);
    }
}
