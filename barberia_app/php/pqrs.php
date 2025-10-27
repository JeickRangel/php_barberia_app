<?php

require_once "cors.php"; 
header("Content-Type: application/json; charset=utf-8");

require_once "conexion.php";

$method = $_SERVER['REQUEST_METHOD'];

/* --- Helpers --- */
function allowed($value, $set) {
  return in_array($value, $set, true);
}

$ENUM_TIPO = ['Petición','Queja','Reclamo','Sugerencia'];
$ENUM_ESTADO = ['pendiente','en_proceso','resuelta','cerrada'];
$ENUM_PRIORIDAD = ['baja','media','alta'];
$ENUM_CANAL = ['web','app','telefono','presencial'];

switch ($method) {

  /* ============ LISTAR (con filtros) ============ */
  case 'GET':
    $where = [];

    if (isset($_GET['cliente_id']) && $_GET['cliente_id'] !== '') {
      $cliente_id = intval($_GET['cliente_id']);
      $where[] = "p.cliente_id = $cliente_id";
    }

    if (isset($_GET['estado']) && $_GET['estado'] !== '') {
      $estado = $conn->real_escape_string($_GET['estado']);
      if (!allowed($estado, $ENUM_ESTADO)) {
        echo json_encode(["status" => "ERROR", "message" => "Estado inválido"]);
        exit();
      }
      $where[] = "p.estado = '$estado'";
    }

    if (isset($_GET['tipo']) && $_GET['tipo'] !== '') {
      $tipo = $conn->real_escape_string($_GET['tipo']);
      if (!allowed($tipo, $ENUM_TIPO)) {
        echo json_encode(["status" => "ERROR", "message" => "Tipo inválido"]);
        exit();
      }
      $where[] = "p.tipo = '$tipo'";
    }

    if (!empty($_GET['fecha_inicio']) && !empty($_GET['fecha_fin'])) {
      $fi = $conn->real_escape_string($_GET['fecha_inicio']); // YYYY-MM-DD
      $ff = $conn->real_escape_string($_GET['fecha_fin']);    // YYYY-MM-DD
      $where[] = "DATE(p.created_at) BETWEEN '$fi' AND '$ff'";
    }

    if (!empty($_GET['q'])) {
      $q = $conn->real_escape_string($_GET['q']);
      $where[] = "(p.descripcion LIKE '%$q%' OR u.nombre LIKE '%$q%')";
    }

    $whereSql = count($where) ? "WHERE " . implode(" AND ", $where) : "";

    $sql = "SELECT
              p.id_pqrs,
              p.cliente_id,
              u.nombre AS cliente_nombre,
              p.tipo,
              p.descripcion,
              p.estado,
              p.prioridad,
              p.canal,
              p.created_at,
              p.updated_at,
              p.adjunto_url
            FROM pqrs p
            JOIN usuarios u ON p.cliente_id = u.id
            $whereSql
            ORDER BY p.created_at DESC";

    $res = $conn->query($sql);
    if (!$res) {
      echo json_encode(["status" => "ERROR", "message" => $conn->error]);
      exit();
    }

    $out = [];
    while ($row = $res->fetch_assoc()) $out[] = $row;
    echo json_encode($out);
    break;

  /* ============ CREAR ============ */
  case 'POST':
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data || !isset($data['cliente_id'], $data['tipo'], $data['descripcion'])) {
      echo json_encode(["status" => "ERROR", "message" => "Datos incompletos"]);
      exit();
    }

    $cliente_id  = intval($data['cliente_id']);
    $tipo        = $data['tipo'];
    $descripcion = $data['descripcion'];
    $prioridad   = $data['prioridad'] ?? 'media';
    $canal       = $data['canal'] ?? 'web';
    $adjunto_url = $data['adjunto_url'] ?? null;

    if (!allowed($tipo, $ENUM_TIPO)) {
      echo json_encode(["status" => "ERROR", "message" => "Tipo inválido"]);
      exit();
    }
    if (!allowed($prioridad, $ENUM_PRIORIDAD)) {
      echo json_encode(["status" => "ERROR", "message" => "Prioridad inválida"]);
      exit();
    }
    if (!allowed($canal, $ENUM_CANAL)) {
      echo json_encode(["status" => "ERROR", "message" => "Canal inválido"]);
      exit();
    }

    $stmt = $conn->prepare(
      "INSERT INTO pqrs (cliente_id, tipo, descripcion, estado, prioridad, canal, adjunto_url)
       VALUES (?, ?, ?, 'pendiente', ?, ?, ?)"
    );
    $stmt->bind_param(
      "isssss",
      $cliente_id,
      $tipo,
      $descripcion,
      $prioridad,
      $canal,
      $adjunto_url
    );

    if ($stmt->execute()) {
      echo json_encode(["status" => "OK", "message" => "PQRS creado", "id" => $stmt->insert_id]);
    } else {
      echo json_encode(["status" => "ERROR", "message" => $stmt->error]);
    }
    break;

  /* ============ ACTUALIZAR (estado/prioridad/descripcion/adjunto) ============ */
  case 'PUT':
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data || !isset($data['id_pqrs'])) {
      echo json_encode(["status" => "ERROR", "message" => "ID no proporcionado"]);
      exit();
    }

    $id = intval($data['id_pqrs']);
    $campos = [];
    $params = [];
    $types  = "";

    if (isset($data['estado'])) {
      $estado = $data['estado'];
      if (!allowed($estado, $ENUM_ESTADO)) {
        echo json_encode(["status" => "ERROR", "message" => "Estado inválido"]);
        exit();
      }
      $campos[] = "estado=?";
      $params[] = $estado;
      $types   .= "s";
    }

    if (isset($data['prioridad'])) {
      $prioridad = $data['prioridad'];
      if (!allowed($prioridad, $ENUM_PRIORIDAD)) {
        echo json_encode(["status" => "ERROR", "message" => "Prioridad inválida"]);
        exit();
      }
      $campos[] = "prioridad=?";
      $params[] = $prioridad;
      $types   .= "s";
    }

    if (isset($data['descripcion'])) {
      $campos[] = "descripcion=?";
      $params[] = $data['descripcion'];
      $types   .= "s";
    }

    if (isset($data['adjunto_url'])) {
      $campos[] = "adjunto_url=?";
      $params[] = $data['adjunto_url'];
      $types   .= "s";
    }

    if (empty($campos)) {
      echo json_encode(["status" => "ERROR", "message" => "Nada para actualizar"]);
      exit();
    }

    $sql = "UPDATE pqrs SET ".implode(",", $campos)." WHERE id_pqrs=?";
    $stmt = $conn->prepare($sql);
    $types .= "i";
    $params[] = $id;

    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
      echo json_encode(["status" => "OK", "message" => "PQRS actualizado"]);
    } else {
      echo json_encode(["status" => "ERROR", "message" => $stmt->error]);
    }
    break;

  /* ============ ELIMINAR ============ */
  case 'DELETE':
    $data = json_decode(file_get_contents("php://input"), true);
    $id = $data['id'] ?? null;

    if (!$id) {
      echo json_encode(["status" => "ERROR", "message" => "ID no proporcionado"]);
      exit();
    }

    $id = intval($id);
    $stmt = $conn->prepare("DELETE FROM pqrs WHERE id_pqrs=?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
      echo json_encode(["status" => "OK", "message" => "PQRS eliminado"]);
    } else {
      echo json_encode(["status" => "ERROR", "message" => $stmt->error]);
    }
    break;

  default:
    http_response_code(405);
    echo json_encode(["status" => "ERROR", "message" => "Método no permitido"]);
}
