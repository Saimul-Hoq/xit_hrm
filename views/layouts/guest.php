<?php

declare(strict_types=1);

/**
 * Layout for pages shown to visitors who are not logged in (landing, login pages).
 *
 * @var App\Core\View $view
 * @var string        $title
 * @var string        $content  Already-rendered HTML of the page (printed raw on purpose)
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
    <main class="container py-5">
        <?= $view->partial('flash') ?>
        <?= $content ?>
    </main>

    <script src="<?= $view->asset('js/bootstrap.bundle.min.js') ?>"></script>
</body>
</html>