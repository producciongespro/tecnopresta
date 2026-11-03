<?php
/**
 * ============================================================
 * ENDPOINT: Gestor de Usuarios - Acciones CRUD
 * ============================================================
 * Proposito: Procesa asignar, editar y eliminar roles de usuarios
 * desde el formulario gestor_usuarios_n.php.
 *
 * Acciones soportadas:
 *   - asignar: INSERT INTO usuarios_roles
 *   - editar: UPDATE usuarios_roles SET rol_id = ?, codigo_presu = ?
 *   - eliminar: UPDATE usuarios_roles SET eliminado = 1
 *
 * Seguridad:
 *   - Valida sesion Azure
 *   - Solo Root
 *   - Prepared statements
 *   - Transaccion completa
 * ============================================================
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/usuarioAzure.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/sql/bd.php';
require_once __DIR__ . '/sql/ws_mep.php';

$usuario_azure = obtenerUsuarioSesion();
if (!$usuario_azure) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesion invalida']);
    exit;
}

if (!esUsuarioRoot()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

/**
 * Valida que el codigo presupuestario pertenezca a uno de los
 * centros actuales del funcionario segun el WS del MEP.
 *
 * Politica:
 * - Si el WS devuelve centros, el codigo debe coincidir con uno.
 * - Si el WS falla o no devuelve centros, se permite guardar con
 *   una advertencia para no bloquear la administracion.
 *
 * @return array{ok: bool, message: string, warning: string, centros: array<int, array{codigo: string, dependencia: string}>}
 */
function validarCodigoPresupuestario(PDO $conexionBD, int $usuarioId, string $codigoPresu): array
{
    $stmtUsuario = $conexionBD->prepare(
        "SELECT cedula FROM usuarios WHERE id = ? AND eliminado = 0 LIMIT 1"
    );
    $stmtUsuario->execute([$usuarioId]);
    $cedula = $stmtUsuario->fetchColumn();

    if ($cedula === false || trim((string) $cedula) === '') {
        return [
            'ok' => false,
            'message' => 'Usuario no encontrado o eliminado',
            'warning' => '',
            'centros' => [],
        ];
    }

    $cedula = trim((string) $cedula);
    $cedulaWs = preg_replace('/\D/', '', $cedula);

    // Validacion basica de formato, independiente de la disponibilidad
    // del WS. Evita guardar codigos vacios o con caracteres invalidos.
    if (!preg_match('/^\d{3,4}$/', $codigoPresu)) {
        return [
            'ok' => false,
            'message' => 'El código presupuestario debe tener 3 o 4 dígitos',
            'warning' => '',
            'centros' => [],
        ];
    }

    try {
        $registrosWs = consultarFuncionarioWS($cedulaWs !== '' ? $cedulaWs : $cedula);
        $centrosWs = normalizarCentrosMep($registrosWs);
    } catch (Throwable $e) {
        error_log(
            'No se pudo validar el codigo presupuestario con el MEP. ' .
            'usuario_id=' . $usuarioId . ' cedula=' . $cedula . ' error=' . $e->getMessage()
        );

        return [
            'ok' => true,
            'message' => '',
            'warning' => 'No se pudo validar el código con el MEP; verifique el centro educativo.',
            'centros' => [],
        ];
    }

    if (empty($centrosWs)) {
        return [
            'ok' => true,
            'message' => '',
            'warning' => 'El MEP no reportó centros educativos; verifique el código presupuestario.',
            'centros' => [],
        ];
    }

    $permitidos = array_column($centrosWs, 'codigo');
    if (!in_array($codigoPresu, $permitidos, true)) {
        $listaCentros = implode(', ', array_map(function (array $centro): string {
            return $centro['codigo'] . ($centro['dependencia'] !== '' ? ' - ' . $centro['dependencia'] : '');
        }, $centrosWs));

        return [
            'ok' => false,
            'message' => 'El código presupuestario ' . $codigoPresu .
                ' no corresponde a ninguno de los centros actuales del funcionario en el MEP. ' .
                'Centros válidos: ' . $listaCentros,
            'warning' => '',
            'centros' => $centrosWs,
        ];
    }

    return [
        'ok' => true,
        'message' => '',
        'warning' => '',
        'centros' => $centrosWs,
    ];
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['accion'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Peticion invalida: se requiere accion']);
    exit;
}

$accion = $input['accion'];

try {
    $conexionBD = BD::crearInstancia();
    $conexionBD->beginTransaction();

    switch ($accion) {

        case 'asignar':
            $usuario_id = isset($input['usuario_id']) ? (int)$input['usuario_id'] : 0;
            $rol_id = isset($input['rol_id']) ? (int)$input['rol_id'] : 0;
            $subsistema_id = isset($input['subsistema_id']) ? (int)$input['subsistema_id'] : 0;
            $codigo_presu = (isset($input['codigo_presu']) && is_scalar($input['codigo_presu']))
                ? trim((string)$input['codigo_presu'])
                : '';

            if ($usuario_id <= 0 || $rol_id <= 0 || $subsistema_id <= 0) {
                $conexionBD->rollBack();
                echo json_encode(['success' => false, 'message' => 'Datos incompletos: se requieren usuario_id, rol_id y subsistema_id']);
                exit;
            }

            // Validar el codigo contra los centros actuales del MEP.
            $validacionCodigo = validarCodigoPresupuestario($conexionBD, $usuario_id, $codigo_presu);
            if (!$validacionCodigo['ok']) {
                $conexionBD->rollBack();
                echo json_encode([
                    'success' => false,
                    'message' => $validacionCodigo['message']
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $warningCodigo = $validacionCodigo['warning'];

            // Verificar que no exista ya la asignacion
            $sqlCheck = "SELECT id FROM usuarios_roles WHERE usuario_id = ? AND rol_id = ? AND subsistema_id = ? AND codigo_presu = ? AND eliminado = 0";
            $stmtCheck = $conexionBD->prepare($sqlCheck);
            $stmtCheck->execute([$usuario_id, $rol_id, $subsistema_id, $codigo_presu]);
            if ($stmtCheck->fetch()) {
                $conexionBD->rollBack();
                echo json_encode(['success' => false, 'message' => 'Este usuario ya tiene este rol asignado en este subsistema']);
                exit;
            }

            // Verificar si existe pero eliminado (reactivar)
            $sqlCheckEliminado = "SELECT id FROM usuarios_roles WHERE usuario_id = ? AND rol_id = ? AND subsistema_id = ? AND codigo_presu = ? AND eliminado = 1";
            $stmtCheckElim = $conexionBD->prepare($sqlCheckEliminado);
            $stmtCheckElim->execute([$usuario_id, $rol_id, $subsistema_id, $codigo_presu]);
            $rowElim = $stmtCheckElim->fetch();

            if ($rowElim) {
                // Reactivar registro eliminado
                $sqlReactivar = "UPDATE usuarios_roles SET eliminado = 0, updated_at = NOW() WHERE id = ?";
                $stmtReactivar = $conexionBD->prepare($sqlReactivar);
                $stmtReactivar->execute([$rowElim['id']]);
                $nuevo_id = $rowElim['id'];
            } else {
                // Obtener created_by del usuario actual
                $sqlCreatedBy = "SELECT id FROM usuarios WHERE cedula = ? LIMIT 1";
                $stmtCreatedBy = $conexionBD->prepare($sqlCreatedBy);
                $stmtCreatedBy->execute([$usuario_azure['cedula'] ?? '']);
                $rowCreatedBy = $stmtCreatedBy->fetch();
                $created_by = $rowCreatedBy ? $rowCreatedBy['id'] : null;

                // Insertar nueva asignacion
                $sqlInsert = "INSERT INTO usuarios_roles (usuario_id, rol_id, subsistema_id, codigo_presu, eliminado, created_by, created_at) VALUES (?, ?, ?, ?, 0, ?, NOW())";
                $stmtInsert = $conexionBD->prepare($sqlInsert);
                $stmtInsert->execute([$usuario_id, $rol_id, $subsistema_id, $codigo_presu, $created_by]);
                $nuevo_id = $conexionBD->lastInsertId();
            }

            $conexionBD->commit();
            echo json_encode([
                'success' => true,
                'message' => 'Rol asignado correctamente',
                'warning' => $warningCodigo,
                'data' => ['id' => $nuevo_id]
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            $usuario_rol_id = isset($input['usuario_rol_id']) ? (int)$input['usuario_rol_id'] : 0;
            $nuevo_rol_id = isset($input['nuevo_rol_id']) ? (int)$input['nuevo_rol_id'] : 0;
            $codigo_presu = (isset($input['codigo_presu']) && is_scalar($input['codigo_presu']))
                ? trim((string)$input['codigo_presu'])
                : '';

            if ($usuario_rol_id <= 0 || $nuevo_rol_id <= 0) {
                $conexionBD->rollBack();
                echo json_encode(['success' => false, 'message' => 'Datos incompletos: se requieren usuario_rol_id y nuevo_rol_id']);
                exit;
            }

            // Verificar que el registro exista
            $sqlExist = "SELECT id, usuario_id, rol_id, subsistema_id, codigo_presu FROM usuarios_roles WHERE id = ? AND eliminado = 0";
            $stmtExist = $conexionBD->prepare($sqlExist);
            $stmtExist->execute([$usuario_rol_id]);
            $rowExist = $stmtExist->fetch();

            if (!$rowExist) {
                $conexionBD->rollBack();
                echo json_encode(['success' => false, 'message' => 'Registro de rol no encontrado']);
                exit;
            }

            // Si el frontend no envia codigo, conservar el actual como respaldo.
            if ($codigo_presu === '') {
                $codigo_presu = trim((string)$rowExist['codigo_presu']);
            }

            // Validar el codigo contra los centros actuales del MEP.
            $validacionCodigo = validarCodigoPresupuestario(
                $conexionBD,
                (int)$rowExist['usuario_id'],
                $codigo_presu
            );
            if (!$validacionCodigo['ok']) {
                $conexionBD->rollBack();
                echo json_encode([
                    'success' => false,
                    'message' => $validacionCodigo['message']
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $warningCodigo = $validacionCodigo['warning'];

            // Verificar que no exista ya el nuevo rol para este usuario/subsistema.
            // Se usa el NUEVO codigo presupuestario, no el anterior.
            $sqlDup = "SELECT id FROM usuarios_roles WHERE usuario_id = ? AND rol_id = ? AND subsistema_id = ? AND codigo_presu = ? AND eliminado = 0 AND id != ?";
            $stmtDup = $conexionBD->prepare($sqlDup);
            $stmtDup->execute([$rowExist['usuario_id'], $nuevo_rol_id, $rowExist['subsistema_id'], $codigo_presu, $usuario_rol_id]);
            if ($stmtDup->fetch()) {
                $conexionBD->rollBack();
                echo json_encode(['success' => false, 'message' => 'Este usuario ya tiene el nuevo rol asignado en este subsistema y centro educativo']);
                exit;
            }

            // Actualizar rol y codigo presupuestario en una sola operacion.
            $sqlUpdate = "UPDATE usuarios_roles SET rol_id = ?, codigo_presu = ?, updated_at = NOW() WHERE id = ?";
            $stmtUpdate = $conexionBD->prepare($sqlUpdate);
            $stmtUpdate->execute([$nuevo_rol_id, $codigo_presu, $usuario_rol_id]);

            $conexionBD->commit();
            echo json_encode([
                'success' => true,
                'message' => 'Rol y código presupuestario actualizados correctamente',
                'warning' => $warningCodigo
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'eliminar':
            $usuario_rol_id = isset($input['usuario_rol_id']) ? (int)$input['usuario_rol_id'] : 0;

            if ($usuario_rol_id <= 0) {
                $conexionBD->rollBack();
                echo json_encode(['success' => false, 'message' => 'Datos incompletos: se requiere usuario_rol_id']);
                exit;
            }

            // Verificar que exista
            $sqlExist2 = "SELECT id FROM usuarios_roles WHERE id = ? AND eliminado = 0";
            $stmtExist2 = $conexionBD->prepare($sqlExist2);
            $stmtExist2->execute([$usuario_rol_id]);
            if (!$stmtExist2->fetch()) {
                $conexionBD->rollBack();
                echo json_encode(['success' => false, 'message' => 'Registro de rol no encontrado']);
                exit;
            }

            // Eliminado logico
            $sqlDelete = "UPDATE usuarios_roles SET eliminado = 1, updated_at = NOW() WHERE id = ?";
            $stmtDelete = $conexionBD->prepare($sqlDelete);
            $stmtDelete->execute([$usuario_rol_id]);

            $conexionBD->commit();
            echo json_encode([
                'success' => true,
                'message' => 'Rol eliminado correctamente'
            ]);
            break;

        default:
            $conexionBD->rollBack();
            echo json_encode(['success' => false, 'message' => 'Accion no reconocida: ' . $accion]);
            break;
    }

} catch (Exception $e) {
    if (isset($conexionBD) && $conexionBD->inTransaction()) {
        $conexionBD->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
}
