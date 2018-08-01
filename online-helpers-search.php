<?php
/**
 * Plugin Name:       Online Helpers - Smart Property Search
 * Description:		  Site specific API and functionalities. 
 * Version:           1.2.2
 * Author:            Sample
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages
 */

/**
 * Initialize the plugin once loaded.
 */
//add_action( 'plugins_loaded', 'ajax_search_init' );
//
//function ajax_search_init() {
//    
//}

//add_action( 'wp_enqueue_scripts', 'ajax_enqueue_scripts' );
//
//function ajax_enqueue_scripts() {
//    wp_enqueue_script( 'obh-ajax-script', plugin_dir_url( __FILE__ ) . 'js/ajax.js', array( 'jquery' ), '1.0', true );
//}

// Setup custom routes
add_action( 'rest_api_init', 'register_routes' );

function register_routes() {
    register_rest_route( 'obh-property-search/v1', '/properties/', array(
        array(
            'methods' => 'GET',
            'callback' => 'get_properties',
        )
    ) );
}

function get_properties( $request ) {
    // Get the paramaters from the request
    $params = $request->get_params();
    $search = isset($params[ 'search' ]) ? $params[ 'search' ] : '';
    $page = isset($params[ 'page' ]) ? $params[ 'page' ] : 1;
    $current_page = $page ? intval( $page ) : 1;
    
    // Prepare arguments
    $args = array(
        'fields'            => 'ids',
        'orderby'           => 'date', 
        'order'             => 'DESC', 
        'posts_per_page'    => -1,
        'post_type'         => 'property',
        's'                 => $search,
    );
    
    // Prepare search keywords
    $search_terms = preg_split( '/\s+/', str_replace( '-', ' ', $search ),-1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE );
    $search_count = count( $search_terms );
    
    // Initial Search Query (Full phrase)
    $query = new WP_Query( $args );
    $query_ids = $query->posts;
    
    // Search query for multiple keywords
    if( $search_count > 1 ) {
        for( $i = 0; $i < $search_count; $i++ ) {
            $args['s'] = $search_terms[i];
            
            $query_mask = new WP_Query( $args ); 
            $query_ids = array_merge( $query_ids, $query_mask->posts );
        }
    }
    
    // Run new query with merged IDs
    $sorted_args = array( 
        'post__in'          => $query_ids,
        'post_type'         => 'property',
        'orderby'           => 'date', 
        'order'             => 'DESC',
        'posts_per_page'    => 10,
        'paged'             => $current_page,
    );
    
    $sorted_query = new WP_Query( $sorted_args );
    
    // Prepare return data
    $data = array(
        'search_args'       => $search_terms,
        'current_page'		=> $current_page,
        'max_num_pages'		=> $sorted_query->max_num_pages,
        'total'				=> $sorted_query->found_posts,
        'raw_posts'			=> $sorted_query->posts,
        'sorted_args'		=> $sorted_args,
    );
    
    $data['properties'] = [];
    
    if($sorted_query->have_posts()) {
    
        while($sorted_query->have_posts()) { 
            $sorted_query->the_post(); 
            
            global $property;
            
            $attachment_id = get_post_thumbnail_id( get_the_ID() );
            $attachment = array();

            if( $attachment_id ){
                $attachment = wp_get_attachment_image_src( $attachment_id, 'large' );
                $attachment['alt'] = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true);
            }
            
            $property_bed = $property->get_property_meta( 'property_bedrooms' );
            $property_bath = $property->get_property_meta( 'property_bathrooms' );
            $property_garage = $property->get_property_meta( 'property_garage' );
            
            $property_details = array(
                'bed'   => $property_bed ? $property_bed : 'N/A',
                'bath'  => $property_bath ? $property_bath : 'N/A',
                'garage'=> $property_garage ? $property_garage : 'N/A',
            );
            
            $property_price = $property->get_property_meta( 'property_price_view' );
            
            $data['properties'][] = array(
                'thumb_url'         => $attachment[0],
                'thumb_alt'         => $attachment['alt'],
                'property_id'       => get_the_ID(),
                'property_name'     => get_the_title(),
                'property_desc'     => get_the_excerpt(),
                'property_suburb'   => $property->get_property_meta( 'property_address_suburb' ),
                'property_price'    => $property_price,
                'property_details'  => $property_details,
                'property_link'     => get_the_permalink(),
            );
        }

        wp_reset_postdata(); 
    }
    
    return new WP_REST_Response( $data, 200 );
}
