<?php

declare(strict_types=1);

/** @var App\Core\View $view */
?>
<div class="auth-card text-center">
    <p class="display-4 fw-bold text-secondary mb-2">405</p>
    <h1 class="h4 mb-3">Action not allowed this way</h1>
    <p class="text-muted mb-4">This address exists, but it cannot be opened like this. Please use the buttons in the application.</p>
    <a class="btn btn-primary" href="<?= $view->url('/') ?>">Go to the start page</a>
</div>