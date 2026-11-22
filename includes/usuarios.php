<?php

function usuarios_listar(): array
{
    return db()->query('SELECT id, nombre, usuario, rol, activo, creado, permisos_propios FROM usuarios ORDER BY nombre, id')->fetchAll();
}

function usuario_obtener(int $id): ?array
{
    $st = db()->prepare('SELECT id, nombre, usuario, rol, activo, creado, permisos_propios FROM usuarios WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function usuario_hash_clave(string $clave): string
{
    if (mb_strlen($clave) < 8) {
        throw new InvalidArgumentException('La contraseña debe tener al menos 8 caracteres.');
    }
    if (strlen($clave) > 72 || str_contains($clave, "\0")) {
        throw new InvalidArgumentException('Usa una contraseña de máximo 72 bytes y sin caracteres nulos.');
    }
    return password_hash($clave, PASSWORD_DEFAULT);
}

function usuario_guardar(array $datos): int
{
    $id = (int) ($datos['id'] ?? 0);
    $nombre = trim($datos['nombre'] ?? '');
    $usuario = mb_strtolower(trim($datos['usuario'] ?? ''));
    $rol = $datos['rol'] ?? '';
    $activo = (int) !empty($datos['activo']);
    $clave = $datos['clave'] ?? '';

    if ($nombre === '' || mb_strlen($nombre) > 100) {
        throw new InvalidArgumentException('Escribe un nombre de máximo 100 caracteres.');
    }
    if ($usuario === '' || mb_strlen($usuario) > 60 || preg_match('/\s/u', $usuario)) {
        throw new InvalidArgumentException('Escribe un usuario de máximo 60 caracteres, sin espacios.');
    }
    if (!isset(ROLES[$rol])) {
        throw new InvalidArgumentException('Selecciona un rol válido.');
    }
    if ($id && !$activo && $id === (int) (usuario_actual()['id'] ?? 0)) {
        throw new InvalidArgumentException('No puedes desactivar tu propia cuenta.');
    }
    $hash = !$id || $clave !== '' ? usuario_hash_clave($clave) : null;

    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($id) {
            $sqlite = es_sqlite($pdo);
            if ($sqlite) {
                // SQLite reserva la escritura antes de consultar los administradores activos.
                $pdo->prepare('UPDATE usuarios SET activo = activo WHERE id = ?')->execute([$id]);
            }
            $st = $pdo->prepare('SELECT id FROM usuarios WHERE rol = ? AND activo = 1 ORDER BY id' . ($sqlite ? '' : ' FOR UPDATE'));
            $st->execute(['admin']);
            $admins = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
            if ($admins === [$id] && (!$activo || $rol !== 'admin')) {
                throw new InvalidArgumentException('Debe quedar al menos un administrador activo.');
            }
            if (!usuario_obtener($id)) {
                throw new InvalidArgumentException('Usuario no encontrado.');
            }
            $pdo->prepare('UPDATE usuarios SET nombre = ?, usuario = ?, rol = ?, activo = ?, clave_hash = COALESCE(?, clave_hash) WHERE id = ?')
                ->execute([$nombre, $usuario, $rol, $activo, $hash, $id]);
        } else {
            $pdo->prepare('INSERT INTO usuarios (nombre, usuario, rol, activo, clave_hash, creado) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$nombre, $usuario, $rol, $activo, $hash, ahora()]);
            $id = (int) $pdo->lastInsertId();
        }
        $pdo->commit();
        return $id;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($ex instanceof PDOException && (string) $ex->getCode() === '23000') {
            throw new InvalidArgumentException('Ese usuario ya existe.', 0, $ex);
        }
        throw $ex;
    }
}

function usuario_guardar_permisos(int $id, bool $propios, array $permisos): void
{
    foreach ($permisos as $permiso) {
        if (!is_string($permiso) || !isset(PERMISOS_NOMBRES[$permiso])) {
            throw new InvalidArgumentException('Selecciona únicamente permisos válidos.');
        }
    }
    if (!usuario_obtener($id)) {
        throw new InvalidArgumentException('Usuario no encontrado.');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE usuarios SET permisos_propios = ? WHERE id = ?')->execute([(int) $propios, $id]);
        $pdo->prepare('DELETE FROM usuario_permisos WHERE usuario_id = ?')->execute([$id]);
        if ($propios) {
            $st = $pdo->prepare('INSERT INTO usuario_permisos (usuario_id, permiso) VALUES (?, ?)');
            foreach (array_unique($permisos) as $permiso) {
                $st->execute([$id, $permiso]);
            }
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

function usuario_cambiar_clave(int $id, string $clave): void
{
    if (!usuario_obtener($id)) {
        throw new InvalidArgumentException('Usuario no encontrado.');
    }
    db()->prepare('UPDATE usuarios SET clave_hash = ? WHERE id = ?')->execute([usuario_hash_clave($clave), $id]);
}
