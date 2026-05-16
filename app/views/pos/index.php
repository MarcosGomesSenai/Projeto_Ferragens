<?php
$pageTitle = 'PDV';
require_once APP_PATH . '/views/templates/header.php';
?>

<div class="app-container">
    <?php require_once APP_PATH . '/views/templates/navigation.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left"><h1 class="topbar-title">PDV</h1></div>
            <div class="topbar-right">
                <?php if ($cashRegister): ?>
                    <span class="badge badge-success">Caixa aberto</span>
                <?php elseif (hasPermission('seller')): ?>
                    <a href="index.php?page=cash&action=open" class="btn btn-warning">Abrir Caixa</a>
                <?php else: ?>
                    <span class="badge badge-warning">Caixa fechado</span>
                <?php endif; ?>
            </div>
        </header>

        <div class="content-area">
            <?php if (!$cashRegister): ?>
                <div class="alert alert-warning"><?php echo hasPermission('seller') ? 'Abra o caixa antes de registrar vendas.' : 'Solicite a abertura do caixa antes de registrar vendas.'; ?></div>
            <?php endif; ?>

            <form action="index.php?page=pos&action=checkout" method="POST" data-validate id="pos-form">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="items_json" id="items_json" value="[]">
                <input type="hidden" name="payments_json" id="payments_json" value="[]">

                <div class="pos-layout">
                    <section class="card">
                        <div class="card-header"><h3 class="card-title">Carrinho</h3></div>
                        <div class="card-body">
                            <div class="form-grid-2col">
                                <div class="form-group">
                                    <label for="customer_id" class="form-label">Cliente</label>
                                    <select id="customer_id" name="customer_id" class="form-control">
                                        <?php foreach ($customers as $customer): ?>
                                            <option value="<?php echo (int) $customer['id']; ?>" data-type="<?php echo htmlspecialchars($customer['customer_type']); ?>" data-credit="<?php echo (int) $customer['credit_enabled']; ?>" data-limit="<?php echo htmlspecialchars((string) $customer['credit_limit']); ?>">
                                                <?php echo htmlspecialchars($customer['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="overall_discount" class="form-label">Desconto total</label>
                                    <input type="number" id="overall_discount" name="overall_discount" class="form-control" min="0" step="0.01" value="0.00">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="product_search" class="form-label">Adicionar produto</label>
                                <input type="text" id="product_search" class="form-control" placeholder="Nome ou SKU" autocomplete="off">
                                <div id="search_results" class="pos-search-results"></div>
                            </div>

                            <div class="table-container">
                                <table class="table" id="cart-table">
                                    <thead>
                                        <tr>
                                            <th>Produto</th>
                                            <th>Qtd.</th>
                                            <th>Preco</th>
                                            <th>Desc. %</th>
                                            <th>Total</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td colspan="6" class="text-center">Nenhum item adicionado.</td></tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="form-group">
                                <label for="notes" class="form-label">Observacoes</label>
                                <textarea id="notes" name="notes" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                    </section>

                    <aside class="card">
                        <div class="card-header"><h3 class="card-title">Fechamento</h3></div>
                        <div class="card-body">
                            <div class="detail-grid">
                                <div><strong>Subtotal</strong><span id="subtotal_value"><?php echo formatMoney(0); ?></span></div>
                                <div><strong>Descontos</strong><span id="discount_value"><?php echo formatMoney(0); ?></span></div>
                            </div>

                            <div class="text-block">
                                <div class="stat-label">Total da venda</div>
                                <div class="pos-total" id="total_value"><?php echo formatMoney(0); ?></div>
                            </div>

                            <div class="form-section-title">Pagamentos</div>
                            <div id="payment_rows"></div>
                            <div class="form-actions form-actions-left">
                                <button type="button" class="btn btn-secondary btn-sm" id="add_payment_btn">Adicionar pagamento</button>
                            </div>

                            <div class="form-section-title">Autorizacao de desconto</div>
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
                                <span>Confirmar venda abaixo do custo quando autorizada</span>
                            </label>
                            <label class="checkbox-row">
                                <input type="checkbox" name="confirm_negative_stock" value="1">
                                <span>Confirmar venda sem estoque quando autorizada</span>
                            </label>

                            <div class="detail-grid" style="margin-top: var(--space-4);">
                                <div><strong>Pago</strong><span id="paid_value"><?php echo formatMoney(0); ?></span></div>
                                <div><strong>Troco</strong><span id="change_value"><?php echo formatMoney(0); ?></span></div>
                            </div>

                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary" id="suspend_sale_btn">Suspender</button>
                                <button type="button" class="btn btn-outline" id="resume_sale_btn">Retomar</button>
                                <button type="submit" class="btn btn-success" <?php echo !$cashRegister ? 'disabled' : ''; ?>>Finalizar Venda</button>
                            </div>
                        </div>
                    </aside>
                </div>
            </form>
        </div>
    </main>
</div>

<script nonce="<?php echo $cspNonce ?? ''; ?>">
const currentUserId = <?php echo (int) ($_SESSION['user_id'] ?? 0); ?>;

document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('product_search');
    const resultsBox = document.getElementById('search_results');
    const cartTableBody = document.querySelector('#cart-table tbody');
    const customerSelect = document.getElementById('customer_id');
    const discountInput = document.getElementById('overall_discount');
    const itemsInput = document.getElementById('items_json');
    const paymentsInput = document.getElementById('payments_json');
    const paymentRows = document.getElementById('payment_rows');
    const addPaymentBtn = document.getElementById('add_payment_btn');
    const subtotalValue = document.getElementById('subtotal_value');
    const discountValue = document.getElementById('discount_value');
    const totalValue = document.getElementById('total_value');
    const paidValue = document.getElementById('paid_value');
    const changeValue = document.getElementById('change_value');
    const suspendSaleBtn = document.getElementById('suspend_sale_btn');
    const resumeSaleBtn = document.getElementById('resume_sale_btn');

    let cart = [];
    let payments = [];
    let searchTimer = null;

    function formatMoney(value) {
        return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value || 0);
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, char => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char]));
    }

    function customerType() {
        return customerSelect.options[customerSelect.selectedIndex]?.dataset.type || 'retail';
    }

    function refreshCart() {
        if (!cart.length) {
            cartTableBody.innerHTML = '<tr><td colspan="6" class="text-center">Nenhum item adicionado.</td></tr>';
        } else {
            cartTableBody.innerHTML = cart.map((item, index) => {
                const lineTotal = (item.unit_price * item.quantity) * (1 - (item.discount_percent / 100));
                return `
                    <tr>
                        <td>
                            <strong>${escapeHtml(item.name)}</strong>
                            <small class="muted-line">${escapeHtml(item.sku)} | estoque ${escapeHtml(item.quantity_available)}</small>
                        </td>
                        <td><input type="number" min="0.001" step="0.001" value="${item.quantity}" data-index="${index}" data-field="quantity" class="form-control"></td>
                        <td><input type="number" min="0" step="0.01" value="${item.unit_price.toFixed(2)}" data-index="${index}" data-field="unit_price" class="form-control" readonly></td>
                        <td><input type="number" min="0" step="0.01" value="${item.discount_percent.toFixed(2)}" data-index="${index}" data-field="discount_percent" class="form-control"></td>
                        <td>${formatMoney(lineTotal)}</td>
                        <td><button type="button" class="btn btn-sm btn-danger" data-remove="${index}">Remover</button></td>
                    </tr>
                `;
            }).join('');
        }
        itemsInput.value = JSON.stringify(cart);
        refreshTotals();
    }

    function refreshTotals() {
        const subtotal = cart.reduce((sum, item) => sum + (item.unit_price * item.quantity), 0);
        const itemDiscount = cart.reduce((sum, item) => sum + ((item.unit_price * item.quantity) * (item.discount_percent / 100)), 0);
        const overallDiscount = parseFloat(discountInput.value) || 0;
        const total = Math.max(0, subtotal - itemDiscount - overallDiscount);
        const paid = payments.reduce((sum, payment) => sum + (parseFloat(payment.amount) || 0), 0);
        const cashPaid = payments
            .filter(payment => payment.payment_method === 'cash')
            .reduce((sum, payment) => sum + (parseFloat(payment.amount) || 0), 0);
        const overpaid = Math.max(0, paid - total);
        const change = cashPaid > 0 && overpaid <= cashPaid ? overpaid : 0;
        subtotalValue.textContent = formatMoney(subtotal);
        discountValue.textContent = formatMoney(itemDiscount + overallDiscount);
        totalValue.textContent = formatMoney(total);
        paidValue.textContent = formatMoney(paid);
        changeValue.textContent = formatMoney(change);
        itemsInput.value = JSON.stringify(cart);
        paymentsInput.value = JSON.stringify(payments);
    }

    function updateCartRowTotal(index, row) {
        if (!row || !cart[index]) return;
        const lineTotal = (cart[index].unit_price * cart[index].quantity) * (1 - (cart[index].discount_percent / 100));
        if (row.cells[4]) {
            row.cells[4].textContent = formatMoney(lineTotal);
        }
    }

    function addToCart(product) {
        const retail = parseFloat(product.sale_price);
        const wholesale = product.wholesale_price ? parseFloat(product.wholesale_price) : retail;
        const price = customerType() === 'professional' ? wholesale : retail;
        const existing = cart.find(item => item.product_id === Number(product.id));
        if (existing) {
            existing.quantity += 1;
            existing.unit_price = customerType() === 'professional' ? existing.wholesale_price : existing.retail_price;
        } else {
            cart.push({
                product_id: Number(product.id),
                sku: product.sku,
                name: product.name,
                unit_price: price,
                retail_price: retail,
                wholesale_price: wholesale,
                quantity: 1,
                discount_percent: 0,
                quantity_available: parseFloat(product.quantity),
            });
        }
        refreshCart();
        searchInput.value = '';
        resultsBox.innerHTML = '';
    }

    function renderResults(products) {
        if (!products.length) {
            resultsBox.innerHTML = '';
            return;
        }
        resultsBox.innerHTML = products.map(product => {
            const price = customerType() === 'professional' && product.wholesale_price ? product.wholesale_price : product.sale_price;
            return `
                <button type="button" class="pos-result" data-product="${encodeURIComponent(JSON.stringify(product))}">
                    <strong>${escapeHtml(product.name)}</strong>
                    <small class="muted-line">${escapeHtml(product.sku)} | estoque ${escapeHtml(product.quantity)} | ${formatMoney(parseFloat(price))}</small>
                </button>
            `;
        }).join('');
    }

    function addPaymentRow(payment = { payment_method: 'cash', amount: 0, installments: 1 }) {
        payments.push(payment);
        renderPayments();
    }

    function renderPayments() {
        paymentRows.innerHTML = payments.map((payment, index) => `
            <div class="form-grid-3col" data-payment-index="${index}">
                <div class="form-group">
                    <label class="form-label">Forma</label>
                    <select class="form-control" data-payment-field="payment_method" data-index="${index}">
                        <option value="cash" ${payment.payment_method === 'cash' ? 'selected' : ''}>Dinheiro</option>
                        <option value="debit_card" ${payment.payment_method === 'debit_card' ? 'selected' : ''}>Debito</option>
                        <option value="credit_card" ${payment.payment_method === 'credit_card' ? 'selected' : ''}>Credito</option>
                        <option value="pix" ${payment.payment_method === 'pix' ? 'selected' : ''}>Pix</option>
                        <option value="store_credit" ${payment.payment_method === 'store_credit' ? 'selected' : ''}>Crediario</option>
                        <option value="customer_credit" ${payment.payment_method === 'customer_credit' ? 'selected' : ''}>Credito do Cliente</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Valor</label>
                    <input type="number" min="0" step="0.01" class="form-control" data-payment-field="amount" data-index="${index}" value="${payment.amount}">
                </div>
                <div class="form-group">
                    <label class="form-label">Parcelas</label>
                    <div class="table-actions">
                        <input type="number" min="1" max="12" step="1" class="form-control" data-payment-field="installments" data-index="${index}" value="${payment.installments}">
                        <button type="button" class="btn btn-sm btn-danger" data-remove-payment="${index}">Remover</button>
                    </div>
                </div>
            </div>
        `).join('');
        refreshTotals();
    }

    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimer);
        const query = searchInput.value.trim();
        if (query.length < 2) {
            resultsBox.innerHTML = '';
            return;
        }
        searchTimer = setTimeout(async function () {
            const response = await fetch('index.php?page=products&action=search&q=' + encodeURIComponent(query));
            const products = await response.json();
            renderResults(products);
        }, 250);
    });

    resultsBox.addEventListener('click', function (event) {
        const button = event.target.closest('.pos-result');
        if (!button) return;
        const product = JSON.parse(decodeURIComponent(button.dataset.product));
        addToCart(product);
    });

    cartTableBody.addEventListener('input', function (event) {
        const field = event.target.dataset.field;
        const index = Number(event.target.dataset.index);
        if (field === undefined || Number.isNaN(index) || !cart[index]) return;
        cart[index][field] = parseFloat(event.target.value) || 0;
        updateCartRowTotal(index, event.target.closest('tr'));
        refreshTotals();
    });

    cartTableBody.addEventListener('click', function (event) {
        const index = Number(event.target.dataset.remove);
        if (Number.isNaN(index)) return;
        cart.splice(index, 1);
        refreshCart();
    });

    paymentRows.addEventListener('input', function (event) {
        const field = event.target.dataset.paymentField;
        const index = Number(event.target.dataset.index);
        if (field === undefined || Number.isNaN(index) || !payments[index]) return;
        payments[index][field] = field === 'payment_method' ? event.target.value : (parseFloat(event.target.value) || 0);
        if (field === 'payment_method' && payments[index][field] !== 'credit_card' && payments[index][field] !== 'store_credit') {
            payments[index].installments = 1;
            renderPayments();
            return;
        }
        refreshTotals();
    });

    paymentRows.addEventListener('change', function (event) {
        const field = event.target.dataset.paymentField;
        const index = Number(event.target.dataset.index);
        if (field === undefined || Number.isNaN(index) || !payments[index]) return;
        payments[index][field] = field === 'payment_method' ? event.target.value : (parseFloat(event.target.value) || 0);
        renderPayments();
    });

    paymentRows.addEventListener('click', function (event) {
        const index = Number(event.target.dataset.removePayment);
        if (Number.isNaN(index)) return;
        payments.splice(index, 1);
        renderPayments();
    });

    customerSelect.addEventListener('change', function () {
        cart = cart.map(item => ({
            ...item,
            unit_price: item.wholesale_price && customerType() === 'professional' ? item.wholesale_price : item.retail_price
        }));
        refreshCart();
    });

    discountInput.addEventListener('input', refreshTotals);
    addPaymentBtn.addEventListener('click', function () { addPaymentRow(); });
    suspendSaleBtn.addEventListener('click', function () {
        sessionStorage.setItem('suspended_sale_user_' + currentUserId, JSON.stringify({
            cart,
            payments,
            customer_id: customerSelect.value,
            overall_discount: discountInput.value
        }));
        cart = [];
        payments = [];
        discountInput.value = '0.00';
        addPaymentRow({ payment_method: 'cash', amount: 0, installments: 1 });
        refreshCart();
    });
    resumeSaleBtn.addEventListener('click', function () {
        const stored = sessionStorage.getItem('suspended_sale_user_' + currentUserId);
        if (!stored) return;
        const sale = JSON.parse(stored);
        cart = Array.isArray(sale.cart) ? sale.cart : [];
        payments = Array.isArray(sale.payments) && sale.payments.length ? sale.payments : [{ payment_method: 'cash', amount: 0, installments: 1 }];
        customerSelect.value = sale.customer_id || customerSelect.value;
        discountInput.value = sale.overall_discount || '0.00';
        sessionStorage.removeItem('suspended_sale_user_' + currentUserId);
        renderPayments();
        refreshCart();
    });
    document.getElementById('pos-form').addEventListener('submit', function () {
        itemsInput.value = JSON.stringify(cart);
        paymentsInput.value = JSON.stringify(payments);
    });

    addPaymentRow({ payment_method: 'cash', amount: 0, installments: 1 });
    refreshCart();
});
</script>

<?php require_once APP_PATH . '/views/templates/footer.php'; ?>
