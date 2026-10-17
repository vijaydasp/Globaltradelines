<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Schema};
class EnsureInstalled {
    public function handle(Request $request,Closure $next){
        if(!Schema::hasTable('installation')||!DB::table('installation')->exists())return response()->view('public.setup',[],503);
        return $next($request);
    }
}
