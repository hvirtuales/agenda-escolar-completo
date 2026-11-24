<?php

namespace App\Libraries;

use App\Models\BankQuestionModel;
use App\Models\QuestionBankModel;
use App\Models\QuestionModel;
use App\Models\SubjectModel;

/**
 * Bancos de preguntas (documento maestro 4.3). Úsela con service('banks').
 *
 * - Son de la institución: todo docente que crea actividades los ve y los usa. Los edita quien los creó o la
 *   coordinación (academic.structure.manage).
 * - Una evaluación copia las preguntas (desde el editor con «Agregar desde un banco» o con «Evaluación aleatoria»);
 *   editar el banco después no cambia evaluaciones ya creadas.
 */
class Banks
{
    private function db()
    {
        return db_connect();
    }

    private function iid(): int
    {
        return (int) service('tenant')->institutionId();
    }

    private function uid(): int
    {
        return (int) service('auth')->id();
    }

    /** Bancos con asignatura, autor, número de preguntas y de temas. Filtros: subject (id, 0 = generales), q (texto). */
    public function list(array $f = []): array
    {
        $q = $this->db()->table('question_banks b')
            ->select('b.id, b.name, b.description, b.subject_id, b.network_shared, b.created_by, b.updated_at, s.name AS subject, u.name AS author,
                (SELECT COUNT(*) FROM bank_questions q WHERE q.bank_id = b.id) AS questions,
                (SELECT COUNT(DISTINCT q2.topic) FROM bank_questions q2 WHERE q2.bank_id = b.id) AS topics', false)
            ->join('subjects s', 's.id = b.subject_id', 'left')->join('users u', 'u.id = b.created_by', 'left')
            ->where('b.institution_id', $this->iid())->where('b.deleted_at', null);
        if (isset($f['subject']) && $f['subject'] !== '') {
            (int) $f['subject'] === 0 ? $q->where('b.subject_id', null) : $q->where('b.subject_id', (int) $f['subject']);
        }
        if (($f['q'] ?? '') !== '') {
            $q->groupStart()->like('b.name', $f['q'])->orLike('s.name', $f['q'])->groupEnd();
        }

        return array_map(fn ($b) => $b + ['can_edit' => $this->canEdit($b)], $q->orderBy('s.name')->orderBy('b.name')->get()->getResultArray());
    }

    public function get(int $id): ?array
    {
        $b = model(QuestionBankModel::class)->find($id);
        if (! $b) {
            return null;
        }
        $b['subject'] = $b['subject_id'] ? (model(SubjectModel::class)->find($b['subject_id'])['name'] ?? null) : null;

        return $b;
    }

    public function canEdit(?array $bank): bool
    {
        if (! $bank) {
            return false;
        }
        $t = service('tenant');

        return $t->can('academic.structure.manage') || service('auth')->isSuperadmin()
            || ($t->can('activities.create') && (int) $bank['created_by'] === $this->uid());
    }

    /** Preguntas del banco, decodificadas. Filtros opcionales: topic, difficulty, type. */
    public function questions(int $bankId, array $f = []): array
    {
        $m = model(BankQuestionModel::class)->where('bank_id', $bankId);
        foreach (['topic', 'difficulty', 'type'] as $k) {
            if (($f[$k] ?? '') !== '') {
                $k === 'topic' && $f[$k] === '-' ? $m->where('topic', null) : $m->where($k, $f[$k]);
            }
        }

        return array_map(static function ($q) {
            $q['options'] = json_decode((string) $q['options'], true) ?: [];
            $q['correct'] = json_decode((string) $q['correct'], true) ?: [];
            $q['points']  = (float) $q['points'];

            return $q;
        }, $m->orderBy('sort_order')->findAll());
    }

    /** Temas distintos del banco (para filtros). */
    public function topics(int $bankId): array
    {
        return array_column($this->db()->table('bank_questions')->select('topic')->distinct()
            ->where('institution_id', $this->iid())->where('bank_id', $bankId)->where('topic IS NOT NULL', null, false)
            ->orderBy('topic')->get()->getResultArray(), 'topic');
    }

    private function validMeta(array $in): array
    {
        $name = trim((string) ($in['name'] ?? ''));
        $sid  = (int) ($in['subject_id'] ?? 0);
        $err  = [];
        if ($name === '' || mb_strlen($name) > 120) {
            $err['name'] = 'Escriba un nombre de hasta 120 caracteres.';
        }
        if ($sid && ! model(SubjectModel::class)->find($sid)) {
            $err['subject_id'] = 'Esa asignatura no existe.';
        }

        return [$err, ['name' => $name, 'subject_id' => $sid ?: null, 'description' => mb_substr(trim((string) ($in['description'] ?? '')), 0, 2000) ?: null]];
    }

    /** Crea un banco vacío. Devuelve [id] o [errors]. */
    public function create(array $in): array
    {
        [$err, $data] = $this->validMeta($in);
        if ($err) {
            return ['errors' => $err];
        }
        $id = (int) model(QuestionBankModel::class)->insert($data + ['created_by' => $this->uid(), 'updated_by' => $this->uid()]);
        service('audit')->log('bank.create', ['banco' => $data['name']]);

        return ['id' => $id];
    }

    /** Guarda nombre, asignatura, descripción y preguntas (reemplaza el conjunto). Devuelve null o [campo => mensaje]. */
    public function save(int $id, array $in): ?array
    {
        $bank = $this->get($id);
        if (! $this->canEdit($bank)) {
            return ['_' => 'Ese banco no existe o no puede editarlo.'];
        }
        [$err, $data] = $this->validMeta($in);
        if ($err) {
            return $err;
        }
        $clean = Quizzes::cleanQuestions((string) ($in['questions_json'] ?? '[]'));
        if (isset($clean['error'])) {
            return ['questions' => $clean['error']];
        }

        $db = $this->db();
        $db->transStart();
        model(QuestionBankModel::class)->update($id, $data + ['updated_by' => $this->uid()]);
        $db->table('bank_questions')->where('institution_id', $this->iid())->where('bank_id', $id)->delete();
        foreach ($clean as $i => $q) {
            $this->insertQuestion($id, $q, $i);
        }
        $db->transComplete();

        return $db->transStatus() ? null : ['_' => 'No se pudo guardar. Intente de nuevo.'];
    }

    private function insertQuestion(int $bankId, array $q, int $order): void
    {
        model(BankQuestionModel::class)->insert(['bank_id' => $bankId, 'type' => $q['type'], 'text' => $q['text'],
            'options' => json_encode($q['options'], JSON_UNESCAPED_UNICODE), 'correct' => json_encode($q['correct'], JSON_UNESCAPED_UNICODE),
            'points' => $q['points'], 'topic' => $q['topic'] ?: null, 'difficulty' => $q['difficulty'] ?? 'medium', 'sort_order' => $order]);
    }

    public function delete(int $id): ?string
    {
        $bank = $this->get($id);
        if (! $this->canEdit($bank)) {
            return 'Ese banco no existe o no puede eliminarlo.';
        }
        model(QuestionBankModel::class)->delete($id);
        service('audit')->log('bank.delete', ['banco' => $bank['name']]);

        return null;
    }

    /**
     * Agrega al banco las preguntas de una evaluación que la persona puede editar (las que ya estén, iguales, no se
     * repiten). Devuelve [added] o [error].
     */
    public function importFromQuiz(int $bankId, int $activityId): array
    {
        $bank = $this->get($bankId);
        $act  = service('activities')->get($activityId);
        if (! $this->canEdit($bank)) {
            return ['error' => 'Ese banco no existe o no puede editarlo.'];
        }
        if (($act['kind'] ?? '') !== 'quiz' || ! service('activities')->canManage($act)) {
            return ['error' => 'Esa evaluación no existe o no puede usarla.'];
        }
        $have  = array_map(static fn ($q) => $q['type'] . '|' . mb_strtolower(trim($q['text'])), $this->questions($bankId));
        $order = count($have);
        $added = 0;
        foreach (service('quizzes')->questions($activityId) as $q) {
            if (in_array($q['type'] . '|' . mb_strtolower(trim($q['text'])), $have, true)) {
                continue;
            }
            $this->insertQuestion($bankId, $q + ['difficulty' => 'medium'], $order++);
            $added++;
        }
        model(QuestionBankModel::class)->update($bankId, ['updated_by' => $this->uid()]);

        return ['added' => $added];
    }

    /**
     * Crea una evaluación en borrador con N preguntas al azar del banco (filtros opcionales por tema y dificultad).
     * Devuelve [id, count] o [error].
     */
    public function randomQuiz(int $bankId, array $in): array
    {
        $bank = $this->get($bankId);
        if (! $bank) {
            return ['error' => 'Ese banco no existe.'];
        }
        $n    = (int) ($in['count'] ?? 0);
        $pool = $this->questions($bankId, ['topic' => (string) ($in['topic'] ?? ''), 'difficulty' => (string) ($in['difficulty'] ?? '')]);
        if (! $pool) {
            return ['error' => 'No hay preguntas en el banco con ese tema y dificultad.'];
        }
        if ($n < 1 || $n > 100) {
            return ['error' => 'Elija entre 1 y 100 preguntas.'];
        }
        $title = trim((string) ($in['title'] ?? '')) ?: 'Evaluación: ' . $bank['name'];
        $r     = service('quizzes')->create((int) ($in['offering_id'] ?? 0), $title);
        if (isset($r['error'])) {
            return $r;
        }
        shuffle($pool);
        $pick = array_slice($pool, 0, $n);
        usort($pick, static fn ($a, $b) => $a['sort_order'] <=> $b['sort_order']);
        foreach ($pick as $i => $q) {
            model(QuestionModel::class)->insert(['activity_id' => $r['id'], 'type' => $q['type'], 'text' => $q['text'],
                'options' => json_encode($q['options'], JSON_UNESCAPED_UNICODE), 'correct' => json_encode($q['correct'], JSON_UNESCAPED_UNICODE),
                'points' => $q['points'], 'topic' => $q['topic'], 'sort_order' => $i]);
        }

        return ['id' => $r['id'], 'count' => count($pick)];
    }

    /** Para el selector del editor de evaluaciones: preguntas en el formato del editor (sin ids internos). */
    public function forEditor(int $bankId, array $f = []): array
    {
        return array_map(static fn ($q) => ['id' => (int) $q['id'], 'type' => $q['type'], 'text' => $q['text'], 'options' => $q['options'],
            'correct' => $q['correct'], 'points' => $q['points'], 'topic' => (string) $q['topic'], 'difficulty' => $q['difficulty']],
            $this->get($bankId) ? $this->questions($bankId, $f) : []);
    }
    /* ---------- Red educativa: bancos compartidos ---------- */

    /** Red de la institución activa, o null si es independiente. */
    public function networkId(): ?int
    {
        $id = service('tenant')->institution()['network_id'] ?? null;

        return $id ? (int) $id : null;
    }

    /** Ofrece (o deja de ofrecer) el banco a los demás colegios de la red. */
    public function setShared(int $id, bool $on): ?string
    {
        $bank = $this->get($id);
        if (! $this->canEdit($bank)) {
            return 'Ese banco no existe o no puede editarlo.';
        }
        if (! $this->networkId()) {
            return 'Esta institución no pertenece a una red.';
        }
        model(QuestionBankModel::class)->update($id, ['network_shared' => $on ? 1 : 0, 'updated_by' => $this->uid()]);
        service('audit')->log($on ? 'bank.share' : 'bank.unshare', ['banco' => $bank['name']]);

        return null;
    }

    /**
     * Banco que otro colegio de la misma red comparte, con sus preguntas (lectura). La pertenencia a la red se
     * comprueba aquí: solo entonces se leen filas de otra institución.
     */
    public function shared(int $id): ?array
    {
        $net = $this->networkId();
        if (! $net) {
            return null;
        }
        $bank = $this->db()->table('question_banks b')->select('b.*, i.name AS school, s.name AS subject, u.name AS author')
            ->join('institutions i', 'i.id = b.institution_id')->join('subjects s', 's.id = b.subject_id', 'left')->join('users u', 'u.id = b.created_by', 'left')
            ->where('b.id', $id)->where('b.network_shared', 1)->where('b.deleted_at', null)
            ->where('i.network_id', $net)->where('b.institution_id !=', $this->iid())->get()->getRowArray();
        if (! $bank) {
            return null;
        }
        $bank['questions'] = array_map(static function ($q) {
            $q['options'] = json_decode((string) $q['options'], true) ?: [];
            $q['correct'] = json_decode((string) $q['correct'], true) ?: [];
            $q['points']  = (float) $q['points'];

            return $q;
        }, $this->db()->table('bank_questions')->where('institution_id', $bank['institution_id'])->where('bank_id', $id)->orderBy('sort_order')->get()->getResultArray());

        return $bank;
    }

    /** Copia un banco compartido de la red a la institución activa. Devuelve [id] o [error]. */
    public function copyShared(int $id): array
    {
        if (! service('tenant')->can('activities.create')) {
            return ['error' => 'Su rol no puede crear bancos.'];
        }
        $src = $this->shared($id);
        if (! $src) {
            return ['error' => 'Ese banco no está compartido con su red.'];
        }
        // La asignatura se busca por nombre en esta institución (cada colegio tiene su catálogo).
        $sid = $src['subject'] ? (int) ($this->db()->table('subjects')->select('id')->where('institution_id', $this->iid())->where('name', $src['subject'])->get()->getRow()->id ?? 0) : 0;
        $db  = $this->db();
        $db->transStart();
        $newId = (int) model(QuestionBankModel::class)->insert(['subject_id' => $sid ?: null, 'name' => mb_substr($src['name'], 0, 120),
            'description' => trim(($src['description'] ?? '') . "\nCopiado de " . $src['school'] . '.'), 'created_by' => $this->uid(), 'updated_by' => $this->uid()]);
        foreach ($src['questions'] as $i => $q) {
            $this->insertQuestion($newId, $q, $i);
        }
        $db->transComplete();
        service('audit')->log('bank.copy', ['banco' => $src['name'], 'de' => $src['school']]);

        return $db->transStatus() ? ['id' => $newId] : ['error' => 'No se pudo copiar. Intente de nuevo.'];
    }
}
