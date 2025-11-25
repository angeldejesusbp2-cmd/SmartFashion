<?php
// =====================================================================
// API/CREAR_PEDIDO.PHP - Crear un nuevo pedido
// =====================================================================
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include_once 'config.php';  // CORREGIDO

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

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->cliente) && !empty($data->carrito)) {
    
    try {
        $db->beginTransaction();
        
        // 1. Crear o actualizar cliente
        $query_cliente = "INSERT INTO clientes (nombre, email, telefono) 
                         VALUES (:nombre, :email, :telefono)
                         ON DUPLICATE KEY UPDATE 
                         nombre = VALUES(nombre),
                         telefono = VALUES(telefono),
                         id_cliente = LAST_INSERT_ID(id_cliente)";
        
        $stmt = $db->prepare($query_cliente);
        $stmt->bindParam(":nombre", $data->cliente->nombre);
        $stmt->bindParam(":email", $data->cliente->email);
        $stmt->bindParam(":telefono", $data->cliente->telefono);
        $stmt->execute();
        
        $id_cliente = $db->lastInsertId();
        if ($id_cliente == 0) {
            // Si es un cliente existente, obtener su ID
            $query = "SELECT id_cliente FROM clientes WHERE email = :email";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":email", $data->cliente->email);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $id_cliente = $result['id_cliente'];
        }
        
        // 2. Calcular totales y validar stock
        $subtotal = 0;
        $productos_pedido = [];
        
        foreach ($data->carrito as $item) {
            $query_prod = "SELECT precio, stock, nombre FROM productos WHERE id_producto = :id AND activo = 1";
            $stmt = $db->prepare($query_prod);
            $stmt->bindParam(":id", $item->id);
            $stmt->execute();
            $producto = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$producto) {
                throw new Exception("Producto no encontrado: ID " . $item->id);
            }
            
            if ($producto['stock'] < $item->cantidad) {
                throw new Exception("Stock insuficiente para: " . $producto['nombre']);
            }
            
            $precio = $producto['precio'];
            $subtotal_item = $precio * $item->cantidad;
            $subtotal += $subtotal_item;
            
            $productos_pedido[] = [
                'id' => $item->id,
                'cantidad' => $item->cantidad,
                'precio' => $precio,
                'subtotal' => $subtotal_item
            ];
        }
        
        $envio = 50.00;
        $total = $subtotal + $envio;
        
        // 3. Crear pedido
        $metodo_pago = isset($data->metodo_pago) ? $data->metodo_pago : 'transferencia';
        
        $query_pedido = "INSERT INTO pedidos 
                        (id_cliente, subtotal, envio, total, metodo_pago, estado)
                        VALUES (:id_cliente, :subtotal, :envio, :total, :metodo_pago, 'pendiente')";
        
        $stmt = $db->prepare($query_pedido);
        $stmt->bindParam(":id_cliente", $id_cliente);
        $stmt->bindParam(":subtotal", $subtotal);
        $stmt->bindParam(":envio", $envio);
        $stmt->bindParam(":total", $total);
        $stmt->bindParam(":metodo_pago", $metodo_pago);
        $stmt->execute();
        
        $id_pedido = $db->lastInsertId();
        
        // 4. Agregar detalle del pedido y actualizar stock
        foreach ($productos_pedido as $prod) {
            // Insertar detalle
            $query_detalle = "INSERT INTO detalle_pedidos 
                             (id_pedido, id_producto, cantidad, precio_unitario, subtotal)
                             VALUES (:id_pedido, :id_producto, :cantidad, :precio, :subtotal)";
            
            $stmt = $db->prepare($query_detalle);
            $stmt->bindParam(":id_pedido", $id_pedido);
            $stmt->bindParam(":id_producto", $prod['id']);
            $stmt->bindParam(":cantidad", $prod['cantidad']);
            $stmt->bindParam(":precio", $prod['precio']);
            $stmt->bindParam(":subtotal", $prod['subtotal']);
            $stmt->execute();
            
            // Actualizar stock
            $query_stock = "UPDATE productos SET stock = stock - :cantidad WHERE id_producto = :id";
            $stmt = $db->prepare($query_stock);
            $stmt->bindParam(":cantidad", $prod['cantidad']);
            $stmt->bindParam(":id", $prod['id']);
            $stmt->execute();
            
            // Registrar movimiento de inventario
            $query_inventario = "INSERT INTO inventario (id_producto, tipo_movimiento, cantidad, motivo, id_pedido)
                                VALUES (:id_producto, 'salida', :cantidad, 'Venta - Pedido web', :id_pedido)";
            $stmt = $db->prepare($query_inventario);
            $stmt->bindParam(":id_producto", $prod['id']);
            $stmt->bindParam(":cantidad", $prod['cantidad']);
            $stmt->bindParam(":id_pedido", $id_pedido);
            $stmt->execute();
        }
        
        // 5. Registrar historial
        $query_historial = "INSERT INTO historial_pedidos (id_pedido, estado_nuevo, observaciones)
                           VALUES (:id_pedido, 'pendiente', 'Pedido creado desde web')";
        $stmt = $db->prepare($query_historial);
        $stmt->bindParam(":id_pedido", $id_pedido);
        $stmt->execute();
        
        $db->commit();
        
        http_response_code(201);
        echo json_encode([
            "success" => true,
            "message" => "¡Pedido creado exitosamente!",
            "data" => [
                "id_pedido" => $id_pedido,
                "subtotal" => number_format($subtotal, 2),
                "envio" => number_format($envio, 2),
                "total" => number_format($total, 2),
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
        "message" => "Datos incompletos. Se requiere información del cliente y productos."
    ]);
}
?>