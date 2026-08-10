<?php

namespace App\Concerns;

/**
 * Link and host rendering shared by anything displayed as a SERP row.
 *
 * URLs on these models are never authored in Blade — they arrive from
 * third-party search APIs or from the sponsored:add command. Blade escaping
 * protects the attribute boundary but not the scheme, so a javascript:,
 * data:, or vbscript: URL would still execute on click. Callers render
 * unlinked text when safeUrl() returns null.
 *
 * @property string $url
 * @property string|null $image_url
 */
trait RendersResultLinks
{
    /**
     * The link target, or null when the stored URL is not safe to navigate to.
     */
    public function safeUrl(): ?string
    {
        return $this->safeHttpUrl($this->url);
    }

    /**
     * The image to render, or null when the stored value is not a plain
     * http(s) image reference.
     */
    public function safeImageUrl(): ?string
    {
        return is_string($this->image_url) ? $this->safeHttpUrl($this->image_url) : null;
    }

    /**
     * The host to show above a row.
     */
    public function displayHost(): string
    {
        $host = parse_url($this->url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $this->stripWww($host) : $this->url;
    }

    protected function stripWww(string $host): string
    {
        return (string) preg_replace('/^www\./i', '', $host);
    }

    /**
     * Allow-list http and https. Anything else — including scheme-relative and
     * schemeless values, which resolve unpredictably — is rejected.
     */
    protected function safeHttpUrl(string $url): ?string
    {
        $trimmed = trim($url);

        if ($trimmed === '') {
            return null;
        }

        $scheme = parse_url($trimmed, PHP_URL_SCHEME);

        if (! is_string($scheme)) {
            return null;
        }

        return in_array(strtolower($scheme), ['http', 'https'], true) ? $trimmed : null;
    }
}
