let txtBuscar = document.getElementById("txtBuscar");
let alias_id = 0;
let codigoPresupuestario;
let id_placa = 0;

txtBuscar.addEventListener("keydown", 
function(e) {

  if (e.keyCode === 27) {
    limpiarBusqueda();
    e.preventDefault();
    return false;
  }

  var allowedCode = [8, 13, 27, 32, 37, 39, 44, 45, 46, 95];
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
    return false;
  }

  var btnClear = document.getElementById("btnLimpiarBusqueda");
  if (btnClear) {
    btnClear.style.display = (txtBuscar.value.trim() !== '') ? '' : 'none';
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

document.getElementById("btnLimpiarBusqueda").addEventListener("click", function() {
  limpiarBusqueda();
});

function limpiarBusqueda() {
  txtBuscar.value = '';
  $('#filaResultados').empty();
  $('#resultadosDivider, #resultadosBusqueda').hide();
  document.getElementById("btnLimpiarBusqueda").style.display = 'none';
  txtBuscar.focus();
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
                                
    return false;    

  }

  return true;

}

function solicitud() {       
  
  window.sessionStorage.setItem('botonEnviaSolicitud',false);
  window.location.href = 'formulario_solicitud_n.php';

  return false;
}

function modificar(jsonArray, cantidad) {
   
  let jsonCanasta = [];
  let userData = [];
  
  userData = window.sessionStorage.getItem('sesionCanasta');

  let jsonDataincludes = [];
  jsonDataincludes = JSON.parse(userData);
      
  let indice = jsonDataincludes.indexOf(jsonDataincludes.find( jsonDataincludes => jsonDataincludes['alias_id'] === jsonArray.alias_id ));
  jsonDataincludes.splice(indice,1);
  
  jsonCanasta = jsonDataincludes; 

  jsonArray["cantidad"]=cantidad; //Agrega la cantidad de cboCantidad
  jsonCanasta.push(jsonArray);

  let json = JSON.stringify(jsonCanasta);
  window.sessionStorage.setItem('sesionCanasta',json);
    
  return true;
  
}

function buscar() {
  let activo_nombre = $('#txtBuscar').val();
  if (!activo_nombre || activo_nombre.trim() === '') return false;

  $('#filaResultados').empty();
  $('#resultadosDivider, #resultadosBusqueda').show();
  document.getElementById("btnLimpiarBusqueda").style.display = '';

  fetch('sql/selectActivoAliasGestor.php?' + new URLSearchParams({aliasActivo: activo_nombre, codigo: codigoPresupuestario}))
    .then(function(response) {
      if (response.ok) {
        response.json().then(function(data) {
          if (Object.keys(data).length > 0) {
            renderResultadosAlias(data);
          }
        });
      }
    }).catch(function(err) {
      console.error(err);
    });

  fetch('sql/selectActivoGestor.php?' + new URLSearchParams({aliasActivo: activo_nombre, codigo: codigoPresupuestario}))
    .then(function(response) {
      if (response.ok) {
        response.json().then(function(data) {
          if (Object.keys(data).length > 0) {
            renderResultadosActivo(data);
          }
        });
      }
    }).catch(function(err) {
      console.error(err);
    });

  return false;
}

function renderResultadosAlias(rs) {
  rs.forEach(function(obj) {
    let card = document.createElement('div');
    card.className = "cart-item-card d-flex align-items-center p-3";

    let cardimg = document.createElement('img');
    cardimg.className = "cart-item-img me-3";
    cardimg.src = './img/alias/' + obj.alias_imagen;

    let bodyDiv = document.createElement('div');
    bodyDiv.className = "flex-grow-1";
    bodyDiv.innerHTML = '<div class="fw-semibold">' + obj.alias + '</div>';

    let btnAgregar = document.createElement('button');
    btnAgregar.type = "button";
    btnAgregar.className = "btn-agregar-resultado ms-2";
    btnAgregar.innerText = "+ Agregar";
    btnAgregar.onclick = new Function("agregarDesdeResultado(" + JSON.stringify(obj) + ");");

    card.appendChild(cardimg);
    card.appendChild(bodyDiv);
    card.appendChild(btnAgregar);

    document.getElementById('filaResultados').appendChild(card);
  });
}

function renderResultadosActivo(rs) {
  rs.forEach(function(obj) {
    let card = document.createElement('div');
    card.className = "cart-item-card d-flex align-items-center p-3";

    let cardimg = document.createElement('img');
    cardimg.className = "cart-item-img me-3";
    cardimg.src = './img/' + obj.imagen;

    let bodyDiv = document.createElement('div');
    bodyDiv.className = "flex-grow-1";
    let info = obj.clase;
    if (obj.marca) info += ' · ' + obj.marca;
    if (obj.placa) info += ' · ' + obj.placa;
    bodyDiv.innerHTML = '<div class="fw-semibold">' + info + '</div>';

    let btnAgregar = document.createElement('button');
    btnAgregar.type = "button";
    btnAgregar.className = "btn-agregar-resultado ms-2";
    btnAgregar.innerText = "+ Agregar";
    btnAgregar.onclick = new Function("agregarActivoDesdeResultado(" + JSON.stringify(obj) + ");");

    card.appendChild(cardimg);
    card.appendChild(bodyDiv);
    card.appendChild(btnAgregar);

    document.getElementById('filaResultados').appendChild(card);
  });
}

function agregarDesdeResultado(obj) {
  let userData = window.sessionStorage.getItem('sesionCanasta');
  let jsonCanasta = userData ? JSON.parse(userData) : [];

  let existe = jsonCanasta.some(function(item) { return item.alias_id === obj.alias_id; });
  if (existe) {
    Swal.fire({icon: 'info', title: 'Ya está en el carrito', timer: 1500, showConfirmButton: false});
    return false;
  }

  obj.cantidad = 1;
  jsonCanasta.push(obj);
  window.sessionStorage.setItem('sesionCanasta', JSON.stringify(jsonCanasta));

  muestraContador();
  $('#filaResultados').empty();
  $('#resultadosDivider, #resultadosBusqueda').hide();
  $('#colCards').empty();
  cargaDatosPantalla();
  cargaDatosPantallaActivo();

  Swal.fire({icon: 'success', title: 'Agregado al carrito', timer: 1000, showConfirmButton: false});
  return true;
}

function agregarActivoDesdeResultado(obj) {
  let userData = window.sessionStorage.getItem('sesionActivo');
  let jsonActivo = userData ? JSON.parse(userData) : [];

  let existe = jsonActivo.some(function(item) { return item.id_placa === obj.id_placa; });
  if (existe) {
    Swal.fire({icon: 'info', title: 'Ya está en el carrito', timer: 1500, showConfirmButton: false});
    return false;
  }

  jsonActivo.push(obj);
  window.sessionStorage.setItem('sesionActivo', JSON.stringify(jsonActivo));

  muestraContador();
  $('#filaResultados').empty();
  $('#resultadosDivider, #resultadosBusqueda').hide();
  $('#colCards').empty();
  cargaDatosPantalla();
  cargaDatosPantallaActivo();

  Swal.fire({icon: 'success', title: 'Agregado al carrito', timer: 1000, showConfirmButton: false});
  return true;
}

window.onload = function() {
  
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
  
  muestraContador();
  $('#colCards').empty();
  cargaDatosPantalla();
  cargaDatosPantallaActivo();  
        
  return false;

}

function muestraContador() {

  /* let contador = document.getElementById("contador");

  let userDataCanasta = [];
  userDataCanasta = window.sessionStorage.getItem('sesionCanasta');
  
  if (userDataCanasta && userDataCanasta.length>0) {
    let jsonData = [];
    jsonData = JSON.parse(userDataCanasta);  
    contador.innerText = jsonData.length;

    
  } else {

    contador.innerText = "0";
    
  } */

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

  
  return false;

}

function quitarElementoArray() {

  let jsonData = JSON.parse(window.sessionStorage.getItem('sesionCanasta'));
    
  let newjsonData = jsonData.filter(item=>item.alias_id!=alias_id);
  
  let jsonCanasta = [];
  jsonCanasta = newjsonData;

  let json = JSON.stringify(jsonCanasta);
  window.sessionStorage.setItem('sesionCanasta',json);

  $("#modalMensajeSiNo").modal('hide');

  muestraContador();
  $('#colCards').empty();
  cargaDatosPantalla();
  cargaDatosPantallaActivo(); 
    
  return false;

}

function quitarElementoArrayArticulo() {

  let jsonData = JSON.parse(window.sessionStorage.getItem('sesionActivo'));
    
  let newjsonData = jsonData.filter(item=>item.id_placa!=id_placa);
  
  let jsonCanasta = [];
  jsonCanasta = newjsonData;

  let json = JSON.stringify(jsonCanasta);
  window.sessionStorage.setItem('sesionActivo',json);

  $("#modalMensajeSiNoArticulo").modal('hide');

  muestraContador();
  $('#colCards').empty();
  cargaDatosPantalla();
  cargaDatosPantallaActivo(); 
    
  return false;

}

  function botonQuitar(arrayArticulo) {

    let tituloMensaje = document.getElementById("tituloMensajeSiNo");
    tituloMensaje.innerText='';

    let contenedorError = document.getElementById("mensajeModalSiNo");
    contenedorError.innerText='';
    
    tituloMensaje.innerText = 'Aviso importante!' ;
    contenedorError.innerText = 'Realmente desea quitar ' + arrayArticulo["alias"] + ' del Carrito ?';
    alias_id=arrayArticulo["alias_id"]; 

    $("#modalMensajeSiNo").modal('show');

    return false;

  }  
  
  function botonQuitarActivo(arrayArticulo) {

    let tituloMensaje = document.getElementById("tituloMensajeSiNoArticulo");
    tituloMensaje.innerText='';

    let contenedorError = document.getElementById("mensajeModalSiNoArticulo");
    contenedorError.innerText='';
    
    tituloMensaje.innerText = 'Aviso importante!' ;
    contenedorError.innerText = 'Realmente desea quitar ' + arrayArticulo["clase"] + ' del Carrito ?';

    id_placa=arrayArticulo["id_placa"];   

    $("#modalMensajeSiNoArticulo").modal('show');

    return false;

  }  

function cargaDatosPantalla() {
    let userDataCanasta = window.sessionStorage.getItem('sesionCanasta');

    if (userDataCanasta && userDataCanasta.length > 0) {
      let jsonData = JSON.parse(userDataCanasta);

      jsonData.forEach(function(obj) {
        let card = document.createElement('div');
        card.className = "cart-item-card d-flex align-items-center p-3";
        card.id = obj.alias;

        let cardimg = document.createElement('img');
        cardimg.className = "cart-item-img me-3";
        cardimg.src = './img/alias/' + obj.alias_imagen;

        let bodyDiv = document.createElement('div');
        bodyDiv.className = "flex-grow-1";
        bodyDiv.innerHTML = '<div class="fw-semibold">' + obj.alias + '</div>';

        let btnQuitar = document.createElement('button');
        btnQuitar.type = "button";
        btnQuitar.className = "btn-quitar-item ms-2";
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
  

function cargaDatosPantallaActivo() {
    let userDataActivo = window.sessionStorage.getItem('sesionActivo');

    if (userDataActivo && userDataActivo.length > 0) {
      let jsonData = JSON.parse(userDataActivo);

      jsonData.forEach(function(obj) {
        let card = document.createElement('div');
        card.className = "cart-item-card d-flex align-items-center p-3";
        card.id = obj.id_placa;
        card.setAttribute('data-id', obj.id_placa);
        card.setAttribute('data-tipo', "activo");

        let cardimg = document.createElement('img');
        cardimg.className = "cart-item-img me-3";
        cardimg.src = './img/' + obj.imagen;

        let bodyDiv = document.createElement('div');
        bodyDiv.className = "flex-grow-1";

        let etiqueta = (obj.numero_activo === null) ? "Por asignar" : obj.numero_activo;
        let infoHtml = '<div class="fw-semibold">' + obj.clase + '</div>';
        infoHtml += '<div class="text-muted small">' + obj.marca;
        if (obj.placa) infoHtml += ' · Placa: ' + obj.placa;
        infoHtml += ' · Etiqueta: ' + etiqueta + '</div>';
        bodyDiv.innerHTML = infoHtml;

        let btnQuitar = document.createElement('button');
        btnQuitar.type = "button";
        btnQuitar.className = "btn-quitar-item ms-2";
        btnQuitar.setAttribute('aria-label', 'Quitar');
        btnQuitar.onclick = new Function("botonQuitarActivo(" + JSON.stringify(obj) + ");");
        btnQuitar.innerHTML = '<i class="bi bi-trash"></i>';

        card.appendChild(cardimg);
        card.appendChild(bodyDiv);
        card.appendChild(btnQuitar);

        document.getElementById('colCards').appendChild(card);
      });
    }

    return false;
  }

function guardar() {

    let btnIngresar = document.getElementById("btnGuardar");
    btnIngresar.disabled = true;
    btnIngresar.innerHTML = '<span id="spinner" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
    let spinner = document.getElementById("spinner");   

    let arrayArticulos = []
    let checkboxes = document.querySelectorAll('input[type=checkbox]:checked')

    for (let i = 0; i < checkboxes.length; i++) {    
    arrayArticulos.push(checkboxes[i].getAttribute('data-id'))
    }

    if (arrayArticulos && arrayArticulos.length>0) {

      const formData = new FormData();    
      const json = JSON.stringify(arrayArticulos);
      formData.append('prestamo_fechaRetiro', prestamo_fechaRetiro);
      formData.append('prestamo_fechaDevolucion', prestamo_fechaDevolucion);
      formData.append('arrayArticulos', json);

      fetch('sql/insertPrestamoGestor.php', {
      method: 'POST', 
      body: formData,     
      }).then(function(response) {

      if(response.ok) {

      response.text().then(function(data) {  
      //console.log(data);                 

      }).catch(function(error) {

      spinner.style.visibility = 'hidden';
      btnIngresar.innerText="Guardar";
      btnIngresar.disabled = false;
      let contenedorError = document.getElementById("mensaje");
      contenedorError.innerHTML='<div class="alert alert-danger">' +
                              '<strong>Error! </strong>' +
                              'Intente de nuevo... no hubo respuesta del servidor MEP' +
                              '</div>'; 
      })

      } else {

      spinner.style.visibility = 'hidden';
      btnIngresar.innerText="Guardar";
      btnIngresar.disabled = false;
      let contenedorError = document.getElementById("mensaje");
      contenedorError.innerHTML='<div class="alert alert-danger">' +
                          '<strong>Error! </strong>' +
                          'No hay respuesta del servidor MEP. Verifique su conexión de internet ' +
                          '</div>';
      }

      })
      .catch(function(error) {

      spinner.style.visibility = 'hidden';
      btnIngresar.innerText="Guardar";
      btnIngresar.disabled = false;
      let contenedorError = document.getElementById("mensaje");
      contenedorError.innerHTML='<div class="alert alert-danger">' +
                        '<strong>Error! </strong>' +
                        'Hubo un problema al guardar la información: ' + error.message +
                        '</div>';        
      })
      .then();

      } else {

      spinner.style.visibility = 'hidden';
      btnIngresar.innerText="Guardar";
      btnIngresar.disabled = false;
      let contenedorError = document.getElementById("mensaje");    
      contenedorError.innerHTML='<div class="alert alert-danger">' +
                        '<strong>Error! </strong>' +
                            'Seleccione los artículos ...' +
                        '</div>';
      return false;   
      }

      spinner.style.visibility = 'hidden';
      btnIngresar.innerText="Guardar";

      return true;
  }
