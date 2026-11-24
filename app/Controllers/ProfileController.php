<?php

namespace App\Controllers;

use App\Libraries\Notifier;
use App\Models\UserModel;

/**
 * Mi perfil (documento maestro 6.6, 10 y 11.1): datos, foto, contraseña, accesibilidad y avisos de la propia persona.
 * Solo requiere sesión (filtro auth): se abre con o sin institución activa y desde la consola de plataforma.
 */
class ProfileController extends BaseController
{
    public const TEXT_SIZES = ['normal' => 'Normal', 'large' => 'Grande', 'xlarge' => 'Muy grande'];

    private const AVATAR_DIR = WRITEPATH . 'uploads/avatars/';

    private function uid(): int
    {
        return (int) service('auth')->id();
    }

    private function ctx(array $data = []): array
    {
        // En la consola, el perfil conserva el menú de la consola.
        return $data + (service('auth')->isSuperadmin() && ! service('tenant')->institution() ? $this->platformLayout() : $this->layoutData());
    }

    /** Preferencias de aviso por tema, con los valores por defecto donde no hay fila. */
    private function prefs(): array
    {
        $rows = array_column(db_connect()->table('notification_prefs')->where('user_id', $this->uid())->get()->getResultArray(), null, 'topic');
        $out  = [];
        foreach (Notifier::TOPICS as $k => $t) {
            $out[$k] = ['label' => $t['label'], 'hint' => $t['hint'],
                'in_app' => (bool) ($rows[$k]['in_app'] ?? true), 'email' => (bool) ($rows[$k]['email'] ?? $t['email'])];
        }

        return $out;
    }

    public function index()
    {
        $email = config('Email');

        $mfa  = service('mfa');
        $user = service('auth')->user();

        return view('profile/edit', $this->ctx([
            'mfa' => [
                'enabled'  => $mfa->enabled($user), 'required' => $mfa->required($user), 'since' => $user['totp_enabled_at'] ?? null,
                'left'     => $mfa->enabled($user) ? $mfa->remainingCodes((int) $user['id']) : 0,
                'pending'  => session('mfa_pending') ? ['secret' => session('mfa_pending'), 'uri' => \App\Libraries\Mfa::uri($user['email'], session('mfa_pending'))] : null,
                'codes'    => session('mfaCodes'),
            ],
            'title' => 'Mi perfil', 'prefs' => $this->prefs(), 'sizes' => self::TEXT_SIZES,
            'mailReady' => $email->fromEmail !== '' && ($email->protocol !== 'smtp' || $email->SMTPHost !== ''),
            'memberships' => db_connect()->table('institution_users m')->select('i.name AS institution, r.name AS role')
                ->join('institutions i', 'i.id = m.institution_id')->join('roles r', 'r.id = m.role_id', 'left')
                ->where('m.user_id', $this->uid())->where('m.status', 'active')->where('i.deleted_at', null)->orderBy('i.name')->get()->getResultArray(),
        ]));
    }

    /** Datos, accesibilidad y avisos (un solo formulario). */
    public function update()
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) $this->request->getPost('name')));
        if ($name === '' || mb_strlen($name) > 120) {
            return redirect()->back()->withInput()->with('errors', ['name' => 'Escribe tu nombre (hasta 120 caracteres).']);
        }
        $size = (string) $this->request->getPost('text_size');
        model(UserModel::class)->update($this->uid(), [
            'name'          => $name,
            'text_size'     => isset(self::TEXT_SIZES[$size]) ? $size : 'normal',
            'reduce_motion' => $this->request->getPost('reduce_motion') ? 1 : 0,
            'tutor_tts'     => $this->request->getPost('tutor_tts') ? 1 : 0,
        ]);

        $db  = db_connect();
        $now = date('Y-m-d H:i:s');
        $in  = (array) $this->request->getPost('in_app');
        $em  = (array) $this->request->getPost('email');
        foreach (array_keys(Notifier::TOPICS) as $k) {
            $row = ['in_app' => isset($in[$k]) ? 1 : 0, 'email' => isset($em[$k]) ? 1 : 0, 'updated_at' => $now];
            $q   = $db->table('notification_prefs')->where(['user_id' => $this->uid(), 'topic' => $k]);
            $q->countAllResults(false) ? $q->update($row) : $db->table('notification_prefs')->insert($row + ['user_id' => $this->uid(), 'topic' => $k]);
        }

        return redirect()->to('perfil')->with('toast', ['type' => 'ok', 'msg' => 'Perfil guardado.']);
    }

    public function password()
    {
        $user    = service('auth')->user();
        $current = (string) $this->request->getPost('current');
        $new     = (string) $this->request->getPost('new');
        $errors  = [];
        if (! password_verify($current, $user['password_hash'])) {
            $errors['current'] = 'La contraseña actual no es correcta.';
        }
        if (mb_strlen($new) < 10) {
            $errors['new'] = 'La nueva contraseña debe tener al menos 10 caracteres.';
        } elseif ($new === $current) {
            $errors['new'] = 'La nueva contraseña debe ser distinta de la actual.';
        } elseif ($new !== (string) $this->request->getPost('confirm')) {
            $errors['confirm'] = 'La confirmación no coincide con la nueva contraseña.';
        }
        if ($errors) {
            return redirect()->to('perfil#seguridad')->with('errors', $errors)->with('toast', ['type' => 'error', 'msg' => 'No se cambió la contraseña.']);
        }

        model(UserModel::class)->update($this->uid(), ['password_hash' => password_hash($new, PASSWORD_DEFAULT), 'password_changed_at' => date('Y-m-d H:i:s'), 'must_change_password' => 0]);
        session()->regenerate(true);
        service('audit')->log('profile.password', [], null);

        return redirect()->to('perfil')->with('toast', ['type' => 'ok', 'msg' => 'Contraseña actualizada.']);
    }

    /* ---------- Verificación en dos pasos ---------- */

    /** Paso 1: genera un secreto pendiente (en la sesión) y muestra el QR. */
    public function mfaStart()
    {
        if (service('mfa')->enabled(service('auth')->user())) {
            return redirect()->to('perfil#dos-pasos');
        }
        session()->set('mfa_pending', \App\Libraries\Mfa::newSecret());

        return redirect()->to('perfil#dos-pasos');
    }

    /** Paso 2: confirma con un código de la aplicación y muestra los códigos de respaldo (una sola vez). */
    public function mfaEnable()
    {
        $secret = (string) session('mfa_pending');
        if ($secret === '') {
            return redirect()->to('perfil#dos-pasos')->with('toast', ['type' => 'error', 'msg' => 'Vuelve a empezar la activación.']);
        }
        $codes = service('mfa')->enable($this->uid(), $secret, (string) $this->request->getPost('code'));
        if (! $codes) {
            return redirect()->to('perfil#dos-pasos')->with('toast', ['type' => 'error', 'msg' => 'El código no coincide. Revisa que la hora del celular sea automática y escribe el código actual.']);
        }
        session()->remove('mfa_pending');
        service('audit')->log('mfa.enable', [], null);

        return redirect()->to('perfil#dos-pasos')->with('mfaCodes', $codes)->with('toast', ['type' => 'ok', 'msg' => 'Verificación en dos pasos activada.']);
    }

    public function mfaCancel()
    {
        session()->remove('mfa_pending');

        return redirect()->to('perfil#dos-pasos');
    }

    /** Exige la contraseña actual y un código (de la aplicación o de respaldo) para acciones sensibles. */
    private function confirmIdentity(): ?string
    {
        $user = service('auth')->user();
        if (! password_verify((string) $this->request->getPost('password'), $user['password_hash'])) {
            return 'La contraseña no es correcta.';
        }

        return service('mfa')->verify($user, (string) $this->request->getPost('code')) ? null : 'El código no es correcto o ya se usó.';
    }

    public function mfaCodes()
    {
        if ($e = $this->confirmIdentity()) {
            return redirect()->to('perfil#dos-pasos')->with('toast', ['type' => 'error', 'msg' => $e]);
        }
        $codes = service('mfa')->newRecoveryCodes($this->uid());
        service('audit')->log('mfa.codes', [], null);

        return redirect()->to('perfil#dos-pasos')->with('mfaCodes', $codes)->with('toast', ['type' => 'ok', 'msg' => 'Nuevos códigos de respaldo generados. Los anteriores ya no sirven.']);
    }

    public function mfaDisable()
    {
        if (service('mfa')->required(service('auth')->user())) {
            return redirect()->to('perfil#dos-pasos')->with('toast', ['type' => 'error', 'msg' => 'Por tu rol, la verificación en dos pasos es obligatoria.']);
        }
        if ($e = $this->confirmIdentity()) {
            return redirect()->to('perfil#dos-pasos')->with('toast', ['type' => 'error', 'msg' => $e]);
        }
        service('mfa')->disable($this->uid());
        service('audit')->log('mfa.disable', [], null);

        return redirect()->to('perfil#dos-pasos')->with('toast', ['type' => 'ok', 'msg' => 'Verificación en dos pasos desactivada.']);
    }

    /** Sube la foto: JPG, PNG o WebP de hasta 2 MB, recortada al centro a 256 × 256 y guardada como JPG. */
    public function uploadPhoto()
    {
        $file = $this->request->getFile('photo');
        $ok   = $file && $file->isValid() && ! $file->hasMoved()
            && in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true) && $file->getSize() <= 2 * 1024 * 1024
            && @getimagesize($file->getTempName()) !== false;
        if (! $ok) {
            return redirect()->to('perfil')->with('toast', ['type' => 'error', 'msg' => 'Elige una imagen JPG, PNG o WebP de hasta 2 MB.']);
        }
        if (! is_dir(self::AVATAR_DIR)) {
            mkdir(self::AVATAR_DIR, 0775, true);
        }
        $name = $this->uid() . '-' . bin2hex(random_bytes(6)) . '.jpg';
        try {
            \Config\Services::image(null, null, false)->withFile($file->getTempName())->fit(256, 256, 'center')->convert(IMAGETYPE_JPEG)->save(self::AVATAR_DIR . $name, 85);
        } catch (\Throwable $e) {
            log_message('error', 'Foto de perfil: ' . $e->getMessage());

            return redirect()->to('perfil')->with('toast', ['type' => 'error', 'msg' => 'No se pudo procesar la imagen. Prueba con otra.']);
        }
        $this->removePhotoFile();
        model(UserModel::class)->update($this->uid(), ['avatar_path' => $name]);

        return redirect()->to('perfil')->with('toast', ['type' => 'ok', 'msg' => 'Foto actualizada.']);
    }

    public function deletePhoto()
    {
        $this->removePhotoFile();
        model(UserModel::class)->update($this->uid(), ['avatar_path' => null]);

        return redirect()->to('perfil')->with('toast', ['type' => 'ok', 'msg' => 'Quitaste tu foto.']);
    }

    private function removePhotoFile(): void
    {
        $old = (string) (service('auth')->user()['avatar_path'] ?? '');
        if ($old !== '' && basename($old) === $old && is_file(self::AVATAR_DIR . $old)) {
            @unlink(self::AVATAR_DIR . $old);
        }
    }

    /** La foto de la propia persona (por ahora solo se muestra en su barra superior y en su perfil). */
    public function photo()
    {
        $name = (string) (service('auth')->user()['avatar_path'] ?? '');
        $path = self::AVATAR_DIR . $name;
        if ($name === '' || basename($name) !== $name || ! is_file($path)) {
            return $this->response->setStatusCode(404);
        }

        return $this->response->setHeader('Content-Type', 'image/jpeg')->setHeader('Cache-Control', 'private, max-age=86400')
            ->setHeader('X-Content-Type-Options', 'nosniff')->setBody((string) file_get_contents($path));
    }
}
