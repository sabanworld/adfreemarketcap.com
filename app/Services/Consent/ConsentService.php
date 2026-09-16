<?php

declare(strict_types=1);

namespace App\Services\Consent;

/**
 * One answer to "does this site load anything that needs opt-in", shared by
 * every surface that has to agree on it: the head script, the cookie bar, the
 * footer control, the privacy policy, the cookie policy, the Why ad-free page,
 * and the exchange widget placeholder. If those drift apart, the pages describe
 * a site the visitor is not on, which is the defect this class exists to prevent.
 */
class ConsentService
{
    public function advertisingEnabled(): bool
    {
        return (bool) config('google-ads.enabled') && filled($this->conversionId());
    }

    public function exchangeWidgetEnabled(): bool
    {
        return (bool) config('exchange-widget.enabled') && filled($this->exchangeLinkId());
    }

    /**
     * True when any non-essential third-party contact ships in this build.
     * The cookie bar becomes a choice rather than a notice whenever this is true.
     */
    public function required(): bool
    {
        return $this->advertisingEnabled() || $this->exchangeWidgetEnabled();
    }

    public function conversionId(): string
    {
        return (string) config('google-ads.conversion_id');
    }

    public function exchangeLinkId(): string
    {
        return (string) config('exchange-widget.link_id');
    }

    /**
     * Injected into the page only after the visitor accepts, never as a plain
     * script tag in the markup.
     */
    public function scriptUrl(): string
    {
        return config('google-ads.script_url') . '?id=' . $this->conversionId();
    }

    public function exchangeConnectorScriptUrl(): string
    {
        return (string) config('exchange-widget.connector_script_url');
    }

    public function exchangeWidgetBaseUrl(): string
    {
        return (string) config('exchange-widget.widget_base_url');
    }

    /**
     * Default query params for the ChangeNOW iframe, without from/to/amount so
     * a Blade call site can override the pair per page.
     *
     * @return array<string, scalar>
     */
    public function exchangeWidgetDefaults(): array
    {
        $defaults = config('exchange-widget.defaults', []);

        return [
            'FAQ' => (bool) ($defaults['faq'] ?? true),
            'amount' => (string) ($defaults['amount'] ?? '0.01'),
            'amountFiat' => '',
            'backgroundColor' => (string) ($defaults['background_color_light'] ?? 'FFFFFF'),
            'darkMode' => false,
            'from' => (string) ($defaults['from'] ?? 'btc'),
            'horizontal' => (bool) ($defaults['horizontal'] ?? true),
            'isFiat' => (bool) ($defaults['is_fiat'] ?? false),
            'lang' => (string) ($defaults['lang'] ?? 'en-US'),
            'link_id' => $this->exchangeLinkId(),
            'locales' => (bool) ($defaults['locales'] ?? true),
            'logo' => (bool) ($defaults['logo'] ?? false),
            'primaryColor' => (string) ($defaults['primary_color'] ?? '00C26F'),
            'to' => (string) ($defaults['to'] ?? 'eth'),
            'toTheMoon' => (bool) ($defaults['to_the_moon'] ?? false),
        ];
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
