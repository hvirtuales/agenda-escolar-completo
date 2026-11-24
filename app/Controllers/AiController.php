<?php

namespace App\Controllers;

/**
 * Puntos JSON de la IA (documento maestro 5.1): tutor del estudiante y copiloto docente.
 * Todas las comprobaciones (permiso, acceso a la actividad, modo, cupos) están en Assistant y en el Gateway.
 */
class AiController extends BaseController
{
    private function input(): array
    {
        $json = $this->request->getJSON(true);

        return is_array($json) ? $json : (array) $this->request->getPost();
    }

    private function reply(array $data, int $status = 200)
    {
        return $this->response->setStatusCode($status)->setJSON($data + ['quota' => service('aiQuota')->balance()]);
    }

    /** POST estudiante/retos/{asignación}/tutor  {message} */
    public function tutor(int $assignmentId)
    {
        $r = service('assistant')->tutor($assignmentId, (string) ($this->input()['message'] ?? ''));

        return isset($r['error']) ? $this->reply(['ok' => false, 'error' => $r['error'], 'level' => $r['level'] ?? null], 422)
            : $this->reply(['ok' => true, 'reply' => $r['reply'], 'simulated' => $r['simulated']]);
    }

    /** POST estudiante/paginas/{página}/tutor  {message} — tutor del cuaderno del estudiante */
    public function study(int $pageId)
    {
        $r = service('assistant')->study($pageId, (string) ($this->input()['message'] ?? ''));

        return isset($r['error']) ? $this->reply(['ok' => false, 'error' => $r['error'], 'level' => $r['level'] ?? null], 422)
            : $this->reply(['ok' => true, 'reply' => $r['reply'], 'simulated' => $r['simulated']]);
    }

    /** POST ia/retroalimentacion/{asignación}/{entrega}  {levels[]} — propuesta de retroalimentación */
    public function feedback(int $assignmentId, int $submissionId)
    {
        $r = service('assistant')->feedback($assignmentId, $submissionId, (array) ($this->input()['levels'] ?? []));

        return isset($r['error']) ? $this->reply(['ok' => false, 'error' => $r['error'], 'level' => $r['level'] ?? null], 422)
            : $this->reply(['ok' => true, 'reply' => $r['reply'], 'simulated' => $r['simulated']]);
    }

    /** POST ia/cuaderno/{página}  {message} — asistente del cuaderno */
    public function copilot(int $pageId)
    {
        $r = service('assistant')->copilot($pageId, (string) ($this->input()['message'] ?? ''));

        return isset($r['error']) ? $this->reply(['ok' => false, 'error' => $r['error'], 'level' => $r['level'] ?? null], 422)
            : $this->reply(['ok' => true, 'reply' => $r['reply'], 'simulated' => $r['simulated']]);
    }

    /** POST ia/preguntas  {topic, count, types[], difficulty, notes, offering_id | subject_id} */
    public function questions()
    {
        $r = service('assistant')->questions($this->input());

        return isset($r['error']) ? $this->reply(['ok' => false, 'error' => $r['error'], 'level' => $r['level'] ?? null], 422)
            : $this->reply(['ok' => true, 'questions' => $r['questions'], 'simulated' => $r['simulated'], 'discarded' => $r['discarded']]);
    }
}
