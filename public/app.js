class CloudAnalyticsApp {
    constructor() {
        this.apiBase = 'api.php';
        this.init();
    }

    async init() {
        await this.checkAuthentication();
        this.setupEventListeners();
    }

    async checkAuthentication() {
        try {
            const response = await fetch(`${this.apiBase}/auth`);
            const data = await response.json();
            
            if (data.authenticated) {
                this.showMainContent();
            } else {
                this.showAuthContainer(data.auth_url);
            }
        } catch (error) {
            console.error('Authentication check failed:', error);
            this.showError('فشل في التحقق من المصادقة');
        }
    }

    showAuthContainer(authUrl) {
        document.getElementById('authContainer').style.display = 'block';
        document.getElementById('mainContent').style.display = 'none';
        
        document.getElementById('authBtn').onclick = () => {
            window.location.href = authUrl;
        };
    }

    showMainContent() {
        document.getElementById('authContainer').style.display = 'none';
        document.getElementById('mainContent').style.display = 'block';
        this.loadStatistics();
    }

    setupEventListeners() {
        // Upload form
        document.getElementById('uploadForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.uploadDocument();
        });

        // Sync button
        document.getElementById('syncBtn').addEventListener('click', () => {
            this.syncFromDrive();
        });

        // Search form
        document.getElementById('searchForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.searchDocuments();
        });

        // Sort form
        document.getElementById('sortForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.sortDocuments();
        });

        // Classify buttons
        document.getElementById('classifyBtn').addEventListener('click', () => {
            this.classifyDocuments(false);
        });

        document.getElementById('reclassifyBtn').addEventListener('click', () => {
            this.classifyDocuments(true);
        });

        // Tab change event
        document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
            tab.addEventListener('shown.bs.tab', (e) => {
                if (e.target.id === 'stats-tab') {
                    this.loadStatistics();
                }
            });
        });
    }

    async uploadDocument() {
        const fileInput = document.getElementById('documentFile');
        const file = fileInput.files[0];
        
        if (!file) {
            this.showError('يرجى اختيار ملف');
            return;
        }

        const formData = new FormData();
        formData.append('document', file);

        this.showLoading('uploadResults');

        try {
            const response = await fetch(`${this.apiBase}/upload`, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('uploadResults', `تم رفع المستند بنجاح: ${data.filename}`);
                fileInput.value = '';
            } else {
                this.showError('uploadResults', data.error);
            }
        } catch (error) {
            console.error('Upload failed:', error);
            this.showError('uploadResults', 'فشل في رفع المستند');
        }
    }

    async syncFromDrive() {
        this.showLoading('uploadResults');

        try {
            const response = await fetch(`${this.apiBase}/sync`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({})
            });

            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('uploadResults', `تم مزامنة ${data.synced_count} مستند في ${data.sync_time.toFixed(2)} ثانية`);
            } else {
                this.showError('uploadResults', data.error);
            }
        } catch (error) {
            console.error('Sync failed:', error);
            this.showError('uploadResults', 'فشل في المزامنة');
        }
    }

    async searchDocuments() {
        const keywords = document.getElementById('searchKeywords').value.trim();
        const searchInContent = document.getElementById('searchInContent').checked;

        if (!keywords) {
            this.showError('searchResults', 'يرجى إدخال كلمات البحث');
            return;
        }

        this.showLoading('searchResults');

        try {
            const response = await fetch(`${this.apiBase}/search`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    keywords: keywords,
                    search_in_content: searchInContent
                })
            });

            const data = await response.json();
            
            if (data.success) {
                this.displaySearchResults(data);
            } else {
                this.showError('searchResults', data.error);
            }
        } catch (error) {
            console.error('Search failed:', error);
            this.showError('searchResults', 'فشل في البحث');
        }
    }

    async sortDocuments() {
        const sortBy = document.getElementById('sortBy').value;
        const order = document.getElementById('sortOrder').value;

        this.showLoading('sortResults');

        try {
            const response = await fetch(`${this.apiBase}/sort?sort_by=${sortBy}&order=${order}`);
            const data = await response.json();
            
            if (data.success) {
                this.displaySortResults(data);
            } else {
                this.showError('sortResults', data.error);
            }
        } catch (error) {
            console.error('Sort failed:', error);
            this.showError('sortResults', 'فشل في الفرز');
        }
    }

    async classifyDocuments(forceReclassify = false) {
        const resultContainer = 'classifyResults';
        this.showLoading(resultContainer);

        try {
            const response = await fetch(`${this.apiBase}/classify`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    force_reclassify: forceReclassify
                })
            });

            const data = await response.json();
            
            if (data.success) {
                this.showSuccess(resultContainer, 
                    `تم تصنيف ${data.classified_count} مستند في ${data.classification_time.toFixed(2)} ثانية`);
            } else {
                this.showError(resultContainer, data.error);
            }
        } catch (error) {
            console.error('Classification failed:', error);
            this.showError(resultContainer, 'فشل في التصنيف');
        }
    }

    async loadStatistics() {
        const container = document.getElementById('statisticsContent');
        this.showLoading('statisticsContent');

        try {
            const response = await fetch(`${this.apiBase}/statistics`);
            const data = await response.json();
            
            if (data.success) {
                this.displayStatistics(data.statistics, data.recent_searches);
            } else {
                this.showError('statisticsContent', data.error);
            }
        } catch (error) {
            console.error('Statistics loading failed:', error);
            this.showError('statisticsContent', 'فشل في تحميل الإحصائيات');
        }
    }

    displaySearchResults(data) {
        const container = document.getElementById('searchResults');
        
        if (data.results.length === 0) {
            container.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> لم يتم العثور على نتائج للبحث عن: "${data.keywords}"
                </div>
            `;
            return;
        }

        let html = `
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> تم العثور على ${data.count} نتيجة في ${data.search_time.toFixed(3)} ثانية
            </div>
            <div class="row">
        `;

        data.results.forEach(doc => {
            html += `
                <div class="col-12">
                    <div class="document-item">
                        <h6>${doc.highlighted_title || doc.title}</h6>
                        <p class="text-muted mb-1">
                            <i class="fas fa-file"></i> ${doc.filename} | 
                            <i class="fas fa-tag"></i> ${doc.category || 'غير مصنف'} | 
                            <i class="fas fa-calendar"></i> ${new Date(doc.upload_date).toLocaleDateString('ar')}
                        </p>
                        <p class="mb-0">${doc.highlighted_content || doc.content.substring(0, 200) + '...'}</p>
                    </div>
                </div>
            `;
        });

        html += '</div>';
        container.innerHTML = html;
    }

    displaySortResults(data) {
        const container = document.getElementById('sortResults');
        
        if (data.documents.length === 0) {
            container.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> لا توجد مستندات للعرض
                </div>
            `;
            return;
        }

        let html = `
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> تم فرز ${data.count} مستند حسب ${this.getSortLabel(data.sort_by)} (${data.order === 'ASC' ? 'تصاعدي' : 'تنازلي'}) في ${data.sort_time.toFixed(3)} ثانية
            </div>
            <div class="row">
        `;

        data.documents.forEach(doc => {
            const fileSize = this.formatFileSize(doc.file_size);
            html += `
                <div class="col-12">
                    <div class="document-item">
                        <h6>${doc.title}</h6>
                        <p class="text-muted mb-1">
                            <i class="fas fa-file"></i> ${doc.filename} | 
                            <i class="fas fa-tag"></i> ${doc.category || 'غير مصنف'} | 
                            <i class="fas fa-hdd"></i> ${fileSize} |
                            <i class="fas fa-calendar"></i> ${new Date(doc.upload_date).toLocaleDateString('ar')}
                        </p>
                        <p class="mb-0">${doc.content.substring(0, 200)}...</p>
                    </div>
                </div>
            `;
        });

        html += '</div>';
        container.innerHTML = html;
    }

    displayStatistics(stats, recentSearches) {
        const container = document.getElementById('statisticsContent');
        
        let html = `
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-number">${stats.total_documents}</div>
                        <div>إجمالي المستندات</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-number">${this.formatFileSize(stats.total_size)}</div>
                        <div>إجمالي الحجم</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-number">${Object.keys(stats.by_category).length}</div>
                        <div>عدد التصنيفات</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-number">${Object.keys(stats.by_type).length}</div>
                        <div>أنواع الملفات</div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-chart-pie"></i> التوزيع حسب التصنيف</h5>
                        </div>
                        <div class="card-body">
        `;

        for (const [category, count] of Object.entries(stats.by_category)) {
            const percentage = ((count / stats.total_documents) * 100).toFixed(1);
            html += `
                <div class="mb-2">
                    <div class="d-flex justify-content-between">
                        <span>${category || 'غير مصنف'}</span>
                        <span>${count} (${percentage}%)</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar" style="width: ${percentage}%"></div>
                    </div>
                </div>
            `;
        }

        html += `
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-search"></i> عمليات البحث الأخيرة</h5>
                        </div>
                        <div class="card-body">
        `;

        if (recentSearches.length === 0) {
            html += '<p class="text-muted">لا توجد عمليات بحث سابقة</p>';
        } else {
            recentSearches.forEach(search => {
                html += `
                    <div class="mb-2 p-2 border-bottom">
                        <div class="d-flex justify-content-between">
                            <span><strong>${search.query}</strong></span>
                            <small class="text-muted">${search.results_count} نتيجة</small>
                        </div>
                        <small class="text-muted">
                            ${new Date(search.search_date).toLocaleString('ar')} | 
                            ${(search.search_time * 1000).toFixed(0)}ms
                        </small>
                    </div>
                `;
            });
        }

        html += `
                        </div>
                    </div>
                </div>
            </div>
        `;

        container.innerHTML = html;
    }

    getSortLabel(sortBy) {
        const labels = {
            'title': 'العنوان',
            'filename': 'اسم الملف',
            'upload_date': 'تاريخ الرفع',
            'category': 'التصنيف',
            'file_size': 'حجم الملف'
        };
        return labels[sortBy] || sortBy;
    }

    formatFileSize(bytes) {
        if (!bytes) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    showLoading(containerId) {
        const container = document.getElementById(containerId);
        container.innerHTML = `
            <div class="loading" style="display: block;">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">جاري التحميل...</span>
                </div>
                <p class="mt-2">جاري المعالجة...</p>
            </div>
        `;
    }

    showSuccess(containerId, message) {
        const container = document.getElementById(containerId);
        container.innerHTML = `
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> ${message}
            </div>
        `;
    }

    showError(containerId, message) {
        const container = document.getElementById(containerId);
        container.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> ${message}
            </div>
        `;
    }
}

// Initialize the app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new CloudAnalyticsApp();
});

