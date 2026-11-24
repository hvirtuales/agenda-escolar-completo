<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
/**
 * Ajustes › Personas › Importar (estudiantes, acudientes y matrícula). Tres estados:
 * inicio (plantilla y subir archivo), vista previa (nada guardado aún) y resultado (descargar contraseñas una vez).
 *
 * @var array|null $preview StudentImport::load(), $result ['summary', 'credentials']
 * @var array      $columns, $groups; string $period
 */
use App\Libraries\Guardians;
use App\Models\UserModel;

$actionLabel = ['new' => ['Cuenta nueva', 'ok'], 'existing' => ['Ya tenía cuenta', 'neutral'], 'enroll' => ['Se matricula', 'accent'], 'move' => ['Cambia de grupo', 'warn'], 'link' => ['Vincula acudiente', 'accent']];
?>
<div class="page-header">
  <div>
    <nav class="crumbs text-xs" aria-label="Ubicación"><a class="link" href="<?= site_url('ajustes/usuarios') ?>">Ajustes › Personas</a><?= icon('chevron-right', 12) ?><span>Importar</span></nav>
    <h1 class="mt-1">Importar estudiantes y acudientes</h1>
    <p>Crea las cuentas, matricula a cada estudiante en su grupo de <?= esc($period) ?> y vincula a sus acudientes. Se identifican por número de documento.</p>
  </div>
</div>

<?php if ($result): $s = $result['summary']; ?>
  <section class="card">
    <div class="card-header"><h2>Importación completa</h2><?= badge('Listo', 'ok') ?></div>
    <div class="card-body stack">
      <div class="grid-kpi">
        <div class="kpi"><div class="k-label">Cuentas nuevas</div><div class="k-value"><?= (int) $s['created'] ?></div><div class="k-hint">Con contraseña temporal</div></div>
        <div class="kpi"><div class="k-label">Agregadas a la institución</div><div class="k-value"><?= (int) $s['members'] ?></div><div class="k-hint">Incluye cuentas que ya existían</div></div>
        <div class="kpi"><div class="k-label">Matrículas</div><div class="k-value"><?= (int) $s['enrolled'] + (int) $s['moved'] ?></div><div class="k-hint"><?= (int) $s['moved'] ? plural((int) $s['moved'], 'cambio de grupo', 'cambios de grupo') : 'En el periodo actual' ?></div></div>
        <div class="kpi"><div class="k-label">Acudientes vinculados</div><div class="k-value"><?= (int) $s['links'] ?></div><div class="k-hint">Recibieron un aviso</div></div>
      </div>
      <?php if ($result['credentials']): ?>
        <div class="notice"><?= icon('key-round', 16) ?><span><strong class="font-medium">Descargue ahora las contraseñas temporales.</strong> La lista se puede descargar <strong>una sola vez</strong>; repártala por un medio seguro. Cada persona entra con su número de documento y debe cambiar la contraseña al entrar.</span></div>
        <div class="row" style="gap:0.5rem;flex-wrap:wrap">
          <a class="btn btn-primary" href="<?= site_url('ajustes/usuarios/importar/credenciales/' . $result['credentials']) ?>"><?= icon('download', 16) ?> Descargar contraseñas (CSV)</a>
          <a class="btn" href="<?= site_url('ajustes/usuarios') ?>"><?= icon('users', 16) ?> Ir a Personas</a>
        </div>
      <?php else: ?>
        <p class="hint">No hubo cuentas nuevas: todas las personas ya tenían cuenta y entran con su contraseña de siempre.</p>
        <div class="row"><a class="btn" href="<?= site_url('ajustes/usuarios') ?>"><?= icon('users', 16) ?> Ir a Personas</a></div>
      <?php endif ?>
    </div>
  </section>

<?php elseif ($preview): $s = $preview['summary']; $lim = $preview['limit']; $blocked = $s['errors'] > 0 || $lim['over']; ?>
  <div x-data="{ onlyErrors: <?= $s['errors'] ? 'true' : 'false' ?> }">
    <div class="grid-kpi">
      <div class="kpi"><div class="k-label">Filas</div><div class="k-value"><?= (int) $s['rows'] ?></div><div class="k-hint truncate"><?= esc($preview['file']) ?></div></div>
      <div class="kpi"><div class="k-label">Cuentas nuevas</div><div class="k-value"><?= (int) $s['new'] ?></div><div class="k-hint"><?= plural((int) $s['existing'], 'persona ya tenía cuenta', 'personas ya tenían cuenta') ?></div></div>
      <div class="kpi"><div class="k-label">Matrículas</div><div class="k-value"><?= (int) $s['enroll'] + (int) $s['move'] ?></div><div class="k-hint"><?= (int) $s['move'] ? plural((int) $s['move'], 'cambia de grupo', 'cambian de grupo') : 'Sin cambios de grupo' ?> · <?= plural((int) $s['links'], 'vínculo', 'vínculos') ?></div></div>
      <div class="kpi"><div class="k-label">Filas con error</div><div class="k-value <?= $s['errors'] ? 'text-danger' : '' ?>"><?= (int) $s['errors'] ?></div><div class="k-hint"><?= $s['errors'] ? 'Corríjalas y suba el archivo de nuevo' : 'Todo listo para importar' ?></div></div>
    </div>

    <?php if ($lim['over']): ?>
      <div class="notice notice-danger mt-4"><?= icon('circle-alert', 16) ?><span>El plan de la institución permite <strong><?= number_format($lim['max'], 0, ',', '.') ?></strong> usuarios y quedarían <strong><?= number_format($lim['after'], 0, ',', '.') ?></strong>. Pida al administrador de la plataforma un plan mayor o reduzca el archivo.</span></div>
    <?php elseif ($lim['max']): ?>
      <p class="hint mt-2">Después de importar: <?= number_format($lim['after'], 0, ',', '.') ?> de <?= number_format($lim['max'], 0, ',', '.') ?> usuarios del plan.</p>
    <?php endif ?>

    <div class="panel mt-4">
      <div class="panel-bar">
        <label class="row text-sm" style="gap:0.5rem"><input type="checkbox" x-model="onlyErrors"> Solo filas con error</label>
        <span class="panel-count"><?= plural((int) $s['rows'], 'fila', 'filas') ?></span>
      </div>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Fila</th><th>Estudiante</th><th>Grupo</th><th>Acudiente</th><th>Qué pasará</th></tr></thead>
          <tbody>
            <?php foreach ($preview['rows'] as $r): $err = (bool) $r['errors']; ?>
              <tr x-show="!onlyErrors || <?= $err ? 'true' : 'false' ?>">
                <td class="nowrap soft"><?= (int) $r['line'] ?></td>
                <td><span class="font-medium"><?= esc($r['name'] ?: '—') ?></span><span class="meta"><?= esc($r['doc'] ?: 'Sin documento') ?></span></td>
                <td class="nowrap"><?= esc($r['groupName'] ?: '—') ?></td>
                <td><?php if ($r['guardian']): ?><span><?= esc($r['guardian']['name']) ?></span><span class="meta"><?= esc($r['guardian']['relationship'] . ' · ' . $r['guardian']['doc']) ?></span><?php else: ?><span class="soft">—</span><?php endif ?></td>
                <td>
                  <?php if ($err): ?>
                    <?php foreach ($r['errors'] as $e): ?><span class="block text-sm text-danger"><?= icon('circle-alert', 13) ?> <?= esc($e) ?></span><?php endforeach ?>
                  <?php else: ?>
                    <span class="row" style="gap:0.25rem;flex-wrap:wrap"><?php foreach ($r['actions'] as $a): [$l, $t] = $actionLabel[$a]; ?><?= badge($l, $t) ?><?php endforeach ?><?= $r['actions'] ? '' : badge('Sin cambios') ?></span>
                  <?php endif ?>
                  <?php foreach ($r['notes'] as $nt): ?><span class="block text-xs soft"><?= esc($nt) ?></span><?php endforeach ?>
                </td>
              </tr>
            <?php endforeach ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="row mt-4" style="gap:0.5rem;flex-wrap:wrap;justify-content:flex-end">
      <a class="btn" href="<?= site_url('ajustes/usuarios/importar') ?>"><?= icon('file-up', 16) ?> Subir otro archivo</a>
      <form method="post" action="<?= site_url('ajustes/usuarios/importar/confirmar') ?>" style="margin:0"
            x-data @submit="if (!confirm('¿Importar ahora? Se crearán <?= (int) $s['new'] ?> cuentas y <?= (int) $s['enroll'] + (int) $s['move'] ?> matrículas.')) $event.preventDefault()">
        <?= csrf_field() ?><input type="hidden" name="token" value="<?= esc($preview['token'], 'attr') ?>">
        <button type="submit" class="btn btn-primary" <?= $blocked ? 'disabled' : '' ?> data-loading="Importando…"><?= icon('check', 16) ?> Confirmar importación</button>
      </form>
    </div>
    <?php if ($blocked): ?><p class="hint" style="text-align:right">No se puede confirmar mientras haya errores<?= $lim['over'] ? ' o se supere el límite del plan' : '' ?>. No se guardó nada.</p><?php endif ?>
  </div>

<?php else: ?>
  <div class="grid-2" style="align-items:start">
    <section class="card">
      <div class="card-header"><h2>1. Descargue la plantilla</h2></div>
      <div class="card-body stack">
        <p class="text-sm">Una fila por estudiante. Si tiene dos acudientes, repita al estudiante en otra fila con el segundo acudiente.</p>
        <div class="row" style="gap:0.5rem;flex-wrap:wrap">
          <a class="btn" href="<?= site_url('ajustes/usuarios/importar/plantilla/xlsx') ?>"><?= icon('file-spreadsheet', 16) ?> Plantilla Excel (.xlsx)</a>
          <a class="btn" href="<?= site_url('ajustes/usuarios/importar/plantilla/csv') ?>"><?= icon('file-text', 16) ?> Plantilla CSV</a>
        </div>
        <dl class="plan-limits text-sm">
          <div><dt>Obligatorias</dt><dd>tipo_documento, numero_documento, nombres, apellidos, grupo</dd></div>
          <div><dt>Opcionales</dt><dd>correo y las columnas acudiente_…</dd></div>
          <div><dt>Tipos de documento</dt><dd><?= esc(implode(', ', array_keys(UserModel::DOC_TYPES))) ?></dd></div>
          <div><dt>Parentesco</dt><dd><?= esc(implode(', ', Guardians::RELATIONSHIPS)) ?></dd></div>
          <div><dt>Grupos (<?= esc($period) ?>)</dt><dd><?= $groups ? esc(implode(', ', $groups)) : 'Aún no hay grupos: créelos en Estructura académica' ?></dd></div>
        </dl>
        <p class="hint">El grupo se reconoce aunque se escriba distinto: «7.º A», «7A» o «7 a». Si un estudiante ya está en otro grupo del periodo, se cambia de grupo.</p>
      </div>
    </section>

    <section class="card">
      <div class="card-header"><h2>2. Suba el archivo</h2></div>
      <form class="card-body stack" method="post" action="<?= site_url('ajustes/usuarios/importar') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <label class="field"><span class="label">Archivo .xlsx o .csv</span><input class="input" type="file" name="archivo" accept=".xlsx,.csv" required>
          <span class="hint">Hasta 2 MB y <?= number_format(\App\Libraries\Import\SheetReader::MAX_ROWS, 0, ',', '.') ?> filas. Primero verá una vista previa: nada se guarda hasta que confirme.</span></label>
        <div class="row"><button type="submit" class="btn btn-primary" data-loading="Analizando…"><?= icon('scan-search', 16) ?> Revisar archivo</button></div>
        <div class="notice"><?= icon('shield-check', 16, 'soft') ?><span class="text-sm">Las personas nuevas reciben una contraseña temporal (se descarga una vez al final) y deben cambiarla al entrar. Quien ya tiene cuenta, en este u otro colegio, conserva la suya.</span></div>
      </form>
    </section>
  </div>
<?php endif ?>
<?= $this->endSection() ?>
