<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config.php';

$database = new Database();
$db = $database->getConnection();

try {
    $query = "SELECT 
                p.id_producto as id,
                p.nombre as name,
                p.descripcion as `desc`,
                p.precio as price,
                p.imagen_url as img,
                p.imagen_alt as alt,
                p.stock,
                c.nombre as categoria
              FROM productos p
              LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
              WHERE p.activo = 1";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    http_response_code(200);
    echo json_encode([
        "success" => true,
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