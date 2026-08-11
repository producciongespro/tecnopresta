const msalConfig = {
    auth: {
        clientId: "be0e9b41-718d-4d2a-8c2f-d26eba67d767",
        authority: "https://login.microsoftonline.com/mep.go.cr",
        redirectUri: "https://tecnopresta.mep.go.cr/index.html"
    },
    cache: {
        cacheLocation: "sessionStorage",
        storeAuthStateInCookie: false,
    }
};

// Scopes para login inicial (sin cambios)
const loginRequest = {
    scopes: ["openid", "profile", "User.Read"]
};

// Scopes optimizados para citas - AGREGAR openid y profile
const graphRequest = {
    scopes: [
        "User.Read", 
        "Calendars.ReadWrite",
        "OnlineMeetings.ReadWrite",
        "openid",    // AGREGADO
        "profile"    // AGREGADO
    ]
};

// Configuración adicional para Graph API
const graphConfig = {
    graphMeEndpoint: "https://graph.microsoft.com/v1.0/me",
    graphMailEndpoint: "https://graph.microsoft.com/v1.0/me/messages",
    graphEventsEndpoint: "https://graph.microsoft.com/v1.0/me/events",
    graphOnlineMeetingsEndpoint: "https://graph.microsoft.com/v1.0/me/onlineMeetings",
    graphPhotoEndpoint: "https://graph.microsoft.com/v1.0/me/photo/$value"
};

// Inicializar MSAL
let myMSALObj = null;

async function initializeMSAL() {
    try {
        myMSALObj = new msal.PublicClientApplication(msalConfig);
        await myMSALObj.initialize();
        console.log("✅ MSAL inicializado correctamente");
    } catch (error) {
        console.error("Error al inicializar MSAL:", error);
    }
}

window.jsonData = []; //Variable global para almacenar el JSON del ws

window.addEventListener("load", async function () {

    // SOLO ejecuta si corresponde al login activo
    if (
        (window.tipoLoginActivo === "normal" && typeof signIn === "function")
        ||
        (window.tipoLoginActivo === "especial" && typeof signInEspecial === "function")
    ) {

        await initializeMSAL();

        sesionCompatibildad();

        loadPage();
    }

});

/*window.onload = async function() {
    // Inicializar MSAL primero
    await initializeMSAL();
    sesionCompatibildad();
    loadPage();
    return true;
};*/

function loadPage() {
    
    if (window.tipoLoginActivo !== "normal") {
        return;
    }
    
    // VERIFICACIÓN DE INICIALIZACIÓN AGREGADA
    if (!myMSALObj) {
        console.warn("MSAL no inicializado, reintentando...");
        setTimeout(loadPage, 100);
        return;
    }

    const currentAccounts = myMSALObj.getAllAccounts();

    if (currentAccounts === null) {
        console.warn("No accounts detected.");
        return;
    } else if (currentAccounts.length > 1) {
        // Add choose account code here
        console.warn("Multiple accounts detected.");
    } else if (currentAccounts.length === 1) {
        let username = currentAccounts[0].username;
        login(username);           
    }
}

function handleResponse(resp) {
    if (resp !== null) {
        let username = resp.account?.username || myMSALObj.getAllAccounts()[0]?.username;
        login(username);           
    } else {
        loadPage();
    }
}

function signIn() {
    myMSALObj.loginPopup(loginRequest).then(handleResponse).catch(error => {
        console.error(error);
    });
}

function sesionCompatibildad() {
    if (typeof(Storage) !== 'undefined') {
        // Código cuando Storage es compatible    
    } else {
        // Código cuando Storage NO es compatible
        let contenedorError = document.getElementById("contenedorError");
        contenedorError.innerHTML='<div class="alert alert-danger">' +
        '<strong>Error! </strong>' +
            'No es compatible con el objeto Storage' +
        '</div>';        
    }

    if (typeof(window.FormData) !== 'undefined') {
        // Código cuando Storage es compatible    
    } else {
        // Código cuando Storage NO es compatible
        let contenedorError = document.getElementById("contenedorError1");
        contenedorError.innerHTML='<div class="alert alert-danger">' +
        '<strong>Error! </strong>' +
            'No es compatible con el objeto window.FormData' +
        '</div>';        
    }
}

$(document).ready(function() {    
    $("#fila").on("click", "a", function(event) {
        event.preventDefault();        
        let indice = $(this).data('id');
        inicioSesion(indice);       
        return false;
    });
});    

function inicioSesion(indice) {
    const json = JSON.stringify(jsonData[indice]);
    window.sessionStorage.setItem('sesion',json);
    window.location.replace('formulario_menu_principal.html');
    return false;
}

// ==============================================
// FUNCIONES PARA TOKEN DE GRAPH API - CORREGIDAS
// ==============================================

/**
 * Obtiene token de acceso para Microsoft Graph
 */
async function obtenerTokenAcceso() {
    try {
        // Verificar que MSAL esté inicializado
        if (!myMSALObj) {
            console.warn("MSAL no inicializado. Inicializando...");
            await initializeMSAL();
        }

        const currentAccounts = myMSALObj.getAllAccounts();
        
        if (currentAccounts.length === 0) {
            console.warn("No hay cuentas activas para obtener token");
            return null;
        }

        const account = currentAccounts[0];
        
        // INTENTAR OBTENER TOKEN CON SCOPES COMPLETOS
        const tokenResponse = await myMSALObj.acquireTokenSilent({
            scopes: graphRequest.scopes,  // USA TODOS LOS SCOPES INCLUYENDO openid y profile
            account: account
        });

        console.log("✅ Token obtenido exitosamente");
        return tokenResponse.accessToken;
        
    } catch (error) {
        console.warn("Error obteniendo token silencioso:", error);
        
        // Si falla, intentar con popup
        if (error.name === "InteractionRequiredAuthError") {
            try {
                console.log("Intentando obtener token via popup...");
                const tokenResponse = await myMSALObj.acquireTokenPopup({
                    scopes: graphRequest.scopes  // USA LOS MISMOS SCOPES
                });
                return tokenResponse.accessToken;
            } catch (popupError) {
                console.error("Error en popup de token:", popupError);
                return null;
            }
        }
        return null;
    }
}

/**
 * Refresca el token de acceso cuando está por expirar
 */
async function refrescarTokenSiNecesario() {
    const tokenAlmacenado = window.sessionStorage.getItem('graphAccessToken');
    
    if (!tokenAlmacenado) {
        return await obtenerTokenAcceso();
    }

    try {
        const nuevoToken = await obtenerTokenAcceso();
        if (nuevoToken) {
            window.sessionStorage.setItem('graphAccessToken', nuevoToken);
            console.log("Token refrescado exitosamente");
        }
        return nuevoToken;
    } catch (error) {
        console.error("Error refrescando token:", error);
        return null;
    }
}

/**
 * Obtiene token válido para operaciones de calendario/Teams
 */
async function obtenerTokenParaCalendario() {
    let token = window.sessionStorage.getItem('graphAccessToken');
    
    if (!token) {
        console.log("No hay token almacenado, obteniendo nuevo...");
        token = await refrescarTokenSiNecesario();
    } else {
        console.log("Token encontrado en almacenamiento");
        token = await refrescarTokenSiNecesario();
    }
    
    return token;
}

// ==============================================
// FUNCIÓN LOGIN PRINCIPAL (CON TOKEN GRAPH) - SIN CAMBIOS ESTRUCTURALES
// ==============================================

async function login(username) {      
    
    let btnIngresar = document.getElementById("btnIngresar");
    btnIngresar.disabled = true;
    let contenedorError = document.getElementById("contenedorError");
    btnIngresar.innerHTML = '<span id="spinner" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
    let spinner = document.getElementById("spinner");   

    const formDataLogin = new FormData();    
    formDataLogin.append('correo', username);
    formDataLogin.append('pass', '');

    try {
        const response = await fetch('sql/selectLoginGestor.php', {
            method: 'POST', 
            body: formDataLogin,     
        });

        if (!response.ok) {
            throw new Error('Error en respuesta del servidor');
        }

        const data = await response.json();  
        console.log("Lo que viene de integra:", data);
        jsonData = data;

        // ✅ OBTENER TOKEN PARA GRAPH API (CORREGIDO)
        console.log("Obteniendo token de acceso para Graph API...");
        const accessToken = await obtenerTokenAcceso();
        if (accessToken) {
            window.sessionStorage.setItem('graphAccessToken', accessToken);
            console.log("✅ Token de Graph API almacenado correctamente");
            window.sessionStorage.setItem('graphUser', username);
        } else {
            console.warn("No se pudo obtener token de Graph API, pero el login continúa");
        }

        // Lógica existente para códigos presupuestarios (SIN CAMBIOS)
        if (Object.keys(data).length === 1) {
            inicioSesion(0);
        } else if (Object.keys(data).length > 1) {
            cargaModal();
        } else if (Object.keys(data).length === 0) {
            spinner.style.visibility = 'hidden';
            btnIngresar.innerText="Ingresar";
            btnIngresar.disabled = false;
            contenedorError.innerHTML='<div class="alert alert-danger">' +
                                    '<strong> Atención: </strong>' +
                                        'Estimado funcionario(a) MEP:'+
                                        'Recuerde que debe ingresar con correo de FUNCIONARIO ' +
                                        'NO con correo de institucion; ' + 
                                        'Si el problema persiste ' +
                                        'escribanos a <strong>tecnopresta@mep.go.cr</strong>' +
                                    '</div>';                        
        }

    } catch (error) {
        console.error("Error en login:", error);
        spinner.style.visibility = 'hidden';
        btnIngresar.innerText="Ingresar";
        btnIngresar.disabled = false;
        contenedorError.innerHTML='<div class="alert alert-danger">' +
                                '<strong>Error! Intente de nuevo </strong>' +
                                'Hubo un problema con la conexión al Servidor: ' + error.message +
                                '</div>';        
    }

    return true;    
}

// ==============================================
// FUNCIONES PARA CALENDARIOS Y TEAMS - SIN CAMBIOS
// ==============================================

/**
 * Crear evento en calendario
 */
async function crearEventoEnCalendario(eventoData) {
    try {
        const token = await obtenerTokenParaCalendario();
        
        if (!token) {
            throw new Error("No se pudo obtener token de acceso para calendario");
        }

        const response = await fetch(graphConfig.graphEventsEndpoint, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(eventoData)
        });

        if (!response.ok) {
            throw new Error(`Error HTTP: ${response.status}`);
        }

        return await response.json();
    } catch (error) {
        console.error("Error creando evento:", error);
        throw error;
    }
}

/**
 * Crear reunión de Teams
 */
async function crearReunionTeams(meetingData) {
    try {
        const token = await obtenerTokenParaCalendario();
        
        if (!token) {
            throw new Error("No se pudo obtener token de acceso para Teams");
        }

        const response = await fetch(graphConfig.graphOnlineMeetingsEndpoint, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(meetingData)
        });

        if (!response.ok) {
            throw new Error(`Error HTTP: ${response.status}`);
        }

        return await response.json();
    } catch (error) {
        console.error("Error creando reunión Teams:", error);
        throw error;
    }
}

// ==============================================
// FUNCIÓN cargaModal (SIN CAMBIOS)
// ==============================================

function cargaModal() {
    $('.list-group-flush').remove();
    let i=0;

    jsonData.forEach(obj => {
        
        let list = document.createElement('div');
        list.className = "list-group-flush";
        
        let linkList = document.createElement('a');      
        linkList.setAttribute("data-id", i);
        linkList.setAttribute('href', "formulario_menu_principal.html");
        linkList.className = "list-group-item list-group-item-action";
        
        let contenedorCodPre = document.createElement('div');
        contenedorCodPre.className = "d-flex w-100 justify-content-between-group-flush";
        
        let columnaCodigo = document.createElement('div');
        columnaCodigo.className = "col-sm-2";
        
        let h5codigo = document.createElement('h5');
        h5codigo.className = "mb-1";

        let codPre = obj.Codigo_Presupuestario;             
        let createATextCodigo = document.createTextNode(codPre.substr(-4));
        h5codigo.appendChild(createATextCodigo);
        
        columnaCodigo.appendChild(h5codigo);
            
        let columnaNombre = document.createElement('div');
        columnaNombre.className = "col-sm-10";
        
        let h5nombre = document.createElement('h5');
        h5nombre.className = "mb-1";
        let createATextNombre = document.createTextNode(obj.Dependencia);
        h5nombre.appendChild(createATextNombre);
        
        columnaNombre.appendChild(h5nombre); 

        contenedorCodPre.appendChild(columnaCodigo);
        contenedorCodPre.appendChild(columnaNombre);
        linkList.appendChild(contenedorCodPre);
        
        list.appendChild(linkList);
        document.getElementById('fila').appendChild(list);
        
        i=i+1;             
    
    });

    $("#loginModal").modal(); //si hay varios se seleccionan uno

    return false;
}