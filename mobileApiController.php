<?php

class Mobile_Api_Controller
{
    /** @Constructor */
    public function __construct()
    {
        $this->namespace = '/mobileApi/v1';
        $this->resource_name = 'places';
    }

    /** @Register routes */
    public function register_routes()
    {
        register_rest_route($this->namespace, '/'.$this->resource_name, [
            // Here we register the readable endpoint for collections.
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_items'],
                'permission_callback' => [$this, 'get_items_permissions_check'],
            ],
            // Register our schema callback.
            // 'schema' => array( $this, 'get_item_schema' ),
        ]);

        register_rest_route($this->namespace, '/'.$this->resource_name. '/(?P<id>\d+)', [
            // Here we register the readable endpoint for collections.
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_item'],
                'permission_callback' => [$this, 'get_items_permissions_check'],
            ],
            // Register our schema callback.
            // 'schema' => array( $this, 'get_item_schema' ),
        ]);
    }

    /**
     * Check permissions for the posts.
     *
     * @param WP_REST_Request $request current request
     */
    public function get_items_permissions_check($request)
    {
        // Always return true
        return true;
    }

    /**
     * Grabs the five most recent posts and outputs them as a rest response.
     *
     * @param WP_REST_Request $request current request
     */
    public function get_items(WP_REST_Request $request)
    {
        $perPage = $request->get_param('per_page');

        $page = $request->get_param('page');



        $args = [
            'post_per_page' => intval($perPage)?:5,
            'paged' => intval($page)?:1,
            'post_type' => ['places'],
        ];

        $posts = get_posts($args);

        $data = [];
        // $data[] = $args;

        if (empty($posts)) {
            return rest_ensure_response($data);
        }

        foreach ($posts as $post) {
            $response = $this->prepare_item_for_response($post, $request);
            $data[] = $this->prepare_response_for_collection($response);
        }

        // Return all of our comment response data.
        return rest_ensure_response($data);
    }

    /**
     * Grabs the five most recent posts and outputs them as a rest response.
     *
     * @param WP_REST_Request $request current request
     */
    public function get_item($request)
    {
        $id = (int) $request['id'];
        $post = get_post($id);

        if (empty($post)) {
            return rest_ensure_response([]);
        }

        return $this->prepare_item_for_response($post, $request);
        // Return all of our post response data.
    }

    /**
     * Matches the post data to the schema we want.
     *
     * @param WP_Post $post    the comment object whose response is being prepared
     * @param mixed   $request
     */
    public function prepare_item_for_response($post, $request)
    {
        $post_data = [];

        $schema = $this->get_item_schema($request);

        // We are also renaming the fields to more understandable names.
        if (isset($schema['properties']['id'])) {
            $post_data['id'] = (int) $post->ID;
        }

        if (isset($schema['properties']['title'])) {
            $post_data['title'] = apply_filters('the_title', $post->post_title, $post);
        }

        if (isset($schema['properties']['content'])) {
            $post_data['content'] = wp_strip_all_tags(apply_filters('the_content', $post->post_content, $post));
        }

        $acfFields = get_fields($post->ID);

        $post_data['tagline'] = $acfFields['tagline'];
        $post_data['address'] = $acfFields['location_map']['address'];

        // Get all image urls
        $post_data['gallery'] = [];
        foreach ($acfFields['gallery'] as $image) {
            $post_data['gallery'][] = wp_get_attachment_image_url($image);
        }

        $post_data['main_banner'] = wp_get_attachment_image_url($acfFields['main_banner'][0]);
        $post_data['facilities'] = $this->getTermIcons('facilities', $post->ID);
        $post_data['restrictions'] = $this->getTermIcons('restrictions', $post->ID);

        $post_data['directions_links'] = $acfFields['directions_links'];
        $post_data['opening_times'] = wp_strip_all_tags($acfFields['opening_times']['text']);
        $post_data['admission'] = wp_strip_all_tags($acfFields['admission']['text']);
        $post_data['you_may_also_like'] = $acfFields['you_may_also_like']["places"];
        $post_data['location'] = $acfFields["location_map"];
        // $post_data['acf'] = $acfFields;

        return rest_ensure_response($post_data);
    }

    /**
     * Prepare a response for inserting into a collection of responses.
     *
     * This is copied from WP_REST_Controller class in the WP REST API v2 plugin.
     *
     * @param WP_REST_Response $response response object
     *
     * @return array response data, ready for insertion into collection data
     */
    public function prepare_response_for_collection($response)
    {
        if (!($response instanceof WP_REST_Response)) {
            return $response;
        }

        $data = (array) $response->get_data();
        $server = rest_get_server();

        if (method_exists($server, 'get_compact_response_links')) {
            $links = call_user_func([$server, 'get_compact_response_links'], $response);
        } else {
            $links = call_user_func([$server, 'get_response_links'], $response);
        }

        if (!empty($links)) {
            $data['_links'] = $links;
        }

        return $data;
    }

    /**
     * Get our sample schema for a post.
     *
     * @return array The sample schema for a post
     */
    public function get_item_schema()
    {
        if ($this->schema) {
            // Since WordPress 5.3, the schema can be cached in the $schema property.
            return $this->schema;
        }

        $this->schema = [
            // This tells the spec of JSON Schema we are using which is draft 4.
            '$schema' => 'http://json-schema.org/draft-04/schema#',
            // The title property marks the identity of the resource.
            'title' => 'post',
            'type' => 'object',
            // In JSON Schema you can specify object properties in the properties attribute.
            'properties' => [
                'id' => [
                    'description' => esc_html__('Unique identifier for the object.', 'my-textdomain'),
                    'type' => 'integer',
                    'context' => ['view', 'edit', 'embed'],
                    'readonly' => true,
                ],
                'title' => [
                    'description' => esc_html__('The content for the object.', 'my-textdomain'),
                    'type' => 'string',
                ],
                'tagline' => [
                    'description' => esc_html__('The content for the object.', 'my-textdomain'),
                    'type' => 'string',
                ],
                'address' => [
                    'description' => esc_html__('The content for the object.', 'my-textdomain'),
                    'type' => 'string',
                ],
                'content' => [
                    'description' => esc_html__('The content for the object.', 'my-textdomain'),
                    'type' => 'string',
                ],
            ],
        ];

        return $this->schema;
    }

    private function getTermIcons($field, $postId)
    {
        $terms = get_the_terms($postId, $field);
        $result = [];

        foreach ($terms as $term) {
            $processedTerm = new stdClass();
            $processedTerm->name = $term->name;
            $ico = get_field('icon', $field.'_'.$term->term_id);

            if ($ico) {
                if (false !== strpos($ico, 'http')) {
                    $processedTerm->icon = $ico;
                } else {
                    $ico = wp_get_attachment_image_src($ico, 'thumbnail');
                    $processedTerm->icon = $ico[0];
                }
            }

            $result[] = $processedTerm;
        }

        return $result;
    }
}

// Function to register our new routes from the controller.
function prefix_register_my_rest_routes()
{
    $controller = new Mobile_Api_Controller();
    $controller->register_routes();
}

// Accepting zero/one arguments.
// function strip() {
//     ...
//     return 'some value';
// }
// add_filter( 'hook', 'example_callback' );

add_action('rest_api_init', 'prefix_register_my_rest_routes');
