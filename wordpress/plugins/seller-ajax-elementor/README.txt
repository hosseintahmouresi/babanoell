Seller AJAX Search for Elementor
================================

Purpose
-------
Connect an existing Elementor Search widget to a Loop Grid containing the custom post type "seller" and perform AJAX title-only search without a full page refresh.

Expected Elementor configuration
--------------------------------
1) Search widget -> Advanced -> CSS Classes:
   seller-ajax-search

2) Loop Grid -> Advanced -> CSS Classes:
   seller-ajax-results

3) Loop Grid -> Query -> Query ID:
   seller_ajax

4) Loop Grid source / post type:
   seller

5) Any Elementor Taxonomy Filter widgets must point to the same Loop Grid.

How it works
------------
- Elementor's official elementor/query/seller_ajax hook keeps the Loop Grid query on the seller post type.
- When seller_search is present in the URL, the plugin performs a title-only search instead of WordPress' broad content search.
- The frontend script fetches the current page over AJAX and replaces only the Loop Grid's rendered contents.
- Existing Elementor Loop Item templates, dynamic tags, dynamic CSS, and Taxonomy Filter deep-link parameters remain handled by Elementor itself.

Search behavior
---------------
- Searches only post_title of seller posts.
- Multiple words are ANDed (all entered words must be present in the title).
- Persian/Arabic ی/ي and ک/ك variants are handled.
- Exact title matches are ranked first, then titles beginning with the phrase, then other contains matches.

Notes
-----
- This plugin deliberately does not render Loop Item template ID 773 itself; it lets Elementor render the page normally and extracts the resulting Loop Grid. This avoids relying on Elementor Pro's internal loop-renderer APIs.
- If the store name is stored in a custom field instead of the WordPress post title, the PHP query needs to be extended for that meta key.
