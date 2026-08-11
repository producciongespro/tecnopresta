// =======================================================
// CONFIGURACIÓN GENERAL
// =======================================================

// Modo automático: si estás en localhost, se desactiva Azure
const MODO_LOCAL = (location.hostname === "localhost" || location.hostname === "127.0.0.1");

// Usuario quemado para desarrollo local
const USUARIO_PRUEBA = "erick.cerdas.gonzalez@mep.go.cr";

// =======================================================
// CONFIGURACIÓN MSAL (SOLO PRODUCCIÓN)
// =======================================================

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

const loginRequest = {
    scopes: ["openid", "profile", "User.Read"]
};

const graphRequest = {
    scopes: [
        "User.Read",
        "Calendars.ReadWrite",
        "OnlineMeetings.ReadWrite",
        "openid",
        "profile"
    ]
};

const graphConfig = {
    graphMeEndpoint: "https://graph.microsoft.com/v1.0/me",
    graphMailEndpoint: "https://graph.microsoft.com/v1.0/me/messages",
    graphEventsEndpoint: "https://graph.microsoft.com/v1.0/me/events",
    graphOnlineMeetingsEndpoint: "https://graph.microsoft.com/v1.0/me/onlineMeetings",
    graphPhotoEndpoint: "https://graph.microsoft.com/v1.0/me/photo/$value"
};

// =======================================================
// INICIALIZACIÓN MSAL
// =======================================================

let myMSALObj = null;
window.jsonData = [];

async function initializeMSAL() {
    if (MODO_LOCAL) {
        console.log("🔧 MODO LOCAL: MSAL deshabilitado");
        return;
    }

    try {
        myMSALObj = new msal.PublicClientApplication(msalConfig);
        await myMSALObj.initialize();
        console.log("✅ MSAL inicializado correctamente");
    } catch (error) {
        console.error("Error al inicializar MSAL:", error);
    }
}

// =======================================================
// CARGA INICIAL
// =======================================================

window.onload = async function() {
    if (!MODO_LOCAL) {
        await initializeMSAL();
    } else {
        console.log("🔧 Ejecutando en MODO LOCAL con usuario:", USUARIO_PRUEBA);
    }

    sesionCompatibildad();
    loadPage();
    return true;
};

function loadPage() {

    if (MODO_LOCAL) {
        console.log("🔧 Esperando clic en botón Ingresar (modo local)");
        return;
    }

    if (MODO_LOCAL) {
        // Simula sesión activa sin Azure
        login(USUARIO_PRUEBA);
        return;
    }

    if (!myMSALObj) {
        console.warn("MSAL no inicializado, reintentando...");
        setTimeout(loadPage, 100);
        return;
    }

    const currentAccounts = myMSALObj.getAllAccounts();

    if (currentAccounts.length === 1) {
        let username = currentAccounts[0].username;
        login(username);
    }
}

// =======================================================
// LOGIN
// =======================================================

function signIn() {

    if (MODO_LOCAL) {
        console.log("🔧 Login simulado con:", USUARIO_PRUEBA);
        login(USUARIO_PRUEBA);
        return;
    }

    // Producción real con Azure
    myMSALObj.loginPopup(loginRequest)
        .then(handleResponse)
        .catch(error => {
            console.error(error);
        });
}

function handleResponse(resp) {
    if (resp !== null) {
        let username = resp.account.username;
        login(username);
    }
}

async function login(username) {

    let btnIngresar = document.getElementById("btnIngresar");
    btnIngresar.disabled = true;
    btnIngresar.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    const formDataLogin = new FormData();
    formDataLogin.append('correo', username);
    formDataLogin.append('pass', '');

    try {
        const response = await fetch('sql/selectLoginGestor.php', {
            method: 'POST',
            body: formDataLogin,
        });

        const data = await response.json();
        jsonData = data;

        if (!MODO_LOCAL) {
            const accessToken = await obtenerTokenAcceso();
            if (accessToken) {
                sessionStorage.setItem('graphAccessToken', accessToken);
                sessionStorage.setItem('graphUser', username);
            }
        } else {
            sessionStorage.setItem('graphAccessToken', 'TOKEN_FAKE_LOCAL');
            sessionStorage.setItem('graphUser', username);
        }

        if (Object.keys(data).length === 1) {
            inicioSesion(0);
        } else if (Object.keys(data).length > 1) {
            cargaModal();
        } else {
            btnIngresar.innerText = "Ingresar";
            btnIngresar.disabled = false;
            document.getElementById("contenedorError").innerHTML =
                '<div class="alert alert-danger">Usuario no autorizado.</div>';
        }

    } catch (error) {
        console.error("Error en login:", error);
        btnIngresar.innerText = "Ingresar";
        btnIngresar.disabled = false;
    }
}

// =======================================================
// SESIÓN Y NAVEGACIÓN
// =======================================================

/*function inicioSesion(indice) {
    const json = JSON.stringify(jsonData[indice]);
    sessionStorage.setItem('sesion', json);
    window.location.replace('formulario_menu_principal.html');
}*/

function inicioSesion(indice) {
    console.log("✅ Redirigiendo a formulario_menu_principal.html");
    const json = JSON.stringify(jsonData[indice]);
    sessionStorage.setItem('sesion', json);
    window.location.replace('formulario_menu_principal.html');
}

function sesionCompatibildad() {
    if (typeof(Storage) === 'undefined') {
        document.getElementById("contenedorError").innerHTML =
            '<div class="alert alert-danger">Navegador incompatible con Storage</div>';
    }
}
// =======================================================
// TOKEN GRAPH (REAL Y SIMULADO)
// =======================================================

async function obtenerTokenAcceso() {

    if (MODO_LOCAL) {
        console.log("🔧 MODO LOCAL: usando token simulado");
        return "TOKEN_FAKE_LOCAL";
    }

    try {
        const currentAccounts = myMSALObj.getAllAccounts();

        if (currentAccounts.length === 0) {
            console.warn("No hay cuentas activas");
            return null;
        }

        const account = currentAccounts[0];

        const tokenResponse = await myMSALObj.acquireTokenSilent({
            scopes: graphRequest.scopes,
            account: account
        });

        return tokenResponse.accessToken;

    } catch (error) {
        console.warn("Token silencioso falló, intentando popup...", error);

        try {
            const tokenResponse = await myMSALObj.acquireTokenPopup({
                scopes: graphRequest.scopes
            });
            return tokenResponse.accessToken;
        } catch (popupError) {
            console.error("Error obteniendo token:", popupError);
            return null;
        }
    }
}

async function refrescarTokenSiNecesario() {
    if (MODO_LOCAL) return "TOKEN_FAKE_LOCAL";

    const nuevoToken = await obtenerTokenAcceso();
    if (nuevoToken) {
        sessionStorage.setItem('graphAccessToken', nuevoToken);
    }
    return nuevoToken;
}

async function obtenerTokenParaCalendario() {
    if (MODO_LOCAL) return "TOKEN_FAKE_LOCAL";

    let token = sessionStorage.getItem('graphAccessToken');
    if (!token) {
        token = await refrescarTokenSiNecesario();
    }
    return token;
}

// =======================================================
// MICROSOFT GRAPH (CALENDARIO Y TEAMS)
// =======================================================

async function crearEventoEnCalendario(eventoData) {

    if (MODO_LOCAL) {
        console.log("🔧 Simulación creación de evento:", eventoData);
        return { id: "EVENTO_LOCAL_FAKE" };
    }

    const token = await obtenerTokenParaCalendario();

    const response = await fetch(graphConfig.graphEventsEndpoint, {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(eventoData)
    });

    return await response.json();
}

async function crearReunionTeams(meetingData) {

    if (MODO_LOCAL) {
        console.log("🔧 Simulación reunión Teams:", meetingData);
        return { joinUrl: "https://teams.local/fake" };
    }

    const token = await obtenerTokenParaCalendario();

    const response = await fetch(graphConfig.graphOnlineMeetingsEndpoint, {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(meetingData)
    });

    return await response.json();
}

// =======================================================
// MODAL DE SELECCIÓN DE CÓDIGO PRESUPUESTARIO
// =======================================================

function cargaModal() {

    $('.list-group-flush').remove();
    let i = 0;

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
        h5codigo.appendChild(document.createTextNode(obj.Codigo_Presupuestario.substr(-4)));

        columnaCodigo.appendChild(h5codigo);

        let columnaNombre = document.createElement('div');
        columnaNombre.className = "col-sm-10";

        let h5nombre = document.createElement('h5');
        h5nombre.className = "mb-1";
        h5nombre.appendChild(document.createTextNode(obj.Dependencia));

        columnaNombre.appendChild(h5nombre);

        contenedorCodPre.appendChild(columnaCodigo);
        contenedorCodPre.appendChild(columnaNombre);
        linkList.appendChild(contenedorCodPre);

        list.appendChild(linkList);
        document.getElementById('fila').appendChild(list);

        i++;
    });

    $("#loginModal").modal();
}

// =======================================================
// JQUERY EVENTOS
// =======================================================

$(document).ready(function() {
    $("#fila").on("click", "a", function(event) {
        event.preventDefault();
        let indice = $(this).data('id');
        inicioSesion(indice);
        return false;
    });
});
