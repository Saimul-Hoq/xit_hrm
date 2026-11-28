<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Csrf;
use App\Core\Session;
use App\Entities\Admin;
use App\Enums\Role;
use App\Repositories\AdminRepository;

/**
 * Authentication: checking credentials, starting and ending logged-in sessions,
 * and recognising the logged-in user on later requests.
 *
 * - The session stores ONLY role + id. The user is reloaded from the database on every
 *   request (once per request: the result is cached), so deleted accounts are logged out at once.
 * - Unknown account and wrong password fail the same way and take about the same time.
 *
 * Employee login and currentEmployee() are added in Step 21.
 */
final class AuthService
{
    private const SESSION_ROLE_KEY = 'auth_role';
    private const SESSION_ID_KEY   = 'auth_id';

    /**
     * bcrypt hash (cost 12, PHP's current default) of a random secret that nobody knows.
     * Verified against when the account does not exist, so "unknown email" takes as long
     * as "wrong password" and response timing reveals nothing.
     */
    private const DUMMY_HASH = '$2y$12$cjqINNJJaY7Ij3MSZIvjF.GfyapmMA2pg2N4CUJGfHHFIFx65VpIm';

    private ?Admin $currentAdmin = null;
    private bool $adminResolved  = false;

    public function __construct(
        private readonly AdminRepository $admins,
        private readonly Session $session,
        private readonly Csrf $csrf,
    ) {
    }

    /**
     * True on success (the session is now logged in as this admin), false otherwise.
     * The caller shows ONE generic message for every failure.
     */
    public function attemptAdminLogin(string $email, string $password): bool
    {
        // ┌──────────────────────────── LOGIN LOCKOUT (deferred) ───────────────────────────┐
        // │ When failed-login lockout is added: check the attempt counter for this          │
        // │ email / client HERE, before touching the database, and record each failure      │
        // │ where this method returns false. Nothing outside this method has to change.     │
        // └─────────────────────────────────────────────────────────────────────────────────┘

        $credentials = $this->admins->findCredentialsByEmail($email);

        if ($credentials === null) {
            password_verify($password, self::DUMMY_HASH); // same work as a real check
            return false;
        }

        if (!password_verify($password, $credentials['passwordHash'])) {
            return false;
        }

        $admin = $credentials['admin'];

        // The plain password is only ever available here: upgrade an outdated hash now.
        if (password_needs_rehash($credentials['passwordHash'], PASSWORD_DEFAULT)) {
            $this->admins->updatePassword($admin->getId(), password_hash($password, PASSWORD_DEFAULT));
        }

        $this->beginAuthenticatedSession(Role::Admin, $admin->getId());

        $this->currentAdmin  = $admin;
        $this->adminResolved = true;

        return true;
    }

    /**
     * The role stored in the session, or null when nobody (validly) is logged in.
     * Does NOT check the database: use currentAdmin() for that.
     */
    public function currentRole(): ?Role
    {
        $role = $this->session->get(self::SESSION_ROLE_KEY);
        $id   = $this->session->get(self::SESSION_ID_KEY);

        if (!is_string($role) || !is_int($id) || $id <= 0) {
            return null;
        }

        return Role::tryFrom($role);
    }

    /**
     * The logged-in admin, freshly loaded from the database (once per request), or null.
     * If the admin no longer exists, the session is logged out.
     */
    public function currentAdmin(): ?Admin
    {
        if ($this->adminResolved) {
            return $this->currentAdmin;
        }

        $this->adminResolved = true;

        if ($this->currentRole() !== Role::Admin) {
            return $this->currentAdmin = null;
        }

        $admin = $this->admins->findById((int) $this->session->get(self::SESSION_ID_KEY));

        if ($admin === null) {
            $this->logout(); // account deleted since login
            return null;
        }

        return $this->currentAdmin = $admin;
    }

    /**
     * Full logout, then a fresh empty session (so a flash message can still be shown).
     */
    public function logout(): void
    {
        $this->session->destroy();
        $this->session->start();

        $this->currentAdmin  = null;
        $this->adminResolved = true;
    }

    private function beginAuthenticatedSession(Role $role, int $id): void
    {
        $this->session->regenerate(); // new session ID: defeats session fixation
        $this->csrf->rotate();        // new CSRF token for the new privilege level
        $this->session->set(self::SESSION_ROLE_KEY, $role->value);
        $this->session->set(self::SESSION_ID_KEY, $id);
    }
}