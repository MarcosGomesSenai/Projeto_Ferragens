<?php
$pageTitle = 'Nova Venda';
require_once APP_PATH . '/views/templates/header.php';
?>

<div class="app-container">
    <?php require_once APP_PATH . '/views/templates/navigation.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left"><h1 class="topbar-title">Nova Venda</h1></div>
            <div class="topbar-right">
                <?php if (hasPermission('manager')): ?>
                    <a href="index.php?page=sales" class="btn btn-secondary">Historico</a>
                <?php endif; ?>
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

                <div class="pos-layout pos-terminal-layout">
                    <section class="pos-panel pos-sale-panel">
                        <div class="pos-panel-header">
                            <h3>Carrinho</h3>
                        </div>
                        <div class="pos-panel-body">
                            <div class="form-group pos-search-box pos-search-primary">
                                <label for="product_search" class="form-label">Adicionar produto</label>
                                <input type="text" id="product_search" class="form-control" placeholder="Bipar codigo de barras ou buscar por nome" autocomplete="off" autofocus>
                                <div id="search_results" class="pos-search-results"></div>
                            </div>

                            <div id="cart_items" class="sale-items-list">
                                <div class="sale-empty-state">Nenhum item adicionado.</div>
                            </div>

                            <div class="pos-control-bar pos-sale-options">
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

                            <details class="pos-extra-details">
                                <summary>Observacoes</summary>
                                <div class="form-group pos-notes-box">
                                    <label for="notes" class="form-label">Observacoes da venda</label>
                                    <textarea id="notes" name="notes" class="form-control" rows="2"></textarea>
                                </div>
                            </details>
                        </div>
                    </section>

                    <aside class="pos-panel pos-checkout-panel">
                        <div class="pos-panel-header">
                            <h3>Pagamento</h3>
                        </div>
                        <div class="pos-panel-body">
                            <div class="pos-total-box">
                                <span>Total da venda</span>
                                <strong id="total_value"><?php echo formatMoney(0); ?></strong>
                            </div>

                            <div class="pos-mini-totals">
                                <div><span>Subtotal</span><strong id="subtotal_value"><?php echo formatMoney(0); ?></strong></div>
                                <div><span>Desconto</span><strong id="discount_value"><?php echo formatMoney(0); ?></strong></div>
                            </div>

                            <div class="payment-shortcuts" id="payment_shortcuts">
                                <button type="button" class="btn btn-secondary btn-sm" data-pay-method="cash">Dinheiro</button>
                                <button type="button" class="btn btn-secondary btn-sm" data-pay-method="pix">Pix</button>
                                <button type="button" class="btn btn-secondary btn-sm" data-pay-method="debit_card">Debito</button>
                                <button type="button" class="btn btn-secondary btn-sm" data-pay-method="credit_card">Credito</button>
                            </div>
                            <div id="payment_rows"></div>
                            <div class="form-actions form-actions-left">
                                <button type="button" class="btn btn-outline btn-sm" id="add_payment_btn">Dividir</button>
                            </div>

                            <div class="pos-mini-totals pos-payment-result">
                                <div><span>Pago</span><strong id="paid_value"><?php echo formatMoney(0); ?></strong></div>
                                <div><span>Troco</span><strong id="change_value"><?php echo formatMoney(0); ?></strong></div>
                            </div>

                            <div class="pos-final-actions">
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

<script nonce="<?php echo htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '', ENT_QUOTES, 'UTF-8'); ?>">
const currentUserId = <?php echo (int) ($_SESSION['user_id'] ?? 0); ?>;

document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('product_search');
    const resultsBox = document.getElementById('search_results');
    const cartItemsBox = document.getElementById('cart_items');
    const customerSelect = document.getElementById('customer_id');
    const discountInput = document.getElementById('overall_discount');
    const itemsInput = document.getElementById('items_json');
    const paymentsInput = document.getElementById('payments_json');
    const paymentRows = document.getElementById('payment_rows');
    const paymentShortcuts = document.getElementById('payment_shortcuts');
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
    let autoFillPayment = true;

    function formatMoney(value) {
        return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value || 0);
    }

    function moneyValue(value) {
        return (parseFloat(value) || 0).toFixed(2);
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

    function normalizeBarcode(value) {
        return String(value ?? '').replace(/\D/g, '');
    }

    function productBarcode(product) {
        return product.barcode || product.sku || '';
    }

    function cartSubtotal() {
        return cart.reduce((sum, item) => sum + (item.unit_price * item.quantity), 0);
    }

    function cartItemDiscount() {
        return cart.reduce((sum, item) => sum + ((item.unit_price * item.quantity) * (item.discount_percent / 100)), 0);
    }

    function saleTotal() {
        const overallDiscount = parseFloat(discountInput.value) || 0;
        return Math.max(0, cartSubtotal() - cartItemDiscount() - overallDiscount);
    }

    function ensurePrimaryPayment() {
        if (!payments.length) {
            payments.push({ payment_method: 'cash', amount: moneyValue(saleTotal()), installments: 1 });
        }
    }

    function fillPaymentWithTotal(index = 0) {
        ensurePrimaryPayment();
        if (!payments[index]) return;
        payments[index].amount = moneyValue(saleTotal());
        renderPayments();
    }

    function setPrimaryPayment(method) {
        ensurePrimaryPayment();
        payments[0].payment_method = method;
        payments[0].amount = moneyValue(saleTotal());
        if (method !== 'credit_card' && method !== 'store_credit') {
            payments[0].installments = 1;
        }
        renderPayments();
    }

    function refreshPaymentButtons() {
        const method = payments[0]?.payment_method || 'cash';
        paymentShortcuts.querySelectorAll('[data-pay-method]').forEach(button => {
            button.classList.toggle('is-active', button.dataset.payMethod === method);
        });
    }

    function paymentMethodOptions(selected) {
        return `
            <option value="cash" ${selected === 'cash' ? 'selected' : ''}>Dinheiro</option>
            <option value="debit_card" ${selected === 'debit_card' ? 'selected' : ''}>Debito</option>
            <option value="credit_card" ${selected === 'credit_card' ? 'selected' : ''}>Credito</option>
            <option value="pix" ${selected === 'pix' ? 'selected' : ''}>Pix</option>
            <option value="store_credit" ${selected === 'store_credit' ? 'selected' : ''}>Crediario</option>
            <option value="customer_credit" ${selected === 'customer_credit' ? 'selected' : ''}>Credito do Cliente</option>
        `;
    }

    function refreshCart() {
        if (!cart.length) {
            cartItemsBox.innerHTML = '<div class="sale-empty-state">Nenhum item adicionado.</div>';
        } else {
            cartItemsBox.innerHTML = cart.map((item, index) => {
                const lineTotal = (item.unit_price * item.quantity) * (1 - (item.discount_percent / 100));
                return `
                    <div class="sale-item" data-index="${index}">
                        <div class="sale-item-main">
                            <div class="sale-item-product">
                                <strong>${escapeHtml(item.name)}</strong>
                                <small class="muted-line">${escapeHtml(item.barcode)} | estoque ${escapeHtml(item.quantity_available)}</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-danger sale-remove-btn" data-remove="${index}" title="Remover item">X</button>
                        </div>
                        <div class="sale-item-fields">
                            <label class="sale-field">
                                <span>Quantidade</span>
                                <div class="quantity-stepper">
                                    <button type="button" class="qty-step-btn" data-qty-step="-1" data-index="${index}">-</button>
                                    <input type="number" min="0.001" step="0.001" value="${item.quantity}" data-index="${index}" data-field="quantity" class="form-control sale-number-input">
                                    <button type="button" class="qty-step-btn" data-qty-step="1" data-index="${index}">+</button>
                                </div>
                            </label>
                            <label class="sale-field">
                                <span>Preco</span>
                                <input type="number" min="0" step="0.01" value="${item.unit_price.toFixed(2)}" data-index="${index}" data-field="unit_price" class="form-control sale-number-input" readonly>
                            </label>
                            <div class="sale-field sale-line-total">
                                <span>Total</span>
                                <strong data-line-total="${index}">${formatMoney(lineTotal)}</strong>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }
        itemsInput.value = JSON.stringify(cart);
        refreshTotals();
    }

    function refreshTotals() {
        const subtotal = cartSubtotal();
        const itemDiscount = cartItemDiscount();
        const overallDiscount = parseFloat(discountInput.value) || 0;
        const total = saleTotal();
        if (autoFillPayment && payments.length === 1) {
            payments[0].amount = moneyValue(total);
            const amountInput = paymentRows.querySelector('[data-payment-field="amount"][data-index="0"]');
            if (amountInput && document.activeElement !== amountInput) {
                amountInput.value = payments[0].amount;
            }
        }
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

    function updateCartItemTotal(index) {
        if (!cart[index]) return;
        const lineTotal = (cart[index].unit_price * cart[index].quantity) * (1 - (cart[index].discount_percent / 100));
        const totalNode = cartItemsBox.querySelector(`[data-line-total="${index}"]`);
        if (totalNode) {
            totalNode.textContent = formatMoney(lineTotal);
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
                barcode: productBarcode(product),
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

    function showSearchMessage(message, type = 'info') {
        resultsBox.innerHTML = `<div class="pos-search-message pos-search-message-${type}">${escapeHtml(message)}</div>`;
    }

    async function fetchProducts(query, render = true) {
        try {
            const response = await fetch('index.php?page=products&action=search&q=' + encodeURIComponent(query), {
                headers: { 'Accept': 'application/json' }
            });
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            const products = await response.json();
            const list = Array.isArray(products) ? products : [];
            if (render) {
                renderResults(list);
            }
            return list;
        } catch (error) {
            showSearchMessage('Nao foi possivel buscar produtos. Recarregue a pagina e tente novamente.', 'error');
            return [];
        }
    }

    function findDirectProduct(products, query) {
        const normalizedQuery = normalizeBarcode(query);
        if (normalizedQuery !== '') {
            const exactBarcode = products.find(product => normalizeBarcode(productBarcode(product)) === normalizedQuery);
            if (exactBarcode) {
                return exactBarcode;
            }
        }
        return products.length === 1 ? products[0] : null;
    }

    function renderResults(products) {
        if (!products.length) {
            showSearchMessage('Nenhum produto encontrado para esta busca.', 'empty');
            return;
        }
        resultsBox.innerHTML = products.map(product => {
            const price = customerType() === 'professional' && product.wholesale_price ? product.wholesale_price : product.sale_price;
            return `
                <button type="button" class="pos-result" data-product="${encodeURIComponent(JSON.stringify(product))}">
                    <strong>${escapeHtml(product.name)}</strong>
                    <small class="muted-line">${escapeHtml(productBarcode(product))} | estoque ${escapeHtml(product.quantity)} | ${formatMoney(parseFloat(price))}</small>
                </button>
            `;
        }).join('');
    }

    function addPaymentRow(payment = { payment_method: 'cash', amount: 0, installments: 1 }) {
        payments.push(payment);
        if (payments.length > 1) {
            autoFillPayment = false;
        }
        renderPayments();
    }

    function renderPayments() {
        paymentRows.innerHTML = payments.map((payment, index) => {
            const canInstallments = payment.payment_method === 'credit_card' || payment.payment_method === 'store_credit';
            const canRemove = payments.length > 1;
            const methodControl = canRemove ? `
                <div class="form-group">
                    <label class="form-label">Forma</label>
                    <select class="form-control" data-payment-field="payment_method" data-index="${index}">
                        ${paymentMethodOptions(payment.payment_method)}
                    </select>
                </div>
            ` : '';
            return `
            <div class="payment-row simple-payment-row ${canRemove ? 'is-split' : 'is-single'}" data-payment-index="${index}">
                ${methodControl}
                <div class="form-group">
                    <label class="form-label">Valor</label>
                    <div class="payment-amount-row">
                        <input type="number" min="0" step="0.01" class="form-control" data-payment-field="amount" data-index="${index}" value="${moneyValue(payment.amount)}">
                        <button type="button" class="btn btn-sm btn-outline" data-fill-payment="${index}">Total</button>
                    </div>
                </div>
                <div class="form-group ${canInstallments ? '' : 'hidden'}">
                    <label class="form-label">Parcelas</label>
                    <div class="table-actions">
                        <input type="number" min="1" max="12" step="1" class="form-control" data-payment-field="installments" data-index="${index}" value="${payment.installments}">
                        ${canRemove ? `<button type="button" class="btn btn-sm btn-danger" data-remove-payment="${index}">Remover</button>` : ''}
                    </div>
                </div>
                <div class="form-group ${canInstallments || !canRemove ? 'hidden' : ''}">
                    <label class="form-label">&nbsp;</label>
                    <button type="button" class="btn btn-sm btn-danger payment-remove-btn" data-remove-payment="${index}">Remover</button>
                </div>
            </div>
        `;
        }).join('');
        refreshTotals();
        refreshPaymentButtons();
    }

    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimer);
        const query = searchInput.value.trim();
        searchTimer = setTimeout(async function () {
            await fetchProducts(query);
        }, 250);
    });

    searchInput.addEventListener('keydown', async function (event) {
        if (event.key !== 'Enter') {
            return;
        }
        event.preventDefault();
        clearTimeout(searchTimer);
        const query = searchInput.value.trim();
        if (query === '') {
            return;
        }
        const products = await fetchProducts(query);
        const product = findDirectProduct(products, query);
        if (product) {
            addToCart(product);
        }
    });

    searchInput.addEventListener('focus', function () {
        if (searchInput.value.trim() === '' && resultsBox.innerHTML.trim() === '') {
            fetchProducts('');
        }
    });

    resultsBox.addEventListener('click', function (event) {
        const button = event.target.closest('.pos-result');
        if (!button) return;
        const product = JSON.parse(decodeURIComponent(button.dataset.product));
        addToCart(product);
    });

    cartItemsBox.addEventListener('input', function (event) {
        const field = event.target.dataset.field;
        const index = Number(event.target.dataset.index);
        if (field === undefined || Number.isNaN(index) || !cart[index]) return;
        cart[index][field] = parseFloat(event.target.value) || 0;
        updateCartItemTotal(index);
        refreshTotals();
    });

    cartItemsBox.addEventListener('click', function (event) {
        const removeIndex = Number(event.target.dataset.remove);
        if (!Number.isNaN(removeIndex)) {
            cart.splice(removeIndex, 1);
            refreshCart();
            return;
        }

        const step = Number(event.target.dataset.qtyStep);
        const index = Number(event.target.dataset.index);
        if (Number.isNaN(step) || Number.isNaN(index) || !cart[index]) return;
        cart[index].quantity = Math.max(0.001, (parseFloat(cart[index].quantity) || 0) + step);
        refreshCart();
    });

    paymentRows.addEventListener('input', function (event) {
        const field = event.target.dataset.paymentField;
        const index = Number(event.target.dataset.index);
        if (field === undefined || Number.isNaN(index) || !payments[index]) return;
        payments[index][field] = field === 'payment_method' ? event.target.value : (parseFloat(event.target.value) || 0);
        if (field === 'amount') {
            autoFillPayment = false;
        }
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
        if (field === 'amount') {
            autoFillPayment = false;
        }
        renderPayments();
    });

    paymentRows.addEventListener('click', function (event) {
        const fillIndex = Number(event.target.dataset.fillPayment);
        if (!Number.isNaN(fillIndex)) {
            autoFillPayment = payments.length === 1;
            fillPaymentWithTotal(fillIndex);
            return;
        }
        const index = Number(event.target.dataset.removePayment);
        if (Number.isNaN(index)) return;
        payments.splice(index, 1);
        ensurePrimaryPayment();
        renderPayments();
    });

    paymentShortcuts.addEventListener('click', function (event) {
        const method = event.target.dataset.payMethod;
        if (!method) return;
        autoFillPayment = true;
        setPrimaryPayment(method);
    });

    customerSelect.addEventListener('change', function () {
        cart = cart.map(item => ({
            ...item,
            unit_price: item.wholesale_price && customerType() === 'professional' ? item.wholesale_price : item.retail_price
        }));
        refreshCart();
    });

    discountInput.addEventListener('input', refreshTotals);
    addPaymentBtn.addEventListener('click', function () {
        autoFillPayment = false;
        addPaymentRow();
    });
    suspendSaleBtn.addEventListener('click', function () {
        sessionStorage.setItem('suspended_sale_user_' + currentUserId, JSON.stringify({
            cart,
            payments,
            customer_id: customerSelect.value,
            overall_discount: discountInput.value
        }));
        cart = [];
        payments = [];
        autoFillPayment = true;
        discountInput.value = '0.00';
        addPaymentRow({ payment_method: 'cash', amount: moneyValue(saleTotal()), installments: 1 });
        refreshCart();
    });
    resumeSaleBtn.addEventListener('click', function () {
        const stored = sessionStorage.getItem('suspended_sale_user_' + currentUserId);
        if (!stored) return;
        const sale = JSON.parse(stored);
        cart = Array.isArray(sale.cart) ? sale.cart : [];
        payments = Array.isArray(sale.payments) && sale.payments.length ? sale.payments : [{ payment_method: 'cash', amount: 0, installments: 1 }];
        autoFillPayment = payments.length === 1;
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
    fetchProducts('');
});
</script>

<?php require_once APP_PATH . '/views/templates/footer.php'; ?>
