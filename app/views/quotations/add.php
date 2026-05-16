<?php
$pageTitle = 'Novo Orcamento';
require_once APP_PATH . '/views/templates/header.php';
?>
<div class="app-container">
    <?php require_once APP_PATH . '/views/templates/navigation.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left"><h1 class="topbar-title">Novo Orcamento</h1></div>
            <div class="topbar-right"><a href="index.php?page=quotations" class="btn btn-secondary">Voltar</a></div>
        </header>
        <div class="content-area">
            <form action="index.php?page=quotations&action=save" method="POST" data-validate id="quotation-form">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="items_json" id="quotation_items_json" value="[]">

                <div class="pos-layout">
                    <section class="card">
                        <div class="card-header"><h3 class="card-title">Itens do orcamento</h3></div>
                        <div class="card-body">
                            <div class="form-grid-2col">
                                <div class="form-group">
                                    <label for="customer_id" class="form-label">Cliente cadastrado</label>
                                    <select id="quotation_customer_id" name="customer_id" class="form-control">
                                        <?php foreach ($customers as $customer): ?>
                                            <option value="<?php echo (int) $customer['id']; ?>" data-type="<?php echo htmlspecialchars($customer['customer_type']); ?>">
                                                <?php echo htmlspecialchars($customer['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="customer_name" class="form-label">Nome livre</label>
                                    <input type="text" id="customer_name" name="customer_name" class="form-control" placeholder="Use para cliente nao cadastrado">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="quotation_product_search" class="form-label">Adicionar produto</label>
                                <input type="text" id="quotation_product_search" class="form-control" placeholder="Nome ou SKU">
                                <div id="quotation_search_results" class="pos-search-results"></div>
                            </div>
                            <div class="table-container">
                                <table class="table" id="quotation-table">
                                    <thead><tr><th>Produto</th><th>Qtd.</th><th>Preco</th><th>Total</th><th></th></tr></thead>
                                    <tbody><tr><td colspan="5" class="text-center">Nenhum item adicionado.</td></tr></tbody>
                                </table>
                            </div>
                            <div class="form-group">
                                <label for="notes" class="form-label">Observacoes</label>
                                <textarea id="notes" name="notes" class="form-control" rows="2"></textarea>
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
                                <span>Confirmar orcamento abaixo do custo quando autorizado</span>
                            </label>
                        </div>
                    </section>
                    <aside class="card">
                        <div class="card-header"><h3 class="card-title">Totais</h3></div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="overall_discount" class="form-label">Desconto total</label>
                                <input type="number" id="quotation_overall_discount" name="overall_discount" class="form-control" min="0" step="0.01" value="0.00">
                            </div>
                            <div class="detail-grid">
                                <div><strong>Subtotal</strong><span id="quotation_subtotal"><?php echo formatMoney(0); ?></span></div>
                                <div><strong>Total</strong><span id="quotation_total"><?php echo formatMoney(0); ?></span></div>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn btn-success">Salvar Orcamento</button>
                            </div>
                        </div>
                    </aside>
                </div>
            </form>
        </div>
    </main>
</div>

<script nonce="<?php echo $cspNonce ?? ''; ?>">
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('quotation_product_search');
    const resultsBox = document.getElementById('quotation_search_results');
    const tableBody = document.querySelector('#quotation-table tbody');
    const itemsInput = document.getElementById('quotation_items_json');
    const customerSelect = document.getElementById('quotation_customer_id');
    const discountInput = document.getElementById('quotation_overall_discount');
    const subtotalLabel = document.getElementById('quotation_subtotal');
    const totalLabel = document.getElementById('quotation_total');
    let items = [];
    let timer = null;

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

    function renderItems() {
        if (!items.length) {
            tableBody.innerHTML = '<tr><td colspan="5" class="text-center">Nenhum item adicionado.</td></tr>';
        } else {
            tableBody.innerHTML = items.map((item, index) => `
                <tr>
                    <td><strong>${escapeHtml(item.name)}</strong><small class="muted-line">${escapeHtml(item.sku)}</small></td>
                    <td><input type="number" min="0.001" step="0.001" class="form-control" data-index="${index}" data-field="quantity" value="${item.quantity}"></td>
                    <td><input type="number" min="0" step="0.01" class="form-control" data-index="${index}" data-field="unit_price" value="${item.unit_price.toFixed(2)}" readonly></td>
                    <td>${formatMoney(item.quantity * item.unit_price)}</td>
                    <td><button type="button" class="btn btn-sm btn-danger" data-remove="${index}">Remover</button></td>
                </tr>
            `).join('');
        }
        itemsInput.value = JSON.stringify(items);
        refreshTotals();
    }

    function refreshTotals() {
        const subtotal = items.reduce((sum, item) => sum + (item.quantity * item.unit_price), 0);
        const total = Math.max(0, subtotal - (parseFloat(discountInput.value) || 0));
        subtotalLabel.textContent = formatMoney(subtotal);
        totalLabel.textContent = formatMoney(total);
        itemsInput.value = JSON.stringify(items);
    }

    function updateItemRowTotal(index, row) {
        if (!row || !items[index]) return;
        if (row.cells[3]) {
            row.cells[3].textContent = formatMoney(items[index].quantity * items[index].unit_price);
        }
    }

    function addItem(product) {
        const retail = parseFloat(product.sale_price);
        const wholesale = product.wholesale_price ? parseFloat(product.wholesale_price) : retail;
        const price = customerType() === 'professional' ? wholesale : retail;
        const existing = items.find(item => item.product_id === Number(product.id));
        if (existing) {
            existing.quantity += 1;
        } else {
            items.push({
                product_id: Number(product.id),
                sku: product.sku,
                name: product.name,
                quantity: 1,
                unit_price: price,
                retail_price: retail,
                wholesale_price: wholesale
            });
        }
        renderItems();
        searchInput.value = '';
        resultsBox.innerHTML = '';
    }

    searchInput.addEventListener('input', function () {
        clearTimeout(timer);
        const query = searchInput.value.trim();
        if (query.length < 2) {
            resultsBox.innerHTML = '';
            return;
        }
        timer = setTimeout(async function () {
            const response = await fetch('index.php?page=products&action=search&q=' + encodeURIComponent(query));
            const products = await response.json();
            resultsBox.innerHTML = products.map(product => `
                <button type="button" class="pos-result" data-product="${encodeURIComponent(JSON.stringify(product))}">
                    <strong>${escapeHtml(product.name)}</strong>
                    <small class="muted-line">${escapeHtml(product.sku)} | ${formatMoney(customerType() === 'professional' && product.wholesale_price ? parseFloat(product.wholesale_price) : parseFloat(product.sale_price))}</small>
                </button>
            `).join('');
        }, 250);
    });

    resultsBox.addEventListener('click', function (event) {
        const button = event.target.closest('.pos-result');
        if (!button) return;
        const product = JSON.parse(decodeURIComponent(button.dataset.product));
        addItem(product);
    });

    tableBody.addEventListener('input', function (event) {
        const index = Number(event.target.dataset.index);
        const field = event.target.dataset.field;
        if (Number.isNaN(index) || !items[index]) return;
        items[index][field] = parseFloat(event.target.value) || 0;
        updateItemRowTotal(index, event.target.closest('tr'));
        refreshTotals();
    });

    tableBody.addEventListener('click', function (event) {
        const index = Number(event.target.dataset.remove);
        if (Number.isNaN(index)) return;
        items.splice(index, 1);
        renderItems();
    });

    customerSelect.addEventListener('change', function () {
        const professional = customerType() === 'professional';
        items = items.map(item => ({ ...item, unit_price: professional ? item.wholesale_price : item.retail_price }));
        renderItems();
    });

    discountInput.addEventListener('input', refreshTotals);
    document.getElementById('quotation-form').addEventListener('submit', function () {
        itemsInput.value = JSON.stringify(items);
    });
    renderItems();
});
</script>

<?php require_once APP_PATH . '/views/templates/footer.php'; ?>
