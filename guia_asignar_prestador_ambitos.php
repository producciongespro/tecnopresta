<?php
// ==============================================================================
// TECNOPRESTA - MINISTERIO DE EDUCACIÓN PÚBLICA (MEP)
// GUÍA PRÁCTICA DE CONFIGURACIÓN RÁPIDA: ASIGNACIÓN DE ROL PRESTADOR, ÁMBITO Y UBICACIÓN
// Control de cabecera segura PHP para protección institucional
// ==============================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guía Práctica TecnoPresta - Asignación de Rol Prestador, Ámbitos y Ubicaciones</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --mep-navy: #0b2545;
            --mep-blue: #134074;
            --mep-cyan: #0284c7;
            --mep-cyan-light: #e0f2fe;
            --bg-body: #f8fafc;
            --bg-card: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --success-bg: #dcfce7;
            --success-text: #166534;
            --warning-bg: #fef9c3;
            --warning-text: #854d0e;
            --danger-bg: #fee2e2;
            --danger-text: #991b1b;
            --info-bg: #f0f9ff;
            --info-text: #0369a1;
            --purple-bg: #f3e8ff;
            --purple-text: #6b21a8;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            line-height: 1.65;
            padding-bottom: 90px;
        }

        /* HEADER INSTITUCIONAL */
        .mep-header {
            background: linear-gradient(135deg, var(--mep-navy) 0%, var(--mep-blue) 100%);
            color: white;
            padding: 22px 32px;
            box-shadow: 0 4px 20px rgba(11, 37, 69, 0.15);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .mep-header-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .mep-branding {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .mep-badge {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.25);
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
        }
        .mep-title h1 {
            font-size: 1.3rem;
            font-weight: 800;
            letter-spacing: -0.01em;
        }
        .mep-title p {
            font-size: 0.82rem;
            color: #93c5fd;
        }
        .btn-header-return {
            background: #ffffff;
            color: var(--mep-blue);
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.82rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }
        .btn-header-return:hover {
            background: #e2e8f0;
            transform: translateY(-1px);
        }

        /* CONTENEDOR */
        .container {
            max-width: 1100px;
            margin: 32px auto;
            padding: 0 24px;
        }

        /* HERO DIRECTO */
        .guide-hero {
            background: linear-gradient(135deg, #ffffff 0%, #f0f9ff 100%);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 32px 36px;
            margin-bottom: 32px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
        }
        .guide-pill {
            display: inline-block;
            background: var(--mep-cyan-light);
            color: var(--mep-blue);
            font-weight: 700;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 4px 12px;
            border-radius: 20px;
            margin-bottom: 12px;
            border: 1px solid #bae6fd;
        }
        .guide-hero h2 {
            font-size: 1.85rem;
            font-weight: 800;
            color: var(--mep-navy);
            letter-spacing: -0.02em;
            margin-bottom: 12px;
            line-height: 1.25;
        }
        .guide-hero p {
            font-size: 0.98rem;
            color: var(--text-main);
            line-height: 1.6;
        }

        /* PRINCIPIO PEDAGÓGICO CLAVE */
        .rule-spotlight {
            background: #ffffff;
            border: 2px solid #0284c7;
            border-radius: 14px;
            padding: 24px 28px;
            margin-bottom: 36px;
            box-shadow: 0 6px 18px rgba(2, 132, 199, 0.08);
            position: relative;
        }
        .rule-spotlight::before {
            content: "PRINCIPIO ARQUITECTÓNICO CLAVE";
            position: absolute;
            top: -12px;
            left: 24px;
            background: #0284c7;
            color: white;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            padding: 3px 12px;
            border-radius: 12px;
        }
        .rule-spotlight-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--mep-navy);
            margin-top: 4px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .rule-spotlight-body {
            font-size: 0.93rem;
            color: var(--text-main);
            line-height: 1.65;
        }
        .example-box {
            background: #f8fafc;
            border-left: 4px solid #0284c7;
            padding: 14px 18px;
            border-radius: 0 8px 8px 0;
            margin-top: 14px;
            font-size: 0.9rem;
        }

        /* PASOS SECUENCIALES */
        .step-block {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 28px;
            margin-bottom: 36px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.04);
        }
        .step-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border-color);
        }
        .step-num-badge {
            width: 40px;
            height: 40px;
            background: var(--mep-navy);
            color: white;
            font-family: 'JetBrains Mono', monospace;
            font-weight: 800;
            font-size: 1.1rem;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .step-header-text h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--mep-navy);
        }
        .step-header-text span {
            font-size: 0.82rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* GRID DE CAPTURA Y EXPLICACIÓN */
        .step-layout {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 24px;
            align-items: start;
        }
        .step-layout.form-wide {
            grid-template-columns: 380px 1fr;
        }
        @media (max-width: 900px) {
            .step-layout, .step-layout.form-wide {
                grid-template-columns: 1fr;
            }
        }
        .step-image-wrap {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 12px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }
        .step-image-wrap img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            display: block;
            margin: 0 auto;
        }
        .step-caption {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-top: 8px;
            font-weight: 600;
        }

        .step-instructions {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .instruction-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 0.92rem;
            line-height: 1.55;
        }
        .instruction-dot {
            width: 8px;
            height: 8px;
            background: var(--mep-cyan);
            border-radius: 50%;
            margin-top: 8px;
            flex-shrink: 0;
        }

        /* TABLA DE CAMPOS */
        .fields-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
            margin-top: 8px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
        }
        .fields-table th {
            background: #f1f5f9;
            color: var(--mep-navy);
            padding: 10px 14px;
            text-align: left;
            font-weight: 700;
            border-bottom: 1px solid var(--border-color);
        }
        .fields-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
        }
        .fields-table tr:last-child td {
            border-bottom: none;
        }

        .tag-pill {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.74rem;
            font-weight: 600;
            font-family: 'JetBrains Mono', monospace;
        }
        .tag-blue { background: #e0f2fe; color: #0369a1; }
        .tag-green { background: #dcfce7; color: #166534; }
        .tag-amber { background: #fef3c7; color: #92400e; }

        /* ALERTAS */
        .callout {
            border-left: 4px solid;
            padding: 14px 18px;
            border-radius: 0 8px 8px 0;
            font-size: 0.9rem;
            margin: 12px 0;
        }
        .callout-warning {
            background: var(--warning-bg);
            border-color: #eab308;
            color: var(--warning-text);
        }
        .callout-info {
            background: var(--info-bg);
            border-color: var(--mep-cyan);
            color: var(--info-text);
        }

        /* FOOTER DE RETORNO */
        .footer-nav {
            margin-top: 48px;
            display: flex;
            justify-content: center;
            align-items: center;
            padding-top: 24px;
            border-top: 1px solid var(--border-color);
        }
        .nav-btn {
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.92rem;
            background: var(--mep-navy);
            color: white;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .nav-btn:hover {
            background: var(--mep-blue);
            transform: translateY(-1px);
        }
    </style>
</head>
<body>

    <!-- CABECERA INSTITUCIONAL MEP -->
    <header class="mep-header">
        <div class="mep-header-inner">
            <div class="mep-branding">
                <div class="mep-badge">MEP - DRTE</div>
                <div class="mep-title">
                    <h1>Guía Rápida de Administración</h1>
                    <p>Asignación de Rol Prestador, Definición de Ámbitos y Lugares de Resguardo</p>
                </div>
            </div>
            <div>
                <a href="navegar.php?ruta=formulario_buscar_prestadores_n.php&subsistema_id=1&modulo_id=2&formulario_id=10" class="btn-header-return">
                    <span>←</span> Volver al Menú del Manual
                </a>
            </div>
        </div>
    </header>

    <div class="container">

        <!-- HERO DE LA GUÍA -->
        <section class="guide-hero">
            <span class="guide-pill">Procedimiento Específico • Paso a Paso</span>
            <h2>Cómo habilitar a un funcionario para prestar activos tecnológicos</h2>
            <p>
                Esta guía orienta de forma clara y directa al Administrador o Director del centro educativo en el proceso de registrar un usuario con <strong>Rol Prestador</strong>, configurar su <strong>Ámbito</strong> de acción y vincular las <strong>Ubicaciones físicas</strong> sobre las cuales tendrá potestad para prestar activos y registrar devoluciones o incidencias técnicas.
            </p>
        </section>

        <!-- PRINCIPIO PEDAGÓGICO Y REGLA DE NEGOCIO -->
        <section class="rule-spotlight">
            <div class="rule-spotlight-title">
                <span>🎯</span> Criterio Recomendado: Relación de Ámbito y Ubicación Específica
            </div>
            <div class="rule-spotlight-body">
                El sistema TecnoPresta ofrece flexibilidad para que un prestador posea varios ámbitos y cada ámbito agrupe múltiples lugares físicos de resguardo. Sin embargo, <strong>la recomendación institucional de gestión y control es asignar a cada funcionario un único ámbito estrechamente vinculado a su espacio real de trabajo</strong>.
                
                <div class="example-box">
                    <strong>Ejemplo Práctico:</strong><br>
                    Si el docente <em>Juan Perico Palote</em> imparte la asignatura de <em>Formación Tecnológica</em> en el <strong>Laboratorio de Cómputo</strong>, su ámbito ideal debe denominarse <code>Innovación Educativa</code> o <code>Laboratorio Informática</code> y tener marcada <strong>únicamente la ubicación "LABORATORIO"</strong>.<br>
                    De este modo, el docente solo gestionará los equipos que tiene a su vista y custodia directa. Si a este mismo funcionario se le marcan simultáneamente <em>Bodega</em>, <em>Biblioteca</em> y <em>Oficinas Administrativas</em>, su visibilidad de activos se dispersará, complicando la verificación física del inventario y la rendición de cuentas.
                </div>
            </div>
        </section>

        <!-- =========================================================================
             PASO 1: ACCESO AL MENÚ PRINCIPAL
        ========================================================================== -->
        <div class="step-block">
            <div class="step-header">
                <div class="step-num-badge">1</div>
                <div class="step-header-text">
                    <h3>Acceder a Gestión de Inventario</h3>
                    <span>Menú Principal de TecnoPresta</span>
                </div>
            </div>
            <div class="step-layout">
                <div class="step-image-wrap">
                    <img src="assets/guia_prestador_01_menu_inventario.png" alt="Acceso al menú Gestión de Inventario">
                    <div class="step-caption">Tarjeta principal del subsistema Gestión de Inventario</div>
                </div>
                <div class="step-instructions">
                    <div class="instruction-item">
                        <div class="instruction-dot"></div>
                        <div>Ingrese a la plataforma con su usuario institucional con permisos de administración en el centro educativo.</div>
                    </div>
                    <div class="instruction-item">
                        <div class="instruction-dot"></div>
                        <div>En el Menú Principal, localice la tarjeta <strong>Gestión de Inventario</strong> (Registro, control y préstamo de activos tecnológicos).</div>
                    </div>
                    <div class="instruction-item">
                        <div class="instruction-dot"></div>
                        <div>Haga clic en el botón o acceso denominado <strong>Gestión del Centro Educativo →</strong>.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- =========================================================================
             PASO 2: SUB-MÓDULO GESTIÓN DEL CENTRO EDUCATIVO
        ========================================================================== -->
        <div class="step-block">
            <div class="step-header">
                <div class="step-num-badge">2</div>
                <div class="step-header-text">
                    <h3>Ingresar a Usuarios del Centro</h3>
                    <span>Formulario de administración de permisos locales</span>
                </div>
            </div>
            <div class="step-layout">
                <div class="step-image-wrap">
                    <img src="assets/guia_prestador_02_submodulo_gestion_centro.png" alt="Tarjeta Gestión del Centro Educativo">
                    <div class="step-caption">Selección del formulario Usuarios del Centro</div>
                </div>
                <div class="step-instructions">
                    <div class="instruction-item">
                        <div class="instruction-dot"></div>
                        <div>Dentro del panel de <strong>Gestión del Centro Educativo</strong> (Información y usuarios del centro), verá la lista de formularios disponibles.</div>
                    </div>
                    <div class="instruction-item">
                        <div class="instruction-dot"></div>
                        <div>Haga clic sobre el botón interactivo <strong>Usuarios del Centro →</strong>.</div>
                    </div>
                    <div class="instruction-item">
                        <div class="instruction-dot"></div>
                        <div>El sistema cargará la consola <em>"Agregar Usuarios con Permisos"</em>, mostrando a la derecha el listado de funcionarios activos en la institución.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- =========================================================================
             PASO 3: SELECCIÓN DEL ROL Y DATOS DEL FUNCIONARIO
        ========================================================================== -->
        <div class="step-block">
            <div class="step-header">
                <div class="step-num-badge">3</div>
                <div class="step-header-text">
                    <h3>Seleccionar Rol Prestador y Digitar Datos</h3>
                    <span>Nivel de usuario y credenciales institucionales</span>
                </div>
            </div>
            <div class="step-layout form-wide">
                <div class="step-image-wrap">
                    <img src="assets/guia_prestador_03_formulario_usuarios_permisos_inicial.png" alt="Formulario Agregar Usuarios con Permisos">
                    <div class="step-caption">Panel de selección de nivel: Prestador vs. Inventariador</div>
                </div>
                <div class="step-instructions">
                    <div class="instruction-item">
                        <div class="instruction-dot"></div>
                        <div>
                            <strong>Seleccione el Nivel de Usuario:</strong> En la sección superior, haga clic en la tarjeta <strong>Prestador</strong> (Docente. Préstamos y reporte de incidencias).
                        </div>
                    </div>

                    <table class="fields-table">
                        <thead>
                            <tr>
                                <th>Campo</th>
                                <th>Instrucción de Llenado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Cédula</strong></td>
                                <td>Digite el número de identificación del funcionario en formato de 10 dígitos (ej. <code>0109670579</code>).</td>
                            </tr>
                            <tr>
                                <td><strong>Correo MEP</strong></td>
                                <td>Escriba únicamente la parte anterior al dominio (ej. <code>juan.perico.palote</code>). El sufijo <code>@mep.go.cr</code> ya está incorporado automáticamente.</td>
                            </tr>
                            <tr>
                                <td><strong>Código Presupuestario</strong></td>
                                <td>Campo bloqueado con candado 🔒. Se precarga de forma automática con el código de 4 dígitos del centro educativo (ej. <code>4071</code>).</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="callout callout-info">
                        <strong>Detección Dinámica:</strong> Al hacer clic en <strong>Prestador</strong>, el formulario despliega de inmediato la sección inferior interactiva titulada <strong>ÁMBITOS DE PRÉSTAMO</strong>.
                    </div>
                </div>
            </div>
        </div>

        <!-- =========================================================================
             PASO 4: CONFIGURACIÓN DEL ÁMBITO Y LUGARES DE RESGUARDO
        ========================================================================== -->
        <div class="step-block">
            <div class="step-header">
                <div class="step-num-badge">4</div>
                <div class="step-header-text">
                    <h3>Definir el Ámbito y Asociar las Ubicaciones</h3>
                    <span>Delimitación de los activos prestables</span>
                </div>
            </div>
            <div class="step-layout form-wide">
                <div class="step-image-wrap">
                    <img src="assets/guia_prestador_04_formulario_prestador_ambitos_ubicaciones.png" alt="Formulario desplegado con ámbitos y checkboxes de lugares">
                    <div class="step-caption">Bloque de Ámbitos de Préstamo con casillas de verificación</div>
                </div>
                <div class="step-instructions">
                    <div class="instruction-item">
                        <div class="instruction-dot"></div>
                        <div>
                            <strong>Nombre del Ámbito (Ámbito #1):</strong> Escriba un nombre claro y descriptivo que identifique el área o programa pedagógico (ej. <code>Innovación Educativa</code>, <code>Equipo INCO</code>, o <code>Laboratorio Informática</code>).
                        </div>
                    </div>
                    <div class="instruction-item">
                        <div class="instruction-dot"></div>
                        <div>
                            <strong>Selección de Lugares (Artículos que podrá prestar):</strong> Marque las casillas de verificación que correspondan estrictamente a la custodia física del docente:
                            <div style="margin: 8px 0 0 10px; display:flex; flex-direction:column; gap:4px; font-size:0.88rem;">
                                <span>☑️ <strong>LABORATORIO:</strong> Marcar si el funcionario custodia el laboratorio de informática.</span>
                                <span>⬜ <strong>BIBLIOTECA:</strong> Dejar desmarcado si la biblioteca cuenta con su propio bibliotecólogo o encargado.</span>
                                <span>⬜ <strong>BODEGA / OFICINAS:</strong> Solo marcar si el funcionario también administra el resguardo general de suministros.</span>
                            </div>
                        </div>
                    </div>
                    <div class="instruction-item">
                        <div class="instruction-dot"></div>
                        <div>
                            <strong>Múltiples Ámbitos (Opcional):</strong> Si por excepción el funcionario asume dos funciones distintas en diferentes espacios físicos, puede presionar el botón <strong>+ Agregar ámbito</strong> para crear un segundo contenedor separado.
                        </div>
                    </div>
                    <div class="instruction-item">
                        <div class="instruction-dot"></div>
                        <div>
                            <strong>Finalizar y Guardar:</strong> Haga clic en el botón oscuro <strong>💾 Guardar</strong> al pie del formulario. El usuario aparecerá de inmediato en la tabla lateral derecha acreditado con su rol, correo y ámbito asociado.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RESUMEN FINAL -->
        <div class="callout callout-warning">
            <strong>✅ Verificación del Prestador:</strong> Una vez guardado, cuando el docente inicie sesión con su usuario y contraseña MEP, el módulo de <em>Préstamos de Activos</em> únicamente le mostrará los equipos inventariados en las ubicaciones que fueron marcadas en su ámbito.
        </div>

        <!-- RETORNO ÚNICO AL MANUAL -->
        <div class="footer-nav">
            <a href="../navegar.php?ruta=acerca_de_n.php&seccion=manual" class="nav-btn">
                ← Volver al Menú del Manual
            </a>
        </div>

    </div>

</body>
</html>
