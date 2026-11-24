<?php

namespace App\Libraries;

use App\Models\InstitutionModel;
use App\Models\NetworkModel;

/**
 * Redes educativas (grupo de colegios). Úsela con service('network').
 *
 * La institución sigue siendo el tenant: la red no mezcla datos. Lo que da:
 * - Administradores de red (admin): consola de la red y entrada a cualquiera de sus colegios con todos los permisos;
 *   cada entrada queda en la auditoría. Analistas (analyst): solo la consola, con reportes agregados.
 * - Reporte consolidado por colegio, solo con cifras agregadas (sin nombres de estudiantes).
 * - Bancos de preguntas que un colegio comparte con la red (los demás los copian a su institución).
 * - Cupo común de IA opcional (networks.monthly_tokens), que Ai\Quota aplica como un nivel más.
 */
class Network
{
    private function db()
    {
        return db_connect();
    }

    private function uid(): int
    {
        return (int) service('auth')->id();
    }

    /** Redes a las que la persona puede entrar, con su rol (el superadministrador: todas, como admin). */
    public function forUser(?int $userId = null): array
    {
        $userId ??= $this->uid();
        $q = $this->db()->table('networks n')->select('n.*, (SELECT COUNT(*) FROM institutions i WHERE i.network_id = n.id AND i.deleted_at IS NULL) AS schools', false)
            ->where('n.deleted_at', null)->where('n.status', 'active');
        if (service('auth')->isSuperadmin() && $userId === $this->uid()) {
            return array_map(static fn ($n) => $n + ['role' => 'admin'], $q->orderBy('n.name')->get()->getResultArray());
        }

        return $q->select('nu.role')->join('network_users nu', 'nu.network_id = n.id')
            ->where('nu.user_id', $userId)->where('nu.status', 'active')->orderBy('n.name')->get()->getResultArray();
    }

    public function roleIn(int $networkId, ?int $userId = null): ?string
    {
        foreach ($this->forUser($userId) as $n) {
            if ((int) $n['id'] === $networkId) {
                return $n['role'];
            }
        }

        return null;
    }

    /** ¿Administra la red de esa institución? (no cuenta al superadministrador, que tiene su propia regla) */
    public function adminOfInstitution(int $institutionId, ?int $userId = null): ?array
    {
        $userId ??= $this->uid();

        return $this->db()->table('institutions i')->select('n.id, n.name')->join('networks n', 'n.id = i.network_id')
            ->join('network_users nu', "nu.network_id = n.id AND nu.role = 'admin' AND nu.status = 'active'")
            ->where('i.id', $institutionId)->where('nu.user_id', $userId)->where('n.status', 'active')->where('n.deleted_at', null)
            ->get()->getRowArray();
    }

    public function get(int $id): ?array
    {
        return model(NetworkModel::class)->find($id);
    }

    /* ---------- Red activa (consola de la red) ---------- */

    /** Red activa de la consola, comprobada contra el acceso de la persona. */
    public function active(): ?array
    {
        $id = (int) session('network_id');
        if (! $id) {
            return null;
        }
        $role = $this->roleIn($id);
        if (! $role) {
            session()->remove('network_id');

            return null;
        }

        return $this->get($id) + ['role' => $role];
    }

    public function enter(int $networkId): bool
    {
        if (! $this->roleIn($networkId)) {
            return false;
        }
        session()->set('network_id', $networkId);

        return true;
    }

    /** Colegios de la red. */
    public function institutions(int $networkId): array
    {
        return model(InstitutionModel::class)->where('network_id', $networkId)->orderBy('name')->findAll();
    }

    /* ---------- Reporte consolidado (solo cifras agregadas) ---------- */

    public function report(int $networkId): array
    {
        $rows  = [];
        $quota = service('aiQuota');
        foreach ($this->institutions($networkId) as $i) {
            $iid    = (int) $i['id'];
            $people = [];
            foreach ($this->db()->table('institution_users hu')->select('r.template_key, COUNT(*) AS n', false)->join('roles r', 'r.id = hu.role_id')
                ->where('hu.institution_id', $iid)->where('hu.status', 'active')->groupBy('r.template_key')->get()->getResultArray() as $p) {
                $people[$p['template_key']] = (int) $p['n'];
            }
            $students = $people['student'] ?? 0;
            $acts     = (int) $this->db()->table('activities')->where('institution_id', $iid)->where('status', 'published')->where('deleted_at', null)->countAllResults();

            // Estudiantes con al menos una entrega de reto o un intento terminado.
            $active = (int) ($this->db()->query(
                "SELECT COUNT(DISTINCT x.user_id) AS n FROM (
                    SELECT user_id FROM submissions WHERE institution_id = ? AND status IN ('submitted','reviewed','returned')
                    UNION SELECT user_id FROM attempts WHERE institution_id = ? AND status <> 'in_progress') x",
                [$iid, $iid]
            )->getRow()->n ?? 0);

            $s = $this->db()->table('submissions')->select('SUM(grade) AS s, COUNT(grade) AS c', false)->where('institution_id', $iid)->where('status', 'reviewed')->get()->getRow();
            $a = $this->db()->table('attempts')->select('SUM(grade) AS s, COUNT(grade) AS c', false)->where('institution_id', $iid)->where('status', 'graded')->get()->getRow();
            $n = (int) $s->c + (int) $a->c;

            $rows[] = [
                'id' => $iid, 'name' => $i['name'], 'city' => $i['city'], 'plan' => $i['plan'], 'status' => $i['status'], 'accent_color' => $i['accent_color'],
                'students' => $students, 'teachers' => ($people['teacher'] ?? 0) + ($people['coordinator'] ?? 0), 'activities' => $acts,
                'participation' => $students ? (int) round(min($active, $students) * 100 / $students) : null,
                'avg' => $n ? round(((float) $s->s + (float) $a->s) / $n, 1) : null, 'graded' => $n,
                'ai_month' => $quota->used($iid, 'month'),
            ];
        }
        $net      = $this->get($networkId);
        $students = array_sum(array_column($rows, 'students'));
        $graded   = array_sum(array_column($rows, 'graded'));

        return [
            'schools' => $rows,
            'totals'  => [
                'schools'    => count($rows),
                'students'   => $students,
                'teachers'   => array_sum(array_column($rows, 'teachers')),
                'activities' => array_sum(array_column($rows, 'activities')),
                'avg'        => $graded ? round(array_sum(array_map(static fn ($r) => ($r['avg'] ?? 0) * $r['graded'], $rows)) / $graded, 1) : null,
                'ai_month'   => array_sum(array_column($rows, 'ai_month')),
                'ai_cap'     => (int) ($net['monthly_tokens'] ?? 0),
            ],
        ];
    }

    /** Tokens de IA del mes en curso de toda la red (cada colegio en su zona horaria). */
    public function aiUsedThisMonth(int $networkId): int
    {
        $quota = service('aiQuota');

        return array_sum(array_map(static fn ($i) => $quota->used((int) $i['id'], 'month'), $this->institutions($networkId)));
    }

    /* ---------- Bancos compartidos ---------- */

    /** Bancos que los demás colegios de la red de $institutionId comparten (o todos los de la red si $institutionId es null). */
    public function sharedBanks(int $networkId, ?int $exceptInstitution = null): array
    {
        $q = $this->db()->table('question_banks b')
            ->select('b.id, b.name, b.description, b.institution_id, i.name AS school, s.name AS subject, u.name AS author,
                (SELECT COUNT(*) FROM bank_questions q WHERE q.bank_id = b.id) AS questions', false)
            ->join('institutions i', 'i.id = b.institution_id')->join('subjects s', 's.id = b.subject_id', 'left')->join('users u', 'u.id = b.created_by', 'left')
            ->where('i.network_id', $networkId)->where('b.network_shared', 1)->where('b.deleted_at', null);
        if ($exceptInstitution) {
            $q->where('b.institution_id !=', $exceptInstitution);
        }

        return $q->orderBy('s.name')->orderBy('b.name')->get()->getResultArray();
    }

    /* ---------- Administradores de la red ---------- */

    public function members(int $networkId): array
    {
        return $this->db()->table('network_users nu')->select('nu.id, nu.role, nu.created_at, u.name, u.email')->join('users u', 'u.id = nu.user_id')
            ->where('nu.network_id', $networkId)->where('nu.status', 'active')->orderBy('u.name')->get()->getResultArray();
    }

    /** Agrega una persona con cuenta existente. Devuelve null o el mensaje de error. */
    public function addMember(int $networkId, string $email, string $role): ?string
    {
        if (! in_array($role, ['admin', 'analyst'], true)) {
            return 'Elija un rol válido.';
        }
        $user = $this->db()->table('users')->where('email', mb_strtolower(trim($email)))->where('status', 'active')->get()->getRowArray();
        if (! $user) {
            return 'No hay una cuenta activa con ese correo. Pida al superadministrador que la cree.';
        }
        $exists = $this->db()->table('network_users')->where(['network_id' => $networkId, 'user_id' => $user['id']])->get()->getRowArray();
        $now    = date('Y-m-d H:i:s');
        if ($exists) {
            $this->db()->table('network_users')->where('id', $exists['id'])->update(['role' => $role, 'status' => 'active', 'updated_at' => $now]);
        } else {
            $this->db()->table('network_users')->insert(['network_id' => $networkId, 'user_id' => $user['id'], 'role' => $role, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        }
        service('audit')->log('network.member.add', ['red' => $this->get($networkId)['name'] ?? '', 'persona' => $user['name'], 'rol' => $role]);

        return null;
    }

    public function removeMember(int $networkId, int $memberId): ?string
    {
        $m = $this->db()->table('network_users nu')->select('nu.*, u.name')->join('users u', 'u.id = nu.user_id')
            ->where('nu.network_id', $networkId)->where('nu.id', $memberId)->get()->getRowArray();
        if (! $m) {
            return 'Esa persona no está en la red.';
        }
        if ((int) $m['user_id'] === $this->uid()) {
            return 'No puede quitarse a sí mismo.';
        }
        $this->db()->table('network_users')->where('id', $memberId)->delete();
        service('audit')->log('network.member.remove', ['red' => $this->get($networkId)['name'] ?? '', 'persona' => $m['name']]);

        return null;
    }
}
