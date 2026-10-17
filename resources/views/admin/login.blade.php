<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?=$path==='/admin/login'?'Sign in':($path==='/admin/forgot-password'?'Reset password':'New password')?> · Global Trade Lines</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500&display=swap">
<link rel="stylesheet" href="<?=e(cms_url('/assets/css/auth.css?v=20261017'))?>">
<link rel="icon" href="<?=e(cms_url('/assets/img/global-trade-lines-favicon-48.png'))?>">
</head>
<body>

  <!-- Animated mesh background -->
  <div class="bg-mesh" aria-hidden="true"></div>

  <!-- Floating colour orbs -->
  <div class="orb orb-1" aria-hidden="true"></div>
  <div class="orb orb-2" aria-hidden="true"></div>
  <div class="orb orb-3" aria-hidden="true"></div>
  <div class="orb orb-4" aria-hidden="true"></div>

  <main class="auth-wrapper">
    <!-- Brand Logo -->
    <a class="brand" href="<?=e(cms_url('/'))?>">
      <img src="<?=e(cms_url('/assets/img/global-trade-line-logo.png'))?>"
           alt="Global Trade Lines" class="brand-logo" width="14998" height="3656" fetchpriority="high">
    </a>

    <div class="auth-card" aria-labelledby="auth-title">
      <h1 id="auth-title"><?=$path==='/admin/login'?'Welcome back.':($path==='/admin/forgot-password'?'Reset your password.':'Choose a new password.')?></h1>
      <p class="subtitle"><?=$path==='/admin/login'?'Sign in to manage your website.':'Secure access to your website studio.'?></p>

      <?php foreach(flashes() as $flash):?>
        <div class="notice <?=$flash['type']==='error'?'error':''?>" role="status"><?=e($flash['message'])?></div>
      <?php endforeach?>
      <?php if($error):?>
        <div class="notice error" role="alert"><?=e($error)?></div>
      <?php endif?>
      <?php if($message):?>
        <div class="notice" role="status"><?=e($message)?></div>
      <?php endif?>

      <form method="post" action="<?=e(cms_url($path))?>">
        <?=csrf_field()?>

        <?php if($path!=='/admin/reset-password'):?>
          <div class="field">
            <label for="email">Email address</label>
            <input id="email" type="email" name="email" required autocomplete="username"
                   placeholder="you@company.com"
                   value="<?=e(request()->post()['email']??'')?>">
          </div>
        <?php endif?>

        <?php if($path!=='/admin/forgot-password'):?>
          <div class="field">
            <label for="password">Password</label>
            <div class="input-wrap">
              <input id="password" type="password" name="password" required
                     placeholder="••••••••••••"
                     autocomplete="<?=$path==='/admin/login'?'current-password':'new-password'?>"
                     <?=$path==='/admin/reset-password'?'minlength="12" maxlength="72"':''?>>
              <button type="button" class="reveal" data-target="password" aria-controls="password" aria-label="Show password" aria-pressed="false">Show</button>
            </div>
          </div>
        <?php endif?>

        <?php if($path==='/admin/reset-password'):?>
          <input type="hidden" name="token" value="<?=e(request()->query()['token']??request()->post()['token']??'')?>">
        <?php endif?>

        <button class="button" type="submit">
          <?=$path==='/admin/login'?'Sign in to your workspace →':($path==='/admin/forgot-password'?'Send reset link':'Update password')?>
        </button>
      </form>

      <div class="auth-links">
        <?php if($path==='/admin/login'&&cms_config('MAIL_ENABLED')==='true'):?>
          <a href="<?=e(cms_url('/admin/forgot-password'))?>">Forgot password?</a>
        <?php elseif($path!=='/admin/login'):?>
          <a href="<?=e(cms_url('/admin/login'))?>">Back to sign in</a>
        <?php endif?>
        <a href="<?=e(cms_url('/'))?>">← Back to website</a>
      </div>
    </div>

    <p class="legal">© <?=date('Y')?> Global Trade Lines. All rights reserved.</p>
  </main>

<script src="<?=e(cms_url('/assets/js/auth.js?v=20261017'))?>" defer></script>
</body>
</html>
