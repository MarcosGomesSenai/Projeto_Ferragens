<?php
$pageTitle = 'Gerenciar Usuarios';
require_once APP_PATH . '/views/templates/header.php';
?>

<div class="app-container">
    <?php require_once APP_PATH . '/views/templates/navigation.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <h1 class="topbar-title">Gerenciar Usuarios</h1>
            </div>
            <div class="topbar-right">
                <a href="index.php?page=register" class="btn btn-primary">Novo Usuario</a>
                <div class="user-menu">
                    <div class="user-avatar"><?php echo strtoupper(substr(getCurrentUser()['name'], 0, 1)); ?></div>
                </div>
            </div>
        </header>

        <div class="content-area">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Lista de Usuarios (<?php echo count($users); ?>)</h3>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Email</th>
                                    <th>Perfil</th>
                                    <th>Status</th>
                                    <th>Data de cadastro</th>
                                    <th>Ultimo acesso</th>
                                    <th>Acoes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td style="font-weight: 600;"><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $user['role'] === 'admin' ? 'error' : 'info'; ?>">
                                            <?php echo USER_ROLES[$user['role']] ?? htmlspecialchars($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $user['status'] === 'active' ? 'success' : 'neutral'; ?>">
                                            <?php echo $user['status'] === 'active' ? 'Ativo' : 'Inativo'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatDate($user['created_at'], 'd/m/Y'); ?></td>
                                    <td><?php echo formatDate($user['last_login_at'] ?? null, 'd/m/Y H:i'); ?></td>
                                    <td>
                                        <?php if ((int) $user['id'] !== (int) getCurrentUser()['id']): ?>
                                            <div class="table-actions">
                                                <?php if ($user['status'] === 'active'): ?>
                                                    <form action="index.php?page=users&action=delete" method="POST">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                        <input type="hidden" name="id" value="<?php echo (int) $user['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" data-confirm="Tem certeza que deseja inativar este usuario?">Inativar</button>
                                                    </form>
                                                <?php else: ?>
                                                    <form action="index.php?page=users&action=reactivate" method="POST">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                        <input type="hidden" name="id" value="<?php echo (int) $user['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-success">Reativar</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                            <form action="index.php?page=users&action=resetPassword" method="POST" class="table-actions" style="margin-top: var(--space-2);">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                <input type="hidden" name="id" value="<?php echo (int) $user['id']; ?>">
                                                <input type="password" name="password" class="form-control" placeholder="Senha temporaria" autocomplete="new-password" required>
                                                <button type="submit" class="btn btn-sm btn-secondary">Redefinir</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="badge badge-neutral">Voce</span>
                                        <?php endif; ?>
                                    </td>
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
