
let solicitud_nombre_funcionario;
let solicitud_email_funcionario;
let codigoPresupuestario;
let solicitud_Id;
let rechazoMotivo = "";
let booRechazo = false;
let booBoleta = 0;
let txtBoleta = "";

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

  $('#fechaDevolución').datepicker({
    locale: 'es-es',
    format: 'dd mm yyyy',
    uiLibrary: 'bootstrap5'
  });

  $('#horaDevolucion').timepicker({
    locale: 'es-es',
    format: 'HH:MM',
    uiLibrary: 'bootstrap5'
  });

  $("#filaRechazo").on("click", "a", function(event) {
        
    event.preventDefault();            
    rechazoMotivo = $(this).data('motivo');
    booRechazo = true;
    //console.log( $(this).data('motivo'));
    rechazar();    
    return false;
    
  });
   
});

window.onload = function() {
  
  let boologin = login();

  if (boologin) {
   
    const queryString = window.location.search;
    const urlParams = new URLSearchParams(queryString);
    solicitud_Id = urlParams.get('solicitud_Id');
    
    //cargaComboSeccion();
    //cargaComboSoftware();
    cargaDatosBdSolicitudEncabezado();
    cargaDatosBd();
    cargaDatosRechazoSolicitud(); 
  
  }  else {
   
    let contenedorError = document.getElementById("contenedorError");
    
    contenedorError.innerHTML='<div class="alert alert-danger">' +
                                    '<strong>Error! </strong>' +
                                        'No ha iniciado sesión ...' +
                                    '</div>';

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
                          prestamo_horaDevolucionM) 
                          
  {

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

function isValidDateTime(horaminutos) {
    
  var isValid = /^([0-1]?[0-9]|2[0-4]):([0-5][0-9])(:[0-5][0-9])?$/.test(horaminutos);      
  return isValid;

}

function rechazar() {
  
  if (booRechazo == true) {

    let btnIngresar = document.getElementById("btnGuardar");
    btnIngresar.disabled = true;
    btnIngresar.innerHTML = '<span id="spinner" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
      
    let arrayArticulosNombre = [];

    let checkboxes = document.querySelectorAll('input[type=checkbox]');

    for (let i = 0; i < checkboxes.length; i++) {
      let nombre = checkboxes[i].getAttribute('data-nombre');
      if (nombre) arrayArticulosNombre.push(nombre);
    }

    const formData = new FormData();
    const jsonNombre = JSON.stringify(arrayArticulosNombre);        
    
    formData.append('solicitud_Id', solicitud_Id);
    formData.append('solicitud_email_funcionario', solicitud_email_funcionario);
    formData.append('solicitud_motivo_rechazo', rechazoMotivo);
    formData.append('arrayArticulosNombre', jsonNombre);
    formData.append('prestamo_nombre_solicitante', solicitud_nombre_funcionario);
        
    fetch('sql/updateSolicitudRechazadaGestor.php', {
      method: 'POST', 
      body: formData,     
    }).then(function(response) {
  
      if(response.ok) {
                
          response.text().then(function(data) {
            //console.log(data);

            if (data != "ok") {

              let spinner = document.getElementById("spinner");
              spinner.style.visibility = 'hidden';
              btnIngresar.innerText="Registrar préstamo";
              btnIngresar.disabled = false;
    
              let tituloMensaje = document.getElementById("tituloMensaje");
              tituloMensaje.innerText='';
          
              let contenedorError = document.getElementById("mensajeModal");
              contenedorError.innerText='';
    
              let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
              mensajeModalParrafo.innerText='';
    
              tituloMensaje.innerText = 'Hubo un inconveniente!';
              contenedorError.innerText = data;
              mensajeModalParrafo.innerText ='No se pudo rechazar la solicitud';  
            
              new bootstrap.Modal('#modalMensaje').show();

            }  
            
            if (data == "ok") {

                let spinner = document.getElementById("spinner");
                spinner.style.visibility = 'hidden';
                btnIngresar.innerText="Registrar préstamo";

                let tituloMensaje = document.getElementById("tituloMensajeGuardar");
                tituloMensaje.innerText='';

                let contenedorError = document.getElementById("mensajeModalGuardar");
                contenedorError.innerText='';

                let mensajeModalParrafo = document.getElementById("mensajeModalParrafoGuardar");
                mensajeModalParrafo.innerText='TecnoPresta enviará una notificación al correo institucional del solicitante';

                tituloMensaje.innerText = 'Ok!';
                contenedorError.innerText ='Se rechazó el préstamo de equipo!';      
                
                var modalElement = document.getElementById('modalRechazo');
                var rechazoModal = bootstrap.Modal.getInstance(modalElement);
                if (rechazoModal) rechazoModal.hide();
                modalElement.addEventListener('hidden.bs.modal', function () {
                    new bootstrap.Modal('#modalMensajeGuardar').show();
                }, { once: true });

            }        
                            
        }).catch(function(error) {
          
          let spinner = document.getElementById("spinner");
          spinner.style.visibility = 'hidden';
          btnIngresar.innerText="Registrar préstamo";
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
        
          new bootstrap.Modal('#modalMensaje').show();

        })
  
      } else {
              
              let spinner = document.getElementById("spinner");
              spinner.style.visibility = 'hidden';
              btnIngresar.innerText="Registrar préstamo";
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
            
              new bootstrap.Modal('#modalMensaje').show();

      }
  
    })
    .catch(function(error) {
      
            let spinner = document.getElementById("spinner");
            spinner.style.visibility = 'hidden';
            btnIngresar.innerText="Registrar préstamo";
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
            
            new bootstrap.Modal('#modalMensaje').show();

    })
    .then();        
  }
  
  return true;

}

function guardar() {
  
  let boologin = login();

  let btnIngresar = document.getElementById("btnGuardar");
  btnIngresar.disabled = true;
  btnIngresar.innerHTML = '<span id="spinner" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

  if (boologin==false) {
    
    let spinner = document.getElementById("spinner");

    spinner.style.visibility = 'hidden';
    btnIngresar.innerText="Registrar préstamo";
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
    
    new bootstrap.Modal('#modalMensaje').show();

    return false;
  }
    
  let prestamo_fechaRetiro = $('#fechaRetiro').val();
  let prestamo_fechaDevolucion = $('#fechaDevolución').val();
  let prestamo_horaRetiro = $('#horaRetiro').val();    
  let prestamo_horaDevolucion = $('#horaDevolucion').val();

  if (prestamo_fechaRetiro.trim()=="") {

    spinner.style.visibility = 'hidden';
    btnIngresar.innerText="Registrar préstamo";
    btnIngresar.disabled = false;
    
    let tituloMensaje = document.getElementById("tituloMensaje");
    tituloMensaje.innerText='';

    let contenedorError = document.getElementById("mensajeModal");
    contenedorError.innerText='';

    let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
    mensajeModalParrafo.innerText='';

    tituloMensaje.innerText = 'Hubo un inconveniente!';
    contenedorError.innerText ='No es posible guardar el préstamo!';
    mensajeModalParrafo.innerText ='Selecciona una fecha de retiro.';   
    
    new bootstrap.Modal('#modalMensaje').show();

    return false;

  }
  
  if (prestamo_horaRetiro.trim()=="") {

    spinner.style.visibility = 'hidden';
    btnIngresar.innerText="Registrar préstamo";
    btnIngresar.disabled = false;
    
    let tituloMensaje = document.getElementById("tituloMensaje");
    tituloMensaje.innerText='';

    let contenedorError = document.getElementById("mensajeModal");
    contenedorError.innerText='';

    let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
    mensajeModalParrafo.innerText='';

    tituloMensaje.innerText = 'Hubo un inconveniente!';
    contenedorError.innerText ='No es posible guardar el préstamo!';
    mensajeModalParrafo.innerText ='Selecciona una hora de retiro.';   
    
    new bootstrap.Modal('#modalMensaje').show();

    return false;
  }

  if (prestamo_fechaDevolucion.trim()=="") {

    spinner.style.visibility = 'hidden';
      btnIngresar.innerText="Registrar préstamo";
      btnIngresar.disabled = false;

      let tituloMensaje = document.getElementById("tituloMensaje");
      tituloMensaje.innerText='';
  
      let contenedorError = document.getElementById("mensajeModal");
      contenedorError.innerText='';

      let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
      mensajeModalParrafo.innerText='';

      tituloMensaje.innerText = 'Hubo un inconveniente!';
      contenedorError.innerText ='No es posible guardar el préstamo!';
      mensajeModalParrafo.innerText ='Selecciona una fecha de devolución.';   
      
      new bootstrap.Modal('#modalMensaje').show();      

      return false;

  }

  if (prestamo_horaDevolucion.trim()=="") {

    spinner.style.visibility = 'hidden';
    btnIngresar.innerText="Registrar préstamo";
    btnIngresar.disabled = false;
    
    let tituloMensaje = document.getElementById("tituloMensaje");
    tituloMensaje.innerText='';

    let contenedorError = document.getElementById("mensajeModal");
    contenedorError.innerText='';

    let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
    mensajeModalParrafo.innerText='';

    tituloMensaje.innerText = 'Hubo un inconveniente!';
    contenedorError.innerText ='No es posible guardar el préstamo!';
    mensajeModalParrafo.innerText ='Selecciona una hora de devolución.';   
    
    new bootstrap.Modal('#modalMensaje').show();

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
    btnIngresar.innerText="Registrar préstamo";
    btnIngresar.disabled = false;

    let tituloMensaje = document.getElementById("tituloMensaje");
    tituloMensaje.innerText='';

    let contenedorError = document.getElementById("mensajeModal");
    contenedorError.innerText='';

    let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
    mensajeModalParrafo.innerText='';

    tituloMensaje.innerText = 'Hubo un inconveniente!' ;
    contenedorError.innerText ='No es posible guardar el préstamo!';
    mensajeModalParrafo.innerText ='Selecciona una fecha de retiro.';
    
    new bootstrap.Modal('#modalMensaje').show();

    return false;

  }

  if (isValidDateTime(prestamo_horaRetiro)==false) {

    spinner.style.visibility = 'hidden';
    btnIngresar.innerText="Registrar préstamo";
    btnIngresar.disabled = false;

    let tituloMensaje = document.getElementById("tituloMensaje");
    tituloMensaje.innerText='';

    let contenedorError = document.getElementById("mensajeModal");
    contenedorError.innerText='';

    let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
    mensajeModalParrafo.innerText='';

    tituloMensaje.innerText = 'Hubo un inconveniente!' ;
    contenedorError.innerText ='No es posible guardar el préstamo!';
    mensajeModalParrafo.innerText ='Selecciona una hora de retiro.';
    
    new bootstrap.Modal('#modalMensaje').show();

    return false;
  }  

  if (validaFechasHora(prestamo_fechaRetiroY, prestamo_fechaRetiroM, 
    prestamo_fechaRetiroD, prestamo_horaRetiroH, prestamo_horaRetiroM,
    prestamo_fechaDevolucionY, prestamo_fechaDevolucionM, 
    prestamo_fechaDevolucionD, prestamo_horaDevolucionH,
    prestamo_horaDevolucionM)) {

    spinner.style.visibility = 'hidden';
    btnIngresar.innerText="Registrar préstamo";
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

    new bootstrap.Modal('#modalMensaje').show();

    return false;
    }

    if (validaFechaHoy(prestamo_fechaRetiroY, prestamo_fechaRetiroM, 
      prestamo_fechaRetiroD)) {

      spinner.style.visibility = 'hidden';
      btnIngresar.innerText="Registrar préstamo";
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

      new bootstrap.Modal('#modalMensaje').show();

      return false;

      }

  if (isValidDate(prestamo_fechaDevolucionY, prestamo_fechaDevolucionM, prestamo_fechaDevolucionD)!==true) {
    
    spinner.style.visibility = 'hidden';
    btnIngresar.innerText="Registrar préstamo";
    btnIngresar.disabled = false;

    let tituloMensaje = document.getElementById("tituloMensaje");
    tituloMensaje.innerText='';

    let contenedorError = document.getElementById("mensajeModal");
    contenedorError.innerText='';

    let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
    mensajeModalParrafo.innerText='';

    tituloMensaje.innerText = 'Hubo un inconveniente!';
    contenedorError.innerText ='No es posible guardar el préstamo!';
    mensajeModalParrafo.innerText ='Selecciona una fecha de devolución.';
    
    new bootstrap.Modal('#modalMensaje').show();

    return false;
  }

  if (isValidDateTime(prestamo_horaDevolucion)==false) {

    spinner.style.visibility = 'hidden';
    btnIngresar.innerText="Registrar préstamo";
    btnIngresar.disabled = false;

    let tituloMensaje = document.getElementById("tituloMensaje");
    tituloMensaje.innerText='';

    let contenedorError = document.getElementById("mensajeModal");
    contenedorError.innerText='';

    let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
    mensajeModalParrafo.innerText='';

    tituloMensaje.innerText = 'Hubo un inconveniente!' ;
    contenedorError.innerText ='No es posible guardar el préstamo!';
    mensajeModalParrafo.innerText ='Selecciona una hora de devolución.';

    new bootstrap.Modal('#modalMensaje').show();

    return false;
  }

  if (validaFechas(prestamo_fechaRetiroY, prestamo_fechaRetiroM, prestamo_fechaRetiroD,
                    prestamo_fechaDevolucionY, prestamo_fechaDevolucionM, prestamo_fechaDevolucionD)) {

    spinner.style.visibility = 'hidden';
      btnIngresar.innerText="Registrar préstamo";
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
     
      new bootstrap.Modal('#modalMensaje').show();

    return false;
  }

  if (validaFechaHoy(prestamo_fechaDevolucionY, prestamo_fechaDevolucionM, 
    prestamo_fechaDevolucionY)) {

    spinner.style.visibility = 'hidden';
    btnIngresar.innerText="Registrar préstamo";
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

    new bootstrap.Modal('#modalMensaje').show();

    return false;

  }

  //let cboSoftware = document.getElementById("cboSoftware");
  //let cboSoftwareId = new Array();
  // cboSoftwareId = [...cboSoftware.options]
  //                    .filter((x) => x.selected)
  //                    .map((x)=>x.value);
  
  let json_prestamo_detalle_cs = new Array();

  // for (let i = 0; i < cboSoftwareId.length; i++) {         

  //   let prestamo_detalle_cs = new Array();
          
  //   let id_cs = cboSoftwareId[i];
    
  //   prestamo_detalle_cs = {"id_cs":id_cs};

  //   json_prestamo_detalle_cs.push(prestamo_detalle_cs);
           
  // }                   

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

  //   new bootstrap.Modal('#modalMensaje').show();

  //   return false;

  // }

  let cboSeccionDescripcion = "";
  let cboSeccionId = 0;
  //let cboSeccionDescripcion = $('#cboSeccion option:selected').text()
  //let cboSeccionId = $('#cboSeccion option:selected').val();
  
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

  //   new bootstrap.Modal('#modalMensaje').show();

  //   return false;

  // }

  let prestamo_uso = document.getElementById("txtUso");
      
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
  let arrayArticulosNombre = [];
  let arrayArticulosNombreNoSeleccionados = [];
  let checkboxes = document.querySelectorAll('input[type=checkbox]:checked');
  let unCheckboxes = document.querySelectorAll('input[type="checkbox"]:not(:checked)');
  let chkTodos = document.getElementById("chkTodos");
    
  for (let i = 0; i < checkboxes.length; i++) {  
  
    if (checkboxes[i] != chkTodos) {

      arrayArticulos.push(checkboxes[i].getAttribute('data-id'));
      let nombre = checkboxes[i].getAttribute('data-nombre');
      if (nombre) arrayArticulosNombre.push(nombre);

    }

  }

  for (let x = 0; x < unCheckboxes.length; x++) {  
  
    if (unCheckboxes[x] != chkTodos) {
      
      let nombreNoSel = unCheckboxes[x].getAttribute('data-nombre');
      if (nombreNoSel) arrayArticulosNombreNoSeleccionados.push(nombreNoSel);

    }

  }
  
  if (arrayArticulos && arrayArticulos.length>0) {
   
    const formData = new FormData();    
    const json = JSON.stringify(arrayArticulos);
    const jsonNombre = JSON.stringify(arrayArticulosNombre);
    const jsonNombreNoSeleccionados = JSON.stringify(arrayArticulosNombreNoSeleccionados);
    const jsonNombreSoftware = JSON.stringify(cboSoftwareDescripcion);
    const jsonSoftwareId = JSON.stringify(json_prestamo_detalle_cs);

    formData.append('prestamo_fechaRetiro', prestamo_fechaRetiro);
    formData.append('prestamo_horaRetiro', prestamo_horaRetiro);
    formData.append('prestamo_fechaDevolucion', prestamo_fechaDevolucion);
    formData.append('prestamo_horaDevolucion', prestamo_horaDevolucion);    
    formData.append('prestamo_uso', prestamo_uso.value);
    formData.append('arrayArticulos', json);
    formData.append('prestamo_cedula_funcionario', cedula);
    formData.append('prestamo_nombre_funcionario', nombre);
    formData.append('prestamo_codigo_presupuestario', codigo);
    formData.append('prestamo_nombre_solicitante', solicitud_nombre_funcionario);
    formData.append('para', solicitud_email_funcionario);
    formData.append('solicitud_Id', solicitud_Id);
    formData.append('codigo', codigo);
    formData.append('Dependencia', Dependencia);
    formData.append('arrayArticulosNombre', jsonNombre);
    formData.append('arraySoftwareDescripcion', jsonNombreSoftware);
    formData.append('arraySoftwareId', jsonSoftwareId);
    formData.append('seccionDescripcion', cboSeccionDescripcion);
    formData.append('seccion_Id', cboSeccionId);
    formData.append('arrayArticulosNombreNoSeleccionados', jsonNombreNoSeleccionados);
    formData.append('boleta', booBoleta);
    formData.append('destino', txtBoleta);
    
    fetch('sql/insertPrestamoGestor.php', {
      method: 'POST', 
      body: formData,     
    }).then(function(response) {
  
      if(response.ok) {
                
          response.text().then(function(data) {
            
            //console.log(data);           

            if (data != "ok") {
              
              spinner.style.visibility = 'hidden';
              btnIngresar.innerText="Registrar préstamo";
              btnIngresar.disabled = false;
    
              let tituloMensaje = document.getElementById("tituloMensaje");
              tituloMensaje.innerText='';
          
              let contenedorError = document.getElementById("mensajeModal");
              contenedorError.innerText='';
    
              let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
              mensajeModalParrafo.innerText='';
    
              tituloMensaje.innerText = 'Hubo un inconveniente!';
              contenedorError.innerText = data;
              mensajeModalParrafo.innerText ='Está prestado en este momento.';  
            
              new bootstrap.Modal('#modalMensaje').show();

            }  
            
            if (data == "ok") {

                spinner.style.visibility = 'hidden';
                btnIngresar.innerText="Registrar préstamo";

                let tituloMensaje = document.getElementById("tituloMensajeGuardar");
                tituloMensaje.innerText='';

                let contenedorError = document.getElementById("mensajeModalGuardar");
                contenedorError.innerText='';

                let mensajeModalParrafo = document.getElementById("mensajeModalParrafoGuardar");
                mensajeModalParrafo.innerText='TecnoPresta le enviará una notificación a su correo institucional';

                tituloMensaje.innerText = 'Ok!';
                contenedorError.innerText ='Se registró el préstamo de equipo!';      
                
                new bootstrap.Modal('#modalMensajeGuardar').show();

            }        
                            
        }).catch(function(error) {
                  
          spinner.style.visibility = 'hidden';
          btnIngresar.innerText="Registrar préstamo";
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
        
          new bootstrap.Modal('#modalMensaje').show();

        })
  
      } else {
              
              spinner.style.visibility = 'hidden';
              btnIngresar.innerText="Registrar préstamo";
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
            
              new bootstrap.Modal('#modalMensaje').show();

      }
  
    })
    .catch(function(error) {
      
            spinner.style.visibility = 'hidden';
            btnIngresar.innerText="Registrar préstamo";
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
            
            new bootstrap.Modal('#modalMensaje').show();

    })
    .then();
  
  } else {

    spinner.style.visibility = 'hidden';
    btnIngresar.innerText="Registrar préstamo";
    btnIngresar.disabled = false;

    let tituloMensaje = document.getElementById("tituloMensaje");
    tituloMensaje.innerText='';

    let contenedorError = document.getElementById("mensajeModal");
    contenedorError.innerText='';

    let mensajeModalParrafo = document.getElementById("mensajeModalParrafo");
    mensajeModalParrafo.innerText='';

    tituloMensaje.innerText = 'Hubo un inconveniente!';
    contenedorError.innerText ='Al parecer no hay artículos seleccionados!';
    mensajeModalParrafo.innerText = 'Marca los artículos que vas a prestar ' + 
                                    'haciendo click en el cuadro pequeño junto al nombre del Artículo';
    
    new bootstrap.Modal('#modalMensaje').show();

    return false;   
  }

  
return true;

}

function cargaDatosBd(id) {

  $('#item').remove();
 
  fetch('sql/selectSolicitudDetalleAliasGestor.php?' 
          + new URLSearchParams({solicitud_Id: solicitud_Id, codigo: codigoPresupuestario}))
    .then(function(response) {

    if(response.ok) {

      response.json().then(function(data) {  
       
          //console.log(data);
          cargaDatosPantalla(data);

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

  fetch('sql/selectSolicitudDetalleActivoGestor.php?' 
    + new URLSearchParams({solicitud_Id: solicitud_Id, codigo: codigoPresupuestario}))
  .then(function(response) {

  if(response.ok) {

  response.json().then(function(data) {  

    //console.log(data);
    cargaDatosPantalla(data);

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

function cargaDatosRechazoSolicitud() {

  fetch('sql/selectRechazoSolicitudGestor.php?').then(function(response) {

    if(response.ok) {

      response.json().then(function(data) {  
       
          //console.log(data);
          cargaDatosPantallaRechazoSolicitud(data);

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


function cargaDatosBdSolicitudEncabezado() {

  fetch('sql/selectSolicitudEncabezadoGestor.php?' 
          + new URLSearchParams({solicitud_Id: solicitud_Id}))
    .then(function(response) {

    if(response.ok) {

      response.json().then(function(data) {  
       
          //console.log(data);
          cargaDatosPantallaEncabezado(data);

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

function cargaDatosPantallaRechazoSolicitud(rs) {

  $('.list-group-flush').remove();
  let i=0;

  rs.forEach(obj => {
      
      let list = document.createElement('div');
      list.className = "list-group-flush";
      
      let linkList = document.createElement('a');      
      linkList.setAttribute("data-motivo", obj.motivo_rechazo_solicitud);
      linkList.setAttribute('href', "#");
      linkList.className = "list-group-item list-group-item-action";
      
      let contenedorCodPre = document.createElement('div');
      contenedorCodPre.className = "d-flex w-100 justify-content-between-group-flush";
                     
      let columnaNombre = document.createElement('div');
      columnaNombre.className = "col-sm-10";
      
      let h5nombre = document.createElement('h5');
      h5nombre.className = "mb-1";
      let createATextNombre = document.createTextNode(obj.motivo_rechazo_solicitud);
      h5nombre.appendChild(createATextNombre);
      
      columnaNombre.appendChild(h5nombre); 

      //contenedorCodPre.appendChild(columnaCodigo);
      contenedorCodPre.appendChild(columnaNombre);
      linkList.appendChild(contenedorCodPre);
      
      list.appendChild(linkList);
      document.getElementById('filaRechazo').appendChild(list);
      
      i=i+1;             
    
  });
  
  return false;

}

function cargaDatosPantalla(rs) {

  //$('#item').remove();

  //console.log(rs);   

  rs.forEach(obj => {

      
      let plantilla = document.getElementById("plantilla");
      
      let row = document.createElement('div');
      row.className = "form-group row";
      row.id = "item";
       
      let nombreArticulo = obj.clase + " " + obj.modelo + " " + obj.marca;
      let colCheck = document.createElement('div');
      colCheck.className = "col-1";
      colCheck.innerHTML='<div class="form-check">'+
                            '<input class="form-check-input position-static" ' +
                            'type="checkbox" data-id="' + obj.id_placa  + '" data-nombre="' + nombreArticulo + '" >' +
                          '</div>';
            
      let colNombre = document.createElement('div');
      colNombre.className = "col";
      let createATextNombre = document.createTextNode(obj.clase + " " + obj.marca);
      colNombre.appendChild(createATextNombre);
             
      // marca
      let colMarca = document.createElement('div');
      colMarca.className = "col";     
      let createATextMarca = document.createTextNode(obj.modelo);
      colMarca.appendChild(createATextMarca);
      
      //Placa
      let colPlaca = document.createElement('div');
      colPlaca.className = "col";     
      let createATextPlaca = document.createTextNode(obj.placa);
      colPlaca.appendChild(createATextPlaca);     
          
      // numero_activo
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

  //console.log(rs[0].solicitud_nombre_funcionario);
  
    let rsfechaRetiro = rs[0].solicitud_fechaRetiro;
    let solicitud_fechaRetiroY = rsfechaRetiro.substr(0, 4);
    let solicitud_fechaRetiroM = rsfechaRetiro.substr(5, 2);
    let solicitud_fechaRetiroD = rsfechaRetiro.substr(8, 2);

    let solicitud_fechaRetiro = solicitud_fechaRetiroD + " " + solicitud_fechaRetiroM + " "  + solicitud_fechaRetiroY;

    let fechaRetiro = document.getElementById("fechaRetiro");
    fechaRetiro.value = solicitud_fechaRetiro;
    
    let rshoraRetiro = rs[0].solicitud_horaRetiro;
    let solicitud_horaRetiroH = rshoraRetiro.substr(0, 2);
    let solicitud_horaRetiroM = rshoraRetiro.substr(3, 2);

    let solicitud_horaRetiro = solicitud_horaRetiroH + ":" + solicitud_horaRetiroM;

    let horaRetiro = document.getElementById("horaRetiro");
    horaRetiro.value = solicitud_horaRetiro;

    let rsfechaDevolucion = rs[0].solicitud_fechaDevolucion;
    let solicitud_fechaDevolucionY = rsfechaDevolucion.substr(0, 4);
    let solicitud_fechaDevolucionM = rsfechaDevolucion.substr(5, 2);
    let solicitud_fechaDevolucionD = rsfechaDevolucion.substr(8, 2);

    let solicitud_fechaDevolucion = solicitud_fechaDevolucionD + " " + solicitud_fechaDevolucionM + " "  + solicitud_fechaDevolucionY;

    let fechaDevolucion = document.getElementById("fechaDevolución");
    fechaDevolucion.value = solicitud_fechaDevolucion;
    
    let rshoraDevolucion = rs[0].solicitud_horaDevolucion;
    let solicitud_horaDevolucionH = rshoraDevolucion.substr(0, 2);
    let solicitud_horaDevolucionM = rshoraDevolucion.substr(3, 2);

    let solicitud_horaDevolucion = solicitud_horaDevolucionH + ":" + solicitud_horaDevolucionM;

    let horaDevolucion = document.getElementById("horaDevolucion");
    horaDevolucion.value = solicitud_horaDevolucion;

    // let cboSeccion = document.getElementById("cboSeccion");
    // cboSeccion.value= rs[0].seccion_Id;     
    //$("#cboSeccion").selectpicker("refresh");
    
    // fetch('sql/selectSolicitudDetalleCSGestor.php?' 
    //       + new URLSearchParams({solicitud_Id: solicitud_Id}))
    // .then(function(response) {

    //   if(response.ok) {

    //     response.json().then(function(data) {
                      
    //         let json_solicitud_detalle_cs = new Array();

    //         for (let i = 0; i < data.length; i++) {         
                  
    //           json_solicitud_detalle_cs.push(data[i].id_cs);
                    
    //         }
                                 
    //         $('#cboSoftware').selectpicker('val', json_solicitud_detalle_cs);
    //         $("#cboSoftware").selectpicker("refresh");
            
    //       });              

    //   }

    // }).then();
  
    let rssolicitud_uso = rs[0].solicitud_uso;
    let solicitud_uso = document.getElementById("txtUso");
    solicitud_uso.value = rssolicitud_uso;

    solicitud_nombre_funcionario = rs[0].solicitud_nombre_funcionario;
    solicitud_email_funcionario = rs[0].solicitud_email_funcionario;

    booBoleta = rs[0].solicitud_boleta;
    txtBoleta = rs[0].solicitud_uso_externo;

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
                                
    return false;    

  }

  return true;

}

function salir() {

  if (booRechazo == false) {

    // window.location.assign('formulario_menu_prestamo_n.php');
    window.location.assign('navegar.php?ruta=formulario_VistaSolicitud_n.php');
    
  } else {

    window.location.assign('navegar.php?ruta=formulario_VistaSolicitud_n.php');
    
  }
  
  return true;

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

function checkAll(source) {
      
  let checkboxes = document.querySelectorAll('input[type="checkbox"]');
  
  for (let i = 0; i < checkboxes.length; i++) {
      if (checkboxes[i] != source)
          checkboxes[i].checked = source.checked;
  }

}
