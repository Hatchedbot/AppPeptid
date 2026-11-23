// Recorrido de BRDG Seguimiento en iPhone 17 Pro Max (WebKit) y en computador (Chrome).
const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

// Las contraseñas de la demo NO van en el código: se leen del archivo que genera herramientas/datos_demo.php.
function credencial(usuario) {
  const archivo = path.join(__dirname, '..', '..', 'datos', 'LEEME-DEMO.txt');
  const linea = fs.readFileSync(archivo, 'utf8').split(/\r?\n/).find((l) => l.startsWith(usuario + ':'));
  if (!linea) { throw new Error('No encontré el usuario ' + usuario + ' en datos/LEEME-DEMO.txt'); }
  return linea.slice(usuario.length + 1).trim();
}

async function entrar(page, usuario = 'demo') {
  await page.goto('/login.php');
  await page.getByLabel('Usuario').fill(usuario);
  await page.locator('#clave').fill(credencial(usuario));
  await page.getByRole('button', { name: 'Entrar' }).click();
  await expect(page).toHaveURL(/index\.php|\/$/);
}

function vigilarErrores(page) {
  const errores = [];
  page.on('pageerror', (e) => errores.push('JS: ' + e.message));
  page.on('console', (m) => { if (m.type() === 'error') { errores.push('consola: ' + m.text()); } });
  return errores;
}

const pantallas = [
  ['Inicio', '/index.php'],
  ['Clientes', '/clientes.php'],
  ['Pedidos', '/pedidos.php'],
  ['Envíos', '/envios.php'],
  ['Inventario', '/inventario.php'],
  ['Mensajes', '/mensajes.php'],
  ['Usuarios', '/usuarios.php'],
];

test('los archivos privados no se pueden descargar', async ({ request }) => {
  for (const ruta of ['/datos/demo.sqlite', '/datos/LEEME-DEMO.txt', '/datos/app.sqlite', '/.git/config',
    '/includes/funciones.php', '/sql/001_inicial.sql', '/config.php', '/herramientas/datos_demo.php']) {
    const r = await request.get(ruta);
    expect(r.status(), ruta).toBe(404);
  }
});

test('entrada: el botón Mostrar deja ver la contraseña y se entra', async ({ page }) => {
  await page.goto('/login.php');
  await expect(page.getByAltText('BRDG Peptides')).toBeVisible();
  const clave = page.locator('#clave');
  await clave.fill('texto-de-prueba');
  await expect(clave).toHaveAttribute('type', 'password');
  await page.getByRole('button', { name: 'Mostrar la contraseña' }).click();
  await expect(clave).toHaveAttribute('type', 'text');
  await page.getByRole('button', { name: 'Ocultar la contraseña' }).click();
  await expect(clave).toHaveAttribute('type', 'password');
  await entrar(page);
  await expect(page.getByRole('heading', { level: 1 })).toContainText('Hola');
});

test('una contraseña equivocada no entra', async ({ page }) => {
  await page.goto('/login.php');
  await page.getByLabel('Usuario').fill('demo');
  await page.locator('#clave').fill('esta-no-es');
  await page.getByRole('button', { name: 'Entrar' }).click();
  await expect(page).toHaveURL(/login\.php/);
  await expect(page.locator('.aviso-error')).toBeVisible();
});

for (const [nombre, ruta] of pantallas) {
  test(`${nombre}: carga sin errores, sin desbordes y con el menú completo`, async ({ page }, info) => {
    const errores = vigilarErrores(page);
    await entrar(page);
    await page.goto(ruta);
    await expect(page.locator('main.contenido')).toBeVisible();
    await page.waitForTimeout(600);   // deja terminar la entrada suave

    // Nada se sale de la pantalla hacia los lados.
    const desborde = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    expect(desborde, 'ancho que se sale de la pantalla').toBeLessThanOrEqual(1);

    // Menú: 5 pestañas, y en celular ningún nombre cortado.
    await expect(page.locator('nav.menu > a')).toHaveCount(5);
    if (info.project.name.startsWith('iphone')) {
      const cortados = await page.$$eval('nav.menu > a span', (s) => s.filter((x) => x.scrollWidth > x.clientWidth + 1).map((x) => x.textContent));
      expect(cortados, 'nombres del menú cortados').toEqual([]);
    }

    // Lo que se toca mide al menos 44 px de alto (botones y pestañas visibles).
    const pequenos = await page.$$eval('main .boton, main button, nav.menu > a', (els) => els
      .filter((e) => e.offsetParent !== null)
      .map((e) => ({ t: (e.textContent || '').trim().slice(0, 30), h: Math.round(e.getBoundingClientRect().height) }))
      .filter((x) => x.h > 0 && x.h < 40));
    expect(pequenos, 'botones más bajos de 40 px').toEqual([]);

    await page.screenshot({ path: info.outputPath(`${nombre}.png`), fullPage: true });
    expect(errores, 'errores de JavaScript o de consola').toEqual([]);
  });
}

test('buscar un cliente muestra Crear pedido y Crear proceso', async ({ page }) => {
  await entrar(page);
  await page.goto('/clientes.php');
  const primero = await page.locator('table.tabla tbody tr td a.boton-texto').first().innerText();
  const nombre = primero.trim().split(' ')[0];
  await page.getByLabel('Nombre o teléfono').fill(nombre);
  await page.getByRole('button', { name: 'Buscar' }).click();
  await expect(page.getByRole('link', { name: new RegExp('Crear pedido para ' + nombre) }).first()).toBeVisible();
  await expect(page.getByRole('link', { name: new RegExp('Crear proceso para ' + nombre) }).first()).toBeVisible();
});

test('la cantidad del pedido solo acepta números enteros', async ({ page }) => {
  await entrar(page);
  await page.goto('/pedido.php?nuevo=1');
  const cantidad = page.locator('input[name$="[cantidad]"]').first();
  await expect(cantidad).toHaveAttribute('type', 'number');
  await expect(cantidad).toHaveAttribute('min', '1');
});

test('envíos muestra sus tres grupos', async ({ page }) => {
  await entrar(page);
  await page.goto('/envios.php');
  for (const titulo of [/Sin guía/i, /Esperando pago/i, /En camino/i]) {
    await expect(page.getByRole('heading', { name: titulo })).toBeVisible();
  }
});

test('la lente del menú no deforma los íconos en Safari', async ({ page, browserName }) => {
  await entrar(page);
  await page.goto('/inventario.php');
  const refracta = await page.evaluate(() => document.documentElement.classList.contains('lg-lente-refracta'));
  if (browserName === 'webkit') {
    expect(refracta, 'en Safari no debe usar la copia refractada').toBe(false);
    await expect(page.locator('.menu-lente-copia')).toBeHidden();
  }
  await expect(page.locator('nav.menu > a.activo')).toContainText('Inventario');
});

test('Lía responde una pregunta sin mostrar alias internos', async ({ page }) => {
  test.slow();
  await entrar(page);
  await page.getByRole('button', { name: 'Abrir a Lía' }).click();
  const campo = page.getByRole('textbox', { name: 'Escribe tu pregunta para Lía' });
  await campo.fill('¿Quién está atrasado?');
  await campo.press('Enter');
  const respuesta = page.locator('#lia-raiz .lia-msg.lia-lia').last();
  await expect(respuesta).toBeVisible({ timeout: 30_000 });
  await expect(respuesta).not.toHaveText(/Cliente\s*\d+/);
  await expect(respuesta).not.toHaveText(/^\s*$/);
});

// Lía como asistente: con un solo nombre, sin tildes, y llevándote a la pantalla.
for (const [frase, destino] of [
  ['llevame al pedido de nicolas', /pedido\.php\?id=\d+/],
  ['abre la ficha de julian', /cliente\.php\?id=\d+/],
  ['muestrame los envios de nicolas', /envios\.php/],
]) {
  test(`Lía: «${frase}» lleva a la pantalla`, async ({ page }) => {
    test.slow();
    await entrar(page);
    await page.getByRole('button', { name: 'Abrir a Lía' }).click();
    const campo = page.getByRole('textbox', { name: 'Escribe tu pregunta para Lía' });
    await campo.fill(frase);
    await campo.press('Enter');
    await expect(page).toHaveURL(destino, { timeout: 30_000 });
  });
}

test('pedidos: la búsqueda ignora tildes', async ({ page }) => {
  await entrar(page);
  await page.goto('/pedidos.php?q=nicolas');
  await expect(page.locator('table.tabla tbody tr').first()).toContainText('Nicolás');
});

test('la burbuja de Lía se arrastra, se pega al borde y un toque la abre', async ({ page }) => {
  await entrar(page);
  const burbuja = page.getByRole('button', { name: 'Abrir a Lía' });
  await expect(burbuja).toBeVisible();
  const caja = await burbuja.boundingBox();
  const ancho = page.viewportSize().width;
  // Arrastrar hacia la izquierda y un poco hacia arriba
  await page.mouse.move(caja.x + caja.width / 2, caja.y + caja.height / 2);
  await page.mouse.down();
  await page.mouse.move(ancho * 0.35, caja.y - 120, { steps: 12 });
  await page.mouse.up();
  await page.waitForTimeout(700);
  const despues = await burbuja.boundingBox();
  expect(despues.x, 'se pegó al borde izquierdo').toBeLessThan(40);
  expect(despues.y).toBeLessThan(caja.y);
  // Arrastrar no la abre
  await expect(page.getByRole('textbox', { name: 'Escribe tu pregunta para Lía' })).toBeHidden();
  // Un toque sí
  await burbuja.click();
  await expect(page.getByRole('textbox', { name: 'Escribe tu pregunta para Lía' })).toBeVisible();
});
