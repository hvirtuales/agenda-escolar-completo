<?php

namespace App\Controllers;

use App\Libraries\StudentProgress;

/**
 * Pantallas del estudiante: inicio (con su progreso), mis asignaturas (el cuaderno de cada asignatura, en solo lectura), actividades y progreso. Con datos reales en los dos modos:
 * - el estudiante ve lo suyo (su grupo, sus entregas, sus notas);
 * - docentes, coordinación y superadministrador ven la «vista de estudiante» de un grupo real (service('studentPreview')),
 *   con el contenido publicado y las actividades del grupo, sin datos de ningún estudiante.
 */
class StudentController extends BaseController
{
    private function preview(): bool
    {
        return ! service('studentPreview')->isStudent();
    }

    private function uid(): int
    {
        return (int) service('auth')->id();
    }

    /** Grupo previsualizado (null para el propio estudiante; 0 si la vista previa no tiene grupos: todo vacío). */
    private function gid(): ?int
    {
        return $this->preview() ? (service('studentPreview')->groupId() ?? 0) : null;
    }

    /** Catálogo de asignaturas: las del grupo del estudiante o las del grupo previsualizado. */
    private function catalog(): array
    {
        return $this->preview()
            ? (($g = $this->gid()) ? service('academic')->catalogFor(0, 'group', $g) : [])
            : service('academic')->catalogFor($this->uid(), 'student');
    }

    private static function url(array $a): string
    {
        return ($a['kind'] === 'quiz' ? 'estudiante/evaluaciones/' : 'estudiante/retos/') . $a['assignment_id'];
    }

    /** «?grupo=N» cambia el grupo de la vista previa (selector de la barra y botón «Vista de estudiante» del cuaderno). */
    private function applyGroup(): bool
    {
        if ($this->preview() && ($g = $this->request->getGet('grupo')) !== null) {
            service('studentPreview')->select((int) $g);

            return true;
        }

        return false;
    }

    /** Inicio: une el antiguo «Mi progreso» (resumen, por hacer, avance por asignatura, retroalimentación y logros). */
    public function index()
    {
        if ($this->applyGroup()) {
            return redirect()->to('estudiante');
        }
        $period = service('academic')->currentPeriod();

        return view('student/home', $this->studentLayout() + ['title' => 'Inicio', 'period' => $period['name'] ?? '',
            'p'    => service('myProgress')->progress($this->uid(), $this->gid()),
            'acts' => service('myProgress')->activities($this->uid(), $this->gid())]);
    }

    /** Busca la oferta por slug (o{id}) en el catálogo; si no está, la primera. */
    private static function pickOffering(array $catalog, ?string $slug): ?array
    {
        $items = \App\Libraries\Academic::flatten($catalog);
        foreach ($items as $it) {
            if ($it['slug'] === $slug) {
                return $it;
            }
        }

        return $items[0] ?? null;
    }

    /** «estudiante/cuadernos/…» (nombre anterior) → «estudiante/asignaturas/…», conservando la página y el ?grupo. */
    public function legacyNotebook(string ...$segments)
    {
        $rest = implode('/', array_map('rawurlencode', $segments)); // (:any) entrega cada segmento por separado
        $q    = (string) $this->request->getUri()->getQuery();

        return redirect()->to('estudiante/asignaturas' . ($rest !== '' ? '/' . $rest : '') . ($q !== '' ? '?' . $q : ''), 301);
    }

    /** Mis asignaturas: el cuaderno de la asignatura con solo páginas publicadas, en su última versión publicada. */
    public function notebook(?string $slug = null, ?string $page = null)
    {
        $this->applyGroup();
        $catalog  = $this->catalog();
        $offering = self::pickOffering($catalog, $slug);
        $allowed  = $offering && ($this->preview() || service('content')->canReadOffering($offering['id']));
        if (! $allowed) {
            return redirect()->to('estudiante')->with('toast', ['type' => 'info', 'msg' => $this->preview()
                ? 'Ese grupo aún no tiene asignaturas en el periodo en curso.'
                : 'Aún no estás en un grupo con asignaturas. Pregúntale a tu docente o a coordinación.']);
        }
        $content = service('content');
        $ws      = $content->workspaceFor($offering['id'], $offering['subject'] . ' · ' . $offering['group']);
        $tree    = $content->tree((int) $ws['id'], true);
        $pageId  = ctype_digit((string) $page) ? (int) $page : (int) ($tree[0]['pages'][0]['id'] ?? 0);

        return view('student/notebook', $this->studentLayout() + [
            'title'    => $offering['subject'] . ' · ' . $offering['group'],
            'catalog'  => $catalog,
            'notebook' => ['slug' => $offering['slug'], 'title' => $offering['subject'] . ' · ' . $offering['group'], 'category' => $offering['category'],
                'color' => $offering['color'], 'group' => $offering['group'], 'sections' => $tree],
            'pageSlug' => (string) $pageId,
            'page'     => $published = $pageId ? $content->publishedPage($pageId, (int) $ws['id']) : null,
            // Archivos de los bloques «Imagen» y «Archivo» de la versión publicada.
            'files'    => $published ? service('notebookFiles')->forWorkspace((int) $ws['id'], array_map(static fn ($b) => $b['data']['file_id'] ?? 0, $published['blocks'] ?? [])) : [],
            // Tutor del cuaderno: su conversación sobre la página (en la vista de estudiante no hay conversación ni respuestas).
            'thread'   => $pageId && ! $this->preview() ? service('assistant')->studyThread($pageId, $this->uid()) : [],
            'labelAi'  => (bool) service('aiQuota')->settings((int) service('tenant')->institutionId())['label_ai'],
            'canTutor' => ! $this->preview() && service('tenant')->can('ai.student.use'),
            // Bloques «Reto» y «Evaluación»: las actividades que el estudiante (o el grupo de la vista previa) ya puede ver,
            // con su estado y enlace. Una actividad que no esté aquí (borrador, sin abrir, de otro grupo) no se muestra.
            'acts'     => array_column(service('myProgress')->activities($this->uid(), $this->gid()), null, 'activity_id'),
        ]);
    }

    /** «Mi progreso» se unió al Inicio: la dirección anterior redirige (conserva el ?grupo de la vista previa). */
    public function progress()
    {
        $q = (string) $this->request->getUri()->getQuery();

        return redirect()->to('estudiante' . ($q !== '' ? '?' . $q : ''), 301);
    }

    /** Mis actividades: por estado (por hacer, entregadas, calificadas, vencidas) y asignatura. */
    public function activities()
    {
        $tab = (string) $this->request->getGet('ver');

        return view('student/activities', $this->studentLayout() + ['title' => 'Mis actividades',
            'acts'    => service('myProgress')->activities($this->uid(), $this->gid()),
            'tab'     => array_key_exists($tab, StudentProgress::GROUPS) ? $tab : 'pending',
            'subject' => (string) $this->request->getGet('asignatura')]);
    }
}
