=== AI Translate For Polylang ===
Contributors: jamesdlow
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=PGV92BZCFTDL4&item_name=Donation%20to%20jameslow%2ecom&currency_code=USD&bn=PP%2dDonationsBF&charset=UTF%2d8
Tags: language, translate, multilingual, translation, polylang
Requires at least: 3.0
Tested up to: 6.6.1
Stable tag: 1.0.9
License: MIT
License URI: https://opensource.org/licenses/MIT

Add auto AI translation caperbility to Polylang using OpenAI/ChatGPT or Anthropic/Claude.

== Description ==
Add auto AI translation caperbility to Polylang using OpenAI/ChatGPT  or Anthropic/Claude.

This plugin connects to OpenAI/ChatGPT (api.openai.com) or Anthropic/Claude (api.anthropic.com) in PHP from the Wordpress admin in order to faciliate the translations. When a Wordpress author has the plugin activated, has entered their OpenAI API key in the settings, and clicks new translation from Polylang, the plugin will send the post title and post content to OpenAI or Anthropic for tranlsation.

== Installation ==

This section describes how to install the plugin and get it working.

1. If not already installed, install and activate the Polylang plugin https://www.wordpress.org/plugins/polylang/
2. Upload entire `ai-translate-polylang` folder to the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Edit AI Translate settings at Languages -> AI Translate
5. Click the translate button on a post or page

== Frequently Asked Questions ==


== Screenshots ==


== Changelog ==

= 1.0.9 =
* Fix PHP 8.X warning

= 1.0.8 =
* Correctly set author in translated post

= 1.0.7 =
* Copy author and dates when translating post

= 1.0.6 =
* Add helper function for programmatic translation in PHP

= 1.0.5 =
* Edit prompt to make sure only the new content is returned

= 1.0.4 =
* Use PageApp for SettingsLib if avaliable

= 1.0.3 =
* Add Claude API
* Add options to translate or clear out prior meta

= 1.0.2 =
* Futher updates for Wordpress plugin standards

= 1.0.1 =
* Updates for Wordpress plugin standards

= 1.0.0 =
* Initital version