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

  // Botones con data-confirmar="¿Seguro?" piden confirmación antes de enviar.
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-confirmar]');
    if (b && !window.confirm(b.getAttribute('data-confirmar'))) {
      ev.preventDefault();
    }
  });
})();
