<?php
$_navPage = $_GET['page'] ?? 'dashboard';
$_navAction = $_GET['action'] ?? '';

function navActive(string $page, string $action = ''): string {
    global $_navPage, $_navAction;
    $pageMatch = $_navPage === $page;
    $actionMatch = $action === '' || $_navAction === $action;
    return 'sidebar-nav-link' . ($pageMatch && $actionMatch ? ' active' : '');
}
?>

<button type="button" class="sidebar-toggle-btn" id="hamburger" aria-label="Abrir menu" aria-expanded="false" aria-controls="sidebar">&#9776;</button>
<div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <button type="button" class="sidebar-close-btn" id="sidebar-close" aria-label="Fechar menu">&times;</button>
        <div class="sidebar-logo">Ferragens Souza</div>
        <p class="sidebar-subtitle">Gestao de loja</p>
    </div>

    <nav>
        <ul class="sidebar-nav">
            <?php if (hasPermission('operator')): ?>
                <li class="sidebar-nav-item">
                    <a href="index.php?page=dashboard" class="<?php echo navActive('dashboard'); ?>">
                        <span class="sidebar-nav-icon">DB</span><span>Dashboard</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php if (hasPermission('seller')): ?>
            <li class="sidebar-nav-item">
                <a href="index.php?page=pos" class="<?php echo navActive('pos'); ?>">
                    <span class="sidebar-nav-icon">VD</span><span>Nova Venda</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="index.php?page=quotations" class="<?php echo navActive('quotations'); ?>">
                    <span class="sidebar-nav-icon">OR</span><span>Orcamentos</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (hasPermission('operator')): ?>
            <li class="sidebar-nav-item">
                <a href="index.php?page=products" class="<?php echo navActive('products'); ?>">
                    <span class="sidebar-nav-icon">PR</span><span>Produtos</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="index.php?page=customers" class="<?php echo navActive('customers'); ?>">
                    <span class="sidebar-nav-icon">CL</span><span>Clientes</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="index.php?page=suppliers" class="<?php echo navActive('suppliers'); ?>">
                    <span class="sidebar-nav-icon">FO</span><span>Fornecedores</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="index.php?page=stock&action=movements" class="<?php echo navActive('stock', 'movements'); ?>">
                    <span class="sidebar-nav-icon">ES</span><span>Movimentacoes</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="index.php?page=stock&action=inventory" class="<?php echo navActive('stock', 'inventory'); ?>">
                    <span class="sidebar-nav-icon">IV</span><span>Inventario</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="index.php?page=stock&action=lowStock" class="<?php echo navActive('stock', 'lowStock'); ?>">
                    <span class="sidebar-nav-icon">RP</span><span>Reposicao</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="index.php?page=categories" class="<?php echo navActive('categories'); ?>">
                    <span class="sidebar-nav-icon">CA</span><span>Categorias</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="index.php?page=fiscal" class="<?php echo navActive('fiscal'); ?>">
                    <span class="sidebar-nav-icon">NF</span><span>Fiscal</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (hasPermission('manager')): ?>
            <li class="sidebar-nav-item sidebar-nav-separator">
                <a href="index.php?page=cash" class="<?php echo navActive('cash'); ?>">
                    <span class="sidebar-nav-icon">CX</span><span>Caixa</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="index.php?page=sales" class="<?php echo navActive('sales'); ?>">
                    <span class="sidebar-nav-icon">HV</span><span>Historico de Vendas</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="index.php?page=financial" class="<?php echo navActive('financial'); ?>">
                    <span class="sidebar-nav-icon">FI</span><span>Financeiro</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="index.php?page=reports" class="<?php echo navActive('reports'); ?>">
                    <span class="sidebar-nav-icon">RL</span><span>Relatorios</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (hasPermission('admin')): ?>
            <li class="sidebar-nav-item sidebar-nav-separator">
                <a href="index.php?page=users" class="<?php echo navActive('users'); ?>">
                    <span class="sidebar-nav-icon">US</span><span>Usuarios</span>
                </a>
            </li>
            <?php endif; ?>

            <li class="sidebar-nav-item sidebar-nav-separator">
                <a href="index.php?page=logout&action=logout" class="sidebar-nav-link" data-confirm="Deseja sair do sistema?">
                    <span class="sidebar-nav-icon">SA</span><span>Sair</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>
