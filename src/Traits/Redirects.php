<?php

declare(strict_types=1);

namespace App\Traits;

use App\Core\Url;

/**
 * Safe redirects for classes that answer HTTP requests (controllers, ErrorHandler).
 *
 * - Internal paths only: Url::to() rejects external URLs, "//host" and line breaks,
 *   so there is no open redirect and no header injection.
 * - 303 See Other: after a POST the browser follows with a GET (Post/Redirect/Get).
 *
 * The using class provides url(). Call `return;` right after redirect(): it does not exit.
 */
trait Redirects
{
    abstract protected function url(): Url;

    /**
     * @param array<string, scalar|list<scalar>> $query
     */
    protected function redirect(string $path, array $query = []): void
    {
        header('Location: ' . $this->url()->to($path, $query), true, 303);
    }
}