<?php

namespace App\Libraries\Import;

use App\Libraries\Guardians;
use App\Models\UserModel;

/**
 * Importador masivo de estudiantes, acudientes y matrícula en grupo (Ajustes › Personas › Importar).
 *
 * 1. analyze(): lee las filas, valida y decide qué pasará con cada persona sin guardar nada. El resultado se guarda en
 *    writable/cache/import-{token}.json (ligado a la persona y la institución, vence en 1 hora).
 * 2. apply(): aplica ese análisis en una transacción: crea cuentas (contraseña temporal, cambio obligatorio), agrega
 *    membresías, matricula en el grupo del periodo actual y vincula acudientes. Devuelve las credenciales nuevas.
 *
 * La identidad es el número de documento (users.doc_number, único en toda la plataforma): si la persona ya tiene cuenta
 * (aquí o en otro colegio) se reutiliza y conserva su contraseña.
 */
class StudentImport
{
    public const TTL = 3600;

    /** Columnas de la plantilla: clave interna => encabezado. */
    public const COLUMNS = [
        'doc_type' => 'tipo_documento', 'doc_number' => 'numero_documento', 'first' => 'nombres', 'last' => 'apellidos',
        'group' => 'grupo', 'email' => 'correo',
        'g_doc_type' => 'acudiente_tipo_documento', 'g_doc_number' => 'acudiente_numero_documento', 'g_first' => 'acudiente_nombres',
        'g_last' => 'acudiente_apellidos', 'g_email' => 'acudiente_correo', 'g_relationship' => 'acudiente_parentesco',
    ];

    /** Sinónimos de encabezados (sin tildes, en minúsculas y con «_»). */
    private const ALIASES = [
        'documento' => 'doc_number', 'numero_de_documento' => 'doc_number', 'identificacion' => 'doc_number', 'tipo_de_documento' => 'doc_type', 'tipo' => 'doc_type',
        'nombre' => 'first', 'apellido' => 'last', 'curso' => 'group', 'grado' => 'group', 'email' => 'email', 'correo_electronico' => 'email',
        'parentesco' => 'g_relationship', 'acudiente_documento' => 'g_doc_number', 'acudiente_nombre' => 'g_first', 'acudiente_apellido' => 'g_last',
        'acudiente_email' => 'g_email', 'acudiente_tipo' => 'g_doc_type',
    ];

    private function db()
    {
        return db_connect();
    }

    private function iid(): int
    {
        return (int) service('tenant')->institutionId();
    }

    private static function dir(): string
    {
        return WRITEPATH . 'cache/';
    }

    /** «Número de Documento» → «numero_de_documento». */
    private static function key(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);

        return trim((string) preg_replace('/[^a-z0-9]+/', '_', $s), '_');
    }

    /** «7.º A» / «7A» / «séptimo a» → «7a». */
    private static function groupKey(string $s): string
    {
        $s = mb_strtolower($s);
        $s = strtr($s, ['º' => '', '°' => '', 'ª' => '', 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
        $s = strtr($s, ['sexto' => '6', 'septimo' => '7', 'octavo' => '8', 'noveno' => '9', 'decimo' => '10', 'undecimo' => '11', 'once' => '11', 'grado' => '']);

        return (string) preg_replace('/[^a-z0-9]/', '', $s);
    }

    private static function name(string $first, string $last): string
    {
        $n = trim(preg_replace('/\s+/u', ' ', $first . ' ' . $last));

        return mb_convert_case(mb_strtolower($n), MB_CASE_TITLE);
    }

    /** Plantilla (encabezados y 2 filas de ejemplo). */
    public static function template(): array
    {
        return [array_values(self::COLUMNS), [
            ['TI', '1012345678', 'Ana Sofía', 'Pérez Gómez', '7.º A', '', 'CC', '52345678', 'Laura', 'Gómez', 'laura.gomez@correo.com', 'Madre'],
            ['TI', '1012345679', 'Juan David', 'Pérez Gómez', '9.º A', '', 'CC', '52345678', 'Laura', 'Gómez', 'laura.gomez@correo.com', 'Madre'],
        ]];
    }

    /**
     * Analiza las filas. Devuelve ['token', 'summary', 'rows', 'limit'] o ['error'].
     *
     * @param list<list<string>> $rows primera fila = encabezados
     */
    public function analyze(array $rows, string $fileName): array
    {
        if (count($rows) < 2) {
            return ['error' => 'El archivo no tiene filas de datos debajo de los encabezados.'];
        }
        // Encabezados → columnas.
        $map = [];
        foreach (array_shift($rows) as $i => $h) {
            $k = self::key((string) $h);
            $field = array_search($k, self::COLUMNS, true) ?: (self::ALIASES[$k] ?? null);
            if ($field && ! isset($map[$field])) {
                $map[$field] = $i;
            }
        }
        foreach (['doc_number', 'first', 'group'] as $req) {
            if (! isset($map[$req])) {
                return ['error' => 'Falta la columna «' . self::COLUMNS[$req] . '». Use la plantilla descargable.'];
            }
        }

        $period = service('academic')->currentPeriod();
        if (! $period) {
            return ['error' => 'No hay un periodo académico actual. Créelo en Ajustes › Estructura académica.'];
        }
        $groups = [];
        foreach (service('academic')->groups((int) $period['id']) as $g) {
            $groups[self::groupKey($g['name'])] = $g;
            $groups[self::groupKey($g['level'] . $g['name'])] ??= $g;
        }
        $iid      = $this->iid();
        $users    = model(UserModel::class);
        $relations = array_combine(array_map([self::class, 'key'], Guardians::RELATIONSHIPS), Guardians::RELATIONSHIPS);

        // Estado actual: membresías de esta institución y grupo del periodo de cada persona.
        $members = array_column($this->db()->table('institution_users hu')->select('hu.user_id, hu.status, r.template_key')
            ->join('roles r', 'r.id = hu.role_id', 'left')->where('hu.institution_id', $iid)->get()->getResultArray(), null, 'user_id');
        $groupOf = array_column($this->db()->table('class_group_members cm')->select('cm.user_id, cm.group_id')
            ->join('class_groups g', 'g.id = cm.group_id')->where('cm.institution_id', $iid)->where('g.period_id', (int) $period['id'])
            ->get()->getResultArray(), 'group_id', 'user_id');

        $out = [];
        $seenStudents = [];   // documento => índice de fila (un estudiante en varias filas = varios acudientes)
        $people = [];         // documento => persona a crear/usar (estudiantes y acudientes, sin repetir)
        $sum = ['rows' => 0, 'errors' => 0, 'new' => 0, 'existing' => 0, 'enroll' => 0, 'move' => 0, 'links' => 0, 'newMembers' => 0];
        $cell = static fn (array $r, string $f) => isset($map[$f]) ? trim((string) ($r[$map[$f]] ?? '')) : '';

        foreach ($rows as $n => $r) {
            $line = $n + 2; // número de fila en Excel
            $sum['rows']++;
            $errs = [];
            $notes = [];
            $doc  = UserModel::normalizeDocument($cell($r, 'doc_number'));
            $type = strtoupper($cell($r, 'doc_type')) ?: 'TI';
            $name = self::name($cell($r, 'first'), $cell($r, 'last'));
            $email = mb_strtolower($cell($r, 'email'));
            $gk   = self::groupKey($cell($r, 'group'));

            if (strlen($doc) < 4) {
                $errs[] = 'Falta el número de documento del estudiante (mínimo 4 caracteres).';
            }
            if (! isset(UserModel::DOC_TYPES[$type])) {
                $errs[] = 'Tipo de documento «' . $type . '» no válido (use ' . implode(', ', array_keys(UserModel::DOC_TYPES)) . ').';
            }
            if ($name === '' || mb_strlen($name) > 120) {
                $errs[] = 'Falta el nombre del estudiante.';
            }
            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errs[] = 'El correo del estudiante no es válido.';
            }
            $group = $groups[$gk] ?? null;
            if (! $group) {
                $errs[] = 'El grupo «' . $cell($r, 'group') . '» no existe en el periodo ' . $period['name'] . '.';
            }

            // Acudiente (opcional).
            $gDoc = UserModel::normalizeDocument($cell($r, 'g_doc_number'));
            $guardian = null;
            if ($gDoc !== '' || $cell($r, 'g_first') !== '') {
                $gType = strtoupper($cell($r, 'g_doc_type')) ?: 'CC';
                $gName = self::name($cell($r, 'g_first'), $cell($r, 'g_last'));
                $gEmail = mb_strtolower($cell($r, 'g_email'));
                $relKey = self::key($cell($r, 'g_relationship'));
                if (strlen($gDoc) < 4) {
                    $errs[] = 'Falta el número de documento del acudiente.';
                } elseif ($gDoc === $doc) {
                    $errs[] = 'El acudiente no puede tener el mismo documento que el estudiante.';
                }
                if (! isset(UserModel::DOC_TYPES[$gType])) {
                    $errs[] = 'Tipo de documento del acudiente «' . $gType . '» no válido.';
                }
                if ($gName === '') {
                    $errs[] = 'Falta el nombre del acudiente.';
                }
                if ($gEmail !== '' && ! filter_var($gEmail, FILTER_VALIDATE_EMAIL)) {
                    $errs[] = 'El correo del acudiente no es válido.';
                }
                $guardian = ['doc' => $gDoc, 'type' => $gType, 'name' => $gName, 'email' => $gEmail, 'relationship' => $relations[$relKey] ?? 'Acudiente'];
                if ($cell($r, 'g_relationship') !== '' && ! isset($relations[$relKey])) {
                    $notes[] = 'Parentesco «' . $cell($r, 'g_relationship') . '» no reconocido: queda como «Acudiente».';
                }
            }

            // Persona del estudiante: ¿ya existe? ¿ya está en esta institución? ¿en qué grupo?
            $action = [];
            if (! $errs) {
                $repeat = isset($seenStudents[$doc]);
                if ($repeat && ($out[$seenStudents[$doc]]['group'] ?? null) !== $group['id']) {
                    $errs[] = 'El estudiante aparece en otra fila con un grupo distinto (fila ' . $out[$seenStudents[$doc]]['line'] . ').';
                }
                if (! $errs && ! $repeat) {
                    $seenStudents[$doc] = count($out);
                    $existing = $users->findByDocument($doc);
                    if ($existing && $email !== '' && $existing['email'] && $existing['email'] !== $email) {
                        $notes[] = 'Ya tenía cuenta con otro correo; se conserva el que tenía.';
                    }
                    if (! $existing && $email !== '' && $users->findByEmail($email)) {
                        $errs[] = 'El correo ' . $email . ' ya pertenece a otra cuenta.';
                    }
                    if (! $errs) {
                        $m = $existing ? ($members[$existing['id']] ?? null) : null;
                        if ($m && $m['template_key'] && $m['template_key'] !== 'student') {
                            $errs[] = 'Esa persona ya está en la institución con otro rol; cámbielo en Ajustes › Personas.';
                        } else {
                            $people[$doc] = ['type' => $type, 'name' => $name, 'email' => $email, 'role' => 'student', 'id' => $existing['id'] ?? null, 'member' => (bool) $m];
                            $action[] = $existing ? 'existing' : 'new';
                            $sum[$existing ? 'existing' : 'new']++;
                            if (! $m) {
                                $sum['newMembers']++;
                            }
                            $current = $existing ? ($groupOf[$existing['id']] ?? null) : null;
                            if ((int) $current !== (int) $group['id']) {
                                $action[] = $current ? 'move' : 'enroll';
                                $sum[$current ? 'move' : 'enroll']++;
                                if ($current) {
                                    $notes[] = 'Cambia de grupo.';
                                }
                            }
                        }
                    }
                }
            }

            // Acudiente: crear o reutilizar, y vincular.
            if (! $errs && $guardian) {
                if (! isset($people[$guardian['doc']])) {
                    $gx = $users->findByDocument($guardian['doc']);
                    if (! $gx && $guardian['email'] !== '' && $users->findByEmail($guardian['email'])) {
                        $errs[] = 'El correo ' . $guardian['email'] . ' ya pertenece a otra cuenta.';
                    } else {
                        $gm = $gx ? ($members[$gx['id']] ?? null) : null;
                        if ($gm && $gm['template_key'] && $gm['template_key'] !== 'guardian') {
                            $notes[] = 'El acudiente ya está en la institución con otro rol; se vincula igual con su cuenta.';
                        }
                        $people[$guardian['doc']] = ['type' => $guardian['type'], 'name' => $guardian['name'], 'email' => $guardian['email'], 'role' => 'guardian',
                            'id' => $gx['id'] ?? null, 'member' => (bool) $gm];
                        if (! $gm) {
                            $sum['newMembers']++;
                        }
                        $sum[$gx ? 'existing' : 'new']++;
                    }
                } elseif ($people[$guardian['doc']]['role'] !== 'guardian') {
                    $errs[] = 'El documento del acudiente es el de un estudiante del archivo.';
                }
                if (! $errs) {
                    $already = false;
                    if (! empty($people[$guardian['doc']]['id']) && ! empty($people[$doc]['id'])) {
                        $already = $this->db()->table('guardian_links')->where('institution_id', $iid)
                            ->where('guardian_id', $people[$guardian['doc']]['id'])->where('student_id', $people[$doc]['id'])->countAllResults() > 0;
                    }
                    if (! $already) {
                        $action[] = 'link';
                        $sum['links']++;
                    }
                }
            }

            if ($errs) {
                $sum['errors']++;
            }
            $out[] = ['line' => $line, 'doc' => $doc, 'name' => $name, 'groupName' => $group['name'] ?? $cell($r, 'group'), 'group' => $group['id'] ?? null,
                'guardian' => $guardian, 'actions' => $action, 'errors' => $errs, 'notes' => $notes];
        }

        $limit = $this->limit($sum['newMembers']);
        $token = bin2hex(random_bytes(12));
        $data  = ['user' => (int) service('auth')->id(), 'institution' => $iid, 'created' => time(), 'file' => mb_substr($fileName, 0, 120),
            'period' => (int) $period['id'], 'summary' => $sum, 'rows' => $out, 'people' => $people, 'limit' => $limit];
        file_put_contents(self::dir() . 'import-' . $token . '.json', json_encode($data, JSON_UNESCAPED_UNICODE));
        $this->cleanup();

        return ['token' => $token, 'summary' => $sum, 'rows' => $out, 'limit' => $limit, 'file' => $data['file'], 'period' => $period['name']];
    }

    /** Límite de usuarios del plan: ['max' => int|0, 'current' => int, 'after' => int, 'over' => bool]. */
    private function limit(int $newMembers): array
    {
        $iid     = $this->iid();
        $max     = (int) (service('aiQuota')->plan($iid)['max_users'] ?? 0);
        $current = (int) $this->db()->table('institution_users')->where('institution_id', $iid)->where('status', 'active')->countAllResults();

        return ['max' => $max, 'current' => $current, 'after' => $current + $newMembers, 'over' => $max > 0 && $current + $newMembers > $max];
    }

    /** Análisis guardado (solo de quien lo hizo, en la misma institución, sin vencer). */
    public function load(string $token): ?array
    {
        if (! preg_match('/^[a-f0-9]{24}$/', $token)) {
            return null;
        }
        $f = self::dir() . 'import-' . $token . '.json';
        $d = is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
        if (! $d || $d['user'] !== (int) service('auth')->id() || $d['institution'] !== $this->iid() || time() - $d['created'] > self::TTL) {
            return null;
        }

        return $d + ['token' => $token];
    }

    /**
     * Aplica el análisis. Devuelve ['summary', 'credentials' => token del archivo de contraseñas | null] o ['error'].
     */
    public function apply(string $token): array
    {
        $d = $this->load($token);
        if (! $d) {
            return ['error' => 'La vista previa venció o no existe. Suba el archivo de nuevo.'];
        }
        if ($d['summary']['errors'] > 0) {
            return ['error' => 'Corrija las filas con errores y suba el archivo de nuevo.'];
        }
        $limit = $this->limit($d['summary']['newMembers']); // se vuelve a comprobar al confirmar
        if ($limit['over']) {
            return ['error' => 'El plan permite ' . $limit['max'] . ' usuarios y quedarían ' . $limit['after'] . '. Suba de plan o reduzca el archivo.'];
        }
        $iid   = $this->iid();
        $db    = $this->db();
        $users = model(UserModel::class);
        $roles = array_column($db->table('roles')->select('id, template_key')->where('institution_id', $iid)->whereIn('template_key', ['student', 'guardian'])->get()->getResultArray(), 'id', 'template_key');
        if (! isset($roles['student'], $roles['guardian'])) {
            return ['error' => 'La institución no tiene los roles Estudiante y Acudiente.'];
        }
        $now   = date('Y-m-d H:i:s');
        $creds = [];
        $ids   = [];
        $done  = ['created' => 0, 'members' => 0, 'enrolled' => 0, 'moved' => 0, 'links' => 0];
        $notify = [];

        $db->transStart();
        // 1) Personas: crear las nuevas y agregar membresías.
        foreach ($d['people'] as $doc => $p) {
            $doc = (string) $doc;
            $id  = $p['id'] ? (int) $p['id'] : (int) ($users->findByDocument($doc)['id'] ?? 0);
            if (! $id) {
                $temp = temp_password();
                $id   = (int) $users->insert(['name' => $p['name'], 'email' => $p['email'] !== '' ? $p['email'] : null, 'doc_type' => $p['type'], 'doc_number' => $doc,
                    'password_hash' => password_hash($temp, PASSWORD_DEFAULT), 'must_change_password' => 1, 'status' => 'active']);
                $creds[] = [$p['name'], \App\Models\UserModel::docLabel($p['type'], $doc), $p['role'] === 'student' ? 'Estudiante' : 'Acudiente', $p['email'], $temp];
                $done['created']++;
            } else {
                // Si solo pertenece a esta institución (o a ninguna), se actualiza el nombre.
                $others = $db->table('institution_users')->where('user_id', $id)->where('institution_id !=', $iid)->countAllResults();
                if (! $others) {
                    $users->update($id, ['name' => $p['name']] + ($p['email'] !== '' && ! ($users->find($id)['email'] ?? null) && ! $users->findByEmail($p['email']) ? ['email' => $p['email']] : []));
                }
            }
            $ids[$doc] = $id;
            $exists = $db->table('institution_users')->where('institution_id', $iid)->where('user_id', $id)->get()->getRowArray();
            if (! $exists) {
                $db->table('institution_users')->insert(['institution_id' => $iid, 'user_id' => $id, 'role_id' => $roles[$p['role']], 'status' => 'active',
                    'starts_on' => date('Y-m-d'), 'created_at' => $now, 'updated_at' => $now]);
                $done['members']++;
            } elseif ($exists['status'] !== 'active') {
                $db->table('institution_users')->where('id', $exists['id'])->update(['status' => 'active', 'updated_at' => $now]);
            }
        }
        // 2) Matrícula y vínculos.
        foreach ($d['rows'] as $r) {
            $sid = $ids[$r['doc']] ?? null;
            if (! $sid) {
                continue;
            }
            if (in_array('enroll', $r['actions'], true) || in_array('move', $r['actions'], true)) {
                $db->query('DELETE cm FROM class_group_members cm JOIN class_groups g ON g.id = cm.group_id WHERE cm.institution_id = ? AND cm.user_id = ? AND g.period_id = ?', [$iid, $sid, $d['period']]);
                $db->table('class_group_members')->insert(['institution_id' => $iid, 'group_id' => $r['group'], 'user_id' => $sid, 'created_at' => $now, 'updated_at' => $now]);
                $done[in_array('move', $r['actions'], true) ? 'moved' : 'enrolled']++;
            }
            if (in_array('link', $r['actions'], true) && ($gid = $ids[$r['guardian']['doc']] ?? null)) {
                $dup = $db->table('guardian_links')->where('institution_id', $iid)->where('guardian_id', $gid)->where('student_id', $sid)->countAllResults();
                if (! $dup) {
                    $db->table('guardian_links')->insert(['institution_id' => $iid, 'guardian_id' => $gid, 'student_id' => $sid, 'relationship' => $r['guardian']['relationship'],
                        'created_by' => (int) service('auth')->id(), 'created_at' => $now, 'updated_at' => $now]);
                    $done['links']++;
                    $notify[] = [$gid, $sid];
                }
            }
        }
        $db->transComplete();
        if (! $db->transStatus()) {
            log_message('error', 'Importador: la transacción falló en la institución ' . $iid);

            return ['error' => 'No se pudo completar la importación; no se guardó nada. Intente de nuevo.'];
        }
        foreach ($notify as [$g, $s]) {
            service('notify')->guardianLinked($g, $s);
        }
        service('audit')->log('users.import', ['archivo' => $d['file'], 'cuentas nuevas' => $done['created'], 'agregadas a la institución' => $done['members'],
            'matriculados' => $done['enrolled'], 'cambios de grupo' => $done['moved'], 'vínculos de acudiente' => $done['links']]);
        @unlink(self::dir() . 'import-' . $token . '.json');

        // Contraseñas temporales: un archivo que se descarga una sola vez.
        $credToken = null;
        if ($creds) {
            $credToken = bin2hex(random_bytes(12));
            file_put_contents(self::dir() . 'import-cred-' . $credToken . '.json', json_encode(['user' => (int) service('auth')->id(), 'institution' => $iid,
                'created' => time(), 'rows' => $creds], JSON_UNESCAPED_UNICODE));
        }

        return ['summary' => $done, 'credentials' => $credToken];
    }

    /** Lee (y borra) el archivo de credenciales: solo una vez, solo quien importó. */
    public function takeCredentials(string $token): ?array
    {
        if (! preg_match('/^[a-f0-9]{24}$/', $token)) {
            return null;
        }
        $f = self::dir() . 'import-cred-' . $token . '.json';
        $d = is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
        @unlink($f);
        if (! $d || $d['user'] !== (int) service('auth')->id() || $d['institution'] !== $this->iid() || time() - $d['created'] > self::TTL) {
            return null;
        }

        return $d['rows'];
    }

    /** Borra análisis y credenciales vencidos. */
    private function cleanup(): void
    {
        foreach (glob(self::dir() . 'import-*.json') ?: [] as $f) {
            if (filemtime($f) < time() - self::TTL) {
                @unlink($f);
            }
        }
    }
}
