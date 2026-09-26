<?php

/**
 * SafeHtml — HTML Sanitizer and Formatter for Hazra EV Journal & News.
 *
 * Provides safe rendering of article body content using a strict allowlist
 * of semantic HTML elements and attributes, stripping unsafe scripts, iframes,
 * event handlers, and malicious protocols.
 */
class SafeHtml
{
    private const ALLOWED_TAGS = '<p><br><h2><h3><h4><h5><h6><strong><b><em><i><u><ul><ol><li><blockquote><a><img><hr><figure><figcaption><div><span><code><pre><mark>';

    private const ALLOWED_ATTRS = [
        'a'          => ['href', 'title', 'target', 'rel'],
        'img'        => ['src', 'alt', 'title', 'loading', 'width', 'height'],
        'div'        => ['class', 'id'],
        'span'       => ['class'],
        'blockquote' => ['class'],
        'figure'     => ['class'],
        'figcaption' => ['class'],
        'p'          => ['class'],
        'h2'         => ['id', 'class'],
        'h3'         => ['id', 'class'],
        'h4'         => ['id', 'class'],
    ];

    /**
     * Clean and format HTML content for public display.
     */
    public static function clean(string $raw): string
    {
        $text = trim($raw);
        if ($text === '') {
            return '';
        }

        // If content is plain text without HTML tags, wrap paragraphs
        if (!preg_match('/<[a-z][\s\S]*>/i', $text)) {
            $paragraphs = preg_split('/\n{2,}/', $text);
            $wrapped = array_map(function ($p) {
                return '<p>' . nl2br(htmlspecialchars(trim($p), ENT_QUOTES, 'UTF-8')) . '</p>';
            }, $paragraphs);
            return implode("\n", $wrapped);
        }

        // Remove dangerous tags completely including their inner text
        $cleaned = preg_replace('/<(script|style|iframe|object|embed|applet)[\s\S]*?<\/\1>/i', '', $text);

        // Strip tags outside the allowlist
        $cleaned = strip_tags($cleaned, self::ALLOWED_TAGS);

        // Strip dangerous attributes (javascript: urls, onerror, onclick, etc.)
        $cleaned = preg_replace_callback('/<([a-z0-9]+)([^>]*)>/i', function ($matches) {
            $tag = strtolower($matches[1]);
            $attrString = $matches[2];

            if (empty(trim($attrString))) {
                return "<{$tag}>";
            }

            $allowedForTag = self::ALLOWED_ATTRS[$tag] ?? [];
            if (empty($allowedForTag)) {
                return "<{$tag}>";
            }

            // Parse attributes
            preg_match_all('/([a-z0-9_-]+)\s*=\s*(["\'])(.*?)\2/i', $attrString, $attrMatches, PREG_SET_ORDER);
            $safeAttrs = [];

            foreach ($attrMatches as $attr) {
                $name = strtolower($attr[1]);
                $val  = $attr[3];

                if (!in_array($name, $allowedForTag, true)) {
                    continue;
                }

                // Check for javascript:, data:, or vbscript: links
                if (($name === 'href' || $name === 'src') && preg_match('/^\s*(javascript|data|vbscript):/i', $val)) {
                    continue;
                }

                if ($name === 'href' && str_starts_with($val, 'http')) {
                    $safeAttrs['target'] = 'target="_blank"';
                    $safeAttrs['rel'] = 'rel="noopener noreferrer"';
                }

                $safeAttrs[$name] = sprintf('%s="%s"', $name, htmlspecialchars($val, ENT_QUOTES, 'UTF-8'));
            }

            $attrStr = implode(' ', $safeAttrs);
            return $attrStr ? "<{$tag} {$attrStr}>" : "<{$tag}>";
        }, $cleaned);

        return $cleaned;
    }

    /**
     * Compute estimated reading time in minutes based on word count.
     */
    public static function readMinutes(string $content, int $default = 4): int
    {
        $plain = strip_tags($content);
        $words = str_word_count($plain);
        if ($words <= 0) {
            return $default;
        }

        $minutes = (int) ceil($words / 180);
        return max(1, $minutes);
    }
}
