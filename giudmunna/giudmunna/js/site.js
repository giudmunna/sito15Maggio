/**
 * Script condiviso per Giudmunna: menu mobile, ricerca/filtri catalogo,
 * pulsante "torna su" e messaggi di validazione senza alert nativi.
 */
(function () {
  'use strict';

  /**
   * Mostra un messaggio temporaneo in cima alla pagina (preferibile all'alert).
   * @param {string} testo - Testo da mostrare
   * @param {boolean} [isError=false] - Se true usa stile errore
   */
  function mostraToast(testo, isError) {
    var esistente = document.getElementById('gm-toast');
    if (esistente) {
      esistente.remove();
    }
    var el = document.createElement('div');
    el.id = 'gm-toast';
    el.setAttribute('role', 'status');
    el.className = 'gm-toast' + (isError ? ' gm-toast--error' : '');
    el.textContent = testo;
    document.body.appendChild(el);
    requestAnimationFrame(function () {
      el.classList.add('gm-toast--visible');
    });
    window.setTimeout(function () {
      el.classList.remove('gm-toast--visible');
      window.setTimeout(function () {
        el.remove();
      }, 300);
    }, 4200);
  }

  /**
   * Menu hamburger: apre/chiude la navigazione su schermi piccoli.
   */
  function initNavMobile() {
    var btn = document.querySelector('.nav-toggle');
    var nav = document.querySelector('.nav-main');
    if (!btn || !nav) {
      return;
    }
    btn.addEventListener('click', function () {
      var aperto = nav.classList.toggle('nav-main--open');
      btn.setAttribute('aria-expanded', aperto ? 'true' : 'false');
    });
    nav.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () {
        if (window.matchMedia('(max-width: 768px)').matches) {
          nav.classList.remove('nav-main--open');
          btn.setAttribute('aria-expanded', 'false');
        }
      });
    });
  }

  /**
   * Menu account ospite (icona omino) con apertura/chiusura dropdown.
   */
  function initGuestMenu() {
    var menu = document.querySelector('.guest-menu');
    if (!menu) {
      return;
    }
    var btn = menu.querySelector('.guest-menu-toggle');
    if (!btn) {
      return;
    }

    function chiudiMenu() {
      menu.classList.remove('guest-menu--open');
      btn.setAttribute('aria-expanded', 'false');
    }

    btn.addEventListener('click', function () {
      var aperto = menu.classList.toggle('guest-menu--open');
      btn.setAttribute('aria-expanded', aperto ? 'true' : 'false');
    });

    document.addEventListener('click', function (e) {
      // Click fuori dal menu => chiudi dropdown.
      if (!menu.contains(e.target)) {
        chiudiMenu();
      }
    });

    document.addEventListener('keydown', function (e) {
      // Escape per migliorare usabilità/accessibilità tastiera.
      if (e.key === 'Escape') {
        chiudiMenu();
      }
    });
  }

  /**
   * Pulsante fisso in basso a destra per tornare in cima alla pagina.
   */
  function initScrollTop() {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'gm-scroll-top';
    btn.setAttribute('aria-label', 'Torna in cima alla pagina');
    btn.innerHTML = '&uarr;';
    document.body.appendChild(btn);

    function aggiornaVisibilita() {
      btn.classList.toggle('gm-scroll-top--visible', window.scrollY > 380);
    }
    window.addEventListener('scroll', aggiornaVisibilita, { passive: true });
    aggiornaVisibilita();

    btn.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  /**
   * Filtri client-side sul catalogo: ricerca testuale + pannello filtri laterale.
   */
  function initRicercaCatalogo() {
    var input = document.getElementById('catalog-search');
    var grid = document.querySelector('[data-catalog-grid]');
    if (!grid) {
      return;
    }
    var cards = grid.querySelectorAll('[data-search]');
    var empty = document.getElementById('catalog-empty-filter');
    var form = document.getElementById('catalog-filters-form');
    var model = document.getElementById('filter-model');
    var storage = document.getElementById('filter-storage');
    var battery = document.getElementById('filter-battery');
    var color = document.getElementById('filter-color');
    var condition = document.getElementById('filter-condition');
    var priceMin = document.getElementById('filter-price-min');
    var priceMax = document.getElementById('filter-price-max');
    var resetBtn = document.getElementById('catalog-filters-reset');

    function toStorageGb(text) {
      var m = String(text || '').match(/(\d+)/);
      return m ? parseInt(m[1], 10) : NaN;
    }

    function inRangeStorage(rangeValue, cardStorageText) {
      if (!rangeValue) {
        return true;
      }
      var storageGb = toStorageGb(cardStorageText);
      if (!storageGb && storageGb !== 0) {
        return false;
      }
      var parts = rangeValue.split('-');
      var min = parseInt(parts[0], 10);
      var max = parseInt(parts[1], 10);
      return storageGb >= min && storageGb <= max;
    }

    function filtra() {
      var q = input ? input.value.trim().toLowerCase() : '';
      var modelValue = model ? model.value.toLowerCase() : '';
      var storageValue = storage ? storage.value : '';
      var batteryValue = battery ? battery.value.toLowerCase() : '';
      var colorValue = color ? color.value.toLowerCase() : '';
      var conditionValue = condition ? condition.value.toLowerCase() : '';
      var minPrice = priceMin && priceMin.value !== '' ? parseFloat(priceMin.value) : null;
      var maxPrice = priceMax && priceMax.value !== '' ? parseFloat(priceMax.value) : null;
      var visibili = 0;

      cards.forEach(function (card) {
        var hay = (card.getAttribute('data-search') || '').toLowerCase();
        var cardModel = (card.getAttribute('data-modello') || '').toLowerCase();
        var cardStorage = card.getAttribute('data-capacita') || '';
        var cardBattery = (card.getAttribute('data-batteria') || '').toLowerCase();
        var cardColor = (card.getAttribute('data-colore') || '').toLowerCase();
        var cardCondition = (card.getAttribute('data-condizione') || '').toLowerCase();
        var cardPrice = parseFloat(card.getAttribute('data-prezzo') || '0');

        var ok = true;
        if (q && hay.indexOf(q) === -1) {
          ok = false;
        }
        if (ok && modelValue && cardModel !== modelValue) {
          ok = false;
        }
        if (ok && !inRangeStorage(storageValue, cardStorage)) {
          ok = false;
        }
        if (ok && batteryValue && cardBattery !== batteryValue) {
          ok = false;
        }
        if (ok && colorValue && cardColor !== colorValue) {
          ok = false;
        }
        if (ok && conditionValue && cardCondition !== conditionValue) {
          ok = false;
        }
        if (ok && minPrice !== null && cardPrice < minPrice) {
          ok = false;
        }
        if (ok && maxPrice !== null && cardPrice > maxPrice) {
          ok = false;
        }

        card.style.display = ok ? '' : 'none';
        if (ok) {
          visibili++;
        }
      });
      if (empty) {
        empty.hidden = visibili !== 0;
      }
    }

    if (input) {
      input.addEventListener('input', filtra);
      input.addEventListener('search', filtra);
    }

    [model, storage, battery, color, condition, priceMin, priceMax].forEach(function (el) {
      if (!el) {
        return;
      }
      el.addEventListener('change', filtra);
      if (el.tagName === 'INPUT') {
        el.addEventListener('input', filtra);
      }
    });

    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        if (form) {
          form.reset();
        }
        if (input) {
          input.value = '';
        }
        filtra();
      });
    }
  }

  /**
   * Validazione login lato client (il server resta l'unica autorità).
   */
  function initLoginForm() {
    var form = document.querySelector('form[data-gm-login]');
    if (!form) {
      return;
    }
    form.addEventListener('submit', function (e) {
      var u = (form.querySelector('[name="username"]') || {}).value;
      var p = (form.querySelector('[name="password"]') || {}).value;
      if (!String(u || '').trim() || !String(p || '').trim()) {
        e.preventDefault();
        mostraToast('Inserisci username e password.', true);
      }
    });
  }

  /**
   * Validazione registrazione: sostituisce gli alert con toast.
   */
  function initRegistrazioneForm() {
    var form = document.querySelector('form[data-gm-registrazione]');
    if (!form) {
      return;
    }
    form.addEventListener('submit', function (e) {
      var u = document.getElementById('username');
      var em = document.getElementById('email');
      var pw = document.getElementById('password');
      var ut = u ? u.value.trim() : '';
      var et = em ? em.value.trim() : '';
      var pt = pw ? pw.value.trim() : '';
      if (!ut || !et || !pt) {
        e.preventDefault();
        mostraToast('Tutti i campi sono obbligatori.', true);
        return;
      }
      if (pt.length < 6) {
        e.preventDefault();
        mostraToast('La password deve avere almeno 6 caratteri.', true);
      }
    });
  }

  /**
   * Conferma quantità nel carrello prima di inviare (evita submit accidentali a 0).
   */
  function initCarrelloQty() {
    document.querySelectorAll('form.cart-qty-form').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        var num = form.querySelector('input[name="quantita"]');
        var v = parseInt(num && num.value, 10);
        if (!v || v < 1) {
          e.preventDefault();
          mostraToast('La quantità deve essere almeno 1.', true);
        }
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }

  function initAll() {
    // Inizializzazione header/navigation prima delle altre utility globali.
    initNavMobile();
    initGuestMenu();
    initScrollTop();
    initRicercaCatalogo();
    initLoginForm();
    initRegistrazioneForm();
    initCarrelloQty();
  }
})();
