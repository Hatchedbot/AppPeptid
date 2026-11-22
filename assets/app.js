// Utilidades comunes del navegador. Cada módulo con mucho JS va en su propio archivo (ej. assets/lia.js).
(function () {
  'use strict';

  /** Token CSRF para fetch: cabecera X-CSRF. */
  window.csrfToken = function () {
    var m = document.querySelector('meta[name="csrf"]');
    return m ? m.content : '';
  };

  /** POST JSON con el token CSRF. Devuelve la respuesta ya convertida a objeto. */
  window.enviarJSON = function (url, datos) {
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF': window.csrfToken() },
      body: JSON.stringify(datos || {}),
      credentials: 'same-origin'
    }).then(function (r) { return r.json(); });
  };

  // ---- Liquid Glass: refracción, brillo que sigue al dedo y lente del menú ----
  var raiz = document.documentElement;
  var pideMenosMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var pideMenosTransparencia = window.matchMedia('(prefers-reduced-transparency: reduce)').matches;
  // Solo Chrome y Edge refractan el fondo con filtros SVG; los demás usan el vidrio sin refracción.
  var marcas = (navigator.userAgentData && navigator.userAgentData.brands) || [];
  var esChromium = marcas.some(function (m) { return /Chromium/.test(m.brand); });
  if (esChromium && !pideMenosTransparencia) { raiz.classList.add('lg-refracta'); }

  // El reflejo de luz sigue al puntero sobre cada pieza de vidrio.
  var vidrios = '.lg, .barra, .menu, .portada, .rejilla-cifras .tarjeta, .lia-flotante';
  var pendiente = null;
  var cuadro = 0;
  function moverBrillo(ev) {
    var el = ev.target.closest && ev.target.closest(vidrios);
    if (!el) { return; }
    pendiente = { el: el, x: ev.clientX, y: ev.clientY };
    if (cuadro) { return; }
    cuadro = window.requestAnimationFrame(function () {
      var p = pendiente; pendiente = null; cuadro = 0;
      if (!p) { return; }
      var r = p.el.getBoundingClientRect();
      p.el.style.setProperty('--lg-x', (p.x - r.left) + 'px');
      p.el.style.setProperty('--lg-y', (p.y - r.top) + 'px');
    });
  }
  if (!pideMenosMovimiento) { document.addEventListener('pointermove', moverBrillo, { passive: true }); }

  // Lente del menú: una gota de vidrio que se desliza a la pestaña que tocas.
  function prepararLente() {
    var menu = document.querySelector('.menu');
    if (!menu) { return; }
    var lente = document.createElement('span');
    lente.className = 'menu-lente';
    lente.setAttribute('aria-hidden', 'true');
    menu.insertBefore(lente, menu.firstChild);
    function ponerEn(enlace, animar) {
      if (!enlace) { lente.style.opacity = '0'; return; }
      lente.classList.toggle('sin-animar', !animar);
      lente.style.width = enlace.offsetWidth + 'px';
      lente.style.height = enlace.offsetHeight + 'px';
      lente.style.transform = 'translate(' + enlace.offsetLeft + 'px,' + enlace.offsetTop + 'px)';
      lente.style.opacity = '1';
      if (animar && !pideMenosMovimiento) {
        lente.classList.remove('estirando'); void lente.offsetWidth; lente.classList.add('estirando');
      }
    }
    ponerEn(menu.querySelector('a.activo'), false);
    menu.classList.add('con-lente');
    menu.addEventListener('pointerdown', function (ev) {
      var a = ev.target.closest('a');
      if (a) { ponerEn(a, true); }
    });
    window.addEventListener('resize', function () { ponerEn(menu.querySelector('a.activo'), false); });
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', prepararLente); } else { prepararLente(); }

  // Botones con data-confirmar="¿Seguro?" piden confirmación antes de enviar.
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-confirmar]');
    if (b && !window.confirm(b.getAttribute('data-confirmar'))) {
      ev.preventDefault();
    }
  });
})();
