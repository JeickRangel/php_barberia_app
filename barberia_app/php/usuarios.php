<?php
// usuarios.php
require_once "cors.php";           // 👈 maneja CORS y preflight
require_once "conexion.php";

header("Content-Type: application/json; charset=utf-8");

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

  /* ===================== GET ===================== */
  case 'GET':
    if (isset($_GET['id'])) {
      $id = intval($_GET['id']);
      $sql = "
        SELECT u.id, u.nombre, u.email AS correo, u.telefono, u.foto_url,
               u.genero, u.tipo_documento, u.numero_documento,
               u.rol_id AS rol, r.nombre AS rol_nombre
        FROM usuarios u
        INNER JOIN roles r ON u.rol_id = r.id
        WHERE u.id = $id
        LIMIT 1";
      $res = $conn->query($sql);
      if (!$res) { echo json_encode(["status"=>"ERROR","message"=>$conn->error]); exit(); }
      echo json_encode($res->fetch_assoc() ?: null);
      break;
    }

    if (isset($_GET['rol'])) {
      $rol = intval($_GET['rol']);
      $sql = "
        SELECT u.id, u.nombre, u.email AS correo, u.telefono, u.foto_url,
               u.genero, u.tipo_documento, u.numero_documento,
               u.rol_id AS rol, r.nombre AS rol_nombre
        FROM usuarios u
        INNER JOIN roles r ON u.rol_id = r.id
        WHERE u.rol_id = $rol";
    } else {
      $sql = "
        SELECT u.id, u.nombre, u.email AS correo, u.telefono, u.foto_url,
               u.genero, u.tipo_documento, u.numero_documento,
               u.rol_id AS rol, r.nombre AS rol_nombre
        FROM usuarios u
        INNER JOIN roles r ON u.rol_id = r.id";   // 👈 corregido (INNER JOIN)
    }

    $result = $conn->query($sql);
    if (!$result) { echo json_encode(["status"=>"ERROR","message"=>$conn->error]); exit(); }

    $usuarios = [];
    while ($row = $result->fetch_assoc()) { $usuarios[] = $row; }
    echo json_encode($usuarios);
    break;

  /* ===================== POST ===================== */
  case 'POST':
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data || !isset($data['nombre'], $data['correo'], $data['rol'])) {
      echo json_encode(["status"=>"ERROR","message"=>"Datos incompletos"]); exit();
    }

    $nombre   = $data['nombre'];
    $correo   = $data['correo'];
    $telefono = $data['telefono'] ?? null;
    $foto_url = $data['foto_url'] ?? null;
    $genero   = $data['genero'] ?? null;
    $tipo_doc = $data['tipo_documento'] ?? null;
    $num_doc  = $data['numero_documento'] ?? null;
    $rol      = intval($data['rol']);
    $password = password_hash("123456", PASSWORD_BCRYPT);

    $check = $conn->prepare("SELECT id FROM usuarios WHERE email=? OR numero_documento=?");
    $check->bind_param("ss", $correo, $num_doc);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) { echo json_encode(["status"=>"ERROR","message"=>"El correo o documento ya está registrado"]); exit(); }
    $check->close();

    $stmt = $conn->prepare("
      INSERT INTO usuarios
        (nombre, email, password_hash, telefono, foto_url, genero, tipo_documento, numero_documento, rol_id, estado)
      VALUES
        (?,?,?,?,?,?,?,?,?,1)
    ");
    $stmt->bind_param("ssssssssi", $nombre, $correo, $password, $telefono, $foto_url, $genero, $tipo_doc, $num_doc, $rol);

    if ($stmt->execute()) echo json_encode(["status"=>"OK","message"=>"Usuario creado","id"=>$stmt->insert_id]);
    else echo json_encode(["status"=>"ERROR","message"=>$stmt->error]);
    break;

  /* ===================== PUT (dinámico) ===================== */
  case 'PUT':
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data || !isset($data['id'])) {
      echo json_encode(["status"=>"ERROR","message"=>"ID no proporcionado"]); exit();
    }

    // Reset rápido (opcional)
    if (isset($data['resetPassword']) && $data['resetPassword'] === true) {
      $id = intval($data['id']);
      $newPass = password_hash("123456", PASSWORD_BCRYPT);
      $stmt = $conn->prepare("UPDATE usuarios SET password_hash=? WHERE id=?");
      $stmt->bind_param("si", $newPass, $id);
      if ($stmt->execute()) echo json_encode(["status"=>"OK","message"=>"Contraseña reiniciada a 123456"]);
      else echo json_encode(["status"=>"ERROR","message"=>$stmt->error]);
      break;
    }

    $id         = intval($data['id']);
    $nombre     = $data['nombre'] ?? null;
    $telefono   = $data['telefono'] ?? null;
    $foto_url   = $data['foto_url'] ?? null;
    $genero     = $data['genero'] ?? null;
    $tipo_doc   = $data['tipo_documento'] ?? null;
    $num_doc    = $data['numero_documento'] ?? null;

    // cambio de email solo si viene explícito
    $cambiarEmail = !empty($data['cambiarEmail']);
    $correoNuevo  = $cambiarEmail ? ($data['correo'] ?? null) : null;

    // valida duplicados si cambia email/doc
    if ($cambiarEmail || $num_doc !== null) {
      $correoChk = $cambiarEmail ? $correoNuevo : "";
      $numDocChk = $num_doc ?? "";
      $check = $conn->prepare("SELECT id FROM usuarios WHERE (email=? OR numero_documento=?) AND id<>?");
      $check->bind_param("ssi", $correoChk, $numDocChk, $id);
      $check->execute();
      $check->store_result();
      if ($check->num_rows > 0) {
        echo json_encode(["status"=>"ERROR","message"=>"El correo o documento ya está en uso por otro usuario"]); exit();
      }
      $check->close();
    }

    // Construir UPDATE dinámico (no tocamos rol_id ni email salvo que se pida)
    $sets=[]; $types=""; $params=[];
    if ($nombre   !== null) { $sets[]="nombre=?";           $types.="s"; $params[]=$nombre; }
    if ($telefono !== null) { $sets[]="telefono=?";         $types.="s"; $params[]=$telefono; }
    if ($foto_url !== null) { $sets[]="foto_url=?";         $types.="s"; $params[]=$foto_url; }
    if ($genero   !== null) { $sets[]="genero=?";           $types.="s"; $params[]=$genero; }
    if ($tipo_doc !== null) { $sets[]="tipo_documento=?";   $types.="s"; $params[]=$tipo_doc; }
    if ($num_doc  !== null) { $sets[]="numero_documento=?"; $types.="s"; $params[]=$num_doc; }
    if ($cambiarEmail && $correoNuevo !== null) {
      $sets[]="email=?"; $types.="s"; $params[]=$correoNuevo;
    }

    if (empty($sets)) { echo json_encode(["status"=>"ERROR","message"=>"Nada que actualizar"]); exit(); }

    $sql = "UPDATE usuarios SET ".implode(", ", $sets)." WHERE id=?";
    $types.="i"; $params[]=$id;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) echo json_encode(["status"=>"OK","message"=>"Usuario actualizado"]);
    else echo json_encode(["status"=>"ERROR","message"=>$stmt->error]);
    break;

  /* ===================== DELETE ===================== */
  case 'DELETE':
    $data = json_decode(file_get_contents("php://input"), true);
    $id = $data['id'] ?? null;
    if (!$id) { echo json_encode(["status"=>"ERROR","message"=>"ID no proporcionado"]); exit(); }
    $stmt = $conn->prepare("DELETE FROM usuarios WHERE id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) echo json_encode(["status"=>"OK","message"=>"Usuario eliminado"]);
    else echo json_encode(["status"=>"ERROR","message"=>$stmt->error]);
    break;

  default:
    http_response_code(405);
    echo json_encode(["status"=>"ERROR","message"=>"Método no permitido"]);
}
