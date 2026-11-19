<?php
// api/rentals.php — returns rental items for return modal
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
startSession();

header('Content-Type: application/json');
requireRole(['seller','admin','super_admin']);

$action    = $_GET['action'] ?? '';
$rental_id = (int)($_GET['rental_id'] ?? 0);

if ($action === 'get_items' && $rental_id) {
    $items = db_query($conn,
        "SELECT ri.*, p.product_name, p.main_image, p.rental_deposit AS deposit_per_item
         FROM rental_items ri
         JOIN products p ON p.product_id=ri.product_id
         WHERE ri.rental_id=?", 'i', [$rental_id]);

    echo json_encode(['success'=>true,'items'=>$items]);
} else {
    echo json_encode(['success'=>false,'message'=>'Invalid request.']);
}
