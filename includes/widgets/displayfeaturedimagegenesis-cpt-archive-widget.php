<?php
/**
 * Dependent class to build a featured taxonomy widget
 *
 * @package   DisplayFeaturedImageGenesis
 * @author    Robin Cornett <hello@robincornett.com>
 * @link      https://robincornett.com
 * @copyright 2014-2017 Robin Cornett Creative, LLC
 * @license   GPL-2.0+
 * @since     2.0.0
 */

/**
 * Genesis Featured Taxonomy widget class.
 *
 * @since 2.0.0
 *
 */
class Display_Featured_Image_Genesis_Widget_CPT extends WP_Widget {

	/**
	 * @var $form_class \DisplayFeaturedImageGenesisWidgets
	 */
	protected $form_class;

	/**
	 * Constructor. Set the default widget options and create widget.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {

		$widget_ops = array(
			'classname'                   => 'featured-posttype',
			'description'                 => __( 'Displays a post type archive with its featured image', 'display-featured-image-genesis' ),
			'customize_selective_refresh' => true,
		);

		$control_ops = array(
			'id_base' => 'featured-posttype',
			'width'   => 505,
			'height'  => 350,
		);

		parent::__construct( 'featured-posttype', __( 'Display Featured Post Type Archive Image', 'display-featured-image-genesis' ), $widget_ops, $control_ops );

	}

	/**
	 * Define the widget defaults.
	 * @return array
	 */
	public function defaults() {
		return array(
			'title'           => '',
			'post_type'       => 'post',
			'show_image'      => 0,
			'image_alignment' => 'alignnone',
			'image_size'      => 'medium',
			'show_title'      => 0,
			'show_content'    => 0,
		);
	}

	/**
	 * Echo the widget content.
	 *
	 * @since 2.0.0
	 *
	 *
	 * @param array $args     Display arguments including before_title, after_title, before_widget, and after_widget.
	 * @param array $instance The settings for the particular instance of the widget
	 */
	public function widget( $args, $instance ) {

		// Merge with defaults
		$instance = wp_parse_args( (array) $instance, $this->defaults() );

		$post_type = get_post_type_object( $instance['post_type'] );
		if ( ! $post_type ) {
			return;
		}
		$option   = displayfeaturedimagegenesis_get_setting();
		$image_id = '';

		if ( 'post' === $instance['post_type'] ) {
			$frontpage       = get_option( 'show_on_front' ); // either 'posts' or 'page'
			$postspage       = get_option( 'page_for_posts' );
			$postspage_image = get_post_thumbnail_id( $postspage );
			$title           = get_post( $postspage )->post_title;
			$permalink       = esc_url( get_the_permalink( $postspage ) );

			if ( 'posts' === $frontpage || ( 'page' === $frontpage && ! $postspage ) ) {
				$postspage_image = display_featured_image_genesis_get_default_image_id();
				$title           = get_bloginfo( 'name' );
				$permalink       = home_url();
			}
			$image_id = $postspage_image;
		} else {
			$title     = $post_type->label;
			$permalink = esc_url( get_post_type_archive_link( $instance['post_type'] ) );
			if ( post_type_supports( $instance['post_type'], 'genesis-cpt-archives-settings' ) ) {
				$headline = genesis_get_cpt_option( 'headline', $instance['post_type'] );
				if ( ! empty( $headline ) ) {
					$title = $headline;
				}
			}
		}

		echo $args['before_widget'];

		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base ) . $args['after_title'];
		}

		$image = $this->get_image( $image_id, $option, $post_type, $instance, $title );
		if ( $instance['show_image'] && $image ) {
			$role = empty( $instance['show_title'] ) ? '' : 'aria-hidden="true"';
			printf( '<a href="%s" title="%s" class="%s" %s>%s</a>', esc_url( $permalink ), esc_html( $title ), esc_attr( $instance['image_alignment'] ), $role, $image );
		}

		if ( $instance['show_title'] ) {

			$title_output = sprintf( '<h2><a href="%s">%s</a></h2>', $permalink, esc_html( $title ) );
			if ( genesis_html5() ) {
				$title_output = sprintf( '<h2 class="archive-title"><a href="%s">%s</a></h2>', $permalink, esc_html( $title ) );
			}
			echo wp_kses_post( $title_output );

		}

		$intro_text = '';
		if ( post_type_supports( $instance['post_type'], 'genesis-cpt-archives-settings' ) ) {
			$intro_text = genesis_get_cpt_option( 'intro_text', $instance['post_type'] );
		} elseif ( 'post' === $instance['post_type'] ) {
			$intro_text = get_post( $postspage )->post_excerpt;
			if ( 'posts' === $frontpage || ( 'page' === $frontpage && ! $postspage ) ) {
				$intro_text = get_bloginfo( 'description' );
			}
		}

		if ( $instance['show_content'] && $intro_text ) {

			echo genesis_html5() ? '<div class="archive-description">' : '';

			$intro_text = apply_filters( 'display_featured_image_genesis_cpt_description', $intro_text );

			echo wp_kses_post( wpautop( $intro_text ) );

			echo genesis_html5() ? '</div>' : '';

		}

		echo $args['after_widget'];

	}

	/**
	 * @param $image_id
	 * @param $option
	 * @param $post_type
	 * @param $instance
	 * @param $title
	 *
	 * @return string
	 */
	protected function get_image( $image_id, $option, $post_type, $instance, $title ) {
		$image = '';
		if ( isset( $option['post_type'][ $post_type->name ] ) && $option['post_type'][ $post_type->name ] ) {
			$image_id = displayfeaturedimagegenesis_check_image_id( $option['post_type'][ $post_type->name ] );
		}
		return wp_get_attachment_image( $image_id, $instance['image_size'], array(
			'alt' => $title,
		) );
	}

	/**
	 * Update a particular instance.
	 *
	 * This function should check that $new_instance is set correctly.
	 * The newly calculated value of $instance should be returned.
	 * If "false" is returned, the instance won't be saved/updated.
	 *
	 * @since 2.0.0
	 *
	 * @param array $new_instance New settings for this instance as input by the user via form()
	 * @param array $old_instance Old settings for this instance
	 *
	 * @return array Settings to save or bool false to cancel saving
	 */
	function update( $new_instance, $old_instance ) {

		$updater = new DisplayFeaturedImageGenesisWidgetsUpdate();

		return $updater->update( $new_instance, $old_instance, $this->get_fields( $new_instance ) );

	}

	/**
	 * Get all widget fields.
	 * @return array
	 */
	public function get_fields( $instance = array() ) {
		$form    = $this->get_form_class( $instance );
		return array_merge(
			$this->get_post_type_fields(),
			$form->get_text_fields(),
			$form->get_image_fields()
		);
	}

	/**
	 * Echo the settings update form.
	 *
	 * @since 2.0.0
	 *
	 * @param array $instance Current settings
	 */
	public function form( $instance ) {

		// Merge with defaults
		$instance = wp_parse_args( (array) $instance, $this->defaults() );
		$form     = $this->get_form_class( $instance );

		$form->do_text( $instance, array(
			'id'    => 'title',
			'label' => __( 'Title:', 'display-featured-image-genesis' ),
			'class' => 'widefat',
		) );

		echo '<div class="genesis-widget-column">';

		$form->do_boxes( array(
			'post_type' => $this->get_post_type_fields(),
		), 'genesis-widget-column-box-top' );

		$form->do_boxes( array(
			'words' => $this->get_text_fields(),
		) );

		echo '</div>';
		echo '<div class="genesis-widget-column genesis-widget-column-right">';

		$form->do_boxes( array(
			'image' => $form->get_image_fields(),
		), 'genesis-widget-column-box-top' );

		echo '</div>';
	}

	/**
	 * Get the post type fields.
	 *
	 * @return array
	 */
	protected function get_post_type_fields() {
		return array(
			array(
				'method' => 'select',
				'args'   => array(
					'id'      => 'post_type',
					'label'   => __( 'Post Type:', 'display-featured-image-genesis' ),
					'flex'    => true,
					'choices' => $this->get_post_types(),
				),
			),
		);
	}

	protected function get_text_fields() {
		return array(
			array(
				'method' => 'checkbox',
				'args'   => array(
					'id'    => 'show_title',
					'label' => __( 'Show Archive Title', 'display-featured-image-genesis' ),
				),
			),
			array(
				'method' => 'checkbox',
				'args'   => array(
					'id'    => 'show_content',
					'label' => __( 'Show Archive Intro Text', 'display-featured-image-genesis' ),
				),
			),
		);
	}

	/**
	 * Get the plugin widget forms class.
	 *
	 * @param array $instance
	 *
	 * @return \DisplayFeaturedImageGenesisWidgets
	 */
	protected function get_form_class( $instance = array() ) {
		if ( isset( $this->form_class ) ) {
			return $this->form_class;
		}
		$this->form_class = new DisplayFeaturedImageGenesisWidgets( $this, $instance );

		return $this->form_class;
	}

	/**
	 * Get the public registered post types on the site.
	 *
	 * @return mixed
	 */
	protected function get_post_types() {
		$args       = array(
			'public'      => true,
			'_builtin'    => false,
			'has_archive' => true,
		);
		$output     = 'objects';
		$post_types = get_post_types( $args, $output );

		$options['post'] = __( 'Posts', 'display-featured-image-genesis' );
		foreach ( $post_types as $post_type ) {
			$options[ $post_type->name ] = $post_type->label;
		}

		return $options;
	}
}
