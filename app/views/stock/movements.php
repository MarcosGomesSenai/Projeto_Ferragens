<?php
$pageTitle = 'Movimentacoes de Estoque';
require_once APP_PATH . '/views/templates/header.php';
?>

<div class="app-container">
    <?php require_once APP_PATH . '/views/templates/navigation.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left"><h1 class="topbar-title">Movimentacoes de Estoque</h1></div>
            <?php if (hasPermission('manager')): ?>
                <div class="topbar-right"><a href="index.php?page=stock&action=adjustment" class="btn btn-primary">Nova Movimentacao</a></div>
            <?php endif; ?>
        </header>

        <div class="content-area">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Historico</h3></div>
                <div class="card-body">
                    <?php if (empty($movements)): ?>
                        <div class="alert alert-info">Nenhuma movimentacao registrada.</div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Produto</th>
                                        <th>Tipo</th>
                                        <th>Qtd.</th>
                                        <th>Anterior</th>
                                        <th>Novo</th>
                                        <th>Motivo</th>
                                        <th>Usuario</th>
                                        <th>Acoes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($movements as $movement): ?>
                                        <tr>
                                            <td><?php echo formatDate($movement['date']); ?></td>
                                            <td><strong><?php echo htmlspecialchars($movement['product_name'] ?? '-'); ?></strong><small class="muted-line"><?php echo htmlspecialchars($movement['sku'] ?? ''); ?></small></td>
                                            <td><?php echo STOCK_MOVEMENT_TYPES[$movement['type']] ?? htmlspecialchars($movement['type']); ?></td>
                                            <td><?php echo formatQuantity($movement['quantity']); ?></td>
                                            <td><?php echo formatQuantity($movement['old_quantity']); ?></td>
                                            <td><?php echo formatQuantity($movement['new_quantity']); ?></td>
                                            <td><?php echo htmlspecialchars($movement['reason'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($movement['user_name'] ?? '-'); ?></td>
                                            <td>
                                                <?php if (hasPermission('admin')): ?>
                                                    <form action="index.php?page=stock&action=reverseMovement" method="POST" class="table-actions">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                        <input type="hidden" name="id" value="<?php echo (int) $movement['id']; ?>">
                                                        <input type="password" name="reauth_password" class="form-control" placeholder="Senha" autocomplete="current-password" required>
                                                        <button type="submit" class="btn btn-sm btn-danger" data-confirm="Reverter esta movimentacao? So e permitido se ela for a ultima alteracao do produto.">Reverter</button>
                                                    </form>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once APP_PATH . '/views/templates/footer.php'; ?>
