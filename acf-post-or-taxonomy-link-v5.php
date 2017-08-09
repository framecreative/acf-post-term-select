<?php

/**
 * Class acf_field_post_or_taxonomy_link
 *
 */
class acf_field_post_or_taxonomy_link extends acf_field
{


    /*
    *  __construct
    *
    *  This function will setup the field type data
    */
    public function __construct()
    {

        // vars
        $this->name = 'post_or_taxonomy_link';
        $this->label = __("Post or Term Object", 'acf-post-or-taxonomy-link');
        $this->category = 'relational';
        $this->defaults = array(
            'post_type'        => array(),
            'taxonomy'        => array(),
            'allow_null'    => 0,
            'multiple'        => 0,
        );


        // extra
        add_action('wp_ajax_acf/fields/post_or_taxonomy_link/query', [$this, 'ajax_query']);
        add_action('wp_ajax_nopriv_acf/fields/post_or_taxonomy_link/query', [$this, 'ajax_query']);


        // do not delete!
        parent::__construct();
    }


    /*
    *  get_choices
    *
    *  This function will return an array of data formatted for use in a select2 AJAX response
    *
    *  @type	function
    *  @date	15/10/2014
    *  @since	5.0.9
    *
    *  @param	$options (array)
    *  @return	(array)
    */

    public function get_choices($options = array())
    {

        // defaults
        $options = acf_parse_args($options, array(
            'post_id'        => 0,
            's'                => '',
            'lang'            => false,
            'field_key'        => '',
            'paged'            => 1
        ));


        // vars
        $r = [];
        $args = [];


        // paged
        $args['posts_per_page'] = 20;
        $args['paged'] = $options['paged'];


        // load field
        $field = acf_get_field($options['field_key']);

        if (!$field) {
            return false;
        }


        // WPML
        if ($options['lang']) {
            global $sitepress;
            $sitepress->switch_lang($options['lang']);
        }


        // update $args
        if (!empty($field['post_type'])) {
            $args['post_type'] = acf_get_array($field['post_type']);
        } else {
            $args['post_type'] = acf_get_post_types();
        }



        // search
        if ($options['s']) {
            $args['s'] = $options['s'];
        }


        // filters TODO update
        //		$args = apply_filters('acf/fields/page_link/query', $args, $field, $options['post_id']);
        //		$args = apply_filters('acf/fields/page_link/query/name=' . $field['name'], $args, $field, $options['post_id'] );
        //		$args = apply_filters('acf/fields/page_link/query/key=' . $field['key'], $args, $field, $options['post_id'] );


        // add archives to $r
        if ($args['paged'] == 1) {
            $archives = array();
            $archives[] = array(
                'id'    => home_url(),
                'text'    => home_url()
            );

            foreach ($args['post_type'] as $post_type) {
                $archive_link = get_post_type_archive_link($post_type);

                if ($archive_link) {
                    $archives[] = array(
                        'id'    => $archive_link,
                        'text'    => $archive_link
                    );
                }
            }


            // search
            if (!empty($args['s'])) {
                foreach (array_keys($archives) as $i) {
                    if (strpos($archives[$i]['text'], $args['s']) === false) {
                        unset($archives[$i]);
                    }
                }

                $archives = array_values($archives);
            }


            if (!empty($archives)) {
                $r[] = array(
                    'text'        => __('Archives', 'acf'),
                    'children'    => $archives
                );
            }
        }



        // get posts grouped by post type
        $groups = acf_get_grouped_posts($args);

        if (!empty($groups)) {
            foreach (array_keys($groups) as $group_title) {

                // vars
                $posts = acf_extract_var($groups, $group_title);
                $titles = array();


                // data
                $data = array(
                    'text'        => $group_title,
                    'children'    => array()
                );


                foreach (array_keys($posts) as $post_id) {

                    // override data
                    $posts[ $post_id ] = $this->get_post_title($posts[ $post_id ], $field, $options['post_id']);
                };


                // order by search
                if (!empty($args['s'])) {
                    $posts = acf_order_by_search($posts, $args['s']);
                }


                // append to $data
                foreach (array_keys($posts) as $post_id) {
                    $data['children'][] = array(
                        'id'    => $this->get_value_from_object(get_post($post_id)),
                        'text'    => $posts[ $post_id ]
                    );
                }


                // append to $r
                $r[] = $data;
            }
        }



        // get terms


        $args = array( 'hide_empty'    => false );

        if ($options['s']) {
            $args['search'] = $options['s'];
        }


        foreach ($this->get_taxonomies($field) as $taxonomy) {
            $tax_obj = get_taxonomy($taxonomy);

            $terms = get_terms($taxonomy, $args);

            // data
            $data = array(
                'text'        => $tax_obj->labels->singular_name,
                'children'    => array()
            );


            // sort into hierachial order!
            if (is_taxonomy_hierarchical($taxonomy)) {

                // get parent
                $parent = acf_maybe_get($args, 'parent', 0);
                $parent = acf_maybe_get($args, 'child_of', $parent);


                // this will fail if a search has taken place because parents wont exist
                if (empty($args['search'])) {
                    $terms = _get_term_children($parent, $terms, $taxonomy);
                }
            }


            if ($terms) {
                foreach ($terms as $term) {
                    // add to json
                    $data['children'][] = array(
                        'id'    => $this->get_value_from_object($term),
                        'text'    => $this->get_term_title($term, $field)
                    );
                }



                // append to $r
                $r[] = $data;
            }
        }





        // return
        return $r;
    }



    /*
    *  ajax_query
    *
    *  description
    *
    *  @type	function
    *  @date	24/10/13
    *  @since	5.0.0
    *
    *  @param	$post_id (int)
    *  @return	$post_id (int)
    */

    public function ajax_query()
    {

        // validate
        if (empty($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'acf_nonce')) {
            die();
        }


        // get choices
        $choices = $this->get_choices($_POST);


        // validate
        if (!$choices) {
            die();
        }


        // return JSON
        echo json_encode($choices);
        die();
    }


    /*
    *  get_post_title
    *
    *  This function returns the HTML for a result
    *
    *  @type	function
    *  @date	1/11/2013
    *  @since	5.0.0
    *
    *  @param	$post (object)
    *  @param	$field (array)
    *  @param	$post_id (int) the post_id to which this value is saved to
    *  @return	(string)
    */

    public function get_post_title($post, $field, $post_id = 0)
    {

        // get post_id
        if (!$post_id) {
            $form_data = acf_get_setting('form_data');

            if (!empty($form_data['post_id'])) {
                $post_id = $form_data['post_id'];
            } else {
                $post_id = get_the_ID();
            }
        }



        // load post if given an ID
        if (is_numeric($post)) {
            $post = get_post($post);
        }


        // title
        $title = get_the_title($post->ID);


        // empty
        if ($title === '') {
            $title = __('(no title)', 'acf');
        }


        // ancestors
        if ($post->post_type != 'attachment') {
            $ancestors = get_ancestors($post->ID, $post->post_type);

            if (!empty($ancestors)) {
                $ancestors_title = '';

                foreach (array_reverse($ancestors) as $ancestor_id) {
                    $ancestors_title .= get_the_title($ancestor_id) . ' / ';
                }

                $title = $ancestors_title . $title;
            }
        }


        // status
        if (get_post_status($post->ID) != "publish") {
            $title .= ' (' . get_post_status($post->ID) . ')';
        }



        // filters
        // TODO update
        //		$title = apply_filters('acf/fields/page_link/result', $title, $post, $field, $post_id);
        //		$title = apply_filters('acf/fields/page_link/result/name=' . $field['_name'], $title, $post, $field, $post_id);
        //		$title = apply_filters('acf/fields/page_link/result/key=' . $field['key'], $title, $post, $field, $post_id);


        // return
        return $title;
    }



    /*
    *  get_term_title
    *
    *  This function returns the HTML for a result
    *
    *  @type	function
    *  @date	1/11/2013
    *  @since	5.0.0
    *
    *  @param	$post (object)
    *  @param	$field (array)
    *  @param	$post_id (int) the post_id to which this value is saved to
    *  @return	(string)
    */

    public function get_term_title($term, $field, $post_id = 0)
    {

        // get post_id
        if (!$post_id) {
            $form_data = acf_get_setting('form_data');

            if (! empty($form_data['post_id'])) {
                $post_id = $form_data['post_id'];
            } else {
                $post_id = get_the_ID();
            }
        }


        // vars
        $title = '';


        // ancestors
        $ancestors = get_ancestors($term->term_id, $term->taxonomy);

        if (!empty($ancestors)) {
            foreach (array_reverse($ancestors) as $ancestor_id) {
                $ancestor = get_term($ancestor_id, $term->taxonomy);
                $title .= $ancestor->name . ' / ';
            }
        }


        // title
        $title .= $term->name;


        // filters
        // TODO
        //		$title = apply_filters('acf/fields/taxonomy/result', $title, $term, $field, $post_id);
        //		$title = apply_filters('acf/fields/taxonomy/result/name=' . $field['_name'] , $title, $term, $field, $post_id);
        //		$title = apply_filters('acf/fields/taxonomy/result/key=' . $field['key'], $title, $term, $field, $post_id);


        // return
        return $title;
    }



    /**
     * Extract the object from the stored value
     *
     * @param $value array
     *
     * @return mixed
     */
    public function get_object_from_value($value)
    {
        if (! $value) {
            return;
        }

        if (strstr($value, '|')) {
            list($type, $id) = explode('|', $value);

            if ($type == 'post') {
                return get_post($id);
            } else {
                if (taxonomy_exists($type)) {
                    return get_term($id, $type);
                }
            }
        }

        return $value;
    }



    /**
     * @param $object (post or term object)
     *
     * @return string|false
     */
    public function get_value_from_object($object)
    {
        if (is_object($object)) {
            if ($object instanceof WP_Post) {
                return 'post|' . $object->ID;
            } elseif (isset($object->term_id)) {
                return $object->taxonomy . '|' . $object->term_id;
            }
        } elseif (is_string($object)) {
            return $object;
        }

        return false;
    }



    /*
    *  get_posts
    *
    *  This function will return an array of posts for a given field value
    *
    *  @type	function
    *  @date	13/06/2014
    *  @since	5.0.0
    *
    *  @param	$value (array)
    *  @return	$value
    */

    public function get_posts($value)
    {

        // force value to array
        $value = acf_get_array($value);


        // get selected post ID's
        $post__in = array();

        foreach (array_keys($value) as $k) {
            if (is_numeric($value[ $k ])) {

                // convert to int
                $value[ $k ] = intval($value[ $k ]);


                // append to $post__in
                $post__in[] = $value[ $k ];
            }
        }


        // bail early if no posts
        if (empty($post__in)) {
            return $value;
        }


        // get posts
        $posts = acf_get_posts(array(
            'post__in' => $post__in,
        ));


        // override value with post
        $return = array();


        // append to $return
        foreach ($value as $k => $v) {
            if (is_numeric($v)) {

                // find matching $post
                foreach ($posts as $post) {
                    if ($post->ID == $v) {
                        $return[] = $post;
                        break;
                    }
                }
            } else {
                $return[] = $v;
            }
        }


        // return
        return $return;
    }


    /*
    *  render_field()
    *
    *  Create the HTML interface for your field
    *
    *  @param	$field - an array holding all the field's data
    *
    *  @type	action
    *  @since	3.6
    *  @date	23/01/13
    */

    public function render_field($field)
    {

        // Change Field into a select
        $field['type'] = 'select';
        $field['ui'] = 1;
        $field['ajax'] = 1;
        $field['choices'] = array();

        // populate choices if value exists
        if (!empty($field['value'])) {
            // get saved value object
            $object = $this->get_object_from_value($field['value']);

            // set choices
            if ($object) {
                if ($object instanceof WP_Post) {
                    $field['choices'][ $field['value'] ] = $this->get_post_title($object->ID, $field);
                } elseif (isset($object->term_id)) {
                    $field['choices'][ $field['value'] ] = $this->get_term_title($object, $field);
                } else {
                    // allow for string archive links
                    $field['choices'][ $field['value'] ] = $field['value'];
                }
            }
        }

        // render
        acf_render_field($field);
    }


    /*
    *  render_field_settings()
    *
    *  Create extra options for your field. This is rendered when editing a field.
    *  The value of $field['name'] can be used (like bellow) to save extra data to the $field
    *
    *  @type	action
    *  @since	3.6
    *  @date	23/01/13
    *
    *  @param	$field	- an array holding all the field's data
    */

    public function render_field_settings($field)
    {

        // post_type
        acf_render_field_setting($field, array(
            'label'            => __('Filter by Post Type', 'acf'),
            'instructions'    => '',
            'type'            => 'select',
            'name'            => 'post_type',
            'choices'        => acf_get_pretty_post_types(),
            'multiple'        => 1,
            'ui'            => 1,
            'allow_null'    => 1,
            'placeholder'    => __("All post types", 'acf'),
        ));


        // taxonomy
        acf_render_field_setting($field, array(
            'label'            => __('Filter by Taxonomy', 'acf'),
            'instructions'    => '',
            'type'            => 'select',
            'name'            => 'taxonomy',
            'choices'        => acf_get_taxonomies(),
            'multiple'        => 1,
            'ui'            => 1,
            'allow_null'    => 1,
            'placeholder'    => __("All taxonomies", 'acf'),
        ));


        // allow_null
        acf_render_field_setting($field, array(
            'label'            => __('Allow Null?', 'acf'),
            'instructions'    => '',
            'type'            => 'radio',
            'name'            => 'allow_null',
            'choices'        => array(
                1                => __("Yes", 'acf'),
                0                => __("No", 'acf'),
            ),
            'layout'    =>    'horizontal',
        ));


        // multiple
        acf_render_field_setting($field, array(
            'label'            => __('Select multiple values?', 'acf'),
            'instructions'    => '',
            'type'            => 'radio',
            'name'            => 'multiple',
            'choices'        => array(
                1                => __("Yes", 'acf'),
                0                => __("No", 'acf'),
            ),
            'layout'    =>    'horizontal',
        ));
    }




    /*
    *  format_value()
    *
    *  This filter is appied to the $value after it is loaded from the db and before it is returned to the template
    *
    *  @type	filter
    *  @since	3.6
    *  @date	23/01/13
    *
    *  @param	$value (mixed) the value which was loaded from the database
    *  @param	$post_id (mixed) the $post_id from which the value was loaded
    *  @param	$field (array) the field array holding all the field options
    *
    *  @return	$value (mixed) the modified value
    */

    public function format_value($value, $post_id, $field)
    {

        // ACF4 null
        if ($value === 'null') {
            return false;
        }


        // bail early if no value
        if (empty($value)) {
            return $value;
        }

        return $this->get_object_from_value($value);
    }


    /*
    *  update_value()
    *
    *  This filter is appied to the $value before it is updated in the db
    *
    *  @type	filter
    *  @since	3.6
    *  @date	23/01/13
    *
    *  @param	$value - the value which will be saved in the database
    *  @param	$post_id - the $post_id of which the value will be saved
    *  @param	$field - the field array holding all the field options
    *
    *  @return	$value - the modified value
    */

    public function update_value($value, $post_id, $field)
    {
        // validate
        if (empty($value)) {
            return $value;
        }

        // format
        if (is_array($value)) {

            // array
            foreach ($value as $k => $v) {

                // object?
                if (is_object($v) && isset($v->ID)) {
                    $value[ $k ] = $v->ID;
                }
            }


            // save value as strings, so we can clearly search for them in SQL LIKE statements
            $value = array_map('strval', $value);
        } elseif (is_object($value) && isset($value->ID)) {

            // object
            $value = $value->ID;
        }


        // return
        return $value;
    }


    /**
     *
     */
    public function get_taxonomies($field)
    {
        if (isset($field['taxonomy']) && is_array($field['taxonomy']) && ! empty($field['taxonomy'])) {
            return $field['taxonomy'];
        }

        return get_taxonomies();
    }



    /*
    *  input_admin_enqueue_scripts()
    *
    *  This action is called in the admin_enqueue_scripts action on the edit screen where your field is created.
    *  Use this action to add CSS + JavaScript to assist your render_field() action.
    *
    *  @type	action (admin_enqueue_scripts)
    *  @since	3.6
    *  @date	23/01/13
    *
    *  @param	n/a
    *  @return	n/a
    */

    public function input_admin_enqueue_scripts()
    {
        $dir = plugin_dir_url(__FILE__);


        // register & include JS
        wp_register_script('acf-input-post_or_taxonomy_link', "{$dir}js/input.js");
        wp_enqueue_script('acf-input-post_or_taxonomy_link');
    }
}


// create field
new acf_field_post_or_taxonomy_link();
