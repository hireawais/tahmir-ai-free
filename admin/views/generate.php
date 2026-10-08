<?php
/**
 * Generate Page View — Premium UI
 */

if (!defined('ABSPATH')) {
    exit;
}

$api = ugentic_builder()->api;
$database = ugentic_builder()->database;
$existing_pages = $database->get_all_pages(['limit' => 50]);
?>
<div class="wrap ugentic-wrap ugentic-generate-v2">

    <!-- Hero Header -->
    <div class="generate-header">
        <div class="header-content">
            <div class="header-icon">
                <span class="dashicons dashicons-admin-site-alt3"></span>
            </div>
            <div class="header-text">
                <h1><?php esc_html_e('Generate Page with AI', 'tahmir-ai-to-wp-converter'); ?></h1>
                <p><?php esc_html_e('Describe the page you want to create. Be specific about the layout, content, and styling.', 'tahmir-ai-to-wp-converter'); ?>
                </p>
            </div>
        </div>
        <div class="header-decoration">
            <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" class="blob-svg">
                <path fill="rgba(255,255,255,0.08)"
                    d="M44.7,-76.4C58.8,-69.2,71.8,-59.1,79.6,-45.8C87.4,-32.5,90,-16.3,88.5,-0.9C87,14.5,81.3,29,72.8,41.7C64.3,54.4,53,65.3,39.7,72.9C26.4,80.5,13.2,84.8,-1.1,86.7C-15.4,88.6,-30.8,88.1,-43.3,80.8C-55.8,73.5,-65.4,59.4,-73.1,44.8C-80.8,30.2,-86.6,15.1,-87.2,-0.3C-87.8,-15.8,-83.2,-31.6,-74.5,-44.4C-65.8,-57.2,-53,-67,-39.2,-74.4C-25.4,-81.8,-12.7,-86.8,1.4,-89.2C15.5,-91.6,30.6,-83.6,44.7,-76.4Z"
                    transform="translate(100 100)" />
            </svg>
        </div>
    </div>

    <?php if (!$api->is_configured() && !$api->is_configured('anthropic')): ?>
        <div class="generate-notice">
            <div class="notice-icon-wrap">
                <span class="dashicons dashicons-warning"></span>
            </div>
            <div class="notice-body">
                <strong><?php esc_html_e('API Key Required', 'tahmir-ai-to-wp-converter'); ?></strong>
                <p>
                    <?php esc_html_e('Please configure your Gemini or Anthropic API key in', 'tahmir-ai-to-wp-converter'); ?>
                    <a
                        href="<?php echo esc_url(admin_url('admin.php?page=ugentic-settings')); ?>"><?php esc_html_e('Settings', 'tahmir-ai-to-wp-converter'); ?></a>
                    <?php esc_html_e('to use AI features.', 'tahmir-ai-to-wp-converter'); ?>
                </p>
            </div>
        </div>
    <?php else: ?>

        <div class="generate-layout">

            <!-- Main Form Panel -->
            <div class="generate-panel main-panel">
                <div class="panel-header">
                    <div class="panel-icon">
                        <span class="dashicons dashicons-edit-page"></span>
                    </div>
                    <h2><?php esc_html_e('Create a New Page', 'tahmir-ai-to-wp-converter'); ?></h2>
                </div>

                <form id="generate-form">
                    <?php wp_nonce_field('ugentic_admin_nonce', 'nonce'); ?>

                    <div class="form-group">
                        <label for="page-title">
                            <span class="dashicons dashicons-tag"></span>
                            <?php esc_html_e('Page Title', 'tahmir-ai-to-wp-converter'); ?>
                        </label>
                        <input type="text" id="page-title" name="title"
                            placeholder="<?php esc_html_e('e.g., About Us, Pricing, Contact', 'tahmir-ai-to-wp-converter'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="page-prompt">
                            <span class="dashicons dashicons-editor-paragraph"></span>
                            <?php esc_html_e('Describe Your Page', 'tahmir-ai-to-wp-converter'); ?> <span class="required-dot"></span>
                        </label>
                        <textarea id="page-prompt" name="prompt" rows="5" required
                            placeholder="<?php esc_html_e('e.g., Create a modern pricing page with 3 tiers: Basic ($9/mo), Pro ($29/mo), and Enterprise ($99/mo). Include a comparison table, FAQ section, and a call-to-action at the bottom. Use a clean, professional design with blue and white colors.', 'tahmir-ai-to-wp-converter'); ?>"></textarea>
                        <span
                            class="input-hint"><?php esc_html_e('The more detail you provide, the better the result.', 'tahmir-ai-to-wp-converter'); ?></span>
                    </div>

                    <!-- ═══ Design Vision: Image Upload ═══ -->
                    <div class="form-group dv-generate-section">
                        <div class="dv-or-divider">
                            <span class="dv-or-line"></span>
                            <span class="dv-or-text">OR</span>
                            <span class="dv-or-line"></span>
                        </div>
                        <div class="dv-upload-card" id="dv-gen-upload-card">
                            <div class="dv-upload-card-inner" id="dv-gen-dropzone">
                                <div class="dv-upload-icon">
                                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                        <circle cx="8.5" cy="8.5" r="1.5"/>
                                        <polyline points="21 15 16 10 5 21"/>
                                    </svg>
                                </div>
                                <div class="dv-upload-text">
                                    <h3>✨ <?php esc_html_e('Upload a Design Screenshot', 'tahmir-ai-to-wp-converter'); ?></h3>
                                    <p><?php esc_html_e('Drop an image of any design and AI will recreate it pixel-perfectly', 'tahmir-ai-to-wp-converter'); ?></p>
                                </div>
                                <div class="dv-upload-actions">
                                    <label for="dv-gen-file-input" class="dv-gen-browse-btn">
                                        <span class="dashicons dashicons-upload"></span>
                                        <?php esc_html_e('Choose Image', 'tahmir-ai-to-wp-converter'); ?>
                                    </label>
                                    <span class="dv-gen-paste-hint">
                                        or <kbd>Ctrl</kbd>+<kbd>V</kbd> to paste
                                    </span>
                                </div>
                                <input type="file" id="dv-gen-file-input" accept="image/png,image/jpeg,image/webp" style="display:none;">
                            </div>
                            <!-- Preview state (hidden until file selected) -->
                            <div class="dv-gen-preview" id="dv-gen-preview" style="display:none;">
                                <img id="dv-gen-preview-img" src="" alt="Design preview">
                                <div class="dv-gen-preview-actions">
                                    <button type="button" class="dv-gen-change-btn" id="dv-gen-change">
                                        <span class="dashicons dashicons-image-rotate"></span> <?php esc_html_e('Change', 'tahmir-ai-to-wp-converter'); ?>
                                    </button>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=ugentic-design-vision')); ?>"
                                       class="dv-gen-go-btn" id="dv-gen-go-btn">
                                        <span class="dashicons dashicons-admin-customizer"></span>
                                        <?php esc_html_e('Generate from Image →', 'tahmir-ai-to-wp-converter'); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Template Suggestions -->
                    <div class="form-group">
                        <label>
                            <span class="dashicons dashicons-screenoptions"></span>
                            <?php esc_html_e('Quick Templates', 'tahmir-ai-to-wp-converter'); ?>
                        </label>
                        <div class="template-grid">
                            <button type="button" class="template-chip"
                                data-prompt="Create a modern landing page with a hero section featuring a headline, subheadline, and CTA button. Include sections for features (3 columns), testimonials, and a contact form.">
                                <span class="chip-icon">🚀</span>
                                <span><?php esc_html_e('Landing Page', 'tahmir-ai-to-wp-converter'); ?></span>
                            </button>
                            <button type="button" class="template-chip"
                                data-prompt="Create a pricing page with 3 tiers: Basic, Pro, and Enterprise. Include feature comparison, FAQs, and money-back guarantee badge.">
                                <span class="chip-icon">💰</span>
                                <span><?php esc_html_e('Pricing Page', 'tahmir-ai-to-wp-converter'); ?></span>
                            </button>
                            <button type="button" class="template-chip"
                                data-prompt="Create an About Us page with company story, team members section (3-4 people with photos), mission statement, and company values.">
                                <span class="chip-icon">👥</span>
                                <span><?php esc_html_e('About Us', 'tahmir-ai-to-wp-converter'); ?></span>
                            </button>
                            <button type="button" class="template-chip"
                                data-prompt="Create a contact page with a contact form (name, email, subject, message), office address, phone number, email, and an embedded map placeholder.">
                                <span class="chip-icon">📬</span>
                                <span><?php esc_html_e('Contact Page', 'tahmir-ai-to-wp-converter'); ?></span>
                            </button>
                            <button type="button" class="template-chip"
                                data-prompt="Create a services page showcasing 6 services with icons, descriptions, and 'Learn More' links. Include a CTA section at the bottom.">
                                <span class="chip-icon">⚙️</span>
                                <span><?php esc_html_e('Services Page', 'tahmir-ai-to-wp-converter'); ?></span>
                            </button>
                            <button type="button" class="template-chip"
                                data-prompt="Create an FAQ page with 10 common questions organized in an accordion/collapsible format. Include a search bar and contact support section.">
                                <span class="chip-icon">❓</span>
                                <span><?php esc_html_e('FAQ Page', 'tahmir-ai-to-wp-converter'); ?></span>
                            </button>
                        </div>
                    </div>

                    <!-- Design Intelligence Selectors -->
                    <div class="form-group">
                        <label>
                            <span class="dashicons dashicons-admin-appearance"></span>
                            <?php esc_html_e('Design Intelligence', 'tahmir-ai-to-wp-converter'); ?>
                            <span
                                class="label-hint"><?php esc_html_e('(optional — auto-detected from prompt)', 'tahmir-ai-to-wp-converter'); ?></span>
                        </label>
                        <div class="design-selectors-row">
                            <div class="design-selector">
                                <label for="gen-industry"><?php esc_html_e('Industry', 'tahmir-ai-to-wp-converter'); ?></label>
                                <select id="gen-industry" name="industry">
                                    <option value=""><?php esc_html_e('Auto-detect', 'tahmir-ai-to-wp-converter'); ?></option>
                                    <option value="saas">SaaS / Software</option>
                                    <option value="ecommerce">E-commerce / Retail</option>
                                    <option value="finance">Finance / Insurance</option>
                                    <option value="healthcare">Healthcare / Wellness</option>
                                    <option value="agency">Agency / Consulting</option>
                                    <option value="restaurant">Restaurant / Food</option>
                                    <option value="realestate">Real Estate</option>
                                    <option value="education">Education</option>
                                    <option value="law">Legal / Law Firm</option>
                                    <option value="travel">Travel / Hospitality</option>
                                    <option value="construction">Construction</option>
                                    <option value="nonprofit">Nonprofit / Charity</option>
                                    <option value="portfolio">Portfolio / Personal</option>
                                </select>
                            </div>
                            <div class="design-selector">
                                <label for="gen-page-type"><?php esc_html_e('Page Type', 'tahmir-ai-to-wp-converter'); ?></label>
                                <select id="gen-page-type" name="page_type">
                                    <option value=""><?php esc_html_e('Auto-detect', 'tahmir-ai-to-wp-converter'); ?></option>
                                    <option value="homepage">Homepage</option>
                                    <option value="about">About Us</option>
                                    <option value="services">Services</option>
                                    <option value="pricing">Pricing</option>
                                    <option value="contact">Contact</option>
                                    <option value="blog">Blog / News</option>
                                    <option value="faq">FAQ</option>
                                    <option value="portfolio">Portfolio / Work</option>
                                    <option value="careers">Careers</option>
                                </select>
                            </div>
                            <div class="design-selector">
                                <label for="gen-design-style"><?php esc_html_e('Design Style', 'tahmir-ai-to-wp-converter'); ?></label>
                                <select id="gen-design-style" name="design_style">
                                    <option value=""><?php esc_html_e('Auto (Minimal)', 'tahmir-ai-to-wp-converter'); ?></option>
                                    <option value="minimal">Minimal / Clean</option>
                                    <option value="bold">Bold Maximalist</option>
                                    <option value="editorial">Editorial</option>
                                    <option value="luxury">Luxury / Premium</option>
                                    <option value="tech">Tech / Dark Mode</option>
                                    <option value="creative-agency">Creative Agency</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Match Existing Site Style -->
                    <?php if (!empty($existing_pages)): ?>
                        <div class="style-match-section">
                            <label class="toggle-label">
                                <div class="toggle-switch">
                                    <input type="checkbox" id="match-style-toggle" value="1">
                                    <span class="toggle-slider"></span>
                                </div>
                                <div class="toggle-info">
                                    <span class="toggle-title">
                                        <span class="dashicons dashicons-art"></span>
                                        <?php esc_html_e('Match Existing Site Style', 'tahmir-ai-to-wp-converter'); ?>
                                    </span>
                                    <span
                                        class="toggle-desc"><?php esc_html_e('Clone the design DNA from an existing page so the new page looks like it belongs to the same site.', 'tahmir-ai-to-wp-converter'); ?></span>
                                </div>
                            </label>
                            <div id="reference-page-selector" class="reference-selector">
                                <label for="reference-page"><?php esc_html_e('Reference Page:', 'tahmir-ai-to-wp-converter'); ?></label>
                                <select id="reference-page" name="reference_page_id">
                                    <option value="0"><?php esc_html_e('— Select a page to match —', 'tahmir-ai-to-wp-converter'); ?></option>
                                    <?php foreach ($existing_pages as $ep):
                                        $wp_title = '';
                                        if (!empty($ep->wp_page_id)) {
                                            $wp_title = get_the_title($ep->wp_page_id);
                                        }
                                        $label = !empty($wp_title) ? $wp_title : esc_html__('Page #', 'tahmir-ai-to-wp-converter') . $ep->id;
                                        ?>
                                        <option value="<?php echo esc_attr($ep->id); ?>"><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Publish Option -->
                    <div class="publish-option">
                        <label class="checkbox-label">
                            <input type="checkbox" name="publish" value="1">
                            <span class="checkbox-text">
                                <span class="dashicons dashicons-megaphone"></span>
                                <?php esc_html_e('Publish immediately', 'tahmir-ai-to-wp-converter'); ?>
                            </span>
                            <span class="checkbox-hint"><?php esc_html_e('Otherwise saves as draft', 'tahmir-ai-to-wp-converter'); ?></span>
                        </label>
                    </div>

                    <!-- Generate Button -->
                    <div class="form-submit">
                        <button type="submit" class="generate-cta" id="generate-btn">
                            <span class="cta-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
                                </svg>
                            </span>
                            <span class="cta-text"><?php esc_html_e('Generate Page', 'tahmir-ai-to-wp-converter'); ?></span>
                        </button>
                        <span id="generate-status" class="generate-status"></span>
                    </div>
                </form>
            </div>

            <!-- Side Tips Panel -->
            <div class="generate-panel tips-panel">
                <div class="panel-header">
                    <div class="panel-icon tips-icon">
                        <span class="dashicons dashicons-lightbulb"></span>
                    </div>
                    <h2><?php esc_html_e('Tips for Great Results', 'tahmir-ai-to-wp-converter'); ?></h2>
                </div>
                <div class="tips-list">
                    <div class="tip-item">
                        <div class="tip-number">1</div>
                        <div class="tip-content">
                            <strong><?php esc_html_e('Be Specific', 'tahmir-ai-to-wp-converter'); ?></strong>
                            <p><?php esc_html_e('Mention colors, layout style, number of sections, and content type.', 'tahmir-ai-to-wp-converter'); ?>
                            </p>
                        </div>
                    </div>
                    <div class="tip-item">
                        <div class="tip-number">2</div>
                        <div class="tip-content">
                            <strong><?php esc_html_e('Reference Styles', 'tahmir-ai-to-wp-converter'); ?></strong>
                            <p><?php esc_html_e('Use terms like "modern", "minimal", "corporate", or "playful" to guide the design.', 'tahmir-ai-to-wp-converter'); ?>
                            </p>
                        </div>
                    </div>
                    <div class="tip-item">
                        <div class="tip-number">3</div>
                        <div class="tip-content">
                            <strong><?php esc_html_e('Include Content', 'tahmir-ai-to-wp-converter'); ?></strong>
                            <p><?php esc_html_e('Provide actual text, pricing, or feature names for more realistic results.', 'tahmir-ai-to-wp-converter'); ?>
                            </p>
                        </div>
                    </div>
                    <div class="tip-item">
                        <div class="tip-number">4</div>
                        <div class="tip-content">
                            <strong><?php esc_html_e('Iterate', 'tahmir-ai-to-wp-converter'); ?></strong>
                            <p><?php esc_html_e('Generate a draft, then use the visual editor to refine it section by section.', 'tahmir-ai-to-wp-converter'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Preview -->
        <div id="preview-container" class="generate-panel preview-panel" style="display: none;">
            <div class="panel-header">
                <div class="panel-icon preview-icon">
                    <span class="dashicons dashicons-visibility"></span>
                </div>
                <h2><?php esc_html_e('Preview', 'tahmir-ai-to-wp-converter'); ?></h2>
                <div class="preview-actions">
                    <a href="#" id="view-page-btn" class="preview-btn preview-btn-secondary" target="_blank">
                        <span class="dashicons dashicons-external"></span>
                        <?php esc_html_e('View Page', 'tahmir-ai-to-wp-converter'); ?>
                    </a>
                    <a href="#" id="edit-page-btn" class="preview-btn preview-btn-primary">
                        <span class="dashicons dashicons-edit"></span>
                        <?php esc_html_e('Edit Page', 'tahmir-ai-to-wp-converter'); ?>
                    </a>
                </div>
            </div>
            <div class="preview-frame-wrap">
                <iframe id="preview-frame" srcdoc=""></iframe>
            </div>
        </div>

    <?php endif; ?>
</div>

<style>
    /* ==========================================================================
   GENERATE PAGE V2 — Premium Redesign
   ========================================================================== */

    .ugentic-generate-v2 {
        margin: -20px -20px 0;
        padding: 24px;
        background: linear-gradient(180deg, #f0f2f8 0%, #e8eaf0 100%);
        min-height: 100vh;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    /* ── Hero Header ── */
    .generate-header {
        position: relative;
        background: linear-gradient(135deg, #f97316 0%, #c2410c 100%);
        border-radius: 20px;
        padding: 36px 40px;
        margin-bottom: 28px;
        overflow: hidden;
        box-shadow: 0 8px 32px rgba(249, 115, 22, 0.3);
    }

    .header-content {
        display: flex;
        align-items: center;
        gap: 20px;
        position: relative;
        z-index: 2;
    }

    .header-icon {
        width: 64px;
        height: 64px;
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .header-icon .dashicons {
        font-size: 32px;
        width: 32px;
        height: 32px;
        color: #fff;
    }

    .header-text h1 {
        margin: 0 0 4px;
        font-size: 28px;
        font-weight: 700;
        color: #fff;
        letter-spacing: -0.5px;
    }

    .header-text p {
        margin: 0;
        font-size: 14px;
        color: rgba(255, 255, 255, 0.8);
        max-width: 500px;
    }

    .header-decoration {
        position: absolute;
        right: -40px;
        top: -60px;
        width: 280px;
        height: 280px;
        opacity: 0.6;
        z-index: 1;
        pointer-events: none;
    }

    .blob-svg {
        width: 100%;
        height: 100%;
        animation: blobRotate 20s linear infinite;
    }

    @keyframes blobRotate {
        to {
            transform: rotate(360deg);
        }
    }

    /* ── API Notice ── */
    .generate-notice {
        display: flex;
        align-items: center;
        gap: 16px;
        background: #fff;
        border-radius: 16px;
        padding: 20px 24px;
        border-left: 4px solid #f59e0b;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    }

    .notice-icon-wrap {
        width: 48px;
        height: 48px;
        background: #fef3c7;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .notice-icon-wrap .dashicons {
        font-size: 24px;
        width: 24px;
        height: 24px;
        color: #f59e0b;
    }

    .notice-body strong {
        display: block;
        margin-bottom: 4px;
        color: #92400e;
    }

    .notice-body p {
        margin: 0;
        color: #78716c;
        font-size: 13px;
    }

    .notice-body a {
        color: #f97316;
        font-weight: 600;
    }

    /* ── Layout ── */
    .generate-layout {
        display: grid;
        grid-template-columns: 1fr 340px;
        gap: 24px;
        margin-bottom: 24px;
    }

    @media (max-width: 1100px) {
        .generate-layout {
            grid-template-columns: 1fr;
        }
    }

    /* ── Panels ── */
    .generate-panel {
        background: #fff;
        border-radius: 16px;
        padding: 28px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
    }

    .panel-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 24px;
        padding-bottom: 20px;
        border-bottom: 1px solid #f1f5f9;
    }

    .panel-header h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: #1e293b;
        flex: 1;
    }

    .panel-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #f97316, #c2410c);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .panel-icon .dashicons {
        font-size: 20px;
        width: 20px;
        height: 20px;
        color: #fff;
    }

    .tips-icon {
        background: linear-gradient(135deg, #f59e0b, #d97706);
    }

    .preview-icon {
        background: linear-gradient(135deg, #10b981, #059669);
    }

    /* ── Form Styling ── */
    .form-group {
        margin-bottom: 22px;
    }

    .form-group>label {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 8px;
    }

    .form-group>label .dashicons {
        font-size: 16px;
        width: 16px;
        height: 16px;
        color: #f97316;
    }

    .required-dot {
        width: 6px;
        height: 6px;
        background: #ef4444;
        border-radius: 50%;
        display: inline-block;
    }

    .form-group input[type="text"],
    .form-group textarea,
    .form-group select {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        font-size: 14px;
        color: #1e293b;
        background: #f9fafb;
        transition: all 0.2s ease;
        box-sizing: border-box;
        font-family: inherit;
    }

    .form-group input[type="text"]:focus,
    .form-group textarea:focus,
    .form-group select:focus {
        outline: none;
        border-color: #f97316;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.15);
    }

    .form-group textarea {
        resize: vertical;
        min-height: 120px;
        line-height: 1.6;
    }

    .input-hint {
        display: block;
        font-size: 12px;
        color: #9ca3af;
        margin-top: 6px;
    }

    /* ── Template Chips ── */
    .template-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .template-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border: 2px solid #e5e7eb;
        border-radius: 50px;
        background: #fff;
        font-size: 13px;
        font-weight: 500;
        color: #4b5563;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .template-chip:hover {
        border-color: #f97316;
        color: #f97316;
        background: #eef0fd;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(249, 115, 22, 0.15);
    }

    .template-chip:active {
        transform: translateY(0);
    }

    .chip-icon {
        font-size: 16px;
        line-height: 1;
    }

    /* ── Design Intelligence Selectors ── */
    .label-hint {
        font-weight: 400;
        font-size: 11px;
        color: #9ca3af;
        margin-left: 4px;
    }

    .design-selectors-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 14px;
    }

    @media (max-width: 600px) {
        .design-selectors-row {
            grid-template-columns: 1fr;
        }
    }

    .design-selector label {
        display: block;
        font-size: 11px;
        font-weight: 600;
        color: #6b7280;
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .design-selector select {
        width: 100%;
        padding: 10px 12px;
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        font-size: 13px;
        color: #1e293b;
        background: #f9fafb;
        transition: all 0.2s ease;
        box-sizing: border-box;
        cursor: pointer;
        font-family: inherit;
    }

    .design-selector select:focus {
        outline: none;
        border-color: #f97316;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.15);
    }

    /* ── Style Match Section ── */
    .style-match-section {
        background: linear-gradient(135deg, #f8f9ff 0%, #eef0fd 100%);
        border: 2px solid #ddd6fe;
        border-radius: 14px;
        padding: 20px;
        margin-bottom: 22px;
    }

    .toggle-label {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        cursor: pointer;
    }

    /* Custom Toggle Switch */
    .toggle-switch {
        position: relative;
        width: 44px;
        height: 24px;
        flex-shrink: 0;
        margin-top: 2px;
    }

    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
        position: absolute;
    }

    .toggle-slider {
        position: absolute;
        inset: 0;
        background: #d1d5db;
        border-radius: 24px;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .toggle-slider::before {
        content: '';
        position: absolute;
        width: 18px;
        height: 18px;
        left: 3px;
        top: 3px;
        background: #fff;
        border-radius: 50%;
        transition: all 0.3s ease;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    }

    .toggle-switch input:checked+.toggle-slider {
        background: linear-gradient(135deg, #f97316, #c2410c);
    }

    .toggle-switch input:checked+.toggle-slider::before {
        transform: translateX(20px);
    }

    .toggle-info {
        flex: 1;
    }

    .toggle-title {
        display: flex;
        align-items: center;
        gap: 6px;
        font-weight: 600;
        font-size: 14px;
        color: #374151;
    }

    .toggle-title .dashicons {
        font-size: 18px;
        width: 18px;
        height: 18px;
        color: #c2410c;
    }

    .toggle-desc {
        display: block;
        font-size: 12px;
        color: #6b7280;
        margin-top: 4px;
        line-height: 1.5;
    }

    .reference-selector {
        display: none;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid #ddd6fe;
    }

    .reference-selector label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 6px;
    }

    .reference-selector select {
        width: 100%;
        padding: 10px 14px;
        border: 2px solid #ddd6fe;
        border-radius: 10px;
        font-size: 13px;
        background: #fff;
        color: #1e293b;
        transition: all 0.2s ease;
    }

    .reference-selector select:focus {
        outline: none;
        border-color: #f97316;
        box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.15);
    }

    /* ── Publish Option ── */
    .publish-option {
        margin-bottom: 24px;
        padding: 14px 16px;
        background: #f9fafb;
        border-radius: 10px;
        border: 1px solid #e5e7eb;
    }

    .checkbox-label {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        font-size: 14px;
    }

    .checkbox-label input[type="checkbox"] {
        width: 18px;
        height: 18px;
        accent-color: #f97316;
    }

    .checkbox-text {
        display: flex;
        align-items: center;
        gap: 6px;
        font-weight: 500;
        color: #374151;
    }

    .checkbox-text .dashicons {
        font-size: 16px;
        width: 16px;
        height: 16px;
        color: #f97316;
    }

    .checkbox-hint {
        font-size: 12px;
        color: #9ca3af;
        margin-left: auto;
    }

    /* ── Generate CTA Button ── */
    .form-submit {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .generate-cta {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 14px 36px;
        background: linear-gradient(135deg, #f97316 0%, #c2410c 100%);
        color: #fff;
        border: none;
        border-radius: 12px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.25s ease;
        box-shadow: 0 4px 16px rgba(249, 115, 22, 0.35);
        position: relative;
        overflow: hidden;
    }

    .generate-cta::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.15) 0%, transparent 50%);
        opacity: 0;
        transition: opacity 0.25s ease;
    }

    .generate-cta:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(249, 115, 22, 0.45);
    }

    .generate-cta:hover::before {
        opacity: 1;
    }

    .generate-cta:active {
        transform: translateY(0);
    }

    .generate-cta:disabled {
        opacity: 0.7;
        cursor: not-allowed;
        transform: none;
        box-shadow: 0 4px 16px rgba(249, 115, 22, 0.2);
    }

    .cta-icon svg {
        display: block;
    }

    .generate-status {
        font-size: 14px;
        font-weight: 500;
    }

    .generate-status .spinner-inline {
        border-top-color: #f97316;
    }

    /* ── Tips Panel ── */
    .tips-panel {
        height: fit-content;
        position: sticky;
        top: 56px;
    }

    .tips-list {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .tip-item {
        display: flex;
        gap: 14px;
        align-items: flex-start;
    }

    .tip-number {
        width: 32px;
        height: 32px;
        background: linear-gradient(135deg, #eef0fd, #ddd6fe);
        color: #f97316;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 14px;
        flex-shrink: 0;
    }

    .tip-content strong {
        display: block;
        font-size: 14px;
        color: #1e293b;
        margin-bottom: 2px;
    }

    .tip-content p {
        margin: 0;
        font-size: 13px;
        color: #64748b;
        line-height: 1.5;
    }

    /* ── Preview Panel ── */
    .preview-panel {
        margin-top: 0;
    }

    .preview-panel .panel-header {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .preview-actions {
        display: flex;
        gap: 10px;
        margin-left: auto;
    }

    .preview-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.2s ease;
    }

    .preview-btn .dashicons {
        font-size: 16px;
        width: 16px;
        height: 16px;
    }

    .preview-btn-secondary {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
    }

    .preview-btn-secondary:hover {
        background: #e2e8f0;
        color: #1e293b;
    }

    .preview-btn-primary {
        background: linear-gradient(135deg, #f97316, #c2410c);
        color: #fff;
        border: none;
    }

    .preview-btn-primary:hover {
        box-shadow: 0 4px 12px rgba(249, 115, 22, 0.3);
        color: #fff;
    }

    .preview-frame-wrap {
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        overflow: hidden;
        height: 520px;
        background: #f9fafb;
    }

    .preview-frame-wrap iframe {
        width: 100%;
        height: 100%;
        border: none;
    }

    /* ── Responsive ── */
    @media (max-width: 782px) {
        .ugentic-generate-v2 {
            padding: 16px;
        }

        .generate-header {
            padding: 24px;
        }

        .header-text h1 {
            font-size: 22px;
        }

        .generate-panel {
            padding: 20px;
        }

        .template-grid {
            gap: 8px;
        }

        .template-chip {
            padding: 6px 12px;
            font-size: 12px;
        }

        .form-submit {
            flex-direction: column;
            align-items: stretch;
        }

        .generate-cta {
            justify-content: center;
        }

        .checkbox-hint {
            display: none;
        }
    }

    /* ═══════════════════════════════════════════════════════════════════
       DESIGN VISION — Image Upload Card (in Generate Page)
       ═══════════════════════════════════════════════════════════════════ */

    .dv-generate-section {
        margin-top: 4px !important;
    }

    /* OR Divider */
    .dv-or-divider {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 16px;
    }
    .dv-or-line {
        flex: 1;
        height: 1px;
        background: linear-gradient(90deg, transparent, #d0d0d0, transparent);
    }
    .dv-or-text {
        font-size: 12px;
        font-weight: 700;
        color: #999;
        letter-spacing: 1px;
        text-transform: uppercase;
    }

    /* Upload Card */
    .dv-upload-card {
        border: 2px dashed rgba(249, 115, 22, 0.25);
        border-radius: 16px;
        background: linear-gradient(145deg, rgba(249, 115, 22, 0.03) 0%, rgba(118, 75, 226, 0.04) 100%);
        transition: all 0.3s ease;
        overflow: hidden;
    }
    .dv-upload-card:hover,
    .dv-upload-card.drag-over {
        border-color: rgba(249, 115, 22, 0.5);
        background: linear-gradient(145deg, rgba(249, 115, 22, 0.07) 0%, rgba(118, 75, 226, 0.08) 100%);
        box-shadow: 0 0 30px rgba(249, 115, 22, 0.06);
    }

    .dv-upload-card-inner {
        display: flex;
        align-items: center;
        gap: 20px;
        padding: 24px 28px;
        cursor: pointer;
    }

    .dv-upload-icon {
        flex-shrink: 0;
        color: rgba(249, 115, 22, 0.45);
    }
    .dv-upload-card:hover .dv-upload-icon {
        color: rgba(249, 115, 22, 0.7);
    }

    .dv-upload-text h3 {
        font-size: 15px;
        font-weight: 600;
        color: #333;
        margin: 0 0 4px;
    }
    .dv-upload-text p {
        font-size: 13px;
        color: #888;
        margin: 0;
    }

    .dv-upload-actions {
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-shrink: 0;
    }

    .dv-gen-browse-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 18px;
        background: linear-gradient(135deg, #f97316, #764be2);
        color: #fff !important;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.25s;
        text-decoration: none !important;
        box-shadow: 0 3px 12px rgba(249, 115, 22, 0.25);
    }
    .dv-gen-browse-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 5px 18px rgba(249, 115, 22, 0.35);
    }
    .dv-gen-browse-btn .dashicons {
        font-size: 16px;
        width: 16px;
        height: 16px;
    }

    .dv-gen-paste-hint {
        font-size: 12px;
        color: #aaa;
    }
    .dv-gen-paste-hint kbd {
        background: #f0f0f0;
        border: 1px solid #ddd;
        border-radius: 3px;
        padding: 1px 5px;
        font-size: 11px;
        font-family: monospace;
    }

    /* Preview state */
    .dv-gen-preview {
        position: relative;
    }
    .dv-gen-preview img {
        width: 100%;
        max-height: 200px;
        object-fit: cover;
        display: block;
    }
    .dv-gen-preview-actions {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        background: linear-gradient(transparent, rgba(0,0,0,0.7));
    }
    .dv-gen-change-btn {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 6px 14px;
        background: rgba(255,255,255,0.2);
        backdrop-filter: blur(8px);
        color: #fff;
        border: 1px solid rgba(255,255,255,0.3);
        border-radius: 6px;
        font-size: 12px;
        cursor: pointer;
        transition: background 0.2s;
    }
    .dv-gen-change-btn:hover {
        background: rgba(255,255,255,0.35);
    }
    .dv-gen-change-btn .dashicons {
        font-size: 14px;
        width: 14px;
        height: 14px;
    }
    .dv-gen-go-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 20px;
        background: linear-gradient(135deg, #f97316, #764be2);
        color: #fff !important;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none !important;
        box-shadow: 0 3px 15px rgba(249, 115, 22, 0.35);
        transition: all 0.25s;
    }
    .dv-gen-go-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 5px 20px rgba(249, 115, 22, 0.45);
        color: #fff !important;
    }
    .dv-gen-go-btn .dashicons {
        font-size: 16px;
        width: 16px;
        height: 16px;
    }

    @media (max-width: 768px) {
        .dv-upload-card-inner {
            flex-direction: column;
            text-align: center;
            padding: 20px;
        }
        .dv-upload-actions {
            margin-left: 0;
            flex-direction: column;
        }
    }
</style>

<!-- Design Vision: Generate Page Integration JS -->
