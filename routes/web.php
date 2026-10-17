<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{AdminController,ImportController,PublicController,MediaController};

Route::match(['get','post'],'admin/login',[AdminController::class,'authentication'])->name('login');
Route::match(['get','post'],'admin/forgot-password',[AdminController::class,'authentication'])->name('password.request');
Route::match(['get','post'],'admin/reset-password',[AdminController::class,'authentication'])->name('password.reset');
Route::prefix('admin')->name('admin.')->middleware('cms.auth')->group(function(): void {
    Route::get('/',[AdminController::class,'dashboard'])->name('dashboard');
    Route::post('logout',[AdminController::class,'logout'])->name('logout');
    Route::match(['get','post'],'password',[AdminController::class,'password'])->name('password');
    Route::get('search',[AdminController::class,'search'])->name('search');
    Route::get('options',[AdminController::class,'options'])->name('options');
    Route::get('{kind}',[AdminController::class,'builder'])->whereIn('kind',['homepage','navigation','footer'])->name('builder');
    Route::match(['get','post'],'settings/{group}',[AdminController::class,'settings'])->name('settings');
    Route::post('upload',[AdminController::class,'upload'])->name('upload');
    Route::match(['get','post'],'import',[ImportController::class,'handle'])->name('import');
    Route::get('preview/{module}/{id}',[PublicController::class,'preview'])->whereIn('module',['pages','products'])->whereNumber('id')->name('preview');
    Route::middleware('cms.module')->group(function(): void {
        Route::get('{module}',[AdminController::class,'listing'])->name('listing');
        Route::get('{module}/export',[AdminController::class,'export'])->name('export');
        Route::post('{module}/reorder',[AdminController::class,'reorder'])->name('reorder');
        Route::match(['get','post'],'{module}/{id}',[AdminController::class,'edit'])->where('id','new|[0-9]+')->name('edit');
        Route::post('{module}/{id}/{action}',[AdminController::class,'action'])->whereNumber('id')->whereIn('action',['delete','duplicate','restore'])->name('action');
    });
});
Route::get('media/{id}',[MediaController::class,'show'])->whereNumber('id')->name('media');
Route::post('enquiry',[PublicController::class,'enquiry'])->name('enquiry');
Route::get('sitemap.xml',[PublicController::class,'sitemap'])->name('sitemap');
Route::get('robots.txt',[PublicController::class,'robots'])->name('robots');
Route::get('search',[PublicController::class,'search'])->name('search');
Route::get('brochures/{id}/{action}',[PublicController::class,'download'])->whereNumber('id')->whereIn('action',['pdf','download'])->name('brochure');
Route::get('products/{slug}',[PublicController::class,'showProduct'])->name('product');
Route::get('product/{slug}',[PublicController::class,'showProduct'])->name('product.legacy');
Route::get('/',[PublicController::class,'show'])->name('home');
Route::get('{slug}',[PublicController::class,'show'])->where('slug','(?!admin$|media$|assets$|storage$|vendor$|\.)([a-z0-9-]+)')->name('page');
