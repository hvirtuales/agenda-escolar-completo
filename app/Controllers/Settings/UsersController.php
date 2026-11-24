<?php

namespace App\Controllers\Settings;

use App\Controllers\BaseController;
use App\Libraries\Rbac;
use App\Models\CampusModel;
use App\Models\InstitutionUserModel;
use App\Models\UserModel;

/**
* Ajustes › Personas (documento maestro 2 y 4.1), igual que en las otras plataformas.
 *
 * Una cuenta (users) puede pertenecer a varias instituciones; aquí se gestiona su membresía en esta (rol, sede,
 * vigencia y estado). Reglas:
 *  - Anti-escalada: nadie asigna ni gestiona un rol con permisos que él no tiene (salvo el superadministrador).
 *  - La institución nunca se queda sin una persona activa que pueda gestionar personas y roles.
 *  - Un correo que ya existe en la plataforma no se vincula desde aquí (podría ser de otra institución).
 */
class UsersController extends BaseController
{
    private function roles(): array
    {
        $rbac  = service('rbac');
        $roles = $rbac->roles(service('tenant')->institutionId());
        foreach ($roles as &$r) {
            $r['grantable'] = $rbac->canGrantRole((int) $r['id']);
        }

        return $roles;
    }

    private function validRole(int $roleId): bool
    {
        return in_array($roleId, array_map('intval', array_column($this->roles(), 'id')), true);
    }

    private function validCampus(?int $campusId): bool
    {
        return $campusId === null || model(CampusModel::class)->find($campusId) !== null;
    }

    private function rules(): array
    {
        return [
            'role_id'   => ['label' => 'Rol', 'rules' => 'required|is_natural_no_zero'],
            'campus_id' => ['label' => 'Sede', 'rules' => 'permit_empty|is_natural_no_zero'],
            'starts_on' => ['label' => 'Desde', 'rules' => 'permit_empty|valid_date[Y-m-d]'],
            'ends_on'   => ['label' => 'Hasta', 'rules' => 'permit_empty|valid_date[Y-m-d]'],
        ];
    }

    /** Errores que dependen de otros datos (fechas, rol, sede). */
    private function extraErrors(array $form): ?array
    {
        if ($form['starts_on'] && $form['ends_on'] && $form['ends_on'] < $form['starts_on']) {
            return ['ends_on' => 'La fecha final no puede ser anterior a la inicial.'];
        }
        if (! $this->validRole((int) $form['role_id'])) {
            return ['role_id' => 'Elija un rol de esta institución.'];
        }
        if (! service('rbac')->canGrantRole((int) $form['role_id'])) {
            return ['role_id' => 'Ese rol tiene permisos que usted no tiene; no puede asignarlo.'];
        }
        if (! $this->validCampus($form['campus_id'] ? (int) $form['campus_id'] : null)) {
            return ['campus_id' => 'Elija una sede de esta institución.'];
        }

        return null;
    }

    private function membership(array $form): array
    {
        return [
            'role_id'   => (int) $form['role_id'],
            'campus_id' => $form['campus_id'] ? (int) $form['campus_id'] : null,
            'starts_on' => $form['starts_on'] ?: null,
            'ends_on'   => $form['ends_on'] ?: null,
        ];
    }

    public function index()
    {
        $q      = trim((string) $this->request->getGet('q'));
        $role   = (int) $this->request->getGet('rol');
        $status = (string) $this->request->getGet('estado');
        $roles  = $this->roles();

        $model = model(InstitutionUserModel::class)->withUsers();
        foreach (array_slice(preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY), 0, 5) as $word) {
            $model->groupStart()->like('users.name', $word, 'both', null, true)->orLike('users.email', $word, 'both', null, true)
                ->orLike('users.doc_number', \App\Models\UserModel::normalizeDocument($word) ?: $word, 'both', null, true)->groupEnd();
        }
        if ($role && $this->validRole($role)) {
            $model->where('institution_users.role_id', $role);
        }
        if (in_array($status, ['active', 'inactive'], true)) {
            $model->where('institution_users.status', $status);
        }
        $rows = $model->orderBy('institution_users.status', 'ASC')->orderBy('users.name', 'ASC')->findAll();

        $grantable = array_column(array_filter($roles, static fn ($r) => $r['grantable']), 'id');
        foreach ($rows as &$r) {
            $r['manageable'] = in_array($r['role_id'], $grantable, false) && service('tenant')->can('tenant.users.manage');
            $r['reset']      = $r['manageable'] ? $this->resetBlocker($r) : 'Tiene permisos que usted no tiene.';
            $r['mfa']        = ! empty($r['totp_enabled_at']);
        }
        unset($r);

        // Conteo por rol para los filtros rápidos.
        $counts = [];
        foreach (db_connect()->table('institution_users')->select('role_id, COUNT(*) AS n')
            ->where('institution_id', service('tenant')->institutionId())->groupBy('role_id')->get()->getResultArray() as $c) {
            $counts[$c['role_id']] = (int) $c['n'];
        }

        return view('settings/users', $this->layoutData() + [
            'title'    => 'Personas',
            'section'  => 'usuarios',
            'rows'     => $rows,
            'roles'    => $roles,
            'counts'   => $counts,
            'campuses' => model(CampusModel::class)->orderBy('name')->findAll(),
            'filters'  => ['q' => $q, 'rol' => $role ?: '', 'estado' => $status],
            'meId'     => service('auth')->id(),
        ]);
    }

    /**
     * Por qué no se le puede asignar una contraseña temporal a esta persona (null = sí se puede).
     * Las cuentas son globales: un administrador solo puede hacerlo con quien pertenece únicamente a su institución
     * (si no, podría entrar a la cuenta de alguien en otro colegio o red). El superadministrador, con cualquiera.
     */
    private function resetBlocker(array $m): ?string
    {
        $uid = (int) $m['user_id'];
        if ($uid === (int) service('auth')->id()) {
            return 'Para cambiar su propia contraseña use Mi perfil.';
        }
        $u = model(UserModel::class)->find($uid);
        if (! $u || (int) $u['is_superadmin']) {
            return 'No se puede cambiar la contraseña de un superadministrador desde aquí.';
        }
        if (service('auth')->isSuperadmin()) {
            return null;
        }
        $db     = db_connect();
        $others = $db->table('institution_users')->where('user_id', $uid)->where('institution_id !=', service('tenant')->institutionId())->countAllResults()
            + $db->table('network_users')->where('user_id', $uid)->countAllResults();

        return $others ? 'Esta persona también está en otra institución o red; pídale al superadministrador que le asigne una contraseña temporal.' : null;
    }

    /** Quita la verificación en dos pasos (perdió el celular y los códigos). Mismas reglas que la contraseña temporal. */
    public function resetMfa(int $id)
    {
        $row = model(InstitutionUserModel::class)->withUsers()->find($id);
        if (! $row) {
            return $this->backWith('error', 'Esa persona ya no está en esta institución.');
        }
        if (! service('rbac')->canGrantRole((int) $row['role_id'])) {
            return $this->backWith('error', 'Esta persona tiene permisos que usted no tiene.');
        }
        if ($why = $this->resetBlocker($row)) {
            return $this->backWith('error', str_replace('contraseña temporal', 'quite la verificación', $why));
        }
        service('mfa')->disable((int) $row['user_id']);
        service('audit')->log('users.mfa_reset', ['persona' => $row['name'], 'cuenta' => person_id($row)]);

        return redirect()->to('ajustes/usuarios')->with('toast', ['type' => 'ok', 'msg' => 'Se quitó la verificación en dos pasos de ' . $row['name'] . '. Deberá activarla de nuevo' . (service('mfa')->required(model(UserModel::class)->find((int) $row['user_id'])) ? ' al entrar.' : ' si quiere usarla.')]);
    }

    /** Asigna una contraseña temporal (se muestra una sola vez) y obliga a cambiarla al entrar. */
    public function resetPassword(int $id)
    {
        $row = model(InstitutionUserModel::class)->withUsers()->find($id);
        if (! $row) {
            return $this->backWith('error', 'Esa persona ya no está en esta institución.');
        }
        if (! service('rbac')->canGrantRole((int) $row['role_id'])) {
            return $this->backWith('error', 'Esta persona tiene permisos que usted no tiene; no puede cambiar su contraseña.');
        }
        if ($why = $this->resetBlocker($row)) {
            return $this->backWith('error', $why);
        }
        $temp = temp_password();
        model(UserModel::class)->update((int) $row['user_id'], ['password_hash' => password_hash($temp, PASSWORD_DEFAULT), 'must_change_password' => 1]);
        service('audit')->log('users.password_reset', ['persona' => $row['name'], 'cuenta' => person_id($row)]);

        return redirect()->to('ajustes/usuarios')->with('tempPassword', ['name' => $row['name'], 'email' => person_id($row), 'temp' => $temp]);
    }

    public function store()
    {
        $form               = $this->postFields(['name', 'email', 'doc_type', 'doc_number', 'role_id', 'campus_id', 'starts_on', 'ends_on']);
        $form['email']      = mb_strtolower((string) $form['email']);
        $form['doc_number'] = UserModel::normalizeDocument($form['doc_number']);

        if (! $this->validate([
            'name'       => ['label' => 'Nombre', 'rules' => 'required|max_length[120]'],
            'email'      => ['label' => 'Correo', 'rules' => 'permit_empty|valid_email|max_length[160]'],
            'doc_type'   => ['label' => 'Tipo de documento', 'rules' => 'permit_empty|in_list[' . implode(',', array_keys(UserModel::DOC_TYPES)) . ']'],
            'doc_number' => ['label' => 'Número de documento', 'rules' => 'permit_empty|min_length[4]|max_length[20]'],
            'password' => ['label' => 'Contraseña inicial', 'rules' => 'required|min_length[10]|max_length[72]'],
        ] + $this->rules())) {
            return $this->backInvalid('create', $form);
        }
        if ($e = $this->extraErrors($form)) {
            return $this->backInvalid('create', $form, $e);
        }
        if ($form['email'] === '' && $form['doc_number'] === '') {
            return $this->backInvalid('create', $form, ['email' => 'Escriba el correo o el número de documento: con uno de los dos la persona inicia sesión.']);
        }
        if ($form['doc_number'] !== '' && $form['doc_type'] === '') {
            return $this->backInvalid('create', $form, ['doc_type' => 'Elija el tipo de documento.']);
        }
        if ($form['doc_number'] !== '' && model(UserModel::class)->findByDocument($form['doc_number'])) {
            return $this->backInvalid('create', $form, ['doc_number' => 'Ese documento ya tiene una cuenta en la plataforma. Si la persona está en otra institución, use el importador o pida al superadministrador que la vincule.']);
        }
        if ($form['email'] !== '' && model(UserModel::class)->findByEmail($form['email'])) {
            // No revela si la cuenta es de otra institución ni ningún dato de ella.
            return $this->backInvalid('create', $form, ['email' => 'Ese correo no se puede usar para una cuenta nueva. Si la persona ya trabaja en otra institución de la plataforma, pida al superadministrador que la vincule.']);
        }

        $db = db_connect();
        $db->transStart();
        $userId = model(UserModel::class)->insert([
            'name' => $form['name'], 'email' => $form['email'], 'doc_type' => $form['doc_type'], 'doc_number' => $form['doc_number'], 'status' => 'active',
            'password_hash' => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT), 'must_change_password' => 1,
        ]);
        model(InstitutionUserModel::class)->insert(['user_id' => $userId, 'status' => 'active'] + $this->membership($form));
        $db->transComplete();
        service('audit')->log('users.created', ['persona' => $form['name'], 'cuenta' => person_id($form)]);

        return redirect()->to('ajustes/usuarios')->with('toast', ['type' => 'ok', 'msg' => 'Persona creada. Ya puede iniciar sesión con su ' . ($form['email'] !== '' ? 'correo' : 'número de documento') . ' y la contraseña inicial; al entrar se le pedirá cambiarla.']);
    }

    public function update(int $id)
    {
        $memberships = model(InstitutionUserModel::class);
        $row         = $memberships->withUsers()->find($id);
        if (! $row) {
            return $this->backWith('error', 'Esa persona ya no está en esta institución.');
        }

        $form   = $this->postFields(['role_id', 'campus_id', 'starts_on', 'ends_on', 'status']);
        $reopen = array_merge($row, $form);
        if (! $this->validate($this->rules() + ['status' => ['label' => 'Estado', 'rules' => 'required|in_list[active,inactive]']])) {
            return $this->backInvalid('edit', $reopen);
        }
        if ($e = $this->extraErrors($form)) {
            return $this->backInvalid('edit', $reopen, $e);
        }

        $rbac = service('rbac');
        if (! $rbac->canGrantRole((int) $row['role_id'])) {
            return $this->backWith('error', 'Esta persona tiene permisos que usted no tiene; no puede cambiar su rol ni su acceso.');
        }

        // Nadie se quita a sí mismo la gestión de personas, ni deja la institución sin quien la tenga.
        $iid         = service('tenant')->institutionId();
        $today       = date('Y-m-d');
        $inForce     = (! $form['starts_on'] || $form['starts_on'] <= $today) && (! $form['ends_on'] || $form['ends_on'] >= $today);
        $wasGuardian = $row['status'] === 'active' && $rbac->isGuardianRole((int) $row['role_id']);
        $isGuardian  = $form['status'] === 'active' && $inForce && $rbac->isGuardianRole((int) $form['role_id']);
        if ($wasGuardian && ! $isGuardian) {
            if ((int) $row['user_id'] === service('auth')->id() && ! service('auth')->isSuperadmin()) {
                return $this->backInvalid('edit', $reopen, ['role_id' => 'No puede quitarse a sí mismo el permiso de gestionar personas y roles, ni desactivarse.']);
            }
            if (! $rbac->guardianRemainsWithout($iid, null, $id)) {
                return $this->backInvalid('edit', $reopen, ['role_id' => 'Es la única persona activa que puede gestionar personas y roles. Dele ese acceso a otra persona antes de cambiarla.']);
            }
        }

        $memberships->update($id, ['status' => $form['status']] + $this->membership($form));
        service('audit')->log('users.updated', ['persona' => $row['name'], 'rol' => (string) $form['role_id'], 'estado' => $form['status']]);

        return redirect()->to('ajustes/usuarios')->with('toast', ['type' => 'ok', 'msg' => 'Cambios guardados para ' . $row['name'] . '.']);
    }
}
