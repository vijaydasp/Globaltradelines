<?php
declare(strict_types=1);
namespace App\Services;

final class Sanitizer {
    public static function html(string $input): string {
        if ($input==='') return '';
        $dom=new \DOMDocument();
        $old=libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div>'.$input.'</div>',LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD|LIBXML_NONET);
        libxml_clear_errors(); libxml_use_internal_errors($old);
        $allowed=['div','p','br','strong','b','em','i','u','h2','h3','h4','ul','ol','li','blockquote','a','table','thead','tbody','tr','th','td'];
        $clean=function(\DOMNode $node) use (&$clean,$allowed): void {
            foreach (iterator_to_array($node->childNodes) as $child) {
                if ($child instanceof \DOMElement) {
                    if (!in_array(strtolower($child->tagName),$allowed,true)) { $node->removeChild($child); continue; }
                    $href=$child->getAttribute('href');
                    foreach(iterator_to_array($child->attributes) as $attr) $child->removeAttribute($attr->name);
                    if ($child->tagName==='a' && safe_url($href)) { $child->setAttribute('href',cms_url($href)); $child->setAttribute('rel','noopener noreferrer'); }
                    $clean($child);
                } elseif (!($child instanceof \DOMText)) $node->removeChild($child);
            }
        };
        $clean($dom);
        $out=$dom->saveHTML() ?: '';
        return preg_replace('~^<div>|</div>\s*$~','',$out);
    }
}
