<?php
require_once 'includes/auth.php';
renderHeader("Access Denied");
?>
<div class="flex-center-wrapper" style="min-height: 60vh;">
    <div class="container" style="text-align: center;">
        <i class="fas fa-shield-alt" style="font-size: 4rem; color: var(--error-color); margin-bottom: 2rem;"></i>
        <h2 style="color: var(--error-color);">Access Denied</h2>
        <p>You do not have the required permissions to view this highly confidential module.</p>
        <a href="index.php" class="btn btn-primary" style="margin-top: 2rem;">Back to Safety</a>
    </div>
</div>
<?php renderFooter(); ?>
