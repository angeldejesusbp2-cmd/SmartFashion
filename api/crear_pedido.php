// =============================================================================
// api/crear_pedido.php - Endpoint para crear un pedido
// =============================================================================
<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers");

include_once '../config.php';

$database = new Database();
$db = $database->getConnection();

// Obtener datos del POST
$data = json_decode(file_get_contents("php://input"));

if (!empty($data->cliente) && !empty($data->carrito)) {
    
    try {
        $db->beginTransaction();
        
        // 1. Crear o verificar cliente
        $query_cliente = "INSERT INTO clientes (nombre, email, telefono) 
                         VALUES (:nombre, :email, :telefono)
                         ON DUPLICATE KEY UPDATE id_cliente=LAST_INSERT_ID(id_cliente)";
        
        $stmt = $db->prepare($query_cliente);
        $stmt->bindParam(":nombre", $data->cliente->nombre);
        $stmt->bindParam(":email", $data->cliente->email);
        $stmt->bindParam(":telefono", $data->cliente->telefono);
        $stmt->execute();
        
        $id_cliente = $db->lastInsertId();
        
        // 2. Crear dirección de envío
        $id_direccion = null;
        if (!empty($data->direccion)) {
            $query_dir = "INSERT INTO direcciones 
                         (id_cliente, nombre_completo, telefono, calle, colonia, ciudad, estado, codigo_postal)
                         VALUES (:id_cliente, :nombre, :telefono, :calle, :colonia, :ciudad, :estado, :cp)";
            
            $stmt = $db->prepare($query_dir);
            $stmt->bindParam(":id_cliente", $id_cliente);
            $stmt->bindParam(":nombre", $data->direccion->nombre_completo);
            $stmt->bindParam(":telefono", $data->direccion->telefono);
            $stmt->bindParam(":calle", $data->direccion->calle);
            $stmt->bindParam(":colonia", $data->direccion->colonia);
            $stmt->bindParam(":ciudad", $data->direccion->ciudad);
            $stmt->bindParam(":estado", $data->direccion->estado);
            $stmt->bindParam(":cp", $data->direccion->codigo_postal);
            $stmt->execute();
            
            $id_direccion = $db->lastInsertId();
        }
        
        // 3. Calcular totales
        $subtotal = 0;
        $productos_pedido = [];
        
        foreach ($data->carrito as $item) {
            // Obtener precio actual del producto
            $query_prod = "SELECT precio, stock FROM productos WHERE id_producto = :id";
            $stmt = $db->prepare($query_prod);
            $stmt->bindParam(":id", $item->id);
            $stmt->execute();
            $producto = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($producto && $producto['stock'] >= $item->cantidad) {
                $precio = $producto['precio'];
                $subtotal_item = $precio * $item->cantidad;
                $subtotal += $subtotal_item;
                
                $productos_pedido[] = [
                    'id' => $item->id,
                    'cantidad' => $item->cantidad,
                    'precio' => $precio,
                    'subtotal' => $subtotal_item
                ];
            } else {
                throw new Exception("Stock insuficiente para producto ID: " . $item->id);
            }
        }
        
        $envio = 50.00; // Costo de envío fijo
        $total = $subtotal + $envio;
        
        // 4. Crear pedido
        $metodo_pago = $data->metodo_pago ?? 'transferencia';
        
        $query_pedido = "INSERT INTO pedidos 
                        (id_cliente, id_direccion, subtotal, envio, total, metodo_pago, estado)
                        VALUES (:id_cliente, :id_direccion, :subtotal, :envio, :total, :metodo_pago, 'pendiente')";
        
        $stmt = $db->prepare($query_pedido);
        $stmt->bindParam(":id_cliente", $id_cliente);
        $stmt->bindParam(":id_direccion", $id_direccion);
        $stmt->bindParam(":subtotal", $subtotal);
        $stmt->bindParam(":envio", $envio);
        $stmt->bindParam(":total", $total);
        $stmt->bindParam(":metodo_pago", $metodo_pago);
        $stmt->execute();
        
        $id_pedido = $db->lastInsertId();
        
        // 5. Agregar detalle del pedido y actualizar stock
        $query_detalle = "INSERT INTO detalle_pedidos 
                         (id_pedido, id_producto, cantidad, precio_unitario, subtotal)
                         VALUES (:id_pedido, :id_producto, :cantidad, :precio, :subtotal)";
        
        $query_stock = "UPDATE productos SET stock = stock - :cantidad WHERE id_producto = :id";
        
        $query_inventario = "INSERT INTO inventario (id_producto, tipo_movimiento, cantidad, motivo, id_pedido)
                            VALUES (:id_producto, 'salida', :cantidad, 'Venta', :id_pedido)";
        
        foreach ($productos_pedido as $prod) {
            // Insertar detalle
            $stmt = $db->prepare($query_detalle);
            $stmt->bindParam(":id_pedido", $id_pedido);
            $stmt->bindParam(":id_producto", $prod['id']);
            $stmt->bindParam(":cantidad", $prod['cantidad']);
            $stmt->bindParam(":precio", $prod['precio']);
            $stmt->bindParam(":subtotal", $prod['subtotal']);
            $stmt->execute();
            
            // Actualizar stock
            $stmt = $db->prepare($query_stock);
            $stmt->bindParam(":cantidad", $prod['cantidad']);
            $stmt->bindParam(":id", $prod['id']);
            $stmt->execute();
            
            // Registrar movimiento de inventario
            $stmt = $db->prepare($query_inventario);
            $stmt->bindParam(":id_producto", $prod['id']);
            $stmt->bindParam(":cantidad", $prod['cantidad']);
            $stmt->bindParam(":id_pedido", $id_pedido);
            $stmt->execute();
        }
        
        // 6. Registrar historial
        $query_historial = "INSERT INTO historial_pedidos (id_pedido, estado_nuevo, observaciones)
                           VALUES (:id_pedido, 'pendiente', 'Pedido creado')";
        $stmt = $db->prepare($query_historial);
        $stmt->bindParam(":id_pedido", $id_pedido);
        $stmt->execute();
        
        $db->commit();
        
        http_response_code(201);
        echo json_encode([
            "success" => true,
            "message" => "Pedido creado exitosamente",
            "data" => [
                "id_pedido" => $id_pedido,
                "total" => $total,
                "estado" => "pendiente"
            ]
        ]);
        
    } catch(Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Error al crear pedido: " . $e->getMessage()
        ]);
    }
    
} else {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Datos incompletos"
    ]);
}
?>