<?php
http_response_code(404);
require_once __DIR__ . '/includes/auth.php';
startSession();

$page_title = '404 - Portal Not Found — Costplay Wonderland';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
    <div style="min-height: 70vh; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 2rem;">
        <div style="font-size: 6rem; font-family: 'Cinzel', serif; font-weight: 900; background: linear-gradient(135deg, var(--neon-pink), var(--neon-cyan)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; text-shadow: 0 0 30px rgba(255, 20, 147, 0.4); margin-bottom: 1rem;">
            404
        </div>
        <div style="font-size: 3rem; margin-bottom: 1rem; animation: float 3s ease-in-out infinite;">
            🛸✨
        </div>
        <h2 style="font-size: 2rem; color: #fff; margin-bottom: 1rem;">Dimensional Rift Detected!</h2>
        <p style="color: var(--text-muted); max-width: 500px; line-height: 1.6; margin-bottom: 2rem;">
            The cosplay or mystical page you are searching for seems to have slipped into an alternate anime dimension. Don't worry, convention staff is on the case!
        </p>
        <div style="display: flex; gap: 1rem; flex-wrap: wrap; justify-content: center;">
            <a href="index.php" class="btn btn-primary" style="padding: 0.8rem 1.8rem; font-size: 1rem;">
                <i class="fas fa-home"></i> Back to Main Stage
            </a>
            <a href="shop.php" class="btn btn-secondary" style="padding: 0.8rem 1.8rem; font-size: 1rem;">
                <i class="fas fa-store"></i> Explore All Costplays
            </a>
        </div>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
