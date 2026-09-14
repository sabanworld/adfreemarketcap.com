# First principle: privacy and honesty win

This is the rule that breaks ties in this repository. When privacy or honesty pulls against something else, growth, revenue, conversion rate, page weight, or the hour it would take to ship the easy version, the other thing gives way.

Not "we weigh it carefully". It loses.

A principle that never costs anything is decoration, so most of this page is about what it has already cost and how to apply it to the next change.

## What the two words mean here

### Privacy is a property of the code

It is not a promise on a page. The page only describes what the code already does.

- **Collect nothing without a named use.** Every row in the privacy policy table exists because something in `app/` reads it. A field with no reader is deleted, not documented.
- **Keep the number of parties a browser talks to as close to one as possible.** Today it is us, a cookieless counter, and, only after the visitor accepts, Google.
- **Non-essential storage waits for opt-in.** Withdrawing is one click, sits in the same place as accepting, and deletes what was already written rather than only stopping the next write.
- **Server side beats client side.** Market data, exchange rates, and Nostr notes are fetched by our own servers on a schedule, so a visit never reaches a provider and a provider never sees a visitor.
- **Self-hosted beats a CDN.** Fonts, icons, styles, and scripts come from our domain, because a font request is a visitor's IP address handed to a company they never chose.

### Honesty is falsifiability

Every public claim has to be checkable by a reader who does not trust us.

- **A policy sentence and the code cannot disagree.** If they do, one of them is a bug, and the fix ships in the same change as whatever caused the drift.
- **Prefer a rule, a formula, or a file path to reassurance.** "Rank follows market capitalisation as our providers report it, one formula for every asset" is honest. "We are committed to integrity" is noise.
- **Never write a claim a reader cannot check.** Trust theatre is worse than silence, because it teaches people to skim the sentences that do matter. `tests/Feature/CopyStyleTest.php` fails the build on the specific slogans we have caught ourselves reaching for.
- **Name the cost out loud.** When we added advert measurement, the Why ad-free page grew a section explaining it rather than a vaguer sentence about partners.
- **Honest is not the same as vague.** Relaxed product copy still has to match the code exactly. Softening a fact until it stops being wrong is the failure mode this rule exists to catch.

## What it has already cost

Real trade-offs the repository has taken, so the next one has precedent:

| Decision | The easy version we did not ship |
|----------|----------------------------------|
| Google Ads conversion tag sits behind a consent gate and loads no script until Accept ([`docs/privacy-and-legal.md`](privacy-and-legal.md)) | Google's own snippet, pasted into `<head>`, measuring every visitor |
| Accept and Reject are the same size and variant in the cookie bar | A bright Accept next to a grey link, which measurably converts better |
| Conversions report only after consent, so the figures under-count | Full attribution, the way most sites get it |
| Fonts and the icon subset are built into our bundle | Two lines of Google Fonts and a CDN icon set |
| Providers are called by scheduled jobs, never from a request | Live provider calls per page, which would be fresher and simpler |
| No ad slots, no paid placement, no ad-free tier | The business model every comparable site runs on |

Each line is a thing the product gave up. That is what makes the claims on the public pages worth anything.

## Applying it to a change

Before you open a pull request, answer these. Any "no" is a blocker, not a note for later.

1. **Does this send anything new to a third party?** If yes, it needs a policy update, a processor entry in `config/company.php`, and a consent gate unless it is strictly necessary.
2. **Does it write to the visitor's device?** If yes and it is not strictly necessary, it is off until they accept, and rejecting is exactly as easy.
3. **Does it collect a field nothing reads?** Then do not collect it.
4. **Does any sentence on a public page become false?** Fix the sentence in the same change, and bump `COMPANY_POLICIES_UPDATED_AT`.
5. **Could a sceptical reader check every claim you added?** If not, cut it or replace it with the concrete rule behind it.
6. **Did you add a test that fails if someone quietly undoes this?** A promise with no test is a comment.

## Where it is enforced

The principle is only real where a build fails without it:

| Guard | Covers |
|-------|--------|
| `tests/Feature/ConsentTest.php` | Nothing reaches Google before Accept, consent mode defaults to denied, withdrawal deletes the cookies, both buttons carry equal weight |
| `tests/Feature/LegalPagesTest.php` | Purpose, legal basis, retention, recipients, and transfer safeguards on every page, in both the tag-on and tag-off states |
| `tests/Feature/AnalyticsTest.php` | The counter renders nothing when off, and honours Do Not Track as the policy describes |
| `tests/Feature/CopyStyleTest.php` | Machine-sounding copy and hollow slogans across views, `lang/`, `docs/`, and `README.md` |
| `tests/Feature/SeoTest.php` | Admin and Livewire internals stay out of the sitemap and robots |
| [`AGENTS.md`](../AGENTS.md) | The two-third-party-request ceiling, the consent rules, and the writing rules |

## What this page is not

It is a decision rule for people working on the repository. It is not copy.

Do not quote it onto a public page as a badge, do not turn any line here into a tagline, and do not let it become the kind of unfalsifiable virtue claim it tells you to avoid. Visitors get the concrete version: [`resources/views/legal/`](../resources/views/legal) and the Why ad-free page, where every sentence points at something they can check.
