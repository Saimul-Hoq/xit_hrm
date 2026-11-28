<?php

declare(strict_types=1);

/**
 * @var App\Core\View $view
 * @var string|null   $detail  Exception details: ONLY passed in debug mode
 */
?>
<div class="auth-card text-center">
    <p class="display-4 fw-bold text-secondary mb-2">500</p>
    <h1 class="h4 mb-3">Something went wrong</h1>
    <p class="text-muted mb-4">The problem has been logged. Please try again in a moment.</p>
    <a class="btn btn-primary" href="<?= $view->url('/') ?>">Go to the start page</a>
</div>

<?php if ($detail !== null): ?>
    <pre class="small bg-white border rounded p-3 mt-4 overflow-auto"><?= $view->e($detail) ?></pre>
<?php endif; ?>