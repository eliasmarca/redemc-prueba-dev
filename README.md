# Registro de contactos

Alta de contactos (nombre, email y teléfono opcional) con validación en el navegador y en el servidor, guardado por Ajax en MySQL y listado paginado de a 8.

## Deploy en Railway

Quedó armado como dos servicios en el mismo proyecto:

- **MySQL**, el plugin de base de Railway.
- **Web**, esta aplicación. Railway la construye con el `Dockerfile` de la raíz: PHP 8.3, Apache y la extensión `pdo_mysql`. No usa Nixpacks ni un comando de inicio aparte.

Railway inyecta `PORT`. `docker/entrypoint.sh` cambia el `Listen 80` de Apache por ese puerto y deja una línea en los logs: `Apache escucha en el puerto …`. Si Apache se queda en el 80, el dominio no responde.

La conexión no está escrita en el código. En cada request, `includes/db.php` lee:

| Variable | Qué es |
| --- | --- |
| `MYSQLHOST` | Host interno del MySQL |
| `MYSQLPORT` | Puerto |
| `MYSQLUSER` | Usuario |
| `MYSQLPASSWORD` | Contraseña |
| `MYSQLDATABASE` | Nombre de la base |

Esas cinco tienen que existir en el servicio **web**, como referencia al servicio MySQL. Si faltan, el código cae a un valor local (`127.0.0.1`, puerto `3306`, usuario `root`, sin contraseña, base `redemc`). En Railway ese respaldo no llega a la base.

La tabla `contactos` se crea sola la primera vez que la app se conecta, con el `CREATE TABLE` de `sql/contactos.sql`. No hace falta correr el `.sql` a mano. El archivo queda para revisarlo o para pegarlo en la pestaña Query de MySQL si se quiere crear la tabla antes.

### Pasos

1. Subir este repositorio a GitHub.
2. En [Railway](https://railway.com), **New Project** → **Deploy from GitHub repo** y elegir el repositorio. Railway detecta el `Dockerfile` y construye la imagen.
3. En el mismo proyecto, **New** → **Database** → **Add MySQL**. Esperar a que el servicio quede activo.
4. Abrir el servicio de la app (no el de MySQL) → **Variables** → **Add Reference** y crear estas cinco. El nombre `MySQL` es el del servicio de base; si en el proyecto se llama distinto, usar ese nombre:

   - `MYSQLHOST` = `${{MySQL.MYSQLHOST}}`
   - `MYSQLPORT` = `${{MySQL.MYSQLPORT}}`
   - `MYSQLUSER` = `${{MySQL.MYSQLUSER}}`
   - `MYSQLPASSWORD` = `${{MySQL.MYSQLPASSWORD}}`
   - `MYSQLDATABASE` = `${{MySQL.MYSQLDATABASE}}`

   Tienen que verse en el servicio web. Si solo existen dentro del servicio MySQL, la app no las recibe.
5. Dejar los dos servicios en el mismo proyecto. `MYSQLHOST` es un host privado (`*.railway.internal`) y solo resuelve ahí. No usar `MYSQL_PUBLIC_URL`: dentro de Railway la conexión va por la red interna, sin SSL.
6. Al guardar las variables, Railway vuelve a desplegar el servicio web. Esperar a que termine.
7. En el servicio web, **Settings → Networking → Generate Domain**.
8. Abrir el dominio. El primer alta o el primer listado crea la tabla. Un contacto válido aparece primero en el listado, sin recargar la página. Si hay más de 8, las páginas siguientes se piden por Ajax.

### Si la página abre y el listado no conecta

- Las cinco variables están en el servicio web y el nombre dentro de `${{...}}` coincide con el servicio de la base.
- El deploy del web terminó después de agregar las variables.
- En los logs del web aparece `Apache escucha en el puerto` seguido del `PORT` de Railway.
- La tabla, si se quiere mirar, está en la base `MYSQLDATABASE`. La estructura es `sql/contactos.sql`.

Piezas de ese deploy: `Dockerfile`, `docker/entrypoint.sh`, `docker/apache.conf`, `docker/php.ini`, `railway.toml`, `includes/db.php` y `sql/contactos.sql`.
