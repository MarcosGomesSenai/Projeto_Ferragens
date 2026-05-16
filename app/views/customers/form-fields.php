<?php
$customer = $customer ?? [];
$value = static function (string $key, $default = '') use ($customer) {
    return htmlspecialchars((string) ($customer[$key] ?? $default), ENT_QUOTES, 'UTF-8');
};
$selected = static function ($actual, $expected): string {
    return (string) $actual === (string) $expected ? 'selected' : '';
};
?>

<div class="form-grid-3col">
    <div class="form-group">
        <label for="document_type" class="form-label required">Tipo de documento</label>
        <select id="document_type" name="document_type" class="form-control" required>
            <option value="cpf" <?php echo $selected($customer['document_type'] ?? 'cpf', 'cpf'); ?>>CPF</option>
            <option value="cnpj" <?php echo $selected($customer['document_type'] ?? '', 'cnpj'); ?>>CNPJ</option>
            <option value="none" <?php echo $selected($customer['document_type'] ?? '', 'none'); ?>>Nao informado</option>
        </select>
    </div>
    <div class="form-group">
        <label for="document" class="form-label">Documento</label>
        <input type="text" id="document" name="document" class="form-control" value="<?php echo $value('document'); ?>">
    </div>
    <div class="form-group">
        <label for="customer_type" class="form-label required">Tipo de cliente</label>
        <select id="customer_type" name="customer_type" class="form-control" required>
            <?php foreach (CUSTOMER_TYPES as $key => $label): ?>
                <option value="<?php echo $key; ?>" <?php echo $selected($customer['customer_type'] ?? 'retail', $key); ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<div class="form-group">
    <label for="name" class="form-label required">Nome</label>
    <input type="text" id="name" name="name" class="form-control" value="<?php echo $value('name'); ?>" required>
</div>

<div class="form-grid-2col">
    <div class="form-group">
        <label for="phone" class="form-label">Telefone</label>
        <input type="text" id="phone" name="phone" class="form-control" value="<?php echo $value('phone'); ?>" data-mask="phone">
    </div>
    <div class="form-group">
        <label for="email" class="form-label">Email</label>
        <input type="email" id="email" name="email" class="form-control" value="<?php echo $value('email'); ?>">
    </div>
</div>

<div class="form-group">
    <label for="address" class="form-label">Endereco</label>
    <input type="text" id="address" name="address" class="form-control" value="<?php echo $value('address'); ?>">
</div>

<div class="form-grid-3col">
    <div class="form-group">
        <label for="city" class="form-label">Cidade</label>
        <input type="text" id="city" name="city" class="form-control" value="<?php echo $value('city'); ?>">
    </div>
    <div class="form-group">
        <label for="state" class="form-label">UF</label>
        <input type="text" id="state" name="state" class="form-control" value="<?php echo $value('state', 'SP'); ?>" maxlength="2">
    </div>
    <div class="form-group">
        <label for="status" class="form-label required">Status</label>
        <select id="status" name="status" class="form-control" required>
            <option value="active" <?php echo $selected($customer['status'] ?? 'active', 'active'); ?>>Ativo</option>
            <option value="inactive" <?php echo $selected($customer['status'] ?? '', 'inactive'); ?>>Inativo</option>
        </select>
    </div>
</div>

<div class="form-grid-2col">
    <div class="form-group">
        <label class="form-check">
            <input type="checkbox" name="credit_enabled" class="form-check-input" <?php echo !empty($customer['credit_enabled']) ? 'checked' : ''; ?>>
            <span class="form-check-label">Permitir crediario</span>
        </label>
    </div>
    <div class="form-group">
        <label for="credit_limit" class="form-label">Limite de credito</label>
        <input type="number" id="credit_limit" name="credit_limit" class="form-control" value="<?php echo $value('credit_limit', '0.00'); ?>" min="0" step="0.01">
    </div>
</div>
