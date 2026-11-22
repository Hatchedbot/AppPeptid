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

  // ---- Liquid Glass ----
  var menosMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)');
  var admiteRaton = window.matchMedia('(hover: hover) and (pointer: fine)');

  function prepararBrillo() {
    var vidrios = '.barra, .menu, .portada, .rejilla-cifras .tarjeta, .lia-flotante';
    document.querySelectorAll(vidrios).forEach(function (vidrio) {
      var cuadro = 0;
      function restablecer() {
        cancelAnimationFrame(cuadro);
        vidrio.style.removeProperty('--lg-x');
        vidrio.style.removeProperty('--lg-y');
      }
      vidrio.addEventListener('pointermove', function (ev) {
        if (ev.pointerType !== 'mouse' || !admiteRaton.matches || menosMovimiento.matches) { return; }
        cancelAnimationFrame(cuadro);
        cuadro = requestAnimationFrame(function () {
          var rect = vidrio.getBoundingClientRect();
          vidrio.style.setProperty('--lg-x', (ev.clientX - rect.left) + 'px');
          vidrio.style.setProperty('--lg-y', (ev.clientY - rect.top) + 'px');
        });
      }, { passive: true });
      vidrio.addEventListener('pointerleave', restablecer);
      admiteRaton.addEventListener('change', restablecer);
      menosMovimiento.addEventListener('change', restablecer);
    });
  }

  function prepararLente(menu, observarTamano) {
    var enlaces = Array.from(menu.querySelectorAll('a'));
    var activo = menu.querySelector('a.activo');
    var destino = activo;
    var lente = document.createElement('span');
    lente.className = 'menu-lente';
    lente.setAttribute('aria-hidden', 'true');
    lente.hidden = true;
    menu.prepend(lente);

    var posiciones = new Map();
    var animacion;
    var pulsacion;
    var duracion;
    var curva;

    function transformar(x, y, escala) {
      return 'translate3d(' + x + 'px, ' + y + 'px, 0) scaleX(' + escala + ')';
    }

    function ponerEn(enlace, animar) {
      var posicion = posiciones.get(enlace);
      if (!posicion) {
        if (animacion) { animacion.cancel(); animacion = null; }
        destino = enlace;
        lente.hidden = true;
        return;
      }
      var final = transformar(posicion.x, posicion.y, 1);
      var salirDesde = animar && destino !== enlace && !lente.hidden && !menosMovimiento.matches;
      var inicio = salirDesde ? getComputedStyle(lente).transform : null;
      if (animacion) { animacion.cancel(); animacion = null; }
      destino = enlace;
      lente.style.transform = final;
      lente.hidden = false;
      if (inicio && inicio !== 'none') {
        var origen = new DOMMatrixReadOnly(inicio);
        var centroX = origen.m41 + (posicion.x - origen.m41) / 2;
        var centroY = origen.m42 + (posicion.y - origen.m42) / 2;
        animacion = lente.animate([
          { transform: transformar(origen.m41, origen.m42, origen.m11) },
          { transform: transformar(centroX, centroY, 1.04) },
          { transform: final }
        ], { duration: duracion, easing: curva });
      }
    }

    function medir() {
      if (!enlaces.length) { return; }
      enlaces.forEach(function (enlace) {
        posiciones.set(enlace, { x: enlace.offsetLeft, y: enlace.offsetTop });
      });
      var ancho = enlaces[0].offsetWidth;
      var alto = enlaces[0].offsetHeight;
      var estilo = getComputedStyle(lente);
      duracion = parseFloat(estilo.getPropertyValue('--lg-viaje'));
      curva = estilo.getPropertyValue('--lg-curva').trim();
      lente.style.setProperty('--lente-ancho', ancho + 'px');
      lente.style.setProperty('--lente-alto', alto + 'px');
      ponerEn(destino, false);
      menu.classList.add('con-lente');
    }

    menu.addEventListener('pointerdown', function (ev) {
      var enlace = ev.target.closest('a');
      if (!enlace || !ev.isPrimary || ev.button !== 0) { return; }
      pulsacion = { id: ev.pointerId, enlace: enlace };
      ponerEn(enlace, true);
    });
    function terminarPulsacion(ev) {
      if (!pulsacion || ev.pointerId !== pulsacion.id) { return; }
      var debajo = ev.type === 'pointerup' && document.elementFromPoint(ev.clientX, ev.clientY);
      if (!debajo || !pulsacion.enlace.contains(debajo)) { ponerEn(activo, true); }
      pulsacion = null;
    }
    window.addEventListener('pointerup', terminarPulsacion);
    window.addEventListener('pointercancel', terminarPulsacion);
    menosMovimiento.addEventListener('change', function () { ponerEn(destino, false); });
    observarTamano(medir, menu);
    if (document.fonts) { document.fonts.ready.then(medir); }
  }

  function prepararVidrio() {
    prepararBrillo();
    var menu = document.querySelector('.menu');
    if (!menu) { return; }

    function observarTamano(actualizar, elemento) {
      actualizar();
      if (window.ResizeObserver) { new ResizeObserver(actualizar).observe(elemento); }
      else { window.addEventListener('resize', actualizar); }
    }
    prepararLente(menu, observarTamano);

    var barra = document.querySelector('.barra');
    function actualizarBordes() {
      var pagina = document.scrollingElement;
      barra.classList.toggle('con-contenido-debajo', pagina.scrollTop > 0);
      menu.classList.toggle('con-contenido-debajo', pagina.scrollTop + pagina.clientHeight < pagina.scrollHeight - 1);
    }
    window.addEventListener('scroll', actualizarBordes, { passive: true });
    window.addEventListener('resize', actualizarBordes);
    observarTamano(actualizarBordes, document.body);
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', prepararVidrio); }
  else { prepararVidrio(); }

  // Botones con data-confirmar="¿Seguro?" piden confirmación antes de enviar.
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-confirmar]');
    if (b && !window.confirm(b.getAttribute('data-confirmar'))) {
      ev.preventDefault();
    }
  });
})();
