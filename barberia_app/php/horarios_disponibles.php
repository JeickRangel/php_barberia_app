<?php
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Content-Type: application/json; charset=utf-8");
require_once "conexion.php";

$empleado_id = intval($_GET['empleado_id'] ?? 0);
$fecha = $_GET['fecha'] ?? '';
$servicio_id = intval($_GET['servicio_id'] ?? 0);

if (!$empleado_id || !$fecha || !$servicio_id) {
    echo json_encode([]);
    exit();
}

// 1. Obtener duración del servicio
$stmt = $conn->prepare("SELECT duracion FROM servicios WHERE id_servicio=?");
$stmt->bind_param("i", $servicio_id);
$stmt->execute();
$res = $stmt->get_result();
$duracion = $res->fetch_assoc()['duracion'] ?? 60;

// 2. Obtener disponibilidad del barbero ese día
$diaSemana = date("l", strtotime($fecha));
$diasMap = [
  "Monday"=>"Lunes","Tuesday"=>"Martes","Wednesday"=>"Miércoles",
  "Thursday"=>"Jueves","Friday"=>"Viernes",
  "Saturday"=>"Sábado","Sunday"=>"Domingo"
];
$diaSemanaEsp = $diasMap[$diaSemana] ?? $diaSemana;

$stmt = $conn->prepare("SELECT hora_inicio, hora_fin FROM disponibilidad 
                        WHERE id_usuario=? AND dia_semana=?");
$stmt->bind_param("is", $empleado_id, $diaSemanaEsp);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode([]);
    exit();
}

$disp = $res->fetch_assoc();
$horaInicio = strtotime($disp['hora_inicio']);
$horaFin = strtotime($disp['hora_fin']);

// 3. Generar intervalos según la duración del servicio
$horarios = [];
for ($h = $horaInicio; $h + ($duracion*60) <= $horaFin; $h += $duracion*60) {
    $horarios[] = date("H:i", $h);
}

// 4. Traer reservas ya ocupadas
$stmt = $conn->prepare("SELECT hora, s.duracion 
                        FROM reservas r
                        JOIN servicios s ON r.servicio_id=s.id_servicio
                        WHERE r.empleado_id=? AND r.fecha=? 
                          AND r.estado IN ('pendiente','confirmada')");
$stmt->bind_param("is", $empleado_id, $fecha);
$stmt->execute();
$res = $stmt->get_result();

$ocupados = [];
while ($row = $res->fetch_assoc()) {
    $horaReserva = strtotime($row['hora']);
    $duracionReserva = $row['duracion'] * 60;
    // Marca solo el bloque de inicio
    $ocupados[] = date("H:i", $horaReserva);
}

// 5. Filtrar horarios disponibles
$disponibles = array_values(array_diff($horarios, $ocupados));

// 🔎 Debug (se verá en el log de PHP/XAMPP)
error_log("Horarios generados: " . json_encode($horarios));
error_log("Ocupados: " . json_encode($ocupados));
error_log("Disponibles: " . json_encode($disponibles));

// ✅ Respuesta al frontend
echo json_encode($disponibles);
