/**
 * Serenity - Sistema de Gerenciamento de Estoque
 * JavaScript Principal
 */

// ========================================
// INICIALIZAÇÃO
// ========================================
document.addEventListener('DOMContentLoaded', function() {
    initApp();
});

function initApp() {
    initSidebar();
    initModals();
    initForms();
    initTables();
    initNotifications();
    initSearch();
    initTooltips();
    initConfirmations();
    initFormatting();
    initMasks();
    initAccessibility();
}

// ========================================
// SIDEBAR E NAVEGAÇÃO
// ========================================
function initSidebar() {
    const sidebar   = document.getElementById('sidebar') || document.querySelector('.sidebar');
    const toggleBtn = document.getElementById('sidebar-toggle') || document.getElementById('hamburger');
    const closeBtn  = document.getElementById('sidebar-close');

    // Cria o fundo escuro (overlay) se não existir
    let overlay = document.querySelector('.sidebar-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
    }

    function toggleMenu() {
        if (sidebar) {
            sidebar.classList.toggle('active');
            document.body.classList.toggle('sidebar-open', sidebar.classList.contains('active'));
        } else {
            document.body.classList.toggle('sidebar-open');
        }
        overlay.classList.toggle('active');
    }

    if (toggleBtn) toggleBtn.addEventListener('click', toggleMenu);
    if (closeBtn)  closeBtn.addEventListener('click', toggleMenu);
    if (overlay)   overlay.addEventListener('click', toggleMenu);

    // Marcar item ativo no menu (o PHP já faz isso via navActive(),
    // mas o JS garante o comportamento em páginas onde o PHP não consegue
    // inferir a ação — ex: rotas com parâmetros extras)
    const currentPage   = new URLSearchParams(window.location.search).get('page');
    const currentAction = new URLSearchParams(window.location.search).get('action');
    const navLinks      = document.querySelectorAll('.sidebar-nav-link');

    navLinks.forEach(link => {
        try {
            const params     = new URL(link.href).searchParams;
            const linkPage   = params.get('page');
            const linkAction = params.get('action');
            if (linkPage === currentPage && (linkAction === currentAction || !linkAction)) {
                link.classList.add('active');
            }
        } catch (e) { /* ignora links inválidos */ }
    });
}

// ========================================
// SISTEMA DE MODAIS
// ========================================
function initModals() {
    // Abrir modal
    document.querySelectorAll('[data-modal-open]').forEach(btn => {
        btn.addEventListener('click', function() {
            const modalId = this.getAttribute('data-modal-open');
            openModal(modalId);
        });
    });
    
    // Fechar modal
    document.querySelectorAll('[data-modal-close]').forEach(btn => {
        btn.addEventListener('click', function() {
            const modal = this.closest('.modal-overlay');
            if (modal) {
                closeModal(modal.id);
            }
        });
    });
    
    // Fechar ao clicar fora
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal(this.id);
            }
        });
    });
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// ========================================
// VALIDAÇÃO DE FORMULÁRIOS
// ========================================
function initForms() {
    // Validação em tempo real
    document.querySelectorAll('.form-control[required]').forEach(input => {
        input.addEventListener('blur', function() {
            validateField(this);
        });
        
        input.addEventListener('input', function() {
            if (this.classList.contains('error')) {
                validateField(this);
            }
        });
    });
    
    // Submissão de formulários
    document.querySelectorAll('form[data-validate]').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
                showNotification('Por favor, corrija os erros no formulário.', 'error');
            }
        });
    });
    
    // Toggle de senha
    document.querySelectorAll('[data-toggle-password]').forEach(btn => {
        btn.addEventListener('click', function() {
            const input = document.querySelector(this.getAttribute('data-toggle-password'));
            if (input) {
                if (input.type === 'password') {
                    input.type = 'text';
                } else {
                    input.type = 'password';
                }
                this.textContent = input.type === 'password' ? 'Ver' : 'Ocultar';
            }
        });
    });
    
        // B-07: cálculo de markup/margin removido — gerenciado exclusivamente por form-fields.php
}

function validateField(field) {
    const value = field.value.trim();
    const type = field.type;
    let isValid = true;
    let errorMessage = '';
    
    // Campo obrigatório
    if (field.hasAttribute('required') && !value) {
        isValid = false;
        errorMessage = 'Este campo é obrigatório';
    }
    
    // Email
    if (type === 'email' && value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            isValid = false;
            errorMessage = 'Email inválido';
        }
    }
    
    // Número mínimo
    if (type === 'number' && field.hasAttribute('min')) {
        const min = parseFloat(field.getAttribute('min'));
        if (parseFloat(value) < min) {
            isValid = false;
            errorMessage = `Valor mínimo: ${min}`;
        }
    }
    
    
    // Senha forte
    if (field.hasAttribute('data-strong-password') && value) {
        if (value.length < 8) {
            isValid = false;
            errorMessage = 'A senha deve ter no mínimo 8 caracteres';
        } else if (!/[A-Z]/.test(value)) {
            isValid = false;
            errorMessage = 'A senha deve conter pelo menos uma letra maiúscula';
        } else if (!/[a-z]/.test(value)) {
            isValid = false;
            errorMessage = 'A senha deve conter pelo menos uma letra minúscula';
        } else if (!/[0-9]/.test(value)) {
            isValid = false;
            errorMessage = 'A senha deve conter pelo menos um número';
        }
    }
    
    // Confirmação de senha
    if (field.hasAttribute('data-confirm-password')) {
        const passwordField = document.querySelector(field.getAttribute('data-confirm-password'));
        if (passwordField && value !== passwordField.value) {
            isValid = false;
            errorMessage = 'As senhas não coincidem';
        }
    }
    
    // Aplicar classes e mensagens
    if (isValid) {
        field.classList.remove('error');
        removeFieldError(field);
    } else {
        field.classList.add('error');
        showFieldError(field, errorMessage);
    }
    
    return isValid;
}

function validateForm(form) {
    let isValid = true;
    const fields = form.querySelectorAll('.form-control[required]');
    
    fields.forEach(field => {
        if (!validateField(field)) {
            isValid = false;
        }
    });
    
    return isValid;
}

function showFieldError(field, message) {
    removeFieldError(field);
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'form-error';
    errorDiv.textContent = message;
    
    field.parentNode.appendChild(errorDiv);
}

function removeFieldError(field) {
    const existingError = field.parentNode.querySelector('.form-error');
    if (existingError) {
        existingError.remove();
    }
}

// ========================================
// TABELAS INTERATIVAS
// ========================================
function initTables() {
    // Ordenação de tabelas
    document.querySelectorAll('[data-sortable]').forEach(th => {
        th.style.cursor = 'pointer';
        th.addEventListener('click', function() {
            sortTable(this);
        });
    });
    
    // Seleção de linhas
    document.querySelectorAll('.table-checkbox-all').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const table = this.closest('table');
            const checkboxes = table.querySelectorAll('.table-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = this.checked;
            });
            updateBulkActions();
        });
    });
    
    document.querySelectorAll('.table-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkActions);
    });
}

function sortTable(th) {
    const table = th.closest('table');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const index = Array.from(th.parentNode.children).indexOf(th);
    const currentOrder = th.getAttribute('data-order') || 'asc';
    const newOrder = currentOrder === 'asc' ? 'desc' : 'asc';
    
    rows.sort((a, b) => {
        const aValue = a.children[index].textContent.trim();
        const bValue = b.children[index].textContent.trim();
        
        const aNum = parseFloat(aValue.replace(/[^\d.-]/g, ''));
        const bNum = parseFloat(bValue.replace(/[^\d.-]/g, ''));
        
        if (!isNaN(aNum) && !isNaN(bNum)) {
            return newOrder === 'asc' ? aNum - bNum : bNum - aNum;
        }
        
        return newOrder === 'asc' 
            ? aValue.localeCompare(bValue)
            : bValue.localeCompare(aValue);
    });
    
    rows.forEach(row => tbody.appendChild(row));
    
    // Atualizar indicadores
    table.querySelectorAll('th').forEach(header => {
        header.removeAttribute('data-order');
    });
    th.setAttribute('data-order', newOrder);
}

function updateBulkActions() {
    const selected = document.querySelectorAll('.table-checkbox:checked').length;
    const bulkActions = document.querySelector('.bulk-actions');
    
    if (bulkActions) {
        if (selected > 0) {
            bulkActions.style.display = 'flex';
            bulkActions.querySelector('.selected-count').textContent = selected;
        } else {
            bulkActions.style.display = 'none';
        }
    }
}

// ========================================
// SISTEMA DE NOTIFICAÇÕES
// ========================================
function initNotifications() {
    // Auto-fechar alertas
    document.querySelectorAll('.alert[data-auto-close]').forEach(alert => {
        const delay = parseInt(alert.getAttribute('data-auto-close')) || 5000;
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, delay);
    });
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} fade-in`;
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '9999';
    notification.style.minWidth = '300px';
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

// ========================================
// PESQUISA EM TEMPO REAL
// ========================================
function initSearch() {
    const searchInputs = document.querySelectorAll('[data-search]');
    
    searchInputs.forEach(input => {
        let timeout;
        input.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                performSearch(this);
            }, 300);
        });
    });
}

function performSearch(input) {
    const searchTerm = input.value.toLowerCase();
    const target = input.getAttribute('data-search');
    const items = document.querySelectorAll(target);
    
    items.forEach(item => {
        const text = item.textContent.toLowerCase();
        if (text.includes(searchTerm)) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
}

// ========================================
// TOOLTIPS
// ========================================
function initTooltips() {
    document.querySelectorAll('[data-tooltip]').forEach(element => {
        element.addEventListener('mouseenter', function() {
            const text = this.getAttribute('data-tooltip');
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = text;
            tooltip.style.position = 'absolute';
            tooltip.style.background = 'var(--neutral-900)';
            tooltip.style.color = 'white';
            tooltip.style.padding = 'var(--space-2) var(--space-3)';
            tooltip.style.borderRadius = 'var(--radius-md)';
            tooltip.style.fontSize = 'var(--text-sm)';
            tooltip.style.zIndex = '10000';
            tooltip.style.pointerEvents = 'none';
            
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.top = (rect.top - tooltip.offsetHeight - 5) + 'px';
            tooltip.style.left = (rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2)) + 'px';
            
            this._tooltip = tooltip;
        });
        
        element.addEventListener('mouseleave', function() {
            if (this._tooltip) {
                this._tooltip.remove();
                this._tooltip = null;
            }
        });
    });
}

// ========================================
// CONFIRMAÇÕES
// ========================================
function confirmDelete(message = 'Tem certeza que deseja excluir?') {
    return confirm(message);
}

// Adicionar confirmação a links de exclusão
function initConfirmations() {
    document.querySelectorAll('[data-confirm]').forEach(link => {
        link.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm');
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
}

// ========================================
// FORMATAÇÃO DE VALORES
// ========================================
function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(value);
}

function formatNumber(value, decimals = 0) {
    return new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    }).format(value);
}

// Aplicar formatação automática
function initFormatting() {
    document.querySelectorAll('[data-format="currency"]').forEach(element => {
        const value = parseFloat(element.textContent);
        if (!isNaN(value)) {
            element.textContent = formatCurrency(value);
        }
    });
}

// ========================================
// MÁSCARAS DE INPUT
// ========================================
function initMasks() {
    // Máscara de telefone
    document.querySelectorAll('[data-mask="phone"]').forEach(input => {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                value = value.replace(/^(\d{2})(\d)/g, '($1) $2');
                value = value.replace(/(\d)(\d{4})$/, '$1-$2');
            }
            e.target.value = value;
        });
    });
    
    // Máscara de CNPJ
    document.querySelectorAll('[data-mask="cnpj"]').forEach(input => {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/^(\d{2})(\d)/, '$1.$2');
            value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
            value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
            value = value.replace(/(\d{4})(\d)/, '$1-$2');
            e.target.value = value;
        });
    });
}

// ========================================
// UTILITÁRIOS GLOBAIS
// ========================================
window.Serenity = {
    openModal,
    closeModal,
    showNotification,
    confirmDelete,
    formatCurrency,
    formatNumber
};



// ========================================
// MODO DARK E ACESSIBILIDADE
// ========================================
function initAccessibility() {
    const darkModeToggle = document.getElementById('dark-mode-toggle');
    const increaseFontBtn = document.getElementById('increase-font');
    const decreaseFontBtn = document.getElementById('decrease-font');
    const highContrastToggle = document.getElementById('high-contrast-toggle');

    // Dark Mode
    if (darkModeToggle) {
        darkModeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            if (document.body.classList.contains('dark-mode')) {
                localStorage.setItem('theme', 'dark');
            } else {
                localStorage.setItem('theme', 'light');
            }
        });
    }

    // Apply saved theme on load
    if (localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('dark-mode');
    } else {
        document.body.classList.remove('dark-mode');
    }

    // Font Size
    let currentFontSize = localStorage.getItem('fontSize') || 'medium'; // 'small', 'medium', 'large'
    document.body.classList.add(`font-${currentFontSize}`);

    function updateFontSize(size) {
        document.body.classList.remove('font-small', 'font-medium', 'font-large');
        document.body.classList.add(`font-${size}`);
        localStorage.setItem('fontSize', size);
        currentFontSize = size;
    }

    if (increaseFontBtn) {
        increaseFontBtn.addEventListener('click', () => {
            if (currentFontSize === 'small') updateFontSize('medium');
            else if (currentFontSize === 'medium') updateFontSize('large');
        });
    }

    if (decreaseFontBtn) {
        decreaseFontBtn.addEventListener('click', () => {
            if (currentFontSize === 'large') updateFontSize('medium');
            else if (currentFontSize === 'medium') updateFontSize('small');
        });
    }

    // High Contrast
    if (highContrastToggle) {
        highContrastToggle.addEventListener('click', () => {
            document.body.classList.toggle('high-contrast');
            if (document.body.classList.contains('high-contrast')) {
                localStorage.setItem('highContrast', 'enabled');
            } else {
                localStorage.setItem('highContrast', 'disabled');
            }
        });
    }

    // Apply saved high contrast on load
    if (localStorage.getItem('highContrast') === 'enabled') {
        document.body.classList.add('high-contrast');
    } else {
        document.body.classList.remove('high-contrast');
    }
}

// (sidebar toggle e overlay gerenciados por initSidebar() acima)
