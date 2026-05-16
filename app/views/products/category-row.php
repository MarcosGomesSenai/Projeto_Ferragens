<tr>
    <td style="font-weight: 600;">
        <?php echo !empty($isSubcategory) ? '&nbsp;&nbsp;&rarr; ' : ''; ?><?php echo htmlspecialchars($category['name']); ?>
        <?php if (!empty($category['description'])): ?>
            <small class="muted-line"><?php echo htmlspecialchars($category['description']); ?></small>
        <?php endif; ?>
    </td>
    <td><?php echo htmlspecialchars($category['code'] ?? '-'); ?></td>
    <td>
        <span class="badge <?php echo $category['status'] === 'active' ? 'badge-success' : 'badge-neutral'; ?>">
            <?php echo $category['status'] === 'active' ? 'Ativa' : 'Inativa'; ?>
        </span>
    </td>
    <td>
        <div class="table-actions">
            <a href="index.php?page=categories&edit_id=<?php echo (int) $category['id']; ?>" class="btn btn-sm btn-primary">Editar</a>
            <?php if (hasPermission('manager')): ?>
                <form action="index.php?page=categories&action=delete" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="id" value="<?php echo (int) $category['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-danger" data-confirm="Excluir esta categoria?">Excluir</button>
                </form>
            <?php endif; ?>
        </div>
    </td>
</tr>
