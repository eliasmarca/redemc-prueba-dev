<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/inicializar.php';

$base = e(base_web());
$csrf = e(token_csrf());
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es" translate="no">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="google" content="notranslate">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= $csrf ?>">
    <meta name="app-base" content="<?= $base ?>">
    <title>Registro de contactos · RedEMC</title>
    <link rel="icon" href="<?= $base ?>/assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="<?= $base ?>/assets/vendor/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $base ?>/assets/css/estilos.css">
</head>
<body>
    <a class="saltar" href="#form-contacto">Saltar al formulario</a>
    <div class="container pagina">
        <header class="cabecera">
            <p class="marca"><span class="marca__punto" aria-hidden="true"></span> RedEMC <span class="marca__sep" aria-hidden="true">/</span> Prueba técnica</p>
            <h1>Registro de contactos</h1>
            <p class="cabecera__texto">Dejá nombre y email. El teléfono es opcional y, si lo cargás, tiene que ser un celular de Uruguay.</p>
        </header>

        <!-- Maquetado con divs. Bootstrap arma la grilla; el listado no usa tablas. -->
        <div class="row g-4 align-items-start">
            <div class="col-12 col-lg-5">
                <section class="panel panel--alta" aria-labelledby="titulo-form">
                    <h2 id="titulo-form">Nuevo contacto</h2>
                    <p class="panel__ayuda">Los datos se revisan acá y otra vez en el servidor antes de guardarse.</p>

                    <form id="form-contacto" action="<?= $base ?>/api/guardar.php" method="post" novalidate>
                        <div id="aviso" class="aviso" hidden></div>

                        <div class="campo" data-campo="nombre">
                            <label class="campo__etiqueta" for="nombre">
                                Nombre
                                <span class="campo__marca campo__marca--si">Obligatorio</span>
                            </label>
                            <input class="campo__input" id="nombre" name="nombre" type="text" maxlength="120" autocomplete="name" autocapitalize="words" required aria-required="true" aria-describedby="nombre-ayuda nombre-error">
                            <p class="campo__ayuda" id="nombre-ayuda">Letras y espacios. Podés usar tildes y ñ.</p>
                            <p class="campo__error" id="nombre-error" hidden></p>
                        </div>

                        <div class="campo" data-campo="email">
                            <label class="campo__etiqueta" for="email">
                                Email
                                <span class="campo__marca campo__marca--si">Obligatorio</span>
                            </label>
                            <input class="campo__input" id="email" name="email" type="text" inputmode="email" maxlength="254" autocomplete="email" autocapitalize="none" autocorrect="off" spellcheck="false" required aria-required="true" aria-describedby="email-ayuda email-error">
                            <p class="campo__ayuda" id="email-ayuda">Tiene que ser único. No importa si lo escribís en mayúsculas.</p>
                            <p class="campo__error" id="email-error" hidden></p>
                        </div>

                        <div class="campo" data-campo="telefono">
                            <label class="campo__etiqueta" for="telefono">
                                Teléfono
                                <span class="campo__marca">Opcional</span>
                            </label>
                            <input class="campo__input" id="telefono" name="telefono" type="tel" inputmode="numeric" maxlength="24" autocomplete="tel" autocapitalize="none" autocorrect="off" spellcheck="false" aria-required="false" aria-describedby="telefono-ayuda telefono-error">
                            <p class="campo__ayuda" id="telefono-ayuda">9 dígitos, empieza con 09, sin espacios ni guiones. Ejemplo: 097680102.</p>
                            <p class="campo__error" id="telefono-error" hidden></p>
                        </div>

                        <div class="form__accion">
                            <button class="boton" type="submit" id="btn-guardar">
                                <span class="boton__texto">Guardar contacto</span>
                            </button>
                        </div>
                    </form>
                </section>
            </div>

            <div class="col-12 col-lg-7">
                <section class="panel" aria-labelledby="titulo-lista">
                    <h2 id="titulo-lista">Personas registradas</h2>
                    <p class="panel__ayuda">Los más nuevos quedan arriba. Se muestran de a 8.</p>

                    <div id="zona-listado" aria-live="polite" aria-busy="true">
                        <p id="listado-resumen" class="listado__resumen">Cargando contactos…</p>
                        <div id="listado" class="listado"></div>
                    </div>

                    <nav id="paginacion" class="paginacion" aria-label="Paginación del listado" hidden>
                        <button type="button" class="paginacion__btn" id="pagina-anterior">Anterior</button>
                        <p id="pagina-estado" class="paginacion__estado"></p>
                        <button type="button" class="paginacion__btn" id="pagina-siguiente">Siguiente</button>
                    </nav>
                </section>
            </div>
        </div>

        <footer class="pie">
            <p>El email se guarda en minúsculas. Si ya existe, aunque cambien las mayúsculas, no se vuelve a cargar.</p>
        </footer>
    </div>

    <noscript>
        <p class="aviso aviso--error aviso--fijo">Esta pantalla necesita JavaScript para guardar y para cargar el listado.</p>
    </noscript>

    <script src="<?= $base ?>/assets/vendor/jquery.min.js"></script>
    <script src="<?= $base ?>/assets/js/app.js"></script>
</body>
</html>
