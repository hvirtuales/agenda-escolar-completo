<?php

namespace App\Controllers;

use App\Models\SubmissionModel;

/** Revisión de entregas (documento maestro 4.7): lista por asignación, entrega elegida y calificación por rúbrica. */
class SubmissionsController extends BaseController
{
    public function index(?int $assignmentId = null)
    {
        $acts = service('activities');
        $list = $acts->assignmentsForReview();
        if (! $list) {
            return view('submissions/empty', $this->layoutData() + ['title' => 'Entregas']);
        }
        $current = null;
        foreach ($list as $a) {
            if ($assignmentId === null || (int) $a['assignment_id'] === $assignmentId) {
                $current = $a;
                break;
            }
        }
        if (! $current) {
            return redirect()->to('entregas')->with('toast', ['type' => 'error', 'msg' => 'Esa actividad no existe o no es suya.']);
        }
        $activity = $acts->get((int) $current['id']);
        if ($activity['kind'] === 'quiz') {
            return $this->quiz($list, $current, $activity);
        }
        $students = $acts->reviewList((int) $current['assignment_id']);
        $subId    = (int) $this->request->getGet('e');
        $chosen   = null;
        foreach ($students as $s) {
            if ($s['id'] && ($subId ? (int) $s['id'] === $subId : $s['status'] === 'submitted')) {
                $chosen = $s;
                break;
            }
        }
        $submission = $chosen ? model(SubmissionModel::class)->where('assignment_id', $current['assignment_id'])->find($chosen['id']) : null;
        if ($submission) {
            $submission['levels']     = json_decode((string) $submission['levels'], true) ?: [];
            $submission['steps_done'] = json_decode((string) $submission['steps_done'], true) ?: [];
            $submission['name']       = $chosen['name'];
        }

        return view('submissions/review', $this->layoutData() + [
            'title'      => 'Entregas',
            'list'       => $list,
            'current'    => $current,
            'activity'   => $activity,
            'students'   => $students,
            'submission' => $submission,
            'quota'      => service('aiQuota')->balance(),
        ]);
    }

    /** Revisión de una evaluación: estudiantes con su intento, respuestas con su puntaje y puntos de las abiertas. */
    private function quiz(array $list, array $current, array $activity)
    {
        $q        = service('quizzes');
        $students = $q->reviewList((int) $current['assignment_id']);
        $attId    = (int) $this->request->getGet('e');
        $attempt  = null;
        foreach ($students as $s) {
            if ($s['attempt'] && ($attId ? (int) $s['attempt']['id'] === $attId : $s['attempt']['status'] === 'pending_review')) {
                $attempt = $s['attempt'] + ['name' => $s['name']];
                break;
            }
        }
        if ($attempt) {
            $attempt['answers'] = json_decode((string) $attempt['answers'], true) ?: [];
            $attempt['manual']  = json_decode((string) $attempt['manual'], true) ?: [];
        }

        return view('submissions/quiz_review', $this->layoutData() + [
            'title' => 'Entregas', 'list' => $list, 'current' => $current, 'activity' => $activity,
            'students' => $students, 'attempt' => $attempt, 'questions' => $q->questions((int) $activity['id']),
        ]);
    }

    public function reviewAttempt(int $assignmentId, int $attemptId)
    {
        $e = service('quizzes')->review($assignmentId, $attemptId, (array) $this->request->getPost('manual'), (string) $this->request->getPost('feedback'));
        if ($e) {
            return $this->backWith('error', $e);
        }

        return redirect()->to('entregas/' . $assignmentId . '?e=' . $attemptId)->with('toast', ['type' => 'ok', 'msg' => 'Revisión guardada.']);
    }

    public function grade(int $assignmentId, int $submissionId)
    {
        $action = $this->request->getPost('action') === 'return' ? 'return' : 'grade';
        $e      = service('activities')->grade($assignmentId, $submissionId, (array) $this->request->getPost('levels'), (string) $this->request->getPost('feedback'), $action);
        if ($e) {
            return $this->backWith('error', $e);
        }
        // Siguiente entrega por revisar, si hay.
        $next = null;
        foreach (service('activities')->reviewList($assignmentId) as $s) {
            if ($s['status'] === 'submitted' && (int) $s['id'] !== $submissionId) {
                $next = (int) $s['id'];
                break;
            }
        }

        return redirect()->to('entregas/' . $assignmentId . ($next ? '?e=' . $next : '?e=' . $submissionId))
            ->with('toast', ['type' => 'ok', 'msg' => $action === 'return' ? 'Entrega devuelta para corrección.' : 'Calificación guardada.' . ($next ? ' Siguiente entrega.' : '')]);
    }
}
