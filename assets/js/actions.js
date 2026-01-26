/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * نظام صرح الإتقان - Actions Management JavaScript
 * Sarh Al-Itqan - Actions & Tasks Management Client-Side Logic
 * ═══════════════════════════════════════════════════════════════════════════════
 * @version 1.0.0
 */

class ActionsManager {
    constructor() {
        // Use relative path from the app root
        this.apiUrl = 'api/actions/handler.php';
        this.csrfToken = this.getCSRFToken();
        this.init();
    }

    /**
     * Initialize the actions manager
     */
    init() {
        console.log('ActionsManager initialized');
        this.setupEventListeners();
        this.startAutoRefresh();
    }

    /**
     * Get CSRF token from meta tag or form
     */
    getCSRFToken() {
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        if (metaTag) {
            return metaTag.content;
        }
        
        const hiddenInput = document.querySelector('input[name="sarh_csrf_token"]');
        if (hiddenInput) {
            return hiddenInput.value;
        }
        
        return '';
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Action creation form
        const createForm = document.getElementById('newActionForm');
        if (createForm) {
            createForm.addEventListener('submit', (e) => this.handleCreateAction(e));
        }

        // Action update forms
        document.querySelectorAll('.action-update-form').forEach(form => {
            form.addEventListener('submit', (e) => this.handleUpdateAction(e));
        });

        // Approve/Reject buttons
        document.querySelectorAll('.btn-approve-action').forEach(btn => {
            btn.addEventListener('click', (e) => this.handleApproveAction(e));
        });

        document.querySelectorAll('.btn-reject-action').forEach(btn => {
            btn.addEventListener('click', (e) => this.handleRejectAction(e));
        });

        // Comment forms
        document.querySelectorAll('.action-comment-form').forEach(form => {
            form.addEventListener('submit', (e) => this.handleAddComment(e));
        });
    }

    /**
     * Make API request
     */
    async apiRequest(action, data = {}) {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken
                },
                body: JSON.stringify({ action, ...data })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            return result;

        } catch (error) {
            console.error('API request failed:', error);
            throw error;
        }
    }

    /**
     * Handle create action
     */
    async handleCreateAction(event) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        
        const data = {
            type: formData.get('type'),
            title: formData.get('title'),
            description: formData.get('description'),
            priority: formData.get('priority'),
            category: formData.get('category'),
            due_date: formData.get('due_date')
        };

        try {
            const result = await this.apiRequest('create', data);

            if (result.success) {
                this.showSuccess('تم إنشاء الإجراء بنجاح');
                
                // Close modal if exists
                const modal = bootstrap.Modal.getInstance(document.getElementById('newActionModal'));
                if (modal) modal.hide();

                // Reload or update list
                setTimeout(() => location.reload(), 1500);
            } else {
                this.showError(result.message || 'فشل في إنشاء الإجراء');
            }
        } catch (error) {
            this.showError('حدث خطأ في الاتصال بالخادم');
        }
    }

    /**
     * Handle update action
     */
    async handleUpdateAction(event) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        const actionId = formData.get('action_id');
        
        const data = {
            action_id: actionId,
            title: formData.get('title'),
            description: formData.get('description'),
            priority: formData.get('priority'),
            status: formData.get('status'),
            assigned_to: formData.get('assigned_to')
        };

        try {
            const result = await this.apiRequest('update', data);

            if (result.success) {
                this.showSuccess('تم تحديث الإجراء بنجاح');
                setTimeout(() => location.reload(), 1500);
            } else {
                this.showError(result.message || 'فشل في تحديث الإجراء');
            }
        } catch (error) {
            this.showError('حدث خطأ في الاتصال بالخادم');
        }
    }

    /**
     * Handle approve action
     */
    async handleApproveAction(event) {
        event.preventDefault();
        
        const button = event.target.closest('.btn-approve-action');
        const actionId = button.dataset.actionId;

        const { value: notes } = await Swal.fire({
            title: 'الموافقة على الإجراء',
            input: 'textarea',
            inputLabel: 'ملاحظات (اختياري)',
            inputPlaceholder: 'أضف ملاحظاتك هنا...',
            showCancelButton: true,
            confirmButtonText: 'موافق',
            cancelButtonText: 'إلغاء',
            confirmButtonColor: '#28a745'
        });

        if (notes !== undefined) {
            try {
                const result = await this.apiRequest('approve', {
                    action_id: actionId,
                    notes: notes || ''
                });

                if (result.success) {
                    this.showSuccess('تمت الموافقة على الإجراء');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    this.showError(result.message || 'فشلت الموافقة على الإجراء');
                }
            } catch (error) {
                this.showError('حدث خطأ في الاتصال بالخادم');
            }
        }
    }

    /**
     * Handle reject action
     */
    async handleRejectAction(event) {
        event.preventDefault();
        
        const button = event.target.closest('.btn-reject-action');
        const actionId = button.dataset.actionId;

        const { value: notes } = await Swal.fire({
            title: 'رفض الإجراء',
            input: 'textarea',
            inputLabel: 'سبب الرفض *',
            inputPlaceholder: 'يرجى توضيح سبب الرفض...',
            inputValidator: (value) => {
                if (!value) {
                    return 'يجب إدخال سبب الرفض';
                }
            },
            showCancelButton: true,
            confirmButtonText: 'رفض',
            cancelButtonText: 'إلغاء',
            confirmButtonColor: '#dc3545'
        });

        if (notes) {
            try {
                const result = await this.apiRequest('reject', {
                    action_id: actionId,
                    notes: notes
                });

                if (result.success) {
                    this.showSuccess('تم رفض الإجراء');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    this.showError(result.message || 'فشل رفض الإجراء');
                }
            } catch (error) {
                this.showError('حدث خطأ في الاتصال بالخادم');
            }
        }
    }

    /**
     * Handle add comment
     */
    async handleAddComment(event) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        const actionId = formData.get('action_id');
        const content = formData.get('content');

        if (!content || !content.trim()) {
            this.showError('يرجى إدخال نص التعليق');
            return;
        }

        try {
            const result = await this.apiRequest('add_comment', {
                action_id: actionId,
                content: content.trim(),
                comment_type: 'comment'
            });

            if (result.success) {
                this.showSuccess('تم إضافة التعليق');
                form.reset();
                
                // Reload comments section if exists
                this.loadComments(actionId);
            } else {
                this.showError(result.message || 'فشل في إضافة التعليق');
            }
        } catch (error) {
            this.showError('حدث خطأ في الاتصال بالخادم');
        }
    }

    /**
     * Load action comments
     */
    async loadComments(actionId) {
        try {
            const result = await this.apiRequest('get_comments', { action_id: actionId });

            if (result.success && result.data) {
                this.renderComments(actionId, result.data.comments);
            }
        } catch (error) {
            console.error('Failed to load comments:', error);
        }
    }

    /**
     * Render comments
     */
    renderComments(actionId, comments) {
        const container = document.querySelector(`.action-comments[data-action-id="${actionId}"]`);
        if (!container) return;

        container.innerHTML = comments.map(comment => `
            <div class="comment mb-3">
                <div class="d-flex justify-content-between">
                    <strong>${this.escapeHtml(comment.user_name)}</strong>
                    <small class="text-muted">${this.formatDateTime(comment.created_at)}</small>
                </div>
                <p class="mb-0 mt-1">${this.escapeHtml(comment.content)}</p>
            </div>
        `).join('');
    }

    /**
     * Start auto-refresh for pending approvals
     */
    startAutoRefresh() {
        // Refresh every 5 minutes
        setInterval(() => {
            this.refreshPendingCount();
        }, 5 * 60 * 1000);
    }

    /**
     * Refresh pending approvals count
     */
    async refreshPendingCount() {
        try {
            const result = await this.apiRequest('get_pending_count');

            if (result.success && result.data) {
                const badge = document.querySelector('.pending-approvals-badge');
                if (badge) {
                    badge.textContent = result.data.count;
                    badge.style.display = result.data.count > 0 ? 'inline-block' : 'none';
                }
            }
        } catch (error) {
            console.error('Failed to refresh pending count:', error);
        }
    }

    /**
     * Show success message
     */
    showSuccess(message) {
        Swal.fire({
            icon: 'success',
            title: 'نجح',
            text: message,
            confirmButtonText: 'حسناً',
            timer: 2000
        });
    }

    /**
     * Show error message
     */
    showError(message) {
        Swal.fire({
            icon: 'error',
            title: 'خطأ',
            text: message,
            confirmButtonText: 'حسناً'
        });
    }

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Format date and time
     */
    formatDateTime(datetime) {
        const date = new Date(datetime);
        return date.toLocaleString('ar-SA', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.actionsManager = new ActionsManager();
});
