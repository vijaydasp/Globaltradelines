<?php
return ['mail_enabled'=>(bool)env('MAIL_ENABLED',false),'turnstile_secret'=>env('TURNSTILE_SECRET',''),'upload_max_mb'=>(int)env('UPLOAD_MAX_MB',25)];
