<?php
$pageTitle = 'Venda ' . $sale['sale_number'];
$salesBackUrl = hasPermission('manager') ? 'index.php?page=sales' : 'index.php?page=pos';
require_once APP_PATH . '/views/templates/header.php';
?>
<div class="app-container">
    <?php require_once APP_PATH . '/views/templates/navigation.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left"><h1 class="topbar-title">Venda <?php echo htmlspecialchars($sale['sale_number']); ?></h1></div>
            <div class="topbar-right">
                <a href="index.php?page=sales&action=pdf&id=<?php echo (int) $sale['id']; ?>" class="btn btn-primary">PDF</a>
                <a href="<?php echo $salesBackUrl; ?>" class="btn btn-secondary">Voltar</a>
            </div>
        </header>
        <div class="content-area">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Resumo</h3>
                    <span class="badge <?php echo $sale['status'] === 'completed' ? 'badge-success' : ($sale['status'] === 'cancelled' ? 'badge-error' : 'badge-neutral'); ?>">
                        <?php echo htmlspecialchars($sale['status']); ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="detail-grid">
                        <div><strong>Cliente</strong><span><?php echo htmlspecialchars($sale['customer_name'] ?? 'Consumidor Final'); ?></span></div>
                        <div><strong>Operador</strong><span><?php echo htmlspecialchars($sale['user_name'] ?? '-'); ?></span></div>
                        <div><strong>Data</strong><span><?php echo formatDate($sale['created_at']); ?></span></div>
                        <div><strong>Subtotal</strong><span><?php echo formatMoney((float) $sale['subtotal']); ?></span></div>
                        <div><strong>Desconto</strong><span><?php echo formatMoney((float) $sale['discount_amount']); ?></span></div>
                        <div><strong>Total</strong><span><?php echo formatMoney((float) $sale['total_amount']); ?></span></div>
                    </div>
                    <div class="alert alert-info" style="margin-top: var(--space-4);">Este documento nao possui valor fiscal.</div>
                    <?php if (!empty($sale['notes'])): ?>
                        <div class="text-block">
                            <strong>Observacoes da venda</strong>
                            <p><?php echo nl2br(htmlspecialchars($sale['notes'])); ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if ($sale['status'] !== 'cancelled' && hasPermission('manager')): ?>
                        <div class="text-block">
                            <strong>Cancelar venda</strong>
                            <form action="index.php?page=sales&action=cancel&id=<?php echo (int) $sale['id']; ?>" method="POST" data-validate>
                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                 <div class="form-group">
                                     <label for="reason" class="form-label required">Motivo</label>
                                     <input type="text" id="reason" name="reason" class="form-control" required>
                                 </div>
                                 <div class="form-group">
                                     <label for="reauth_password" class="form-label required">Confirme sua senha</label>
                                     <input type="password" id="reauth_password" name="reauth_password" class="form-control" autocomplete="current-password" required>
                                 </div>
                                 <div class="form-actions form-actions-left">
                                    <button type="submit" class="btn btn-danger" data-confirm="Cancelar a venda estorna estoque e lancamentos. Continuar?">Cancelar Venda</button>
                                </div>
                            </form>
                        </div>
                    <?php elseif ($sale['status'] === 'cancelled'): ?>
                        <div class="alert alert-warning" style="margin-top: var(--space-6);">
                            Cancelada em <?php echo formatDate($sale['cancelled_at']); ?>. Motivo: <?php echo htmlspecialchars($sale['cancel_reason'] ?? '-'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Itens</h3></div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="table">
                            <thead><tr><th>Produto</th><th>Qtd.</th><th>Unitario</th><th>Desc.</th><th>Total</th></tr></thead>
                            <tbody>
                                <?php foreach ($saleItems as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['product_name']); ?><small class="muted-line"><?php echo htmlspecialchars($item['sku_snapshot']); ?></small></td>
                                        <td><?php echo formatQuantity($item['quantity']); ?></td>
                                        <td><?php echo formatMoney((float) $item['unit_price']); ?></td>
                                        <td><?php echo formatMoney((float) $item['discount_amount']); ?></td>
                                        <td><?php echo formatMoney((float) $item['line_total']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($sale['status'] === 'completed' && hasPermission('manager')): ?>
                        <div class="text-block">
                            <strong>Devolucao parcial</strong>
                            <form action="index.php?page=sales&action=returnItem" method="POST" data-validate>
                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                <input type="hidden" name="sale_id" value="<?php echo (int) $sale['id']; ?>">
                                <div class="form-grid-3col">
                                    <div class="form-group">
                                        <label for="item_id" class="form-label required">Item</label>
                                        <select id="item_id" name="item_id" class="form-control" required>
                                            <?php foreach ($saleItems as $item): ?>
                                                <option value="<?php echo (int) $item['id']; ?>">
                                                    <?php echo htmlspecialchars($item['product_name']); ?> - <?php echo formatQuantity($item['quantity']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="return_quantity" class="form-label required">Quantidade</label>
                                        <input type="number" id="return_quantity" name="quantity" class="form-control" min="0.001" step="0.001" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="refund_method" class="form-label required">Destino</label>
                                        <select id="refund_method" name="refund_method" class="form-control" required>
                                            <option value="cash_refund">Estorno no caixa</option>
                                            <option value="customer_credit">Credito para o cliente</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-grid-2col">
                                    <div class="form-group">
                                        <label for="return_reason" class="form-label required">Motivo</label>
                                        <input type="text" id="return_reason" name="reason" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="return_password" class="form-label required">Confirme sua senha</label>
                                        <input type="password" id="return_password" name="reauth_password" class="form-control" autocomplete="current-password" required>
                                    </div>
                                </div>
                                <div class="form-actions form-actions-left">
                                    <button type="submit" class="btn btn-warning" data-confirm="Registrar devolucao parcial desta venda?">Registrar devolucao</button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Pagamentos</h3></div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="table">
                            <thead><tr><th>Forma</th><th>Valor</th><th>Parcelas</th><th>Troco</th></tr></thead>
                            <tbody>
                                <?php foreach ($salePayments as $payment): ?>
                                    <tr>
                                        <td><?php echo PAYMENT_METHODS[$payment['payment_method']] ?? htmlspecialchars($payment['payment_method']); ?></td>
                                        <td><?php echo formatMoney((float) $payment['amount']); ?></td>
                                        <td><?php echo (int) $payment['installments']; ?></td>
                                        <td><?php echo formatMoney((float) $payment['change_amount']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once APP_PATH . '/views/templates/footer.php'; ?>
