# Public SEO

Search visibility matters for adfreemarketcap: rankings and coin pages should be crawlable, shareable, and free of thin duplicate URLs.

## What we ship

| Surface | Route / location | Role |
|---------|------------------|------|
| Sitemap | `/sitemap.xml` (`sitemap` route) | Home, DexScan, Why ad-free, every legal page, and every coin slug; `lastmod` from sync timestamps when present |
| Robots | `/robots.txt` (`robots` route) | Allow public UI; disallow `/admin` and `/livewire`; advertise sitemap |
| Page meta | `layouts.app` + `<x-seo-meta>` | Title, description, canonical, Open Graph, Twitter, JSON-LD |
| Builder | `app/Services/Seo/SeoService.php` | Single place for titles, descriptions, sitemap entries, robots body |
| Copy | `lang/en/seo.php` | Translatable SEO strings |
| Config | `config/seo.php` | Default description, optional `SEO_OG_IMAGE` / `SEO_TWITTER_SITE` |

## Rules agents must follow

1. **New public pages** must get: a named route, sitemap inclusion (if indexable), unique title + meta description via `SeoService` (or an extension of it), and a **clean canonical** (no filter/sort/query junk).
2. **Do not** put Livewire URL state (`?search=`, `?sort=`, pagination) into the sitemap or into `rel=canonical`.
3. **Admin and internal endpoints** stay out of the index (`Disallow` in robots; never add Filament URLs to the sitemap).
4. When renaming routes or slug patterns, update `SeoService`, sitemap tests in `tests/Feature/SeoTest.php`, and this doc in the same change.
5. Prefer meaningful `alt` text on public images (coin logos use the coin name).

## Tests

```bash
./vendor/bin/sail artisan test tests/Feature/SeoTest.php tests/Unit/SeoServiceTest.php
```
