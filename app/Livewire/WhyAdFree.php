<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\Seo\SeoService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class WhyAdFree extends Component
{
    public function render(SeoService $seo)
    {
        $pageSeo = $seo->forStaticPage(
            title: __('seo.why_ad_free_title', ['site' => config('app.name')]),
            description: __('seo.why_ad_free_description'),
            canonical: route('why-ad-free'),
        );

        return view('livewire.why-ad-free')
            ->title($pageSeo->title)
            ->layoutData(['seo' => $pageSeo]);
    }
}
