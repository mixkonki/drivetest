</main>
    
    <!-- Footer -->
    <footer class="main-footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section about">
                    <h3>Σχετικά με εμάς</h3>
                    <p>Το DriveTest είναι η κορυφαία πλατφόρμα για την προετοιμασία θεωρητικών εξετάσεων στον τομέα των μεταφορών. Προσφέρουμε ολοκληρωμένη υποστήριξη για σχολές και μαθητές.</p>
                    <div class="contact">
                        <span><i class="fas fa-phone"></i> &nbsp; +30 2310 123456</span>
                        <span><i class="fas fa-envelope"></i> &nbsp; info@drivetest.gr</span>
                    </div>
                    <div class="social-links">
                        <a href="#" target="_blank"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" target="_blank"><i class="fab fa-instagram"></i></a>
                        <a href="#" target="_blank"><i class="fab fa-twitter"></i></a>
                        <a href="#" target="_blank"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                
                <div class="footer-section links">
                    <h3>Σύνδεσμοι</h3>
                    <ul>
                        <li><a href="<?= BASE_URL ?>/public/index.php">Αρχική</a></li>
                        <li><a href="<?= BASE_URL ?>/public/about.php">Σχετικά με εμάς</a></li>
                        <li><a href="<?= BASE_URL ?>/subscriptions/buy.php">Συνδρομές</a></li>
                        <li><a href="<?= BASE_URL ?>/public/contact.php">Επικοινωνία</a></li>
                        <li><a href="<?= BASE_URL ?>/public/school-search.php">Αναζήτηση Σχολών</a></li>
                        <li><a href="<?= BASE_URL ?>/public/guest_test.php">Δοκιμαστικό Τεστ</a></li>
                    </ul>
                </div>
                
                <div class="footer-section categories">
                    <h3>Κατηγορίες</h3>
                    <ul>
                        <li><a href="<?= BASE_URL ?>/subscriptions/buy.php?category=1">Υποψηφίων Οδηγών</a></li>
                        <li><a href="<?= BASE_URL ?>/subscriptions/buy.php?category=2">ADR</a></li>
                        <li><a href="<?= BASE_URL ?>/subscriptions/buy.php?category=3">ΠΕΕ</a></li>
                        <li><a href="<?= BASE_URL ?>/subscriptions/buy.php?category=4">Χειριστών Μηχανημάτων Έργου</a></li>
                        <li><a href="<?= BASE_URL ?>/subscriptions/buy.php?category=5">Ταχυπλόων</a></li>
                        <li><a href="<?= BASE_URL ?>/subscriptions/buy.php?category=6">ΤΑΞΙ</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> DriveTest - Όλα τα δικαιώματα προστατεύονται.</p>
                <div class="footer-legal">
                    <a href="<?= BASE_URL ?>/public/terms.php">Όροι Χρήσης</a>
                    <a href="<?= BASE_URL ?>/public/privacy.php">Πολιτική Απορρήτου</a>
                    <a href="<?= BASE_URL ?>/public/cookie.php">Πολιτική Cookies</a>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- JavaScript Files -->
    <?php require_once dirname(__FILE__) . '/scripts.php'; ?>
</body>
</html>