let codigoPresupuestario;

window.onload = function() {
  let boologin = login();
  if (boologin) {
    mostrarLoading(true);
    cargaDatosBd();
  } else {
    let contenedorError = document.getElementById("contenedorError");
    contenedorError.innerHTML='<div class="alert alert-danger">' +
                                    '<strong>Error! </strong>' +
                                        'No ha iniciado sesión ...' +
                                    '</div>';
  }
  return false;
};

function mostrarLoading(mostrar) {
  let fila = document.getElementById('fila');
  if (mostrar) {
    fila.innerHTML = '<div class="devolucion-loading"><div class="spinner"></div><small>Cargando devoluciones...</small></div>';
  }
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

function cargaDatosBd() {
  fetch('sql_n/selectVistaDevolucionGestor.php?'
    + new URLSearchParams({codigo: codigoPresupuestario}))
    .then(function(response) {
      if(response.ok) {
        response.json().then(function(data) {
          if (data != null && data.length > 0) {
            cargaDatosPantalla(data);            
          } else {
            document.getElementById("fila").innerHTML =
              '<div class="devolucion-empty">' +
                '<img src="./img/Almacenlleno-01.png" alt="Sin artículos" />' +
                '<p>No hay artículos pendientes de devolución</p>' +
              '</div>';
          }
        }).catch(function(error) {
          let contenedorError = document.getElementById("contenedorError");
          contenedorError.innerHTML='<div class="alert alert-danger">' +
                                  '<strong>Error! </strong>' +
                                  'No hay respuesta del servidor MEP. Verifique su conexión de internet ' + error.message +
                                  '</div>';
        });
      } else {
        let contenedorError = document.getElementById("contenedorError");
        contenedorError.innerHTML='<div class="alert alert-danger">' +
                                '<strong>Error! </strong>' +
                                    'No se pudo conectar con el servidor. Intente de nuevo.' +
                                '</div>';
      }
    }).catch(function(error) {
      let contenedorError = document.getElementById("contenedorError");
      contenedorError.innerHTML='<div class="alert alert-danger">' +
                              '<strong>Error! </strong>' +
                                  'Hubo un problema al conectar con el servidor: ' + error.message +
                              '</div>';
    }).then();
  return false;
}

function muestraFecha(fecha) {
  if (!fecha) return '';
  let fechaY = fecha.substr(0, 4);
  let fechaM = fecha.substr(5, 2);
  let fechaD = fecha.substr(8, 2);
  return [fechaD, fechaM, fechaY].join('/');
}

function muestraHora(hora) {
  if (!hora) return '';
  let horaH = hora.substr(0, 2);
  let horaM = hora.substr(3, 2);
  return [horaH, horaM].join(':');
}

function cargaDatosPantalla(rs) {
  let dir = "formulario_devolucion_n.php";  

  let fila = document.getElementById('fila');
  fila.innerHTML = '';

  rs.forEach(function(obj, index) {
    let colCard = document.createElement('div');
    colCard.className = "col-xxl-3 col-xl-4 col-lg-4 col-md-6 mb-4";
    colCard.style.animationDelay = (index * 0.07) + 's';

    let wrapper = document.createElement('div');
    wrapper.className = "devolucion-wrapper";

    let card = document.createElement('div');
    card.className = "card devolucion-card";

    // Header
    let header = document.createElement('div');
    header.className = "card-header devolucion-header";
    header.textContent = obj.prestamo_nombre_solicitante;

    // Body
    let body = document.createElement('div');
    body.className = "card-body devolucion-body";

    // Items grid
    let itemsGrid = document.createElement('div');
    itemsGrid.className = "devolucion-items-grid";

    // Info compact (dates)
    let infoCompact = document.createElement('div');
    infoCompact.className = "devolucion-info-compact";

    let rowRetiro = document.createElement('div');
    rowRetiro.className = "info-row";
    rowRetiro.innerHTML = '<i class="bi bi-calendar3"></i>'
      + '<span class="info-label">Retiro</span>'
      + '<span class="info-fecha">' + muestraFecha(obj.prestamo_fechaRetiro) + '</span>'
      + '<span class="info-hora"><i class="bi bi-clock"></i> ' + muestraHora(obj.prestamo_horaRetiro) + '</span>';

    let rowDevolucion = document.createElement('div');
    rowDevolucion.className = "info-row";
    rowDevolucion.innerHTML = '<i class="bi bi-calendar3"></i>'
      + '<span class="info-label">Devolución</span>'
      + '<span class="info-fecha">' + muestraFecha(obj.prestamo_fechaDevolucion) + '</span>'
      + '<span class="info-hora"><i class="bi bi-clock"></i> ' + muestraHora(obj.prestamo_horaDevolucion) + '</span>';

    infoCompact.appendChild(rowRetiro);
    infoCompact.appendChild(rowDevolucion);

    body.appendChild(itemsGrid);
    body.appendChild(infoCompact);

    // Footer
    let footer = document.createElement('div');
    footer.className = "card-footer devolucion-footer";

    let btn = document.createElement('a');
    btn.className = "btn-mep";
    btn.href = dir + "?prestamo_Id=" + obj.prestamo_Id;
    btn.innerHTML = '<i class="bi bi-check2-circle"></i> Recibir equipo';

    footer.appendChild(btn);

    // Assemble card
    card.appendChild(header);
    card.appendChild(body);
    card.appendChild(footer);
    wrapper.appendChild(card);
    colCard.appendChild(wrapper);
    fila.appendChild(colCard);

    // Fetch detail items
    fetch('sql_n/selectDevolucionDetalleActivosGestor.php?'
      + new URLSearchParams({prestamo_Id: obj.prestamo_Id, codigo: codigoPresupuestario}))
      .then(function(response) {
        if(response.ok) {
          response.json().then(function(data) {
            if (data && data.length > 0) {
              data.forEach(function(item) {
                let itemDiv = document.createElement('div');
                itemDiv.className = "solicitud-item";

                let imgWrapper = document.createElement('div');
                imgWrapper.className = "solicitud-item-img";

                let img = document.createElement('img');
                img.src = './img/' + item.imagen;
                img.alt = item.clase + ' ' + item.marca;
                imgWrapper.appendChild(img);

                let infoDiv = document.createElement('div');
                infoDiv.className = "solicitud-item-info";

                let nombreP = document.createElement('p');
                nombreP.className = "item-nombre";
                nombreP.textContent = item.clase + ' ' + item.marca + ' ' + item.modelo;

                let placaP = document.createElement('p');
                placaP.className = "item-placa";
                placaP.textContent = (item.placa ? item.placa + ' ' : '') + (item.numero_activo || '');

                infoDiv.appendChild(nombreP);
                infoDiv.appendChild(placaP);

                itemDiv.appendChild(imgWrapper);
                itemDiv.appendChild(infoDiv);
                itemsGrid.appendChild(itemDiv);
              });
            }
          }).catch(function() {});
        }
      }).catch(function() {}).then();
  });

  return false;
}
