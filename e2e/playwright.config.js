// Pruebas de punta a punta contra la DEMO (datos ficticios): C:\xampp\php\php.exe -S localhost:8080 router.php
// con APP_DB_DSN apuntando a datos/demo.sqlite. Correr: cd e2e && npx playwright test
const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests',
  timeout: 45_000,
  workers: 1,                      // el servidor de PHP atiende una petición a la vez
  retries: 0,
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: process.env.BRDG_URL || 'http://localhost:8080',
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
    locale: 'es-CO',
    timezoneId: 'America/Bogota',
  },
  projects: [
    { name: 'iphone-17-pro-max', use: { ...devices['iPhone 17 Pro Max'] } },   // WebKit = motor de Safari
    { name: 'computador', use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 800 } } },
  ],
});
