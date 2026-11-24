<?php

namespace App\Libraries;

use App\Models\UserModel;

/**
 * Autenticación por sesión. Úsela con service('auth').
 */
class Auth
{
    protected ?array $user = null;
    protected bool $loaded = false;

    public function user(): ?array
    {
        if (! $this->loaded) {
            $this->loaded = true;
            $id           = session('user_id');
            if ($id) {
                $user = model(UserModel::class)->find($id);
                if ($user && $user['status'] === 'active') {
                    $this->user = $user;
                } else {
                    session()->remove(['user_id', 'institution_id']);
                }
            }
        }

        return $this->user;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function id(): ?int
    {
        return $this->user() ? (int) $this->user['id'] : null;
    }

    public function isSuperadmin(): bool
    {
        return (bool) ($this->user()['is_superadmin'] ?? false);
    }

    /**
     * Comprueba la identidad (correo o número de documento) y la contraseña. Devuelve el usuario o null.
     * Siempre ejecuta password_verify para no revelar por tiempo si la cuenta existe.
     */
    public function attempt(string $identity, string $password): ?array
    {
        $users = model(UserModel::class);
        $user  = str_contains($identity, '@') ? $users->findByEmail($identity) : $users->findByDocument($identity);
        $hash = $user['password_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
        $ok   = password_verify($password, $hash);

        if (! $user || ! $ok || $user['status'] !== 'active') {
            return null;
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            model(UserModel::class)->update($user['id'], ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
        }

        return $user;
    }

    public function login(array $user): void
    {
        session()->regenerate(true);
        session()->set('user_id', (int) $user['id']);
        session()->remove('institution_id');
        $upd = ['last_login_at' => date('Y-m-d H:i:s')];
        // Idioma elegido en la pantalla de inicio de sesión (solo existe si la persona usó el selector): queda en su cuenta.
        if (Locale::valid(session('locale')) && session('locale') !== ($user['locale'] ?? null)) {
            $upd['locale']  = session('locale');
            $user['locale'] = session('locale');
        }
        model(UserModel::class)->update($user['id'], $upd);
        $this->user   = $user;
        $this->loaded = true;
    }

    public function logout(): void
    {
        session()->destroy();
        $this->user   = null;
        $this->loaded = true;
    }
}
