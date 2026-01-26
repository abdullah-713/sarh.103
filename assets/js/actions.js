/**
 * SARH SYSTEM - ACTIONS JAVASCRIPT
 * JavaScript للتفاعل مع API الإجراءات
 */

const ActionsApp = {
    currentFilter: 'my',
    currentActionId: null,
    
    /**
     * تهيئة التطبيق
     */
    init() {
        this.loadStats();
        this.loadActions();
        this.attachEventListeners();
    },
    
    /**
     * تحميل الإحصائيات
     */
    async loadStats() {
        try {
            const response = await fetch('/api/actions/handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': SARH.csrfToken
                },
                body: JSON.stringify({ action: 'stats' })
            });
            
            const result = await response.json();
            
            if (result.success) {
                const stats = result.data;
                document.getElementById('statPending').textContent = stats.pending || 0;
                document.getElementById('statInProgress').textContent = stats.in_progress || 0;
                document.getElementById('statWaitingApproval').textContent = stats.waiting_approval || 0;
                document.getElementById('statCompleted').textContent = stats.completed || 0;
            }
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    },
    
    /**
     * تحميل قائمة الإجراءات
     */
    async loadActions(filter = null) {
        if (filter) this.currentFilter = filter;
        
        showLoading('جاري التحميل...');
        
        try {
            const response = await fetch('/api/actions/handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': SARH.csrfToken
                },
                body: JSON.stringify({
                    action: 'list',
                    view: this.currentFilter,
                    per_page: 50
                })
            });
            
            const result = await response.json();
            hideLoading();
            
            if (result.success) {
                this.renderActionsList(result.data);
            } else {
                showError('فشل تحميل الإجراءات');
            }
        } catch (error) {
            hideLoading();
            showError('خطأ في الاتصال بالخادم');
            console.error('Error loading actions:', error);
        }
    },
    
    /**
     * عرض قائمة الإجراءات
     */
    renderActionsList(actions) {
        const container = document.getElementById('actionsListContainer');
        
        if (!actions || actions.length === 0) {
            container.innerHTML = `
                <div class="text-center py-5">
                    <i class="bi bi-inbox display-1 text-muted mb-3"></i>
                    <h5 class="text-muted">لا توجد إجراءات</h5>
                </div>
            `;
            return;
        }
        
        const html = actions.map(action => `
            <div class="action-card" data-id="${action.id}" onclick="ActionsApp.viewActionDetails(${action.id})">
                <div class="action-header">
                    <div class="d-flex align-items-start gap-3">
                        <div class="action-icon ${this.getStatusColor(action.status)}">
                            <i class="bi bi-${this.getTypeIcon(action.type)}"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="action-title mb-1">${this.escapeHtml(action.title)}</h6>
                            <div class="action-meta">
                                <span class="badge bg-secondary">${action.action_code}</span>
                                <span class="text-muted">${action.requester_name}</span>
                                <span class="text-muted">${this.formatDate(action.created_at)}</span>
                            </div>
                        </div>
                        <span class="badge ${this.getStatusBadgeClass(action.status)}">
                            ${this.getStatusText(action.status)}
                        </span>
                    </div>
                </div>
                ${action.description ? `<p class="action-description">${this.escapeHtml(action.description)}</p>` : ''}
                <div class="action-footer">
                    <span class="badge badge-outline">${this.getTypeText(action.type)}</span>
                    <span class="badge badge-outline">${this.getPriorityText(action.priority)}</span>
                    ${action.assigned_name ? `<span class="text-muted"><i class="bi bi-person"></i> ${this.escapeHtml(action.assigned_name)}</span>` : ''}
                </div>
            </div>
        `).join('');
        
        container.innerHTML = html;
    },
    
    /**
     * عرض تفاصيل إجراء
     */
    async viewActionDetails(actionId) {
        this.currentActionId = actionId;
        showLoading('جاري التحميل...');
        
        try {
            const response = await fetch('/api/actions/handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': SARH.csrfToken
                },
                body: JSON.stringify({
                    action: 'get',
                    id: actionId
                })
            });
            
            const result = await response.json();
            hideLoading();
            
            if (result.success) {
                this.renderActionDetails(result.data);
                const detailsPanel = document.getElementById('actionDetailsPanel');
                detailsPanel.classList.add('show');
            } else {
                showError('فشل تحميل التفاصيل');
            }
        } catch (error) {
            hideLoading();
            showError('خطأ في الاتصال بالخادم');
            console.error('Error loading action details:', error);
        }
    },
    
    /**
     * عرض تفاصيل الإجراء
     */
    renderActionDetails(data) {
        const action = data.action;
        const comments = data.comments || [];
        const approvals = data.approvals || [];
        
        document.getElementById('detailTitle').textContent = action.title;
        document.getElementById('detailCode').textContent = action.action_code;
        document.getElementById('detailStatus').innerHTML = `
            <span class="badge ${this.getStatusBadgeClass(action.status)}">
                ${this.getStatusText(action.status)}
            </span>
        `;
        document.getElementById('detailDescription').textContent = action.description || 'لا يوجد وصف';
        document.getElementById('detailRequester').textContent = action.requester_name;
        document.getElementById('detailDate').textContent = this.formatDateTime(action.created_at);
        
        // عرض التعليقات
        const timelineHtml = comments.map(comment => `
            <div class="timeline-item">
                <div class="timeline-marker ${comment.comment_type === 'system' ? 'bg-secondary' : 'bg-primary'}"></div>
                <div class="timeline-content">
                    <div class="d-flex justify-content-between">
                        <strong>${this.escapeHtml(comment.user_name)}</strong>
                        <small class="text-muted">${this.formatDateTime(comment.created_at)}</small>
                    </div>
                    <p class="mb-0">${this.escapeHtml(comment.content)}</p>
                </div>
            </div>
        `).join('');
        
        document.getElementById('detailTimeline').innerHTML = timelineHtml || '<p class="text-muted">لا توجد تعليقات</p>';
    },
    
    /**
     * إغلاق لوحة التفاصيل
     */
    closeDetails() {
        document.getElementById('actionDetailsPanel').classList.remove('show');
        this.currentActionId = null;
    },
    
    /**
     * إضافة تعليق
     */
    async addComment() {
        const content = document.getElementById('commentInput').value.trim();
        
        if (!content) {
            showError('يرجى كتابة تعليق');
            return;
        }
        
        if (!this.currentActionId) {
            showError('لم يتم تحديد إجراء');
            return;
        }
        
        showLoading('جاري الإرسال...');
        
        try {
            const response = await fetch('/api/actions/handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': SARH.csrfToken
                },
                body: JSON.stringify({
                    action: 'add_comment',
                    action_id: this.currentActionId,
                    content: content
                })
            });
            
            const result = await response.json();
            hideLoading();
            
            if (result.success) {
                document.getElementById('commentInput').value = '';
                this.viewActionDetails(this.currentActionId);
                showSuccess('تم إضافة التعليق');
            } else {
                showError(result.error || 'فشل إضافة التعليق');
            }
        } catch (error) {
            hideLoading();
            showError('خطأ في الاتصال بالخادم');
            console.error('Error adding comment:', error);
        }
    },
    
    /**
     * تغيير حالة الإجراء
     */
    async changeStatus(newStatus) {
        if (!this.currentActionId) return;
        
        const confirmed = await showConfirm(
            'تأكيد التغيير',
            'هل أنت متأكد من تغيير حالة الإجراء؟'
        );
        
        if (!confirmed) return;
        
        showLoading('جاري الحفظ...');
        
        try {
            const response = await fetch('/api/actions/handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': SARH.csrfToken
                },
                body: JSON.stringify({
                    action: 'change_status',
                    id: this.currentActionId,
                    status: newStatus
                })
            });
            
            const result = await response.json();
            hideLoading();
            
            if (result.success) {
                showSuccess('تم تغيير الحالة بنجاح');
                this.viewActionDetails(this.currentActionId);
                this.loadStats();
                this.loadActions();
            } else {
                showError(result.error || 'فشل تغيير الحالة');
            }
        } catch (error) {
            hideLoading();
            showError('خطأ في الاتصال بالخادم');
            console.error('Error changing status:', error);
        }
    },
    
    /**
     * ربط أحداث العناصر
     */
    attachEventListeners() {
        // أزرار الفلاتر
        document.querySelectorAll('[data-filter]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('[data-filter]').forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');
                this.loadActions(e.target.dataset.filter);
            });
        });
        
        // زر إغلاق التفاصيل
        document.getElementById('closeDetailsBtn')?.addEventListener('click', () => {
            this.closeDetails();
        });
        
        // زر إضافة تعليق
        document.getElementById('addCommentBtn')?.addEventListener('click', () => {
            this.addComment();
        });
    },
    
    // Helper functions
    getStatusColor(status) {
        const colors = {
            'pending': 'status-pending',
            'in_progress': 'status-progress',
            'waiting_approval': 'status-waiting',
            'approved': 'status-approved',
            'completed': 'status-completed',
            'rejected': 'status-rejected',
            'cancelled': 'status-cancelled'
        };
        return colors[status] || 'status-pending';
    },
    
    getStatusBadgeClass(status) {
        const classes = {
            'pending': 'bg-warning',
            'in_progress': 'bg-info',
            'waiting_approval': 'bg-primary',
            'approved': 'bg-success',
            'completed': 'bg-success',
            'rejected': 'bg-danger',
            'cancelled': 'bg-secondary'
        };
        return classes[status] || 'bg-secondary';
    },
    
    getStatusText(status) {
        const texts = {
            'pending': 'قيد الانتظار',
            'in_progress': 'قيد التنفيذ',
            'waiting_approval': 'بانتظار الموافقة',
            'approved': 'موافق عليه',
            'completed': 'مكتمل',
            'rejected': 'مرفوض',
            'cancelled': 'ملغى'
        };
        return texts[status] || status;
    },
    
    getTypeIcon(type) {
        const icons = {
            'request': 'file-earmark-text',
            'task': 'list-check',
            'approval': 'clipboard-check',
            'complaint': 'exclamation-triangle',
            'suggestion': 'lightbulb'
        };
        return icons[type] || 'file-text';
    },
    
    getTypeText(type) {
        const texts = {
            'request': 'طلب',
            'task': 'مهمة',
            'approval': 'موافقة',
            'complaint': 'شكوى',
            'suggestion': 'اقتراح'
        };
        return texts[type] || type;
    },
    
    getPriorityText(priority) {
        const texts = {
            'low': 'منخفضة',
            'medium': 'متوسطة',
            'high': 'عالية',
            'urgent': 'عاجلة'
        };
        return texts[priority] || priority;
    },
    
    formatDate(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        return date.toLocaleDateString('ar-SA');
    },
    
    formatDateTime(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        return date.toLocaleString('ar-SA');
    },
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// تهيئة التطبيق عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('actionsApp')) {
        ActionsApp.init();
    }
});
