<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
startSession();
requireLogin();

$user_id    = $_SESSION['user_id'];
$page_title = 'Checkout';

$cart_items = db_query($conn,
    "SELECT ci.*, p.product_name, p.character_name, p.main_image,
            p.sale_price, p.rental_price_per_day, p.rental_deposit,
            p.listing_type, s.store_name, s.store_id, p.stock_quantity, p.rental_stock
     FROM cart_items ci
     JOIN products p ON p.product_id=ci.product_id
     JOIN stores s ON s.store_id=p.store_id
     WHERE ci.user_id=?
     ORDER BY s.store_id", 'i', [$user_id]);

if (!$cart_items) { header('Location: /cart.php'); exit; }

$profile = db_query_one($conn,
    "SELECT * FROM customer_profiles WHERE user_id=?", 'i', [$user_id]);

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $address = trim($_POST['shipping_address'] ?? '');
    $method  = $_POST['payment_method'] ?? '';
    if (!$address) { $error = 'Please enter a shipping/pickup address.'; }
    elseif (!$method) { $error = 'Please select a payment method.'; }
    else {
        // Group by store and create orders/rentals
        $grouped = [];
        foreach ($cart_items as $item) {
            $grouped[$item['store_id']][] = $item;
        }

        foreach ($grouped as $store_id => $items) {
            // Separate purchases and rentals
            $purchases = array_filter($items, fn($i) => $i['item_type']==='purchase');
            $rentals   = array_filter($items, fn($i) => $i['item_type']==='rental');

            // Create order if there are purchases
            if ($purchases) {
                $order_total = array_sum(array_map(fn($i)=>($i['sale_price']??0)*$i['quantity'], $purchases));
                $order_id = db_execute($conn,
                    "INSERT INTO orders (user_id,store_id,total_amount,shipping_address,payment_method,order_status,payment_status)
                     VALUES (?,?,?,?,?,'pending','unpaid')",
                    'iidss', [$user_id,$store_id,$order_total,$address,$method]);
                foreach ($purchases as $item) {
                    db_execute($conn,
                        "INSERT INTO order_items (order_id,product_id,quantity,unit_price) VALUES (?,?,?,?)",
                        'iiid', [$order_id,$item['product_id'],$item['quantity'],$item['sale_price']]);
                    // Reduce stock
                    $conn->query("UPDATE products SET stock_quantity=stock_quantity-{$item['quantity']} WHERE product_id={$item['product_id']}");
                }
                // Notification
                db_execute($conn,"INSERT INTO notifications(user_id,title,message,type,link) VALUES(?,?,?,?,?)",
                    'issss',[$user_id,'Order Placed! 📦',"Your order #{$order_id} has been placed successfully.",'order',"/orders.php"]);
            }

            // Create rental if there are rentals
            if ($rentals) {
                $rental_fee   = 0;
                $deposit_sum  = 0;
                foreach ($rentals as $item) {
                    $days = max(1,(int)round((strtotime($item['rental_end_date'])-strtotime($item['rental_start_date']))/86400));
                    $rental_fee  += ($item['rental_price_per_day']??0) * $days;
                    $deposit_sum += ($item['rental_deposit']??0);
                }
                $rental_id = db_execute($conn,
                    "INSERT INTO rentals (user_id,store_id,total_amount,deposit_amount,payment_method,rental_status,payment_status)
                     VALUES (?,?,?,?,?,'pending','unpaid')",
                    'iiddss', [$user_id,$store_id,$rental_fee,$deposit_sum,$method]);
                foreach ($rentals as $item) {
                    $days = max(1,(int)round((strtotime($item['rental_end_date'])-strtotime($item['rental_start_date']))/86400));
                    $sub  = ($item['rental_price_per_day']??0)*$days;
                    db_execute($conn,
                        "INSERT INTO rental_items (rental_id,product_id,rental_start_date,rental_end_date,days_count,daily_rate,deposit_per_item,subtotal)
                         VALUES (?,?,?,?,?,?,?,?)",
                        'iissiidd', [$rental_id,$item['product_id'],$item['rental_start_date'],$item['rental_end_date'],$days,$item['rental_price_per_day'],$item['rental_deposit'],$sub]);
                    $conn->query("UPDATE products SET rental_stock=rental_stock-1 WHERE product_id={$item['product_id']} AND rental_stock>0");
                }
                db_execute($conn,"INSERT INTO notifications(user_id,title,message,type,link) VALUES(?,?,?,?,?)",
                    'issss',[$user_id,'Rental Confirmed! 🎪',"Your rental #{$rental_id} has been created.",'rental',"/rentals.php"]);
            }
        }

        // Clear cart
        db_execute($conn,"DELETE FROM cart_items WHERE user_id=?","i",[$user_id]);
        header('Location: /orders.php?placed=1');
        exit;
    }
}

// Totals
$grand_total = 0;
foreach ($cart_items as $item) {
    if ($item['item_type']==='purchase') {
        $grand_total += ($item['sale_price']??0)*$item['quantity'];
    } else {
        $days = max(1,(int)round((strtotime($item['rental_end_date'])-strtotime($item['rental_start_date']))/86400));
        $grand_total += ($item['rental_price_per_day']??0)*$days + ($item['rental_deposit']??0);
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width:900px;margin:0 auto;">
  <h1 class="page-title">💳 Checkout</h1>
  <p class="page-subtitle">Review your order and complete payment</p>

  <?php if ($error): ?>
  <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div style="display:grid;grid-template-columns:1fr 340px;gap:2rem;align-items:start;">

      <div>
        <!-- Shipping Address -->
        <div class="card" style="margin-bottom:1.5rem;">
          <h3 style="font-family:'Cinzel',serif;font-size:1rem;margin-bottom:1rem;">📍 Delivery / Pickup Address</h3>
          <div class="form-group" style="margin-bottom:0;">
            <textarea name="shipping_address" class="form-control" rows="3"
              placeholder="Enter your full address for delivery, or store address for pickup..."
              required><?= htmlspecialchars($profile['address'] ?? '') ?></textarea>
          </div>
        </div>

        <!-- Payment Method -->
        <div class="card" style="margin-bottom:1.5rem;">
          <h3 style="font-family:'Cinzel',serif;font-size:1rem;margin-bottom:1rem;">💰 Payment Method</h3>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
            <?php $methods = ['gcash'=>['💙','GCash'],'maya'=>['💚','Maya'],'bank_transfer'=>['🏦','Bank Transfer'],'cod'=>['💵','Cash on Delivery'],'credit_card'=>['💳','Credit Card']]; ?>
            <?php foreach ($methods as $val=>[$icon,$lbl]): ?>
            <label style="display:flex;align-items:center;gap:0.6rem;padding:0.9rem;border:2px solid var(--border-color);border-radius:10px;cursor:pointer;transition:all 0.2s;" id="method-label-<?= $val ?>">
              <input type="radio" name="payment_method" value="<?= $val ?>" style="accent-color:var(--accent-purple);"
                     onchange="document.querySelectorAll('[id^=method-label-]').forEach(l=>l.style.borderColor='var(--border-color)');document.getElementById('method-label-<?= $val ?>').style.borderColor='var(--accent-purple)'">
              <span style="font-size:1.3rem;"><?= $icon ?></span>
              <span style="font-size:0.9rem;font-weight:600;"><?= $lbl ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Order Items Summary -->
        <div class="card">
          <h3 style="font-family:'Cinzel',serif;font-size:1rem;margin-bottom:1rem;">🎭 Order Items</h3>
          <?php foreach ($cart_items as $item): ?>
          <div style="display:flex;gap:0.75rem;padding:0.75rem 0;border-bottom:1px solid rgba(255,255,255,0.04);align-items:center;">
            <div style="width:50px;height:60px;border-radius:8px;overflow:hidden;background:var(--bg-mid);flex-shrink:0;">
              <img src="<?= htmlspecialchars($item['main_image']??'https://picsum.photos/50/60') ?>" style="width:100%;height:100%;object-fit:cover;">
            </div>
            <div style="flex:1;">
              <div style="font-size:0.88rem;font-weight:600;"><?= htmlspecialchars($item['product_name']) ?></div>
              <div style="font-size:0.75rem;color:var(--text-muted);"><?= $item['store_name'] ?></div>
              <?php if ($item['item_type']==='rental'): ?>
              <div style="font-size:0.75rem;color:var(--accent-cyan);">📅 <?= $item['rental_start_date'] ?> → <?= $item['rental_end_date'] ?></div>
              <?php endif; ?>
            </div>
            <?php
            if ($item['item_type']==='purchase') {
                $sub = ($item['sale_price']??0)*$item['quantity'];
            } else {
                $days=max(1,(int)round((strtotime($item['rental_end_date'])-strtotime($item['rental_start_date']))/86400));
                $sub = ($item['rental_price_per_day']??0)*$days + ($item['rental_deposit']??0);
            }
            ?>
            <span style="font-weight:700;color:var(--accent-gold);font-size:0.9rem;">₱<?= number_format($sub,2) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Summary Sidebar -->
      <div class="card" style="position:sticky;top:calc(var(--header-h)+1rem);">
        <h3 style="font-family:'Cinzel',serif;font-size:1rem;margin-bottom:1.25rem;">📋 Summary</h3>
        <div style="font-size:0.9rem;line-height:2.5;">
          <div style="display:flex;justify-content:space-between;color:var(--text-secondary);">
            <span>Items (<?= count($cart_items) ?>)</span><span>₱<?= number_format($grand_total,2) ?></span>
          </div>
          <div style="display:flex;justify-content:space-between;color:var(--text-secondary);">
            <span>Shipping</span><span style="color:#00e676;">FREE</span>
          </div>
          <div style="border-top:1px solid var(--border-color);margin:0.5rem 0;"></div>
          <div style="display:flex;justify-content:space-between;font-weight:800;font-size:1.05rem;color:var(--accent-gold);">
            <span>Total</span><span>₱<?= number_format($grand_total,2) ?></span>
          </div>
        </div>
        <div style="margin-top:1.5rem;padding:0.75rem;background:rgba(0,229,255,0.06);border:1px solid rgba(0,229,255,0.2);border-radius:8px;font-size:0.78rem;color:var(--text-secondary);line-height:1.7;">
          🔒 Your payment is securely processed.<br>
          📅 Rental deposits are refunded after return inspection.
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:1.25rem;">
          <i class="fas fa-check-circle"></i> Place Order
        </button>
        <a href="/cart.php" class="btn btn-outline btn-block" style="margin-top:0.6rem;">← Back to Cart</a>
      </div>

    </div>
  </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
