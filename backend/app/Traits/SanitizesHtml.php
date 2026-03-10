<?php

namespace App\Traits;

trait SanitizesHtml
{
    protected function sanitizeHtml(string $html): string
    {
        $allowed = '<a><b><strong><i><em><u><s><br><p><div><span><ul><ol><li><h1><h2><h3><h4><table><thead><tbody><tr><th><td><img>';
        $html = strip_tags($html, $allowed);

        // Remove event handler attributes (onclick, onerror, onload, etc.) and javascript: URIs
        $html = preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
        $html = preg_replace('/\s+on\w+\s*=\s*\S+/i', '', $html);
        $html = preg_replace('/href\s*=\s*["\']?\s*javascript:[^"\'>\s]*/i', 'href="#"', $html);
        $html = preg_replace('/src\s*=\s*["\']?\s*javascript:[^"\'>\s]*/i', 'src=""', $html);

        return $html;
    }
}
