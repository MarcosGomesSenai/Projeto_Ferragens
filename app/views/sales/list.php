<?php
$pageTitle = 'Historico de Vendas';
require_once APP_PATH . '/views/templates/header.php';
?>
<div class="app-container">
    <?php require_once APP_PATH . '/views/templates/navigation.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left"><h1 class="topbar-title">Historico de Vendas</h1></div>
            <div class="topbar-right"><a href="index.php?page=pos" class="btn btn-primary">Nova Venda</a></div>
        </header>
        <div class="content-area">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Historico recente</h3></div>
                <div class="card-body">
                    <?php if (empty($sales)): ?>
                        <div class="alert alert-info">Nenhuma venda registrada.</div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="table">
                                <thead><tr><th>Venda</th><th>Cliente</th><th>Operador</th><th>Data</th><th>Total</th><th>Status</th></tr></thead>
                                <tbody>
                                    <?php foreach ($sales as $sale): ?>
                                        <tr>
                                            <td><a href="index.php?page=sales&action=view&id=<?php echo (int) $sale['id']; ?>"><?php echo htmlspecialchars($sale['sale_number']); ?></a></td>
                                            <td><?php echo htmlspecialchars($sale['customer_name'] ?? 'Consumidor Final'); ?></td>
                                            <td><?php echo htmlspecialchars($sale['user_name'] ?? '-'); ?></td>
                                            <td><?php echo formatDate($sale['created_at']); ?></td>
                                            <td><?php echo formatMoney((float) $sale['total_amount']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $sale['status'] === 'completed' ? 'badge-success' : ($sale['status'] === 'cancelled' ? 'badge-error' : 'badge-neutral'); ?>">
                                                    <?php echo htmlspecialchars($sale['status']); ?>
                                                </span>
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
