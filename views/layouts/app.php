<?php

declare(strict_types=1);

/**
 * Layout for logged-in pages (admin and employee).
 * The controller chooses the navigation bar by passing 'navbar' => 'navbar-admin' or 'navbar-employee'
 * (those partials arrive in Steps 15 and 21; until then no navbar is shown).
 *
 * @var App\Core\View $view
 * @var string        $title
 * @var string        $content  Already-rendered HTML of the page (printed raw on purpose)
 * @var string|null   $navbar   Partial name, optional
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $view->e($title) ?> · <?= $view->e($view->appName()) ?></title>
    <link rel="stylesheet" href="<?= $view->asset('css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= $view->asset('css/app.css') ?>">
</head>
<body class="bg-light">
    <?php if (isset($navbar) && is_string($navbar)): ?>
        <?= $view->partial($navbar) ?>
    <?php endif; ?>

    <main class="container py-4">
        <?= $view->partial('flash') ?>
        <?= $content ?>
    </main>

    <script src="<?= $view->asset('js/bootstrap.bundle.min.js') ?>"></script>
</body>
</html>