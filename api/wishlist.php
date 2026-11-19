<?php
// api/wishlist.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
startSession();

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success'=>false,'redirect'=>'/login.php']);
    exit;
}

$user_id    = $_SESSION['user_id'];
$product_id = (int)($_POST['product_id'] ?? 0);
$action     = $_POST['action'] ?? 'toggle';

if (!$product_id) { echo json_encode(['success'=>false,'message'=>'Invalid product.']); exit; }

$exists = db_query_one($conn,
    "SELECT wishlist_id FROM wishlists WHERE user_id=? AND product_id=?","ii",[$user_id,$product_id]);

if ($action === 'toggle') {
    if ($exists) {
        db_execute($conn,"DELETE FROM wishlists WHERE user_id=? AND product_id=?","ii",[$user_id,$product_id]);
        echo json_encode(['success'=>true,'action'=>'removed']);
    } else {
        db_execute($conn,"INSERT INTO wishlists (user_id,product_id) VALUES (?,?)","ii",[$user_id,$product_id]);
        echo json_encode(['success'=>true,'action'=>'added']);
    }
} else {
    echo json_encode(['success'=>false,'message'=>'Unknown action.']);
}
