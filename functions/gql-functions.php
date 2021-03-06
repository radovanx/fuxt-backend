<?php
/*
 * Expose theme screenshot to WP-GQL API
 */
    function whitelist_settings() {
        // Define a field to get Theme screenshot URL
        register_graphql_field('GeneralSettings', 'themeScreenshotUrl', [
            'type' => 'String',
            'description' => __('URL to the active theme screenshot', 'fuxt'),
            'resolve' => function ($root, $args, $context, $info) {
                $theme = wp_get_theme();
                $url = "";
                if ($theme->screenshot) {
                    $url = get_template_directory_uri() . "/" . $theme->screenshot;
                }
                return $url;
            }
        ]);

        // Define a fields to get both WordPress URLs     
	register_graphql_field('GeneralSettings', 'backendUrl', [
		'type' 			=> 'String',
		'description' 	=> __( 'WordPress Address (URL)', 'fuxt' ),
		'resolve' 		=> function( $root, $args, $context, $info ) {
			return get_site_url();
		}
	]);
	register_graphql_field('GeneralSettings', 'frontendUrl', [
		'type' 			=> 'String',
		'description' 	=> __( 'Site Address (URL)', 'fuxt' ),
		'resolve' 		=> function( $root, $args, $context, $info ) {
			return get_home_url();
		}
	]);
    }
    add_action('graphql_init', 'whitelist_settings', 1);


/*
 * Give each content node a field of HTML encoded to play nicely with wp-content Vue component
 * SEE https://github.com/wp-graphql/wp-graphql/issues/1035
 */
    function add_encoded_content_field() {
    	register_graphql_field( 'NodeWithContentEditor', 'encodedContent', [
    		'type'      => 'String',
    		'resolve'   => function( $post ) {
    			$content = get_post( $post->databaseId )->post_content;
    			return !empty( $content ) ?  apply_filters( 'the_content', $content ) : null;
    		}
    	]);
    };
	add_action( 'graphql_register_types', 'add_encoded_content_field');


/*
 * Adds next post node to all the custom Post Types
 */
    function gql_register_next_post() {
        $post_types = WPGraphQL::get_allowed_post_types();

        if (!empty($post_types) && is_array($post_types)) {
            foreach ($post_types as $post_type) {
                $post_type_object = get_post_type_object($post_type);

                // Get the Type name with ucfirst
                $ucfirst = ucfirst($post_type_object->graphql_single_name);

                // Register a new Edge Type
                register_graphql_type('Next' . $ucfirst . 'Edge', [
                    'fields' => [
                        'node' => [
                            'description' => __(
                                'The node of the next item',
                                'wp-graphql'
                            ),
                            'type' => $ucfirst,
                            'resolve' => function ($post_id, $args, $context) {
                                return \WPGraphQL\Data\DataSource::resolve_post_object(
                                    $post_id,
                                    $context
                                );
                            }
                        ]
                    ]
                ]);

                // Register the next{$type} field
                register_graphql_field($ucfirst, 'next' . $ucfirst, [
                    'type' => 'Next' . $ucfirst . 'Edge',
                    'description' => __(
                        'The next post of the current port',
                        'wp-graphql'
                    ),
                    'resolve' => function ($post_object, $args, $context) {
                        // global $post;
                        $post = get_post($post_object->postId);
    					if (is_post_type_hierarchical($post->post_type)) {
    	                    $post_id = get_next_page_id($post);
                        }
                        else {
    	                    $next_post = get_next_post();
    	                    $post_id = $next_post->ID;
                        }
                        return $post_id;
                    }
                ]);
            }
        }
    }
    add_action('graphql_register_types', 'gql_register_next_post');


/*
 * Adds previous post node to all the custom Post Types
 */
    function gql_register_previous_post() {
        $post_types = WPGraphQL::get_allowed_post_types();

        if (!empty($post_types) && is_array($post_types)) {
            foreach ($post_types as $post_type) {
                $post_type_object = get_post_type_object($post_type);

                // Get the Type name with ucfirst
                $ucfirst = ucfirst($post_type_object->graphql_single_name);

                // Register a new Edge Type
                register_graphql_type('Previous' . $ucfirst . 'Edge', [
                    'fields' => [
                        'node' => [
                            'description' => __(
                                'The node of the previous item',
                                'wp-graphql'
                            ),
                            'type' => $ucfirst,
                            'resolve' => function ($post_id, $args, $context) {
                                return \WPGraphQL\Data\DataSource::resolve_post_object(
                                    $post_id,
                                    $context
                                );
                            }
                        ]
                    ]
                ]);

                // Register the next{$type} field
                register_graphql_field($ucfirst, 'previous' . $ucfirst, [
                    'type' => 'Previous' . $ucfirst . 'Edge',
                    'description' => __(
                        'The previous post of the current post',
                        'wp-graphql'
                    ),
                    'resolve' => function ($post_object, $args, $context) {
                        // global $post;
                        $post = get_post($post_object->postId);
                        if (is_post_type_hierarchical($post->post_type)) {
    	                    $post_id = get_previous_page_id($post);
                        }
                        else {
    	                    $prev_post = get_previous_post();
    						$post_id = $prev_post->ID;

                        }
                        return $post_id;
                    }
                ]);
            }
        }
    }
    add_action('graphql_register_types', 'gql_register_previous_post');


/*
 *  Util function for previous page id
 */
    function get_previous_page_id($page) {
    	return get_adjacent_page_id($page, -1);
    }


/*
 * Util function for next page id
 */
    function get_next_page_id($page) {
    	return get_adjacent_page_id($page, 1);
    }


/*
 * Util function for adjacent page id
 *
 * Params: $page -> page object from wordpress
 * Params: $direction -> Integer -1 or 1 indicating next or previous post
 * returns adjacent page id
 */
    function get_adjacent_page_id($page, $direction) {
    	$args = array(
    		'post_type'         => $page->post_type,
    		'order'             => 'ASC',
    		'orderby'           => 'menu_order',
    		'post_parent'       => $page->post_parent,
    		'fields'            => 'ids',
    		'posts_per_page'    => -1
    	);

    	$pages = get_posts($args);
    	$current_key = array_search($page->ID, $pages);
    	$output = 0;

        if( isset($pages[$current_key+$direction]) ) {
    		// Next page exists
    		$output = $pages[$current_key+$direction];
    	}
    	return $output;
    }


/*
 * Allow for more than 100 posts/pages to be returned.
 * There is probably a better way to do what you are doing than changing this!
 */
    function allow_more_posts_per_query($amount, $source, $args, $context, $info) {
        return 300;
    }
    //add_filter( 'graphql_connection_max_query_amount', 'allow_more_posts_per_query', 10, 5);


/*
 * Set the default ordering of quieres in WP-GQL
 */
    function custom_default_where_args($query_args, $source, $args, $context, $info) {

        $post_types = $query_args['post_type'];
        $gql_args = $query_args['graphql_args'];

        if( isset($gql_args['where']) ) {
            // Where args set, so use them
            return $query_args;
        } else if( count($post_types) == 1 && in_array('post', $post_types) ) {
            // Is just Posts, so use defaults
            return $query_args;
        }

        // Is anything else, so set to menu_order
        $query_args['orderby'] = 'menu_order';
        $query_args['order'] = 'ASC';
        return $query_args;
    }
    add_filter( 'graphql_post_object_connection_query_args', 'custom_default_where_args', 10, 5);


/*
 * Extend GraphQL to add a mutation to send emails via the wp_mail() function.
 * SEE https://developer.wordpress.org/reference/functions/wp_mail/ for more info on how each input works.
 * SEE https://docs.wpgraphql.com/extending/mutations/
 */
    use GraphQL\Error\UserError;
    function gql_register_email_mutation() {

    	// Define the input parameters
    	$inputFields = array(
    		'to' => [
    			'type' 			=> ['list_of' => 'String'],
    			'description' 	=> 'Array of email addresses to send email to. Must comply to RFC 2822 format.',
    		],
    		'subject' => [
    			'type' 			=> 'String',
    			'description' 	=> 'Email subject.',
    		],
    		'message' => [
    			'type' 			=> 'String',
    			'description' 	=> 'Message contents.',
    		],
    		'headers' => [
    			'type' 			=> ['list_of' => 'String'],
    			'description' 	=> 'Array of any additional headers. This is how you set BCC, CC and HTML type. See wp_mail() function for more details.'
    		],
    		'trap' => [
    			'type' 			=> 'String',
    			'description' 	=> 'Crude anti-spam measure. This must equal the clientMutationId, otherwise the email will not be sent.'
    		]
    	);

    	// Define the ouput parameters
    	$outputFields = array(
    		'to' => [
    			'type' 			=> ['list_of' => 'String'],
    			'description' 	=> 'Array of email addresses to send email to. Must comply to RFC 2822 format.',
    		],
    		'subject' => [
    			'type' 			=> 'String',
    			'description' 	=> 'Email subject.',
    		],
    		'message' => [
    			'type' 			=> 'String',
    			'description' 	=> 'Message contents.',
    		],
    		'headers' => [
    			'type' 			=> ['list_of' => 'String'],
    			'description' 	=> 'Array of any additional headers. This is how you set BCC, CC and HTML type. See wp_mail() function for more details.'
    		],
    		'sent' => [
    			'type' 			=> 'Boolean',
    			'description' 	=> 'Returns true if the email was sent successfully.',
    			'resolve' => function( $payload, $args, $context, $info ) {
    				return isset( $payload['sent'] ) ? $payload['sent'] : false;
    			}
    		]
    	);

    	// This function processes the submitted data
    	$mutateAndGetPayload = function( $input, $context, $info ) {

    		// Spam honeypot. Make sure that the clientMutationId matches the trap input.
    		if( $input['clientMutationId'] !== $input['trap'] ) {
    			throw new UserError("You got caught in a spam trap");
    		}

    		// Vailidate email before trying to send
    		foreach( $input["to"] as $email ) {
    			if( !is_email($email) ) {
    				throw new UserError("Invalid email address: " . $email);
    			}
    		}

    		// Send email!
    		$input['sent'] = wp_mail( $input['to'], $input['subject'], $input['message'], $input['headers'], $input['attachments'] );

    		return $input;
    	};

    	// Add mutation to WP-GQL now
    	$args = array(
    		'inputFields' 			=> $inputFields,
    		'outputFields'			=> $outputFields,
    		'mutateAndGetPayload'	=> $mutateAndGetPayload
    	);
    	register_graphql_mutation( 'sendEmail', $args);
    }
    //add_action( 'graphql_register_types', 'gql_register_email_mutation');
