<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\LegalPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_imprint_shows_the_identification_details_the_e_commerce_directive_requires(): void
    {
        $this->get(route('legal.show', 'imprint'))
            ->assertOk()
            ->assertSee('The Saban Company B.V.', false)
            ->assertSee('Besloten vennootschap (B.V.)', false)
            ->assertSee('91125030', false)
            ->assertSee('Jaap Bijzerweg 19', false)
            ->assertSee('Woerden', false)
            ->assertSee('hello@the.saban.company', false)
            ->assertSee('https://the.saban.company/en', false);
    }

    public function test_imprint_shows_the_vat_number_only_once_configured(): void
    {
        config(['company.vat' => null]);

        $this->get(route('legal.show', 'imprint'))
            ->assertOk()
            ->assertDontSee('VAT identification number', false);

        config(['company.vat' => 'NL123456789B01']);

        $this->get(route('legal.show', 'imprint'))
            ->assertOk()
            ->assertSee('NL123456789B01', false);
    }

    public function test_legal_pages_are_reachable(): void
    {
        foreach (array_keys(LegalPage::PAGES) as $page) {
            $this->get(route('legal.show', $page))->assertOk();
        }
    }

    public function test_privacy_policy_states_purpose_legal_basis_retention_and_rights(): void
    {
        config([
            'company.retention.account_deletion_days' => 30,
            'company.retention.server_log_days' => 90,
        ]);

        $response = $this->get(route('legal.show', 'privacy-policy'));

        $response->assertOk();
        $response->assertSee('article 6(1)(b) GDPR', false);
        $response->assertSee('article 6(1)(f) GDPR', false);
        $response->assertSee('Legal basis', false);
        $response->assertSee('Kept for', false);
        $response->assertSee('90 days', false);
        $response->assertSee('erased within 30 days', false);
        $response->assertSee('Portability', false);
        $response->assertSee('Withdrawal of any consent', false);
        $response->assertSee('article 22 GDPR', false);
        $response->assertSee('Autoriteit Persoonsgegevens', false);
        $response->assertSee('under 16', false);
    }

    public function test_privacy_policy_names_processors_and_transfer_safeguards(): void
    {
        config(['company.processors' => [
            [
                'category' => 'Hosting, database, and backups',
                'name' => 'Example Hosting B.V.',
                'location' => 'European Union',
                'transfer' => null,
            ],
            [
                'category' => 'Reverse proxy, DDoS protection, and bot filtering',
                'name' => 'Cloudflare, Inc.',
                'location' => 'European edge locations',
                'transfer' => 'EU standard contractual clauses',
            ],
            [
                'category' => 'Transactional email (account and support messages)',
                'name' => null,
                'location' => 'European Union',
                'transfer' => null,
            ],
        ]]);

        $response = $this->get(route('legal.show', 'privacy-policy'));

        $response->assertOk();
        $response->assertSee('article 28 GDPR', false);
        $response->assertSee('Example Hosting B.V.', false);
        $response->assertSee('Cloudflare, Inc.', false);
        $response->assertSee('Safeguard for transfers outside the EEA: EU standard contractual clauses.', false);
        // A vendor that is not appointed yet shows as a category, and is never invented.
        $response->assertSee('Not yet appointed', false);
    }

    public function test_configured_hosting_stack_appears_on_the_privacy_and_imprint_pages(): void
    {
        $this->get(route('legal.show', 'privacy-policy'))
            ->assertOk()
            ->assertSee('DigitalOcean, LLC', false)
            ->assertSee('Amsterdam, the Netherlands (AMS3)', false)
            ->assertSee('Ploi (WebBuilds B.V.)', false)
            // A US provider needs its transfer safeguard named, even with EU storage.
            ->assertSee('EU standard contractual clauses and the EU-US Data Privacy Framework', false);

        $this->get(route('legal.show', 'imprint'))
            ->assertOk()
            ->assertSee('DigitalOcean, LLC', false);
    }

    public function test_imprint_omits_hosting_while_no_provider_is_configured(): void
    {
        config(['company.processors.hosting.name' => null]);

        $response = $this->get(route('legal.show', 'imprint'));

        $response->assertOk();
        $response->assertDontSee('The service runs on infrastructure from', false);
    }

    public function test_picks_link_out_and_match_the_disclosure_page(): void
    {
        $home = $this->get(route('home'));

        $home->assertOk();
        foreach (config('picks') as $pick) {
            $home->assertSee('href="' . $pick['url'] . '"', false);
            $home->assertSee($pick['name'], false);
        }
        // An outbound link must not hand the destination our referrer or window.
        $home->assertSee('rel="noopener noreferrer"', false);

        $disclosure = $this->get(route('legal.show', 'disclosure-of-interests'));

        $disclosure->assertOk();
        $disclosure->assertSee('Trezor (trezor.io): our creator uses this product and has no commercial partnership', false);
        $disclosure->assertSee('Rigly (rigly.io): our creator has a strategic partnership with this company', false);
    }

    public function test_a_pick_with_a_partner_relationship_carries_the_partner_badge(): void
    {
        config(['picks' => [
            [
                'name' => 'Example Mining',
                'kind' => 'Mining',
                'url' => 'https://example.test',
                'note' => 'A pick used only by this test.',
                'relationship' => 'partner',
            ],
        ]]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('afmc-pick__badge afmc-pick__badge--warn', false);
        $response->assertSee('Creator is a partner', false);
        $response->assertDontSee('We use this', false);
    }

    public function test_cookie_policy_lists_every_stored_item_by_name(): void
    {
        $response = $this->get(route('legal.show', 'cookie-policy'));

        $response->assertOk();
        $response->assertSee((string) config('session.cookie'), false);
        $response->assertSee('XSRF-TOKEN', false);
        $response->assertSee('remember_web_*', false);
        $response->assertSee('afmc-theme', false);
        $response->assertSee('afmc-cookies', false);
        $response->assertSee('article 5(3) of the ePrivacy Directive', false);
        $response->assertSee('no analytics cookies', false);
        $response->assertSee('the footer has no preference panel', false);
    }

    public function test_cookie_policy_explains_why_the_counter_needs_no_consent(): void
    {
        $response = $this->get(route('legal.show', 'cookie-policy'));

        $response->assertOk();
        $response->assertSee('Statistics without cookies', false);
        $response->assertSee('Simple Analytics', false);
        $response->assertSee('It sets no cookie, writes nothing to local storage', false);
    }

    public function test_privacy_policy_discloses_the_visitor_counter(): void
    {
        $response = $this->get(route('legal.show', 'privacy-policy'));

        $response->assertOk();
        $response->assertSee('Visitor statistics', false);
        $response->assertSee('Simple Analytics B.V., Amsterdam', false);
        // What it measures, what happens to the IP, the basis, and the way out.
        $response->assertSee('your time zone, and your device and browser type', false);
        $response->assertSee('every IP address is dropped without being logged or stored', false);
        $response->assertSee('Legitimate interests in measuring use of the site, article 6(1)(f) GDPR', false);
        $response->assertSee('discards visits from browsers that send Do Not Track', false);
    }

    public function test_footer_control_reopens_the_cookie_notice(): void
    {
        $response = $this->get(route('legal.show', 'cookie-policy'));

        $response->assertOk();
        // The footer button and the notice have to agree on the same event name,
        // otherwise the control silently does nothing.
        $response->assertSee("\$dispatch('afmc-cookie-notice')", false);
        $response->assertSee('@afmc-cookie-notice.window', false);
        $response->assertSee('Cookie notice</button>', false);
    }

    public function test_terms_set_out_the_mica_position_and_consumer_protections(): void
    {
        $response = $this->get(route('legal.show', 'terms'));

        $response->assertOk();
        $response->assertSee('not a crypto-asset service provider', false);
        $response->assertSee('Regulation (EU) 2023/1114', false);
        $response->assertSee('Authority for the Financial Markets', false);
        $response->assertSee('Rome I Regulation', false);
        $response->assertSee('mandatory EU or Dutch law', false);
        $response->assertSee('ConsuWijzer', false);
    }

    public function test_risk_disclosure_warns_about_missing_investor_protection(): void
    {
        $response = $this->get(route('legal.show', 'risk-disclosure'));

        $response->assertOk();
        $response->assertSee('you can lose all the money you put in', false);
        $response->assertSee('investor compensation scheme', false);
        $response->assertSee('not a marketing communication', false);
    }

    public function test_complaints_page_routes_to_national_bodies_and_never_to_the_closed_odr_platform(): void
    {
        $response = $this->get(route('legal.show', 'complaints'));

        $response->assertOk();
        $response->assertSee('five business days', false);
        $response->assertSee('ConsuWijzer', false);
        $response->assertSee('Autoriteit Persoonsgegevens', false);
        $response->assertDontSee('Online Dispute Resolution', false);
    }

    public function test_no_legal_page_still_references_the_closed_odr_platform(): void
    {
        foreach (glob(resource_path('views/legal/*.blade.php')) as $file) {
            $body = (string) file_get_contents($file);

            foreach (['ODR', 'Online Dispute Resolution', 'consumers/odr'] as $reference) {
                $this->assertStringNotContainsString(
                    $reference,
                    $body,
                    basename($file) . ' references the ODR platform, which closed on 20 July 2025.'
                );
            }
        }
    }

    public function test_accessibility_statement_names_the_standard_and_a_feedback_route(): void
    {
        $this->get(route('legal.show', 'accessibility'))
            ->assertOk()
            ->assertSee('WCAG 2.2 level AA', false)
            ->assertSee('Directive (EU) 2019/882', false)
            ->assertSee('five business days', false);
    }

    public function test_pages_show_the_configured_policy_date(): void
    {
        config(['company.policies_updated_at' => '1 October 2026']);

        $this->get(route('legal.show', 'privacy-policy'))
            ->assertOk()
            ->assertSee('Last updated: 1 October 2026', false);
    }

    public function test_registration_points_at_the_terms_and_the_privacy_policy(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee(route('legal.show', 'terms'), false)
            ->assertSee(route('legal.show', 'privacy-policy'), false);
    }

    public function test_sitemap_includes_dexscan_and_legal_pages(): void
    {
        $response = $this->get(route('sitemap'));

        $response->assertOk();
        $response->assertSee('<loc>' . route('dexscan') . '</loc>', false);
        $response->assertSee('<loc>' . route('legal.show', 'privacy-policy') . '</loc>', false);
        $response->assertSee('<loc>' . route('legal.show', 'imprint') . '</loc>', false);
    }

    public function test_robots_disallows_auth_and_watchlist(): void
    {
        $response = $this->get(route('robots'));

        $response->assertOk();
        $response->assertSee('Disallow: /login', false);
        $response->assertSee('Disallow: /watchlist', false);
    }
}
