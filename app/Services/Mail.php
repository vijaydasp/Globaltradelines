<?php
namespace App\Services;
use App\Core\DB;
use Illuminate\Support\Facades\Mail as LaravelMail;

final class Mail {
    public static function send(string $to,string $subject,string $body): string {
        if(!config('cms.mail_enabled'))return 'disabled';
        $from=config('mail.from.address');
        if(!filter_var($to,FILTER_VALIDATE_EMAIL)||!filter_var($from,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$to.$from.$subject))return 'failed';
        try{LaravelMail::raw($body,fn($message)=>$message->to($to)->subject($subject));return config('mail.default')==='smtp'?'accepted':'logged';}
        catch(\Throwable $error){report($error);return 'failed';}
    }
    public static function requestReset(string $email): void {
        RateLimit::hit('reset:'.request()->ip(),5,900);
        if(!config('cms.mail_enabled'))return;
        $user=DB::one('SELECT id FROM admin_users WHERE email=? AND is_active=1',[strtolower($email)]);if(!$user)return;
        $token=bin2hex(random_bytes(32));
        DB::run('DELETE FROM password_resets WHERE user_id=? OR expires_at<UTC_TIMESTAMP()',[$user['id']]);
        DB::insert('password_resets',['token_hash'=>hash('sha256',$token),'user_id'=>$user['id'],'expires_at'=>gmdate('Y-m-d H:i:s',time()+1800)]);
        $status=self::send($email,'Reset your Global Trade Lines CMS password',"Use this link within 30 minutes:\n".absolute_url('admin/reset-password?token='.$token)."\nIf you did not request this, ignore this message.");
        if(!in_array($status,['accepted','logged'],true))DB::run('DELETE FROM password_resets WHERE token_hash=?',[hash('sha256',$token)]);
    }
}
