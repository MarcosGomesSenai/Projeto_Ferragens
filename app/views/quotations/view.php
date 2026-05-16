<?php
$pageTitle = 'Orcamento ' . $quotation['quotation_number'];
require_once APP_PATH . '/views/templates/header.php';
?>
<div class="app-container">
    <?php require_once APP_PATH . '/views/templates/navigation.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left"><h1 class="topbar-title">Orcamento <?php echo htmlspecialchars($quotation['quotation_number']); ?></h1></div>
            <div class="topbar-right">
                <a href="index.php?page=quotations&action=pdf&id=<?php echo (int) $quotation['id']; ?>" class="btn btn-primary">PDF</a>
                <a href="index.php?page=quotations" class="btn btn-secondary">Voltar</a>
            </div>
        </header>
        <div class="content-area">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Resumo</h3></div>
                <div class="card-body">
                    <div class="detail-grid">
                        <div><strong>Cliente</strong><span><?php echo htmlspecialchars($quotation['customer_name'] ?: ($quotation['customer_name_db'] ?? '-')); ?></span></div>
                        <div><strong>Criado por</strong><span><?php echo htmlspecialchars($quotation['user_name'] ?? '-'); ?></span></div>
                        <div><strong>Validade</strong><span><?php echo formatDate($quotation['valid_until'], 'd/m/Y'); ?></span></div>
                        <div><strong>Status</strong><span><?php echo htmlspecialchars($quotation['status']); ?></span></div>
                        <div><strong>Subtotal</strong><span><?php echo formatMoney((float) $quotation['subtotal']); ?></span></div>
                        <div><strong>Desconto</strong><span><?php echo formatMoney((float) $quotation['discount_amount']); ?></span></div>
                        <div><strong>Total</strong><span><?php echo formatMoney((float) $quotation['total_amount']); ?></span></div>
                    </div>
                    <div class="alert alert-info" style="margin-top: var(--space-4);">Este documento nao possui valor fiscal.</div>
                    <?php if (!empty($quotation['notes'])): ?>
                        <div class="text-block">
                            <strong>Observacoes</strong>
                            <p><?php echo nl2br(htmlspecialchars($quotation['notes'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php $quotationExpired = strtotime((string) $quotation['valid_until']) < strtotime(date('Y-m-d')); ?>
                    <?php if ($quotation['status'] !== 'converted'): ?>
                        <div class="text-block">
                            <strong>Acoes</strong>
                            <?php if ($quotation['status'] === 'expired' || $quotationExpired || $quotation['status'] === 'rejected'): ?>
                                <form action="index.php?page=quotations&action=reopen" method="POST" class="form-actions form-actions-left">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                    <input type="hidden" name="id" value="<?php echo (int) $quotation['id']; ?>">
                                    <button type="submit" class="btn btn-primary">Reabrir Orcamento</button>
                                </form>
                            <?php else: ?>
                                <form action="index.php?page=quotations&action=status" method="POST" class="form-actions form-actions-left">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                    <input type="hidden" name="id" value="<?php echo (int) $quotation['id']; ?>">
                                    <?php if ($quotation['status'] === 'draft'): ?>
                                        <button type="submit" name="status" value="sent" class="btn btn-secondary">Marcar como Enviado</button>
                                    <?php endif; ?>
                                    <?php if (in_array($quotation['status'], ['draft', 'sent'], true)): ?>
                                        <button type="submit" name="status" value="approved" class="btn btn-success">Aprovar</button>
                                        <button type="submit" name="status" value="rejected" class="btn btn-danger">Recusar</button>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        </div>

                        <?php if ($quotation['status'] === 'approved' && !$quotationExpired): ?>
                            <div class="text-block">
                                <strong>Converter em venda</strong>
                                <form action="index.php?page=quotations&action=convert" method="POST" data-validate>
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                    <input type="hidden" name="id" value="<?php echo (int) $quotation['id']; ?>">
                                    <div class="form-grid-3col">
                                        <div class="form-group">
                                            <label for="payment_method" class="form-label required">Forma</label>
                                            <select id="payment_method" name="payment_method" class="form-control" required>
                                                <?php foreach (PAYMENT_METHODS as $key => $label): ?>
                                                    <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="payment_amount" class="form-label required">Valor recebido</label>
                                            <input type="number" id="payment_amount" name="payment_amount" class="form-control" min="0.01" step="0.01" value="<?php echo htmlspecialchars((string) $quotation['total_amount']); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="installments" class="form-label required">Parcelas</label>
                                            <input type="number" id="installments" name="installments" class="form-control" min="1" max="12" step="1" value="1" required>
                                        </div>
                                    </div>
                                    <div class="form-grid-2col">
                                        <div class="form-group">
                                            <label for="approval_email" class="form-label">Email autorizador</label>
                                            <input type="email" id="approval_email" name="approval_email" class="form-control" autocomplete="username">
                                        </div>
                                        <div class="form-group">
                                            <label for="approval_password" class="form-label">Senha autorizador</label>
                                            <input type="password" id="approval_password" name="approval_password" class="form-control" autocomplete="current-password">
                                        </div>
                                    </div>
                                    <label class="checkbox-row">
                                        <input type="checkbox" name="confirm_below_cost" value="1">
                                        <span>Confirmar conversao abaixo do custo quando autorizada</span>
                                    </label>
                                    <label class="checkbox-row">
                                        <input type="checkbox" name="confirm_negative_stock" value="1">
                                        <span>Confirmar conversao sem estoque quando autorizada</span>
                                    </label>
                                    <div class="form-actions form-actions-left">
                                        <button type="submit" class="btn btn-success" data-confirm="Converter este orcamento em venda e baixar o estoque?">Converter</button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                    <?php elseif (!empty($quotation['sale_id'])): ?>
                        <div class="text-block">
                            <a href="index.php?page=sales&action=view&id=<?php echo (int) $quotation['sale_id']; ?>" class="btn btn-secondary">Ver venda vinculada</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Itens</h3></div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="table">
                            <thead><tr><th>Produto</th><th>Qtd.</th><th>Preco</th><th>Total</th></tr></thead>
                            <tbody>
                                <?php foreach ($quotationItems as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['product_name']); ?><small class="muted-line"><?php echo htmlspecialchars($item['sku_snapshot']); ?></small></td>
                                        <td><?php echo formatQuantity($item['quantity']); ?></td>
                                        <td><?php echo formatMoney((float) $item['unit_price']); ?></td>
                                        <td><?php echo formatMoney((float) $item['line_total']); ?></td>
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
