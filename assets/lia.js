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
    '#lia-raiz .lia-flotante{position:fixed;top:0;left:0;right:auto;bottom:auto;z-index:60;width:56px;height:56px;',
    '  display:grid;place-items:center;padding:0;border:0;border-radius:50%;cursor:grab;touch-action:none;user-select:none;',
    '  transform:translate3d(var(--lia-x,0px),var(--lia-y,0px),0);transition:transform 380ms cubic-bezier(.2,.8,.35,1.18);',
    '  background:radial-gradient(circle at 35% 20%,var(--primario),var(--noche) 85%);color:var(--superficie);',
    '  box-shadow:inset 0 2px 4px var(--superficie),var(--sombra);overflow:visible}',
    '#lia-raiz .lia-flotante:active{transform:translate3d(var(--lia-x,0px),var(--lia-y,0px),0);opacity:1}',
    '#lia-raiz .lia-flotante[data-moviendo]{transition:none;cursor:grabbing}',
    '#lia-raiz .lia-flotante::before{content:"";position:absolute;inset:0;border-radius:inherit;pointer-events:none;',
    '  background:none;-webkit-mask:none;mask:none;box-shadow:0 0 14px 4px var(--primario);animation:lia-halo 4s ease-in-out infinite}',
    '@keyframes lia-halo{0%,100%{opacity:.12;transform:scale(1)}50%{opacity:.28;transform:scale(1.08)}}',
    '.lia-flotante svg{width:27px;height:27px;fill:currentColor;pointer-events:none}',
    '.lia-flotante[data-estado="pensando"] svg{animation:lia-giro 3s linear infinite}',
    '@keyframes lia-giro{to{transform:rotate(1turn)}}',
    '.lia-flotante[data-estado="escuchando"] svg,.lia-flotante:not([data-estado="escuchando"]) span{display:none}',
    '.lia-flotante span{display:flex;align-items:center;gap:3px}',
    '.lia-flotante i{width:3px;height:20px;border-radius:4px;background:currentColor;animation:lia-onda .65s ease-in-out infinite alternate}',
    '.lia-flotante i:nth-child(2){animation-delay:-.3s}.lia-flotante i:nth-child(3){animation-delay:-.5s}',
    '@keyframes lia-onda{from{transform:scaleY(.35)}to{transform:scaleY(1)}}',
    '.lia-area{position:fixed;visibility:hidden;pointer-events:none;top:env(safe-area-inset-top);',
    '  right:env(safe-area-inset-right);bottom:env(safe-area-inset-bottom);left:env(safe-area-inset-left)}',
    '.lia-sugerencias{display:flex;flex-wrap:wrap;gap:.35rem}',
    '.lia-transcripcion{min-height:1.45em}',
    '@media (prefers-reduced-motion: reduce){#lia-raiz .lia-flotante{transition:none}#lia-raiz .lia-flotante::before,',
    '  #lia-raiz .lia-flotante svg,#lia-raiz .lia-flotante i{animation:none}}',
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
    '.lia-yo{align-self:flex-end;background:var(--primario);color:var(--superficie)}',
    '.lia-lia{align-self:flex-start;background:var(--superficie);border:1px solid var(--borde)}',
    '.lia-acciones{padding:0 .75rem .55rem;background:var(--fondo)}',
    '.lia-abrir{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:.5rem .9rem;',
    '  border-radius:10px;background:var(--primario);color:var(--superficie);font-weight:600;text-decoration:none}',
    '.lia-form{display:grid;grid-template-columns:1fr auto;gap:.4rem;padding:.6rem .75rem;',
    '  border-top:1px solid var(--borde);background:var(--superficie)}',
    '.lia-campo{grid-column:1 / -1;width:100%;min-height:44px;padding:.55rem .65rem;border:1px solid var(--borde);',
    '  border-radius:10px;font:inherit;color:var(--texto);background:var(--superficie)}',
    '.lia-campo:focus{outline:2px solid var(--primario);outline-offset:1px}',
    '.lia-boton{min-height:44px;padding:.5rem .8rem;border:1px solid transparent;border-radius:10px;',
    '  background:var(--primario);color:var(--superficie);font:600 .95rem/1 var(--fuente);cursor:pointer}',
    '.lia-boton:hover{background:var(--primario-hover)}',
    '.lia-boton[disabled]{opacity:.6;cursor:default}',
    '.lia-boton-sec{background:var(--superficie);color:var(--acento);border-color:var(--borde)}',
    '.lia-boton-sec:hover{background:var(--fondo)}',
    '.lia-pie{display:flex;gap:.4rem;align-items:center;justify-content:space-between;flex-wrap:wrap;',
    '  padding:0 .75rem .6rem;background:var(--superficie)}',
    '.lia-estado{font-size:.85rem;color:var(--texto-suave);min-height:1.2em}',
    '.lia-voz{display:flex;gap:.4rem;flex-wrap:wrap}',
    '#lia-raiz .lia-panel{bottom:var(--lia-panel-abajo,88px);max-height:min(72dvh,640px,var(--lia-panel-alto,640px))}',
    '#lia-raiz .lia-mensajes{min-height:0;overscroll-behavior:contain}',
    '#lia-raiz button{min-width:44px;min-height:44px}',
    '@media (min-width:769px){.lia-panel[data-lado="izquierdo"]{left:16px;right:auto}}',
    '@media (max-width:768px){#lia-raiz .lia-panel{left:max(10px,env(safe-area-inset-left));',
    '  right:max(10px,env(safe-area-inset-right));width:auto;border-radius:26px}}'
  ].join('\n');
  document.head.appendChild(estilo);

  var abrir = document.createElement('button');
  abrir.type = 'button';
  abrir.className = 'lia-flotante';
  abrir.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 0C13.4 7.2 16.8 10.6 24 12C16.8 13.4 13.4 16.8 12 24C10.6 16.8 7.2 13.4 0 12C7.2 10.6 10.6 7.2 12 0Z"/></svg>'
    + '<span aria-hidden="true"><i></i><i></i><i></i></span>';
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
  var area = document.createElement('div');
  area.className = 'lia-area';
  area.setAttribute('aria-hidden', 'true');
  raiz.appendChild(area);
  raiz.appendChild(abrir);
  raiz.appendChild(panel);

  function leer(clave) {
    try { return window.localStorage.getItem(clave); } catch (error) { return null; }
  }

  function guardar(clave, valor) {
    try { window.localStorage.setItem(clave, valor); } catch (error) { /* almacenamiento no disponible */ }
  }

  var hablar = leer('lia-voz') !== '0';
  var ocupado = false;
  var tieneVoz = typeof window.speechSynthesis !== 'undefined' && typeof window.SpeechSynthesisUtterance === 'function';
  var esBrave = 'brave' in navigator;
  var Reconocimiento = esBrave ? null : (window.SpeechRecognition || window.webkitSpeechRecognition);
  var escucha = null;
  var apertura = 0;
  var navegacion;
  var historialAbierto = false;
  var cerrandoHistorial = false;

  function pintarVoz() {
    silenciar.hidden = !tieneVoz;
    silenciar.textContent = hablar ? 'Silenciar voz' : 'Activar voz';
    silenciar.setAttribute('aria-pressed', String(!hablar));
  }

  function callar() {
    if (tieneVoz) {
      try { window.speechSynthesis.cancel(); } catch (error) { /* síntesis no disponible */ }
    }
  }

  function prioridadVoz(voz) {
    var idioma = voz.lang.toLowerCase().replace(/_/g, '-');
    var region = idioma === 'es-co' ? 0 : /^es-(mx|us|419)$/.test(idioma) ? 1 : idioma === 'es-es' ? 2 : 3;
    return region * 2 + (/premium|enhanced|natural/i.test(voz.name) ? 0 : 1);
  }

  function decir(texto) {
    if (!tieneVoz || !hablar || panel.hidden || escucha) { return; }
    try {
      var voces = window.speechSynthesis.getVoices().filter(function (voz) {
        return voz.localService && /^es(?:[-_]|$)/i.test(voz.lang);
      });
      voces.sort(function (a, b) { return prioridadVoz(a) - prioridadVoz(b); });
      if (!voces.length) { return; }
      callar();
      var frase = new window.SpeechSynthesisUtterance(texto);
      frase.voice = voces[0];
      frase.lang = voces[0].lang;
      window.speechSynthesis.speak(frase);
    } catch (error) { /* síntesis no disponible */ }
  }

  function agregar(texto, de) {
    var fila = document.createElement('div');
    fila.className = 'lia-msg ' + (de === 'yo' ? 'lia-yo' : 'lia-lia');
    var parrafo = document.createElement('p');
    parrafo.textContent = texto;
    fila.appendChild(parrafo);
    mensajes.appendChild(fila);
    mensajes.scrollTop = mensajes.scrollHeight;
    return fila;
  }

  function urlSegura(url) {
    return typeof url === 'string' && /^[a-z0-9_\-]+\.php(\?[^\s#]*)?(#[a-z0-9_\-]+)?$/i.test(url);
  }

  // Si la persona pidió ir a algún lado («llévame», «abre», «muéstrame», «nuevo pedido»…), Lía la lleva sola.
  var pideIr = /\b(ll[eé]v[ae]me|lleva|llevar|abre|abrir|mu[eé]strame|muestra|ens[eé][nñ]ame|ir a|ve a|v[aá]monos|quiero ver|ver (la|el|los|las|mis)|nuevo|nueva|registrar|crear|hacer un)\b/i;
  function llevarSiPidio(texto, r) {
    if (!r || !urlSegura(r.url)) { return; }
    if (r.accion !== 'ir' && !pideIr.test(texto)) { return; }
    estado.textContent = 'Te llevo…';
    navegacion = setTimeout(function () { window.location.href = r.url; }, 1100);
  }

  function mostrarEnlace(url) {
    var segura = urlSegura(url);
    if (!segura) {
      enlace.hidden = true;
      acciones.hidden = true;
      return;
    }
    enlace.href = url;
    enlace.hidden = false;
    acciones.hidden = false;
  }

  function pintarEstado() {
    abrir.dataset.estado = escucha ? 'escuchando' : ocupado ? 'pensando' : '';
    estado.textContent = ocupado ? 'Pensando…' : '';
    micro.textContent = escucha ? 'Ya terminé' : 'Hablar';
    micro.setAttribute('aria-label', escucha ? 'Ya terminé de hablar' : 'Hablar con Lía por micrófono');
    micro.setAttribute('aria-pressed', String(Boolean(escucha)));
    micro.disabled = ocupado;
  }

  function ocupar(valor) {
    ocupado = valor;
    enviar.disabled = valor;
    campo.disabled = valor;
    sugerencias.querySelectorAll('button').forEach(function (boton) { boton.disabled = valor; });
    pintarEstado();
  }

  function enviarTexto(texto) {
    texto = texto.trim().slice(0, campo.maxLength);
    if (!texto || ocupado || panel.hidden) { return; }
    descartarEscucha();
    callar();
    clearTimeout(navegacion);
    mostrarEnlace('');
    agregar(texto, 'yo');
    ocupar(true);

    var turno = apertura;
    window.enviarJSON('api/lia.php', { texto: texto }).then(function (r) {
      var respuesta = r && typeof r.respuesta === 'string' && r.respuesta.trim()
        ? r.respuesta.trim()
        : 'No pude responder ahora. Inténtalo de nuevo.';
      agregar(respuesta, 'lia');
      mostrarEnlace(r && typeof r.url === 'string' ? r.url : '');
      ocupar(false);
      if (turno === apertura && !panel.hidden) {
        decir(respuesta);
        llevarSiPidio(texto, r);
      }
    }, function () {
      ocupar(false);
      agregar('No pude conectarme. Revisa tu conexión e inténtalo de nuevo.', 'lia');
    });
  }

  var sugerencias = document.createElement('div');
  sugerencias.className = 'lia-sugerencias';
  ['¿Quién está atrasado hoy?', 'Llévame a envíos', 'Nuevo pedido', '¿Qué productos están agotados?'].forEach(function (texto) {
    var boton = document.createElement('button');
    boton.type = 'button';
    boton.className = 'lia-boton lia-boton-sec';
    boton.textContent = texto;
    boton.addEventListener('click', function () { enviarTexto(texto); });
    sugerencias.appendChild(boton);
  });

  function saludar() {
    var perfil = document.querySelector('.quien-nombre');
    var nombre = (raiz.dataset.nombre || document.body.dataset.nombre || (perfil && perfil.textContent) || '').trim().split(/\s+/)[0];
    agregar(nombre ? 'Hola, ' + nombre + '. ¿En qué te ayudo?' : 'Hola.', 'lia');
    mensajes.appendChild(sugerencias);
    if (esBrave && leer('lia-aviso-brave') !== '1') {
      agregar('En Brave no puedo escucharte; escríbeme o usa Safari/Chrome.', 'lia');
      guardar('lia-aviso-brave', '1');
    }
  }

  function abrirPanel() {
    if (cerrandoHistorial) { return; }
    apertura++;
    panel.hidden = false;
    abrir.setAttribute('aria-expanded', 'true');
    if (!mensajes.childElementCount) { saludar(); }
    mensajes.scrollTop = mensajes.scrollHeight;
    try {
      history.pushState(Object.assign({}, history.state, { liaAbierta: true }), '');
      historialAbierto = true;
    } catch (error) { /* historial no disponible */ }
    if (Reconocimiento && !ocupado) {
      cerrar.focus({ preventScroll: true });
      escuchar();
    } else if (!ocupado) {
      campo.focus({ preventScroll: true });
    }
  }

  function cerrarPanel(desdeHistorial, devolverFoco) {
    if (panel.hidden) { return; }
    panel.hidden = true;
    abrir.setAttribute('aria-expanded', 'false');
    descartarEscucha();
    callar();
    clearTimeout(navegacion);
    pintarEstado();
    if (!desdeHistorial && historialAbierto && history.state && history.state.liaAbierta) {
      cerrandoHistorial = true;
      history.back();
    }
    historialAbierto = false;
    if (devolverFoco) { abrir.focus({ preventScroll: true }); }
  }

  window.addEventListener('popstate', function () {
    cerrandoHistorial = false;
    cerrarPanel(true, true);
  });
  window.addEventListener('pagehide', function () { cerrarPanel(true, false); });
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape' && !panel.hidden) {
      ev.preventDefault();
      cerrarPanel(false, true);
    }
  });
  document.addEventListener('click', function (ev) {
    if (!panel.hidden && !panel.contains(ev.target) && !abrir.contains(ev.target)) {
      cerrarPanel(false, false);
    }
  });
  cerrar.addEventListener('click', function () { cerrarPanel(false, true); });

  formulario.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var texto = campo.value;
    if (!texto.trim() || ocupado) { return; }
    campo.value = '';
    enviarTexto(texto);
  });
  campo.addEventListener('input', descartarEscucha);

  silenciar.addEventListener('click', function () {
    hablar = !hablar;
    pintarVoz();
    if (!hablar) { callar(); }
    guardar('lia-voz', hablar ? '1' : '0');
  });

  function transcripcion(sesion) {
    return sesion.frases.concat(sesion.frase).join(' ').trim();
  }

  function descartarEscucha() {
    var sesion = escucha;
    if (!sesion) { return; }
    escucha = null;
    clearInterval(sesion.vigilante);
    clearTimeout(sesion.reinicio);
    clearTimeout(sesion.respaldo);
    sesion.burbuja.remove();
    if (sesion.activa) {
      try { sesion.rec.abort(); } catch (error) { /* el motor ya se detuvo */ }
    }
    pintarEstado();
  }

  function enviarDictado(sesion) {
    var texto = transcripcion(sesion);
    descartarEscucha();
    enviarTexto(texto);
  }

  function terminarEscucha(sesion) {
    if (sesion.terminando) { return; }
    sesion.terminando = true;
    if (sesion.activa) {
      try {
        sesion.rec.stop();
        // Algunos motores omiten onend después de stop().
        if (escucha === sesion) {
          sesion.respaldo = setTimeout(function () { enviarDictado(sesion); }, 500);
        }
        return;
      } catch (error) { /* conserva la última transcripción */ }
    }
    enviarDictado(sesion);
  }

  function iniciarFrase(sesion) {
    if (escucha !== sesion || sesion.terminando) { return; }
    try {
      sesion.activa = true;
      sesion.rec.start();
    } catch (error) {
      descartarEscucha();
      agregar('No pude usar el micrófono. Revisa el permiso o escríbeme abajo.', 'lia');
    }
  }

  function escuchar() {
    if (!Reconocimiento || ocupado || panel.hidden) { return; }
    if (escucha) {
      terminarEscucha(escucha);
      return;
    }
    callar();
    if (navigator.onLine === false) {
      agregar('Para escucharte necesito internet. Puedes escribirme abajo.', 'lia');
      return;
    }
    var rec;
    try { rec = new Reconocimiento(); } catch (error) {
      agregar('No pude usar el micrófono. Puedes escribirme abajo.', 'lia');
      return;
    }
    var burbuja = agregar('Te escucho…', 'yo');
    burbuja.classList.add('lia-transcripcion');
    var sesion = escucha = { rec: rec, burbuja: burbuja, frases: [], frase: '', ultima: Date.now() };
    rec.lang = 'es-CO';
    rec.continuous = false;
    rec.interimResults = true;
    rec.maxAlternatives = 1;

    function detectarVoz() {
      if (escucha !== sesion) { return; }
      sesion.corte = 0;
      sesion.hablo = true;
      sesion.ultima = Date.now();
    }
    rec.onspeechstart = detectarVoz;
    rec.onresult = function (ev) {
      if (escucha !== sesion) { return; }
      // Cada ciclo entrega una hipótesis completa, incluso cuando cambia de interina a final.
      var partes = [];
      for (var i = 0; i < ev.results.length; i++) {
        partes.push(ev.results[i][0].transcript.trim());
      }
      sesion.frase = partes.join(' ').trim();
      if (sesion.frase) { detectarVoz(); }
      burbuja.firstChild.textContent = transcripcion(sesion) || 'Te escucho…';
      mensajes.scrollTop = mensajes.scrollHeight;
    };
    rec.onerror = function (ev) {
      if (escucha !== sesion || ev.error === 'no-speech' || ev.error === 'aborted') { return; }
      var aviso = 'No pude usar el micrófono. Puedes escribirme abajo.';
      if (ev.error === 'not-allowed' || ev.error === 'service-not-allowed') {
        aviso = 'El navegador no dio permiso para el micrófono. Puedes escribirme abajo.';
      } else if (ev.error === 'network') {
        aviso = 'Para escucharte necesito internet. Puedes escribirme abajo.';
      }
      descartarEscucha();
      agregar(aviso, 'lia');
    };
    rec.onend = function () {
      if (escucha !== sesion) { return; }
      sesion.activa = false;
      if (sesion.frase) { sesion.frases.push(sesion.frase); }
      sesion.frase = '';
      if (sesion.terminando) {
        enviarDictado(sesion);
        return;
      }
      if (sesion.hablo && !sesion.corte) { sesion.corte = Date.now(); }
      sesion.reinicio = setTimeout(function () { iniciarFrase(sesion); }, 150);
    };
    sesion.vigilante = setInterval(function () {
      var ahora = Date.now();
      var silencio = ahora - sesion.ultima;
      if ((sesion.corte && ahora - sesion.corte >= 1000) || silencio >= (sesion.hablo ? 6000 : 8000)) {
        terminarEscucha(sesion);
      }
    }, 100);
    pintarEstado();
    iniciarFrase(sesion);
  }

  micro.hidden = !Reconocimiento;
  micro.addEventListener('click', escuchar);
  pintarVoz();

  // La altura guardada es proporcional al espacio libre entre las barras.
  var posicion = { lado: 'derecho', altura: 1 };
  try {
    var anterior = JSON.parse(leer('lia-posicion'));
    if (anterior && /^(izquierdo|derecho)$/.test(anterior.lado) && Number.isFinite(anterior.altura)) {
      posicion = anterior;
    }
  } catch (error) { /* posición guardada inválida */ }

  var regla = estilo.sheet.cssRules[estilo.sheet.insertRule('#lia-raiz {}', estilo.sheet.cssRules.length)].style;
  var barra = document.querySelector('.barra');
  var menu = document.querySelector('.menu');
  var pantalla = window.visualViewport;
  var limites;
  var punto;
  var arrastre;
  var omitirClic = false;

  function limitar(valor, minimo, maximo) {
    return Math.max(minimo, Math.min(valor, maximo));
  }

  function medir() {
    var segura = area.getBoundingClientRect();
    var movil = window.matchMedia('(max-width: 768px)').matches;
    var fondoVisible = pantalla ? pantalla.offsetTop + pantalla.height : innerHeight;
    var techo = Math.max(segura.top, pantalla ? pantalla.offsetTop : 0, barra ? barra.getBoundingClientRect().bottom : 0);
    var piso = Math.min(segura.bottom, fondoVisible);
    if (movil && menu && menu.getBoundingClientRect().height) {
      piso = Math.min(piso, menu.getBoundingClientRect().top);
    }
    var ancho = abrir.offsetWidth;
    var alto = abrir.offsetHeight;
    limites = {
      izquierda: segura.left + 10,
      derecha: Math.max(segura.left + 10, segura.right - ancho - 10),
      arriba: techo + 10,
      abajo: Math.max(techo + 10, piso - alto - 10)
    };
    var panelAbajo = Math.max(0, innerHeight - piso) + alto + 20;
    regla.setProperty('--lia-panel-abajo', panelAbajo + 'px');
    regla.setProperty('--lia-panel-alto', Math.max(0, innerHeight - panelAbajo - techo - 10) + 'px');
  }

  function mover(x, y) {
    punto = {
      x: limitar(x, limites.izquierda, limites.derecha),
      y: limitar(y, limites.arriba, limites.abajo)
    };
    regla.setProperty('--lia-x', punto.x + 'px');
    regla.setProperty('--lia-y', punto.y + 'px');
  }

  function acoplar() {
    panel.dataset.lado = posicion.lado;
    mover(posicion.lado === 'izquierdo' ? limites.izquierda : limites.derecha,
      limites.arriba + posicion.altura * (limites.abajo - limites.arriba));
  }

  function recolocar() {
    medir();
    if (arrastre && arrastre.movido) { mover(punto.x, punto.y); }
    else { acoplar(); }
  }

  abrir.addEventListener('pointerdown', function (ev) {
    if (!ev.isPrimary || ev.button !== 0 || arrastre) { return; }
    medir();
    var rect = abrir.getBoundingClientRect();
    arrastre = { id: ev.pointerId, x: ev.clientX, y: ev.clientY, dx: ev.clientX - rect.left, dy: ev.clientY - rect.top };
    omitirClic = false;
    abrir.setPointerCapture(ev.pointerId);
  });

  function seguir(ev) {
    if (!arrastre || ev.pointerId !== arrastre.id) { return; }
    if (!arrastre.movido && Math.hypot(ev.clientX - arrastre.x, ev.clientY - arrastre.y) <= 8) { return; }
    arrastre.movido = true;
    abrir.dataset.moviendo = '';
    mover(ev.clientX - arrastre.dx, ev.clientY - arrastre.dy);
  }

  function soltar(ev) {
    if (!arrastre || ev.pointerId !== arrastre.id) { return; }
    if (ev.type === 'pointerup') { seguir(ev); }
    var movido = arrastre.movido;
    arrastre = null;
    omitirClic = movido || ev.type !== 'pointerup';
    if (!movido) { return; }
    posicion.lado = punto.x < (limites.izquierda + limites.derecha) / 2 ? 'izquierdo' : 'derecho';
    var recorrido = limites.abajo - limites.arriba;
    if (recorrido) { posicion.altura = (punto.y - limites.arriba) / recorrido; }
    guardar('lia-posicion', JSON.stringify(posicion));
    // Consolida la última posición arrastrada antes de activar la transición.
    abrir.getBoundingClientRect();
    delete abrir.dataset.moviendo;
    acoplar();
  }

  abrir.addEventListener('pointermove', seguir);
  abrir.addEventListener('pointerup', soltar);
  abrir.addEventListener('pointercancel', soltar);
  abrir.addEventListener('lostpointercapture', soltar);
  abrir.addEventListener('click', function (ev) {
    if (omitirClic && ev.detail !== 0) { return; }
    if (panel.hidden) { abrirPanel(); }
    else { cerrarPanel(false, true); }
  });

  abrir.setAttribute('aria-expanded', 'false');
  abrir.setAttribute('aria-controls', 'lia-panel-chat');
  panel.id = 'lia-panel-chat';

  abrir.dataset.moviendo = '';
  recolocar();
  abrir.getBoundingClientRect();
  delete abrir.dataset.moviendo;
  window.addEventListener('resize', recolocar);
  if (pantalla) {
    pantalla.addEventListener('resize', recolocar);
    pantalla.addEventListener('scroll', recolocar);
  }
  if (window.ResizeObserver) {
    var observar = new ResizeObserver(recolocar);
    if (barra) { observar.observe(barra); }
    if (menu) { observar.observe(menu); }
  }
})();
