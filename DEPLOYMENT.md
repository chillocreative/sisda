# SISDA - Deployment Guide for cPanel

## Pre-Deployment Checklist

### ✅ Files Ready
- [x] `.cpanel.yml` - Automated deployment configuration
- [x] `.env.example` - Production environment template
- [x] `.gitignore` - Excludes unnecessary files (*.zip, node_modules, etc.)
- [x] `composer.json` - PHP dependencies
- [x] `package.json` - Node.js dependencies
- [x] Database migrations - 25 migration files ready
- [x] Public assets - Built and ready in `public/build`

## Deployment Steps

### 1. **Prepare cPanel Database**
Before deploying, create a MySQL database in cPanel:
- Database Name: `chilloc1_sisda` (or your preferred name)
- Database User: Create a user with full privileges
- Note down: DB_NAME, DB_USER, DB_PASSWORD

### 2. **Configure Git Deployment in cPanel**
1. Log into cPanel
2. Go to **Git™ Version Control**
3. Click **Create**
4. Repository URL: `https://github.com/chillocreative/sisda.git`
5. Repository Path: `/home/chilloc1/sistemdatapengundi.com`
6. Branch: `main`
7. Click **Create**

### 3. **Update Environment Variables**
After first deployment, edit `.env` file in cPanel File Manager:
```env
APP_NAME=SISDA
APP_ENV=production
APP_KEY=base64:... (will be generated automatically)
APP_DEBUG=false
APP_URL=https://sistemdatapengundi.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=chilloc1_sisda
DB_USERNAME=chilloc1_sisda_user
DB_PASSWORD=your_secure_password

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

### 4. **Set Document Root**
In cPanel, set your domain's document root to:
```
/home/chilloc1/sistemdatapengundi.com/public
```

### 5. **Deploy from Git**
1. Go to **Git™ Version Control**
2. Click **Manage** on your repository
3. Click **Pull or Deploy** tab
4. Click **Deploy HEAD Commit**

The `.cpanel.yml` will automatically:
- Copy files to deployment path
- Install Composer dependencies
- Generate application key
- Create storage symlink
- Run database migrations
- Cache configurations
- Install and build frontend assets
- Set proper permissions

### 6. **Verify Deployment**
Visit: https://sistemdatapengundi.com

You should see the SISDA welcome page with login/register buttons.

## Post-Deployment

### Create Super Admin User
SSH into your server or use cPanel Terminal:
```bash
cd /home/chilloc1/sistemdatapengundi.com
php artisan tinker
```

Then run:
```php
$user = new App\Models\User();
$user->name = 'Super Admin';
$user->email = 'admin@sistemdatapengundi.com';
$user->password = bcrypt('your_secure_password');
$user->role = 'super_admin';
$user->is_approved = true;
$user->email_verified_at = now();
$user->save();
```

### Set Up Cron Jobs (Optional)
For queue workers and scheduled tasks:
```
* * * * * cd /home/chilloc1/sistemdatapengundi.com && php artisan schedule:run >> /dev/null 2>&1
```

## Troubleshooting

### Issue: 500 Internal Server Error
**Solution:**
```bash
chmod -R 755 storage bootstrap/cache
php artisan config:clear
php artisan cache:clear
```

### Issue: Database Connection Error
**Solution:**
- Verify database credentials in `.env`
- Ensure database user has proper privileges
- Check if database exists

### Issue: Missing Assets
**Solution:**
```bash
npm install
npm run build
```

### Issue: Routes Not Working
**Solution:**
- Verify `.htaccess` exists in `public/` directory
- Ensure mod_rewrite is enabled in Apache
- Clear route cache: `php artisan route:clear`

## Updating the Application

To deploy updates:
1. Push changes to GitHub
2. In cPanel Git™ Version Control, click **Pull or Deploy**
3. Click **Deploy HEAD Commit**

The deployment script will automatically rebuild assets and clear caches.

## Security Notes

- ✅ `APP_DEBUG=false` in production
- ✅ Strong `APP_KEY` generated
- ✅ Database credentials secured
- ✅ `.env` file not in Git repository
- ✅ Proper file permissions set
- ✅ HTTPS enabled

## Support

For issues or questions, contact the development team.

---
**Last Updated:** December 3, 2025
**Version:** 1.0.0
