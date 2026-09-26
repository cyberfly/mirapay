<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

class HtmlSanitizer
{
    /** @var array<string, list<string>> */
    protected const ALLOWED = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
        'h2' => [], 'h3' => [], 'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [], 'hr' => [],
        'code' => [], 'pre' => [], 'a' => ['href'],
    ];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?><body>'.$html.'</body>', LIBXML_HTML_NODEFDTD | LIBXML_NOERROR);
        libxml_clear_errors();

        $body = $document->getElementsByTagName('body')->item(0);

        if ($body === null) {
            return '';
        }

        static::sanitizeChildren($body);

        $output = '';
        foreach ($body->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    protected static function sanitizeChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if (! $node instanceof DOMElement) {
                if ($node->nodeType !== XML_TEXT_NODE) {
                    $parent->removeChild($node);
                }

                continue;
            }

            $tag = strtolower($node->tagName);

            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'noscript', 'template'], true)) {
                $parent->removeChild($node);

                continue;
            }

            static::sanitizeChildren($node);

            if (! array_key_exists($tag, static::ALLOWED)) {
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);

                continue;
            }

            static::sanitizeAttributes($node, static::ALLOWED[$tag]);
        }
    }

    /**
     * @param  list<string>  $allowed
     */
    protected static function sanitizeAttributes(DOMElement $element, array $allowed): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            if (! in_array($attribute->name, $allowed, true)) {
                $element->removeAttribute($attribute->name);
            }
        }

        if (strtolower($element->tagName) !== 'a') {
            return;
        }

        $href = trim($element->getAttribute('href'));

        if (! preg_match('/^(https?:\/\/|mailto:|\/(?!\/)|#)/i', $href)) {
            $element->removeAttribute('href');

            return;
        }

        $element->setAttribute('rel', 'noopener noreferrer nofollow');
    }
}
