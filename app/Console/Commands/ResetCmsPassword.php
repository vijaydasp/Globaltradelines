<?php
namespace App\Console\Commands;
use App\Models\AdminUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Hash,Validator};
class ResetCmsPassword extends Command {
    protected $signature='cms:reset-password';
    protected $description='Reset an existing administrator password and invalidate previous sessions';
    public function handle(): int {
        $email=strtolower((string)$this->ask('Administrator email'));
        $user=AdminUser::where('email',$email)->first();if(!$user){$this->error('Administrator not found.');return self::FAILURE;}
        $password=$this->secret('New password (12–72 characters)');
        $validator=Validator::make(compact('password'),['password'=>'required|string|min:12|max:72']);if($validator->fails()){$this->error('Use a password of 12–72 characters.');return self::FAILURE;}
        $user->forceFill(['password_hash'=>Hash::make($password),'auth_version'=>$user->auth_version+1])->save();
        $this->info('Password updated; previous sessions were invalidated.');return self::SUCCESS;
    }
}
