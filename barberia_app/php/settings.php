<?php
require_once "cors.php";                      // 1) CORS primero
header("Content-Type: application/json; charset=utf-8");   // 2) tipo JSON
require_once "conexion.php";                  // 3) DB

$method = $_SERVER['REQUEST_METHOD'];

function get_setting($conn, $key) {
  $stmt = $conn->prepare("SELECT value FROM settings WHERE `key`=?");
  $stmt->bind_param("s", $key);
  $stmt->execute();
  $res = $stmt->get_result();
  if (!$res || $res->num_rows===0) return null;
  $row = $res->fetch_assoc();
  return json_decode($row['value'], true);
}

function set_setting($conn, $key, $arr) {
  $json = json_encode($arr, JSON_UNESCAPED_UNICODE);
  $stmt = $conn->prepare("INSERT INTO settings(`key`,`value`) VALUES(?,?)
                          ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
  $stmt->bind_param("ss", $key, $json);
  return $stmt->execute();
}

switch ($method) {
  case 'GET':
    $calendar = get_setting($conn, "calendar");
    echo json_encode([ "calendar" => $calendar ]);
    break;

  case 'PUT':
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) { echo json_encode(["status"=>"ERROR","message"=>"JSON inválido"]); exit; }
    if (isset($data['calendar'])) {
      if (!set_setting($conn, "calendar", $data['calendar'])) {
        echo json_encode(["status"=>"ERROR","message"=>"No se pudo guardar calendar"]); exit;
      }
    }
    echo json_encode(["status"=>"OK"]);
    break;

  default:
    http_response_code(405);
    echo json_encode(["status"=>"ERROR","message"=>"Método no permitido"]);
}
