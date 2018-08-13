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
    $search = isset( $params[ 'search_text' ] ) ? $params[ 'search_text' ] : '';
    $page = isset( $params[ 'page' ] ) ? $params[ 'page' ] : 1;
    $current_page = $page ? intval( $page ) : 1;

    $has_params = false;

    if( isset( $params[ 'search_text' ] )
       || isset( $params[ 'suburbs' ] )
       || isset( $params[ 'property_types' ] )
       || isset( $params[ 'property_bedroom_max' ] ) 
       || isset($params[ 'property_bedroom_min' ] ) 
       || isset($params[ 'property_price_max' ] ) 
       || isset($params[ 'property_price_min' ] ) ) {
        $has_params = true;
    }

    // Run query
    $sorted_args = array( 
        'post_type'         => 'property',
        'orderby'           => 'date', 
        'order'             => 'DESC',
        'posts_per_page'    => -1,
    );


    $sorted_query = new WP_Query( $sorted_args );

    $properties = [];

    $search_array = isset( $params[ 'search_text' ] ) && $params[ 'search_text' ] != '' ? explode( " ", $params[ 'search_text' ] ) : array();

    if($sorted_query->have_posts()) {

        while($sorted_query->have_posts()) { 
            $sorted_query->the_post(); 

            global $property;

            $add_property = false;

            // Filter Properties
            if( $has_params ) {
                $add_property = check_property_filter( get_the_ID(), $property, $params );
            } else {
                $add_property = true;
            }

            if( $add_property ) {

                // Prepare Property Image Thumbnail
                $attachment_id = get_post_thumbnail_id( get_the_ID() );
                $attachment = array();

                if( $attachment_id ){
                    $attachment = wp_get_attachment_image_src( $attachment_id, 'large' );
                    $attachment['alt'] = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true);
                }

                // Prepare Property Details
                $property_type = get_post_meta( get_the_ID(), 'property_category', true );

                $property_suburb = $property->get_property_meta( 'property_address_suburb' );

                $property_bed = $property->get_property_meta( 'property_bedrooms' ) ? intval( $property->get_property_meta( 'property_bedrooms' ) ) : 0;
                $property_bath = $property->get_property_meta( 'property_bathrooms' ) ? intval( $property->get_property_meta( 'property_bathrooms' ) ) : 0;
                $property_garage = $property->get_property_meta( 'property_garage' ) ? intval( $property->get_property_meta( 'property_garage' ) ) : 0;

                $property_details = array(
                    'bed'   => $property_bed ? $property_bed : 'N/A',
                    'bath'  => $property_bath ? $property_bath : 'N/A',
                    'garage'=> $property_garage ? $property_garage : 'N/A',
                );

                $property_price = $property->get_property_meta( 'property_price_view' );
                $property_price_raw = $property->get_property_meta( 'property_price' );

                $properties[] = array(
                    'thumb_url'         => $attachment[0],
                    'thumb_alt'         => $attachment['alt'],
                    'property_id'       => get_the_ID(),
                    'property_name'     => get_the_title(),
                    'property_desc'     => get_the_excerpt(),
                    'property_suburb'   => $property_suburb,
                    'property_type'     => $property_type,
                    'property_price'    => $property_price,
                    'property_details'  => $property_details,
                    'property_link'     => get_the_permalink(),
                    'add_property'      => $add_property,
                );
            }
        }

        wp_reset_postdata(); 
    }

    $paginated_properties = array_chunk( $properties, 10 );

    $get_page = intval( $current_page ) > 0 ? intval( $current_page ) - 1 : intval( $current_page );

    if( empty( $paginated_properties ) ) {
        $paginated_properties[$get_page] = array();
    }

    // Prepare return data
    $data = array(
        'search_args'       => $search,
        'has_params'        => $has_params,
        'current_page'		=> $current_page,
        'params'		    => $params,
        'paginated'		    => $paginated_properties,
        'properties'		=> $paginated_properties[ $get_page ],
        'max_num_pages'		=> count( $paginated_properties ),
        'total'				=> count( $properties ),
    );

    return new WP_REST_Response( $data, 200 );
}

function check_property_filter ( $property_id, $property, $params ) {

    $search_array = isset( $params[ 'search_text' ] ) && $params[ 'search_text' ] != '' ? explode( " ", $params[ 'search_text' ] ) : array();
    $search_text = isset( $params[ 'search_text' ] ) && $params[ 'search_text' ] != '' ? $params[ 'search_text' ] : '';

    if( isset( $params[ 'suburbs' ] )
       || isset( $params[ 'property_types' ] )
       || isset( $params[ 'property_bedroom_max' ] ) 
       || isset($params[ 'property_bedroom_min' ] ) 
       || isset($params[ 'property_price_max' ] ) 
       || isset($params[ 'property_price_min' ] ) ) {
        $has_params = true;
    } else {
        $has_params = false;
    }
        
    if( $has_params ) {

        // Property Suburbs Filter
        $property_suburb = $property->get_property_meta( 'property_address_suburb' );
        $suburbs = array();

        if( isset( $params[ 'suburbs' ] ) && $params[ 'suburbs' ] != '' ) {
            $suburbs = $params[ 'suburbs' ];
        }

        $suburb_match = params_match( $suburbs, $property_suburb );


        // Property Types Filter
        $property_type = get_post_meta( $property_id, 'property_category', true );
        $types = array();

        if( isset( $params[ 'property_types' ] ) && $params[ 'property_types' ] != '' ) {
            $types = $params[ 'property_types' ];
        }

        $property_type_match = params_match( $types, $property_type );


        // Bed Filter
        $bed_min = isset( $params[ 'property_bedroom_min' ] ) && $params[ 'property_bedroom_min' ] != '' ? intval( $params[ 'property_bedroom_min' ] ) : 1;
        $bed_max = isset( $params[ 'property_bedroom_max' ] ) && $params[ 'property_bedroom_max' ] != '' ? intval( $params[ 'property_bedroom_max' ] ) : PHP_INT_MAX;

        $property_bed = $property->get_property_meta( 'property_bedrooms' ) ? intval( $property->get_property_meta( 'property_bedrooms' ) ) : 0;

        $bed_search = '';
        $bed_search_count = 0;
        $regex = '/(\d*)\sbed(?:$|rooms|room|s)/im';
        if( $search_text ) {
            preg_match_all( $regex, $search_text, $bed_search, PREG_SET_ORDER, 0 );
            if( !empty( $bed_search[0] ) && $bed_search[0][1] ) {
                $bed_search_count = intval( $bed_search[0][1] );
                $bed_match = ( $property_bed == $bed_search_count ) ? true : false;
            } else {
                $bed_match = ( $property_bed >= $bed_min && $property_bed <= $bed_max ) ? true : false;
            }
        } else {
            $bed_match = ( $property_bed >= $bed_min && $property_bed <= $bed_max ) ? true : false;
        }


        // Price Filter
        $price_min = isset( $params[ 'property_price_min' ] ) ? intval( $params[ 'property_price_min' ] ) : 0;
        $price_max = isset( $params[ 'property_price_max' ] ) && $params[ 'property_price_max' ] > 0 ? intval( $params[ 'property_price_max' ] ) : PHP_INT_MAX;

        $property_price_raw = $property->get_property_meta( 'property_price' );

        $price_match = ( $property_price_raw >= $price_min && $property_price_raw <= $price_max ) ? true : false;
        
        if( !empty( $search_array ) ) {
            $search_match = search_match( get_the_ID(), $property, $search_array, $search_text );
        } else {
            $search_match = true;
        }

        if( $suburb_match && $property_type_match && $bed_match && $price_match && $search_match ) {
            return true;
        }
    } else if( !empty( $search_array ) ) {
        return search_match( get_the_ID(), $property, $search_array, $search_text );
    } else {
        return true;
    }

    return false;
}

function search_match( $property_id, $property, $search_array, $search_text ) {
    //    $property_serialized = get_post_meta( $property_id, 'property_index' );
    //    $added = array();

    //    if( !$property_serialized ) {
    // Get property attributes
    $property_title = get_the_title( $property_id );
    
    $property_suburb = $property->get_property_meta( 'property_address_suburb' );
    $property_type = get_post_meta( $property_id, 'property_category', true );

    $property_address_sub_number = get_post_meta($property_id, 'property_address_sub_number', true);
    $property_address_street_number = get_post_meta($property_id, 'property_address_street_number', true);
    $property_address_street = get_post_meta($property_id, 'property_address_street', true);

    if($property_address_sub_number && $property_address_street_number) {
        $property_address_street_number = $property_address_sub_number .'/'. $property_address_street_number;
    }

    $property_address = $property_address_street_number .' '. $property_address_street;
    $property_content = get_the_content( $property_id );

    $property_bed = '';
    $property_bed_count = $property->get_property_meta( 'property_bedrooms' ) ? intval( $property->get_property_meta( 'property_bedrooms' ) ) : 0;

    $bed_search = '';
    $bed_search_count = 0;
    $remove_bed = '';
    $regex = '/(\d*)\sbed(?:$|rooms|room|s)/im';
    if( $search_text ) {
        preg_match_all( $regex, $search_text, $bed_search, PREG_SET_ORDER, 0 );
        if( !empty( $bed_search[0] ) ) {
            $remove_bed = $bed_search[0][0];
        }
    }

    if( $property_bed_count > 0 && $remove_bed == '' ) {
        $property_bed = $property_bed_count . ' bed beds bedroom bedrooms';
    }

    $property_bath = '';
    $property_bath_count = $property->get_property_meta( 'property_bathrooms' ) ? intval( $property->get_property_meta( 'property_bathrooms' ) ) : 0;

    if( $property_bath_count > 0 ) {
        $property_bath = $property_bath_count . ' bath baths bathroom bathrooms';
    }

    $property_garage = '';
    $property_garage_count = $property->get_property_meta( 'property_garage' ) ? intval( $property->get_property_meta( 'property_garage' ) ) : 0;

    if( $property_garage_count > 0 ) {
        $property_garage = $property_garage_count . ' car cars parking spaces garage';
    }

    // Prepare property_index
    $property_index = $property_title . ' ' . $property_suburb . ' ' . $property_type . ' ' . $property_address . ' ' . $property_content . ' ' . $property_bed . ' ' . $property_bath . ' ' . $property_garage;

    // Convert property_index to array
    $property_unserialized = explode( " ", $property_index );

    //        TODO:
    //        if( !empty( $property_unserialized ) ) {
    //            $property_serialized = serialize( $property_unserialized );
    //            add_post_meta( $property_id, 'property_index', $property_serialized );
    //            $added = get_post_meta( $property_id, 'property_index', $property_serialized );
    //        }

    //    } else {
    //        $property_unserialized = unserialize( $property_serialized[0] );
    //    }

    foreach( $search_array as $search_key => $search_value ) {
        if( !in_arrayi( $search_value, $property_unserialized ) ) {
            return false;
        }
    }

    return true;
}

function params_match( $params, $property_var ) {
    if( !empty( $params ) ) {
        return in_array( $property_var, $params );
    }

    return true;
}

function contains( $str, $charToSearch ) {
    $str = strtolower( $str );
    $charToSearch = strtolower( $charToSearch );

    foreach( ( array ) $charToSearch as $char ) {
        if( strpos( $str, $char ) !== FALSE ) {
            return true;
        }
    }
    return false;
}

function in_arrayi( $needle, $haystack ) {
    return in_array( strtolower( $needle ), array_map( 'strtolower', $haystack ) );
}