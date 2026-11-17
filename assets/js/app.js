/* Registro de contactos.
   Los regex y los mensajes tienen que coincidir con includes/validacion.php.
   Esto avisa antes de ir al servidor. El servidor igual vuelve a validar. */
(function ($) {
    'use strict';

    var RX_EMAIL = /^[A-Za-z0-9_%+-]+(?:\.[A-Za-z0-9_%+-]+)*@[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?)*\.[A-Za-z]{2,}$/;
    var RX_TELEFONO = /^09[0-9]{7}$/;
    var RX_NOMBRE = compilarNombre();

    var base = $('meta[name="app-base"]').attr('content') || '';
    var csrf = $('meta[name="csrf-token"]').attr('content') || '';
    var paginaActual = 1;
    var formularioIntentado = false;
    var guardando = false;

    function compilarNombre() {
        try {
            var rx = new RegExp('^[\\p{L} ]+$', 'u');
            if (rx.test('José') && !rx.test('Ana2')) {
                return rx;
            }
        } catch (error) {
            // Algún navegador viejo no entiende \p{L}. El servidor igual usa Unicode.
        }
        return /^[A-Za-z\u00C0-\u00D6\u00D8-\u00F6\u00F8-\u00FF ]+$/;
    }

    // Mismo recorte que trim() de PHP: no borra un espacio raro del medio.
    function recortar(valor) {
        return String(valor).replace(/^[ \t\n\r\v\0]+|[ \t\n\r\v\0]+$/g, '');
    }

    function valorCampo(selector) {
        var valor = $(selector).val();
        return typeof valor === 'string' ? valor : '';
    }

    function validarCampos(valores) {
        var errores = {};
        var nombre = recortar(valores.nombre);
        var email = recortar(valores.email);
        var telefono = recortar(valores.telefono);

        if (nombre === '') {
            errores.nombre = 'El nombre es obligatorio.';
        } else if (!RX_NOMBRE.test(nombre)) {
            errores.nombre = 'El nombre solo puede contener letras y espacios (se permiten tildes y ñ).';
        } else if (Array.from(nombre).length > 120) {
            errores.nombre = 'El nombre no puede superar los 120 caracteres.';
        }

        if (email === '') {
            errores.email = 'El email es obligatorio.';
        } else if (email.length > 254 || !RX_EMAIL.test(email)) {
            errores.email = 'Ingresá un email válido. Ejemplo: nombre@dominio.com.';
        }

        if (telefono !== '' && !RX_TELEFONO.test(telefono)) {
            errores.telefono = 'Si completás el teléfono, tiene que ser un celular uruguayo: 9 dígitos, empezar con 09, sin espacios, guiones ni +598. Ejemplo: 097680102.';
        }

        return errores;
    }

    function campoDe(nombre) {
        return $('.campo').filter(function () {
            return $(this).attr('data-campo') === nombre;
        });
    }

    function mostrarUno(nombre, mensaje) {
        var $campo = campoDe(nombre);
        $campo.addClass('campo--invalido');
        $campo.find('.campo__input').attr('aria-invalid', 'true');
        $campo.find('.campo__error').removeAttr('hidden').text(mensaje);
    }

    function limpiarUno(nombre) {
        var $campo = campoDe(nombre);
        $campo.removeClass('campo--invalido');
        $campo.find('.campo__input').attr('aria-invalid', 'false');
        $campo.find('.campo__error').attr('hidden', 'hidden').empty();
    }

    function limpiarErrores() {
        ['nombre', 'email', 'telefono'].forEach(limpiarUno);
    }

    function aplicarErrores(errores, enfocar) {
        limpiarErrores();
        var $primero = null;
        $.each(errores, function (nombre, mensaje) {
            if (!mensaje) {
                return;
            }
            var $input = campoDe(nombre).find('.campo__input');
            if (!$input.length) {
                return;
            }
            mostrarUno(nombre, mensaje);
            if (!$primero) {
                $primero = $input;
            }
        });
        if (enfocar && $primero) {
            $primero.trigger('focus');
        }
    }

    function mostrarAviso(tipo, mensaje) {
        $('#aviso')
            .removeClass('aviso--ok aviso--error')
            .addClass(tipo === 'ok' ? 'aviso--ok' : 'aviso--error')
            .attr('role', tipo === 'ok' ? 'status' : 'alert')
            .removeAttr('hidden')
            .text(mensaje);
    }

    function ocultarAviso() {
        $('#aviso').attr('hidden', 'hidden').removeClass('aviso--ok aviso--error').empty();
    }

    function formatearTelefono(telefono) {
        return telefono.slice(0, 3) + ' ' + telefono.slice(3, 6) + ' ' + telefono.slice(6);
    }

    function crearContacto(contacto) {
        var $art = $('<article>', { 'class': 'contacto' });
        $art.attr('data-id', String(parseInt(contacto.id, 10)));

        var $cabecera = $('<div>', { 'class': 'contacto__cabecera' });
        $cabecera.append($('<h3>', { 'class': 'contacto__nombre', text: contacto.nombre }));
        $cabecera.append($('<time>', {
            'class': 'contacto__fecha',
            datetime: contacto.creado_en,
            text: contacto.creado_en_legible
        }));

        var $datos = $('<div>', { 'class': 'contacto__datos' });
        if (contacto.email && RX_EMAIL.test(contacto.email)) {
            $datos.append($('<a>', {
                'class': 'contacto__email',
                href: 'mailto:' + contacto.email,
                text: contacto.email
            }));
        } else {
            $datos.append($('<p>', { 'class': 'contacto__email', text: contacto.email || '' }));
        }

        if (contacto.telefono && RX_TELEFONO.test(contacto.telefono)) {
            $datos.append($('<a>', {
                'class': 'contacto__telefono',
                href: 'tel:' + contacto.telefono,
                text: formatearTelefono(contacto.telefono)
            }));
        } else if (contacto.telefono) {
            $datos.append($('<p>', { 'class': 'contacto__telefono', text: contacto.telefono }));
        } else {
            $datos.append($('<p>', { 'class': 'contacto__telefono contacto__vacio', text: 'Sin teléfono' }));
        }

        $art.append($cabecera, $datos);
        return $art;
    }

    function estadoVacio() {
        return $('<div>', { 'class': 'vacio' })
            .append($('<div>', { 'class': 'vacio__marca', 'aria-hidden': 'true' }))
            .append($('<p>', { 'class': 'vacio__titulo', text: 'Todavía no hay contactos' }))
            .append($('<p>', { 'class': 'vacio__texto', text: 'Cuando guardes uno, va a quedar primero en esta lista.' }));
    }

    function estadoError(mensaje) {
        return $('<div>', { 'class': 'estado-error' })
            .append($('<p>', { 'class': 'estado-error__titulo', text: 'No se pudo cargar el listado' }))
            .append($('<p>', { 'class': 'estado-error__texto', text: mensaje }))
            .append($('<button>', {
                type: 'button',
                'class': 'boton boton--secundario js-reintentar',
                text: 'Reintentar'
            }));
    }

    function textoResumen(data) {
        var total = data.total;
        var frase = total === 1 ? '1 persona' : total + ' personas';
        if (total <= data.por_pagina) {
            return frase + '.';
        }
        var desde = (data.pagina - 1) * data.por_pagina + 1;
        var hasta = desde + data.contactos.length - 1;
        return frase + ' · se ven ' + desde + '–' + hasta + '.';
    }

    function pintarPaginacion(data) {
        var $nav = $('#paginacion');
        if (!data || data.total <= data.por_pagina) {
            $nav.attr('hidden', 'hidden');
            return;
        }
        $nav.removeAttr('hidden');
        $('#pagina-estado').text('Página ' + data.pagina + ' de ' + data.paginas);
        $('#pagina-anterior').prop('disabled', data.pagina <= 1);
        $('#pagina-siguiente').prop('disabled', data.pagina >= data.paginas);
    }

    function pintarListado(data, idDestacado) {
        paginaActual = data.pagina;
        var $lista = $('#listado');
        $lista.empty();
        $('#zona-listado').attr('aria-busy', 'false');

        if (!data.contactos || data.contactos.length === 0) {
            $('#listado-resumen').text('Todavía no hay personas cargadas.');
            $lista.append(estadoVacio());
            pintarPaginacion(data);
            return;
        }

        $('#listado-resumen').text(textoResumen(data));
        data.contactos.forEach(function (contacto) {
            var $item = crearContacto(contacto);
            if (idDestacado !== null && idDestacado !== undefined && String(contacto.id) === String(idDestacado)) {
                $item.addClass('contacto--nuevo');
            }
            $lista.append($item);
        });
        pintarPaginacion(data);

        var $nuevo = $lista.children('.contacto--nuevo').first();
        if ($nuevo.length && $nuevo.get(0).scrollIntoView) {
            $nuevo.get(0).scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    }

    function ponerCargando() {
        var $lista = $('#listado');
        $lista.empty();
        $('#zona-listado').attr('aria-busy', 'true');
        $('#listado-resumen').text('Cargando contactos…');
        $('#pagina-anterior, #pagina-siguiente').prop('disabled', true);
        var i;
        for (i = 0; i < 3; i += 1) {
            $lista.append(
                $('<div>', { 'class': 'esqueleto', 'aria-hidden': 'true' })
                    .append($('<div>', { 'class': 'esqueleto__linea esqueleto__linea--ancha' }))
                    .append($('<div>', { 'class': 'esqueleto__linea' }))
            );
        }
    }

    function cargarPagina(pagina, idDestacado, contactoRespaldo, intento) {
        intento = intento || 1;
        ponerCargando();

        $.ajax({
            url: base + '/api/listar.php?pagina=' + encodeURIComponent(pagina),
            method: 'GET',
            dataType: 'json',
            cache: false,
            timeout: 15000
        }).done(function (respuesta) {
            if (!respuesta || !respuesta.ok) {
                pintarFallo((respuesta && respuesta.mensaje) || 'No se pudo cargar el listado.', contactoRespaldo);
                return;
            }
            pintarListado(respuesta, idDestacado);
        }).fail(function (xhr, estado) {
            var transitorio = estado === 'timeout' || !xhr.status || xhr.status >= 500;
            if (transitorio && intento < 2) {
                window.setTimeout(function () {
                    cargarPagina(pagina, idDestacado, contactoRespaldo, intento + 1);
                }, 700);
                return;
            }
            var cuerpo = xhr.responseJSON;
            var mensaje = estado === 'timeout'
                ? 'El servidor tardó demasiado. Intentá de nuevo.'
                : ((cuerpo && cuerpo.mensaje) || 'No se pudo cargar el listado.');
            pintarFallo(mensaje, contactoRespaldo);
        });
    }

    function pintarFallo(mensaje, contactoRespaldo) {
        $('#zona-listado').attr('aria-busy', 'false');
        if (contactoRespaldo) {
            $('#listado').empty().append(crearContacto(contactoRespaldo).addClass('contacto--nuevo'));
            $('#listado-resumen').text('Guardado. No se pudo actualizar el resto del listado.');
            $('#paginacion').attr('hidden', 'hidden');
            return;
        }
        $('#listado-resumen').text('No se pudo cargar el listado.');
        $('#listado').empty().append(estadoError(mensaje));
        $('#paginacion').attr('hidden', 'hidden');
    }

    function valoresFormulario() {
        return {
            nombre: valorCampo('#nombre'),
            email: valorCampo('#email'),
            telefono: valorCampo('#telefono')
        };
    }

    $('#form-contacto').on('submit', function (evento) {
        evento.preventDefault();
        if (guardando) {
            return;
        }

        formularioIntentado = true;
        ocultarAviso();

        var valores = valoresFormulario();
        var errores = validarCampos(valores);
        if (!$.isEmptyObject(errores)) {
            aplicarErrores(errores, true);
            mostrarAviso('error', 'Revisá los campos marcados.');
            return;
        }
        if (!csrf) {
            mostrarAviso('error', 'No se pudo validar la sesión. Recargá la página e intentá de nuevo.');
            return;
        }

        guardando = true;
        var $boton = $('#btn-guardar');
        $boton.prop('disabled', true).attr('aria-busy', 'true');
        $boton.find('.boton__texto').text('Guardando…');

        $.ajax({
            url: base + '/api/guardar.php',
            method: 'POST',
            contentType: 'application/json; charset=utf-8',
            dataType: 'json',
            cache: false,
            timeout: 15000,
            headers: { 'X-CSRF-Token': csrf },
            data: JSON.stringify({
                nombre: valores.nombre,
                email: valores.email,
                telefono: valores.telefono,
                csrf: csrf
            })
        }).done(function (respuesta) {
            if (!respuesta || !respuesta.ok) {
                if (respuesta && respuesta.errores) {
                    aplicarErrores(respuesta.errores, true);
                }
                mostrarAviso('error', (respuesta && respuesta.mensaje) || 'No se pudo guardar el contacto. Intentá de nuevo en unos segundos.');
                return;
            }
            this.reset();
            formularioIntentado = false;
            limpiarErrores();
            mostrarAviso('ok', respuesta.mensaje || 'Contacto guardado. Ya aparece primero en el listado.');
            cargarPagina(1, respuesta.contacto ? respuesta.contacto.id : null, respuesta.contacto || null);
        }.bind(this)).fail(function (xhr, estado) {
            if (estado === 'timeout') {
                mostrarAviso('error', 'El servidor tardó demasiado. Intentá de nuevo.');
                return;
            }
            var cuerpo = xhr.responseJSON;
            if (xhr.status === 422 && cuerpo && cuerpo.errores) {
                aplicarErrores(cuerpo.errores, true);
                mostrarAviso('error', cuerpo.mensaje || 'Revisá los campos marcados.');
                return;
            }
            mostrarAviso('error', (cuerpo && cuerpo.mensaje) || 'No hay conexión con el servidor. Revisá tu red e intentá otra vez.');
        }).always(function () {
            guardando = false;
            $boton.prop('disabled', false).attr('aria-busy', 'false');
            $boton.find('.boton__texto').text('Guardar contacto');
        });
    });

    $('.campo__input').on('input', function () {
        if (!formularioIntentado) {
            return;
        }
        var nombre = $(this).attr('name');
        var errores = validarCampos(valoresFormulario());
        if (errores[nombre]) {
            mostrarUno(nombre, errores[nombre]);
        } else {
            limpiarUno(nombre);
        }
        if ($.isEmptyObject(errores)) {
            ocultarAviso();
        }
    });

    $('#pagina-anterior').on('click', function () {
        if (paginaActual > 1) {
            cargarPagina(paginaActual - 1);
        }
    });

    $('#pagina-siguiente').on('click', function () {
        cargarPagina(paginaActual + 1);
    });

    $('#listado').on('click', '.js-reintentar', function () {
        cargarPagina(paginaActual);
    });

    $(function () {
        cargarPagina(1);
    });
}(jQuery));
