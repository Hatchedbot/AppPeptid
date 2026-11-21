<?php
/**
 * Funciones de clientes: crear, actualizar, consultar y buscar.
 *
 * Los datos de salud son sensibles (Ley 1581 de 2012): al crear un cliente se exige
 * la autorización y se guarda la fecha en clientes.consentimiento_fecha.
 * Cada creación o edición queda en el historial con registrar_evento().
 */

/** Texto comparable para buscar por nombre: minúsculas y sin tildes. */
function cliente_texto_comparable(string $texto): string
{
    $texto = (string) preg_replace('/\s+/u', ' ', trim($texto));
    $texto = mb_strtolower($texto, 'UTF-8');
    return strtr($texto, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u', 'ç' => 'c',
    ]);
}

/** Solo los dígitos de un teléfono. " +57 311-503-4356 " → "573115034356". */
function cliente_telefono_digitos(string $telefono): string
{
    return (string) preg_replace('/\D/', '', $telefono);
}

/**
 * Dígitos comparables de un teléfono colombiano: quita el indicativo 57 para que
 * "573115034356" y "311 503 4356" cuenten como el mismo número.
 */
function cliente_telefono_comparable(string $telefono): string
{
    $digitos = cliente_telefono_digitos($telefono);
    if (strlen($digitos) === 12 && str_starts_with($digitos, '57')) {
        return substr($digitos, 2);
    }
    return $digitos;
}

/** Busca un cliente por teléfono (comparando solo los dígitos). */
function cliente_por_telefono(string $telefono, ?int $excepto_id = null): ?array
{
    $buscado = cliente_telefono_comparable($telefono);
    if ($buscado === '') {
        return null;
    }
    $filas = db()->query('SELECT id, nombre, telefono FROM clientes ORDER BY id')->fetchAll();
    foreach ($filas as $fila) {
        if ($excepto_id !== null && (int) $fila['id'] === $excepto_id) {
            continue;
        }
        if (cliente_telefono_comparable((string) $fila['telefono']) === $buscado) {
            return $fila;
        }
    }
    return null;
}

/**
 * Revisa y limpia los datos del formulario. Lanza InvalidArgumentException con un
 * mensaje que se le pueda mostrar tal cual a la persona.
 */
function cliente_validar(array $datos, bool $exigir_consentimiento): array
{
    $texto = static fn(string $campo): string => trim((string) ($datos[$campo] ?? ''));

    $nombre = (string) preg_replace('/\s+/u', ' ', $texto('nombre'));
    $telefono = (string) preg_replace('/\s+/u', ' ', $texto('telefono'));
    $correo = $texto('correo');
    $ciudad = (string) preg_replace('/\s+/u', ' ', $texto('ciudad'));
    $objetivo = $texto('objetivo');
    $condiciones = $texto('condiciones');
    $notas = $texto('notas');
    $sexo = $texto('sexo');
    $fecha_nacimiento = $texto('fecha_nacimiento');

    if ($nombre === '') {
        throw new InvalidArgumentException('Escribe el nombre del cliente.');
    }
    if (mb_strlen($nombre) > 150) {
        throw new InvalidArgumentException('El nombre no puede tener más de 150 caracteres.');
    }

    if ($telefono === '') {
        throw new InvalidArgumentException('Escribe el teléfono del cliente.');
    }
    if (mb_strlen($telefono) > 20) {
        throw new InvalidArgumentException('El teléfono no puede tener más de 20 caracteres.');
    }
    if (strlen(cliente_telefono_digitos($telefono)) < 7) {
        throw new InvalidArgumentException('El teléfono debe tener al menos 7 dígitos.');
    }

    if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('El correo no parece válido. Revísalo.');
    }
    if (mb_strlen($correo) > 150) {
        throw new InvalidArgumentException('El correo no puede tener más de 150 caracteres.');
    }
    if (mb_strlen($ciudad) > 80) {
        throw new InvalidArgumentException('La ciudad no puede tener más de 80 caracteres.');
    }
    if ($sexo !== '' && !in_array($sexo, ['Femenino', 'Masculino', 'Otro'], true)) {
        throw new InvalidArgumentException('Elige el sexo de la lista.');
    }
    foreach (['objetivo' => $objetivo, 'condiciones de salud' => $condiciones, 'notas' => $notas] as $etiqueta => $valor) {
        if (mb_strlen($valor) > 5000) {
            throw new InvalidArgumentException("El campo $etiqueta no puede tener más de 5.000 caracteres.");
        }
    }

    if ($fecha_nacimiento !== '') {
        $fecha = DateTime::createFromFormat('!Y-m-d', $fecha_nacimiento);
        if (!$fecha || $fecha->format('Y-m-d') !== $fecha_nacimiento) {
            throw new InvalidArgumentException('La fecha de nacimiento no es válida.');
        }
        if ($fecha_nacimiento > hoy()) {
            throw new InvalidArgumentException('La fecha de nacimiento no puede ser futura.');
        }
    }

    $estatura = null;
    $estatura_texto = $texto('estatura_cm');
    if ($estatura_texto !== '') {
        $estatura = numero_entrada($estatura_texto);
        if ($estatura === null) {
            throw new InvalidArgumentException('La estatura debe ser un número en centímetros, por ejemplo 170.');
        }
        if ($estatura < 30 || $estatura > 300) {
            throw new InvalidArgumentException('La estatura debe estar entre 30 y 300 centímetros.');
        }
    }

    if ($exigir_consentimiento && empty($datos['consentimiento'])) {
        throw new InvalidArgumentException(
            'Marca la casilla para confirmar que el cliente autorizó el tratamiento de sus datos personales y de salud.'
        );
    }

    return [
        'nombre'           => $nombre,
        'telefono'         => $telefono,
        'correo'           => $correo !== '' ? $correo : null,
        'ciudad'           => $ciudad !== '' ? $ciudad : null,
        'fecha_nacimiento' => $fecha_nacimiento !== '' ? $fecha_nacimiento : null,
        'sexo'             => $sexo !== '' ? $sexo : null,
        'estatura_cm'      => $estatura,
        'objetivo'         => $objetivo !== '' ? $objetivo : null,
        'condiciones'      => $condiciones !== '' ? $condiciones : null,
        'notas'            => $notas !== '' ? $notas : null,
    ];
}

/**
 * Crea un cliente. Exige nombre, teléfono y consentimiento.
 * Devuelve el id del cliente nuevo.
 */
function cliente_crear(array $datos): int
{
    $limpio = cliente_validar($datos, true);

    $duplicado = cliente_por_telefono($limpio['telefono']);
    if ($duplicado) {
        throw new InvalidArgumentException(sprintf(
            'El teléfono %s ya está registrado a nombre de %s. Abre su ficha para editarlo.',
            $limpio['telefono'],
            $duplicado['nombre']
        ));
    }

    $u = usuario_actual();
    $momento = ahora();
    $columnas = ['nombre', 'telefono', 'correo', 'ciudad', 'fecha_nacimiento', 'sexo', 'estatura_cm',
                 'objetivo', 'condiciones', 'notas'];
    $valores = [];
    foreach ($columnas as $columna) {
        $valores[] = $limpio[$columna];
    }
    $valores[] = $momento;                 // consentimiento_fecha
    $valores[] = $u['id'] ?? null;         // asesor_id
    $valores[] = $momento;                 // creado
    $valores[] = $momento;                 // actualizado

    $sql = 'INSERT INTO clientes (' . implode(', ', $columnas)
        . ', consentimiento_fecha, asesor_id, activo, creado, actualizado)'
        . ' VALUES (' . implode(', ', array_fill(0, count($columnas), '?')) . ', ?, ?, 1, ?, ?)';

    db()->beginTransaction();
    try {
        db()->prepare($sql)->execute($valores);
        $id = (int) db()->lastInsertId();
        registrar_evento($id, 'cliente', 'Se creó el cliente.');
        db()->commit();
    } catch (Throwable $ex) {
        db()->rollBack();
        throw $ex;
    }

    return $id;
}

/** Actualiza los datos de un cliente. Los campos que no llegan se dejan como están. */
function cliente_actualizar(int $id, array $datos): void
{
    $actual = cliente_obtener($id);
    if (!$actual) {
        throw new InvalidArgumentException('El cliente no existe.');
    }

    $datos = array_merge($actual, $datos);
    $limpio = cliente_validar($datos, false);

    $duplicado = cliente_por_telefono($limpio['telefono'], $id);
    if ($duplicado) {
        throw new InvalidArgumentException(sprintf(
            'El teléfono %s ya está registrado a nombre de %s. Abre su ficha para editarlo.',
            $limpio['telefono'],
            $duplicado['nombre']
        ));
    }

    $cambios = [];
    $etiquetas = [
        'nombre'           => 'nombre',
        'telefono'         => 'teléfono',
        'correo'           => 'correo',
        'ciudad'           => 'ciudad',
        'fecha_nacimiento' => 'fecha de nacimiento',
        'sexo'             => 'sexo',
        'estatura_cm'      => 'estatura',
        'objetivo'         => 'objetivo',
        'condiciones'      => 'condiciones de salud',
        'notas'            => 'notas',
    ];
    foreach ($etiquetas as $columna => $etiqueta) {
        $antes = $actual[$columna] === null ? '' : (string) $actual[$columna];
        $despues = $limpio[$columna] === null ? '' : (string) $limpio[$columna];
        if ($columna === 'estatura_cm') {
            $antes = $antes === '' ? '' : numero_corto($antes);
            $despues = $despues === '' ? '' : numero_corto($despues);
        }
        if ($antes !== $despues) {
            $cambios[] = $etiqueta;
        }
    }

    $consentimiento_fecha = $actual['consentimiento_fecha'] ?? null;
    $registro_consentimiento = false;
    if (empty($consentimiento_fecha) && !empty($datos['consentimiento'])) {
        $consentimiento_fecha = ahora();
        $registro_consentimiento = true;
    }

    $columnas = ['nombre', 'telefono', 'correo', 'ciudad', 'fecha_nacimiento', 'sexo', 'estatura_cm',
                 'objetivo', 'condiciones', 'notas'];
    $asignaciones = [];
    $valores = [];
    foreach ($columnas as $columna) {
        $asignaciones[] = "$columna = ?";
        $valores[] = $limpio[$columna];
    }
    $asignaciones[] = 'consentimiento_fecha = ?';
    $valores[] = $consentimiento_fecha;
    $asignaciones[] = 'actualizado = ?';
    $valores[] = ahora();
    $valores[] = $id;

    db()->beginTransaction();
    try {
        db()->prepare('UPDATE clientes SET ' . implode(', ', $asignaciones) . ' WHERE id = ?')
            ->execute($valores);

        if ($registro_consentimiento) {
            registrar_evento($id, 'cliente', 'Se registró la autorización para el tratamiento de sus datos personales y de salud.');
        }
        if ($cambios) {
            registrar_evento($id, 'cliente', 'Se actualizaron los datos: ' . implode(', ', $cambios) . '.');
        } elseif (!$registro_consentimiento) {
            registrar_evento($id, 'cliente', 'Se guardó la ficha sin cambios.');
        }
        db()->commit();
    } catch (Throwable $ex) {
        db()->rollBack();
        throw $ex;
    }
}

/** Devuelve la fila del cliente o null si no existe. */
function cliente_obtener(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $st = db()->prepare('SELECT * FROM clientes WHERE id = ?');
    $st->execute([$id]);
    $fila = $st->fetch();
    return $fila ?: null;
}

/**
 * Clientes activos, ordenados por nombre. Con $q busca por nombre o por teléfono;
 * el teléfono se compara solo por sus dígitos ("311 503" encuentra "3115034356").
 */
function clientes_buscar(string $q): array
{
    $filas = db()->query(
        "SELECT c.*,
                (SELECT COUNT(*) FROM casos k WHERE k.cliente_id = c.id AND k.estado = 'activo') AS casos_activos
         FROM clientes c
         WHERE c.activo = 1"
    )->fetchAll();

    usort($filas, static fn(array $a, array $b): int => strnatcasecmp((string) $a['nombre'], (string) $b['nombre']));

    $q = trim($q);
    if ($q === '') {
        return $filas;
    }

    $texto = cliente_texto_comparable($q);
    $digitos = cliente_telefono_comparable($q);

    $resultado = [];
    foreach ($filas as $fila) {
        $nombre = cliente_texto_comparable((string) $fila['nombre']);
        $coincide_nombre = str_contains($nombre, $texto);
        $coincide_telefono = $digitos !== ''
            && str_contains(cliente_telefono_comparable((string) $fila['telefono']), $digitos);
        if ($coincide_nombre || $coincide_telefono) {
            $resultado[] = $fila;
        }
    }
    return $resultado;
}

/** Casos de un cliente, del más reciente al más viejo. */
function cliente_casos(int $cliente_id): array
{
    $st = db()->prepare('SELECT * FROM casos WHERE cliente_id = ? ORDER BY fecha_inicio DESC, id DESC');
    $st->execute([$cliente_id]);
    return $st->fetchAll();
}

/** Historial del cliente: lo más nuevo primero, con el nombre de quien lo anotó. */
function cliente_eventos(int $cliente_id): array
{
    $st = db()->prepare(
        'SELECT e.*, u.nombre AS nombre_usuario
         FROM eventos e
         LEFT JOIN usuarios u ON u.id = e.usuario_id
         WHERE e.cliente_id = ?
         ORDER BY e.fecha DESC, e.id DESC'
    );
    $st->execute([$cliente_id]);
    return $st->fetchAll();
}
