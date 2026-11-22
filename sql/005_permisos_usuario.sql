-- Permisos por usuario (T7). Si usuarios.permisos_propios = 0, el usuario usa los permisos de su rol
-- (PERMISOS en includes/funciones.php). Si es 1, solo puede lo que tenga en usuario_permisos.
-- El administrador siempre puede todo.

ALTER TABLE usuarios ADD COLUMN permisos_propios TINYINT NOT NULL DEFAULT 0;

CREATE TABLE usuario_permisos (
    usuario_id INTEGER NOT NULL REFERENCES usuarios(id),
    permiso VARCHAR(30) NOT NULL,
    PRIMARY KEY (usuario_id, permiso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
