<?php
$product = $product ?? [];
$isEdit = $isEdit ?? false;
$hasMovements = $hasMovements ?? false;

$value = static function (string $key, $default = '') use ($product) {
    return htmlspecialchars((string) ($product[$key] ?? $default), ENT_QUOTES, 'UTF-8');
};
$selected = static function ($actual, $expected): string {
    return (string) $actual === (string) $expected ? 'selected' : '';
};

$parentCategories = array_filter($categories, static fn($cat) => empty($cat['parent_id']));
$subcategories = array_filter($categories, static fn($cat) => !empty($cat['parent_id']));
?>

<div class="form-section-title">Identificacao</div>
<div class="form-grid-2col">
    <div class="form-group">
        <label for="sku" class="form-label">SKU</label>
        <input type="text" id="sku" name="sku" class="form-control"
               value="<?php echo $value('sku'); ?>"
               placeholder="Gerado automaticamente"
               <?php echo $isEdit && $hasMovements ? 'readonly' : ''; ?>>
        <small class="form-text">Apos movimentar estoque, o SKU fica travado.</small>
    </div>

    <div class="form-group">
        <label for="brand" class="form-label">Marca</label>
        <input type="text" id="brand" name="brand" class="form-control" value="<?php echo $value('brand'); ?>">
    </div>
</div>

<div class="form-group">
    <label for="name" class="form-label required">Nome do Produto</label>
    <input type="text" id="name" name="name" class="form-control" value="<?php echo $value('name'); ?>" required>
</div>

<div class="form-grid-2col">
    <div class="form-group">
        <label for="category_id" class="form-label required">Categoria</label>
        <select id="category_id" name="category_id" class="form-control" required>
            <option value="">Selecione...</option>
            <?php foreach ($parentCategories as $cat): ?>
                <option value="<?php echo (int) $cat['id']; ?>" <?php echo $selected($product['category_id'] ?? '', $cat['id']); ?>>
                    <?php echo htmlspecialchars($cat['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="subcategory_id" class="form-label">Subcategoria</label>
        <select id="subcategory_id" name="subcategory_id" class="form-control">
            <option value="">Sem subcategoria</option>
            <?php foreach ($subcategories as $cat): ?>
                <option value="<?php echo (int) $cat['id']; ?>" <?php echo $selected($product['subcategory_id'] ?? '', $cat['id']); ?>>
                    <?php echo htmlspecialchars($cat['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<div class="form-grid-2col">
    <div class="form-group">
        <label for="supplier_id" class="form-label">Fornecedor principal</label>
        <select id="supplier_id" name="supplier_id" class="form-control">
            <option value="">Sem fornecedor</option>
            <?php foreach ($suppliers as $supplier): ?>
                <option value="<?php echo (int) $supplier['id']; ?>" <?php echo $selected($product['supplier_id'] ?? '', $supplier['id']); ?>>
                    <?php echo htmlspecialchars($supplier['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="alternate_supplier_id" class="form-label">Fornecedor alternativo</label>
        <select id="alternate_supplier_id" name="alternate_supplier_id" class="form-control">
            <option value="">Sem fornecedor alternativo</option>
            <?php foreach ($suppliers as $supplier): ?>
                <option value="<?php echo (int) $supplier['id']; ?>" <?php echo $selected($product['alternate_supplier_id'] ?? '', $supplier['id']); ?>>
                    <?php echo htmlspecialchars($supplier['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<div class="form-section-title">Unidades e Estoque</div>
<div class="form-grid-3col">
    <div class="form-group">
        <label for="unit_of_measure" class="form-label required">Unidade de venda</label>
        <select id="unit_of_measure" name="unit_of_measure" class="form-control" required>
            <?php foreach (PRODUCT_UNITS as $key => $label): ?>
                <option value="<?php echo $key; ?>" <?php echo $selected($product['unit_of_measure'] ?? 'UN', $key); ?>>
                    <?php echo $label; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="purchase_unit" class="form-label required">Unidade de compra</label>
        <select id="purchase_unit" name="purchase_unit" class="form-control" required>
            <?php foreach (PRODUCT_UNITS as $key => $label): ?>
                <option value="<?php echo $key; ?>" <?php echo $selected($product['purchase_unit'] ?? 'UN', $key); ?>>
                    <?php echo $label; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="conversion_factor" class="form-label required">Fator de conversao</label>
        <input type="number" id="conversion_factor" name="conversion_factor" class="form-control"
               value="<?php echo $value('conversion_factor', '1'); ?>" min="0.0001" step="0.0001" required>
        <small class="form-text">Ex.: caixa com 100 unidades = 100.</small>
    </div>
</div>

<div class="form-grid-3col">
    <?php if (!$isEdit): ?>
    <div class="form-group">
        <label for="initial_quantity" class="form-label">Estoque inicial</label>
        <input type="number" id="initial_quantity" name="initial_quantity" class="form-control" value="0" min="0" step="0.001">
    </div>
    <?php else: ?>
    <div class="form-group">
        <label class="form-label">Estoque atual</label>
        <input type="text" class="form-control" value="<?php echo formatQuantity($product['quantity'] ?? 0); ?>" readonly>
    </div>
    <?php endif; ?>

    <div class="form-group">
        <label for="min_quantity" class="form-label required">Estoque minimo</label>
        <input type="number" id="min_quantity" name="min_quantity" class="form-control"
               value="<?php echo $value('min_quantity', LOW_STOCK_THRESHOLD); ?>" min="0" step="0.001" required>
    </div>

    <div class="form-group">
        <label for="reorder_point" class="form-label">Ponto de reposicao</label>
        <input type="number" id="reorder_point" name="reorder_point" class="form-control"
               value="<?php echo $value('reorder_point', '0'); ?>" min="0" step="0.001">
    </div>
</div>

<div class="form-section-title">Precos</div>
<div class="form-grid-4col">
    <div class="form-group">
        <label for="cost_price" class="form-label required">Preco de custo</label>
        <input type="number" id="cost_price" name="cost_price" class="form-control"
               value="<?php echo $value('cost_price'); ?>" min="0" step="0.01" required>
    </div>

    <div class="form-group">
        <label for="desired_markup_percent" class="form-label">Markup desejado (%)</label>
        <input type="number" id="desired_markup_percent" name="desired_markup_percent" class="form-control"
               value="<?php echo $value('markup_percent'); ?>" min="0" step="0.01">
        <small class="form-text">Digite o markup para calcular o preco de venda.</small>
    </div>

    <div class="form-group">
        <label for="sale_price" class="form-label required">Preco varejo</label>
        <input type="number" id="sale_price" name="sale_price" class="form-control"
               value="<?php echo $value('sale_price'); ?>" min="0" step="0.01" required>
        <small class="form-text">Margem: <span id="margin_display">0%</span> | Markup: <span id="markup_display">0%</span></small>
    </div>

    <div class="form-group">
        <label for="wholesale_price" class="form-label">Preco atacado</label>
        <input type="number" id="wholesale_price" name="wholesale_price" class="form-control"
               value="<?php echo $value('wholesale_price'); ?>" min="0" step="0.01">
    </div>
</div>

<div class="form-section-title">Status e observacoes</div>
<div class="form-grid-2col">
    <div class="form-group">
        <label for="status" class="form-label required">Status</label>
        <select id="status" name="status" class="form-control" required>
            <?php foreach (PRODUCT_STATUS as $key => $label): ?>
                <option value="<?php echo $key; ?>" <?php echo $selected($product['status'] ?? 'active', $key); ?>>
                    <?php echo $label; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<div class="form-group">
    <label for="description" class="form-label">Descricao</label>
    <textarea id="description" name="description" class="form-control" rows="3"><?php echo $value('description'); ?></textarea>
</div>

<div class="form-group">
    <label for="notes" class="form-label">Observacoes internas</label>
    <textarea id="notes" name="notes" class="form-control" rows="3"><?php echo $value('notes'); ?></textarea>
</div>

<script nonce="<?php echo $cspNonce ?? ''; ?>">
document.addEventListener('DOMContentLoaded', function () {
    const cost = document.getElementById('cost_price');
    const sale = document.getElementById('sale_price');
    const desiredMarkup = document.getElementById('desired_markup_percent');
    const margin = document.getElementById('margin_display');
    const markup = document.getElementById('markup_display');

    function numberValue(field) {
        return parseFloat(String(field?.value || '0').replace(',', '.')) || 0;
    }

    function roundMoney(value) {
        return Math.round((value + Number.EPSILON) * 100) / 100;
    }

    function updateDisplays() {
        const c = numberValue(cost);
        const s = numberValue(sale);
        const marginValue = s > 0 ? ((s - c) / s) * 100 : 0;
        const markupValue = c > 0 ? ((s - c) / c) * 100 : 0;
        margin.textContent = marginValue.toFixed(2) + '%';
        markup.textContent = markupValue.toFixed(2) + '%';
        const color = s <= c ? 'var(--error)' : 'var(--success)';
        margin.style.color = color;
        markup.style.color = color;
        if (desiredMarkup && document.activeElement !== desiredMarkup) {
            desiredMarkup.value = markupValue > 0 ? markupValue.toFixed(2) : '';
        }
    }

    function applyMarkup() {
        const c = numberValue(cost);
        const m = numberValue(desiredMarkup);
        if (c > 0 && m >= 0 && sale) {
            sale.value = roundMoney(c * (1 + (m / 100))).toFixed(2);
        }
        updateDisplays();
    }

    if (cost && sale && margin && markup) {
        cost.addEventListener('input', function () {
            if (desiredMarkup && desiredMarkup.value !== '') {
                applyMarkup();
                return;
            }
            updateDisplays();
        });
        sale.addEventListener('input', updateDisplays);
        if (desiredMarkup) {
            desiredMarkup.addEventListener('input', applyMarkup);
            desiredMarkup.addEventListener('change', applyMarkup);
        }
        updateDisplays();
    }
});
</script>
