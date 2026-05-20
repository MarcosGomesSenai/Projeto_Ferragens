<?php
$pageTitle = 'Fechar Caixa';
require_once APP_PATH . '/views/templates/header.php';
?>
<div class="app-container">
    <?php require_once APP_PATH . '/views/templates/navigation.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left"><h1 class="topbar-title">Fechar Caixa</h1></div>
            <div class="topbar-right"><a href="index.php?page=cash" class="btn btn-secondary">Voltar</a></div>
        </header>
        <div class="content-area">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Resumo do turno</h3></div>
                <div class="card-body">
                    <div class="detail-grid">
                        <div><strong>Saldo inicial</strong><span><?php echo formatMoney((float) $currentCash['initial_balance']); ?></span></div>
                        <div><strong>Saldo esperado</strong><span><?php echo formatMoney((float) $currentCash['expected_balance']); ?></span></div>
                        <div><strong>Aberto em</strong><span><?php echo formatDate($currentCash['opened_at']); ?></span></div>
                    </div>
                    <form action="index.php?page=cash&action=saveClose" method="POST" data-validate style="margin-top: var(--space-6);">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <div class="form-group">
                            <label for="counted_balance" class="form-label required">Saldo fisico contado</label>
                            <input type="number" id="counted_balance" name="counted_balance" class="form-control" min="0" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label for="close_notes" class="form-label">Observacoes</label>
                            <textarea id="close_notes" name="close_notes" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="form-actions">
                            <a href="index.php?page=cash" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-warning">Fechar Caixa</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once APP_PATH . '/views/templates/footer.php'; ?>
