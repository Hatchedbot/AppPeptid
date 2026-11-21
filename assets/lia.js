// Lía: botón flotante, chat por texto y voz, respuestas en voz alta. Estilos aquí mismo.
(function () {
  'use strict';

  var raiz = document.getElementById('lia-raiz');
  if (!raiz || raiz.dataset.lia === '1' || typeof window.enviarJSON !== 'function') {
    return;
  }
  raiz.dataset.lia = '1';

  var estilo = document.createElement('style');
  estilo.textContent = [
    '#lia-raiz [hidden]{display:none !important}',
    '.lia-flotante{position:fixed;right:16px;bottom:16px;z-index:60;width:60px;height:60px;border-radius:50%;',
    '  border:0;background:var(--primario);color:#fff;font:600 15px/1 var(--fuente);cursor:pointer;',
    '  box-shadow:0 6px 18px rgba(16,24,40,.22);min-height:44px}',
    '.lia-flotante:hover{background:var(--primario-hover)}',
    '.lia-panel{position:fixed;right:16px;bottom:88px;z-index:61;display:flex;flex-direction:column;',
    '  width:min(370px,calc(100vw - 32px));max-height:min(70vh,560px);background:var(--superficie);',
    '  color:var(--texto);border:1px solid var(--borde);border-radius:var(--radio);box-shadow:var(--sombra);',
    '  overflow:hidden;font:16px/1.45 var(--fuente)}',
    '.lia-cabeza{display:flex;align-items:center;justify-content:space-between;gap:.5rem;padding:.6rem .8rem;',
    '  border-bottom:1px solid var(--borde);background:var(--superficie)}',
    '.lia-titulo{margin:0;font-size:1rem;font-weight:700}',
    '.lia-cerrar{border:0;background:none;color:var(--texto-suave);font:inherit;cursor:pointer;',
    '  min-width:44px;min-height:44px;border-radius:8px}',
    '.lia-cerrar:hover{background:var(--fondo)}',
    '.lia-mensajes{flex:1;overflow-y:auto;padding:.75rem;display:flex;flex-direction:column;gap:.5rem;',
    '  background:var(--fondo);min-height:150px}',
    '.lia-msg{max-width:92%;padding:.55rem .7rem;border-radius:10px;white-space:pre-wrap;word-wrap:break-word}',
    '.lia-msg p{margin:0}',
    '.lia-yo{align-self:flex-end;background:var(--primario);color:#fff}',
    '.lia-lia{align-self:flex-start;background:var(--superficie);border:1px solid var(--borde)}',
    '.lia-acciones{padding:0 .75rem .55rem;background:var(--fondo)}',
    '.lia-abrir{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:.5rem .9rem;',
    '  border-radius:10px;background:var(--primario);color:#fff;font-weight:600;text-decoration:none}',
    '.lia-form{display:grid;grid-template-columns:1fr auto;gap:.4rem;padding:.6rem .75rem;',
    '  border-top:1px solid var(--borde);background:var(--superficie)}',
    '.lia-campo{grid-column:1 / -1;width:100%;min-height:44px;padding:.55rem .65rem;border:1px solid var(--borde);',
    '  border-radius:10px;font:inherit;color:var(--texto);background:#fff}',
    '.lia-campo:focus{outline:2px solid var(--primario);outline-offset:1px}',
    '.lia-boton{min-height:44px;padding:.5rem .8rem;border:1px solid transparent;border-radius:10px;',
    '  background:var(--primario);color:#fff;font:600 .95rem/1 var(--fuente);cursor:pointer}',
    '.lia-boton:hover{background:var(--primario-hover)}',
    '.lia-boton[disabled]{opacity:.6;cursor:default}',
    '.lia-boton-sec{background:var(--superficie);color:var(--acento);border-color:var(--borde)}',
    '.lia-boton-sec:hover{background:var(--fondo)}',
    '.lia-pie{display:flex;gap:.4rem;align-items:center;justify-content:space-between;flex-wrap:wrap;',
    '  padding:0 .75rem .6rem;background:var(--superficie)}',
    '.lia-estado{font-size:.85rem;color:var(--texto-suave);min-height:1.2em}',
    '.lia-voz{display:flex;gap:.4rem;flex-wrap:wrap}',
    '@media (max-width:420px){.lia-panel{right:8px;left:8px;width:auto;bottom:80px}}'
  ].join('\n');
  document.head.appendChild(estilo);

  var abrir = document.createElement('button');
  abrir.type = 'button';
  abrir.className = 'lia-flotante';
  abrir.textContent = 'Lía';
  abrir.setAttribute('aria-label', 'Abrir a Lía');

  var panel = document.createElement('section');
  panel.className = 'lia-panel';
  panel.hidden = true;
  panel.setAttribute('aria-label', 'Chat con Lía');

  var cabeza = document.createElement('div');
  cabeza.className = 'lia-cabeza';
  var titulo = document.createElement('h2');
  titulo.className = 'lia-titulo';
  titulo.textContent = 'Lía';
  var cerrar = document.createElement('button');
  cerrar.type = 'button';
  cerrar.className = 'lia-cerrar';
  cerrar.textContent = 'Cerrar';
  cerrar.setAttribute('aria-label', 'Cerrar el chat');
  cabeza.appendChild(titulo);
  cabeza.appendChild(cerrar);

  var mensajes = document.createElement('div');
  mensajes.className = 'lia-mensajes';
  mensajes.setAttribute('role', 'log');
  mensajes.setAttribute('aria-live', 'polite');

  var acciones = document.createElement('div');
  acciones.className = 'lia-acciones';
  var enlace = document.createElement('a');
  enlace.className = 'lia-abrir';
  enlace.textContent = 'Abrir';
  enlace.hidden = true;
  acciones.appendChild(enlace);
  acciones.hidden = true;

  var formulario = document.createElement('form');
  formulario.className = 'lia-form';
  formulario.setAttribute('autocomplete', 'off');
  var campo = document.createElement('input');
  campo.className = 'lia-campo';
  campo.type = 'text';
  campo.placeholder = 'Pregúntame por un cliente o un caso';
  campo.setAttribute('aria-label', 'Escribe tu pregunta para Lía');
  campo.maxLength = 500;
  var enviar = document.createElement('button');
  enviar.type = 'submit';
  enviar.className = 'lia-boton';
  enviar.textContent = 'Enviar';
  var micro = document.createElement('button');
  micro.type = 'button';
  micro.className = 'lia-boton lia-boton-sec';
  micro.textContent = 'Hablar';
  micro.setAttribute('aria-label', 'Hablar con Lía por micrófono');
  formulario.appendChild(campo);
  formulario.appendChild(enviar);
  formulario.appendChild(micro);

  var pie = document.createElement('div');
  pie.className = 'lia-pie';
  var estado = document.createElement('span');
  estado.className = 'lia-estado';
  estado.setAttribute('role', 'status');
  var voz = document.createElement('div');
  voz.className = 'lia-voz';
  var silenciar = document.createElement('button');
  silenciar.type = 'button';
  silenciar.className = 'lia-boton lia-boton-sec';
  pie.appendChild(estado);
  pie.appendChild(voz);
  voz.appendChild(silenciar);

  panel.appendChild(cabeza);
  panel.appendChild(mensajes);
  panel.appendChild(acciones);
  panel.appendChild(formulario);
  panel.appendChild(pie);
  raiz.appendChild(abrir);
  raiz.appendChild(panel);

  var hablar = true;
  var reconociendo = false;
  var ocupado = false;
  var historial = [];
  var tieneVoz = typeof window.speechSynthesis !== 'undefined' && typeof window.SpeechSynthesisUtterance === 'function';
  var Reconocimiento = window.SpeechRecognition || window.webkitSpeechRecognition;
  var reconocedor = null;

  try {
    if (window.localStorage && window.localStorage.getItem('lia-voz') === '0') {
      hablar = false;
    }
  } catch (ignorado) { /* sin almacenamiento */ }

  function pintarVoz() {
    if (!tieneVoz) {
      silenciar.hidden = true;
      return;
    }
    silenciar.textContent = hablar ? 'Silenciar voz' : 'Activar voz';
    silenciar.setAttribute('aria-pressed', hablar ? 'true' : 'false');
  }

  function decir(texto) {
    if (!tieneVoz || !hablar || !texto) {
      return;
    }
    try {
      window.speechSynthesis.cancel();
      var frase = new window.SpeechSynthesisUtterance(texto);
      frase.lang = 'es-CO';
      var voces = window.speechSynthesis.getVoices ? window.speechSynthesis.getVoices() : [];
      for (var i = 0; i < voces.length; i++) {
        if ((voces[i].lang || '').toLowerCase().indexOf('es') === 0) {
          frase.voice = voces[i];
          break;
        }
      }
      window.speechSynthesis.speak(frase);
    } catch (ignorado) { /* voz no disponible */ }
  }

  function recortarHistorial() {
    while (historial.length > 6) {
      historial.shift();
    }
  }

  function agregar(texto, de) {
    var fila = document.createElement('div');
    fila.className = 'lia-msg ' + (de === 'yo' ? 'lia-yo' : 'lia-lia');
    var parrafo = document.createElement('p');
    parrafo.textContent = texto;
    fila.appendChild(parrafo);
    mensajes.appendChild(fila);
    mensajes.scrollTop = mensajes.scrollHeight;
  }

  function mostrarEnlace(url) {
    var segura = typeof url === 'string' && /^[a-z0-9_\-]+\.php(\?[^\s]*)?$/i.test(url);
    if (!segura) {
      enlace.hidden = true;
      acciones.hidden = true;
      return;
    }
    enlace.href = url;
    enlace.hidden = false;
    acciones.hidden = false;
  }

  function ocupar(valor) {
    ocupado = valor;
    enviar.disabled = valor;
    campo.disabled = valor;
    estado.textContent = valor ? 'Pensando…' : '';
  }

  function responderError() {
    var texto = 'No pude conectarme. Revisa tu conexión e inténtalo de nuevo.';
    agregar(texto, 'lia');
    historial.push('Lía: ' + texto);
    recortarHistorial();
    estado.textContent = '';
  }

  function enviarTexto() {
    var texto = campo.value.trim();
    if (texto === '' || ocupado) {
      return;
    }
    campo.value = '';
    mostrarEnlace('');
    agregar(texto, 'yo');
    historial.push('Tú: ' + texto);
    recortarHistorial();
    ocupar(true);

    window.enviarJSON('api/lia.php', { texto: texto, historial: historial.slice() }).then(function (r) {
      var respuesta = r && typeof r.respuesta === 'string' && r.respuesta.trim() !== ''
        ? r.respuesta.trim()
        : 'No pude responder ahora. Inténtalo de nuevo.';
      agregar(respuesta, 'lia');
      historial.push('Lía: ' + respuesta);
      recortarHistorial();
      mostrarEnlace(r && typeof r.url === 'string' ? r.url : '');
      decir(respuesta);
      ocupar(false);
    }, function () {
      ocupar(false);
      responderError();
    });
  }

  function abrirPanel() {
    panel.hidden = false;
    abrir.setAttribute('aria-expanded', 'true');
    campo.focus();
  }

  function cerrarPanel() {
    panel.hidden = true;
    abrir.setAttribute('aria-expanded', 'false');
    if (tieneVoz) {
      try { window.speechSynthesis.cancel(); } catch (ignorado) { /* nada */ }
    }
    abrir.focus();
  }

  abrir.addEventListener('click', function () {
    if (panel.hidden) {
      abrirPanel();
    } else {
      cerrarPanel();
    }
  });
  cerrar.addEventListener('click', cerrarPanel);
  panel.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') {
      cerrarPanel();
    }
  });

  formulario.addEventListener('submit', function (ev) {
    ev.preventDefault();
    if (reconociendo && reconocedor) {
      try { reconocedor.stop(); } catch (ignorado) { /* nada */ }
    }
    enviarTexto();
  });

  silenciar.addEventListener('click', function () {
    hablar = !hablar;
    pintarVoz();
    if (!hablar && tieneVoz) {
      try { window.speechSynthesis.cancel(); } catch (ignorado) { /* nada */ }
    }
    try {
      if (window.localStorage) {
        window.localStorage.setItem('lia-voz', hablar ? '1' : '0');
      }
    } catch (ignorado) { /* sin almacenamiento */ }
  });

  if (!tieneVoz) {
    silenciar.hidden = true;
  } else {
    pintarVoz();
    if (window.speechSynthesis.addEventListener) {
      window.speechSynthesis.addEventListener('voiceschanged', pintarVoz);
    }
  }

  if (!Reconocimiento) {
    micro.hidden = true;
  } else {
    reconocedor = new Reconocimiento();
    reconocedor.lang = 'es-CO';
    reconocedor.interimResults = false;
    reconocedor.maxAlternatives = 1;

    reconocedor.onresult = function (ev) {
      var dicho = '';
      if (ev.results && ev.results[0] && ev.results[0][0]) {
        dicho = ev.results[0][0].transcript || '';
      }
      campo.value = dicho.trim();
      if (campo.value !== '') {
        enviarTexto();
      }
    };
    reconocedor.onend = function () {
      reconociendo = false;
      micro.textContent = 'Hablar';
      estado.textContent = '';
    };
    reconocedor.onerror = function () {
      reconociendo = false;
      micro.textContent = 'Hablar';
      estado.textContent = 'No te escuché. Inténtalo otra vez.';
    };

    micro.addEventListener('click', function () {
      if (reconociendo) {
        try { reconocedor.stop(); } catch (ignorado) { /* nada */ }
        return;
      }
      if (tieneVoz) {
        try { window.speechSynthesis.cancel(); } catch (ignorado) { /* nada */ }
      }
      try {
        reconocedor.start();
        reconociendo = true;
        micro.textContent = 'Escuchando…';
        estado.textContent = 'Habla ahora';
      } catch (ignorado) {
        estado.textContent = 'No pude usar el micrófono.';
      }
    });
  }

  abrir.setAttribute('aria-expanded', 'false');
  abrir.setAttribute('aria-controls', 'lia-panel-chat');
  panel.id = 'lia-panel-chat';
})();
