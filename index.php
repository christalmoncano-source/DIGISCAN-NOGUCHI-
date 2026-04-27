<?php
require_once 'includes/auth.php';
renderHeader("Noguchi Library - Digital Access");
?>

<style>
/* ── Landing Page Specialized Styles ── */
.lp-hero { 
    padding: 6rem 0; 
    background: white; 
}
.lp-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 24px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    align-items: center;
    gap: 4rem;
}
.lp-content { text-align: left; }
.lp-content h1 { 
    font-size: 4.5rem; 
    font-weight: 800; 
    line-height: 1; 
    color: #6366f1; 
    margin: 0 0 2rem 0; 
}
.lp-content p { 
    font-size: 1.15rem; 
    color: #64748b; 
    line-height: 1.6; 
    max-width: 500px; 
}

.lp-image-wrap { 
    border-radius: 24px; 
    overflow: hidden; 
    box-shadow: 0 30px 60px -12px rgba(0,0,0,0.25); 
}
.lp-image-wrap img { width: 100%; display: block; }

/* ── Infrastructure Section ── */
.infra-section { padding: 6rem 0; background: #fcfcfd; }
.infra-header { max-width: 1200px; margin: 0 auto 4rem; padding: 0 24px; text-align: left; }
.infra-header h2 { font-size: 2.5rem; font-weight: 800; color: #1e293b; }

.infra-card { 
    background: white; 
    border-radius: 20px; 
    padding: 3rem; 
    margin-bottom: 2rem; 
    display: flex; 
    flex-direction: column; 
    gap: 1.5rem;
    border: 1px solid #f1f5f9;
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05);
    text-align: left;
}
.infra-card i { font-size: 3rem; color: #6366f1; margin-bottom: 0.5rem; }
.infra-card h2 { font-size: 1.75rem; text-transform: uppercase; letter-spacing: 1px; color: #1e293b; margin-bottom: 1rem; }
.infra-card ul { list-style: none; padding: 0; margin: 0; }
.infra-card li { margin-bottom: 1rem; display: flex; gap: 0.75rem; color: #475569; font-size: 1.1rem; }
.infra-card li::before { content: '•'; color: #1e293b; font-weight: 800; }
</style>

<div class="lp-hero">
    <div class="lp-container">
        <div class="lp-content">
            <h1>Discover the rich collections of Noguchi Library</h1>
            <p>Access thousands of institutional resources, research papers, and literature through our state-of-the-art digital library system.</p>
        </div>
        <div class="lp-image-wrap">
            <img src="assets/img/noguchi_main.png" alt="Noguchi Library Shelves">
        </div>
    </div>
</div>
    
    <div class="container-wide" style="max-width: 1200px; margin: 0 auto; padding: 0 24px;">
        <div class="infra-card">
            <i class="fas fa-microchip"></i>
            <h2>RULES AND REGULATIONS</h2>
            <p style="color: #080d13ff; margin-bottom: 1.5rem;">All clients who would like to avail the services of the Noguchi Library are expected to observe the following:</p>
            <ul>
                <li>Write their names on the logbook upon entry.</li>
                <li>Observe silence at all times.</li>
                <li>Avoid eating or drinking inside the library.</li>
                <li>Observe courtesy inside the library.</li>
            </ul>
                <p>Everyone is also reminded that the library collections are strictly for inside reading only. The main function of the Noguchi Library is research; thus, making assignments and projects, chatting and other non-research activities are not allowed inside.
        </div>

        <div class="infra-card">
            <i class="fas fa-shield-alt"></i>
            <h2>SERVICE HOURS</h2>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <div>
                    <strong>Monday, Tuesday, Thursday & Friday</strong>
                    <p>7:00 AM - 6:00 PM</p>
                </div>
                <div>
                    <strong>Wednesday</strong>
                    <p>7:00 AM - 5:00 PM</p>
                </div>
                <div>
                    <strong>Saturday</strong>
                    <p>8:00 AM - 12:00 PM</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php renderFooter(); ?>
