# Installation

## Step 1: Clone the Project

```bash
git clone <your-repo-url> vayu
cd vayu
```

## Step 2: Install Dependencies

```bash
composer install
```

## Step 3: Configure Environment

```bash
cp .env.example .env
```

Edit `.env` with your settings:

```env
APP_NAME=MyApp
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_TYPE=sqlite
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=my_database
DB_USERNAME=root
DB_PASSWORD=secret

JWT_SECRET=your-secret-key-change-in-production
JWT_TTL=3600

CORS_ORIGINS=*
```

## Step 4: Start the Dev Server

```bash
php -S localhost:8000
```

## Step 5: Open Your Browser

Visit `http://localhost:8000` — you should see the welcome page.

## Apache Setup (Optional)

The project includes an `.htaccess` file for Apache. Enable `mod_rewrite`:

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

Ensure your virtual host has `AllowOverride All` set for the project directory.
