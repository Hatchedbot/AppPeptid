# BRDG Seguimiento

App web para que el equipo de **BRDG Peptides Colombia** haga seguimiento de sus clientes: procesos, planes
(con historial de versiones), controles de peso, envío del plan por WhatsApp y una asistente de IA, **Lía**,
a la que se le puede preguntar «¿cómo va Julián Gómez?».

Hecha en PHP + MySQL (SQLite en local). Se publica en Hostinger.

## Probarla en tu PC

1. Necesitas XAMPP (trae PHP).
2. En la carpeta del proyecto:
   ```
   C:\xampp\php\php.exe -S localhost:8000
   ```
3. Abre http://localhost:8000 y crea el administrador.

## Pruebas

```
C:\xampp\php\php.exe tests/run.php
```

## Documentos

- `PLAN.md`: qué incluye la primera versión y el estado de cada tarea.
- `CLAUDE.md`: reglas del código y de los datos sensibles.
