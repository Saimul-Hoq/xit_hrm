<?php

declare(strict_types=1);

/** @var App\Core\View $view */
?>
<div class="auth-card text-center">
    <p class="display-4 fw-bold text-secondary mb-2">404</p>
    <h1 class="h4 mb-3">Page not found</h1>
    <p class="text-muted mb-4">The page you asked for does not exist, or the link is broken.</p>
    <a class="btn btn-primary" href="<?= $view->url('/') ?>">Go to the start page</a>
</div>