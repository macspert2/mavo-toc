=== Mavo TOC ===
Contributors:
Tags: table of contents, toc, shortcode, headings
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
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

= 1.0.0 =
* Initial release.
