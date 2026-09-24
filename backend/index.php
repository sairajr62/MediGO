<?php
/**
 * index.php — public home page with a quick medicine search.
 */
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Home';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div class="hero-content">
    <h1>Find and reserve medicines near you</h1>
    <p>Search real-time stock across approved pharmacies, then reserve what you need for pickup.</p>
    <form class="hero-search" action="<?= e(base_url('search.php')) ?>" method="get">
      <input type="text" name="q" placeholder="Search by medicine or generic name, e.g. Paracetamol">
      <button type="submit" class="btn btn-primary">Search</button>
    </form>
  </div>
</section>

<section class="features">
  <div class="feature-card">
    <h3>Search availability</h3>
    <p>Check which pharmacies have your medicine in stock, with live quantity and pricing.</p>
  </div>
  <div class="feature-card">
    <h3>Reserve instantly</h3>
    <p>Reserve available medicine in a couple of clicks and pick it up at the pharmacy.</p>
  </div>
  <div class="feature-card">
    <h3>Track reservations</h3>
    <p>Follow the status of every reservation from pending to collected in your dashboard.</p>
  </div>
</section>

<?php if (!is_logged_in()): ?>
<section class="cta">
  <p>Are you a pharmacy? <a href="<?= e(base_url('register.php')) ?>">List your inventory</a> and start receiving reservations.</p>
</section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
