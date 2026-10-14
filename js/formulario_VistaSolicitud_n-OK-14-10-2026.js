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
}

function mostrarLoading(mostrar) {
  let fila = document.getElementById('fila');
  if (mostrar) {
    fila.innerHTML = '<div class="solicitud-loading"><div class="spinner"></div><small>Cargando solicitudes...</small></div>';
  }
}

function cargaDatosBd() {

  fetch('sql/selectSolicitudGestor.php?'
    + new URLSearchParams({codigo: codigoPresupuestario}))
    .then(function(response) {
    if(response.ok) {
      response.json().then(function(data) {
        if (data != null && data.length > 0) {
          cargaDatosPantalla(data);
        } else {
          document.getElementById("fila").innerHTML =
            '<div class="solicitud-empty">' +
              '<img src="./img/nosolicitudes.png" alt="Sin solicitudes" />' +
              '<p>No hay solicitudes pendientes de revisión</p>' +
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

function muestraFecha(fecha) {
  let fechaY = fecha.substr(0, 4);
  let fechaM = fecha.substr(5, 2);
  let fechaD = fecha.substr(8, 2);
  return [fechaD, fechaM, fechaY].join('/');
}

function muestraHora(hora) {
  let horaH = hora.substr(0, 2);
  let horaM = hora.substr(3, 2);
  return [horaH, horaM].join(':');
}

function cargaDatosPantalla(rs) {
  let dir = "formulario_prestamo_n.php";
  let fila = document.getElementById('fila');
  fila.innerHTML = '';

  rs.forEach(function(obj, index) {

    let colCard = document.createElement('div');
    colCard.className = "col-xxl-3 col-xl-4 col-lg-4 col-md-6 mb-4";
    colCard.style.animationDelay = (index * 0.07) + 's';

    let wrapper = document.createElement('div');
    wrapper.className = "solicitud-wrapper";

    let card = document.createElement('div');
    card.className = "card solicitud-card";

    /* ══════ HEADER con badge ══════ */
    let header = document.createElement('div');
    header.className = "card-header solicitud-header";

    let nombreSpan = document.createElement('span');
    nombreSpan.className = "nombre";
    nombreSpan.textContent = obj.solicitud_nombre_funcionario;

    let badge = document.createElement('span');
    badge.className = "badge-estado";
    badge.innerHTML = '<span class="indicador"></span> Pendiente';

    header.appendChild(nombreSpan);
    header.appendChild(badge);

    /* ══════ BODY ══════ */
    let body = document.createElement('div');
    body.className = "card-body solicitud-body";

    /* ── Grid de artículos ── */
    let itemsGrid = document.createElement('div');
    itemsGrid.className = "solicitud-items-grid";

    /* ── Info compacta (fechas + descripción) ── */
    let infoCompact = document.createElement('div');
    infoCompact.className = "solicitud-info-compact";

    let rowRetiro = document.createElement('div');
    rowRetiro.className = "info-row";
    rowRetiro.innerHTML = '<i class="bi bi-calendar3"></i>'
      + '<span class="info-label">Retiro</span>'
      + '<span class="info-fecha">' + muestraFecha(obj.solicitud_fechaRetiro) + '</span>'
      + '<span class="info-hora"><i class="bi bi-clock"></i> ' + muestraHora(obj.solicitud_horaRetiro) + '</span>';

    let rowDevolucion = document.createElement('div');
    rowDevolucion.className = "info-row";
    rowDevolucion.innerHTML = '<i class="bi bi-calendar3"></i>'
      + '<span class="info-label">Devolución</span>'
      + '<span class="info-fecha">' + muestraFecha(obj.solicitud_fechaDevolucion) + '</span>'
      + '<span class="info-hora"><i class="bi bi-clock"></i> ' + muestraHora(obj.solicitud_horaDevolucion) + '</span>';

    let descripcionDiv = document.createElement('div');
    descripcionDiv.className = "solicitud-descripcion";
    descripcionDiv.textContent = obj.solicitud_uso;

    infoCompact.appendChild(rowRetiro);
    infoCompact.appendChild(rowDevolucion);
    infoCompact.appendChild(descripcionDiv);

    /* ── Ensamblar body ── */
    body.appendChild(itemsGrid);
    body.appendChild(infoCompact);

    /* ══════ FOOTER con botón MEP ══════ */
    let footer = document.createElement('div');
    footer.className = "card-footer solicitud-footer";

    let btn = document.createElement('a');
    btn.className = "btn-mep";
    btn.href = dir + "?solicitud_Id=" + obj.solicitud_Id;
    btn.innerHTML = '<i class="bi bi-arrow-right-circle"></i> Tramitar solicitud';

    footer.appendChild(btn);

    /* ══════ Ensamblar card ══════ */
    card.appendChild(header);
    card.appendChild(body);
    card.appendChild(footer);
    wrapper.appendChild(card);
    colCard.appendChild(wrapper);
    fila.appendChild(colCard);

    /* ── Cargar alias (artículos sin placa) ── */
    fetch('sql/selectSolicitudDetalleGestor.php?'
      + new URLSearchParams({solicitud_Id: obj.solicitud_Id}))
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
                img.src = './img/alias/' + item.alias_imagen;
                img.alt = item.alias;
                imgWrapper.appendChild(img);

                let cantidadBadge = document.createElement('span');
                cantidadBadge.className = "solicitud-cantidad";
                cantidadBadge.textContent = "x" + item.solicitud_detalle_cantidad;

                let infoDiv = document.createElement('div');
                infoDiv.className = "solicitud-item-info";

                let nombreP = document.createElement('p');
                nombreP.className = "item-nombre";
                nombreP.textContent = item.alias;

                infoDiv.appendChild(nombreP);

                itemDiv.appendChild(imgWrapper);
                itemDiv.appendChild(cantidadBadge);
                itemDiv.appendChild(infoDiv);
                itemsGrid.appendChild(itemDiv);
              });
            }
          }).catch(function() {});
        }
      }).catch(function() {}).then();

    /* ── Cargar activos (con placa) ── */
    fetch('sql/selectSolicitudDetalleActivosGestor.php?'
      + new URLSearchParams({solicitud_Id: obj.solicitud_Id}))
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
