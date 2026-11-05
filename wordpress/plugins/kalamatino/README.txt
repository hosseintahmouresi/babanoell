=== Kalamatino ===
Contributors: shokrino
Donate link: https://shokrino.ir/
Tags: replace text, replace words, text , words , string ,replace string
Requires at least: 4.7
Tested up to: 6.0.2
Stable tag: 1.0.1
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

You can easily replace a letter or every text with your custom text with kalamatino.

== Description ==

after activating the plugin you can go to kalamatino words list and add your custom text for replacement.

kalamatino name made from "kalamat" word that means "words" in persian.

you can add words to the plugin's list to start replace texts
1. words list on kalamatino 

== Installation ==

1. Upload `kalamatino.zip` to the `/wp-content/plugins/` directory
2. Extract zip file
3. Activate Kalamatino through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= why didn't my text replace? =

double check that your selected text in "from" field should be same as the text you want to replace.
if the source text has for example extra html tags , replacement doesn't work!

currect example:
source: `<h4>i don't like this text</h4>`
from: i don't like this text
to: your custom text
// this will work!

wrong example:
source: `<h4>i <strong>don't like</strong> this text</h4>`
from: i don't like this text
to: your custom text
// this will not work!

== Screenshots ==

1. screenshot-1.jpg

== Changelog ==

= 1.0 =
* plugin released

== Upgrade Notice ==

it works without problem.