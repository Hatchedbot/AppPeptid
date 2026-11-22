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
  var vidrioOpaco = window.matchMedia('(prefers-reduced-transparency: reduce), (prefers-contrast: more), (forced-colors: active)');

  function prepararBisel(pieza, nombre, observarTamano) {
    var plantilla = document.getElementById('bisel-plantilla');
    var recursos = document.querySelector('.bisel-recursos defs');
    if (!plantilla || !recursos) { return; }
    var filtro = plantilla.content.querySelector('filter').cloneNode(true);
    var imagen = filtro.querySelector('feImage');
    var canvas = document.createElement('canvas');
    var contexto = canvas.getContext('2d');
    if (!contexto) { return; }
    filtro.id = 'bisel-' + nombre;
    if (nombre === 'lente') {
      // Los textos están dentro del bisel. Una escala menor evita repetir trazos en el canto.
      filtro.querySelectorAll('feDisplacementMap').forEach(function (canal) {
        canal.setAttribute('scale', Number(canal.getAttribute('scale')) * .25);
      });
    }
    var medidas;

    function actualizar() {
      if (vidrioOpaco.matches) {
        pieza.style.removeProperty('--bisel');
        pieza.classList.remove('con-bisel');
        filtro.remove();
        return;
      }
      var estilo = getComputedStyle(pieza);
      var ancho = parseFloat(estilo.width);
      var alto = parseFloat(estilo.height);
      if (!ancho || !alto) { return; }
      var mitadX = ancho / 2;
      var mitadY = alto / 2;
      var radio = Math.min(parseFloat(estilo.borderTopLeftRadius) * (estilo.borderTopLeftRadius.endsWith('%') ? alto / 100 : 1), mitadX, mitadY);
      var clave = [ancho, alto, radio].join('/');
      if (clave !== medidas) {
        canvas.width = Math.ceil(ancho);
        canvas.height = Math.ceil(alto);
        var mapa = contexto.createImageData(canvas.width, canvas.height);
        var franja = Math.min(nombre === 'lente' ? 10 : 14, mitadY);
        for (var y = 0; y < canvas.height; y++) {
          var dy = (y + .5) * alto / canvas.height - mitadY;
          for (var x = 0; x < canvas.width; x++) {
            var dx = (x + .5) * ancho / canvas.width - mitadX;
            // Distancia firmada y normal del rectángulo redondeado.
            var qx = Math.abs(dx) - mitadX + radio;
            var qy = Math.abs(dy) - mitadY + radio;
            var esquinaX = Math.max(qx, 0);
            var esquinaY = Math.max(qy, 0);
            var diagonal = Math.hypot(esquinaX, esquinaY);
            var distancia = diagonal + Math.min(Math.max(qx, qy), 0) - radio;
            var canto = Math.max(0, Math.min(1, 1 + distancia / franja));
            var fuerza = 104 * canto * canto;
            var normalX = diagonal ? esquinaX / diagonal : Number(qx > qy);
            var normalY = diagonal ? esquinaY / diagonal : Number(qy >= qx);
            var i = 4 * (y * canvas.width + x);
            mapa.data[i] = 128 - Math.sign(dx) * normalX * fuerza;
            mapa.data[i + 1] = 128 - Math.sign(dy) * normalY * fuerza;
            // El canal B mezcla el resultado solo en el bisel y deja intacto el centro.
            mapa.data[i + 2] = 255 * Math.min(1, canto * 4);
            mapa.data[i + 3] = 255;
          }
        }
        contexto.putImageData(mapa, 0, 0);
        var url = canvas.toDataURL();
        imagen.setAttribute('width', ancho);
        imagen.setAttribute('height', alto);
        imagen.setAttribute('href', url);
        imagen.setAttributeNS('http://www.w3.org/1999/xlink', 'xlink:href', url);
        medidas = clave;
      }
      if (!filtro.isConnected) { recursos.append(filtro); }
      pieza.style.setProperty('--bisel', 'url(#' + filtro.id + ')');
      pieza.classList.add('con-bisel');
    }
    observarTamano(actualizar, pieza);
    vidrioOpaco.addEventListener('change', actualizar);
  }

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
    var margenLente = 3;
    var activo = menu.querySelector('a.activo');
    var destino = activo;
    var lente = document.createElement('span');
    var cristal = document.createElement('span');
    var copia = document.createElement('span');
    lente.className = 'menu-lente';
    cristal.className = 'menu-lente-cristal';
    copia.className = 'menu-lente-copia';
    var pestanas = enlaces.map(function (enlace) {
      var pestana = document.createElement('span');
      pestana.className = 'menu-lente-pestana';
      enlace.childNodes.forEach(function (nodo) { pestana.append(nodo.cloneNode(true)); });
      copia.append(pestana);
      return pestana;
    });
    cristal.append(copia);
    lente.append(cristal);
    lente.setAttribute('aria-hidden', 'true');
    lente.hidden = true;
    menu.prepend(lente);

    var posiciones = new Map();
    var animaciones = [];
    var pulsacion;
    var duracion;
    var curva;

    function transformar(x, y) {
      return 'translate(' + x + 'px, ' + y + 'px)';
    }

    function cancelarViaje() {
      animaciones.forEach(function (animacion) { animacion.cancel(); });
      animaciones = [];
    }

    function ponerEn(enlace, animar) {
      var posicion = posiciones.get(enlace);
      if (!posicion) {
        cancelarViaje();
        destino = enlace;
        lente.hidden = true;
        return;
      }
      var final = transformar(posicion.x, posicion.y);
      var inverso = transformar(-posicion.x, -posicion.y);
      var salirDesde = animar && destino !== enlace && !lente.hidden && !menosMovimiento.matches;
      var inicio = salirDesde ? getComputedStyle(lente).transform : null;
      cancelarViaje();
      destino = enlace;
      lente.style.transform = final;
      copia.style.transform = inverso;
      lente.hidden = false;
      if (inicio && inicio !== 'none') {
        var origen = new DOMMatrixReadOnly(inicio);
        var opciones = { duration: duracion, easing: curva };
        animaciones = [
          lente.animate([{ transform: transformar(origen.m41, origen.m42) }, { transform: final }], opciones),
          copia.animate([{ transform: transformar(-origen.m41, -origen.m42) }, { transform: inverso }], opciones)
        ];
      }
    }

    function medir() {
      if (!enlaces.length) { return; }
      var rectMenu = menu.getBoundingClientRect();
      var estiloMenu = getComputedStyle(menu);
      enlaces.forEach(function (enlace, i) {
        var rect = enlace.getBoundingClientRect();
        var estiloEnlace = getComputedStyle(enlace);
        var x = rect.left - rectMenu.left - parseFloat(estiloMenu.borderLeftWidth);
        var y = rect.top - rectMenu.top - parseFloat(estiloMenu.borderTopWidth);
        pestanas[i].style.left = x + 'px';
        pestanas[i].style.top = y + 'px';
        pestanas[i].style.width = estiloEnlace.width;
        pestanas[i].style.height = estiloEnlace.height;
        posiciones.set(enlace, { x: x - margenLente, y: y });
      });
      var estilo = getComputedStyle(lente);
      duracion = parseFloat(estilo.getPropertyValue('--lg-viaje'));
      curva = estilo.getPropertyValue('--lg-curva').trim();
      lente.style.setProperty('--lente-ancho', (parseFloat(pestanas[0].style.width) + margenLente * 2) + 'px');
      lente.style.setProperty('--lente-alto', pestanas[0].style.height);
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
    prepararBisel(lente, 'lente', observarTamano);
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
    // Safari acepta la sintaxis url() en algunas versiones sin aplicar la refracción al fondo.
    var chromium = navigator.userAgentData
      ? navigator.userAgentData.brands.some(function (marca) { return marca.brand === 'Chromium'; })
      : /\bChrome\//.test(navigator.userAgent) && !/\b(?:iPhone|iPad|iPod)\b/.test(navigator.userAgent);
    var equipoLento = (navigator.hardwareConcurrency && navigator.hardwareConcurrency < 4) || (navigator.deviceMemory && navigator.deviceMemory < 4);
    if (chromium && !equipoLento && CSS.supports('backdrop-filter', 'url(#bisel-menu) blur(3px)')) {
      prepararBisel(menu, 'menu', observarTamano);
      var lia = document.querySelector('#lia-raiz .lia-flotante');
      var portada = document.querySelector('.portada .boton');
      if (lia) { prepararBisel(lia, 'lia', observarTamano); }
      if (portada) { prepararBisel(portada, 'portada', observarTamano); }
    }

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
