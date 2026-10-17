<?php
namespace Tests;
use Illuminate\Foundation\Testing\TestCase as LaravelTestCase;
use Illuminate\Support\Facades\{Artisan,DB,Schema};

abstract class TestCase extends LaravelTestCase {
    protected function setUp(): void {
        parent::setUp();
        if(!str_ends_with(config('database.connections.mysql.database'),'_test'))throw new \RuntimeException('Tests require a dedicated *_test database.');
        if(!Schema::hasTable('installation')){Artisan::call('migrate',['--force'=>true]);Artisan::call('db:seed',['--force'=>true]);DB::table('installation')->insert(['id'=>1,'version'=>'test']);}
        DB::beginTransaction();
    }
    protected function tearDown(): void {DB::rollBack();parent::tearDown();}
}
