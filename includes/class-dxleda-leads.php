<?php
/**
 * Leads Handler Class
 *
 * Manages leads from every supported form plugin. Which tables a lead lives in
 * is decided by DXLEDA_Sources; nothing here names a vendor directly.
 *
 * A lead is identified by the pair (entry_id, source) — entry IDs are only
 * unique within their own form plugin.
 *
 * @package DevXpert_Lead_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queries and updates leads across every supported form-plugin source.
 */
class DXLEDA_Leads {

	/**
	 * Build the UNION that presents every source's entries as one result set
	 * with a uniform shape: entry_id, form_id, date_created, source.
	 *
	 * @param string $only_source Restrict to a single source, or '' for all.
	 * @return string Parenthesised SQL, or '' when no source is available.
	 */
	private static function entries_union( $only_source = '' ) {
		$parts = array();

		foreach ( array_keys( DXLEDA_Sources::available() ) as $slug ) {
			if ( '' !== $only_source && $slug !== $only_source ) {
				continue;
			}

			$table = DXLEDA_Sources::entries_table( $slug );

			if ( ! $table ) {
				continue;
			}

			// Table names come from $wpdb->prefix; $slug is one of our own
			// constants, never user input.
			$sql = "SELECT entry_id, form_id, date_created, '" . esc_sql( $slug ) . "' AS source FROM $table";

			$where = DXLEDA_Sources::entries_where( $slug );

			if ( $where ) {
				$sql .= " WHERE $where";
			}

			$parts[] = $sql;
		}

		if ( empty( $parts ) ) {
			return '';
		}

		return '(' . implode( ' UNION ALL ', $parts ) . ')';
	}

	/**
	 * Get leads with filters
	 *
	 * @param array $args Optional arguments.
	 */
	public static function get_leads( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'form_id'     => 0,
			'source'      => '',
			'status'      => '',
			'page'        => 1,
			'per_page'    => 20,
			'search'      => '',
			'date_from'   => '',
			'date_to'     => '',
			'assigned_to' => 0,
			'orderby'     => 'date_created',
			'order'       => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		// An unrecognised source filter would silently widen to "everything";
		// treat it as "no filter" only when it is genuinely empty.
		$source_filter = '';
		if ( '' !== $args['source'] && DXLEDA_Sources::is_valid( $args['source'] ) ) {
			$source_filter = $args['source'];
		}

		$entries = self::entries_union( $source_filter );

		if ( '' === $entries ) {
			return array(
				'leads'        => array(),
				'total'        => 0,
				'pages'        => 0,
				'current_page' => $args['page'],
			);
		}

		$table_status = $wpdb->prefix . 'dxleda_lead_status';

		// Base query.
		$query = "SELECT e.*,
                         COALESCE(s.status, 'new') as lead_status,
                         s.assigned_to,
                         s.priority,
                         s.lead_source
                  FROM $entries e
                  LEFT JOIN $table_status s
                         ON e.entry_id = s.entry_id AND e.source = s.source
                  WHERE 1=1";

		$query_args = array();

		// Form filter.
		if ( $args['form_id'] > 0 ) {
			$query       .= ' AND e.form_id = %d';
			$query_args[] = $args['form_id'];
		}

		// Status filter.
		if ( ! empty( $args['status'] ) ) {
			if ( 'new' === $args['status'] ) {
				$query .= " AND (s.status IS NULL OR s.status = 'new')";
			} else {
				$query       .= ' AND s.status = %s';
				$query_args[] = $args['status'];
			}
		}

		// Date range filter.
		if ( ! empty( $args['date_from'] ) ) {
			$query       .= ' AND e.date_created >= %s';
			$query_args[] = $args['date_from'] . ' 00:00:00';
		}

		if ( ! empty( $args['date_to'] ) ) {
			$query       .= ' AND e.date_created <= %s';
			$query_args[] = $args['date_to'] . ' 23:59:59';
		}

		// Assigned to filter.
		if ( $args['assigned_to'] > 0 ) {
			$query       .= ' AND s.assigned_to = %d';
			$query_args[] = $args['assigned_to'];
		}

		// Search filter (searches in entry meta). Each source keeps its field
		// values in its own table, so the term is matched per source.
		if ( ! empty( $args['search'] ) ) {
			$search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$clauses     = array();

			foreach ( array_keys( DXLEDA_Sources::available() ) as $slug ) {
				if ( '' !== $source_filter && $slug !== $source_filter ) {
					continue;
				}

				$meta_table = DXLEDA_Sources::meta_table( $slug );

				if ( ! $meta_table ) {
					continue;
				}

				$clauses[]    = "(e.source = '" . esc_sql( $slug ) . "' AND e.entry_id IN (
                    SELECT DISTINCT entry_id FROM $meta_table WHERE meta_value LIKE %s
                ))";
				$query_args[] = $search_term;
			}

			if ( ! empty( $clauses ) ) {
				$query .= ' AND (' . implode( ' OR ', $clauses ) . ')';
			}
		}

		// Count total — wrap in subquery to avoid ONLY_FULL_GROUP_BY issues
		// (the inner SELECT has mixed aggregate + non-aggregate columns)
		// $query is assembled from hardcoded SQL, {$wpdb->prefix} table names and
		// our own source constants; all user values are bound through prepare().
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		if ( ! empty( $query_args ) ) {
			$total = $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM ($query) AS dxleda_count_subq", $query_args )
			);
		} else {
			$total = $wpdb->get_var( "SELECT COUNT(*) FROM ($query) AS dxleda_count_subq" );
		}
        // phpcs:enable

		// Order.
		$allowed_orderby = array( 'date_created', 'entry_id', 'lead_status' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'date_created';
		$order           = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		if ( 'date_created' === $orderby ) {
			$query .= " ORDER BY e.date_created $order";
		} else {
			$query .= " ORDER BY $orderby $order";
		}

		// Pagination.
		$offset       = ( $args['page'] - 1 ) * $args['per_page'];
		$query       .= ' LIMIT %d OFFSET %d';
		$query_args[] = $args['per_page'];
		$query_args[] = $offset;

		// Execute query. See note above: table names come from $wpdb->prefix,
		// all values are bound via $wpdb->prepare().
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		if ( ! empty( $query_args ) ) {
			$rows = $wpdb->get_results( $wpdb->prepare( $query, $query_args ) );
		} else {
			$rows = $wpdb->get_results( $query );
		}
        // phpcs:enable

		// Batch-load meta and feedback counts for all entries, grouped by source,
		// to avoid an N+1 pattern.
		$ids_by_source = array();
		foreach ( $rows as $row ) {
			$ids_by_source[ $row->source ][] = (int) $row->entry_id;
		}

		$meta_map  = array();
		$count_map = array();

		foreach ( $ids_by_source as $slug => $ids ) {
			$meta_map[ $slug ]  = self::get_entry_meta_bulk( $ids, $slug );
			$count_map[ $slug ] = DXLEDA_Feedback::get_feedback_counts( $ids, $slug );
		}

		$leads = array();
		foreach ( $rows as $entry ) {
			$slug = $entry->source;
			$eid  = (int) $entry->entry_id;

			$leads[] = array(
				'entry_id'       => $entry->entry_id,
				'form_id'        => $entry->form_id,
				'source'         => $slug,
				'source_label'   => DXLEDA_Sources::label( $slug ),
				'form_name'      => self::form_name( $entry->form_id, $slug ),
				'date_created'   => $entry->date_created,
				'status'         => $entry->lead_status,
				'assigned_to'    => $entry->assigned_to,
				'priority'       => $entry->priority,
				'lead_source'    => $entry->lead_source,
				'meta'           => isset( $meta_map[ $slug ][ $eid ] ) ? $meta_map[ $slug ][ $eid ] : array(),
				'feedback_count' => isset( $count_map[ $slug ][ $eid ] ) ? $count_map[ $slug ][ $eid ] : 0,
			);
		}

		return array(
			'leads'        => $leads,
			'total'        => intval( $total ),
			'pages'        => ceil( $total / $args['per_page'] ),
			'current_page' => $args['page'],
		);
	}

	/**
	 * Get entry meta data
	 *
	 * @param int    $entry_id Entry ID.
	 * @param string $source Source slug.
	 * @return array<string,mixed>
	 */
	public static function get_entry_meta( $entry_id, $source = DXLEDA_Sources::FORMINATOR ) {
		$map = self::get_entry_meta_bulk( array( $entry_id ), $source );

		return isset( $map[ (int) $entry_id ] ) ? $map[ (int) $entry_id ] : array();
	}

	/**
	 * Bulk-load entry meta for many entries of one source in a single query.
	 *
	 * @param int[]  $entry_ids Entry IDs.
	 * @param string $source Source slug.
	 * @return array<int,array<string,mixed>> Map of entry_id => [meta_key => value].
	 */
	public static function get_entry_meta_bulk( $entry_ids, $source = DXLEDA_Sources::FORMINATOR ) {
		global $wpdb;

		$entry_ids = array_values( array_unique( array_map( 'intval', (array) $entry_ids ) ) );
		if ( empty( $entry_ids ) ) {
			return array();
		}

		$table_meta = DXLEDA_Sources::meta_table( DXLEDA_Sources::sanitize( $source ) );
		if ( ! $table_meta ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $entry_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- table name from $wpdb->prefix; IN() placeholders are built from a count and bound via prepare().
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT entry_id, meta_key, meta_value FROM $table_meta WHERE entry_id IN ($placeholders)",
				$entry_ids
			)
		);
		// phpcs:enable

		$map = array();
		foreach ( $rows as $row ) {
			$map[ (int) $row->entry_id ][ $row->meta_key ] = maybe_unserialize( $row->meta_value );
		}

		return $map;
	}

	/**
	 * Look up the form_id for an entry within its own source.
	 *
	 * @param int    $entry_id Entry ID.
	 * @param string $source Source slug.
	 * @return int
	 */
	private static function lookup_form_id( $entry_id, $source ) {
		global $wpdb;

		$table = DXLEDA_Sources::entries_table( $source );

		// No table for the source, or its form plugin (and therefore its
		// entries table) is absent — nothing to look up.
		if ( ! $table || ! DXLEDA_Sources::is_available( $source ) ) {
			return 0;
		}

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted prefix; entry_id bound via prepare().
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is built from $wpdb->prefix.
				"SELECT form_id FROM $table WHERE entry_id = %d",
				(int) $entry_id
			)
		);
	}

	/**
	 * Update lead status
	 *
	 * @param int    $entry_id Entry ID.
	 * @param string $status Lead status slug.
	 * @param array  $additional Additional.
	 * @param string $source Source slug.
	 * @return bool
	 */
	public static function update_lead_status( $entry_id, $status, $additional = array(), $source = DXLEDA_Sources::FORMINATOR ) {
		global $wpdb;

		// Defence in depth: only ever persist a known status value.
		if ( ! array_key_exists( $status, self::get_statuses() ) ) {
			return false;
		}

		$source   = DXLEDA_Sources::sanitize( $source );
		$entry_id = (int) $entry_id;
		$table    = $wpdb->prefix . 'dxleda_lead_status';

		// Check if record exists.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is built from $wpdb->prefix.
				"SELECT id FROM $table WHERE entry_id = %d AND source = %s",
				$entry_id,
				$source
			)
		);

		$data = array(
			'status'     => $status,
			'updated_at' => current_time( 'mysql' ),
		);

		// Add additional fields if provided.
		if ( ! empty( $additional['assigned_to'] ) ) {
			$data['assigned_to'] = intval( $additional['assigned_to'] );
		}
		if ( ! empty( $additional['priority'] ) ) {
			$data['priority'] = sanitize_text_field( $additional['priority'] );
		}
		if ( ! empty( $additional['lead_source'] ) ) {
			$data['lead_source'] = sanitize_text_field( $additional['lead_source'] );
		}

		if ( $exists ) {
			$result = $wpdb->update(
				$table,
				$data,
				array(
					'entry_id' => $entry_id,
					'source'   => $source,
				)
			);
		} else {
			$data['entry_id']   = $entry_id;
			$data['source']     = $source;
			$data['form_id']    = self::lookup_form_id( $entry_id, $source );
			$data['created_at'] = current_time( 'mysql' );
			$result             = $wpdb->insert( $table, $data );
		}

		// Log activity.
		self::log_activity(
			$entry_id,
			'status_change',
			array(
				'new_status' => $status,
				'user'       => get_current_user_id(),
			),
			$source
		);

		return false !== $result;
	}

	/**
	 * Get lead by entry ID
	 *
	 * @param int    $entry_id Entry ID.
	 * @param string $source Source slug.
	 * @return array|null
	 */
	public static function get_lead( $entry_id, $source = DXLEDA_Sources::FORMINATOR ) {
		global $wpdb;

		$source   = DXLEDA_Sources::sanitize( $source );
		$entry_id = (int) $entry_id;

		$entries = self::entries_union( $source );

		if ( '' === $entries ) {
			return null;
		}

		$table_status = $wpdb->prefix . 'dxleda_lead_status';

		// Table names come from $wpdb->prefix and our own source constants;
		// entry_id is bound via prepare().
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$entry = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT e.*,
                    COALESCE(s.status, 'new') as lead_status,
                    s.assigned_to,
                    s.priority,
                    s.lead_source
             FROM $entries e
             LEFT JOIN $table_status s
                    ON e.entry_id = s.entry_id AND e.source = s.source
             WHERE e.entry_id = %d",
				$entry_id
			)
		);
		// phpcs:enable

		if ( ! $entry ) {
			return null;
		}

		return array(
			'entry_id'     => $entry->entry_id,
			'form_id'      => $entry->form_id,
			'source'       => $entry->source,
			'source_label' => DXLEDA_Sources::label( $entry->source ),
			'form_name'    => self::form_name( $entry->form_id, $entry->source ),
			'date_created' => $entry->date_created,
			'status'       => $entry->lead_status,
			'assigned_to'  => $entry->assigned_to,
			'priority'     => $entry->priority,
			'lead_source'  => $entry->lead_source,
			'meta'         => self::get_entry_meta( $entry_id, $entry->source ),
			'feedback'     => DXLEDA_Feedback::get_feedback( $entry_id, $entry->source ),
		);
	}

	/**
	 * Flatten a lead's submitted fields into readable label/value pairs.
	 *
	 * Shared by every new-lead notification channel (email, Telegram) so the
	 * two cannot drift apart. Empty values are dropped and array values —
	 * checkbox groups, multi-selects — are joined into one string.
	 *
	 * A list of pairs is returned rather than a label-keyed map because two
	 * distinct keys can humanize to the same label ("name-1" and "name_1" both
	 * become "Name"); keying by label would silently drop one of them.
	 *
	 * @param array|null $lead Lead array as returned by get_lead().
	 * @return array<int,array{label:string,value:string}>
	 */
	public static function humanize_fields( $lead ) {
		$fields = array();

		if ( empty( $lead['meta'] ) || ! is_array( $lead['meta'] ) ) {
			return $fields;
		}

		foreach ( $lead['meta'] as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = implode( ', ', $value );
			}

			$value = trim( (string) $value );

			if ( '' === $value ) {
				continue;
			}

			$fields[] = array(
				'label' => self::humanize_key( $key ),
				'value' => $value,
			);
		}

		return $fields;
	}

	/**
	 * Turn a form field key into a human-readable label.
	 *
	 * @param string $key Field key.
	 * @return string
	 */
	private static function humanize_key( $key ) {
		$key = preg_replace( '/-\d+$/', '', (string) $key );      // strip trailing "-1".
		$key = str_replace( array( '-', '_' ), ' ', $key );
		return ucwords( trim( $key ) );
	}

	/**
	 * Get dashboard statistics
	 *
	 * @param int $days Number of days to look back.
	 */
	public static function get_dashboard_stats( $days = 30 ) {
		global $wpdb;

		$entries = self::entries_union();

		if ( '' === $entries ) {
			return array(
				'total_leads'     => 0,
				'status_counts'   => array(),
				'leads_by_day'    => array(),
				'leads_by_form'   => array(),
				'leads_by_source' => array(),
				'conversion_rate' => 0,
				'positive_leads'  => 0,
				'negative_leads'  => 0,
				'new_leads'       => 0,
			);
		}

		$table_status = $wpdb->prefix . 'dxleda_lead_status';
		$date_from    = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

		// Table names come from $wpdb->prefix and our own source constants;
		// the date is bound via prepare().
        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		// Total leads.
		$total_leads = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $entries e WHERE e.date_created >= %s",
				$date_from
			)
		);

		// Leads by status.
		$status_counts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COALESCE(s.status, 'new') as status, COUNT(*) as count
             FROM $entries e
             LEFT JOIN $table_status s
                    ON e.entry_id = s.entry_id AND e.source = s.source
             WHERE e.date_created >= %s
             GROUP BY COALESCE(s.status, 'new')",
				$date_from
			),
			OBJECT_K
		);

		// Leads by day (for chart).
		$leads_by_day = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(e.date_created) as date, COUNT(*) as count
             FROM $entries e
             WHERE e.date_created >= %s
             GROUP BY DATE(e.date_created)
             ORDER BY date ASC",
				$date_from
			)
		);

		// Leads by form.
		$leads_by_form = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT e.form_id, e.source, COUNT(*) as count
             FROM $entries e
             WHERE e.date_created >= %s
             GROUP BY e.form_id, e.source
             ORDER BY count DESC
             LIMIT 10",
				$date_from
			)
		);

		// Leads by source.
		$leads_by_source = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT e.source, COUNT(*) as count
             FROM $entries e
             WHERE e.date_created >= %s
             GROUP BY e.source
             ORDER BY count DESC",
				$date_from
			)
		);

        // phpcs:enable

		// Add form names from the cached map (one API call per source).
		foreach ( $leads_by_form as $item ) {
			$item->form_name    = self::form_name( $item->form_id, $item->source );
			$item->source_label = DXLEDA_Sources::label( $item->source );
		}

		foreach ( $leads_by_source as $item ) {
			$item->source_label = DXLEDA_Sources::label( $item->source );
		}

		// Conversion rate (positive leads / total).
		$positive_count  = isset( $status_counts['positive'] ) ? $status_counts['positive']->count : 0;
		$converted_count = isset( $status_counts['converted'] ) ? $status_counts['converted']->count : 0;
		$conversion_rate = $total_leads > 0 ? round( ( ( $positive_count + $converted_count ) / $total_leads ) * 100, 1 ) : 0;

		return array(
			'total_leads'     => intval( $total_leads ),
			'status_counts'   => $status_counts,
			'leads_by_day'    => $leads_by_day,
			'leads_by_form'   => $leads_by_form,
			'leads_by_source' => $leads_by_source,
			'conversion_rate' => $conversion_rate,
			'positive_leads'  => intval( $positive_count ),
			'negative_leads'  => isset( $status_counts['negative'] ) ? intval( $status_counts['negative']->count ) : 0,
			'new_leads'       => isset( $status_counts['new'] ) ? intval( $status_counts['new']->count ) : intval( $total_leads - array_sum( array_column( (array) $status_counts, 'count' ) ) ),
		);
	}

	/**
	 * Export leads to CSV
	 *
	 * @param int    $form_id Form ID.
	 * @param string $status Lead status slug.
	 * @param string $source Source slug.
	 */
	public static function export_leads_csv( $form_id = 0, $status = '', $source = '' ) {
		$leads_data = self::get_leads(
			array(
				'form_id'  => $form_id,
				'status'   => $status,
				'source'   => $source,
				'per_page' => 10000,
			)
		);

		$leads = $leads_data['leads'];

		if ( empty( $leads ) ) {
			return '';
		}

		// Get all meta keys.
		$all_keys = array();
		foreach ( $leads as $lead ) {
			$all_keys = array_merge( $all_keys, array_keys( $lead['meta'] ) );
		}
		$all_keys = array_unique( $all_keys );

		// Build the CSV in an in-memory stream. php://temp is used deliberately
		// (no file is written to disk); the WP_Filesystem API does not apply here.
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$output = fopen( 'php://temp', 'r+' );

		// Header row.
		$header = array_merge(
			array( 'Entry ID', 'Source', 'Form ID', 'Form Name', 'Date', 'Status', 'Feedback Count' ),
			$all_keys
		);
		fputcsv( $output, $header );

		// Data rows.
		foreach ( $leads as $lead ) {
			$row = array(
				$lead['entry_id'],
				$lead['source_label'],
				$lead['form_id'],
				$lead['form_name'],
				$lead['date_created'],
				$lead['status'],
				$lead['feedback_count'],
			);

			foreach ( $all_keys as $key ) {
				$value = isset( $lead['meta'][ $key ] ) ? $lead['meta'][ $key ] : '';
				if ( is_array( $value ) ) {
					$value = implode( ', ', $value );
				}
				$row[] = $value;
			}

			// Neutralise spreadsheet formula injection before writing.
			$row = array_map( array( __CLASS__, 'csv_escape' ), $row );

			fputcsv( $output, $row );
		}

		rewind( $output );
		$csv = stream_get_contents( $output );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $output );

		return $csv;
	}

	/**
	 * Neutralise CSV/formula injection: a leading =, +, -, @, tab or CR can be
	 * interpreted as a formula by Excel/Sheets. Prefix such values with a
	 * single quote so they are treated as literal text.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function csv_escape( $value ) {
		$value = (string) $value;

		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			$value = "'" . $value;
		}

		return $value;
	}

	/**
	 * Log activity
	 *
	 * @param int    $entry_id Entry ID.
	 * @param string $action Action.
	 * @param array  $details Details.
	 * @param string $source Source slug.
	 * @return int|false
	 */
	public static function log_activity( $entry_id, $action, $details = array(), $source = DXLEDA_Sources::FORMINATOR ) {
		global $wpdb;

		$table = $wpdb->prefix . 'dxleda_activity_log';

		return $wpdb->insert(
			$table,
			array(
				'entry_id'   => (int) $entry_id,
				'source'     => DXLEDA_Sources::sanitize( $source ),
				'user_id'    => get_current_user_id(),
				'action'     => $action,
				'details'    => wp_json_encode( $details ),
				'created_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Cached map of source => [form ID => form name].
	 *
	 * Resolved once per request (one lookup per source) and reused everywhere.
	 * Form IDs are only unique within a source, hence the two-level map.
	 *
	 * @return array<string,array<int,string>>
	 */
	public static function form_names() {
		static $map = null;

		if ( null !== $map ) {
			return $map;
		}

		$map = array();

		foreach ( array_keys( DXLEDA_Sources::available() ) as $slug ) {
			$map[ $slug ] = DXLEDA_Sources::form_names( $slug );
		}

		return $map;
	}

	/**
	 * Display name for one form, falling back to its ID.
	 *
	 * @param int    $form_id Form ID.
	 * @param string $source Source slug.
	 * @return string
	 */
	public static function form_name( $form_id, $source ) {
		$names   = self::form_names();
		$form_id = (int) $form_id;

		if ( isset( $names[ $source ][ $form_id ] ) ) {
			return $names[ $source ][ $form_id ];
		}

		return 'Form #' . $form_id;
	}

	/**
	 * Get available forms as a flat list for dropdowns.
	 *
	 * Each item carries its source, since a form ID alone is ambiguous.
	 *
	 * @return array<int,array{id:int,name:string,source:string,source_label:string}>
	 */
	public static function get_forms() {
		$list = array();

		foreach ( self::form_names() as $slug => $names ) {
			foreach ( $names as $id => $name ) {
				$list[] = array(
					'id'           => $id,
					'name'         => $name,
					'source'       => $slug,
					'source_label' => DXLEDA_Sources::label( $slug ),
				);
			}
		}

		return $list;
	}

	/**
	 * Get the activity log for a single entry, newest first.
	 *
	 * @param int    $entry_id Entry ID.
	 * @param string $source Source slug.
	 * @return array
	 */
	public static function get_activity( $entry_id, $source = DXLEDA_Sources::FORMINATOR ) {
		global $wpdb;

		$table = $wpdb->prefix . 'dxleda_activity_log';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is built from $wpdb->prefix.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.action, a.details, a.created_at, u.display_name AS user_name
             FROM $table a
             LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
             WHERE a.entry_id = %d AND a.source = %s
             ORDER BY a.created_at DESC, a.id DESC",
				intval( $entry_id ),
				DXLEDA_Sources::sanitize( $source )
			)
		);
		// phpcs:enable

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Empty the activity log entirely. Administrators only (enforced by caller).
	 *
	 * @return int Number of rows removed.
	 */
	public static function clear_activity_log() {
		global $wpdb;

		$table = $wpdb->prefix . 'dxleda_activity_log';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- deleting all rows from a plugin-owned table; name from trusted prefix.
		return (int) $wpdb->query( 'DELETE FROM `' . esc_sql( $table ) . '`' );
	}

	/**
	 * Reset every lead back to "new" by clearing the status table.
	 * Administrators only (enforced by caller).
	 *
	 * @return int Number of status rows removed.
	 */
	public static function reset_all_statuses() {
		global $wpdb;

		$table = $wpdb->prefix . 'dxleda_lead_status';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- deleting all rows from a plugin-owned table; name from trusted prefix.
		return (int) $wpdb->query( 'DELETE FROM `' . esc_sql( $table ) . '`' );
	}

	/**
	 * Assign a lead to a user without altering its status.
	 * Creates the status row (status "new") if none exists yet.
	 *
	 * @param int    $entry_id Entry ID.
	 * @param int    $form_id Form ID.
	 * @param int    $user_id User ID.
	 * @param string $source Source slug.
	 * @return bool
	 */
	public static function assign_lead( $entry_id, $form_id, $user_id, $source = DXLEDA_Sources::FORMINATOR ) {
		global $wpdb;

		$table    = $wpdb->prefix . 'dxleda_lead_status';
		$entry_id = intval( $entry_id );
		$user_id  = intval( $user_id );
		$source   = DXLEDA_Sources::sanitize( $source );

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is built from $wpdb->prefix.
				"SELECT id FROM $table WHERE entry_id = %d AND source = %s",
				$entry_id,
				$source
			)
		);

		if ( $exists ) {
			$result = $wpdb->update(
				$table,
				array(
					'assigned_to' => $user_id,
					'updated_at'  => current_time( 'mysql' ),
				),
				array(
					'entry_id' => $entry_id,
					'source'   => $source,
				)
			);
		} else {
			$result = $wpdb->insert(
				$table,
				array(
					'entry_id'    => $entry_id,
					'source'      => $source,
					'form_id'     => intval( $form_id ),
					'status'      => 'new',
					'assigned_to' => $user_id,
					'created_at'  => current_time( 'mysql' ),
					'updated_at'  => current_time( 'mysql' ),
				)
			);
		}

		if ( false !== $result ) {
			self::log_activity( $entry_id, 'assigned', array( 'assigned_to' => $user_id ), $source );
			return true;
		}

		return false;
	}

	/**
	 * Get lead statuses
	 */
	public static function get_statuses() {
		return array(
			'new'       => __( 'New', 'devxpert-lead-dashboard-for-forminator' ),
			'positive'  => __( 'Positive', 'devxpert-lead-dashboard-for-forminator' ),
			'negative'  => __( 'Negative', 'devxpert-lead-dashboard-for-forminator' ),
			'follow_up' => __( 'Follow Up', 'devxpert-lead-dashboard-for-forminator' ),
			'converted' => __( 'Converted', 'devxpert-lead-dashboard-for-forminator' ),
			'closed'    => __( 'Closed', 'devxpert-lead-dashboard-for-forminator' ),
		);
	}
}
