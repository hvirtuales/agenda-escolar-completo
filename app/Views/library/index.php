<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Biblioteca institucional (documento maestro 4.2 y 4.3): bancos de preguntas, plantillas y recursos (datos reales).
 * La búsqueda y la asignatura filtran las tres pestañas.
 *
 * @var array $banks, $templates, $resources, $filters, $subjects, $offerings, $tab
 * @var bool  $canCreate (bancos), $canTemplate (plantillas y recursos)
 */
$diffs  = \App\Libraries\Quizzes::DIFFICULTIES;
$errors = session('errors') ?? [];
$filtered = $filters['q'] !== '' || $filters['subject'] !== '';
$tplKinds = \App\Libraries\Templates::KINDS;
$accept   = implode(',', array_map(static fn ($e) => '.' . $e, array_keys(\App\Libraries\Resources::TYPES)));
$fsize    = static fn (int $b): string => $b >= 1048576 ? str_replace('.', ',', (string) round($b / 1048576, 1)) . ' MB' : max(1, (int) round($b / 1024)) . ' KB';
?>
<div x-data="{ t: '<?= esc($tab, 'js') ?>', nb: <?= $errors && old('name') !== null ? 'true' : 'false' ?>, rq: null, ut: null, et: null, nr: <?= $errors && $tab === 'recursos' ? 'true' : 'false' ?> }">
  <div class="page-header">
    <div><h1>Biblioteca</h1><p>Lo que la institución comparte para no empezar de cero.</p></div>
    <div class="actions">
      <?php if ($canCreate): ?><button type="button" class="btn btn-primary" x-show="t === 'bancos'" @click="nb = true"><?= icon('plus', 16) ?> Nuevo banco de preguntas</button><?php endif ?>
      <?php if ($canTemplate): ?><button type="button" class="btn btn-primary" x-show="t === 'recursos'" x-cloak @click="nr = true"><?= icon('plus', 16) ?> Nuevo recurso</button><?php endif ?>
    </div>
  </div>

  <div class="chips mb-4" role="tablist">
    <button type="button" class="chip" role="tab" :aria-selected="(t === 'bancos').toString()" @click="t = 'bancos'">Bancos de preguntas (<?= count($banks) ?>)</button>
    <button type="button" class="chip" role="tab" :aria-selected="(t === 'plantillas').toString()" @click="t = 'plantillas'">Plantillas (<?= count($templates) ?>)</button>
    <button type="button" class="chip" role="tab" :aria-selected="(t === 'recursos').toString()" @click="t = 'recursos'">Recursos (<?= count($resources) ?>)</button>
  </div>

    <form class="toolbar" method="get" action="<?= site_url('biblioteca') ?>">
      <input type="hidden" name="t" :value="t">
      <label class="input-icon"><span class="sr-only">Buscar en la biblioteca</span><?= icon('search', 16) ?><input class="input" type="search" name="q" value="<?= esc($filters['q'], 'attr') ?>" placeholder="Buscar por nombre o descripción"></label>
      <select class="select" name="asignatura" aria-label="Asignatura" onchange="this.form.submit()" style="width:auto">
        <option value="">Todas las asignaturas</option>
        <option value="0" <?= $filters['subject'] === '0' ? 'selected' : '' ?>>Generales (sin asignatura)</option>
        <?php foreach ($subjects as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $filters['subject'] === (string) $s['id'] ? 'selected' : '' ?>><?= esc($s['name']) ?></option><?php endforeach ?>
      </select>
      <?php if ($filtered): ?><a class="btn btn-ghost btn-sm" :href="'<?= site_url('biblioteca') ?>?t=' + t">Limpiar filtros</a><?php endif ?>
    </form>

  <div x-show="t === 'bancos'">

    <div class="panel">
      <?php if (! $banks): ?>
        <div class="empty">
          <?= icon('library', 32) ?>
          <p><?= $filters['q'] !== '' || $filters['subject'] !== '' ? 'Ningún banco coincide con la búsqueda.' : 'La institución aún no tiene bancos de preguntas. Cree uno para reutilizar preguntas en varias evaluaciones.' ?></p>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Banco</th><th>Asignatura</th><th class="num">Preguntas</th><th class="num">Temas</th><th>Autor</th><th class="text-right">Acciones</th></tr></thead>
            <tbody>
              <?php foreach ($banks as $b): ?>
                <tr>
                  <td><a class="font-medium link" href="<?= site_url('biblioteca/bancos/' . $b['id']) ?>"><?= esc($b['name']) ?></a><?php if ($b['description']): ?><span class="bank-desc"><?= esc($b['description']) ?></span><?php endif ?></td>
                  <td><?= $b['subject'] ? esc($b['subject']) : '<span class="soft">General</span>' ?></td>
                  <td class="num"><?= (int) $b['questions'] ?></td>
                  <td class="num"><?= (int) $b['topics'] ?></td>
                  <td class="nowrap"><?= esc($b['author'] ?? '—') ?></td>
                  <td class="text-right nowrap">
                    <a class="btn btn-sm" href="<?= site_url('biblioteca/bancos/' . $b['id']) ?>"><?= icon($b['can_edit'] ? 'pencil' : 'eye', 14) ?> <?= $b['can_edit'] ? 'Editar' : 'Ver preguntas' ?></a>
                    <button type="button" <?= $b['questions'] && $offerings ? '' : 'disabled' ?> @click="rq = <?= esc(json_encode(['id' => (int) $b['id'], 'name' => $b['name'], 'n' => (int) $b['questions']]), 'attr') ?>" title="Crear una evaluación aleatoria con «<?= esc($b['name'], 'attr') ?>»" aria-label="Crear una evaluación aleatoria con «<?= esc($b['name'], 'attr') ?>»" class="btn btn-sm btn-icon"><?= icon('shuffle', 14) ?></button>
                  </td>
                </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>
      <?php endif ?>
    </div>

    <?php if ($shared !== null): ?>
      <section class="panel mt-4">
        <div class="panel-bar"><span class="font-medium row"><?= icon('network', 16, 'soft') ?> Compartidos por la red</span><span class="panel-count"><?= plural(count($shared), 'banco', 'bancos') ?></span></div>
        <?php if (! $shared): ?>
          <div class="card-body"><p class="hint">Los demás colegios de la red aún no comparten bancos. Usted puede compartir los suyos desde la página de cada banco.</p></div>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table">
              <thead><tr><th>Banco</th><th>Colegio</th><th>Asignatura</th><th class="num">Preguntas</th><th class="text-right">Acciones</th></tr></thead>
              <tbody>
                <?php foreach ($shared as $b): ?>
                  <tr>
                    <td><a class="font-medium link" href="<?= site_url('biblioteca/red/' . $b['id']) ?>"><?= esc($b['name']) ?></a><?php if ($b['description']): ?><span class="bank-desc"><?= esc($b['description']) ?></span><?php endif ?></td>
                    <td><?= esc($b['school']) ?></td>
                    <td><?= $b['subject'] ? esc($b['subject']) : '<span class="soft">General</span>' ?></td>
                    <td class="num"><?= (int) $b['questions'] ?></td>
                    <td class="text-right nowrap">
                      <a class="btn btn-sm" href="<?= site_url('biblioteca/red/' . $b['id']) ?>"><?= icon('eye', 14) ?> Ver</a>
                      <?php if ($canCreate): ?>
                        <form method="post" action="<?= site_url('biblioteca/red/' . $b['id'] . '/copiar') ?>" style="display:inline">
                          <?= csrf_field() ?><button type="submit" class="btn btn-sm" data-loading="Copiando…" title="Copiar «<?= esc($b['name'], 'attr') ?>» a su institución"><?= icon('copy', 14) ?> Copiar</button>
                        </form>
                      <?php endif ?>
                    </td>
                  </tr>
                <?php endforeach ?>
              </tbody>
            </table>
          </div>
        <?php endif ?>
      </section>
    <?php endif ?>
  </div>

  <div x-show="t === 'plantillas'" x-cloak>
    <?php if (! $templates): ?>
      <div class="panel"><div class="empty"><?= icon('layout-template', 32) ?>
        <p><?= $filtered ? 'Ninguna plantilla coincide con la búsqueda.' : 'Aún no hay plantillas. Abra un reto, una evaluación o una página del cuaderno y use «Guardar como plantilla» para que otros docentes partan de ella.' ?></p></div></div>
    <?php else: ?>
      <div class="lib-grid">
        <?php foreach ($templates as $tp): $k = page_kind($tp['kind']); ?>
          <article class="card lib-card">
            <div class="card-body stack-sm">
              <div class="act-head" style="padding-right:0">
                <span class="act-icon"><?= icon($k['icon'], 18) ?></span>
                <div style="min-width:0"><div class="act-kind"><?= esc($tplKinds[$tp['kind']] ?? $k['label']) ?></div><h2 class="act-title"><?= esc($tp['title']) ?></h2></div>
              </div>
              <?php if ($tp['description']): ?><p class="text-sm"><?= esc($tp['description']) ?></p><?php endif ?>
              <p class="text-xs soft"><?= $tp['subject'] ? esc($tp['subject']) : 'Cualquier asignatura' ?> · de <?= esc($tp['author'] ?? '—') ?> · <?= (int) $tp['uses'] === 1 ? 'usada 1 vez' : 'usada ' . (int) $tp['uses'] . ' veces' ?></p>
            </div>
            <div class="lib-card-foot">
              <?php if ($canTemplate && $offerings): ?>
                <button type="button" class="btn btn-sm btn-primary" @click="ut = <?= esc(json_encode(['id' => (int) $tp['id'], 'title' => $tp['title'], 'kind' => $tplKinds[$tp['kind']] ?? '']), 'attr') ?>"><?= icon('copy-plus', 14) ?> Usar plantilla</button>
              <?php endif ?>
              <?php if ($tp['can_manage']): ?>
                <button type="button" class="btn btn-sm btn-icon" @click="et = <?= esc(json_encode(['id' => (int) $tp['id'], 'title' => $tp['title'], 'description' => (string) $tp['description'], 'subject_id' => (string) ($tp['subject_id'] ?? '')]), 'attr') ?>"
                        title="Editar «<?= esc($tp['title'], 'attr') ?>»" aria-label="Editar «<?= esc($tp['title'], 'attr') ?>»"><?= icon('pencil', 14) ?></button>
                <form method="post" action="<?= site_url('biblioteca/plantillas/' . $tp['id'] . '/eliminar') ?>" onsubmit="return confirm('¿Eliminar la plantilla «<?= esc($tp['title'], 'js') ?>»? Lo que ya se creó con ella no cambia.')">
                  <?= csrf_field() ?><button type="submit" class="btn btn-sm btn-icon btn-danger" title="Eliminar «<?= esc($tp['title'], 'attr') ?>»" aria-label="Eliminar «<?= esc($tp['title'], 'attr') ?>»"><?= icon('trash-2', 14) ?></button></form>
              <?php endif ?>
            </div>
          </article>
        <?php endforeach ?>
      </div>
    <?php endif ?>
  </div>

  <div x-show="t === 'recursos'" x-cloak>
    <div class="panel">
      <?php if (! $resources): ?>
        <div class="empty"><?= icon('folder-open', 32) ?>
          <p><?= $filtered ? 'Ningún recurso coincide con la búsqueda.' : 'Aún no hay recursos. Comparta documentos (manual de convivencia, guías, rúbricas) o enlaces útiles para toda la institución.' ?></p></div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Recurso</th><th>Asignatura</th><th>Tipo</th><th class="num">Tamaño</th><th>Actualizado</th><th class="text-right">Acciones</th></tr></thead>
            <tbody>
              <?php foreach ($resources as $r): $isLink = $r['kind'] === 'link'; $href = $isLink ? $r['url'] : site_url('biblioteca/recursos/' . $r['id'] . '/descargar'); ?>
                <tr>
                  <td><span class="row" style="align-items:flex-start"><?= icon($isLink ? 'link' : 'file-text', 16, 'soft') ?>
                    <span><a class="font-medium link" href="<?= esc($href, 'attr') ?>"<?= $isLink ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?= esc($r['title']) ?></a>
                      <span class="meta"><?= esc($isLink ? parse_url($r['url'], PHP_URL_HOST) : $r['file_name']) ?><?= $r['description'] ? ' · ' . esc($r['description']) : '' ?></span></span></span></td>
                  <td><?= $r['subject'] ? esc($r['subject']) : '<span class="soft">General</span>' ?></td>
                  <td><?= esc($isLink ? 'Enlace' : strtoupper(pathinfo((string) $r['file_name'], PATHINFO_EXTENSION))) ?></td>
                  <td class="num"><?= $isLink ? '—' : esc($fsize((int) $r['size'])) ?></td>
                  <td class="nowrap"><?= esc(date_short($r['updated_at'], false)) ?><span class="meta"><?= esc($r['author'] ?? '') ?></span></td>
                  <td class="text-right nowrap">
                    <a class="btn btn-sm" href="<?= esc($href, 'attr') ?>"<?= $isLink ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?= icon($isLink ? 'external-link' : 'download', 14) ?> <?= $isLink ? 'Abrir' : 'Descargar' ?></a>
                    <?php if ($r['can_manage']): ?>
                      <form method="post" action="<?= site_url('biblioteca/recursos/' . $r['id'] . '/eliminar') ?>" style="display:inline" onsubmit="return confirm('¿Eliminar «<?= esc($r['title'], 'js') ?>» de la biblioteca?')">
                        <?= csrf_field() ?><button type="submit" class="btn btn-sm btn-icon btn-danger" title="Eliminar «<?= esc($r['title'], 'attr') ?>»" aria-label="Eliminar «<?= esc($r['title'], 'attr') ?>»"><?= icon('trash-2', 14) ?></button></form>
                    <?php endif ?>
                  </td>
                </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>
      <?php endif ?>
    </div>
  </div>

  <?php if ($canTemplate): ?>
    <?php /* Usar plantilla */ ?>
    <div class="modal-layer" x-show="ut" x-cloak @keydown.escape.window="ut = null">
      <div class="veil" @click="ut = null"></div>
      <div class="modal" role="dialog" aria-modal="true" aria-labelledby="ut-title">
        <form method="post" :action="ut ? '<?= site_url('biblioteca/plantillas') ?>/' + ut.id + '/usar' : '#'">
          <?= csrf_field() ?>
          <div class="modal-head"><h2 id="ut-title">Usar plantilla</h2><button type="button" class="btn btn-ghost btn-sm btn-icon" @click="ut = null" aria-label="Cerrar">×</button></div>
          <div class="modal-body stack">
            <p class="text-sm">Se crea un borrador de <span class="font-medium" x-text="ut && ut.kind.toLowerCase()"></span> a partir de «<span x-text="ut && ut.title"></span>». Luego lo ajustas y lo publicas.</p>
            <label class="field"><span class="label">Asignatura y grupo <span class="text-danger">*</span></span>
              <select class="select" name="offering_id" required><?php foreach ($offerings as $o): ?><option value="<?= (int) $o['id'] ?>"><?= esc($o['subject'] . ' · ' . $o['group']) ?></option><?php endforeach ?></select></label>
            <label class="field"><span class="label">Título</span><input class="input" name="title" maxlength="160" :value="ut && ut.title"></label>
          </div>
          <div class="modal-foot"><button type="button" class="btn" @click="ut = null">Cancelar</button><button type="submit" class="btn btn-primary" data-loading="Creando…">Crear borrador</button></div>
        </form>
      </div>
    </div>

    <?php /* Editar plantilla */ ?>
    <div class="modal-layer" x-show="et" x-cloak @keydown.escape.window="et = null">
      <div class="veil" @click="et = null"></div>
      <div class="modal" role="dialog" aria-modal="true" aria-labelledby="et-title">
        <form method="post" :action="et ? '<?= site_url('biblioteca/plantillas') ?>/' + et.id : '#'">
          <?= csrf_field() ?>
          <div class="modal-head"><h2 id="et-title">Editar plantilla</h2><button type="button" class="btn btn-ghost btn-sm btn-icon" @click="et = null" aria-label="Cerrar">×</button></div>
          <div class="modal-body stack">
            <label class="field"><span class="label">Título <span class="text-danger">*</span></span><input class="input" name="title" maxlength="160" required :value="et && et.title"></label>
            <label class="field"><span class="label">Asignatura</span>
              <select class="select" name="subject_id"><option value="" :selected="et && !et.subject_id">Cualquier asignatura</option>
                <?php foreach ($subjects as $s): ?><option value="<?= (int) $s['id'] ?>" :selected="et && et.subject_id == '<?= (int) $s['id'] ?>'"><?= esc($s['name']) ?></option><?php endforeach ?></select></label>
            <label class="field"><span class="label">Descripción</span><textarea class="textarea" name="description" rows="3" maxlength="500" x-text="et && et.description" placeholder="Para qué sirve, grado, cómo adaptarla…"></textarea></label>
          </div>
          <div class="modal-foot"><button type="button" class="btn" @click="et = null">Cancelar</button><button type="submit" class="btn btn-primary" data-loading="Guardando…">Guardar</button></div>
        </form>
      </div>
    </div>

    <?php /* Nuevo recurso */ ?>
    <div class="modal-layer" x-show="nr" x-cloak @keydown.escape.window="nr = false">
      <div class="veil" @click="nr = false"></div>
      <div class="modal lg" role="dialog" aria-modal="true" aria-labelledby="nr-title">
        <form method="post" action="<?= site_url('biblioteca/recursos') ?>" enctype="multipart/form-data" x-data="{ kind: 'file' }">
          <?= csrf_field() ?>
          <div class="modal-head"><h2 id="nr-title">Nuevo recurso</h2><button type="button" class="btn btn-ghost btn-sm btn-icon" @click="nr = false" aria-label="Cerrar">×</button></div>
          <div class="modal-body stack">
            <div class="level-row" role="radiogroup" aria-label="Tipo de recurso">
              <label class="btn btn-sm" :class="{ 'btn-active': kind === 'file' }"><input type="radio" name="kind" value="file" class="sr-only" x-model="kind"><?= icon('upload', 14) ?> Archivo</label>
              <label class="btn btn-sm" :class="{ 'btn-active': kind === 'link' }"><input type="radio" name="kind" value="link" class="sr-only" x-model="kind"><?= icon('link', 14) ?> Enlace</label>
            </div>
            <label class="field" x-show="kind === 'file'"><span class="label">Archivo <span class="text-danger">*</span></span>
              <input class="input" type="file" name="file" accept="<?= esc($accept, 'attr') ?>" :required="kind === 'file'">
              <span class="hint">PDF, Word, Excel, PowerPoint, OpenDocument, imagen o texto. Máximo 20 MB.</span><?= field_error('file') ?></label>
            <label class="field" x-show="kind === 'link'" x-cloak><span class="label">Enlace <span class="text-danger">*</span></span>
              <input class="input" type="url" name="url" maxlength="500" placeholder="https://" :required="kind === 'link'"><?= field_error('url') ?></label>
            <label class="field"><span class="label">Título <span x-show="kind === 'link'" class="text-danger">*</span></span><input class="input" name="title" maxlength="160" :required="kind === 'link'" placeholder="Si lo deja vacío, se usa el nombre del archivo"><?= field_error('title') ?></label>
            <label class="field"><span class="label">Asignatura</span>
              <select class="select" name="subject_id"><option value="">General (toda la institución)</option><?php foreach ($subjects as $s): ?><option value="<?= (int) $s['id'] ?>"><?= esc($s['name']) ?></option><?php endforeach ?></select></label>
            <label class="field"><span class="label">Descripción</span><textarea class="textarea" name="description" rows="2" maxlength="500"></textarea></label>
          </div>
          <div class="modal-foot"><button type="button" class="btn" @click="nr = false">Cancelar</button><button type="submit" class="btn btn-primary" data-loading="Subiendo…">Agregar recurso</button></div>
        </form>
      </div>
    </div>
  <?php endif ?>

  <?php if ($canCreate): ?>
    <div class="modal-layer" x-show="nb" x-cloak @keydown.escape.window="nb = false">
      <div class="veil" @click="nb = false"></div>
      <div class="modal lg" role="dialog" aria-modal="true" aria-labelledby="nb-title">
        <form method="post" action="<?= site_url('biblioteca/bancos') ?>">
          <?= csrf_field() ?>
          <div class="modal-head"><h2 id="nb-title">Nuevo banco de preguntas</h2><button type="button" class="btn btn-ghost btn-sm btn-icon" @click="nb = false" aria-label="Cerrar">×</button></div>
          <div class="modal-body stack">
            <label class="field"><span class="label">Nombre <span class="text-danger">*</span></span><input class="input" name="name" maxlength="120" required value="<?= esc(old('name') ?? '', 'attr') ?>" placeholder="Ej.: Fracciones · 7.º"><?= field_error('name') ?></label>
            <label class="field"><span class="label">Asignatura</span>
              <select class="select" name="subject_id">
                <option value="">General (cualquier asignatura)</option>
                <?php foreach ($subjects as $s): ?><option value="<?= (int) $s['id'] ?>" <?= (string) old('subject_id') === (string) $s['id'] ? 'selected' : '' ?>><?= esc($s['name']) ?></option><?php endforeach ?>
              </select><?= field_error('subject_id') ?></label>
            <label class="field"><span class="label">Descripción</span><textarea class="textarea" name="description" rows="2" maxlength="2000" placeholder="Para qué sirve, grado, unidad…"><?= esc(old('description') ?? '') ?></textarea></label>
            <p class="hint">Lo verán y usarán los docentes de la institución; lo editan usted y la coordinación.</p>
          </div>
          <div class="modal-foot"><button type="button" class="btn" @click="nb = false">Cancelar</button><button type="submit" class="btn btn-primary" data-loading="Creando…">Crear banco</button></div>
        </form>
      </div>
    </div>

    <?= $this->include('library/_random_modal') ?>
  <?php endif ?>
</div>
<?= $this->endSection() ?>
