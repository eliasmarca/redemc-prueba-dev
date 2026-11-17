-- Estructura de la tabla de contactos.
-- La aplicación ejecuta este archivo si la tabla todavía no existe.
--
-- email se guarda siempre en minúsculas: el UNIQUE alcanza para que
-- Ana@Mail.com y ana@mail.com no puedan coexistir.
-- telefono es VARCHAR y no INT, para no perder el 0 inicial.
-- NULL significa "no se cargó". MySQL permite varios NULL en un UNIQUE,
-- así el teléfono opcional no choca consigo mismo. Un string vacío no serviría.

CREATE TABLE IF NOT EXISTS contactos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(120) NOT NULL,
    email VARCHAR(254) NOT NULL,
    telefono VARCHAR(9) NULL DEFAULT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_contactos_email (email),
    UNIQUE KEY uq_contactos_telefono (telefono),
    KEY idx_contactos_creado (creado_en, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
