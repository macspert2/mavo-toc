=== Mavo TOC ===
Contributors:
Tags: table of contents, toc, shortcode, headings
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.4.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds a [mavo_toc] shortcode that builds a table of contents from the headings in a post.

== Description ==

Place `[mavo_toc]` anywhere in a post or page and it will be replaced with a
table of contents built from the H1-H6 headings in that content. Headings
without an existing `id` get one assigned automatically (Gutenberg's "HTML
Anchor" field is respected if already set).

Global defaults are configured under Settings > Mavo TOC, and every shortcode
attribute below overrides the corresponding default for that one instance.

When a heading range spans more than one level (e.g. H2-H4), the list starts
collapsed to the shallowest level only; a "Show subheadings" control reveals
one further level per click until everything is shown. This control only
appears when there's actually a deeper level to reveal.

When `sticky` is on, the box sticks just below the site's fixed/sticky menu
bar (configured as a CSS selector under Settings > Mavo TOC, "Sticky bar CSS
selector") instead of underneath it, and automatically collapses to its title
only while stuck to the bar, reopening once scrolled back to its normal
position in the post.

== Caching ==

Editing mavo-toc.css or mavo-toc.js automatically busts the plugin's own
enqueued URL (versioned by file modification time) and triggers a one-time
purge of Autoptimize's and Swift Performance's caches, if either is active,
the next time any page loads after the change. Neither of those is reachable
until the *page* serving the old HTML is itself refetched, though — and on
this site that's normally Cloudflare's job, which this plugin has no API
credentials to purge automatically. If a change still doesn't show up after
a purge of the WordPress-level caches, check Cloudflare's own cache (a manual
"Purge Everything", or a Cache Rule that doesn't hold HTML for as long as
static assets) before assuming the plugin itself is at fault.

== Languages ==

All visible text (the default title, button labels, and the settings page)
is translatable, and French and German translations are bundled. On a
Polylang-powered site, the title and buttons automatically follow each page's
Polylang language — no per-language settings needed. Translation source
files live in `languages/` (`mavo-toc.pot` is the template; `mavo-toc-fr.po`
and `mavo-toc-de.po` are the translations — edit these and recompile to
`.mo` with `msgfmt` if you want to tweak the wording).

== Shortcode ==

`[mavo_toc]`

Attributes:

* `title` - heading text shown above the list. Empty string hides it.
* `min_level` / `max_level` - heading levels to include, 1-6.
* `collapsible` - `true`/`false`, adds a toggle to show/hide the list.
* `collapsed` - `true`/`false`, whether it starts collapsed (requires collapsible).
* `sticky` - `true`/`false`, keeps the box in view while scrolling (CSS position: sticky).
* `numbered` - `true`/`false`, ordered vs. unordered list.
* `smooth_scroll` - `true`/`false`, smooth-scrolls to the target heading on click.
* `limit` - max entries shown before a "Show more" toggle appears. `0` = no limit.
* `class` - extra CSS class added to the wrapper.

Example:

`[mavo_toc title="Contents" min_level="2" max_level="3" sticky="true" limit="8"]`

To exclude a single heading from the table of contents, add the
"Exclude class" (default `mavo-toc-skip`) configured in the settings page to
that heading's CSS class in the editor.

== Installation ==

1. Upload the `mavo-toc` folder to `/wp-content/plugins/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Optionally adjust defaults under Settings > Mavo TOC.
4. Add `[mavo_toc]` to any post or page.

== Changelog ==

= 1.4.2 =
* Fixed: a URL with a #heading-id loaded directly (the kind your own browser's address bar shows after clicking a TOC link, via history.pushState) triggers the *browser's own* native scroll-to-anchor before any of our JS runs, landing the heading behind the fixed menu bar. A `scroll-margin-top` CSS rule on post headings now covers that path too, not just our own click handler.
* Fixed: an instant/large scroll jump (that same deep-linked #hash, or browser back/forward restoring scroll position) didn't always get marked "stuck" by the IntersectionObserver, even though CSS position: sticky had genuinely engaged — confirmed directly in testing. The TOC's own position is now also checked directly on scroll and on initial setup as a safety net, independent of whether the observer's sentinel-crossing fired.

= 1.4.1 =
* The automatic cache purge on asset change now also clears Swift Performance's cache (`Swift_Performance_Cache::clear_all_cache()`), not just Autoptimize's, since it caches full pages independently. Cloudflare's edge cache sits in front of both and can't be purged from this plugin without API credentials it doesn't have — see the description below for what to do about that layer.

= 1.4.0 =
* Found the actual cause of the title-language issue, and it had nothing to do with text domains: the Settings page's "Default title" field was pre-filled with the *resolved* default, which is shown in wp-admin's own locale (not a front-end visitor's Polylang language). Saving the settings form for any reason — even just to change an unrelated option — silently froze that resolved text into the database as a permanent override, applied to every visitor regardless of language from then on.
  - The settings field no longer pre-fills with a resolved value; it now shows only what was actually saved as a deliberate override, with the live default shown as a placeholder hint instead.
  - A saved title of "" is now treated as "no override" and re-resolves to the current default (translated per page) rather than being a frozen value — if your title is currently stuck in one language, open Settings > Mavo TOC, clear the "Default title" field, and save once to fix it.
* Removed the temporary diagnostic added while tracking this down.

= 1.3.6 =
* Found it: the diagnostic showed the loaded French translation object was still sitting in memory, correct, but WordPress's own `__()` was treating the domain as "unloaded" again by the time the shortcode rendered (something elsewhere in the request re-marks it after our own load completes, then it's fine again by the time the page footer runs). Rather than fight that, the title and button labels are now read from our own snapshot taken at the two points already confirmed correct, instead of calling `__()` again at render time — making our output immune to whatever's toggling that flag.

= 1.3.5 =
* Diagnostic now confirmed the title-language bug happens on the real `the_content` render (not a separate SEO/meta-description call), with the correct locale and Polylang slug active at that exact moment — yet the wrong string comes back. Added a direct inspection of the loaded translation object itself (entry count, whether it's actually unloaded, what it returns for the exact string) to find out why.

= 1.3.4 =
* Expanded the temporary language diagnostic further: the shortcode render snapshot now records every call (not just the first) along with the active filter stack and a call-stack summary, to identify whether something else (an SEO plugin generating a meta description from the content, for instance) is triggering an extra, earlier render of the shortcode in the wrong language context.

= 1.3.3 =
* Fixed: clicking a TOC link while it was still in its normal (non-sticky) position could still land the heading half behind the TOC bar. Confirmed via direct testing that a sticky element renders with extra spacing before it has actually engaged position: sticky versus once it truly has — even with "stuck" and "collapsed" already forced. The jump now does an instant rough placement first (guaranteeing the TOC has genuinely engaged), then a small corrective smooth scroll using a fresh measurement, plus the stuck/collapse observer is suspended for the duration so it can't undo the forced state mid-scroll.
* Added `unload_textdomain()` before reloading translations, defensively guarding against a WordPress core behavior where reloading a text domain merges with (and can be overridden by) anything already loaded earlier in the request — and expanded the temporary language diagnostic to capture the translation state at three points (textdomain load, shortcode render, page footer) to track down the remaining title-language issue.

= 1.3.2 =
* Fixed: the heading could still land partially behind the sticky TOC bar itself after the menu-bar fix. The TOC's own CSS removes its outer padding and shrinks its title font only once it's *also* marked "stuck" (not just collapsed) — the jump calculation now forces that state too before measuring, instead of measuring a taller pre-stuck height. Verified directly against the live page: clearance went from -22px (hidden) to a clean +12px.
* Added a temporary diagnostic (HTML comment near the TOC, invisible to visitors) to track down the remaining title-language issue — see plugin notes; safe to remove once resolved.

= 1.3.1 =
* Fixed: the title (and other translatable strings) could still show in English on a Polylang page, because pll_current_language() isn't reliable for the post being viewed until the main query has run, which is after the `init` hook used to load it. Translation loading now also re-runs on `wp`, once Polylang has actually resolved the page's language.
* Fixed: clicking a TOC link could still land the heading behind the menu bar when the TOC started off expanded (not yet collapsed) — auto-collapsing only happened mid-scroll, after the landing position had already been calculated using its old, taller height. The TOC is now collapsed synchronously before that calculation, not left to happen on the way.
* Fixed: a manual "peek" (re-opening the title while stuck) could re-collapse itself within ~100ms even without the user scrolling, because collapsing/expanding the TOC's own height was triggering a spurious `scroll` event with no real position change. Recollapse-on-scroll now requires an actual change in scroll position.
* Fixed: the stuck/unstuck detection could incorrectly mark the TOC "stuck" from the moment the page loaded, before it was ever scrolled anywhere near it.

= 1.3.0 =
* Added French and German translations (bundled .mo files) for the title, button labels, and settings page.
* On Polylang sites, the displayed language now automatically follows each page's Polylang language (using Polylang's own language slug, independent of whatever exact WordPress locale each language is mapped to) instead of always showing the same hardcoded text.

= 1.2.2 =
* Fixed: the menu bar's height was only ever measured when a *sticky* TOC was on the page, so the jump-offset silently did nothing on pages where the TOC wasn't sticky. It's now measured for every TOC, sticky or not, and re-measured directly at click time instead of relying on a cached value.
* Sticky TOC keeps its normal padding while just sitting in its flow position; padding now only tightens once actually pinned against the menu bar.
* Border replaced with a slight drop-shadow in the normal (non-sticky-pinned) state too.
* A TOC manually re-opened while stuck collapses again as soon as the user scrolls, instead of staying open indefinitely.

= 1.2.1 =
* Stuck/collapsed sticky bar loses its top/bottom padding (left/right kept) for a slimmer look.
* Title and toggle buttons keep their own hover color instead of inheriting the theme's generic button hover skin, which could make the label unreadable.
* Clicking a TOC link now lands the heading below the menu bar and the sticky TOC bar instead of behind them.

= 1.2.0 =
* CSS/JS are now versioned by their own file modification time instead of a hand-bumped constant, so the enqueued URL always changes when either file is edited.
* If the Autoptimize plugin is active, its full CSS/JS cache is purged automatically the moment mavo-toc.css or mavo-toc.js actually changes on disk (checked once per request, only acts on an actual change).

= 1.1.0 =
* Styling adapted to match the Maman Voyage theme (colors, font, shadow via CSS custom properties).
* Sticky mode now sticks below the site's fixed menu bar instead of behind it, with a configurable selector and a height measured at runtime.
* Multi-level lists now start collapsed to the shallowest level, with a "Show subheadings" control to reveal deeper levels one at a time.
* Sticky + collapsible TOCs now auto-collapse to their title once stuck against the menu bar, and reopen once unstuck.

= 1.0.0 =
* Initial release.
