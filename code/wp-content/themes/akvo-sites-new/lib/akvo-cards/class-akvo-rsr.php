<?php

	class AKVO_RSR{
		
		function get_json_data( $data_feed_id ){
			
			$data = do_shortcode('[data_feed name="'.$data_feed_id.'"]'); 		/* Dependancy on the Data Feed Plugin */
			
			return json_decode( str_replace('&quot;', '"', $data) );
		}
		
		/* GET DATA FEEDS FROM THE DATABASE */
		function get_data_feeds(){
			global $wpdb;
			
			$data_feeds = array();
			
			$rows = $wpdb->get_results( 'SELECT df_name FROM ' . $wpdb->prefix . 'data_feeds' );
			
			foreach( $rows as $row ){
				$data_feeds[ $row->df_name ] = $row->df_name;
			}
			
			return $data_feeds;
			
		}
		
		function get_base_url( $akvo_card_options ){
			$base_url = 'http://rsr.akvo.org';
			if($akvo_card_options && array_key_exists('akvoapp', $akvo_card_options)){
				$base_url = $akvo_card_options['akvoapp'];
			}
			return $base_url;
		}
		
		function parse_rsr( $rsr_obj, $akvo_card_options, $akvo_date_format, $type ){
			
			$data = array( 'type-text'	=> 'RSR Project' );
			if( $type == 'update' ){
				$data[ 'type-text' ] = 'RSR Updates';
			}
			
			$base_url = self::get_base_url( $akvo_card_options );								// GET BASE URL
			
			/* PARSING JSON OBJECT OF RSR PROJECT */
			
			/* COMMON RSR OBJECTS */
			if( isset( $rsr_obj->title ) ){
				$data['title'] = $rsr_obj->title;												/* rsr object title */
			}
			if( isset( $rsr_obj->created_at ) ){
				$data['date'] = date( $akvo_date_format, strtotime($rsr_obj->created_at) );		/* rsr object date */
			}
			if( isset( $rsr_obj->absolute_url ) ){
				$data['link'] = $base_url.$rsr_obj->absolute_url;								/* rsr absolute url */
			}
			/* COMMON RSR OBJECTS */
			
			/* RSR OBJECT CONTENT */
			if( $type == 'project' && isset( $rsr_obj->project_plan_summary ) ){
				$data['content'] = truncate( $rsr_obj->project_plan_summary, 130 );				/* rsr project summary */
			}
			elseif( $type == 'update' && isset( $rsr_obj->text ) ){
				$data['content'] = truncate( $rsr_obj->text, 130 );								/* rsr updates text */
			}
			/* RSR OBJECT CONTENT */
			
			/* RSR OBJECT IMAGE */
			if( $type == 'project' && isset( $rsr_obj->current_image ) ){
				$data['img'] = $base_url.$rsr_obj->current_image;
			}
			elseif( $type == 'update' && isset( $rsr_obj->photo ) ){
				if( isset( $rsr_obj->photo->original ) ){
					$data['img'] = $base_url.$rsr_obj->photo->original;
				}
				else{
					$data['img'] = $base_url.$rsr_obj->photo;	
				}
			}
			/* RSR OBJECT IMAGE */
			
			return $data;
			
		}
		
		
	}
	
	global $akvo_rsr;
	$akvo_rsr = new AKVO_RSR;