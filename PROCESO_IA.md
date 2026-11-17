# Proceso con IA

Armé esta solución de referencia en Cursor, con Grok 4.7, a partir del enunciado de RedEMC. La IA escribió el primer corte (formulario, validación, Ajax, esquema y Dockerfile). Yo contrasté cada regla con el enunciado y tiré lo que lo aflojaba. El código que quedó es PHP plano, con PDO y consultas preparadas, sin framework.

## Prompts que importaron

1. El enunciado completo: nombre, email y teléfono opcional; regex en cliente y servidor; email único sin importar mayúsculas; celular uruguayo de 9 dígitos que empiece con 09, sin espacios, guiones ni +598; errores puntuales sin borrar los otros campos; alta por Ajax con fecha de inserción y el registro nuevo primero; listado de a 8, sin `<table>`, con paginado por Ajax.
2. «Dejalo listo para Railway: leé `MYSQLHOST`, `MYSQLPORT`, `MYSQLUSER`, `MYSQLPASSWORD` y `MYSQLDATABASE`, creá la tabla si no existe, y un Dockerfile `php-apache` con `pdo_mysql` que escuche en `PORT`.»
3. «No normalices el teléfono. Si viene con espacios, guiones o +598, el regex tiene que fallar y el mensaje tiene que decirlo, sin borrar lo que ya estaba escrito en los otros campos. El email duplicado se detecta aunque cambien las mayúsculas.»

## Qué se propuso y no quedó

- Validar el email con `filter_var`. Es el atajo habitual y el enunciado pide regex, el mismo en el navegador y en PHP.
- Borrar espacios y guiones del teléfono antes de validar, para aceptar lo que la gente pega. Eso escondía los casos que la prueba quiere rechazar.
- Poner `maxlength="9"` en el input. Un `+598…` quedaba cortado y el error dejaba de entenderse. El tope de 9 dígitos lo impone el regex; el input deja escribir el prefijo de más para poder marcarlo.
- Dibujar el listado con `<table>`. El enunciado lo prohíbe. Son divs, y Bootstrap se usa para la grilla.
- Guardar el teléfono como `INT`. Se perdía el 0 inicial. Quedó `VARCHAR(9)` y, si no hay teléfono, `NULL`: un UNIQUE de MySQL permite varios NULL, y un string vacío no.
- Sumar `docker-compose` y un README para levantarlo en la máquina. Se sacaron: el README cuenta solo el deploy en Railway.

## Qué aprendí

El UNIQUE no adivina la regla. El email hay que guardarlo ya en minúsculas para que `Ana@Mail.com` y `ana@mail.com` sean la misma fila y el índice los frene, incluso si dos altas pasan juntas. En el teléfono es al revés: la ausencia tiene que ser `NULL`, no `''`, si no el segundo contacto sin teléfono rompe el UNIQUE. Y en Railway, por más que la imagen sea la de Apache, si el proceso no escucha en `$PORT` el dominio nunca abre.
