<?php
$pageTitle = 'Nova Entrada NF';
require_once APP_PATH . '/views/templates/header.php';
?>
<div class="app-container">
    <?php require_once APP_PATH . '/views/templates/navigation.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left"><h1 class="topbar-title">Nova Entrada NF</h1></div>
            <div class="topbar-right"><a href="index.php?page=fiscal" class="btn btn-secondary">Voltar</a></div>
        </header>
        <div class="content-area">
            <form action="index.php?page=fiscal&action=save" method="POST" data-validate id="fiscal-form">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="items_json" id="fiscal_items_json" value="[]">

                <div class="card">
                    <div class="card-header"><h3 class="card-title">Dados da nota</h3></div>
                    <div class="card-body">
                        <div class="form-grid-3col">
                            <div class="form-group">
                                <label for="supplier_id" class="form-label required">Fornecedor</label>
                                <select id="supplier_id" name="supplier_id" class="form-control" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                        <option value="<?php echo (int) $supplier['id']; ?>" data-terms="<?php echo htmlspecialchars($supplier['default_payment_terms'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($supplier['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text">Fornecedor que emitiu a nota de entrada.</small>
                            </div>
                            <div class="form-group">
                                <label for="invoice_number" class="form-label required">Numero NF</label>
                                <input type="text" id="invoice_number" name="invoice_number" class="form-control" required>
                                <small class="form-text">Numero da nota fiscal do fornecedor.</small>
                            </div>
                            <div class="form-group">
                                <label for="invoice_series" class="form-label required">Serie</label>
                                <input type="text" id="invoice_series" name="invoice_series" class="form-control" value="1" required>
                                <small class="form-text">Normalmente e 1, salvo quando a NF informar outra serie.</small>
                            </div>
                        </div>
                        <div class="form-grid-3col">
                            <div class="form-group">
                                <label for="issue_date" class="form-label required">Data de emissao</label>
                                <input type="date" id="issue_date" name="issue_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                <small class="form-text">Data emitida na nota fiscal.</small>
                            </div>
                            <div class="form-group">
                                <label for="access_key" class="form-label">Chave de acesso</label>
                                <input type="text" id="access_key" name="access_key" class="form-control" maxlength="44">
                                <small class="form-text">Opcional. Use a chave de 44 digitos quando tiver a NF-e.</small>
                            </div>
                            <div class="form-group">
                                <label for="payment_terms" class="form-label">Prazo de pagamento</label>
                                <input type="text" id="payment_terms" name="payment_terms" class="form-control" placeholder="Ex.: 30/60">
                                <small class="form-text">Use 0 para a vista ou 30/60 para parcelas futuras.</small>
                            </div>
                        </div>
                        <div class="form-grid-2col">
                            <div class="form-group">
                                <label for="cst" class="form-label">CST</label>
                                <input type="text" id="cst" name="cst" class="form-control">
                                <small class="form-text">Opcional. Codigo tributario informado na nota.</small>
                            </div>
                            <div class="form-group">
                                <label for="icms_base" class="form-label">Base ICMS</label>
                                <input type="number" id="icms_base" name="icms_base" class="form-control" min="0" step="0.01">
                                <small class="form-text">Opcional. Valor da base de ICMS da nota.</small>
                            </div>
                        </div>
                        <label class="checkbox-row">
                            <input type="checkbox" name="update_costs" value="1">
                            <span>Atualizar o custo cadastrado dos produtos quando o custo da NF for diferente</span>
                        </label>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h3 class="card-title">Itens</h3></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="fiscal_product_search" class="form-label">Adicionar produto</label>
                            <input type="text" id="fiscal_product_search" class="form-control" placeholder="Nome ou codigo de barras" autocomplete="off">
                            <small class="form-text">Busque o produto cadastrado para somar a entrada ao estoque.</small>
                            <div id="fiscal_search_results" class="pos-search-results"></div>
                        </div>
                        <div class="table-container">
                            <table class="table commerce-table" id="fiscal-table">
                                <thead><tr><th>Produto</th><th>Qtd. compra</th><th>Custo unit.</th><th>Total</th><th></th></tr></thead>
                                <tbody><tr><td colspan="5" class="text-center">Nenhum item adicionado.</td></tr></tbody>
                            </table>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-success">Registrar entrada</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </main>
</div>

<script nonce="<?php echo htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '', ENT_QUOTES, 'UTF-8'); ?>">
document.addEventListener('DOMContentLoaded', function () {
    const supplierSelect = document.getElementById('supplier_id');
    const paymentTerms = document.getElementById('payment_terms');
    const searchInput = document.getElementById('fiscal_product_search');
    const resultsBox = document.getElementById('fiscal_search_results');
    const tableBody = document.querySelector('#fiscal-table tbody');
    const itemsInput = document.getElementById('fiscal_items_json');
    let items = [];
    let timer = null;

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, char => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char]));
    }

    function formatMoney(value) {
        return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value || 0);
    }

    function renderItems() {
        if (!items.length) {
            tableBody.innerHTML = '<tr><td colspan="5" class="text-center">Nenhum item adicionado.</td></tr>';
        } else {
            tableBody.innerHTML = items.map((item, index) => `
                <tr>
                    <td data-label="Produto" class="item-product-cell"><strong>${escapeHtml(item.name)}</strong><small class="muted-line">${escapeHtml(item.barcode)}</small></td>
                    <td data-label="Qtd. compra" class="item-quantity-cell"><input type="number" min="0.001" step="0.001" class="form-control" data-index="${index}" data-field="quantity" value="${item.quantity}"></td>
                    <td data-label="Custo unit." class="item-money-cell"><input type="number" min="0" step="0.01" class="form-control" data-index="${index}" data-field="unit_cost" value="${item.unit_cost.toFixed(2)}"></td>
                    <td data-label="Total" class="item-total-cell">${formatMoney(item.quantity * item.unit_cost)}</td>
                    <td data-label="Acao" class="item-action-cell"><button type="button" class="btn btn-sm btn-danger" data-remove="${index}">Remover</button></td>
                </tr>
            `).join('');
        }
        itemsInput.value = JSON.stringify(items);
    }

    supplierSelect.addEventListener('change', function () {
        const terms = supplierSelect.options[supplierSelect.selectedIndex]?.dataset.terms || '';
        if (!paymentTerms.value) paymentTerms.value = terms;
    });

    function showSearchMessage(message, type = 'info') {
        resultsBox.innerHTML = `<div class="pos-search-message pos-search-message-${type}">${escapeHtml(message)}</div>`;
    }

    async function fetchProducts(query) {
        try {
            const response = await fetch('index.php?page=products&action=search&context=purchase&q=' + encodeURIComponent(query), {
                headers: { 'Accept': 'application/json' }
            });
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            const products = await response.json();
            renderResults(Array.isArray(products) ? products : []);
        } catch (error) {
            showSearchMessage('Nao foi possivel buscar produtos. Recarregue a pagina e tente novamente.', 'error');
        }
    }

    function renderResults(products) {
        if (!products.length) {
            showSearchMessage('Nenhum produto encontrado para esta busca.', 'empty');
            return;
        }
        resultsBox.innerHTML = products.map(product => `
            <button type="button" class="pos-result" data-product="${encodeURIComponent(JSON.stringify(product))}">
                <strong>${escapeHtml(product.name)}</strong>
                <small class="muted-line">${escapeHtml(product.barcode)} | estoque ${escapeHtml(product.quantity)}</small>
            </button>
        `).join('');
    }

    searchInput.addEventListener('input', function () {
        clearTimeout(timer);
        const query = searchInput.value.trim();
        timer = setTimeout(async function () {
            await fetchProducts(query);
        }, 250);
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
        if (!items.find(item => item.product_id === Number(product.id))) {
            items.push({ product_id: Number(product.id), barcode: product.barcode, name: product.name, quantity: 1, unit_cost: 0 });
        }
        searchInput.value = '';
        resultsBox.innerHTML = '';
        renderItems();
    });

    tableBody.addEventListener('input', function (event) {
        // M-07: Atualiza apenas a célula de total da linha — sem re-render destrutivo
        const index = Number(event.target.dataset.index);
        const field = event.target.dataset.field;
        if (Number.isNaN(index) || !items[index]) return;
        items[index][field] = parseFloat(event.target.value) || 0;
        itemsInput.value = JSON.stringify(items);

        // Atualiza somente a célula de total da linha (3ª <td>)
        const row = event.target.closest('tr');
        if (row) {
            const totalCell = row.cells[3];
            if (totalCell) {
                totalCell.textContent = formatMoney(items[index].quantity * items[index].unit_cost);
            }
        }
    });

    tableBody.addEventListener('click', function (event) {
        const index = Number(event.target.dataset.remove);
        if (Number.isNaN(index)) return;
        items.splice(index, 1);
        renderItems();
    });

    document.getElementById('fiscal-form').addEventListener('submit', function () {
        itemsInput.value = JSON.stringify(items);
    });
    fetchProducts('');
});
</script>

<?php require_once APP_PATH . '/views/templates/footer.php'; ?>
