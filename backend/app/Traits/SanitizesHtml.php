<?php

namespace App\Traits;

use HTMLPurifier;
use HTMLPurifier_Config;

trait SanitizesHtml
{
    protected function purifyHtml(string $html): string
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', 'p,br,strong,em,u,s,ul,ol,li,a[href|target],h1,h2,h3,h4,h5,h6,blockquote,span[style],div[style],table,thead,tbody,tr,th,td,img[src|alt|width|height]');
        $config->set('HTML.SafeIframe', true);
        $config->set('URI.SafeIframeRegexp', '%^%');
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('Cache.SerializerPath', sys_get_temp_dir());
        $purifier = new HTMLPurifier($config);
        return $purifier->purify($html);
    }
}
