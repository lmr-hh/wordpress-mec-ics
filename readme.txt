=== ICS Feed for Modern Events Calendar ===

This plugin publishes an ICS feed containing events from Modern Events Calendar (Pro or Lite).

== Description ==

This plugin publishes an ICS feed that contains all events from the Modern Events Calendar.
Optionally the feed can be filtered by category, tags, labels, locations, and organizers.

After activating the plugin you will see a settings page `M.E. Calendar > ICS Feed` where you can
set up the ICS feed. The only option that must be configured is the `Feed Slug`. After the slug is
set the plugin will show you the URL of the feed (usually something like
`http://example.com/feed/myicsfeed`).

You can then subscribe to the ICS in the calendar program of your choice by adding the feed URL.
Additionally, you can filter events by `categories`, `tags`, `labels`, `locations`, and `organizers`
using GET parameters. For example the URL
`http://example.com/feed/myicsfeed?tags=hello,world&categories=demo` will contain a feed of events
that belong to the category `demo` AND have one or both of the tags `hello` and `world` associated
with them. The other filters work analogously. Multiple filter values are ORed, different filters
are ANDed. All filters use the slugs of their respective taxonomies.
