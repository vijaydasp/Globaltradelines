<?php
declare(strict_types=1);
/** Creates and removes randomly named test databases on the configured QA server. */
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Symfony\Component\Process\Process;

$db=config('database.connections.mysql');
if(PHP_SAPI!=='cli'||!str_ends_with($db['database'],'_test')){
    fwrite(STDERR,"Installation checks require a dedicated *_test database configuration.\n");exit(2);
}
$pdo=new PDO("mysql:host={$db['host']};port={$db['port']};charset=utf8mb4",$db['username'],$db['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$created=[];$passed=0;
function verify(bool $condition,string $label):void{
    global $passed;
    if(!$condition)throw new RuntimeException($label);
    $passed++;echo "PASS $label\n";
}
function command(string $database,array $arguments):Process{
    global $db;
    $process=new Process([PHP_BINARY,base_path('artisan'),...$arguments,'--no-interaction','--no-ansi'],base_path(),[
        'APP_ENV'=>'testing','APP_KEY'=>config('app.key'),'DB_CONNECTION'=>'mysql','DB_URL'=>'',
        'DB_HOST'=>$db['host'],'DB_PORT'=>(string)$db['port'],'DB_DATABASE'=>$database,
        'DB_USERNAME'=>$db['username'],'DB_PASSWORD'=>$db['password'],'BCRYPT_ROUNDS'=>'4',
        'CMS_INSTALL_PASSWORD'=>'Install-'.bin2hex(random_bytes(16)),
    ]);
    $process->setTimeout(90);$process->run();return $process;
}
function database():string{
    global $pdo,$created;
    $name='gtl_install_'.bin2hex(random_bytes(8)).'_test';
    $pdo->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $created[]=$name;$pdo->exec("USE `$name`");return $name;
}
try{
    $fresh=database();
    $result=command($fresh,['cms:install','--name=Installer QA','--email=installer@example.test']);
    verify($result->isSuccessful(),'Fresh Artisan installer succeeds');
    verify((int)$pdo->query('SELECT COUNT(*) FROM pages')->fetchColumn()===11,'Recovered pages seeded');
    $hash=$pdo->query('SELECT password_hash FROM admin_users')->fetchColumn();
    verify(str_starts_with($hash,'$2y$'),'First administrator stores a bcrypt hash');
    verify((int)$pdo->query('SELECT COUNT(*) FROM installation')->fetchColumn()===1,'Installation marker recorded');
    verify(!command($fresh,['cms:install','--name=Again','--email=again@example.test'])->isSuccessful(),'Repeat installation rejected');
    verify((int)$pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn()===1,'Repeat installation preserves administrator');

    $native=database();
    $pdo->exec(file_get_contents(database_path('schema/cms-v1.sql')));
    $pdo->exec(file_get_contents(database_path('schema/seed-v1.sql')));
    $pdo->exec("INSERT INTO installation (id,version) VALUES (1,'1.0.0')");
    $pdo->exec("INSERT INTO products (title,slug,status) VALUES ('Preserved native product','native-product','published')");
    $pdo->exec("UPDATE site_settings SET value='Preserved business' WHERE group_name='company' AND setting_key='name'");
    $nativeHash=password_hash('Native-'.bin2hex(random_bytes(12)),PASSWORD_BCRYPT,['cost'=>4]);
    $pdo->prepare('INSERT INTO admin_users (name,email,password_hash,role_id,is_active) VALUES (?,?,?,?,?)')->execute(['Native admin','native@example.test',$nativeHash,1,1]);
    $productId=(int)$pdo->query("SELECT id FROM products WHERE slug='native-product'")->fetchColumn();
    verify(command($native,['migrate','--force'])->isSuccessful(),'Complete native schema adopted through Laravel migration');
    verify((int)$pdo->query("SELECT id FROM products WHERE slug='native-product'")->fetchColumn()===$productId,'Migration preserves existing product IDs');
    verify($pdo->query('SELECT password_hash FROM admin_users')->fetchColumn()===$nativeHash,'Migration preserves administrator password hash');
    verify(command($native,['db:seed','--force'])->isSuccessful()&&(int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn()===1,'Seeding preserves existing content');
    verify(!command($native,['cms:install','--name=Again','--email=again@example.test'])->isSuccessful(),'Fresh installer refuses an adopted installation');

    $partial=database();
    $pdo->exec('CREATE TABLE pages (id INT PRIMARY KEY)');
    verify(!command($partial,['migrate','--force'])->isSuccessful(),'Partial legacy schema rejected without replacement');
    verify((int)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='$partial' AND table_name='pages'")->fetchColumn()===1,'Partial schema remains intact');
    echo "$passed installation checks passed.\n";
}finally{
    foreach($created as $name){
        if(!preg_match('/^gtl_install_[a-f0-9]{16}_test$/',$name))throw new RuntimeException('Unsafe cleanup target');
        $pdo->exec("DROP DATABASE `$name`");
    }
}
