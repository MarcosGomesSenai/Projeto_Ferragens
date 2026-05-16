<?php
$pageTitle = 'Fiscal';
require_once APP_PATH . '/views/templates/header.php';
?>
<div class="app-container">
    <?php require_once APP_PATH . '/views/templates/navigation.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left"><h1 class="topbar-title">Entradas de NF</h1></div>
            <div class="topbar-right"><a href="index.php?page=fiscal&action=add" class="btn btn-primary">Nova Entrada</a></div>
        </header>
        <div class="content-area">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="index.php" class="form-grid-3col">
                        <input type="hidden" name="page" value="fiscal">
                        <div class="form-group">
                            <label for="start_date" class="form-label">Data inicial</label>
                            <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>">
                        </div>
                        <div class="form-group">
                            <label for="end_date" class="form-label">Data final</label>
                            <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>">
                        </div>
                        <div class="form-actions form-actions-left">
                            <button type="submit" class="btn btn-primary">Filtrar</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h3 class="card-title">Notas registradas</h3></div>
                <div class="card-body">
                    <?php if (empty($entries)): ?>
                        <div class="alert alert-info">Nenhuma entrada fiscal registrada.</div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="table">
                                <thead><tr><th>NF</th><th>Serie</th><th>Fornecedor</th><th>Emissao</th><th>Total</th><th>Usuario</th></tr></thead>
                                <tbody>
                                    <?php foreach ($entries as $entry): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($entry['invoice_number']); ?></td>
                                            <td><?php echo htmlspecialchars($entry['invoice_series']); ?></td>
                                            <td><?php echo htmlspecialchars($entry['supplier_name']); ?></td>
                                            <td><?php echo formatDate($entry['issue_date'], 'd/m/Y'); ?></td>
                                            <td><?php echo formatMoney((float) $entry['total_amount']); ?></td>
                                            <td><?php echo htmlspecialchars($entry['user_name'] ?? '-'); ?></td>
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
