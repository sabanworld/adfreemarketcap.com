<?php

declare(strict_types=1);

namespace App\Services\Consent;

/**
 * One answer to "does this site load a tag that needs opt-in", shared by every
 * surface that has to agree on it: the head script, the cookie bar, the footer
 * control, the privacy policy, and the cookie policy. If those drift apart, the
 * pages describe a site the visitor is not on, which is the defect this class
 * exists to prevent.
 */
class ConsentService
{
    public function advertisingEnabled(): bool
    {
        return (bool) config('google-ads.enabled') && filled($this->conversionId());
    }

    public function conversionId(): string
    {
        return (string) config('google-ads.conversion_id');
    }

    /**
     * Injected into the page only after the visitor accepts, never as a plain
     * script tag in the markup.
     */
    public function scriptUrl(): string
    {
        return config('google-ads.script_url') . '?id=' . $this->conversionId();
    }

    /**
     * Conversion actions that are fully configured, keyed by the name the app
     * reports them under, mapped to the send_to value Google expects. A label
     * that is still empty is left out, so the browser cannot report a
     * conversion against an action that does not exist yet.
     *
     * @return array<string, string>
     */
    public function conversions(): array
    {
        if (! $this->advertisingEnabled()) {
            return [];
        }

        return collect(config('google-ads.conversions'))
            ->filter(fn ($label): bool => filled($label))
            ->map(fn ($label): string => $this->conversionId() . '/' . $label)
            ->all();
    }

    public function storageKey(): string
    {
        return (string) config('consent.storage_key');
    }

    public function version(): string
    {
        return (string) config('consent.version');
    }
}
