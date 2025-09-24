<?php
// conexión.php
// Archivo único de conexión a la base de datos MySQL

$host = "localhost";   // Servidor (en XAMPP es siempre localhost)
$user = "root";        // Usuario por defecto en XAMPP
$pass = "";            // Contraseña (en XAMPP normalmente está vacía)
$db   = "barberia_app"; // Nombre de la base de datos que creaste


// Crear conexión con MySQL
$conn = new mysqli($host, $user, $pass, $db);

// Verificar si hay error de conexión
if ($conn->connect_error) {
    die("❌ Error de conexión: " . $conn->connect_error);
}

// Configurar para usar UTF-8
$conn->set_charset("utf8mb4");

// ✅ Si llegaste aquí, la conexión fue exitosa
?>
