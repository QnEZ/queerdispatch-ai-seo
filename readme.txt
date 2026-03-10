=== QueerDispatch AI SEO ===
Contributors: qnez
Tags: seo, ai, openai, metadata, social, editor
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-assisted SEO tools for QueerDispatch and similar news publishers.

== Description ==

QueerDispatch AI SEO adds a Gutenberg sidebar that can generate:

* SEO title
* Meta description
* Focus keyphrase and variants
* Headline variants
* Social title and social description
* Internal-link suggestions from recent site posts
* Excerpt suggestions
* AI disclosure copy
* Editorial notes for review

The plugin is designed for editorial review. It generates suggestions, then lets an editor save them into post meta.

Version 0.3.0 adds:

* per-post article mode presets (News, Editorial, Explainer, Social Copy)
* selective one-click apply controls in Gutenberg
* smarter internal-link candidate scoring using title overlap, tags, and categories
* sync to Yoast and Rank Math meta fields on save
* an admin diagnostic request button for the OpenAI connection

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin through the WordPress Plugins screen.
3. Go to Settings > QueerDispatch AI SEO.
4. Add your OpenAI API key.
5. Open a supported post type in Gutenberg and use the sidebar.

== Frequently Asked Questions ==

= Does this replace a technical SEO plugin? =

Not completely. It covers editorial metadata generation and can output basic meta tags, but you may still want a dedicated free SEO plugin for sitemaps, canonicals, and broader technical SEO.

= Does it automatically publish AI output? =

No. Editors review and save the generated output.

= Will this conflict with Yoast, Rank Math, or All in One SEO? =

The editorial generation tools can coexist. Front-end meta output in this plugin is automatically suppressed when another major SEO plugin is detected.

== Changelog ==

= 0.3.0 =
* Added article mode presets in the editor.
* Added selective field-apply controls.
* Added Yoast and Rank Math meta sync on save.
* Improved internal-link candidate scoring.
* Added an OpenAI diagnostic button in settings.

= 0.2.0 =
* Added better validation, sanitization, and conflict handling.
* Added headline variant generation and storage.
* Masked saved API keys on the settings screen.
* Disabled front-end meta output by default.

= 0.1.0 =
* Initial scaffold.
