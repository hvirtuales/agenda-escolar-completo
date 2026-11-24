<?php

namespace App\Libraries;

use App\Models\UserModel;

/**
 * Suplantación de soporte («Ver como»), en SOLO LECTURA. Úsela con service('impersonation').
 *
 * Reglas de seguridad:
 * - Solo el superadministrador, dentro de una institución, con su contraseña y un motivo. Nunca a sí mismo, a otro
 *   superadministrador, a una cuenta inactiva ni a quien no tenga membresía vigente en esa institución.
 * - La sesión guarda a la persona suplantada en user_id (así permisos y pantallas son exactamente los suyos) y a quien
 *   suplanta en 'impersonation'. La sesión se regenera al entrar y al salir. No se puede suplantar dentro de una suplantación.
 * - Dura MINUTES minutos y no se renueva. Termina con «Terminar», al vencer, al cerrar sesión o si la fila se cierra
 *   desde la base (revocación).
 * - Solo lectura (ImpersonationFilter): se rechaza todo lo que no sea GET/HEAD, y cada GET corre dentro de una
 *   transacción que se deshace al final, así ni los efectos colaterales de leer (marcar como leído…) se guardan.
 * - Queda en la tabla impersonations y en la auditoría (inicio y fin), y se avisa a los administradores de la institución.
 */
class Impersonation
{
    public const MINUTES = 30;

    /** Datos de la suplantación en curso o null. */
    public function current(): ?array
    {
        $s = session('impersonation');

        return is_array($s) ? $s : null;
    }

    public function active(): bool
    {
        return $this->current() !== null;
    }

    /** Id del superadministrador que está suplantando (o null). */
    public function impersonatorId(): ?int
    {
        return ($c = $this->current()) ? (int) $c['by'] : null;
    }

    /** Por qué no se puede suplantar a esta persona en la institución activa (null = sí se puede). */
    public function blocker(int $targetId): ?string
    {
        $auth = service('auth');
        $iid  = (int) service('tenant')->institutionId();
        if ($this->active()) {
            return 'Ya está viendo como otra persona.';
        }
        if (! $auth->isSuperadmin() || ! $iid) {
            return 'Solo el superadministrador, dentro de una institución.';
        }
        if ($targetId === (int) $auth->id()) {
            return 'No puede verse a sí mismo.';
        }
        $user = model(UserModel::class)->find($targetId);
        if (! $user || $user['status'] !== 'active') {
            return 'La cuenta no está activa.';
        }
        if ((int) $user['is_superadmin']) {
            return 'No se puede ver como otro superadministrador.';
        }
        $m = db_connect()->table('institution_users hu')->select('hu.id')
            ->join('roles r', 'r.id = hu.role_id AND r.institution_id = hu.institution_id')
            ->where(['hu.institution_id' => $iid, 'hu.user_id' => $targetId, 'hu.status' => 'active'])->where(Tenant::inForce(), null, false)
            ->get()->getRowArray();

        return $m ? null : 'No tiene un acceso vigente en esta institución.';
    }

    /** Inicia la suplantación. Quien llama ya validó contraseña y motivo. Devuelve un error o null. */
    public function start(int $targetId, string $reason): ?string
    {
        if ($e = $this->blocker($targetId)) {
            return $e;
        }
        $auth   = service('auth');
        $me     = $auth->user();
        $target = model(UserModel::class)->find($targetId);
        $inst   = service('tenant')->institution();
        $req    = service('request');
        $now    = time();

        $db = db_connect();
        $db->table('impersonations')->insert([
            'impersonator_id' => (int) $me['id'], 'target_id' => $targetId, 'institution_id' => (int) $inst['id'], 'reason' => mb_substr($reason, 0, 255),
            'ip_address' => $req->getIPAddress(), 'user_agent' => mb_substr((string) $req->getUserAgent(), 0, 255),
            'started_at' => date('Y-m-d H:i:s', $now), 'expires_at' => date('Y-m-d H:i:s', $now + self::MINUTES * 60),
        ]);
        $id = (int) $db->insertID();
        service('audit')->log('impersonation.start', ['persona' => $target['name'], 'correo' => $target['email'], 'motivo' => $reason], (int) $inst['id']);
        $this->notifyAdmins((int) $inst['id'], $me, $target, $reason, $targetId);

        session()->regenerate(true);
        session()->set('user_id', $targetId);
        session()->set('institution_id', (int) $inst['id']);
        session()->set('impersonation', [
            'id' => $id, 'by' => (int) $me['id'], 'by_name' => $me['name'], 'target_name' => $target['name'],
            'institution_id' => (int) $inst['id'], 'expires' => $now + self::MINUTES * 60,
        ]);

        return null;
    }

    /**
     * ¿Sigue siendo válida? Vencida, revocada en la base, o quien suplanta dejó de ser superadministrador activo → no.
     */
    public function valid(): bool
    {
        $c = $this->current();
        if (! $c) {
            return false;
        }
        if (time() >= (int) $c['expires']) {
            return false;
        }
        $row = db_connect()->table('impersonations')->select('ended_at')->where('id', (int) $c['id'])->get()->getRowArray();
        if (! $row || $row['ended_at'] !== null) {
            return false;
        }
        $by = model(UserModel::class)->find((int) $c['by']);

        return $by && $by['status'] === 'active' && (int) $by['is_superadmin'];
    }

    /** Termina y devuelve la sesión al superadministrador (o la cierra si ya no lo es). */
    public function end(string $why = 'manual'): void
    {
        $c = $this->current();
        if (! $c) {
            return;
        }
        $db = db_connect();
        $db->table('impersonations')->where('id', (int) $c['id'])->where('ended_at', null)
            ->update(['ended_at' => date('Y-m-d H:i:s'), 'end_reason' => $why]);

        $by = model(UserModel::class)->find((int) $c['by']);
        session()->remove('impersonation');
        if (! $by || $by['status'] !== 'active' || ! (int) $by['is_superadmin']) {
            service('auth')->logout();

            return;
        }
        session()->regenerate(true);
        session()->set('user_id', (int) $by['id']);
        session()->set('institution_id', (int) $c['institution_id']);
        // Auth y Tenant ya cargaron a la persona suplantada en esta petición: se usan instancias nuevas.
        \Config\Services::resetSingle('auth');
        \Config\Services::resetSingle('tenant');
        service('audit')->log('impersonation.end', ['persona' => $c['target_name'], 'cómo' => self::END_LABELS[$why] ?? $why], (int) $c['institution_id']);
    }

    public const END_LABELS = ['manual' => 'la terminó', 'expired' => 'venció', 'logout' => 'cerró sesión', 'revoked' => 'revocada'];

    /** Aviso a quienes administran usuarios en la institución (transparencia). */
    private function notifyAdmins(int $iid, array $me, array $target, string $reason, int $targetId): void
    {
        $admins = array_map('intval', array_column(db_connect()->table('institution_users hu')->select('hu.user_id')
            ->join('role_permissions rp', 'rp.role_id = hu.role_id')->join('users u', 'u.id = hu.user_id')
            ->where('hu.institution_id', $iid)->where('hu.status', 'active')->where('rp.permission', 'tenant.users.manage')
            ->where('u.is_superadmin', 0)->where('hu.user_id !=', $targetId)->get()->getResultArray(), 'user_id'));
        service('notify')->send($admins, 'support', 'Soporte vio la plataforma como ' . $target['name'],
            $me['name'] . ' (superadministrador) entró en modo solo lectura por ' . self::MINUTES . ' minutos. Motivo: ' . $reason, null, null, $iid);
    }
}
