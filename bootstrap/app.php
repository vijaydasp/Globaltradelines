<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->remove([Illuminate\Foundation\Http\Middleware\TrimStrings::class, Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class]);
        $middleware->web(append: [App\Http\Middleware\CmsState::class, App\Http\Middleware\EnsureInstalled::class]);
        $middleware->alias(['cms.auth'=>App\Http\Middleware\AuthenticateCms::class,'cms.module'=>App\Http\Middleware\AuthorizeModule::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $error, Request $request) {
            if ($error instanceof Illuminate\Http\Exceptions\HttpResponseException) return $error->getResponse();
            if ($error instanceof Illuminate\Validation\ValidationException) return null;
            $status=$error instanceof HttpExceptionInterface?$error->getStatusCode():500;
            $message=$status===500?'An unexpected error occurred. Check the application log.':($error->getMessage()?:'This request could not be completed.');
            if ($request->expectsJson()) return response()->json(['error'=>$message],$status);
            return response()->view('public.error',compact('status','message'),$status)->header('Cache-Control','no-store');
        });
    })->create();
