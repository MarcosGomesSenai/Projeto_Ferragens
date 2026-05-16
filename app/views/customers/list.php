<?php
$pageTitle = 'Clientes';
require_once APP_PATH . '/views/templates/header.php';
?>

<div class="app-container">
    <?php require_once APP_PATH . '/views/templates/navigation.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left"><h1 class="topbar-title">Clientes</h1></div>
            <div class="topbar-right"><a href="index.php?page=customers&action=add" class="btn btn-primary">Novo Cliente</a></div>
        </header>

        <div class="content-area">
            <div class="filters-bar">
                <form method="GET" action="index.php">
                    <input type="hidden" name="page" value="customers">
                    <div class="filters-grid filters-grid-compact">
                        <div class="form-group mb-0">
                            <label for="search" class="form-label">Pesquisar</label>
                            <input type="text" id="search" name="search" class="form-control" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" placeholder="Nome, CPF/CNPJ ou telefone">
                        </div>
                    </div>
                    <div class="form-actions form-actions-left">
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                        <a href="index.php?page=customers" class="btn btn-secondary">Limpar</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Lista de Clientes (<?php echo count($customers); ?>)</h3></div>
                <div class="card-body">
                    <?php if (empty($customers)): ?>
                        <div class="alert alert-info">Nenhum cliente encontrado.</div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Cliente</th>
                                        <th>Documento</th>
                                        <th>Tipo</th>
                                        <th>Contato</th>
                                        <th>Crediario</th>
                                        <th>Status</th>
                                        <th>Acoes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($customers as $customer): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($customer['name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($customer['document'] ?? '-'); ?></td>
                                            <td><?php echo CUSTOMER_TYPES[$customer['customer_type']] ?? htmlspecialchars($customer['customer_type']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($customer['phone'] ?: '-'); ?>
                                                <?php if (!empty($customer['email'])): ?>
                                                    <small class="muted-line"><?php echo htmlspecialchars($customer['email']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo !empty($customer['credit_enabled']) ? formatMoney((float) $customer['credit_limit']) : '-'; ?>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $customer['status'] === 'active' ? 'badge-success' : 'badge-neutral'; ?>">
                                                    <?php echo $customer['status'] === 'active' ? 'Ativo' : 'Inativo'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (empty($customer['is_default'])): ?>
                                                    <div class="table-actions">
                                                        <a href="index.php?page=customers&action=edit&id=<?php echo (int) $customer['id']; ?>" class="btn btn-sm btn-primary">Editar</a>
                                                        <?php if (hasPermission('manager')): ?>
                                                            <form action="index.php?page=customers&action=delete" method="POST" class="table-actions">
                                                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                                <input type="hidden" name="id" value="<?php echo (int) $customer['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-danger" data-confirm="Clientes com historico serao inativados. Continuar?">Excluir</button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="badge badge-info">Fixo</span>
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
