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

        .kb-alert.success {
            background: #e8f7ee;
            color: #1e7d45;
            border: 1px solid #93d3aa;
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

        .kb-input-wrap {
            position: relative;
        }

        .kb-input-wrap input {
            padding-right: 38px;
        }

        .kb-input-clear {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            width: 24px;
            height: 24px;
            border: none;
            border-radius: 999px;
            background: #d9e2ec;
            color: #243b53;
            font-size: 16px;
            line-height: 1;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 0;
        }

        .kb-input-wrap.has-value .kb-input-clear {
            display: inline-flex;
        }

        .kb-input-clear:hover {
            background: #bcccdc;
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

        .kb-job-card {
            border: 1px solid #dbe5f0;
            background: linear-gradient(180deg, #fbfdff 0%, #f4f8ff 100%);
        }

        .kb-job-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 12px;
            margin-top: 16px;
        }

        .kb-job-metric {
            border: 1px solid #e6edf5;
            border-radius: 12px;
            background: #fff;
            padding: 12px 14px;
        }

        .kb-job-metric strong {
            display: block;
            margin-top: 6px;
            color: #102a43;
            font-size: 20px;
        }

        .kb-log-box {
            margin-top: 16px;
            background: #102a43;
            color: #d9e2ec;
            border-radius: 12px;
            padding: 14px 16px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 12px;
            line-height: 1.6;
            white-space: pre-wrap;
            max-height: 280px;
            overflow: auto;
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
                <p class="breadcrumb">Upload, auto-analyze, and review the AI companion knowledge base for children</p>
            </div>

            <div class="kb-shell">
                <div id="globalAlert" class="kb-alert warning"></div>

                <div class="kb-topbar">
                    <div class="kb-tabs">
                        <button class="kb-tab" data-view="documents">Documents</button>
                        <button class="kb-tab" data-view="upload">Upload</button>
                    </div>

                    <div id="serviceStatus" class="kb-status offline">
                        <span class="kb-status-dot"></span>
                        <span>Checking service...</span>
                    </div>
                </div>

                <div id="appRoot" class="kb-grid"></div>
            </div>
        </div>
    </div>

    <div id="chunkModal" class="kb-modal">
        <div class="kb-modal-card">
            <div class="kb-section-head">
                <h3>Chunk Details</h3>
                <button class="kb-btn ghost" id="closeChunkModalBtn">
                    <i class="fas fa-times"></i> Close
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
            serviceDbHealthy: false,
            serviceDbError: '',
            serviceDbMode: '',
            serviceDbSocket: '',
            serviceDbTables: {
                kb_documents: false,
                kb_chunks: false,
            },
            maxFileSizeFormatted: '20MB',
            maxFileSize: 20 * 1024 * 1024,
            rebuild: {
                status: 'idle',
                message: 'No rebuild job has started.',
                startedAt: null,
                finishedAt: null,
                updatedAt: null,
                pid: null,
                isRunning: false,
                scanned: 0,
                inserted: 0,
                repaired: 0,
                skipped: 0,
                failed: 0,
                lastFile: null,
                logLines: [],
            },
            view: 'documents',
            advancedMode: false,
            upload: {
                items: [],
                submitting: false,
                totalProgress: 0,
                uploadedBytes: 0,
                lastUploadedDocId: null,
            },
            recentUploadDocIds: [],
            recentUploadStatuses: {},
            docFilters: {
                search: '',
                ageBand: '',
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
            rebuildPollTimer: null,
            recentUploadPollTimer: null,
            pendingViewAlert: null,
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
            Promise.allSettled([checkServiceHealth(), loadDocuments(), loadRebuildStatus(true)]).finally(() => {
                syncRebuildPolling();
                handleRoute();
            });
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
            clearRecentUploadPolling();
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
            const rawText = await response.text();
            let data = {};
            try {
                data = rawText ? JSON.parse(rawText) : {};
            } catch (error) {
                const compactBody = rawText.replace(/\s+/g, ' ').trim();
                throw new Error(`API returned a non-JSON response (HTTP ${response.status || 0}): ${compactBody.slice(0, 220) || 'empty response'}`);
            }
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
            const rawText = await response.text();
            let data = {};
            try {
                data = rawText ? JSON.parse(rawText) : {};
            } catch (error) {
                const compactBody = rawText.replace(/\s+/g, ' ').trim();
                if (/sign-?in|Authentication required|Session expired/i.test(compactBody)) {
                    throw new Error('Your session has expired. Refresh the page and sign in again.');
                }
                throw new Error(`API returned a non-JSON response (HTTP ${response.status || 0}): ${compactBody.slice(0, 220) || 'empty response'}`);
            }
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
            const rawText = await response.text();
            let data = {};
            try {
                data = rawText ? JSON.parse(rawText) : {};
            } catch (error) {
                const compactBody = rawText.replace(/\s+/g, ' ').trim();
                throw new Error(`API returned a non-JSON response (HTTP ${response.status || 0}): ${compactBody.slice(0, 220) || 'empty response'}`);
            }
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
                state.serviceDbHealthy = !!(data.database && data.database.ok);
                state.serviceDbError = data.database?.error || '';
                state.serviceDbMode = data.database?.mode || '';
                state.serviceDbSocket = data.database?.resolved_socket || data.database?.socket || '';
                state.serviceDbTables = data.database?.tables || {
                    kb_documents: false,
                    kb_chunks: false,
                };
                state.maxFileSize = data.config?.max_file_size || state.maxFileSize;
                state.maxFileSizeFormatted = data.config?.max_file_size_formatted || state.maxFileSizeFormatted;
                updateServiceStatus();
            } catch (error) {
                state.serviceOnline = false;
                state.serviceApiCompatible = false;
                state.serviceVersion = '';
                state.serviceMissingEndpoints = [];
                state.serviceDbHealthy = false;
                state.serviceDbError = '';
                state.serviceDbMode = '';
                state.serviceDbSocket = '';
                state.serviceDbTables = {
                    kb_documents: false,
                    kb_chunks: false,
                };
                updateServiceStatus();
            }
        }

        function updateServiceStatus() {
            const statusClass = !state.serviceOnline ? 'offline' : (state.serviceApiCompatible ? 'online' : 'incompatible');
            const statusText = !state.serviceOnline
                ? 'Knowledge base service offline'
                : (state.serviceApiCompatible
                    ? `Knowledge base service online${state.serviceVersion ? ` · v${escapeHtml(state.serviceVersion)}` : ''}`
                    : 'Knowledge base service online, but still running an older API version');
            serviceStatus.className = `kb-status ${statusClass}`;
            serviceStatus.innerHTML = `
                <span class="kb-status-dot"></span>
                <span>${statusText}</span>
            `;
            syncGlobalServiceAlert();
        }

        function showAlert(message, type = 'warning') {
            globalAlert.className = `kb-alert ${type} show`;
            const icon = type === 'error'
                ? 'fa-circle-xmark'
                : (type === 'success' ? 'fa-circle-check' : 'fa-circle-info');
            globalAlert.innerHTML = `<i class="fas ${icon}"></i><span>${escapeHtml(message)}</span>`;
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
                    `The running knowledge base service is still using an older API version. Some endpoints required by the admin knowledge page are not available yet. Restart or redeploy the current services/chroma code.${missing ? ` Missing examples: ${missing}` : ''}`,
                    'warning'
                );
                return;
            }

            if (state.serviceOnline && !state.serviceDbHealthy) {
                const tableHint = `kb_documents=${state.serviceDbTables?.kb_documents ? 'yes' : 'no'}, kb_chunks=${state.serviceDbTables?.kb_chunks ? 'yes' : 'no'}`;
                const modeHint = state.serviceDbMode ? ` Connection mode: ${state.serviceDbMode}` : '';
                const socketHint = state.serviceDbSocket ? ` Socket: ${state.serviceDbSocket}` : '';
                showAlert(
                    `The knowledge base service is running, but the MySQL self-check failed. ${state.serviceDbError || 'Check DB_HOST, DB_PORT, DB_USERNAME, DB_PASS, and DB_SOCKET.'}${modeHint}${socketHint}; table status: ${tableHint}`,
                    'error'
                );
            }
        }

        function ensureCompatibleService(featureLabel) {
            if (!state.serviceOnline) {
                showAlert(`The knowledge base service is offline and cannot perform ${featureLabel}.`, 'error');
                return false;
            }
            if (!state.serviceApiCompatible) {
                const missing = state.serviceMissingEndpoints.slice(0, 6).join(', ');
                showAlert(
                    `The running knowledge base service is still missing endpoints required by this admin page, so it cannot perform ${featureLabel}. Restart or redeploy the updated services/chroma service first.${missing ? ` Missing endpoints: ${missing}` : ''}`,
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
                if (state.docFilters.ageBand) query.set('age_band', state.docFilters.ageBand);
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

        async function loadRebuildStatus(silent = false) {
            try {
                const data = await apiGet('api/knowledge/rebuild/status');
                const job = data.job || {};
                state.rebuild = {
                    status: job.status || 'idle',
                    message: job.message || 'No rebuild job has started.',
                    startedAt: job.started_at || null,
                    finishedAt: job.finished_at || null,
                    updatedAt: job.updated_at || null,
                    pid: job.pid || null,
                    isRunning: !!job.is_running,
                    scanned: Number(job.scanned || 0),
                    inserted: Number(job.inserted || 0),
                    repaired: Number(job.repaired || 0),
                    skipped: Number(job.skipped || 0),
                    failed: Number(job.failed || 0),
                    lastFile: job.last_file || null,
                    logLines: Array.isArray(job.log_lines) ? job.log_lines : [],
                };
                syncRebuildPolling();
                return state.rebuild;
            } catch (error) {
                if (!silent) {
                    showAlert(error.message, 'error');
                }
                return state.rebuild;
            }
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
            const rebuildJob = state.rebuild || {};
            const rebuildRunning = !!rebuildJob.isRunning;

            appRoot.innerHTML = `
                <section class="kb-card">
                    <div class="kb-section-head">
                        <div>
                            <h2>Documents</h2>
                            <div class="kb-muted">One search box covers document titles, filenames, and chunk keywords. Only Age Band and Review Status filters are kept.</div>
                        </div>
                        <div class="kb-actions">
                            <button class="kb-btn secondary" id="refreshDocsBtn"><i class="fas fa-rotate"></i> Refresh</button>
                            <button class="kb-btn ghost" id="refreshRebuildStatusBtn"><i class="fas fa-list-check"></i> Refresh Rebuild Status</button>
                            <button class="kb-btn success" id="rebuildKnowledgeBtn" ${rebuildRunning ? 'disabled' : ''}><i class="fas fa-screwdriver-wrench"></i> Rebuild Knowledge Base</button>
                            <button class="kb-btn primary" id="goUploadBtn"><i class="fas fa-upload"></i> Upload New Document</button>
                        </div>
                    </div>

                    <div class="kb-filter-grid">
                        ${renderInputField('doc-search', 'Search', 'Search document titles, filenames, or chunk keywords', state.docFilters.search, {clearable: true, clearTitle: 'Clear search'})}
                        ${renderSelectField('doc-age', 'Age Bands', ['', ...AGE_BANDS], state.docFilters.ageBand)}
                        ${renderSelectField('doc-review', 'Review Status', ['', ...REVIEW_STATUSES], state.docFilters.reviewStatus)}
                    </div>
                </section>

                ${renderRebuildJobCard()}

                <section class="kb-card">
                    <div class="kb-section-head">
                        <div>
                            <h3>${docs.length} documents</h3>
                            <div class="kb-muted">Sorted by updated time in descending order by default. Documents with needs_review are highlighted.</div>
                        </div>
                    </div>

                    ${state.docsLoaded ? (docs.length ? `
                    <div class="kb-table-wrap">
                        <table class="kb-table">
                            <thead>
                                <tr>
                                    <th>Document</th>
                                    <th>System Fields</th>
                                    <th>Status</th>
                                    <th>Updated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>${rows}</tbody>
                        </table>
                    </div>` : `<div class="kb-empty">No documents match the current filters.</div>`) : `<div class="kb-loading"><i class="fas fa-spinner"></i> Loading documents...</div>`}
                </section>
            `;

            bindDocumentFilters();
            document.getElementById('refreshDocsBtn').addEventListener('click', async () => {
                appRoot.innerHTML = `<div class="kb-card"><div class="kb-loading"><i class="fas fa-spinner"></i> Refreshing document list...</div></div>`;
                await loadDocuments();
                renderDocumentsView();
            });
            document.getElementById('refreshRebuildStatusBtn').addEventListener('click', async () => {
                await loadRebuildStatus();
                renderDocumentsView();
            });
            document.getElementById('rebuildKnowledgeBtn').addEventListener('click', startKnowledgeRebuild);
            document.getElementById('goUploadBtn').addEventListener('click', () => navigate('upload'));
            bindDocRowActions();
            flushPendingViewAlert();
            syncRecentUploadPolling();
        }

        function renderRebuildJobCard() {
            const job = state.rebuild || {};
            const status = job.status || 'idle';
            const logOutput = (job.logLines || []).length
                ? escapeHtml(job.logLines.join('\n'))
                : 'No log output yet.';

            return `
                <section class="kb-card kb-job-card">
                    <div class="kb-section-head">
                        <div>
                            <h3>Knowledge Base Rebuild Job</h3>
                            <div class="kb-muted">Use this to restore document records, chunk metadata, and vector indexes from files already stored in uploads when moving to a new environment. Do not upload the same batch again during rebuild.</div>
                        </div>
                        <div class="kb-actions">
                            ${renderRebuildStatusPill(status)}
                            ${job.pid ? `<span class="kb-pill">PID ${escapeHtml(String(job.pid))}</span>` : ''}
                        </div>
                    </div>

                    <div class="kb-note">
                        ${escapeHtml(job.message || 'No rebuild job has started.')}
                        ${job.lastFile ? `<div style="margin-top:8px;"><strong>Last processed:</strong> ${escapeHtml(job.lastFile)}</div>` : ''}
                        <div style="margin-top:8px;">
                            Started: ${escapeHtml(formatDate(job.startedAt))}
                            · Finished: ${escapeHtml(formatDate(job.finishedAt))}
                            · Updated: ${escapeHtml(formatDate(job.updatedAt))}
                        </div>
                    </div>

                    <div class="kb-job-grid">
                        <div class="kb-job-metric"><span class="kb-muted">Scanned Files</span><strong>${escapeHtml(String(job.scanned || 0))}</strong></div>
                        <div class="kb-job-metric"><span class="kb-muted">Inserted</span><strong>${escapeHtml(String(job.inserted || 0))}</strong></div>
                        <div class="kb-job-metric"><span class="kb-muted">Repaired</span><strong>${escapeHtml(String(job.repaired || 0))}</strong></div>
                        <div class="kb-job-metric"><span class="kb-muted">Skipped</span><strong>${escapeHtml(String(job.skipped || 0))}</strong></div>
                        <div class="kb-job-metric"><span class="kb-muted">Failed</span><strong>${escapeHtml(String(job.failed || 0))}</strong></div>
                    </div>

                    <div class="kb-log-box">${logOutput}</div>
                </section>
            `;
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
            const recentUploadBadge = renderRecentUploadBadge(doc.id);
            const recentUploadProgress = renderRecentUploadProgress(doc.id);

            return `
                <tr data-doc-id="${escapeHtml(doc.id)}">
                    <td>
                        <div class="kb-inline" style="justify-content: space-between;">
                            <div>
                                <h4 class="kb-doc-title" style="font-size:16px;">${escapeHtml(doc.title || doc.original_filename)}</h4>
                                <div class="kb-doc-meta">${escapeHtml(doc.original_filename)} · ${escapeHtml(formatFileSize(doc.file_size))} · ${doc.chunk_count} chunks</div>
                            </div>
                            <div class="kb-actions" style="gap:8px;">
                                ${recentUploadBadge}
                                ${doc.review_status === 'needs_review' ? '<span class="kb-danger-flag"><i class="fas fa-triangle-exclamation"></i> needs_review</span>' : ''}
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="kb-pill-row">${renderPill(doc.library)}${renderPill(doc.audience)}${visibilityBadge}</div>
                        <div class="kb-pill-row" style="margin-top:8px;">${ageBands || '<span class="kb-muted">No age bands</span>'}</div>
                        <div class="kb-doc-meta" style="margin-top:8px;">Source org: ${escapeHtml(doc.source_org || 'Unknown')}</div>
                        <div class="kb-pill-row" style="margin-top:8px;">${topics || '<span class="kb-muted">No topics</span>'}</div>
                    </td>
                    <td>
                        <div class="kb-pill-row">${reviewBadge}${parserBadge}${chunkBadge}${embeddingBadge}${indexingBadge}</div>
                        ${recentUploadProgress}
                        ${doc.error_message ? `<div class="kb-doc-meta" style="margin-top:8px; color:#b42318;">Error: ${escapeHtml(doc.error_message)}</div>` : ''}
                    </td>
                    <td>
                        <div>${escapeHtml(formatDate(doc.updated_at || doc.upload_time))}</div>
                        <div class="kb-doc-meta">Indexed: ${escapeHtml(doc.last_indexed_at ? formatDate(doc.last_indexed_at) : 'Not indexed')}</div>
                    </td>
                    <td>
                        <div class="kb-actions">
                            <button class="kb-btn secondary" data-doc-action="confirm" data-doc-id="${escapeHtml(doc.id)}"><i class="fas fa-wand-magic-sparkles"></i> Review Results</button>
                            <button class="kb-btn ghost" data-doc-action="detail" data-doc-id="${escapeHtml(doc.id)}"><i class="fas fa-file-lines"></i> Details</button>
                            <button class="kb-btn ghost" data-doc-action="chunks" data-doc-id="${escapeHtml(doc.id)}"><i class="fas fa-layer-group"></i> Chunk Review</button>
                            <button class="kb-btn danger" data-doc-action="delete" data-doc-id="${escapeHtml(doc.id)}" data-doc-title="${escapeHtml(doc.title || doc.original_filename)}"><i class="fas fa-trash"></i> Delete</button>
                        </div>
                    </td>
                </tr>
            `;
        }

        function bindDocumentFilters() {
            const applyDocFilters = async () => {
                state.docsLoaded = false;
                renderDocumentsView();
                await loadDocuments();
                renderDocumentsView();
            };

            const map = [
                {id: 'doc-search', key: 'search', mode: 'enter'},
                {id: 'doc-age', key: 'ageBand', mode: 'change'},
                {id: 'doc-review', key: 'reviewStatus', mode: 'change'},
            ];

            map.forEach(({id, key, mode}) => {
                const element = document.getElementById(id);
                if (!element) return;

                if (mode === 'enter') {
                    element.addEventListener('keydown', async event => {
                        if (event.key !== 'Enter') return;
                        event.preventDefault();
                        const next = event.target.value.trim();
                        if (state.docFilters[key] === next) return;
                        state.docFilters[key] = next;
                        await applyDocFilters();
                    });

                    const clearButton = document.querySelector(`[data-clear-input="${id}"]`);
                    if (clearButton) {
                        clearButton.addEventListener('click', async () => {
                            if (!state.docFilters[key]) return;
                            state.docFilters[key] = '';
                            await applyDocFilters();
                        });
                    }
                    return;
                }

                element.addEventListener('change', async event => {
                    state.docFilters[key] = event.target.value;
                    await applyDocFilters();
                });
            });
        }

        function bindDocRowActions() {
            document.querySelectorAll('[data-doc-action]').forEach(button => {
                button.addEventListener('click', async event => {
                    const docId = event.currentTarget.dataset.docId;
                    const action = event.currentTarget.dataset.docAction;

                    if (action === 'delete') {
                        const title = event.currentTarget.dataset.docTitle || 'this document';
                        if (!confirm(`Delete "${title}"? This will remove the document, chunks, and vector index.`)) {
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
            const uploadLabel = state.upload.submitting ? 'Uploading...' : 'Start Upload';
            const uploadIcon = state.upload.submitting ? 'fa-spinner fa-spin' : 'fa-cloud-arrow-up';
            const overallProgress = Math.max(0, Math.min(100, state.upload.totalProgress || 0));

            appRoot.innerHTML = `
                <section class="kb-card">
                    <div class="kb-section-head">
                        <div>
                            <h2>Minimal Upload</h2>
                            <div class="kb-muted">The upload screen keeps only file and title in the first step. All other fields are generated automatically by the system.</div>
                        </div>
                    </div>

                    <div class="kb-note">
                        After upload succeeds, the document enters the automated analysis pipeline: file storage, document parsing, text chunking, auto-classification, automatic age band/audience/visibility/topic generation, embedding creation, and index storage.
                    </div>

                    ${serviceReady ? '' : `
                        <div class="kb-alert warning show" style="margin-top:18px;">
                            <i class="fas fa-circle-info"></i>
                            <span>The current knowledge base service is not running the API version required by this admin page. Upload is disabled to avoid saving files without running the full analysis pipeline.</span>
                        </div>
                    `}

                    <div class="kb-form-grid" style="margin-top:18px;">
                        <div class="kb-field">
                            <label for="uploadFileInput">File</label>
                            <input type="file" id="uploadFileInput" accept=".pdf,.txt,.doc,.docx,.md,.html,.htm" multiple ${disabled} ${state.upload.submitting ? 'disabled' : ''}>
                            <div class="kb-muted">Supported: PDF / TXT / DOCX / MD / HTML. Max size per file: ${escapeHtml(state.maxFileSizeFormatted)}. Max total size per batch: ${escapeHtml(formatFileSize(MAX_BATCH_UPLOAD_SIZE))}.</div>
                        </div>
                    </div>

                    <div class="kb-upload-summary">
                        <div>
                            <strong style="display:block;color:#102a43;">Upload Queue</strong>
                            <div class="kb-doc-meta">${totalFiles} file(s) · ${escapeHtml(formatFileSize(totalSize))}</div>
                            <div class="kb-upload-status">${state.upload.submitting ? 'Do not click Start Upload again while an upload is in progress.' : 'Only the title can be edited for each file. Click X to remove a queued file.'}</div>
                        </div>
                        <div class="kb-actions">
                            <button class="kb-btn ghost" id="clearUploadQueueBtn" ${state.upload.submitting || !totalFiles ? 'disabled' : ''}>
                                <i class="fas fa-xmark"></i> Clear Queue
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
                                <strong style="display:block;color:#102a43;">Overall Progress</strong>
                                <div class="kb-doc-meta">${overallProgress.toFixed(0)}%</div>
                            </div>
                            <div class="kb-upload-progress ${state.upload.submitting ? '' : (overallProgress === 100 && totalFiles ? 'success' : '')}" style="margin-top:12px;">
                                <span style="width:${overallProgress}%;"></span>
                            </div>
                        </div>
                    ` : ''}

                    <div class="kb-upload-list">
                        ${queueRows || '<div class="kb-empty">No files are queued yet. You can select multiple files at once.</div>'}
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
            if (!ensureCompatibleService('upload and auto-analyze')) {
                return;
            }
            if (!state.upload.items.length) {
                showAlert('Select at least one file first.', 'warning');
                return;
            }

            if (state.upload.items.some(item => !item.title.trim())) {
                showAlert('Every queued file must have a non-empty title.', 'warning');
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
                                duplicate: !!data.duplicate
                            });
                            state.upload.lastUploadedDocId = item.docId;
                        }
                    } catch (error) {
                        item.status = 'error';
                        item.error = error.message || 'Upload failed';
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
                const newlyUploadedDocIds = successfulDocs
                    .filter(item => !item.duplicate)
                    .map(item => item.id);

                if (newlyUploadedDocIds.length) {
                    trackRecentUploads(newlyUploadedDocIds);
                    await refreshRecentUploadStatuses(true);
                }

                if (successCount) {
                    state.upload.items = hasError
                        ? state.upload.items.filter(item => item.status === 'error')
                        : [];
                    state.upload.totalProgress = 0;
                    state.upload.uploadedBytes = 0;
                    state.pendingViewAlert = {
                        type: hasError ? 'warning' : 'success',
                        message: hasError
                            ? (() => {
                                const firstError = state.upload.items.find(item => item.status === 'error' && item.error)?.error || '';
                                return firstError
                                    ? `Upload finished, but some files failed: ${firstError}`
                                    : 'Upload finished, but some files failed. Check the queue if you want to retry them.';
                            })()
                            : (newlyUploadedDocIds.length
                                ? 'Upload finished. The latest document is now being processed in the document list.'
                                : 'Upload finished. The document list has been refreshed.'),
                    };
                    navigate('documents');
                    return;
                }

                const firstError = state.upload.items.find(item => item.status === 'error' && item.error)?.error || '';
                showAlert(firstError ? `Upload failed: ${firstError}` : 'Upload failed. Check the queue status.', 'error');
            } catch (error) {
                state.upload.submitting = false;
                showAlert(error.message, 'error');
                renderUploadView();
            }
        }

        async function renderProcessView(docId) {
            clearAlert();
            if (!ensureCompatibleService('status polling after upload')) {
                appRoot.innerHTML = `<section class="kb-card"><div class="kb-empty">The current service version does not support the new status polling endpoint.</div></section>`;
                return;
            }
            appRoot.innerHTML = `<section class="kb-card"><div class="kb-loading"><i class="fas fa-spinner"></i> Loading analysis status...</div></section>`;
            try {
                const data = await loadDocumentStatus(docId);
                appRoot.innerHTML = `
                    <section class="kb-card">
                        <div class="kb-section-head">
                            <div>
                                <h2>Analysis Pipeline</h2>
                                <div class="kb-muted">This view supports polling. You can refresh the page and keep tracking the same document.</div>
                            </div>
                            <div class="kb-actions">
                                <button class="kb-btn ghost" id="refreshStatusBtn"><i class="fas fa-rotate"></i> Refresh Status</button>
                                <button class="kb-btn secondary" id="openDocDetailBtn"><i class="fas fa-file-lines"></i> Document Details</button>
                            </div>
                        </div>

                        <div class="kb-summary-grid">
                            <div class="kb-stat">
                                <span class="kb-muted">Title</span>
                                <strong style="font-size:18px;">${escapeHtml(data.file.title || data.file.original_filename)}</strong>
                            </div>
                            <div class="kb-stat">
                                <span class="kb-muted">Review Status</span>
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
                                <span class="kb-muted">Chunk Count</span>
                                <strong>${data.file.chunk_count || 0}</strong>
                            </div>
                        </div>

                        <div class="kb-summary-grid" style="margin-top:14px;">
                            ${renderReadOnlyStat('Current Stage', escapeHtml(stageLabelByKey(data.current_stage)))}
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
                                <span>Analysis failed. Open the document details to review the error summary and decide whether to run it again.</span>
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
                appRoot.innerHTML = `<section class="kb-card"><div class="kb-empty">Unable to load analysis status.</div></section>`;
            }
        }

        async function renderDocumentWorkspace(docId, mode) {
            clearAlert();
            if (!ensureCompatibleService(mode === 'confirm' ? 'viewing review results' : 'viewing document details')) {
                appRoot.innerHTML = `<section class="kb-card"><div class="kb-empty">The current service version does not support the new document detail endpoint.</div></section>`;
                return;
            }
            appRoot.innerHTML = `<section class="kb-card"><div class="kb-loading"><i class="fas fa-spinner"></i> Loading document details...</div></section>`;
            try {
                const data = await loadDocument(docId);
                const file = data.file;
                const documentData = data.document;
                const titleFieldId = `title-${mode}`;

                appRoot.innerHTML = `
                    <section class="kb-card">
                        <div class="kb-section-head">
                            <div>
                                <h2>${mode === 'confirm' ? 'Review Analysis Results' : 'Document Details'}</h2>
                                <div class="kb-muted">${mode === 'confirm' ? 'Basic mode by default. Review the system suggestions and confirm them.' : 'View the original file details, generated fields, statuses, and maintenance actions.'}</div>
                            </div>
                            <div class="kb-actions">
                                <label class="kb-toggle">
                                    <input type="checkbox" id="advancedModeToggle" ${state.advancedMode ? 'checked' : ''}>
                                    Advanced Mode
                                </label>
                                <button class="kb-btn ghost" id="backToDocsBtn"><i class="fas fa-list"></i> Back to List</button>
                                <button class="kb-btn secondary" id="openChunksBtn"><i class="fas fa-layer-group"></i> Chunk Review</button>
                            </div>
                        </div>

                        <div class="kb-note">
                            These values are generated automatically by the system. A document-level <span class="kb-mono">safety_visibility = mixed</span> is only an analysis result. It does not mean the whole document is directly retrievable for children. Final retrieval still depends on each chunk's <span class="kb-mono">visibility + retrieval_enabled + age_bands</span>.
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
                                <strong style="font-size:18px;">${escapeHtml(file.last_indexed_at ? formatDate(file.last_indexed_at) : 'Pending')}</strong>
                            </div>
                        </div>
                    </section>

                    <section class="kb-card">
                        <div class="kb-section-head">
                            <div>
                                <h3>Basic Mode</h3>
                                <div class="kb-muted">The title is editable. Other fields show the system suggestions by default.</div>
                            </div>
                            <div class="kb-actions">
                                <button class="kb-btn primary" id="saveTitleBtn"><i class="fas fa-floppy-disk"></i> Save Title</button>
                                <button class="kb-btn success" id="confirmSuggestionsBtn"><i class="fas fa-check"></i> Accept Suggestions</button>
                                <button class="kb-btn secondary" id="reanalyzeBtn"><i class="fas fa-wand-magic-sparkles"></i> Reanalyze</button>
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
                            ${renderReadOnlyStat('age_bands', renderArrayPills(documentData.age_bands) || 'None')}
                            ${renderReadOnlyStat('safety_visibility', documentData.safety_visibility)}
                            ${renderReadOnlyStat('topics', renderArrayPills(documentData.topics) || 'None')}
                            ${renderReadOnlyStat('summary', escapeHtml(documentData.summary || 'No summary'))}
                            ${renderReadOnlyStat('error_message', escapeHtml(documentData.error_message || 'None'))}
                        </div>
                    </section>

                    <section class="kb-card ${state.advancedMode ? '' : 'kb-disabled'}" id="advancedSection">
                        <div class="kb-section-head">
                            <div>
                                <h3>Advanced Mode</h3>
                                <div class="kb-muted">Adjust document-level generated fields for maintenance and review workflows.</div>
                            </div>
                            <div class="kb-actions">
                                <button class="kb-btn primary" id="saveAdvancedDocBtn" ${state.advancedMode ? '' : 'disabled'}>
                                    <i class="fas fa-pen-to-square"></i> Save Advanced Changes
                                </button>
                                <button class="kb-btn ghost" id="reparseBtn"><i class="fas fa-file-arrow-up"></i> Re-parse</button>
                                <button class="kb-btn ghost" id="rechunkBtn"><i class="fas fa-scissors"></i> Re-chunk</button>
                                <button class="kb-btn ghost" id="reindexBtn"><i class="fas fa-database"></i> Rebuild Index</button>
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
                                <input type="text" id="doc-advanced-topics" value="${escapeHtml((documentData.topics || []).join(', '))}" placeholder="Comma-separated" ${state.advancedMode ? '' : 'disabled'}>
                            </div>
                        </div>

                        <div class="kb-field" style="margin-top:16px;">
                            <label>age_bands (multi-value)</label>
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
                                <h3>Status and File Information</h3>
                                <div class="kb-muted">Includes original file info, processing status, generated fields, and version data.</div>
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
                            ${renderReadOnlyStat('last_indexed_at', escapeHtml(documentData.last_indexed_at ? formatDate(documentData.last_indexed_at) : 'Pending'))}
                            ${renderReadOnlyStat('error_message', escapeHtml(documentData.error_message || 'None'))}
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
                        showAlert('The title cannot be empty.', 'warning');
                        return;
                    }
                    await saveDocumentUpdates(docId, {
                        title
                    }, () => renderDocumentWorkspace(docId, mode));
                });
                document.getElementById('confirmSuggestionsBtn').addEventListener('click', async () => {
                    if (!confirm('Mark review_status as auto_accepted?')) {
                        return;
                    }
                    await saveDocumentUpdates(docId, {
                        review_status: 'auto_accepted'
                    }, () => renderDocumentWorkspace(docId, mode));
                });
                document.getElementById('reanalyzeBtn').addEventListener('click', () => triggerDocumentAction(docId, 'reanalyze', 'Reanalyze this document? This will regenerate system fields and chunk classifications.'));
                document.getElementById('reparseBtn').addEventListener('click', () => triggerDocumentAction(docId, 'reparse', 'Re-parse the original file?'));
                document.getElementById('rechunkBtn').addEventListener('click', () => triggerDocumentAction(docId, 'rechunk', 'Re-chunk this document? Existing chunk review results may be overwritten.'));
                document.getElementById('reindexBtn').addEventListener('click', () => triggerDocumentAction(docId, 'reindex', 'Rebuild embeddings and index?'));
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
                appRoot.innerHTML = `<section class="kb-card"><div class="kb-empty">Unable to load document details.</div></section>`;
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
            if (!ensureCompatibleService('viewing chunk review')) {
                appRoot.innerHTML = `<section class="kb-card"><div class="kb-empty">The current service version does not support the new chunk review endpoint.</div></section>`;
                return;
            }
            appRoot.innerHTML = `<section class="kb-card"><div class="kb-loading"><i class="fas fa-spinner"></i> Loading chunk list...</div></section>`;
            try {
                const [docData, chunkData] = await Promise.all([loadDocument(docId), loadChunks(docId)]);
                const file = docData.file;
                const chunks = chunkData.chunks || [];

                appRoot.innerHTML = `
                    <section class="kb-card">
                        <div class="kb-section-head">
                            <div>
                                <h2>Chunk Review</h2>
                                <div class="kb-muted">Supports low-confidence-first sorting, filtering by visibility / audience / age_bands / retrieval_enabled, and batch updates.</div>
                            </div>
                            <div class="kb-actions">
                                <button class="kb-btn ghost" id="backToDetailBtn"><i class="fas fa-arrow-left"></i> Back to Document</button>
                                <button class="kb-btn secondary" id="refreshChunksBtn"><i class="fas fa-rotate"></i> Refresh Chunks</button>
                            </div>
                        </div>

                        <div class="kb-note">
                            Current document: <strong>${escapeHtml(file.title || file.original_filename)}</strong>. Both document and chunk age_bands are multi-value arrays, and the UI treats them accordingly.
                        </div>
                    </section>

                    <section class="kb-card">
                        <div class="kb-section-head">
                            <div>
                                <h3>Filters and Sorting</h3>
                                <div class="kb-muted">Sorted by confidence ascending by default so low-confidence chunks are reviewed first.</div>
                            </div>
                        </div>

                        <div class="kb-filter-grid">
                            ${renderInputField('chunk-search', 'Search', 'Search heading / content / topics', state.chunkFilters.search, {clearable: true, clearTitle: 'Clear search'})}
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
                                <h3>Batch Update</h3>
                                <div class="kb-muted">Recommended fields for advanced review: age_bands, audience, visibility, topics, retrieval_enabled.</div>
                            </div>
                            <div class="kb-actions">
                                <span class="kb-pill">${state.selectedChunkIds.length} selected</span>
                                <button class="kb-btn primary" id="applyBatchBtn" ${state.selectedChunkIds.length ? '' : 'disabled'}><i class="fas fa-pen-to-square"></i> Apply to Selected Chunks</button>
                            </div>
                        </div>

                        <div class="kb-batch-grid">
                            ${renderSelectField('batch-audience', 'audience', [''].concat(CHUNK_AUDIENCES), '')}
                            ${renderSelectField('batch-visibility', 'visibility', [''].concat(CHUNK_VISIBILITIES), '')}
                            ${renderSelectField('batch-retrieval-enabled', 'retrieval_enabled', ['', 'true', 'false'], '')}
                            <div class="kb-field">
                                <label for="batch-topics">topics</label>
                                <input type="text" id="batch-topics" placeholder="Comma-separated">
                            </div>
                        </div>

                        <div class="kb-field" style="margin-top:16px;">
                            <label>age_bands (multi-value)</label>
                            <div class="kb-multiselect">${renderAgeBandCheckboxes('batch-age', [], false)}</div>
                        </div>
                    </section>

                    <section class="kb-card">
                        <div class="kb-section-head">
                            <div>
                                <h3>Chunk List</h3>
                                <div class="kb-muted">${chunks.length} result(s).</div>
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
                                            <th>Content Preview</th>
                                            <th>System Fields</th>
                                            <th>confidence</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${chunks.map(chunk => renderChunkRow(chunk)).join('')}
                                    </tbody>
                                </table>
                            </div>` : `<div class="kb-empty">This document has no chunks yet. It may still be processing, or parsing may have failed.</div>`}
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
                        showAlert('Select at least one chunk.', 'warning');
                        return;
                    }

                    if (!confirm(`Apply updates to ${state.selectedChunkIds.length} chunk(s)?`)) {
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
                        showAlert('Fill in at least one field to update.', 'warning');
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
                appRoot.innerHTML = `<section class="kb-card"><div class="kb-empty">Unable to load the chunk list.</div></section>`;
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
                        <button class="kb-btn ghost" data-open-chunk="${escapeHtml(chunk.chunk_id)}"><i class="fas fa-eye"></i> View</button>
                    </td>
                </tr>
            `;
        }

        function bindChunkFilterEvents(docId) {
            const map = [
                {id: 'chunk-search', key: 'search', mode: 'enter'},
                {id: 'chunk-visibility', key: 'visibility', mode: 'change'},
                {id: 'chunk-audience', key: 'audience', mode: 'change'},
                {id: 'chunk-age', key: 'ageBand', mode: 'change'},
                {id: 'chunk-retrieval', key: 'retrievalEnabled', mode: 'change'},
                {id: 'chunk-sort-by', key: 'sortBy', mode: 'change'},
                {id: 'chunk-sort-dir', key: 'sortDir', mode: 'change'},
            ];

            map.forEach(({id, key, mode}) => {
                const element = document.getElementById(id);
                if (!element) return;

                if (mode === 'enter') {
                    element.addEventListener('keydown', event => {
                        if (event.key !== 'Enter') return;
                        event.preventDefault();
                        const next = event.target.value.trim();
                        if (state.chunkFilters[key] === next) return;
                        state.chunkFilters[key] = next;
                        renderChunkReviewView(docId);
                    });

                    const clearButton = document.querySelector(`[data-clear-input="${id}"]`);
                    if (clearButton) {
                        clearButton.addEventListener('click', () => {
                            if (!state.chunkFilters[key]) return;
                            state.chunkFilters[key] = '';
                            renderChunkReviewView(docId);
                        });
                    }
                    return;
                }

                element.addEventListener('change', event => {
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
                        <h3 style="margin:0;">Full Content</h3>
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

        function renderRebuildStatusPill(status) {
            const map = {
                idle: {label: 'idle', className: ''},
                queued: {label: 'queued', className: 'status-pending'},
                running: {label: 'running', className: 'status-pending'},
                completed: {label: 'completed', className: 'status-completed'},
                failed: {label: 'failed', className: 'status-failed'},
            };
            const current = map[status] || {label: String(status || 'unknown'), className: ''};
            return `<span class="kb-pill ${current.className}">${escapeHtml(current.label)}</span>`;
        }

        function renderInputField(id, label, placeholder, value, options = {}) {
            const clearable = !!options.clearable;
            const hasValue = String(value || '').trim() !== '';
            const clearTitle = options.clearTitle || 'Clear';

            return `
                <div class="kb-field">
                    <label for="${id}">${label}</label>
                    <div class="kb-input-wrap ${clearable && hasValue ? 'has-value' : ''}">
                        <input id="${id}" type="text" placeholder="${escapeHtml(placeholder)}" value="${escapeHtml(value || '')}">
                        ${clearable ? `<button type="button" class="kb-input-clear" data-clear-input="${escapeHtml(id)}" title="${escapeHtml(clearTitle)}" aria-label="${escapeHtml(clearTitle)}">&times;</button>` : ''}
                    </div>
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
                            const optionLabel = option === '' ? 'All' : option;
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
                    showAlert(`File type not supported: ${file.name}.`, 'warning');
                    continue;
                }

                if (file.size > state.maxFileSize) {
                    showAlert(`File ${file.name} exceeds the per-file limit of ${state.maxFileSizeFormatted}.`, 'warning');
                    continue;
                }

                const key = `${file.name}-${file.size}-${file.lastModified}`;
                if (existingKeys.has(key)) {
                    showAlert(`File ${file.name} is already in the upload queue.`, 'warning');
                    continue;
                }

                if (projectedSize + file.size > MAX_BATCH_UPLOAD_SIZE) {
                    showAlert(`Total batch upload size cannot exceed ${formatFileSize(MAX_BATCH_UPLOAD_SIZE)}.`, 'warning');
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
                pending: 'Pending',
                uploading: 'Uploading',
                success: 'Uploaded',
                duplicate: 'Duplicate',
                error: 'Upload failed',
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
                            ${item.docId ? `<button class="kb-btn ghost" data-open-upload-doc="${escapeHtml(item.docId)}" data-open-upload-view="${openView}"><i class="fas fa-arrow-right"></i> Open Flow</button>` : ''}
                            ${!state.upload.submitting && !done ? `<button class="kb-upload-remove" data-remove-upload="${escapeHtml(item.clientId)}" title="Remove queued file"><i class="fas fa-xmark"></i></button>` : ''}
                        </div>
                    </div>

                    <div class="kb-field">
                        <label for="upload-title-${escapeHtml(item.clientId)}">title</label>
                        <input type="text" id="upload-title-${escapeHtml(item.clientId)}" data-upload-title="${escapeHtml(item.clientId)}" value="${escapeHtml(item.title)}" placeholder="Defaults to filename without extension" ${state.upload.submitting || done ? 'disabled' : ''}>
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
                            reject(new Error('Your session has expired. Refresh the page and sign in again.'));
                            return;
                        }
                        reject(new Error(`Upload API returned a non-JSON response (HTTP ${xhr.status || 0}): ${compactBody.slice(0, 180) || 'empty response'}`));
                        return;
                    }

                    if (xhr.status >= 200 && xhr.status < 300) {
                        resolve(data);
                        return;
                    }

                    if (xhr.status === 401) {
                        reject(new Error('Your session has expired. Refresh the page and sign in again.'));
                        return;
                    }
                    if (xhr.status === 403) {
                        reject(new Error('Upload request was rejected, possibly due to an expired CSRF token. Refresh the page and try again.'));
                        return;
                    }

                    reject(new Error(data.error || data.detail || 'Upload failed'));
                };

                xhr.onerror = () => reject(new Error('Network error: upload failed'));
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

        async function startKnowledgeRebuild() {
            if (state.rebuild?.isRunning) {
                showAlert('A rebuild job is already running.', 'warning');
                return;
            }

            if (!confirm('Start a knowledge base rebuild? The system will scan storage/knowledge/uploads, restore missing documents and chunks, and rebuild the vector index. Avoid uploading the same batch during rebuild.')) {
                return;
            }

            try {
                await apiPost('api/knowledge/rebuild', {});
                await loadRebuildStatus();
                syncRebuildPolling();
                renderDocumentsView();
                showAlert('Knowledge base rebuild started.', 'success');
            } catch (error) {
                showAlert(error.message, 'error');
            }
        }

        function stageLabelByKey(key) {
            const map = {
                saved: 'File saved',
                parser: 'Parsing document',
                chunking: 'Chunking text',
                classification: 'Classifying automatically',
                metadata: 'Generating age bands / audience / visibility / topics',
                embedding: 'Building embeddings / indexing',
                done: 'Analysis completed'
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

        function clearRebuildPolling() {
            if (state.rebuildPollTimer) {
                clearTimeout(state.rebuildPollTimer);
                state.rebuildPollTimer = null;
            }
        }

        function clearRecentUploadPolling() {
            if (state.recentUploadPollTimer) {
                clearTimeout(state.recentUploadPollTimer);
                state.recentUploadPollTimer = null;
            }
        }

        function flushPendingViewAlert() {
            if (!state.pendingViewAlert) {
                return;
            }
            const { message, type } = state.pendingViewAlert;
            state.pendingViewAlert = null;
            showAlert(message, type || 'warning');
        }

        function trackRecentUploads(docIds) {
            docIds.forEach(docId => {
                if (!docId) return;
                if (!state.recentUploadDocIds.includes(docId)) {
                    state.recentUploadDocIds.unshift(docId);
                }
            });
            state.recentUploadDocIds = state.recentUploadDocIds.slice(0, 10);
        }

        function mergeRecentUploadStatus(docId, statusPayload) {
            state.recentUploadStatuses[docId] = statusPayload;
            const file = statusPayload?.file || null;
            if (!file) {
                return;
            }

            const index = state.docs.findIndex(doc => String(doc.id) === String(docId));
            if (index >= 0) {
                state.docs[index] = {
                    ...state.docs[index],
                    ...file,
                };
            }
        }

        function isRecentUploadTerminal(docId) {
            const status = state.recentUploadStatuses[docId];
            if (!status) {
                return false;
            }
            return !!(status.completed || status.failed);
        }

        function getRecentUploadStageLabel(docId) {
            const status = state.recentUploadStatuses[docId];
            if (!status) {
                return 'Queued for processing';
            }
            if (status.failed) {
                return 'Analysis failed';
            }
            if (status.completed) {
                return 'Analysis completed';
            }
            return stageLabelByKey(status.current_stage || 'saved');
        }

        function renderRecentUploadBadge(docId) {
            if (!state.recentUploadDocIds.includes(docId)) {
                return '';
            }

            if (isRecentUploadTerminal(docId)) {
                const failed = !!state.recentUploadStatuses[docId]?.failed;
                return `<span class="kb-pill ${failed ? 'status-failed' : 'status-completed'}">${failed ? 'Recent upload failed' : 'Recent upload completed'}</span>`;
            }

            return `<span class="kb-pill status-pending">Recent upload</span>`;
        }

        function renderRecentUploadProgress(docId) {
            if (!state.recentUploadDocIds.includes(docId)) {
                return '';
            }

            const status = state.recentUploadStatuses[docId];
            const line = getRecentUploadStageLabel(docId);
            const color = status?.failed ? '#b42318' : '#486581';
            return `<div class="kb-doc-meta" style="margin-top:8px; color:${color};">Current step: ${escapeHtml(line)}</div>`;
        }

        async function refreshRecentUploadStatuses(silent = true) {
            if (!state.recentUploadDocIds.length) {
                return;
            }

            const activeDocIds = [...state.recentUploadDocIds];
            await Promise.all(activeDocIds.map(async docId => {
                try {
                    const data = await loadDocumentStatus(docId);
                    mergeRecentUploadStatus(docId, data);
                } catch (error) {
                    if (!silent) {
                        showAlert(error.message, 'error');
                    }
                }
            }));
        }

        function syncRecentUploadPolling() {
            clearRecentUploadPolling();

            if (state.view !== 'documents' || !state.recentUploadDocIds.length) {
                return;
            }

            const pendingDocIds = state.recentUploadDocIds.filter(docId => !isRecentUploadTerminal(docId));
            if (!pendingDocIds.length) {
                return;
            }

            state.recentUploadPollTimer = setTimeout(async () => {
                await refreshRecentUploadStatuses(true);
                if (state.view === 'documents') {
                    renderDocumentsView();
                }
            }, 2500);
        }

        function syncRebuildPolling() {
            clearRebuildPolling();
            if (!state.rebuild?.isRunning) {
                return;
            }

            state.rebuildPollTimer = setTimeout(async () => {
                await loadRebuildStatus(true);
                if (state.view === 'documents') {
                    renderDocumentsView();
                }
                if (!state.rebuild?.isRunning) {
                    await loadDocuments();
                    if (state.view === 'documents') {
                        renderDocumentsView();
                    }
                    if (state.rebuild?.status === 'completed') {
                        showAlert('Knowledge base rebuild completed. The document list has been refreshed.', 'success');
                    } else if (state.rebuild?.status === 'failed') {
                        showAlert('Knowledge base rebuild failed. Check the log output.', 'error');
                    }
                    return;
                }
                syncRebuildPolling();
            }, 2500);
        }
    </script>
</body>

</html>
