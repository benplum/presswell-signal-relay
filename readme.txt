=== Presswell Tracking Field for Gravity Forms ===
Contributors: presswell, benplum
Tags: gravity forms, attribution, utm, tracking, hidden field, marketing, click id
Requires at least: 6.1
Tested up to: 6.5
Stable tag: trunk
License: GNU General Public License v2.0 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Capture UTM and click tracking parameters across a visitor session.

== Description ==

Presswell Tracking Field for Gravity Forms adds a hidden "Tracking" field to the form builder so each entry can include campaign attribution metadata.

**Features**

* Adds a Tracking field in Gravity Forms under Advanced Fields
* Captures common attribution parameters and stores them with submissions
* Tracks `utm_source`, `utm_medium`, `utm_campaign`, `utm_content`, `utm_term`, `gclid`, `fbclid`, `msclkid`, `ttclid`, `landing_page`, `landing_query`, and `referrer`
* Persists values in browser localStorage for the current session (1-hour TTL by default)
* Automatically populates hidden field inputs when forms render
* Enforces a single tracking field per form to avoid duplicate data

***Session Attribution Storage***

The plugin reads query parameters from the current URL, merges them with stored values, and keeps them available for later form views during the same visitor session.

***Form Entry Integration***

When a form includes the Tracking field, hidden inputs are generated and populated automatically so data arrives in the entry without custom template code.

= Documentation =

**presswell_gf_tracking_keys( $keys )**

* **$keys** (array) (required) - Ordered list of tracking keys that should be captured and stored with each entry.
* Return an indexed array of string keys.
* These values are used to build the Tracking field's hidden inputs and entry-detail output.

Example:

`
add_filter( 'presswell_gf_tracking_keys', function( $keys ) {
    $keys[] = 'custom_param';
    $keys[] = 'utm_id';
    return $keys;
} );
`

**presswell_gf_tracking_ttl( $ttl )**

* **$ttl** (int) (required) - Session storage lifetime in seconds. Default is `3600` (1 hour).
* Return a positive integer. Invalid values automatically fall back to the default TTL.

Example:

`
add_filter( 'presswell_gf_tracking_ttl', function( $ttl ) {
    return DAY_IN_SECONDS * 7;
} );
`

**Default Tracking Keys**

* `utm_source`
* `utm_medium`
* `utm_campaign`
* `utm_content`
* `utm_term`
* `gclid`
* `fbclid`
* `msclkid`
* `ttclid`
* `landing_page`
* `landing_query`
* `referrer`

== Installation ==

Install via the WordPress plugin installer or manually upload the folder to `wp-content/plugins/`.

1. Activate the plugin.
2. Edit a Gravity Form and add the **Tracking** field from *Advanced Fields*.
3. Publish the form and begin sending traffic with UTM/click parameters.

== Frequently Asked Questions ==

= What parameters are tracked by default? =

UTM parameters (`utm_source`, `utm_medium`, `utm_campaign`, `utm_content`, `utm_term`), common ad click IDs (`gclid`, `fbclid`, `msclkid`, `ttclid`), plus `landing_page`, `landing_query`, and `referrer`.

= Does this require custom JavaScript in my theme? =

No. The plugin injects and populates the field automatically when a form contains the Tracking field.

= How long is attribution data stored? =

By default, one hour. You can change the TTL with the `presswell_gf_tracking_ttl` filter.

= Can I track additional custom parameters? =

Yes. Use the `presswell_gf_tracking_keys` filter to add or remove keys.

== Screenshots ==

1. Gravity Forms editor showing the Tracking field in Advanced Fields
2. Tracking field settings and hidden input mapping
3. Gravity Forms entry details with captured attribution values

== Changelog ==

= 1.0.0 =
* Initial release with Tracking field, session storage, and attribution key filters.
