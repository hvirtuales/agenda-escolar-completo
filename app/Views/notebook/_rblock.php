<?php
/**
 * Bloque real de una página. $b = ['type' => …, 'data' => [...]]; $edit = true en el editor del docente.
 * En edición, cada campo editable lleva data-field y el bloque data-block/data-type: el guardado automático los lee
 * del DOM (notebook.collect() en app.js; «Tabla» y «Pregunta» se leen aparte). En lectura se muestra el texto escapado.
 * Textos en app/Language/{es,en}/Notebook.php.
 *
 * @var array $acts  editor: actividades de la asignatura (Activities::forOffering); lectura: las del estudiante por activity_id
 * @var array $files archivos de los bloques «Imagen» y «Archivo» por id (NotebookFiles::forWorkspace)
 * @var string|null $uploadUrl dónde suben archivos los bloques «Imagen» y «Archivo» (solo en el editor)
 */
$edit ??= false;
$acts ??= [];
$files ??= [];
$uploadUrl ??= null;
$d    = $b['data'] ?? [];
$t    = static fn (string $k): string => esc((string) ($d[$k] ?? ''));
$js   = static fn ($v): string => esc((string) json_encode($v, JSON_UNESCAPED_UNICODE), 'attr');
$ce   = static fn (string $field, string $ph) => $edit ? 'contenteditable="true" spellcheck="true" data-field="' . $field . '" data-placeholder="' . esc($ph, 'attr') . '" @input="edited()"' : '';
// Texto con formato (si lo hay), limpiado otra vez al mostrar; si no, el texto plano escapado.
$rich = static fn (): string => ! empty($d['html']) ? \App\Libraries\RichText::clean((string) $d['html']) : '';
$body = static fn (): string => ($h = $rich()) !== '' ? $h : esc((string) ($d['text'] ?? ''));
$nameKey = ['heading' => 'nHeading', 'text' => 'nText', 'callout' => 'nCallout', 'formula' => 'nFormula', 'image' => 'nImage', 'file' => 'nFile', 'divider' => 'nDivider',
    'video' => 'nVideo', 'challenge' => 'nChallenge', 'quiz' => 'nQuiz', 'table' => 'nTable', 'question' => 'nQuestion'];
$what  = t('Notebook.' . ($nameKey[$b['type']] ?? 'nBlock'));
$file  = isset($d['file_id']) ? ($files[(int) $d['file_id']] ?? null) : null;
?>
<div class="blk blk--<?= esc($b['type'], 'attr') ?>" <?= $edit ? 'data-block data-type="' . esc($b['type'], 'attr') . '"' : '' ?>>
  <?php switch ($b['type']):
      case 'heading': ?>
      <h2 class="blk-heading" <?= $ce('text', t('Notebook.phHeading')) ?>><?= $t('text') ?></h2>
      <?php break;
      case 'text': ?>
      <div class="blk-text blk-para" <?= $ce('text', t('Notebook.phText')) ?><?= $edit ? ' data-rich' : '' ?>><?= $body() ?></div>
      <?php break;
      case 'callout': ?>
      <div class="notice notice-accent"><?= icon('lightbulb', 16) ?><div class="blk-text" style="flex:1;min-width:0" <?= $ce('text', t('Notebook.phCallout')) ?><?= $edit ? ' data-rich' : '' ?>><?= $body() ?></div></div>
      <?php break;
      case 'formula': ?>
      <div class="blk-formula" role="math" <?= $ce('text', '1/2 = 2/4') ?>><?= $t('text') ?></div>
      <p class="blk-caption" <?= $ce('caption', t('Notebook.phFormulaCaption')) ?>><?= $t('caption') ?></p>
      <?php break;

      case 'image':
      case 'file':
          $isImg = $b['type'] === 'image';
          $info  = $file ? ['id' => (int) $file['id'], 'name' => $file['file_name'], 'sizeLabel' => \App\Libraries\NotebookFiles::human((int) $file['size']), 'url' => site_url('archivos/' . $file['id'])] : null;
          if ($edit): ?>
        <div class="blk-upload" x-data="fileBlock(<?= $js(['kind' => $b['type'], 'url' => $uploadUrl, 'file' => $info, 'failed' => t('Notebook.uploadFailed')]) ?>)">
          <input type="hidden" data-field="file_id" :value="file ? file.id : ''">
          <?php if ($isImg): ?>
            <img class="blk-image" x-show="file" x-cloak :src="file && file.url" alt="">
          <?php else: ?>
            <div class="blk-filecard" x-show="file" x-cloak><?= icon('paperclip', 18) ?><span style="flex:1;min-width:0"><span class="block font-medium truncate" x-text="file && file.name"></span><span class="block text-xs soft" x-text="file && file.sizeLabel"></span></span></div>
          <?php endif ?>
          <div class="blk-figure" x-show="!file"><?= icon($isImg ? 'image' : 'paperclip', 28) ?><span><?= esc(t($isImg ? 'Notebook.imgHint' : 'Notebook.fileHint')) ?></span></div>
          <div class="row" style="gap:0.5rem;flex-wrap:wrap">
            <label class="btn btn-sm" :class="{ 'is-loading': busy }">
              <?= icon('upload', 14) ?> <span x-text="busy ? <?= $js(t('Notebook.uploading')) ?> : (file ? <?= $js(t($isImg ? 'Notebook.imgReplace' : 'Notebook.fileReplace')) ?> : <?= $js(t($isImg ? 'Notebook.imgUpload' : 'Notebook.fileUpload')) ?>)"></span>
              <input type="file" class="sr-only" accept="<?= $isImg ? 'image/jpeg,image/png,image/webp,image/gif' : '.' . implode(',.', array_keys(\App\Libraries\Resources::TYPES)) ?>" @change="upload($event)" :disabled="busy">
            </label>
            <span class="hint text-danger" x-show="error" x-text="error" x-cloak></span>
          </div>
        </div>
        <p class="blk-caption" <?= $ce('caption', t($isImg ? 'Notebook.phImageCaption' : 'Notebook.phFileCaption')) ?>><?= $t('caption') ?></p>
          <?php elseif ($info && $isImg): ?>
        <figure class="blk-figure-img">
          <img class="blk-image" src="<?= esc($info['url'], 'attr') ?>" alt="<?= esc((string) ($d['caption'] ?? ''), 'attr') ?>" loading="lazy">
          <?php if (trim((string) ($d['caption'] ?? '')) !== ''): ?><figcaption class="blk-caption"><?= $t('caption') ?></figcaption><?php endif ?>
        </figure>
          <?php elseif ($info): ?>
        <div class="blk-filecard">
          <span class="act-icon"><?= icon('paperclip', 18) ?></span>
          <span style="flex:1;min-width:0"><span class="block font-medium truncate"><?= esc($info['name']) ?></span>
            <span class="block text-xs soft"><?= esc(trim((string) ($d['caption'] ?? '')) !== '' ? $d['caption'] . ' · ' . $info['sizeLabel'] : $info['sizeLabel']) ?></span></span>
          <a class="btn btn-sm" href="<?= esc($info['url'], 'attr') ?>"><?= icon('download', 14) ?> <?= esc(t('Notebook.download')) ?></a>
        </div>
          <?php endif;
          break;

      case 'table':
          $rows   = array_values((array) ($d['rows'] ?? []));
          $header = ! empty($d['header']);
          if ($edit):
              $rows = $rows ?: [['', '', ''], ['', '', ''], ['', '', '']]; ?>
        <div class="blk-tablewrap" x-data="tableBlock(<?= $js(['rows' => $rows, 'header' => $header, 'maxRows' => \App\Libraries\Content::TABLE_MAX_ROWS, 'maxCols' => \App\Libraries\Content::TABLE_MAX_COLS]) ?>)">
          <div class="table-wrap">
            <table class="blk-table" :data-header="header ? '1' : '0'" :class="{ 'has-header': header }">
              <template x-for="(row, r) in rows" :key="ver + '-' + r">
                <tr>
                  <template x-for="(cell, c) in row" :key="ver + '-' + r + '-' + c">
                    <td data-cell contenteditable="true" spellcheck="true" x-init="$el.innerText = cell" @input="rows[r][c] = $el.innerText; edited()"
                        :aria-label="label(r, c)"></td>
                  </template>
                  <td class="blk-table-tool"><button type="button" class="btn btn-sm btn-icon btn-danger" @click="delRow(r)" x-show="rows.length > 1" :title="delRowLabel(r)" :aria-label="delRowLabel(r)"><?= icon('x', 12) ?></button></td>
                </tr>
              </template>
              <tr class="blk-table-foot">
                <template x-for="(cell, c) in rows[0]" :key="ver + '-f-' + c">
                  <td><button type="button" class="btn btn-sm btn-icon btn-danger" @click="delCol(c)" x-show="rows[0].length > 1" :title="delColLabel(c)" :aria-label="delColLabel(c)"><?= icon('x', 12) ?></button></td>
                </template>
                <td></td>
              </tr>
            </table>
          </div>
          <div class="row" style="gap:0.5rem;flex-wrap:wrap;align-items:center">
            <button type="button" class="btn btn-sm" @click="addRow()" :disabled="rows.length >= maxRows"><?= icon('rows-3', 14) ?> <?= esc(t('Notebook.addRow')) ?></button>
            <button type="button" class="btn btn-sm" @click="addCol()" :disabled="rows[0].length >= maxCols"><?= icon('columns-3', 14) ?> <?= esc(t('Notebook.addCol')) ?></button>
            <label class="ag-done"><input type="checkbox" x-model="header" @change="edited()"> <?= esc(t('Notebook.headerRow')) ?></label>
            <span class="hint"><?= esc(t('Notebook.tableLimit', [\App\Libraries\Content::TABLE_MAX_ROWS, \App\Libraries\Content::TABLE_MAX_COLS])) ?></span>
          </div>
        </div>
        <p class="blk-caption" <?= $ce('caption', t('Notebook.phTableCaption')) ?>><?= $t('caption') ?></p>
          <?php elseif ($rows): ?>
        <div class="table-wrap blk-tablewrap">
          <table class="blk-table<?= $header ? ' has-header' : '' ?>">
            <?php if (trim((string) ($d['caption'] ?? '')) !== ''): ?><caption class="blk-caption"><?= $t('caption') ?></caption><?php endif ?>
            <?php foreach ($rows as $r => $row): $tag = $header && $r === 0 ? 'th' : 'td'; ?>
              <?= $header && $r === 0 ? '<thead>' : ($r === ($header ? 1 : 0) ? '<tbody>' : '') ?>
              <tr><?php foreach ((array) $row as $cell): ?><<?= $tag ?><?= $tag === 'th' ? ' scope="col"' : '' ?>><?= esc((string) $cell) ?></<?= $tag ?>><?php endforeach ?></tr>
              <?= $header && $r === 0 ? '</thead>' : '' ?>
            <?php endforeach ?>
            <?= count($rows) > ($header ? 1 : 0) ? '</tbody>' : '' ?>
          </table>
        </div>
          <?php endif;
          break;

      case 'question':
          $q    = ['question' => (string) ($d['question'] ?? ''), 'options' => array_values((array) ($d['options'] ?? [])), 'correct' => isset($d['correct']) && is_numeric($d['correct']) ? (int) $d['correct'] : null,
              'explanation' => (string) ($d['explanation'] ?? '')];
          if ($edit):
              $q['options'] = $q['options'] ?: ['', ''];
              $labels = ['option' => t('Notebook.qOption', ['{n}']), 'correct' => t('Notebook.qCorrect', ['{n}']), 'remove' => t('Notebook.qRemoveOption', ['{n}'])]; ?>
        <div class="blk-question" x-data="questionBlock(<?= $js($q + ['labels' => $labels, 'max' => \App\Libraries\Content::QUESTION_MAX_OPTIONS]) ?>)">
          <div class="blk-act-head"><?= icon('message-circle-question', 16) ?><span><?= esc(t('Notebook.qQuestion')) ?></span></div>
          <textarea class="textarea" data-q="question" rows="2" maxlength="500" x-model="question" @input="edited()" placeholder="<?= esc(t('Notebook.qPh'), 'attr') ?>"></textarea>
          <template x-for="(o, i) in options" :key="i">
            <div class="blk-q-opt">
              <input type="radio" :name="name" :checked="correct === i" @change="correct = i; edited()" :aria-label="lbl('correct', i)" :title="lbl('correct', i)">
              <input class="input" data-q-opt maxlength="200" x-model="options[i]" @input="edited()" :placeholder="lbl('option', i)">
              <button type="button" class="btn btn-sm btn-icon btn-danger" @click="removeOpt(i)" x-show="options.length > 2" :title="lbl('remove', i)" :aria-label="lbl('remove', i)"><?= icon('x', 12) ?></button>
            </div>
          </template>
          <input type="hidden" data-q="correct" :value="correct === null ? '' : correct">
          <div class="row" style="gap:0.5rem;flex-wrap:wrap;align-items:center">
            <button type="button" class="btn btn-sm" @click="addOpt()" :disabled="options.length >= max"><?= icon('plus', 14) ?> <?= esc(t('Notebook.qAddOption')) ?></button>
            <span class="hint text-danger" x-show="correct === null" x-cloak><?= esc(t('Notebook.qNoCorrect')) ?></span>
          </div>
          <textarea class="textarea" data-q="explanation" rows="2" maxlength="1000" x-model="explanation" @input="edited()" placeholder="<?= esc(t('Notebook.qExplanationPh'), 'attr') ?>"></textarea>
          <p class="hint"><?= esc(t('Notebook.qHint')) ?></p>
        </div>
          <?php elseif ($q['question'] !== '' && count($q['options']) >= 2 && $q['correct'] !== null):
              // La respuesta correcta y la explicación viajan a la página: es una pregunta de práctica, no evaluada. ?>
        <div class="blk-question is-view" x-data="questionView(<?= $js(['correct' => $q['correct']]) ?>)">
          <div class="blk-act-head"><?= icon('message-circle-question', 16) ?><span><?= esc(t('Notebook.qCheck')) ?></span></div>
          <p class="font-medium" style="white-space:pre-wrap"><?= esc($q['question']) ?></p>
          <div class="blk-q-choices" role="group" aria-label="<?= esc($q['question'], 'attr') ?>">
            <?php foreach ($q['options'] as $i => $o): ?>
              <button type="button" class="blk-q-choice" :class="cls(<?= $i ?>)" :aria-pressed="(picked === <?= $i ?>).toString()" :disabled="solved" @click="pick(<?= $i ?>)">
                <span class="blk-q-mark" aria-hidden="true"><?= chr(65 + $i) ?></span><span><?= esc($o) ?></span></button>
            <?php endforeach ?>
          </div>
          <div aria-live="polite">
            <p class="blk-q-feedback is-right" x-show="solved" x-cloak><?= icon('circle-check', 16) ?> <?= esc(t('Notebook.qRight')) ?></p>
            <p class="blk-q-feedback is-wrong" x-show="picked !== null && !solved" x-cloak><?= icon('circle-x', 16) ?> <?= esc(t('Notebook.qWrong')) ?></p>
          </div>
          <?php if (trim($q['explanation']) !== ''): ?><div class="ag-modal-notes" x-show="solved" x-cloak><?= esc($q['explanation']) ?></div><?php endif ?>
          <button type="button" class="btn btn-sm" x-show="solved" x-cloak @click="reset()"><?= icon('rotate-ccw', 14) ?> <?= esc(t('Notebook.qTryAgain')) ?></button>
        </div>
          <?php endif;
          break;

      case 'divider': ?>
      <hr class="blk-divider">
      <?php break;

      case 'challenge':
      case 'quiz':
          $isQuiz = $b['type'] === 'quiz';
          $aid    = (int) ($d['activity_id'] ?? 0);
          if ($edit):
              $opts = [];
              foreach ($acts as $a) {
                  if ($a['kind'] !== $b['type']) {
                      continue;
                  }
                  $opts[] = ['id' => (int) $a['id'], 'title' => $a['title'], 'state' => $a['status'] !== 'published' ? t('Notebook.actDraft')
                      : ($a['due_at'] ? t('Notebook.actPublishedCloses', [date_short($a['due_at'])]) : t('Notebook.actPublishedNoDates'))];
              } ?>
        <div class="blk-act" x-data="{ sel: '<?= $aid ?: '' ?>', list: <?= $js($opts) ?>, get cur() { return this.list.find((a) => String(a.id) === String(this.sel)) } }">
          <div class="blk-act-head"><?= icon($isQuiz ? 'clipboard-list' : 'target', 16) ?><span><?= esc(t($isQuiz ? 'Notebook.bQuiz' : 'Notebook.bChallenge')) ?></span></div>
          <?php if ($opts): ?>
            <label class="field"><span class="sr-only"><?= esc(t('Notebook.actChoose', [t($isQuiz ? 'Notebook.actTheQuiz' : 'Notebook.actThe')])) ?></span>
              <select class="select" data-field="activity_id" x-model="sel" @change="edited()">
                <option value=""><?= esc(t('Notebook.actChooseFrom', [t($isQuiz ? 'Notebook.actOneQuiz' : 'Notebook.actOne')])) ?></option>
                <?php foreach ($opts as $o): ?><option value="<?= $o['id'] ?>" <?= $o['id'] === $aid ? 'selected' : '' ?>><?= esc($o['title']) ?></option><?php endforeach ?>
              </select></label>
            <p class="hint" x-show="cur" x-text="cur && cur.state"></p>
            <p class="hint"><?= esc(t('Notebook.actStudentsSee')) ?></p>
          <?php else: ?>
            <input type="hidden" data-field="activity_id" value="">
            <p class="hint"><?= str_replace('%LINK%', '<a class="link" href="' . site_url('actividades') . '">' . esc(t('Notebook.activities')) . '</a>', esc(t($isQuiz ? 'Notebook.actNoneQuiz' : 'Notebook.actNone', ['%LINK%']))) ?></p>
          <?php endif ?>
        </div>
          <?php elseif ($aid && isset($acts[$aid])):
              $a     = $acts[$aid];
              $badge = match ($a['state']) {
                  'graded'      => badge(t('Notebook.grade', [number_format((float) $a['grade'], 1, ',', '')]), (float) $a['grade'] < 3 ? 'danger' : ((float) $a['grade'] < 4 ? 'warn' : 'ok')),
                  'submitted'   => badge(t('Notebook.stSubmitted'), 'accent'),
                  'returned'    => badge(t('Notebook.stReturned'), 'warn'),
                  'in_progress' => badge(t('Notebook.stInProgress')),
                  'late'        => badge(t('Notebook.stLate'), 'warn'),
                  'missed'      => badge(t('Notebook.stMissed'), 'danger'),
                  default       => badge(t('Notebook.stTodo')),
              };
              $cta = match ($a['state']) {
                  'graded', 'submitted', 'missed' => t('Notebook.ctaSee', [t($isQuiz ? 'Notebook.actTheQuiz' : 'Notebook.actThe')]),
                  'in_progress'                   => t('Notebook.ctaContinue'),
                  'returned'                      => t('Notebook.ctaFix'),
                  default                         => t($isQuiz ? 'Notebook.ctaQuiz' : 'Notebook.ctaChallenge'),
              }; ?>
        <div class="blk-act-card" style="--cat:<?= cat_color($a['color'] ?? null) ?>">
          <span class="act-icon"><?= icon($isQuiz ? 'clipboard-list' : 'target', 18) ?></span>
          <span style="flex:1;min-width:0">
            <span class="block text-xs soft"><?= esc(t($isQuiz ? 'Notebook.bQuiz' : 'Notebook.bChallenge')) ?> · <?= esc($a['subject']) ?></span>
            <span class="block font-medium"><?= esc($a['title']) ?></span>
            <span class="row text-xs soft" style="gap:0.375rem;flex-wrap:wrap"><?= $a['due_at'] ? esc(t('Notebook.closes', [date_short($a['due_at'])])) : '' ?> <?= $badge ?></span>
          </span>
          <a class="btn btn-sm btn-primary" href="<?= site_url($a['url']) ?>"><?= esc($cta) ?> <?= icon('arrow-right', 14) ?></a>
        </div>
          <?php elseif ($aid): ?>
        <div class="blk-act-card is-muted"><span class="act-icon"><?= icon($isQuiz ? 'clipboard-list' : 'target', 18) ?></span>
          <span class="text-sm soft"><?= esc(t($isQuiz ? 'Notebook.actWillAppearQuiz' : 'Notebook.actWillAppear')) ?></span></div>
          <?php endif;
          break;

      case 'video':
          $vurl = (string) ($d['url'] ?? '');
          $vid  = \App\Libraries\VideoEmbed::parse($vurl);
          $vcap = trim((string) ($d['caption'] ?? ''));
          if ($edit): ?>
        <div class="blk-video" x-data="videoBlock(<?= $js($vurl) ?>)">
          <label class="input-icon blk-video-url"><span class="sr-only"><?= esc(t('Notebook.videoUrl')) ?></span><?= icon('link', 16) ?>
            <input class="input" type="url" inputmode="url" data-field="url" x-model.debounce.400ms="url" @input="edited()" maxlength="500"
                   placeholder="<?= esc(t('Notebook.videoPh'), 'attr') ?>" spellcheck="false" autocomplete="off"></label>
          <p class="hint text-danger" x-show="url.trim() && !src" x-cloak><?= esc(t('Notebook.videoBad')) ?></p>
          <div class="blk-video-frame" x-show="src" x-cloak>
            <template x-if="src"><iframe :src="src" title="<?= esc(t('Notebook.videoPreview'), 'attr') ?>" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"
              allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen
              sandbox="allow-scripts allow-same-origin allow-presentation allow-popups allow-popups-to-escape-sandbox"></iframe></template>
          </div>
          <div class="blk-figure" x-show="!src" x-cloak><?= icon('video', 28) ?><span><?= esc(t('Notebook.videoPaste')) ?></span></div>
        </div>
        <p class="blk-caption" <?= $ce('caption', t('Notebook.phVideoCaption')) ?>><?= $t('caption') ?></p>
          <?php elseif ($vid): ?>
        <div class="blk-video-frame">
          <iframe src="<?= esc(\App\Libraries\VideoEmbed::src($vid), 'attr') ?>" title="<?= esc($vcap !== '' ? t('Notebook.videoTitled', [$vcap]) : t('Notebook.videoOf', [\App\Libraries\VideoEmbed::PROVIDERS[$vid['provider']]]), 'attr') ?>"
                  loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen
                  sandbox="allow-scripts allow-same-origin allow-presentation allow-popups allow-popups-to-escape-sandbox"></iframe>
        </div>
        <?php if ($vcap !== ''): ?><p class="blk-caption"><?= $t('caption') ?></p><?php endif ?>
          <?php endif;
          break;
  endswitch ?>
  <?php if ($edit): ?>
    <div class="blk-actions">
      <button type="button" class="btn btn-sm btn-icon" title="<?= esc(t('Notebook.addBelow', [$what]), 'attr') ?>" aria-label="<?= esc(t('Notebook.addBelow', [$what]), 'attr') ?>" aria-haspopup="true"
              @click.stop="openInsertAfter($el.closest('[data-block]'), $el)"><?= icon('plus', 14) ?></button>
      <button type="button" class="btn btn-sm btn-icon" title="<?= esc(t('Notebook.moveUp', [$what]), 'attr') ?>" aria-label="<?= esc(t('Notebook.moveUp', [$what]), 'attr') ?>" @click="move($el.closest('[data-block]'), -1)"><?= icon('arrow-up', 14) ?></button>
      <button type="button" class="btn btn-sm btn-icon" title="<?= esc(t('Notebook.moveDown', [$what]), 'attr') ?>" aria-label="<?= esc(t('Notebook.moveDown', [$what]), 'attr') ?>" @click="move($el.closest('[data-block]'), 1)"><?= icon('arrow-down', 14) ?></button>
      <button type="button" class="btn btn-sm btn-icon btn-danger" title="<?= esc(t('Notebook.remove', [$what]), 'attr') ?>" aria-label="<?= esc(t('Notebook.remove', [$what]), 'attr') ?>" @click="removeBlock($el.closest('[data-block]'))"><?= icon('trash-2', 14) ?></button>
    </div>
  <?php endif ?>
</div>
