<?php

/**
 * Class Tribe__Events__Importer__File_Importer
 */
abstract class Tribe__Events__Importer__File_Importer {
	protected $required_fields = array();

	/** @var Tribe__Events__Importer__File_Reader */
	private $reader = null;
	private $map = array();
	private $inverted_map = array();
	private $type = '';
	private $limit = 100;
	private $offset = 0;
	private $errors = array();
	private $updated = 0;
	private $created = 0;
	private $skipped = array();
	private $log = array();

	/**
	 * @var Tribe__Events__Importer__Featured_Image_Uploader
	 */
	protected $featured_image_uploader;

	/**
	 * @param string                         $type
	 * @param Tribe__Events__Importer__File_Reader $file_reader
	 *
	 * @return Tribe__Events__Importer__File_Importer
	 * @throws InvalidArgumentException
	 */
	public static function get_importer( $type, Tribe__Events__Importer__File_Reader $file_reader ) {
		switch ( $type ) {
			case 'events':
				return new Tribe__Events__Importer__File_Importer_Events( $file_reader );
			case 'venues':
				return new Tribe__Events__Importer__File_Importer_Venues( $file_reader );
			case 'organizers':
				return new Tribe__Events__Importer__File_Importer_Organizers( $file_reader );
			default:
				throw new InvalidArgumentException( sprintf( esc_html__( 'No importer defined for %s', 'the-events-calendar' ), $type ) );
		}
	}

	/**
	 * @param Tribe__Events__Importer__File_Reader $file_reader
	 */
	public function __construct( Tribe__Events__Importer__File_Reader $file_reader, Tribe__Events__Importer__Featured_Image_Uploader $featured_image_uploader = null ) {
		$this->reader = $file_reader;
		$this->featured_image_uploader = $featured_image_uploader;
	}

	public function set_map( array $map_array ) {
		$this->map          = $map_array;
		$this->inverted_map = array_flip( $this->map );
	}

	public function set_type( $type ) {
		$this->type = $type;
	}

	public function set_limit( $limit ) {
		$this->limit = (int) $limit;
	}

	public function set_offset( $offset ) {
		$this->offset = (int) $offset;
	}

	public function do_import() {
		$this->reader->set_row( $this->offset );
		for ( $i = 0; $i < $this->limit && ! $this->import_complete(); $i ++ ) {
			set_time_limit( 30 );
			$this->import_next_row();
		}
	}

	public function get_last_completed_row() {
		return $this->reader->get_last_line_number_read() + 1;
	}

	public function import_complete() {
		return $this->reader->at_end_of_file();
	}

	public function get_updated_post_count() {
		return $this->updated;
	}

	public function get_new_post_count() {
		return $this->created;
	}

	public function get_skipped_row_count() {
		return count( $this->skipped );
	}

	public function get_skipped_row_numbers() {
		return $this->skipped;
	}

	public function get_log_messages() {
		return $this->log;
	}

	public function get_required_fields() {
		return $this->required_fields;
	}

	public function import_next_row( $throw = false ) {
		$record = $this->reader->read_next_row();
		$row    = $this->reader->get_last_line_number_read() + 1;
		if ( ! $this->is_valid_record( $record ) ) {
			if ( ! $throw ) {
				$this->log[ $row ] = sprintf( esc_html__( 'Missing required fields in row %d.', 'the-events-calendar', $row ) );
				$this->skipped[]   = $row;

				return false;
			} else {
				throw new RuntimeException( sprintf( 'Missing required fields in row %d', $row ) );
			}
		}
		try {
			$post_id = $this->update_or_create_post( $record );
		} catch ( Exception $e ) {
			$this->log[ $row ] = sprintf( esc_html__( 'Failed to import record in row %d.', 'the-events-calendar' ), $row );
			$this->skipped[] = $row;
		}

		return $post_id;
	}

	protected function update_or_create_post( array $record ) {
		if ( $id = $this->match_existing_post( $record ) ) {
			$this->update_post( $id, $record );
			$this->updated ++;
			$this->log[ $this->reader->get_last_line_number_read() + 1 ] = sprintf( esc_html__( '%s (post ID %d) updated.', 'the-events-calendar' ), get_the_title( $id ), $id );
		} else {
			$id = $this->create_post( $record );
			$this->created ++;
			$this->log[ $this->reader->get_last_line_number_read() + 1 ] = sprintf( esc_html__( '%s (post ID %d) created.', 'the-events-calendar' ), get_the_title( $id ), $id );
		}

		return $id;
	}

	abstract protected function match_existing_post( array $record );

	abstract protected function update_post( $post_id, array $record );

	abstract protected function create_post( array $record );

	protected function is_valid_record( array $record ) {
		foreach ( $this->get_required_fields() as $field ) {
			if ( $this->get_value_by_key( $record, $field ) == '' ) {
				return false;
			}
		}

		return true;
	}

	protected function get_value_by_key( array $record, $key ) {
		if ( ! isset( $this->inverted_map[ $key ] ) ) {
			return '';
		}
		if ( ! isset( $record[ $this->inverted_map[ $key ] ] ) ) {
			return '';
		}

		return $record[ $this->inverted_map[ $key ] ];
	}

	protected function find_matching_post_id( $name, $post_type ) {
		if ( empty( $name ) ) {
			return 0;
		}
		$query_args = array(
			'post_type'        => $post_type,
			'post_status'      => 'publish',
			'post_title'       => $name,
			'fields'           => 'ids',
			'suppress_filters' => false,
		);
		add_filter( 'posts_search', array( $this, 'filter_query_for_title_search' ), 10, 2 );
		$ids = get_posts( $query_args );
		remove_filter( 'posts_search', array( $this, 'filter_query_for_title_search' ), 10 );

		return empty( $ids ) ? 0 : reset( $ids );
	}

	public function filter_query_for_title_search( $search, WP_Query $wp_query ) {
		$title = $wp_query->get( 'post_title' );
		if ( ! empty( $title ) ) {
			global $wpdb;
			$search .= $wpdb->prepare( " AND {$wpdb->posts}.post_title=%s", $title );
		}

		return $search;
	}

	/**
	 * @param string|int $featured_image Either an absolute path to an image or an attachment ID.
	 *
	 * @return Tribe__Events__Importer__Featured_Image_Uploader
	 */
	protected function featured_image_uploader( $featured_image ) {
		// Remove any leading/trailing whitespace (if the string is a URL, extra whitespace
		// could result in URL validation fail)
		if ( is_string( $featured_image ) ) {
			$featured_image = trim( $featured_image );
		}

		return empty( $this->featured_image_uploader )
			? new Tribe__Events__Importer__Featured_Image_Uploader( $featured_image )
			: $this->featured_image_uploader;
	}
}
