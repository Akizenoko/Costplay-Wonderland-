<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
startSession();
requireRole(['seller']);

$user_id    = $_SESSION['user_id'];
$store_id   = $_SESSION['store_id'];
$page_title = 'Store Settings';
$msg = $err = '';

$store = db_query_one($conn, "SELECT * FROM stores WHERE store_id=?", 'i', [$store_id]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $store_name = trim($_POST['store_name'] ?? '');
    $desc       = trim($_POST['description'] ?? '');
    $phone      = trim($_POST['contact_phone'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $logo       = trim($_POST['logo_url'] ?? '');
    $banner     = trim($_POST['banner_url'] ?? '');

    if (!$store_name) { $err = 'Store name is required.'; }
    else {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $store_name));
        db_execute($conn,
            "UPDATE stores SET store_name=?,store_slug=?,description=?,contact_phone=?,address=?,logo_url=?,banner_url=?
             WHERE store_id=?",
            'sssssssi',
            [$store_name, $slug, $desc, $phone, $address, $logo, $banner, $store_id]);
        $store = db_query_one($conn, "SELECT * FROM stores WHERE store_id=?", 'i', [$store_id]);
        $msg   = 'Store settings updated successfully!';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">⚙️ Store Settings</h1>
<p class="page-subtitle">Customize your Costplay Wonderland store</p>

<?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 320px;gap:2rem;align-items:start;">
  <form method="POST">
    <div class="card" style="margin-bottom:1.5rem;">
      <h3 style="font-family:'Cinzel',serif;font-size:1rem;margin-bottom:1.5rem;">🏪 Basic Info</h3>
      <div class="grid grid-2" style="gap:1rem;">
        <div class="form-group">
          <label class="form-label">Store Name *</label>
          <input type="text" name="store_name" class="form-control" required value="<?= htmlspecialchars($store['store_name'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Contact Phone</label>
          <input type="text" name="contact_phone" class="form-control" value="<?= htmlspecialchars($store['contact_phone'] ?? '') ?>" placeholder="09XXXXXXXXX">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Store Description</label>
        <textarea name="description" class="form-control" rows="4" placeholder="Tell customers about your store..."><?= htmlspecialchars($store['description'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Address / Location</label>
        <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($store['address'] ?? '') ?>" placeholder="City, Province">
      </div>
    </div>

    <div class="card" style="margin-bottom:1.5rem;">
      <h3 style="font-family:'Cinzel',serif;font-size:1rem;margin-bottom:1.5rem;">🖼️ Store Branding</h3>
      <div class="form-group">
        <label class="form-label">Logo Image URL</label>
        <input type="url" name="logo_url" class="form-control" value="<?= htmlspecialchars($store['logo_url'] ?? '') ?>" placeholder="https://...">
        <div class="form-hint">Direct link to your store logo (square image recommended)</div>
      </div>
      <div class="form-group">
        <label class="form-label">Banner Image URL</label>
        <input type="url" name="banner_url" class="form-control" value="<?= htmlspecialchars($store['banner_url'] ?? '') ?>" placeholder="https://...">
        <div class="form-hint">Wide banner image shown at top of your store page (1200×300 recommended)</div>
      </div>
    </div>

    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Save Changes</button>
  </form>

  <!-- Preview -->
  <div style="position:sticky;top:calc(var(--header-h)+1rem);">
    <div class="card">
      <h3 style="font-family:'Cinzel',serif;font-size:0.95rem;margin-bottom:1rem;">👁️ Store Preview</h3>
      <div style="border-radius:10px;overflow:hidden;border:1px solid var(--border-color);">
        <div style="height:80px;background:linear-gradient(135deg,rgba(224,64,251,0.4),rgba(124,77,255,0.4));position:relative;">
          <?php if ($store['banner_url']): ?>
          <img src="<?= htmlspecialchars($store['banner_url']) ?>" style="width:100%;height:100%;object-fit:cover;opacity:0.6;">
          <?php endif; ?>
          <div style="position:absolute;bottom:-18px;left:0.75rem;width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#e040fb,#7c4dff);border:2px solid var(--bg-card);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.9rem;color:#fff;">
            <?= strtoupper(substr($store['store_name'] ?? '?', 0, 1)) ?>
          </div>
        </div>
        <div style="padding:1.5rem 0.75rem 0.75rem;">
          <div style="font-weight:700;font-size:0.9rem;"><?= htmlspecialchars($store['store_name'] ?? '') ?></div>
          <div style="display:flex;align-items:center;gap:0.4rem;font-size:0.75rem;color:var(--text-muted);margin:0.3rem 0;">
            <span style="color:var(--accent-gold);">⭐ <?= number_format($store['rating'] ?? 0, 1) ?></span>
            <?php if ($store['is_verified']): ?><span>· ✓ Verified</span><?php endif; ?>
          </div>
          <p style="font-size:0.78rem;color:var(--text-secondary);line-height:1.5;"><?= htmlspecialchars(substr($store['description'] ?? 'No description yet.', 0, 100)) ?><?= strlen($store['description'] ?? '') > 100 ? '...' : '' ?></p>
        </div>
      </div>
      <div style="margin-top:1rem;">
        <a href="/store.php?slug=<?= urlencode($store['store_slug'] ?? '') ?>" target="_blank" class="btn btn-outline btn-block btn-sm">
          <i class="fas fa-external-link-alt"></i> View Live Store
        </a>
      </div>
    </div>

    <div class="card" style="margin-top:1rem;">
      <h3 style="font-family:'Cinzel',serif;font-size:0.95rem;margin-bottom:0.75rem;">📊 Store Status</h3>
      <div style="font-size:0.85rem;line-height:2.2;color:var(--text-secondary);">
        <div style="display:flex;justify-content:space-between;">
          <span>Verification</span>
          <span><?= $store['is_verified'] ? '<span style="color:#00e676;">✓ Verified</span>' : '<span style="color:#ffa000;">⏳ Pending</span>' ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;">
          <span>Store Status</span>
          <span><?= $store['is_active'] ? '<span style="color:#00e676;">Active</span>' : '<span style="color:#ff5252;">Inactive</span>' ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;">
          <span>Rating</span>
          <span style="color:var(--accent-gold);">⭐ <?= number_format($store['rating'] ?? 0, 1) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;">
          <span>Total Sales</span>
          <span><?= number_format($store['total_sales'] ?? 0) ?></span>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
