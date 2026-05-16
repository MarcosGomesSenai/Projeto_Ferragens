<?php
$pageTitle = 'Fornecedores';
require_once APP_PATH . '/views/templates/header.php';
?>

<div class="app-container">
    <?php require_once APP_PATH . '/views/templates/navigation.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left"><h1 class="topbar-title">Fornecedores</h1></div>
            <div class="topbar-right"><a href="index.php?page=suppliers&action=add" class="btn btn-primary">Novo Fornecedor</a></div>
        </header>

        <div class="content-area">
            <div class="filters-bar">
                <form method="GET" action="index.php">
                    <input type="hidden" name="page" value="suppliers">
                    <div class="filters-grid filters-grid-compact">
                        <div class="form-group mb-0">
                            <label for="search" class="form-label">Pesquisar</label>
                            <input type="text" id="search" name="search" class="form-control" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" placeholder="Nome, CNPJ ou segmento">
                        </div>
                        <div class="form-group mb-0">
                            <label for="status" class="form-label">Status</label>
                            <select id="status" name="status" class="form-control">
                                <option value="">Todos</option>
                                <option value="active" <?php echo ($_GET['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Ativo</option>
                                <option value="inactive" <?php echo ($_GET['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inativo</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-actions form-actions-left">
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                        <a href="index.php?page=suppliers" class="btn btn-secondary">Limpar</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Lista de Fornecedores (<?php echo count($suppliers); ?>)</h3></div>
                <div class="card-body">
                    <?php if (empty($suppliers)): ?>
                        <div class="alert alert-info">Nenhum fornecedor encontrado.</div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Fornecedor</th>
                                        <th>CNPJ</th>
                                        <th>Segmento</th>
                                        <th>Contato</th>
                                        <th>Prazo</th>
                                        <th>Em aberto</th>
                                        <th>Status</th>
                                        <th>Acoes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($suppliers as $supplier): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($supplier['name']); ?></strong>
                                                <small class="muted-line"><?php echo htmlspecialchars($supplier['legal_name'] ?? ''); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($supplier['cnpj']); ?></td>
                                            <td><?php echo htmlspecialchars($supplier['segment'] ?? '-'); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($supplier['commercial_contact_name'] ?: ($supplier['phone'] ?? '-')); ?>
                                                <?php if (!empty($supplier['commercial_contact_phone'])): ?>
                                                    <small class="muted-line"><?php echo htmlspecialchars($supplier['commercial_contact_phone']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($supplier['default_payment_terms'] ?? '-'); ?></td>
                                            <td><?php echo formatMoney((float) ($supplier['open_payables'] ?? 0)); ?></td>
                                            <td>
                                                <span class="badge <?php echo $supplier['status'] === 'active' ? 'badge-success' : 'badge-neutral'; ?>">
                                                    <?php echo $supplier['status'] === 'active' ? 'Ativo' : 'Inativo'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="table-actions">
                                                    <a href="index.php?page=suppliers&action=edit&id=<?php echo (int) $supplier['id']; ?>" class="btn btn-sm btn-primary">Editar</a>
                                                    <?php if (hasPermission('manager')): ?>
                                                        <form action="index.php?page=suppliers&action=delete" method="POST" class="table-actions">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                            <input type="hidden" name="id" value="<?php echo (int) $supplier['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger" data-confirm="Fornecedores com vinculos serao inativados para preservar historico. Continuar?">Excluir</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
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
