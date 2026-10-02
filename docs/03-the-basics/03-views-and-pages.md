# Views and Pages

Views are PHP template files that render HTML. They live in `app/page/`.

## Rendering a View

From a controller, use `$this->respond()`:

```php
class About extends BaseController
{
    public function index()
    {
        return $this->respond('/app/page/about.php', [
            'title' => 'About Us',
            'team'  => ['Alice', 'Bob'],
        ]);
    }
}
```

The data array keys become variables in the view (`$title`, `$team`).

## Writing a View

```php
// app/page/about.php
<?php App::render('head', ['title' => $title]); ?>

<div class="container">
    <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>

    <ul>
        <?php foreach ($team as $member): ?>
            <li><?= htmlspecialchars($member, ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endforeach; ?>
    </ul>
</div>

<?php App::render('footer'); ?>
```

## View Helpers

| Function | Purpose |
|----------|---------|
| `load_view($path, $data)` | Load a view file with extracted variables |
| `view_data($data, $extras)` | Merge data arrays |
| `render_view($path, $data)` | Alias for `load_view()` |
| `base_url($path)` | Generate a full URL from `APP_URL` |

## Using `base_url()`

```php
<a href="<?= base_url('about'); ?>">About</a>
<!-- Output: http://localhost:8000/about -->

<link rel="stylesheet" href="<?= base_url('assets/css/style.css'); ?>">
```

## Data Merging

Use `viewData()` to combine common and page-specific data:

```php
$common = ['appName' => env('APP_NAME')];
$page   = ['title' => 'Dashboard'];

$data = $this->viewData($common, $page);
// Result: ['appName' => 'MyApp', 'title' => 'Dashboard']
```
