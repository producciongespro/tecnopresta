let cantidad = 0;
let txtBuscar = document.getElementById("txtBuscar");
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

  let buscarNombre = "";
  buscarNombre = window.sessionStorage.getItem('buscarNombre');
  if (buscarNombre != "") {
    $('#txtBuscar').val(buscarNombre);
    window.sessionStorage.setItem('buscarNombre',"");//limpia la variable 
    //para que no quede con el valor de busqueda
    
  }
  
  muestraContador();  
  buscar();
      
  return false;

}

function getval(sel){             
    //alert(sel);
    cantidad = sel;           
}

txtBuscar.addEventListener("keypress", 
function(e) {
  if(e.keyCode == 13) {
    e.preventDefault();
    buscar();
    return false;
  }
});

document.getElementById("btnLimpiarBusqueda").addEventListener("click", function() {
  limpiarBusqueda();
});

function limpiarBusqueda() {
  txtBuscar.value = '';
  document.getElementById("btnLimpiarBusqueda").style.display = 'none';
  cargaDatosBd('');
}

txtBuscar.addEventListener("keydown", 
function(e) {

  if (e.keyCode === 27) {
    limpiarBusqueda();
    e.preventDefault();
    return false;
  }

  let allowedCode = [8, 13, 27, 32, 37, 39, 44, 45, 46, 95];
  let charCode = (e.charCode) ? e.charCode : ((e.keyCode) ? e.keyCode :
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
  
    let allowedCode = [8, 13, 32, 37, 39, 44, 45, 46, 95];
    let charCode = (e.charCode) ? e.charCode : ((e.keyCode) ? e.keyCode :
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
    return false;
  }

  var btnClear = document.getElementById("btnLimpiarBusqueda");
  if (btnClear) {
    btnClear.style.display = (txtBuscar.value.trim() !== '') ? '' : 'none';
  }

});

function getModuleParams() {
  let params = new URLSearchParams(window.location.search);
  let sid = params.get('subsistema_id');
  let mid = params.get('modulo_id');
  if (sid && mid) {
    return '&subsistema_id=' + sid + '&modulo_id=' + mid;
  }
  return '';
}

function formularioCanasta() {

  let tituloMensaje = document.getElementById("tituloMensaje");
  tituloMensaje.innerText='';
  
  let contenedorError = document.getElementById("mensajeModal");
  contenedorError.innerText='';

  let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
  mensajeModalParrafo.innerText='';

  let userData = [];
  let userDataActivo = [];

  userData = window.sessionStorage.getItem('sesionCanasta');
  userDataActivo = window.sessionStorage.getItem('sesionActivo');
  
  let jsonDataincludes = [];
  jsonDataincludes = JSON.parse(userData);

  let jsonDataincludesActivo = [];
  jsonDataincludesActivo = JSON.parse(userDataActivo);
  
  
  if ((jsonDataincludes && jsonDataincludes.length>0) || (jsonDataincludesActivo && jsonDataincludesActivo.length>0)) {

    // window.location.href = "navegar.php?ruta=formulario_solicitud_canasta_n.php" + getModuleParams();
    window.location.href = "formulario_solicitud_canasta_n.php";
  
  } else {

    tituloMensaje.innerText = 'Hubo un inconveniente!' ;
    contenedorError.innerText ='No es posible mostrar el Carrito!'
    mensajeModalParrafo.innerText ='Parece que no has agregado ningún artículo aún.';                           
    
    $('#modalMensaje').modal('show');
  }

  return false;

}

function botonEnviaSolicitud(jsonArray, cantidad) {
    
  let jsonCanasta = [];
 
  jsonArray["cantidad"]=cantidad;
  jsonCanasta.push(jsonArray);

  let json = JSON.stringify(jsonCanasta);
  
  window.sessionStorage.setItem('postSolicitud',json);
  window.sessionStorage.setItem('botonEnviaSolicitud',true);
  // window.location.assign('navegar.php?ruta=formulario_solicitud_canasta_n.php' + getModuleParams());
  window.location.assign('formulario_solicitud_canasta_n.php');
  return true;
  
}

function botonEnviaSolicitudActivo(jsonArray) {
  
  let jsonActivo = [];
  jsonActivo.push(jsonArray);

  let json = JSON.stringify(jsonActivo);
  
  window.sessionStorage.setItem('postSolicitudActivo',json);
  window.sessionStorage.setItem('botonEnviaSolicitud',true);
  // window.location.assign('navegar.php?ruta=formulario_solicitud_n.php' + getModuleParams());
  window.location.assign('formulario_solicitud_n.php');
  return true;
  
}

function agregarActivo(jsonArray) {
  
  let tituloMensaje = document.getElementById("tituloMensaje");
  tituloMensaje.innerHTML='';

  let contenedorError = document.getElementById("mensajeModal");
  contenedorError.innerHTML='';

  let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
  mensajeModalParrafo.innerText='';
  
  let jsonActivo = [];
  let userData = [];
  
  userData = window.sessionStorage.getItem('sesionActivo');

  

  if (userData && userData.length>0) {

    let jsonDataincludes = [];
    jsonDataincludes = JSON.parse(userData);
    
    let magenicVendor = jsonDataincludes.find(jsonDataincludes => jsonDataincludes['id_placa'] === jsonArray.id_placa);
    
    if (typeof magenicVendor !== 'undefined') {
      
      tituloMensaje.innerText = 'Hubo un inconveniente!' ;
      contenedorError.innerText = 'Parece que ya agregaste ' + magenicVendor.clase + " " + 
                                                              magenicVendor.marca + " " + 
                                                              magenicVendor.modelo;
      
      $('#modalMensaje').modal('show');

      return false;
    }
  
    jsonActivo = jsonDataincludes; //si ya existe datos en sessionStorage

  }

  jsonActivo.push(jsonArray);

  let json = JSON.stringify(jsonActivo);
  window.sessionStorage.setItem('sesionActivo',json);

  muestraContador();

  tituloMensaje.innerText = 'Ok!';
  contenedorError.innerHTML='Has agregado ' + jsonArray.clase + ' al Carrito.';
        
  $('#modalMensaje').modal('show');
  
  return true;
  

}

function agregar(jsonArray, cantidad) {
  
  let tituloMensaje = document.getElementById("tituloMensaje");
  tituloMensaje.innerHTML='';

  let contenedorError = document.getElementById("mensajeModal");
  contenedorError.innerHTML='';

  let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
  mensajeModalParrafo.innerText='';
  
  let jsonCanasta = [];
  let userData = [];
  
  userData = window.sessionStorage.getItem('sesionCanasta');

  if (userData && userData.length>0) {

    let jsonDataincludes = [];
    jsonDataincludes = JSON.parse(userData);
        
    let magenicVendor = jsonDataincludes.find( jsonDataincludes => jsonDataincludes['alias_id'] === jsonArray.alias_id );
    
    if (typeof magenicVendor !== 'undefined') {
      
      tituloMensaje.innerText = 'Hubo un inconveniente!' ;
      contenedorError.innerText = 'Parece que ya agregaste ' + magenicVendor.alias;
      
      $('#modalMensaje').modal('show');

      return false;
    }
  
    jsonCanasta = jsonDataincludes; //si ya existe datos en sessionStorage

  }

  jsonArray["cantidad"]=cantidad; //Agrega la cantidad de cboCantidad
  jsonCanasta.push(jsonArray);

  let json = JSON.stringify(jsonCanasta);
  window.sessionStorage.setItem('sesionCanasta',json);
  
  
  muestraContador();

  tituloMensaje.innerText = 'Ok!';
  contenedorError.innerHTML='Has agregado ' + cantidad +                           
                                ' ' + jsonArray.alias + ' al Carrito.';
        
  $('#modalMensaje').modal('show');
  
  return true;
  

}

function muestraContador() {

  let contador = document.getElementById("contador");
  let userDataCanasta = [];
  let userDataActivo = [];
  let intcontadorAlias = 0;
  let intcontadorActivo = 0;
  let total = 0;

  userDataCanasta = window.sessionStorage.getItem('sesionCanasta');
  userDataActivo = window.sessionStorage.getItem('sesionActivo');
  
  if (userDataCanasta && userDataCanasta.length>0) {
    let jsonData = [];
    jsonData = JSON.parse(userDataCanasta);  
    intcontadorAlias = jsonData.length;
  }
  
  if (userDataActivo && userDataActivo.length>0) {
    let jsonData = [];
    jsonData = JSON.parse(userDataActivo);  
    intcontadorActivo = jsonData.length;
  } 
  
  total = intcontadorAlias + intcontadorActivo;
 
  contador.innerText = total;

}

function buscar() {

  let contenedorError = document.getElementById("mensaje");
  contenedorError.innerHTML='';
  
  let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
  mensajeModalParrafo.innerText='';

  let btnBuscar = document.getElementById("btnBuscar");
   
  btnBuscar.disabled = true;
  
  btnBuscar.innerHTML = '<span id="spinner" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
  let spinner = document.getElementById("spinner"); 
  let activo_nombre = $('#txtBuscar').val();

  cargaDatosBd(activo_nombre);

  spinner.style.visibility = 'hidden';
  btnBuscar.innerHTML='<i class="bi bi-search"></i>';
  btnBuscar.disabled = false;

  document.getElementById("btnLimpiarBusqueda").style.display = '';

}

function cargaDatosPantalla(rs) {
  rs.forEach(obj => {
    let colCard = document.createElement('div');
    colCard.className = "col-6 col-sm-6 col-md-4 col-lg-3 col-xl-3 mb-4";

    let card = document.createElement('div');
    card.className = "card h-100";

    let cardimg = document.createElement('img');
    cardimg.className = "card-img-mep";
    cardimg.src = './img/alias/' + obj.alias_imagen;

    let cardbody = document.createElement('div');
    cardbody.className = "card-body d-flex flex-column";

    let contenedorTituloImagen = document.createElement('div');
    contenedorTituloImagen.className = "text-center mb-3";

    let h4 = document.createElement('h4');
    h4.className = "card-title fw-semibold fs-5 mb-0";
    let createATextNombre = document.createTextNode(obj.alias);
    h4.appendChild(createATextNombre);

    contenedorTituloImagen.appendChild(h4);

    let spacer = document.createElement('div');
    spacer.className = "mt-auto";

    let botonAgregar = document.createElement('a');
    botonAgregar.id = "btnAgregar";
    botonAgregar.className = "btn btn-primary w-100 mb-2";
    botonAgregar.href = "javascript:void(0)";
    botonAgregar.onclick = function () {
      agregar(obj, 1);
    };
    let createATextBotonAgregar = document.createTextNode("Agregar al Carrito");
    botonAgregar.appendChild(createATextBotonAgregar);

    let botonSolicitud = document.createElement('a');
    botonSolicitud.id = "btnAgregar";
    botonSolicitud.className = "btn btn-outline-mep w-100";
    botonSolicitud.href = "javascript:void(0)";
    botonSolicitud.onclick = function () {
      botonEnviaSolicitud(obj, 1);
    };
    let createATextBotonSolicitud = document.createTextNode("Solicitar ahora");
    botonSolicitud.appendChild(createATextBotonSolicitud);

    cardbody.appendChild(contenedorTituloImagen);
    cardbody.appendChild(spacer);
    cardbody.appendChild(botonAgregar);
    cardbody.appendChild(botonSolicitud);
    card.appendChild(cardimg);
    card.appendChild(cardbody);
    colCard.appendChild(card);

    document.getElementById('fila').appendChild(colCard);
  });

  return false;
}

function cargaDatosPantallaActivo(rs) {
  rs.forEach(obj => {
    let colCard = document.createElement('div');
    colCard.className = "col-6 col-sm-6 col-md-4 col-lg-3 col-xl-3 mb-4";

    let card = document.createElement('div');
    card.className = "card h-100";

    let cardimg = document.createElement('img');
    cardimg.className = "card-img-mep";
    cardimg.src = './img/' + obj.imagen;

    let cardbody = document.createElement('div');
    cardbody.className = "card-body d-flex flex-column";

    let contenedorTituloImagen = document.createElement('div');
    contenedorTituloImagen.className = "text-center mb-2";

    let h4 = document.createElement('p');
    h4.className = "card-title fw-semibold fs-5 mb-0";
    let createATextNombre = document.createTextNode(obj.clase);
    h4.appendChild(createATextNombre);

    contenedorTituloImagen.appendChild(h4);

    let infoSection = document.createElement('div');
    infoSection.className = "mb-3";

    let hMarca = document.createElement('p');
    hMarca.className = "text-muted small mb-1";
    let createATextNombreMarca = document.createTextNode(obj.marca);
    hMarca.appendChild(createATextNombreMarca);
    infoSection.appendChild(hMarca);

    let hPlaca = document.createElement('p');
    hPlaca.className = "text-muted small mb-1";
    let createATextNombrePlaca = document.createTextNode("Placa: " + obj.placa);
    hPlaca.appendChild(createATextNombrePlaca);
    infoSection.appendChild(hPlaca);

    let etiqueta = "";
    if (obj.numero_activo === null) {
      etiqueta = "Etiqueta: Por asignar";
    } else {
      etiqueta = "Etiqueta: " + obj.numero_activo;
    }

    let hnumero_Activo = document.createElement('p');
    hnumero_Activo.className = "text-muted small mb-0";
    let createATextNombrenumero_Activo = document.createTextNode(etiqueta);
    hnumero_Activo.appendChild(createATextNombrenumero_Activo);
    infoSection.appendChild(hnumero_Activo);

    let spacer = document.createElement('div');
    spacer.className = "mt-auto";

    let botonAgregar = document.createElement('a');
    botonAgregar.id = "btnAgregar";
    botonAgregar.className = "btn btn-primary w-100 mb-2";
    botonAgregar.href = "javascript:void(0)";
    botonAgregar.onclick = function () {
      agregarActivo(obj);
    };
    let createATextBotonAgregar = document.createTextNode("Agregar al Carrito");
    botonAgregar.appendChild(createATextBotonAgregar);

    let botonSolicitud = document.createElement('a');
    botonSolicitud.id = "btnAgregar";
    botonSolicitud.className = "btn btn-outline-mep w-100";
    botonSolicitud.href = "javascript:void(0)";
    botonSolicitud.onclick = function () {
      botonEnviaSolicitudActivo(obj);
    };
    let createATextBotonSolicitud = document.createTextNode("Solicitar ahora");
    botonSolicitud.appendChild(createATextBotonSolicitud);

    cardbody.appendChild(contenedorTituloImagen);
    cardbody.appendChild(infoSection);
    cardbody.appendChild(spacer);
    cardbody.appendChild(botonAgregar);
    cardbody.appendChild(botonSolicitud);
    card.appendChild(cardimg);
    card.appendChild(cardbody);
    colCard.appendChild(card);

    document.getElementById('fila').appendChild(colCard);
  });

  return false;
}

function cargaDatosBd(activo_nombre) {            
    
    $('#fila').empty();    
    
    fetch('sql/selectActivoAliasGestor.php?'
    + new URLSearchParams({aliasActivo: activo_nombre, codigo: codigoPresupuestario}))
    .then(function(response) {

      if(response.ok) {
  
        response.json().then(function(data) {  
            
         //console.log(data);
         if (Object.keys(data).length>0) {

          cargaDatosPantalla(data);

         } else {
          
          /* document.getElementById('txtBuscar').value = '';
          $('.card').remove();

          let contenedorError = document.getElementById("mensaje");
          contenedorError.innerHTML='<div class="alert alert-danger">' +
                                  '<strong>Error! </strong>' +
                                      'No se encontró el artículo' +
                                  '</div>'; */    
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
                           
    fetch('sql/selectActivoGestor.php?'
    + new URLSearchParams({aliasActivo: activo_nombre, codigo: codigoPresupuestario}))
    .then(function(response) {

      if(response.ok) {
  
        response.json().then(function(data) {  
            
         //console.log(data);
         if (Object.keys(data).length>0) {

          cargaDatosPantallaActivo(data);

         } else {
          
          /* document.getElementById('txtBuscar').value = '';
          $('.card').remove();
          let contenedorError = document.getElementById("mensaje");
          contenedorError.innerHTML='<div class="alert alert-danger">' +
                                  '<strong>Error! </strong>' +
                                      'No se encontró el artículo' +
                                  '</div>';  */   
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
    
    //console.log(jsonData);

  } else {
    
    //codigoPresupuestario = "5300";
    return false;    

  }

  return true;

}
