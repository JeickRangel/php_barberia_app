<?php
require_once "cors.php";
// IMPORTANTE: NO pongas application/json: esto devuelve archivo
// header("Content-Type: application/json; charset=utf-8");
require_once "conexion.php";

/* Ajusta la ruta del binario de mysqldump según tu XAMPP/SO */
$mysqldump = PHP_OS_FAMILY === 'Windows'
  ? '"C:\\xampp\\mysql\\bin\\mysqldump.exe"'   // <-- ajusta si tu ruta es distinta
  : 'mysqldump';

$db = $conn->query("SELECT DATABASE() AS db")->fetch_assoc()['db'] ?? '';
$user = $conn->thread_id ? $conn->get_charset()->charset : ''; // no disponible; mejor toma de tu conexion.php

// Lee credenciales desde conexion.php
// Supongo que tienes: $servername, $username, $password, $dbname
require "conexion.php"; // asegura que existan $username,$password,$dbname

$filename = "backup_${db}_" . date("Ymd_His") . ".sql";
header("Content-Type: application/octet-stream");
header("Content-Disposition: attachment; filename=\"$filename\"");

$cmd = $mysqldump . " --user=" . escapeshellarg($username) .
       " --password=" . escapeshellarg($password) .
       " --host=" . escapeshellarg($servername) .
       " --databases " . escapeshellarg($dbname);

// Ejecutar y volcar directamente al output
$descriptor = [
  1 => ["pipe", "w"], // stdout
  2 => ["pipe", "w"], // stderr
];
$proc = proc_open($cmd, $descriptor, $pipes);
if (is_resource($proc)) {
  fpassthru($pipes[1]); // manda el .sql al navegador
  fclose($pipes[1]);
  $err = stream_get_contents($pipes[2]); fclose($pipes[2]);
  $status = proc_close($proc);
  if ($status !== 0) {
    // Si falla, devolvemos texto simple
    header("Content-Type: text/plain");
    echo "Error creando backup:\n".$err;
  }
} else {
  header("Content-Type: text/plain");
  echo "No se pudo iniciar mysqldump";
}
