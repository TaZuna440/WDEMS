<?php

namespace App\Support;

use DOMDocument;
use DOMNode;

class RegistrationRichText
{
    /** Allow only the formatting offered by the disclaimer editor. */
    public static function sanitize(string $html): string
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>', LIBXML_NONET);
            $body = $document->getElementsByTagName('body')->item(0);

            return $body ? self::render($body) : '';
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private static function render(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return htmlspecialchars($node->nodeValue ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return '';
        }
        $tag = strtolower($node->nodeName);
        if (in_array($tag, ['script', 'style', 'iframe', 'object', 'svg', 'math', 'template'], true)) {
            return '';
        }
        $content = '';
        foreach ($node->childNodes as $child) {
            $content .= self::render($child);
        }
        $tag = match ($tag) {
            'b' => 'strong', 'i' => 'em', default => $tag
        };
        if ($tag === 'br') {
            return '<br>';
        }

        return in_array($tag, ['p', 'h1', 'h2', 'strong', 'em'], true)
            ? "<$tag>$content</$tag>" : $content;
    }
}
