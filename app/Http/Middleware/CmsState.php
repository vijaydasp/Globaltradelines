<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use App\Services\{Auth,Settings,Media,Navigation};
class CmsState {
    public function handle(Request $request,Closure $next){
        Auth::reset();Settings::reset();Media::reset();Navigation::reset();\App\Models\Content::$preview=false;
        $response=$next($request);
        $response->headers->add(['X-Content-Type-Options'=>'nosniff','X-Frame-Options'=>'SAMEORIGIN','Referrer-Policy'=>'strict-origin-when-cross-origin','Permissions-Policy'=>'camera=(), microphone=(), geolocation=()']);
        if($request->is('admin','admin/*'))$response->headers->set('Cache-Control','private, no-store');
        if(str_starts_with(config('app.url'),'https://'))$response->headers->set('Strict-Transport-Security','max-age=31536000');
        return $response;
    }
}
