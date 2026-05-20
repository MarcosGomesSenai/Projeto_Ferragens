<?php
$pageTitle = 'Produtos';
require_once APP_PATH . '/views/templates/header.php';
?>

<div class="app-container">
    <?php require_once APP_PATH . '/views/templates/navigation.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <h1 class="topbar-title">Produtos</h1>
            </div>
            <div class="topbar-right">
                <a href="index.php?page=products&action=add" class="btn btn-primary">Novo Produto</a>
            </div>
        </header>

        <div class="content-area">
            <div class="filters-bar">
                <form method="GET" action="index.php">
                    <input type="hidden" name="page" value="products">
                    <div class="filters-grid">
                        <div class="form-group mb-0">
                            <label for="search" class="form-label">Pesquisar</label>
                            <input type="text" id="search" name="search" class="form-control"
                                   placeholder="Nome, codigo de barras ou marca"
                                   value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                        </div>

                        <div class="form-group mb-0">
                            <label for="category_id" class="form-label">Categoria</label>
                            <select id="category_id" name="category_id" class="form-control">
                                <option value="">Todas</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo (int) $cat['id']; ?>" <?php echo (int) ($_GET['category_id'] ?? 0) === (int) $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group mb-0">
                            <label for="status" class="form-label">Status</label>
                            <select id="status" name="status" class="form-control">
                                <option value="">Todos</option>
                                <?php foreach (PRODUCT_STATUS as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" <?php echo ($_GET['status'] ?? '') === $key ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group mb-0">
                            <label for="stock_alert" class="form-label">Alerta</label>
                            <select id="stock_alert" name="stock_alert" class="form-control">
                                <option value="">Todos</option>
                                <option value="low" <?php echo ($_GET['stock_alert'] ?? '') === 'low' ? 'selected' : ''; ?>>Para reposicao</option>
                                <option value="critical" <?php echo ($_GET['stock_alert'] ?? '') === 'critical' ? 'selected' : ''; ?>>Abaixo do minimo</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions form-actions-left">
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                        <a href="index.php?page=products" class="btn btn-secondary">Limpar</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Lista de Produtos (<?php echo count($products); ?>)</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($products)): ?>
                        <div class="alert alert-info">
                            Nenhum produto encontrado.
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Codigo de barras</th>
                                        <th>Produto</th>
                                        <th>Categoria</th>
                                        <th>Un.</th>
                                        <th>Varejo</th>
                                        <th>Atacado</th>
                                        <th>Estoque</th>
                                        <th>Min.</th>
                                        <th>Status</th>
                                        <th>Acoes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                        <?php
                                        $stockLevel = productStockAlertLevel($product);
                                        $badge = $stockLevel === 'critical' ? 'badge-error' : ($stockLevel === 'low' ? 'badge-warning' : 'badge-success');
                                        ?>
                                        <tr>
                                            <td>
                                                <code><?php echo htmlspecialchars($product['sku']); ?></code>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                                <?php if (!empty($product['brand'])): ?>
                                                    <small class="muted-line"><?php echo htmlspecialchars($product['brand']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($product['category_name'] ?? '-'); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($product['unit_of_measure'] ?? 'UN'); ?></td>
                                            <td><?php echo formatMoney((float) $product['sale_price']); ?></td>
                                            <td><?php echo $product['wholesale_price'] ? formatMoney((float) $product['wholesale_price']) : '-'; ?></td>
                                            <td><span class="badge <?php echo $badge; ?>"><?php echo formatQuantity($product['quantity']); ?></span></td>
                                            <td><?php echo formatQuantity($product['min_quantity']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $product['status'] === 'active' ? 'badge-success' : 'badge-neutral'; ?>">
                                                    <?php echo PRODUCT_STATUS[$product['status']] ?? htmlspecialchars($product['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="table-actions">
                                                    <a href="index.php?page=products&action=view&id=<?php echo (int) $product['id']; ?>" class="btn btn-sm btn-secondary">Ver</a>
                                                    <a href="index.php?page=products&action=edit&id=<?php echo (int) $product['id']; ?>" class="btn btn-sm btn-primary">Editar</a>
                                                    <?php if (hasPermission('manager')): ?>
                                                        <form action="index.php?page=products&action=delete" method="POST" class="table-actions">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                            <input type="hidden" name="id" value="<?php echo (int) $product['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger" data-confirm="Excluir produtos sem historico remove o cadastro. Produtos com historico serao inativados. Continuar?">Excluir</button>
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
