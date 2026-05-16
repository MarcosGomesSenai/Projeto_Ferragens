<?php
if (defined('FOOTER_RENDERED')) {
    return;
}
define('FOOTER_RENDERED', true);
?>
<footer class="footer <?php echo isLoggedIn() ? 'app-footer' : 'auth-footer-main'; ?>">
    <div class="footer-content">
        <div class="footer-section about">
            <h4><?php echo htmlspecialchars(APP_NAME); ?></h4>
            <p><?php echo htmlspecialchars(APP_DESCRIPTION); ?></p>
        </div>
        <div class="footer-section contact">
            <h4>Dados da loja</h4>
            <ul>
                <li><?php echo htmlspecialchars(COMPANY_LEGAL_NAME); ?></li>
                <li>CNPJ <?php echo htmlspecialchars(COMPANY_CNPJ); ?></li>
                <li><?php echo htmlspecialchars(COMPANY_ADDRESS); ?></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(APP_NAME); ?>. Documento de controle interno.
    </div>
</footer>
</body>
</html>
