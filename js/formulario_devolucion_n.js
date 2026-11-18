let prestamo_email_solicitante = "";
let prestamo_nombre_solicitante = "";
let codigoPresupuestario = "";
let prestamo_Id = 0;
let solicitud_email_funcionario;

$('#chkIncidencia').on( 'change', function() {

  let txtIncidenciaRO = document.getElementById("txtIncidencia").readOnly;

  if (txtIncidenciaRO) {
    document.getElementById("txtIncidencia").readOnly = false;
  } else {
    document.getElementById("txtIncidencia").readOnly = true;
  }
});

function checkAll(source) {
  let chkIncidencia = document.getElementById("chkIncidencia");
  let checkboxes = document.querySelectorAll('input[type="checkbox"]');

  for (let i = 0; i < checkboxes.length; i++) {
      if (checkboxes[i] != source && checkboxes[i] != chkIncidencia)
          checkboxes[i].checked = source.checked;
  }
}

function mostrarModal(id) {
  bootstrap.Modal.getOrCreateInstance(document.getElementById(id)).show();
}

window.onload = function() {
  let boologin = login();

  if (boologin) {
    const queryString = window.location.search;
    const urlParams = new URLSearchParams(queryString);
    prestamo_Id = urlParams.get('prestamo_Id');

    cargaDatosBdPrestamoEncabezado();
    cargaDatosBd();
  } else {
    let contenedorError = document.getElementById("mensaje");
    contenedorError.innerHTML='<div class="alert alert-danger">' +
                                    '<strong>Error! </strong>' +
                                        'No ha iniciado sesión ...' +
                                    '</div>';
  }

  return false;
};

function validaFechas(prestamo_fechaRetiroY,
                      prestamo_fechaRetiroM,
                      prestamo_fechaRetiroD,
                      prestamo_fechaDevolucionY,
                      prestamo_fechaDevolucionM,
                      prestamo_fechaDevolucionD) {
  let fechaInicio = new Date(prestamo_fechaRetiroY, prestamo_fechaRetiroM, prestamo_fechaRetiroD);
  let fechaFin = new Date(prestamo_fechaDevolucionY, prestamo_fechaDevolucionM, prestamo_fechaDevolucionD);

  if (fechaInicio > fechaFin) {
    return true;
  }
  return false;
}

function isValidDate(valueY, valueM, valueD) {
  var dateWrapper = new Date(valueY, valueM, valueD);
  return !isNaN(dateWrapper.getDate());
}

function guardar() {
  let boologin = login();

  let btnIngresar = document.getElementById("btnGuardar");
  btnIngresar.disabled = true;
  btnIngresar.innerHTML = '<span id="spinner" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
  let spinner = document.getElementById("spinner");

  if (boologin==false) {
    spinner.style.visibility = 'hidden';
    btnIngresar.innerText="Registrar devolución de equipo";
    btnIngresar.disabled = false;

    let tituloMensaje = document.getElementById("tituloMensaje");
    tituloMensaje.innerText='';

    let contenedorError = document.getElementById("mensajeModal");
    contenedorError.innerText='';

    let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
    mensajeModalParrafo.innerText='';

    tituloMensaje.innerText = 'Hubo un inconveniente!';
    contenedorError.innerText ='No has iniciado sesión';
    mensajeModalParrafo.innerText ='O probablemente ha expirado la sesión. Ingresa de nuevo a TecnoPresta';

    mostrarModal('modalMensaje');
    return false;
  }

  let arrayArticulos = [];
  let arrayArticulosNombre = [];
  let checkboxes = document.querySelectorAll('input[name="chkDetalle"]:checked');

  for (let i = 0; i < checkboxes.length; i++) {
    arrayArticulos.push(checkboxes[i].getAttribute('data-id'));
    arrayArticulosNombre.push(checkboxes[i].getAttribute('data-nombre'));
  }

  let txtIncidenciaRO = document.getElementById("txtIncidencia").readOnly;
  let booIncidencia = false;
  let txtIncidencia = "";

  if (!txtIncidenciaRO) {
    booIncidencia=true;
    txtIncidencia = $('#txtIncidencia').val();
  }

  if (!txtIncidenciaRO && txtIncidencia.length < 5) {
    spinner.style.visibility = 'hidden';
    btnIngresar.innerText="Registrar devolución de equipo";
    btnIngresar.disabled = false;

    let tituloMensaje = document.getElementById("tituloMensaje");
    tituloMensaje.innerText='';

    let contenedorError = document.getElementById("mensajeModal");
    contenedorError.innerText='';

    let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
    mensajeModalParrafo.innerText='';

    tituloMensaje.innerText = 'Hubo un inconveniente!';
    contenedorError.innerText ='Al habilitar la opción de Registrar Incidencia, ' +
                              'es necesario hacer una descripción del incidente relacionado ' +
                              'a la devolución del equipo';
    mensajeModalParrafo.innerText ='Por favor digite la descripción con más detalle';

    mostrarModal('modalMensaje');
    return false;
  }

  if (arrayArticulos && arrayArticulos.length>0) {
    const formData = new FormData();
    const json = JSON.stringify(arrayArticulos);
    const jsonNombre = JSON.stringify(arrayArticulosNombre);

    formData.append('prestamo_Id', prestamo_Id);
    formData.append('arrayArticulos', json);

    formData.append('prestamo_email_solicitante', prestamo_email_solicitante);
    formData.append('arrayArticulosNombre', jsonNombre);
    formData.append('prestamo_nombre_solicitante', prestamo_nombre_solicitante);
    formData.append('prestamo_incidente', booIncidencia);
    formData.append('prestamo_incidente_comentario', txtIncidencia);

    fetch('sql_n/insertDevolucionGestor.php', {
      method: 'POST',
      body: formData,
    }).then(function(response) {

      if(response.ok) {
          response.text().then(function(data) {
          }).catch(function(error) {
            spinner.style.visibility = 'hidden';
            btnIngresar.innerText="Registrar devolución de equipo";
            btnIngresar.disabled = false;

            let tituloMensaje = document.getElementById("tituloMensaje");
            tituloMensaje.innerText='';

            let contenedorError = document.getElementById("mensajeModal");
            contenedorError.innerText='';

            let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
            mensajeModalParrafo.innerText='';

            tituloMensaje.innerText = 'Hubo un inconveniente!';
            contenedorError.innerText ='Intente de nuevo!';
            mensajeModalParrafo.innerText ='No hubo respuesta del servidor MEP.';

            mostrarModal('modalMensaje');
          })
      } else {
              spinner.style.visibility = 'hidden';
              btnIngresar.innerText="Registrar devolución de equipo";
              btnIngresar.disabled = false;

              let tituloMensaje = document.getElementById("tituloMensaje");
              tituloMensaje.innerText='';

              let contenedorError = document.getElementById("mensajeModal");
              contenedorError.innerText='';

              let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
              mensajeModalParrafo.innerText='';

              tituloMensaje.innerText = 'Hubo un inconveniente!';
              contenedorError.innerText ='No hay respuesta del servidor MEP!';
              mensajeModalParrafo.innerText ='Verifique su conexión de internet.';

              mostrarModal('modalMensaje');
      }
    })
    .catch(function(error) {
            spinner.style.visibility = 'hidden';
            btnIngresar.innerText="Registrar devolución de equipo";
            btnIngresar.disabled = false;

            let tituloMensaje = document.getElementById("tituloMensaje");
            tituloMensaje.innerText='';

            let contenedorError = document.getElementById("mensajeModal");
            contenedorError.innerText='';

            let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
            mensajeModalParrafo.innerText='';

            tituloMensaje.innerText = 'Hubo un inconveniente!';
            contenedorError.innerText ='Error al guardar la información!';
            mensajeModalParrafo.innerText = error.message;

            mostrarModal('modalMensaje');
    })
    .then();
  } else {
    spinner.style.visibility = 'hidden';
    btnIngresar.innerText="Registrar devolución de equipo";
    btnIngresar.disabled = false;

    let tituloMensaje = document.getElementById("tituloMensaje");
    tituloMensaje.innerText='';

    let contenedorError = document.getElementById("mensajeModal");
    contenedorError.innerText='';

    let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
    mensajeModalParrafo.innerText='';

    tituloMensaje.innerText = 'Hubo un inconveniente!';
    contenedorError.innerText ='Al parecer no hay artículos seleccionados!';
    mensajeModalParrafo.innerText = 'Marca los artículos que vas a devolver, ' +
                                    'haciendo click en el cuadro pequeño junto al nombre del Artículo';

    mostrarModal('modalMensaje');
    return false;
  }

  spinner.style.visibility = 'hidden';
  btnIngresar.innerText="Registrar devolución de equipo";

  let tituloMensaje = document.getElementById("tituloMensajeGuardar");
  tituloMensaje.innerText='';

  let contenedorError = document.getElementById("mensajeModalGuardar");
  contenedorError.innerText='';

  let mensajeModalParrafo = document.getElementById("mensajeModalParrafoGuardar");
  mensajeModalParrafo.innerText = 'TecnoPresta enviará una notificación al correo institucional del solicitante';

  tituloMensaje.innerText = 'Ok!';
  contenedorError.innerText ='Se registró la devolución!';

  mostrarModal('modalMensajeGuardar');

  return true;
}

function cargaDatosBd() {
  fetch('sql_n/selectPrestamoDetalleActivoGestor.php?'
          + new URLSearchParams({prestamo_Id: prestamo_Id, codigo: codigoPresupuestario}))
    .then(function(response) {
    if(response.ok) {
      response.json().then(function(data) {
          cargaDatosPantalla(data);
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

function cargaDatosBdPrestamoEncabezado() {
  fetch('sql_n/selectPrestamoEncabezadoGestor.php?'
          + new URLSearchParams({prestamo_Id: prestamo_Id, codigo: codigoPresupuestario}))
    .then(function(response) {
    if(response.ok) {
      response.json().then(function(data) {
          cargaDatosPantallaEncabezado(data);
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

function cargaDatosPantalla(rs) {
  $('#item').remove();

  rs.forEach(obj => {
      let plantilla = document.getElementById("plantilla");

      let row = document.createElement('div');
      row.className = "form-group row";
      row.id = "item";

      let nombreArticulo = obj.clase + " " + obj.modelo + " " + obj.marca + " " + obj.placa + " " + obj.numero_activo;
      let colCheck = document.createElement('div');
      colCheck.className = "col-1";
      colCheck.innerHTML='<div class="form-check">'+
                            '<input class="form-check-input position-static" ' +
                            'type="checkbox" name="chkDetalle" ' +
                            'data-id="' + obj.id_placa  + '" data-nombre="' + nombreArticulo + '" >' +
                          '</div>';

      let colNombre = document.createElement('div');
      colNombre.className = "col-4";
      let createATextNombre = document.createTextNode(obj.clase + " " + obj.marca);
      colNombre.appendChild(createATextNombre);

      let colMarca = document.createElement('div');
      colMarca.className = "col";
      let createATextMarca = document.createTextNode(obj.modelo);
      colMarca.appendChild(createATextMarca);

      let colPlaca = document.createElement('div');
      colPlaca.className = "col";
      let createATextPlaca = document.createTextNode(obj.placa);
      colPlaca.appendChild(createATextPlaca);

      let colNumero = document.createElement('div');
      colNumero.className = "col";
      let createATextNumero = document.createTextNode(obj.numero_activo);
      colNumero.appendChild(createATextNumero);

      row.appendChild(colCheck);
      row.appendChild(colNombre);
      row.appendChild(colMarca);
      row.appendChild(colPlaca);
      row.appendChild(colNumero);
      plantilla.appendChild(row);
    });

    return false;
}

function cargaDatosPantallaEncabezado(rs) {
    let rsfechaRetiro = rs[0].prestamo_fechaRetiro;
    let prestamo_fechaRetiroY = rsfechaRetiro.substr(0, 4);
    let prestamo_fechaRetiroM = rsfechaRetiro.substr(5, 2);
    let prestamo_fechaRetiroD = rsfechaRetiro.substr(8, 2);

    let prestamo_fechaRetiro = prestamo_fechaRetiroD + "/" + prestamo_fechaRetiroM + "/"  + prestamo_fechaRetiroY;

    let rshoraRetiro = rs[0].prestamo_horaRetiro;
    let prestamo_horaRetiroH = rshoraRetiro.substr(0, 2);
    let prestamo_horaRetiroM = rshoraRetiro.substr(3, 2);

    let prestamo_horaRetiro = prestamo_horaRetiroH + ":" + prestamo_horaRetiroM;

    let fechaRetiro = document.getElementById("fechaRetiro");
    fechaRetiro.value = prestamo_fechaRetiro + " " + prestamo_horaRetiro;

    let rsfechaDevolucion = rs[0].prestamo_fechaDevolucion;
    let prestamo_fechaDevolucionY = rsfechaDevolucion.substr(0, 4);
    let prestamo_fechaDevolucionM = rsfechaDevolucion.substr(5, 2);
    let prestamo_fechaDevolucionD = rsfechaDevolucion.substr(8, 2);

    let prestamo_fechaDevolucion = prestamo_fechaDevolucionD + "/" + prestamo_fechaDevolucionM + "/"  + prestamo_fechaDevolucionY;

    let rshoraDevolucion = rs[0].prestamo_horaDevolucion;
    let prestamo_horaDevolucionH = rshoraDevolucion.substr(0, 2);
    let prestamo_horaDevolucionM = rshoraDevolucion.substr(3, 2);

    let prestamo_horaDevolucion = prestamo_horaDevolucionH + ":" + prestamo_horaDevolucionM;

    let fechaDevolucion = document.getElementById("fechaDevolución");
    fechaDevolucion.value = prestamo_fechaDevolucion + " " + prestamo_horaDevolucion;

    let rsprestamo_uso = rs[0].prestamo_uso;
    let prestamo_uso = document.getElementById("txtUso");
    prestamo_uso.value = rsprestamo_uso;

    let rsPrestamo_incidente_comentario = rs[0].prestamo_incidente_comentario;
    let prestamo_incidente_comentario = document.getElementById("txtIncidencia");
    prestamo_incidente_comentario.value = rsPrestamo_incidente_comentario;

    prestamo_nombre_solicitante = rs[0].prestamo_nombre_solicitante;

    prestamo_email_solicitante = rs[0].prestamo_email_solicitante;

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

function salir() {
  // window.location.replace('navegar.php?ruta=formulario_menu_prestamo.html');
  window.location.replace('navegar.php?ruta=formulario_VistaDevolucion_n.php');
}
