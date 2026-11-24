<?php
/**
 * Botón flotante de contacto (páginas públicas: inicio y login): WhatsApp y correo. Datos en App\Libraries\Contact.
 * Sin ningún dato configurado no se muestra.
 */
$c = (new \App\Libraries\Contact())->get();
if ($c['whatsapp'] === '' && $c['email'] === '') {
    return;
}
$app = config('AgendaEscolar')->appName;
$msg = t('App.contactWaText', [$app]);
?>
<div class="contact-fab" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
  <div class="contact-panel" x-show="open" x-cloak role="dialog" aria-label="<?= esc(t('App.contactTitle'), 'attr') ?>">
    <p class="font-medium"><?= esc(t('App.contactTitle')) ?></p>
    <p class="text-xs soft"><?= esc(t('App.contactIntro')) ?></p>
    <?php if ($c['whatsapp'] !== ''): ?>
      <a class="btn contact-wa" href="https://wa.me/<?= esc($c['whatsapp'], 'url') ?>?text=<?= rawurlencode($msg) ?>" target="_blank" rel="noopener noreferrer">
        <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.9 11.9 0 0 0 4.5 4c1.7.7 2.4.8 3.2.6.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.2-1.2-.1-.1-.3-.2-.5-.3Z"/></svg>
        WhatsApp</a>
    <?php endif ?>
    <?php if ($c['email'] !== ''): ?>
      <a class="btn" href="mailto:<?= esc($c['email'], 'attr') ?>?subject=<?= rawurlencode(t('App.contactMailSubject', [$app])) ?>"><?= icon('mail', 16) ?> <?= esc($c['email']) ?></a>
    <?php endif ?>
  </div>
  <button type="button" class="contact-btn" @click="open = !open" :aria-expanded="open.toString()" aria-label="<?= esc(t('App.contactOpen'), 'attr') ?>" title="<?= esc(t('App.contactOpen'), 'attr') ?>">
    <span x-show="!open"><?= icon('message-circle', 22) ?></span><span x-show="open" x-cloak><?= icon('x', 22) ?></span>
  </button>
</div>
