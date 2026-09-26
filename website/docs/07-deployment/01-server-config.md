# Deployment

## Production Environment

Update your `.env` for production:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

JWT_SECRET=a-long-random-string-generated-securely

CORS_ORIGINS=https://yourdomain.com,https://app.yourdomain.com
```

## Apache

### Virtual Host

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    DocumentRoot /var/www/vayu

    <Directory /var/www/vayu>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/vayu-error.log
    CustomLog ${APACHE_LOG_DIR}/vayu-access.log combined
</VirtualHost>
```

The project includes an `.htaccess` that handles URL rewriting to `index.php`.

### Required Modules

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

## Nginx

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/vayu;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(env|git|htaccess) {
        deny all;
    }
}
```

## Security Checklist

- [ ] Set `APP_DEBUG=false`
- [ ] Set `APP_ENV=production`
- [ ] Change `JWT_SECRET` to a strong random value
- [ ] Restrict `CORS_ORIGINS` to your actual domains
- [ ] Ensure `.env` is not web-accessible
- [ ] Ensure `vendor/`, `config/`, `core/` directories are not directly accessible
- [ ] Use HTTPS
- [ ] Set proper file permissions (`644` for files, `755` for directories)

## PHP-FPM Optimization

For deferred tasks (`Async::defer()`), PHP-FPM provides the best experience. It uses `fastcgi_finish_request()` to send the response to the client immediately, then runs deferred tasks in the background.

```ini
; /etc/php/8.1/fpm/pool.d/www.conf
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
```
