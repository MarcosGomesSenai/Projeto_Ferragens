<?php
$pageTitle = 'Caixa';
require_once APP_PATH . '/views/templates/header.php';
?>
<div class="app-container">
    <?php require_once APP_PATH . '/views/templates/navigation.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left"><h1 class="topbar-title">Caixa</h1></div>
            <div class="topbar-right">
                <?php if ($currentCash): ?>
                    <a href="index.php?page=cash&action=movement" class="btn btn-secondary">Movimento</a>
                    <a href="index.php?page=cash&action=close" class="btn btn-warning">Fechar Caixa</a>
                <?php else: ?>
                    <a href="index.php?page=cash&action=open" class="btn btn-primary">Abrir Caixa</a>
                <?php endif; ?>
            </div>
        </header>

        <div class="content-area">
            <div class="stats-grid">
                <div class="stat-card <?php echo $currentCash ? 'success' : 'warning'; ?>">
                    <div class="stat-label">Meu caixa</div>
                    <div class="stat-value"><?php echo $currentCash ? 'Aberto' : 'Fechado'; ?></div>
                    <div class="stat-description"><?php echo $currentCash ? 'Saldo esperado ' . formatMoney((float) $currentCash['expected_balance']) : 'Abra o caixa antes de vender'; ?></div>
                </div>
                <div class="stat-card info">
                    <div class="stat-label">Caixas abertos</div>
                    <div class="stat-value"><?php echo formatNumber(count($openRegisters)); ?></div>
                    <div class="stat-description">Operadores com turno em andamento</div>
                </div>
            </div>

            <?php if (!empty($pendingRegisters)): ?>
            <div class="card">
                <div class="card-header"><h3 class="card-title">Caixas pendentes de dias anteriores</h3></div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="table">
                            <thead><tr><th>Operador</th><th>Abertura</th><th>Saldo esperado</th><th>Acao</th></tr></thead>
                            <tbody>
                                <?php foreach ($pendingRegisters as $pending): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($pending['user_name']); ?></td>
                                        <td><?php echo formatDate($pending['opened_at']); ?></td>
                                        <td><?php echo formatMoney((float) $pending['expected_balance']); ?></td>
                                        <td>
                                            <?php if (hasPermission('admin')): ?>
                                                <form action="index.php?page=cash&action=forceClose" method="POST" class="table-actions">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                    <input type="hidden" name="id" value="<?php echo (int) $pending['id']; ?>">
                                                    <input type="password" name="reauth_password" class="form-control" placeholder="Senha admin" autocomplete="current-password" required>
                                                    <button type="submit" class="btn btn-sm btn-warning" data-confirm="Forcar fechamento retroativo deste caixa?">Forcar fechamento</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="badge badge-warning">Admin necessario</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($currentCash): ?>
            <div class="card">
                <div class="card-header"><h3 class="card-title">Movimentos recentes</h3></div>
                <div class="card-body">
                    <?php if (empty($recentMovements)): ?>
                        <div class="alert alert-info">Nenhum movimento registrado.</div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="table">
                                <thead><tr><th>Data</th><th>Tipo</th><th>Forma</th><th>Valor</th><th>Motivo</th></tr></thead>
                                <tbody>
                                    <?php foreach ($recentMovements as $movement): ?>
                                        <tr>
                                            <td><?php echo formatDate($movement['created_at']); ?></td>
                                            <td><?php echo htmlspecialchars($movement['type']); ?></td>
                                            <td><?php echo PAYMENT_METHODS[$movement['payment_method']] ?? htmlspecialchars($movement['payment_method'] ?? '-'); ?></td>
                                            <td><?php echo formatMoney((float) $movement['amount']); ?></td>
                                            <td><?php echo htmlspecialchars($movement['reason'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php require_once APP_PATH . '/views/templates/footer.php'; ?>
