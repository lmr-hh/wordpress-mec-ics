<?php

namespace LMR\MecIcs;

use DateTime;
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
	 * @var array Known locations from the events calendar.
	 */
	private array $locations;
	/**
	 * @var array Known geos from the events calendar.
	 */
	private array $geos;
	/**
	 * @var array Known organizers from the event calendar.
	 */
	private array $organizers;

	/**
	 * Registers the required hooks to display the feed.
	 */
	public function __construct() {
		add_action( 'query_vars', [ $this, 'registerQueryVars' ] );
		add_action( 'init', [ $this, 'registerFeed' ] );
	}

	/**
	 * Register additional query variables. The additional variables are used to filter the ICS feed.
	 *
	 * @param array $vars The current query vars.
	 *
	 * @return mixed
	 */
	public function registerQueryVars( array $vars ) {
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
	public function registerFeed() {
		$feed = get_option( 'mec-ics-feed-slug' );
		if ( $feed ) {
			add_feed( $feed, [ $this, 'renderICSFeed' ] );
		}
	}


	/**
	 * Loads all known locations from the events calendar and stores them in an ICS compatible format. This populates
	 * the `$this->locations` variable.
	 *
	 * @return void
	 */
	private function loadLocations() {
		$locationTerms = get_terms( 'mec_location' );
		# Known meta fields are: address, latitude, longitude, url, thumbnail

		$this->locations = [];
		foreach ( $locationTerms as $term ) {
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
	 * Loads all known geos from the events calendar and stores them in an ICS compatible format. This populates
	 * the `$this->geos` variable. The geos are extracted from the locations.
	 *
	 * @return void
	 */
	private function loadGeos() {
		$locationTerms = get_terms( 'mec_location' );
		# Known meta fields are: address, latitude, longitude, url, thumbnail
		$this->geos = [];
		foreach ( $locationTerms as $term ) {
			$geo = new Geo();
			$geo->setLatitude( get_term_meta( $term->term_id, 'latitude', true ) );
			$geo->setLongitude( get_term_meta( $term->term_id, 'longitude', true ) );
			if ( $geo->getLatitude() == 0 || $geo->getLongitude() == 0 ) {
				$this->geos[ $term->term_id ] = null;
			} else {
				$this->geos[ $term->term_id ] = $geo;
			}
		}
	}

	/**
	 * Loads all known organizers from the events calendar and stores them in an ICS compatible format. This populates
	 * the `$this->organizers` variable.
	 *
	 * @return void
	 */
	private function loadOrganizers( Formatter $formatter ) {
		$organizerTerms = get_terms( 'mec_organizer' );
		# Known meta fields are: tel, email, url, thumbnail, page_label, facebook, instagram, twitter, linkedin
		$this->organizers = [];

		foreach ( $organizerTerms as $term ) {
			$organizer = new Organizer( $formatter );
			$organizer->setName( $term->name );
			$url       = get_term_meta( $term->term_id, 'url', true );
			$email     = get_term_meta( $term->term_id, 'email', true );
			$telephone = get_term_meta( $term->term_id, 'tel', true );
			if ( $url ) {
				$organizer->setValue( $url );
			} else if ( $email ) {
				$organizer->setValue( "mailto:" . $email );
			} else if ( $telephone ) {
				$organizer->setValue( "tel:" . $telephone );
			}
			$this->organizers[ $term->term_id ] = $organizer;
		}
	}

	/**
	 * Renders the ICS feed.
	 *
	 * @return void
	 */
	public function renderICSFeed() {
		$formatter = new Formatter();
		// Loading these terms beforehand is an optimization. Because the linked
		// terms are also stored as post meta fields we don't need to query the DB
		// for related terms for each event.
		$this->loadLocations();
		$this->loadGeos();
		$this->loadOrganizers( $formatter );

		$calendar = new Calendar();
		$calendar->setProdId( get_option( 'mec-ics-prodid' ) );
		$calendar->setName( get_option( 'mec-ics-feed-name' ) );
		// TODO: Maybe allow setting an image for the whole calendar
		// TODO: Maybe allow setting the calendar's color
		$calendar->setTimezone( wp_timezone() );

		foreach ( $this->fetchFeedEvents() as $event ) {
			$calendar->addEvent( $this->makeCalendarEvent( new Event( $event ), $formatter ) );
			break;
		}

		header( 'Content-Type: text/plain' );
		$calendarExport = new CalendarExport( new CalendarStream, $formatter );
		$calendarExport->addCalendar( $calendar );
		#    $calendarExport->getStreamObject()->setDoImmediateOutput(true);
		echo $calendarExport->getStream();
		exit();
	}

	/**
	 * Loads events from the events calendar database according to the plugin's settings.
	 *
	 * @return array An array of `WP_Post` objects.
	 */
	private function fetchFeedEvents(): array {
		$args = [
			'post_type'   => 'mec-events',
			'post_status' => get_option( 'mec-ics-private-events' ) ? [ "publish", "private" ] : [ "publish" ],
			'orderby'     => 'meta_value',
			'meta_key'    => 'mec_start_date',
			'order'       => 'ASC',
		];

		// Hard event limit
		$limit               = intval( get_option( 'mec-ics-event-limit' ) );
		$args['numberposts'] = $limit <= 0 ? - 1 : $limit;


		// Meta Query (start / end date)
		$meta_query = [];

		/** @noinspection PhpUnhandledExceptionInspection */
		$now    = new DateTime( "now", wp_timezone() );
		$past   = get_option( 'mec-ics-past-events', 90 );
		$future = get_option( 'mec-ics-future-events', 365 );
		if ( $past ) {
			$pastDate = clone $now;
			$pastDate . modify( sprintf( '-%d days', $past ) );
			$meta_query[] = [
				'key'     => 'mec_end_date',
				'compare' => '>=',
				'value'   => $pastDate,
				'type'    => 'DATE',
			];
		}
		if ( $future ) {
			$futureDate = clone $now;
			$futureDate . modify( sprintf( '+%d days', $future ) );
			$meta_query[] = [
				'key'    => 'mec_start_date',
				'copare' => '<=',
				'value'  => $futureDate,
				'type'   => 'DATE',
			];
		}
		if ( $meta_query ) {
			$meta_query['relation'] = 'AND';
			$args['meta_query']     = $meta_query;
		}
		var_dump( $past );

		// Taxonomy Query (category filters)
		$tax_query = [];

		$addTaxFilter = function ( $queryVar, $taxonomy ) use ( &$tax_query ) {
			$filterValues = get_query_var( $queryVar );
			if ( $filterValues ) {
				$tax_query[] = [
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => explode( ',', $filterValues ),
				];
			}
		};
		$addTaxFilter( 'tags', 'post_tag' );
		$addTaxFilter( 'categories', 'mec_category' );
		$addTaxFilter( 'labels', 'mec_label' );
		$addTaxFilter( 'locations', 'mec_location' );
		$addTaxFilter( 'organizers', 'mec_organizer' );

		if ( $tax_query ) {
			$tax_query['relation'] = 'AND';
			$args['tax_query']     = $tax_query;
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
	 * @throws \Jsvrcek\ICS\Exception\CalendarEventException
	 */
	private function makeCalendarEvent( Event $event, Formatter $formatter ): CalendarEvent {
		$calEvent = new CalendarEvent();
		$calEvent->setUid( sprintf( get_option( 'mec-ics-uid-format' ), $event->ID ) );

		// Creation, start and end date
		$calEvent->setCreated( $this->dateFromTimestamp( get_the_date( 'U', $event->ID ) ) );
		$calEvent->setLastModified( $this->dateFromTimestamp( get_the_modified_date( 'U', $event->ID ) ) );
		$datetime = $event->get_datetime();
		$calEvent->setStart( $this->dateFromTimestamp( $datetime['start']['timestamp'] ) );
		$calEvent->setEnd( $this->dateFromTimestamp( $datetime['end']['timestamp'] ) );
		$calEvent->setAllDay( boolval( get_post_meta( $event->ID, 'mec_allday', true ) ) );

		// Simple event metadata
		$calEvent->setSummary( $event->get_title() );
		$calEvent->setDescription( get_the_content( null, false, $event->ID ) );
		$calEvent->setUrl( get_post_meta( $event->ID, 'mec_read_more', true ) );
		if ( empty( $calEvent->getUrl() ) ) {
			$calEvent->setUrl( get_permalink( $event->ID ) );
		}
		$calEvent->setTransp( 'TRANSPARENT' ); // TODO: Maybe make this configurable.

		// Status
		if ( $event->data['post_status'] == "private" ) {
			$calEvent->setClass( "PRIVATE" );
		}
		$status = get_post_meta( $event->ID, 'mec_event_status', true );
		if ( $status == "EventCancelled" ) {
			$calEvent->setStatus( "CANCELLED" );
		}

		// Event location
		$locationID = intval( get_post_meta( $event->ID, 'mec_location_id', true ) );
		if ( array_key_exists( $locationID, $this->locations ) ) {
			$calEvent->addLocation( $this->locations[ $locationID ] );
			$additionalLocationIDs = get_post_meta( $event->ID, 'mec_additional_location_ids', true ) ?: [];
			foreach ( $additionalLocationIDs as $id ) {
				$calEvent->addLocation( $this->locations[ $id ] );
			}

			$geo = $this->geos[ $locationID ];
			if ( $geo ) {
				$calEvent->setGeo( $geo );
			}
		}

		// Organizer
		$organizerID = intval( get_post_meta( $event->ID, 'mec_organizer_id', true ) );
		if ( array_key_exists( $organizerID, $this->organizers ) ) {
			$calEvent->setOrganizer( $this->organizers[ $organizerID ] );
		}

		// Appearance
		$calEvent->setColor( get_post_meta( $event->ID, 'mec_color', true ) );
		$image = get_the_post_thumbnail_url( $event->ID, 'full' );
		if ( $image ) {
			$calEvent->setImage( [
				'VALUE' => 'URI',
				'URI'   => $image,
			] );
		}

		// TODO: Support recurring events

		return $calEvent;
	}

	/**
	 * Converts a UNIX timestamp into a `DateTime` object.
	 *
	 * @param int $timestamp A timestamp value.
	 *
	 * @return DateTime
	 */
	private function dateFromTimestamp( int $timestamp ): DateTime {
		$datetime = new DateTime();
		$datetime->setTimestamp( $timestamp );

		# $datetime->setTimezone(wp_timezone());
		return $datetime;
	}
}
