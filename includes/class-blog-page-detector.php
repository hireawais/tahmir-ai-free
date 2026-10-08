<?php
/**
 * Deterministic blog page detector.
 *
 * Classifies imported/static HTML as a blog archive, blog single, non-blog, or
 * ambiguous. This detector intentionally abstains when evidence conflicts.
 *
 * @package Ugentic_Builder
 * @since 2.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ugentic_Blog_Page_Detector
{
    private const UPPER = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    private const LOWER = 'abcdefghijklmnopqrstuvwxyz';

    /**
     * Detect whether a page is a blog archive, single post, non-blog, or unknown.
     *
     * @param string $html       Page HTML, body HTML, or full document HTML.
     * @param string $page_title Human page title if known.
     * @param string $source     Source filename or URL if known.
     * @return array Structured detection result.
     */
    public static function detect($html, $page_title = '', $source = '')
    {
        $html = (string) $html;
        $page_title = trim((string) $page_title);
        $source = trim((string) $source);

        if (trim($html) === '') {
            return self::base_result('none', null, 0.0, [], 'Empty HTML cannot be classified.');
        }

        [$doc, $xpath] = self::load_dom($html);
        $schema = self::detect_schema($html);
        $meta = self::detect_meta($html);
        $body = self::detect_body_context($html);
        $url_context = self::detect_url_context($html, $source);
        $title_context = self::detect_title_context($page_title);
        $archive_items = $xpath ? self::find_archive_items($xpath) : self::empty_archive_items();
        $single_article = $xpath ? self::find_single_article($xpath, $archive_items) : self::empty_single_article();
        $page_signals = self::detect_page_signals($html, $xpath);

        $archive_score = 0.0;
        $single_score = 0.0;
        $archive_evidence = [];
        $single_evidence = [];

        if ($schema['archive']) {
            $archive_score += 5.0;
            $archive_evidence[] = 'schema:' . implode(',', $schema['archive_types']);
        }

        if ($schema['single']) {
            $single_score += 5.0;
            $single_evidence[] = 'schema:' . implode(',', $schema['single_types']);
        }

        if ($meta['og_article']) {
            $single_score += 4.0;
            $single_evidence[] = 'meta:og:type=article';
        }

        if ($body['archive']) {
            $archive_score += 2.0;
            $archive_evidence[] = 'body_class:archive';
        }

        if ($body['single']) {
            $single_score += 3.0;
            $single_evidence[] = 'body_class:single';
        }

        if ($url_context['archive']) {
            $archive_score += 2.0;
            $archive_evidence[] = 'url:archive';
        }

        if ($url_context['single']) {
            $single_score += 1.5;
            $single_evidence[] = 'url:single';
        }

        if ($title_context['archive']) {
            $archive_score += 2.0;
            $archive_evidence[] = 'title:archive';
        }

        if ($title_context['single']) {
            $single_score += 1.0;
            $single_evidence[] = 'title:single';
        }

        if ($archive_items['count'] >= 3) {
            $archive_score += 4.0;
            $archive_evidence[] = 'items:repeated_post_cards(' . $archive_items['count'] . ')';
        } elseif ($archive_items['count'] === 2) {
            $archive_score += 3.0;
            $archive_evidence[] = 'items:two_post_cards';
        } elseif ($archive_items['count'] === 1) {
            $archive_evidence[] = 'items:one_post_card';
        }

        if ($archive_items['has_blog_context']) {
            $archive_score += 1.5;
            $archive_evidence[] = 'items:blog_classes';
        }

        if ($archive_items['has_dates']) {
            $archive_score += 1.0;
            $archive_evidence[] = 'items:dates';
        }

        if ($archive_items['has_read_more']) {
            $archive_score += 1.0;
            $archive_evidence[] = 'items:read_more_links';
        }

        if ($page_signals['pagination']) {
            $archive_score += 2.0;
            $archive_evidence[] = 'page:pagination';
        }

        if ($page_signals['filters']) {
            $archive_score += 1.5;
            $archive_evidence[] = 'page:category_filters';
        }

        if ($single_article['primary_article']) {
            $single_score += 3.0;
            $single_evidence[] = 'article:single_primary';
        }

        if ($single_article['long_body']) {
            $single_score += 2.0;
            $single_evidence[] = 'article:long_body';
        }

        if ($single_article['explicit_single_context']) {
            $single_score += 3.0;
            $single_evidence[] = 'article:explicit_single_context';
        }

        if ($single_article['meta']) {
            $single_score += 2.0;
            $single_evidence[] = 'article:date_author_category';
        }

        if ($page_signals['comments']) {
            $single_score += 1.0;
            $single_evidence[] = 'page:comments';
        }

        $archive_is_proven = self::archive_is_proven($archive_score, $archive_items, $schema, $url_context, $title_context, $page_signals);
        $single_is_proven = self::single_is_proven($single_score, $single_article, $schema, $meta, $body, $url_context, $title_context);

        if ($archive_is_proven && $single_is_proven) {
            return self::base_result(
                'unknown',
                null,
                self::confidence(max($archive_score, $single_score), 10.0),
                array_merge($archive_evidence, $single_evidence),
                'Conflicting archive and single-post evidence; review required.',
                $archive_items
            );
        }

        if ($archive_is_proven) {
            return self::archive_result(
                $archive_score,
                $archive_evidence,
                $archive_items,
                $page_title,
                $url_context['url']
            );
        }

        if ($single_is_proven) {
            return self::single_result(
                $single_score,
                $single_evidence,
                $single_article,
                $page_title,
                $url_context['url']
            );
        }

        if ($archive_score >= 3.0 || $single_score >= 3.0) {
            return self::base_result(
                'unknown',
                null,
                self::confidence(max($archive_score, $single_score), 8.0),
                array_merge($archive_evidence, $single_evidence),
                'Blog-like signals were found, but not enough deterministic evidence to classify this page.',
                $archive_items
            );
        }

        return self::base_result(
            'none',
            null,
            0.0,
            array_merge($archive_evidence, $single_evidence),
            'No deterministic blog archive or single-post evidence found.',
            $archive_items
        );
    }

    private static function archive_is_proven($score, $items, $schema, $url_context, $title_context, $page_signals)
    {
        if ($items['count'] >= 2) {
            $has_context = $schema['archive']
                || $url_context['archive']
                || $title_context['archive']
                || $items['has_blog_context']
                || $items['has_dates']
                || $page_signals['pagination']
                || $page_signals['filters'];

            return $score >= 5.0 && $has_context;
        }

        if ($items['count'] === 1) {
            return $schema['archive'] && ($url_context['archive'] || $title_context['archive'] || $page_signals['pagination'] || $page_signals['filters']);
        }

        return $schema['archive'] && ($url_context['archive'] || $title_context['archive']);
    }

    private static function single_is_proven($score, $article, $schema, $meta, $body, $url_context, $title_context)
    {
        if (empty($article['primary_article'])) {
            return false;
        }

        $hard_schema = !empty(array_intersect($schema['single_types'] ?? [], ['BlogPosting', 'NewsArticle']));
        $generic_schema = !empty($schema['single']) && !$hard_schema;
        $has_real_post_meta = !empty($article['has_date']) || !empty($article['has_author']) || !empty($article['has_category']);
        $has_strong_post_meta = !empty($article['has_date']) || !empty($article['has_author']);
        $has_article_container = !empty($article['clear_article_context']);
        $has_explicit_single_context = !empty($article['explicit_single_context']);

        if (!empty($title_context['non_blog']) && !$hard_schema && !$meta['og_article'] && !$body['single']) {
            return false;
        }

        if ((!empty($url_context['non_blog']) || !empty($title_context['non_blog'])) && !$hard_schema && !$body['single'] && !$has_strong_post_meta) {
            return false;
        }

        if ($hard_schema || $meta['og_article'] || $body['single']) {
            return $score >= 6.0 && ($article['long_body'] || $has_real_post_meta || $has_article_container);
        }

        if ($url_context['single'] || $title_context['single']) {
            return $score >= 6.0 && ($article['long_body'] || $has_real_post_meta);
        }

        if ($generic_schema) {
            return $score >= 8.0 && $article['long_body'] && $has_real_post_meta && ($has_article_container || !empty($article['uses_article_tag']));
        }

        // Imported design templates often omit author/date metadata entirely.
        // A unique, substantial article carrying an explicit single-post class
        // is still deterministic and should be visually mappable as a single.
        if ($has_explicit_single_context) {
            return $score >= 7.0 && $article['long_body'] && !empty($article['uses_article_tag']);
        }

        return $score >= 7.0 && $article['long_body'] && $has_real_post_meta && ($has_article_container || !empty($article['uses_article_tag']));
    }

    private static function archive_result($score, $evidence, $items, $page_title, $url)
    {
        [$label, $singular] = self::labels_for_context($page_title, $url);
        $fields = self::fields_from_sample($items['sample'], $items['sample_xpath']);

        return [
            'content_type' => 'blog',
            'blog_role' => 'archive',
            'confidence' => self::confidence($score, 10.0),
            'evidence' => array_values(array_unique($evidence)),
            'reason' => 'Confirmed blog archive from deterministic structural evidence.',
            'item_count' => $items['count'],
            'label' => $label,
            'singular' => $singular,
            'icon' => 'dashicons-admin-post',
            'fields' => $fields,
            'selectors' => [
                'xpath' => $items['xpath'],
                'item_xpath' => $items['sample'] instanceof DOMElement ? $items['sample']->getNodePath() : '',
                'container_xpath' => $items['container_xpath'],
                'container_css' => $items['container_css'],
                'item_css' => $items['item_css'],
            ],
            // Backward compatibility: dynamic conversion expects an XPath here.
            'css_selector' => $items['xpath'],
            'item_selector' => $items['item_css'],
            'container_selector' => $items['container_css'],
            'field_mappings' => self::field_mappings_from_fields($fields),
            'ai_powered' => false,
        ];
    }

    private static function single_result($score, $evidence, $article, $page_title, $url)
    {
        return [
            'content_type' => 'blog',
            'blog_role' => 'single',
            'confidence' => self::confidence($score, 10.0),
            'evidence' => array_values(array_unique($evidence)),
            'reason' => 'Confirmed blog single page. It is not convertible as a repeating archive.',
            'item_count' => 1,
            'label' => 'Blog Post',
            'singular' => 'Blog Post',
            'icon' => 'dashicons-admin-post',
            'fields' => self::default_blog_fields(),
            'selectors' => [
                'xpath' => $article['xpath'],
                'item_xpath' => '',
                'container_xpath' => '',
                'container_css' => 'body',
                'item_css' => 'article',
            ],
            'css_selector' => $article['xpath'],
            'item_selector' => 'article',
            'ai_powered' => false,
        ];
    }

    private static function base_result($role, $content_type, $confidence, $evidence, $reason, $items = null)
    {
        $items = $items ?: self::empty_archive_items();

        return [
            'content_type' => $content_type,
            'blog_role' => $role,
            'confidence' => $confidence,
            'evidence' => array_values(array_unique($evidence)),
            'reason' => $reason,
            'item_count' => $items['count'] ?? 0,
            'label' => $role === 'unknown' ? 'Needs Review' : '',
            'singular' => '',
            'icon' => 'dashicons-admin-post',
            'fields' => [],
            'selectors' => [
                'xpath' => $items['xpath'] ?? '',
                'item_xpath' => '',
                'container_xpath' => $items['container_xpath'] ?? '',
                'container_css' => $items['container_css'] ?? '',
                'item_css' => $items['item_css'] ?? '',
            ],
            'css_selector' => $items['xpath'] ?? '',
            'item_selector' => $items['item_css'] ?? '',
            'ai_powered' => false,
        ];
    }

    private static function detect_schema($html)
    {
        $schema_text = '';
        if (preg_match_all('/<script\b[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches)) {
            $schema_text = html_entity_decode(implode("\n", $matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $archive_types = [];
        $single_types = [];

        foreach (['CollectionPage', 'ItemList'] as $type) {
            if (stripos($schema_text, '"' . $type . '"') !== false || stripos($schema_text, ':' . $type) !== false) {
                $archive_types[] = $type;
            }
        }

        if (preg_match('/"@type"\s*:\s*(?:\[[^\]]*)?"Blog"(?:[^\]]*\])?/i', $schema_text)) {
            $archive_types[] = 'Blog';
        }

        foreach (['BlogPosting', 'NewsArticle'] as $type) {
            if (stripos($schema_text, '"' . $type . '"') !== false || stripos($schema_text, ':' . $type) !== false) {
                $single_types[] = $type;
            }
        }

        if (empty($archive_types) && preg_match('/"@type"\s*:\s*(?:\[[^\]]*)?"Article"(?:[^\]]*\])?/i', $schema_text)) {
            $single_types[] = 'Article';
        }

        return [
            'archive' => !empty($archive_types),
            'single' => !empty($single_types),
            'archive_types' => array_values(array_unique($archive_types)),
            'single_types' => array_values(array_unique($single_types)),
        ];
    }

    private static function detect_meta($html)
    {
        return [
            'og_article' => (bool) preg_match('/<meta\b[^>]*(?:property|name)=["\']og:type["\'][^>]*content=["\']article["\']/i', $html)
                || (bool) preg_match('/<meta\b[^>]*content=["\']article["\'][^>]*(?:property|name)=["\']og:type["\']/i', $html),
        ];
    }

    private static function detect_body_context($html)
    {
        $body_class = '';
        if (preg_match('/<body\b[^>]*class=["\']([^"\']*)["\']/i', $html, $m)) {
            $body_class = strtolower($m[1]);
        }

        return [
            'archive' => (bool) preg_match('/\b(?:blog|archive|category|tag|author|date|home|post-type-archive)\b/i', $body_class),
            'single' => (bool) preg_match('/\b(?:single|single-post|post-template|blog-single)\b/i', $body_class),
        ];
    }

    private static function detect_url_context($html, $source)
    {
        $url = trim($source);

        if ($url === '') {
            if (preg_match('/<link\b[^>]*rel=["\']canonical["\'][^>]*href=["\']([^"\']+)["\']/i', $html, $m)) {
                $url = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } elseif (preg_match('/<meta\b[^>]*(?:property|name)=["\']og:url["\'][^>]*content=["\']([^"\']+)["\']/i', $html, $m)) {
                $url = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        $path = strtolower(trim(wp_parse_url($url, PHP_URL_PATH) ?: $url));
        $path = preg_replace('/\.html?$/i', '', $path);
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        $last = end($segments) ?: '';

        $archive = in_array($last, ['blog', 'blogs', 'news', 'articles', 'article', 'insights', 'resources', 'archive', 'category', 'tag'], true)
            || preg_match('/(?:^|\/)(blog|news|articles|insights|resources)(?:\/page\/\d+)?\/?$/i', $path)
            || preg_match('/(?:^|\/)(category|tag|author|archive)(?:\/|$)/i', $path);

        $single = preg_match('/(?:^|\/)(?:19|20)\d{2}\/(?:0?[1-9]|1[0-2])(?:\/|$)/', $path)
            || preg_match('/(?:single-post|blog-single|post-detail|article-detail)/i', $path)
            || (count($segments) >= 2
                && in_array($segments[0], ['blog', 'news', 'articles', 'insights', 'resources'], true)
                && !in_array($last, ['blog', 'blogs', 'news', 'articles', 'insights', 'resources', 'page'], true)
                && strlen($last) > 8);

        return [
            'url' => $url,
            'archive' => (bool) $archive,
            'single' => (bool) $single,
            'non_blog' => in_array($last, [
                'about',
                'about-us',
                'contact',
                'contact-us',
                'services',
                'service',
                'features',
                'portfolio',
                'work',
                'case-studies',
                'locations',
                'location',
                'careers',
                'privacy',
                'privacy-policy',
                'terms',
                'terms-of-service',
                'support',
                'faq',
                'faqs',
                'login',
                'register',
                'signup',
                'sign-up',
                'sign-in',
                'checkout',
                'cart',
                'shop',
            ], true) || (bool) preg_match('/^(?:(?:privacy-policy|terms-and-conditions|terms-of-service|contact-us|about-us)(?:-[a-z0-9]+)*)$/i', $last),
        ];
    }

    private static function detect_title_context($page_title)
    {
        $title = strtolower(trim($page_title));
        $title = preg_replace('/\s+[|:-]\s+.*$/', '', $title);

        $archive = (bool) preg_match('/^(blog|blogs|news|articles|insights|resources|latest posts|latest articles|our blog|our news)$/i', $title)
            || (bool) preg_match('/\b(blog|articles|news|insights|resources)\b/i', $title);

        $single = (bool) preg_match('/\b(single post|blog post|article detail|post detail)\b/i', $title);
        $non_blog = (bool) preg_match('/\b(?:privacy policy|terms and conditions|terms of service|contact us|about us)\b/i', $title);

        return [
            'archive' => $archive,
            'single' => $single,
            'non_blog' => $non_blog,
        ];
    }

    private static function detect_page_signals($html, $xpath)
    {
        $pagination = (bool) preg_match('/class=["\'][^"\']*(?:pagination|page-numbers|pager|paging-navigation|posts-navigation)[^"\']*["\']/i', $html);
        $filters = (bool) preg_match('/class=["\'][^"\']*(?:category-filter|cat-filter|blog-filter|post-filter|archive-filter|tags-filter)[^"\']*["\']/i', $html);
        $comments = (bool) preg_match('/\b(?:comments-area|comment-list|respond|leave a reply|post-comments)\b/i', $html);

        if ($xpath) {
            $pagination = $pagination
                || self::xpath_count($xpath, '//*[self::nav or self::div or self::ul][' . self::class_or_id_contains(['pagination', 'page-numbers', 'pager', 'paging-navigation', 'posts-navigation']) . ']') > 0
                || self::xpath_count($xpath, '//a[contains(concat(" ", normalize-space(@class), " "), " page-numbers ") or contains(@href, "/page/")]') > 0
                || self::xpath_count($xpath, '//*[contains(concat(" ", normalize-space(@class), " "), " nav-links ")][.//*[contains(concat(" ", normalize-space(@class), " "), " page-numbers ")]]') > 0;
            $filters = $filters || self::xpath_count($xpath, '//*[' . self::class_or_id_contains(['category-filter', 'cat-filter', 'blog-filter', 'post-filter', 'archive-filter', 'tags-filter']) . ']') > 0;
        }

        return [
            'pagination' => $pagination,
            'filters' => $filters,
            'comments' => $comments,
        ];
    }

    private static function find_archive_items($xpath)
    {
        $patterns = [
            [
                'name' => 'post_specific_classes',
                'xpath' => '//*[' . self::class_contains(['post-card', 'post-item', 'blog-card', 'blog-item', 'article-card', 'article-item', 'news-card', 'news-item', 'entry-card', 'entry-item']) . '][' . self::not_related_condition() . ']',
                'container_css' => 'body',
                'item_css' => '[class*="post-card"], [class*="post-item"], [class*="blog-card"], [class*="blog-item"], [class*="article-card"], [class*="article-item"], [class*="news-card"], [class*="news-item"], [class*="entry-card"], [class*="entry-item"]',
            ],
            [
                'name' => 'blog_container_items',
                'xpath' => '//*[' . self::class_or_id_contains(['blog', 'posts', 'articles', 'news', 'insights']) . ']//*[(self::article or self::li or self::div or self::section) and (' . self::class_contains(['card', 'item', 'post', 'article', 'entry', 'news']) . ')][' . self::not_related_condition() . ']',
                'container_css' => '[class*="blog"], [class*="posts"], [class*="articles"], [class*="news"], [class*="insights"]',
                'item_css' => 'article, li, [class*="card"], [class*="item"], [class*="post"], [class*="article"], [class*="entry"], [class*="news"]',
            ],
            [
                'name' => 'article_tags',
                'xpath' => '//article[' . self::not_related_condition() . ']',
                'container_css' => 'body',
                'item_css' => 'article',
            ],
            [
                'name' => 'post_lists',
                'xpath' => '//*[' . self::class_or_id_contains(['post-list', 'blog-list', 'article-list', 'news-list']) . ']//li[' . self::not_related_condition() . ']',
                'container_css' => '[class*="post-list"], [class*="blog-list"], [class*="article-list"], [class*="news-list"]',
                'item_css' => 'li',
            ],
        ];

        $best = self::empty_archive_items();

        foreach ($patterns as $pattern) {
            $nodes = $xpath->query($pattern['xpath']);
            if (!$nodes || $nodes->length === 0) {
                continue;
            }

            $filtered = [];
            $seen = [];
            foreach ($nodes as $node) {
                if (!($node instanceof DOMElement)) {
                    continue;
                }
                if (!self::is_quality_archive_item($node, $xpath)) {
                    continue;
                }

                $node_path = $node->getNodePath();
                if (isset($seen[$node_path])) {
                    continue;
                }
                $seen[$node_path] = true;

                if (self::has_selected_ancestor($node, $seen)) {
                    continue;
                }

                $filtered[] = $node;
            }

            if (count($filtered) > $best['count']) {
                $sample = $filtered[0] ?? null;
                $container = $sample && $sample->parentNode instanceof DOMElement ? $sample->parentNode : null;
                $best = [
                    'count' => count($filtered),
                    'xpath' => $pattern['xpath'],
                    'container_xpath' => $container ? $container->getNodePath() : '',
                    'container_css' => $pattern['container_css'],
                    'item_css' => $pattern['item_css'],
                    'sample' => $sample,
                    'sample_xpath' => $xpath,
                    'has_blog_context' => self::nodes_have_blog_context($filtered),
                    'has_dates' => self::nodes_have_dates($filtered, $xpath),
                    'has_read_more' => self::nodes_have_read_more($filtered, $xpath),
                ];
            }
        }

        // A single long article inside a blog-labelled main region is a common
        // imported single-post template, not a one-card archive.
        if (
            $best['count'] === 1
            && $best['sample'] instanceof DOMElement
            && self::is_explicit_single_article($best['sample'])
            && self::word_count(self::node_text($best['sample'])) >= 180
        ) {
            return self::empty_archive_items();
        }

        return $best;
    }

    private static function find_single_article($xpath, $archive_items = [])
    {
        $article_xpath = '//article[' . self::not_related_condition() . ']';
        $articles = $xpath->query($article_xpath);
        $quality_articles = [];

        if ($articles) {
            foreach ($articles as $article) {
                if (!($article instanceof DOMElement)) {
                    continue;
                }
                if (self::is_related_node($article) || self::is_low_value_region($article)) {
                    continue;
                }
                $quality_articles[] = $article;
            }
        }

        // A repeated set of quality articles is an archive signal, not one
        // giant single article. Falling back to <main> in that situation made
        // archive pages conflict with themselves and hid real single posts.
        if (count($quality_articles) > 1 || intval($archive_items['count'] ?? 0) >= 2) {
            return self::empty_single_article();
        }

        $primary = null;
        if (count($quality_articles) === 1) {
            $primary = $quality_articles[0];
        } else {
            $candidate_queries = [
                '//*[@itemtype and (contains(@itemtype, "BlogPosting") or contains(@itemtype, "NewsArticle") or contains(@itemtype, "/Article"))][' . self::not_related_condition() . ']',
                '//main//*[' . self::class_or_id_contains([
                    'single-post',
                    'post-detail',
                    'article-detail',
                    'article-body',
                    'post-body',
                    'entry-content',
                    'post-content',
                    'blog-content',
                ]) . '][' . self::not_related_condition() . ']',
                '//main[' . self::not_related_condition() . ']',
                '//*[@role="main"][' . self::not_related_condition() . ']',
            ];

            foreach ($candidate_queries as $query) {
                $nodes = $xpath->query($query);
                if (!$nodes || $nodes->length !== 1 || !$nodes->item(0) instanceof DOMElement) {
                    continue;
                }
                $candidate = $nodes->item(0);
                if (self::is_low_value_region($candidate) || self::contains_repeated_article_regions($candidate, $xpath)) {
                    continue;
                }
                $primary = $candidate;
                break;
            }
        }

        if (!$primary instanceof DOMElement) {
            return self::empty_single_article();
        }

        $text = self::node_text($primary);
        $word_count = self::word_count($text);
        $has_h1 = self::xpath_count($xpath, './/h1', $primary) > 0;
        $has_heading = $has_h1 || self::xpath_count($xpath, './/h2|.//h3', $primary) > 0;
        $meta_flags = self::node_article_meta_flags($primary, $xpath);
        $context_node = $primary;
        if (!$meta_flags['date'] && !$meta_flags['author'] && !$meta_flags['category']) {
            $main = $xpath->query('ancestor-or-self::main[1]', $primary)->item(0);
            if ($main instanceof DOMElement) {
                $context_node = $main;
                $meta_flags = self::node_article_meta_flags($main, $xpath);
            }
        }
        $has_meta = $meta_flags['date'] || $meta_flags['author'] || $meta_flags['category'];
        $context = strtolower(
            $primary->getAttribute('class') . ' '
            . $primary->getAttribute('id') . ' '
            . ($context_node instanceof DOMElement ? $context_node->getAttribute('class') . ' ' . $context_node->getAttribute('id') : '')
        );
        $clear_article_context = (bool) preg_match('/\b(?:post|blog|article|news|entry|single-post|post-detail|article-detail)\b/i', $context);
        $explicit_single_context = self::is_explicit_single_article($primary)
            || (bool) preg_match('/(?:^|[\s_-])(?:single[-_ ]?(?:post|article)|post[-_ ]?detail|article[-_ ]?detail|blog[-_ ]?single)(?:$|[\s_-])/i', $context);
        $global_h1_count = self::xpath_count($xpath, '//main//h1 | //*[@role="main"]//h1');
        $has_page_heading = $has_heading || $global_h1_count === 1;

        return [
            'primary_article' => $has_page_heading && $word_count >= 80,
            'long_body' => $word_count >= 180,
            'meta' => $has_meta,
            'has_date' => $meta_flags['date'],
            'has_author' => $meta_flags['author'],
            'has_category' => $meta_flags['category'],
            'uses_article_tag' => strtolower($primary->nodeName) === 'article',
            'clear_article_context' => $clear_article_context,
            'explicit_single_context' => $explicit_single_context,
            'xpath' => $primary->getNodePath(),
            'word_count' => $word_count,
        ];
    }

    /**
     * Reject containers that are actually post grids or related-content rails.
     */
    private static function contains_repeated_article_regions($node, $xpath)
    {
        if (self::xpath_count($xpath, './/article[' . self::not_related_condition() . ']', $node) >= 2) {
            return true;
        }

        $cards = $xpath->query(
            './/*[' . self::class_contains([
                'post-card',
                'post-item',
                'blog-card',
                'blog-item',
                'article-card',
                'article-item',
                'entry-card',
                'entry-item',
            ]) . ']',
            $node
        );

        return $cards && $cards->length >= 2;
    }

    private static function is_quality_archive_item($node, $xpath)
    {
        if (self::is_related_node($node) || self::is_low_value_region($node)) {
            return false;
        }

        $text = self::node_text($node);
        $text_len = strlen($text);
        $has_heading = self::xpath_count($xpath, './/h2|.//h3|.//h4|.//h5|.//h6', $node) > 0;
        $has_link = self::xpath_count($xpath, './/a[@href]', $node) > 0;
        $has_image = self::xpath_count($xpath, './/img[@src]', $node) > 0;
        $has_date = self::node_has_date($node, $xpath);

        if ($text_len < 20 && !$has_image) {
            return false;
        }

        return $has_heading || ($has_link && ($has_date || $has_image || $text_len >= 40));
    }

    private static function is_explicit_single_article($node)
    {
        if (!$node instanceof DOMElement) {
            return false;
        }

        $context = strtolower($node->getAttribute('class') . ' ' . $node->getAttribute('id'));
        return (bool) preg_match('/(?:^|[\s_-])(?:single[-_ ]?(?:post|article)|post[-_ ]?detail|article[-_ ]?detail|blog[-_ ]?single)(?:$|[\s_-])/i', $context);
    }

    private static function has_selected_ancestor($node, $selected_paths)
    {
        $parent = $node->parentNode;
        while ($parent instanceof DOMElement) {
            if (isset($selected_paths[$parent->getNodePath()])) {
                return true;
            }
            $parent = $parent->parentNode;
        }

        return false;
    }

    private static function nodes_have_blog_context($nodes)
    {
        foreach ($nodes as $node) {
            $context = strtolower($node->getAttribute('class') . ' ' . $node->getAttribute('id'));
            if (preg_match('/\b(?:post|blog|news|entry)\b/i', $context)) {
                return true;
            }
        }
        return false;
    }

    private static function nodes_have_dates($nodes, $xpath)
    {
        $with_dates = 0;
        foreach ($nodes as $node) {
            if (self::node_has_date($node, $xpath)) {
                $with_dates++;
            }
        }

        return $with_dates >= min(2, count($nodes));
    }

    private static function nodes_have_read_more($nodes, $xpath)
    {
        $with_read_more = 0;
        foreach ($nodes as $node) {
            $links = $xpath->query('.//a', $node);
            if (!$links) {
                continue;
            }
            foreach ($links as $link) {
                if (preg_match('/\b(?:read\s+more|continue\s+reading|view\s+post|view\s+article)\b/i', trim($link->textContent))) {
                    $with_read_more++;
                    break;
                }
            }
        }

        return $with_read_more >= min(2, count($nodes));
    }

    private static function node_has_date($node, $xpath)
    {
        return self::xpath_count($xpath, './/time', $node) > 0
            || self::xpath_count($xpath, './/*[' . self::class_contains(['date', 'posted', 'published']) . ']', $node) > 0;
    }

    private static function node_has_article_meta($node, $xpath)
    {
        $flags = self::node_article_meta_flags($node, $xpath);

        return $flags['date'] || $flags['author'] || $flags['category'];
    }

    private static function node_article_meta_flags($node, $xpath)
    {
        $date = self::node_has_date($node, $xpath);
        $author = self::xpath_count($xpath, './/*[' . self::class_contains(['author', 'byline', 'writer']) . ']', $node) > 0;
        $category = self::xpath_count($xpath, './/a[contains(@rel, "category") or contains(@rel, "tag") or contains(' . self::lc_expr('@href') . ', "/category/") or contains(' . self::lc_expr('@href') . ', "/tag/") or ' . self::class_contains(['category', 'cat-', 'post-category', 'post-tag']) . ']', $node) > 0;

        return [
            'date' => $date,
            'author' => $author,
            'category' => $category,
        ];
    }

    private static function is_related_node($node)
    {
        while ($node instanceof DOMElement) {
            $context = strtolower($node->getAttribute('class') . ' ' . $node->getAttribute('id') . ' ' . $node->nodeName);
            if (preg_match('/\b(?:related|recent|popular|recommended|more-posts|more_posts|you-may|suggested|sidebar|widget)\b/i', $context)) {
                return true;
            }
            $node = $node->parentNode;
        }

        return false;
    }

    private static function is_low_value_region($node)
    {
        $context = strtolower($node->getAttribute('class') . ' ' . $node->getAttribute('id') . ' ' . $node->nodeName);

        return (bool) preg_match('/\b(?:nav|navbar|menu|header|footer|breadcrumb|modal|popup|testimonial|review|service|team|portfolio|product|gallery)\b/i', $context);
    }

    private static function fields_from_sample($sample, $xpath)
    {
        if (!$sample instanceof DOMElement || !$xpath instanceof DOMXPath) {
            return self::default_blog_fields();
        }

        $fields = [];

        if (self::xpath_count($xpath, './/h1|.//h2|.//h3|.//h4|.//h5|.//h6', $sample) > 0) {
            $fields[] = ['key' => 'title', 'label' => 'Title', 'type' => 'text'];
        }

        if (self::xpath_count($xpath, './/p', $sample) > 0) {
            $fields[] = ['key' => 'description', 'label' => 'Description', 'type' => 'textarea'];
        }

        if (self::xpath_count($xpath, './/img[@src]', $sample) > 0) {
            $fields[] = ['key' => 'image', 'label' => 'Image', 'type' => 'image'];
        }

        if (self::node_has_date($sample, $xpath)) {
            $fields[] = ['key' => 'date', 'label' => 'Date', 'type' => 'date'];
        }

        if (self::xpath_count($xpath, './/*[' . self::class_contains(['author', 'byline', 'writer']) . ']', $sample) > 0) {
            $fields[] = ['key' => 'author', 'label' => 'Author', 'type' => 'text'];
        }

        if (self::xpath_count($xpath, './/*[' . self::class_contains(['category', 'cat-', 'badge']) . ']', $sample) > 0) {
            $fields[] = ['key' => 'category', 'label' => 'Category', 'type' => 'text'];
        }

        if (self::xpath_count($xpath, './/a[@href]', $sample) > 0) {
            $fields[] = ['key' => 'link_url', 'label' => 'Link URL', 'type' => 'url'];
            $fields[] = ['key' => 'link_text', 'label' => 'Link Text', 'type' => 'text'];
        }

        return !empty($fields) ? $fields : self::default_blog_fields();
    }

    private static function field_mappings_from_fields($fields)
    {
        $selector_map = [
            'title' => 'h1, h2, h3, h4, h5, h6',
            'description' => 'p',
            'image' => 'img',
            'date' => 'time, [class*="date"], [class*="posted"], [class*="published"], [class*="meta"]',
            'author' => '[class*="author"], [class*="byline"], [class*="writer"]',
            'category' => '[class*="category"], [class*="cat-"], [class*="badge"]',
            'link_url' => 'a[href]',
            'link_text' => 'a[href]',
        ];

        $mappings = [];
        foreach ($fields as $field) {
            $key = $field['key'] ?? '';
            $mappings[] = [
                'field_type' => $key,
                'css_selector' => $selector_map[$key] ?? '',
                'sample_value' => '',
                'confidence' => 0.9,
            ];
        }

        return $mappings;
    }

    private static function default_blog_fields()
    {
        return [
            ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['key' => 'description', 'label' => 'Description', 'type' => 'textarea'],
            ['key' => 'image', 'label' => 'Image', 'type' => 'image'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'author', 'label' => 'Author', 'type' => 'text'],
            ['key' => 'link_url', 'label' => 'Link URL', 'type' => 'url'],
            ['key' => 'link_text', 'label' => 'Link Text', 'type' => 'text'],
        ];
    }

    private static function labels_for_context($page_title, $url)
    {
        $haystack = strtolower($page_title . ' ' . $url);
        if (preg_match('/\bnews\b/', $haystack)) {
            return ['News Articles', 'News Article'];
        }
        if (preg_match('/\binsights\b/', $haystack)) {
            return ['Insights', 'Insight'];
        }
        if (preg_match('/\bresources\b/', $haystack)) {
            return ['Resources', 'Resource'];
        }
        if (preg_match('/\barticles\b/', $haystack)) {
            return ['Articles', 'Article'];
        }

        return ['Blog Posts', 'Blog Post'];
    }

    private static function load_dom($html)
    {
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $loaded = $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        if (!$loaded) {
            return [null, null];
        }

        return [$doc, new DOMXPath($doc)];
    }

    private static function xpath_count($xpath, $query, $context = null)
    {
        $nodes = $context ? $xpath->query($query, $context) : $xpath->query($query);
        return $nodes ? $nodes->length : 0;
    }

    private static function node_text($node)
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode($node->textContent ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private static function word_count($text)
    {
        $text = trim((string) $text);
        if ($text === '') {
            return 0;
        }

        $words = preg_split('/[\s\p{Z}\p{P}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        return is_array($words) ? count($words) : str_word_count($text);
    }

    private static function class_contains($terms)
    {
        return self::contains_any(self::lc_expr('@class'), $terms);
    }

    private static function class_or_id_contains($terms)
    {
        return self::contains_any(self::lc_expr('concat(" ", normalize-space(@class), " ", normalize-space(@id), " ")'), $terms);
    }

    private static function not_related_condition()
    {
        return 'not(ancestor::*[' . self::class_or_id_contains(['related', 'recent', 'popular', 'recommended', 'more-posts', 'more_posts', 'you-may', 'suggested', 'sidebar', 'widget']) . '])';
    }

    private static function contains_any($expr, $terms)
    {
        $parts = [];
        foreach ($terms as $term) {
            $parts[] = 'contains(' . $expr . ', "' . strtolower($term) . '")';
        }

        return implode(' or ', $parts);
    }

    private static function lc_expr($expr)
    {
        return 'translate(' . $expr . ', "' . self::UPPER . '", "' . self::LOWER . '")';
    }

    private static function confidence($score, $scale)
    {
        return round(max(0.0, min(1.0, $score / $scale)), 2);
    }

    private static function empty_archive_items()
    {
        return [
            'count' => 0,
            'xpath' => '',
            'container_xpath' => '',
            'container_css' => '',
            'item_css' => '',
            'sample' => null,
            'sample_xpath' => null,
            'has_blog_context' => false,
            'has_dates' => false,
            'has_read_more' => false,
        ];
    }

    private static function empty_single_article()
    {
        return [
            'primary_article' => false,
            'long_body' => false,
            'meta' => false,
            'has_date' => false,
            'has_author' => false,
            'has_category' => false,
            'uses_article_tag' => false,
            'clear_article_context' => false,
            'explicit_single_context' => false,
            'xpath' => '',
            'word_count' => 0,
        ];
    }
}
