<?php
// Footer Component — Costplay Wonderland
?>
</div><!-- /main-content -->

<!-- Footer -->
<footer style="background:var(--bg-mid); border-top:1px solid var(--border-color); padding:3rem 2rem 1.5rem; margin-top:4rem;">
  <div style="max-width:1200px; margin:0 auto;">
    <div class="grid grid-4" style="margin-bottom:2.5rem;">
      <!-- Brand -->
      <div>
        <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1rem;">
          <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#e040fb,#7c4dff);display:flex;align-items:center;justify-content:center;font-size:1.2rem;">🎭</div>
          <div>
            <div style="font-family:'Cinzel',serif;font-weight:900;font-size:0.9rem;background:linear-gradient(135deg,#e040fb,#7c4dff);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">Costplay Wonderland</div>
            <div style="font-size:0.65rem;color:var(--text-muted);">Where Cosplay Dreams Come True ✨</div>
          </div>
        </div>
        <p style="color:var(--text-secondary);font-size:0.85rem;line-height:1.7;">
          The Philippines' premier cosplay e-commerce &amp; rental platform. Buy, sell, and rent cosplay costumes from your favorite anime, games, and movies.
        </p>
        <div style="display:flex;gap:0.6rem;margin-top:1rem;">
          <?php foreach(['fab fa-facebook-f','fab fa-instagram','fab fa-tiktok','fab fa-twitter'] as $icon): ?>
          <a href="#" style="width:34px;height:34px;border-radius:50%;background:rgba(124,77,255,0.15);border:1px solid var(--border-color);display:flex;align-items:center;justify-content:center;color:var(--text-secondary);font-size:0.85rem;transition:all 0.3s;" onmouseover="this.style.background='linear-gradient(135deg,#e040fb,#7c4dff)';this.style.color='#fff'" onmouseout="this.style.background='rgba(124,77,255,0.15)';this.style.color='var(--text-secondary)'">
            <i class="<?= $icon ?>"></i>
          </a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Quick Links -->
      <div>
        <h4 style="font-family:'Cinzel',serif;font-size:0.9rem;margin-bottom:1rem;color:var(--accent-purple);">Quick Links</h4>
        <?php foreach([
          ['🏠','Home','/index.php'],
          ['🛍️','Shop','/shop.php'],
          ['🏪','Stores','/stores.php'],
          ['📦','My Orders','/orders.php'],
          ['🎪','My Rentals','/rentals.php'],
        ] as [$icon,$label,$href]): ?>
        <a href="<?= $href ?>" style="display:flex;align-items:center;gap:0.5rem;color:var(--text-secondary);font-size:0.85rem;padding:0.3rem 0;text-decoration:none;transition:color 0.2s;" onmouseover="this.style.color='var(--accent-cyan)'" onmouseout="this.style.color='var(--text-secondary)'">
          <span><?= $icon ?></span> <?= $label ?>
        </a>
        <?php endforeach; ?>
      </div>

      <!-- For Sellers -->
      <div>
        <h4 style="font-family:'Cinzel',serif;font-size:0.9rem;margin-bottom:1rem;color:var(--accent-pink);">For Sellers</h4>
        <?php foreach([
          ['🏪','Open a Store','/register.php?role=seller'],
          ['📊','Seller Dashboard','/seller/dashboard.php'],
          ['🎭','List Products','/seller/products.php'],
          ['📋','Seller Guide','#'],
          ['💬','Support','#'],
        ] as [$icon,$label,$href]): ?>
        <a href="<?= $href ?>" style="display:flex;align-items:center;gap:0.5rem;color:var(--text-secondary);font-size:0.85rem;padding:0.3rem 0;text-decoration:none;transition:color 0.2s;" onmouseover="this.style.color='var(--accent-cyan)'" onmouseout="this.style.color='var(--text-secondary)'">
          <span><?= $icon ?></span> <?= $label ?>
        </a>
        <?php endforeach; ?>
      </div>

      <!-- Rental Info -->
      <div>
        <h4 style="font-family:'Cinzel',serif;font-size:0.9rem;margin-bottom:1rem;color:var(--accent-cyan);">Rental Policy</h4>
        <div style="background:rgba(0,229,255,0.05);border:1px solid rgba(0,229,255,0.2);border-radius:10px;padding:1rem;font-size:0.82rem;color:var(--text-secondary);line-height:1.8;">
          <div>✅ Costumes must be returned on due date</div>
          <div>✅ No damage, alterations, or stains</div>
          <div>✅ Deposit held until return inspection</div>
          <div>⚠️ Damage fees may apply</div>
          <div>⚠️ Late return incurs extra charges</div>
        </div>
      </div>
    </div>

    <!-- Bottom Bar -->
    <div style="border-top:1px solid var(--border-color); padding-top:1.5rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem;">
      <div style="color:var(--text-muted);font-size:0.8rem;">
        © <?= date('Y') ?> Costplay Wonderland. All rights reserved. Made with ❤️ for the cosplay community.
      </div>
      <div style="display:flex;gap:1rem;">
        <?php foreach(['Privacy Policy','Terms of Service','Rental Policy'] as $link): ?>
        <a href="#" style="color:var(--text-muted);font-size:0.78rem;text-decoration:none;" onmouseover="this.style.color='var(--accent-cyan)'" onmouseout="this.style.color='var(--text-muted)'"><?= $link ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</footer>

<!-- Toast Container -->
<div id="toast-container"></div>

<!-- Scripts -->
<script src="/assets/js/main.js"></script>
<?php if (isset($extra_js)): ?>
  <?php foreach ((array)$extra_js as $js): ?>
  <script src="<?= $js ?>"></script>
  <?php endforeach; ?>
<?php endif; ?>

</body>
</html>
