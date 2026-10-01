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

function seleccionarPrestador(obj) {
  window.sessionStorage.setItem('ambitoPrestadorSeleccionado', obj.id_ambito);
  //window.location.href = "navegar.php?ruta=formulario_buscar_alias_n.php";
  window.location.href = "formulario_buscar_alias_n.php";
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
          contenedorError.innerHTML='<div class="alert alert-danger">' +
                                  '<strong>Aviso! </strong>' +
                                      'No se han establecido prestadores, comuníquese con el administrador del sistema' +
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
