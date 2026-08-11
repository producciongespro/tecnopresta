//window.userData = []; //Variable global para almacenar el JSON del ws

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
    
    //console.log(userData);

  } else {
                                
    return false;    

  }

  return true;

}

window.onload = function() {
  
  //let boologin = loginAzure();
  let boologin = login();

  if (boologin) {

   
  }  else {
    
  let contenedorError = document.getElementById("mensaje");
    
  contenedorError.innerHTML='<div class="alert alert-danger">' +
                                     '<strong>Error! </strong>' +
                                         'No ha iniciado sesión ...' +
                                     '</div>';

  }
          
  return false;

};

function loginAzure() {      
        
  fetch('sql/selectLoginGestor1.php')
  .then(function(response) 
  {
          
    if(response.ok) 
    {

      response.json().then(function(data) 
      {
        
        //console.log(data);
        //console.log("hola1");                     
    
      });
  
    }

  }).then(function(data){});

  return true;

}