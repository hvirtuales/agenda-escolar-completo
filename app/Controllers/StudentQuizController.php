<?php

namespace App\Controllers;

/**
 * Evaluación del estudiante (documento maestro 13.4): presentación con intentos, el intento en curso con tiempo límite
 * y guardado automático, y los resultados cuando la configuración lo permite. El tutor de IA no está disponible.
 */
class StudentQuizController extends BaseController
{
    private function uid(): int
    {
        return (int) service('auth')->id();
    }

    /** Evaluación del estudiante, o la del grupo previsualizado en la vista de estudiante (null si no corresponde). */
    private function quizFor(int $assignmentId): ?array
    {
        $pv      = service('studentPreview');
        $preview = ! $pv->isStudent();
        $v       = service('activities')->studentView($assignmentId, $preview ? 0 : $this->uid(), $preview ? ($pv->groupId() ?? 0) : null);

        return $v && $v['activity']['kind'] === 'quiz' ? $v + ['preview' => $preview] : null;
    }

    /** Presentación: datos, intentos usados y botón para iniciar o retomar. */
    public function show(int $assignmentId)
    {
        $v = $this->quizFor($assignmentId);
        if (! $v) {
            return redirect()->to('estudiante/actividades')->with('toast', ['type' => 'error', 'msg' => 'Esa evaluación no está disponible para usted.']);
        }

        return view('student/quiz_intro', $this->studentLayout() + [
            'title'     => $v['activity']['title'],
            'v'         => $v,
            'preview'   => $v['preview'],
            'attempts'  => $v['preview'] ? [] : service('quizzes')->attempts($assignmentId, $this->uid()),
            'questions' => count(service('quizzes')->questions((int) $v['activity']['id'], false)),
        ]);
    }

    /** Vista de estudiante: las preguntas tal como se presentan, sin respuestas correctas y sin guardar nada. */
    public function preview(int $assignmentId)
    {
        $v = $this->quizFor($assignmentId);
        if (! $v || ! $v['preview']) {
            return redirect()->to('estudiante/evaluaciones/' . $assignmentId);
        }
        $limit = (int) $v['activity']['time_limit'];

        return view('student/quiz_take', $this->studentLayout() + ['title' => $v['activity']['title'], 'preview' => true, 'q' => $v + [
            'attempt'   => ['id' => 0, 'number' => 1, 'answers' => []],
            'questions' => service('quizzes')->questions((int) $v['activity']['id'], false),
            'deadline'  => $limit ? date('Y-m-d H:i:s', time() + $limit * 60) : $v['assignment']['due_at'],
        ]]);
    }

    public function start(int $assignmentId)
    {
        $r = service('quizzes')->start($assignmentId, $this->uid());
        if (isset($r['error'])) {
            return $this->backWith('error', $r['error']);
        }

        return redirect()->to('estudiante/evaluaciones/' . $assignmentId . '/intento/' . $r['id']);
    }

    /** El intento: en curso se presenta; terminado muestra el resultado (si ya se puede ver). */
    public function attempt(int $assignmentId, int $attemptId)
    {
        $view = service('quizzes')->attemptView($assignmentId, $attemptId, $this->uid());
        if (! $view) {
            return redirect()->to('estudiante/actividades')->with('toast', ['type' => 'error', 'msg' => 'Ese intento no existe o no es suyo.']);
        }
        $data = $this->studentLayout() + ['title' => $view['activity']['title'], 'q' => $view];

        return view($view['attempt']['status'] === 'in_progress' ? 'student/quiz_take' : 'student/quiz_result', $data);
    }

    /** Guardado automático (JSON): { answers: { idPregunta: valor } }. */
    public function save(int $assignmentId, int $attemptId)
    {
        $in = $this->request->getJSON(true) ?? [];
        $e  = service('quizzes')->saveAnswers($assignmentId, $attemptId, $this->uid(), (array) ($in['answers'] ?? []));

        return $e ? $this->response->setStatusCode(409)->setJSON(['ok' => false, 'error' => $e]) : $this->response->setJSON(['ok' => true]);
    }

    public function finish(int $assignmentId, int $attemptId)
    {
        $answers = json_decode((string) $this->request->getPost('answers_json'), true);
        if ($e = service('quizzes')->finish($assignmentId, $attemptId, $this->uid(), is_array($answers) ? $answers : null)) {
            return redirect()->to('estudiante/evaluaciones/' . $assignmentId)->with('toast', ['type' => 'error', 'msg' => $e]);
        }

        return redirect()->to('estudiante/evaluaciones/' . $assignmentId . '/intento/' . $attemptId)->with('toast', ['type' => 'ok', 'msg' => 'Intento enviado.']);
    }
}
