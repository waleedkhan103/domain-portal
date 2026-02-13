</main>

<footer class="bg-dark text-light mt-5 py-5">
  <div class="container">
    <div class="row mb-4">
      <div class="col-md-3">
        <h5><i class="bi bi-globe"></i> DomainPortal</h5>
        <p class="small text-muted">Your trusted domain registration and management platform.</p>
      </div>
      <div class="col-md-3">
        <h6 class="fw-bold">Quick Links</h6>
        <ul class="list-unstyled small">
          <li><a href="<?php echo pageUrl('domain_search.php'); ?>" class="text-decoration-none text-muted">Search Domains</a></li>
          <li><a href="<?php echo pageUrl('whois.php'); ?>" class="text-decoration-none text-muted">WHOIS Lookup</a></li>
          <li><a href="<?php echo pageUrl('my_domains.php'); ?>" class="text-decoration-none text-muted">My Domains</a>
          </li>
          <li><a href="<?php echo pageUrl('cart.php'); ?>" class="text-decoration-none text-muted">Cart</a></li>
        </ul>
      </div>
      <div class="col-md-3">
        <h6 class="fw-bold">Support</h6>
        <ul class="list-unstyled small">
          <li><a href="#" class="text-decoration-none text-muted">Help Center</a></li>
          <li><a href="#" class="text-decoration-none text-muted">Contact Us</a></li>
          <li><a href="#" class="text-decoration-none text-muted">FAQ</a></li>
        </ul>
      </div>
      <div class="col-md-3">
        <h6 class="fw-bold">Legal</h6>
        <ul class="list-unstyled small">
          <li><a href="#" class="text-decoration-none text-muted">Terms of Service</a></li>
          <li><a href="#" class="text-decoration-none text-muted">Privacy Policy</a></li>
        </ul>
      </div>
    </div>
    <hr class="bg-secondary">
    <div class="text-center small text-muted">
      <p>&copy; <?php echo date('Y'); ?> DomainPortal. All rights reserved.</p>
    </div>
  </div>
</footer>

<script src="<?php echo assetUrl('assets/js/main.js'); ?>"></script>
<?php if (isset($additionalScripts)): ?>
  <?php foreach ($additionalScripts as $script): ?>
    <script src="<?php echo assetUrl($script); ?>"></script>
  <?php endforeach; ?>
<?php endif; ?>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>