<?php
$supplier = $supplier ?? [];
$value = static function (string $key, $default = '') use ($supplier) {
    return htmlspecialchars((string) ($supplier[$key] ?? $default), ENT_QUOTES, 'UTF-8');
};
$selected = static function ($actual, $expected): string {
    return (string) $actual === (string) $expected ? 'selected' : '';
};
?>

<div class="form-grid-2col">
    <div class="form-group">
        <label for="legal_name" class="form-label required">Razao social</label>
        <input type="text" id="legal_name" name="legal_name" class="form-control" value="<?php echo $value('legal_name'); ?>" required>
        <small class="form-text">Nome juridico do fornecedor, conforme cadastro/CNPJ.</small>
    </div>
    <div class="form-group">
        <label for="name" class="form-label required">Nome fantasia</label>
        <input type="text" id="name" name="name" class="form-control" value="<?php echo $value('name'); ?>" required>
        <small class="form-text">Nome curto que aparecera em produtos, compras e financeiro.</small>
    </div>
</div>

<div class="form-grid-3col">
    <div class="form-group">
        <label for="cnpj" class="form-label required">CNPJ</label>
        <input type="text" id="cnpj" name="cnpj" class="form-control" value="<?php echo $value('cnpj'); ?>" data-mask="cnpj" required>
        <small class="form-text">CNPJ valido do fornecedor.</small>
    </div>
    <div class="form-group">
        <label for="segment" class="form-label required">Segmento</label>
        <input type="text" id="segment" name="segment" class="form-control" value="<?php echo $value('segment'); ?>" placeholder="Ferragens, tintas, eletrica..." required>
        <small class="form-text">Ramo principal do fornecedor para facilitar filtros e compras.</small>
    </div>
    <div class="form-group">
        <label for="status" class="form-label required">Status</label>
        <select id="status" name="status" class="form-control" required>
            <option value="active" <?php echo $selected($supplier['status'] ?? 'active', 'active'); ?>>Ativo</option>
            <option value="inactive" <?php echo $selected($supplier['status'] ?? '', 'inactive'); ?>>Inativo</option>
        </select>
        <small class="form-text">Inativo fica fora de novas compras, mas preserva historico.</small>
    </div>
</div>

<div class="form-section-title">Contato</div>
<div class="form-grid-3col">
    <div class="form-group">
        <label for="email" class="form-label">Email</label>
        <input type="email" id="email" name="email" class="form-control" value="<?php echo $value('email'); ?>">
        <small class="form-text">Opcional. Email comercial ou financeiro.</small>
    </div>
    <div class="form-group">
        <label for="phone" class="form-label">Telefone</label>
        <input type="text" id="phone" name="phone" class="form-control" value="<?php echo $value('phone'); ?>" data-mask="phone">
        <small class="form-text">Opcional. Telefone principal do fornecedor.</small>
    </div>
    <div class="form-group">
        <label for="commercial_contact_phone" class="form-label">Celular representante</label>
        <input type="text" id="commercial_contact_phone" name="commercial_contact_phone" class="form-control" value="<?php echo $value('commercial_contact_phone'); ?>" data-mask="phone">
        <small class="form-text">Opcional. Contato direto do vendedor/representante.</small>
    </div>
</div>

<div class="form-group">
    <label for="commercial_contact_name" class="form-label">Contato comercial</label>
    <input type="text" id="commercial_contact_name" name="commercial_contact_name" class="form-control" value="<?php echo $value('commercial_contact_name'); ?>">
    <small class="form-text">Opcional. Nome da pessoa que atende pedidos.</small>
</div>

<div class="form-section-title">Endereco</div>
<div class="form-group">
    <label for="address" class="form-label">Endereco</label>
    <input type="text" id="address" name="address" class="form-control" value="<?php echo $value('address'); ?>">
    <small class="form-text">Opcional. Endereco comercial do fornecedor.</small>
</div>

<div class="form-grid-2col">
    <div class="form-group">
        <label for="city" class="form-label">Cidade</label>
        <input type="text" id="city" name="city" class="form-control" value="<?php echo $value('city'); ?>">
        <small class="form-text">Cidade do fornecedor.</small>
    </div>
    <div class="form-group">
        <label for="state" class="form-label">UF</label>
        <input type="text" id="state" name="state" class="form-control" value="<?php echo $value('state', 'SP'); ?>" maxlength="2">
        <small class="form-text">Sigla do estado, como SP.</small>
    </div>
</div>

<div class="form-section-title">Condicoes comerciais</div>
<div class="form-grid-3col">
    <div class="form-group">
        <label for="default_payment_terms" class="form-label">Prazo padrao</label>
        <input type="text" id="default_payment_terms" name="default_payment_terms" class="form-control" value="<?php echo $value('default_payment_terms'); ?>" placeholder="30/60">
        <small class="form-text">Sugestao automatica na entrada fiscal. Ex.: 0, 30 ou 30/60.</small>
    </div>
    <div class="form-group">
        <label for="credit_limit" class="form-label">Limite de credito</label>
        <input type="number" id="credit_limit" name="credit_limit" class="form-control" value="<?php echo $value('credit_limit'); ?>" min="0" step="0.01">
        <small class="form-text">Opcional. Limite comercial concedido pelo fornecedor.</small>
    </div>
    <div class="form-group">
        <label for="commercial_terms" class="form-label">Condicao comercial</label>
        <input type="text" id="commercial_terms" name="commercial_terms" class="form-control" value="<?php echo $value('commercial_terms'); ?>">
        <small class="form-text">Opcional. Ex.: pedido minimo, desconto, entrega ou observacoes.</small>
    </div>
</div>
