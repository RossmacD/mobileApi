<?php
/**

 */ 
class Mobile_Api_Controller {

    /** @Constructor */ 
    public function __construct() {
        $this->namespace = '/mobileApi/v1';
        $this->resource_name = 'places';
    }
 
    /** @Register routes */ 
    public function register_routes() {
        register_rest_route( $this->namespace, '/' . $this->resource_name, array(
            // Here we register the readable endpoint for collections.
            array(
                'methods'   => 'GET',
                'callback'  => array( $this, 'get_items' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
            ),
            // Register our schema callback.
            // 'schema' => array( $this, 'get_item_schema' ),
        ) );
    }
 
    /**
     * Check permissions for the posts.
     *
     * @param WP_REST_Request $request Current request.
     */
    public function get_items_permissions_check( $request ) {
       // Always return true
        return true;
    }
 
    /**
     * Grabs the five most recent posts and outputs them as a rest response.
     *
     * @param WP_REST_Request $request Current request.
     */
    public function get_items( $request ) {
        $args = array(
            'post_per_page' => 5,
            'page' => 1,
            'post_type' => array('places') 
        );

        $posts = get_posts( $args );
 
        $data = array();
 
        if ( empty( $posts ) ) {
            return rest_ensure_response( $data );
        }
 
        foreach ( $posts as $post ) {
            $response = $this->prepare_item_for_response( $post, $request );
            $data[] = $this->prepare_response_for_collection( $response );
        }
 
        // Return all of our comment response data.
        return rest_ensure_response( $data );
    }
 
   
 
    /**
     * Grabs the five most recent posts and outputs them as a rest response.
     *
     * @param WP_REST_Request $request Current request.
     */
    public function get_item( $request ) {
        $id = (int) $request['id'];
        $post = get_post( $id );
 
        if ( empty( $post ) ) {
            return rest_ensure_response( array() );
        }
 
        $response = $this->prepare_item_for_response( $post, $request );
 
        // Return all of our post response data.
        return $response;
    }
 
    /**
     * Matches the post data to the schema we want.
     *
     * @param WP_Post $post The comment object whose response is being prepared.
     */
    public function prepare_item_for_response( $post, $request ) {
        $post_data = array();
 
        $schema = $this->get_item_schema( $request );
 
        // We are also renaming the fields to more understandable names.
        if ( isset( $schema['properties']['id'] ) ) {
            $post_data['id'] = (int) $post->ID;
        }

        if ( isset( $schema['properties']['title'] ) ) {
            $post_data['title'] = apply_filters( 'the_title', $post->post_title, $post );
        }
 
        if ( isset( $schema['properties']['content'] ) ) {
            $post_data['content'] = wp_strip_all_tags(apply_filters( 'the_content', $post->post_content, $post ));
        }

        $acfFields = get_fields($post->ID);

        $post_data["tagline"] = $acfFields["tagline"];
        $post_data["address"] = $acfFields["location_map"]["address"];

        // Get all image urls
        $post_data["gallery"] =array();
        foreach( $acfFields["gallery"] as $image){
            $post_data["gallery"][] = wp_get_attachment_image_url($image);
            // echo $array_values . "<br>";
        }
        
        $post_data["main_banner"] = wp_get_attachment_image_url($acfFields["main_banner"][0]);

        // $post_data["facilities"] = get_sub_field( 'term' , $post->ID );
        
        // if( have_rows('facilities',$post->ID) ):
            //     while ( have_rows('facilities') ) : the_row();
            //         $facility = json_encode (new stdClass);
            //         $facility->term = get_sub_field('term');
            //         $facility->icon = get_sub_field('icon');
            //         $post_data["facilities"][] = $facility;
            //     endwhile;
            // endif;
            
            // $post_data["facilities"][] = get_field( 'term', "facilities" );
            
            $facilities= get_the_terms($post->ID,'facilities');

            $post_data["facilities"] =array();


        foreach($facilities as $term ){
                $facility = json_encode (new stdClass);
                $facility->term  = get_field('term','facilities_' . $term->term_id);
                $facility->icon  = get_field('icon','facilities_' . $term->term_id);
                $post_data["facilities"][] = $facility;
                $post_data["facilities"][] = $term->term_id;
            }     



        $post_data['acf'] = $acfFields;

        return rest_ensure_response( $post_data );
    }
 
    /**
     * Prepare a response for inserting into a collection of responses.
     *
     * This is copied from WP_REST_Controller class in the WP REST API v2 plugin.
     *
     * @param WP_REST_Response $response Response object.
     * @return array Response data, ready for insertion into collection data.
     */
    public function prepare_response_for_collection( $response ) {
        if ( ! ( $response instanceof WP_REST_Response ) ) {
            return $response;
        }
 
        $data = (array) $response->get_data();
        $server = rest_get_server();
 
        if ( method_exists( $server, 'get_compact_response_links' ) ) {
            $links = call_user_func( array( $server, 'get_compact_response_links' ), $response );
        } else {
            $links = call_user_func( array( $server, 'get_response_links' ), $response );
        }
 
        if ( ! empty( $links ) ) {
            $data['_links'] = $links;
        }
 
        return $data;
    }
 
    /**
     * Get our sample schema for a post.
     *
     * @return array The sample schema for a post
     */
    public function get_item_schema() {
        if ( $this->schema ) {
            // Since WordPress 5.3, the schema can be cached in the $schema property.
            return $this->schema;
        }
 
        $this->schema = array(
            // This tells the spec of JSON Schema we are using which is draft 4.
            '$schema'              => 'http://json-schema.org/draft-04/schema#',
            // The title property marks the identity of the resource.
            'title'                => 'post',
            'type'                 => 'object',
            // In JSON Schema you can specify object properties in the properties attribute.
            'properties'           => array(
                'id' => array(
                    'description'  => esc_html__( 'Unique identifier for the object.', 'my-textdomain' ),
                    'type'         => 'integer',
                    'context'      => array( 'view', 'edit', 'embed' ),
                    'readonly'     => true,
                ),
                'title' => array(
                    'description'  => esc_html__( 'The content for the object.', 'my-textdomain' ),
                    'type'         => 'string',
                ),
                'tagline' => array(
                    'description'  => esc_html__( 'The content for the object.', 'my-textdomain' ),
                    'type'         => 'string',
                ),
                'address' => array(
                    'description'  => esc_html__( 'The content for the object.', 'my-textdomain' ),
                    'type'         => 'string',
                ),
                'content' => array(
                    'description'  => esc_html__( 'The content for the object.', 'my-textdomain' ),
                    'type'         => 'string',
                ),
            ),
        );
 
        return $this->schema;
    }
 
    
}
 
// Function to register our new routes from the controller.
function prefix_register_my_rest_routes() {
    $controller = new Mobile_Api_Controller();
    $controller->register_routes();
}
 

// Accepting zero/one arguments.
// function strip() {
//     ...
//     return 'some value';
// }
// add_filter( 'hook', 'example_callback' );


add_action( 'rest_api_init', 'prefix_register_my_rest_routes' );