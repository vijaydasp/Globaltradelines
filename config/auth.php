<?php
return ['defaults'=>['guard'=>'web','passwords'=>'admins'],'guards'=>['web'=>['driver'=>'session','provider'=>'admins']],'providers'=>['admins'=>['driver'=>'eloquent','model'=>App\Models\AdminUser::class]],'password_timeout'=>10800];
