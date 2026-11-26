<?php

declare(strict_types=1);

/**
 * The error message for one form field, shown under the input.
 * The input itself gets the "is-invalid" class in its own template.
 *
 * @var App\Core\View          $view
 * @var array<string, string>  $errors  Field name => message
 * @var string                 $field
 */
?>
<?php if (isset($errors[$field])): ?>
    <div class="invalid-feedback d-block"><?= $view->e($errors[$field]) ?></div>
<?php endif; ?>