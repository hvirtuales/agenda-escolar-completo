<?php

namespace App\Database\Seeds;

use App\Libraries\Activities;
use App\Libraries\Demo;
use App\Models\InstitutionModel;
use App\Models\UserModel;
use CodeIgniter\Database\Seeder;

/**
 * Colegio de demostración (ficticio): «Colegio Horizonte». Es el único colegio de la demostración pública.
 *
 *   php spark db:seed SchoolDemoSeeder   crea el colegio (no hace nada si ya existe)
 *   php spark demo:reset                 lo restaura (borra lo que hicieron los visitantes y lo vuelve a cargar)
 *
 * Contenido: periodo en curso; 6.º a 11.º con un grupo cada uno; todas las asignaturas (6.º–9.º: 10; 10.º–11.º: 13, con
 * Química, Física, Filosofía y Ciencias políticas y económicas); rectora, coordinadora y un docente por asignatura;
 * 8 estudiantes por grupo; acudientes; cuadernos con páginas publicadas (también tabla y pregunta); retos y evaluaciones
 * cerrados, abiertos y programados; agenda, comunicaciones, banco de preguntas y un recurso.
 * Todas las cuentas tienen users.is_demo = 1 y la contraseña DemoSeeder::DEMO_PASSWORD. Nunca crea un superadministrador.
 * Las fechas son relativas a hoy, para que la demostración siempre se vea actual. Correos en el dominio ficticio .demo.
 */
class SchoolDemoSeeder extends Seeder
{
    public const NAME   = 'Colegio Horizonte';
    public const DOMAIN = 'horizonte.demo';

    private int $iid;
    private string $now;
    private array $users = [];
    private bool $fresh = true;

    private function ts(string $rel): string
    {
        return date('Y-m-d H:i:s', strtotime($rel));
    }

    private function ins(string $table, array $row): int
    {
        $this->db->table($table)->insert($row + ['institution_id' => $this->iid]);

        return (int) $this->db->insertID();
    }

    private function user(string $name, string $local): int
    {
        $email = $local . '@' . self::DOMAIN;
        for ($n = 2; isset($this->users[$email]); $n++) { // homónimos: juan.perez2@…
            $email = $local . $n . '@' . self::DOMAIN;
        }
        $id = (int) model(UserModel::class)->insert(['name' => $name, 'email' => $email, 'password_hash' => $this->users['_hash'], 'is_superadmin' => 0, 'status' => 'active']);
        $this->db->table('users')->where('id', $id)->update(['is_demo' => 1]);

        return $this->users[$email] = $id;
    }

    public function run()
    {
        $existing = $this->db->table('institutions')->where('name', self::NAME)->where('deleted_at', null)->get()->getRowArray();
        if ($existing && ! getenv('DEMO_RESET')) {
            echo self::NAME . " ya existe (id {$existing['id']}). Para restaurarlo: php spark demo:reset\n";

            return;
        }
        $this->load($existing ? (int) $existing['id'] : null);
    }

    /**
     * Carga el colegio. Con $keepId (restauración) se borra todo lo del colegio menos la institución y su consumo de IA
     * (así el cupo mensual de IA no se reinicia cada noche) y se vuelve a cargar sobre la misma institución.
     */
    public function load(?int $keepId = null): void
    {
        mt_srand(2026); // datos estables entre recargas
        $this->now = date('Y-m-d H:i:s');
        $m         = model(InstitutionModel::class);
        if ($keepId) {
            self::wipe($this->db, $keepId, true);
            $this->iid   = $keepId;
            $this->fresh = false;
            $this->db->table('institutions')->where('id', $keepId)->update(['plan' => 'Demostración', 'status' => 'active', 'accent_color' => '#0E7490', 'updated_at' => $this->now]);
        } else {
            $this->iid = (int) $m->insert(['name' => self::NAME, 'slug' => $m->uniqueSlug(self::NAME), 'city' => 'Pereira', 'country' => 'CO', 'timezone' => 'America/Bogota',
                'accent_color' => '#0E7490', 'plan' => 'Demostración', 'status' => 'active', 'email' => 'contacto@' . self::DOMAIN]);
        }

        /* ---------- Roles y estructura ---------- */
        service('rbac')->ensureBaseTemplates();
        $roles  = service('rbac')->copyTemplatesToInstitution($this->iid);
        $period = $this->ins('academic_periods', ['name' => 'Año lectivo ' . date('Y'), 'starts_on' => date('Y') . '-01-19', 'ends_on' => date('Y') . '-11-27', 'is_current' => 1, 'created_at' => $this->now, 'updated_at' => $this->now]);
        $campus = $this->ins('campuses', ['name' => 'Sede principal', 'address' => 'Calle 14 # 20-35', 'status' => 'active', 'created_at' => $this->now, 'updated_at' => $this->now]);
        $grades = ['6.º', '7.º', '8.º', '9.º', '10.º', '11.º'];
        $groups = [];
        foreach ($grades as $i => $lv) {
            $level           = $this->ins('levels', ['name' => $lv, 'sort_order' => $i + 1, 'created_at' => $this->now, 'updated_at' => $this->now]);
            $groups[$lv . ' A'] = $this->ins('class_groups', ['level_id' => $level, 'period_id' => $period, 'campus_id' => $campus, 'name' => $lv . ' A', 'created_at' => $this->now, 'updated_at' => $this->now]);
        }

        // Áreas y asignaturas del plan de estudios (Ley 115). 'g' = grados donde se dicta; 'h' = horas por semana.
        $areas = [
            ['Matemáticas', '#2563EB', [['Matemáticas', 'all', 5]]],
            ['Ciencias naturales', '#16A34A', [['Biología', '6-9', 4], ['Química', '10-11', 3], ['Física', '10-11', 3]]],
            ['Lenguaje', '#DB2777', [['Lengua castellana', 'all', 4]]],
            ['Idiomas', '#7C3AED', [['Inglés', 'all', 3]]],
            ['Ciencias sociales', '#D97706', [['Ciencias sociales', 'all', 3], ['Filosofía', '10-11', 2], ['Ciencias políticas y económicas', '10-11', 1]]],
            ['Tecnología', '#0891B2', [['Tecnología e informática', 'all', 2]]],
            ['Educación artística', '#E11D48', [['Educación artística', 'all', 2]]],
            ['Educación física', '#EA580C', [['Educación física', 'all', 2]]],
            ['Formación humana', '#64748B', [['Ética y valores', 'all', 1], ['Religión', 'all', 1]]],
        ];
        $subj = [];
        $plan = []; // asignatura => [grados, horas]
        foreach ($areas as $i => [$cat, $color, $list]) {
            $c = $this->ins('categories', ['name' => $cat, 'color' => $color, 'sort_order' => $i + 1, 'status' => 'active', 'created_at' => $this->now, 'updated_at' => $this->now]);
            foreach ($list as [$s, $g, $h]) {
                $subj[$s] = $this->ins('subjects', ['category_id' => $c, 'name' => $s, 'status' => 'active', 'created_at' => $this->now, 'updated_at' => $this->now]);
                $plan[$s] = [$g === 'all' ? $grades : ($g === '6-9' ? array_slice($grades, 0, 4) : array_slice($grades, 4)), $h];
            }
        }

        /* ---------- Personas ---------- */
        $this->users['_hash'] = password_hash(DemoSeeder::DEMO_PASSWORD, PASSWORD_DEFAULT);
        $member = function (int $uid, string $role, ?string $lastAccess) use ($roles, $campus): void {
            $this->ins('institution_users', ['user_id' => $uid, 'role_id' => $roles[$role], 'campus_id' => $role === 'student' ? $campus : null, 'starts_on' => date('Y') . '-01-15',
                'status' => 'active', 'last_access_at' => $lastAccess, 'created_at' => $this->now, 'updated_at' => $this->now]);
        };
        $rector = $this->user('Claudia Restrepo', 'rectoria');
        $coord  = $this->user('Martha Ospina', 'coordinacion');
        $member($rector, 'admin', $this->ts('-1 day'));
        $member($coord, 'coordinator', $this->ts('-3 hours'));

        // Un docente por asignatura, en todos los grupos donde se dicta.
        $teachers = [
            'Matemáticas' => ['Jorge Ramírez', 'jorge.ramirez', '-2 hours'], 'Biología' => ['Diana Castaño', 'diana.castano', '-1 day'],
            'Química' => ['Andrés Quintero', 'andres.quintero', '-6 hours'], 'Física' => ['Laura Bermúdez', 'laura.bermudez', '-2 days'],
            'Lengua castellana' => ['Felipe Herrera', 'felipe.herrera', '-9 days'], 'Inglés' => ['Sofía Mejía', 'sofia.mejia', '-5 hours'],
            'Ciencias sociales' => ['Natalia Vargas', 'natalia.vargas', '-2 days'], 'Filosofía' => ['Ernesto Gaviria', 'ernesto.gaviria', '-3 days'],
            'Ciencias políticas y económicas' => ['Carolina Montoya', 'carolina.montoya', '-4 days'], 'Tecnología e informática' => ['Camilo Duarte', 'camilo.duarte', '-4 days'],
            'Educación artística' => ['Juliana Ochoa', 'juliana.ochoa', '-1 day'], 'Educación física' => ['Mauricio Cano', 'mauricio.cano', '-8 hours'],
            'Ética y valores' => ['Teresa Villegas', 'teresa.villegas', '-5 days'], 'Religión' => ['Gonzalo Arias', 'gonzalo.arias', '-6 days'],
        ];
        $tid = [];
        foreach ($teachers as $s => [$name, $local, $last]) {
            $tid[$s] = $this->user($name, $local);
            $member($tid[$s], 'teacher', $this->ts($last));
        }

        // Ofertas: cada grupo con todas las asignaturas de su grado, y su docente.
        $off = []; // [asignatura][grupo] => oferta
        foreach ($plan as $s => [$gs, $hours]) {
            foreach ($gs as $lv) {
                $g          = $lv . ' A';
                $o          = $this->ins('subject_offerings', ['subject_id' => $subj[$s], 'group_id' => $groups[$g], 'period_id' => $period, 'hours_per_week' => $hours, 'created_at' => $this->now, 'updated_at' => $this->now]);
                $off[$s][$g] = $o;
                $this->ins('teaching_assignments', ['offering_id' => $o, 'user_id' => $tid[$s], 'created_at' => $this->now, 'updated_at' => $this->now]);
            }
        }

        // Estudiantes: 8 por grupo, con un «nivel» estable que define su desempeño. 7.º A primero: Mariana es la cuenta de demostración.
        $first = ['Mariana', 'Santiago', 'Valeria', 'Samuel', 'Isabella', 'Matías', 'Sara', 'Tomás', 'Luciana', 'Emiliano', 'Gabriela', 'Juan José', 'Antonia', 'Martín',
            'Salomé', 'Jerónimo', 'Daniela', 'Nicolás', 'Manuela', 'Sebastián', 'Camila', 'Miguel Ángel', 'Juliana', 'Alejandro', 'María José', 'David', 'Laura Sofía',
            'Andrés Felipe', 'Paula', 'Esteban', 'Victoria', 'Simón', 'Mariangel', 'Emilio', 'Ana Sofía', 'Felipe', 'Renata', 'Joaquín', 'Lucía', 'Pablo', 'Amelia', 'Thiago',
            'Catalina', 'Mateo', 'Elena', 'Agustín', 'Zoe', 'Benjamín'];
        $last  = ['López', 'García', 'Martínez', 'Rodríguez', 'Gómez', 'Hernández', 'Díaz', 'Moreno', 'Álvarez', 'Muñoz', 'Rojas', 'Jiménez', 'Torres', 'Ramírez',
            'Castro', 'Vargas', 'Ortiz', 'Suárez', 'Cárdenas', 'Rincón', 'Mejía', 'Arango', 'Zapata', 'Osorio', 'Giraldo', 'Ríos', 'Acosta', 'Quintero', 'Patiño',
            'Salazar', 'Valencia', 'Cardona', 'Henao', 'Betancur', 'Echeverri', 'Londoño', 'Marín', 'Correa', 'Franco', 'Duque', 'Escobar', 'Toro', 'Arbeláez',
            'Bedoya', 'Villa', 'Montoya', 'Gallego', 'Posada'];
        $students = []; // [grupo] => [[id, ability, name]]
        $n        = 0;
        foreach (['7.º A', '6.º A', '8.º A', '9.º A', '10.º A', '11.º A'] as $g) {
            for ($i = 0; $i < 8; $i++, $n++) {
                $name    = $first[$n] . ' ' . $last[$n];
                $uid     = $this->user($name, self::slug($first[$n]) . '.' . self::slug($last[$n]));
                // Documento de ejemplo (ingreso con documento): Mariana = TI 1090000001.
                $this->db->table('users')->where('id', $uid)->update(['doc_type' => 'TI', 'doc_number' => '109' . sprintf('%07d', $n + 1)]);
                $ability = $n === 0 ? 0.86 : round(0.42 + (mt_rand(0, 53) / 100), 2); // Mariana (cuenta de demostración) va bien
                $member($uid, 'student', mt_rand(0, 9) ? $this->ts('-' . mt_rand(1, 96) . ' hours') : null);
                $this->ins('class_group_members', ['group_id' => $groups[$g], 'user_id' => $uid, 'created_at' => $this->now, 'updated_at' => $this->now]);
                $students[$g][] = ['id' => $uid, 'ability' => $ability, 'name' => $name];
            }
        }

        // Acudientes: la familia López (cuenta de demostración) con Mariana; otros con un estudiante cada uno.
        $guardians = [['Patricia López', 'familia.lopez', $students['7.º A'][0]['id'], 'Madre']];
        foreach ([['7.º A', 3, 'Carlos Rodríguez', 'Padre'], ['6.º A', 2, 'Luz Marina Muñoz', 'Madre'], ['8.º A', 1, 'Hernán Suárez', 'Padre'],
            ['9.º A', 4, 'Gloria Ríos', 'Abuela'], ['10.º A', 0, 'Ricardo Echeverri', 'Padre'], ['11.º A', 5, 'Mónica Duque', 'Tía']] as [$g, $i, $name, $rel]) {
            $guardians[] = [$name, 'acudiente.' . self::slug(explode(' ', $name)[0]), $students[$g][$i]['id'], $rel];
        }
        foreach ($guardians as $gi => [$name, $local, $sid, $rel]) {
            $gid = $this->user($name, $local);
            $this->db->table('users')->where('id', $gid)->update(['doc_type' => 'CC', 'doc_number' => '24' . sprintf('%06d', $gi + 1)]);
            $member($gid, 'guardian', mt_rand(0, 2) ? $this->ts('-' . mt_rand(1, 6) . ' days') : null);
            $this->ins('guardian_links', ['guardian_id' => $gid, 'student_id' => $sid, 'relationship' => $rel, 'created_by' => $coord, 'created_at' => $this->now, 'updated_at' => $this->now]);
        }

        /* ---------- Cuadernos ---------- */
        foreach ($off as $s => $byGroup) {
            foreach ($byGroup as $g => $o) {
                $this->notebook($o, $s . ' · ' . $g, self::content($s), $tid[$s]);
            }
        }

        /* ---------- Actividades, entregas e intentos ---------- */
        $rubric = Activities::defaultRubric();
        foreach (array_keys($groups) as $g) {
            $kids  = $students[$g];
            $upper = in_array($g, ['10.º A', '11.º A'], true);
            // Matemáticas: reto cerrado y calificado, evaluación cerrada, reto abierto con pocas entregas, evaluación programada.
            $this->challenge($off['Matemáticas'][$g], $groups[$g], $tid['Matemáticas'], 'Reto: la receta para 30', 'Ajustar una receta para 4 personas a 30 usando fracciones y proporciones.', '-24 days', '-12 days', $kids, 'graded', $rubric);
            $this->quiz($off['Matemáticas'][$g], $groups[$g], $tid['Matemáticas'], 'Evaluación: números enteros', '-18 days', '-15 days', $kids, self::qIntegers(), 'graded');
            $this->challenge($off['Matemáticas'][$g], $groups[$g], $tid['Matemáticas'], 'Reto: el presupuesto de la salida', 'Calcular el costo por estudiante de una salida pedagógica con descuentos.', '-3 days', '+5 days', $kids, 'few', $rubric);
            $this->quiz($off['Matemáticas'][$g], $groups[$g], $tid['Matemáticas'], 'Evaluación: fracciones', '+3 days', '+4 days', $kids, self::qFractions(), 'none');
            // Ciencias: la célula (6.º–9.º) o la materia (10.º–11.º).
            if ($upper) {
                $this->quiz($off['Química'][$g], $groups[$g], $tid['Química'], 'Evaluación: la materia y sus cambios', '-10 days', '-6 days', $kids, self::qMatter(), 'mixed');
                $this->challenge($off['Física'][$g], $groups[$g], $tid['Física'], 'Reto: la velocidad de mi bicicleta', 'Medir distancia y tiempo para calcular una velocidad media.', '-30 days', '-16 days', $kids, 'graded', $rubric);
            } else {
                $this->quiz($off['Biología'][$g], $groups[$g], $tid['Biología'], 'Evaluación: la célula', '-10 days', '-6 days', $kids, self::qCell(), 'mixed');
                $this->challenge($off['Biología'][$g], $groups[$g], $tid['Biología'], 'Reto: maqueta de la célula', 'Construir y explicar una maqueta de la célula animal o vegetal.', '-30 days', '-16 days', $kids, 'graded', $rubric);
            }
            $this->challenge($off['Lengua castellana'][$g], $groups[$g], $tid['Lengua castellana'], 'Reto: mi primer cuento', 'Escribir un cuento corto con inicio, nudo y desenlace.', '-20 days', '-9 days', $kids, 'unreviewed', $rubric);
            $this->challenge($off['Inglés'][$g], $groups[$g], $tid['Inglés'], 'Challenge: my daily routine', 'Write and record a short description of your daily routine.', '-6 days', '+2 days', $kids, 'recent', $rubric);
            $this->challenge($off['Ciencias sociales'][$g], $groups[$g], $tid['Ciencias sociales'], 'Reto: línea de tiempo de mi región', 'Elaborar una línea de tiempo con 6 hechos históricos del Eje Cafetero.', '-28 days', '-14 days', $kids, 'graded', $rubric);
        }
        $this->ins('activities', ['offering_id' => $off['Matemáticas']['8.º A'], 'kind' => 'challenge', 'title' => 'Reto: ecuaciones en la tienda', 'status' => 'draft', 'steps' => '[]', 'hints' => '[]',
            'rubric' => json_encode($rubric, JSON_UNESCAPED_UNICODE), 'difficulty' => 'medium', 'mode' => 'individual', 'ai_mode' => 'guide', 'points' => 100, 'retries' => 1,
            'created_by' => $tid['Matemáticas'], 'updated_by' => $tid['Matemáticas'], 'created_at' => $this->now, 'updated_at' => $this->now]);

        /* ---------- Agenda, biblioteca, IA y avisos ---------- */
        foreach ([['Matemáticas', '7.º A', 'Traer regla, compás y transportador', '+1 day', '07:00:00'], ['Biología', '7.º A', 'Salida al jardín botánico', '+6 days', '08:00:00'],
            ['Inglés', '7.º A', 'Spelling bee: practicar las palabras de la unidad 3', '+3 days', null], ['Educación física', '7.º A', 'Traer ropa deportiva y botella de agua', '+2 days', null],
            ['Lengua castellana', '8.º A', 'Leer los capítulos 1 a 3 de la novela', '+4 days', null], ['Química', '10.º A', 'Laboratorio: traer bata y gafas', '+2 days', '09:00:00']] as [$s, $g, $title, $when, $time]) {
            $this->ins('agenda_entries', ['created_by' => $tid[$s], 'offering_id' => $off[$s][$g], 'title' => $title, 'entry_date' => date('Y-m-d', strtotime($when)), 'entry_time' => $time,
                'created_at' => $this->now, 'updated_at' => $this->now]);
        }
        $bank = $this->ins('question_banks', ['subject_id' => $subj['Matemáticas'], 'name' => 'Fracciones · 7.º', 'description' => 'Preguntas de fracciones equivalentes, suma y resta.',
            'network_shared' => 0, 'created_by' => $tid['Matemáticas'], 'updated_by' => $tid['Matemáticas'], 'created_at' => $this->now, 'updated_at' => $this->now]);
        foreach (array_merge(self::qFractions(), [['single', '¿Cuál fracción es mayor?', ['3/4', '2/3', '5/8'], [0], 1, 'Comparación']]) as $i => $q) {
            $this->ins('bank_questions', ['bank_id' => $bank, 'type' => $q[0], 'text' => $q[1], 'options' => json_encode($q[2], JSON_UNESCAPED_UNICODE), 'correct' => json_encode($q[3]),
                'points' => $q[4], 'topic' => $q[5], 'difficulty' => ['easy', 'medium', 'hard'][$i % 3], 'sort_order' => $i, 'created_at' => $this->now, 'updated_at' => $this->now]);
        }
        $this->ins('library_resources', ['kind' => 'link', 'title' => 'Manual de convivencia (demostración)', 'description' => 'Enlace de ejemplo', 'url' => 'https://example.org/manual-de-convivencia',
            'created_by' => $coord, 'created_at' => $this->now, 'updated_at' => $this->now]);
        if ($this->fresh) { // consumo de IA de ejemplo: solo la primera vez (la restauración conserva el consumo real del mes)
            foreach ([['Matemáticas', 42000], ['Biología', 18500], ['Inglés', 9800]] as [$s, $tokens]) {
                for ($i = 0; $i < 6; $i++) {
                    $t = (int) ($tokens / 6);
                    $this->ins('ai_usage', ['user_id' => $tid[$s], 'bucket' => 'teacher', 'feature' => $i % 2 ? 'questions' : 'copilot', 'model' => 'claude-haiku-5-5', 'status' => 'done',
                        'reserved_tokens' => $t, 'input_tokens' => (int) ($t * 0.7), 'output_tokens' => (int) ($t * 0.3), 'tokens' => $t, 'cost_usd' => round(($t * 0.7 * 0.10 + $t * 0.3 * 0.50) / 1e6, 6),
                        'latency_ms' => 1800, 'created_at' => $this->ts('-' . ($i + 1) . ' days'), 'updated_at' => $this->now]);
                }
            }
        }
        $mariana = $students['7.º A'][0]['id'];
        foreach ([['grade', 'Calificaron «Reto: la receta para 30»', 'Matemáticas · Mira la retroalimentación de tu docente.', '-11 days'],
            ['activity', 'Nuevo reto: el presupuesto de la salida', 'Matemáticas · Cierra pronto', '-3 days'], ['agenda', 'Nueva nota en tu agenda: Traer regla, compás y transportador', 'Matemáticas', '-1 hour']] as [$kind, $title, $body, $when]) {
            $this->ins('notifications', ['user_id' => $mariana, 'kind' => $kind, 'title' => $title, 'body' => $body, 'url' => $kind === 'agenda' ? 'estudiante/agenda' : 'estudiante', 'created_at' => $this->ts($when)]);
        }
        $this->communications($rector, $coord, $tid['Matemáticas'], $students);

        echo self::NAME . ' ' . ($this->fresh ? 'cargado' : 'restaurado') . " (id {$this->iid}): " . count($groups) . ' grupos, ' . count($subj) . ' asignaturas, '
            . array_sum(array_map('count', $students)) . ' estudiantes. Cuentas: ' . implode(', ', array_column(Demo::ACCOUNTS, 0)) . "\n";
    }

    /** Mensajes, noticias y eventos de ejemplo. */
    private function communications(int $rector, int $coord, int $jorge, array $students): void
    {
        $db  = $this->db;
        $msg = function (int $from, string $subject, string $body, string $audience, array $to, string $when, bool $important = false): void {
            $id = $this->ins('messages', ['sender_id' => $from, 'subject' => $subject, 'body' => $body, 'audience' => $audience, 'priority' => $important ? 'important' : 'normal',
                'recipients' => count($to), 'created_at' => $this->ts($when)]);
            foreach ($to as $i => $u) {
                $this->ins('message_recipients', ['message_id' => $id, 'user_id' => $u, 'read_at' => $i % 3 ? $this->ts($when . ' +' . ($i + 1) . ' hours') : null]);
            }
        };
        $guardians = array_map('intval', array_column($db->table('guardian_links')->select('guardian_id')->where('institution_id', $this->iid)->get()->getResultArray(), 'guardian_id'));
        $teachers  = array_map('intval', array_column($db->table('teaching_assignments')->distinct()->select('user_id')->where('institution_id', $this->iid)->get()->getResultArray(), 'user_id'));
        $msg($rector, 'Entrega de informes del periodo', "Estimadas familias:\n\nLes recordamos que la entrega de informes será el próximo viernes de 7:00 a 9:00 a. m. en el auditorio. Es importante su asistencia.\n\nCordialmente,\nRectoría", 'Todas las familias', $guardians, '-2 days', true);
        $msg($coord, 'Planeación de la semana cultural', "Docentes:\n\nPor favor envíen antes del miércoles la propuesta de actividad de su área para la semana cultural.\n\nGracias.", 'Todos los docentes', $teachers, '-1 day');
        $msg($jorge, 'Repaso de fracciones en casa', "Hola, chicos:\n\nEsta semana repasaremos fracciones equivalentes. Revisen la página «Fracciones equivalentes» de su cuaderno antes de la evaluación.\n\nProfe Jorge", '7.º A (estudiantes)', array_column($students['7.º A'], 'id'), '-5 hours');

        $news = function (string $title, string $summary, string $body, string $audience, bool $pinned, string $when, string $status = 'published') use ($coord): void {
            $this->ins('news', ['author_id' => $coord, 'title' => $title, 'summary' => $summary, 'body' => $body, 'audience' => $audience, 'pinned' => $pinned ? 1 : 0, 'status' => $status, 'is_public' => $status === 'published' ? 1 : 0,
                'published_at' => $status === 'published' ? $this->ts($when) : null, 'created_at' => $this->ts($when), 'updated_at' => $this->ts($when)]);
        };
        $news('Feria de la ciencia ' . date('Y'), 'Los estudiantes presentarán sus proyectos de investigación ante la comunidad.', "Este año la feria de la ciencia reúne más de veinte proyectos de todos los grados.\n\nLas familias están invitadas a visitar los stands en el coliseo, de 8:00 a. m. a 12:00 m.", 'all', true, '-3 days');
        $news('Resultados de los juegos intercolegiados', 'Nuestro equipo de microfútbol obtuvo el segundo lugar en la fase municipal.', "Felicitamos a los estudiantes que representaron al colegio en los juegos intercolegiados.", 'student,guardian,teacher', false, '-8 days');
        $news('Calendario de cierre del año escolar', 'Fechas de recuperaciones, entrega de informes y vacaciones.', "Compartimos el calendario de cierre del año escolar.", 'all', false, '-1 day', 'draft');

        $ev = function (string $title, string $cat, string $day, ?string $t1, ?string $t2, ?string $endDay, ?string $place, string $aud, string $desc) use ($coord): void {
            $d1 = date('Y-m-d', strtotime($day));
            $this->ins('events', ['title' => $title, 'category' => $cat, 'location' => $place, 'description' => $desc, 'audience' => $aud, 'all_day' => $t1 ? 0 : 1, 'is_public' => $aud === 'all' ? 1 : 0,
                'starts_at' => $d1 . ' ' . ($t1 ?? '00:00') . ':00', 'ends_at' => $endDay ? date('Y-m-d', strtotime($endDay)) . ' 23:59:00' : ($t2 ? $d1 . ' ' . $t2 . ':00' : null),
                'created_by' => $coord, 'created_at' => $this->now, 'updated_at' => $this->now]);
        };
        $ev('Entrega de informes del periodo', 'meeting', '+3 days', '07:00', '09:00', null, 'Auditorio', 'guardian,teacher,staff', 'Las familias recogen el informe con el director de grupo.');
        $ev('Feria de la ciencia', 'academic', '+5 days', '08:00', '12:00', null, 'Coliseo', 'all', 'Presentación de proyectos de todos los grados.');
        $ev('Semana cultural', 'cultural', '+9 days', null, null, '+11 days', 'Todo el colegio', 'all', 'Muestras artísticas, danzas y gastronomía de la región.');
        $ev('Día de la familia (sin clases)', 'holiday', '+15 days', null, null, null, null, 'all', 'No hay clases. ¡Disfruten en familia!');
        $ev('Jornada deportiva interclases', 'sports', '-4 days', '07:30', '12:00', null, 'Canchas', 'student,teacher,staff', 'Encuentros de microfútbol y voleibol entre grupos.');

        $this->site();
    }

    /** Página pública del colegio (agendaescolar.co/colegio-horizonte), con contenido completo y ya publicada. */
    private function site(): void
    {
        $inst = $this->db->table('institutions')->where('id', $this->iid)->get()->getRowArray();
        $site = \App\Libraries\SchoolSite::clean([
            'hero'    => ['title' => 'Colegio Horizonte', 'tagline' => "Formamos personas curiosas, solidarias y felices de aprender.\nDe sexto a once, en el corazón de Pereira.", 'cta' => 'Conoce el colegio'],
            'about'   => [
                'heading' => 'Un colegio para crecer en comunidad',
                'text'    => "Desde hace más de veinte años acompañamos a niñas, niños y jóvenes en su formación académica y humana. Nuestras aulas son espacios para preguntar, experimentar y crear, con docentes cercanos que conocen a cada estudiante por su nombre.\n\nTrabajamos de la mano con las familias: comparten el día a día del colegio desde el celular, con la agenda, las noticias y el progreso de sus hijos.",
                'mission' => 'Ofrecer una educación integral y de calidad que desarrolle el pensamiento crítico, la creatividad y los valores ciudadanos de nuestros estudiantes, en alianza con sus familias.',
                'vision'  => 'En 2030 seremos reconocidos en la región por la innovación pedagógica, el uso responsable de la tecnología y la formación de jóvenes comprometidos con su comunidad.',
                'values'  => ['Respeto', 'Responsabilidad', 'Curiosidad', 'Solidaridad', 'Honestidad'],
            ],
            'admissions' => ['dates' => 'Inscripciones para 2027 abiertas hasta el 15 de diciembre'],
            'contact' => [
                'heading' => 'Ven a conocernos', 'intro' => 'Con gusto te atendemos para resolver tus preguntas sobre admisiones, horarios y vida escolar.',
                'address' => 'Carrera 12 # 34-56, barrio Los Álamos', 'phone' => '+57 606 333 4455', 'whatsapp' => '+57 300 123 4567',
                'email' => 'contacto@' . self::DOMAIN, 'hours' => 'Lunes a viernes, 7:00 a. m. a 3:00 p. m.',
            ],
        ], $inst);
        $json = json_encode($site, JSON_UNESCAPED_UNICODE);
        $this->db->table('institution_sites')->insert(['institution_id' => $this->iid, 'draft' => $json, 'published' => $json, 'published_at' => $this->now, 'updated_at' => $this->now]);
    }

    /* ---------- Borrado (restauración y limpieza) ---------- */

    /**
     * Borra todo lo de un colegio de demostración: sus filas en las tablas con institution_id, los permisos de sus roles,
     * sus cuentas de demostración (is_demo) y sus archivos subidos. Con $keepInstitution conserva la institución y su
     * consumo de IA (ai_usage) para que el cupo mensual no se reinicie con cada restauración.
     */
    public static function wipe(\CodeIgniter\Database\BaseConnection $db, int $iid, bool $keepInstitution = false): void
    {
        $users   = array_column($db->table('institution_users')->select('user_id')->where('institution_id', $iid)->get()->getResultArray(), 'user_id');
        $roleIds = array_column($db->table('roles')->select('id')->where('institution_id', $iid)->get()->getResultArray(), 'id'); // antes de borrar los roles
        $db->query('SET FOREIGN_KEY_CHECKS = 0');
        if ($roleIds) {
            $db->table('role_permissions')->whereIn('role_id', $roleIds)->delete();
        }
        foreach ($db->listTables() as $t) {
            if ($t === 'institutions' || ($keepInstitution && $t === 'ai_usage')) {
                continue;
            }
            if (in_array('institution_id', $db->getFieldNames($t), true)) {
                $db->table($t)->where('institution_id', $iid)->delete();
            }
        }
        $demo = $users ? array_column($db->table('users')->select('id')->whereIn('id', $users)->where('is_demo', 1)->where('is_superadmin', 0)->get()->getResultArray(), 'id') : [];
        if ($demo) {
            foreach (['agenda_done', 'notification_prefs', 'user_recovery_codes', 'notifications'] as $t) {
                if ($db->tableExists($t)) {
                    $db->table($t)->whereIn('user_id', $demo)->delete();
                }
            }
            $db->table('users')->whereIn('id', $demo)->delete();
        }
        if (! $keepInstitution) {
            $db->table('institutions')->where('id', $iid)->delete();
        }
        $db->query('SET FOREIGN_KEY_CHECKS = 1');
        foreach (['notebooks', 'resources', 'news', 'sites'] as $dir) { // archivos subidos por los visitantes
            self::rmdir(WRITEPATH . 'uploads/' . $dir . '/' . $iid);
        }
    }

    private static function rmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }

    /* ---------- Piezas ---------- */

    private static function slug(string $s): string
    {
        return strtolower(preg_replace('/[^a-z]/i', '', strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N'])));
    }

    /** Crea el cuaderno con sus secciones y páginas publicadas (y su versión publicada). Un bloque: [tipo, texto] o [tipo, datos]. */
    private function notebook(int $offering, string $title, array $sections, int $author): void
    {
        $ws = $this->ins('workspaces', ['offering_id' => $offering, 'title' => $title, 'status' => 'active', 'created_at' => $this->now, 'updated_at' => $this->now]);
        foreach ($sections as $si => [$secName, $pages]) {
            $sec = $this->ins('sections', ['workspace_id' => $ws, 'name' => $secName, 'sort_order' => $si + 1, 'created_at' => $this->now, 'updated_at' => $this->now]);
            foreach ($pages as $pi => [$ptitle, $objective, $blocks]) {
                $when = $this->ts('-' . (40 - 10 * $si - 3 * $pi) . ' days');
                $page = $this->ins('pages', ['workspace_id' => $ws, 'section_id' => $sec, 'title' => $ptitle, 'kind' => 'page', 'status' => 'published', 'objective' => $objective,
                    'sort_order' => $pi + 1, 'published_at' => $when, 'created_by' => $author, 'updated_by' => $author, 'created_at' => $when, 'updated_at' => $when]);
                $data = static fn (array $b): array => $b[0] === 'divider' ? [] : (is_array($b[1]) ? $b[1] : ['text' => $b[1]]);
                foreach ($blocks as $bi => $b) {
                    $this->ins('page_blocks', ['page_id' => $page, 'type' => $b[0], 'data' => json_encode($data($b), JSON_UNESCAPED_UNICODE), 'sort_order' => $bi, 'created_at' => $when, 'updated_at' => $when]);
                }
                $this->ins('content_versions', ['page_id' => $page, 'user_id' => $author, 'created_at' => $when, 'snapshot' => json_encode(['title' => $ptitle, 'objective' => $objective,
                    'blocks' => array_map(static fn ($b) => ['type' => $b[0], 'data' => $data($b)], $blocks)], JSON_UNESCAPED_UNICODE)]);
            }
        }
    }

    private function assignment(int $activity, int $group, int $by, string $starts, string $due): int
    {
        return $this->ins('activity_assignments', ['activity_id' => $activity, 'group_id' => $group, 'starts_at' => $this->ts($starts . ' 07:00'), 'due_at' => date('Y-m-d 23:59:00', strtotime($due)),
            'allow_late' => 1, 'created_by' => $by, 'created_at' => $this->now, 'updated_at' => $this->now]);
    }

    /** Reto con su asignación y entregas. $mode: graded, unreviewed, recent, few. */
    private function challenge(int $offering, int $group, int $by, string $title, string $instructions, string $starts, string $due, array $kids, string $mode, array $rubric): void
    {
        $act = $this->ins('activities', ['offering_id' => $offering, 'kind' => 'challenge', 'title' => $title, 'objective' => 'Aplicar lo aprendido en una situación real.',
            'instructions' => $instructions, 'deliverable' => 'Escribe tu procedimiento y tu respuesta, y explica cómo la verificaste.',
            'steps' => json_encode([['title' => 'Lee y subraya los datos', 'text' => 'Identifica qué te dan y qué te piden.'], ['title' => 'Plantea tu estrategia', 'text' => 'Decide cómo resolverlo.'],
                ['title' => 'Resuelve y verifica', 'text' => 'Comprueba que tu respuesta tenga sentido.']], JSON_UNESCAPED_UNICODE),
            'hints' => json_encode([['text' => 'Empieza por lo que ya sabes.'], ['text' => 'Haz un dibujo o una tabla.']], JSON_UNESCAPED_UNICODE),
            'rubric' => json_encode($rubric, JSON_UNESCAPED_UNICODE), 'difficulty' => 'medium', 'mode' => 'individual', 'ai_mode' => 'guide', 'points' => 100, 'retries' => 1,
            'status' => 'published', 'created_by' => $by, 'updated_by' => $by, 'created_at' => $this->ts($starts), 'updated_at' => $this->ts($starts)]);
        $x     = $this->assignment($act, $group, $by, $starts, $due);
        $dueTs = strtotime(date('Y-m-d 23:59:00', strtotime($due)));
        foreach ($kids as $k) {
            $p       = $k['ability'];
            $submits = match ($mode) { 'few' => mt_rand(0, 100) < 30, 'recent' => mt_rand(0, 100) < 70, default => mt_rand(0, 100) < (int) (($p + 0.12) * 100) };
            if (! $submits) {
                continue;
            }
            $subAt = match ($mode) {
                'unreviewed' => date('Y-m-d H:i:s', strtotime('-' . mt_rand(10, 12) . ' days ' . mt_rand(8, 20) . ':' . mt_rand(10, 59))),
                'recent', 'few' => $this->ts('-' . mt_rand(1, 40) . ' hours'),
                default => date('Y-m-d H:i:s', $dueTs - mt_rand(3600, 6 * 86400)),
            };
            $row = ['assignment_id' => $x, 'user_id' => $k['id'], 'answer' => 'Mi respuesta: organicé los datos en una tabla, apliqué la operación y verifiqué el resultado comparándolo con el enunciado.',
                'steps_done' => json_encode([true, true, $p > 0.6]), 'attempt' => 1, 'submitted_at' => $subAt, 'is_late' => strtotime($subAt) > $dueTs ? 1 : 0, 'created_at' => $subAt, 'updated_at' => $subAt];
            if ($mode === 'graded') {
                $levels = array_map(static fn () => max(0, min(3, (int) round((1 - $p) * 3 + (mt_rand(-50, 50) / 100)))), $rubric['criteria']);
                $score  = 0.0;
                foreach ($rubric['criteria'] as $i => $c) {
                    $score += $c['weight'] * (3 - $levels[$i]) / 3;
                }
                $grade = round(1 + $score * 4 / 100, 1);
                $row += ['status' => 'reviewed', 'levels' => json_encode($levels), 'grade' => $grade, 'reviewed_by' => $by, 'reviewed_at' => date('Y-m-d H:i:s', $dueTs + 2 * 86400),
                    'feedback' => $grade >= 4 ? 'Excelente trabajo: tu procedimiento es claro y verificaste el resultado.' : ($grade >= 3 ? 'Buen trabajo. Revisa el paso del procedimiento donde tuviste dudas y explica mejor cómo verificaste.' : 'Vas en camino. Repasemos juntos la estrategia: identifica primero los datos y lo que te piden.')];
            } else {
                $row += ['status' => 'submitted'];
            }
            $this->ins('submissions', $row);
        }
    }

    /** Evaluación con preguntas, asignación e intentos. $mode: graded, mixed (una abierta, algunas por revisar), none (programada). */
    private function quiz(int $offering, int $group, int $by, string $title, string $starts, string $due, array $kids, array $questions, string $mode): void
    {
        $act = $this->ins('activities', ['offering_id' => $offering, 'kind' => 'quiz', 'title' => $title, 'instructions' => 'Lee con atención cada pregunta. Tienes un intento.',
            'steps' => '[]', 'hints' => '[]', 'ai_mode' => 'off', 'time_limit' => 30, 'max_attempts' => 1, 'shuffle' => 0, 'results_mode' => 'after_close',
            'status' => 'published', 'created_by' => $by, 'updated_by' => $by, 'created_at' => $this->ts($starts), 'updated_at' => $this->ts($starts)]);
        $qids = [];
        foreach ($questions as $i => $q) {
            $qids[] = ['id' => $this->ins('questions', ['activity_id' => $act, 'type' => $q[0], 'text' => $q[1], 'options' => json_encode($q[2], JSON_UNESCAPED_UNICODE),
                'correct' => json_encode($q[3]), 'points' => $q[4], 'topic' => $q[5], 'sort_order' => $i, 'created_at' => $this->now, 'updated_at' => $this->now])] + ['q' => $q];
        }
        $x = $this->assignment($act, $group, $by, $starts, $due);
        if ($mode === 'none') {
            return;
        }
        foreach ($kids as $k) {
            if (mt_rand(0, 100) > (int) (($k['ability'] + 0.15) * 100)) {
                continue;
            }
            $answers = [];
            $auto    = 0.0;
            $max     = 0.0;
            $open    = false;
            foreach ($qids as $item) {
                [$type, , $opts, $correct, $pts] = $item['q'];
                $max  += $pts;
                $right = mt_rand(0, 100) < (int) ($k['ability'] * 100);
                if ($type === 'long') {
                    $answers[$item['id']] = 'Mi explicación con mis palabras, con un ejemplo de la vida diaria.';
                    $open                 = true;
                } elseif ($type === 'numeric') {
                    $answers[$item['id']] = (string) ($right ? $correct['value'] : $correct['value'] + 2);
                    $auto                += $right ? $pts : 0;
                } else {
                    $answers[$item['id']] = (string) ($right ? $correct[0] : (($correct[0] + 1) % max(2, count($opts))));
                    $auto                += $right ? $pts : 0;
                }
            }
            $fin     = date('Y-m-d H:i:s', strtotime($due) - mt_rand(1, 3) * 86400 + mt_rand(28800, 43200));
            $pending = $open && mt_rand(0, 1);
            $manual  = $open && ! $pending ? [$qids[count($qids) - 1]['id'] => (string) round($qids[count($qids) - 1]['q'][4] * $k['ability'], 1)] : [];
            $score   = $auto + array_sum(array_map('floatval', $manual));
            $this->ins('attempts', ['assignment_id' => $x, 'user_id' => $k['id'], 'number' => 1, 'status' => $pending ? 'pending_review' : 'graded', 'answers' => json_encode($answers),
                'manual' => json_encode((object) $manual), 'auto_score' => $auto, 'score' => $pending ? null : $score, 'max_score' => $max,
                'grade' => $pending ? null : round(1 + 4 * $score / $max, 1), 'started_at' => date('Y-m-d H:i:s', strtotime($fin) - 1500), 'finished_at' => $fin, 'over_time' => 0,
                'reviewed_by' => $pending ? null : $by, 'created_at' => $fin, 'updated_at' => $fin]);
        }
    }

    /* ---------- Contenido ---------- */

    /** Preguntas: [tipo, texto, opciones, correcta, puntos, tema]. */
    private static function qIntegers(): array
    {
        return [
            ['single', '¿Cuál es el resultado de −7 + 4?', ['−3', '3', '−11', '11'], [0], 1, 'Suma de enteros'],
            ['single', '¿Qué número está más a la izquierda en la recta numérica?', ['−2', '−9', '0', '5'], [1], 1, 'Recta numérica'],
            ['numeric', 'Calcula (−3) × (−4).', [], ['value' => 12, 'tolerance' => 0], 1, 'Multiplicación'],
            ['truefalse', 'El opuesto de −8 es 8.', ['Verdadero', 'Falso'], [0], 1, 'Opuestos'],
            ['single', 'La temperatura era −2 °C y bajó 5 °C. ¿Cuál es la nueva temperatura?', ['3 °C', '−7 °C', '−3 °C', '7 °C'], [1], 1, 'Problemas'],
        ];
    }

    private static function qFractions(): array
    {
        return [
            ['single', '¿Qué fracción es equivalente a 2/3?', ['4/6', '3/4', '2/5', '6/8'], [0], 1, 'Fracciones equivalentes'],
            ['single', '¿Cuánto es 1/4 + 2/4?', ['3/8', '3/4', '1/2', '2/16'], [1], 1, 'Suma'],
            ['numeric', 'Simplifica 8/12 y escribe el denominador.', [], ['value' => 3, 'tolerance' => 0], 1, 'Simplificación'],
            ['truefalse', '5/10 es lo mismo que 1/2.', ['Verdadero', 'Falso'], [0], 1, 'Fracciones equivalentes'],
        ];
    }

    private static function qCell(): array
    {
        return [
            ['single', '¿Qué organelo produce energía para la célula?', ['Mitocondria', 'Ribosoma', 'Vacuola', 'Núcleo'], [0], 1, 'Organelos'],
            ['single', '¿Qué estructura tienen las células vegetales y no las animales?', ['Membrana', 'Pared celular', 'Citoplasma', 'Ribosomas'], [1], 1, 'Tipos de célula'],
            ['truefalse', 'Todos los seres vivos están formados por células.', ['Verdadero', 'Falso'], [0], 1, 'Teoría celular'],
            ['long', 'Explica con tus palabras las partes principales de la célula y su función.', [], [], 3, 'Partes de la célula'],
        ];
    }

    private static function qMatter(): array
    {
        return [
            ['single', '¿Cuál es un cambio químico?', ['Hervir agua', 'Oxidarse un clavo', 'Cortar papel', 'Derretir hielo'], [1], 1, 'Cambios químicos'],
            ['single', '¿En qué estado la materia tiene forma y volumen propios?', ['Sólido', 'Líquido', 'Gaseoso', 'Plasma'], [0], 1, 'Estados de la materia'],
            ['truefalse', 'La masa se conserva en una reacción química.', ['Verdadero', 'Falso'], [0], 1, 'Conservación de la masa'],
            ['long', 'Explica la diferencia entre un cambio físico y un cambio químico, con un ejemplo de cada uno.', [], [], 3, 'Cambios de la materia'],
        ];
    }

    /** Secciones y páginas de cada asignatura: [[sección, [[título, objetivo, bloques], …]], …]. */
    private static function content(string $subject): array
    {
        $page = static fn (string $t, string $obj, string $intro, string $key, string $tip, ?string $formula = null, array $extra = []) => [$t, $obj, array_values(array_filter(array_merge([
            ['heading', $t], ['text', $intro], $formula ? ['formula', $formula] : null, ['callout', $tip], ['divider', ''], ['text', $key],
        ], $extra)))];
        $one = static fn (string $unit, string $t, string $obj, string $intro, string $key, string $tip) => [[$unit, [$page($t, $obj, $intro, $key, $tip)]]];

        return match ($subject) {
            'Matemáticas' => [
                ['Unidad 1 · Números enteros', [
                    $page('La recta numérica', 'Ubicar y comparar números enteros en la recta.', 'Los números enteros incluyen los positivos, los negativos y el cero. En la recta numérica, los negativos están a la izquierda del cero.', 'Un número es mayor cuanto más a la derecha esté en la recta. Por eso −2 es mayor que −9.', 'Piensa en un termómetro: −9 °C es más frío que −2 °C.'),
                    $page('Operaciones con enteros', 'Sumar, restar y multiplicar enteros.', 'Para sumar enteros con el mismo signo, sumamos y conservamos el signo. Con signos distintos, restamos y dejamos el signo del de mayor valor absoluto.', 'Ley de los signos para multiplicar:', 'Usa la recta numérica para comprobar tus sumas.', '(−3) × (−4) = 12', [
                        ['table', ['header' => true, 'rows' => [['Signos', 'Resultado', 'Ejemplo'], ['(+) × (+)', '+', '3 × 4 = 12'], ['(−) × (−)', '+', '(−3) × (−4) = 12'], ['(+) × (−)', '−', '3 × (−4) = −12']], 'caption' => 'Ley de los signos']],
                        ['question', ['question' => '¿Cuál es el resultado de (−5) × (−2)?', 'options' => ['−10', '10', '−7', '7'], 'correct' => 1, 'explanation' => 'Menos por menos da más: (−5) × (−2) = 10.']],
                    ]),
                ]],
                ['Unidad 2 · Fracciones', [
                    $page('Fracciones equivalentes', 'Reconocer y construir fracciones equivalentes.', 'Dos fracciones son equivalentes cuando representan la misma cantidad, aunque se escriban distinto.', 'Para obtener una fracción equivalente, multiplica o divide el numerador y el denominador por el mismo número.', 'Dibuja dos pizzas iguales: la mitad de una es igual a dos cuartos de la otra.', '1/2 = 2/4 = 4/8', [
                        ['question', ['question' => '¿Qué fracción es equivalente a 3/4?', 'options' => ['6/8', '3/8', '4/3'], 'correct' => 0, 'explanation' => 'Multiplicamos numerador y denominador por 2: 3/4 = 6/8.']],
                    ]),
                    $page('Suma y resta de fracciones', 'Sumar y restar fracciones con igual y distinto denominador.', 'Si los denominadores son iguales, sumamos los numeradores. Si son distintos, primero buscamos un denominador común.', 'Ejemplo: 1/3 + 1/6 = 2/6 + 1/6 = 3/6 = 1/2.', 'Simplifica siempre el resultado al final.', '1/4 + 2/4 = 3/4'),
                ]],
            ],
            'Biología' => [
                ['Unidad 1 · La célula', [
                    $page('La teoría celular', 'Explicar los postulados de la teoría celular.', 'La célula es la unidad básica de la vida: todos los seres vivos están formados por una o más células.', 'Toda célula proviene de otra célula. La célula es la unidad estructural y funcional de los seres vivos.', 'Observa la piel de una cebolla al microscopio: verás células vegetales.'),
                    $page('Partes de la célula', 'Identificar la membrana, el citoplasma, el núcleo y los organelos.', 'La membrana protege y controla lo que entra y sale. El citoplasma contiene los organelos. El núcleo guarda la información genética.', 'La célula vegetal además tiene pared celular y cloroplastos.', 'Haz una tabla con cada organelo y su función.', null, [
                        ['table', ['header' => true, 'rows' => [['Organelo', 'Función'], ['Núcleo', 'Guarda el ADN y controla la célula'], ['Mitocondria', 'Produce energía'], ['Ribosoma', 'Fabrica proteínas'], ['Cloroplasto', 'Hace la fotosíntesis (solo en plantas)']], 'caption' => 'Organelos y su función']],
                        ['question', ['question' => '¿Qué organelo produce la energía de la célula?', 'options' => ['Núcleo', 'Mitocondria', 'Ribosoma'], 'correct' => 1, 'explanation' => 'La mitocondria transforma los nutrientes en energía que la célula puede usar.']],
                    ]),
                ]],
            ],
            'Química' => $one('Unidad 1 · La materia', 'Propiedades y cambios de la materia', 'Distinguir cambios físicos y químicos.', 'La materia es todo lo que tiene masa y ocupa un lugar. Puede cambiar de estado (cambio físico) o transformarse en otra sustancia (cambio químico).', 'En un cambio químico se forman sustancias nuevas: por ejemplo, cuando se oxida un clavo.', 'Busca tres cambios químicos en tu cocina.'),
            'Física' => $one('Unidad 1 · Movimiento', 'Velocidad y rapidez', 'Calcular la velocidad media de un objeto.', 'La velocidad media es la distancia recorrida dividida entre el tiempo empleado: v = d / t.', 'Si recorres 100 m en 20 s, tu velocidad media es 5 m/s.', 'Mide cuánto tardas en caminar 50 metros y calcula tu velocidad.'),
            'Lengua castellana' => [
                ['Unidad 1 · La narración', [
                    $page('El cuento y sus partes', 'Reconocer el inicio, el nudo y el desenlace.', 'El cuento es una narración breve con pocos personajes. Tiene tres partes: inicio, nudo y desenlace.', 'Antes de escribir, piensa: ¿quién?, ¿dónde?, ¿qué problema tiene?, ¿cómo lo resuelve?', 'Lee «El gato con botas» y marca sus tres partes.'),
                    $page('Los conectores', 'Usar conectores para ordenar un texto.', 'Los conectores unen ideas y ayudan a que el lector siga la historia: primero, luego, después, finalmente, sin embargo, por eso.', 'Un buen cuento usa conectores de tiempo para ordenar los hechos.', 'Subraya los conectores en tu cuento antes de entregarlo.'),
                ]],
            ],
            'Ciencias sociales' => $one('Unidad 1 · Mi región', 'El Eje Cafetero', 'Ubicar el Eje Cafetero y sus departamentos.', 'El Eje Cafetero está formado por Caldas, Risaralda y Quindío. Su paisaje cultural cafetero es Patrimonio de la Humanidad.', 'Pereira, Manizales y Armenia son sus capitales.', 'Busca en el mapa los tres departamentos y sus capitales.'),
            'Filosofía' => $one('Unidad 1 · ¿Qué es la filosofía?', 'Preguntarse por el mundo', 'Reconocer las preguntas filosóficas.', 'La filosofía nace del asombro: preguntarse qué es la verdad, el bien o la justicia.', 'Una pregunta filosófica no se responde con un dato: se piensa, se argumenta y se discute.', 'Escribe una pregunta que te haga pensar y compártela con el grupo.'),
            'Ciencias políticas y económicas' => $one('Unidad 1 · Estado y ciudadanía', 'La Constitución de 1991', 'Conocer los principios de la Constitución.', 'La Constitución Política de Colombia de 1991 define al país como un Estado social de derecho.', 'Los derechos fundamentales están en el título II de la Constitución.', 'Lee el artículo 67: ¿qué dice sobre la educación?'),
            'Inglés' => [
                ['Unit 1 · Daily life', [
                    $page('Daily routines', 'Talk about daily routines using the simple present.', 'We use the simple present for habits: I wake up at 6. She goes to school by bus.', 'Remember: he / she / it + verb with -s (he plays, she watches).', 'Write five sentences about your morning.'),
                    $page('Telling the time', 'Ask and answer about the time.', 'What time is it? It is half past seven. It is a quarter to nine.', 'Use "at" with times: at 7 o\'clock, at noon.', 'Practice with a partner using a clock.'),
                ]],
            ],
            'Educación artística' => $one('Unidad 1 · El color', 'Colores primarios y secundarios', 'Mezclar colores primarios para obtener secundarios.', 'Los colores primarios son rojo, amarillo y azul. Al mezclarlos se obtienen los secundarios: naranja, verde y morado.', 'Rojo + amarillo = naranja; amarillo + azul = verde; azul + rojo = morado.', 'Pinta un círculo cromático con témperas.'),
            'Educación física' => $one('Unidad 1 · Mi cuerpo en movimiento', 'El calentamiento', 'Realizar un calentamiento adecuado.', 'Antes de hacer ejercicio, el calentamiento prepara músculos y articulaciones y evita lesiones.', 'Un buen calentamiento dura entre 5 y 10 minutos e incluye movilidad y trote suave.', 'Diseña tu rutina de calentamiento de 5 ejercicios.'),
            'Ética y valores' => $one('Unidad 1 · Convivencia', 'El respeto', 'Reconocer el respeto en la vida diaria.', 'Respetar es reconocer el valor de los demás y de sus diferencias.', 'Escuchar sin interrumpir también es una forma de respeto.', 'Escribe una situación del colegio donde viste respeto.'),
            'Religión' => $one('Unidad 1 · El sentido de la vida', 'Valores que compartimos', 'Reflexionar sobre los valores comunes.', 'Las grandes tradiciones religiosas comparten valores como la solidaridad, la justicia y el cuidado del otro.', 'La solidaridad es ayudar a quien lo necesita sin esperar nada a cambio.', 'Piensa en una acción solidaria que puedas hacer esta semana.'),
            default => $one('Unidad 1 · Ciudadanía digital', 'Contraseñas seguras', 'Crear contraseñas difíciles de adivinar.', 'Una contraseña segura es larga, mezcla palabras y números, y no se comparte.', 'Usa una frase que puedas recordar y no la uses en varios sitios.', 'Nunca escribas tu contraseña en un papel pegado al computador.'),
        };
    }
}
