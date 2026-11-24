<?php

namespace App\Libraries;

use App\Models\ContentVersionModel;
use App\Models\PageBlockModel;
use App\Models\PageModel;
use App\Models\SectionModel;
use App\Models\SubjectOfferingModel;
use App\Models\WorkspaceModel;

/**
 * Cuadernos: secciones, páginas y bloques (documento maestro 3.3 y 4.3). Úsela con service('content').
 *
 * Reglas:
 *  - Edita el cuaderno de una oferta quien la dicta (teaching_assignments + permiso content.edit) o coordinación
 *    (academic.structure.manage). Lee el estudiante matriculado en el grupo de la oferta.
 *  - El docente edita en vivo (guardado automático). Publicar copia la página a content_versions: el estudiante
 *    siempre lee la última copia publicada, así los cambios en curso no le aparecen a medias.
 *  - Todo pasa por modelos InstitutionScopedModel y se comprueba que la página es del cuaderno indicado.
 */
class Content
{
    /** Tipos de bloque que ya se guardan de verdad. */
    public const BLOCK_TYPES = ['heading', 'text', 'callout', 'formula', 'image', 'file', 'divider', 'video', 'challenge', 'quiz', 'table', 'question'];

    /** Límites de los bloques «Tabla» y «Pregunta». */
    public const TABLE_MAX_ROWS = 30;
    public const TABLE_MAX_COLS = 10;
    public const QUESTION_MAX_OPTIONS = 6;

    /** Bloques que admiten formato (negrita, resaltado, color…): ver App\Libraries\RichText. */
    public const RICH_TYPES = ['text', 'callout'];

    private const MAX_TEXT = 8000;

    private function uid(): int
    {
        return (int) service('auth')->id();
    }

    /* ---------- Acceso ---------- */

    public function canEditOffering(int $offeringId): bool
    {
        $t = service('tenant');
        if (! model(SubjectOfferingModel::class)->find($offeringId)) {
            return false;
        }
        if ($t->can('academic.structure.manage') || service('auth')->isSuperadmin()) {
            return true;
        }

        return $t->can('content.edit') && db_connect()->table('teaching_assignments')
            ->where(['institution_id' => $t->institutionId(), 'offering_id' => $offeringId, 'user_id' => $this->uid()])
            ->countAllResults() > 0;
    }

    public function canReadOffering(int $offeringId): bool
    {
        $o = model(SubjectOfferingModel::class)->find($offeringId);
        if (! $o) {
            return false;
        }

        return db_connect()->table('class_group_members')
            ->where(['institution_id' => service('tenant')->institutionId(), 'group_id' => $o['group_id'], 'user_id' => $this->uid()])
            ->countAllResults() > 0;
    }

    /* ---------- Lecturas ---------- */

    /** Cuaderno de la oferta; si no existe lo crea con una primera sección. */
    public function workspaceFor(int $offeringId, string $title): array
    {
        $ws = model(WorkspaceModel::class)->where('offering_id', $offeringId)->first();
        if ($ws) {
            return $ws;
        }
        $id = (int) model(WorkspaceModel::class)->insert(['offering_id' => $offeringId, 'title' => $title, 'status' => 'active']);
        model(SectionModel::class)->insert(['workspace_id' => $id, 'name' => 'Unidad 1', 'sort_order' => 0]);

        return model(WorkspaceModel::class)->find($id);
    }

    /** Secciones con sus páginas. $publishedOnly: vista del estudiante (solo páginas con versión publicada). */
    public function tree(int $workspaceId, bool $publishedOnly = false): array
    {
        $sections = model(SectionModel::class)->where('workspace_id', $workspaceId)->orderBy('sort_order')->orderBy('id')->findAll();
        $pm       = model(PageModel::class)->where('workspace_id', $workspaceId);
        if ($publishedOnly) {
            $pm->where('published_at IS NOT NULL', null, false)->where('status !=', 'archived');
        } else {
            $pm->where('status !=', 'archived');
        }
        $pages = $pm->orderBy('sort_order')->orderBy('id')->findAll();
        foreach ($sections as &$s) {
            $s['pages'] = array_values(array_filter($pages, static fn ($p) => (int) $p['section_id'] === (int) $s['id']));
        }
        unset($s);

        return $publishedOnly ? array_values(array_filter($sections, static fn ($s) => $s['pages'] !== [])) : $sections;
    }

    /** Página editable con sus bloques (data decodificada), o null si no es de ese cuaderno. */
    public function page(int $pageId, int $workspaceId): ?array
    {
        $page = model(PageModel::class)->where('workspace_id', $workspaceId)->find($pageId);
        if (! $page) {
            return null;
        }
        $page['blocks'] = array_map(static fn ($b) => ['id' => (int) $b['id'], 'type' => $b['type'], 'data' => json_decode((string) $b['data'], true) ?: []],
            model(PageBlockModel::class)->where('page_id', $pageId)->orderBy('sort_order')->findAll());
        $page['dirty']   = $page['published_at'] === null || $page['updated_at'] > $page['published_at'];
        $page['section'] = model(SectionModel::class)->find($page['section_id'])['name'] ?? '';
        $page['editor']  = $page['updated_by'] ? (model(\App\Models\UserModel::class)->find($page['updated_by'])['name'] ?? '') : '';

        return $page;
    }

    /** Última versión publicada de una página (lo que ve el estudiante), o null. */
    public function publishedPage(int $pageId, int $workspaceId): ?array
    {
        $page = model(PageModel::class)->where('workspace_id', $workspaceId)->where('published_at IS NOT NULL', null, false)->find($pageId);
        if (! $page || $page['status'] === 'archived') {
            return null;
        }
        $v = model(ContentVersionModel::class)->where('page_id', $pageId)->orderBy('id', 'DESC')->first();
        $snap = json_decode((string) ($v['snapshot'] ?? ''), true) ?: ['title' => $page['title'], 'objective' => $page['objective'], 'blocks' => []];

        // Título, objetivo y bloques salen de la versión publicada, no del borrador en curso.
        return ['objective' => $snap['objective'] ?? null, 'title_published' => $snap['title'], 'blocks' => $snap['blocks'],
            'section' => model(SectionModel::class)->find($page['section_id'])['name'] ?? ''] + $page;
    }

    /* ---------- Escrituras ---------- */

    public function createSection(int $workspaceId, string $name): ?string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 120) {
            return 'Escriba un nombre de hasta 120 caracteres.';
        }
        $max = (int) (model(SectionModel::class)->where('workspace_id', $workspaceId)->selectMax('sort_order')->first()['sort_order'] ?? 0);
        model(SectionModel::class)->insert(['workspace_id' => $workspaceId, 'name' => $name, 'sort_order' => $max + 1]);

        return null;
    }

    public function renameSection(int $sectionId, int $workspaceId, string $name): ?string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 120) {
            return 'Escriba un nombre de hasta 120 caracteres.';
        }
        if (! model(SectionModel::class)->where('workspace_id', $workspaceId)->find($sectionId)) {
            return 'Esa sección ya no existe en este cuaderno.';
        }
        model(SectionModel::class)->update($sectionId, ['name' => $name]);

        return null;
    }

    public function deleteSection(int $sectionId, int $workspaceId): ?string
    {
        if (! model(SectionModel::class)->where('workspace_id', $workspaceId)->find($sectionId)) {
            return 'Esa sección ya no existe en este cuaderno.';
        }
        if (model(PageModel::class)->withDeleted()->where('section_id', $sectionId)->countAllResults() > 0) {
            return 'La sección tiene páginas. Muévalas o elimínelas primero.';
        }
        if (model(SectionModel::class)->where('workspace_id', $workspaceId)->countAllResults() <= 1) {
            return 'El cuaderno necesita al menos una sección.';
        }
        model(SectionModel::class)->delete($sectionId);

        return null;
    }

    /** Crea una página en borrador con un primer bloque de texto. Devuelve [id] o [error]. */
    public function createPage(int $workspaceId, int $sectionId, string $title): array
    {
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 160) {
            return ['error' => 'Escriba un título de hasta 160 caracteres.'];
        }
        if (! model(SectionModel::class)->where('workspace_id', $workspaceId)->find($sectionId)) {
            return ['error' => 'Elija una sección de este cuaderno.'];
        }
        $max = (int) (model(PageModel::class)->where('section_id', $sectionId)->selectMax('sort_order')->first()['sort_order'] ?? 0);
        $id  = (int) model(PageModel::class)->insert([
            'workspace_id' => $workspaceId, 'section_id' => $sectionId, 'title' => $title, 'kind' => 'page', 'status' => 'draft',
            'sort_order' => $max + 1, 'created_by' => $this->uid(), 'updated_by' => $this->uid(),
        ]);
        model(PageBlockModel::class)->insert(['page_id' => $id, 'type' => 'text', 'data' => json_encode(['text' => ''], JSON_UNESCAPED_UNICODE), 'sort_order' => 0]);

        return ['id' => $id];
    }

    /**
     * Guardado automático: título, objetivo y bloques (reemplaza el conjunto). Devuelve null o el mensaje de error.
     *
     * @param list<array{type?: string, data?: array}> $blocks
     */
    public function savePage(int $pageId, int $workspaceId, string $title, ?string $objective, array $blocks): ?string
    {
        $page = model(PageModel::class)->where('workspace_id', $workspaceId)->find($pageId);
        if (! $page) {
            return 'Esa página ya no existe en este cuaderno.';
        }
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 160) {
            return 'El título es obligatorio y de hasta 160 caracteres.';
        }
        if (count($blocks) > 300) {
            return 'La página tiene demasiados bloques; divídala en varias páginas.';
        }
        // Bloques «Reto» y «Evaluación»: solo actividades de la asignatura de este cuaderno y del tipo del bloque.
        $offeringId = (int) (db_connect()->table('workspaces')->select('offering_id')->where('id', $workspaceId)->get()->getRow()->offering_id ?? 0);
        $validActs  = [];
        foreach (db_connect()->table('activities')->select('id, kind')->where('institution_id', service('tenant')->institutionId())
            ->where('offering_id', $offeringId)->where('deleted_at', null)->get()->getResultArray() as $a) {
            $validActs[(int) $a['id']] = $a['kind'];
        }
        // Bloques «Imagen» y «Archivo»: solo archivos subidos a este mismo cuaderno (y del tipo del bloque).
        $files = service('notebookFiles')->forWorkspace($workspaceId, array_map(static fn ($b) => $b['data']['file_id'] ?? 0, $blocks));
        $clean = [];
        foreach ($blocks as $b) {
            $type = (string) ($b['type'] ?? '');
            if (! in_array($type, self::BLOCK_TYPES, true)) {
                continue;
            }
            $data = [];
            foreach (['text', 'caption'] as $k) {
                if (isset($b['data'][$k]) && is_string($b['data'][$k])) {
                    $data[$k] = mb_substr(str_replace("\r", '', $b['data'][$k]), 0, self::MAX_TEXT);
                }
            }
            if ($type === 'image' || $type === 'file') {
                $fid = (int) ($b['data']['file_id'] ?? 0);
                if ($fid && isset($files[$fid]) && $files[$fid]['kind'] === $type) {
                    $data['file_id'] = $fid;
                }
            }
            if ($type === 'table') {
                // Filas de texto plano (sin HTML); como máximo TABLE_MAX_ROWS × TABLE_MAX_COLS celdas de hasta 500 caracteres.
                $rows = [];
                foreach (array_slice((array) ($b['data']['rows'] ?? []), 0, self::TABLE_MAX_ROWS) as $row) {
                    $rows[] = array_map(static fn ($c) => mb_substr(trim(str_replace("\r", '', is_scalar($c) ? (string) $c : '')), 0, 500),
                        array_slice(array_values((array) $row), 0, self::TABLE_MAX_COLS));
                }
                $cols = $rows ? max(array_map('count', $rows)) : 0;
                $data['rows']   = array_map(static fn ($r) => array_pad($r, $cols, ''), $rows); // todas las filas con las mismas columnas
                $data['header'] = ! empty($b['data']['header']);
            }
            if ($type === 'question') {
                // Pregunta de comprobación: enunciado, 2 a 6 opciones, la correcta y una explicación. No se califica.
                // Las opciones vacías se quitan; la correcta se busca por su posición original (si era una vacía, queda sin marcar).
                $opts    = [];
                $correct = null;
                $marked  = isset($b['data']['correct']) && is_numeric($b['data']['correct']) ? (int) $b['data']['correct'] : -1;
                foreach (array_slice(array_values((array) ($b['data']['options'] ?? [])), 0, self::QUESTION_MAX_OPTIONS) as $i => $o) {
                    $o = mb_substr(trim(is_scalar($o) ? (string) $o : ''), 0, 200);
                    if ($o === '') {
                        continue;
                    }
                    if ($i === $marked) {
                        $correct = count($opts);
                    }
                    $opts[] = $o;
                }
                $data['question']    = mb_substr(trim(str_replace("\r", '', (string) ($b['data']['question'] ?? ''))), 0, 500);
                $data['options']     = $opts;
                $data['correct']     = $correct;
                $data['explanation'] = mb_substr(trim(str_replace("\r", '', (string) ($b['data']['explanation'] ?? ''))), 0, 1000);
            }
            if ($type === 'challenge' || $type === 'quiz') {
                $aid = (int) ($b['data']['activity_id'] ?? 0);
                if ($aid && ($validActs[$aid] ?? null) === $type) {
                    $data['activity_id'] = $aid;
                }
            }
            // Video: el enlace tal como lo pegó el docente; al mostrarlo se valida (App\Libraries\VideoEmbed: solo YouTube y Vimeo).
            if ($type === 'video' && is_string($b['data']['url'] ?? null)) {
                $data['url'] = mb_substr(trim($b['data']['url']), 0, 500);
            }
            // Formato (barra flotante): solo en párrafos y notas, limpio con lista blanca; si no hay formato, basta el texto.
            if (in_array($type, self::RICH_TYPES, true) && is_string($b['data']['html'] ?? null)) {
                $html = \App\Libraries\RichText::clean(str_replace("\r", '', $b['data']['html']), self::MAX_TEXT * 4);
                if (\App\Libraries\RichText::hasFormatting($html)) {
                    $data['html'] = $html;
                }
            }
            $clean[] = ['type' => $type, 'data' => $data];
        }

        $db = db_connect();
        $db->transStart();
        model(PageModel::class)->update($pageId, ['title' => $title, 'objective' => mb_substr(trim((string) $objective), 0, 255) ?: null, 'updated_by' => $this->uid()]);
        $db->table('page_blocks')->where('institution_id', service('tenant')->institutionId())->where('page_id', $pageId)->delete();
        foreach ($clean as $i => $b) {
            model(PageBlockModel::class)->insert(['page_id' => $pageId, 'type' => $b['type'], 'data' => json_encode($b['data'], JSON_UNESCAPED_UNICODE), 'sort_order' => $i]);
        }
        $db->transComplete();

        return $db->transStatus() ? null : 'No se pudo guardar. Revise su conexión e intente de nuevo.';
    }

    /** Publica: copia la página a content_versions y la deja visible para los estudiantes. */
    public function publish(int $pageId, int $workspaceId): ?string
    {
        $page = $this->page($pageId, $workspaceId);
        if (! $page) {
            return 'Esa página ya no existe en este cuaderno.';
        }
        $snapshot = ['title' => $page['title'], 'objective' => $page['objective'], 'blocks' => array_map(static fn ($b) => ['type' => $b['type'], 'data' => $b['data']], $page['blocks'])];
        $now      = date('Y-m-d H:i:s');
        model(ContentVersionModel::class)->insert(['page_id' => $pageId, 'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE), 'user_id' => $this->uid()]);
        // published_at queda un segundo después de updated_at para que la página no figure con cambios pendientes.
        db_connect()->table('pages')->where('institution_id', service('tenant')->institutionId())->where('id', $pageId)
            ->update(['status' => 'published', 'published_at' => $now, 'updated_at' => $now]);

        return null;
    }

    public function deletePage(int $pageId, int $workspaceId): ?string
    {
        if (! model(PageModel::class)->where('workspace_id', $workspaceId)->find($pageId)) {
            return 'Esa página ya no existe en este cuaderno.';
        }
        model(PageModel::class)->delete($pageId);

        return null;
    }
}
