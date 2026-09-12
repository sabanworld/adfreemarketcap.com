<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\Seo\SeoService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class LegalPage extends Component
{
    /**
     * @var array<string, array{title: string, view: string}>
     */
    public const PAGES = [
        'cookie-policy' => [
            'title' => 'Cookie policy',
            'view' => 'legal.cookie-policy',
        ],
        'privacy-policy' => [
            'title' => 'Privacy policy',
            'view' => 'legal.privacy-policy',
        ],
        'terms' => [
            'title' => 'Terms & conditions',
            'view' => 'legal.terms',
        ],
        'imprint' => [
            'title' => 'Imprint',
            'view' => 'legal.imprint',
        ],
        'risk-disclosure' => [
            'title' => 'Risk disclosure',
            'view' => 'legal.risk-disclosure',
        ],
        'disclosure-of-interests' => [
            'title' => 'Disclosure of interests',
            'view' => 'legal.disclosure-of-interests',
        ],
        'accessibility' => [
            'title' => 'Accessibility statement',
            'view' => 'legal.accessibility',
        ],
        'complaints' => [
            'title' => 'Complaints',
            'view' => 'legal.complaints',
        ],
    ];

    public string $page;

    public function mount(string $page): void
    {
        abort_unless(array_key_exists($page, self::PAGES), 404);
        $this->page = $page;
    }

    public function render(SeoService $seo)
    {
        $meta = self::PAGES[$this->page];
        $title = __($meta['title']);

        $pageSeo = $seo->forStaticPage(
            title: __('seo.legal_title', ['page' => $title, 'site' => config('app.name')]),
            description: __('seo.legal_description', [
                'page' => $title,
                'site' => config('app.name'),
                'operator' => config('company.legal_name'),
            ]),
            canonical: route('legal.show', $this->page),
        );

        return view('livewire.legal-page', [
            'heading' => $title,
            'contentView' => $meta['view'],
        ])
            ->title($pageSeo->title)
            ->layoutData(['seo' => $pageSeo]);
    }
}
