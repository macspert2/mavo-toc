=== Mavo TOC ===
Contributors:
Tags: table of contents, toc, shortcode, headings
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.3.2
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
