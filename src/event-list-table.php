<?php

namespace Crontrol;

require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

class Event_List_Table extends \WP_List_Table {

	protected static $core_hooks;
	protected static $can_edit_files;

	public function __construct() {
		parent::__construct( [
			'singular' => 'crontrol-event',
			'plural'   => 'crontrol-events',
			'ajax'     => false,
			'screen'   => 'crontrol-events',
		] );

		self::$core_hooks     = get_core_hooks();
		self::$can_edit_files = current_user_can( 'edit_files' );
	}

	public function prepare_items() {
		$events   = Event\get();
		$count    = count( $events );
		$per_page = 50;
		$offset   = ( $this->get_pagenum() - 1 ) * $per_page;

		$this->items = array_slice( $events, $offset, $per_page );

		$this->set_pagination_args( array(
			'total_items' => $count,
			'per_page'    => $per_page,
			'total_pages' => ceil( $count / $per_page ),
		) );
	}

	public function get_columns() {
		return array(
			'cb'                  => '<input type="checkbox" />',
			'crontrol_hook'       => __( 'Hook Name', 'wp-crontrol' ),
			'crontrol_args'       => __( 'Arguments', 'wp-crontrol' ),
			'crontrol_actions'    => __( 'Actions', 'wp-crontrol' ),
			'crontrol_next'       => __( 'Next Run', 'wp-crontrol' ),
			'crontrol_recurrence' => __( 'Recurrence', 'wp-crontrol' ),
		);
	}

	protected function get_table_classes() {
		return array( 'widefat', 'striped', $this->_args['plural'] );
	}

	/**
	 * Generates and display row actions links for the list table.
	 *
	 * @param object $event        The event being acted upon.
	 * @param string $column_name Current column name.
	 * @param string $primary     Primary column name.
	 * @return string The row actions HTML.
	 */
	protected function handle_row_actions( $event, $column_name, $primary ) {
		if ( $primary !== $column_name ) {
			return '';
		}

		$links = array();

		if ( ( 'crontrol_cron_job' !== $event->hook ) || self::$can_edit_files ) {
			$link = array(
				'page'     => 'crontrol_admin_manage_page',
				'action'   => 'edit-cron',
				'id'       => rawurlencode( $event->hook ),
				'sig'      => rawurlencode( $event->sig ),
				'next_run' => rawurlencode( $event->time ),
			);
			$link = add_query_arg( $link, admin_url( 'tools.php' ) ) . '#crontrol_form';
			$links[] = "<a href='" . esc_url( $link ) . "'>" . esc_html__( 'Edit', 'wp-crontrol' ) . '</a>';
		}

		$link = array(
			'page'     => 'crontrol_admin_manage_page',
			'action'   => 'run-cron',
			'id'       => rawurlencode( $event->hook ),
			'sig'      => rawurlencode( $event->sig ),
			'next_run' => rawurlencode( $event->time ),
		);
		$link = add_query_arg( $link, admin_url( 'tools.php' ) );
		$link = wp_nonce_url( $link, "run-cron_{$event->hook}_{$event->sig}" );
		$links[] = "<a href='" . esc_url( $link ) . "'>" . esc_html__( 'Run Now', 'wp-crontrol' ) . '</a>';

		if ( ! in_array( $event->hook, self::$core_hooks, true ) && ( ( 'crontrol_cron_job' !== $event->hook ) || self::$can_edit_files ) ) {
			$link = array(
				'page'     => 'crontrol_admin_manage_page',
				'action'   => 'delete-cron',
				'id'       => rawurlencode( $event->hook ),
				'sig'      => rawurlencode( $event->sig ),
				'next_run' => rawurlencode( $event->time ),
			);
			$link = add_query_arg( $link, admin_url( 'tools.php' ) );
			$link = wp_nonce_url( $link, "delete-cron_{$event->hook}_{$event->sig}_{$event->time}" );
			$links[] = "<span class='delete'><a href='" . esc_url( $link ) . "'>" . esc_html__( 'Delete', 'wp-crontrol' ) . '</a></span>';
		}

		return $this->row_actions( $links );
	}

	protected function column_cb( $event ) {
		if ( ! in_array( $event->hook, self::$core_hooks, true ) ) {
			?>
			<label class="screen-reader-text" for="">
				<?php printf( esc_html__( 'Select this row', 'wp-crontrol' ) ); ?>
			</label>
			<?php printf(
				'<input type="checkbox" name="delete[%1$s][%2$s]" value="%3$s" id="">',
				esc_attr( $event->time ),
				esc_attr( rawurlencode( $event->hook ) ),
				esc_attr( $event->sig )
			);
			?>
			<?php
		}
	}

	protected function column_crontrol_hook( $event ) {
		if ( 'crontrol_cron_job' === $event->hook ) {
			if ( ! empty( $event->args['name'] ) ) {
				/* translators: 1: The name of the PHP cron event. */
				return '<em>' . esc_html( sprintf( __( 'PHP Cron (%s)', 'wp-crontrol' ), $event->args['name'] ) ) . '</em>';
			} else {
				return '<em>' . esc_html__( 'PHP Cron', 'wp-crontrol' ) . '</em>';
			}
		} else {
			return esc_html( $event->hook );
		}
	}

	protected function column_crontrol_args( $event ) {
		if ( ! empty( $event->args ) ) {
			$json_options = 0;

			if ( defined( 'JSON_UNESCAPED_SLASHES' ) ) {
				$json_options |= JSON_UNESCAPED_SLASHES;
			}
			if ( defined( 'JSON_PRETTY_PRINT' ) ) {
				$json_options |= JSON_PRETTY_PRINT;
			}

			$args = wp_json_encode( $event->args, $json_options );
		}

		if ( 'crontrol_cron_job' === $event->hook ) {
			return '<em>' . esc_html__( 'PHP Code', 'wp-crontrol' ) . '</em>';
		} else {
			if ( empty( $event->args ) ) {
				return sprintf(
					'<em>%s</em>',
					esc_html__( 'None', 'wp-crontrol' )
				);
			} else {
				return sprintf(
					'<pre>%s</pre>',
					esc_html( $args )
				);
			}
		}
	}

	protected function column_crontrol_actions( $event ) {
		if ( 'crontrol_cron_job' === $event->hook ) {
			return '<em>' . esc_html__( 'WP Crontrol', 'wp-crontrol' ) . '</em>';
		} else {
			$callbacks = array();

			foreach ( get_action_callbacks( $event->hook ) as $callback ) {
				$callbacks[] = '<pre>' . output_callback( $callback ) . '</pre>';
			}

			return implode( '', $callbacks ); // WPCS:: XSS ok.
		}
	}

	protected function column_crontrol_next( $event ) {
		return sprintf(
			'%s (%s)',
			esc_html( get_date_from_gmt( date( 'Y-m-d H:i:s', $event->time ), 'Y-m-d H:i:s' ) ),
			esc_html( time_since( time(), $event->time ) )
		);
	}

	protected function column_crontrol_recurrence( $event ) {
		if ( $event->schedule ) {
			$schedule_name = Event\get_schedule_name( $event );
			if ( is_wp_error( $schedule_name ) ) {
				return sprintf(
					'<span class="dashicons dashicons-warning" style="color:#c00" aria-hidden="true"></span> %s',
					esc_html( $schedule_name->get_error_message() )
				);
			} else {
				return esc_html( $schedule_name );
			}
		} else {
			return esc_html__( 'Non-repeating', 'wp-crontrol' );
		}
	}

	public function no_items() {
		esc_html_e( 'There are currently no scheduled cron events.', 'wp-crontrol' );
	}

}