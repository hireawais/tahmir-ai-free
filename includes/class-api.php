<?php
/**
 * Google Gemini API Integration for Tahmir AI
 * Uses direct REST API for access to all models including Gemini 3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class Ugentic_API
{

    /**
     * Gemini API base URL
     */
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta';

    /**
     * Anthropic API base URL
     */
    private const ANTHROPIC_API_BASE = 'https://api.anthropic.com/v1';

    /**
     * OpenAI API base URL
     */
    private const OPENAI_API_BASE = 'https://api.openai.com/v1';

    /**
     * Anthropic API version header
     */
    private const ANTHROPIC_VERSION = '2023-06-01';

    /**
     * Available Gemini models with their capabilities
     */
    public const AVAILABLE_MODELS = [
        // Gemini 3.1 Models (Preview - Feb 2026)
        'gemini-3.1-pro-preview' => [
            'name' => 'Gemini 3.1 Pro Preview',
            'description' => 'Most advanced — enhanced reasoning & agentic workflows',
            'tier' => 'preview',
            'provider' => 'gemini',
        ],
        // Gemini 3 Models (Preview - Dec 2025)
        'gemini-3-pro-preview' => [
            'name' => 'Gemini 3 Pro Preview',
            'description' => 'Advanced reasoning & problem-solving',
            'tier' => 'preview',
            'provider' => 'gemini',
        ],
        'gemini-3-flash-preview' => [
            'name' => 'Gemini 3 Flash Preview',
            'description' => 'Fast frontier-class performance',
            'tier' => 'preview',
            'provider' => 'gemini',
        ],
        'gemini-3-pro-image-preview' => [
            'name' => 'Gemini 3 Pro Image',
            'description' => 'Native image generation & editing (Nano Banana Pro)',
            'tier' => 'preview',
            'provider' => 'gemini',
        ],
        // Gemini 2.5 Models (Latest Stable - June 2025)
        'gemini-2.5-pro' => [
            'name' => 'Gemini 2.5 Pro',
            'description' => 'Most capable stable - complex reasoning',
            'tier' => 'stable',
            'provider' => 'gemini',
        ],
        'gemini-2.5-flash' => [
            'name' => 'Gemini 2.5 Flash',
            'description' => 'Fast + intelligent (Recommended)',
            'tier' => 'stable',
            'provider' => 'gemini',
        ],
        'gemini-2.5-flash-lite' => [
            'name' => 'Gemini 2.5 Flash Lite',
            'description' => 'Ultra-fast, cost-effective',
            'tier' => 'stable',
            'provider' => 'gemini',
        ],
        // Gemini 2.0 Models (Stable)
        'gemini-2.0-flash' => [
            'name' => 'Gemini 2.0 Flash',
            'description' => 'Reliable general-purpose',
            'tier' => 'stable',
            'provider' => 'gemini',
        ],
        'gemini-2.0-flash-lite' => [
            'name' => 'Gemini 2.0 Flash Lite',
            'description' => 'Fastest, budget-friendly',
            'tier' => 'stable',
            'provider' => 'gemini',
        ],
        // Gemini 1.5 Models (Legacy)
        'gemini-1.5-pro' => [
            'name' => 'Gemini 1.5 Pro',
            'description' => 'Previous generation, stable',
            'tier' => 'legacy',
            'provider' => 'gemini',
        ],
        'gemini-1.5-flash' => [
            'name' => 'Gemini 1.5 Flash',
            'description' => 'Previous generation, fast',
            'tier' => 'legacy',
            'provider' => 'gemini',
        ],
    ];

    /**
     * Available Anthropic (Claude) models
     */
    public const ANTHROPIC_MODELS = [
        // Claude 4.6 Models (Feb 2026)
        'claude-opus-4-6-20260205' => [
            'name' => 'Claude Opus 4.6',
            'description' => 'Most intelligent — advanced coding & agentic tasks (1M context)',
            'tier' => 'stable',
            'provider' => 'anthropic',
        ],
        'claude-sonnet-4-6-20260217' => [
            'name' => 'Claude Sonnet 4.6',
            'description' => 'Excellent coding & accuracy — fast (1M context)',
            'tier' => 'stable',
            'provider' => 'anthropic',
        ],
        // Claude 4.5 Models (Oct 2025)
        'claude-haiku-4-5-20251015' => [
            'name' => 'Claude Haiku 4.5',
            'description' => 'Fast & affordable — great for quick tasks',
            'tier' => 'stable',
            'provider' => 'anthropic',
        ],
        // Claude 4 Models
        'claude-sonnet-4-20250514' => [
            'name' => 'Claude Sonnet 4',
            'description' => 'Strong reasoning & coding',
            'tier' => 'stable',
            'provider' => 'anthropic',
        ],
        'claude-3-7-sonnet-20250219' => [
            'name' => 'Claude 3.7 Sonnet',
            'description' => 'Excellent reasoning, fast',
            'tier' => 'legacy',
            'provider' => 'anthropic',
        ],
        'claude-3-5-sonnet-20241022' => [
            'name' => 'Claude 3.5 Sonnet',
            'description' => 'Balanced intelligence & speed',
            'tier' => 'legacy',
            'provider' => 'anthropic',
        ],
        'claude-3-5-haiku-20241022' => [
            'name' => 'Claude 3.5 Haiku',
            'description' => 'Fastest legacy Claude',
            'tier' => 'legacy',
            'provider' => 'anthropic',
        ],
    ];

    /**
     * Task types for per-task model selection
     */
    public const TASK_TYPES = [
        'content_generation' => 'Content Generation (sitemap, page copy, discovery)',
        'page_design' => 'Page Design (HTML generation)',
        'content_editing' => 'Content Editing (inline editor)',
        'ai_clone_forms' => 'AI-assisted Import (form fields and flow)',
        'image_generation' => 'Image Generation (Gemini only)',
    ];

    /**
     * HTTP client
     */
    private $client;

    /**
     * Gemini API key
     */
    private $api_key;

    /**
     * Anthropic API key
     */
    private $anthropic_key;

    /**
     * Constructor
     */public function __construct() {}

    /**
     * Check if API is configured.
     * In licensed mode, checks against the active license.
     * In dev mode, checks local API keys.
     */public function is_configured($provider = 'gemini') { return false; }

    /**
     * Check if running in development mode (local API keys).
     */

    /** Local selection is explicit and never silently falls back to portal credentials. */

    /**
     * Get decrypted API key
     */

    /**
     * Get decrypted Anthropic API key
     */

    /**
     * Get decrypted OpenAI API key from the portal/agency provider first,
     * then local settings/constants as fallbacks.
     */

    /**
     * Get a model choice from Tahmir Portal when the upgraded portal is active
     * on the same WordPress install.
     */

    /**
     * Infer provider from known and dynamically-fetched model IDs.
     */

    /**
     * Get the OpenAI text model used to plan image prompts.
     */public function get_openai_text_model() { return ''; }

    /**
     * Get the OpenAI image model used to render page mockup images.
     */public function get_openai_image_model() { return ''; }

    /**
     * Encrypt API key for storage
     */

    /** Decrypt authenticated secrets, retaining compatibility with existing CBC keys. */

    /**
     * Get masked API key for display
     */public function get_masked_api_key() { return ''; }

    /**
     * Get encryption key from WordPress salts
     */

    /**
     * Make API request to Gemini.
     * In licensed mode, routes through the account server proxy.
     * In dev mode, calls Gemini API directly with local keys.
     */
    /**
     * Log API usage from a Gemini response to the database.
     *
     * @param string $model  The model name used.
     * @param array  $data   Decoded JSON response from the Gemini API.
     * @param array  $config Generation config (may contain _action_type hint).
     */

    /**
     * Make API request to Gemini with Google Search grounding.
     * Uses the `tools` parameter with `google_search` for live web context.
     * Requires gemini-2.0-flash or later.
     */

    /**
     * Get validated model (auto-fix invalid model names)
     */

    /**
     * Get the model and provider for a specific task type.
     * In licensed mode, reads the model config from the account server.
     * In dev mode, reads from local settings.
     *
     * @param string $task One of: content_generation, page_design, content_editing, image_generation
     * @return array ['provider' => 'gemini'|'anthropic', 'model' => string]
     */

    /**
     * Make API request to Anthropic (Claude).
     * In licensed mode, routes through the account server proxy.
     * In dev mode, calls Anthropic API directly.
     *
     * @param string $model          Anthropic model name
     * @param string $user_prompt    User prompt text
     * @param string $system         System instruction
     * @param array  $config         Generation config overrides
     * @return array ['success' => bool, 'content' => string, ...]
     */

    /**
     * Unified generate call that routes to the correct provider based on task
     *
     * @param string $prompt            User prompt
     * @param string $system_instruction System instruction
     * @param string $task              Task type for model routing
     * @param array  $gen_config        Generation config overrides
     * @return array ['success' => bool, 'content' => string, ...]
     */

    /**
     * Unified multimodal generation call for tasks that need one or more images.
     *
     * @param array  $parts             Gemini-style parts: inlineData / inline_data and text.
     * @param string $system_instruction System instruction.
     * @param string $task              Task type for model routing.
     * @param array  $gen_config        Generation config overrides.
     * @return array ['success' => bool, 'content' => string, ...]
     */

    /**
     * Make a text request to OpenAI. Used for ChatGPT/GPT prompt planning.
     *
     * @param string $prompt User prompt.
     * @param string $system System instruction.
     * @param array  $config model, temperature, max_tokens, timeout.
     * @return array ['success' => bool, 'content' => string, 'model' => string]
     */

    /**
     * Generate an image with OpenAI. If reference/input images are provided,
     * use the image edit endpoint so those exact visuals can guide the result.
     *
     * @param string $prompt
     * @param array  $reference_images Each item: base64, mime_type, title.
     * @param array  $options model, size, quality, timeout.
     * @return array ['success', 'image_data', 'mime_type', 'image_url', 'model']
     */

    /**
     * Generate multiple OpenAI images concurrently. Used by the Design Concepts
     * stage so concept thumbnails are rendered as a batch instead of one by one.
     *
     * @param array $prompts Prompt strings.
     * @param array $reference_images Optional image context.
     * @param array $options model, size, quality, timeout.
     * @return array ['success' => bool, 'images' => array, 'errors' => array, 'model' => string]
     */

    /**
     * OpenAI image edit request with exact input images.
     */

    /**
     * Make a multimodal (image + text) request to the AI provider.
     *
     * Constructs the proper inlineData + text parts payload and routes through
     * the appropriate provider (Gemini direct, Gemini proxy, or Anthropic).
     *
     * @param string $image_base64       Raw base64 image data (without data: prefix).
     * @param string $mime_type          Image MIME type (image/png, image/jpeg, etc.).
     * @param string $text_prompt        Text prompt to accompany the image.
     * @param string $system_instruction System instruction for the model.
     * @param array  $gen_config         Generation config overrides.
     * @return array ['success' => bool, 'content' => string, 'model' => string]
     */

    /**
     * Make a multimodal request to Anthropic (images + text).
     *
     * @param string $model    Model name.
     * @param array  $content  Anthropic content array with image + text blocks.
     * @param string $system   System instruction.
     * @param array  $config   Generation config.
     * @return array
     */

    /**
     * Get all available models (both providers combined)
     */

    /**
     * Get masked Anthropic API key for display
     */public function get_masked_anthropic_key() { return ''; }

    /**
     * Test API connection
     */

    /**
     * List available models from API
     */

    /**
     * General content generation
     */

    /**
     * Generate a complete HTML page — Design-Intelligent Version
     *
     * Mirrors the section generator's intelligence: industry blueprints,
     * HTML exemplars, section variety rules, Design DNA injection, and
     * live design research.
     *
     * @param string $prompt       User's page description.
     * @param string $design_style Optional design style (minimal, bold, editorial, luxury, tech, creative-agency).
     * @param string $industry     Optional industry key (saas, ecommerce, finance, healthcare, agency, restaurant, etc.).
     * @param string $page_type    Optional page type key (homepage, about, services, pricing, contact, faq, etc.).
     * @param array  $site_context Optional context from existing site ['css' => '', 'fonts' => '', 'colors' => '', 'dna' => []].
     */

    /**
     * Generate a page that matches the style of an existing imported page.
     * Enhanced with Design DNA extraction, Design Intelligence, and
     * industry/page-type awareness.
     *
     * @param string $prompt           User's page description.
     * @param int    $reference_page_id Tahmir AI page ID to clone styles from.
     * @param string $industry         Optional industry key.
     * @param string $page_type        Optional page type key.
     * @param string $design_style     Optional design style key.
     * @return array ['success' => bool, 'content' => string, ...]
     */

    /**
     * Auto-detect industry from user's prompt.
     *
     * @param string $prompt
     * @return string Industry key or empty string
     */

    /**
     * Auto-detect page type from user's prompt.
     *
     * @param string $prompt
     * @return string Page type key or 'homepage'
     */

    /**
     * Edit existing HTML content
     *
     * @param string $html            HTML content to edit
     * @param string $prompt          User's edit request
     * @param string $element_type    Type of element being edited
     * @param string $reference_image Optional base64 data URI of a user-uploaded reference image
     */

    /**
     * Add content to an element (e.g., add pricing tier, list item)
     */

    /**
     * Analyze element type
     */

    /**
     * Get smart suggestions for an element
     */

    /**
     * Generate or edit an image using Gemini's image generation model
     * Uses Gemini 3 Pro Image (Nano Banana Pro) for native image output
     *
     * @param string $prompt         User's description of the desired image changes
     * @param string $reference_image Optional base64 data URI of the current image (data:image/...;base64,...)
     * @return array                  ['success' => bool, 'image_data' => base64 string, 'mime_type' => string]
     */

    /**
     * Improve content quality
     */

    /**
     * Analyze HTML for repeating child patterns and generate a preservation hint.
     * This helps the AI understand how many sibling elements exist so it doesn't
     * accidentally remove content when making small edits.
     *
     * @param string $html The HTML to analyze
     * @return string A hint string to append to the user prompt, or empty string
     */

    /**
     * Network exceptions can echo request URLs. Redact credentials before an
     * error reaches logs, importer warnings, AJAX, or the browser.
     */
}
