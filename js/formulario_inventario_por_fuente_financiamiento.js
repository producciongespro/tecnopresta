
let codigoPresupuestario;

function mostrarModal(id) {
  var el = document.getElementById(id);
  if (el) {
    var modal = new bootstrap.Modal(el);
    modal.show();
  }
}

window.onload = function() {
  
  let boologin = login();

  if (boologin) {
    
    cargaComboFondos();
     
  }  else {
   
    let contenedorError = document.getElementById("mensaje");
    
    contenedorError.innerHTML='<div class="alert alert-danger">' +
                                    '<strong>Error! </strong>' +
                                        'No ha iniciado sesión ...' +
                                    '</div>';

  }
      
  return false;

}

function obtieneEstadoCheck(nombreElem) {
  
  let rates = document.getElementsByName(nombreElem);
  let rate_value;
  for(let i = 0; i < rates.length; i++){
      if(rates[i].checked){
          rate_value = rates[i].value;
          return rate_value;
      }
  }

  return "0";
  
}

function guardar() {
  
  let boologin = login();

  let btnIngresar = document.getElementById("btnGuardar");
  btnIngresar.disabled = true;
  btnIngresar.innerHTML = '<span id="spinner" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

  if (boologin==false) {
    
    let spinner = document.getElementById("spinner");

    spinner.style.visibility = 'hidden';
    btnIngresar.innerText="Guardar";
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
    

  let cboFondos = $('#cboFondos option:selected').val();
  
  if (cboFondos < 1) {
    
    spinner.style.visibility = 'hidden';
    btnIngresar.innerText="Registrar estdado del Activo";
    btnIngresar.disabled = false;

    let tituloMensaje = document.getElementById("tituloMensaje");
    tituloMensaje.innerText='';

    let contenedorError = document.getElementById("mensajeModal");
    contenedorError.innerText='';

    let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
    mensajeModalParrafo.innerText='';

    tituloMensaje.innerText = 'Hubo un inconveniente!';
    contenedorError.innerText ='No ha seleccionado el Origen de los Fondos';
    mensajeModalParrafo.innerText ='Por favor seleccione un Origen Presupuestario';

    mostrarModal('modalMensaje');

    return false;

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

  let arrayArticulos = [];
  let arrayEstado =[];
  let arrayEnUso =[];
  let arrayDonacion =[];

  let checkboxes = document.querySelectorAll('input[type=checkbox]:checked');
  let chkTodos = document.getElementById("chkTodos");

  for (let i = 0; i < checkboxes.length; i++) {  
  
    if (checkboxes[i] != chkTodos && 
        checkboxes[i].id !="usoSINO" && 
        checkboxes[i].id !="donar") {

            arrayArticulos.push(checkboxes[i].getAttribute('data-id'));
            arrayEstado.push(obtieneEstadoCheck("est-" + checkboxes[i].getAttribute('data-id')));
            arrayEnUso.push(obtieneEstadoCheck("usoSINO-" + checkboxes[i].getAttribute('data-id')));
            arrayDonacion.push(obtieneEstadoCheck("donar-" + checkboxes[i].getAttribute('data-id')));              
    }

  } 
  
  console.log(arrayArticulos);
  console.log(arrayEstado);
  console.log(arrayEnUso);
  console.log(arrayDonacion);
  
  if (arrayArticulos && arrayArticulos.length>0) {
   
    const formData = new FormData();    
    const jsonArticulos = JSON.stringify(arrayArticulos);
    const jsonEstado = JSON.stringify(arrayEstado);
    const jsonEnUso = JSON.stringify(arrayEnUso);
    const jsonDonacion = JSON.stringify(arrayDonacion);
    
    formData.append('arrayArticulos', jsonArticulos);    
    formData.append('arrayEstado', jsonEstado);
    formData.append('arrayEnUso', jsonEnUso);    
    formData.append('arrayDonacion', jsonDonacion);    
    
    fetch('sql/updateEstadoporPlacaGestor.php',{
      method: 'POST', 
      body: formData,     
    }).then(function(response) {
  
      if(response.ok) {
                
          response.text().then(function(data) {
            
            console.log(data);                       
            
            if (data == "ok") {

                spinner.style.visibility = 'hidden';
                btnIngresar.innerText="Guardar";

                let tituloMensaje = document.getElementById("tituloMensajeGuardar");
                tituloMensaje.innerText='';

                let contenedorError = document.getElementById("mensajeModalGuardar");
                contenedorError.innerText='';

                let mensajeModalParrafo = document.getElementById("mensajeModalParrafoGuardar");
                mensajeModalParrafo.innerText="";

                tituloMensaje.innerText = 'Ok!';
                contenedorError.innerText ='Se registró la información correctamente!';      
                
                mostrarModal('modalMensajeGuardar');

            }        
                            
        }).catch(function(error) {
                  
          spinner.style.visibility = 'hidden';
          btnIngresar.innerText="Guardar";
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
              btnIngresar.innerText="Guardar";
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
            btnIngresar.innerText="Guardar";
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
    btnIngresar.innerText="Guardar";
    btnIngresar.disabled = false;

    let tituloMensaje = document.getElementById("tituloMensaje");
    tituloMensaje.innerText='';

    let contenedorError = document.getElementById("mensajeModal");
    contenedorError.innerText='';

    let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
    mensajeModalParrafo.innerText='';

    tituloMensaje.innerText = 'Hubo un inconveniente!';
    contenedorError.innerText ='Al parecer no hay artículos seleccionados!';
    mensajeModalParrafo.innerText = 'Marca los artículos que vas a actualizar ' + 
                                    'haciendo click en el cuadro pequeño junto al nombre del Artículo';
    
    mostrarModal('modalMensaje');

    return false;   
  }

  
return true;

}

function cargaDatosBd(id_fondos) {

  document.getElementById("chkTodos").checked = false;
  //$("#chkTodos").attr("checked", false);  
  $('#plantilla').empty();

  fetch('sql/selectActivos_por_Fondos_Gestor.php?' 
          + new URLSearchParams({id_fondos: id_fondos, codigo: codigoPresupuestario}))
    .then(function(response) {

    if(response.ok) {

      response.json().then(function(data) {  
       
          //console.log(data);
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

function cargaDatosPantalla(rs) {

  var colEstadoHtml = function(idPlaca, idEstado) {
    var estados = [
      { v: 1, l: 'Muy Buena' },
      { v: 2, l: 'Bueno' },
      { v: 3, l: 'Regular' },
      { v: 4, l: 'Malo' },
      { v: 5, l: 'Robado o Hurtado' }
    ];
    return '<div class="estado-list">' +
      estados.map(function(e) {
        var checked = (e.v === idEstado) ? 'checked' : '';
        return '<label class="radio-label-mep">' +
                 '<input value="' + e.v + '" ' + checked + ' type="radio" class="radio-mep" name="est-' + idPlaca + '">' +
                 e.l +
               '</label>';
      }).join('') +
      '</div>';
  };

  rs.forEach(function(obj) {

    var plantilla = document.getElementById("plantilla");

    var row = document.createElement('div');
    row.className = "activos-row";

    var colCheck = document.createElement('div');
    colCheck.className = "col-check";
    colCheck.innerHTML = '<input type="checkbox" class="check-mep" data-id="' + obj.id_placa + '">';

    var colNombre = document.createElement('div');
    colNombre.className = "col-nombre";
    colNombre.textContent = obj.clase + " " + obj.modelo + " " + obj.marca;

    var colPlaca = document.createElement('div');
    colPlaca.className = "col-placa";
    colPlaca.textContent = obj.placa;

    var colEstado = document.createElement('div');
    colEstado.className = "col-estado";
    colEstado.innerHTML = colEstadoHtml(obj.id_placa, obj.id_estado);

    var colCheckusosino = document.createElement('div');
    colCheckusosino.className = "col-uso";
    var usoChecked = (obj.enuso === 1) ? 'checked' : '';
    colCheckusosino.innerHTML = '<input type="checkbox" class="check-mep" value="1" ' + usoChecked + ' id="usoSINO" name="usoSINO-' + obj.id_placa + '">';

    var colCheckdonar = document.createElement('div');
    colCheckdonar.className = "col-donar";
    var donarChecked = (obj.donar === 1) ? 'checked' : '';
    colCheckdonar.innerHTML = '<input type="checkbox" class="check-mep" value="1" ' + donarChecked + ' id="donar" name="donar-' + obj.id_placa + '">';

    row.appendChild(colCheck);
    row.appendChild(colNombre);
    row.appendChild(colPlaca);
    row.appendChild(colEstado);
    row.appendChild(colCheckusosino);
    row.appendChild(colCheckdonar);
    plantilla.appendChild(row);

  });

  return false;
}

function login() {

  window.userData = [];
  userData = window.sessionStorage.getItem('sesion');
    
  if (userData && userData.length>0) {

    let nombre = document.getElementById("nombre");
    let codigo = document.getElementById("codigo");
    let jsonData = [];

    jsonData = JSON.parse(userData);

    nombre.innerText = jsonData["Nombre"] + " " + jsonData["Apellido1"] + " " + jsonData["Apellido2"];
    codigo.innerText = jsonData["Dependencia"];
    codigoPresupuestario = jsonData["CentrosEducativosDondeTrabaja"];   

  } else {
                                
    return false;    

  }

  return true;

}

function cargaComboFondos() {
  
  fetch('sql/selectFondos_Gestor.php')
  .then(function(response) 
  {
          
    if(response.ok) 
    {

      response.json().then(function(data) 
      {
        
        //console.log(data);
              
        data.forEach(element => 
          {

            let cboSeccion = document.getElementById("cboFondos"); 
            let opt = document.createElement("option");
            opt.value = element.id_fondos;
            opt.innerHTML = element.fondos;        
            cboSeccion.append(opt);            

          });
          
     
      });
  
    }

  }).then(function(data){});

}

function checkAll(source) {
      
  let checkboxes = document.querySelectorAll('input[type="checkbox"]');

  for (let i = 0; i < checkboxes.length; i++) {
    if (checkboxes[i] != source && 
        checkboxes[i].id !="usoSINO" && 
        checkboxes[i].id !="donar") {
          checkboxes[i].checked = source.checked;     
    }
  }

}

function muestraEstadoDescripcion() {

  mostrarModal('modalMensajeEstado');
  return true;
  
}
