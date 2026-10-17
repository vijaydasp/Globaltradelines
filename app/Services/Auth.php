<?php
namespace App\Services;
use App\Core\HttpException;
use App\Models\AdminUser;
use Illuminate\Support\Facades\Auth as LaravelAuth;
use Illuminate\Support\Facades\Gate;

final class Auth {
    private static ?array $user=null;
    public static function reset(): void {self::$user=null;}
    public static function user(): ?array {
        if(self::$user!==null)return self::$user;
        $identity=LaravelAuth::user();if(!$identity)return null;
        $user=AdminUser::with('role')->find($identity->id);
        if(!$user||!$user->is_active||$user->auth_version!==(int)session('auth_version',0)){self::clear();return null;}
        LaravelAuth::setUser($user);
        return self::$user=$user->getAttributes()+['is_super'=>$user->role->is_super,'role_title'=>$user->role->title,'permissions'=>$user->role->permissions()->pluck('permission_key')->all()];
    }
    public static function can(string $permission): bool {return self::user()!==null&&Gate::allows($permission);}
    public static function require(string $permission=''): void {
        if(!self::user())cms_redirect('/admin/login');
        if($permission!==''&&!self::can($permission))throw new HttpException(403,'Your role does not allow this action.');
    }
    public static function id(): ?int {return self::user()?(int)self::user()['id']:null;}
    public static function login(string $email,string $password): bool {
        RateLimit::hit('login-ip:'.request()->ip(),30,900);
        RateLimit::hit('login-account:'.strtolower($email),8,900);
        if(!LaravelAuth::attempt(['email'=>strtolower($email),'password'=>$password,'is_active'=>1]))return false;
        session()->regenerate();self::reset();
        $user=LaravelAuth::user();session()->put('auth_version',(int)$user->auth_version);
        $user->forceFill(['last_login_at'=>now()])->save();
        Audit::record('login','admin_users',(int)$user->id,'Administrator signed in');return true;
    }
    public static function clear(): void {self::reset();LaravelAuth::logout();session()->invalidate();session()->regenerateToken();}
}
