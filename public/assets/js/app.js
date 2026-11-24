/** Texto traducido (window.I18N, ver partials/head): t('clave', 'texto por defecto', { n: 3 }) reemplaza {n}. */
window.t = (key, fallback, vars) => {
  let s = (window.I18N && typeof window.I18N[key] === 'string') ? window.I18N[key] : (fallback ?? key);
  if (vars) Object.keys(vars).forEach((k) => { s = s.split('{' + k + '}').join(String(vars[k])); });
  return s;
};

/*
 * Agenda Escolar — comportamiento de la interfaz (Alpine.js + lucide).
 * Base común de las plataformas (tema, shell, menús, toasts, crud) + componentes propios (cuaderno).
 * Este archivo se carga ANTES de Alpine (ambos con defer) para registrar los componentes.
 */

function safeStorage(fn, fallback) {
  try { return fn(); } catch (e) { return fallback; }
}

/* Tema claro / oscuro / automático (guardado por navegador). */
window.appTheme = {
  get() { return safeStorage(() => localStorage.getItem('agenda-theme'), null) || 'auto'; },
  set(value) {
    safeStorage(() => value === 'auto' ? localStorage.removeItem('agenda-theme') : localStorage.setItem('agenda-theme', value));
    if (value === 'auto') document.documentElement.removeAttribute('data-theme');
    else document.documentElement.setAttribute('data-theme', value);
  },
};

window.toast = (msg, type = 'info') => window.dispatchEvent(new CustomEvent('toast', { detail: { msg, type } }));

document.addEventListener('alpine:init', () => {
  /* Shell: menú móvil (rail) y tema. */
  Alpine.data('shell', () => ({
    navOpen: false,
    collapsed: safeStorage(() => localStorage.getItem('agenda-rail') === 'collapsed', false),
    toggleCollapsed() {
      this.collapsed = !this.collapsed;
      safeStorage(() => this.collapsed ? localStorage.setItem('agenda-rail', 'collapsed') : localStorage.removeItem('agenda-rail'));
    },
    theme: window.appTheme.get(),
    setTheme(value) { this.theme = value; window.appTheme.set(value); },
  }));

  /*
   * Asistente flotante de la interfaz. Se arrastra con el puntero (ratón, lápiz o dedo) o con Alt + flechas; la posición
   * y si está oculto se recuerdan en este navegador. La conversación dura la sesión de la pestaña. Un arrastre de menos
   * de 5 px cuenta como clic (abre o cierra el panel).
   */
  Alpine.data('helpAssistant', (opts) => ({
    size: 56,
    open: false,
    hidden: safeStorage(() => localStorage.getItem('agenda-assistant-hidden') === '1', false),
    x: 0,
    y: 0,
    side: 'left',
    vside: 'top',
    dragging: false,
    moved: false,
    thread: safeStorage(() => JSON.parse(sessionStorage.getItem('agenda-assistant-thread') || '[]'), []),
    text: '',
    sending: false,
    init() {
      const p = safeStorage(() => JSON.parse(localStorage.getItem('agenda-assistant-pos') || 'null'), null);
      this.x = p ? p.x : window.innerWidth - this.size - 24;
      this.y = p ? p.y : window.innerHeight - this.size - 24;
      this.clamp();
    },
    get pos() { return 'left:' + this.x + 'px;top:' + this.y + 'px'; },
    clamp() {
      const m = 8;
      this.x = Math.min(Math.max(m, this.x), window.innerWidth - this.size - m);
      this.y = Math.min(Math.max(m, this.y), window.innerHeight - this.size - m);
      this.side = this.x + this.size / 2 > window.innerWidth / 2 ? 'left' : 'right';   // el panel se abre hacia donde hay espacio
      this.vside = this.y + this.size / 2 > window.innerHeight / 2 ? 'top' : 'bottom';
    },
    save() { safeStorage(() => localStorage.setItem('agenda-assistant-pos', JSON.stringify({ x: Math.round(this.x), y: Math.round(this.y) }))); },
    dragStart(e) {
      if (e.button !== 0) return;
      const el = this.$refs.avatar, sx = e.clientX, sy = e.clientY, ox = this.x, oy = this.y;
      this.moved = false;
      el.setPointerCapture(e.pointerId);
      const move = (ev) => {
        const dx = ev.clientX - sx, dy = ev.clientY - sy;
        if (!this.moved && Math.hypot(dx, dy) < 5) return;
        this.moved = true;
        this.dragging = true;
        this.x = ox + dx;
        this.y = oy + dy;
        this.clamp();
      };
      const up = () => {
        el.removeEventListener('pointermove', move);
        el.removeEventListener('pointerup', up);
        el.removeEventListener('pointercancel', up);
        if (this.moved) this.save();
        setTimeout(() => { this.dragging = false; }, 0);
      };
      el.addEventListener('pointermove', move);
      el.addEventListener('pointerup', up);
      el.addEventListener('pointercancel', up);
    },
    click() {
      if (this.moved) { this.moved = false; return; } // fue un arrastre, no un clic
      this.open = !this.open;
      if (this.open) this.$nextTick(() => { this.scroll(); if (this.$refs.q) this.$refs.q.focus(); });
    },
    nudge(dx, dy) { this.x += dx; this.y += dy; this.clamp(); this.save(); },
    /* Abre el panel desde otro lugar (por ejemplo, «Pregúntale al asistente» de la Ayuda), aunque esté oculto. */
    show() {
      if (this.hidden) this.toggleHidden();
      this.open = true;
      this.$nextTick(() => { this.scroll(); if (this.$refs.q) this.$refs.q.focus(); });
    },
    toggleHidden() {
      this.hidden = !this.hidden;
      this.open = false;
      safeStorage(() => (this.hidden ? localStorage.setItem('agenda-assistant-hidden', '1') : localStorage.removeItem('agenda-assistant-hidden')));
      if (this.hidden) {
        window.toast(t('assistantHidden', 'Asistente oculto. Vuelve a mostrarlo desde el menú de tu cuenta.'), 'info');
      } else {
        this.clamp();
        this.$nextTick(() => this.$refs.avatar.focus());
      }
    },
    persist() { safeStorage(() => sessionStorage.setItem('agenda-assistant-thread', JSON.stringify(this.thread.slice(-20)))); },
    clear() { this.thread = []; this.persist(); this.$nextTick(() => this.$refs.q && this.$refs.q.focus()); },
    scroll() { const b = this.$refs.body; if (b) b.scrollTop = b.scrollHeight; },
    async ask(q) {
      const msg = String(q ?? this.text).trim();
      if (!msg || this.sending) return;
      const history = this.thread.slice(-6).map((m) => ({ role: m.role, text: m.text }));
      this.thread.push({ role: 'user', text: msg });
      this.text = '';
      this.sending = true;
      this.$nextTick(() => this.scroll());
      try {
        const out = await window.postJson(opts.url, { message: msg, history, page: location.pathname, title: document.title });
        if (out.ok) {
          this.thread.push({ role: 'assistant', text: out.reply, link: out.link || null, article: out.article || null, source: out.source, simulated: !!out.simulated });
          if (out.notice) window.toast(out.notice, 'info');
        } else { this.thread.pop(); this.text = msg; window.toast(out.error || t('noAnswer', 'No pude responder.'), 'error'); }
      } catch (e) { this.thread.pop(); this.text = msg; window.toast(t('offline', 'Sin conexión: intenta de nuevo.'), 'error'); }
      this.sending = false;
      this.persist();
      this.$nextTick(() => this.scroll());
    },
  }));

  /* Redacción de mensajes: destinos (rol, grupo, persona) como fichas y conteo en vivo de destinatarios. */
  Alpine.data('messageComposer', (opts) => ({
    targets: [],
    q: '',
    results: [],
    count: null,
    add(key, label) {
      if (this.targets.some((t) => t.key === key)) return;
      this.targets.push({ key, label });
      this.recount();
    },
    remove(key) { this.targets = this.targets.filter((t) => t.key !== key); this.recount(); },
    async search() {
      if (this.q.trim().length < 2) { this.results = []; return; }
      try { this.results = await (await fetch(opts.people + '?q=' + encodeURIComponent(this.q.trim()), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })).json(); } catch (e) { this.results = []; }
    },
    async recount() {
      if (!this.targets.length) { this.count = null; return; }
      this.count = null;
      try { const out = await window.postJson(opts.count, { targets: this.targets.map((t) => t.key) }); this.count = out.count ?? 0; } catch (e) { this.count = 0; }
    },
  }));

  /* Menú desplegable simple. */
  Alpine.data('dropdown', () => ({
    open: false,
    toggle() { this.open = !this.open; if (this.open) this.$nextTick(() => window.lucide && lucide.createIcons()); },
    close() { this.open = false; },
  }));

  /* Toasts: los del servidor (flash) y los que se lancen con window.toast(). */
  Alpine.data('toasts', (initial) => ({
    items: [],
    init() {
      (initial || []).forEach((t) => this.push(t));
      window.addEventListener('toast', (e) => this.push(e.detail));
    },
    push(t) {
      const id = Date.now() + Math.random();
      this.items.push({ id, type: t.type || 'info', msg: t.msg });
      if (this.items.length > 3) this.items.shift();
      setTimeout(() => this.remove(id), t.type === 'error' ? 7000 : 3500);
      this.$nextTick(() => window.lucide && lucide.createIcons());
    },
    remove(id) { this.items = this.items.filter((i) => i.id !== id); },
  }));

  /*
   * Listado con Drawer de edición (igual que en las otras plataformas). Uso:
   *   x-data="crud({ blank: {...}, reopen: <estado tras error de validación o null> })"
   *   @click="edit({...})"  ·  @click="create()"  ·  ask({ title, message, button, action })
   */
  Alpine.data('crud', (opts) => ({
    open: false,
    mode: 'create',
    blank: opts.blank || {},
    form: { ...(opts.blank || {}) },
    confirm: null,
    init() {
      if (opts.reopen) {
        this.mode = opts.reopen.mode || 'create';
        this.form = { ...this.blank, ...opts.reopen.form };
        this.open = true;
        this.focusFirst();
      }
    },
    create() { this.mode = 'create'; this.form = { ...this.blank }; this.open = true; this.focusFirst(); },
    edit(row) { this.mode = 'edit'; this.form = { ...this.blank, ...row }; this.open = true; this.focusFirst(); },
    close() { this.open = false; },
    focusFirst() {
      this.$nextTick(() => {
        if (window.lucide) lucide.createIcons();
        const el = this.$root.querySelector('.drawer [data-autofocus]');
        if (el) el.focus();
      });
    },
    ask(c) { this.confirm = c; this.$nextTick(() => window.lucide && lucide.createIcons()); },
  }));

  /*
   * Ajustes › Estructura académica: un Drawer para varios tipos de registro (periodo, categoría, asignatura, curso,
   * grupo, oferta, sede), confirmación de borrado y asignación de docentes a una oferta.
   */
  Alpine.data('structure', (opts) => ({
    open: false,
    mode: 'create',
    type: 'periodo',
    id: null,
    form: {},
    confirm: null,
    teach: null,
    base: opts.base,
    init() {
      const r = opts.reopen;
      if (r && r.type) {
        this.type = r.type; this.mode = r.mode; this.id = r.form.id || null;
        this.form = { ...(opts.blank[r.type] || {}), ...r.form };
        this.open = true; this.focusFirst();
      }
    },
    create(type, preset = {}) { this.type = type; this.mode = 'create'; this.id = null; this.form = { ...(opts.blank[type] || {}), ...preset }; this.open = true; this.focusFirst(); },
    edit(type, row) { this.type = type; this.mode = 'edit'; this.id = row.id; this.form = { ...(opts.blank[type] || {}), ...row }; this.open = true; this.focusFirst(); },
    close() { this.open = false; },
    action() { return this.base + '/' + this.type + (this.mode === 'edit' && this.id ? '/' + this.id : ''); },
    ask(type, id, label) { this.confirm = { type, id, label }; },
    teachersFor(id, ids, label) { this.teach = { id, ids, label }; },
    focusFirst() {
      this.$nextTick(() => {
        if (window.lucide) lucide.createIcons();
        const el = this.$root.querySelector('.drawer [data-autofocus]');
        if (el) el.focus();
      });
    },
  }));

  /* Editor de evaluación: preguntas en el navegador; el formulario envía todo como JSON (questions_json). */
  Alpine.data('quizEditor', (initial, opts = {}) => ({
    questions: (initial || []).map((q) => ({ topic: '', difficulty: 'medium', ...q, topic: q.topic || '', correct: q.correct ?? [] })),
    dirty: false,
    addOpen: false,
    blank(type) {
      const base = { type, text: '', points: 1, topic: '', difficulty: 'medium', options: [], correct: [] };
      if (type === 'single' || type === 'multiple') { base.options = ['', '', '']; }
      if (type === 'truefalse') { base.options = [t('true', 'Verdadero'), t('false', 'Falso')]; base.correct = [0]; }
      if (type === 'numeric') base.correct = { value: '', tolerance: 0 };
      if (type === 'long') base.points = 5;
      return base;
    },
    add(type) { this.questions.push(this.blank(type)); this.addOpen = false; this.dirty = true; },
    retype(q) { const b = this.blank(q.type); q.options = b.options; q.correct = b.correct; this.dirty = true; },
    mark(q, j, on) {
      if (q.type === 'single') q.correct = [j];
      else q.correct = on ? [...new Set([...q.correct, j])] : q.correct.filter((x) => x !== j);
      this.dirty = true;
    },
    dropOption(q, j) {
      q.options.splice(j, 1);
      q.correct = q.correct.filter((x) => x !== j).map((x) => (x > j ? x - 1 : x));
      this.dirty = true;
    },
    move(i, d) { const t = this.questions[i + d]; this.questions[i + d] = this.questions[i]; this.questions[i] = t; this.dirty = true; },
    total() { return this.questions.reduce((a, q) => a + Number(q.points || 0), 0); },

    // «Agregar desde un banco»: carga las preguntas del banco elegido (JSON) y copia las marcadas al editor.
    pick: { open: false, bank: '', topic: '', difficulty: '', items: [], topics: [], chosen: [], loading: false },
    openPicker() { this.pick.open = true; if (this.pick.bank && !this.pick.items.length) this.loadBank(); },
    async loadBank() {
      if (!this.pick.bank) { this.pick.items = []; return; }
      this.pick.loading = true;
      this.pick.chosen = [];
      try {
        const qs = new URLSearchParams({ topic: this.pick.topic, difficulty: this.pick.difficulty });
        const res = await fetch(opts.bankUrl.replace('{id}', this.pick.bank) + '?' + qs, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const out = await res.json();
        this.pick.items = out.questions || [];
        this.pick.topics = out.topics || [];
      } catch (e) { window.toast(t('bankLoadError', 'No se pudieron cargar las preguntas del banco.'), 'error'); }
      this.pick.loading = false;
    },
    togglePick(id, on) { this.pick.chosen = on ? [...new Set([...this.pick.chosen, id])] : this.pick.chosen.filter((x) => x !== id); },
    addPicked() {
      const items = this.pick.items.filter((q) => this.pick.chosen.includes(q.id));
      items.forEach(({ id, ...q }) => this.questions.push(JSON.parse(JSON.stringify(q))));
      this.dirty = true;
      this.pick.open = false;
      this.pick.chosen = [];
      window.toast(items.length === 1 ? t('addedOne', 'Se agregó 1 pregunta. Guarde para conservarla.') : t('addedMany', 'Se agregaron {n} preguntas. Guarde para conservarlas.', { n: items.length }), 'ok');
    },
    // «Generar con IA» (copiloto docente): propone preguntas que se agregan al editor para revisar antes de guardar.
    gen: { open: false, topic: '', count: 5, types: ['single', 'multiple', 'truefalse'], difficulty: '', notes: '', loading: false },
    async generate() {
      if (!this.gen.topic.trim() || this.gen.loading) return;
      this.gen.loading = true;
      try {
        const out = await window.postJson(opts.aiUrl, { topic: this.gen.topic, count: this.gen.count, types: this.gen.types, difficulty: this.gen.difficulty, notes: this.gen.notes, ...(opts.aiContext || {}) });
        window.renderQuota(out.quota);
        if (out.ok) {
          out.questions.forEach((q) => this.questions.push(q));
          this.dirty = true;
          this.gen.open = false;
          const n = out.questions.length;
          window.toast((n === 1 ? t('aiAddedOne', 'Se agregó 1 pregunta') : t('aiAddedMany', 'Se agregaron {n} preguntas', { n })) + (out.simulated ? t('aiSimulated', ' simuladas') : t('aiProposed', ' propuestas por la IA')) + t('aiReview', '. Revíselas y guarde.') + (out.discarded ? t('aiDiscarded', ' Se descartaron {n} inválidas.', { n: out.discarded }) : ''), 'ok');
        } else window.toast(out.error || t('aiGenError', 'La IA no pudo generar preguntas.'), 'error');
      } catch (e) { window.toast(t('offline', 'Sin conexión: intente de nuevo.'), 'error'); }
      this.gen.loading = false;
    },
  }));

  /*
   * Presentación de una evaluación: una pregunta a la vez, reloj hasta la hora límite y guardado automático de
   * respuestas (JSON). Al acabarse el tiempo se envía solo.
   */
  /*
   * IA (documento maestro 5.1 y 5.8). postJson: POST con el token CSRF; renderQuota: actualiza el saldo visible
   * (partials/ai_quota) con el que devuelve el servidor tras cada respuesta.
   */
  window.postJson = async (url, data) => {
    const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify(data) });
    const out = await res.json().catch(() => ({ ok: false, error: t('unexpected', 'Respuesta inesperada del servidor.') }));
    return out;
  };
  window.renderQuota = (q) => {
    if (!q) return;
    document.querySelectorAll('[data-ai-quota]').forEach((el) => {
      const left = Math.max(0, q.limit - q.used), pct = Math.round((q.used * 100) / Math.max(1, q.limit));
      el.classList.remove('is-ok', 'is-warn', 'is-danger');
      el.classList.add(q.blocked || pct >= 100 ? 'is-danger' : pct >= 80 ? 'is-warn' : 'is-ok');
      const txt = el.querySelector('[data-q-text]'), bar = el.querySelector('[data-q-bar]');
      if (txt) txt.innerHTML = el.dataset.asQueries === '1'
        ? t('quotaLeft', 'Te quedan unas <strong>{n}</strong> consultas hoy', { n: Math.floor(left / Math.max(1, q.per_query)) })
        : t('quotaTokens', 'Hoy: <strong>{left}</strong> de {limit} tokens disponibles', { left: left.toLocaleString(document.documentElement.lang || 'es'), limit: q.limit.toLocaleString(document.documentElement.lang || 'es') });
      if (bar) { bar.firstElementChild.style.width = Math.min(100, pct) + '%'; bar.setAttribute('aria-label', t('quotaUsedPct', 'Usado hoy: {n} % del cupo', { n: pct })); }
    });
  };

  // Tutor del estudiante en un reto: conversación guardada en el servidor.
  /*
   * Búsqueda global de la barra superior (combobox accesible). El servidor decide el alcance (plataforma, red o
   * institución) y filtra por permisos. Los resultados que cambian de contexto (entrar a una institución o a una red)
   * se envían por POST con el token CSRF.
   */
  Alpine.data('globalSearch', (opts) => ({
    q: '',
    results: [],
    open: false,
    loading: false,
    active: -1,
    timer: null,
    seq: 0,
    queue() {
      clearTimeout(this.timer);
      if (this.q.trim().length < 2) { this.results = []; this.open = false; return; }
      this.timer = setTimeout(() => this.search(), 250);
    },
    async search() {
      const mine = ++this.seq;
      this.loading = true;
      this.open = true;
      try {
        const res = await fetch(opts.url + '?' + new URLSearchParams({ scope: opts.scope, q: this.q.trim() }), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const out = await res.json();
        if (mine !== this.seq) return; // llegó una búsqueda más reciente
        this.results = out.results || [];
        this.active = this.results.length ? 0 : -1;
      } catch (e) { if (mine === this.seq) this.results = []; }
      this.loading = false;
      this.$nextTick(() => window.lucide && lucide.createIcons());
    },
    move(d) {
      if (!this.results.length) return;
      this.open = true;
      this.active = (this.active + d + this.results.length) % this.results.length;
      const el = document.getElementById('sr-' + this.active);
      if (el) el.scrollIntoView({ block: 'nearest' });
    },
    go(i) {
      const r = this.results[i];
      if (!r) return;
      if (r.post === null || r.post === undefined) { window.location.href = r.url; return; }
      const f = document.createElement('form');
      f.method = 'post';
      f.action = r.url;
      const fields = Object.assign({ [document.querySelector('meta[name="csrf-name"]').content]: document.querySelector('meta[name="csrf-token"]').content }, r.post);
      Object.entries(fields).forEach(([k, v]) => { const inp = document.createElement('input'); inp.type = 'hidden'; inp.name = k; inp.value = v; f.appendChild(inp); });
      document.body.appendChild(f);
      f.submit();
    },
  }));
  Alpine.data('tutorChat', (opts) => ({
    thread: opts.thread || [],
    text: '',
    sending: false,
    /** Respuesta con formato (Markdown → HTML seguro: el texto se escapa antes de dar formato). */
    md(text) { return window.mdLite.toHtml(text); },
    /** Copia con formato (Word, Google Docs, correo) y, para donde solo se pega texto, sin símbolos de Markdown. */
    async copy(text) {
      const html = window.mdLite.toHtml(text);
      const plain = window.mdLite.toPlain(text);
      try {
        if (window.ClipboardItem && navigator.clipboard.write) {
          await navigator.clipboard.write([new ClipboardItem({
            'text/html': new Blob(['<meta charset="utf-8">' + html], { type: 'text/html' }),
            'text/plain': new Blob([plain], { type: 'text/plain' }),
          })]);
        } else {
          await navigator.clipboard.writeText(plain);
        }
        window.toast(t('copiedRich', 'Copiado con formato. Pégalo en la página, en Word o en Google Docs.'), 'ok');
      } catch (e) {
        window.toast(t('copyFailed', 'El navegador no permitió copiar. Selecciona el texto y usa Ctrl+C.'), 'error');
      }
    },
    /** Convierte la respuesta en bloques de la página (títulos, párrafos, notas, separadores). */
    insertInPage(text) {
      this.$dispatch('ai-insert', { blocks: window.mdLite.toBlocks(text) });
    },
    init() { this.$nextTick(() => this.scroll()); },
    scroll() { const b = this.$refs.body; if (b) b.scrollTop = b.scrollHeight; },
    /* «Lectura en voz alta en el tutor» (Mi perfil): voz del navegador, en español. */
    speak(text) {
      if (document.body.dataset.tts !== '1' || !('speechSynthesis' in window)) return;
      const u = new SpeechSynthesisUtterance(String(text).replace(/[*_#`>]/g, ''));
      u.lang = 'es-CO';
      window.speechSynthesis.cancel();
      window.speechSynthesis.speak(u);
    },
    async send() {
      const msg = this.text.trim();
      if (!msg || this.sending) return;
      this.thread.push({ role: 'user', text: msg });
      this.text = '';
      this.sending = true;
      this.$nextTick(() => this.scroll());
      try {
        const out = await window.postJson(opts.url, { message: msg });
        window.renderQuota(out.quota);
        if (out.ok) { this.thread.push({ role: 'assistant', text: out.reply, simulated: out.simulated }); this.speak(out.reply); }
        else { this.thread.pop(); this.text = msg; window.toast(out.error || t('tutorError', 'El tutor no pudo responder.'), 'error'); }
      } catch (e) { this.thread.pop(); this.text = msg; window.toast(t('offline', 'Sin conexión: intenta de nuevo.'), 'error'); }
      this.sending = false;
      this.$nextTick(() => this.scroll());
    },
  }));
  /*
   * Barra de formato flotante del cuaderno (como la de un editor de documentos): aparece sobre el texto seleccionado en
   * un párrafo o una nota ([data-rich]). Negrita, cursiva, subrayado y tachado con los comandos del navegador; resaltado
   * y color de texto con clases de una paleta fija (hl-*, tc-*), que se ven bien en tema claro y oscuro y son lo único
   * que acepta el servidor (App\Libraries\RichText). Avisa los cambios con el evento «fmt-changed» (guardado automático).
   */
  Alpine.data('fmtBar', () => ({
    show: false,
    x: 0,
    y: 0,
    pop: null, // 'fs' | 'hl' | 'tc' | null
    state: {},
    field: null,
    range: null,
    init() {
      this._onSel = () => this.update();
      document.addEventListener('selectionchange', this._onSel);
      // Dentro de una lista: Tab sube un nivel (sublista) y Mayús+Tab lo baja.
      document.addEventListener('keydown', (e) => {
        if (e.key !== 'Tab') return;
        const sel = window.getSelection();
        const at = sel && sel.rangeCount ? sel.getRangeAt(0).startContainer : null;
        const el = at && (at.nodeType === 1 ? at : at.parentElement);
        if (!el || !el.closest('li') || !this.fieldOf(el)) return;
        e.preventDefault();
        document.execCommand(e.shiftKey ? 'outdent' : 'indent', false, null);
        this.changed();
      });
      window.addEventListener('scroll', () => this.show && this.place(), true);
      window.addEventListener('resize', () => this.show && this.place());
    },
    fieldOf(node) {
      const el = node && (node.nodeType === 1 ? node : node.parentElement);
      return el && el.closest('[data-rich][contenteditable="true"]');
    },
    update() {
      const sel = window.getSelection();
      if (!sel || sel.rangeCount === 0) { if (!this.pop) this.show = false; return; }
      const r = sel.getRangeAt(0);
      const f = this.fieldOf(r.commonAncestorContainer);
      // En el editor de la página del colegio (data-fmt-always) la barra aparece con solo poner el cursor en un texto.
      if (!f || (sel.isCollapsed && !f.closest('[data-fmt-always]'))) { if (!this.pop) this.show = false; return; }
      this.field = f;
      this.range = r.cloneRange();
      this.state = {
        bold: document.queryCommandState('bold'), italic: document.queryCommandState('italic'),
        underline: document.queryCommandState('underline'), strike: document.queryCommandState('strikeThrough'),
        sup: document.queryCommandState('superscript'), sub: document.queryCommandState('subscript'),
        ul: document.queryCommandState('insertUnorderedList'), ol: document.queryCommandState('insertOrderedList'),
      };
      this.place();
      this.show = true;
    },
    place() {
      if (!this.range) return;
      let rect = this.range.getBoundingClientRect();
      if (this.range.collapsed && this.field) rect = this.field.getBoundingClientRect(); // solo el cursor: sobre el campo
      const w = this.$el.offsetWidth || 300;
      this.x = Math.max(8, Math.min(window.innerWidth - w - 8, rect.left + rect.width / 2 - w / 2));
      this.y = rect.top > 56 ? rect.top - 48 : rect.bottom + 8; // arriba de la selección; si no cabe, debajo
    },
    restore() {
      if (!this.range) return null;
      const sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(this.range);
      return sel;
    },
    changed() {
      this.$dispatch('fmt-changed');
      this.$nextTick(() => this.update());
    },
    cmd(name) {
      if (!this.restore()) return;
      document.execCommand('styleWithCSS', false, false);
      document.execCommand(name, false, null);
      this.changed();
    },
    /** Envuelve la selección con <mark class="hl-…"> o <span class="tc-…">; sin clase, quita ese formato. */
    wrap(kind, color) {
      const sel = this.restore();
      if (!sel) return;
      const r = sel.getRangeAt(0);
      const frag = r.extractContents();
      const tag = kind === 'hl' ? 'mark' : 'span'; // hl: resaltado · tc: color · fs: tamaño
      // Quita dentro de la selección el mismo tipo de formato (para no anidar resaltados o colores).
      frag.querySelectorAll(kind === 'hl' ? 'mark' : 'span[class*="' + kind + '-"]').forEach((n) => n.replaceWith(...n.childNodes));
      let node = frag;
      if (color) {
        node = document.createElement(tag);
        node.className = kind + '-' + color;
        node.appendChild(frag);
      }
      const first = node.firstChild, last = node.lastChild;
      r.insertNode(node);
      // Vuelve a seleccionar lo formateado para poder seguir aplicando.
      const nr = document.createRange();
      if (color) nr.selectNodeContents(node); else if (first && last) { nr.setStartBefore(first); nr.setEndAfter(last); }
      sel.removeAllRanges();
      sel.addRange(nr);
      this.range = nr.cloneRange();
      this.field && this.field.normalize();
      this.pop = null;
      this.changed();
    },
    /** Quita todo el formato de la selección (queda el texto). */
    clear() {
      const sel = this.restore();
      if (!sel) return;
      const r = sel.getRangeAt(0);
      const txt = r.toString();
      r.deleteContents();
      const t = document.createTextNode(txt);
      r.insertNode(t);
      const nr = document.createRange();
      nr.selectNodeContents(t);
      sel.removeAllRanges();
      sel.addRange(nr);
      // Si la selección estaba dentro de un formato, sácala de él partiendo ese formato en dos (antes y después).
      let p = t.parentElement;
      while (p && p !== this.field && p.matches('strong,b,em,i,u,s,strike,del,sup,sub,mark,span')) {
        const left = document.createRange(); left.setStart(p, 0); left.setEndBefore(t);
        const right = document.createRange(); right.setStartAfter(t); right.setEnd(p, p.childNodes.length);
        const lw = p.cloneNode(false); lw.appendChild(left.extractContents());
        const rw = p.cloneNode(false); rw.appendChild(right.extractContents());
        p.parentNode.insertBefore(lw, p);
        p.parentNode.insertBefore(t, p);
        p.parentNode.insertBefore(rw, p);
        p.remove();
        if (!lw.textContent) lw.remove();
        if (!rw.textContent) rw.remove();
        p = t.parentElement;
      }
      nr.selectNodeContents(t);
      sel.removeAllRanges();
      sel.addRange(nr);
      this.range = nr.cloneRange();
      this.field && this.field.normalize();
      this.pop = null;
      this.changed();
    },
  }));
  /*
   * Bloque «Video» en el editor: vista previa del enlace pegado. Solo YouTube (sin cookies) y Vimeo (sin seguimiento).
   * Es la misma regla que App\Libraries\VideoEmbed, que es la que vale al guardar y al mostrar a los estudiantes.
   */
  Alpine.data('videoBlock', (initial) => ({
    url: initial || '',
    get src() {
      let raw = String(this.url || '').trim();
      if (!raw || raw.length > 500) return '';
      if (!/^https?:\/\//i.test(raw)) raw = 'https://' + raw;
      let u;
      try { u = new URL(raw); } catch (e) { return ''; }
      const host = u.hostname.toLowerCase().replace(/^(www\.|m\.)/, '');
      const t = u.searchParams.get('t') || u.searchParams.get('start') || '';
      let start = 0;
      if (/^\d+$/.test(t)) start = +t;
      else { const m = t.match(/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/); if (m && t) start = (+m[1] || 0) * 3600 + (+m[2] || 0) * 60 + (+m[3] || 0); }
      start = Math.min(start, 86400);
      let id = '';
      if (['youtube.com', 'youtube-nocookie.com', 'music.youtube.com'].includes(host)) {
        id = u.pathname === '/watch' ? (u.searchParams.get('v') || '') : ((u.pathname.match(/^\/(?:embed|shorts|live|v)\/([^/?]+)/) || [])[1] || '');
      } else if (host === 'youtu.be') {
        id = u.pathname.replace(/^\//, '');
      } else if (host === 'vimeo.com' || host === 'player.vimeo.com') {
        const m = u.pathname.match(/^\/(?:video\/)?(\d{6,12})(?:\/([0-9a-f]{6,20}))?\/?$/);
        return m ? 'https://player.vimeo.com/video/' + m[1] + '?dnt=1' + (m[2] ? '&h=' + m[2] : '') + (start ? '#t=' + start + 's' : '') : '';
      } else {
        return '';
      }
      return /^[A-Za-z0-9_-]{11}$/.test(id) ? 'https://www.youtube-nocookie.com/embed/' + id + '?rel=0' + (start ? '&start=' + start : '') : '';
    },
  }));
  /*
   * Filtros de «Mi agenda»: tipo (event, activity, note, personal), asignatura y ocultar lo hecho. Cada elemento lleva
   * { t: tipo, s: asignatura, d: hecho }; la lista del día, los próximos días y el calendario se filtran sin recargar.
   * La elección se recuerda por persona y tipo de agenda (localStorage; si no está disponible, solo dura la visita).
   */
  /* ---------- Bloques del cuaderno: Imagen / Archivo, Tabla y Pregunta ---------- */

  /** Imagen o archivo: sube al cuaderno (NotebookController::upload) y guarda el id en el campo oculto file_id. */
  Alpine.data('fileBlock', (opts) => ({
    file: opts.file || null,
    busy: false,
    error: '',
    async upload(e) {
      const f = e.target.files && e.target.files[0];
      e.target.value = '';
      if (!f || !opts.url) return;
      this.busy = true;
      this.error = '';
      const fd = new FormData();
      fd.append('file', f);
      fd.append('kind', opts.kind);
      try {
        const res = await fetch(opts.url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } });
        const out = await res.json().catch(() => ({ ok: false }));
        if (!res.ok || !out.ok) throw new Error(out.error || opts.failed);
        this.file = { id: out.id, name: out.name, sizeLabel: out.sizeLabel, url: out.url };
        this.$nextTick(() => this.edited());
      } catch (err) {
        this.error = err.message || opts.failed;
      }
      this.busy = false;
    },
  }));

  /** Tabla editable: celdas con contenteditable; agregar o quitar filas y columnas vuelve a pintar la tabla. */
  Alpine.data('tableBlock', (opts) => ({
    rows: (opts.rows || []).map((r) => r.slice()),
    header: !!opts.header,
    maxRows: opts.maxRows || 30,
    maxCols: opts.maxCols || 10,
    ver: 0,
    /** Antes de cambiar la estructura, toma el texto actual de las celdas (lo que se escribió sin volver a pintar). */
    sync() {
      const trs = [...this.$root.querySelectorAll('table.blk-table tr:not(.blk-table-foot)')];
      trs.forEach((tr, r) => [...tr.querySelectorAll('[data-cell]')].forEach((td, c) => { if (this.rows[r]) this.rows[r][c] = td.innerText.trim(); }));
    },
    change(fn) { this.sync(); fn(); this.ver++; this.$nextTick(() => this.edited()); },
    addRow() { if (this.rows.length < this.maxRows) this.change(() => this.rows.push(this.rows[0].map(() => ''))); },
    addCol() { if (this.rows[0].length < this.maxCols) this.change(() => this.rows.forEach((r) => r.push(''))); },
    delRow(i) { if (this.rows.length > 1) this.change(() => this.rows.splice(i, 1)); },
    delCol(j) { if (this.rows[0].length > 1) this.change(() => this.rows.forEach((r) => r.splice(j, 1))); },
    label(r, c) { return t('cell', 'Fila {r}, columna {c}', { r: r + 1, c: c + 1 }); },
    delRowLabel(r) { return t('delRow', 'Quitar la fila {n}', { n: r + 1 }); },
    delColLabel(c) { return t('delCol', 'Quitar la columna {n}', { n: c + 1 }); },
  }));

  /** Pregunta de comprobación (editor): enunciado, opciones, la correcta y la explicación. */
  Alpine.data('questionBlock', (opts) => ({
    question: opts.question || '',
    options: (opts.options && opts.options.length ? opts.options : ['', '']).slice(),
    correct: typeof opts.correct === 'number' ? opts.correct : null,
    explanation: opts.explanation || '',
    max: opts.max || 6,
    name: 'q' + Math.random().toString(36).slice(2),
    lbl(k, i) { return (opts.labels[k] || '').split('{n}').join(String(i + 1)); },
    addOpt() { if (this.options.length < this.max) { this.options.push(''); this.edited(); } },
    removeOpt(i) {
      if (this.options.length <= 2) return;
      this.options.splice(i, 1);
      if (this.correct === i) this.correct = null; else if (this.correct !== null && this.correct > i) this.correct--;
      this.edited();
    },
  }));

  /** Pregunta de comprobación (estudiante): elige, ve si acertó y la explicación; no se guarda ni se califica. */
  Alpine.data('questionView', (opts) => ({
    picked: null,
    solved: false,
    wrong: [],
    pick(i) {
      this.picked = i;
      if (i === opts.correct) this.solved = true; else if (!this.wrong.includes(i)) this.wrong.push(i);
    },
    cls(i) { return { 'is-right': this.solved && i === opts.correct, 'is-wrong': this.wrong.includes(i) && !(this.solved && i === opts.correct) }; },
    reset() { this.picked = null; this.solved = false; this.wrong = []; },
  }));

  /* Carrusel de banners de la portada pública: avanza solo cada 6 s (salvo con «reducir movimiento»), se detiene con el
     ratón o el foco encima y se puede deslizar con el dedo. */
  Alpine.data('lpSlider', (count) => ({
    i: 0, pause: false, x0: null,
    init() {
      if (count < 2 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
      setInterval(() => { if (!this.pause && !document.hidden) this.go(this.i + 1); }, 6000);
    },
    go(n) { this.i = (n + count) % count; },
    touch(e) { this.x0 = e.changedTouches[0].clientX; },
    swipe(e) {
      if (this.x0 === null) return;
      const dx = e.changedTouches[0].clientX - this.x0; this.x0 = null;
      if (Math.abs(dx) > 40) this.go(this.i + (dx < 0 ? 1 : -1));
    },
  }));

  /* Editor en vivo de la página pública del colegio (app/Views/site/page.php). Los textos son contenteditable de texto
     plano con data-path="sección.campo"; el orden y la visibilidad de las secciones se leen del DOM. Guarda solo. */
  Alpine.data('siteEditor', (opts) => ({
    images: opts.images, hidden: opts.hidden, maxValues: opts.maxValues, fotoBase: opts.fotoBase,
    values: (opts.values || []).map((t, i) => ({ id: i + 1, t })), nextId: 100,
    steps: (opts.steps || []).map((t, i) => ({ id: 200 + i, t })), admissionsForm: !!opts.admissionsForm,
    gallery: (opts.gallery || []).map((g, i) => ({ id: 300 + i, image: g.image, caption: g.caption || '' })),
    docs: (opts.docs || []).map((d, i) => ({ id: 400 + i, file: d.file, title: d.title, size: d.size })),
    pending: opts.pending, state: 'saved', publishing: false, uploading: null, timer: null, saving: null, again: false,
    csrf() { return document.querySelector('meta[name="csrf-token"]').content; },
    headers(json) { const h = { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': this.csrf() }; if (json) h['Content-Type'] = 'application/json'; return h; },
    onInput(e) { if (e.target.closest && e.target.closest('[data-path]')) this.queue(); },
    onPaste(e) { // pegar siempre como texto plano (por si el navegador no soporta plaintext-only)
      if (!e.target.closest || !e.target.closest('[contenteditable]')) return;
      e.preventDefault();
      document.execCommand('insertText', false, (e.clipboardData || window.clipboardData).getData('text/plain'));
    },
    queue() { this.pending = true; this.state = 'saving'; clearTimeout(this.timer); this.timer = setTimeout(() => this.save(), 900); },
    collect() {
      const d = { sections: [] };
      document.querySelectorAll('[data-path]').forEach((el) => {
        const [s, f] = el.dataset.path.split('.');
        // Con formato (barra flotante): el HTML (el servidor lo limpia con una lista blanca); sin formato: texto plano.
        (d[s] = d[s] || {})[f] = el.hasAttribute('data-rich') ? el.innerHTML : el.innerText.replace(/ /g, ' ');
      });
      document.querySelectorAll('[data-sec]').forEach((el) => d.sections.push({ key: el.dataset.sec, visible: !this.hidden[el.dataset.sec] }));
      d.hero = Object.assign(d.hero || {}, { image: this.images.hero });
      const has = (h) => (h || '').replace(/<[^>]*>/g, '').trim() !== '';
      d.about = Object.assign(d.about || {}, { image: this.images.about, values: this.values.map((v) => v.t.trim()).filter(has) });
      d.admissions = Object.assign(d.admissions || {}, { steps: this.steps.map((v) => v.t.trim()).filter(has), form: this.admissionsForm });
      d.gallery = Object.assign(d.gallery || {}, { items: this.gallery.map((g) => ({ image: g.image, caption: g.caption.trim() })) });
      d.documents = Object.assign(d.documents || {}, { items: this.docs.map((x) => ({ file: x.file, title: x.title.trim() })) });
      return d;
    },
    async save() {
      clearTimeout(this.timer);
      if (this.saving) { this.again = true; return this.saving; }
      this.state = 'saving';
      this.saving = (async () => {
        try {
          const res = await fetch(opts.saveUrl, { method: 'POST', headers: this.headers(true), body: JSON.stringify(this.collect()) });
          const out = await res.json().catch(() => ({}));
          if (!res.ok) throw new Error(out.error || '');
          this.pending = out.status ? out.status.pending : true;
          this.state = 'saved';
        } catch (e) { this.state = 'error'; }
      })();
      await this.saving;
      this.saving = null;
      if (this.again) { this.again = false; return this.save(); }
    },
    async publish() {
      this.publishing = true;
      await this.save();
      if (this.state === 'error') { this.publishing = false; window.toast(t('saveFailed', 'No se pudo guardar. Intenta de nuevo.'), 'error'); return; }
      try {
        const res = await fetch(opts.publishUrl, { method: 'POST', headers: this.headers(true), body: '{}' });
        if (!res.ok) throw new Error();
        this.pending = false;
        window.toast(t('sitePublished', 'Listo: la página ya está publicada.'), 'ok');
      } catch (e) { window.toast(t('offline', 'Sin conexión: intenta de nuevo.'), 'error'); }
      this.publishing = false;
    },
    move(key, dir) {
      const el = document.querySelector(`[data-sec="${key}"]`);
      const sib = dir < 0 ? el.previousElementSibling : el.nextElementSibling;
      if (!sib || !sib.dataset.sec) return;
      el.parentNode.insertBefore(el, dir < 0 ? sib : sib.nextElementSibling);
      el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      this.queue();
    },
    toggle(key) { this.hidden[key] = !this.hidden[key]; this.queue(); },
    addValue() {
      if (this.values.length >= this.maxValues) return;
      this.values.push({ id: this.nextId++, t: '' });
      this.$nextTick(() => { const all = document.querySelectorAll('[data-value]'); const last = all[all.length - 1]; if (last) last.focus(); });
    },
    addStep() {
      if (this.steps.length >= 6) return;
      this.steps.push({ id: this.nextId++, t: '' });
      this.$nextTick(() => { const all = document.querySelectorAll('[data-step]'); const last = all[all.length - 1]; if (last) last.focus(); });
    },
    /** Sube un PDF para «Documentos». */
    async uploadDoc(e) {
      const f = e.target.files[0];
      if (!f) return;
      if (f.size > 10 * 1024 * 1024) { window.toast(t('siteDocInvalid', 'El documento debe ser un PDF de hasta 10 MB.'), 'error'); return; }
      this.uploading = 'doc';
      const fd = new FormData(); fd.append('file', f);
      try {
        const res = await fetch(opts.docUrl, { method: 'POST', headers: this.headers(false), body: fd });
        const out = await res.json().catch(() => ({}));
        if (!res.ok || !out.name) throw new Error(out.error || t('siteDocInvalid', 'El documento debe ser un PDF de hasta 10 MB.'));
        this.docs.push({ id: this.nextId++, file: out.name, title: out.title, size: out.size });
        this.queue();
      } catch (err) { window.toast(err.message || t('offline', 'Sin conexión: intenta de nuevo.'), 'error'); }
      this.uploading = null;
    },
    pick(key) { this.target = key; this.$refs.file.value = ''; this.$refs.file.click(); },
    async upload(e) {
      const f = e.target.files[0]; const key = this.target;
      if (!f || !key) return;
      if (f.size > 4 * 1024 * 1024) { window.toast(t('siteImageInvalid', 'La foto debe ser JPG, PNG o WebP de hasta 4 MB.'), 'error'); return; }
      this.uploading = key;
      const fd = new FormData(); fd.append('image', f);
      try {
        const res = await fetch(opts.uploadUrl, { method: 'POST', headers: this.headers(false), body: fd });
        const out = await res.json().catch(() => ({}));
        if (!res.ok || !out.name) throw new Error(out.error || t('siteImageFailed', 'No se pudo procesar la foto. Prueba con otra.'));
        if (key === 'gallery') this.gallery.push({ id: this.nextId++, image: out.name, caption: '' }); else this.images[key] = out.name;
        this.queue();
      } catch (err) { window.toast(err.message || t('offline', 'Sin conexión: intenta de nuevo.'), 'error'); }
      this.uploading = null;
    },
  }));

  Alpine.data('agendaFilters', (opts) => ({
    f: null,
    ev: null,
    evf: opts.evf || null, // formulario de un evento de la institución (quien los gestiona)
    day: opts.day || [],
    next: opts.next || [],
    types: Object.fromEntries((opts.types || []).map((k) => [k, true])),
    subj: '',
    hideDone: false,
    init() {
      const s = safeStorage(() => JSON.parse(localStorage.getItem(opts.key) || '{}'), {});
      if (s.types) Object.keys(this.types).forEach((k) => { if (typeof s.types[k] === 'boolean') this.types[k] = s.types[k]; });
      if (typeof s.subj === 'string' && (opts.subjects || []).includes(s.subj)) this.subj = s.subj;
      this.hideDone = !!s.hideDone;
    },
    persist() {
      safeStorage(() => localStorage.setItem(opts.key, JSON.stringify({ types: this.types, subj: this.subj, hideDone: this.hideDone })));
    },
    toggle(k) { this.types[k] = !this.types[k]; this.persist(); },
    clear() { Object.keys(this.types).forEach((k) => { this.types[k] = true; }); this.subj = ''; this.hideDone = false; this.persist(); },
    get filtered() { return Object.values(this.types).some((v) => !v) || this.subj !== '' || this.hideDone; },
    show(x) {
      if (!x) return false;
      if (this.types[x.t] === false) return false;
      if (this.subj && x.s !== this.subj) return false;
      return !(this.hideDone && x.d);
    },
    vis(list) { return (list || []).filter((x) => this.show(x)); },
    showAt(list, i) { return this.show(list[i]); },
    /** Próximos días: solo los 8 primeros que coinciden con los filtros. */
    topNext(i) { return this.show(this.next[i]) && this.vis(this.next.slice(0, i)).length < 8; },
    dayCount(n) { return (n === 0 ? opts.l.zero : n === 1 ? opts.l.one : opts.l.many.split('{n}').join(String(n))); },
    moreLabel(n) { return opts.l.more.split('{n}').join(String(n)); },
  }));
  Alpine.data('quizTake', (opts) => ({
    cur: 0,
    total: opts.total,
    answers: opts.answers || {},
    flagged: [],
    left: Math.max(0, Math.floor((new Date(opts.deadline.replace(' ', 'T')) - new Date(opts.serverNow.replace(' ', 'T'))) / 1000)),
    hasLimit: opts.hasLimit,
    confirm: false,
    saving: false,
    timer: null,
    init() {
      if (this.hasLimit) {
        setInterval(() => {
          if (this.left > 0) this.left--;
          if (this.left === 0) this.finishNow();
        }, 1000);
      }
    },
    clock() { const h = Math.floor(this.left / 3600), m = Math.floor((this.left % 3600) / 60), s = this.left % 60; return (h ? h + ':' + String(m).padStart(2, '0') : m) + ':' + String(s).padStart(2, '0'); },
    answered(id) { const v = this.answers[id]; return Array.isArray(v) ? v.length > 0 : (v !== undefined && v !== null && String(v).trim() !== ''); },
    countAnswered() { return opts.ids.filter((id) => this.answered(id)).length; },
    set(id, v) { this.answers[id] = v; this.queueSave(); },
    toggle(id, v, on) { const a = new Set(this.answers[id] || []); on ? a.add(v) : a.delete(v); this.answers[id] = [...a]; this.queueSave(); },
    queueSave() { if (!opts.saveUrl) return; clearTimeout(this.timer); this.timer = setTimeout(() => this.save(), 700); },
    async save() {
      this.saving = true;
      try {
        const res = await fetch(opts.saveUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ answers: this.answers }) });
        const out = await res.json().catch(() => ({}));
        if (!res.ok || !out.ok) window.toast(out.error || t('answerNotSaved', 'No se pudo guardar la respuesta; se reintentará.'), 'error');
      } catch (e) { window.toast(t('answersOffline', 'Sin conexión: sus respuestas se enviarán al terminar.'), 'error'); }
      this.saving = false;
    },
    sent: false,
    finishNow() {
      if (this.sent) return; // el reloj puede pedirlo varias veces
      if (!opts.saveUrl) { this.sent = true; window.toast(t('previewNoSubmit', 'En la vista previa el intento no se envía.'), 'info'); return; }
      this.sent = true;
      this.$refs.answersJson.value = JSON.stringify(this.answers);
      this.$refs.finishForm.submit();
    },
  }));

  /*
   * Cuaderno (espacio de trabajo tipo OneNote).
   * Paneles plegables recordados por navegador; por debajo de 1024 px los paneles son cajones y por debajo de
   * 1280 px el panel de IA también (documento maestro 6.2 y 6.5). Al abrir la IA en pantallas de menos de
   * 1440 px se pliega el panel de asignaturas.
   * Guardado automático real: save() envía título, objetivo y bloques al endpoint JSON con el token CSRF (cabecera).
   */
  Alpine.data('notebook', (opts = {}) => ({
    /*
     * Elementos del cuaderno (documento, lista de bloques, formulario de publicar). No se usa this.$refs: cuando el guardado
     * lo dispara un componente anidado (bloque de video, barra de formato), $refs apunta a ese componente y no encuentra
     * nada («Cannot read properties of undefined (reading 'querySelector')»).
     */
    nbRef(name) { return document.querySelector('.nb [x-ref="' + name + '"]'); },
    paneA: true,
    paneB: true,
    ai: false,
    aiOverlay: false,
    narrow: false,
    sections: {},
    saving: 'saved',
    dirty: false,
    insertOpen: false,
    timer: null,
    init() {
      const saved = safeStorage(() => JSON.parse(localStorage.getItem('agenda-nb') || '{}'), {});
      this.narrow = window.matchMedia('(max-width: 1023.98px)').matches;
      this.paneA = this.narrow ? false : saved.paneA !== false;
      this.paneB = this.narrow ? false : saved.paneB !== false;
      this.ai = !this.narrow && window.innerWidth >= 1280 && saved.ai === true;
      this.aiOverlay = window.innerWidth < 1280;
      this.sections = saved.sections || {};
      if (opts.openAi) { // llegada desde «Crear actividad con IA»
        this.ai = true;
        if (this.narrow) { this.paneA = false; this.paneB = false; } else if (window.innerWidth < 1440) this.paneA = false;
      }
      window.matchMedia('(max-width: 1023.98px)').addEventListener('change', (e) => {
        this.narrow = e.matches;
        if (e.matches) { this.paneA = false; this.paneB = false; }
      });
      this.dirty = !!opts.dirty;
      // Si quedan cambios sin enviar al cerrar o cambiar de página, el navegador pide confirmación.
      window.addEventListener('beforeunload', (e) => { if (this.pending || this.saving === 'saving') { e.preventDefault(); e.returnValue = ''; } });
    },
    persist() {
      if (this.narrow) return;
      safeStorage(() => localStorage.setItem('agenda-nb', JSON.stringify({ paneA: this.paneA, paneB: this.paneB, ai: this.ai, sections: this.sections })));
    },
    togglePane(which) {
      this[which] = !this[which];
      if (this.narrow && this[which]) { if (which === 'paneA') this.paneB = false; else this.paneA = false; this.ai = false; }
      this.persist();
      this.$nextTick(() => window.lucide && lucide.createIcons());
    },
    toggleAi() {
      this.ai = !this.ai;
      this.aiOverlay = window.innerWidth < 1280;
      if (this.ai && window.innerWidth < 1440 && !this.narrow) this.paneA = false;
      if (this.ai && this.narrow) { this.paneA = false; this.paneB = false; }
      this.persist();
      this.$nextTick(() => {
        if (window.lucide) lucide.createIcons();
        const box = this.$root.querySelector('.ai-input textarea');
        if (this.ai && box) box.focus();
      });
    },
    closeOverlays() { if (this.narrow) { this.paneA = false; this.paneB = false; } if (window.innerWidth < 1280) this.ai = false; },
    sectionOpen(i) { return this.sections[i] !== false; },
    toggleSection(i) { this.sections[i] = !this.sectionOpen(i); this.persist(); },

    /* ---------- Editor: guardado automático real ---------- */
    newPage: false,
    newSection: false,
    pending: false, // hay cambios en pantalla aún no enviados
    /** Marca la página como modificada y programa el guardado (espera 900 ms sin teclear). */
    edited() {
      if (!opts.saveUrl) return;
      this.saving = 'saving';
      this.pending = true;
      clearTimeout(this.timer);
      this.timer = setTimeout(() => this.save(), 900);
    },
    /** Lee título, objetivo y bloques del DOM. */
    collect() {
      const doc = this.nbRef('doc');
      const text = (el) => (el ? el.innerText.replace(/ /g, ' ').replace(/\n+$/, '') : '');
      return {
        title: text(doc.querySelector('[data-title]')).trim(),
        objective: text(doc.querySelector('[data-objective]')).trim(),
        blocks: [...doc.querySelectorAll('.blocks [data-block]')].map((b) => {
          const data = {};
          b.querySelectorAll('[data-field]').forEach((f) => {
            data[f.dataset.field] = f.matches('input, textarea, select') ? f.value.trim() : text(f);
            // Con formato: también el HTML (el servidor lo limpia con lista blanca; sin formato se guarda solo el texto).
            if (f.hasAttribute('data-rich') && f.querySelector('strong,b,em,i,u,s,strike,del,sup,sub,mark,span,ul,ol')) data.html = f.innerHTML;
          });
          // Tabla: celdas por fila (texto plano) y si la primera fila es encabezado.
          if (b.dataset.type === 'table') {
            const tbl = b.querySelector('table.blk-table');
            data.rows = [...b.querySelectorAll('table.blk-table tr:not(.blk-table-foot)')].map((tr) => [...tr.querySelectorAll('[data-cell]')].map((td) => td.innerText.replace(/\n+$/, '').trim()));
            data.header = !!(tbl && tbl.dataset.header === '1');
          }
          // Pregunta: enunciado, opciones (en orden), la correcta y la explicación.
          if (b.dataset.type === 'question') {
            const v = (sel) => { const el = b.querySelector(sel); return el ? el.value.trim() : ''; };
            data.question = v('[data-q="question"]');
            data.options = [...b.querySelectorAll('[data-q-opt]')].map((i) => i.value.trim());
            data.correct = v('[data-q="correct"]') === '' ? null : Number(v('[data-q="correct"]'));
            data.explanation = v('[data-q="explanation"]');
          }
          return { type: b.dataset.type, data };
        }),
      };
    },
    async save() {
      clearTimeout(this.timer);
      if (!opts.saveUrl) return true;
      this.pending = false;
      try {
        const res = await fetch(opts.saveUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
          body: JSON.stringify(this.collect()),
        });
        const out = await res.json().catch(() => ({ ok: false, error: t('unexpected', 'Respuesta inesperada del servidor.') }));
        if (!res.ok || !out.ok) throw new Error(out.error || t('saveFailed', 'No se pudo guardar.'));
        if (!this.pending) this.saving = 'saved';
        this.dirty = true; // hay cambios sin publicar
        return true;
      } catch (e) {
        this.saving = 'error';
        this.pending = true;
        window.toast(e.message + t('keptOnScreen', ' Los cambios siguen en pantalla; se reintentará al seguir escribiendo.'), 'error');
        return false;
      }
    },
    saveLabel() { return { saving: t('saving', 'Guardando…'), saved: t('saved', 'Guardado'), error: t('notSaved', 'Sin guardar') }[this.saving]; },
    /** Guarda lo pendiente y envía el formulario de publicación. */
    async publishNow() {
      if (this.pending || this.saving === 'saving') { if (!(await this.save())) return; }
      this.nbRef('publishForm').submit();
    },
    /** Bloques que propone el asistente IA («Insertar en la página»): se agregan al final y se guardan. */
    insertBlocks(list) {
      if (!this.nbRef('blocks') || !list || !list.length) return;
      let first = null;
      list.forEach((b) => {
        const tpl = document.getElementById('tpl-' + b.type);
        if (!tpl) return;
        const node = tpl.content.firstElementChild.cloneNode(true);
        const f = node.querySelector('[data-field]');
        if (f) f.innerText = b.text || '';
        this.nbRef('blocks').appendChild(node);
        first = first || node;
      });
      this.$nextTick(() => {
        if (window.lucide) lucide.createIcons();
        if (first) first.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
      this.edited();
      window.toast(t('blocksAdded', 'Se agregaron {n} bloques al final de la página. Revísalos antes de publicar.', { n: list.length }), 'ok');
    },
    /** Pegar en un bloque: siempre como texto limpio (los bloques guardan texto; así no quedan ** ni # sueltos). */
    pastePlain(e) {
      const t = e.target.closest && e.target.closest('[contenteditable="true"]');
      if (!t) return;
      e.preventDefault();
      const raw = (e.clipboardData || window.clipboardData).getData('text/plain') || '';
      document.execCommand('insertText', false, window.mdLite.toPlain(raw));
    },
    /* Insertar entre bloques: el «+» de un bloque abre el menú flotante y el bloque nuevo va justo debajo. */
    insertAfter: null,
    floatOpen: false,
    floatX: 0,
    floatY: 0,
    openInsertAfter(block, btn) {
      this.insertOpen = false;
      this.insertAfter = block;
      const r = btn.getBoundingClientRect();
      const w = Math.min(512, window.innerWidth - 32), h = 300; // tamaño aproximado del menú (2 columnas)
      this.floatX = Math.max(8, Math.min(window.innerWidth - w - 8, r.right - w));
      this.floatY = r.bottom + h + 8 > window.innerHeight ? Math.max(8, r.top - h - 4) : r.bottom + 4;
      this.floatOpen = true;
      this.$nextTick(() => {
        if (window.lucide) lucide.createIcons();
        const menu = this.nbRef('floatMenu');
        const first = menu && menu.querySelector('.menu-item:not([disabled])');
        if (first) first.focus();
      });
    },
    closeFloat() {
      this.floatOpen = false;
      this.insertAfter = null;
    },
    insert(type) {
      this.insertOpen = false;
      const after = this.floatOpen && this.insertAfter && this.insertAfter.isConnected ? this.insertAfter : null;
      this.closeFloat();
      const tpl = document.getElementById('tpl-' + type);
      if (!tpl) return;
      const node = tpl.content.firstElementChild.cloneNode(true);
      if (after) after.after(node); else this.nbRef('blocks').appendChild(node);
      this.$nextTick(() => {
        if (window.lucide) lucide.createIcons();
        const f = node.querySelector('[data-field]');
        if (f) f.focus();
      });
      this.edited();
    },
    move(block, dir) {
      const sib = dir < 0 ? block.previousElementSibling : block.nextElementSibling;
      if (!sib) return;
      dir < 0 ? block.parentNode.insertBefore(block, sib) : block.parentNode.insertBefore(sib, block);
      this.edited();
    },
    removeBlock(block) {
      if (block.innerText.trim() !== '' && !confirm(t('deleteBlock', '¿Eliminar este bloque?'))) return;
      block.remove();
      this.edited();
    },
  }));
});

/*
 * mdLite: el Markdown sencillo que escribe la IA (títulos, listas, negrita, cursiva, código, citas, tablas, separadores)
 * convertido a HTML seguro, a texto limpio o a bloques del cuaderno. Sin librerías externas.
 * Seguridad: todo el texto se escapa ANTES de aplicar formato, y solo se generan etiquetas fijas; nunca se copian
 * atributos ni enlaces del texto, así una respuesta con <script> o javascript: se muestra como texto.
 */
window.mdLite = (() => {
  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const inlineHtml = (s) => esc(s)
    .replace(/`([^`]+)`/g, '<code>$1</code>')
    .replace(/\*\*([^*]+)\*\*|__([^_]+)__/g, (m, a, b) => '<strong>' + (a || b) + '</strong>')
    .replace(/(^|[^*\w])\*([^*\n]+)\*(?!\w)|(^|[^_\w])_([^_\n]+)_(?!\w)/g, (m, p1, a, p2, b) => (p1 ?? p2 ?? '') + '<em>' + (a || b) + '</em>');
  const inlinePlain = (s) => String(s)
    .replace(/`([^`]+)`/g, '$1').replace(/\*\*([^*]+)\*\*|__([^_]+)__/g, (m, a, b) => a || b)
    .replace(/(^|[^*\w])\*([^*\n]+)\*(?!\w)|(^|[^_\w])_([^_\n]+)_(?!\w)/g, (m, p1, a, p2, b) => (p1 ?? p2 ?? '') + (a || b))
    .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '$1 ($2)');
  const cells = (line) => line.trim().replace(/^\||\|$/g, '').split('|').map((c) => c.trim());

  /** Divide en piezas: heading, para, ul, ol, quote, table, hr. */
  function parse(md) {
    const lines = String(md || '').replace(/\r\n?/g, '\n').split('\n');
    const out = [];
    let i = 0;
    while (i < lines.length) {
      const l = lines[i];
      if (!l.trim()) { i++; continue; }
      let m;
      if ((m = l.match(/^\s{0,3}(#{1,6})\s+(.*?)\s*#*\s*$/))) { out.push({ k: 'heading', level: m[1].length, text: m[2] }); i++; continue; }
      if (/^\s{0,3}([-*_])(\s*\1){2,}\s*$/.test(l)) { out.push({ k: 'hr' }); i++; continue; }
      if (/^\s*\|.*\|\s*$/.test(l)) {
        const rows = [];
        while (i < lines.length && /^\s*\|.*\|\s*$/.test(lines[i])) {
          if (!/^\s*\|?[\s:|-]+\|?\s*$/.test(lines[i]) || !/-/.test(lines[i])) rows.push(cells(lines[i]));
          i++;
        }
        out.push({ k: 'table', rows });
        continue;
      }
      if (/^\s*>/.test(l)) {
        const q = [];
        while (i < lines.length && /^\s*>/.test(lines[i])) q.push(lines[i++].replace(/^\s*>\s?/, ''));
        out.push({ k: 'quote', text: q.join('\n') });
        continue;
      }
      const ul = /^\s*[-*+•]\s+(.*)$/, ol = /^\s*(\d+)[.)]\s+(.*)$/;
      if (ul.test(l) || ol.test(l)) {
        const kind = ol.test(l) ? 'ol' : 'ul';
        const items = [];
        while (i < lines.length && lines[i].trim() && (ul.test(lines[i]) || ol.test(lines[i]) || /^\s{2,}\S/.test(lines[i]))) {
          const s = lines[i];
          const it = s.match(ol) ? { text: s.match(ol)[2], sub: /^\s{2,}/.test(s) } : s.match(ul) ? { text: s.match(ul)[1], sub: /^\s{2,}/.test(s) } : null;
          if (it) items.push(it); else if (items.length) items[items.length - 1].text += ' ' + s.trim();
          i++;
        }
        out.push({ k: kind, items });
        continue;
      }
      const p = [lines[i++].trim()];
      while (i < lines.length && lines[i].trim() && !/^\s{0,3}#{1,6}\s|^\s*[-*+•]\s|^\s*\d+[.)]\s|^\s*>|^\s*\|.*\|\s*$/.test(lines[i])) p.push(lines[i++].trim());
      out.push({ k: 'para', text: p.join('\n') });
    }
    return out;
  }

  function toHtml(md) {
    return parse(md).map((b) => {
      switch (b.k) {
        case 'heading': { const h = Math.min(6, b.level + 2); return `<h${h}>${inlineHtml(b.text)}</h${h}>`; } // ## → h4: no compite con los títulos de la pantalla
        case 'hr': return '<hr>';
        case 'quote': return '<blockquote>' + inlineHtml(b.text).replace(/\n/g, '<br>') + '</blockquote>';
        case 'ul': case 'ol': return `<${b.k}>` + b.items.map((it) => `<li${it.sub ? ' class="sub"' : ''}>${inlineHtml(it.text)}</li>`).join('') + `</${b.k}>`;
        case 'table': {
          const [head, ...body] = b.rows;
          return '<table><thead><tr>' + (head || []).map((c) => `<th>${inlineHtml(c)}</th>`).join('') + '</tr></thead><tbody>'
            + body.map((r) => '<tr>' + r.map((c) => `<td>${inlineHtml(c)}</td>`).join('') + '</tr>').join('') + '</tbody></table>';
        }
        default: return '<p>' + inlineHtml(b.text).replace(/\n/g, '<br>') + '</p>';
      }
    }).join('');
  }

  const listText = (b) => { let n = 0; return b.items.map((it) => (it.sub ? '    ' : '') + (b.k === 'ol' && !it.sub ? (++n) + '. ' : '• ') + inlinePlain(it.text)).join('\n'); };
  const tableText = (b) => b.rows.map((r) => r.map(inlinePlain).join(' · ')).join('\n');

  function toPlain(md) {
    return parse(md).map((b) => {
      switch (b.k) {
        case 'heading': return inlinePlain(b.text);
        case 'hr': return '────────';
        case 'quote': return inlinePlain(b.text);
        case 'ul': case 'ol': return listText(b);
        case 'table': return tableText(b);
        default: return inlinePlain(b.text);
      }
    }).join('\n\n');
  }

  /** Bloques del cuaderno: título → heading; cita → nota destacada; lista y tabla → párrafo con viñetas o filas. */
  function toBlocks(md) {
    return parse(md).map((b) => {
      switch (b.k) {
        case 'heading': return { type: 'heading', text: inlinePlain(b.text) };
        case 'hr': return { type: 'divider', text: '' };
        case 'quote': return { type: 'callout', text: inlinePlain(b.text) };
        case 'ul': case 'ol': return { type: 'text', text: listText(b) };
        case 'table': return { type: 'text', text: tableText(b) };
        default: return { type: 'text', text: inlinePlain(b.text) };
      }
    });
  }

  return { toHtml, toPlain, toBlocks };
})();

/* Íconos lucide: primera pintura. */
document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) lucide.createIcons();
});

/* Ctrl+K / Cmd+K: enfoca la búsqueda global. */
document.addEventListener('keydown', (e) => {
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
    const box = document.getElementById('global-search');
    if (box) { e.preventDefault(); box.focus(); }
  }
});

/* Evita doble envío: deshabilita el botón y cambia el texto al gerundio indicado en data-loading. */
document.addEventListener('submit', (e) => {
  const form = e.target;
  if (form.dataset.submitting) { e.preventDefault(); return; }
  form.dataset.submitting = '1';
  // El botón pulsado (si hay varios, p. ej. «Guardar» y «Publicar»); su name/value se conserva en un campo oculto
  // porque un botón deshabilitado no se envía.
  const btn = (e.submitter && e.submitter.matches('[data-loading]')) ? e.submitter : form.querySelector('[type="submit"][data-loading]');
  if (btn) {
    if (btn.name) {
      const keep = document.createElement('input');
      keep.type = 'hidden'; keep.name = btn.name; keep.value = btn.value;
      form.appendChild(keep);
    }
    btn.disabled = true; btn.textContent = btn.dataset.loading;
  }
});

/* Tooltip de los gráficos: cualquier elemento con data-tip dentro de .col-chart (mouse y teclado). */
(function () {
  let tip = null;
  const show = (el, x, y) => {
    tip = tip || document.querySelector('.chart-tip');
    if (!tip) return;
    tip.textContent = el.dataset.tip;
    tip.hidden = false;
    const r = tip.getBoundingClientRect();
    tip.style.left = Math.min(window.innerWidth - r.width - 8, Math.max(8, x - r.width / 2)) + 'px';
    tip.style.top = Math.max(8, y - r.height - 12) + 'px';
  };
  const hide = () => { if (tip) tip.hidden = true; };
  document.addEventListener('mousemove', (e) => {
    const el = e.target.closest && e.target.closest('.col-chart [data-tip]');
    if (el) show(el, e.clientX, e.clientY); else hide();
  });
  document.addEventListener('focusin', (e) => {
    const el = e.target.closest && e.target.closest('.col-chart [data-tip]');
    if (el) { const r = el.getBoundingClientRect(); show(el, r.left + r.width / 2, r.top + r.height / 3); } else hide();
  });
  document.addEventListener('scroll', hide, true);
})();

