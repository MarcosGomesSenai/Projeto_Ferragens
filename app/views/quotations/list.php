<?php
$pageTitle = 'Orcamentos';
require_once APP_PATH . '/views/templates/header.php';
?>
<div class="app-container">
    <?php require_once APP_PATH . '/views/templates/navigation.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left"><h1 class="topbar-title">Orcamentos</h1></div>
            <div class="topbar-right"><a href="index.php?page=quotations&action=add" class="btn btn-primary">Novo Orcamento</a></div>
        </header>
        <div class="content-area">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Ultimos orcamentos</h3></div>
                <div class="card-body">
                    <?php if (empty($quotations)): ?>
                        <div class="alert alert-info">Nenhum orcamento registrado.</div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="table">
                                <thead><tr><th>Numero</th><th>Cliente</th><th>Validade</th><th>Total</th><th>Status</th><th></th></tr></thead>
                                <tbody>
                                    <?php foreach ($quotations as $quotation): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($quotation['quotation_number']); ?></td>
                                            <td><?php echo htmlspecialchars($quotation['customer_name'] ?? $quotation['customer_name_db'] ?? '-'); ?></td>
                                            <td><?php echo formatDate($quotation['valid_until'], 'd/m/Y'); ?></td>
                                            <td><?php echo formatMoney((float) $quotation['total_amount']); ?></td>
                                            <td><span class="badge badge-info"><?php echo htmlspecialchars($quotation['status']); ?></span></td>
                                            <td><a href="index.php?page=quotations&action=view&id=<?php echo (int) $quotation['id']; ?>" class="btn btn-sm btn-secondary">Ver</a></td>
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
