<?php
namespace App\Http\Middleware;
use App\Services\Auth;
use Closure;
use Illuminate\Http\Request;
class AuthenticateCms {
    public function handle(Request $request,Closure $next){Auth::require();return $next($request);}
}
