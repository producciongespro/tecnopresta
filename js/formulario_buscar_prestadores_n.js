let codigoPresupuestario;

window.onload = function() {

  let boologin = login();

  if (boologin==false) {

    let contenedorError = document.getElementById("mensaje");

    contenedorError.innerHTML='<div class="alert alert-danger">' +
                                    '<strong>Error! </strong>' +
                                        'No ha iniciado sesión ...' +
                                    '</div>';
    return false;
  }

  cargaPrestadores();

  return false;

}

/* function getModuleParams() {
  let params = new URLSearchParams(window.location.search);
  let sid = params.get('subsistema_id');
  let mid = params.get('modulo_id');
  if (sid && mid) {
    return '&subsistema_id=' + sid + '&modulo_id=' + mid;
  }
  return '';
} */

function cuentaArticulosPendientes() {
  let total = 0;

  ['sesionCanasta', 'sesionActivo'].forEach(function(clave) {
    try {
      let jsonData = JSON.parse(window.sessionStorage.getItem(clave));
      if (Array.isArray(jsonData)) {
        total += jsonData.length;
      }
    } catch (e) {
      // JSON inválido se trata como solicitud vacía
    }
  });

  return total;
}

function vaciaSolicitudEnCurso() {
  window.sessionStorage.removeItem('sesionCanasta');
  window.sessionStorage.removeItem('sesionActivo');
  window.sessionStorage.removeItem('postSolicitud');
  window.sessionStorage.removeItem('postSolicitudActivo');
  window.sessionStorage.removeItem('solicitud_detalle');
}

function guardaPrestadorYNavega(obj) {
  window.sessionStorage.setItem('ambitoPrestadorSeleccionado', obj.id_ambito);
  window.sessionStorage.setItem('ambitoPrestadorNombre', obj.nombre);
  window.location.href = "formulario_buscar_alias_n.php";
}

function seleccionarPrestador(obj) {

  let ambitoActual = window.sessionStorage.getItem('ambitoPrestadorSeleccionado');

  // Mismo prestador: se conserva la solicitud en curso
  if (ambitoActual !== null && String(obj.id_ambito) === ambitoActual) {
    guardaPrestadorYNavega(obj);
    return false;
  }

  let pendientes = cuentaArticulosPendientes();

  if (pendientes === 0) {
    guardaPrestadorYNavega(obj);
    return false;
  }

  // Una solicitud no puede mezclar artículos de distintos prestadores
  let mensajeCambio = 'Tu carrito tiene artículos. Para cambiar de prestador es necesario vaciarlo.';

  // Respaldo si SweetAlert2 no cargó
  if (typeof Swal === 'undefined') {
    if (window.confirm(mensajeCambio)) {
      vaciaSolicitudEnCurso();
      guardaPrestadorYNavega(obj);
    }
    return false;
  }

  Swal.fire({
    icon: 'warning',
    title: '¿Vaciar el carrito?',
    text: mensajeCambio,
    showCancelButton: true,
    confirmButtonText: 'Vaciar carrito',
    cancelButtonText: 'Cancelar',
    reverseButtons: true
  }).then(function(result) {
    if (result.isConfirmed) {
      vaciaSolicitudEnCurso();
      guardaPrestadorYNavega(obj);
    }
  });

  return false;
}

function cargaDatosPantallaPrestadores(rs) {
  rs.forEach(obj => {
    let colCard = document.createElement('div');
    colCard.className = "w-100";
    colCard.style.maxWidth = "420px";

    let card = document.createElement('div');
    card.className = "card h-100 card-prestador";
    card.onclick = function () {
      seleccionarPrestador(obj);
    };

    let cardbody = document.createElement('div');
    cardbody.className = "card-body d-flex flex-column align-items-center justify-content-center text-center";

    let h4 = document.createElement('h4');
    h4.className = "card-title fw-semibold fs-2 mb-0";
    let createATextNombre = document.createTextNode(obj.nombre);
    h4.appendChild(createATextNombre);

    cardbody.appendChild(h4);
    card.appendChild(cardbody);
    colCard.appendChild(card);

    document.getElementById('fila').appendChild(colCard);
  });

  return false;
}

function cargaPrestadores() {

  $('#fila').empty();

  fetch('sql_n/selectPrestadoresGestor.php?'
  + new URLSearchParams({codigo: codigoPresupuestario}))
  .then(function(response) {

    if(response.ok) {

      response.json().then(function(data) {

        if (Object.keys(data).length>0) {

          cargaDatosPantallaPrestadores(data);

        } else {

          let contenedorError = document.getElementById("mensaje");
          contenedorError.innerHTML='<div class="aviso-sin-prestadores">' +
                                  '<div class="alert alert-danger d-flex align-items-center gap-2 mb-3">' +
                                      '<i class="bi bi-exclamation-triangle-fill fs-5"></i>' +
                                      '<div><strong>Aviso! </strong>' +
                                      'No se han establecido prestadores, comuníquese con el Director(a) del Centro Educativo</div>' +
                                  '</div>' +
                                  '<a href="guia_asignar_prestador_ambitos.php" class="guia-cta">' +
                                      '<span class="guia-cta-icono"><i class="bi bi-lightbulb-fill"></i></span>' +
                                      '<span class="guia-cta-texto">' +
                                          '<span class="guia-cta-etiqueta">Guía paso a paso</span>' +
                                          '<span>Para conocer cómo habilitar un funcionario para prestar activos tecnológicos, <strong>haga click aquí</strong></span>' +
                                      '</span>' +
                                      '<i class="bi bi-arrow-right-circle-fill guia-cta-flecha"></i>' +
                                  '</a>' +
                                  '</div>';
        }

      }).catch(function(error) {

                  let contenedorError = document.getElementById("mensaje");
                  contenedorError.innerHTML='<div class="alert alert-danger">' +
                                          '<strong>Error! </strong>' +
                                          'No hay respuesta del servidor MEP. Verifique su conexión de internet ' + error.message +
                                          '</div>';
            });

    } else {

            let contenedorError = document.getElementById("mensaje");
            contenedorError.innerHTML='<div class="alert alert-danger">' +
                                    '<strong>Error! </strong>' +
                                        'No se pudo conectar con el servidor. Intente de nuevo.' +
                                    '</div>';
    }

  }).catch(function(error) {

          let contenedorError = document.getElementById("mensaje");
          contenedorError.innerHTML='<div class="alert alert-danger">' +
                                  '<strong>Error! </strong>' +
                                      'Hubo un problema al conectar con el servidor: ' + error.message +
                                  '</div>';
  }).then();

  return false;

}

function login() {

  window.userData = [];
  userData = window.sessionStorage.getItem('sesion');

  if (userData && userData.length>0) {

    let jsonData = [];

    jsonData = JSON.parse(userData);

    codigoPresupuestario = jsonData["CentrosEducativosDondeTrabaja"];

  } else {

    return false;

  }

  return true;

}
