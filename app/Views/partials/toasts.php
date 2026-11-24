<?php
$initial = [];
if ($t = session('toast')) {
    $initial[] = $t;
}
if (session('errors') && ! session('toast')) {
    $initial[] = ['type' => 'error', 'msg' => t('App.checkFields')];
}
?>
<div class="toasts" x-data="toasts(<?= esc(json_encode($initial), 'attr') ?>)" aria-live="assertive" role="status">
  <template x-for="t in items" :key="t.id">
    <div class="toast" :class="'toast-' + t.type">
      <i data-lucide="circle-check" class="icon" style="width:16px;height:16px" x-show="t.type === 'ok'" aria-hidden="true"></i>
      <i data-lucide="circle-alert" class="icon" style="width:16px;height:16px" x-show="t.type === 'error'" aria-hidden="true"></i>
      <i data-lucide="info" class="icon soft" style="width:16px;height:16px" x-show="t.type === 'info'" aria-hidden="true"></i>
      <span x-text="t.msg"></span>
      <button type="button" class="btn btn-ghost btn-sm btn-icon ml-auto" style="height:1.5rem;width:1.5rem" @click="remove(t.id)" aria-label="<?= esc(t('App.closeNotice'), 'attr') ?>">×</button>
    </div>
  </template>
</div>
