<?php
/**
 * This file contains the main plugin logic of generating a CSV feed.
 *
 * @package mec-ics
 */

declare( strict_types=1 );

namespace LMR\MecIcs;

use DateTime;
use Exception;
use Jsvrcek\ICS\Exception\CalendarEventException;
use \MEC\Events\Event;

use \Jsvrcek\ICS\Model\Calendar;
use \Jsvrcek\ICS\Model\CalendarEvent;
use \Jsvrcek\ICS\Model\Description\Location;
use \Jsvrcek\ICS\Model\Description\Geo;
use \Jsvrcek\ICS\Model\Relationship\Organizer;
use \Jsvrcek\ICS\CalendarExport;
use \Jsvrcek\ICS\CalendarStream;
use \Jsvrcek\ICS\Utility\Formatter;

/**
 * This class implements generating the ICS feed.
 */
class Feed {
	/**
	 * Known locations from the events calendar.
	 *
	 * @var array
	 */
	private array $locations;
	/**
	 * Known geos from the events calendar.
	 *
	 * @var array
	 */
	private array $geos;
	/**
	 * Known organizers from the event calendar.
	 *
	 * @var array
	 */
	private array $organizers;

	/**
	 * Registers the required hooks to display the feed.
	 */
	public function __construct() {
		add_action( 'query_vars', [ $this, 'register_query_vars' ] );
		add_action( 'init', [ $this, 'register_feed' ] );
	}

	/**
	 * Register additional query variables. The additional variables are used to filter the ICS
	 * feed.
	 *
	 * @param array $vars The current query vars.
	 *
	 * @return array
	 */
	public function register_query_vars( array $vars ): array {
		$vars[] = 'tags';
		$vars[] = 'categories';
		$vars[] = 'labels';
		$vars[] = 'locations';
		$vars[] = 'organizers';

		return $vars;
	}

	/**
	 * Registers the ICS feed in WordPress according to the plugin settings.
	 *
	 * @return void
	 */
	public function register_feed() {
		$feed = get_option( 'mec-ics-feed-slug' );
		if ( $feed ) {
			add_feed( $feed, [ $this, 'render_ics_feed' ] );
		}
	}


	/**
	 * Loads all known locations from the events calendar and stores them in an ICS compatible
	 * format. This populates the `$this->locations` variable.
	 *
	 * @return void
	 */
	private function load_locations() {
		// Known meta fields are: address, latitude, longitude, url, thumbnail.
		$location_terms = get_terms( 'mec_location' );

		$this->locations = [];
		foreach ( $location_terms as $term ) {
			$location = new Location();
			$name     = $term->name;
			$address  = get_term_meta( $term->term_id, 'address', true );
			if ( $address ) {
				$name .= '\n' . $address;
			}
			$location->setName( $name );
			$url = get_term_meta( $term->term_id, 'url', true );
			if ( $url ) {
				$location->setUri( $url );
			}
			$this->locations[ $term->term_id ] = $location;
		}
	}

	/**
	 * Loads all known geos from the events calendar and stores them in an ICS compatible format.
	 * This populates the `$this->geos` variable. The geos are extracted from the locations.
	 *
	 * @return void
	 */
	private function load_geos() {
		// Known meta fields are: address, latitude, longitude, url, thumbnail.
		$location_terms = get_terms( 'mec_location' );
		$this->geos     = [];
		foreach ( $location_terms as $term ) {
			$geo = new Geo();
			$geo->setLatitude( get_term_meta( $term->term_id, 'latitude', true ) );
			$geo->setLongitude( get_term_meta( $term->term_id, 'longitude', true ) );
			if ( intval( $geo->getLatitude() ) === 0 || intval( $geo->getLongitude() ) === 0 ) {
				$this->geos[ $term->term_id ] = null;
			} else {
				$this->geos[ $term->term_id ] = $geo;
			}
		}
	}

	/**
	 * Loads all known organizers from the events calendar and stores them in an ICS compatible
	 * format. This populates the `$this->organizers` variable.
	 *
	 * @param Formatter $formatter A `Formatter` instance used by the calendar.
	 *
	 * @return void
	 */
	private function load_organizers( Formatter $formatter ) {
		// Known meta fields are: tel, email, url, thumbnail, page_label,
		// facebook, instagram, twitter, linkedin.
		$organizer_terms  = get_terms( 'mec_organizer' );
		$this->organizers = [];

		foreach ( $organizer_terms as $term ) {
			$organizer = new Organizer( $formatter );
			$organizer->setName( $term->name );
			$url       = get_term_meta( $term->term_id, 'url', true );
			$email     = get_term_meta( $term->term_id, 'email', true );
			$telephone = get_term_meta( $term->term_id, 'tel', true );
			if ( $url ) {
				$organizer->setValue( $url );
			} elseif ( $email ) {
				$organizer->setValue( 'mailto:' . $email );
			} elseif ( $telephone ) {
				$organizer->setValue( 'tel:' . $telephone );
			}
			$this->organizers[ $term->term_id ] = $organizer;
		}
	}

	/**
	 * Renders the ICS feed.
	 *
	 * @return void
	 * @throws Exception If the calendar cannot be encoded.
	 */
	public function render_ics_feed() {
		$formatter = new Formatter();
		// Loading these terms beforehand is an optimization. Because the linked
		// terms are also stored as post meta fields we don't need to query the DB
		// for related terms for each event.
		$this->load_locations();
		$this->load_geos();
		$this->load_organizers( $formatter );

		$calendar = new Calendar();
		$calendar->setProdId( get_option( 'mec-ics-prodid' ) );
		$calendar->setName( get_option( 'mec-ics-feed-name' ) );
		// TODO: Maybe allow setting an image for the whole calendar.
		// TODO: Maybe allow setting the calendar's color.
		$calendar->setTimezone( wp_timezone() );

		foreach ( $this->fetch_feed_events() as $event ) {
			$calendar->addEvent( $this->make_calendar_event( new Event( $event ), $formatter ) );
			break;
		}

		header( 'Content-Type: text/plain' );
		$export = new CalendarExport( new CalendarStream(), $formatter );
		$export->addCalendar( $calendar );
		echo $export->getStream(); // phpcs:ignore
		exit();
	}

	/**
	 * Loads events from the events calendar database according to the plugin's settings.
	 *
	 * @return array An array of `WP_Post` objects.
	 * @throws Exception If an encoding error happens.
	 */
	private function fetch_feed_events(): array {
		$args = [
			'post_type'   => 'mec-events',
			'post_status' => get_option( 'mec-ics-private-events' ) ? [
				'publish',
				'private',
			] : [ 'publish' ],
			'orderby'     => 'meta_value',
			'order'       => 'ASC',
		];

		// Hard event limit.
		$limit               = intval( get_option( 'mec-ics-event-limit' ) );
		$args['numberposts'] = $limit <= 0 ? - 1 : $limit;

		// Meta Query (start / end date).
		$meta_query = [];

		$now    = new DateTime( 'now', wp_timezone() );
		$past   = get_option( 'mec-ics-past-events', 90 );
		$future = get_option( 'mec-ics-future-events', 365 );
		if ( $past ) {
			$past_date = clone $now;
			$past_date->modify( sprintf( '-%d days', $past ) );
			$meta_query[] = [
				'key'     => 'mec_end_date',
				'compare' => '>=',
				'value'   => $past_date,
				'type'    => 'DATE',
			];
		}
		if ( $future ) {
			$future_date = clone $now;
			$future_date->modify( sprintf( '+%d days', $future ) );
			$meta_query[] = [
				'key'    => 'mec_start_date',
				'copare' => '<=',
				'value'  => $future_date,
				'type'   => 'DATE',
			];
		}
		if ( $meta_query ) {
			$meta_query['relation'] = 'AND';
			$args['meta_query']     = $meta_query; // phpcs:ignore
		}

		// Taxonomy Query (category filters).
		$tax_query = [];

		$add_tax_filter = function ( $query_var, $taxonomy ) use ( &$tax_query ) {
			$filter_values = get_query_var( $query_var );
			if ( $filter_values ) {
				$tax_query[] = [
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => explode( ',', $filter_values ),
				];
			}
		};
		$add_tax_filter( 'tags', 'post_tag' );
		$add_tax_filter( 'categories', 'mec_category' );
		$add_tax_filter( 'labels', 'mec_label' );
		$add_tax_filter( 'locations', 'mec_location' );
		$add_tax_filter( 'organizers', 'mec_organizer' );

		if ( $tax_query ) {
			$tax_query['relation'] = 'AND';
			$args['tax_query']     = $tax_query; // phpcs:ignore
		}

		return get_posts( $args );
	}

	/**
	 * Converts the specified `$event` into a `CalendarEvent`.
	 *
	 * @param Event     $event     An event from the events calendar.
	 * @param Formatter $formatter A formatter used for the calendar.
	 *
	 * @return CalendarEvent
	 * @throws CalendarEventException If an encoding error occurs.
	 */
	private function make_calendar_event( Event $event, Formatter $formatter ): CalendarEvent {
		$cal_event = new CalendarEvent();
		$cal_event->setUid( sprintf( get_option( 'mec-ics-uid-format' ), $event->ID ) );

		// Creation, start and end date.
		$cal_event->setCreated( $this->date_from_timestamp( get_the_date( 'U', $event->ID ) ) );
		$cal_event->setLastModified(
			$this->date_from_timestamp( get_the_modified_date( 'U', $event->ID ) )
		);
		$datetime = $event->get_datetime();
		$cal_event->setStart( $this->date_from_timestamp( $datetime['start']['timestamp'] ) );
		$cal_event->setEnd( $this->date_from_timestamp( $datetime['end']['timestamp'] ) );
		$cal_event->setAllDay( boolval( get_post_meta( $event->ID, 'mec_allday', true ) ) );

		// Simple event metadata.
		$cal_event->setSummary( $event->get_title() );
		$cal_event->setDescription( get_the_content( null, false, $event->ID ) );
		$cal_event->setUrl( get_post_meta( $event->ID, 'mec_read_more', true ) );
		if ( empty( $cal_event->getUrl() ) ) {
			$cal_event->setUrl( get_permalink( $event->ID ) );
		}
		$cal_event->setTransp( 'TRANSPARENT' ); // TODO: Maybe make this configurable.

		// Post Status.
		if ( 'private' === $event->data['post_status'] ) {
			$cal_event->setClass( 'PRIVATE' );
		}
		$status = get_post_meta( $event->ID, 'mec_event_status', true );
		if ( 'EventCancelled' === $status ) {
			$cal_event->setStatus( 'CANCELLED' );
		}

		// Event location.
		$location_id = intval( get_post_meta( $event->ID, 'mec_location_id', true ) );
		if ( array_key_exists( $location_id, $this->locations ) ) {
			$cal_event->addLocation( $this->locations[ $location_id ] );
			$extra_location_ids = get_post_meta( $event->ID, 'mec_additional_location_ids', true ) ?: []; // phpcs:ignore
			foreach ( $extra_location_ids as $id ) {
				$cal_event->addLocation( $this->locations[ $id ] );
			}

			$geo = $this->geos[ $location_id ];
			if ( $geo ) {
				$cal_event->setGeo( $geo );
			}
		}

		// Organizer.
		$organizer_id = intval( get_post_meta( $event->ID, 'mec_organizer_id', true ) );
		if ( array_key_exists( $organizer_id, $this->organizers ) ) {
			$cal_event->setOrganizer( $this->organizers[ $organizer_id ] );
		}

		// Appearance.
		$cal_event->setColor( get_post_meta( $event->ID, 'mec_color', true ) );
		$image = get_the_post_thumbnail_url( $event->ID, 'full' );
		if ( $image ) {
			$cal_event->setImage( [ 'VALUE' => 'URI', 'URI' => $image ] );
		}

		return $cal_event;
	}

	/**
	 * Converts a UNIX timestamp into a `DateTime` object.
	 *
	 * @param int $timestamp A timestamp value.
	 *
	 * @return DateTime
	 */
	private function date_from_timestamp( int $timestamp ): DateTime {
		$datetime = new DateTime();
		$datetime->setTimestamp( $timestamp );

		return $datetime;
	}
}
