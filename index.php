<?php
require_once 'includes/auth.php';
renderHeader("DigiScan - Home");
?>

<section class="hero" id="home">
    <div class="hero-container">
        <div class="hero-content">
            <h1>Unlock the World <br>of Knowledge</h1>
            <p>Access thousands of institutional resources, research papers, and literature through our state-of-the-art digital library system.</p>
            <div class="hero-btns">
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <a href="registration/register.php" class="btn btn-primary">Get Started</a>
                    <a href="#about" class="btn btn-secondary" style="margin-left: 1rem; border: 1px solid #ddd; padding: 1rem 2rem; border-radius: 10px; text-decoration: none; color: inherit;">Learn More</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="hero-image">
            <img src="https://images.unsplash.com/photo-1507842217343-583bb7270b66?auto=format&fit=crop&w=800&q=80" alt="Library" style="width: 100%; border-radius: 20px; box-shadow: var(--shadow-lg);">
        </div>
    </div>
</section>

<section class="section" id="about" style="background: white; padding: 5rem 0;">
    <div class="container-wide" style="max-width: 1200px; margin: 0 auto; padding: 0 24px; text-align: center;">
        <h2 style="font-size: 2.5rem; margin-bottom: 3rem;">Digital Infrastructure</h2>
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem;">
            <div class="card">
                <i class="fas fa-microchip" style="font-size: 3rem; color: var(--primary-color); margin-bottom: 1.5rem;"></i>
                <h3>Catalog Manager</h3>
                <p>Advanced search algorithms to find exactly what you need in seconds.</p>
            </div>
            <div class="card">
                <i class="fas fa-shield-halved" style="font-size: 3rem; color: var(--success-color); margin-bottom: 1.5rem;"></i>
                <h3>Secure Access</h3>
                <p>Role-based permissions ensure institutional data remains protected.</p>
            </div>
            <div class="card">
                <i class="fas fa-laptop-code" style="font-size: 3rem; color: var(--accent-color); margin-bottom: 1.5rem;"></i>
                <h3>24/7 Availability</h3>
                <p>Borrow and read books online anytime, anywhere on any device.</p>
            </div>
        </div>
    </div>
</section>

<?php renderFooter(); ?>
