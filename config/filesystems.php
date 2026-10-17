<?php
return ['default'=>'private','disks'=>[
    'local'=>['driver'=>'local','root'=>storage_path('app/private'),'serve'=>false,'throw'=>true],
    'private'=>['driver'=>'local','root'=>storage_path('app/private'),'serve'=>false,'throw'=>true,'visibility'=>'private'],
]];
