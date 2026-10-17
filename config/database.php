<?php
return ['default'=>env('DB_CONNECTION','mysql'),'connections'=>[
    'mysql'=>['driver'=>'mysql','url'=>env('DB_URL'),'host'=>env('DB_HOST','127.0.0.1'),'port'=>env('DB_PORT','3308'),'database'=>env('DB_DATABASE','globaltradelines'),'username'=>env('DB_USERNAME','root'),'password'=>env('DB_PASSWORD',''),'unix_socket'=>env('DB_SOCKET',''),'charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci','prefix'=>'','prefix_indexes'=>true,'strict'=>true,'timezone'=>'+00:00','engine'=>'InnoDB','options'=>extension_loaded('pdo_mysql')?[PDO::ATTR_EMULATE_PREPARES=>false]:[]],
    'sqlite'=>['driver'=>'sqlite','url'=>env('DB_URL'),'database'=>env('DB_DATABASE',database_path('database.sqlite')),'prefix'=>'','foreign_key_constraints'=>true],
],'migrations'=>['table'=>'migrations','update_date_on_publish'=>true]];
