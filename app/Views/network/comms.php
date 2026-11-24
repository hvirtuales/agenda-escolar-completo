<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Consola de la red › Comunicaciones: historial de mensajes, noticias y eventos enviados a los colegios, con cifras
 * por envío. Los administradores crean uno nuevo en un Drawer (pestañas Mensaje / Noticia / Evento).
 *
 * @var array       $posts, $schools, $categories, $msgAudiences, $audiences
 * @var string|null $kind
 * @var bool        $canSend
 */
use App\Libraries\NetworkComms;

$icons   = ['message' => 'mail', 'news' => 'newspaper', 'event' => 'calendar-check'];
$tones   = ['message' => 'accent', 'news' => 'ok', 'event' => 'warn'];
$allAud  = $audiences + $msgAudiences;
$audText = static fn (string $a): string => $a === 'all' ? 'Todos' : implode(', ', array_map(static fn ($k) => $allAud[$k] ?? $k, explode(',', $a)));
$old     = static fn (string $k, $d = '') => old($k) ?? $d;
$openKind = old('kind') ?: 'message';
?>
<div x-data="{ f: <?= old('kind') ? 'true' : 'false' ?>, kind: '<?= esc($openKind, 'js') ?>' }">
  <div class="page-header">
    <div><h1>Comunicaciones de la red</h1><p>Mensajes a directivos y docentes, noticias y eventos para los colegios de la red. Llegan con la marca de la red y los colegios no pueden editarlos.</p></div>
    <?php if ($canSend): ?>
      <div class="actions">
        <button type="button" class="btn" @click="kind = 'news'; f = true"><?= icon('newspaper', 16) ?> Noticia</button>
        <button type="button" class="btn" @click="kind = 'event'; f = true"><?= icon('calendar-check', 16) ?> Evento</button>
        <button type="button" class="btn btn-primary" @click="kind = 'message'; f = true"><?= icon('send', 16) ?> Nuevo mensaje</button>
      </div>
    <?php endif ?>
  </div>

  <nav class="chips mb-4" aria-label="Tipo">
    <a class="chip" href="<?= site_url('red/comunicaciones') ?>" aria-selected="<?= $kind ? 'false' : 'true' ?>">Todo</a>
    <?php foreach (NetworkComms::KINDS as $k => $label): ?>
      <a class="chip" href="<?= site_url('red/comunicaciones?tipo=' . $k) ?>" aria-selected="<?= $kind === $k ? 'true' : 'false' ?>"><?= esc($label === 'Noticia' ? 'Noticias' : $label . 's') ?></a>
    <?php endforeach ?>
  </nav>

  <section class="panel">
    <?php if (! $posts): ?>
      <div class="empty"><?= icon('megaphone', 32) ?><p><?= $canSend ? 'Todavía no hay comunicaciones. Envía la primera con «Nuevo mensaje», «Noticia» o «Evento».' : 'La red todavía no ha enviado comunicaciones.' ?></p></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Envío</th><th>Para</th><th class="num">Colegios</th><th>Lectura</th><th>Fecha</th><th class="text-right">Acciones</th></tr></thead>
          <tbody>
            <?php foreach ($posts as $p): ?>
              <tr>
                <td><div class="row" style="gap:0.625rem"><span class="net-kind net-kind-<?= $p['kind'] ?>"><?= icon($icons[$p['kind']], 16) ?></span>
                  <span><a class="font-medium" href="<?= site_url('red/comunicaciones/' . $p['id']) ?>"><?= esc($p['title']) ?></a><span class="meta"><?= esc(NetworkComms::KINDS[$p['kind']]) ?> · <?= esc($p['author'] ?? '—') ?></span></span></div></td>
                <td><?= esc($audText($p['audience'])) ?></td>
                <td class="num"><?= (int) $p['schools'] ?></td>
                <td class="nowrap"><?php if ($p['kind'] === 'message'): ?><?= (int) $p['read_count'] ?> de <?= (int) $p['total'] ?> leído<?php else: ?><span class="soft">Publicado</span><?php endif ?></td>
                <td class="nowrap"><?= esc(date_short($p['created_at'])) ?></td>
                <td class="text-right"><a class="btn btn-sm btn-icon" href="<?= site_url('red/comunicaciones/' . $p['id']) ?>" title="Ver «<?= esc($p['title'], 'attr') ?>»" aria-label="Ver «<?= esc($p['title'], 'attr') ?>»"><?= icon('eye', 14) ?></a></td>
              </tr>
            <?php endforeach ?>
          </tbody>
        </table>
      </div>
    <?php endif ?>
  </section>

  <?php if ($canSend): ?>
  <div class="drawer-layer" x-show="f" x-cloak @keydown.escape.window="f = false">
    <div class="veil" @click="f = false"></div>
    <aside class="drawer wide" role="dialog" aria-modal="true" aria-labelledby="net-form-title">
      <form method="post" action="<?= site_url('red/comunicaciones') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="kind" :value="kind">
        <div class="drawer-head"><h2 id="net-form-title" x-text="{ message: 'Nuevo mensaje de la red', news: 'Nueva noticia de la red', event: 'Nuevo evento de la red' }[kind]"></h2><button type="button" class="btn btn-ghost btn-sm btn-icon" @click="f = false" aria-label="Cerrar">×</button></div>
        <div class="drawer-body stack">
          <div class="chips" role="tablist" aria-label="Tipo de envío">
            <button type="button" class="chip" role="tab" :aria-selected="kind === 'message'" @click="kind = 'message'"><?= icon('mail', 14) ?> Mensaje</button>
            <button type="button" class="chip" role="tab" :aria-selected="kind === 'news'" @click="kind = 'news'"><?= icon('newspaper', 14) ?> Noticia</button>
            <button type="button" class="chip" role="tab" :aria-selected="kind === 'event'" @click="kind = 'event'"><?= icon('calendar-check', 14) ?> Evento</button>
          </div>

          <fieldset class="field"><legend class="label">Colegios <span class="text-danger">*</span></legend>
            <div class="net-schools">
              <?php foreach ($schools as $s): ?>
                <label class="net-school"><input type="checkbox" name="schools[]" value="<?= (int) $s['id'] ?>" <?= ! old('schools') || in_array((string) $s['id'], (array) old('schools'), true) ? 'checked' : '' ?>>
                  <span class="net-dot" style="background:<?= esc($s['accent_color'], 'attr') ?>"></span><?= esc($s['name']) ?></label>
              <?php endforeach ?>
            </div></fieldset>

          <!-- Mensaje -->
          <fieldset class="stack net-pane" x-show="kind === 'message'" :disabled="kind !== 'message'">
            <fieldset class="field"><legend class="label">Para quién</legend>
              <div class="row" style="flex-wrap:wrap;gap:0.75rem">
                <?php foreach ($msgAudiences as $k => $label): ?><label class="row text-sm" style="gap:0.375rem"><input type="checkbox" name="audience[]" value="<?= $k ?>" checked> <?= esc($label) ?></label><?php endforeach ?>
              </div>
              <span class="hint">Los mensajes de la red llegan a directivos y docentes. Para estudiantes y familias, usa una noticia o un evento.</span></fieldset>
            <label class="field"><span class="label">Asunto <span class="text-danger">*</span></span><input class="input" name="subject" required maxlength="160" value="<?= esc($old('subject'), 'attr') ?>" placeholder="Ej.: Encuentro de rectores de la red"></label>
            <label class="field"><span class="label">Mensaje <span class="text-danger">*</span></span><textarea class="textarea" name="body" rows="8" required maxlength="5000"><?= esc($old('body')) ?></textarea></label>
            <label class="row text-sm" style="gap:0.5rem"><input type="checkbox" name="important" value="1"> Importante (llega aunque la persona haya apagado los avisos)</label>
          </fieldset>

          <!-- Noticia -->
          <fieldset class="stack net-pane" x-show="kind === 'news'" :disabled="kind !== 'news'">
            <label class="field"><span class="label">Título <span class="text-danger">*</span></span><input class="input" name="title" required maxlength="160" value="<?= esc($old('title'), 'attr') ?>" placeholder="Ej.: Olimpiadas de matemáticas de la red"></label>
            <label class="field"><span class="label">Resumen</span><input class="input" name="summary" maxlength="300" value="<?= esc($old('summary'), 'attr') ?>"></label>
            <label class="field"><span class="label">Contenido <span class="text-danger">*</span></span><textarea class="textarea" name="body" rows="8" required maxlength="20000"><?= esc($old('body')) ?></textarea></label>
            <label class="field"><span class="label">Imagen de portada</span><input class="input" type="file" name="cover" accept="image/jpeg,image/png,image/webp"><span class="hint">JPG, PNG o WebP de hasta 3 MB.</span></label>
            <fieldset class="field"><legend class="label">Para quién</legend>
              <div class="row" style="flex-wrap:wrap;gap:0.75rem">
                <?php foreach ($audiences as $k => $label): ?><label class="row text-sm" style="gap:0.375rem"><input type="checkbox" name="audience[]" value="<?= $k ?>" checked> <?= esc($label) ?></label><?php endforeach ?>
              </div></fieldset>
            <label class="row text-sm" style="gap:0.5rem"><input type="checkbox" name="pinned" value="1"> Destacar (sale primero en cada colegio)</label>
          </fieldset>

          <!-- Evento -->
          <fieldset class="stack net-pane" x-show="kind === 'event'" :disabled="kind !== 'event'" x-data="{ allDay: false }">
            <label class="field"><span class="label">Título <span class="text-danger">*</span></span><input class="input" name="title" required maxlength="160" value="<?= esc($old('title'), 'attr') ?>" placeholder="Ej.: Juegos intercolegiados de la red"></label>
            <div class="grid-2">
              <label class="field"><span class="label">Categoría</span><select class="select" name="category"><?php foreach ($categories as $k => [$label]): ?><option value="<?= $k ?>"><?= esc($label) ?></option><?php endforeach ?></select></label>
              <label class="field"><span class="label">Lugar</span><input class="input" name="location" maxlength="160"></label>
              <label class="field"><span class="label">Fecha <span class="text-danger">*</span></span><input class="input" type="date" name="date" required value="<?= date('Y-m-d', strtotime('+7 days')) ?>"></label>
              <label class="field"><span class="label">Hasta (opcional)</span><input class="input" type="date" name="end_date"></label>
              <label class="field" x-show="!allDay"><span class="label">Hora de inicio</span><input class="input" type="time" name="start_time" value="08:00" :disabled="allDay"></label>
              <label class="field" x-show="!allDay"><span class="label">Hora de fin</span><input class="input" type="time" name="end_time" :disabled="allDay"></label>
            </div>
            <label class="row text-sm" style="gap:0.5rem"><input type="checkbox" name="all_day" value="1" x-model="allDay"> Todo el día</label>
            <label class="field"><span class="label">Descripción</span><textarea class="textarea" name="description" rows="3" maxlength="2000"></textarea></label>
            <fieldset class="field"><legend class="label">Para quién</legend>
              <div class="row" style="flex-wrap:wrap;gap:0.75rem">
                <?php foreach ($audiences as $k => $label): ?><label class="row text-sm" style="gap:0.375rem"><input type="checkbox" name="audience[]" value="<?= $k ?>" checked> <?= esc($label) ?></label><?php endforeach ?>
              </div>
              <span class="hint">Aparece en «Eventos» y en la agenda de ese público en cada colegio.</span></fieldset>
          </fieldset>
        </div>
        <div class="drawer-foot">
          <button type="button" class="btn" @click="f = false">Cancelar</button>
          <button type="submit" class="btn btn-primary ml-auto" data-loading="Enviando…"><?= icon('send', 14) ?> <span x-text="{ message: 'Enviar mensaje', news: 'Publicar noticia', event: 'Crear evento' }[kind]"></span></button>
        </div>
      </form>
    </aside>
  </div>
  <?php endif ?>
</div>
<?= $this->endSection() ?>
