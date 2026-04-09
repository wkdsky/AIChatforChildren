<?php

use Core\Config;
use Utils\Helper;

$user = $_SESSION['user'];
$csrfToken = Helper::generateCsrfToken();
$appTimezone = Config::get('APP_TIMEZONE', Config::get('app.timezone', 'Asia/Shanghai'));

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Parent Area</title>
  <link rel="stylesheet" href="<?php echo Helper::url('assets/css/profile.css'); ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    body {
      min-height: 100vh;
      background:
        radial-gradient(circle at top left, rgba(102, 126, 234, 0.16), transparent 30%),
        radial-gradient(circle at top right, rgba(118, 75, 162, 0.12), transparent 25%),
        #f3f4f8;
    }

    .navbar {
      width: 100%;
      background: rgba(255, 255, 255, 0.92);
      backdrop-filter: blur(10px);
      padding: 15px 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: var(--box-shadow);
      position: sticky;
      top: 0;
      z-index: 50;
    }

    .brand {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--primary-color);
      text-decoration: none;
    }

    .user-menu {
      position: relative;
    }

    .user-info {
      display: flex;
      align-items: center;
      gap: 10px;
      cursor: pointer;
      padding: 8px 12px;
      border-radius: 999px;
      transition: background-color 0.2s ease;
    }

    .user-info:hover {
      background-color: #f1f3f8;
    }

    .user-avatar {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: linear-gradient(135deg, #667eea, #764ba2);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
    }

    .user-name {
      font-weight: 600;
      color: #2d3748;
    }

    .dropdown-menu {
      position: absolute;
      top: calc(100% + 10px);
      right: 0;
      background: white;
      border: 1px solid #e6e8ef;
      border-radius: 12px;
      box-shadow: 0 18px 45px rgba(31, 41, 55, 0.12);
      display: none;
      min-width: 190px;
      z-index: 1000;
      overflow: hidden;
    }

    .dropdown-menu.show {
      display: block;
    }

    .dropdown-item {
      display: block;
      padding: 12px 16px;
      color: #333;
      text-decoration: none;
      border: none;
      background: none;
      width: 100%;
      text-align: left;
      cursor: pointer;
      transition: background-color 0.2s ease;
      font-size: 14px;
    }

    .dropdown-item:hover {
      background-color: #f8f9fc;
    }

    .dropdown-item.danger {
      color: #dc3545;
    }

    .dropdown-item i {
      width: 20px;
      margin-right: 8px;
    }

    .page-shell {
      max-width: 1200px;
      margin: 0 auto;
      padding: 32px 20px 48px;
    }

    .hero-panel {
      margin-bottom: 24px;
    }

    .summary-card,
    .children-section {
      background: rgba(255, 255, 255, 0.94);
      border: 1px solid rgba(230, 232, 239, 0.9);
      border-radius: 22px;
      box-shadow: 0 18px 45px rgba(31, 41, 55, 0.08);
    }

    .summary-card {
      padding: 26px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 24px;
    }

    .summary-label {
      font-size: 13px;
      color: #6b7280;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }

    .summary-number {
      font-size: 2.4rem;
      font-weight: 700;
      color: #1f2937;
    }

    .summary-caption {
      color: #6b7280;
      line-height: 1.6;
      margin: 0;
      max-width: 520px;
    }

    .children-section {
      padding: 28px;
    }

    .section-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 16px;
      margin-bottom: 20px;
    }

    .section-title h2 {
      margin: 0 0 8px;
      font-size: 1.5rem;
      color: #111827;
    }

    .section-title p {
      margin: 0;
      color: #6b7280;
      line-height: 1.6;
    }

    .action-inline {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
    }

    .btn-inline {
      width: auto;
      min-width: 0;
      margin: 0;
      padding: 12px 18px;
      border-radius: 12px;
      font-weight: 600;
    }

    .btn-secondary {
      background: #eef2ff;
      color: #4338ca;
    }

    .btn-secondary:hover {
      background: #e0e7ff;
    }

    .children-grid {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .child-card {
      border: 1px solid #e8eaf3;
      border-radius: 16px;
      padding: 16px 18px;
      background: linear-gradient(180deg, #ffffff 0%, #fafbff 100%);
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 14px;
    }

    .child-card h3 {
      margin: 0;
      color: #111827;
      font-size: 1rem;
    }

    .child-summary {
      display: flex;
      align-items: center;
      gap: 14px;
      min-width: 0;
      flex: 1;
    }

    .child-main {
      min-width: 0;
    }

    .child-meta {
      margin: 2px 0 0;
      color: #6b7280;
      font-size: 13px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .child-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 10px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 600;
      background: #ede9fe;
      color: #6d28d9;
      white-space: nowrap;
    }

    .child-inline-stats {
      display: flex;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
    }

    .child-stat-chip {
      padding: 8px 12px;
      border-radius: 999px;
      background: #f8fafc;
      border: 1px solid #edf2f7;
      color: #475569;
      font-size: 12px;
      font-weight: 600;
      white-space: nowrap;
    }

    .card-actions {
      display: flex;
      gap: 10px;
      flex-shrink: 0;
    }

    .ghost-btn,
    .warning-btn,
    .danger-btn {
      border: none;
      border-radius: 10px;
      padding: 10px 14px;
      font-weight: 600;
      cursor: pointer;
      transition: transform 0.2s ease, opacity 0.2s ease;
    }

    .ghost-btn {
      background: #eef2ff;
      color: #4338ca;
    }

    .danger-btn {
      background: #fee2e2;
      color: #b91c1c;
    }

    .warning-btn {
      background: #fff4d6;
      color: #b45309;
    }

    .ghost-btn:hover,
    .warning-btn:hover,
    .danger-btn:hover {
      transform: translateY(-1px);
      opacity: 0.92;
    }

    .empty-state {
      border: 2px dashed #d8dcef;
      border-radius: 18px;
      padding: 34px 24px;
      text-align: center;
      color: #6b7280;
      background: #fafbff;
    }

    .empty-state i {
      font-size: 28px;
      color: #818cf8;
      margin-bottom: 12px;
      display: inline-block;
    }

    .section-alert,
    #alertMessage {
      padding: 12px 16px;
      border-radius: 12px;
      margin-bottom: 18px;
      font-size: 14px;
    }

    .section-alert.success,
    #alertMessage.success {
      background: #dcfce7;
      color: #166534;
      border: 1px solid #bbf7d0;
    }

    .section-alert.error,
    #alertMessage.error {
      background: #fee2e2;
      color: #991b1b;
      border: 1px solid #fecaca;
    }

    .section-alert.info,
    #alertMessage.info {
      background: #dbeafe;
      color: #1d4ed8;
      border: 1px solid #bfdbfe;
    }

    .modal {
      position: fixed;
      inset: 0;
      z-index: 1000;
      background-color: rgba(17, 24, 39, 0.48);
      backdrop-filter: blur(4px);
    }

    .modal-content {
      background-color: #fff;
      margin: 4% auto;
      padding: 0;
      border-radius: 18px;
      width: 92%;
      max-width: 640px;
      max-height: 86vh;
      overflow-y: auto;
      box-shadow: 0 30px 80px rgba(17, 24, 39, 0.2);
    }

    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px 24px;
      border-bottom: 1px solid #eef2f7;
    }

    .modal-header h2 {
      margin: 0;
      color: #111827;
      font-size: 1.2rem;
    }

    .close {
      color: #9ca3af;
      font-size: 28px;
      font-weight: bold;
      cursor: pointer;
      line-height: 1;
    }

    .close:hover {
      color: #111827;
    }

    .modal-body {
      padding: 24px;
    }

    .form-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 16px;
    }

    .form-group {
      margin-bottom: 18px;
    }

    .form-group.full {
      grid-column: 1 / -1;
    }

    .form-group label {
      display: block;
      font-weight: 600;
      margin-bottom: 8px;
      color: #374151;
    }

    .form-group input,
    .form-group select {
      width: 100%;
      padding: 12px 14px;
      border: 1px solid #d7dbe8;
      border-radius: 12px;
      font-size: 14px;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
      background: white;
    }

    .form-group input:focus,
    .form-group select:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.12);
    }

    .time-select-group {
      display: grid;
      grid-template-columns: 1fr auto 1fr;
      gap: 10px;
      align-items: center;
    }

    .time-select-separator {
      color: #64748b;
      font-size: 18px;
      font-weight: 700;
      text-align: center;
    }

    .gender-symbols {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 12px;
    }

    .gender-symbol-input {
      position: absolute;
      opacity: 0;
      pointer-events: none;
    }

    .gender-symbol-option {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 10px;
      min-height: 140px;
      border: 1px solid #d7dbe8;
      border-radius: 20px;
      background: linear-gradient(180deg, #ffffff 0%, #f8faff 100%);
      cursor: pointer;
      transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease, background 0.2s ease;
    }

    .gender-symbol-option:hover {
      transform: translateY(-2px);
      border-color: #b9c3ff;
      box-shadow: 0 12px 24px rgba(79, 70, 229, 0.08);
    }

    .gender-symbol-input:checked + .gender-symbol-option {
      border-color: #667eea;
      box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.12), 0 14px 28px rgba(102, 126, 234, 0.12);
      background: linear-gradient(180deg, #f7f9ff 0%, #eef2ff 100%);
    }

    .gender-symbol-badge {
      width: 58px;
      height: 58px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 28px;
      font-weight: 700;
      color: white;
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.28);
    }

    .gender-symbol-option[data-gender="male"] .gender-symbol-badge {
      background: linear-gradient(135deg, #60a5fa 0%, #2563eb 100%);
    }

    .gender-symbol-option[data-gender="female"] .gender-symbol-badge {
      background: linear-gradient(135deg, #f472b6 0%, #db2777 100%);
    }

    .gender-symbol-text {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 4px;
    }

    .gender-symbol-title {
      font-size: 16px;
      font-weight: 700;
      color: #1f2937;
      letter-spacing: 0.01em;
    }

    .gender-symbol-subtitle {
      font-size: 12px;
      color: #64748b;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }

    .error-text {
      color: #dc2626;
      font-size: 12px;
      display: block;
      margin-top: 6px;
      min-height: 16px;
    }

    .error-text.success {
      color: #059669;
    }

    .error-text.info {
      color: #64748b;
    }

    .form-note {
      margin: 0 0 18px;
      padding: 12px 14px;
      background: #f8fafc;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      color: #6b7280;
      line-height: 1.6;
      font-size: 14px;
    }

    .settings-overview {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 12px;
      margin-bottom: 18px;
    }

    .settings-overview-item {
      padding: 12px;
      border-radius: 12px;
      background: #f8fafc;
      border: 1px solid #edf2f7;
    }

    .settings-overview-item strong {
      display: block;
      font-size: 12px;
      color: #6b7280;
      margin-bottom: 6px;
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }

    .settings-overview-item span {
      color: #111827;
      font-weight: 600;
      line-height: 1.5;
    }

    .status-chip-paused {
      background: #fff1f2;
      color: #be123c;
    }

    .status-chip-active {
      background: #ecfdf5;
      color: #047857;
    }

    .manage-login-actions {
      display: flex;
      justify-content: flex-start;
      margin-bottom: 18px;
    }

    .report-modal-content {
      width: min(97vw, 1520px);
      max-width: 1520px;
      max-height: 90vh;
    }

    .report-toolbar {
      display: flex;
      justify-content: space-between;
      gap: 14px;
      align-items: center;
      flex-wrap: wrap;
      margin-bottom: 18px;
    }

    .report-metric-switch {
      display: inline-flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .report-switch-btn {
      border: 1px solid #d7dbe8;
      background: #fff;
      color: #475569;
      padding: 9px 12px;
      border-radius: 999px;
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .report-switch-btn.active {
      border-color: #667eea;
      background: #eef2ff;
      color: #4338ca;
    }

    .report-helper-text {
      color: #64748b;
      font-size: 13px;
      line-height: 1.6;
    }

    .report-shell {
      display: grid;
      grid-template-columns: minmax(290px, 330px) minmax(0, 1fr);
      gap: 28px;
      align-items: start;
    }

    .report-sidebar,
    .report-main {
      display: flex;
      flex-direction: column;
      gap: 16px;
      min-width: 0;
    }

    .report-sidebar-card {
      border: 1px solid #e5e7eb;
      border-radius: 18px;
      background: linear-gradient(180deg, #ffffff 0%, #fbfcff 100%);
      box-shadow: 0 14px 30px rgba(15, 23, 42, 0.05);
      padding: 16px;
    }

    .report-settings-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 10px;
      margin: 12px 0;
    }

    .report-settings-grid label {
      display: flex;
      flex-direction: column;
      gap: 6px;
      color: #475569;
      font-size: 13px;
      font-weight: 600;
    }

    .report-settings-grid select {
      width: 100%;
      border: 1px solid #d7dbe8;
      border-radius: 12px;
      padding: 9px 11px;
      background: #fff;
      color: #0f172a;
      font-size: 13px;
    }

    .report-toggle-row {
      display: flex;
      align-items: center;
      gap: 10px;
      color: #0f172a;
      font-weight: 600;
    }

    .report-usage-list {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .report-usage-item {
      width: 100%;
      border: 1px solid #dbeafe;
      background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
      border-radius: 16px;
      padding: 12px;
      text-align: left;
      cursor: pointer;
      transition: all 0.2s ease;
      color: #0f172a;
    }

    .report-usage-item:hover,
    .report-usage-item.active {
      border-color: #60a5fa;
      box-shadow: 0 12px 28px rgba(59, 130, 246, 0.15);
      background: linear-gradient(180deg, #eff6ff 0%, #ffffff 100%);
    }

    .report-usage-kicker {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      color: #1d4ed8;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      margin-bottom: 6px;
    }

    .report-usage-title {
      display: block;
      font-size: 14px;
      font-weight: 700;
      margin-bottom: 4px;
    }

    .report-usage-meta {
      color: #475569;
      font-size: 12px;
      line-height: 1.55;
    }

    .report-history-list {
      display: flex;
      flex-direction: column;
      gap: 8px;
      max-height: calc(90vh - 320px);
      overflow-y: auto;
      margin-top: 10px;
      padding-right: 4px;
    }

    .report-history-item {
      width: 100%;
      border: 1px solid #e2e8f0;
      background: #fff;
      border-radius: 14px;
      padding: 10px 11px;
      text-align: left;
      cursor: pointer;
      transition: all 0.2s ease;
      display: flex;
      flex-direction: column;
      gap: 4px;
      color: #0f172a;
    }

    .report-history-item.in-selection {
      gap: 10px;
    }

    .report-history-item:hover {
      border-color: #c7d2fe;
      box-shadow: 0 10px 24px rgba(99, 102, 241, 0.12);
    }

    .report-history-item.active {
      border-color: #667eea;
      background: #eef2ff;
      box-shadow: 0 14px 28px rgba(99, 102, 241, 0.16);
    }

    .report-history-item.live {
      background: #f8fafc;
    }

    .report-history-item.selected {
      border-color: #6366f1;
      background: #eef2ff;
    }

    .report-history-row {
      display: flex;
      align-items: flex-start;
      gap: 8px;
    }

    .report-history-body {
      display: flex;
      flex-direction: column;
      gap: 4px;
      min-width: 0;
      width: 100%;
    }

    .report-history-check {
      flex: 0 0 auto;
      width: 20px;
      height: 20px;
      border-radius: 50%;
      border: 1px solid #cbd5e1;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      color: transparent;
      background: #fff;
      margin-top: 2px;
      transition: all 0.2s ease;
      font-size: 11px;
    }

    .report-history-item.selected .report-history-check {
      border-color: #4338ca;
      background: #4338ca;
      color: #fff;
    }

    .report-panel-actions,
    .report-selection-quick {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }

    .report-selection-toolbar {
      display: flex;
      flex-direction: column;
      gap: 12px;
      margin-top: 14px;
      padding-top: 14px;
      border-top: 1px solid #e2e8f0;
    }

    .report-selection-actions {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .report-history-title {
      font-weight: 700;
      font-size: 13px;
      line-height: 1.4;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .report-history-meta {
      color: #64748b;
      font-size: 11px;
      line-height: 1.45;
    }

    .report-history-topline,
    .report-history-footline {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      flex-wrap: wrap;
    }

    .report-history-badges {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
    }

    .report-history-pill {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 4px 8px;
      border-radius: 999px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      color: #475569;
      font-size: 10px;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
    }

    .report-history-pill.manual {
      background: #eef2ff;
      border-color: #c7d2fe;
      color: #4338ca;
    }

    .report-history-pill.auto {
      background: #ecfeff;
      border-color: #bae6fd;
      color: #0369a1;
    }

    .report-history-time {
      color: #64748b;
      font-size: 11px;
      white-space: nowrap;
    }

    .report-history-scope {
      color: #334155;
      font-size: 12px;
      line-height: 1.5;
    }

    .report-history-divider {
      width: 100%;
      height: 1px;
      background: #eef2f7;
      margin: 2px 0;
    }

    .report-summary-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 12px;
    }

    .report-stat-card,
    .report-panel {
      border: 1px solid #e5e7eb;
      border-radius: 18px;
      background: linear-gradient(180deg, #ffffff 0%, #fbfcff 100%);
      box-shadow: 0 14px 30px rgba(15, 23, 42, 0.05);
    }

    .report-stat-card {
      padding: 16px;
    }

    .report-stat-label {
      display: block;
      color: #64748b;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      margin-bottom: 8px;
    }

    .report-stat-value {
      display: block;
      color: #0f172a;
      font-size: 1.6rem;
      font-weight: 700;
      margin-bottom: 4px;
    }

    .report-stat-note {
      color: #64748b;
      font-size: 12px;
      line-height: 1.5;
    }

    .report-panel {
      padding: 20px 22px;
    }

    .report-sidebar .btn-inline {
      padding: 9px 10px;
      border-radius: 10px;
      font-size: 12px;
      min-height: 40px;
    }

    .report-panel-actions {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 8px;
      width: 100%;
    }

    .report-panel-actions .btn-inline {
      width: 100%;
    }

    .report-history-caption {
      margin-top: 6px;
    }

    .report-sidebar-footer {
      margin-top: auto;
      padding-top: 8px;
    }

    .report-settings-anchor {
      position: relative;
      display: inline-flex;
      flex-direction: column;
      align-items: flex-start;
      gap: 8px;
    }

    .report-auto-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      border: 1px solid #cbd5e1;
      background: #fff;
      color: #334155;
      border-radius: 999px;
      padding: 7px 11px;
      font-size: 12px;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .report-auto-badge:hover {
      border-color: #94a3b8;
      background: #f8fafc;
    }

    .report-settings-popover {
      position: absolute;
      left: 0;
      bottom: calc(100% + 10px);
      width: min(360px, calc(100vw - 32px));
      border: 1px solid #dbe2ea;
      border-radius: 16px;
      background: #fff;
      box-shadow: 0 18px 38px rgba(15, 23, 42, 0.16);
      padding: 14px;
      z-index: 15;
    }

    .report-settings-popover-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      margin-bottom: 12px;
      color: #0f172a;
    }

    .report-settings-close {
      border: none;
      background: transparent;
      color: #64748b;
      font-size: 20px;
      line-height: 1;
      cursor: pointer;
      padding: 0;
    }

    .report-source-banner {
      margin-bottom: 14px;
      padding: 10px 12px;
      border-radius: 12px;
      border: 1px solid #dbeafe;
      background: #eff6ff;
      color: #1e3a8a;
      font-size: 12px;
      line-height: 1.55;
    }

    .report-source-banner strong {
      display: block;
      margin-bottom: 2px;
      font-size: 12px;
    }

    .report-source-banner.ai {
      background: #ecfeff;
      border-color: #bae6fd;
      color: #0f766e;
    }

    .report-source-banner.fallback {
      background: #fff7ed;
      border-color: #fed7aa;
      color: #9a3412;
    }

    .report-source-banner.preview {
      background: #f8fafc;
      border-color: #cbd5e1;
      color: #334155;
    }

    .report-source-banner.no-data {
      background: #f8fafc;
      border-color: #e2e8f0;
      color: #475569;
    }

    .report-source-banner.usage {
      background: #eff6ff;
      border-color: #bfdbfe;
      color: #1d4ed8;
    }

    .report-panel-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 12px;
      flex-wrap: wrap;
      margin-bottom: 16px;
    }

    .report-panel-header h3 {
      margin: 0;
      color: #0f172a;
      font-size: 1.1rem;
    }

    .report-insights {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-bottom: 16px;
    }

    .report-insight-pill {
      padding: 10px 12px;
      border-radius: 12px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      color: #475569;
      font-size: 13px;
      line-height: 1.5;
    }

    .report-chart {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(38px, 1fr));
      gap: 10px;
      align-items: end;
      min-height: 260px;
      padding-top: 10px;
    }

    .usage-bucket-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
      gap: 12px;
      padding-top: 4px;
    }

    .usage-bucket-card {
      border: 1px solid #e2e8f0;
      border-radius: 14px;
      background: #fff;
      padding: 12px;
      display: flex;
      flex-direction: column;
      gap: 6px;
      min-width: 0;
    }

    .usage-bucket-title {
      font-size: 13px;
      font-weight: 700;
      color: #0f172a;
    }

    .usage-bucket-meta {
      color: #475569;
      font-size: 12px;
      line-height: 1.5;
    }

    .report-bar-col {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
      min-width: 0;
    }

    .report-bar-value {
      color: #0f172a;
      font-size: 12px;
      font-weight: 700;
      min-height: 16px;
    }

    .report-bar-track {
      width: 100%;
      height: 160px;
      border-radius: 16px;
      background: linear-gradient(180deg, #eef2ff 0%, #f8fafc 100%);
      border: 1px solid #e2e8f0;
      display: flex;
      align-items: flex-end;
      padding: 6px;
    }

    .report-bar-fill {
      width: 100%;
      border-radius: 12px;
      min-height: 6px;
      background: linear-gradient(180deg, #60a5fa 0%, #2563eb 100%);
      box-shadow: 0 10px 24px rgba(37, 99, 235, 0.2);
    }

    .report-bar-fill.metric-logins {
      background: linear-gradient(180deg, #34d399 0%, #059669 100%);
      box-shadow: 0 10px 24px rgba(5, 150, 105, 0.18);
    }

    .report-bar-fill.metric-minutes {
      background: linear-gradient(180deg, #f59e0b 0%, #d97706 100%);
      box-shadow: 0 10px 24px rgba(217, 119, 6, 0.18);
    }

    .report-bar-label {
      color: #64748b;
      font-size: 11px;
      font-weight: 700;
      white-space: nowrap;
    }

    .report-content-status {
      padding: 14px 16px;
      border-radius: 14px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      color: #475569;
      font-size: 14px;
      line-height: 1.7;
      margin-bottom: 16px;
    }

    .report-analysis-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 14px;
    }

    .report-analysis-card {
      padding: 16px;
      border-radius: 16px;
      background: #fff;
      border: 1px solid #e5e7eb;
    }

    .report-analysis-card.full {
      grid-column: 1 / -1;
    }

    .report-analysis-card h4 {
      margin: 0 0 10px;
      color: #0f172a;
      font-size: 1rem;
    }

    .report-analysis-card p {
      margin: 0;
      color: #475569;
      line-height: 1.7;
      font-size: 14px;
    }

    .report-tag-list,
    .report-list {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin: 0;
      padding: 0;
      list-style: none;
    }

    .report-tag {
      padding: 8px 12px;
      border-radius: 999px;
      background: #eef2ff;
      color: #4338ca;
      font-size: 13px;
      font-weight: 700;
    }

    .report-list-item {
      width: 100%;
      padding: 12px 14px;
      border-radius: 14px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      color: #475569;
      line-height: 1.6;
      font-size: 14px;
    }

    .report-risk-row {
      display: flex;
      gap: 10px;
      align-items: center;
      flex-wrap: wrap;
      margin-bottom: 12px;
    }

    .report-risk-badge {
      padding: 7px 10px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }

    .report-risk-badge.low {
      background: #ecfdf5;
      color: #047857;
    }

    .report-risk-badge.medium {
      background: #fff7ed;
      color: #c2410c;
    }

    .report-risk-badge.high {
      background: #fef2f2;
      color: #b91c1c;
    }

    .report-inline-level {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-left: 8px;
      padding: 3px 8px;
      border-radius: 999px;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }

    .report-inline-level.low {
      background: #ecfdf5;
      color: #047857;
    }

    .report-inline-level.medium {
      background: #fff7ed;
      color: #c2410c;
    }

    .report-inline-level.high {
      background: #fef2f2;
      color: #b91c1c;
    }

    .btn-danger {
      transition: background-color 0.2s ease;
    }

    .btn-danger:hover {
      background-color: #c82333 !important;
    }

    .report-empty-state {
      padding: 22px;
      border-radius: 18px;
      border: 1px dashed #cbd5e1;
      background: #f8fafc;
      color: #475569;
      line-height: 1.7;
    }

    @media (max-width: 768px) {
      .navbar {
        padding: 14px 16px;
      }

      .page-shell {
        padding: 20px 14px 36px;
      }

      .children-section,
      .summary-card {
        border-radius: 18px;
      }

      .summary-card {
        align-items: flex-start;
        flex-direction: column;
      }

      .section-header {
        flex-direction: column;
        align-items: flex-start;
      }

      .form-grid,
      .settings-overview,
      .report-summary-grid,
      .report-analysis-grid,
      .report-settings-grid,
      .report-shell {
        grid-template-columns: 1fr;
      }

      .child-card {
        flex-direction: column;
        align-items: stretch;
      }

      .card-actions {
        width: 100%;
      }

      .child-summary,
      .child-inline-stats {
        width: 100%;
      }

      .ghost-btn,
      .warning-btn,
      .danger-btn,
      .btn-inline {
        width: 100%;
      }

      .report-panel-actions {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>

<body>
  <div class="navbar">
    <div class="brand">Parent Area</div>

    <div class="user-menu">
      <div class="user-info" onclick="toggleDropdown()">
        <span class="user-name"><?= htmlspecialchars($user['name']) ?></span>
        <div class="user-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
        <i class="fas fa-chevron-down"></i>
      </div>

      <div class="dropdown-menu" id="userDropdown">
        <a href="#" class="dropdown-item" onclick="showProfileModal(); return false;">
          <i class="fas fa-user"></i> Profile
        </a>
        <a href="<?php echo Helper::url('logout'); ?>" class="dropdown-item danger">
          <i class="fas fa-sign-out-alt"></i> Logout
        </a>
      </div>
    </div>
  </div>

  <main class="page-shell">
    <section class="hero-panel">
      <div class="summary-card">
        <div>
          <div class="summary-label">Children Managed</div>
          <div class="summary-number" id="childrenCount">0</div>
        </div>
        <p class="summary-caption">View how many child accounts are linked to your parent account and manage their daily access in the section below.</p>
      </div>
    </section>

    <section class="children-section">
      <div class="section-header">
        <div class="section-title">
          <h2>Child Account Management</h2>
          <p>Create your child's account, review today's usage, and manage their safety limits.</p>
        </div>
        <div class="action-inline">
          <button type="button" class="btn btn-inline" onclick="showCreateChildModal()">
            <i class="fas fa-user-plus"></i> Create Child Account
          </button>
        </div>
      </div>

      <div id="pageAlert" class="section-alert" style="display: none;"></div>
      <div id="childrenList" class="children-grid"></div>
    </section>
  </main>

  <div class="modal" id="profileModal" style="display: none;">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Profile Settings</h2>
        <span class="close" onclick="closeProfileModal()">&times;</span>
      </div>
      <div class="modal-body">
        <div id="alertMessage" style="display: none;"></div>

        <form id="profileForm">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
          <div class="form-group">
            <label for="name">Name</label>
            <input type="text" name="name" id="name" value="<?= htmlspecialchars($user['name']) ?>" required>
            <small class="error-text" id="nameError"></small>
          </div>
          <div class="form-group">
            <label for="email">Email (Cannot be changed)</label>
            <input id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" disabled>
          </div>
          <button name="update-profile" type="submit" class="btn">Update Profile</button>
        </form>

        <hr style="margin: 30px 0; border: none; border-top: 1px solid #eee;">

        <h3 style="margin-bottom: 20px;">Change Password</h3>

        <form id="passwordForm">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

          <div class="form-group">
            <label for="current-password">Current Password</label>
            <input type="password" name="current-password" id="current-password" required>
            <small class="error-text" id="currentPasswordError"></small>
          </div>
          <div class="form-group">
            <label for="new-password">New Password</label>
            <input type="password" name="password" id="new-password" required>
            <small class="error-text" id="newPasswordError"></small>
          </div>
          <div class="form-group">
            <label for="confirm-password">Confirm New Password</label>
            <input type="password" name="confirm-password" id="confirm-password" required>
            <small class="error-text" id="confirmPasswordError"></small>
          </div>
          <button name="update-password" type="submit" class="btn">Update Password</button>
        </form>

        <hr style="margin: 30px 0; border: none; border-top: 1px solid #eee;">

        <a href="<?php echo Helper::url('logout'); ?>" class="btn btn-danger" style="width: 100%; text-align: center; display: block; background: #dc3545; color: white; text-decoration: none;">
          <i class="fas fa-sign-out-alt"></i> Logout
        </a>
      </div>
    </div>
  </div>

  <div class="modal" id="createChildModal" style="display: none;">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Create Child Account</h2>
        <span class="close" onclick="closeCreateChildModal()">&times;</span>
      </div>
      <div class="modal-body">
        <p class="form-note">Set your child's username, password, gender, and birth date. Child usernames cannot contain @. Birth date can only be selected from the date picker.</p>

        <form id="createChildForm">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
          <div class="form-grid">
            <div class="form-group full">
              <label for="child_name">Child Username</label>
              <input type="text" name="child_name" id="child_name" required>
              <small class="error-text" id="child_nameError"></small>
            </div>

            <div class="form-group">
              <label for="child_password">Password</label>
              <input type="password" name="password" id="child_password" required>
              <small class="error-text" id="passwordError"></small>
            </div>

            <div class="form-group">
              <label for="child_confirm_password">Confirm Password</label>
              <input type="password" name="confirm_password" id="child_confirm_password" required>
              <small class="error-text" id="confirm_passwordError"></small>
            </div>

            <div class="form-group">
              <label>Gender</label>
              <div class="gender-symbols">
                <label>
                  <input type="radio" name="gender" value="male" class="gender-symbol-input" required>
                  <span class="gender-symbol-option" data-gender="male" aria-label="Boy">
                    <span class="gender-symbol-badge">&#9794;</span>
                    <span class="gender-symbol-text">
                      <span class="gender-symbol-title">Boy</span>
                      <span class="gender-symbol-subtitle">Male</span>
                    </span>
                  </span>
                </label>
                <label>
                  <input type="radio" name="gender" value="female" class="gender-symbol-input" required>
                  <span class="gender-symbol-option" data-gender="female" aria-label="Girl">
                    <span class="gender-symbol-badge">&#9792;</span>
                    <span class="gender-symbol-text">
                      <span class="gender-symbol-title">Girl</span>
                      <span class="gender-symbol-subtitle">Female</span>
                    </span>
                  </span>
                </label>
              </div>
              <small class="error-text" id="genderError"></small>
            </div>

            <div class="form-group">
              <label for="birth_date">Birth Date</label>
              <input type="date" name="birth_date" id="birth_date" max="<?= date('Y-m-d') ?>" required>
              <small class="error-text" id="birth_dateError"></small>
            </div>
          </div>

          <button type="submit" class="btn">Create Account</button>
        </form>
      </div>
    </div>
  </div>

  <div class="modal" id="manageChildModal" style="display: none;">
    <div class="modal-content">
      <div class="modal-header">
        <h2 id="manageChildTitle">Manage Child Account</h2>
        <span class="close" onclick="closeManageChildModal()">&times;</span>
      </div>
      <div class="modal-body">
        <div class="settings-overview">
          <div class="settings-overview-item">
            <strong>Last Login</strong>
            <span id="manageChildLastLogin">Never</span>
          </div>
          <div class="settings-overview-item">
            <strong>Used Today</strong>
            <span id="manageChildUsage">0 / 0 minutes</span>
          </div>
          <div class="settings-overview-item">
            <strong>Login Access</strong>
            <span id="manageChildLoginStatus">Allowed to log in</span>
          </div>
        </div>

        <p class="form-note">Update the daily login window, total allowed minutes, or set a new password. Leaving the password fields empty keeps the current password unchanged.</p>
        <p class="form-note">Login windows are checked in <?= htmlspecialchars($appTimezone, ENT_QUOTES) ?> server time. Overnight windows such as 21:00-06:00 are supported.</p>

        <div class="manage-login-actions">
          <button type="button" id="manageChildToggleLoginBtn" class="warning-btn" onclick="toggleChildLoginFromModal()">
            Temporarily Disable Login
          </button>
        </div>

        <form id="manageChildForm">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
          <input type="hidden" name="child_id" id="manage_child_id">

          <div class="form-grid">
            <div class="form-group">
              <label>Allowed Start Time</label>
              <input type="hidden" name="allowed_login_start" id="allowed_login_start" required>
              <div class="time-select-group">
                <select id="allowed_login_start_hour" aria-label="Allowed start hour"></select>
                <span class="time-select-separator">:</span>
                <select id="allowed_login_start_minute" aria-label="Allowed start minute"></select>
              </div>
              <small class="error-text" id="allowed_login_startError"></small>
            </div>

            <div class="form-group">
              <label>Allowed End Time</label>
              <input type="hidden" name="allowed_login_end" id="allowed_login_end" required>
              <div class="time-select-group">
                <select id="allowed_login_end_hour" aria-label="Allowed end hour"></select>
                <span class="time-select-separator">:</span>
                <select id="allowed_login_end_minute" aria-label="Allowed end minute"></select>
              </div>
              <small class="error-text" id="allowed_login_endError"></small>
            </div>

            <div class="form-group full">
              <label for="daily_login_minutes">Daily Total Login Time (minutes)</label>
              <input type="number" name="daily_login_minutes" id="daily_login_minutes" min="1" max="1440" required>
              <small class="error-text" id="daily_login_minutesError"></small>
            </div>

            <div class="form-group">
              <label for="manage_password">New Password</label>
              <input type="password" name="password" id="manage_password">
              <small class="error-text" id="passwordManageError"></small>
            </div>

            <div class="form-group">
              <label for="manage_confirm_password">Confirm New Password</label>
              <input type="password" name="confirm_password" id="manage_confirm_password">
              <small class="error-text" id="confirm_passwordManageError"></small>
            </div>
          </div>

          <button type="submit" class="btn">Save Settings</button>
        </form>
      </div>
    </div>
  </div>

  <div class="modal" id="childReportModal" style="display: none;">
    <div class="modal-content report-modal-content">
      <div class="modal-header">
        <h2 id="childReportTitle">Child Report</h2>
        <span class="close" onclick="closeChildReportModal()">&times;</span>
      </div>
      <div class="modal-body">
        <div class="report-toolbar">
          <div class="report-helper-text" id="reportGeneratedHint">Usage habit report updates automatically once per day.</div>
        </div>

        <div id="childReportLoading" class="empty-state" style="display: none;">
          <i class="fas fa-chart-column"></i>
          <div>Loading report...</div>
        </div>

        <div id="childReportContent" class="report-shell" style="display: none;">
          <aside class="report-sidebar">
            <section class="report-sidebar-card">
              <div class="report-panel-header">
                <h3>Usage Habit</h3>
              </div>
              <div id="usageHabitList" class="report-usage-list"></div>
            </section>

            <section class="report-sidebar-card">
              <div class="report-panel-header">
                <h3>Chat Reports</h3>
                <div class="report-panel-actions">
                  <button type="button" class="btn btn-inline btn-secondary" id="generateContentReportBtn" onclick="generateContentReport()">
                    Generate Now
                  </button>
                  <button type="button" class="btn btn-inline" id="toggleTrendSelectionBtn" onclick="toggleTrendSelectionMode()">
                    Cumulative Analysis
                  </button>
                </div>
              </div>
              <div id="reportSelectionToolbar" class="report-selection-toolbar" style="display: none;">
                <div class="report-selection-quick">
                  <button type="button" class="report-switch-btn" onclick="selectTrendRange('1m')">1 Month</button>
                  <button type="button" class="report-switch-btn" onclick="selectTrendRange('3m')">3 Months</button>
                  <button type="button" class="report-switch-btn" onclick="selectTrendRange('6m')">6 Months</button>
                  <button type="button" class="report-switch-btn" onclick="selectAllTrendReports()">All</button>
                </div>
                <div class="report-selection-actions">
                  <div class="report-helper-text" id="reportSelectionMeta">All saved reports selected.</div>
                  <button type="button" class="btn btn-inline" id="runTrendAnalysisBtn" onclick="generateTrendAnalysis()">Analyze Selected</button>
                  <button type="button" class="btn btn-inline btn-secondary" onclick="toggleTrendSelectionMode(false)">Cancel</button>
                </div>
              </div>
              <div class="report-helper-text report-history-caption" id="reportHistoryMeta"></div>
              <div class="report-history-list" id="childReportHistoryList"></div>
            </section>

            <div class="report-sidebar-footer">
              <div class="report-settings-anchor">
                <button type="button" class="report-auto-badge" id="autoReportSettingsTrigger" onclick="toggleAutoReportSettings()">
                  <i class="fas fa-gear"></i>
                  <span>Auto Reports</span>
                </button>
                <div class="report-settings-popover" id="autoReportSettingsPopover" style="display: none;">
                  <div class="report-settings-popover-header">
                    <strong>Auto Reports</strong>
                    <button type="button" class="report-settings-close" onclick="toggleAutoReportSettings(false)">&times;</button>
                  </div>
                  <label class="report-toggle-row" for="autoReportEnabled">
                    <input type="checkbox" id="autoReportEnabled">
                    <span>Generate automatically</span>
                  </label>
                  <div class="report-settings-grid">
                    <label for="autoReportFrequency">
                      <span>Period</span>
                      <select id="autoReportFrequency">
                        <option value="7">7 days</option>
                        <option value="14">14 days</option>
                        <option value="30">30 days</option>
                      </select>
                    </label>
                  </div>
                  <button type="button" class="btn btn-inline" id="saveReportSettingsBtn">Save Schedule</button>
                  <div class="report-helper-text" id="autoReportMeta"></div>
                </div>
              </div>
            </div>
          </aside>

          <div class="report-main">
            <div id="childReportSummary" class="report-summary-grid"></div>

            <section class="report-panel">
              <div class="report-panel-header">
                <h3 id="childReportActivityTitle">Recent Activity</h3>
                <div class="report-metric-switch">
                  <button type="button" class="report-switch-btn active" data-report-switch-slot="0">Messages</button>
                  <button type="button" class="report-switch-btn" data-report-switch-slot="1">Logins</button>
                  <button type="button" class="report-switch-btn" data-report-switch-slot="2">Minutes</button>
                </div>
              </div>
              <div id="childReportInsights" class="report-insights"></div>
              <div id="childReportChart" class="report-chart"></div>
            </section>

            <section class="report-panel">
              <div class="report-panel-header">
                <h3>Report Details</h3>
                <div class="report-helper-text" id="selectedReportMeta"></div>
              </div>
              <div id="reportSourceMeta"></div>
              <div id="childReportSampleStatus" class="report-content-status"></div>
              <div id="childReportAnalysis"></div>
            </section>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    const CHILDREN_API_URL = '<?= Helper::url('api/parent/children') ?>';
    const UPDATE_CHILD_API_URL = '<?= Helper::url('api/parent/children/update') ?>';
    const DELETE_CHILD_API_URL = '<?= Helper::url('api/parent/children/delete') ?>';
    const TOGGLE_CHILD_LOGIN_API_URL = '<?= Helper::url('api/parent/children/toggle-login') ?>';
    const CHILD_REPORT_API_URL = '<?= Helper::url('api/parent/children/report') ?>';
    const CHILD_REPORT_HISTORY_API_URL = '<?= Helper::url('api/parent/children/report/history') ?>';
    const CHILD_REPORT_ITEM_API_URL = '<?= Helper::url('api/parent/children/report/item') ?>';
    const CHILD_REPORT_CONTENT_API_URL = '<?= Helper::url('api/parent/children/report/content') ?>';
    const CHILD_REPORT_SETTINGS_API_URL = '<?= Helper::url('api/parent/children/report/settings') ?>';
    const CHILD_REPORT_TREND_API_URL = '<?= Helper::url('api/parent/children/report/trend') ?>';
    const UPDATE_PROFILE_URL = '<?= Helper::url('update-profile') ?>';
    const VALIDATION_API_URL = '<?= Helper::url('api/validation/account-availability') ?>';
    const CSRF_TOKEN = '<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>';
    const APP_TIMEZONE = '<?= htmlspecialchars($appTimezone, ENT_QUOTES) ?>';
    let alertTimeout = null;
    let pageAlertTimeout = null;
    let childrenState = [];
    let childNameCheckTimer = null;
    let childNameCheckController = null;
    let reportState = {
      childId: null,
      metric: 'child_message_count',
      usagePeriod: 'day',
      usageReport: null,
      activeSnapshot: null,
      activeRecord: null,
      activeSource: 'empty',
      settings: null,
      history: [],
      initializedSelection: false,
      historyLoaded: false,
      requestKey: 0,
      selectionToken: 0,
      selectionMode: false,
      selectedReportIds: [],
      trendAnalysis: null
    };
    const TIME_MINUTE_OPTIONS = ['00', '10', '20', '30', '40', '50'];

    function getSavedReportStorageKey(childId) {
      return `child-report-last-opened:${childId}`;
    }

    function persistSavedReportSelection(childId, reportId) {
      if (!childId || !reportId) {
        return;
      }

      try {
        localStorage.setItem(getSavedReportStorageKey(childId), String(reportId));
      } catch (error) {
        console.warn('Failed to persist report selection', error);
      }
    }

    function getPersistedSavedReportId(childId) {
      if (!childId) {
        return null;
      }

      try {
        const raw = localStorage.getItem(getSavedReportStorageKey(childId));
        const numericId = Number(raw);
        return Number.isFinite(numericId) && numericId > 0 ? numericId : null;
      } catch (error) {
        return null;
      }
    }

    function clearPersistedSavedReportId(childId) {
      if (!childId) {
        return;
      }

      try {
        localStorage.removeItem(getSavedReportStorageKey(childId));
      } catch (error) {
        console.warn('Failed to clear report selection', error);
      }
    }

    function getAnalysisSourceMeta(analysis) {
      const source = analysis && analysis.source ? analysis.source : 'unknown';
      const detail = analysis && analysis.source_detail ? analysis.source_detail : '';

      switch (source) {
        case 'usage_habit':
          return {
            className: 'usage',
            title: 'Usage Habit Report',
            detail: detail || 'This updates automatically once a day from recorded login and chat activity.'
          };
        case 'llm_transcript':
          return {
            className: 'ai',
            title: 'AI Report',
            detail: detail || 'This saved report was generated by AI from the retained transcript bundle and activity summary.'
          };
        case 'llm_trend':
          return {
            className: 'ai',
            title: 'AI Cumulative Analysis',
            detail: detail || 'This cumulative view was generated by AI from the selected saved reports.'
          };
        case 'rule_based':
          return {
            className: 'fallback',
            title: 'Fallback Analysis',
            detail: detail || 'AI did not return a usable result, so the system used local rules instead.'
          };
        case 'preview':
          return {
            className: 'preview',
            title: 'Threshold Preview',
            detail: detail || 'This is only a generation preview and has not been saved as an AI report.'
          };
        case 'no_data':
          return {
            className: 'no-data',
            title: 'No Chat Sample',
            detail: detail || 'There was not enough recent child-authored chat to analyze.'
          };
        default:
          return {
            className: 'preview',
            title: 'Report Status',
            detail: detail || 'Report source is not available.'
          };
      }
    }

    function renderReportSourceMeta(analysis) {
      const container = document.getElementById('reportSourceMeta');
      if (!container) {
        return;
      }

      if (!analysis) {
        container.innerHTML = '';
        return;
      }

      const meta = getAnalysisSourceMeta(analysis);
      container.innerHTML = `
        <div class="report-source-banner ${escapeHtml(meta.className)}">
          <strong>${escapeHtml(meta.title)}</strong>
          ${escapeHtml(meta.detail)}
        </div>
      `;
    }

    function buildReportGeneratedHint(analysis, fallbackText) {
      const meta = getAnalysisSourceMeta(analysis || {});
      return meta.detail || fallbackText;
    }

    function formatOneDecimal(value) {
      const numeric = Number(value || 0);
      return new Intl.NumberFormat(undefined, {
        minimumFractionDigits: numeric % 1 === 0 ? 0 : 1,
        maximumFractionDigits: 1
      }).format(numeric);
    }

    function setActiveReportSwitchValue(value) {
      document.querySelectorAll('[data-report-switch-slot]').forEach(item => {
        item.classList.toggle('active', item.dataset.switchValue === value);
      });
    }

    function setReportSwitchMode(mode) {
      const switchRoot = document.querySelector('.report-metric-switch');
      const buttons = Array.from(document.querySelectorAll('[data-report-switch-slot]'));
      if (!switchRoot || buttons.length !== 3) {
        return;
      }

      if (mode === 'hidden') {
        switchRoot.style.display = 'none';
        return;
      }

      switchRoot.style.display = 'inline-flex';
      const configs = mode === 'usage'
        ? [
            { label: '15 Days', value: 'day' },
            { label: '4 Weeks', value: 'week' },
            { label: '3 Months', value: 'month' },
          ]
        : [
            { label: 'Messages', value: 'child_message_count' },
            { label: 'Logins', value: 'login_count' },
            { label: 'Minutes', value: 'used_minutes' },
          ];

      buttons.forEach((button, index) => {
        const config = configs[index];
        button.textContent = config.label;
        button.dataset.switchMode = mode;
        button.dataset.switchValue = config.value;
      });

      setActiveReportSwitchValue(mode === 'usage' ? reportState.usagePeriod : reportState.metric);
    }

    function toggleAutoReportSettings(forceState) {
      const popover = document.getElementById('autoReportSettingsPopover');
      if (!popover) {
        return;
      }

      const shouldOpen = typeof forceState === 'boolean'
        ? forceState
        : popover.style.display === 'none';
      popover.style.display = shouldOpen ? 'block' : 'none';
    }

    function beginReportRequest(childId) {
      reportState.requestKey += 1;
      reportState.childId = childId;
      return reportState.requestKey;
    }

    function isActiveReportRequest(requestKey, childId) {
      return reportState.requestKey === requestKey
        && Number(reportState.childId) === Number(childId)
        && document.getElementById('childReportModal').style.display === 'block';
    }

    function beginReportSelection() {
      reportState.selectionToken += 1;
      return reportState.selectionToken;
    }

    function isActiveReportSelection(requestKey, childId, selectionToken) {
      return isActiveReportRequest(requestKey, childId)
        && reportState.selectionToken === selectionToken;
    }

    function updateTrendSelectionMeta() {
      const selectedCount = reportState.selectedReportIds.length;
      const totalCount = reportState.history.length;
      document.getElementById('reportSelectionMeta').textContent = totalCount === 0
        ? ''
        : `${selectedCount} of ${totalCount} saved report(s) selected.`;
    }

    function toggleTrendSelectionMode(forceState) {
      const nextState = typeof forceState === 'boolean' ? forceState : !reportState.selectionMode;
      if (nextState && (!Array.isArray(reportState.history) || reportState.history.length === 0)) {
        return;
      }

      reportState.selectionMode = nextState;
      document.getElementById('reportSelectionToolbar').style.display = nextState ? 'flex' : 'none';
      document.getElementById('toggleTrendSelectionBtn').textContent = nextState ? 'Selection On' : 'Cumulative Analysis';

      if (nextState) {
        if (reportState.selectedReportIds.length === 0) {
          reportState.selectedReportIds = reportState.history.map(item => Number(item.id));
        }
      }

      updateTrendSelectionMeta();
      renderHistoryList();
    }

    function selectAllTrendReports() {
      reportState.selectedReportIds = reportState.history.map(item => Number(item.id));
      updateTrendSelectionMeta();
      renderHistoryList();
    }

    function selectTrendRange(rangeKey) {
      const months = {
        '1m': 1,
        '3m': 3,
        '6m': 6
      }[rangeKey];

      if (!months) {
        return;
      }

      const cutoff = new Date();
      cutoff.setMonth(cutoff.getMonth() - months);

      reportState.selectedReportIds = reportState.history
        .filter(item => {
          if (!item.created_at) {
            return false;
          }

          const createdAt = new Date(item.created_at);
          return !Number.isNaN(createdAt.getTime()) && createdAt >= cutoff;
        })
        .map(item => Number(item.id));

      updateTrendSelectionMeta();
      renderHistoryList();
    }

    function toggleTrendReportSelection(reportId) {
      const numericId = Number(reportId);
      if (!numericId) {
        return;
      }

      if (reportState.selectedReportIds.includes(numericId)) {
        reportState.selectedReportIds = reportState.selectedReportIds.filter(id => id !== numericId);
      } else {
        reportState.selectedReportIds = [...reportState.selectedReportIds, numericId];
      }

      updateTrendSelectionMeta();
      renderHistoryList();
    }

    function toggleDropdown() {
      document.getElementById('userDropdown').classList.toggle('show');
    }

    function showProfileModal() {
      document.getElementById('profileModal').style.display = 'block';
      document.getElementById('userDropdown').classList.remove('show');
      clearAlert();
      clearProfileErrors();
    }

    function closeProfileModal() {
      document.getElementById('profileModal').style.display = 'none';
      clearAlert();
      clearProfileErrors();
    }

    function showCreateChildModal() {
      clearChildErrors('create');
      document.getElementById('createChildForm').reset();
      document.getElementById('createChildModal').style.display = 'block';
    }

    function closeCreateChildModal() {
      document.getElementById('createChildModal').style.display = 'none';
      clearChildErrors('create');
    }

    function showManageChildModal(childId) {
      const child = childrenState.find(item => item.id === childId);
      if (!child) {
        return;
      }

      clearChildErrors('manage');
      populateManageChildModal(child);
      document.getElementById('manageChildModal').style.display = 'block';
    }

    function closeManageChildModal() {
      document.getElementById('manageChildModal').style.display = 'none';
      clearChildErrors('manage');
    }

    function showChildReportModal(childId) {
      const child = getChildById(childId);
      if (!child) {
        return;
      }

      beginReportRequest(childId);
      reportState.metric = 'child_message_count';
      reportState.usagePeriod = 'day';
      reportState.usageReport = null;
      reportState.activeSnapshot = null;
      reportState.activeRecord = null;
      reportState.activeSource = 'empty';
      reportState.settings = null;
      reportState.history = [];
      reportState.initializedSelection = false;
      reportState.historyLoaded = false;
      reportState.selectionToken = 0;
      reportState.selectionMode = false;
      reportState.selectedReportIds = [];
      reportState.trendAnalysis = null;
      setReportSwitchMode('usage');
      document.getElementById('childReportTitle').textContent = `${child.name}'s Report`;
      document.getElementById('childReportModal').style.display = 'block';
      document.getElementById('childReportContent').style.display = 'none';
      document.getElementById('childReportLoading').style.display = 'block';
      document.getElementById('reportGeneratedHint').textContent = 'Loading usage habit and chat reports.';
      document.getElementById('autoReportEnabled').checked = false;
      document.getElementById('autoReportFrequency').value = '7';
      document.getElementById('autoReportMeta').textContent = 'Loading report schedule...';
      document.getElementById('childReportActivityTitle').textContent = 'System Usage';
      document.getElementById('toggleTrendSelectionBtn').textContent = 'Cumulative Analysis';
      document.getElementById('reportSelectionToolbar').style.display = 'none';
      document.getElementById('reportSelectionMeta').textContent = '';
      document.getElementById('selectedReportMeta').textContent = '';
      document.getElementById('childReportSummary').innerHTML = '';
      document.getElementById('childReportInsights').innerHTML = '';
      document.getElementById('childReportChart').innerHTML = '';
      document.getElementById('childReportSampleStatus').innerHTML = '';
      document.getElementById('reportSourceMeta').innerHTML = '';
      document.getElementById('childReportAnalysis').innerHTML = '';
      document.getElementById('reportHistoryMeta').textContent = '';
      document.getElementById('usageHabitList').innerHTML = '';
      document.getElementById('childReportHistoryList').innerHTML = '';
      toggleAutoReportSettings(false);
      loadChildReportHistory();
    }

    function closeChildReportModal() {
      reportState.requestKey += 1;
      reportState.selectionToken += 1;
      reportState.childId = null;
      toggleAutoReportSettings(false);
      document.getElementById('childReportModal').style.display = 'none';
    }

    function buildTimeOptions(selectId, values) {
      const select = document.getElementById(selectId);
      if (!select) {
        return;
      }

      select.innerHTML = values.map(value => `<option value="${value}">${value}</option>`).join('');
    }

    function initializeTimeSelectors() {
      const hourOptions = Array.from({ length: 24 }, (_, index) => String(index).padStart(2, '0'));
      buildTimeOptions('allowed_login_start_hour', hourOptions);
      buildTimeOptions('allowed_login_end_hour', hourOptions);
      buildTimeOptions('allowed_login_start_minute', TIME_MINUTE_OPTIONS);
      buildTimeOptions('allowed_login_end_minute', TIME_MINUTE_OPTIONS);
    }

    function normalizeTimeForSelector(timeValue, fallback = '00:00') {
      const baseValue = typeof timeValue === 'string' && timeValue.includes(':') ? timeValue : fallback;
      const [hourPart, minutePart] = baseValue.split(':');
      const hour = String(Math.max(0, Math.min(23, parseInt(hourPart || '0', 10) || 0))).padStart(2, '0');
      const minuteNumber = parseInt(minutePart || '0', 10) || 0;
      const normalizedMinute = String(Math.floor(minuteNumber / 10) * 10).padStart(2, '0');
      return { hour, minute: normalizedMinute };
    }

    function setTimeSelectorValue(prefix, timeValue, fallback) {
      const normalized = normalizeTimeForSelector(timeValue, fallback);
      document.getElementById(`${prefix}_hour`).value = normalized.hour;
      document.getElementById(`${prefix}_minute`).value = normalized.minute;
      document.getElementById(prefix).value = `${normalized.hour}:${normalized.minute}`;
    }

    function syncTimeHiddenValue(prefix) {
      const hour = document.getElementById(`${prefix}_hour`).value;
      const minute = document.getElementById(`${prefix}_minute`).value;
      document.getElementById(prefix).value = `${hour}:${minute}`;
    }

    function getChildById(childId) {
      return childrenState.find(item => item.id === childId) || null;
    }

    function getLoginToggleButtonLabel(loginDisabled) {
      return loginDisabled ? 'Allow Login Again' : 'Temporarily Disable Login';
    }

    function getLoginStatusLabel(loginDisabled) {
      return loginDisabled ? 'Temporarily blocked by parent' : 'Allowed to log in';
    }

    function getLoginStatusChipClass(loginDisabled) {
      return loginDisabled ? 'status-chip-paused' : 'status-chip-active';
    }

    function formatNumber(value) {
      return new Intl.NumberFormat().format(Number(value || 0));
    }

    function formatReportDate(value) {
      if (!value) {
        return 'Not available';
      }

      const normalized = String(value).includes('T') ? String(value) : String(value).replace(' ', 'T');
      const date = new Date(normalized);
      if (Number.isNaN(date.getTime())) {
        return value;
      }

      try {
        return date.toLocaleString(undefined, { timeZone: APP_TIMEZONE });
      } catch (error) {
        return date.toLocaleString();
      }
    }

    function populateManageChildModal(child) {
      document.getElementById('manageChildTitle').textContent = `Manage ${child.name}`;
      document.getElementById('manage_child_id').value = child.id;
      setTimeSelectorValue('allowed_login_start', child.allowed_login_start || '00:00', '00:00');
      setTimeSelectorValue('allowed_login_end', child.allowed_login_end || '23:50', '23:50');
      document.getElementById('daily_login_minutes').value = child.daily_login_minutes;
      document.getElementById('manage_password').value = '';
      document.getElementById('manage_confirm_password').value = '';
      document.getElementById('manageChildLastLogin').textContent = formatDateTime(child.last_login_at) || 'Never';
      document.getElementById('manageChildUsage').textContent = `${child.used_today_minutes} / ${child.daily_login_minutes} minutes`;
      document.getElementById('manageChildLoginStatus').textContent = getLoginStatusLabel(child.login_disabled);

      const toggleButton = document.getElementById('manageChildToggleLoginBtn');
      toggleButton.textContent = getLoginToggleButtonLabel(child.login_disabled);
      toggleButton.className = child.login_disabled ? 'ghost-btn' : 'warning-btn';
    }

    function showAlert(message, type = 'success') {
      const alertDiv = document.getElementById('alertMessage');
      alertDiv.textContent = message;
      alertDiv.className = type;
      alertDiv.style.display = 'block';

      if (alertTimeout) {
        clearTimeout(alertTimeout);
      }

      alertTimeout = setTimeout(() => {
        alertDiv.style.display = 'none';
      }, 5000);
    }

    function clearAlert() {
      const alertDiv = document.getElementById('alertMessage');
      alertDiv.style.display = 'none';
      if (alertTimeout) {
        clearTimeout(alertTimeout);
      }
    }

    function showPageAlert(message, type = 'success') {
      const alertDiv = document.getElementById('pageAlert');
      alertDiv.textContent = message;
      alertDiv.className = `section-alert ${type}`;
      alertDiv.style.display = 'block';

      if (pageAlertTimeout) {
        clearTimeout(pageAlertTimeout);
      }

      pageAlertTimeout = setTimeout(() => {
        alertDiv.style.display = 'none';
      }, 5000);
    }

    function clearProfileErrors() {
      document.querySelectorAll('#profileModal .error-text').forEach(el => el.textContent = '');
    }

    function clearChildErrors(scope) {
      const selector = scope === 'manage'
        ? '#manageChildModal .error-text'
        : '#createChildModal .error-text';
      document.querySelectorAll(selector).forEach(el => {
        el.textContent = '';
        el.className = 'error-text';
      });
    }

    function displayProfileErrors(errors) {
      clearProfileErrors();
      for (const [field, messages] of Object.entries(errors)) {
        let errorElementId = `${field}Error`;

        if (field === 'current-password') {
          errorElementId = 'currentPasswordError';
        } else if (field === 'password') {
          errorElementId = 'newPasswordError';
        } else if (field === 'confirm-password') {
          errorElementId = 'confirmPasswordError';
        }

        const errorElement = document.getElementById(errorElementId);
        if (errorElement && messages.length > 0) {
          errorElement.textContent = messages[0];
        }
      }
    }

    function displayChildErrors(errors, scope) {
      clearChildErrors(scope);
      const suffix = scope === 'manage' ? 'ManageError' : 'Error';

      for (const [field, messages] of Object.entries(errors)) {
        let errorElementId = `${field}${suffix}`;
        if (scope === 'manage') {
          if (field === 'password') {
            errorElementId = 'passwordManageError';
          } else if (field === 'confirm_password') {
            errorElementId = 'confirm_passwordManageError';
          }
        }

        const errorElement = document.getElementById(errorElementId);
        if (errorElement && messages.length > 0) {
          errorElement.textContent = messages[0];
          errorElement.className = 'error-text error';
        }
      }
    }

    function setFieldMessage(elementId, message = '', type = '') {
      const element = document.getElementById(elementId);
      if (!element) {
        return;
      }

      element.textContent = message;
      element.className = type ? `error-text ${type}` : 'error-text';
    }

    function escapeHtml(value) {
      return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function formatDate(value) {
      if (!value) {
        return 'Not set';
      }

      const date = new Date(value);
      if (Number.isNaN(date.getTime())) {
        return value;
      }

      return date.toLocaleDateString();
    }

    function formatDateTime(value) {
      if (!value) {
        return '';
      }

      const normalized = String(value).includes('T') ? String(value) : String(value).replace(' ', 'T');
      const date = new Date(normalized);
      if (Number.isNaN(date.getTime())) {
        return value;
      }

      try {
        return date.toLocaleString(undefined, { timeZone: APP_TIMEZONE });
      } catch (error) {
        return date.toLocaleString();
      }
    }

    function renderReportSummary(report) {
      const summary = report.summary || {};
      document.getElementById('childReportSummary').innerHTML = [
        {
          label: 'Active Days',
          value: summary.active_days,
          note: `${report.days} day span`
        },
        {
          label: 'Logins',
          value: summary.total_logins,
          note: 'Tracked after each sign-in'
        },
        {
          label: 'Messages',
          value: summary.total_child_messages,
          note: `${summary.total_assistant_messages || 0} assistant replies, low weight`
        },
        {
          label: 'Conversations',
          value: summary.total_conversations,
          note: 'New chats started'
        },
        {
          label: 'Online Minutes',
          value: summary.total_minutes,
          note: `Avg ${summary.average_minutes_per_active_day || 0} min on active days`
        }
      ].map(item => `
        <article class="report-stat-card">
          <span class="report-stat-label">${item.label}</span>
          <span class="report-stat-value">${formatNumber(item.value)}</span>
          <span class="report-stat-note">${escapeHtml(item.note)}</span>
        </article>
      `).join('');
    }

    function renderReportInsights(report) {
      const insights = Array.isArray(report.insights) ? report.insights : [];
      const lastLogin = report.summary && report.summary.last_login_at
        ? `Last login: ${formatReportDate(report.summary.last_login_at)}`
        : 'Last login: Never';

      document.getElementById('childReportInsights').innerHTML = [lastLogin, ...insights]
        .map(item => `<div class="report-insight-pill">${escapeHtml(item)}</div>`)
        .join('');
    }

    function getMetricLabel(metric) {
      switch (metric) {
        case 'login_count':
          return 'Logins';
        case 'used_minutes':
          return 'Minutes';
        default:
          return 'Messages';
      }
    }

    function getMetricClass(metric) {
      switch (metric) {
        case 'login_count':
          return 'metric-logins';
        case 'used_minutes':
          return 'metric-minutes';
        default:
          return 'metric-messages';
      }
    }

    function renderReportChart() {
      if (!reportState.activeSnapshot) {
        return;
      }

      const series = Array.isArray(reportState.activeSnapshot.series) ? reportState.activeSnapshot.series : [];
      const metric = reportState.metric;
      const values = series.map(day => Number(day[metric] || 0));
      const maxValue = Math.max(...values, 1);

      document.getElementById('childReportChart').innerHTML = series.map(day => {
        const value = Number(day[metric] || 0);
        const height = Math.max(6, Math.round((value / maxValue) * 100));
        return `
          <div class="report-bar-col" title="${escapeHtml(day.date)} · ${escapeHtml(getMetricLabel(metric))}: ${value}">
            <span class="report-bar-value">${formatNumber(value)}</span>
            <div class="report-bar-track">
              <div class="report-bar-fill ${getMetricClass(metric)}" style="height: ${height}%"></div>
            </div>
            <span class="report-bar-label">${escapeHtml(day.label)}</span>
          </div>
        `;
      }).join('');
    }

    function renderContentReadiness(readiness) {
      let label = 'No recent child messages';
      if (readiness) {
        if (readiness.can_generate) {
          label = 'Ready for AI report';
        } else if (readiness.recommended_sample_met) {
          label = 'Need more new chat';
        } else if (readiness.message_count > 0) {
          label = 'Limited sample';
        }
      }

      document.getElementById('childReportSampleStatus').innerHTML = readiness ? `
        <strong>${escapeHtml(label)}</strong><br>
        ${formatNumber(readiness.message_count)} child messages · ${formatNumber(readiness.increment_message_count || 0)} new since last saved report · ${formatNumber(readiness.active_days)} active day(s)
      ` : '';
    }

    function riskClassFromAnalysis(analysis) {
      const riskDimensions = Array.isArray(analysis.risk_dimensions) ? analysis.risk_dimensions : [];
      const levels = [
        ...(analysis.alerts || []).map(item => item.level),
        ...riskDimensions.map(item => item.level)
      ];
      if (levels.includes('high')) {
        return 'high';
      }
      if (levels.includes('medium')) {
        return 'medium';
      }
      return 'low';
    }

    function renderAnalysisList(items, emptyText = 'No recent items detected.') {
      if (!Array.isArray(items) || items.length === 0) {
        return `<div class="report-list-item">${escapeHtml(emptyText)}</div>`;
      }

      return items.map(item => {
        const text = typeof item === 'string'
          ? item
          : (item.summary || item.why_it_matters || item.detail || item.title || JSON.stringify(item));
        const label = typeof item === 'string' ? '' : (item.name ? `<strong>${escapeHtml(item.name)}:</strong> ` : '');
        return `<div class="report-list-item">${label}${escapeHtml(text)}</div>`;
      }).join('');
    }

    function renderRiskDimensionList(items, emptyText) {
      if (!Array.isArray(items) || items.length === 0) {
        return `<div class="report-list-item">${escapeHtml(emptyText)}</div>`;
      }

      return items.map(item => `
        <div class="report-list-item">
          <strong>${escapeHtml(item.name || 'Signal')}</strong>
          <span class="report-inline-level ${escapeHtml(item.level || 'low')}">${escapeHtml(item.level || 'low')}</span><br>
          ${escapeHtml(item.summary || '')}<br>
          ${(item.evidence || item.why_it_matters) ? `<span class="report-helper-text">${escapeHtml(item.evidence || item.why_it_matters || '')}</span>` : ''}
          ${item.parent_action ? `<div class="report-helper-text" style="margin-top:8px;"><strong>Parent action:</strong> ${escapeHtml(item.parent_action)}</div>` : ''}
        </div>
      `).join('');
    }

    function buildSelectedReportMeta() {
      if (reportState.activeSource === 'usage' && reportState.usageReport) {
        return `Usage habit report · Updated ${formatReportDate(reportState.usageReport.updated_at)}`;
      }

      if (reportState.activeSource === 'saved' && reportState.activeRecord) {
        const savedAt = reportState.activeRecord.created_at || reportState.activeRecord.updated_at;
        const generatedAt = savedAt
          ? formatReportDate(savedAt)
          : 'Unknown time';
        return `${reportState.activeRecord.generation_mode === 'auto' ? 'Auto' : 'Manual'} saved report · ${generatedAt}`;
      }

      if (reportState.activeSource === 'trend' && reportState.trendAnalysis) {
        return `${reportState.trendAnalysis.selected_report_count || 0} report(s) selected`;
      }

      if (reportState.activeSource === 'preview' && reportState.activeSnapshot) {
        return 'Threshold preview';
      }

      return '';
    }

    function updateActiveHistorySelection() {
      document.querySelectorAll('[data-usage-report]').forEach(item => {
        item.classList.toggle('active', reportState.activeSource === 'usage');
      });

      document.querySelectorAll('[data-report-history-id]').forEach(item => {
        const reportId = Number(item.dataset.reportHistoryId);
        item.classList.toggle(
          'active',
          !reportState.selectionMode
            && reportState.activeSource === 'saved'
            && Number(reportState.activeRecord && reportState.activeRecord.id) === reportId
        );
        item.classList.toggle(
          'selected',
          reportState.selectionMode && reportState.selectedReportIds.includes(reportId)
        );
      });
    }

    function renderUsageReportList() {
      const container = document.getElementById('usageHabitList');
      if (!container) {
        return;
      }

      if (!reportState.usageReport) {
        container.innerHTML = '';
        return;
      }

      const dailySummary = reportState.usageReport.ranges && reportState.usageReport.ranges.day
        ? reportState.usageReport.ranges.day.summary
        : null;

      container.innerHTML = `
        <button type="button" class="report-usage-item" data-usage-report="1" onclick="openUsageHabitReport()">
          <span class="report-usage-kicker">
            <i class="fas fa-chart-line"></i>
            Updated Daily
          </span>
          <span class="report-usage-title">System Usage Habit</span>
          <div class="report-usage-meta">
            ${escapeHtml(formatNumber(dailySummary && dailySummary.login_count || 0))} logins in the last 15 days ·
            ${escapeHtml(formatOneDecimal(dailySummary && dailySummary.avg_estimated_minutes_per_login || 0))} min/login
          </div>
          <div class="report-usage-meta">
            ${escapeHtml(formatOneDecimal(dailySummary && dailySummary.avg_child_messages_per_login || 0))} child messages per login ·
            Latest login ${escapeHtml(reportState.usageReport.child && reportState.usageReport.child.last_login_at ? formatReportDate(reportState.usageReport.child.last_login_at) : 'Never')}
          </div>
        </button>
      `;

      updateActiveHistorySelection();
    }

    function renderHistoryList() {
      const container = document.getElementById('childReportHistoryList');
      const historyMeta = document.getElementById('reportHistoryMeta');
      const trendButton = document.getElementById('toggleTrendSelectionBtn');

      if (!Array.isArray(reportState.history) || reportState.history.length === 0) {
        reportState.selectionMode = false;
        document.getElementById('reportSelectionToolbar').style.display = 'none';
        trendButton.textContent = 'Cumulative Analysis';
        historyMeta.textContent = '';
        container.innerHTML = '';
        trendButton.disabled = true;
        updateActiveHistorySelection();
        return;
      }

      trendButton.disabled = false;
      historyMeta.textContent = `${reportState.history.length} saved snapshot(s)`;
      container.innerHTML = reportState.history.map(item => {
        const savedAtLabel = formatReportDate(item.created_at || item.updated_at) || 'Unknown time';
        const scopeLabel = item.scope_started_at && item.scope_ended_at
          ? `${formatDate(item.scope_started_at)} - ${formatDate(item.scope_ended_at)}`
          : `${item.window_days} day span`;
        const activityLabel = `${formatNumber(item.sample_message_count || 0)} child msgs · ${formatNumber(item.message_record_count || 0)} retained`;
        const inSelection = reportState.selectionMode;
        const clickHandler = inSelection
          ? `toggleTrendReportSelection(${item.id})`
          : `openSavedReport(${item.id})`;
        const typeClass = item.generation_mode === 'auto' ? 'auto' : 'manual';

        return `
          <button
            type="button"
            class="report-history-item ${inSelection ? 'in-selection' : ''}"
            data-report-history-id="${item.id}"
            onclick="${clickHandler}"
          >
            <div class="report-history-row">
              ${inSelection ? `<span class="report-history-check"><i class="fas fa-check"></i></span>` : ''}
              <div class="report-history-body">
                <div class="report-history-topline">
                  <div class="report-history-badges">
                    <span class="report-history-pill ${typeClass}">${escapeHtml(item.generation_mode === 'auto' ? 'Auto' : 'Manual')}</span>
                    <span class="report-history-pill">${escapeHtml(item.risk_level || 'low')} risk</span>
                    <span class="report-history-pill">${escapeHtml(item.confidence || 'none')} confidence</span>
                  </div>
                  <span class="report-history-time">${escapeHtml(savedAtLabel)}</span>
                </div>
                <span class="report-history-title">${escapeHtml(item.headline || `${item.generation_mode === 'auto' ? 'Auto' : 'Manual'} report`)}</span>
                <span class="report-history-scope">${escapeHtml(scopeLabel)}</span>
                <div class="report-history-divider"></div>
                <div class="report-history-footline">
                  <span class="report-history-meta">${escapeHtml(activityLabel)}</span>
                  <span class="report-history-meta">${escapeHtml(item.report_day || '')}</span>
                </div>
              </div>
            </div>
          </button>
        `;
      }).join('');

      updateActiveHistorySelection();
    }

    function renderReportSettings() {
      const settings = reportState.settings;
      if (!settings) {
        return;
      }

      document.getElementById('autoReportEnabled').checked = !!settings.auto_generate_enabled;
      const periodDays = String(settings.auto_generate_period_days || settings.auto_generate_frequency_days || 7);
      document.getElementById('autoReportFrequency').value = periodDays;

      const nextDue = settings.next_report_due_at ? formatReportDate(settings.next_report_due_at) : 'Not scheduled';
      const lastGenerated = settings.last_report_generated_at ? formatReportDate(settings.last_report_generated_at) : 'Never';
      document.getElementById('autoReportMeta').textContent = settings.auto_generate_enabled
        ? `Every ${periodDays} day(s) · Next ${nextDue} · Last ${lastGenerated}`
        : 'Auto generation is off.';
    }

    function renderContentAnalysis(analysis, readiness) {
      const riskClass = riskClassFromAnalysis(analysis);
      const interests = Array.isArray(analysis.interests) ? analysis.interests : [];
      const topics = Array.isArray(analysis.topics) ? analysis.topics : [];
      const emotional = analysis.emotional_overview || {};
      const wellbeing = analysis.wellbeing || {};
      const guidance = Array.isArray(analysis.parent_guidance) ? analysis.parent_guidance : [];
      const alerts = Array.isArray(analysis.alerts) ? analysis.alerts : [];
      const riskDimensions = Array.isArray(analysis.risk_dimensions) ? analysis.risk_dimensions : [];
      const thinkingPatterns = Array.isArray(analysis.thinking_patterns) ? analysis.thinking_patterns : [];
      const protectiveFactors = Array.isArray(analysis.protective_factors) ? analysis.protective_factors : [];

      document.getElementById('selectedReportMeta').textContent = buildSelectedReportMeta();
      renderReportSourceMeta(analysis);
      document.getElementById('childReportAnalysis').innerHTML = `
        <div class="report-analysis-grid">
          <article class="report-analysis-card full">
            <div class="report-risk-row">
              <span class="report-risk-badge ${riskClass}">${escapeHtml(riskClass)} risk</span>
              <span class="report-helper-text">${escapeHtml(analysis.sample_confidence || readiness.confidence || 'none')} confidence sample</span>
            </div>
            <h4>${escapeHtml(analysis.headline || 'Recent wellbeing summary')}</h4>
            <p>${escapeHtml(analysis.disclaimer || 'This report summarizes recent patterns only and is not a diagnosis.')}</p>
          </article>

          <article class="report-analysis-card">
            <h4>Topic Overview</h4>
            <p>${escapeHtml(analysis.topic_overview || 'No topic overview available.')}</p>
          </article>

          <article class="report-analysis-card">
            <h4>Emotional Snapshot</h4>
            <p>${escapeHtml(emotional.summary || 'No emotional summary available.')}</p>
            ${renderAnalysisList(Array.isArray(emotional.supporting_signals) ? emotional.supporting_signals : [], 'No supporting signals available.')}
          </article>

          <article class="report-analysis-card">
            <h4>Recent Themes</h4>
            <div class="report-tag-list">
              ${topics.length > 0 ? topics.map(item => `<span class="report-tag">${escapeHtml(item.name || 'Theme')}</span>`).join('') : '<span class="report-helper-text">No clear repeated themes yet.</span>'}
            </div>
          </article>

          <article class="report-analysis-card">
            <h4>Interests</h4>
            <div class="report-tag-list">
              ${interests.length > 0 ? interests.map(item => `<span class="report-tag">${escapeHtml(item.name || 'Interest')}</span>`).join('') : '<span class="report-helper-text">No stable interests extracted.</span>'}
            </div>
          </article>

          <article class="report-analysis-card full">
            <h4>Risk Dimensions</h4>
            ${renderRiskDimensionList(riskDimensions, 'No major risk dimension stands out in this sample.')}
          </article>

          <article class="report-analysis-card">
            <h4>Thinking Patterns</h4>
            ${renderRiskDimensionList(thinkingPatterns, 'No strong rigid or conflict-focused thinking pattern stands out in this sample.')}
          </article>

          <article class="report-analysis-card">
            <h4>Protective Factors</h4>
            ${renderAnalysisList(protectiveFactors, 'No clear protective factor was visible in chat alone.')}
          </article>

          <article class="report-analysis-card">
            <h4>Wellbeing Signals</h4>
            <p>${escapeHtml(wellbeing.summary || 'No wellbeing summary available.')}</p>
          </article>

          <article class="report-analysis-card">
            <h4>Strengths / Positives</h4>
            ${renderAnalysisList(Array.isArray(wellbeing.strengths) ? wellbeing.strengths : [], 'No clear strengths were extracted from this sample.')}
          </article>

          <article class="report-analysis-card">
            <h4>Watch Points</h4>
            ${renderAnalysisList(Array.isArray(wellbeing.watch_points) ? wellbeing.watch_points : [], 'No specific watch point was extracted from this sample.')}
          </article>

          <article class="report-analysis-card full">
            <h4>Parent Guidance</h4>
            ${renderAnalysisList(guidance, 'No parent guidance available.')}
          </article>

          <article class="report-analysis-card full">
            <h4>Alerts</h4>
            ${alerts.length > 0 ? renderAnalysisList(alerts.map(item => `${(item.level || 'low').toUpperCase()}: ${item.title || 'Alert'} - ${item.detail || ''}`), 'No alerts.') : '<div class="report-list-item">No medium or high-priority alert was extracted from this sample.</div>'}
          </article>
        </div>
      `;
    }

    function renderTrendAnalysis(analysis) {
      const trajectory = analysis.risk_trajectory || {};
      const engagement = analysis.engagement || {};
      const riskLevel = trajectory.level || 'low';

      document.getElementById('selectedReportMeta').textContent = buildSelectedReportMeta();
      renderReportSourceMeta(analysis);
      document.getElementById('childReportSampleStatus').innerHTML = `
        <strong>${formatNumber(analysis.selected_report_count || 0)} report(s) selected</strong><br>
        ${formatNumber(engagement.child_message_count || 0)} child messages · ${formatNumber(engagement.active_days || 0)} active day(s)
      `;
      document.getElementById('childReportAnalysis').innerHTML = `
        <div class="report-analysis-grid">
          <article class="report-analysis-card full">
            <div class="report-risk-row">
              <span class="report-risk-badge ${escapeHtml(riskLevel)}">${escapeHtml(riskLevel)} risk</span>
              <span class="report-helper-text">${escapeHtml(trajectory.direction || 'stable')}</span>
            </div>
            <h4>${escapeHtml(analysis.headline || 'Cumulative analysis')}</h4>
            <p>${escapeHtml(analysis.summary || 'No summary available.')}</p>
          </article>

          <article class="report-analysis-card">
            <h4>Period</h4>
            <p>${escapeHtml(
              analysis.date_span && analysis.date_span.start && analysis.date_span.end
                ? `${formatReportDate(analysis.date_span.start)} to ${formatReportDate(analysis.date_span.end)}`
                : 'Not available'
            )}</p>
          </article>

          <article class="report-analysis-card">
            <h4>Engagement</h4>
            <p>${escapeHtml(engagement.summary || 'No engagement summary available.')}</p>
          </article>

          <article class="report-analysis-card full">
            <h4>Recurring Risks</h4>
            ${renderRiskDimensionList(analysis.recurring_risks || [], 'No recurring risk pattern stood out across the selected reports.')}
          </article>

          <article class="report-analysis-card">
            <h4>Thinking Trends</h4>
            ${renderRiskDimensionList(analysis.thinking_trends || [], 'No recurring thinking pattern stood out across the selected reports.')}
          </article>

          <article class="report-analysis-card">
            <h4>Protective Trends</h4>
            ${renderAnalysisList(analysis.protective_trends || [], 'No recurring protective factor stood out across the selected reports.')}
          </article>

          <article class="report-analysis-card">
            <h4>Topic Trends</h4>
            ${renderAnalysisList(analysis.topic_trends || [], 'No recurring topic stood out across the selected reports.')}
          </article>

          <article class="report-analysis-card full">
            <h4>Parent Guidance</h4>
            ${renderAnalysisList(analysis.parent_guidance || [], 'No parent guidance available.')}
          </article>
        </div>
      `;
    }

    function renderUsageHabitSummary(range) {
      const summary = range.summary || {};
      document.getElementById('childReportSummary').innerHTML = [
        {
          label: 'Active Days',
          value: summary.active_days || 0,
          note: range.title
        },
        {
          label: 'Logins',
          value: summary.login_count || 0,
          note: 'Recorded sign-ins'
        },
        {
          label: 'Avg Min / Login*',
          value: formatOneDecimal(summary.avg_estimated_minutes_per_login || 0),
          note: 'Estimated from daily totals'
        },
        {
          label: 'Median Min / Login*',
          value: formatOneDecimal(summary.median_estimated_minutes_per_login || 0),
          note: 'Estimated from daily totals'
        },
        {
          label: 'Avg Child Msg / Login',
          value: formatOneDecimal(summary.avg_child_messages_per_login || 0),
          note: 'Child-authored messages only'
        }
      ].map(item => `
        <article class="report-stat-card">
          <span class="report-stat-label">${item.label}</span>
          <span class="report-stat-value">${escapeHtml(item.value)}</span>
          <span class="report-stat-note">${escapeHtml(item.note)}</span>
        </article>
      `).join('');
    }

    function renderUsageHabitInsights(report, range) {
      const summary = range.summary || {};
      const latestLogin = summary.latest_login_at
        ? `Latest login in view: ${formatReportDate(summary.latest_login_at)}`
        : 'Latest login in view: Never';
      const earliestLogin = summary.earliest_login_at
        ? `Earliest login in view: ${formatReportDate(summary.earliest_login_at)}`
        : 'Earliest login in view: Not available';
      const childLastLogin = report.child && report.child.last_login_at
        ? `Most recent recorded login: ${formatReportDate(report.child.last_login_at)}`
        : 'Most recent recorded login: Never';

      document.getElementById('childReportInsights').innerHTML = [
        latestLogin,
        earliestLogin,
        childLastLogin,
        '* Per-login duration is estimated from daily total minutes because historical single-session timing is not stored.'
      ].map(item => `<div class="report-insight-pill">${escapeHtml(item)}</div>`).join('');
    }

    function renderUsageHabitChart(range) {
      const buckets = Array.isArray(range.buckets) ? range.buckets : [];
      document.getElementById('childReportChart').innerHTML = `
        <div class="usage-bucket-grid">
          ${buckets.map(bucket => `
            <article class="usage-bucket-card">
              <div class="usage-bucket-title">${escapeHtml(bucket.label || '')}</div>
              <div class="usage-bucket-meta">${formatNumber(bucket.login_count || 0)} logins · ${formatNumber(bucket.used_minutes || 0)} min</div>
              <div class="usage-bucket-meta">${formatOneDecimal(bucket.avg_estimated_minutes_per_login || 0)} avg min/login*</div>
              <div class="usage-bucket-meta">${formatOneDecimal(bucket.avg_child_messages_per_login || 0)} child msgs/login</div>
            </article>
          `).join('')}
        </div>
      `;
    }

    function renderUsageHabitAnalysis(report, range) {
      const summary = range.summary || {};
      const ranges = report.ranges || {};
      const rangeCards = ['day', 'week', 'month'].map(periodKey => {
        const period = ranges[periodKey];
        const periodSummary = period && period.summary ? period.summary : {};
        const labelMap = {
          day: '15 Days',
          week: '4 Weeks',
          month: '3 Months'
        };

        return `
          <article class="report-analysis-card">
            <h4>${labelMap[periodKey]}</h4>
            <p>${formatNumber(periodSummary.login_count || 0)} logins across ${formatNumber(periodSummary.active_days || 0)} active day(s).</p>
            <div class="report-helper-text">${formatOneDecimal(periodSummary.avg_estimated_minutes_per_login || 0)} avg min/login* · ${formatOneDecimal(periodSummary.avg_child_messages_per_login || 0)} child msgs/login</div>
          </article>
        `;
      }).join('');

      const bucketRows = (Array.isArray(range.buckets) ? range.buckets : []).map(bucket => `
        <div class="report-list-item">
          <strong>${escapeHtml(bucket.label || '')}</strong><br>
          ${formatNumber(bucket.login_count || 0)} logins · ${formatNumber(bucket.used_minutes || 0)} min ·
          ${formatOneDecimal(bucket.avg_estimated_minutes_per_login || 0)} avg min/login* ·
          ${formatOneDecimal(bucket.avg_child_messages_per_login || 0)} child msgs/login
        </div>
      `).join('');

      document.getElementById('selectedReportMeta').textContent = buildSelectedReportMeta();
      renderReportSourceMeta({
        source: report.source,
        source_detail: report.source_detail,
      });
      document.getElementById('childReportSampleStatus').innerHTML = `
        <strong>${escapeHtml(range.title)}</strong><br>
        ${formatNumber(summary.login_count || 0)} logins · ${formatNumber(summary.used_minutes || 0)} total minutes · ${formatNumber(summary.child_message_count || 0)} child messages
      `;
      document.getElementById('childReportAnalysis').innerHTML = `
        <div class="report-analysis-grid">
          <article class="report-analysis-card full">
            <h4>Usage Habit Summary</h4>
            <p>
              ${formatNumber(summary.login_count || 0)} sign-in(s) were recorded across ${formatNumber(summary.active_days || 0)} active day(s).
              Estimated per-login duration averages ${formatOneDecimal(summary.avg_estimated_minutes_per_login || 0)} minutes, with a median of ${formatOneDecimal(summary.median_estimated_minutes_per_login || 0)} and a highest daily estimate of ${formatOneDecimal(summary.max_estimated_minutes_per_login || 0)}.
              The child sends about ${formatOneDecimal(summary.avg_child_messages_per_login || 0)} child-authored message(s) per login.
            </p>
          </article>

          ${rangeCards}

          <article class="report-analysis-card full">
            <h4>${escapeHtml(range.title)} Breakdown</h4>
            ${bucketRows || '<div class="report-list-item">No usage record was captured in this window.</div>'}
          </article>
        </div>
      `;
    }

    function renderUsageHabitReport() {
      if (!reportState.usageReport) {
        renderReportEmptyState();
        return;
      }

      const ranges = reportState.usageReport.ranges || {};
      const range = ranges[reportState.usagePeriod] || ranges.day;
      if (!range) {
        renderReportEmptyState();
        return;
      }

      document.getElementById('childReportLoading').style.display = 'none';
      document.getElementById('childReportContent').style.display = 'grid';
      document.getElementById('childReportActivityTitle').textContent = 'System Usage';
      setReportSwitchMode('usage');
      renderUsageHabitSummary(range);
      renderUsageHabitInsights(reportState.usageReport, range);
      renderUsageHabitChart(range);
      renderUsageHabitAnalysis(reportState.usageReport, range);
      updateActiveHistorySelection();
    }

    function renderReportEmptyState() {
      document.getElementById('childReportLoading').style.display = 'none';
      document.getElementById('childReportContent').style.display = 'grid';
      document.getElementById('childReportSummary').innerHTML = '';
      document.getElementById('childReportInsights').innerHTML = '';
      document.getElementById('childReportChart').innerHTML = '';
      document.getElementById('childReportSampleStatus').innerHTML = '';
      document.getElementById('reportSourceMeta').innerHTML = '';
      document.getElementById('childReportAnalysis').innerHTML = '';
      document.getElementById('selectedReportMeta').textContent = '';
      updateActiveHistorySelection();
    }

    function renderTrendOverview(analysis) {
      const engagement = analysis.engagement || {};
      document.getElementById('childReportActivityTitle').textContent = 'Trend Coverage';
      setReportSwitchMode('hidden');
      document.getElementById('childReportSummary').innerHTML = [
        {
          label: 'Reports',
          value: analysis.selected_report_count || 0,
          note: 'Selected for trend analysis'
        },
        {
          label: 'Child Messages',
          value: engagement.child_message_count || 0,
          note: 'Distinct retained messages'
        },
        {
          label: 'Active Days',
          value: engagement.active_days || 0,
          note: 'Across selected reports'
        },
        {
          label: 'Manual',
          value: engagement.manual_reports || 0,
          note: 'Manual reports'
        },
        {
          label: 'Auto',
          value: engagement.auto_reports || 0,
          note: 'Auto reports'
        }
      ].map(item => `
        <article class="report-stat-card">
          <span class="report-stat-label">${item.label}</span>
          <span class="report-stat-value">${formatNumber(item.value)}</span>
          <span class="report-stat-note">${escapeHtml(item.note)}</span>
        </article>
      `).join('');

      const periodText = analysis.date_span && analysis.date_span.start && analysis.date_span.end
        ? `${formatReportDate(analysis.date_span.start)} to ${formatReportDate(analysis.date_span.end)}`
        : 'Not available';

      document.getElementById('childReportInsights').innerHTML = [
        periodText,
        analysis.summary || 'No summary available.'
      ].map(item => `<div class="report-insight-pill">${escapeHtml(item)}</div>`).join('');

      document.getElementById('childReportChart').innerHTML = '<div class="report-empty-state">Trend analysis is based on selected saved reports rather than one single day-by-day activity chart.</div>';
    }

    function renderActiveSnapshot() {
      if (reportState.activeSource === 'usage' && reportState.usageReport) {
        renderUsageHabitReport();
        return;
      }

      if (reportState.activeSource === 'trend' && reportState.trendAnalysis) {
        document.getElementById('childReportLoading').style.display = 'none';
        document.getElementById('childReportContent').style.display = 'grid';
        renderTrendOverview(reportState.trendAnalysis);
        renderTrendAnalysis(reportState.trendAnalysis);
        updateActiveHistorySelection();
        return;
      }

      if (!reportState.activeSnapshot) {
        renderReportEmptyState();
        return;
      }

      document.getElementById('childReportLoading').style.display = 'none';
      document.getElementById('childReportContent').style.display = 'grid';
      document.getElementById('childReportActivityTitle').textContent = 'Recent Activity';
      setReportSwitchMode('content');
      renderReportSummary(reportState.activeSnapshot);
      renderReportInsights(reportState.activeSnapshot);
      renderReportChart();
      if (reportState.activeSource === 'trend' && reportState.trendAnalysis) {
        renderTrendAnalysis(reportState.trendAnalysis);
      } else {
        renderContentReadiness(reportState.activeSnapshot.content_readiness || null);
        renderContentAnalysis(reportState.activeSnapshot.analysis || {}, reportState.activeSnapshot.content_readiness || {});
      }
      updateActiveHistorySelection();
    }

    function maybeInitializeReportSelection() {
      if (reportState.initializedSelection || !reportState.historyLoaded) {
        return;
      }

      reportState.initializedSelection = true;
      openUsageHabitReport(reportState.usagePeriod || 'day');
    }

    function openUsageHabitReport(periodKey) {
      if (periodKey) {
        reportState.usagePeriod = periodKey;
      }

      reportState.selectionMode = false;
      document.getElementById('reportSelectionToolbar').style.display = 'none';
      document.getElementById('toggleTrendSelectionBtn').textContent = 'Cumulative Analysis';
      reportState.activeSource = 'usage';
      reportState.activeSnapshot = null;
      reportState.activeRecord = null;
      reportState.trendAnalysis = null;
      document.getElementById('reportGeneratedHint').textContent = buildReportGeneratedHint(reportState.usageReport, 'Usage habit report updates automatically once per day.');
      renderHistoryList();
      renderActiveSnapshot();
    }

    async function loadChildReportHistory(requestKey = reportState.requestKey) {
      const childId = reportState.childId;
      if (!childId) {
        return;
      }

      try {
        const response = await fetch(`${CHILD_REPORT_HISTORY_API_URL}?child_id=${encodeURIComponent(childId)}`, {
          headers: {
            'Accept': 'application/json'
          }
        });

        const result = await response.json();
        if (!isActiveReportRequest(requestKey, childId)) {
          return;
        }

        if (!response.ok || !result.success) {
          throw new Error(result.error || result.message || 'Failed to load report history');
        }

        reportState.settings = result.settings;
        reportState.usageReport = result.usage_report || null;
        if (reportState.usageReport && reportState.usageReport.default_period) {
          reportState.usagePeriod = reportState.usageReport.default_period;
        }
        reportState.history = Array.isArray(result.reports) ? result.reports : [];
        reportState.historyLoaded = true;
        reportState.selectedReportIds = reportState.history.map(item => Number(item.id));
        renderReportSettings();
        renderUsageReportList();
        updateTrendSelectionMeta();
        renderHistoryList();
        maybeInitializeReportSelection();
      } catch (error) {
        if (!isActiveReportRequest(requestKey, childId)) {
          return;
        }

        console.error(error);
        showPageAlert(error.message || 'Failed to load report history.', 'error');
      }
    }

    async function openSavedReport(reportId) {
      const childId = reportState.childId;
      const requestKey = reportState.requestKey;
      if (!childId || !reportId) {
        return;
      }

      if (reportState.selectionMode) {
        toggleTrendReportSelection(reportId);
        return;
      }

      const selectionToken = beginReportSelection();

      try {
        const response = await fetch(`${CHILD_REPORT_ITEM_API_URL}?child_id=${encodeURIComponent(childId)}&report_id=${encodeURIComponent(reportId)}`, {
          headers: {
            'Accept': 'application/json'
          }
        });

        const result = await response.json();
        if (!isActiveReportSelection(requestKey, childId, selectionToken)) {
          return;
        }

        if (!response.ok || !result.success) {
          throw new Error(result.error || result.message || 'Failed to load saved report');
        }

        reportState.activeSource = 'saved';
        reportState.trendAnalysis = null;
        reportState.activeSnapshot = result.report;
        reportState.activeRecord = result.report_record;
        persistSavedReportSelection(childId, result.report_record && result.report_record.id);
        document.getElementById('reportGeneratedHint').textContent = buildReportGeneratedHint(result.report && result.report.analysis, 'Saved report opened.');
        renderActiveSnapshot();
      } catch (error) {
        if (!isActiveReportSelection(requestKey, childId, selectionToken)) {
          return;
        }

        console.error(error);
        showPageAlert(error.message || 'Failed to load saved report.', 'error');
      }
    }

    async function saveReportSettings() {
      const childId = reportState.childId;
      const requestKey = reportState.requestKey;
      if (!childId) {
        return;
      }

      const button = document.getElementById('saveReportSettingsBtn');
      button.disabled = true;
      button.textContent = 'Saving...';

      const formData = new FormData();
      formData.append('csrf_token', CSRF_TOKEN);
      formData.append('child_id', childId);
      formData.append('auto_generate_enabled', document.getElementById('autoReportEnabled').checked ? '1' : '0');
      formData.append('auto_generate_period_days', document.getElementById('autoReportFrequency').value);
      formData.append('auto_generate_frequency_days', document.getElementById('autoReportFrequency').value);

      try {
        const response = await fetch(CHILD_REPORT_SETTINGS_API_URL, {
          method: 'POST',
          body: formData
        });

        const result = await response.json();
        if (!isActiveReportRequest(requestKey, childId)) {
          return;
        }

        if (!response.ok || !result.success) {
          throw new Error(result.error || result.message || 'Failed to save report settings');
        }

        reportState.settings = result.settings;
        renderReportSettings();
        await loadChildReportHistory();
        toggleAutoReportSettings(false);
        showPageAlert(result.message || 'Report settings saved.', 'success');
      } catch (error) {
        if (!isActiveReportRequest(requestKey, childId)) {
          return;
        }

        console.error(error);
        showPageAlert(error.message || 'Failed to save report settings.', 'error');
      } finally {
        if (isActiveReportRequest(requestKey, childId)) {
          button.disabled = false;
          button.textContent = 'Save Schedule';
        }
      }
    }

    async function generateContentReport() {
      const childId = reportState.childId;
      const requestKey = reportState.requestKey;
      if (!childId) {
        return;
      }

      const button = document.getElementById('generateContentReportBtn');
      button.disabled = true;
      button.textContent = 'Generating...';

      const formData = new FormData();
      formData.append('csrf_token', CSRF_TOKEN);
      formData.append('child_id', childId);

      const selectionToken = beginReportSelection();

      try {
        const response = await fetch(CHILD_REPORT_CONTENT_API_URL, {
          method: 'POST',
          body: formData
        });

        const result = await response.json();
        if (!isActiveReportSelection(requestKey, childId, selectionToken)) {
          return;
        }

        if (response.status === 422 && result.report) {
          reportState.history = Array.isArray(result.reports) ? result.reports : reportState.history;
          reportState.settings = result.settings || reportState.settings;
          renderReportSettings();
          updateTrendSelectionMeta();
          renderHistoryList();
          if (reportState.activeSource === 'preview' || reportState.activeSource === 'empty') {
            openUsageHabitReport(reportState.usagePeriod || 'day');
          } else {
            renderActiveSnapshot();
          }
          document.getElementById('reportGeneratedHint').textContent = result.message || 'More new child chat is needed before the next saved report.';
          showPageAlert(result.message || 'More new child chat is needed before the next saved report.', 'info');
          return;
        }

        if (!response.ok || !result.success) {
          throw new Error(result.error || result.message || 'Failed to generate report');
        }

        reportState.history = Array.isArray(result.reports) ? result.reports : reportState.history;
        reportState.settings = result.settings || reportState.settings;
        reportState.selectedReportIds = reportState.history.map(item => Number(item.id));
        reportState.activeSource = 'saved';
        reportState.trendAnalysis = null;
        reportState.activeSnapshot = result.report;
        reportState.activeRecord = result.report_record;
        persistSavedReportSelection(childId, result.report_record && result.report_record.id);
        renderReportSettings();
        updateTrendSelectionMeta();
        renderHistoryList();
        renderActiveSnapshot();
        document.getElementById('reportGeneratedHint').textContent = buildReportGeneratedHint(result.report && result.report.analysis, 'A new manual report was saved.');
        showPageAlert(result.message || 'Report generated successfully.', 'success');
      } catch (error) {
        if (!isActiveReportSelection(requestKey, childId, selectionToken)) {
          return;
        }

        console.error(error);
        showPageAlert(error.message || 'Failed to generate report.', 'error');
      } finally {
        if (isActiveReportRequest(requestKey, childId)) {
          button.disabled = false;
          button.textContent = 'Generate Now';
        }
      }
    }

    async function generateTrendAnalysis() {
      const childId = reportState.childId;
      const requestKey = reportState.requestKey;
      const selectedIds = [...reportState.selectedReportIds];
      if (!childId) {
        return;
      }

      if (selectedIds.length === 0) {
        showPageAlert('Select at least one saved report.', 'error');
        return;
      }

      const button = document.getElementById('runTrendAnalysisBtn');
      button.disabled = true;
      button.textContent = 'Analyzing...';

      const formData = new FormData();
      formData.append('csrf_token', CSRF_TOKEN);
      formData.append('child_id', childId);
      selectedIds.forEach(id => formData.append('report_ids[]', String(id)));

      const selectionToken = beginReportSelection();

      try {
        const response = await fetch(CHILD_REPORT_TREND_API_URL, {
          method: 'POST',
          body: formData
        });

        const result = await response.json();
        if (!isActiveReportSelection(requestKey, childId, selectionToken)) {
          return;
        }

        if (!response.ok || !result.success) {
          throw new Error(result.error || result.message || 'Failed to analyze selected reports');
        }

        reportState.selectionMode = false;
        document.getElementById('reportSelectionToolbar').style.display = 'none';
        document.getElementById('toggleTrendSelectionBtn').textContent = 'Cumulative Analysis';
        reportState.activeSource = 'trend';
        reportState.activeSnapshot = null;
        reportState.activeRecord = null;
        reportState.trendAnalysis = result.analysis || null;
        document.getElementById('reportGeneratedHint').textContent = buildReportGeneratedHint(result.analysis, 'Cumulative analysis loaded.');
        renderHistoryList();
        renderActiveSnapshot();
      } catch (error) {
        if (!isActiveReportSelection(requestKey, childId, selectionToken)) {
          return;
        }

        console.error(error);
        showPageAlert(error.message || 'Failed to analyze selected reports.', 'error');
      } finally {
        if (isActiveReportRequest(requestKey, childId)) {
          button.disabled = false;
          button.textContent = 'Analyze Selected';
        }
      }
    }

    function renderChildren(children) {
      childrenState = Array.isArray(children) ? children : [];
      document.getElementById('childrenCount').textContent = childrenState.length;

      const container = document.getElementById('childrenList');
      if (childrenState.length === 0) {
        container.innerHTML = `
          <div class="empty-state">
            <i class="fas fa-child"></i>
            <div>No child accounts yet.</div>
            <div>Create the first child account to start managing login time and activity.</div>
          </div>
        `;
        return;
      }

      container.innerHTML = childrenState.map(child => `
        <article class="child-card">
          <div class="child-summary">
            <span class="child-pill ${getLoginStatusChipClass(child.login_disabled)}">
              <i class="fas ${child.login_disabled ? 'fa-pause-circle' : 'fa-circle-check'}"></i>
              ${child.login_disabled ? 'Login Paused' : 'Login Allowed'}
            </span>
            <div class="child-main">
              <h3>${escapeHtml(child.name)}</h3>
              <p class="child-meta">${escapeHtml(child.gender_label)} · ${child.age ?? 'N/A'} years old · Born ${escapeHtml(formatDate(child.birth_date))}</p>
            </div>
          </div>

          <div class="child-inline-stats">
            <span class="child-stat-chip">${escapeHtml(child.allowed_login_start)} - ${escapeHtml(child.allowed_login_end)}</span>
            <span class="child-stat-chip">${child.remaining_today_minutes} min left</span>
            <span class="child-stat-chip">Last login: ${escapeHtml(formatDateTime(child.last_login_at) || 'Never')}</span>
          </div>

          <div class="card-actions">
            <button type="button" class="ghost-btn" onclick="showChildReportModal(${child.id})">Report</button>
            <button type="button" class="${child.login_disabled ? 'ghost-btn' : 'warning-btn'}" onclick="toggleChildLogin(${child.id})">
              ${child.login_disabled ? 'Allow Login' : 'Block Login'}
            </button>
            <button type="button" class="ghost-btn" onclick="showManageChildModal(${child.id})">Details</button>
            <button type="button" class="danger-btn" onclick="deleteChild(${child.id})">Delete</button>
          </div>
        </article>
      `).join('');
    }

    async function loadChildren() {
      try {
        const response = await fetch(CHILDREN_API_URL, {
          headers: {
            'Accept': 'application/json'
          }
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
          throw new Error(result.error || result.message || 'Failed to load child accounts');
        }

        renderChildren(result.children);
      } catch (error) {
        console.error(error);
        showPageAlert(error.message || 'Failed to load child accounts.', 'error');
      }
    }

    async function deleteChild(childId) {
      const child = getChildById(childId);
      if (!child) {
        return;
      }

      const confirmed = window.confirm(`Delete ${child.name}'s account? This will also remove that child's conversations.`);
      if (!confirmed) {
        return;
      }

      const formData = new FormData();
      formData.append('csrf_token', CSRF_TOKEN);
      formData.append('child_id', childId);

      try {
        const response = await fetch(DELETE_CHILD_API_URL, {
          method: 'POST',
          body: formData
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
          throw new Error(result.error || result.message || 'Failed to delete child account');
        }

        renderChildren(result.children);
        closeManageChildModal();
        showPageAlert(result.message, 'success');
      } catch (error) {
        console.error(error);
        showPageAlert(error.message || 'Failed to delete child account.', 'error');
      }
    }

    async function toggleChildLogin(childId) {
      const child = getChildById(childId);
      if (!child) {
        return;
      }

      const formData = new FormData();
      formData.append('csrf_token', CSRF_TOKEN);
      formData.append('child_id', childId);

      try {
        const response = await fetch(TOGGLE_CHILD_LOGIN_API_URL, {
          method: 'POST',
          body: formData
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
          throw new Error(result.error || result.message || 'Failed to update child login access');
        }

        renderChildren(result.children);

        const updatedChild = result.children.find(item => item.id === childId);
        if (
          updatedChild &&
          document.getElementById('manageChildModal').style.display === 'block' &&
          Number(document.getElementById('manage_child_id').value) === childId
        ) {
          populateManageChildModal(updatedChild);
        }

        showPageAlert(result.message, 'success');
      } catch (error) {
        console.error(error);
        showPageAlert(error.message || 'Failed to update child login access.', 'error');
      }
    }

    function toggleChildLoginFromModal() {
      const childId = Number(document.getElementById('manage_child_id').value);
      if (childId > 0) {
        toggleChildLogin(childId);
      }
    }

    document.getElementById('profileForm').addEventListener('submit', async function (e) {
      e.preventDefault();
      clearProfileErrors();

      const formData = new FormData(this);
      formData.append('update-profile', '1');

      try {
        const response = await fetch(UPDATE_PROFILE_URL, {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.status === 'success') {
          showAlert(result.message, 'success');

          if (result.newName) {
            document.querySelector('.user-name').textContent = result.newName;
            document.querySelector('.user-avatar').textContent = result.newName.charAt(0).toUpperCase();
            document.getElementById('name').value = result.newName;
          }
        } else if (result.errors) {
          displayProfileErrors(result.errors);
          showAlert('Please fix the errors and try again.', 'error');
        } else {
          showAlert(result.message || 'An error occurred', 'error');
        }
      } catch (error) {
        console.error(error);
        showAlert('An error occurred. Please try again.', 'error');
      }
    });

    document.getElementById('passwordForm').addEventListener('submit', async function (e) {
      e.preventDefault();
      clearProfileErrors();

      const formData = new FormData(this);
      formData.append('update-password', '1');

      try {
        const response = await fetch(UPDATE_PROFILE_URL, {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.status === 'success') {
          showAlert(result.message, 'success');
          this.reset();
          this.querySelector('input[name="csrf_token"]').value = CSRF_TOKEN;
        } else if (result.errors) {
          displayProfileErrors(result.errors);
          showAlert('Please fix the errors and try again.', 'error');
        } else {
          showAlert(result.message || 'An error occurred', 'error');
        }
      } catch (error) {
        console.error(error);
        showAlert('An error occurred. Please try again.', 'error');
      }
    });

    document.getElementById('createChildForm').addEventListener('submit', async function (e) {
      e.preventDefault();
      clearChildErrors('create');

      const formData = new FormData(this);

      try {
        const response = await fetch(CHILDREN_API_URL, {
          method: 'POST',
          body: formData
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
          if (result.errors) {
            displayChildErrors(result.errors, 'create');
          }
          throw new Error(result.message || result.error || 'Failed to create child account');
        }

        renderChildren(result.children);
        closeCreateChildModal();
        this.reset();
        showPageAlert(result.message, 'success');
      } catch (error) {
        console.error(error);
        if (!document.querySelector('#createChildModal .error-text:not(:empty)')) {
          showPageAlert(error.message || 'Failed to create child account.', 'error');
        }
      }
    });

    document.getElementById('manageChildForm').addEventListener('submit', async function (e) {
      e.preventDefault();
      clearChildErrors('manage');
      syncTimeHiddenValue('allowed_login_start');
      syncTimeHiddenValue('allowed_login_end');

      const formData = new FormData(this);

      try {
        const response = await fetch(UPDATE_CHILD_API_URL, {
          method: 'POST',
          body: formData
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
          if (result.errors) {
            displayChildErrors(result.errors, 'manage');
          }
          throw new Error(result.message || result.error || 'Failed to update child account');
        }

        renderChildren(result.children);
        closeManageChildModal();
        showPageAlert(result.message, 'success');
      } catch (error) {
        console.error(error);
        if (!document.querySelector('#manageChildModal .error-text:not(:empty)')) {
          showPageAlert(error.message || 'Failed to update child account.', 'error');
        }
      }
    });

    document.querySelectorAll('input[type="date"]').forEach(input => {
      input.addEventListener('keydown', (event) => event.preventDefault());
      input.addEventListener('focus', () => {
        if (typeof input.showPicker === 'function') {
          input.showPicker();
        }
      });
      input.addEventListener('click', () => {
        if (typeof input.showPicker === 'function') {
          input.showPicker();
        }
      });
    });

    ['allowed_login_start', 'allowed_login_end'].forEach(prefix => {
      const hourSelect = document.getElementById(`${prefix}_hour`);
      const minuteSelect = document.getElementById(`${prefix}_minute`);

      if (hourSelect && minuteSelect) {
        hourSelect.addEventListener('change', () => syncTimeHiddenValue(prefix));
        minuteSelect.addEventListener('change', () => syncTimeHiddenValue(prefix));
      }
    });

    document.querySelectorAll('[data-report-switch-slot]').forEach(button => {
      button.addEventListener('click', () => {
        const switchMode = button.dataset.switchMode || 'content';
        const nextValue = button.dataset.switchValue;
        if (!nextValue) {
          return;
        }

        if (switchMode === 'usage') {
          if (reportState.usagePeriod === nextValue) {
            return;
          }

          reportState.usagePeriod = nextValue;
          setActiveReportSwitchValue(nextValue);
          if (reportState.activeSource === 'usage') {
            renderUsageHabitReport();
          }
          return;
        }

        if (reportState.metric === nextValue) {
          return;
        }

        reportState.metric = nextValue;
        setActiveReportSwitchValue(nextValue);
        if (reportState.activeSource === 'saved' || reportState.activeSource === 'preview') {
          renderReportChart();
        }
      });
    });

    document.addEventListener('click', (event) => {
      const anchor = document.querySelector('.report-settings-anchor');
      const modal = document.getElementById('childReportModal');
      if (!anchor || !modal || modal.style.display !== 'block') {
        return;
      }

      if (anchor.contains(event.target)) {
        return;
      }

      toggleAutoReportSettings(false);
    });

    document.getElementById('saveReportSettingsBtn').addEventListener('click', () => {
      saveReportSettings();
    });

    const childNameInput = document.getElementById('child_name');
    if (childNameInput) {
      childNameInput.addEventListener('input', () => {
        const value = childNameInput.value.trim();
        setFieldMessage('child_nameError', '');

        if (childNameCheckTimer) {
          clearTimeout(childNameCheckTimer);
        }

        if (childNameCheckController) {
          childNameCheckController.abort();
        }

        childNameCheckTimer = setTimeout(async () => {
          if (value === '') {
            setFieldMessage('child_nameError', 'Child username is required.', 'error');
            return;
          }

          childNameCheckController = new AbortController();

          try {
            const response = await fetch(`${VALIDATION_API_URL}?field=child_name&value=${encodeURIComponent(value)}`, {
              signal: childNameCheckController.signal,
              headers: {
                'Accept': 'application/json'
              }
            });

            const result = await response.json();
            if (!result.success) {
              setFieldMessage('child_nameError', result.message || 'Unable to verify child username.', 'error');
              return;
            }

            setFieldMessage('child_nameError', result.message, result.available ? 'success' : 'error');
          } catch (error) {
            if (error.name !== 'AbortError') {
              setFieldMessage('child_nameError', 'Unable to verify child username right now.', 'error');
            }
          }
        }, 450);
      });
    }

    window.onclick = function (event) {
      if (event.target === document.getElementById('profileModal')) {
        closeProfileModal();
      }

      if (event.target === document.getElementById('createChildModal')) {
        closeCreateChildModal();
      }

      if (event.target === document.getElementById('manageChildModal')) {
        closeManageChildModal();
      }

      if (event.target === document.getElementById('childReportModal')) {
        closeChildReportModal();
      }

      if (!event.target.matches('.user-info') && !event.target.closest('.user-info')) {
        const dropdown = document.getElementById('userDropdown');
        if (dropdown.classList.contains('show')) {
          dropdown.classList.remove('show');
        }
      }
    };

    initializeTimeSelectors();
    setTimeSelectorValue('allowed_login_start', '00:00', '00:00');
    setTimeSelectorValue('allowed_login_end', '23:50', '23:50');
    loadChildren();
  </script>
</body>

</html>

<?php
unset($_SESSION['errors']);
unset($_SESSION['old']);
unset($_SESSION['success']);
?>
