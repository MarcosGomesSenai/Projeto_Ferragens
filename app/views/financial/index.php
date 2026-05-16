<?php
$pageTitle = 'Financeiro';
require_once APP_PATH . '/views/templates/header.php';
?>
<div class="app-container">
    <?php require_once APP_PATH . '/views/templates/navigation.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left"><h1 class="topbar-title">Financeiro</h1></div>
        </header>
        <div class="content-area">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Contas a pagar</h3></div>
                <div class="card-body">
                    <?php if (empty($payables)): ?>
                        <div class="alert alert-info">Nenhuma conta a pagar registrada.</div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="table">
                                <thead><tr><th>Fornecedor</th><th>Descricao</th><th>Vencimento</th><th>Valor</th><th>Pago</th><th>Status</th><th>Baixa</th></tr></thead>
                                <tbody>
                                    <?php foreach ($payables as $payable): ?>
                                        <?php $isOverdue = in_array($payable['status'], ['open', 'partial'], true) && strtotime($payable['due_date']) < strtotime(date('Y-m-d')); ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($payable['supplier_name']); ?></td>
                                            <td><?php echo htmlspecialchars($payable['description']); ?></td>
                                            <td style="color: <?php echo $isOverdue ? 'var(--error)' : 'inherit'; ?>;"><?php echo formatDate($payable['due_date'], 'd/m/Y'); ?></td>
                                            <td><?php echo formatMoney((float) $payable['amount']); ?></td>
                                            <td><?php echo formatMoney((float) $payable['paid_amount']); ?></td>
                                            <td><span class="badge <?php echo $payable['status'] === 'paid' ? 'badge-success' : ($isOverdue ? 'badge-error' : 'badge-warning'); ?>"><?php echo htmlspecialchars($payable['status']); ?></span></td>
                                            <td>
                                                <?php if ($payable['status'] !== 'paid'): ?>
                                                    <form action="index.php?page=financial&action=payPayable" method="POST" class="table-actions">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                        <input type="hidden" name="id" value="<?php echo (int) $payable['id']; ?>">
                                                        <input type="number" name="paid_amount" min="0.01" step="0.01" class="form-control" value="<?php echo max(0, (float) $payable['amount'] - (float) $payable['paid_amount']); ?>">
                                                        <select name="payment_method" class="form-control">
                                                            <option value="pix">Pix</option>
                                                            <option value="transfer">Transferencia</option>
                                                            <option value="boleto">Boleto</option>
                                                            <option value="check">Cheque</option>
                                                            <option value="cash">Dinheiro</option>
                                                        </select>
                                                        <button type="submit" class="btn btn-sm btn-success">Baixar</button>
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

            <div class="card">
                <div class="card-header"><h3 class="card-title">Contas a receber</h3></div>
                <div class="card-body">
                    <?php if (empty($receivables)): ?>
                        <div class="alert alert-info">Nenhuma conta a receber registrada.</div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="table">
                                <thead><tr><th>Cliente</th><th>Descricao</th><th>Parcela</th><th>Vencimento</th><th>Valor</th><th>Recebido</th><th>Status</th><th>Origem</th><th>Baixa</th></tr></thead>
                                <tbody>
                                    <?php foreach ($receivables as $receivable): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($receivable['customer_name'] ?? 'Consumidor Final'); ?></td>
                                            <td><?php echo htmlspecialchars($receivable['description']); ?></td>
                                            <td><?php echo (int) $receivable['installment_no']; ?>/<?php echo (int) $receivable['installments']; ?></td>
                                            <td><?php echo formatDate($receivable['due_date'], 'd/m/Y'); ?></td>
                                            <td><?php echo formatMoney((float) $receivable['amount']); ?></td>
                                            <td><?php echo formatMoney((float) $receivable['received_amount']); ?></td>
                                            <td><span class="badge <?php echo $receivable['status'] === 'paid' ? 'badge-success' : 'badge-warning'; ?>"><?php echo htmlspecialchars($receivable['status']); ?></span></td>
                                            <td><?php echo $receivable['source'] === 'credit_card' ? 'Cartao' : 'Crediario'; ?></td>
                                            <td>
                                                <?php if ($receivable['source'] === 'store_credit' && $receivable['status'] !== 'paid'): ?>
                                                    <form action="index.php?page=financial&action=receiveReceivable" method="POST" class="table-actions">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                        <input type="hidden" name="id" value="<?php echo (int) $receivable['id']; ?>">
                                                        <input type="number" name="received_amount" min="0.01" step="0.01" class="form-control" value="<?php echo max(0, (float) $receivable['amount'] - (float) $receivable['received_amount']); ?>">
                                                        <select name="payment_method" class="form-control">
                                                            <option value="pix">Pix</option>
                                                            <option value="transfer">Transferencia</option>
                                                            <option value="cash">Dinheiro</option>
                                                        </select>
                                                        <button type="submit" class="btn btn-sm btn-success">Baixar</button>
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
