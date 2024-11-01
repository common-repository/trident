=== Trident ===
Contributors: michelleblanchette
Tags: content protection, locked content, woocommerce, access, conditional content, page access, post access, limit access, dynamic content
Tested up to: 5.4.1
Requires at least: 4.7.1
Stable tag: 1.0.0
Requires PHP: 7.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.txt

Simple, powerful content protection for your site with WooCommerce integration.

== Description ==

Provides simple content protection settings on any post type (posts, pages, custom post types, etc.). Content protection conditions include user states and WooCommerce product ownership _(requires WooCommerce to be active)_.

= Features =

Features for controlling content protection settings include:

- **Access Conditions** - Grant access to content based on users' WooCommerce product ownership and user state. Admins and Super Admins bypass all content protection settings.
- **Protection Inheritance** _(only for hierarchical post types)_ - Select if content protection settings should be used by all descendent posts.
- **Protection Inheritance Overrides** _(only for hierarchical post types)_ - Optionally choose to override inherited settings on select posts. Overrides may be inherited if desired.
- **Redirect URL** - Enter the URL where prohibited users will be redirected. This defaults to the website's home URL if unset. The redirect URL setting may be overridden per post while still inheriting protection conditions.
- **Classic & Gutenberg Compatible** - While using the Gutenberg Block Editor, the Trident Content Protection sidebar panel will appropriately refresh after updating the post.

= Access Conditions =

The following groups of content protection conditions may be set to specify access requirements. Note that Admins and Super Admins bypass content protection to maintain access to the site.

**User States**
- Logged In
- Logged Out
- Editors Only
- Any User

**WooCommerce Product Ownership**
- Condition method _ANY_ (logical OR) or _ALL_ (logical AND)
- WooCommerce products to require ownership

== Installation ==

Trident does not require any special configurations, so it is ready to use after standard plugin installation and activation procedures.

== Changelog ==

= 1.0.0 – 2020-05-14 =
* Initial Release
