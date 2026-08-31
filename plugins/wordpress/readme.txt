=== GO AI EZ ===
Contributors: TBD-WPORG-USERNAME
Tags: seo, sitemap, indexnow, performance, automation
Requires at least: 5.6
Tested up to: TBD-TESTED-UP-TO
Stable tag: TBD-STABLE-TAG
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lets GO AI EZ make the website changes you asked for, keeps a log of every one on your own site, and gives you a one-click undo that does not need us.

== Description ==

GO AI EZ writes pages, meta and structured data to your website for you. This plugin is the part that lives on your site.

It is built so that you stay in control of it:

* **You choose who we act as.** GO AI EZ can never do more on your site than the WordPress user you pick, and the plugin refuses any user who can install plugins, edit users or edit files.
* **Every change is written down here, on your site.** Not only in our system — yours.
* **Every change has an Undo button on your own Settings screen.** It works whether or not we are reachable, and it never deletes anything: a page we added is unpublished, and stays in your Pages list as a draft.
* **One button stops us.** "Stop GO AI EZ changing this site" takes effect immediately. Nothing already on your site is undone by it.
* **The plugin never contacts anything.** It answers requests from GO AI EZ and makes none of its own. It has no tracking, no analytics and no phone-home of any kind.
* **The plugin never writes files to your server** and contains no way to run code sent to it.

== External services ==

This plugin does not send data anywhere. It receives requests from GO AI EZ (https://goaiez.com) and answers them, and every one of those requests must be signed with a secret this plugin generated on your own site. If you have not connected the site, nothing is accepted at all.

If you ask GO AI EZ to announce your sitemap to search engines, this plugin serves a small text file from your site so that search engines can check the request came from you. That file is served from your own server; nothing is uploaded anywhere.

== Installation ==

1. Install and activate the plugin.
2. Go to **Settings → GO AI EZ**.
3. Press **Create a connection code** and copy the code into GO AI EZ. It is shown once.
4. Choose the user GO AI EZ should act as. An **Editor** is the right answer for almost every site.

== Frequently Asked Questions ==

= How do I stop it? =

Settings → GO AI EZ → **Stop GO AI EZ changing this site**. That takes effect immediately. Deactivating the plugin also stops everything.

= Will it delete anything? =

No. A page GO AI EZ added is unpublished rather than deleted, and stays in your Pages list under Drafts with everything it said still in it.

= What happens to my change history if I delete the plugin? =

It stays, so that you can still see what was changed and undo it. There is a button on the plugin's screen to delete it if you would rather.

= Does it slow my site down? =

It adds no scripts and no styles to your pages unless you have asked GO AI EZ to make a speed change, and its own settings are not loaded on ordinary page views.

== Changelog ==

= 0.1.0 =
* First release.
