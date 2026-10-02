# Quick Start

This guide walks through creating your first page, component, and API endpoint.

## Create a Page

### 1. Create a controller

```php
// app/controllers/About.php
<?php
class About extends BaseController
{
    public function index()
    {
        return $this->respond('/app/page/about.php', [
            'title' => 'About Us'
        ]);
    }
}
```

### 2. Create the view

```php
// app/page/about.php
<?php App::render('head', ['title' => $title]); ?>

<div class="container">
    <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
    <p>Your content here.</p>
</div>

<?php App::render('footer'); ?>
```

### 3. Register the route

```php
// app/view.php — add to the routes array
'about' => ['About', 'index'],
```

Visit `/about` in your browser.

## Create a Component

### 1. Create the component file

```php
// app/components/card.php
<div class="card">
    <h3><?= $title; ?></h3>
    <p><?= $body ?? ''; ?></p>
</div>
```

### 2. Use it in any page

```php
<?php App::render('card', ['title' => 'Hello', 'body' => 'World']); ?>
```

## Create an API Endpoint

### 1. Create an API controller

```php
// api/controllers/StatusController.php
<?php
class StatusController extends ApiController
{
    public function index()
    {
        $this->success(['version' => '1.0.4'], 'API is running');
    }
}
```

### 2. Register in the API gateway

```php
// api/gateway.php — add to the routes array
'GET:api/v1/status' => ['StatusController', 'index'],
```

Test with: `curl http://localhost:8000/api/v1/status`
