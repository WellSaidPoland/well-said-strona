/* WELL SAID — well-said.pl — skrypty strony (bez zewnętrznych bibliotek) */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var mobile = window.matchMedia('(max-width: 860px)');

  /* ---------- Pojawianie się elementów przy przewijaniu ---------- */
  var revealEls = $$('[data-reveal]');
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); }
      });
    }, { threshold: 0.2 });
    revealEls.forEach(function (el) { io.observe(el); });
    // zabezpieczenie przy bardzo szybkim przewijaniu: odsłoń wszystko, co już minęło
    var tick = false;
    window.addEventListener('scroll', function () {
      if (tick) return; tick = true;
      requestAnimationFrame(function () {
        tick = false;
        revealEls.forEach(function (el) {
          if (!el.classList.contains('is-in') && el.getBoundingClientRect().top < window.innerHeight * 0.85) { el.classList.add('is-in'); io.unobserve(el); }
        });
      });
    }, { passive: true });
  } else {
    revealEls.forEach(function (el) { el.classList.add('is-in'); });
  }

  /* ---------- Menu: rozwijana „Oferta” (klik dla ekranów dotykowych) ---------- */
  $$('[data-dd]').forEach(function (dd) {
    var btn = $('button', dd);
    btn.addEventListener('click', function () {
      var open = !dd.classList.contains('is-open');
      dd.classList.toggle('is-open', open);
      btn.setAttribute('aria-expanded', open);
    });
    document.addEventListener('click', function (e) {
      if (!dd.contains(e.target)) { dd.classList.remove('is-open'); btn.setAttribute('aria-expanded', 'false'); }
    });
  });

  /* ---------- Menu mobilne ---------- */
  var nav = $('#nav'), burger = $('[data-burger]');
  function setMenu(open) {
    nav.classList.toggle('is-open', open);
    burger.setAttribute('aria-expanded', open);
    burger.setAttribute('aria-label', open ? 'Zamknij menu' : 'Otwórz menu');
    document.documentElement.style.overflow = open ? 'hidden' : '';
  }
  if (burger) {
    burger.addEventListener('click', function () { setMenu(!nav.classList.contains('is-open')); });
    $$('#mmenu a').forEach(function (a) { a.addEventListener('click', function () { setMenu(false); }); });
    var mof = $('[data-mof]');
    mof.addEventListener('click', function () {
      var open = mof.getAttribute('aria-expanded') !== 'true';
      mof.setAttribute('aria-expanded', open);
      $('#mmenu-oferta').classList.toggle('is-open', open);
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setMenu(false); });
  }

  /* ---------- Zdjęcie w sekcji O mnie: dotknięcie pokazuje cytat ---------- */
  $$('[data-portrait]').forEach(function (p) {
    function toggle() {
      var open = !p.classList.contains('is-open');
      p.classList.toggle('is-open', open);
      p.setAttribute('aria-expanded', open);
    }
    p.addEventListener('click', function () { if (mobile.matches || !window.matchMedia('(hover: hover)').matches) toggle(); });
    p.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); } });
  });

  /* ---------- Metoda: rozwijane filary ---------- */
  $$('[data-pillar]').forEach(function (pl) {
    var b = $('.pillar__head', pl);
    b.addEventListener('click', function () {
      var open = !pl.classList.contains('is-open');
      if (mobile.matches) $$('[data-pillar]').forEach(function (o) { o.classList.remove('is-open'); $('.pillar__head', o).setAttribute('aria-expanded', 'false'); });
      pl.classList.toggle('is-open', open);
      b.setAttribute('aria-expanded', open);
    });
  });

  /* ---------- Pas z notatkami: podwojenie do płynnej pętli ---------- */
  $$('[data-marquee]').forEach(function (t) {
    $$('img', t).forEach(function (img) {
      var c = img.cloneNode(true); c.alt = ''; c.setAttribute('aria-hidden', 'true'); t.appendChild(c);
    });
  });

  /* ---------- Business: kafelki umiejętności (dotknięcie) ---------- */
  $$('[data-sk]').forEach(function (t) {
    t.addEventListener('click', function () {
      if (window.matchMedia('(hover: hover)').matches && !mobile.matches) return;
      var open = !t.classList.contains('is-open');
      $$('[data-sk]').forEach(function (o) { o.classList.remove('is-open'); o.setAttribute('aria-expanded', 'false'); var sg = $('.sk__sign', o); if (sg) sg.textContent = '+'; });
      t.classList.toggle('is-open', open); t.setAttribute('aria-expanded', open);
      $('.sk__sign', t).textContent = open ? '−' : '+';
    });
  });

  /* ---------- Opinie: koperta (dotknięcie wysuwa kartkę) ---------- */
  $$('[data-env]').forEach(function (e) {
    e.addEventListener('click', function (ev) { if (ev.target.closest('a')) return; e.classList.toggle('is-open'); });
  });

  /* ---------- FAQ: zakładki, pytania, płatność krok po kroku ---------- */
  var faq = $('[data-faq]');
  if (faq) {
    var tabs = $$('[data-tab]', faq), panels = $$('.faq__panel', faq);
    var openQ = function (q, scroll) {
      var panel = q.closest('.faq__panel');
      $$('.fq', panel).forEach(function (o) { if (o !== q) { o.classList.remove('is-open'); $('.fq__q', o).setAttribute('aria-expanded', 'false'); } });
      q.classList.add('is-open'); $('.fq__q', q).setAttribute('aria-expanded', 'true');
      if (scroll) setTimeout(function () { window.scrollTo({ top: q.getBoundingClientRect().top + window.scrollY - 90, behavior: 'smooth' }); }, 80);
    };
    var showTab = function (i, focusFirst) {
      tabs.forEach(function (t, j) { t.setAttribute('aria-selected', String(i === j)); t.tabIndex = i === j ? 0 : -1; });
      panels.forEach(function (p, j) { p.hidden = i !== j; });
      if (focusFirst !== false) openQ($('.fq', panels[i]));
    };
    tabs.forEach(function (t, i) {
      t.addEventListener('click', function () { showTab(i); });
      t.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') { var n = (i + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length; showTab(n); tabs[n].focus(); }
      });
    });
    $$('.fq', faq).forEach(function (q) {
      $('.fq__q', q).addEventListener('click', function () {
        if (q.classList.contains('is-open')) { q.classList.remove('is-open'); this.setAttribute('aria-expanded', 'false'); }
        else openQ(q);
      });
    });
    var goTo = function (n) {
      var q = document.getElementById('faq-' + n); if (!q) return;
      showTab(panels.indexOf(q.closest('.faq__panel')), false); openQ(q, true);
    };
    $$('[data-faq-go]', faq).forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); goTo(a.dataset.faqGo); }); });
    var fromHash = function () { if (/^#faq-\d+$/.test(location.hash)) goTo(location.hash.slice(5)); };
    fromHash(); window.addEventListener('hashchange', fromHash);
    var pw = $('[data-paywidget]');
    if (pw) {
      var data = JSON.parse($('[data-pay-data]').textContent);
      $$('[data-pay]', pw).forEach(function (b) {
        var pick = function () {
          var i = +b.dataset.pay;
          $$('[data-pay]', pw).forEach(function (o) { o.setAttribute('aria-pressed', String(o === b)); });
          $('[data-pay-n]', pw).textContent = '0' + (i + 1);
          $('[data-pay-k]', pw).textContent = data[i][0];
          $('[data-pay-t]', pw).innerHTML = data[i][1];
        };
        b.addEventListener('click', pick);
        if (window.matchMedia('(hover: hover)').matches) b.addEventListener('mouseenter', pick);
      });
    }
  }

  /* ---------- Akordeony (jedna pozycja otwarta naraz) ---------- */
  $$('[data-acc]').forEach(function (acc) {
    $$('.acc__item', acc).forEach(function (it) {
      var b = $('.acc__btn', it);
      b.addEventListener('click', function () {
        var open = !it.classList.contains('is-open');
        $$('.acc__item', acc).forEach(function (o) { o.classList.remove('is-open'); $('.acc__btn', o).setAttribute('aria-expanded', 'false'); });
        it.classList.toggle('is-open', open); b.setAttribute('aria-expanded', open);
      });
    });
  });

  /* =========================================================
     FORMULARZ KONTAKTOWY
     ========================================================= */
  var form = $('#contact-form');
  if (!form) return;

  var started = Date.now();
  form.elements.t.value = String(started);

  /* licznik znaków */
  var ta = form.elements.message, count = $('[data-count]');
  ta.addEventListener('input', function () {
    count.textContent = ta.value.length + ' / 1000';
    count.classList.toggle('is-near', ta.value.length >= 950);
  });

  /* --- dostępność terminów --- */
  var av = { mode: null, off: 0, sel: null, slots: [], days: [], hours: [] };
  var HOURS = []; for (var h = 9; h <= 20; h++) HOURS.push(h);
  var WD = ['PN', 'WT', 'ŚR', 'CZW', 'PT', 'SOB', 'ND'];
  var WD_LONG = ['poniedziałki', 'wtorki', 'środy', 'czwartki', 'piątki', 'soboty', 'niedziele'];
  var MONTHS = ['STYCZEŃ', 'LUTY', 'MARZEC', 'KWIECIEŃ', 'MAJ', 'CZERWIEC', 'LIPIEC', 'SIERPIEŃ', 'WRZESIEŃ', 'PAŹDZIERNIK', 'LISTOPAD', 'GRUDZIEŃ'];
  var today = new Date(); today.setHours(0, 0, 0, 0);
  var key = function (d) { return d.getFullYear() + '-' + (d.getMonth() + 1) + '-' + d.getDate(); };
  var fmt = function (d) { return ['nd', 'pn', 'wt', 'śr', 'czw', 'pt', 'sob'][d.getDay()] + ' ' + String(d.getDate()).padStart(2, '0') + '.' + String(d.getMonth() + 1).padStart(2, '0'); };
  var avBox = $('[data-av]');

  function chip(label, on, onClick, disabled) {
    var b = document.createElement('button');
    b.type = 'button'; b.className = 'chip'; b.textContent = label;
    b.setAttribute('aria-pressed', on ? 'true' : 'false');
    if (disabled) b.disabled = true; else b.addEventListener('click', onClick);
    return b;
  }

  function render() {
    // tryby
    $$('[data-mode]', avBox).forEach(function (b) { b.setAttribute('aria-pressed', String(av.mode === +b.dataset.mode)); });
    $('[data-weekly]').hidden = av.mode !== 0;
    $('[data-single]').hidden = av.mode === 0;

    // stałe zajęcia: dni + godziny
    var days = $('[data-days]'); days.innerHTML = '';
    WD.forEach(function (t, i) {
      var on = av.days.indexOf(i) > -1;
      days.appendChild(chip(t, on, function () { av.days = on ? av.days.filter(function (x) { return x !== i; }) : av.days.concat(i).sort(); render(); }));
    });
    var wh = $('[data-whours]'); wh.innerHTML = '';
    HOURS.forEach(function (hh) {
      var on = av.hours.indexOf(hh) > -1;
      wh.appendChild(chip(hh + ':00', on, function () { av.hours = on ? av.hours.filter(function (x) { return x !== hh; }) : av.hours.concat(hh).sort(function (a, b) { return a - b; }); render(); }));
    });
    var ws = $('[data-wsum]');
    ws.hidden = !(av.days.length || av.hours.length);
    ws.textContent = (av.days.length ? av.days.map(function (i) { return WD_LONG[i]; }).join(', ') : 'dowolny dzień') + (av.hours.length ? ', ' + av.hours.map(function (x) { return x + ':00'; }).join(', ') : '');

    // kalendarz
    var first = new Date(today.getFullYear(), today.getMonth() + av.off, 1);
    $('[data-cal-title]').textContent = MONTHS[first.getMonth()] + ' ' + first.getFullYear();
    $('[data-cal-prev]').disabled = av.off === 0;
    var grid = $('[data-cal-grid]'); grid.innerHTML = '';
    ['PN', 'WT', 'ŚR', 'CZ', 'PT', 'SO', 'ND'].forEach(function (t, i) {
      var d = document.createElement('div'); d.className = 'cal__wd' + (i >= 5 ? ' we' : ''); d.textContent = t; grid.appendChild(d);
    });
    var lead = (first.getDay() + 6) % 7, dim = new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate();
    var marked = {}; av.slots.forEach(function (s) { marked[key(s.d)] = 1; });
    var n = 0;
    function empty() { var e = document.createElement('div'); e.className = 'cal__cell' + (n % 7 >= 5 ? ' we' : ''); grid.appendChild(e); n++; }
    for (var i = 0; i < lead; i++) empty();
    for (var dnum = 1; dnum <= dim; dnum++) {
      (function (dd) {
        var d = new Date(first.getFullYear(), first.getMonth(), dd);
        var b = document.createElement('button'); b.type = 'button';
        var cls = 'cal__cell' + (n % 7 >= 5 ? ' we' : '');
        if (av.sel && key(av.sel) === key(d)) cls += ' is-sel'; else if (marked[key(d)]) cls += ' is-marked';
        b.className = cls; b.textContent = dd;
        b.setAttribute('aria-label', fmt(d));
        if (d < today) b.disabled = true; else b.addEventListener('click', function () { av.sel = d; render(); });
        grid.appendChild(b); n++;
      })(dnum);
    }
    while (n % 7) empty();

    // godziny dla wybranego dnia
    $('[data-hour-hint]').textContent = av.sel ? 'Godziny dla: ' + fmt(av.sel) + ' (możesz zaznaczyć kilka)' : 'Najpierw wybierz dzień w kalendarzu.';
    var sh = $('[data-shours]'); sh.innerHTML = '';
    HOURS.forEach(function (hh) {
      var on = av.sel && av.slots.some(function (s) { return key(s.d) === key(av.sel) && s.h === hh; });
      sh.appendChild(chip(hh + ':00', on, function () {
        if (on) av.slots = av.slots.filter(function (s) { return !(key(s.d) === key(av.sel) && s.h === hh); });
        else av.slots = av.slots.concat({ d: av.sel, h: hh }).sort(function (a, b) { return a.d - b.d || a.h - b.h; });
        render();
      }, !av.sel));
    });
    var sl = $('[data-slots]'); sl.innerHTML = '';
    av.slots.forEach(function (s, idx) {
      var el = document.createElement('div'); el.className = 'slot'; el.textContent = fmt(s.d) + ' · ' + s.h + ':00';
      var x = document.createElement('button'); x.type = 'button'; x.textContent = '×'; x.setAttribute('aria-label', 'Usuń termin');
      x.addEventListener('click', function () { av.slots.splice(idx, 1); render(); });
      el.appendChild(x); sl.appendChild(el);
    });
  }
  $$('[data-mode]', avBox).forEach(function (b) {
    b.addEventListener('click', function () { var m = +b.dataset.mode; av.mode = av.mode === m ? null : m; render(); });
  });
  $('[data-cal-prev]').addEventListener('click', function () { if (av.off > 0) { av.off--; render(); } });
  $('[data-cal-next]').addEventListener('click', function () { if (av.off < 11) { av.off++; render(); } });
  render();

  /* dymek „?” */
  var helpBtn = $('[data-av-help]');
  function setHelp(open) { avBox.classList.toggle('help-open', open); helpBtn.setAttribute('aria-expanded', open); }
  helpBtn.addEventListener('click', function (e) { e.stopPropagation(); setHelp(!avBox.classList.contains('help-open')); });
  if (window.matchMedia('(hover: hover)').matches) {
    helpBtn.addEventListener('mouseenter', function () { setHelp(true); });
    $('.av__head', avBox).addEventListener('mouseleave', function () { setHelp(false); });
  }
  document.addEventListener('click', function (e) { if (!e.target.closest('#av-help')) setHelp(false); });

  /* --- walidacja --- */
  function checks() {
    return {
      name: form.elements.name.value.trim().split(/\s+/).filter(Boolean).length >= 2,
      email: /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(form.elements.email.value.trim()),
      course: !!form.querySelector('input[name="course"]:checked'),
      msg: form.elements.message.value.trim().length > 0
    };
  }
  var tried = false;
  function showErrors() {
    var c = checks();
    Object.keys(c).forEach(function (k) { $('[data-f="' + k + '"]', form).classList.toggle('has-err', tried && !c[k]); });
    return c.name && c.email && c.course && c.msg;
  }
  form.addEventListener('input', function () { if (tried) showErrors(); });
  form.addEventListener('change', function () { if (tried) showErrors(); });

  /* --- podsumowanie terminów do maila --- */
  function availabilityText() {
    if (av.mode === 0) {
      if (!av.days.length && !av.hours.length) return 'Stałe zajęcia (bez wskazanych dni i godzin)';
      return 'Stałe zajęcia: ' + (av.days.length ? av.days.map(function (i) { return WD_LONG[i]; }).join(', ') : 'dowolny dzień') + (av.hours.length ? '; godziny: ' + av.hours.map(function (x) { return x + ':00'; }).join(', ') : '');
    }
    var prefix = av.mode === 1 ? 'Z lekcji na lekcję' : 'Preferowany termin';
    if (!av.slots.length) return av.mode === 1 ? prefix + ' (bez wskazanych terminów)' : '';
    return prefix + ': ' + av.slots.map(function (s) {
      return fmt(s.d) + '.' + s.d.getFullYear() + ' godz. ' + s.h + ':00';
    }).join('; ');
  }

  /* --- wysyłka --- */
  var status = $('[data-status]'), btn = $('button[type="submit"]', form);
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    tried = true;
    if (!showErrors()) {
      var firstErr = $('.has-err', form); if (firstErr) firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }
    status.textContent = '';
    btn.disabled = true; btn.textContent = 'WYSYŁANIE…';
    var fd = new FormData(form);
    fd.append('availability', availabilityText());
    fetch(form.action, { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
      .then(function (res) {
        if (res && res.ok) {
          btn.textContent = 'Dziękuję za wysłanie wiadomości!';
          btn.classList.add('is-sent');
          status.textContent = '';
        } else {
          var er = new Error('send'); er.srv = res && res.error; throw er;
        }
      })
      .catch(function (err) {
        status.textContent = (err && err.srv) ? err.srv : 'Nie udało się wysłać wiadomości. Spróbuj ponownie za chwilę albo napisz bezpośrednio na kontakt@well-said.pl.';
        btn.disabled = false; btn.textContent = 'WYŚLIJ WIADOMOŚĆ';
      });
  });
})();

/* Opinie: powiększanie karteczek */
(function () {
  var m = document.querySelector('.rv-modal'); if (!m) return;
  var inn = m.querySelector('.rv-modal__in'), last = null;
  function close() { m.classList.remove('is-open'); document.body.classList.remove('rv-lock'); setTimeout(function () { if (!m.classList.contains('is-open')) { m.hidden = true; inn.innerHTML = ''; } }, 300); if (last) last.focus(); }
  document.querySelectorAll('.revs__list .rev').forEach(function (r) {
    r.tabIndex = 0; r.setAttribute('role', 'button'); r.setAttribute('aria-label', 'Przeczytaj całą opinię: ' + (r.querySelector('.rev__name') || {}).textContent);
    function open() { last = r; var c = r.cloneNode(true); ['role', 'tabindex', 'aria-label'].forEach(function (a) { c.removeAttribute(a); }); inn.innerHTML = ''; inn.appendChild(c); m.hidden = false; m.scrollTop = 0; requestAnimationFrame(function () { m.classList.add('is-open'); }); document.body.classList.add('rv-lock'); m.querySelector('.rv-modal__x').focus(); }
    r.addEventListener('click', open);
    r.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); } });
  });
  m.addEventListener('click', function (e) { if (e.target === m || e.target.closest('.rv-modal__x')) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && m.classList.contains('is-open')) close(); });
})();
