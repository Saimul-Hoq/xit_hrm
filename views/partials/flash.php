<?php

declare(strict_types=1);

/**
 * Shows this page view's flash messages (each is shown once, then gone).
 * Closing works through Bootstrap's JS (data-bs-dismiss): no inline script needed.
 *
 * @var App\Core\View $view
 */

$alertClasses = [
    'success' => 'alert-success',
    'error'   => 'alert-danger',
    'warning' => 'alert-warning',
    'info'    => 'alert-info',
];
?>
<?php foreach ($view->flashes() as $flash): ?>
    <div class="alert <?= $alertClasses[$flash['type']] ?? 'alert-info' ?> alert-dismissible fade show" role="alert">
        <?= $view->e($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endforeach; ?>