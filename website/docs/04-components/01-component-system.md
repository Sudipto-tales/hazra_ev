# Component System

Components are reusable PHP template fragments stored in `app/components/`. They are powered by the `App` class (`core/App.php`).

## Creating a Component

```php
// app/components/alert.php
<div class="alert alert-<?= $type ?? 'info'; ?>">
    <?= $message; ?>
</div>
```

## Using Components

### Direct Output

```php
App::render('alert', ['type' => 'success', 'message' => 'Saved!']);
```

### Capture as String

```php
$html = App::component('alert', ['type' => 'error', 'message' => 'Failed']);
```

### Check Existence

```php
if (App::exists('sidebar')) {
    App::render('sidebar');
}
```

### Subdirectories

Organize components in subdirectories:

```php
// app/components/ui/badge.php
<span class="badge"><?= $text; ?></span>

// Usage:
App::render('ui/badge', ['text' => 'New']);
```

## Built-in Components

| Component | Props | Purpose |
|-----------|-------|---------|
| `head` | `title`, `styles[]` | HTML `<head>`, opens `<body>` |
| `footer` | `scripts[]` | Scripts, closes `</body></html>` |
| `hero` | `heading`, `subheading` | Hero banner section |

## Full Page Example

```php
// app/page/welcome.php
<?php App::render('head', ['title' => 'Home']); ?>

<div class="container">
    <?php App::render('hero', ['heading' => 'Welcome to Vayu']); ?>

    <div class="cards">
        <?php foreach ($features as $f): ?>
            <?php App::render('card', ['title' => $f['name'], 'body' => $f['desc']]); ?>
        <?php endforeach; ?>
    </div>
</div>

<?php App::render('footer'); ?>
```

## How It Works

- Uses output buffering (`ob_start` / `ob_get_clean`) to capture component HTML
- Each component renders inside its own closure scope — variables don't leak between components
- In debug mode, missing components show a visible error instead of failing silently
- Data is passed via `extract()` — array keys become local variables in the component
