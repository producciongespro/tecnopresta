let txtBuscar = document.getElementById("txtBuscar");
let alias_id = 0;
let id_activo = 0;
let codigoPresupuestario;

txtBuscar.addEventListener("keydown", 
function(e) {
  
  var allowedCode = [8, 13, 32, 37, 39, 44, 45, 46, 95];
  var charCode = (e.charCode) ? e.charCode : ((e.keyCode) ? e.keyCode :
      ((e.which) ? e.which : 0));
   if (charCode > 31 && (charCode < 64 || charCode > 90) &&
    (charCode < 97 || charCode > 122) &&
    (charCode < 48 || charCode > 57) &&
    (allowedCode.indexOf(charCode) == -1)) {
      //console.log(e.which);
      e.preventDefault();
      return false;            
  }
          
});

txtBuscar.addEventListener('keyup', 
  function(e) {
  
    var allowedCode = [8, 13, 32, 37, 39, 44, 45, 46, 95];
    var charCode = (e.charCode) ? e.charCode : ((e.keyCode) ? e.keyCode :
        ((e.which) ? e.which : 0));
    if (charCode > 31 && (charCode < 64 || charCode > 90) &&
      (charCode < 97 || charCode > 122) &&
      (charCode < 48 || charCode > 57) &&
      (allowedCode.indexOf(charCode) == -1)) {
        //console.log(e.which);
        e.preventDefault();
        return false;            
    }

  if(charCode == 37 || charCode == 39) {
    e.preventDefault();
    return false;  // No se ejecuta si son las flechitas de adelante y atras, 
                  //pero si son permitidas en la variable allowedCode 
  }

 

});

txtBuscar.addEventListener("keypress", 
function(e) {
  if(e.keyCode == 13) {
    e.preventDefault();
    buscar();
    return false;
  }
});

$(document).ready(function() {   

    $('#fechaRetiro').datepicker({
      locale: 'es-es',
      format: 'dd mm yyyy',
      uiLibrary: 'bootstrap5'
    });

    $('#horaRetiro').timepicker({
      locale: 'es-es',
      format: 'HH:MM',
      uiLibrary: 'bootstrap5'
    });
  
    $('#fechaDevolucion').datepicker({
      locale: 'es-es',
      format: 'dd mm yyyy',
      uiLibrary: 'bootstrap5'
    });

    $('#horaDevolucion').timepicker({
      locale: 'es-es',
      format: 'HH:MM',
      uiLibrary: 'bootstrap5'
    });
  
    $("#btnGuardar").on("click", function(event) {
      
      event.preventDefault();    
        return false;
    });
    
    $("#btnAgregarArticulo").on("click", function(event) {
      
      event.preventDefault();
      $("#buscarModal").modal('show');    
        return false;
    });    

  });

  $('#chkBoleta').on( 'change', function() {

    let txtBoletaRO = document.getElementById("txtBoleta").readOnly;
    
    if (txtBoletaRO) {
  
      document.getElementById("txtBoleta").readOnly = false;
  
    } else {
  
      document.getElementById("txtBoleta").readOnly = true;
  
    }
    
  });

window.onload = function() {
      
    let boologin = login();

    if (boologin) {      
      
      //cargaComboSoftware();
      //cargaComboSeccion();
      cargaDatosGestor();
      
      let activo_nombre = $('#txtBuscar').val();

      if (activo_nombre != "" || activo_nombre != " ") 
      {
        buscar();  
      }      
      

    }  else {
    
      let contenedorError = document.getElementById("mensaje");
      
      contenedorError.innerHTML='<div class="alert alert-danger">' +
                                      '<strong>Error! </strong>' +
                                          'No ha iniciado sesión ...' +
                                      '</div>';

    }
            
    return false;

}

window.onbeforeunload = function(){
  
  sessionStorage.removeItem("postSolicitud");
  sessionStorage.removeItem("postSolicitudActivo");
  
};

function cargaComboSoftware() 
{
  
  fetch('sql/select_Caracteristica_softwareGestor.php')
  .then(function(response) 
  {
          
    if(response.ok) 
    {

      response.json().then(function(data) 
      {
        
        //console.log(data);
              
        data.forEach(element => 
          {

            let cboSoftware = document.getElementById("cboSoftware"); 
            let opt = document.createElement("option");
            opt.value = element.id_cs;
            opt.innerHTML = element.caracteristica;        
            cboSoftware.append(opt);            

          });
          
          $("#cboSoftware").selectpicker("refresh");
    
      });
  
    }

  }).then(function(data){});

}

function cargaComboSeccion() 
{
  
  fetch('sql/selectSeccion_Gestor.php')
  .then(function(response) 
  {
          
    if(response.ok) 
    {

      response.json().then(function(data) 
      {
        
        //console.log(data);
              
        data.forEach(element => 
          {

            let cboSeccion = document.getElementById("cboSeccion"); 
            let opt = document.createElement("option");
            opt.value = element.seccion_Id;
            opt.innerHTML = element.seccion_descripcion;        
            cboSeccion.append(opt);            

          });
          
          $("#cboSeccion").selectpicker("refresh");
    
      });
  
    }

  }).then(function(data){});

}

function login() {

  window.userData = [];
  userData = window.sessionStorage.getItem('sesion');
    
  if (userData && userData.length>0) {

    let jsonData = [];

    jsonData = JSON.parse(userData);

    let nombreEl = document.getElementById("nombre");
    let codigoEl = document.getElementById("codigo");
    if (nombreEl) nombreEl.innerText = jsonData["Nombre"] + " " + jsonData["Apellido1"] + " " + jsonData["Apellido2"];
    if (codigoEl) codigoEl.innerText = jsonData["Dependencia"];
    codigoPresupuestario = jsonData["CentrosEducativosDondeTrabaja"];

    //console.log(jsonData);

  } else {
                 
    //codigoPresupuestario = "5300";
    //return true;
    return false;
    

  }

  return true;

}

function cargaDatosGestor() {

  $('#colCards').empty();
  
  let botonEnviaSolicitud = window.sessionStorage.getItem('botonEnviaSolicitud');
  
  if (botonEnviaSolicitud == "true") {
    
    if (window.sessionStorage.getItem('postSolicitud') !== null) {

      //console.log(window.sessionStorage.getItem('postSolicitud'));
      cargaDatosPantalla(window.sessionStorage.getItem('postSolicitud')); 
      window.sessionStorage.setItem('solicitud_detalle', window.sessionStorage.getItem('postSolicitud')); 

    }
    
    if (window.sessionStorage.getItem('postSolicitudActivo') !== null) {

      //console.log(window.sessionStorage.getItem('postSolicitudActivo'));
      cargaDatosPantallaArticulo(window.sessionStorage.getItem('postSolicitudActivo'));

    }    

  } else {
    
    cargaDatosPantalla(window.sessionStorage.getItem('sesionCanasta'));
    cargaDatosPantallaArticulo(window.sessionStorage.getItem('sesionActivo'));
    window.sessionStorage.setItem('solicitud_detalle',window.sessionStorage.getItem('sesionCanasta'));
  
  }

}

function quitarElementoArray() {

  let cards = document.getElementsByClassName('card')
  
    for (let i = 0; i < cards.length; i++) {

      if (cards[i].getAttribute('data-tipo')=="alias") {

        let dataAlias_id = cards[i].getAttribute('data-id');
        
        if (dataAlias_id==alias_id) {
        
          let elem = document.getElementById(dataAlias_id);
          elem.parentElement.removeChild(elem);               
          postSolicitud = [];
          window.sessionStorage.setItem('postSolicitud',postSolicitud);
        }

      }

    }

  $("#modalMensajeSiNo").modal('hide');
    
  return false;

}

function quitarElementoArrayArticulo() {

  let cards = document.getElementsByClassName('card')
  
    for (let i = 0; i < cards.length; i++) {

      if (cards[i].getAttribute('data-tipo')=="activo") {

        let dataActivo_id = cards[i].getAttribute('data-id');
        
        if (dataActivo_id==id_activo) {
        
          let elem = document.getElementById(dataActivo_id);
          elem.parentElement.removeChild(elem);               
          postSolicitud = [];
          window.sessionStorage.setItem('postSolicitudActivo',postSolicitud);
        }
        
      }

    }

  $("#modalMensajeSiNoArticulo").modal('hide');
    
  return false;

}


  function botonQuitar(arrayArticulo) {

    let tituloMensaje = document.getElementById("tituloMensajeSiNo");
    tituloMensaje.innerText='';

    let contenedorError = document.getElementById("mensajeModalSiNo");
    contenedorError.innerText='';
    
    tituloMensaje.innerText = 'Aviso importante!' ;
    contenedorError.innerText = 'Realmente desea quitar ' + arrayArticulo["alias"] + ' de la solicitud ?';
    alias_id=arrayArticulo["alias_id"];   

    $("#modalMensajeSiNo").modal('show');

    return false;

  } 
  
  function botonQuitarArticulo(arrayArticulo) {

    let tituloMensaje = document.getElementById("tituloMensajeSiNoArticulo");
    tituloMensaje.innerText='';

    let contenedorError = document.getElementById("mensajeModalSiNoArticulo");
    contenedorError.innerText='';
    
    tituloMensaje.innerText = 'Aviso importante!' ;
    contenedorError.innerText = 'Realmente desea quitar ' + arrayArticulo["clase"] + ' de la solicitud ?';
    id_activo=arrayArticulo["id_activo"];   

    $("#modalMensajeSiNoArticulo").modal('show');

    return false;

  }  


function cargaDatosPantalla(userDataCanasta = []) {
    
  
  if (userDataCanasta && userDataCanasta.length>0) {

    let jsonData = [];
    jsonData = JSON.parse(userDataCanasta);

    jsonData.forEach(obj => {
                     
          let card = document.createElement('div');
          card.className = "cart-item-card card d-flex flex-column p-3";
          card.id=obj.alias_id;
          card.setAttribute('data-id',obj.alias_id);
          card.setAttribute('data-aliasNombre',obj.alias);
          card.setAttribute('data-tipo',"alias");

          let cardimg = document.createElement('img');
          cardimg.className = "cart-item-img mx-auto d-block mb-2";
          cardimg.src = './img/alias/' + obj.alias_imagen;

          let bodyDiv = document.createElement('div');
          bodyDiv.className = "flex-grow-1 w-100";
          bodyDiv.innerHTML = '<div class="fw-semibold">' + obj.alias + '</div>';

          let btnQuitar = document.createElement('button');
          btnQuitar.type = "button";
          btnQuitar.className = "btn-quitar-item align-self-end mt-2";
          btnQuitar.setAttribute('aria-label', 'Quitar');
          btnQuitar.onclick = new Function("botonQuitar(" + JSON.stringify(obj) + ");");
          btnQuitar.innerHTML = '<i class="bi bi-trash"></i>';

          card.appendChild(cardimg);
          card.appendChild(bodyDiv);
          card.appendChild(btnQuitar);

          document.getElementById('colCards').appendChild(card);

      });

  }    
  
  return false;

}

function cargaDatosPantallaArticulo(userDataArticulo = []) {
    
  
  if (userDataArticulo && userDataArticulo.length>0) {

    let jsonDataArticulo = [];
    jsonDataArticulo = JSON.parse(userDataArticulo);

    jsonDataArticulo.forEach(obj => {
          
          let nombreArticulo = obj.clase + " " + obj.marca + " " + obj.modelo + " " + obj.placa + " " + obj.numero_activo;

          let card = document.createElement('div');
          card.className = "cart-item-card card d-flex flex-column p-3";
          card.id=obj.id_activo;
          card.setAttribute('data-id' , obj.id_activo);
          card.setAttribute('data-id_placa' , obj.id_placa);
          card.setAttribute('data-articuloNombre' , nombreArticulo);
          card.setAttribute('data-tipo' , "activo");

          let cardimg = document.createElement('img');
          cardimg.className = "cart-item-img mx-auto d-block mb-2";
          cardimg.src = './img/' + obj.imagen;

          let bodyDiv = document.createElement('div');
          bodyDiv.className = "flex-grow-1 w-100";

          let etiqueta = (obj.numero_activo === null) ? "Por asignar" : obj.numero_activo;
          let infoHtml = '<div class="fw-semibold">' + obj.clase + '</div>';
          infoHtml += '<div class="text-muted small">' + obj.marca;
          if (obj.placa) infoHtml += ' &middot; Placa: ' + obj.placa;
          infoHtml += ' &middot; Etiqueta: ' + etiqueta + '</div>';
          bodyDiv.innerHTML = infoHtml;

          let btnQuitar = document.createElement('button');
          btnQuitar.type = "button";
          btnQuitar.className = "btn-quitar-item align-self-end mt-2";
          btnQuitar.setAttribute('aria-label', 'Quitar');
          btnQuitar.onclick = new Function("botonQuitarArticulo(" + JSON.stringify(obj) + ");");
          btnQuitar.innerHTML = '<i class="bi bi-trash"></i>';

          card.appendChild(cardimg);
          card.appendChild(bodyDiv);
          card.appendChild(btnQuitar);

          document.getElementById('colCards').appendChild(card);

     });

  }    
  
  return false;

}

function buscar() {

  let contenedorError = document.getElementById("mensaje");
  contenedorError.innerHTML='';
  
  let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
  mensajeModalParrafo.innerText='';

  let mensajeModalBuscar = document.getElementById("mensajeModalBuscar");
  mensajeModalBuscar.innerHTML='';


  let btnBuscar = document.getElementById("btnBuscar");
   
  btnBuscar.disabled = true;
  
  btnBuscar.innerHTML = '<span id="spinner" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
  let spinner = document.getElementById("spinner");   

  cargaDatosBd();

  spinner.style.visibility = 'hidden';
  btnBuscar.innerHTML='<i class="bi bi-search"></i>';
  btnBuscar.disabled = false;

}

function cargaDatosPantallaBuscar(rs) {

  rs.forEach(function(obj) {
    let card = document.createElement('div');
    card.className = "search-result-card card d-flex align-items-center p-3";

    let cardimg = document.createElement('img');
    cardimg.className = "cart-item-img me-3";
    cardimg.src = './img/alias/' + obj.alias_imagen;

    let bodyDiv = document.createElement('div');
    bodyDiv.className = "flex-grow-1";
    bodyDiv.innerHTML = '<div class="fw-semibold">' + obj.alias + '</div>';

    let btnAgregar = document.createElement('button');
    btnAgregar.type = "button";
    btnAgregar.className = "btn-agregar-resultado ms-2";
    btnAgregar.innerText = "+ Solicitar";
    btnAgregar.onclick = function() { agregar(obj, 1); };

    card.appendChild(cardimg);
    card.appendChild(bodyDiv);
    card.appendChild(btnAgregar);

    document.getElementById('colCardsBuscar').appendChild(card);
  });

  return false;

}

function cargaDatosPantallaBuscarArticulo(rs) {

  rs.forEach(function(obj) {
    let card = document.createElement('div');
    card.className = "search-result-card card d-flex align-items-center p-3";

    let cardimg = document.createElement('img');
    cardimg.className = "cart-item-img me-3";
    cardimg.src = './img/' + obj.imagen;

    let bodyDiv = document.createElement('div');
    bodyDiv.className = "flex-grow-1";

    let info = obj.clase;
    if (obj.marca) info += ' &middot; ' + obj.marca;
    if (obj.placa) info += ' &middot; ' + obj.placa;
    if (obj.numero_activo) info += ' &middot; ' + obj.numero_activo;
    bodyDiv.innerHTML = '<div class="fw-semibold">' + info + '</div>';

    let btnAgregar = document.createElement('button');
    btnAgregar.type = "button";
    btnAgregar.className = "btn-agregar-resultado ms-2";
    btnAgregar.innerText = "+ Solicitar";
    btnAgregar.onclick = function() { agregarArticulo(obj); };

    card.appendChild(cardimg);
    card.appendChild(bodyDiv);
    card.appendChild(btnAgregar);

    document.getElementById('colCardsBuscar').appendChild(card);
  });

  return false;

}

function buscarRepetido(alias_id) {
      
  let btnSolicitar = document.querySelectorAll('.card')

  for (let i = 0; i < btnSolicitar.length; i++) {    
    
    if (btnSolicitar[i].getAttribute('data-tipo')=="alias") {

        if (btnSolicitar[i].getAttribute('data-id')==alias_id) {
            return true;
        }

    } 

  }

  return false;
  }

  function buscarRepetidoArticulo(id_activo, id_placa) {
      
    let btnSolicitar = document.querySelectorAll('.card')
  
    for (let i = 0; i < btnSolicitar.length; i++) {    
      
      if (btnSolicitar[i].getAttribute('data-tipo')=="activo") {

        if (btnSolicitar[i].getAttribute('data-id')==id_activo && btnSolicitar[i].getAttribute('data-id_placa')==id_placa) {
            return true;
        }
    
      }

    }
  
    return false;
    }


  function agregar(jsonArray, cantidad) {

    if (buscarRepetido(jsonArray["alias_id"]) ) {
      
      let contenedorError = document.getElementById("mensajeModalBuscar");
      contenedorError.innerHTML='<div class="alert alert-danger">' +
                              '<strong>Este Contenedor ya lo agregaste a la solicitud !!</strong>' +                          
                              '</div>';
                              
      return false;
    }

    let jsonCanasta = [];
    
    jsonArray["cantidad"]=cantidad;
    jsonCanasta.push(jsonArray);

    let json = JSON.stringify(jsonCanasta);
    
    cargaDatosPantalla(json);

    cerrarModal();
    $('#buscarModal').modal('hide'); ;
    window.scrollTo(0,9999); 
    
    return true;

  }
  
  function agregarArticulo(jsonArray) {

    
    if (buscarRepetidoArticulo(jsonArray["id_activo"], jsonArray["id_placa"])) {
      
      let contenedorError = document.getElementById("mensajeModalBuscar");
      contenedorError.innerHTML='<div class="alert alert-danger">' +
                              '<strong>Este artículo ya lo agregaste a la solicitud !!</strong>' +                          
                              '</div>';
      return false;
    }

    let jsonArticulo = [];
    
    jsonArticulo.push(jsonArray);

    let json = JSON.stringify(jsonArticulo);
    
    cargaDatosPantallaArticulo(json);

    cerrarModal();
    $('#buscarModal').modal('hide'); ;
    window.scrollTo(0,9999); 
    
    return true;

  }

  function cargaDatosBd() {
        
    let activo_nombre = $('#txtBuscar').val();
    $('#colCardsBuscar').empty();
    
    fetch('sql/selectActivoAliasGestor.php?'
    + new URLSearchParams({aliasActivo: activo_nombre, codigo: codigoPresupuestario}))
    .then(function(response) {

      if(response.ok) {
  
        response.json().then(function(data) {  
          
          //console.log(data);
          cargaDatosPantallaBuscar(data);
          
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


    fetch('sql/selectActivoGestor.php?'
    + new URLSearchParams({aliasActivo: activo_nombre, codigo: codigoPresupuestario}))
    .then(function(response) {

      if(response.ok) {
  
        response.json().then(function(data) {  
          
          //console.log(data);
          cargaDatosPantallaBuscarArticulo(data);
          
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

  function cerrarModal() {
    
    document.getElementById('txtBuscar').value = '';
    let contenedorError =document.getElementById('mensajeModalBuscar');
    contenedorError.innerHTML="";
    //$('#colCardsBuscar').empty();
  
  }

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

  function validaFechasHora(prestamo_fechaRetiroY, 
    prestamo_fechaRetiroM, 
    prestamo_fechaRetiroD,
    prestamo_horaRetiroH,
    prestamo_horaRetiroM, 
    prestamo_fechaDevolucionY,
    prestamo_fechaDevolucionM,
    prestamo_fechaDevolucionD,
    prestamo_horaDevolucionH,
    prestamo_horaDevolucionM) {

    let fechaInicio = new Date(prestamo_fechaRetiroY, prestamo_fechaRetiroM, 
                              prestamo_fechaRetiroD,prestamo_horaRetiroH,prestamo_horaRetiroM);
    let fechaFin = new Date(prestamo_fechaDevolucionY, prestamo_fechaDevolucionM, 
                            prestamo_fechaDevolucionD,prestamo_horaDevolucionH,prestamo_horaDevolucionM);

    if (Object.prototype.toString.call(fechaInicio) === "[object Date]"){
      if (isNaN(fechaInicio.getTime())) {
        return true;          
      }
    }

    if (Object.prototype.toString.call(fechaFin) === "[object Date]"){
      if (isNaN(fechaFin.getTime())) {
        return true;          
      }
    }
                        
    if (fechaInicio > fechaFin) {
      return true;
    }

    return false;

  }

  function isValidDate(valueY, valueM, valueD) {
    var dateWrapper = new Date(valueY, valueM, valueD);
    return !isNaN(dateWrapper.getDate());
  }

  function isValidDateTime(horaminutos) {
    
    var isValid = /^([0-1]?[0-9]|2[0-4]):([0-5][0-9])(:[0-5][0-9])?$/.test(horaminutos);      
    return isValid;

  }

  function validaFechaHoy(fechaY, fechaM, fechaD) {
    
    var d = new Date(),
    month = '' + (d.getMonth() + 1),
    day = '' + d.getDate(),
    year = d.getFullYear();

    if (month.length < 2) 
        month = '0' + month;
    if (day.length < 2) 
        day = '0' + day;

    let hoy = [year, month, day].join('-');
    
    let fechaValidar = new Date(fechaY, fechaM, fechaD),
    monthfechaValidar = '' + (fechaValidar.getMonth()),
    dayfechaValidar = '' + fechaValidar.getDate(),
    yearfechaValidar = fechaValidar.getFullYear();;
    
    if (monthfechaValidar.length < 2) 
      monthfechaValidar = '0' + monthfechaValidar;
    if (dayfechaValidar.length < 2) 
      dayfechaValidar = '0' + dayfechaValidar;

    let fecha = [yearfechaValidar, monthfechaValidar, dayfechaValidar].join('-');
   
    if (hoy > fecha) {
      return true;
    }

    return false;

  }

  function muestraFechaHoy() {
    
    let d = new Date(),
    month = '' + (d.getMonth() + 1),
    day = '' + d.getDate(),
    year = d.getFullYear();

    if (month.length < 2) 
        month = '0' + month;
    if (day.length < 2) 
        day = '0' + day;

    let hoy = [day, month, year].join('/');
        
    return hoy;

  }

  function solicitud_Detalle_Activo() {

    let json_solicitud_detalleActivos = new Array();
    let cards = document.querySelectorAll('.card')

    for (let i = 0; i < cards.length; i++) {    
      
      if (cards[i].getAttribute('data-tipo')=="activo") {

          if (cards[i].hasAttribute('data-id')) {
            
              let solicitud_detalleActivo = new Array();      
              let dataActivo_id = cards[i].getAttribute('data-id');
              let dataArticuloNombre = cards[i].getAttribute('data-articuloNombre');              
              let dataid_placa = cards[i].getAttribute('data-id_placa');          
            
              solicitud_detalleActivo = {
                                  "solicitud_detalle_id_activo":dataActivo_id,
                                  "solicitud_detalle_id_placa":dataid_placa,
                                  "solicitud_detalle_ArticuloNombre":dataArticuloNombre
                                  };

              json_solicitud_detalleActivos.push(solicitud_detalleActivo);

          }

        }      
    }
    
    return json_solicitud_detalleActivos;

  }
  
  function guardar() {
      
    let btnIngresar = document.getElementById("btnGuardar");
    btnIngresar.disabled = true;
    btnIngresar.innerHTML = '<span id="spinner" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
    let spinner = document.getElementById("spinner");

    let prestamo_fechaRetiro = $('#fechaRetiro').val();
    let prestamo_horaRetiro = $('#horaRetiro').val();    
    let prestamo_fechaDevolucion = $('#fechaDevolucion').val();
    let prestamo_horaDevolucion = $('#horaDevolucion').val();
    let solicitud_uso = $('#txtUso').val();    

    let boologin = login();

    if (boologin==false) {
      
      spinner.style.visibility = 'hidden';
      btnIngresar.innerText="Registrar solicitud de equipo";
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
      
      $('#modalMensaje').modal('show');

      return false;
    }

    if (prestamo_fechaRetiro.trim()=="") {

      spinner.style.visibility = 'hidden';
      btnIngresar.innerText="Registrar solicitud de equipo";
      btnIngresar.disabled = false;
      
      let tituloMensaje = document.getElementById("tituloMensaje");
      tituloMensaje.innerText='';
  
      let contenedorError = document.getElementById("mensajeModal");
      contenedorError.innerText='';

      let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
      mensajeModalParrafo.innerText='';

      tituloMensaje.innerText = 'Hubo un inconveniente!';
      contenedorError.innerText ='No es posible guardar la solicitud!';
      mensajeModalParrafo.innerText ='Selecciona una fecha de retiro.';   
      
      $('#modalMensaje').modal('show');

      return false;
    }

    if (prestamo_horaRetiro.trim()=="") {

      spinner.style.visibility = 'hidden';
      btnIngresar.innerText="Registrar solicitud de equipo";
      btnIngresar.disabled = false;
      
      let tituloMensaje = document.getElementById("tituloMensaje");
      tituloMensaje.innerText='';
  
      let contenedorError = document.getElementById("mensajeModal");
      contenedorError.innerText='';

      let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
      mensajeModalParrafo.innerText='';

      tituloMensaje.innerText = 'Hubo un inconveniente!';
      contenedorError.innerText ='No es posible guardar la solicitud!';
      mensajeModalParrafo.innerText ='Selecciona una hora de retiro.';   
      
      $('#modalMensaje').modal('show');

      return false;
    }

    if (prestamo_fechaDevolucion.trim()=="") {

      spinner.style.visibility = 'hidden';
      btnIngresar.innerText="Registrar solicitud de equipo";
      btnIngresar.disabled = false;

      let tituloMensaje = document.getElementById("tituloMensaje");
      tituloMensaje.innerText='';
  
      let contenedorError = document.getElementById("mensajeModal");
      contenedorError.innerText='';

      let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
      mensajeModalParrafo.innerText='';

      tituloMensaje.innerText = 'Hubo un inconveniente!';
      contenedorError.innerText ='No es posible guardar la solicitud!';
      mensajeModalParrafo.innerText ='Selecciona una fecha de devolución.';   
      
      $('#modalMensaje').modal('show');      

      return false;
    }

    if (prestamo_horaDevolucion.trim()=="") {

      spinner.style.visibility = 'hidden';
      btnIngresar.innerText="Registrar solicitud de equipo";
      btnIngresar.disabled = false;
      
      let tituloMensaje = document.getElementById("tituloMensaje");
      tituloMensaje.innerText='';
  
      let contenedorError = document.getElementById("mensajeModal");
      contenedorError.innerText='';

      let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
      mensajeModalParrafo.innerText='';

      tituloMensaje.innerText = 'Hubo un inconveniente!';
      contenedorError.innerText ='No es posible guardar la solicitud!';
      mensajeModalParrafo.innerText ='Selecciona una hora de devolución.';   
      
      $('#modalMensaje').modal('show');

      return false;
    }

    let prestamo_fechaRetiroY = prestamo_fechaRetiro.substr(6, 4);
    let prestamo_fechaRetiroM = prestamo_fechaRetiro.substr(3, 2);
    let prestamo_fechaRetiroD = prestamo_fechaRetiro.substr(0, 2);
    let prestamo_horaRetiroH = prestamo_horaRetiro.substr(0, 2);
    let prestamo_horaRetiroM = prestamo_horaRetiro.substr(3, 2);

    let prestamo_fechaDevolucionY = prestamo_fechaDevolucion.substr(6, 4);
    let prestamo_fechaDevolucionM = prestamo_fechaDevolucion.substr(3, 2);
    let prestamo_fechaDevolucionD = prestamo_fechaDevolucion.substr(0, 2);
    let prestamo_horaDevolucionH = prestamo_horaDevolucion.substr(0, 2);
    let prestamo_horaDevolucionM = prestamo_horaDevolucion.substr(3, 2);
   
    if (isValidDate((prestamo_fechaRetiroY, prestamo_fechaRetiroM, prestamo_fechaRetiroD)!==true)) {

      spinner.style.visibility = 'hidden';
      btnIngresar.innerText="Registrar solicitud de equipo";
      btnIngresar.disabled = false;

      let tituloMensaje = document.getElementById("tituloMensaje");
      tituloMensaje.innerText='';
  
      let contenedorError = document.getElementById("mensajeModal");
      contenedorError.innerText='';

      let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
      mensajeModalParrafo.innerText='';

      tituloMensaje.innerText = 'Hubo un inconveniente!' ;
      contenedorError.innerText ='No es posible guardar la solicitud!';
      mensajeModalParrafo.innerText ='Selecciona una fecha de retiro.';
      
      $('#modalMensaje').modal('show');

      return false;
    }

    

    if (isValidDateTime(prestamo_horaRetiro)==false) {

      spinner.style.visibility = 'hidden';
      btnIngresar.innerText="Registrar solicitud de equipo";
      btnIngresar.disabled = false;

      let tituloMensaje = document.getElementById("tituloMensaje");
      tituloMensaje.innerText='';
  
      let contenedorError = document.getElementById("mensajeModal");
      contenedorError.innerText='';

      let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
      mensajeModalParrafo.innerText='';

      tituloMensaje.innerText = 'Hubo un inconveniente!' ;
      contenedorError.innerText ='No es posible guardar la solicitud!';
      mensajeModalParrafo.innerText ='Selecciona una hora de retiro.';
      
      $('#modalMensaje').modal('show');

      return false;
    }  

    if (isValidDate(prestamo_fechaDevolucionY, prestamo_fechaDevolucionM, prestamo_fechaDevolucionD)!==true) {

      spinner.style.visibility = 'hidden';
      btnIngresar.innerText="Registrar solicitud de equipo";
      btnIngresar.disabled = false;

      let tituloMensaje = document.getElementById("tituloMensaje");
      tituloMensaje.innerText='';

      let contenedorError = document.getElementById("mensajeModal");
      contenedorError.innerText='';

      let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
      mensajeModalParrafo.innerText='';

      tituloMensaje.innerText = 'Hubo un inconveniente!';
      contenedorError.innerText ='No es posible guardar la solicitud!';
      mensajeModalParrafo.innerText ='Selecciona una fecha de devolución.';
      
      $('#modalMensaje').modal('show');

      return false;
      
    }

    if (isValidDateTime(prestamo_horaDevolucion)==false) {

      spinner.style.visibility = 'hidden';
      btnIngresar.innerText="Registrar solicitud de equipo";
      btnIngresar.disabled = false;

      let tituloMensaje = document.getElementById("tituloMensaje");
      tituloMensaje.innerText='';

      let contenedorError = document.getElementById("mensajeModal");
      contenedorError.innerText='';

      let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
      mensajeModalParrafo.innerText='';

      tituloMensaje.innerText = 'Hubo un inconveniente!' ;
      contenedorError.innerText ='No es posible guardar la solicitud!';
      mensajeModalParrafo.innerText ='Selecciona una hora de devolución.';

      $('#modalMensaje').modal('show');

      return false;
    }

    if (validaFechas(prestamo_fechaRetiroY, prestamo_fechaRetiroM, prestamo_fechaRetiroD,
      prestamo_fechaDevolucionY, prestamo_fechaDevolucionM, prestamo_fechaDevolucionD)) {

      spinner.style.visibility = 'hidden';
      btnIngresar.innerText="Registrar solicitud de equipo";
      btnIngresar.disabled = false;

      let tituloMensaje = document.getElementById("tituloMensaje");
      tituloMensaje.innerText='';
  
      let contenedorError = document.getElementById("mensajeModal");
      contenedorError.innerText='';

      let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
      mensajeModalParrafo.innerText='';

      tituloMensaje.innerText = 'Hubo un inconveniente!';
      contenedorError.innerText ='Rango de fechas inválido!';
      mensajeModalParrafo.innerText ='La fecha de retiro no puede ser mayor a la fecha de devolución.';
     
      $('#modalMensaje').modal('show');

      return false;
    }

    if (validaFechasHora(prestamo_fechaRetiroY, prestamo_fechaRetiroM, 
                        prestamo_fechaRetiroD, prestamo_horaRetiroH, prestamo_horaRetiroM,
                        prestamo_fechaDevolucionY, prestamo_fechaDevolucionM, 
                        prestamo_fechaDevolucionD, prestamo_horaDevolucionH,
                        prestamo_horaDevolucionM)) {

      spinner.style.visibility = 'hidden';
      btnIngresar.innerText="Registrar solicitud de equipo";
      btnIngresar.disabled = false;

      let tituloMensaje = document.getElementById("tituloMensaje");
      tituloMensaje.innerText='';
  
      let contenedorError = document.getElementById("mensajeModal");
      contenedorError.innerText='';

      let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
      mensajeModalParrafo.innerText='';

      tituloMensaje.innerText = 'Hubo un inconveniente!';
      contenedorError.innerText ='Rango de horas inválido!';
      mensajeModalParrafo.innerText ='La hora de retiro no puede ser mayor a la hora de devolución.';
     
      $('#modalMensaje').modal('show');

      return false;
    }

    if (validaFechaHoy(prestamo_fechaRetiroY, prestamo_fechaRetiroM, 
                      prestamo_fechaRetiroD)) {

      spinner.style.visibility = 'hidden';
      btnIngresar.innerText="Registrar solicitud de equipo";
      btnIngresar.disabled = false;

      let tituloMensaje = document.getElementById("tituloMensaje");
      tituloMensaje.innerText='';

      let contenedorError = document.getElementById("mensajeModal");
      contenedorError.innerText='';

      let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
      mensajeModalParrafo.innerText='';

      tituloMensaje.innerText = 'Hubo un inconveniente!';
      contenedorError.innerText ='Rango de horas inválido!';
      mensajeModalParrafo.innerText ='La fecha de retiro no puede ser menor al día de hoy ' + muestraFechaHoy();

      $('#modalMensaje').modal('show');

      return false;

      }
    

    if (validaFechaHoy(prestamo_fechaDevolucionY, prestamo_fechaDevolucionM, 
        prestamo_fechaDevolucionY)) {

        spinner.style.visibility = 'hidden';
        btnIngresar.innerText="Registrar solicitud de equipo";
        btnIngresar.disabled = false;

        let tituloMensaje = document.getElementById("tituloMensaje");
        tituloMensaje.innerText='';

        let contenedorError = document.getElementById("mensajeModal");
        contenedorError.innerText='';

        let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
        mensajeModalParrafo.innerText='';

        tituloMensaje.innerText = 'Hubo un inconveniente!';
        contenedorError.innerText ='Rango de horas inválido!';
        mensajeModalParrafo.innerText ='La fecha de devolución no puede ser menor al día de hoy ' + muestraFechaHoy();

        $('#modalMensaje').modal('show');

        return false;

      }      
                                       
      //let cboSoftware = document.getElementById("cboSoftware");
      let cboSoftwareId = new Array();
      // cboSoftwareId = [...cboSoftware.options]
      //                    .filter((x) => x.selected)
      //                    .map((x)=>x.value);

      let cboSoftwareDescripcion = new Array();
      // let cboSoftwareDescripcion = [...cboSoftware.options]
      //                  .filter((x) => x.selected)
      //                  .map((x)=>x.innerText);                           
  
      // if (cboSoftwareDescripcion.length == 0) {
        
      //   spinner.style.visibility = 'hidden';
      //   btnIngresar.innerText="Registrar solicitud de equipo";
      //   btnIngresar.disabled = false;

      //   let tituloMensaje = document.getElementById("tituloMensaje");
      //   tituloMensaje.innerText='';

      //   let contenedorError = document.getElementById("mensajeModal");
      //   contenedorError.innerText='';

      //   let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
      //   mensajeModalParrafo.innerText='';

      //   tituloMensaje.innerText = 'Hubo un inconveniente!';
      //   contenedorError.innerText ='No ha seleccionado ningún Software Educativo';
      //   mensajeModalParrafo.innerText ='Por favor seleccione uno o varios Software Educativos';

      //   $('#modalMensaje').modal('show');

      //   return false;

      // }

      let cboSeccionDescripcion = "";
      let cboSeccionId = 0;
      // let cboSeccionDescripcion = $('#cboSeccion option:selected').text()
      // let cboSeccionId = $('#cboSeccion option:selected').val();
      
      // if (cboSeccionId < 1) {
        
      //   spinner.style.visibility = 'hidden';
      //   btnIngresar.innerText="Registrar solicitud de equipo";
      //   btnIngresar.disabled = false;

      //   let tituloMensaje = document.getElementById("tituloMensaje");
      //   tituloMensaje.innerText='';

      //   let contenedorError = document.getElementById("mensajeModal");
      //   contenedorError.innerText='';

      //   let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
      //   mensajeModalParrafo.innerText='';

      //   tituloMensaje.innerText = 'Hubo un inconveniente!';
      //   contenedorError.innerText ='No ha seleccionado la sección';
      //   mensajeModalParrafo.innerText ='Por favor seleccione una sección de la Lista de Secciones';

      //   $('#modalMensaje').modal('show');

      //   return false;

      // }

      if (solicitud_uso.length < 5) {

        spinner.style.visibility = 'hidden';
        btnIngresar.innerText="Registrar solicitud de equipo";
        btnIngresar.disabled = false;

        let tituloMensaje = document.getElementById("tituloMensaje");
        tituloMensaje.innerText='';

        let contenedorError = document.getElementById("mensajeModal");
        contenedorError.innerText='';

        let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
        mensajeModalParrafo.innerText='';

        tituloMensaje.innerText = 'Hubo un inconveniente!';
        contenedorError.innerText ='Es necesario hacer una descripción del uso que se le dará al equipo';
        mensajeModalParrafo.innerText ='Por favor digite la descripción con más detalle';

        $('#modalMensaje').modal('show');

        return false;

      }

      let txtBoletaRO = document.getElementById("txtBoleta").readOnly;
      let booBoleta = 0;
      let txtBoleta = "";
      
      if (!txtBoletaRO && txtBoletaRO.length < 5) {

        spinner.style.visibility = 'hidden';
        btnIngresar.innerText="Registrar solicitud";
        btnIngresar.disabled = false;

        let tituloMensaje = document.getElementById("tituloMensaje");
        tituloMensaje.innerText='';

        let contenedorError = document.getElementById("mensajeModal");
        contenedorError.innerText='';

        let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
        mensajeModalParrafo.innerText='';

        tituloMensaje.innerText = 'Hubo un inconveniente!';
        contenedorError.innerText ='Al habilitar la opción de Generar Boleta para Oficial de Seguridad, ' + 
                                  'es necesario hacer una descripción del uso externo que se le dará al Activo';
        mensajeModalParrafo.innerText ='Por favor digite la descripción con más detalle';

        $('#modalMensaje').modal('show');

        return false;

      }

      if (!txtBoletaRO) {
        booBoleta = 1;
        txtBoleta = $('#txtBoleta').val(); 
      }

    window.userData = [];
    userData = window.sessionStorage.getItem('sesion');

    let nombre = "";
    let codigo = "";
    let cedula = "";
    let jsonData = [];

    jsonData = JSON.parse(userData);

    nombre = jsonData["Nombre"] + " " + jsonData["Apellido1"] + " " + jsonData["Apellido2"];
    codigo = jsonData["CentrosEducativosDondeTrabaja"];
    cedula = jsonData["EMPCED"];
    para =  jsonData["Correo_Electronico_Oficial"];
    Dependencia = jsonData["Dependencia"];                                         

    let cards = document.querySelectorAll('.card')
    
    let json_solicitud_detalle = new Array();
    let arrayAliasNombre = new Array();
    
    for (let i = 0; i < cards.length; i++) {    
      
      if (cards[i].getAttribute('data-tipo')=="alias") {

          if (cards[i].hasAttribute('data-id')) {

              let solicitud_detalle = new Array();      
              let dataAlias_id = cards[i].getAttribute('data-id');
              arrayAliasNombre.push(cards[i].getAttribute('data-aliasNombre'));          
              //let elem = document.getElementById(dataAlias_id);                    
              //let cantidad = elem.getElementsByTagName("select")[0].value;
              let cantidad = 1;
            
              solicitud_detalle = {
                                  "solicitud_detalle_alias_id":dataAlias_id,
                                  "solicitud_detalle_cantidad":cantidad
                                  };

              json_solicitud_detalle.push(solicitud_detalle);

          }

        }
        
    }

    let json_solicitud_detalleActivos = new Array();
    json_solicitud_detalleActivos = solicitud_Detalle_Activo();
   
    if ( (json_solicitud_detalle && json_solicitud_detalle.length>0) || 
         (json_solicitud_detalleActivos && json_solicitud_detalleActivos.length>0)) {

      const formData = new FormData();    
      const json = JSON.stringify(json_solicitud_detalle);
      const jsonActivos = JSON.stringify(json_solicitud_detalleActivos);

      const jsonNombreAlias = JSON.stringify(arrayAliasNombre);      
      const jsonNombreSoftware = JSON.stringify(cboSoftwareDescripcion);
      const jsonSoftwareId = JSON.stringify(cboSoftwareId);
      
      formData.append('solicitud_fechaRetiro', prestamo_fechaRetiro);
      formData.append('solicitud_horaRetiro', prestamo_horaRetiro);
      formData.append('solicitud_fechaDevolucion', prestamo_fechaDevolucion);
      formData.append('solicitud_horaDevolucion', prestamo_horaDevolucion);
      formData.append('solicitud_uso', solicitud_uso);
      formData.append('arrayArticulos', json);
      formData.append('solicitud_cedula_funcionario', cedula);
      formData.append('solicitud_nombre_funcionario', nombre);
      formData.append('solicitud_codigo_presupuestario', codigo);
      formData.append('para', para);
      formData.append('arrayActivos', jsonActivos);
      formData.append('Dependencia', Dependencia);

      formData.append('arrayNombreAlias', jsonNombreAlias);
      formData.append('arraySoftwareDescripcion', jsonNombreSoftware);
      formData.append('arraySoftwareId', jsonSoftwareId);

      formData.append('seccionDescripcion', cboSeccionDescripcion);
      formData.append('seccion_Id', cboSeccionId);
      formData.append('solicitud_uso_externo', txtBoleta);
      formData.append('solicitud_boleta', booBoleta);

      fetch('sql/insertSolicitudGestor.php', {
      method: 'POST', 
      body: formData,     
      }).then(function(response) {

      if(response.ok) {

      response.text().then(function(data) {

          //console.log(data);
                                   
      }).catch(function(error) {

          spinner.style.visibility = 'hidden';
          btnIngresar.innerText="Registrar solicitud de equipo";
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
        
          $('#modalMensaje').modal('show');

          return false;

      })

      } else {

              spinner.style.visibility = 'hidden';
              btnIngresar.innerText="Registrar solicitud de equipo";
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
            
              $('#modalMensaje').modal('show');

              return false;
      
      }

      }).catch(function(error) {

            spinner.style.visibility = 'hidden';
            btnIngresar.innerText="Registrar solicitud de equipo";
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
            
            $('#modalMensaje').modal('show');

            return false;
         
      })
      .then();

      sessionStorage.removeItem("sesionCanasta");
      sessionStorage.removeItem("sesionActivo");
      sessionStorage.removeItem("postSolicitud");
      sessionStorage.removeItem("postSolicitudActivo");          

      } else {
       
        spinner.style.visibility = 'hidden';
        btnIngresar.innerText="Registrar solicitud de equipo";
        btnIngresar.disabled = false;

        let tituloMensaje = document.getElementById("tituloMensaje");
        tituloMensaje.innerText='';
    
        let contenedorError = document.getElementById("mensajeModal");
        contenedorError.innerText='';

        let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
        mensajeModalParrafo.innerText='';

        tituloMensaje.innerText = 'Hubo un inconveniente!';
        contenedorError.innerText ='Al parecer no hay artículos!';
        mensajeModalParrafo.innerText = 'Agrega los artículos a la solcitud con el botón Agregar Artículo';
        
        $('#modalMensaje').modal('show');

        return false;   
      }

      spinner.style.visibility = 'hidden';
      btnIngresar.innerText="Registrar solicitud de equipo";

      let tituloMensaje = document.getElementById("tituloMensajeGuardar");
      tituloMensaje.innerText='';
  
      let contenedorError = document.getElementById("mensajeModalGuardar");
      contenedorError.innerText='';

      let mensajeModalParrafo = document.getElementById("mensajeModalParrafoGuardar");
      mensajeModalParrafo.innerText='TecnoPresta le enviará una notificación a su correo institucional';

      tituloMensaje.innerText = 'Ok!';
      contenedorError.innerText ='Se registró la solicitud!';      
      
      $('#modalMensajeGuardar').modal('show');

      return true;
  }

  function cantidad() {

    let cards = document.querySelectorAll('.card')
    
    let json_solicitud_detalle = new Array();
    
    for (let i = 0; i < cards.length; i++) {    
      
      if (cards[i].getAttribute('data-tipo')=="alias") {

        if (cards[i].hasAttribute('data-id')) {    
            let solicitud_detalle = new Array();      
            let dataAlias_id = cards[i].getAttribute('data-id');          
            let elem = document.getElementById(dataAlias_id);                    
            let cantidad = elem.getElementsByTagName("select")[0].value;
            solicitud_detalle["solicitud_detalle_alias_id"]=dataAlias_id;
            solicitud_detalle["solicitud_detalle_cantidad"]=cantidad;
            json_solicitud_detalle.push(solicitud_detalle);
        }

      }

    }        
    
  }

  function salir() {

    // window.location.assign('formulario_menu_prestamo.html');
    window.location.assign('navegar.php?ruta=formulario_buscar_alias_n.php');
    return true;


  }
  