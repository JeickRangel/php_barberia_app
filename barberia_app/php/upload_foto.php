<?php
require_once "cors.php"; // CORS para Vite http://localhost:5173
header("Content-Type: application/json; charset=utf-8");

// 1) Carpetas
$uploadDir = __DIR__ . "/uploads/usuarios/";            // ruta en disco
$publicBaseRel = "/barberia_app/php/uploads/usuarios/"; // ruta pública (relativa al host)

// Crear carpeta si no existe
if (!is_dir($uploadDir)) {
  @mkdir($uploadDir, 0775, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

if (!isset($_FILES['foto'])) {
  echo json_encode(["status"=>"ERROR","message"=>"Archivo no recibido"]); exit();
}

$f = $_FILES['foto'];
if ($f['error'] !== UPLOAD_ERR_OK) {
  echo json_encode(["status"=>"ERROR","message"=>"Error al subir (code ".$f['error'].")"]); exit();
}

// Validar tipo
$allowed = ['image/jpeg','image/png','image/webp','image/gif'];
$mime = mime_content_type($f['tmp_name']);
if (!in_array($mime, $allowed)) {
  echo json_encode(["status"=>"ERROR","message"=>"Tipo de archivo no permitido"]); exit();
}

// 2) Guardar con nombre único
$ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) ?: "jpg";
$nombre = "u_".date("Ymd_His")."_".bin2hex(random_bytes(4)).".".$ext;
$dest = $uploadDir . $nombre;

if (!move_uploaded_file($f['tmp_name'], $dest)) {
  echo json_encode(["status"=>"ERROR","message"=>"No se pudo mover el archivo"]); exit();
}

// 3) Construir URL ABSOLUTA (http://localhost/barberia_app/php/uploads/usuarios/IMG.jpg)
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$publicBaseAbs = $scheme . '://' . $host . $publicBaseRel;

$urlAbs = $publicBaseAbs . rawurlencode($nombre);

// 4) Responder
echo json_encode(["status" => "OK", "url" => $urlAbs, "file" => $nombre]);
