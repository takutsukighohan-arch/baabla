<?php

/**
 * Decode a JSON value that is expected to contain an array.
 * Existing arrays are returned unchanged; invalid JSON becomes an empty array.
 */
function textbookJsonArray($value) {
    if (is_array($value)) {
        return $value;
    }

    $decoded = json_decode((string)$value, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Normalize common database boolean values.
 */
function textbookDatabaseBoolean($value) {
    return $value === true || $value === 1 ||
        in_array(strtolower(trim((string)$value)), ['1', 't', 'true', 'yes', 'on'], true);
}

/**
 * Check whether a URL is safe for an href or src attribute.
 * Allows http(s), mailto (links only), and ordinary same-site relative paths.
 */
function textbookIsSafeContentUrl($url, $allowMailto = false) {
    $url = trim((string)$url);

    if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url)) {
        return false;
    }

    // Never allow protocol-relative URLs, backslashes, or executable/data schemes.
    if (strncmp($url, '//', 2) === 0 || strpos($url, '\\') !== false) {
        return false;
    }

    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));

    if ($scheme !== '') {
        $allowed = $allowMailto ? ['http', 'https', 'mailto'] : ['http', 'https'];
        return in_array($scheme, $allowed, true);
    }

    // Relative URLs are permitted, but reject path traversal.
    $path = (string)parse_url($url, PHP_URL_PATH);
    foreach (explode('/', $path) as $segment) {
        if ($segment === '..') {
            return false;
        }
    }

    return true;
}

/**
 * Sanitize textbook rich-text HTML while preserving useful document structure.
 *
 * Safe structural tags (headings, paragraphs, lists, tables, figures, etc.) are
 * retained so the textbook viewer can style the content as printed book pages.
 * Scripts, embedded documents, forms, SVG, and other active content are removed.
 * Inline styles and event-handler attributes are never copied.
 */
function textbookSafeHtml($html) {
    if (!class_exists('DOMDocument')) {
        // Do not return unsanitized HTML if the DOM extension is unavailable.
        return htmlspecialchars((string)$html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    $previousErrorSetting = libxml_use_internal_errors(true);

    $source = new DOMDocument('1.0', 'UTF-8');
    $sourceHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' .
        '<div id="textbook-content">' . (string)$html . '</div>' .
        '</body></html>';

    $loaded = $source->loadHTML($sourceHtml, LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previousErrorSetting);

    if (!$loaded) {
        return '';
    }

    $sourceRoot = $source->getElementById('textbook-content');
    if (!$sourceRoot) {
        $roots = $source->getElementsByTagName('div');
        $sourceRoot = $roots->item(0);
    }

    if (!$sourceRoot) {
        return '';
    }

    $clean = new DOMDocument('1.0', 'UTF-8');
    $cleanRoot = $clean->createElement('div');
    $clean->appendChild($cleanRoot);

    $allowedTags = [
        'div', 'span',
        'p', 'br', 'hr',
        'strong', 'b', 'em', 'i', 'u', 's', 'del', 'mark',
        'small', 'sub', 'sup',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li',
        'blockquote',
        'a',
        'figure', 'figcaption',
        'img',
        'table', 'caption', 'thead', 'tbody', 'tfoot',
        'tr', 'th', 'td',
        'dl', 'dt', 'dd',
        'pre', 'code'
    ];

    $discardTags = [
        'script', 'style', 'iframe', 'object', 'embed',
        'svg', 'math', 'form', 'input', 'button', 'textarea',
        'select', 'option', 'video', 'audio', 'source',
        'canvas', 'template', 'noscript'
    ];

    $copyChildren = function ($parent, $target) use (
        &$copyChildren,
        $clean,
        $allowedTags,
        $discardTags
    ) {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMText) {
                $target->appendChild($clean->createTextNode($child->nodeValue));
                continue;
            }

            if (!($child instanceof DOMElement)) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, $discardTags, true)) {
                continue;
            }

            // Unknown formatting wrappers are unwrapped, retaining safe text/children.
            if (!in_array($tag, $allowedTags, true)) {
                $copyChildren($child, $target);
                continue;
            }

            $element = $clean->createElement($tag);

            if ($tag === 'a') {
                $href = trim($child->getAttribute('href'));
                if (textbookIsSafeContentUrl($href, true)) {
                    $element->setAttribute('href', $href);
                    $element->setAttribute('rel', 'noopener noreferrer');
                }
                $title = trim($child->getAttribute('title'));
                if ($title !== '') {
                    $element->setAttribute('title', $title);
                }
            }

            if ($tag === 'img') {
                $src = trim($child->getAttribute('src'));

                if (!textbookIsSafeContentUrl($src, false)) {
                    // Drop an image with an unsafe or missing source.
                    continue;
                }

                $element->setAttribute('src', $src);

                foreach (['alt', 'title'] as $attribute) {
                    $value = trim($child->getAttribute($attribute));
                    if ($value !== '') {
                        $element->setAttribute($attribute, $value);
                    }
                }

                foreach (['width', 'height'] as $attribute) {
                    $value = trim($child->getAttribute($attribute));
                    if ($value !== '' && ctype_digit($value)) {
                        $element->setAttribute($attribute, $value);
                    }
                }

                $element->setAttribute('loading', 'lazy');
            }

            if ($tag === 'ol') {
                $start = trim($child->getAttribute('start'));
                if ($start !== '' && preg_match('/\A[0-9]{1,5}\z/', $start)) {
                    $element->setAttribute('start', $start);
                }
            }

            if ($tag === 'th' || $tag === 'td') {
                foreach (['colspan', 'rowspan'] as $attribute) {
                    $value = trim($child->getAttribute($attribute));
                    if ($value !== '' && preg_match('/\A[1-9][0-9]{0,2}\z/', $value)) {
                        $element->setAttribute($attribute, $value);
                    }
                }
            }

            $target->appendChild($element);

            // Images and horizontal rules are void elements.
            if (!in_array($tag, ['img', 'br', 'hr'], true)) {
                $copyChildren($child, $element);
            }
        }
    };

    $copyChildren($sourceRoot, $cleanRoot);

    $result = '';
    foreach ($cleanRoot->childNodes as $child) {
        $result .= $clean->saveHTML($child);
    }

    return trim($result);
}
