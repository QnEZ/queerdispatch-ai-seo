=== QueerDispatch AI SEO ===
Contributors: queerdispatch
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-assisted SEO, social copy, infographic prompts, internal-link suggestions, and disclosure tools for QueerDispatch.

== Description ==

QueerDispatch AI SEO adds a Gutenberg sidebar for generating editorial SEO fields with the OpenAI API.

Version 0.8.1 focuses on timeout resilience for real post generation:
- trims post content to clean plain text before sending it to OpenAI
- caps article body size to reduce oversized requests
- splits generation into separate core and media requests
- raises the default request timeout to 90 seconds
- logs payload size for easier debugging

== Installation ==
1. Upload the plugin folder to /wp-content/plugins/ or install the ZIP from WordPress admin.
2. Activate the plugin.
3. Visit Settings > QueerDispatch AI SEO.
4. Add your OpenAI API key and choose enabled post types.

== Changelog ==
= 0.8.1 =
* Trimmed post content before API calls and capped article body size.
* Split generation into smaller core and media requests.
* Raised the default request timeout to 90 seconds.
* Added payload-size logging for generation requests.

= 0.5.0 =
* Added beat preset selection.
* Added featured image alt text and caption suggestions.
* Added editorial workflow status fields and admin columns.
* Added copy-ready story package output.
* Added story package generation to bulk actions.

= 0.4.0 =
* Added network-ready social post generation.
* Added infographic prompt output.
* Added optional automatic disclosure block insertion.
* Added post list screen columns.
* Added bulk AI SEO generation action.
