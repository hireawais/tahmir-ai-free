<?php
/**
 * Advanced Website Wizard – View Template
 * Single-page application rendered inside WP admin.
 *
 * @package Ugentic_Builder
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="ugw-wrap" id="ugw-wizard">

    <!-- ====== START SCREEN ====== -->
    <div class="ugw-start" id="ugw-start-screen">
        <div class="ugw-start-inner">
            <div style="text-align:center; margin-bottom:12px; font-size:36px;">🚀</div>
            <h1 class="ugw-start-title">Website Wizard</h1>
            <p class="ugw-start-subtitle">Start quickly, then guide logo, content, design concepts, high fidelity, and final motion in AI Design Studio.</p>
            <div class="ugw-start-flow">
                <div><span>1</span><strong>Tell us the business</strong></div>
                <div><span>2</span><strong>Upload logo</strong></div>
                <div><span>3</span><strong>Select concept</strong></div>
                <div><span>4</span><strong>Approve final motion</strong></div>
            </div>

            <div class="ugw-start-divider" style="margin: 16px 0;"><span>start creating</span></div>
            <button class="ugw-btn ugw-btn--accent ugw-btn--lg ugw-btn--full" id="ugw-quick-gen-btn" style="background: linear-gradient(135deg, #f97316 0%, #fb923c 100%); border: none;">
                Start AI Design Studio
            </button>
            <button class="ugw-btn ugw-btn--ghost ugw-btn--lg ugw-btn--full" id="ugw-landing-gen-btn" style="margin-top:8px;">
                Generate Single Landing Page
            </button>

            <div id="ugw-quick-gen-form" style="display:none; margin-top: 16px;">
                <div class="ugw-start-card ugw-start-card--studio" style="background: rgba(249,115,22,0.08); border: 1px solid rgba(249,115,22,0.2); border-radius: 12px; padding: 20px;">
                    <h3 style="margin: 0 0 4px; font-size: 16px; font-weight: 700; color: var(--ugw-text-primary, #1a1a2e);">AI Design Studio Setup</h3>
                    <p style="margin: 0 0 16px; font-size: 13px; color: var(--ugw-text-secondary, #64748b);">Only the business name and industry are required. Add niche, audience, offers, and goals when you want more distinctive industry-specific output.</p>

                    <div style="display: grid; gap: 12px;">
                        <input type="text" class="ugw-input" id="ugw-qg-name" placeholder="Business Name (e.g. Acme Corp)" style="width: 100%;">
                        <select class="ugw-input" id="ugw-qg-industry-key" style="width: 100%;">
                            <option value="">Select an industry...</option>
                        </select>
                        <input type="text" class="ugw-input" id="ugw-qg-industry" placeholder="Custom industry (optional)" style="width: 100%; display:none;">
                        <input type="text" class="ugw-input" id="ugw-qg-industry-niche" placeholder="Niche / specialty (e.g. family dental implants, luxury rentals, B2B SaaS analytics)" style="width: 100%;">
                        <textarea class="ugw-input" id="ugw-qg-description" placeholder="Brief description (optional — tell us what makes your business special)" style="width: 100%; min-height: 60px; resize: vertical;"></textarea>
                        <textarea class="ugw-input" id="ugw-qg-target-audience" placeholder="Target audience (optional)" style="width: 100%; min-height: 48px; resize: vertical;"></textarea>
                        <textarea class="ugw-input" id="ugw-qg-services" placeholder="Top services or offers, comma-separated (optional)" style="width: 100%; min-height: 48px; resize: vertical;"></textarea>
                        <textarea class="ugw-input" id="ugw-qg-goals" placeholder="Primary website goals, comma-separated (optional)" style="width: 100%; min-height: 48px; resize: vertical;"></textarea>
                        <select class="ugw-input" id="ugw-qg-tone" style="width: 100%;">
                            <option value="professional">Professional</option>
                            <option value="friendly">Friendly</option>
                            <option value="authoritative">Authoritative</option>
                            <option value="luxury">Luxury</option>
                            <option value="bold">Bold</option>
                            <option value="playful">Playful</option>
                        </select>
                    </div>
                    <label class="ugw-qg-landing-toggle" style="display:flex;align-items:flex-start;gap:10px;margin-top:14px;padding:12px;border:1px solid rgba(251,146,60,0.22);border-radius:8px;background:#fff;">
                        <input type="checkbox" id="ugw-qg-single-landing" style="margin-top:3px;">
                        <span>
                            <strong style="display:block;font-size:13px;color:var(--ugw-text-primary);">Generate a single landing page only</strong>
                            <small style="display:block;font-size:12px;color:var(--ugw-text-secondary);line-height:1.45;">Creates one unique conversion-focused page instead of a full site.</small>
                        </span>
                    </label>

                    <!-- Optional pages section -->
                    <div id="ugw-qg-custom-pages-wrap" style="margin-top: 16px;">
                        <button type="button" class="ugw-btn ugw-btn--ghost ugw-btn--sm" id="ugw-qg-toggle-pages" style="font-size: 12px;">
                            ➕ Add custom pages (optional)
                        </button>
                        <div id="ugw-qg-pages-section" style="display:none; margin-top: 12px;">
                            <p style="font-size: 12px; color: var(--ugw-text-secondary, #64748b); margin: 0 0 8px;">Add pages you want. Leave empty for AI-suggested pages.</p>
                            <div id="ugw-qg-pages-list" style="display: grid; gap: 8px;"></div>
                            <button type="button" class="ugw-btn ugw-btn--ghost ugw-btn--sm" id="ugw-qg-add-page" style="margin-top: 8px; font-size: 12px;">+ Add Page</button>
                        </div>
                    </div>

                    <div class="ugw-btn-group" style="gap: 8px; margin-top: 16px;">
                        <button class="ugw-btn ugw-btn--lg" id="ugw-qg-submit" style="flex:1; background: linear-gradient(135deg, #f97316 0%, #fb923c 100%); color: #fff; border: none;">Open Design Studio</button>
                        <button class="ugw-btn ugw-btn--ghost ugw-btn--lg" id="ugw-qg-cancel">Cancel</button>
                    </div>
                </div>
            </div>

            <!-- TERTIARY: Standard wizard -->
            <div class="ugw-start-divider" style="margin: 16px 0;"><span>or build step by step</span></div>
            <button class="ugw-btn ugw-btn--secondary ugw-btn--lg ugw-btn--full" id="ugw-new-project-btn">
                Start Guided Wizard
            </button>

            <div id="ugw-new-project-form" style="display:none; margin-top: 16px;">
                <input type="text" class="ugw-input" id="ugw-new-project-name" placeholder="Enter your project name..." style="margin-bottom: 12px;" autofocus>
                <div class="ugw-btn-group" style="gap: 8px;">
                    <button class="ugw-btn ugw-btn--primary ugw-btn--lg" id="ugw-new-project-submit" style="flex:1;">🚀 Create Project</button>
                    <button class="ugw-btn ugw-btn--ghost ugw-btn--lg" id="ugw-new-project-cancel">Cancel</button>
                </div>
            </div>

            <div id="ugw-existing-projects" style="display:none;">
                <div class="ugw-start-divider"><span>or continue a project</span></div>
                <div id="ugw-projects-list"></div>
            </div>
        </div>
    </div>

    <!-- ====== AI DESIGN STUDIO CANVAS ====== -->
    <div class="ugw-canvas" id="ugw-canvas-view" style="display:none;">
        <div class="ugw-canvas-toolbar" id="ugw-canvas-toolbar">
            <div class="ugw-canvas-toolbar-left">
                <button class="ugw-btn ugw-btn--ghost ugw-btn--sm" id="ugw-canvas-back">Exit Studio</button>
                <h2 class="ugw-canvas-project-name" id="ugw-canvas-title">My Website</h2>
                <span class="ugw-studio-mode-pill">Studio Quality</span>
            </div>
            <div class="ugw-canvas-toolbar-center" id="ugw-canvas-progress">
                <span class="ugw-canvas-progress-text" id="ugw-canvas-progress-text">0 / 0</span>
                <div class="ugw-canvas-progress-bar"><div class="ugw-canvas-progress-fill" id="ugw-canvas-progress-fill"></div></div>
                <div class="ugw-studio-viewport-controls" aria-label="Preview viewport">
                    <button type="button" class="ugw-studio-viewport is-active" data-viewport="desktop">Desktop</button>
                    <button type="button" class="ugw-studio-viewport" data-viewport="tablet">Tablet</button>
                    <button type="button" class="ugw-studio-viewport" data-viewport="mobile">Mobile</button>
                </div>
            </div>
            <div class="ugw-canvas-toolbar-right">
                <div class="ugw-token-group"><label>Primary</label><input type="color" id="ugw-token-primary" value="#1B2A4A" class="ugw-color-picker"></div>
                <div class="ugw-token-group"><label>Accent</label><input type="color" id="ugw-token-accent" value="#00D4AA" class="ugw-color-picker"></div>
                <button class="ugw-btn ugw-btn--primary ugw-btn--sm" id="ugw-canvas-publish-all" disabled>🚀 Publish All</button>
            </div>
        </div>
        <!-- Figma-like Studio workspace is rendered here by admin/js/wizard.js -->
        <div class="ugw-canvas-scroll" id="ugw-canvas-grid"></div>
    </div>

    <!-- ====== WIZARD LAYOUT (sidebar + main) ====== -->
    <div class="ugw-layout" id="ugw-wizard-layout" style="display:none;">

        <!-- Sidebar -->
        <aside class="ugw-sidebar">
            <div class="ugw-sidebar-header">
                <div class="ugw-sidebar-logo">
                    <span>🚀</span>
                    <div>
                        <span style="display:block; font-size:16px; color:#F8FAFC; font-weight:700;">Website Wizard</span>
                        <span>Powered by AI</span>
                    </div>
                </div>
            </div>
            <ul class="ugw-steps" id="ugw-steps-nav">
                <li class="ugw-step ugw-step--active" data-step="discovery">
                    <span class="ugw-step-indicator">1</span>
                    Discovery
                </li>
                <li class="ugw-step" data-step="brand">
                    <span class="ugw-step-indicator">2</span>
                    Brand
                </li>
                <li class="ugw-step" data-step="inspiration">
                    <span class="ugw-step-indicator">3</span>
                    Inspiration
                </li>
                <li class="ugw-step" data-step="pages">
                    <span class="ugw-step-indicator">4</span>
                    Pages
                </li>
                <li class="ugw-step" data-step="content">
                    <span class="ugw-step-indicator">5</span>
                    Content
                </li>
                <li class="ugw-step" data-step="compliance">
                    <span class="ugw-step-indicator">6</span>
                    Legal
                </li>
                <li class="ugw-step" data-step="design">
                    <span class="ugw-step-indicator">7</span>
                    Design
                </li>
                <li class="ugw-step" data-step="images">
                    <span class="ugw-step-indicator">8</span>
                    Images
                </li>
            </ul>
            <div style="padding: 16px 20px; border-top: 1px solid rgba(255,255,255,0.06);">
                <button class="ugw-btn ugw-btn--ghost ugw-btn--sm ugw-btn--full" id="ugw-back-to-projects" style="color: var(--ugw-text-light);">
                    ← All Projects
                </button>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="ugw-main">

            <!-- ===== STEP 1: DISCOVERY ===== -->
            <div class="ugw-step-content ugw-step-content--active" id="ugw-step-discovery">
                <div class="ugw-content">
                    <div class="ugw-section-header">
                        <h2 class="ugw-section-title">Tell us about your business</h2>
                        <p class="ugw-section-subtitle">We'll use this information to create a website tailored to your needs.</p>
                    </div>
                    <div class="ugw-progress" id="ugw-discovery-progress">
                        <div class="ugw-progress-bar" style="width: 0%"></div>
                    </div>
                    <div class="ugw-chat" id="ugw-discovery-chat"></div>
                    <div class="ugw-chat-input-area" id="ugw-discovery-input-area">
                        <textarea class="ugw-chat-input" id="ugw-discovery-input" placeholder="Type your answer..." rows="1"></textarea>
                        <button class="ugw-btn ugw-btn--primary" id="ugw-discovery-send">Send</button>
                    </div>
                    <div id="ugw-discovery-chips" class="ugw-chips" style="margin-top: 12px;"></div>
                    <div id="ugw-discovery-multi-input" style="display: none; margin-top: 12px;"></div>
                    <div id="ugw-discovery-contact-form" style="display: none; margin-top: 12px;"></div>
                </div>
            </div>

            <!-- ===== STEP 3: BRAND ===== -->
            <div class="ugw-step-content" id="ugw-step-brand">
                <div class="ugw-content">
                    <div class="ugw-section-header">
                        <h2 class="ugw-section-title">Brand Identity</h2>
                        <p class="ugw-section-subtitle">Define your brand colors, typography, and design style.</p>
                    </div>

                    <div class="ugw-form-group" style="background: var(--ugw-bg-surface-alt, #f8fafc); border: 1px solid var(--ugw-border, #e2e8f0); border-radius: 12px; padding: 18px;">
                        <label class="ugw-form-label">Industry Optimizer</label>
                        <p style="margin: 0 0 14px; color: var(--ugw-text-secondary); font-size: 13px;">Tune the AI for your specific market before it plans copy and page layouts.</p>
                        <div style="display:grid; gap:12px;">
                            <select class="ugw-input" id="ugw-guided-industry-key">
                                <option value="">Select an industry...</option>
                            </select>
                            <input type="text" class="ugw-input" id="ugw-guided-industry" placeholder="Custom industry (optional)" style="display:none;">
                            <input type="text" class="ugw-input" id="ugw-guided-industry-niche" placeholder="Niche / specialty">
                            <textarea class="ugw-input" id="ugw-guided-target-audience" placeholder="Target audience" style="min-height:52px; resize:vertical;"></textarea>
                            <textarea class="ugw-input" id="ugw-guided-services" placeholder="Top services or offers, comma-separated" style="min-height:52px; resize:vertical;"></textarea>
                            <textarea class="ugw-input" id="ugw-guided-goals" placeholder="Primary website goals, comma-separated" style="min-height:52px; resize:vertical;"></textarea>
                            <select class="ugw-input" id="ugw-guided-tone">
                                <option value="professional">Professional</option>
                                <option value="friendly">Friendly</option>
                                <option value="authoritative">Authoritative</option>
                                <option value="luxury">Luxury</option>
                                <option value="bold">Bold</option>
                                <option value="playful">Playful</option>
                            </select>
                        </div>
                    </div>

                    <div class="ugw-form-group">
                        <label class="ugw-form-label">AI-Suggested Palettes</label>
                        <div id="ugw-palettes-loading" class="ugw-loading" style="display: none;">
                            <div class="ugw-spinner"></div>
                            <div class="ugw-loading-text">Generating palettes...</div>
                        </div>
                        <div id="ugw-palettes-list"></div>
                        <button class="ugw-btn ugw-btn--ghost ugw-btn--sm" id="ugw-suggest-palettes" style="margin-top: 10px;">✨ Suggest Palettes with AI</button>
                    </div>

                    <div class="ugw-color-row" style="margin-bottom: 24px;">
                        <div class="ugw-color-field">
                            <label class="ugw-form-label">Primary Color</label>
                            <input type="text" class="ugw-input" id="ugw-brand-primary" value="#1B2A4A">
                        </div>
                        <div class="ugw-color-field">
                            <label class="ugw-form-label">Secondary / Accent Color</label>
                            <input type="text" class="ugw-input" id="ugw-brand-secondary" value="#00D4AA">
                        </div>
                    </div>

                    <div class="ugw-form-group">
                        <label class="ugw-form-label">Design Style</label>
                        <div class="ugw-card-grid ugw-card-grid--3" id="ugw-design-styles">
                            <div class="ugw-card ugw-card--selectable" data-style="minimal">
                                <div class="ugw-card-title">Minimal</div>
                                <div class="ugw-card-desc">Clean, spacious, refined</div>
                            </div>
                            <div class="ugw-card ugw-card--selectable" data-style="bold">
                                <div class="ugw-card-title">Bold</div>
                                <div class="ugw-card-desc">Strong typography, high contrast</div>
                            </div>
                            <div class="ugw-card ugw-card--selectable" data-style="editorial">
                                <div class="ugw-card-title">Editorial</div>
                                <div class="ugw-card-desc">Magazine-inspired layouts</div>
                            </div>
                            <div class="ugw-card ugw-card--selectable" data-style="luxury">
                                <div class="ugw-card-title">Luxury</div>
                                <div class="ugw-card-desc">Elegant, premium, serif</div>
                            </div>
                            <div class="ugw-card ugw-card--selectable" data-style="tech">
                                <div class="ugw-card-title">Tech</div>
                                <div class="ugw-card-desc">Modern, dynamic, gradient</div>
                            </div>
                            <div class="ugw-card ugw-card--selectable" data-style="creative">
                                <div class="ugw-card-title">Creative</div>
                                <div class="ugw-card-desc">Artistic, expressive, unique</div>
                            </div>
                        </div>
                    </div>

                    <div class="ugw-btn-group" style="margin-top: 28px;">
                        <button class="ugw-btn ugw-btn--primary ugw-btn--lg" id="ugw-brand-save">Continue →</button>
                    </div>
                </div>
            </div>

            <!-- ===== STEP 4: INSPIRATION ===== -->
            <div class="ugw-step-content" id="ugw-step-inspiration">
                <div class="ugw-content ugw-content--wide">
                    <div class="ugw-section-header">
                        <h2 class="ugw-section-title">Design Inspiration</h2>
                        <p class="ugw-section-subtitle">Choose how you'd like to define your design style.</p>
                    </div>

                    <!-- Three-tab inspiration mode selector -->
                    <div class="ugw-inspiration-modes" id="ugw-inspiration-modes">
                        <button class="ugw-insp-mode ugw-insp-mode--active" data-mode="quiz">
                            <span class="ugw-insp-mode-icon">🎨</span>
                            <span class="ugw-insp-mode-label">Style Quiz</span>
                            <span class="ugw-insp-mode-desc">Answer questions & pick from gallery</span>
                        </button>
                        <button class="ugw-insp-mode" data-mode="upload">
                            <span class="ugw-insp-mode-icon">📸</span>
                            <span class="ugw-insp-mode-label">Upload Design</span>
                            <span class="ugw-insp-mode-desc">Screenshot of a design you love</span>
                        </button>
                        <button class="ugw-insp-mode" data-mode="url">
                            <span class="ugw-insp-mode-icon">🔗</span>
                            <span class="ugw-insp-mode-label">Website URL</span>
                            <span class="ugw-insp-mode-desc">Paste a website to extract its style</span>
                        </button>
                    </div>

                    <!-- MODE 1: Quiz (existing) -->
                    <div class="ugw-insp-panel ugw-insp-panel--active" id="ugw-insp-panel-quiz">
                        <!-- Design Preference Quiz -->
                        <div id="ugw-inspiration-quiz">
                            <div class="ugw-inspiration-quiz-progress">
                                <div class="ugw-inspiration-quiz-bar" id="ugw-quiz-bar" style="width: 0%"></div>
                            </div>
                            <div id="ugw-quiz-question-area"></div>
                            <div class="ugw-btn-group" style="margin-top: 24px;">
                                <button class="ugw-btn ugw-btn--ghost" id="ugw-quiz-back" style="display:none;">← Back</button>
                                <button class="ugw-btn ugw-btn--primary" id="ugw-quiz-next" disabled>Next →</button>
                            </div>
                        </div>

                        <!-- Inspiration Gallery (shown after quiz) -->
                        <div id="ugw-inspiration-gallery-area" style="display: none;">
                            <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 8px;">✨ Websites that match your taste</h3>
                            <p style="color: var(--ugw-text-secondary); margin-bottom: 20px; font-size: 14px;">Select sites you like — their design DNA will influence your website. Pick at least 1.</p>
                            <div class="ugw-inspiration-grid" id="ugw-inspiration-grid"></div>
                            <div class="ugw-btn-group" style="margin-top: 28px;">
                                <button class="ugw-btn ugw-btn--primary ugw-btn--lg" id="ugw-inspiration-save" disabled>Save Preferences & Continue →</button>
                                <button class="ugw-btn ugw-btn--ghost" id="ugw-inspiration-skip">Skip this step →</button>
                            </div>
                        </div>
                    </div>

                    <!-- MODE 2: Upload Design Screenshot -->
                    <div class="ugw-insp-panel" id="ugw-insp-panel-upload">
                        <div class="ugw-blueprint-upload-zone" id="ugw-blueprint-dropzone">
                            <div class="ugw-blueprint-upload-inner">
                                <div class="ugw-blueprint-upload-icon">📸</div>
                                <h3>Drop a design screenshot here</h3>
                                <p>Upload a screenshot of a website or design you love. Our AI will extract a complete Design Blueprint — colors, typography, borders, layout, shadows, and more.</p>
                                <div style="margin-top: 16px;">
                                    <button class="ugw-btn ugw-btn--primary" id="ugw-blueprint-browse-btn">Browse Files</button>
                                </div>
                                <input type="file" id="ugw-blueprint-file-input" accept="image/*" style="display:none;">
                            </div>
                        </div>

                        <!-- Upload preview + loading -->
                        <div id="ugw-blueprint-upload-preview" style="display:none;">
                            <div class="ugw-blueprint-preview-card">
                                <img id="ugw-blueprint-preview-img" src="" alt="Design inspiration">
                                <div class="ugw-blueprint-status" id="ugw-blueprint-upload-status">
                                    <div class="ugw-spinner"></div>
                                    <span>Analyzing design and generating blueprint...</span>
                                </div>
                            </div>
                        </div>

                        <!-- Blueprint Result -->
                        <div id="ugw-blueprint-result-upload" style="display:none;"></div>
                    </div>

                    <!-- MODE 3: URL Input -->
                    <div class="ugw-insp-panel" id="ugw-insp-panel-url">
                        <div class="ugw-blueprint-url-card">
                            <div class="ugw-blueprint-url-icon">🌐</div>
                            <h3>Paste a website URL</h3>
                            <p>We'll analyze the website's design and extract a comprehensive blueprint covering colors, typography, borders, layout patterns, shadows, and component styles.</p>
                            <div class="ugw-blueprint-url-input-wrap">
                                <input type="url" class="ugw-input" id="ugw-blueprint-url-input" placeholder="https://example.com">
                                <button class="ugw-btn ugw-btn--primary" id="ugw-blueprint-url-go">Analyze Design →</button>
                            </div>
                            <div class="ugw-blueprint-url-examples">
                                <span>Try:</span>
                                <a class="ugw-blueprint-url-example" data-url="https://stripe.com">Stripe</a>
                                <a class="ugw-blueprint-url-example" data-url="https://linear.app">Linear</a>
                                <a class="ugw-blueprint-url-example" data-url="https://vercel.com">Vercel</a>
                            </div>
                        </div>

                        <!-- URL loading -->
                        <div id="ugw-blueprint-url-loading" style="display:none;">
                            <div class="ugw-blueprint-preview-card">
                                <div class="ugw-blueprint-status">
                                    <div class="ugw-spinner"></div>
                                    <span id="ugw-blueprint-url-status-text">Fetching and analyzing website design...</span>
                                </div>
                            </div>
                        </div>

                        <!-- Blueprint Result -->
                        <div id="ugw-blueprint-result-url" style="display:none;"></div>
                    </div>

                </div>
            </div>

            <!-- ===== STEP 5: PAGES ===== -->
            <div class="ugw-step-content" id="ugw-step-pages">
                <div class="ugw-content">
                    <div class="ugw-section-header">
                        <h2 class="ugw-section-title">Plan Your Pages</h2>
                        <p class="ugw-section-subtitle">Let AI suggest the perfect page structure for your website.</p>
                    </div>

                    <div class="ugw-form-group">
                        <label class="ugw-form-label">Any specific pages you want? <span>(optional)</span></label>
                        <textarea class="ugw-textarea" id="ugw-pages-input" placeholder="e.g. I definitely need a pricing page with 3 tiers, and a portfolio showcase..."></textarea>
                    </div>

                    <div class="ugw-btn-group">
                        <button class="ugw-btn ugw-btn--primary" id="ugw-pages-generate">✨ Generate Sitemap with AI</button>
                        <button class="ugw-btn ugw-btn--ghost" id="ugw-pages-add-manual">➕ Add Page Manually</button>
                    </div>

                    <!-- Manual page add form (hidden by default) -->
                    <div id="ugw-manual-page-form" style="display: none; margin-top: 16px; padding: 20px; border: 2px dashed var(--ugw-border, #e2e8f0); border-radius: 12px; background: var(--ugw-card-bg, #fff);">
                        <h4 style="margin: 0 0 12px; font-size: 14px; font-weight: 600;">Add a Page</h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                            <div>
                                <label class="ugw-form-label" style="font-size: 12px;">Page Name</label>
                                <input type="text" class="ugw-input" id="ugw-manual-page-name" placeholder="e.g. About Us">
                            </div>
                            <div>
                                <label class="ugw-form-label" style="font-size: 12px;">Page Type</label>
                                <select class="ugw-select" id="ugw-manual-page-type" style="width:100%;padding:8px 12px;border:1px solid var(--ugw-border, #e2e8f0);border-radius:8px;font-size:14px;background:#fff;">
                                    <option value="homepage">Homepage</option>
                                    <option value="about" selected>About</option>
                                    <option value="services">Services</option>
                                    <option value="pricing">Pricing</option>
                                    <option value="contact">Contact</option>
                                    <option value="blog">Blog</option>
                                    <option value="faq">FAQ</option>
                                    <option value="team">Team</option>
                                    <option value="portfolio">Portfolio</option>
                                    <option value="testimonials">Testimonials</option>
                                    <option value="features">Features</option>
                                    <option value="case-studies">Case Studies</option>
                                    <option value="landing">Landing Page</option>
                                </select>
                            </div>
                        </div>
                        <div style="margin-bottom: 12px;">
                            <label class="ugw-form-label" style="font-size: 12px;">Description <span>(optional)</span></label>
                            <input type="text" class="ugw-input" id="ugw-manual-page-desc" placeholder="Brief description of this page's purpose...">
                        </div>
                        <div class="ugw-btn-group" style="gap: 8px;">
                            <button class="ugw-btn ugw-btn--primary ugw-btn--sm" id="ugw-manual-page-submit">Add Page</button>
                            <button class="ugw-btn ugw-btn--ghost ugw-btn--sm" id="ugw-manual-page-cancel">Cancel</button>
                        </div>
                    </div>

                    <div id="ugw-pages-loading" class="ugw-loading" style="display: none;">
                        <div class="ugw-spinner"></div>
                        <div class="ugw-loading-text">Planning your website structure...</div>
                        <div class="ugw-loading-elapsed" id="ugw-pages-elapsed" style="font-size: 12px; color: var(--ugw-text-secondary, #94a3b8); margin-top: 8px;"></div>
                        <button class="ugw-btn ugw-btn--ghost ugw-btn--sm" id="ugw-pages-cancel-gen" style="margin-top: 12px;">Cancel</button>
                    </div>

                    <div id="ugw-pages-result" style="display: none; margin-top: 24px;">
                        <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 12px;">Your Website Pages</h3>
                        <ul class="ugw-page-list" id="ugw-page-list"></ul>
                        <div class="ugw-btn-group" style="margin-top: 12px;">
                            <button class="ugw-btn ugw-btn--ghost ugw-btn--sm" id="ugw-pages-add-more" style="width: 100%;">➕ Add another page</button>
                        </div>
                        <div class="ugw-btn-group" style="margin-top: 20px;">
                            <button class="ugw-btn ugw-btn--primary" id="ugw-pages-approve">Looks great! Continue →</button>
                            <button class="ugw-btn ugw-btn--ghost" id="ugw-pages-regenerate">🔄 Regenerate</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== STEP 5: CONTENT ===== -->
            <div class="ugw-step-content" id="ugw-step-content">
                <div class="ugw-content">
                    <div class="ugw-section-header">
                        <h2 class="ugw-section-title">Content Generation</h2>
                        <p class="ugw-section-subtitle">AI-generated copy for each page. Review, edit, and approve.</p>
                    </div>

                    <div class="ugw-content-tabs" id="ugw-content-tabs"></div>

                    <div id="ugw-content-area">
                        <div id="ugw-content-loading" class="ugw-loading" style="display: none;">
                            <div class="ugw-spinner"></div>
                            <div class="ugw-loading-text">Writing content...</div>
                        </div>
                        <div id="ugw-content-sections"></div>
                        <div id="ugw-content-actions" style="display: none; margin-top: 24px;">
                            <div class="ugw-form-group">
                                <textarea class="ugw-textarea" id="ugw-content-feedback" placeholder="Want changes? Describe what to revise..." style="min-height: 60px;"></textarea>
                            </div>
                            <div class="ugw-btn-group">
                                <button class="ugw-btn ugw-btn--primary" id="ugw-content-approve">✓ Approve This Page</button>
                                <button class="ugw-btn ugw-btn--secondary" id="ugw-content-revise">🔄 Revise Content</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== STEP 6: COMPLIANCE ===== -->
            <div class="ugw-step-content" id="ugw-step-compliance">
                <div class="ugw-content">
                    <div class="ugw-section-header">
                        <h2 class="ugw-section-title">Legal &amp; Compliance</h2>
                        <p class="ugw-section-subtitle">Answer a few questions to generate the right legal pages for your website.</p>
                    </div>
                    <div id="ugw-compliance-form"></div>
                    <div class="ugw-btn-group" style="margin-top: 28px;">
                        <button class="ugw-btn ugw-btn--primary ugw-btn--lg" id="ugw-compliance-save">Generate Legal Pages →</button>
                        <button class="ugw-btn ugw-btn--ghost" id="ugw-compliance-skip">Skip</button>
                    </div>
                    <div id="ugw-compliance-result" style="display: none; margin-top: 24px;"></div>
                </div>
            </div>

            <!-- ===== STEP 7: DESIGN ===== -->
            <div class="ugw-step-content" id="ugw-step-design">
                <div class="ugw-content ugw-content--full">
                    <div class="ugw-design-layout">
                        <div class="ugw-design-preview">
                            <div id="ugw-design-loading" class="ugw-loading" style="display: none;">
                                <div class="ugw-spinner"></div>
                                <div class="ugw-loading-text">Generating design...</div>
                            </div>
                            <iframe id="ugw-design-iframe" srcdoc=""></iframe>
                            <div style="position: absolute; top: 16px; left: 16px; display: flex; gap: 8px; z-index: 5;">
                                <select class="ugw-select" id="ugw-design-page-select" style="width: auto; padding: 6px 12px; font-size: 12px;"></select>
                            </div>
                        </div>
                        <div class="ugw-design-chat">
                            <div class="ugw-design-chat-header">
                                💬 Design Feedback
                            </div>
                            <div class="ugw-design-chat-messages" id="ugw-design-messages">
                                <div class="ugw-chat-bubble ugw-chat-bubble--assistant">
                                    Your design is ready! Tell me what changes you'd like, or approve it to move on.
                                </div>
                            </div>
                            <div class="ugw-design-chat-input-area">
                                <textarea class="ugw-chat-input" id="ugw-design-input" placeholder="e.g. Make the hero taller..." rows="1"></textarea>
                                <button class="ugw-btn ugw-btn--primary ugw-btn--sm" id="ugw-design-send">Send</button>
                            </div>
                            <div style="padding: 8px 16px; display: flex; gap: 8px;">
                                <button class="ugw-btn ugw-btn--primary ugw-btn--sm ugw-btn--full" id="ugw-design-approve">✓ Approve Design</button>
                                <button class="ugw-btn ugw-btn--secondary ugw-btn--sm" id="ugw-design-regenerate" title="Regenerate from scratch">🔄</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== STEP 8: IMAGES ===== -->
            <div class="ugw-step-content" id="ugw-step-images">
                <div class="ugw-content ugw-content--wide">
                    <div class="ugw-section-header">
                        <h2 class="ugw-section-title">Image Generation</h2>
                        <p class="ugw-section-subtitle">Generate AI images for your website or skip to add them later.</p>
                    </div>

                    <div class="ugw-btn-group" style="margin-bottom: 24px;">
                        <button class="ugw-btn ugw-btn--primary" id="ugw-images-generate-all">✨ Generate All Images</button>
                        <button class="ugw-btn ugw-btn--ghost" id="ugw-images-skip">I'll add images later →</button>
                    </div>

                    <div class="ugw-image-grid" id="ugw-image-grid"></div>

                    <div class="ugw-btn-group" style="margin-top: 28px;">
                        <button class="ugw-btn ugw-btn--primary ugw-btn--lg" id="ugw-images-continue">Continue to Launch →</button>
                    </div>
                </div>
            </div>

            <!-- ===== LAUNCH / SUMMARY ===== -->
            <div class="ugw-step-content" id="ugw-step-launch" style="display: none;">
                <div class="ugw-content">
                    <div class="ugw-launch">
                        <div class="ugw-launch-icon">🎉</div>
                        <h2 class="ugw-launch-title">Your website is ready!</h2>
                        <p class="ugw-launch-desc">Review your creation and publish when you're ready.</p>
                        <div class="ugw-launch-stats" id="ugw-launch-stats"></div>
                        <div class="ugw-btn-group" style="justify-content: center;">
                            <button class="ugw-btn ugw-btn--primary ugw-btn--lg" id="ugw-launch-publish">🚀 Publish All Pages</button>
                            <button class="ugw-btn ugw-btn--secondary" id="ugw-launch-sitemap">📄 Generate Sitemap XML</button>
                        </div>

                        <!-- Publishing Progress Bar -->
                        <div id="ugw-publish-progress" style="display: none; margin-top: 32px; max-width: 500px; margin-left: auto; margin-right: auto;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <span id="ugw-publish-step-label" style="font-weight: 600; font-size: 14px; color: var(--ugw-text);">Preparing...</span>
                                <span id="ugw-publish-step-count" style="font-size: 13px; color: var(--ugw-text-muted);">0 / 0</span>
                            </div>
                            <div style="background: var(--ugw-surface-alt, #e5e7eb); border-radius: 12px; height: 16px; overflow: hidden; position: relative;">
                                <div id="ugw-publish-bar" style="height: 100%; width: 0%; background: linear-gradient(90deg, var(--ugw-primary, #2563eb), #60a5fa); border-radius: 12px; transition: width 0.5s cubic-bezier(0.4,0,0.2,1); position: relative;">
                                    <div style="position: absolute; inset: 0; background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.3) 50%, transparent 100%); animation: ugw-shine 2s infinite;"></div>
                                </div>
                            </div>
                            <div id="ugw-publish-current-page" style="margin-top: 10px; font-size: 13px; color: var(--ugw-text-muted); text-align: center;"></div>
                        </div>
                        <style>
                        @keyframes ugw-shine {
                            0% { transform: translateX(-100%); }
                            100% { transform: translateX(100%); }
                        }
                        </style>

                        <div id="ugw-launch-result" style="margin-top: 24px;"></div>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <!-- Toast container -->
    <div class="ugw-toast-container" id="ugw-toasts"></div>
</div>

