# Admin design update — 17 October 2026

The supplied design now powers administrator sign-in, forgotten-password and new-password screens. It retains the animated mesh, colour orbs, translucent card, serif heading and password visibility control. The existing company logo appears above the form and in the administration sidebar, including dark mode.

The dashboard, content editors, permissions and authentication backend continue to use the existing Laravel CMS. This update contains no database changes.

## Update an existing cPanel installation

1. Back up the files listed below before replacing them.
2. Upload `global-trade-lines-admin-design-update.zip` into the Laravel application directory containing `artisan`, then extract it there. The ZIP paths start at that directory; there is no extra enclosing folder.
3. If your domain serves the application's `public/` directory directly, the assets are already in their correct location. If you copied public files into `public_html` or a subdomain's document root, also copy these files from the extracted application's `public/` directory into the corresponding locations in that document root:

   ```text
   assets/css/admin.css
   assets/css/auth.css
   assets/js/auth.js
   assets/img/global-trade-line-logo.png
   assets/img/global-trade-lines-favicon-48.png
   ```

   Copy the assets only. Keep the hosting-specific `index.php` and `.htaccess` currently in your document root.

4. In cPanel Terminal, enter the Laravel application directory and run:

   ```bash
   php artisan view:clear
   php artisan view:cache
   ```

   Use your host's PHP 8.3+ executable if the default `php` is older. For an EasyApache PHP 8.4 installation, the path is commonly `/opt/cpanel/ea-php84/root/usr/bin/php`.

5. Refresh `/admin/login` in a private browser window. Check the logo, password Show/Hide button and administrator login. Password reset links remain available according to the existing email configuration.

Keep your current `.env`, administrator accounts and uploaded media. No installer, database migration or new application key is needed for this design update. The update ZIP does not contain environment credentials, database content, public entry points or web-server configuration.

## Changed application files

```text
resources/views/admin/login.blade.php
resources/views/admin/layout.blade.php
public/assets/css/auth.css
public/assets/css/admin.css
public/assets/js/auth.js
```

The existing company logo and favicon are included in the update ZIP for installations missing those assets. Authentication styling and scripts are separate from the authenticated dashboard stylesheet. Asset version parameters refresh the changed styles in browsers.

The complete `global-trade-lines-laravel13.zip` release also includes this design for fresh installations.
