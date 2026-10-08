<?php
/**
 * Design Vision — Image-to-Code Admin View
 * Premium multi-step UI for uploading a design screenshot and generating pixel-perfect HTML/CSS.
 *
 * @package Ugentic_Builder
 * @since   2.1.0
 */

if (!defined('ABSPATH')) exit;
?>

<div class="wrap ugentic-wrap ugentic-design-vision-wrap">

    <!-- ═══════════════════════  HEADER  ═══════════════════════ -->
    <div class="dv-page-header">
        <div class="dv-header-content">
            <div class="dv-header-left">
                <h1 class="dv-title">
                    <span class="dv-icon">✨</span> Design Vision
                </h1>
                <p class="dv-subtitle">Upload a design screenshot — get pixel-perfect HTML/CSS instantly</p>
            </div>
            <div class="dv-header-right">
                <div class="dv-step-pills">
                    <span class="dv-pill active" data-step="1"><span class="dv-pill-num">1</span> Upload</span>
                    <span class="dv-pill" data-step="2"><span class="dv-pill-num">2</span> Analyze</span>
                    <span class="dv-pill" data-step="3"><span class="dv-pill-num">3</span> Generate</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════  STEP 1: UPLOAD  ═══════════════════ -->
    <div class="dv-step dv-step-upload active" id="dv-step-1">
        <div class="dv-upload-container">

            <!-- Drop Zone -->
            <div class="dv-dropzone" id="dv-dropzone">
                <div class="dv-dropzone-content" id="dv-dropzone-content">
                    <div class="dv-dropzone-icon">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                            <circle cx="8.5" cy="8.5" r="1.5"/>
                            <polyline points="21 15 16 10 5 21"/>
                        </svg>
                    </div>
                    <h3>Drop your design here</h3>
                    <p>Upload a screenshot of any web design — we'll recreate it pixel-perfectly</p>
                    <div class="dv-dropzone-actions">
                        <label for="dv-file-input" class="dv-btn dv-btn-primary">
                            <span class="dashicons dashicons-upload"></span> Choose File
                        </label>
                        <span class="dv-or">or</span>
                        <span class="dv-paste-hint">
                            <kbd>Ctrl</kbd>+<kbd>V</kbd> to paste from clipboard
                        </span>
                    </div>
                    <input type="file" id="dv-file-input" accept="image/png,image/jpeg,image/webp,image/gif" style="display:none;">
                    <p class="dv-file-types">PNG, JPG, WebP — Max 10MB</p>
                </div>

                <!-- Preview (hidden until image uploaded) -->
                <div class="dv-dropzone-preview" id="dv-dropzone-preview" style="display:none;">
                    <img id="dv-preview-image" src="" alt="Design preview">
                    <div class="dv-preview-overlay">
                        <button type="button" class="dv-btn dv-btn-secondary dv-btn-sm" id="dv-change-image">
                            <span class="dashicons dashicons-image-rotate"></span> Change
                        </button>
                    </div>
                </div>
            </div>

            <!-- Context (optional) -->
            <div class="dv-context-panel" id="dv-context-panel" style="display:none;">
                <div class="dv-context-header">
                    <span class="dashicons dashicons-info-outline"></span>
                    <span>Optional: Add context about this design</span>
                </div>
                <textarea id="dv-user-context" rows="2"
                    placeholder="e.g., This is a SaaS pricing page for a CRM tool, or: This is the hero section of a fitness website"></textarea>

                <button type="button" class="dv-btn dv-btn-primary dv-btn-lg" id="dv-analyze-btn" disabled>
                    <span class="dv-btn-text">
                        <span class="dashicons dashicons-visibility"></span> Analyze Design
                    </span>
                    <span class="dv-btn-loading" style="display:none;">
                        <span class="dv-spinner"></span> Analyzing — extracting every visual detail...
                    </span>
                </button>
            </div>

            <!-- Progress Panel (shown during analysis/generation) -->
            <div class="dv-progress-panel" id="dv-progress-panel" style="display:none;">
                <div class="dv-progress-header-bar">
                    <span class="dv-progress-icon">🔍</span>
                    <span id="dv-progress-status">Starting analysis...</span>
                    <span class="dv-progress-pct" id="dv-progress-pct">0%</span>
                </div>
                <div class="dv-progress-bar-track">
                    <div class="dv-progress-bar-fill" id="dv-progress-fill" style="width:0%"></div>
                </div>
                <div class="dv-progress-steps" id="dv-progress-steps">
                    <!-- Dynamically populated: ✓ Navbar | ✓ Hero | ⏳ Features | ○ CTA -->
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════  STEP 2: ANALYSIS + QUESTIONS  ═══════════════ -->
    <div class="dv-step dv-step-analyze" id="dv-step-2" style="display:none;">
        <div class="dv-analyze-layout">

            <!-- Left: Analysis Results -->
            <div class="dv-analysis-panel">
                <div class="dv-panel-header">
                    <h2><span class="dashicons dashicons-search"></span> Design Analysis</h2>
                    <span class="dv-badge dv-badge-success" id="dv-analysis-badge" style="display:none;">Complete</span>
                </div>

                <!-- Color Palette -->
                <div class="dv-analysis-card" id="dv-colors-card">
                    <h3><span class="dashicons dashicons-art"></span> Color Palette</h3>
                    <div class="dv-color-swatches" id="dv-color-swatches">
                        <!-- Dynamically populated -->
                    </div>
                </div>

                <!-- Typography -->
                <div class="dv-analysis-card" id="dv-typography-card">
                    <h3><span class="dashicons dashicons-editor-textcolor"></span> Typography</h3>
                    <div class="dv-typography-info" id="dv-typography-info">
                        <!-- Dynamically populated -->
                    </div>
                </div>

                <!-- Layout Structure -->
                <div class="dv-analysis-card" id="dv-layout-card">
                    <h3><span class="dashicons dashicons-layout"></span> Layout Structure</h3>
                    <div class="dv-layout-info" id="dv-layout-info">
                        <!-- Dynamically populated -->
                    </div>
                </div>

                <!-- Components Detected -->
                <div class="dv-analysis-card" id="dv-components-card">
                    <h3><span class="dashicons dashicons-screenoptions"></span> Components Detected</h3>
                    <div class="dv-components-list" id="dv-components-list">
                        <!-- Dynamically populated -->
                    </div>
                </div>
            </div>

            <!-- Right: Smart Questions -->
            <div class="dv-questions-panel">
                <div class="dv-panel-header">
                    <h2><span class="dashicons dashicons-format-chat"></span> Design Preferences</h2>
                    <p class="dv-panel-desc">Fine-tune how your design should behave</p>
                </div>

                <div class="dv-questions-list" id="dv-questions-list">
                    <!-- Dynamically populated with smart questions -->
                </div>

                <div class="dv-questions-actions">
                    <button type="button" class="dv-btn dv-btn-secondary" id="dv-back-to-upload">
                        <span class="dashicons dashicons-arrow-left-alt"></span> Back
                    </button>
                    <button type="button" class="dv-btn dv-btn-primary dv-btn-lg" id="dv-generate-btn">
                        <span class="dv-btn-text">
                            <span class="dashicons dashicons-editor-code"></span> Generate Code
                        </span>
                        <span class="dv-btn-loading" style="display:none;">
                            <span class="dv-spinner"></span> Generating pixel-perfect code...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════  STEP 3: GENERATION + COMPARISON  ═══════════════ -->
    <div class="dv-step dv-step-generate" id="dv-step-3" style="display:none;">

        <!-- Toolbar -->
        <div class="dv-generate-toolbar">
            <div class="dv-toolbar-left">
                <button type="button" class="dv-btn dv-btn-ghost" id="dv-back-to-analyze">
                    <span class="dashicons dashicons-arrow-left-alt"></span> Back to Analysis
                </button>
            </div>
            <div class="dv-toolbar-center">
                <div class="dv-view-toggle" id="dv-view-toggle">
                    <button type="button" class="dv-toggle-btn active" data-view="split">
                        <span class="dashicons dashicons-columns"></span> Split View
                    </button>
                    <button type="button" class="dv-toggle-btn" data-view="overlay">
                        <span class="dashicons dashicons-admin-appearance"></span> Overlay
                    </button>
                    <button type="button" class="dv-toggle-btn" data-view="preview">
                        <span class="dashicons dashicons-visibility"></span> Preview Only
                    </button>
                </div>
            </div>
            <div class="dv-toolbar-right">
                <button type="button" class="dv-btn dv-btn-success" id="dv-save-page-btn">
                    <span class="dashicons dashicons-saved"></span> Save as Page
                </button>
            </div>
        </div>

        <!-- Comparison Area -->
        <div class="dv-comparison-area" id="dv-comparison-area">
            <!-- Split View -->
            <div class="dv-split-view" id="dv-split-view">
                <div class="dv-split-pane dv-split-left">
                    <div class="dv-pane-label">Original Design</div>
                    <div class="dv-pane-content">
                        <img id="dv-compare-original" src="" alt="Original design">
                    </div>
                </div>
                <div class="dv-split-divider" id="dv-split-divider"></div>
                <div class="dv-split-pane dv-split-right">
                    <div class="dv-pane-label">Generated Code</div>
                    <div class="dv-pane-content">
                        <iframe id="dv-preview-frame" srcdoc=""></iframe>
                    </div>
                </div>
            </div>

            <!-- Overlay View (hidden) -->
            <div class="dv-overlay-view" id="dv-overlay-view" style="display:none;">
                <div class="dv-overlay-stack">
                    <img id="dv-overlay-original" src="" alt="Original design" class="dv-overlay-img">
                    <iframe id="dv-overlay-frame" srcdoc="" class="dv-overlay-frame"></iframe>
                </div>
                <div class="dv-overlay-controls">
                    <label>Image Opacity</label>
                    <input type="range" id="dv-overlay-opacity" min="0" max="100" value="50">
                    <span id="dv-opacity-value">50%</span>
                </div>
            </div>

            <!-- Preview Only View (hidden) -->
            <div class="dv-preview-only" id="dv-preview-only" style="display:none;">
                <iframe id="dv-fullpreview-frame" srcdoc=""></iframe>
            </div>
        </div>

        <!-- Refinement Panel -->
        <div class="dv-refine-panel">
            <div class="dv-refine-header">
                <h3><span class="dashicons dashicons-admin-customizer"></span> Refine Design</h3>
                <span class="dv-refine-hint">Describe what to change — AI will adjust the code while preserving the overall design</span>
            </div>
            <div class="dv-refine-input-group">
                <textarea id="dv-refine-feedback" rows="2"
                    placeholder="e.g., Make the hero section taller, change the button color to blue, add more padding between cards..."></textarea>
                <button type="button" class="dv-btn dv-btn-primary" id="dv-refine-btn">
                    <span class="dv-btn-text">
                        <span class="dashicons dashicons-update"></span> Refine
                    </span>
                    <span class="dv-btn-loading" style="display:none;">
                        <span class="dv-spinner"></span> Refining...
                    </span>
                </button>
            </div>
        </div>

        <!-- Save Page Modal -->
        <div class="dv-modal-backdrop" id="dv-save-modal" style="display:none;">
            <div class="dv-modal">
                <div class="dv-modal-header">
                    <h3>Save as WordPress Page</h3>
                    <button type="button" class="dv-modal-close" id="dv-modal-close">&times;</button>
                </div>
                <div class="dv-modal-body">
                    <div class="dv-form-group">
                        <label for="dv-page-title">Page Title</label>
                        <input type="text" id="dv-page-title" placeholder="My Design Vision Page" value="">
                    </div>
                    <div class="dv-form-group dv-form-row">
                        <label class="dv-toggle-label">
                            <input type="checkbox" id="dv-publish-toggle">
                            <span>Publish immediately</span>
                        </label>
                    </div>
                </div>
                <div class="dv-modal-footer">
                    <button type="button" class="dv-btn dv-btn-secondary" id="dv-modal-cancel">Cancel</button>
                    <button type="button" class="dv-btn dv-btn-success" id="dv-modal-save">
                        <span class="dv-btn-text"><span class="dashicons dashicons-saved"></span> Save Page</span>
                        <span class="dv-btn-loading" style="display:none;"><span class="dv-spinner"></span> Saving...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ═══════════════════════  INLINE STYLES  ═══════════════════════ -->
<style>
/* ─── Page Header ──────────────────────────────────────────── */
.dv-page-header {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
    border-radius: 16px;
    padding: 32px 40px;
    margin-bottom: 32px;
    position: relative;
    overflow: hidden;
}
.dv-page-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(249, 115, 22, 0.15) 0%, transparent 70%);
    border-radius: 50%;
}
.dv-page-header::after {
    content: '';
    position: absolute;
    bottom: -30%;
    left: 20%;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(118, 75, 226, 0.1) 0%, transparent 70%);
    border-radius: 50%;
}
.dv-header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    z-index: 2;
}
.dv-title {
    color: #fff;
    font-size: 28px;
    font-weight: 700;
    margin: 0 0 6px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.dv-icon {
    font-size: 32px;
}
.dv-subtitle {
    color: rgba(255,255,255,0.65);
    font-size: 15px;
    margin: 0;
}

/* ─── Step Pills ───────────────────────────────────────────── */
.dv-step-pills {
    display: flex;
    gap: 6px;
}
.dv-pill {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 18px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
    color: rgba(255,255,255,0.4);
    background: rgba(255,255,255,0.05);
    backdrop-filter: blur(4px);
    transition: all 0.3s ease;
    cursor: default;
}
.dv-pill.active {
    color: #fff;
    background: rgba(249, 115, 22, 0.3);
    box-shadow: 0 0 15px rgba(249, 115, 22, 0.2);
}
.dv-pill.completed {
    color: rgba(255,255,255,0.7);
    background: rgba(72, 187, 120, 0.2);
}
.dv-pill-num {
    width: 22px;
    height: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,0.15);
    border-radius: 50%;
    font-size: 12px;
    font-weight: 700;
}
.dv-pill.active .dv-pill-num {
    background: rgba(249, 115, 22, 0.5);
}
.dv-pill.completed .dv-pill-num {
    background: rgba(72, 187, 120, 0.4);
}

/* ─── Steps Container ──────────────────────────────────────── */
.dv-step { animation: dvFadeIn 0.4s ease; }
@keyframes dvFadeIn {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ─── Step 1: Upload / Dropzone ────────────────────────────── */
.dv-upload-container {
    max-width: 760px;
    margin: 0 auto;
}
.dv-dropzone {
    border: 2px dashed rgba(249, 115, 22, 0.3);
    border-radius: 20px;
    background: linear-gradient(145deg, rgba(249, 115, 22, 0.02) 0%, rgba(118, 75, 226, 0.03) 100%);
    padding: 60px 40px;
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
    min-height: 300px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.dv-dropzone:hover,
.dv-dropzone.drag-over {
    border-color: rgba(249, 115, 22, 0.6);
    background: linear-gradient(145deg, rgba(249, 115, 22, 0.06) 0%, rgba(118, 75, 226, 0.07) 100%);
    box-shadow: 0 0 40px rgba(249, 115, 22, 0.08);
}
.dv-dropzone-icon {
    color: rgba(249, 115, 22, 0.4);
    margin-bottom: 16px;
}
.dv-dropzone-content h3 {
    color: #1a1a2e;
    font-size: 20px;
    font-weight: 600;
    margin: 0 0 8px;
}
.dv-dropzone-content p {
    color: #666;
    font-size: 14px;
    margin: 0 0 24px;
}
.dv-dropzone-actions {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
    flex-wrap: wrap;
}
.dv-or {
    color: #999;
    font-size: 13px;
}
.dv-paste-hint {
    color: #888;
    font-size: 13px;
}
.dv-paste-hint kbd {
    background: #f0f0f0;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 2px 6px;
    font-size: 12px;
    font-family: monospace;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}
.dv-file-types {
    color: #aaa;
    font-size: 12px;
    margin-top: 16px !important;
}

/* Preview */
.dv-dropzone-preview {
    position: relative;
    width: 100%;
}
.dv-dropzone-preview img {
    width: 100%;
    max-height: 500px;
    object-fit: contain;
    border-radius: 12px;
}
.dv-preview-overlay {
    position: absolute;
    top: 12px;
    right: 12px;
    opacity: 0;
    transition: opacity 0.2s;
}
.dv-dropzone-preview:hover .dv-preview-overlay {
    opacity: 1;
}

/* Context panel */
.dv-context-panel {
    margin-top: 24px;
}
.dv-context-header {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #666;
    font-size: 13px;
    margin-bottom: 10px;
}
.dv-context-panel textarea {
    width: 100%;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    padding: 14px 18px;
    font-size: 14px;
    resize: none;
    transition: border-color 0.2s;
    background: #fafafa;
    margin-bottom: 20px;
}
.dv-context-panel textarea:focus {
    border-color: #f97316;
    outline: none;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
}

/* ─── Buttons ──────────────────────────────────────────────── */
.dv-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 22px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.25s ease;
    text-decoration: none;
    line-height: 1.4;
}
.dv-btn .dashicons { font-size: 18px; width: 18px; height: 18px; }
.dv-btn-primary {
    background: linear-gradient(135deg, #f97316, #764be2);
    color: #fff;
    box-shadow: 0 4px 15px rgba(249, 115, 22, 0.25);
}
.dv-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 25px rgba(249, 115, 22, 0.35);
}
.dv-btn-primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}
.dv-btn-secondary {
    background: #f5f5f5;
    color: #444;
    border: 1px solid #e0e0e0;
}
.dv-btn-secondary:hover { background: #eee; }
.dv-btn-success {
    background: linear-gradient(135deg, #48bb78, #38a169);
    color: #fff;
    box-shadow: 0 4px 15px rgba(72, 187, 120, 0.25);
}
.dv-btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 25px rgba(72, 187, 120, 0.35);
}
.dv-btn-ghost {
    background: transparent;
    color: #f97316;
    padding: 8px 16px;
}
.dv-btn-ghost:hover { background: rgba(249, 115, 22, 0.06); }
.dv-btn-sm { padding: 6px 14px; font-size: 12px; }
.dv-btn-lg {
    padding: 14px 32px;
    font-size: 16px;
    border-radius: 12px;
    width: 100%;
    justify-content: center;
}

/* ─── Spinner ──────────────────────────────────────────────── */
.dv-spinner {
    display: inline-block;
    width: 18px;
    height: 18px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: dvSpin 0.7s linear infinite;
}
@keyframes dvSpin {
    to { transform: rotate(360deg); }
}

/* ─── Progress Bar Panel ───────────────────────────────────── */
.dv-progress-panel {
    margin-top: 24px;
    background: linear-gradient(135deg, rgba(249, 115, 22, 0.04) 0%, rgba(118, 75, 226, 0.06) 100%);
    border: 1px solid rgba(249, 115, 22, 0.15);
    border-radius: 16px;
    padding: 24px 28px;
    animation: dvFadeIn 0.4s ease;
}
.dv-progress-header-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
    font-size: 14px;
    font-weight: 600;
    color: #1a1a2e;
}
.dv-progress-icon { font-size: 20px; }
.dv-progress-pct {
    margin-left: auto;
    font-size: 14px;
    font-weight: 700;
    color: #f97316;
    font-family: 'SF Mono', 'Fira Code', monospace;
}
.dv-progress-bar-track {
    width: 100%;
    height: 8px;
    background: rgba(249, 115, 22, 0.1);
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 18px;
}
.dv-progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #f97316, #764be2, #f97316);
    background-size: 200% 100%;
    border-radius: 10px;
    transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    animation: dvProgressShimmer 2s linear infinite;
}
@keyframes dvProgressShimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
.dv-progress-steps {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.dv-progress-step {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    transition: all 0.3s ease;
}
.dv-step-pending {
    background: rgba(0,0,0,0.04);
    color: #aaa;
}
.dv-step-active {
    background: rgba(249, 115, 22, 0.12);
    color: #f97316;
    font-weight: 600;
    animation: dvPulse 1.5s ease infinite;
}
@keyframes dvPulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}
.dv-step-done {
    background: rgba(72, 187, 120, 0.1);
    color: #38a169;
}
.dv-step-error {
    background: rgba(245, 101, 101, 0.1);
    color: #e53e3e;
}

/* ─── Step 2: Analysis Layout ──────────────────────────────── */
.dv-analyze-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 28px;
}
@media (max-width: 1024px) {
    .dv-analyze-layout { grid-template-columns: 1fr; }
}

.dv-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}
.dv-panel-header h2 {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a2e;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.dv-panel-desc {
    color: #888;
    font-size: 13px;
    margin: 4px 0 0;
}

/* Analysis Cards */
.dv-analysis-card {
    background: #fff;
    border: 1px solid #eee;
    border-radius: 14px;
    padding: 20px;
    margin-bottom: 16px;
    transition: box-shadow 0.3s;
}
.dv-analysis-card:hover {
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
}
.dv-analysis-card h3 {
    font-size: 14px;
    font-weight: 600;
    color: #333;
    margin: 0 0 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Color Swatches */
.dv-color-swatches {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
.dv-color-swatch {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
}
.dv-swatch-circle {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    border: 2px solid rgba(0,0,0,0.08);
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    transition: transform 0.2s;
}
.dv-swatch-circle:hover { transform: scale(1.15); }
.dv-swatch-label {
    font-size: 10px;
    color: #999;
    text-align: center;
    max-width: 60px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.dv-swatch-hex {
    font-size: 10px;
    color: #666;
    font-family: 'SF Mono', 'Fira Code', monospace;
}

/* Typography Info */
.dv-type-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #f5f5f5;
}
.dv-type-row:last-child { border: none; }
.dv-type-label { color: #888; font-size: 13px; }
.dv-type-value { color: #333; font-size: 13px; font-weight: 600; }

/* Layout Info */
.dv-section-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 5px 12px;
    border-radius: 8px;
    background: rgba(249, 115, 22, 0.08);
    color: #f97316;
    font-size: 12px;
    font-weight: 500;
    margin: 3px;
}

/* Components */
.dv-component-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #f8f8f8;
}
.dv-component-name { font-size: 13px; color: #333; font-weight: 500; text-transform: capitalize; }
.dv-component-count {
    font-size: 12px;
    color: #f97316;
    background: rgba(249, 115, 22, 0.08);
    padding: 2px 8px;
    border-radius: 10px;
}

/* ─── Questions ────────────────────────────────────────────── */
.dv-question-group {
    background: #fff;
    border: 1px solid #eee;
    border-radius: 14px;
    padding: 18px 20px;
    margin-bottom: 14px;
    transition: border-color 0.2s;
}
.dv-question-group:hover { border-color: #ddd; }
.dv-question-category {
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #f97316;
    margin-bottom: 6px;
}
.dv-question-text {
    font-size: 14px;
    color: #333;
    font-weight: 500;
    margin-bottom: 12px;
    line-height: 1.4;
}
.dv-question-select {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    font-size: 13px;
    background: #fafafa;
    color: #333;
    transition: border-color 0.2s;
    cursor: pointer;
    -webkit-appearance: none;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 14px center;
    padding-right: 36px;
}
.dv-question-select:focus {
    border-color: #f97316;
    outline: none;
    box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
}
.dv-questions-actions {
    display: flex;
    gap: 12px;
    margin-top: 24px;
}
.dv-questions-actions .dv-btn-lg { flex: 1; }
.dv-questions-actions .dv-btn-secondary { flex: 0 0 auto; }

/* ─── Badges ───────────────────────────────────────────────── */
.dv-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.dv-badge-success {
    background: rgba(72, 187, 120, 0.1);
    color: #38a169;
}

/* ─── Step 3: Generation ───────────────────────────────────── */
.dv-generate-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #fff;
    border: 1px solid #eee;
    border-radius: 14px;
    padding: 12px 20px;
    margin-bottom: 20px;
    gap: 16px;
}

/* View Toggle */
.dv-view-toggle {
    display: flex;
    background: #f5f5f5;
    border-radius: 10px;
    padding: 3px;
}
.dv-toggle-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 7px 16px;
    border: none;
    background: transparent;
    border-radius: 8px;
    font-size: 13px;
    color: #666;
    cursor: pointer;
    transition: all 0.2s;
    font-weight: 500;
}
.dv-toggle-btn.active {
    background: #fff;
    color: #333;
    box-shadow: 0 1px 4px rgba(0,0,0,0.08);
}
.dv-toggle-btn .dashicons { font-size: 16px; width: 16px; height: 16px; }

/* Comparison Area */
.dv-comparison-area {
    border-radius: 14px;
    overflow: hidden;
    border: 1px solid #eee;
    background: #f8f8f8;
    margin-bottom: 20px;
}

/* Split View */
.dv-split-view {
    display: flex;
    height: 600px;
}
.dv-split-pane {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.dv-pane-label {
    padding: 10px 16px;
    background: rgba(0,0,0,0.03);
    border-bottom: 1px solid #eee;
    font-size: 12px;
    font-weight: 600;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.dv-pane-content {
    flex: 1;
    overflow: auto;
    background: #fff;
}
.dv-pane-content img {
    width: 100%;
    display: block;
}
.dv-pane-content iframe {
    width: 100%;
    height: 100%;
    border: none;
}
.dv-split-divider {
    width: 2px;
    background: #e0e0e0;
    cursor: col-resize;
    position: relative;
}
.dv-split-divider::after {
    content: '⋮';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: #e0e0e0;
    padding: 8px 4px;
    border-radius: 4px;
    font-size: 14px;
    color: #999;
}

/* Overlay View */
.dv-overlay-stack {
    position: relative;
    height: 550px;
}
.dv-overlay-img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: contain;
    z-index: 2;
    pointer-events: none;
    opacity: 0.5;
}
.dv-overlay-frame {
    width: 100%;
    height: 100%;
    border: none;
    z-index: 1;
}
.dv-overlay-controls {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 20px;
    background: rgba(0,0,0,0.03);
    border-top: 1px solid #eee;
}
.dv-overlay-controls label {
    font-size: 13px;
    font-weight: 600;
    color: #666;
}
.dv-overlay-controls input[type="range"] {
    flex: 1;
    max-width: 200px;
    accent-color: #f97316;
}

/* Preview Only */
.dv-preview-only iframe {
    width: 100%;
    height: 600px;
    border: none;
    background: #fff;
}

/* ─── Refinement Panel ─────────────────────────────────────── */
.dv-refine-panel {
    background: #fff;
    border: 1px solid #eee;
    border-radius: 14px;
    padding: 20px;
}
.dv-refine-header {
    margin-bottom: 12px;
}
.dv-refine-header h3 {
    font-size: 15px;
    font-weight: 600;
    margin: 0 0 4px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.dv-refine-hint {
    font-size: 13px;
    color: #888;
}
.dv-refine-input-group {
    display: flex;
    gap: 12px;
}
.dv-refine-input-group textarea {
    flex: 1;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 14px;
    resize: none;
    transition: border-color 0.2s;
}
.dv-refine-input-group textarea:focus {
    border-color: #f97316;
    outline: none;
}
.dv-refine-input-group .dv-btn {
    flex-shrink: 0;
    align-self: flex-end;
}

/* ─── Modal ────────────────────────────────────────────────── */
.dv-modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.4);
    backdrop-filter: blur(4px);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: dvFadeIn 0.2s ease;
}
.dv-modal {
    background: #fff;
    border-radius: 16px;
    width: 480px;
    max-width: 90%;
    box-shadow: 0 20px 60px rgba(0,0,0,0.15);
}
.dv-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid #eee;
}
.dv-modal-header h3 { margin: 0; font-size: 17px; }
.dv-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #999;
    padding: 0 4px;
    line-height: 1;
}
.dv-modal-body { padding: 24px; }
.dv-form-group { margin-bottom: 16px; }
.dv-form-group label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #555;
    margin-bottom: 6px;
}
.dv-form-group input[type="text"] {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    font-size: 14px;
}
.dv-form-group input[type="text"]:focus {
    border-color: #f97316;
    outline: none;
    box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
}
.dv-form-row { display: flex; align-items: center; }
.dv-toggle-label {
    display: flex !important;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 14px !important;
    font-weight: 400 !important;
    color: #555 !important;
}
.dv-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 16px 24px;
    border-top: 1px solid #eee;
}
</style>
