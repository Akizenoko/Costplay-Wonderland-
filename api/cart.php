<?php
// api/cart.php — Cart API
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
startSession();

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success'=>false,'message'=>'Please login first.','redirect'=>'/login.php']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action  = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    case 'add':
        $product_id  = (int)($_POST['product_id'] ?? 0);
        $item_type   = $_POST['item_type'] ?? 'purchase';
        $quantity    = max(1,(int)($_POST['quantity'] ?? 1));
        $start_date  = $_POST['rental_start_date'] ?? null;
        $end_date    = $_POST['rental_end_date']   ?? null;

        if (!$product_id) { echo json_encode(['success'=>false,'message'=>'Invalid product.']); exit; }

        // Validate product
        $product = db_query_one($conn,"SELECT * FROM products WHERE product_id=? AND is_active=1","i",[$product_id]);
        if (!$product) { echo json_encode(['success'=>false,'message'=>'Product not found.']); exit; }

        if ($item_type === 'rental' && (!$start_date || !$end_date)) {
            echo json_encode(['success'=>false,'message'=>'Please select rental dates.']); exit;
        }

        // Check if already in cart
        $existing = db_query_one($conn,
            "SELECT * FROM cart_items WHERE user_id=? AND product_id=? AND item_type=?",
            'iis', [$user_id, $product_id, $item_type]);

        if ($existing) {
            if ($item_type === 'purchase') {
                db_execute($conn,"UPDATE cart_items SET quantity=quantity+? WHERE cart_item_id=?","ii",[$quantity,$existing['cart_item_id']]);
            }
            // For rentals, update dates
            if ($item_type === 'rental' && $start_date && $end_date) {
                db_execute($conn,"UPDATE cart_items SET rental_start_date=?,rental_end_date=? WHERE cart_item_id=?","ssi",[$start_date,$end_date,$existing['cart_item_id']]);
            }
        } else {
            db_execute($conn,
                "INSERT INTO cart_items (user_id,product_id,quantity,item_type,rental_start_date,rental_end_date) VALUES (?,?,?,?,?,?)",
                'iiisss', [$user_id,$product_id,$quantity,$item_type,$start_date,$end_date]);
        }

        $count = getCartCount($conn);
        echo json_encode(['success'=>true,'message'=>'Added to cart!','cart_count'=>$count]);
        break;

    case 'update':
        $cart_item_id = (int)($_POST['cart_item_id'] ?? 0);
        $delta        = (int)($_POST['delta'] ?? 0);

        $item = db_query_one($conn,"SELECT * FROM cart_items WHERE cart_item_id=? AND user_id=?","ii",[$cart_item_id,$user_id]);
        if (!$item) { echo json_encode(['success'=>false,'message'=>'Item not found.']); exit; }

        $new_qty = max(1, $item['quantity'] + $delta);
        db_execute($conn,"UPDATE cart_items SET quantity=? WHERE cart_item_id=?","ii",[$new_qty,$cart_item_id]);
        $count = getCartCount($conn);
        echo json_encode(['success'=>true,'cart_count'=>$count]);
        break;

    case 'remove':
        $cart_item_id = (int)($_POST['cart_item_id'] ?? 0);
        db_execute($conn,"DELETE FROM cart_items WHERE cart_item_id=? AND user_id=?","ii",[$cart_item_id,$user_id]);
        $count = getCartCount($conn);
        echo json_encode(['success'=>true,'cart_count'=>$count]);
        break;

    case 'clear':
        db_execute($conn,"DELETE FROM cart_items WHERE user_id=?","i",[$user_id]);
        echo json_encode(['success'=>true,'cart_count'=>0]);
        break;

    case 'count':
        echo json_encode(['success'=>true,'count'=>getCartCount($conn)]);
        break;

    default:
        echo json_encode(['success'=>false,'message'=>'Unknown action.']);
}
