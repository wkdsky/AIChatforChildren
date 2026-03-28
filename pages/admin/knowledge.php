<?php

use Utils\Helper;

$user = $_SESSION['user'];
$csrfToken = Helper::generateCsrfToken();
$baseUrl = Helper::url('');
?>

<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knowledge Base Management - Admin</title>

    <link rel="stylesheet" href="<?php echo Helper::url('assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        .kb-shell {
            display: grid;
            gap: 20px;
        }

        .kb-topbar,
        .kb-card,
        .kb-panel,
        .kb-modal-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 16px 38px rgba(31, 41, 55, 0.08);
        }

        .kb-topbar {
            padding: 18px 20px;
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
        }

        .kb-tabs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .kb-tab {
            border: 1px solid #dbe5f0;
            background: #f7fafc;
            color: #243b53;
            padding: 10px 14px;
            border-radius: 999px;
            cursor: pointer;
            font-weight: 600;
        }

        .kb-tab.active {
            background: #1f6feb;
            color: #fff;
            border-color: #1f6feb;
        }

        .kb-status {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
        }

        .kb-status.online {
            background: #e8f7ee;
            color: #1e7d45;
        }

        .kb-status.offline {
            background: #fff1f1;
            color: #b42318;
        }

        .kb-status.incompatible {
            background: #fff7e6;
            color: #9a6700;
        }

        .kb-status-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: currentColor;
        }

        .kb-alert {
            display: none;
            padding: 14px 16px;
            border-radius: 12px;
            gap: 10px;
            align-items: center;
            font-weight: 600;
        }

        .kb-alert.show {
            display: flex;
        }

        .kb-alert.warning {
            background: #fff7e6;
            color: #9a6700;
            border: 1px solid #f9d67a;
        }

        .kb-alert.error {
            background: #fff1f1;
            color: #b42318;
            border: 1px solid #f5b3ae;
        }

        .kb-grid {
            display: grid;
            gap: 20px;
        }

        .kb-card {
            padding: 22px;
        }

        .kb-section-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }

        .kb-section-head h2,
        .kb-section-head h3 {
            color: #102a43;
            margin: 0;
        }

        .kb-muted {
            color: #52606d;
            font-size: 14px;
        }

        .kb-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .kb-btn {
            border: none;
            border-radius: 10px;
            padding: 10px 14px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .kb-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .kb-btn.primary {
            background: #1f6feb;
            color: #fff;
        }

        .kb-btn.secondary {
            background: #eef4ff;
            color: #1d4ed8;
        }

        .kb-btn.ghost {
            background: #f4f7fb;
            color: #334e68;
        }

        .kb-btn.danger {
            background: #fff1f1;
            color: #b42318;
        }

        .kb-btn.success {
            background: #e8f7ee;
            color: #1e7d45;
        }

        .kb-filter-grid,
        .kb-form-grid,
        .kb-batch-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
        }

        .kb-field {
            display: grid;
            gap: 8px;
        }

        .kb-field label {
            font-size: 13px;
            font-weight: 700;
            color: #243b53;
        }

        .kb-field input,
        .kb-field select,
        .kb-field textarea {
            width: 100%;
            border: 1px solid #d9e2ec;
            background: #fff;
            border-radius: 10px;
            padding: 10px 12px;
            font: inherit;
            color: #102a43;
        }

        .kb-field textarea {
            min-height: 110px;
            resize: vertical;
        }

        .kb-multiselect {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .kb-chip-toggle {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid #d9e2ec;
            border-radius: 999px;
            padding: 8px 12px;
            background: #fff;
            font-size: 13px;
            color: #243b53;
        }

        .kb-chip-toggle input {
            width: auto;
            margin: 0;
        }

        .kb-table-wrap {
            overflow-x: auto;
        }

        .kb-table {
            width: 100%;
            border-collapse: collapse;
        }

        .kb-table th,
        .kb-table td {
            padding: 14px 12px;
            border-bottom: 1px solid #e6edf5;
            text-align: left;
            vertical-align: top;
            font-size: 14px;
        }

        .kb-table th {
            color: #486581;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .kb-pill-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .kb-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 5px 10px;
            font-size: 12px;
            font-weight: 700;
            background: #edf2f7;
            color: #334e68;
        }

        .kb-pill.review-needs_review {
            background: #fff7e6;
            color: #9a6700;
        }

        .kb-pill.review-auto_accepted {
            background: #e8f7ee;
            color: #1e7d45;
        }

        .kb-pill.review-blocked,
        .kb-pill.status-failed,
        .kb-pill.visibility-blocked {
            background: #fff1f1;
            color: #b42318;
        }

        .kb-pill.status-completed {
            background: #e8f7ee;
            color: #1e7d45;
        }

        .kb-pill.status-pending {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .kb-pill.visibility-retrieval_visible {
            background: #e8f7ee;
            color: #1e7d45;
        }

        .kb-pill.visibility-parent_only {
            background: #fff7e6;
            color: #9a6700;
        }

        .kb-pill.visibility-system_only {
            background: #eef4ff;
            color: #1d4ed8;
        }

        .kb-note {
            border-left: 4px solid #1f6feb;
            background: #f4f8ff;
            padding: 14px 16px;
            border-radius: 12px;
            color: #334e68;
            line-height: 1.6;
        }

        .kb-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 14px;
        }

        .kb-stat {
            border: 1px solid #e6edf5;
            border-radius: 14px;
            padding: 16px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        }

        .kb-stat strong {
            display: block;
            color: #102a43;
            font-size: 24px;
            margin-top: 4px;
        }

        .kb-stage-list {
            display: grid;
            gap: 12px;
            margin-top: 10px;
        }

        .kb-stage {
            display: flex;
            gap: 14px;
            align-items: center;
            border: 1px solid #e6edf5;
            border-radius: 14px;
            padding: 14px 16px;
        }

        .kb-stage-bullet {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #cbd2d9;
            flex: 0 0 auto;
        }

        .kb-stage.completed .kb-stage-bullet {
            background: #1e7d45;
        }

        .kb-stage.pending .kb-stage-bullet {
            background: #1d4ed8;
        }

        .kb-stage.failed .kb-stage-bullet {
            background: #b42318;
        }

        .kb-empty,
        .kb-loading {
            padding: 32px 18px;
            text-align: center;
            color: #52606d;
        }

        .kb-loading i {
            animation: kb-spin 1s linear infinite;
        }

        @keyframes kb-spin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        .kb-result-list,
        .kb-doc-stack {
            display: grid;
            gap: 14px;
        }

        .kb-doc-card,
        .kb-search-card {
            border: 1px solid #e6edf5;
            border-radius: 16px;
            padding: 16px;
            background: #fff;
        }

        .kb-doc-title {
            margin: 0;
            color: #102a43;
            font-size: 18px;
        }

        .kb-doc-meta {
            color: #52606d;
            font-size: 13px;
            margin-top: 6px;
        }

        .kb-inline {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .kb-toggle {
            display: inline-flex;
            gap: 8px;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: #f4f7fb;
            color: #243b53;
            font-weight: 700;
        }

        .kb-toggle input {
            width: auto;
            margin: 0;
        }

        .kb-modal {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
            z-index: 1200;
        }

        .kb-modal.show {
            display: flex;
        }

        .kb-modal-card {
            width: min(860px, 100%);
            max-height: calc(100vh - 60px);
            overflow: auto;
            padding: 22px;
        }

        .kb-mono {
            font-family: "SFMono-Regular", Consolas, monospace;
            font-size: 12px;
        }

        .kb-danger-flag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #fff1f1;
            color: #b42318;
            border-radius: 999px;
            padding: 8px 12px;
            font-weight: 700;
        }

        .kb-disabled {
            opacity: 0.6;
        }

        .kb-upload-list {
            display: grid;
            gap: 12px;
            margin-top: 18px;
        }

        .kb-upload-item {
            border: 1px solid #d9e2ec;
            border-radius: 14px;
            padding: 14px;
            background: #fff;
        }

        .kb-upload-item-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .kb-upload-file {
            display: grid;
            gap: 4px;
            min-width: 0;
        }

        .kb-upload-file strong {
            color: #102a43;
            word-break: break-word;
        }

        .kb-upload-remove {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 50%;
            background: #fff1f1;
            color: #b42318;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .kb-upload-progress {
            width: 100%;
            height: 10px;
            border-radius: 999px;
            background: #e6edf5;
            overflow: hidden;
        }

        .kb-upload-progress > span {
            display: block;
            height: 100%;
            background: linear-gradient(90deg, #1f6feb 0%, #38bdf8 100%);
            border-radius: 999px;
            transition: width 0.2s ease;
        }

        .kb-upload-progress.success > span {
            background: linear-gradient(90deg, #1e7d45 0%, #34d399 100%);
        }

        .kb-upload-progress.error > span {
            background: linear-gradient(90deg, #b42318 0%, #ef4444 100%);
        }

        .kb-upload-summary {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 18px;
            padding: 16px;
            border-radius: 14px;
            background: #f8fbff;
        }

        .kb-upload-status {
            font-size: 13px;
            color: #52606d;
        }

        @media (max-width: 960px) {
            .main-content {
                padding: 20px;
            }
        }

        @media (max-width: 780px) {
            .sidebar {
                position: static;
                width: 100%;
                height: auto;
            }

            .main-content {
                margin-left: 0;
            }

            .admin-layout {
                display: block;
            }
        }
    </style>
</head>

<body>
    <div class="admin-layout">
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-shield-alt"></i> Admin Panel</h2>
                <div class="admin-info">
                    <div class="admin-avatar">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="admin-details">
                        <div class="admin-name"><?php echo htmlspecialchars($user['name']); ?></div>
                        <div class="admin-email"><?php echo htmlspecialchars($user['email']); ?></div>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav">
                <ul>
                    <li class="nav-item">
                        <a href="<?php echo Helper::url('admin-dashboard'); ?>" class="nav-link">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo Helper::url('admin/prompts'); ?>" class="nav-link">
                            <i class="fas fa-edit"></i> Prompt Templates
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo Helper::url('admin/users'); ?>" class="nav-link">
                            <i class="fas fa-users"></i> User Management
                        </a>
                    </li>
                    <li class="nav-item active">
                        <a href="<?php echo Helper::url('admin/knowledge'); ?>" class="nav-link">
                            <i class="fas fa-database"></i> Knowledge Base
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo Helper::url('admin/profile'); ?>" class="nav-link">
                            <i class="fas fa-user-cog"></i> Profile Settings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo Helper::url('logout'); ?>" class="nav-link logout">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </nav>
        </div>

        <div class="main-content">
            <div class="content-header">
                <h1>Knowledge Base Management</h1>
                <p class="breadcrumb">儿童陪伴 AI 知识库上传、自动分析与审查</p>
            </div>

            <div class="kb-shell">
                <div id="globalAlert" class="kb-alert warning"></div>

                <div class="kb-topbar">
                    <div class="kb-tabs">
                        <button class="kb-tab" data-view="documents">文档列表</button>
                        <button class="kb-tab" data-view="upload">上传</button>
                    </div>

                    <div id="serviceStatus" class="kb-status offline">
                        <span class="kb-status-dot"></span>
                        <span>服务检查中</span>
                    </div>
                </div>

                <div id="appRoot" class="kb-grid"></div>
            </div>
        </div>
    </div>

    <div id="chunkModal" class="kb-modal">
        <div class="kb-modal-card">
            <div class="kb-section-head">
                <h3>Chunk 详情</h3>
                <button class="kb-btn ghost" id="closeChunkModalBtn">
                    <i class="fas fa-times"></i> 关闭
                </button>
            </div>
            <div id="chunkModalBody"></div>
        </div>
    </div>

    <script>
        const BASE_URL = '<?php echo $baseUrl; ?>';
        const CSRF_TOKEN = '<?php echo $csrfToken; ?>';
        const AGE_BANDS = ['all', '0_3', '3_6', '6_12', '12_18'];
        const DOC_LIBRARIES = ['rules', 'parent', 'age_content', 'mixed'];
        const DOC_AUDIENCES = ['system', 'parent', 'child', 'teacher', 'mixed'];
        const CHUNK_AUDIENCES = ['system', 'parent', 'child', 'teacher'];
        const DOC_VISIBILITIES = ['system_only', 'parent_only', 'retrieval_visible', 'mixed'];
        const CHUNK_VISIBILITIES = ['system_only', 'parent_only', 'retrieval_visible', 'blocked'];
        const REVIEW_STATUSES = ['auto_accepted', 'needs_review', 'blocked'];
        const RISK_LEVELS = ['low', 'medium', 'high'];
        const MAX_BATCH_UPLOAD_SIZE = 60 * 1024 * 1024;

        const state = {
            docs: [],
            docsLoaded: false,
            currentDoc: null,
            currentDocId: null,
            currentChunks: [],
            currentChunkDetail: null,
            serviceOnline: false,
            serviceApiCompatible: false,
            serviceVersion: '',
            serviceMissingEndpoints: [],
            maxFileSizeFormatted: '20MB',
            maxFileSize: 20 * 1024 * 1024,
            view: 'documents',
            advancedMode: false,
            upload: {
                items: [],
                submitting: false,
                totalProgress: 0,
                uploadedBytes: 0,
                lastUploadedDocId: null,
            },
            docFilters: {
                search: '',
                audience: '',
                reviewStatus: '',
            },
            chunkFilters: {
                search: '',
                visibility: '',
                audience: '',
                ageBand: '',
                retrievalEnabled: '',
                sortBy: 'confidence',
                sortDir: 'asc',
            },
            selectedChunkIds: [],
            statusPollTimer: null,
        };

        const appRoot = document.getElementById('appRoot');
        const globalAlert = document.getElementById('globalAlert');
        const serviceStatus = document.getElementById('serviceStatus');
        const chunkModal = document.getElementById('chunkModal');
        const chunkModalBody = document.getElementById('chunkModalBody');
        const closeChunkModalBtn = document.getElementById('closeChunkModalBtn');

        document.addEventListener('DOMContentLoaded', init);

        function init() {
            bindTopTabs();
            closeChunkModalBtn.addEventListener('click', closeChunkModal);
            chunkModal.addEventListener('click', event => {
                if (event.target === chunkModal) {
                    closeChunkModal();
                }
            });

            window.addEventListener('hashchange', handleRoute);
            Promise.allSettled([checkServiceHealth(), loadDocuments()]).finally(() => handleRoute());
            setInterval(checkServiceHealth, 30000);
        }

        function bindTopTabs() {
            document.querySelectorAll('.kb-tab').forEach(button => {
                button.addEventListener('click', () => {
                    const view = button.dataset.view;
                    navigate(view);
                });
            });
        }

        function handleRoute() {
            clearStatusPolling();
            const route = parseHash();
            state.view = route.view;
            state.currentDocId = route.docId || null;
            updateTopTabs(route.view);

            if (route.view === 'documents') {
                renderDocumentsView();
                return;
            }

            if (route.view === 'upload') {
                renderUploadView();
                return;
            }

            if (!route.docId) {
                navigate('documents');
                return;
            }

            if (route.view === 'process') {
                renderProcessView(route.docId);
                return;
            }

            if (route.view === 'confirm') {
                renderDocumentWorkspace(route.docId, 'confirm');
                return;
            }

            if (route.view === 'detail') {
                renderDocumentWorkspace(route.docId, 'detail');
                return;
            }

            if (route.view === 'chunks') {
                renderChunkReviewView(route.docId);
                return;
            }

            navigate('documents');
        }

        function parseHash() {
            const hash = window.location.hash.replace(/^#/, '').trim();
            if (!hash) {
                return {
                    view: 'documents',
                    docId: null,
                };
            }

            const parts = hash.split('/');
            return {
                view: parts[0] || 'documents',
                docId: parts[1] || null,
            };
        }

        function navigate(view, docId = null) {
            const target = docId ? `${view}/${docId}` : view;
            if (window.location.hash.replace(/^#/, '') === target) {
                handleRoute();
                return;
            }
            window.location.hash = target;
        }

        function updateTopTabs(activeView) {
            document.querySelectorAll('.kb-tab').forEach(button => {
                const isActive = button.dataset.view === activeView || (activeView !== 'documents' && button.dataset.view === 'documents' && ['confirm', 'detail', 'process', 'chunks'].includes(activeView));
                button.classList.toggle('active', isActive);
            });
        }

        async function apiGet(path) {
            const response = await fetch(BASE_URL + path, {
                credentials: 'same-origin'
            });
            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.error || data.detail || 'Request failed');
            }
            return data;
        }

        async function apiPost(path, payload) {
            const response = await fetch(BASE_URL + path, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                body: JSON.stringify({
                    ...payload,
                    csrf_token: CSRF_TOKEN
                })
            });
            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.error || data.detail || 'Request failed');
            }
            return data;
        }

        async function apiForm(path, formData) {
            const response = await fetch(BASE_URL + path, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });
            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.error || data.detail || 'Request failed');
            }
            return data;
        }

        async function checkServiceHealth() {
            try {
                const data = await apiGet('api/knowledge/health');
                state.serviceOnline = !!data.service_running;
                state.serviceApiCompatible = !!data.api_compatible;
                state.serviceVersion = data.service_version || '';
                state.serviceMissingEndpoints = Array.isArray(data.missing_endpoints) ? data.missing_endpoints : [];
                state.maxFileSize = data.config?.max_file_size || state.maxFileSize;
                state.maxFileSizeFormatted = data.config?.max_file_size_formatted || state.maxFileSizeFormatted;
                updateServiceStatus();
            } catch (error) {
                state.serviceOnline = false;
                state.serviceApiCompatible = false;
                state.serviceVersion = '';
                state.serviceMissingEndpoints = [];
                updateServiceStatus();
            }
        }

        function updateServiceStatus() {
            const statusClass = !state.serviceOnline ? 'offline' : (state.serviceApiCompatible ? 'online' : 'incompatible');
            const statusText = !state.serviceOnline
                ? '知识库服务离线'
                : (state.serviceApiCompatible
                    ? `知识库服务在线${state.serviceVersion ? ` · v${escapeHtml(state.serviceVersion)}` : ''}`
                    : '知识库服务在线，但仍是旧接口版本');
            serviceStatus.className = `kb-status ${statusClass}`;
            serviceStatus.innerHTML = `
                <span class="kb-status-dot"></span>
                <span>${statusText}</span>
            `;
            syncGlobalServiceAlert();
        }

        function showAlert(message, type = 'warning') {
            globalAlert.className = `kb-alert ${type} show`;
            globalAlert.innerHTML = `<i class="fas ${type === 'error' ? 'fa-circle-xmark' : 'fa-circle-info'}"></i><span>${escapeHtml(message)}</span>`;
        }

        function clearAlert() {
            globalAlert.className = 'kb-alert warning';
            globalAlert.innerHTML = '';
            syncGlobalServiceAlert();
        }

        function syncGlobalServiceAlert() {
            if (state.serviceOnline && !state.serviceApiCompatible) {
                const missing = state.serviceMissingEndpoints.slice(0, 4).join(', ');
                showAlert(
                    `当前运行中的知识库服务仍是旧接口版本，管理员知识库页需要的新接口尚未全部生效。请重启/部署 services/chroma 当前代码。${missing ? ` 缺失示例：${missing}` : ''}`,
                    'warning'
                );
            }
        }

        function ensureCompatibleService(featureLabel) {
            if (!state.serviceOnline) {
                showAlert(`知识库服务离线，无法执行${featureLabel}。`, 'error');
                return false;
            }
            if (!state.serviceApiCompatible) {
                const missing = state.serviceMissingEndpoints.slice(0, 6).join(', ');
                showAlert(
                    `当前运行中的知识库服务仍缺少管理员页所需接口，无法执行${featureLabel}。请先重启/部署新版 services/chroma 服务。${missing ? ` 缺失接口：${missing}` : ''}`,
                    'warning'
                );
                return false;
            }
            return true;
        }

        async function loadDocuments() {
            try {
                const query = new URLSearchParams();
                if (state.docFilters.search) query.set('search', state.docFilters.search);
                if (state.docFilters.audience) query.set('audience', state.docFilters.audience);
                if (state.docFilters.reviewStatus) query.set('review_status', state.docFilters.reviewStatus);

                const path = query.toString()
                    ? `api/knowledge/files?${query.toString()}`
                    : 'api/knowledge/files';
                const data = await apiGet(path);
                state.docs = Array.isArray(data.files) ? data.files : [];
                state.docsLoaded = true;
                return state.docs;
            } catch (error) {
                state.docs = [];
                state.docsLoaded = true;
                showAlert(error.message, 'error');
                return [];
            }
        }

        async function loadDocument(docId) {
            const data = await apiGet(`api/knowledge/files/${encodeURIComponent(docId)}`);
            state.currentDoc = data;
            return data;
        }

        async function loadDocumentStatus(docId) {
            return apiGet(`api/knowledge/files/${encodeURIComponent(docId)}/status`);
        }

        async function loadChunks(docId) {
            const query = new URLSearchParams();
            if (state.chunkFilters.search) query.set('search', state.chunkFilters.search);
            if (state.chunkFilters.visibility) query.set('visibility', state.chunkFilters.visibility);
            if (state.chunkFilters.audience) query.set('audience', state.chunkFilters.audience);
            if (state.chunkFilters.ageBand) query.set('age_band', state.chunkFilters.ageBand);
            if (state.chunkFilters.retrievalEnabled !== '') query.set('retrieval_enabled', state.chunkFilters.retrievalEnabled);
            query.set('sort_by', state.chunkFilters.sortBy);
            query.set('sort_dir', state.chunkFilters.sortDir);
            const path = `api/knowledge/files/${encodeURIComponent(docId)}/chunks?${query.toString()}`;
            const data = await apiGet(path);
            state.currentChunks = Array.isArray(data.chunks) ? data.chunks : [];
            return data;
        }

        function renderDocumentsView() {
            clearAlert();
            const docs = getFilteredDocuments();
            const rows = docs.map(doc => renderDocRow(doc)).join('');

            appRoot.innerHTML = `
                <section class="kb-card">
                    <div class="kb-section-head">
                        <div>
                            <h2>文档列表</h2>
                            <div class="kb-muted">一个搜索框统一搜索文档标题、文件名和 chunk 关键词；列表只保留受众与审核状态两个筛选项。</div>
                        </div>
                        <div class="kb-actions">
                            <button class="kb-btn secondary" id="refreshDocsBtn"><i class="fas fa-rotate"></i> 刷新</button>
                            <button class="kb-btn primary" id="goUploadBtn"><i class="fas fa-upload"></i> 上传新文档</button>
                        </div>
                    </div>

                    <div class="kb-filter-grid">
                        ${renderInputField('doc-search', '统一搜索', '搜索文档标题、文件名或 chunk 关键词', state.docFilters.search)}
                        ${renderSelectField('doc-audience', 'Audience', ['', ...DOC_AUDIENCES], state.docFilters.audience)}
                        ${renderSelectField('doc-review', 'Review Status', ['', ...REVIEW_STATUSES], state.docFilters.reviewStatus)}
                    </div>
                </section>

                <section class="kb-card">
                    <div class="kb-section-head">
                        <div>
                            <h3>共 ${docs.length} 份文档</h3>
                            <div class="kb-muted">默认按更新时间倒序排列。needs_review 文档会被明显标识。</div>
                        </div>
                    </div>

                    ${state.docsLoaded ? (docs.length ? `
                    <div class="kb-table-wrap">
                        <table class="kb-table">
                            <thead>
                                <tr>
                                    <th>文档</th>
                                    <th>系统字段</th>
                                    <th>状态</th>
                                    <th>更新时间</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>${rows}</tbody>
                        </table>
                    </div>` : `<div class="kb-empty">暂无符合条件的文档。</div>`) : `<div class="kb-loading"><i class="fas fa-spinner"></i> 正在加载文档...</div>`}
                </section>
            `;

            bindDocumentFilters();
            document.getElementById('refreshDocsBtn').addEventListener('click', async () => {
                appRoot.innerHTML = `<div class="kb-card"><div class="kb-loading"><i class="fas fa-spinner"></i> 正在刷新文档列表...</div></div>`;
                await loadDocuments();
                renderDocumentsView();
            });
            document.getElementById('goUploadBtn').addEventListener('click', () => navigate('upload'));
            bindDocRowActions();
        }

        function renderDocRow(doc) {
            const reviewBadge = renderPill(doc.review_status, 'review');
            const parserBadge = renderPill(doc.parser_status, 'status');
            const chunkBadge = renderPill(doc.chunk_status, 'status');
            const embeddingBadge = renderPill(doc.embedding_status, 'status');
            const indexingBadge = renderPill(doc.indexing_status, 'status');
            const visibilityBadge = renderPill(doc.safety_visibility, 'visibility');
            const ageBands = renderArrayPills(doc.age_bands);
            const topics = renderArrayPills(doc.topics);

            return `
                <tr data-doc-id="${escapeHtml(doc.id)}">
                    <td>
                        <div class="kb-inline" style="justify-content: space-between;">
                            <div>
                                <h4 class="kb-doc-title" style="font-size:16px;">${escapeHtml(doc.title || doc.original_filename)}</h4>
                                <div class="kb-doc-meta">${escapeHtml(doc.original_filename)} · ${escapeHtml(formatFileSize(doc.file_size))} · ${doc.chunk_count} chunks</div>
                            </div>
                            ${doc.review_status === 'needs_review' ? '<span class="kb-danger-flag"><i class="fas fa-triangle-exclamation"></i> needs_review</span>' : ''}
                        </div>
                    </td>
                    <td>
                        <div class="kb-pill-row">${renderPill(doc.library)}${renderPill(doc.audience)}${visibilityBadge}</div>
                        <div class="kb-pill-row" style="margin-top:8px;">${ageBands || '<span class="kb-muted">无年龄段</span>'}</div>
                        <div class="kb-doc-meta" style="margin-top:8px;">Source org: ${escapeHtml(doc.source_org || 'Unknown')}</div>
                        <div class="kb-pill-row" style="margin-top:8px;">${topics || '<span class="kb-muted">无主题</span>'}</div>
                    </td>
                    <td>
                        <div class="kb-pill-row">${reviewBadge}${parserBadge}${chunkBadge}${embeddingBadge}${indexingBadge}</div>
                        ${doc.error_message ? `<div class="kb-doc-meta" style="margin-top:8px; color:#b42318;">失败原因：${escapeHtml(doc.error_message)}</div>` : ''}
                    </td>
                    <td>
                        <div>${escapeHtml(formatDate(doc.updated_at || doc.upload_time))}</div>
                        <div class="kb-doc-meta">Indexed: ${escapeHtml(doc.last_indexed_at ? formatDate(doc.last_indexed_at) : '未索引')}</div>
                    </td>
                    <td>
                        <div class="kb-actions">
                            <button class="kb-btn secondary" data-doc-action="confirm" data-doc-id="${escapeHtml(doc.id)}"><i class="fas fa-wand-magic-sparkles"></i> 分析结果</button>
                            <button class="kb-btn ghost" data-doc-action="detail" data-doc-id="${escapeHtml(doc.id)}"><i class="fas fa-file-lines"></i> 详情</button>
                            <button class="kb-btn ghost" data-doc-action="chunks" data-doc-id="${escapeHtml(doc.id)}"><i class="fas fa-layer-group"></i> Chunk 审查</button>
                            <button class="kb-btn danger" data-doc-action="delete" data-doc-id="${escapeHtml(doc.id)}" data-doc-title="${escapeHtml(doc.title || doc.original_filename)}"><i class="fas fa-trash"></i> 删除</button>
                        </div>
                    </td>
                </tr>
            `;
        }

        function bindDocumentFilters() {
            const map = [
                ['doc-search', 'search'],
                ['doc-audience', 'audience'],
                ['doc-review', 'reviewStatus'],
            ];

            map.forEach(([id, key]) => {
                const element = document.getElementById(id);
                const eventName = element.tagName === 'INPUT' ? 'input' : 'change';
                element.addEventListener(eventName, async event => {
                    state.docFilters[key] = event.target.value;
                    state.docsLoaded = false;
                    renderDocumentsView();
                    await loadDocuments();
                    renderDocumentsView();
                });
            });
        }

        function bindDocRowActions() {
            document.querySelectorAll('[data-doc-action]').forEach(button => {
                button.addEventListener('click', async event => {
                    const docId = event.currentTarget.dataset.docId;
                    const action = event.currentTarget.dataset.docAction;

                    if (action === 'delete') {
                        const title = event.currentTarget.dataset.docTitle || '该文档';
                        if (!confirm(`确认删除“${title}”？此操作会删除文档、chunk 与向量索引。`)) {
                            return;
                        }
                        try {
                            await apiPost('api/knowledge/delete', {
                                file_id: docId
                            });
                            await loadDocuments();
                            renderDocumentsView();
                        } catch (error) {
                            showAlert(error.message, 'error');
                        }
                        return;
                    }

                    navigate(action, docId);
                });
            });
        }

        function renderUploadView() {
            clearAlert();
            const serviceReady = state.serviceOnline && state.serviceApiCompatible;
            const disabled = !serviceReady ? 'disabled' : '';
            const totalSize = getUploadQueueTotalSize();
            const totalFiles = state.upload.items.length;
            const queueRows = state.upload.items.map(item => renderUploadQueueRow(item)).join('');
            const uploadLabel = state.upload.submitting ? '上传中...' : '开始上传';
            const uploadIcon = state.upload.submitting ? 'fa-spinner fa-spin' : 'fa-cloud-arrow-up';
            const overallProgress = Math.max(0, Math.min(100, state.upload.totalProgress || 0));

            appRoot.innerHTML = `
                <section class="kb-card">
                    <div class="kb-section-head">
                        <div>
                            <h2>极简上传</h2>
                            <div class="kb-muted">上传页首屏只保留 file 与 title 两个输入。其余字段由系统自动分析生成。</div>
                        </div>
                    </div>

                    <div class="kb-note">
                        上传成功后会自动进入系统分析流程：原文件保存、文档解析、文本切片、自动分类、自动生成年龄段/受众/可见性/主题、embedding 建立与索引入库。
                    </div>

                    ${serviceReady ? '' : `
                        <div class="kb-alert warning show" style="margin-top:18px;">
                            <i class="fas fa-circle-info"></i>
                            <span>当前知识库服务未处于管理员页所需的新版接口状态。上传按钮已禁用，避免只保存文件而不进入完整分析链路。</span>
                        </div>
                    `}

                    <div class="kb-form-grid" style="margin-top:18px;">
                        <div class="kb-field">
                            <label for="uploadFileInput">文件 file</label>
                            <input type="file" id="uploadFileInput" accept=".pdf,.txt,.doc,.docx,.md,.html,.htm" multiple ${disabled} ${state.upload.submitting ? 'disabled' : ''}>
                            <div class="kb-muted">支持 PDF / TXT / DOCX / MD / HTML。单文件最大 ${escapeHtml(state.maxFileSizeFormatted)}，单次批量总大小最大 ${escapeHtml(formatFileSize(MAX_BATCH_UPLOAD_SIZE))}。</div>
                        </div>
                    </div>

                    <div class="kb-upload-summary">
                        <div>
                            <strong style="display:block;color:#102a43;">待上传队列</strong>
                            <div class="kb-doc-meta">${totalFiles} 个文件 · ${escapeHtml(formatFileSize(totalSize))}</div>
                            <div class="kb-upload-status">${state.upload.submitting ? '上传期间不可重复点击开始上传。' : '每个文件只允许修改 title，待上传文件可点 X 移除。'}</div>
                        </div>
                        <div class="kb-actions">
                            <button class="kb-btn ghost" id="clearUploadQueueBtn" ${state.upload.submitting || !totalFiles ? 'disabled' : ''}>
                                <i class="fas fa-xmark"></i> 清空队列
                            </button>
                            <button class="kb-btn primary" id="submitUploadBtn" ${disabled || !totalFiles || state.upload.submitting ? 'disabled' : ''}>
                                <i class="fas ${uploadIcon}"></i>
                                ${uploadLabel}
                            </button>
                        </div>
                    </div>

                    ${totalFiles ? `
                        <div class="kb-card" style="margin-top:18px; padding:16px; background:#f8fbff;">
                            <div>
                                <strong style="display:block;color:#102a43;">总体进度</strong>
                                <div class="kb-doc-meta">${overallProgress.toFixed(0)}%</div>
                            </div>
                            <div class="kb-upload-progress ${state.upload.submitting ? '' : (overallProgress === 100 && totalFiles ? 'success' : '')}" style="margin-top:12px;">
                                <span style="width:${overallProgress}%;"></span>
                            </div>
                        </div>
                    ` : ''}

                    <div class="kb-upload-list">
                        ${queueRows || '<div class="kb-empty">还没有待上传文件。可一次选择多个文件加入队列。</div>'}
                    </div>
                </section>
            `;

            const uploadFileInput = document.getElementById('uploadFileInput');
            const submitUploadBtn = document.getElementById('submitUploadBtn');
            const clearUploadQueueBtn = document.getElementById('clearUploadQueueBtn');

            uploadFileInput.addEventListener('change', event => {
                addUploadFiles([...event.target.files]);
                renderUploadView();
            });

            submitUploadBtn.addEventListener('click', submitUpload);
            if (clearUploadQueueBtn) {
                clearUploadQueueBtn.addEventListener('click', () => {
                    state.upload.items = [];
                    state.upload.totalProgress = 0;
                    state.upload.uploadedBytes = 0;
                    renderUploadView();
                });
            }

            document.querySelectorAll('[data-upload-title]').forEach(input => {
                input.addEventListener('input', event => {
                    const clientId = event.target.dataset.uploadTitle;
                    const item = state.upload.items.find(entry => entry.clientId === clientId);
                    if (!item) return;
                    item.title = event.target.value;
                });
            });

            document.querySelectorAll('[data-remove-upload]').forEach(button => {
                button.addEventListener('click', event => {
                    const clientId = event.currentTarget.dataset.removeUpload;
                    removeUploadFile(clientId);
                    renderUploadView();
                });
            });

            document.querySelectorAll('[data-open-upload-doc]').forEach(button => {
                button.addEventListener('click', event => {
                    const docId = event.currentTarget.dataset.openUploadDoc;
                    const targetView = event.currentTarget.dataset.openUploadView || 'process';
                    navigate(targetView, docId);
                });
            });
        }

        async function submitUpload() {
            if (!ensureCompatibleService('上传并自动分析')) {
                return;
            }
            if (!state.upload.items.length) {
                showAlert('请先选择至少一个文件。', 'warning');
                return;
            }

            if (state.upload.items.some(item => !item.title.trim())) {
                showAlert('每个待上传文件都需要非空 title。', 'warning');
                return;
            }

            state.upload.submitting = true;
            state.upload.totalProgress = 0;
            state.upload.uploadedBytes = 0;
            renderUploadView();

            try {
                const totalBytes = getUploadQueueTotalSize();
                const successfulDocs = [];

                for (const item of state.upload.items) {
                    if (item.status === 'success' || item.status === 'duplicate') {
                        continue;
                    }

                    item.status = 'uploading';
                    item.progress = 0;
                    item.error = '';
                    renderUploadView();

                    try {
                        const data = await uploadQueueItem(item, totalBytes);
                        item.progress = 100;
                        item.response = data;
                        item.docId = data.file?.id || null;
                        item.status = data.duplicate ? 'duplicate' : 'success';
                        if (item.docId) {
                            successfulDocs.push({
                                id: item.docId,
                                view: data.duplicate ? 'confirm' : 'process'
                            });
                            state.upload.lastUploadedDocId = item.docId;
                        }
                    } catch (error) {
                        item.status = 'error';
                        item.error = error.message || '上传失败';
                    } finally {
                        state.upload.uploadedBytes += item.file.size;
                        state.upload.totalProgress = totalBytes ? Math.min(100, (state.upload.uploadedBytes / totalBytes) * 100) : 100;
                        renderUploadView();
                    }
                }

                state.upload.submitting = false;
                renderUploadView();
                await loadDocuments();

                const hasError = state.upload.items.some(item => item.status === 'error');
                const successCount = state.upload.items.filter(item => item.status === 'success' || item.status === 'duplicate').length;

                if (successCount === 1 && !hasError && successfulDocs[0]) {
                    navigate(successfulDocs[0].view, successfulDocs[0].id);
                    return;
                }

                if (successCount) {
                    showAlert(hasError ? '批量上传已完成，部分文件失败，请检查队列状态。' : '批量上传完成，可在队列中进入各文件的分析流程。', hasError ? 'warning' : 'warning');
                } else {
                    showAlert('上传失败，请检查队列状态。', 'error');
                }
            } catch (error) {
                state.upload.submitting = false;
                showAlert(error.message, 'error');
                renderUploadView();
            }
        }

        async function renderProcessView(docId) {
            clearAlert();
            if (!ensureCompatibleService('上传后的状态查询')) {
                appRoot.innerHTML = `<section class="kb-card"><div class="kb-empty">当前服务版本不支持新版状态轮询接口。</div></section>`;
                return;
            }
            appRoot.innerHTML = `<section class="kb-card"><div class="kb-loading"><i class="fas fa-spinner"></i> 正在读取分析状态...</div></section>`;
            try {
                const data = await loadDocumentStatus(docId);
                appRoot.innerHTML = `
                    <section class="kb-card">
                        <div class="kb-section-head">
                            <div>
                                <h2>系统分析流程</h2>
                                <div class="kb-muted">支持轮询，刷新页面后仍可继续查看同一文档的状态。</div>
                            </div>
                            <div class="kb-actions">
                                <button class="kb-btn ghost" id="refreshStatusBtn"><i class="fas fa-rotate"></i> 刷新状态</button>
                                <button class="kb-btn secondary" id="openDocDetailBtn"><i class="fas fa-file-lines"></i> 文档详情</button>
                            </div>
                        </div>

                        <div class="kb-summary-grid">
                            <div class="kb-stat">
                                <span class="kb-muted">标题</span>
                                <strong style="font-size:18px;">${escapeHtml(data.file.title || data.file.original_filename)}</strong>
                            </div>
                            <div class="kb-stat">
                                <span class="kb-muted">审核状态</span>
                                <strong style="font-size:18px;">${escapeHtml(data.file.review_status)}</strong>
                            </div>
                            <div class="kb-stat">
                                <span class="kb-muted">Parser / Chunk</span>
                                <strong style="font-size:18px;">${escapeHtml(data.file.parser_status)} / ${escapeHtml(data.file.chunk_status)}</strong>
                            </div>
                            <div class="kb-stat">
                                <span class="kb-muted">Embedding / Indexing</span>
                                <strong style="font-size:18px;">${escapeHtml(data.file.embedding_status)} / ${escapeHtml(data.file.indexing_status)}</strong>
                            </div>
                            <div class="kb-stat">
                                <span class="kb-muted">Chunk 数</span>
                                <strong>${data.file.chunk_count || 0}</strong>
                            </div>
                        </div>

                        <div class="kb-summary-grid" style="margin-top:14px;">
                            ${renderReadOnlyStat('当前阶段', escapeHtml(stageLabelByKey(data.current_stage)))}
                            ${renderReadOnlyStat('review_status', renderPill(data.file.review_status, 'review'))}
                            ${renderReadOnlyStat('parser_status', renderPill(data.file.parser_status, 'status'))}
                            ${renderReadOnlyStat('chunk_status', renderPill(data.file.chunk_status, 'status'))}
                            ${renderReadOnlyStat('embedding_status', renderPill(data.file.embedding_status, 'status'))}
                            ${renderReadOnlyStat('indexing_status', renderPill(data.file.indexing_status, 'status'))}
                        </div>

                        <div class="kb-stage-list">
                            ${data.stages.map(stage => `
                                <div class="kb-stage ${stage.status}">
                                    <span class="kb-stage-bullet"></span>
                                    <div>
                                        <strong>${escapeHtml(stage.label)}</strong>
                                        <div class="kb-doc-meta">${escapeHtml(stage.status)}</div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>

                        ${data.error_message ? `
                            <div class="kb-alert error show" style="margin-top:18px;">
                                <i class="fas fa-circle-xmark"></i>
                                <span>${escapeHtml(data.error_message)}</span>
                            </div>` : ''}

                        ${data.failed ? `
                            <div class="kb-alert error show" style="margin-top:18px;">
                                <i class="fas fa-circle-xmark"></i>
                                <span>分析失败，请进入文档详情查看失败摘要并决定是否重新分析。</span>
                            </div>` : ''}
                    </section>
                `;

                document.getElementById('refreshStatusBtn').addEventListener('click', () => renderProcessView(docId));
                document.getElementById('openDocDetailBtn').addEventListener('click', () => navigate(data.completed ? 'confirm' : 'detail', docId));

                if (!data.completed && !data.failed) {
                    state.statusPollTimer = setTimeout(() => renderProcessView(docId), 3000);
                } else if (data.completed) {
                    state.statusPollTimer = setTimeout(() => navigate('confirm', docId), 1200);
                }
            } catch (error) {
                showAlert(error.message, 'error');
                appRoot.innerHTML = `<section class="kb-card"><div class="kb-empty">无法读取分析状态。</div></section>`;
            }
        }

        async function renderDocumentWorkspace(docId, mode) {
            clearAlert();
            if (!ensureCompatibleService(mode === 'confirm' ? '查看分析结果' : '查看文档详情')) {
                appRoot.innerHTML = `<section class="kb-card"><div class="kb-empty">当前服务版本不支持新版文档详情接口。</div></section>`;
                return;
            }
            appRoot.innerHTML = `<section class="kb-card"><div class="kb-loading"><i class="fas fa-spinner"></i> 正在读取文档详情...</div></section>`;
            try {
                const data = await loadDocument(docId);
                const file = data.file;
                const documentData = data.document;
                const titleFieldId = `title-${mode}`;

                appRoot.innerHTML = `
                    <section class="kb-card">
                        <div class="kb-section-head">
                            <div>
                                <h2>${mode === 'confirm' ? '分析结果确认页' : '文档详情页'}</h2>
                                <div class="kb-muted">${mode === 'confirm' ? '默认简洁模式，管理员查看系统建议并确认。' : '完整查看原始文件信息、自动字段、状态与运维动作。'}</div>
                            </div>
                            <div class="kb-actions">
                                <label class="kb-toggle">
                                    <input type="checkbox" id="advancedModeToggle" ${state.advancedMode ? 'checked' : ''}>
                                    高级模式
                                </label>
                                <button class="kb-btn ghost" id="backToDocsBtn"><i class="fas fa-list"></i> 返回列表</button>
                                <button class="kb-btn secondary" id="openChunksBtn"><i class="fas fa-layer-group"></i> Chunk 审查</button>
                            </div>
                        </div>

                        <div class="kb-note">
                            由系统自动分析生成。文档级 <span class="kb-mono">safety_visibility = mixed</span> 只是分析结果，不代表该文档整体可直接给儿童检索；最终仍以 chunk 的 <span class="kb-mono">visibility + retrieval_enabled + age_bands</span> 为准。
                        </div>

                        <div class="kb-summary-grid" style="margin-top:18px;">
                            <div class="kb-stat">
                                <span class="kb-muted">Review Status</span>
                                <strong style="font-size:20px;">${escapeHtml(file.review_status)}</strong>
                            </div>
                            <div class="kb-stat">
                                <span class="kb-muted">Parser / Chunk</span>
                                <strong style="font-size:20px;">${escapeHtml(file.parser_status)} / ${escapeHtml(file.chunk_status)}</strong>
                            </div>
                            <div class="kb-stat">
                                <span class="kb-muted">Embedding / Indexing</span>
                                <strong style="font-size:20px;">${escapeHtml(file.embedding_status)} / ${escapeHtml(file.indexing_status)}</strong>
                            </div>
                            <div class="kb-stat">
                                <span class="kb-muted">Library / Audience</span>
                                <strong style="font-size:20px;">${escapeHtml(file.library)} / ${escapeHtml(file.audience)}</strong>
                            </div>
                            <div class="kb-stat">
                                <span class="kb-muted">Chunk Count</span>
                                <strong>${data.chunk_stats.total}</strong>
                            </div>
                            <div class="kb-stat">
                                <span class="kb-muted">Indexed At</span>
                                <strong style="font-size:18px;">${escapeHtml(file.last_indexed_at ? formatDate(file.last_indexed_at) : '未完成')}</strong>
                            </div>
                        </div>
                    </section>

                    <section class="kb-card">
                        <div class="kb-section-head">
                            <div>
                                <h3>简洁模式</h3>
                                <div class="kb-muted">title 可编辑，其余字段默认展示系统建议。</div>
                            </div>
                            <div class="kb-actions">
                                <button class="kb-btn primary" id="saveTitleBtn"><i class="fas fa-floppy-disk"></i> 保存标题</button>
                                <button class="kb-btn success" id="confirmSuggestionsBtn"><i class="fas fa-check"></i> 一键确认</button>
                                <button class="kb-btn secondary" id="reanalyzeBtn"><i class="fas fa-wand-magic-sparkles"></i> 重新分析</button>
                            </div>
                        </div>

                        <div class="kb-form-grid">
                            <div class="kb-field">
                                <label for="${titleFieldId}">title</label>
                                <input type="text" id="${titleFieldId}" value="${escapeHtml(documentData.title || '')}">
                            </div>
                            <div class="kb-field">
                                <label>review_status</label>
                                <div>${renderPill(file.review_status, 'review')}</div>
                            </div>
                        </div>

                        <div class="kb-summary-grid" style="margin-top:16px;">
                            ${renderReadOnlyStat('source_org', documentData.source_org)}
                            ${renderReadOnlyStat('library', documentData.library)}
                            ${renderReadOnlyStat('audience', documentData.audience)}
                            ${renderReadOnlyStat('age_bands', renderArrayPills(documentData.age_bands) || '无')}
                            ${renderReadOnlyStat('safety_visibility', documentData.safety_visibility)}
                            ${renderReadOnlyStat('topics', renderArrayPills(documentData.topics) || '无')}
                            ${renderReadOnlyStat('summary', escapeHtml(documentData.summary || '无摘要'))}
                            ${renderReadOnlyStat('error_message', escapeHtml(documentData.error_message || '无'))}
                        </div>
                    </section>

                    <section class="kb-card ${state.advancedMode ? '' : 'kb-disabled'}" id="advancedSection">
                        <div class="kb-section-head">
                            <div>
                                <h3>高级模式</h3>
                                <div class="kb-muted">允许修正文档级自动字段，适用于运维和审核场景。</div>
                            </div>
                            <div class="kb-actions">
                                <button class="kb-btn primary" id="saveAdvancedDocBtn" ${state.advancedMode ? '' : 'disabled'}>
                                    <i class="fas fa-pen-to-square"></i> 保存高级修正
                                </button>
                                <button class="kb-btn ghost" id="reparseBtn"><i class="fas fa-file-arrow-up"></i> 重新解析</button>
                                <button class="kb-btn ghost" id="rechunkBtn"><i class="fas fa-scissors"></i> 重新切片</button>
                                <button class="kb-btn ghost" id="reindexBtn"><i class="fas fa-database"></i> 重建索引</button>
                            </div>
                        </div>

                        <div class="kb-form-grid">
                            ${renderSelectField('doc-advanced-library', 'library', DOC_LIBRARIES, documentData.library)}
                            ${renderSelectField('doc-advanced-audience', 'audience', DOC_AUDIENCES, documentData.audience)}
                            ${renderSelectField('doc-advanced-visibility', 'safety_visibility', DOC_VISIBILITIES, documentData.safety_visibility)}
                            ${renderSelectField('doc-advanced-risk', 'risk_level', RISK_LEVELS, documentData.risk_level)}
                            ${renderSelectField('doc-advanced-review', 'review_status', REVIEW_STATUSES, documentData.review_status)}
                            ${renderSelectField('doc-advanced-enabled', 'enabled', ['', 'true', 'false'], String(documentData.enabled))}
                            <div class="kb-field">
                                <label for="doc-advanced-source-org">source_org</label>
                                <input type="text" id="doc-advanced-source-org" value="${escapeHtml(documentData.source_org || 'Unknown')}" ${state.advancedMode ? '' : 'disabled'}>
                            </div>
                            <div class="kb-field">
                                <label for="doc-advanced-topics">topics</label>
                                <input type="text" id="doc-advanced-topics" value="${escapeHtml((documentData.topics || []).join(', '))}" placeholder="逗号分隔" ${state.advancedMode ? '' : 'disabled'}>
                            </div>
                        </div>

                        <div class="kb-field" style="margin-top:16px;">
                            <label>age_bands（多值）</label>
                            <div class="kb-multiselect">${renderAgeBandCheckboxes('doc-advanced-age', documentData.age_bands || [], !state.advancedMode)}</div>
                        </div>

                        <div class="kb-field" style="margin-top:16px;">
                            <label for="doc-advanced-summary">summary</label>
                            <textarea id="doc-advanced-summary" ${state.advancedMode ? '' : 'disabled'}>${escapeHtml(documentData.summary || '')}</textarea>
                        </div>
                    </section>

                    <section class="kb-card">
                        <div class="kb-section-head">
                            <div>
                                <h3>状态与文件信息</h3>
                                <div class="kb-muted">包含原始文件信息、解析状态、系统生成字段与版本信息。</div>
                            </div>
                        </div>

                        <div class="kb-summary-grid">
                            ${renderReadOnlyStat('original_filename', escapeHtml(documentData.original_filename))}
                            ${renderReadOnlyStat('format', escapeHtml(documentData.format))}
                            ${renderReadOnlyStat('language', escapeHtml(documentData.language))}
                            ${renderReadOnlyStat('version', escapeHtml(String(documentData.version)))}
                            ${renderReadOnlyStat('parser_status', renderPill(documentData.parser_status, 'status'))}
                            ${renderReadOnlyStat('chunk_status', renderPill(documentData.chunk_status, 'status'))}
                            ${renderReadOnlyStat('embedding_status', renderPill(documentData.embedding_status, 'status'))}
                            ${renderReadOnlyStat('indexing_status', renderPill(documentData.indexing_status, 'status'))}
                            ${renderReadOnlyStat('review_status', renderPill(documentData.review_status, 'review'))}
                            ${renderReadOnlyStat('last_indexed_at', escapeHtml(documentData.last_indexed_at ? formatDate(documentData.last_indexed_at) : '未完成'))}
                            ${renderReadOnlyStat('error_message', escapeHtml(documentData.error_message || '无'))}
                            ${renderReadOnlyStat('updated_at', escapeHtml(formatDate(documentData.updated_at)))}
                        </div>
                    </section>
                `;

                document.getElementById('advancedModeToggle').addEventListener('change', event => {
                    state.advancedMode = event.target.checked;
                    renderDocumentWorkspace(docId, mode);
                });
                document.getElementById('backToDocsBtn').addEventListener('click', () => navigate('documents'));
                document.getElementById('openChunksBtn').addEventListener('click', () => navigate('chunks', docId));
                document.getElementById('saveTitleBtn').addEventListener('click', async () => {
                    const title = document.getElementById(titleFieldId).value.trim();
                    if (!title) {
                        showAlert('title 不能为空。', 'warning');
                        return;
                    }
                    await saveDocumentUpdates(docId, {
                        title
                    }, () => renderDocumentWorkspace(docId, mode));
                });
                document.getElementById('confirmSuggestionsBtn').addEventListener('click', async () => {
                    if (!confirm('确认将 review_status 标记为 auto_accepted？')) {
                        return;
                    }
                    await saveDocumentUpdates(docId, {
                        review_status: 'auto_accepted'
                    }, () => renderDocumentWorkspace(docId, mode));
                });
                document.getElementById('reanalyzeBtn').addEventListener('click', () => triggerDocumentAction(docId, 'reanalyze', '确认重新分析？这会重新生成系统字段与 chunk 分类。'));
                document.getElementById('reparseBtn').addEventListener('click', () => triggerDocumentAction(docId, 'reparse', '确认重新解析原文件？'));
                document.getElementById('rechunkBtn').addEventListener('click', () => triggerDocumentAction(docId, 'rechunk', '确认重新切片？现有 chunk 审查结果可能被覆盖。'));
                document.getElementById('reindexBtn').addEventListener('click', () => triggerDocumentAction(docId, 'reindex', '确认重建 embedding 与索引？'));
                document.getElementById('saveAdvancedDocBtn').addEventListener('click', async () => {
                    if (!state.advancedMode) {
                        return;
                    }

                    const enabledRaw = document.getElementById('doc-advanced-enabled').value;
                    const payload = {
                        source_org: document.getElementById('doc-advanced-source-org').value.trim(),
                        library: document.getElementById('doc-advanced-library').value,
                        audience: document.getElementById('doc-advanced-audience').value,
                        age_bands: getCheckedValues('doc-advanced-age'),
                        safety_visibility: document.getElementById('doc-advanced-visibility').value,
                        topics: splitCommaValues(document.getElementById('doc-advanced-topics').value),
                        summary: document.getElementById('doc-advanced-summary').value.trim(),
                        risk_level: document.getElementById('doc-advanced-risk').value,
                        review_status: document.getElementById('doc-advanced-review').value,
                    };

                    if (enabledRaw === 'true') payload.enabled = true;
                    if (enabledRaw === 'false') payload.enabled = false;

                    await saveDocumentUpdates(docId, payload, () => renderDocumentWorkspace(docId, mode));
                });
            } catch (error) {
                showAlert(error.message, 'error');
                appRoot.innerHTML = `<section class="kb-card"><div class="kb-empty">无法加载文档详情。</div></section>`;
            }
        }

        async function saveDocumentUpdates(docId, payload, onDone) {
            try {
                await apiPost(`api/knowledge/files/${encodeURIComponent(docId)}/update`, payload);
                await loadDocuments();
                if (typeof onDone === 'function') onDone();
            } catch (error) {
                showAlert(error.message, 'error');
            }
        }

        async function triggerDocumentAction(docId, action, confirmText) {
            if (!confirm(confirmText)) {
                return;
            }
            try {
                await apiPost(`api/knowledge/files/${encodeURIComponent(docId)}/actions/${encodeURIComponent(action)}`, {});
                await loadDocuments();
                navigate('process', docId);
            } catch (error) {
                showAlert(error.message, 'error');
            }
        }

        async function renderChunkReviewView(docId) {
            clearAlert();
            if (!ensureCompatibleService('查看 Chunk 审查')) {
                appRoot.innerHTML = `<section class="kb-card"><div class="kb-empty">当前服务版本不支持新版 chunk 审查接口。</div></section>`;
                return;
            }
            appRoot.innerHTML = `<section class="kb-card"><div class="kb-loading"><i class="fas fa-spinner"></i> 正在读取 chunk 列表...</div></section>`;
            try {
                const [docData, chunkData] = await Promise.all([loadDocument(docId), loadChunks(docId)]);
                const file = docData.file;
                const chunks = chunkData.chunks || [];

                appRoot.innerHTML = `
                    <section class="kb-card">
                        <div class="kb-section-head">
                            <div>
                                <h2>Chunk 审查页</h2>
                                <div class="kb-muted">支持低置信度优先排序、visibility / audience / age_bands / retrieval_enabled 过滤和批量修正。</div>
                            </div>
                            <div class="kb-actions">
                                <button class="kb-btn ghost" id="backToDetailBtn"><i class="fas fa-arrow-left"></i> 返回文档</button>
                                <button class="kb-btn secondary" id="refreshChunksBtn"><i class="fas fa-rotate"></i> 刷新 chunk</button>
                            </div>
                        </div>

                        <div class="kb-note">
                            当前文档：<strong>${escapeHtml(file.title || file.original_filename)}</strong>。文档与 chunk 的 age_bands 都是多值数组，前端不会把它当成单值字段处理。
                        </div>
                    </section>

                    <section class="kb-card">
                        <div class="kb-section-head">
                            <div>
                                <h3>筛选与排序</h3>
                                <div class="kb-muted">默认按 confidence 升序，方便优先检查低置信度 chunk。</div>
                            </div>
                        </div>

                        <div class="kb-filter-grid">
                            ${renderInputField('chunk-search', '搜索', '搜索 heading / 内容 / 主题', state.chunkFilters.search)}
                            ${renderSelectField('chunk-visibility', 'visibility', ['', ...CHUNK_VISIBILITIES], state.chunkFilters.visibility)}
                            ${renderSelectField('chunk-audience', 'audience', ['', ...CHUNK_AUDIENCES], state.chunkFilters.audience)}
                            ${renderSelectField('chunk-age', 'age_bands', ['', ...AGE_BANDS], state.chunkFilters.ageBand)}
                            ${renderSelectField('chunk-retrieval', 'retrieval_enabled', ['', '1', '0'], state.chunkFilters.retrievalEnabled)}
                            ${renderSelectField('chunk-sort-by', 'sort_by', ['confidence', 'chunk_index'], state.chunkFilters.sortBy)}
                            ${renderSelectField('chunk-sort-dir', 'sort_dir', ['asc', 'desc'], state.chunkFilters.sortDir)}
                        </div>
                    </section>

                    <section class="kb-card">
                        <div class="kb-section-head">
                            <div>
                                <h3>批量修正</h3>
                                <div class="kb-muted">高级模式下建议修正字段：age_bands、audience、visibility、topics、retrieval_enabled。</div>
                            </div>
                            <div class="kb-actions">
                                <span class="kb-pill">${state.selectedChunkIds.length} selected</span>
                                <button class="kb-btn primary" id="applyBatchBtn" ${state.selectedChunkIds.length ? '' : 'disabled'}><i class="fas fa-pen-to-square"></i> 应用到所选 chunk</button>
                            </div>
                        </div>

                        <div class="kb-batch-grid">
                            ${renderSelectField('batch-audience', 'audience', [''].concat(CHUNK_AUDIENCES), '')}
                            ${renderSelectField('batch-visibility', 'visibility', [''].concat(CHUNK_VISIBILITIES), '')}
                            ${renderSelectField('batch-retrieval-enabled', 'retrieval_enabled', ['', 'true', 'false'], '')}
                            <div class="kb-field">
                                <label for="batch-topics">topics</label>
                                <input type="text" id="batch-topics" placeholder="逗号分隔">
                            </div>
                        </div>

                        <div class="kb-field" style="margin-top:16px;">
                            <label>age_bands（多值）</label>
                            <div class="kb-multiselect">${renderAgeBandCheckboxes('batch-age', [], false)}</div>
                        </div>
                    </section>

                    <section class="kb-card">
                        <div class="kb-section-head">
                            <div>
                                <h3>Chunk 列表</h3>
                                <div class="kb-muted">共 ${chunks.length} 条结果。</div>
                            </div>
                        </div>

                        ${chunks.length ? `
                            <div class="kb-table-wrap">
                                <table class="kb-table">
                                    <thead>
                                        <tr>
                                            <th><input type="checkbox" id="toggleAllChunks"></th>
                                            <th>chunk_index</th>
                                            <th>heading_path</th>
                                            <th>内容预览</th>
                                            <th>系统字段</th>
                                            <th>confidence</th>
                                            <th>操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${chunks.map(chunk => renderChunkRow(chunk)).join('')}
                                    </tbody>
                                </table>
                            </div>` : `<div class="kb-empty">当前文档没有 chunk，可能仍在处理中，或解析失败。</div>`}
                    </section>
                `;

                bindChunkFilterEvents(docId);
                document.getElementById('backToDetailBtn').addEventListener('click', () => navigate('detail', docId));
                document.getElementById('refreshChunksBtn').addEventListener('click', () => renderChunkReviewView(docId));
                const toggleAll = document.getElementById('toggleAllChunks');
                if (toggleAll) {
                    toggleAll.addEventListener('change', event => {
                        if (event.target.checked) {
                            state.selectedChunkIds = chunks.map(chunk => chunk.chunk_id);
                        } else {
                            state.selectedChunkIds = [];
                        }
                        renderChunkReviewView(docId);
                    });
                }

                document.querySelectorAll('[data-chunk-check]').forEach(box => {
                    box.addEventListener('change', event => {
                        const chunkId = event.target.dataset.chunkCheck;
                        if (event.target.checked) {
                            if (!state.selectedChunkIds.includes(chunkId)) state.selectedChunkIds.push(chunkId);
                        } else {
                            state.selectedChunkIds = state.selectedChunkIds.filter(id => id !== chunkId);
                        }
                        renderChunkReviewView(docId);
                    });
                });

                document.querySelectorAll('[data-open-chunk]').forEach(button => {
                    button.addEventListener('click', async event => {
                        const chunkId = event.currentTarget.dataset.openChunk;
                        try {
                            const data = await apiGet(`api/knowledge/files/${encodeURIComponent(docId)}/chunks/${encodeURIComponent(chunkId)}`);
                            state.currentChunkDetail = data.chunk;
                            openChunkModal();
                        } catch (error) {
                            showAlert(error.message, 'error');
                        }
                    });
                });

                document.getElementById('applyBatchBtn').addEventListener('click', async () => {
                    if (!state.selectedChunkIds.length) {
                        showAlert('请至少选择一个 chunk。', 'warning');
                        return;
                    }

                    if (!confirm(`确认批量修正 ${state.selectedChunkIds.length} 个 chunk？`)) {
                        return;
                    }

                    const fields = {};
                    const audience = document.getElementById('batch-audience').value;
                    const visibility = document.getElementById('batch-visibility').value;
                    const retrievalEnabled = document.getElementById('batch-retrieval-enabled').value;
                    const topics = splitCommaValues(document.getElementById('batch-topics').value);
                    const ageBands = getCheckedValues('batch-age');

                    if (audience) fields.audience = audience;
                    if (visibility) fields.visibility = visibility;
                    if (retrievalEnabled === 'true') fields.retrieval_enabled = true;
                    if (retrievalEnabled === 'false') fields.retrieval_enabled = false;
                    if (topics.length) fields.topics = topics;
                    if (ageBands.length) fields.age_bands = ageBands;

                    if (!Object.keys(fields).length) {
                        showAlert('请至少填写一个要修正的字段。', 'warning');
                        return;
                    }

                    try {
                        await apiPost(`api/knowledge/files/${encodeURIComponent(docId)}/chunks/bulk-update`, {
                            chunk_ids: state.selectedChunkIds,
                            fields
                        });
                        state.selectedChunkIds = [];
                        await loadDocuments();
                        renderChunkReviewView(docId);
                    } catch (error) {
                        showAlert(error.message, 'error');
                    }
                });
            } catch (error) {
                showAlert(error.message, 'error');
                appRoot.innerHTML = `<section class="kb-card"><div class="kb-empty">无法加载 chunk 列表。</div></section>`;
            }
        }

        function renderChunkRow(chunk) {
            return `
                <tr>
                    <td><input type="checkbox" data-chunk-check="${escapeHtml(chunk.chunk_id)}" ${state.selectedChunkIds.includes(chunk.chunk_id) ? 'checked' : ''}></td>
                    <td>#${chunk.chunk_index}</td>
                    <td>${escapeHtml(chunk.heading_path_display || 'Root')}</td>
                    <td>
                        <div>${escapeHtml(chunk.content_preview)}</div>
                        <div class="kb-doc-meta">${chunk.char_count} chars · ${chunk.token_count} tokens</div>
                    </td>
                    <td>
                        <div class="kb-pill-row">${renderArrayPills(chunk.age_bands)}${renderPill(chunk.audience)}${renderPill(chunk.visibility, 'visibility')}</div>
                        <div class="kb-pill-row" style="margin-top:8px;">${renderArrayPills(chunk.topics)}</div>
                        <div class="kb-doc-meta" style="margin-top:8px;">retrieval_enabled: ${chunk.retrieval_enabled ? 'true' : 'false'} · risk: ${escapeHtml(chunk.risk_level)}</div>
                    </td>
                    <td>${Number(chunk.confidence).toFixed(2)}</td>
                    <td>
                        <button class="kb-btn ghost" data-open-chunk="${escapeHtml(chunk.chunk_id)}"><i class="fas fa-eye"></i> 查看</button>
                    </td>
                </tr>
            `;
        }

        function bindChunkFilterEvents(docId) {
            const map = [
                ['chunk-search', 'search'],
                ['chunk-visibility', 'visibility'],
                ['chunk-audience', 'audience'],
                ['chunk-age', 'ageBand'],
                ['chunk-retrieval', 'retrievalEnabled'],
                ['chunk-sort-by', 'sortBy'],
                ['chunk-sort-dir', 'sortDir'],
            ];

            map.forEach(([id, key]) => {
                const element = document.getElementById(id);
                const eventName = element.tagName === 'INPUT' ? 'input' : 'change';
                element.addEventListener(eventName, event => {
                    state.chunkFilters[key] = event.target.value;
                    renderChunkReviewView(docId);
                });
            });
        }

        function openChunkModal() {
            const chunk = state.currentChunkDetail;
            if (!chunk) return;

            chunkModalBody.innerHTML = `
                <div class="kb-summary-grid">
                    ${renderReadOnlyStat('chunk_index', `#${escapeHtml(String(chunk.chunk_index))}`)}
                    ${renderReadOnlyStat('heading_path', escapeHtml(chunk.heading_path_display || 'Root'))}
                    ${renderReadOnlyStat('audience', escapeHtml(chunk.audience))}
                    ${renderReadOnlyStat('visibility', renderPill(chunk.visibility, 'visibility'))}
                    ${renderReadOnlyStat('age_bands', renderArrayPills(chunk.age_bands))}
                    ${renderReadOnlyStat('topics', renderArrayPills(chunk.topics))}
                    ${renderReadOnlyStat('risk_level', escapeHtml(chunk.risk_level))}
                    ${renderReadOnlyStat('retrieval_enabled', escapeHtml(chunk.retrieval_enabled ? 'true' : 'false'))}
                    ${renderReadOnlyStat('confidence', escapeHtml(Number(chunk.confidence).toFixed(2)))}
                </div>
                <div class="kb-card" style="margin-top:16px; padding:16px;">
                    <div class="kb-section-head">
                        <h3 style="margin:0;">完整内容</h3>
                    </div>
                    <div style="white-space:pre-wrap; line-height:1.7; color:#243b53;">${escapeHtml(chunk.content || '')}</div>
                </div>
            `;
            chunkModal.classList.add('show');
        }

        function closeChunkModal() {
            chunkModal.classList.remove('show');
        }

        function getFilteredDocuments() {
            return [...state.docs].sort((a, b) => {
                const aTime = new Date(a.updated_at || a.upload_time || 0).getTime();
                const bTime = new Date(b.updated_at || b.upload_time || 0).getTime();
                return bTime - aTime;
            });
        }

        function renderPill(value, kind = '') {
            if (!value && value !== 0) return '';
            return `<span class="kb-pill ${kind ? `${kind}-${escapeHtml(String(value))}` : ''}">${escapeHtml(String(value))}</span>`;
        }

        function renderArrayPills(values) {
            if (!Array.isArray(values) || !values.length) return '';
            return values.map(value => renderPill(value)).join('');
        }

        function renderInputField(id, label, placeholder, value) {
            return `
                <div class="kb-field">
                    <label for="${id}">${label}</label>
                    <input id="${id}" type="text" placeholder="${escapeHtml(placeholder)}" value="${escapeHtml(value || '')}">
                </div>
            `;
        }

        function renderSelectField(id, label, options, value) {
            return `
                <div class="kb-field">
                    <label for="${id}">${label}</label>
                    <select id="${id}">
                        ${options.map(option => {
                            const optionValue = option === '' ? '' : option;
                            const optionLabel = option === '' ? '全部' : option;
                            return `<option value="${escapeHtml(String(optionValue))}" ${String(value) === String(optionValue) ? 'selected' : ''}>${escapeHtml(String(optionLabel))}</option>`;
                        }).join('')}
                    </select>
                </div>
            `;
        }

        function renderAgeBandCheckboxes(prefix, selected, disabled) {
            return AGE_BANDS.map(ageBand => `
                <label class="kb-chip-toggle">
                    <input type="checkbox" data-age-group="${prefix}" value="${ageBand}" ${(selected || []).includes(ageBand) ? 'checked' : ''} ${disabled ? 'disabled' : ''}>
                    <span>${ageBand}</span>
                </label>
            `).join('');
        }

        function getCheckedValues(prefix) {
            return [...document.querySelectorAll(`input[data-age-group="${prefix}"]:checked`)].map(input => input.value);
        }

        function renderReadOnlyStat(label, valueHtml) {
            return `
                <div class="kb-stat">
                    <span class="kb-muted">${label}</span>
                    <div style="margin-top:8px; color:#102a43; line-height:1.7;">${valueHtml}</div>
                </div>
            `;
        }

        function createUploadQueueItem(file) {
            return {
                clientId: `${file.name}-${file.size}-${file.lastModified}-${Math.random().toString(36).slice(2, 8)}`,
                file,
                title: defaultTitleFromFilename(file.name),
                progress: 0,
                status: 'pending',
                error: '',
                response: null,
                docId: null,
            };
        }

        function getUploadQueueTotalSize() {
            return state.upload.items.reduce((total, item) => total + (item.file?.size || 0), 0);
        }

        function addUploadFiles(files) {
            if (state.upload.submitting) {
                return;
            }

            const existingKeys = new Set(state.upload.items.map(item => `${item.file.name}-${item.file.size}-${item.file.lastModified}`));
            let projectedSize = getUploadQueueTotalSize();

            for (const file of files) {
                const ext = `.${(file.name.split('.').pop() || '').toLowerCase()}`;
                const allowed = ['.pdf', '.txt', '.doc', '.docx', '.md', '.html', '.htm'];
                if (!allowed.includes(ext)) {
                    showAlert(`文件 ${file.name} 类型不受支持。`, 'warning');
                    continue;
                }

                if (file.size > state.maxFileSize) {
                    showAlert(`文件 ${file.name} 超过单文件大小限制 ${state.maxFileSizeFormatted}。`, 'warning');
                    continue;
                }

                const key = `${file.name}-${file.size}-${file.lastModified}`;
                if (existingKeys.has(key)) {
                    showAlert(`文件 ${file.name} 已在待上传队列中。`, 'warning');
                    continue;
                }

                if (projectedSize + file.size > MAX_BATCH_UPLOAD_SIZE) {
                    showAlert(`批量上传总大小不能超过 ${formatFileSize(MAX_BATCH_UPLOAD_SIZE)}。`, 'warning');
                    continue;
                }

                state.upload.items.push(createUploadQueueItem(file));
                projectedSize += file.size;
                existingKeys.add(key);
            }
        }

        function removeUploadFile(clientId) {
            if (state.upload.submitting) {
                return;
            }
            state.upload.items = state.upload.items.filter(item => item.clientId !== clientId);
            state.upload.totalProgress = 0;
            state.upload.uploadedBytes = 0;
        }

        function renderUploadQueueRow(item) {
            const done = item.status === 'success' || item.status === 'duplicate';
            const progressClass = item.status === 'error' ? 'error' : (done ? 'success' : '');
            const statusLabelMap = {
                pending: '待上传',
                uploading: '上传中',
                success: '已上传',
                duplicate: '重复文件',
                error: '上传失败',
            };
            const statusLabel = statusLabelMap[item.status] || item.status;
            const openView = item.status === 'duplicate' ? 'confirm' : 'process';

            return `
                <div class="kb-upload-item">
                    <div class="kb-upload-item-head">
                        <div class="kb-upload-file">
                            <strong>${escapeHtml(item.file.name)}</strong>
                            <div class="kb-doc-meta">${escapeHtml(formatFileSize(item.file.size))} · ${escapeHtml(statusLabel)}</div>
                        </div>
                        <div class="kb-actions">
                            ${item.docId ? `<button class="kb-btn ghost" data-open-upload-doc="${escapeHtml(item.docId)}" data-open-upload-view="${openView}"><i class="fas fa-arrow-right"></i> 查看流程</button>` : ''}
                            ${!state.upload.submitting && !done ? `<button class="kb-upload-remove" data-remove-upload="${escapeHtml(item.clientId)}" title="移除待上传文件"><i class="fas fa-xmark"></i></button>` : ''}
                        </div>
                    </div>

                    <div class="kb-field">
                        <label for="upload-title-${escapeHtml(item.clientId)}">title</label>
                        <input type="text" id="upload-title-${escapeHtml(item.clientId)}" data-upload-title="${escapeHtml(item.clientId)}" value="${escapeHtml(item.title)}" placeholder="默认取文件名去扩展名" ${state.upload.submitting || done ? 'disabled' : ''}>
                    </div>

                    <div class="kb-upload-progress ${progressClass}" style="margin-top:12px;">
                        <span style="width:${Math.max(0, Math.min(100, item.progress || 0))}%;"></span>
                    </div>
                    <div class="kb-doc-meta" style="margin-top:8px;">
                        ${Number(item.progress || 0).toFixed(0)}%
                        ${item.error ? ` · ${escapeHtml(item.error)}` : ''}
                    </div>
                </div>
            `;
        }

        function uploadQueueItem(item, totalBytes) {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                const formData = new FormData();
                formData.append('file', item.file);
                formData.append('title', item.title.trim());
                formData.append('csrf_token', CSRF_TOKEN);

                xhr.open('POST', BASE_URL + 'api/knowledge/upload', true);
                xhr.withCredentials = true;
                xhr.setRequestHeader('X-CSRF-TOKEN', CSRF_TOKEN);

                xhr.upload.addEventListener('progress', event => {
                    if (!event.lengthComputable) {
                        return;
                    }

                    item.progress = (event.loaded / event.total) * 100;
                    const uploadedSoFar = state.upload.uploadedBytes + event.loaded;
                    state.upload.totalProgress = totalBytes ? Math.min(100, (uploadedSoFar / totalBytes) * 100) : 0;
                    renderUploadView();
                });

                xhr.onreadystatechange = () => {
                    if (xhr.readyState !== XMLHttpRequest.DONE) {
                        return;
                    }

                    let data = null;
                    const rawText = xhr.responseText || '';
                    try {
                        data = JSON.parse(rawText || '{}');
                    } catch (error) {
                        const compactBody = rawText.replace(/\s+/g, ' ').trim();
                        if (/sign-?in|Authentication required|Session expired/i.test(compactBody)) {
                            reject(new Error('登录状态已失效，请刷新页面后重新登录。'));
                            return;
                        }
                        reject(new Error(`上传接口返回了非 JSON 响应（HTTP ${xhr.status || 0}）：${compactBody.slice(0, 180) || 'empty response'}`));
                        return;
                    }

                    if (xhr.status >= 200 && xhr.status < 300) {
                        resolve(data);
                        return;
                    }

                    reject(new Error(data.error || data.detail || '上传失败'));
                };

                xhr.onerror = () => reject(new Error('网络错误，上传失败'));
                xhr.send(formData);
            });
        }

        function defaultTitleFromFilename(filename) {
            const fallback = 'Untitled Document';
            if (!filename) return fallback;
            const cleaned = filename.replace(/^.*[\\/]/, '');
            const withoutExt = cleaned.replace(/\.[^.]+$/, '').trim();
            return withoutExt || fallback;
        }

        function splitCommaValues(value) {
            return String(value || '')
                .split(',')
                .map(item => item.trim())
                .filter(Boolean);
        }

        function formatFileSize(bytes) {
            if (!Number.isFinite(bytes) || bytes <= 0) return '0 B';
            const units = ['B', 'KB', 'MB', 'GB'];
            const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
            return `${(bytes / Math.pow(1024, index)).toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
        }

        function formatDate(value) {
            if (!value) return '-';
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) return String(value);
            return date.toLocaleString();
        }

        function stageLabelByKey(key) {
            const map = {
                saved: '原文件已保存',
                parser: '文档解析中',
                chunking: '文本切片中',
                classification: '自动分类中',
                metadata: '自动生成年龄段/受众/可见性/主题中',
                embedding: '构建 embedding / 建立索引中',
                done: '分析完成'
            };
            return map[key] || key;
        }

        function extractAgeBandsFromMetadata(metadata) {
            const result = [];
            if (metadata.age_all === 1) result.push('all');
            if (metadata.age_0_3 === 1) result.push('0_3');
            if (metadata.age_3_6 === 1) result.push('3_6');
            if (metadata.age_6_12 === 1) result.push('6_12');
            if (metadata.age_12_18 === 1) result.push('12_18');
            return result;
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function clearStatusPolling() {
            if (state.statusPollTimer) {
                clearTimeout(state.statusPollTimer);
                state.statusPollTimer = null;
            }
        }
    </script>
</body>

</html>
