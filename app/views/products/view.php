<?php
$pageTitle = 'Detalhes do Produto';
require_once APP_PATH . '/views/templates/header.php';
$stockLevel = productStockAlertLevel($product);
?>

<div class="app-container">
    <?php require_once APP_PATH . '/views/templates/navigation.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <h1 class="topbar-title">Detalhes do Produto</h1>
            </div>
            <div class="topbar-right">
                <a href="index.php?page=products" class="btn btn-secondary">Voltar</a>
                <a href="index.php?page=products&action=edit&id=<?php echo (int) $product['id']; ?>" class="btn btn-primary">Editar</a>
            </div>
        </header>

        <div class="content-area">
            <?php if ($stockLevel === 'critical'): ?>
                <div class="alert alert-error">
                    Produto abaixo do estoque minimo. Disponivel: <?php echo formatQuantity($product['quantity']); ?>.
                </div>
            <?php elseif ($stockLevel === 'low'): ?>
                <div class="alert alert-warning">
                    Produto abaixo do ponto de reposicao. Disponivel: <?php echo formatQuantity($product['quantity']); ?>.
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                    <span class="badge <?php echo $product['status'] === 'active' ? 'badge-success' : 'badge-neutral'; ?>">
                        <?php echo PRODUCT_STATUS[$product['status']] ?? htmlspecialchars($product['status']); ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="detail-grid">
                        <div><strong>Codigo de barras</strong><span><?php echo htmlspecialchars($product['sku']); ?></span></div>
                        <div><strong>Marca</strong><span><?php echo htmlspecialchars($product['brand'] ?? '-'); ?></span></div>
                        <div><strong>Categoria</strong><span><?php echo htmlspecialchars($product['category_name'] ?? '-'); ?></span></div>
                        <div><strong>Subcategoria</strong><span><?php echo htmlspecialchars($product['subcategory_name'] ?? '-'); ?></span></div>
                        <div><strong>Fornecedor</strong><span><?php echo htmlspecialchars($product['supplier_name'] ?? '-'); ?></span></div>
                        <div><strong>Fornecedor alternativo</strong><span><?php echo htmlspecialchars($product['alternate_supplier_name'] ?? '-'); ?></span></div>
                        <div><strong>Unidade</strong><span><?php echo htmlspecialchars($product['unit_of_measure'] ?? 'UN'); ?></span></div>
                        <div><strong>Fator</strong><span><?php echo formatQuantity($product['conversion_factor'] ?? 1); ?></span></div>
                        <div><strong>Estoque</strong><span><?php echo formatQuantity($product['quantity']); ?></span></div>
                        <div><strong>Estoque minimo</strong><span><?php echo formatQuantity($product['min_quantity']); ?></span></div>
                        <div><strong>Ponto de reposicao</strong><span><?php echo formatQuantity($product['reorder_point']); ?></span></div>
                        <div><strong>Preco custo</strong><span><?php echo hasPermission('operator') ? formatMoney((float) $product['cost_price']) : '-'; ?></span></div>
                        <div><strong>Preco varejo</strong><span><?php echo formatMoney((float) $product['sale_price']); ?></span></div>
                        <div><strong>Preco atacado</strong><span><?php echo $product['wholesale_price'] ? formatMoney((float) $product['wholesale_price']) : '-'; ?></span></div>
                        <div><strong>Margem</strong><span><?php echo formatNumber($product['margin_percent'], 2); ?>%</span></div>
                        <div><strong>Markup</strong><span><?php echo formatNumber($product['markup_percent'], 2); ?>%</span></div>
                    </div>

                    <?php if (!empty($product['description'])): ?>
                        <div class="text-block">
                            <strong>Descricao</strong>
                            <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Historico de Movimentacoes</h3>
                    <a href="index.php?page=stock&action=adjustment" class="btn btn-sm btn-outline">Nova Movimentacao</a>
                </div>
                <div class="card-body">
                    <?php if (empty($productMovements)): ?>
                        <div class="alert alert-info">Nenhuma movimentacao registrada.</div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Tipo</th>
                                        <th>Qtd.</th>
                                        <th>Anterior</th>
                                        <th>Novo</th>
                                        <th>Origem</th>
                                        <th>Usuario</th>
                                        <th>Acoes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($productMovements as $movement): ?>
                                        <tr>
                                            <td><?php echo formatDate($movement['date']); ?></td>
                                            <td><?php echo STOCK_MOVEMENT_TYPES[$movement['type']] ?? htmlspecialchars($movement['type']); ?></td>
                                            <td><?php echo formatStockMovementQuantity($movement); ?></td>
                                            <td><?php echo formatQuantity($movement['old_quantity']); ?></td>
                                            <td><?php echo formatQuantity($movement['new_quantity']); ?></td>
                                            <td><?php echo htmlspecialchars($movement['invoice_number'] ?? ($movement['reason'] ?? '-')); ?></td>
                                            <td><?php echo htmlspecialchars($movement['user_name'] ?? '-'); ?></td>
                                            <td>
                                                <?php if (hasPermission('admin')): ?>
                                                    <form action="index.php?page=stock&action=reverseMovement" method="POST" class="table-actions">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                        <input type="hidden" name="id" value="<?php echo (int) $movement['id']; ?>">
                                                        <input type="hidden" name="return" value="view">
                                                        <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" data-confirm="Remover esta movimentacao? O estoque voltara ao saldo anterior. So e permitido se ela for a ultima alteracao do produto.">Remover</button>
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
