=== Mavo TOC ===
Contributors:
Tags: table of contents, toc, shortcode, headings
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.1.0
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

= 1.1.0 =
* Styling adapted to match the Maman Voyage theme (colors, font, shadow via CSS custom properties).
* Sticky mode now sticks below the site's fixed menu bar instead of behind it, with a configurable selector and a height measured at runtime.
* Multi-level lists now start collapsed to the shallowest level, with a "Show subheadings" control to reveal deeper levels one at a time.
* Sticky + collapsible TOCs now auto-collapse to their title once stuck against the menu bar, and reopen once unstuck.

= 1.0.0 =
* Initial release.
