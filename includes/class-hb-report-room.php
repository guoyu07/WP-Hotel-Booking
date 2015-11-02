<?php

/**
 * Report Class
 */
class HB_Report_Room extends HB_Report
{
	public $_title;

	public $_chart_type = 'room';

	public $_rooms = array();

	public $_start_in;

	public $_end_in;

	public $chart_groupby;

	public $_axis_x = array();

	public $_axis_y = array();

	public $_range_start;
	public $_range_end;

	public $_range;

	static $_instance = array();

	public function __construct( $range = null )
	{
		if( ! $range ) return;

		$this->_range = $range;

		if( isset( $_GET['tab'] ) && $_GET['tab'] )
			$this->_chart_type = sanitize_text_field( $_GET['tab'] );

		if( isset( $_GET['room_id'] ) && $_GET['room_id'] )
			$this->_rooms = $_GET['room_id'];

		$this->calculate_current_range( $this->_range );

		$this->_title = sprintf( 'Chart in %s to %s', $this->_start_in, $this->_end_in );
	}

	public function get_rooms()
	{
		global $wpdb;
		$query = $wpdb->prepare( "
				(
					SELECT ID, post_title FROM {$wpdb->posts}
					WHERE
						`post_type` = %s
						AND `post_status` = %s
				)
			", 'hb_room', 'publish' );

		return $wpdb->get_results( $query );
	}

	/**
	 * get all post have post_type = hb_booking
	 * completed > start and < end
	 * @return object
	 */
	public function getOrdersItems()
	{
		global $wpdb;

		if( $this->chart_groupby === 'day' )
		{
			$total = $wpdb->prepare("
			        (
			            SELECT ra.meta_value
			            FROM {$wpdb->postmeta} ra
			            INNER JOIN {$wpdb->posts} r ON ra.post_id = r.ID AND ra.meta_key = %s
			                WHERE r.ID = room_ID
			        )
			    ", '_hb_num_of_rooms');

			$sub_query = array();
			foreach ($this->_rooms as $key => $id) {
				$sub_query[] = ' room_ID = ' . $id;
			}
			$sub_query = implode( ' OR' , $sub_query);

			$query = $wpdb->prepare("
					SELECT booked.ID AS book_item_ID,
					checkin.meta_value as checkindate,
					checkout.meta_value as checkoutdate,
					room_id.meta_value AS room_ID,
					{$total} AS total,
					booking.ID AS book_id
					FROM $wpdb->posts AS booked
					INNER JOIN {$wpdb->postmeta} AS room_id ON room_id.post_id = booked.ID AND room_id.meta_key = %s
					INNER JOIN {$wpdb->postmeta} AS checkin ON checkin.post_id = booked.ID AND checkin.meta_key = %s
					INNER JOIN {$wpdb->postmeta} AS checkout ON checkout.post_id = booked.ID AND checkout.meta_key = %s
					INNER JOIN {$wpdb->postmeta} AS pmb ON pmb.post_id = booked.ID AND pmb.meta_key = %s
					RIGHT JOIN {$wpdb->posts} AS booking ON booking.ID = pmb.meta_value AND booking.post_status = %s
					WHERE
						booked.post_type = %s
						AND ( DATE( from_unixtime( checkin.meta_value ) ) <= %s AND DATE( from_unixtime( checkout.meta_value ) ) >= %s )
						OR ( DATE( from_unixtime( checkin.meta_value ) ) >= %s AND DATE( from_unixtime( checkin.meta_value ) ) <= %s )
						OR ( DATE( from_unixtime( checkout.meta_value ) ) > %s AND DATE( from_unixtime( checkout.meta_value ) ) <= %s )
					HAVING {$sub_query}
				", '_hb_id', '_hb_check_in_date', '_hb_check_out_date', '_hb_booking_id', 'hb-completed', 'hb_booking_item',
					$this->_start_in, $this->_end_in,
					$this->_start_in, $this->_end_in,
					$this->_start_in, $this->_end_in
				);
		}
		else
		{
			$total = $wpdb->prepare("
			        (
			            SELECT ra.meta_value
			            FROM {$wpdb->postmeta} ra
			            INNER JOIN {$wpdb->posts} r ON ra.post_id = r.ID AND ra.meta_key = %s
			                WHERE r.ID = room_ID
			        )
			    ", '_hb_num_of_rooms');

			$sub_query = array();
			foreach ($this->_rooms as $key => $id) {
				$sub_query[] = ' room_ID = ' . $id;
			}
			$sub_query = implode( ' OR' , $sub_query);

			$query = $wpdb->prepare("
					SELECT booked.ID AS book_item_ID,
					checkin.meta_value as checkindate,
					checkout.meta_value as checkoutdate,
					room_id.meta_value AS room_ID,
					{$total} AS total,
					booking.ID AS book_id
					FROM $wpdb->posts AS booked
					INNER JOIN {$wpdb->postmeta} AS room_id ON room_id.post_id = booked.ID AND room_id.meta_key = %s
					INNER JOIN {$wpdb->postmeta} AS checkin ON checkin.post_id = booked.ID AND checkin.meta_key = %s
					INNER JOIN {$wpdb->postmeta} AS checkout ON checkout.post_id = booked.ID AND checkout.meta_key = %s
					INNER JOIN {$wpdb->postmeta} AS pmb ON pmb.post_id = booked.ID AND pmb.meta_key = %s
					RIGHT JOIN {$wpdb->posts} AS booking ON booking.ID = pmb.meta_value AND booking.post_status = %s
					WHERE
						booked.post_type = %s
						AND ( MONTH( from_unixtime( checkin.meta_value ) ) <= MONTH(%s) AND MONTH( from_unixtime( checkout.meta_value ) ) >= MONTH(%s) )
						OR ( MONTH( from_unixtime( checkin.meta_value ) ) >= MONTH(%s) AND MONTH( from_unixtime( checkin.meta_value ) ) <= MONTH(%s) )
						OR ( MONTH( from_unixtime( checkout.meta_value ) ) > MONTH(%s) AND MONTH( from_unixtime( checkout.meta_value ) ) <= MONTH(%s) )
					HAVING {$sub_query}
				", '_hb_id', '_hb_check_in_date', '_hb_check_out_date', '_hb_booking_id', 'hb-completed', 'hb_booking_item',
					$this->_start_in, $this->_end_in,
					$this->_start_in, $this->_end_in,
					$this->_start_in, $this->_end_in
				);
		}

		return $this->parseData( $wpdb->get_results( $query ) );
	}

	public function series()
	{
		if( ! $this->_rooms )
			return;
		return apply_filters( 'tp_hotel_booking_charts', $this->getOrdersItems() );
	}

	public function parseData( $results )
	{

		$series = array();

		$ids = array();
		foreach ($results as $key => $value) {
			if( ! isset( $ids[ $value->room_ID ] ) )
				$ids[$value->room_ID] = $value->total;
		}

		foreach( $ids as $id => $total )
		{
			if( ! isset( $series[ $id ] ) )
			{
				$prepare = array(
						'name'	=> sprintf( __( '%s avaiable', 'tp-hotel-booking' ), get_the_title( $id ) ),
						'data'	=> array(),
						'stack' => $id
					);
				$unavaiable = array(
						'name'	=> sprintf( __( '%s unavaiable', 'tp-hotel-booking' ), get_the_title( $id ) ),
						'data'	=> array(),
						'stack' => $id
					);
			}

			$range = $this->_range_end - $this->_range_start;
			$cache = $this->_start_in;
			for( $i = 0; $i <= $range; $i++ )
			{
				$avaiable = 0;
				if( $this->chart_groupby === 'day' )
				{
					$current_time = strtotime( $this->_start_in ) + 24 * 60 * 60 * $i;
				}
				else
				{
					$reg = $this->_range_start + $i;
					$cache = date( "Y-$reg-01", strtotime( $cache ) );
					$current_time = strtotime( date( "Y-$reg-01", strtotime( $cache ) ) ) ;
				}

				foreach ($results as $k => $v) {

					if( (int)$v->room_ID !== (int)$id )
						continue;

					if( $this->chart_groupby === 'day' )
					{
						$_in = strtotime( date( 'Y-m-d', $v->checkindate ) );
						$_out = strtotime( date( 'Y-m-d', $v->checkoutdate ) );
					}
					else
					{
						$_in = strtotime( date( 'Y-m-1', $v->checkindate ) );
						$_out = strtotime( date( 'Y-m-1', $v->checkoutdate ) );
					}

					if( $current_time >= $_in && $current_time <= $_out )
						$avaiable++;

				}

				$prepare['data'][] = array(
						$current_time * 1000,
						$avaiable
				);
				$unavaiable['data'][] = array(
						$current_time * 1000,
						$total - $avaiable
				);

			}

			$series[] = $prepare;
			$series[] = $unavaiable;

		}

		return $series;

	}

	static function instance( $range = null )
	{
		if( ! $range && ! isset( $_GET['range'] ) )
			$range = '7day';

		if( ! $range && isset( $_GET['range'] ) )
			$range = $_GET['range'];

		if( ! empty( self::$_instance[ $range ] ) )
			return self::$_instance[ $range ];

		return new self( $range );
	}

}

// $GLOBAL['hb_report_room'] = HB_Report_Room::instance();