<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\AdminUser;
use App\Models\Modules;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        date_default_timezone_set('Asia/Kolkata');
        foreach (Modules::permissions() as $permission=>$label) {
            Gate::define($permission, fn(AdminUser $user) => $user->hasPermission($permission));
        }
    }
}
