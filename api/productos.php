<?php
// =====================================================================
// API/PRODUCTOS.PHP - Obtener todos los productos
// =====================================================================
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once(__DIR__ . '/config.php');  // CORREGIDO: sin ../ porque está en la misma carpeta

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error de conexión a la base de datos"
    ]);
    exit;
}

try {
    $query = "SELECT 
                p.id_producto as id,
                p.nombre as name,
                p.descripcion as description,
                p.precio as price,
                p.imagen_url as img,
                p.imagen_alt as alt,
                p.stock
              FROM productos p
              WHERE p.activo = 1
              ORDER BY p.destacado DESC, p.id_producto ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    http_response_code(200);
    echo json_encode([
        "success" => true,
        "count" => count($productos),
        "data" => $productos
    ]);
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error al obtener productos: " . $e->getMessage()
    ]);
}
?>