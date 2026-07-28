<?php

namespace App\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

final class TaskDescriptionSanitizer
{
    private HTMLPurifier $purifier;

    public function __construct()
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set(
            'HTML.Allowed',
            'p,br,strong,em,u,s,ul,ol,li,blockquote,h2,h3,a[href|title|target|rel]',
        );
        $config->set('URI.AllowedSchemes', [
            'http' => true,
            'https' => true,
            'mailto' => true,
        ]);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('HTML.TargetBlank', true);
        $config->set('HTML.Nofollow', true);
        $config->set('Cache.SerializerPath', storage_path('framework/cache'));

        $this->purifier = new HTMLPurifier($config);
    }

    public function sanitize(?string $description): ?string
    {
        $description = trim((string) $description);

        if ($description === '') {
            return null;
        }

        if ($description === strip_tags($description)) {
            $description = '<p>'.nl2br(
                htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                false,
            ).'</p>';
        }

        $sanitized = trim($this->purifier->purify($description));
        $plainText = trim(html_entity_decode(strip_tags($sanitized), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $plainText === '' ? null : $sanitized;
    }
}
