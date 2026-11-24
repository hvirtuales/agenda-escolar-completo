<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Nuevo mensaje: destinatarios por rol (solo directivos), por grupo (estudiantes y/o familias) y por persona; muestra
 * en vivo a cuántas personas llegará.
 *
 * @var bool  $manager
 * @var array $roles, $groups
 */
?>
<form method="post" action="<?= site_url('mensajes') ?>" x-data="messageComposer(<?= esc(json_encode(['people' => site_url('mensajes/personas'), 'count' => site_url('mensajes/contar')]), 'attr') ?>)" @submit="if (!targets.length) { $event.preventDefault(); window.toast('Elige al menos un destinatario.', 'error'); }">
  <?= csrf_field() ?>
  <template x-for="t in targets" :key="t.key"><input type="hidden" name="targets[]" :value="t.key"></template>

  <div class="page-header">
    <div><nav class="crumbs text-xs" aria-label="Ubicación"><a class="link" href="<?= site_url('mensajes') ?>">Mensajes</a><?= icon('chevron-right', 12) ?><span>Nuevo</span></nav>
      <h1 class="mt-1">Nuevo mensaje</h1></div>
    <div class="actions"><a class="btn" href="<?= site_url('mensajes') ?>">Cancelar</a><button type="submit" class="btn btn-primary" data-loading="Enviando…"><?= icon('send', 16) ?> Enviar</button></div>
  </div>

  <div class="grid-3" style="align-items:start">
    <section class="card span-2">
      <div class="card-body stack">
        <div class="field"><span class="label">Para <span class="text-danger">*</span></span>
          <div class="recip-box">
            <template x-for="t in targets" :key="t.key">
              <span class="recip-chip"><span x-text="t.label"></span><button type="button" @click="remove(t.key)" :aria-label="'Quitar ' + t.label">×</button></span>
            </template>
            <span class="soft text-sm" x-show="!targets.length">Elige destinatarios a la derecha o busca personas.</span>
          </div>
          <span class="hint" x-show="targets.length"><span x-text="count === null ? 'Calculando…' : ('Llegará a ' + count + (count === 1 ? ' persona' : ' personas'))"></span></span>
        </div>
        <div class="field" style="position:relative" @click.outside="results = []">
          <label class="label" for="msg-people">Agregar personas</label>
          <input id="msg-people" class="input" type="search" x-model="q" @input.debounce.300ms="search()" placeholder="Busca por nombre o correo" autocomplete="off">
          <div class="recip-results" x-show="results.length" x-cloak>
            <template x-for="p in results" :key="p.id">
              <button type="button" class="recip-result" @click="add('user:' + p.id, p.name); q = ''; results = []"><span x-text="p.name"></span> <span class="soft text-xs" x-text="p.role"></span></button>
            </template>
          </div>
        </div>
        <label class="field"><span class="label">Asunto <span class="text-danger">*</span></span><input class="input" name="subject" required maxlength="160" value="<?= esc(old('subject') ?? '', 'attr') ?>" placeholder="Ej.: Reunión de padres del viernes"></label>
        <label class="field"><span class="label">Mensaje <span class="text-danger">*</span></span><textarea class="textarea" name="body" rows="9" required maxlength="5000" placeholder="Escribe tu mensaje…"><?= esc(old('body') ?? '') ?></textarea></label>
        <label class="toggle" x-data="{ on: false }"><span><span class="block t-title">Importante</span><span class="block t-desc">Se destaca en rojo y llega aunque la persona haya apagado los avisos de mensajes.</span></span>
          <input type="hidden" name="important" :value="on ? 1 : 0"><button type="button" class="switch" role="switch" :aria-checked="on.toString()" @click="on = !on" aria-label="Marcar como importante"></button></label>
      </div>
    </section>

    <aside class="stack">
      <?php if ($manager): ?>
        <section class="card">
          <div class="card-header"><h2>Por rol</h2></div>
          <div class="card-body stack-sm">
            <?php foreach ($roles as $k => $label): ?>
              <button type="button" class="btn btn-sm" style="justify-content:flex-start" @click="add('role:<?= $k ?>', <?= esc(json_encode($label), 'attr') ?>)"><?= icon('users', 14) ?> <?= esc($label) ?></button>
            <?php endforeach ?>
          </div>
        </section>
      <?php endif ?>
      <section class="card">
        <div class="card-header"><h2>Por grupo</h2></div>
        <div class="card-body stack-sm">
          <?php if (! $groups): ?><p class="soft text-sm">No tienes grupos en el periodo.</p><?php endif ?>
          <?php foreach ($groups as $g): ?>
            <div class="row-between"><span class="text-sm font-medium"><?= esc($g['name']) ?></span>
              <span class="row" style="gap:0.25rem">
                <button type="button" class="btn btn-sm" @click="add('group:<?= (int) $g['id'] ?>:students', <?= esc(json_encode($g['name'] . ' (estudiantes)'), 'attr') ?>)" title="Agregar los estudiantes de <?= esc($g['name'], 'attr') ?>">Estudiantes</button>
                <button type="button" class="btn btn-sm" @click="add('group:<?= (int) $g['id'] ?>:guardians', <?= esc(json_encode($g['name'] . ' (familias)'), 'attr') ?>)" title="Agregar las familias de <?= esc($g['name'], 'attr') ?>">Familias</button>
              </span></div>
          <?php endforeach ?>
        </div>
      </section>
      <p class="hint"><?= $manager ? 'Como directivo puedes escribir a toda la institución.' : 'Puedes escribir a los estudiantes y familias de tus grupos y a tus colegas.' ?></p>
    </aside>
  </div>
</form>
<?= $this->endSection() ?>
