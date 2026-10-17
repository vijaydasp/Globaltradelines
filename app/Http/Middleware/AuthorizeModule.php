<?php
namespace App\Http\Middleware;
use App\Services\Auth;
use App\Models\Modules;
use Closure;
use Illuminate\Http\Request;
class AuthorizeModule {
    public function handle(Request $request,Closure $next){Auth::require(Modules::get((string)$request->route('module'))['permission']);return $next($request);}
}
