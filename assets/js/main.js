// ============================================================
// COSTPLAY WONDERLAND — Main JavaScript
// ============================================================

'use strict';

// ─── Sidebar Toggle ───────────────────────────────────────────
(function() {
  const sidebar     = document.getElementById('sidebar');
  const overlay     = document.getElementById('sidebar-overlay');
  const header      = document.getElementById('top-header');
  const mainContent = document.getElementById('main-content');
  const burgerBtns  = document.querySelectorAll('.burger-btn, #mobile-menu-btn');

  if (!sidebar) return;

  const isMobile = () => window.innerWidth <= 768;

  function openMobile() {
    sidebar.classList.add('mobile-open');
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeMobile() {
    sidebar.classList.remove('mobile-open');
    overlay.classList.remove('active');
    document.body.style.overflow = '';
  }

  function toggleDesktop() {
    const collapsed = sidebar.classList.toggle('collapsed');
    if (header)      header.classList.toggle('sidebar-collapsed', collapsed);
    if (mainContent) mainContent.classList.toggle('sidebar-collapsed', collapsed);
    localStorage.setItem('cw_sidebar_collapsed', collapsed ? '1' : '0');
  }

  // Restore desktop state
  if (!isMobile() && localStorage.getItem('cw_sidebar_collapsed') === '1') {
    sidebar.classList.add('collapsed');
    if (header)      header.classList.add('sidebar-collapsed');
    if (mainContent) mainContent.classList.add('sidebar-collapsed');
  }

  burgerBtns.forEach(btn => {
    btn.addEventListener('click', e => {
      e.stopPropagation();
      if (isMobile()) {
        sidebar.classList.contains('mobile-open') ? closeMobile() : openMobile();
      } else {
        toggleDesktop();
      }
    });
  });

  if (overlay) {
    overlay.addEventListener('click', closeMobile);
  }

  // Close on resize if switching to desktop
  window.addEventListener('resize', () => {
    if (!isMobile()) closeMobile();
  });
})();


// ─── Active Nav Item ─────────────────────────────────────────
(function() {
  const path = window.location.pathname;
  document.querySelectorAll('.nav-item').forEach(item => {
    const href = item.getAttribute('href') || '';
    if (href && path.endsWith(href)) {
      item.classList.add('active');
    }
  });
})();


// ─── Toast Notifications ─────────────────────────────────────
const Toast = {
  container: null,

  init() {
    if (!this.container) {
      this.container = document.createElement('div');
      this.container.id = 'toast-container';
      document.body.appendChild(this.container);
    }
  },

  show(message, type = 'info', duration = 3500) {
    this.init();
    const icons = { success: '✅', error: '❌', info: 'ℹ️', warning: '⚠️' };
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `<span>${icons[type] || 'ℹ️'}</span><span>${message}</span>`;
    this.container.appendChild(toast);

    toast.addEventListener('click', () => toast.remove());

    setTimeout(() => {
      toast.style.animation = 'slideInRight 0.3s ease reverse';
      setTimeout(() => toast.remove(), 300);
    }, duration);
  },

  success: (msg, d) => Toast.show(msg, 'success', d),
  error:   (msg, d) => Toast.show(msg, 'error',   d),
  info:    (msg, d) => Toast.show(msg, 'info',     d),
};

window.Toast = Toast;


// ─── Cart Functions ───────────────────────────────────────────
const Cart = {
  async addItem(productId, itemType, quantity = 1, startDate = null, endDate = null) {
    try {
      const body = new FormData();
      body.append('action', 'add');
      body.append('product_id', productId);
      body.append('item_type', itemType);
      body.append('quantity', quantity);
      if (startDate) body.append('rental_start_date', startDate);
      if (endDate)   body.append('rental_end_date',   endDate);

      const res  = await fetch('/api/cart.php', { method: 'POST', body });
      const data = await res.json();

      if (data.success) {
        this.updateBadge(data.cart_count);
        Toast.success(data.message || 'Added to cart!');
      } else {
        Toast.error(data.message || 'Failed to add to cart.');
      }
      return data;
    } catch (err) {
      Toast.error('Something went wrong. Please try again.');
      return { success: false };
    }
  },

  updateBadge(count) {
    document.querySelectorAll('.cart-count-badge').forEach(el => {
      el.textContent = count;
      el.style.display = count > 0 ? 'flex' : 'none';
    });
  },

  async getCount() {
    try {
      const res  = await fetch('/api/cart.php?action=count');
      const data = await res.json();
      this.updateBadge(data.count || 0);
    } catch {}
  }
};

window.Cart = Cart;

// Update cart badge on page load
document.addEventListener('DOMContentLoaded', () => Cart.getCount());


// ─── Wishlist Toggle ──────────────────────────────────────────
async function toggleWishlist(productId, btn) {
  try {
    const body = new FormData();
    body.append('action',     'toggle');
    body.append('product_id', productId);

    const res  = await fetch('/api/wishlist.php', { method: 'POST', body });
    const data = await res.json();

    if (data.success) {
      const icon = btn.querySelector('i') || btn;
      if (data.action === 'added') {
        btn.classList.add('wishlisted');
        icon.style.color = '#e040fb';
        Toast.success('Added to wishlist ❤️');
      } else {
        btn.classList.remove('wishlisted');
        icon.style.color = '';
        Toast.info('Removed from wishlist');
      }
    } else if (data.redirect) {
      window.location.href = data.redirect;
    }
  } catch {
    Toast.error('Could not update wishlist.');
  }
}

window.toggleWishlist = toggleWishlist;


// ─── Modal Helpers ────────────────────────────────────────────
function openModal(id) {
  const el = document.getElementById(id);
  if (el) {
    el.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) {
    el.classList.remove('active');
    document.body.style.overflow = '';
  }
}

// Close on overlay click
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('active');
    document.body.style.overflow = '';
  }
});

// Close on Escape
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.active').forEach(m => {
      m.classList.remove('active');
      document.body.style.overflow = '';
    });
  }
});

window.openModal  = openModal;
window.closeModal = closeModal;


// ─── Confirm Dialog ───────────────────────────────────────────
function confirmAction(message, callback) {
  if (confirm(message)) callback();
}
window.confirmAction = confirmAction;


// ─── Animate Numbers ─────────────────────────────────────────
function animateNumber(el, end, duration = 1000, prefix = '') {
  const start    = 0;
  const range    = end - start;
  const startTime = performance.now();

  function step(currentTime) {
    const elapsed  = currentTime - startTime;
    const progress = Math.min(elapsed / duration, 1);
    const eased    = 1 - Math.pow(1 - progress, 3);
    el.textContent = prefix + Math.floor(start + range * eased).toLocaleString();
    if (progress < 1) requestAnimationFrame(step);
  }
  requestAnimationFrame(step);
}

// Animate all stat values on load
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-animate-num]').forEach(el => {
    const val    = parseInt(el.dataset.animateNum) || 0;
    const prefix = el.dataset.prefix || '';
    animateNumber(el, val, 1200, prefix);
  });
});


// ─── Dropdown Menus ───────────────────────────────────────────
document.addEventListener('click', e => {
  const trigger  = e.target.closest('[data-dropdown]');
  const dropdown = e.target.closest('.dropdown-menu');

  if (trigger) {
    const targetId = trigger.dataset.dropdown;
    const menu     = document.getElementById(targetId);
    if (menu) {
      const isOpen = menu.classList.contains('open');
      document.querySelectorAll('.dropdown-menu.open').forEach(m => m.classList.remove('open'));
      if (!isOpen) menu.classList.add('open');
    }
  } else if (!dropdown) {
    document.querySelectorAll('.dropdown-menu.open').forEach(m => m.classList.remove('open'));
  }
});


// ─── Image Preview ────────────────────────────────────────────
function previewImage(input, previewId) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      const preview = document.getElementById(previewId);
      if (preview) preview.src = e.target.result;
    };
    reader.readAsDataURL(input.files[0]);
  }
}
window.previewImage = previewImage;


// ─── Date Range Picker Helpers ────────────────────────────────
function calculateRentalDays(startId, endId, outputId) {
  const start  = document.getElementById(startId);
  const end    = document.getElementById(endId);
  const output = document.getElementById(outputId);
  if (!start || !end || !output) return 0;

  const s = new Date(start.value);
  const e = new Date(end.value);
  if (isNaN(s) || isNaN(e) || e <= s) {
    output.textContent = '0 days';
    return 0;
  }
  const days = Math.ceil((e - s) / (1000 * 60 * 60 * 24));
  output.textContent = `${days} day${days !== 1 ? 's' : ''}`;
  return days;
}

window.calculateRentalDays = calculateRentalDays;


// ─── Star Rating Renderer ─────────────────────────────────────
function renderStars(rating, max = 5) {
  let html = '';
  for (let i = 1; i <= max; i++) {
    html += i <= rating ? '⭐' : '☆';
  }
  return html;
}
window.renderStars = renderStars;


// ─── Format Currency ─────────────────────────────────────────
function formatPHP(amount) {
  return '₱' + parseFloat(amount).toLocaleString('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
}
window.formatPHP = formatPHP;


// ─── Lazy Image Loading ───────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  const images = document.querySelectorAll('img[data-src]');
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(entries => {
      entries.forEach(e => {
        if (e.isIntersecting) {
          const img = e.target;
          img.src = img.dataset.src;
          img.removeAttribute('data-src');
          observer.unobserve(img);
        }
      });
    });
    images.forEach(img => observer.observe(img));
  } else {
    images.forEach(img => { img.src = img.dataset.src; });
  }
});


// ─── Star Particle Background ─────────────────────────────────
function createStarsBg(containerId, count = 120) {
  const container = document.getElementById(containerId);
  if (!container) return;
  for (let i = 0; i < count; i++) {
    const star = document.createElement('div');
    star.className = 'star';
    star.style.left     = Math.random() * 100 + 'vw';
    star.style.top      = Math.random() * 100 + 'vh';
    star.style.setProperty('--duration', (2 + Math.random() * 4) + 's');
    star.style.setProperty('--delay',    (Math.random() * 4) + 's');
    star.style.width  = (Math.random() * 2 + 1) + 'px';
    star.style.height = star.style.width;
    star.style.opacity = Math.random() * 0.5 + 0.1;
    container.appendChild(star);
  }
}
window.createStarsBg = createStarsBg;
