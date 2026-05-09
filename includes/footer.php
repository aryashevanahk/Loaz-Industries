    </main>
    
    <!-- Footer -->
    <footer class="site-footer">
        <div class="container">
            <div class="footer-grid">
                <!-- Brand Column -->
                <div class="footer-col">
                    <div class="footer-brand">
                        <div class="brand-icon">
                            <i class="fas fa-microchip"></i>
                        </div>
                        <div class="brand-text">
                            <span class="brand-name">Loaz</span>
                            <span class="brand-sub">Industries</span>
                        </div>
                    </div>
                    <p class="footer-desc">
                        Solusi terpercaya untuk servis elektronik dan part berkualitas. 
                        Teknisi profesional dengan garansi terbaik.
                    </p>
                    <div class="social-links">
                        <a href="https://www.facebook.com/aditiya.w.putro.9" class="social-link" target="_blank"><i class="fab fa-facebook-f"></i></a>
                        <a href="https://www.youtube.com/@aditiyawiguno9102" class="social-link" target="_blank"><i class="fab fa-youtube"></i></a>
                        <a href="https://www.instagram.com/axfron_/" class="social-link" target="_blank"><i class="fab fa-instagram"></i></a>
                        <a href="https://open.spotify.com/user/31angfgubvczs4zmktwk7k3ukdwq?si=b1160dc57e164510" class="social-link" target="_blank"><i class="fab fa-spotify"></i></a>
                    </div>
                </div>
                
                <!-- Layanan Column -->
                <div class="footer-col">
                    <h4 class="footer-title">Layanan</h4>
                    <ul class="footer-links">
                        <li><a href="/loaz_industries/user/request_service.php">🔧 Servis Elektronik</a></li>
                        <li><a href="/loaz_industries/user/order_part.php">🛒 Jual Beli Part</a></li>
                        <li><a href="/loaz_industries/user/request_service.php?category=ac">❄️ Servis AC</a></li>
                        <li><a href="/loaz_industries/user/request_service.php?category=laptop">💻 Servis Laptop</a></li>
                        <li><a href="/loaz_industries/user/request_service.php?category=smartphone">📱 Servis Smartphone</a></li>
                    </ul>
                </div>
                
                <!-- Perusahaan Column -->
                <div class="footer-col">
                    <h4 class="footer-title">Perusahaan</h4>
                    <ul class="footer-links">
                        <li><a href="/loaz_industries/about.php">📖 Tentang Kami</a></li>
                        <li><a href="/loaz_industries/contact.php">📞 Hubungi Kami</a></li>
                        <li><a href="/loaz_industries/career/karir.php">💼 Karir</a></li>
                        <li><a href="#">📝 Blog</a></li>
                        <li><a href="#">📜 Syarat & Ketentuan</a></li>
                    </ul>
                </div>
                
                <!-- Kontak Column -->
                <div class="footer-col">
                    <h4 class="footer-title">Kontak</h4>
                    <ul class="footer-contact">
                        <li>
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Jalan Raya Serpong No. 123, Kota Tangerang, Banten</span>
                        </li>
                        <li>
                            <i class="fas fa-phone"></i>
                            <span>(021) 1234-5678</span>
                        </li>
                        <li>
                            <i class="fab fa-whatsapp"></i>
                            <span>0812-3456-7890</span>
                        </li>
                        <li>
                            <i class="fas fa-envelope"></i>
                            <span>info@loazindustries.com</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Footer Bottom -->
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Loaz Industries. All rights reserved.</p>
                <div class="footer-bottom-links">
                    <a href="#">Privacy Policy</a>
                    <span class="separator">|</span>
                    <a href="#">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Scroll to Top Button -->
    <button class="scroll-top" id="scrollTop" aria-label="Scroll to top">
        <i class="fas fa-arrow-up"></i>
    </button>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/loaz_industries/assets/js/main.js"></script>
    
    <?php
    // Load page-specific JavaScript
    $current_file = basename($_SERVER['PHP_SELF']);
    $page_name = str_replace('.php', '', $current_file);
    
    // Handle index page
    if ($page_name == 'index') {
        $page_name = 'home';
    }
    
    $page_js = "/loaz_industries/assets/js/{$page_name}.js";
    if (file_exists($_SERVER['DOCUMENT_ROOT'] . $page_js)) {
        echo "<script src='{$page_js}'></script>";
    }
    ?>
    
    <!-- Simple scroll to top script (fallback if main.js not loaded) -->
    <script>
        (function() {
            var scrollBtn = document.getElementById('scrollTop');
            if (scrollBtn) {
                window.addEventListener('scroll', function() {
                    if (window.scrollY > 300) {
                        scrollBtn.classList.add('show');
                    } else {
                        scrollBtn.classList.remove('show');
                    }
                });
                
                scrollBtn.addEventListener('click', function() {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
            }
        })();
    </script>
</body>
</html>