<?php
namespace App\Console\Commands;
use App\Models\AdminUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB,Hash,Schema,Validator};

class InstallCms extends Command {
    protected $signature='cms:install {--name=} {--email=}';
    protected $description='Install CMS tables and recovered content, then securely create the first administrator';
    public function handle(): int {
        if(!config('app.key')){$this->error('Run php artisan key:generate first.');return self::FAILURE;}
        if(Schema::hasTable('installation')&&DB::table('installation')->exists()){$this->error('Already installed. Existing content has not been changed. Use migrate to adopt an existing native CMS.');return self::FAILURE;}
        $name=$this->option('name')?:$this->ask('Administrator name');
        $email=strtolower($this->option('email')?:$this->ask('Administrator email'));
        $password=app()->environment('testing')&&getenv('CMS_INSTALL_PASSWORD')?getenv('CMS_INSTALL_PASSWORD'):$this->secret('Administrator password (12–72 characters)');
        $validator=Validator::make(compact('name','email','password'),['name'=>'required|string|max:150','email'=>'required|email|max:254','password'=>'required|string|min:12|max:72']);
        if($validator->fails()){foreach($validator->errors()->all() as $error)$this->error($error);return self::FAILURE;}
        if($this->call('migrate',['--force'=>true])!==0)return self::FAILURE;
        if(DB::table('admin_users')->exists()){$this->error('Administrator accounts already exist. Recover access using cms:reset-password.');return self::FAILURE;}
        if($this->call('db:seed',['--force'=>true])!==0)return self::FAILURE;
        DB::transaction(function()use($name,$email,$password):void{
            AdminUser::create(['name'=>$name,'email'=>$email,'password_hash'=>Hash::make($password),'role_id'=>1,'is_active'=>1]);
            DB::table('installation')->insert(['id'=>1,'version'=>'2.0.0-laravel13']);
        });
        $this->info('Installed. Sign in at '.absolute_url('admin/login'));return self::SUCCESS;
    }
}
