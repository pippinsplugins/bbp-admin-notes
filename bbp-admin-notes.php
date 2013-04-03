<?php
/**
 * Plugin Name: bbPress - Admin Notes
 * Plugin URI: http://pippinsplugins.com/bbpress-admin-notes
 * Description: Simple bbPress extension enabling admins to leave notes on topic replies
 * Author: Pippin Williamson
 * Author URI: http://pippinsplugins.com
 * Version: 1.0.4
 * Contributors: mordauk, sunnyratilal
 * Requires at least: 3.2
 * Tested up to: 3.6
 *
 * Text Domain: bbp-admin-notes
 *
 * Copyright 2013 Pippin Williamson
 *
 * @package		PW_BBP_Admin_Notes
 * @category 	Core
 * @author		Pippin Williamson
 * @version 	1.0.5
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * PW_BBP_Admin_Notes Class
 *
 * @package	PW_BBP_Admin_Notes
 * @since	1.0
 * @version	1.0
 */
final class PW_BBP_Admin_Notes {
	/**
	 * Holds the instance
	 *
	 * Ensures that only one instance of bbPress Admin Notes exists in memory at any one
	 * time and it also prevents needing to define globals all over the place.
	 *
	 * @var object
	 * @static
	 * @since 1.0
	 */
	private static $instance;

	/**
	 * Get the instance and store the class inside it. This plugin utilises
	 * the PHP singleton design pattern.
	 *
	 * @since 1.0
	 * @static
	 * @staticvar array $instance
	 * @access public
	 * @uses PW_BBP_Admin_Notes::actions()
	 * @uses PW_BBP_Admin_Notes::filters()
	 * @return object self::$instance Instance
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new PW_BBP_Admin_Notes;
			self::$instance->actions();
			self::$instance->filters();
		}

		return self::$instance;
	}

	/**
	 * Dummy constructor
	 *
	 * @since 1.0
	 * @access protected
	 * @return void
	 */
	private function __construct() { /* nothing here */ }


	/**
	 * Add all actions we need
	 *
	 * @since 1.0
	 * @access private
	 * @return void
	 */
	private function actions() {
		// Load plugin textdomain
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Enable comments on the reply post type
		add_action( 'init', array( $this, 'add_comment_support' ) );

		// Save the new notes
		add_action( 'init', array( $this, 'save_note' ) );

		// Removes the "Discussion" box
		add_action( 'add_meta_boxes', array( $this, 'remove_comments_status_box' ) );

		// Load the notes CSS
		add_action( 'wp_enqueue_scripts', array( $this, 'notes_css' ) );

		// Append the notes to the bottom of replies
		add_action( 'bbp_theme_after_reply_content', array( $this, 'reply_notes' ) );

		// Output the "Add Notes" form
		add_action( 'bbp_theme_after_reply_content', array( $this, 'add_note_form' ) );

		// Output our custom JS for the "Add Notes" form
		add_action( 'wp_footer', array( $this, 'notes_js' ) );
	}


	/**
	 * Add all filters we need
	 *
	 * @since 1.0
	 * @access private
	 * @return void
	 */
	private function filters() {
		// Add our custom admin links
		add_filter( 'bbp_get_reply_admin_links', array( $this, 'add_note_link' ), 10, 2 );
	}


	/**
	 * Loads the plugin textdomain
	 *
	 * @since 1.0
	 * @access public
	 * @return bool
	 */
	public function load_textdomain() {
		// Set filter for plugin's languages directory
		$lang_dir = dirname( plugin_basename( __FILE__ ) ) . '/languages/';
		$lang_dir = apply_filters( 'bbp_admin_notes_languages', $lang_dir );

		// Traditional WordPress plugin locale filter
		$locale        = apply_filters( 'plugin_locale',  get_locale(), 'bbp-admin-notes' );
		$mofile        = sprintf( '%1$s-%2$s.mo', 'bbp-admin-notes', $locale );

		// Setup paths to current locale file
		$mofile_local  = $lang_dir . $mofile;
		$mofile_global = WP_LANG_DIR . '/bbp-admin-notes/' . $mofile;

		if ( file_exists( $mofile_global ) ) {
			// Look in global /wp-content/languages/bbp-admin-notes folder
			load_textdomain( 'bbp-admin-notes', $mofile_global );
		} elseif ( file_exists( $mofile_local ) ) {
			// Look in local /wp-content/plugins/bbp-admin-notes/languages/ folder
			load_textdomain( 'bbp-admin-notes', $mofile_local );
		} else {
			// Load the default language files
			load_plugin_textdomain( 'bbp-admin-notes', false, $lang_dir );
		}
	}

	/**
	 * Enable comment support
	 *
	 * This is just for showing the Comments meta box in edit.php
	 *
	 * @since v1.0.3
	 * @access public
	 * @return void
	 */
	public function add_comment_support() {
		add_post_type_support( bbp_get_reply_post_type(), 'comments' ) ;
	}

	/**
	 * Remove "Discussion" meta box
	 *
	 * @since v1.0.3
	 * @access public
	 * @return void
	 */
	public function remove_comments_status_box() {
		remove_meta_box( 'commentstatusdiv', bbp_get_reply_post_type(), 'normal' );
	}

	/**
	 * Add the admin links to topics and replies
	 *
	 * This is kind of a hacky way of doing this for now
	 * Once http://bbpress.trac.wordpress.org/ticket/2090 gets implemented in some form, I will refactor this
	 *
	 * @since 1.0
	 * @access public
	 * @param $links string The HTML string of all existing admin links
	 * @param $args array All arguments passed from bbPress
	 * @return string
	 */
	public function add_note_link( $links = '', $args = array() ) {
		if ( ! current_user_can( 'moderate', bbp_get_forum_id() ) )
			return;

		$reply_id = bbp_get_reply_id();

		$links .= $args['before'];
			$links .= '<a href="#" class="bbp-add-note bbp-add-note-' . $reply_id . '" data-id="' . $reply_id . '">' . __( 'Add Note', 'bbp-admin-notes' ) . '</a>';
			$links .= '<a href="#" class="bbp-add-note bbp-add-note-' . $reply_id . '" style="display:none;" data-id="' . $reply_id . '">' . __( 'Hide Note', 'bbp-admin-notes' ) . '</a>';
			$links .= $args['sep'] . '&nbsp;' . $args['after'];
		return $links;
	}


	/**
	 * Shows the notes at the bottom of each reply
	 *
	 * @since 1.0
	 * @access public
	 * @return void
	 */
	public function reply_notes() {
		$reply_id = bbp_get_reply_id();
		$topic_id = bbp_get_topic_id();

		// only show the form on replies after the main topic reply
		if ( bbp_is_topic( $reply_id ) )
			return;

		if ( ! current_user_can( 'moderate', bbp_get_forum_id() ) )
			return;

		$notes = $this->have_notes( $reply_id );

		if ( empty( $notes ) )
			return;

?>
		<strong><?php _e( 'Moderator Notes:', 'bbp-admin-notes' ); ?></strong>
		<ul id="bbp-reply-<?php bbp_reply_id(); ?>-notes" class="bbp-reply-notes">
			<?php foreach( $notes as $note ) : ?>
			<li class="bbp-reply-note" id="bbp-reply-note-<?php echo $note->comment_ID; ?>">
				<span class="bbp-note-author"><strong><?php echo $note->comment_author; ?></strong>:&nbsp;</span>
				<span class="bbp-note-time"><?php echo date_i18n( get_option( 'date_format' ), strtotime( $note->comment_date ) ); ?>&nbsp;&ndash;</span>
				<span class="bbp-note-content"><?php echo $note->comment_content; ?></span>
			</li>
			<?php endforeach; ?>
		</ul>
<?php
	}

	/**
	 * Attach the Add Note form to the bottom of replies
	 *
	 * @since v1.0
	 * @access public
	 * @return void
	 */
	public function add_note_form() {
		$reply_id = bbp_get_reply_id();
		$topic_id = bbp_get_topic_id();

		// only show the form on replies after the main topic reply
		if ( bbp_is_topic( $reply_id ) )
			return;
?>
		<form id="bbp-add-note-form-<?php bbp_reply_id(); ?>" class="bbp-add-note-form" method="post" action="<?php echo get_permalink( $topic_id ); ?>#post-<?php bbp_reply_id(); ?>" style="display:none">
			<div>
				<label for="bbp-reply-note-<?php bbp_reply_id(); ?>"><?php _e( 'Add New Note to this Reply', 'bbp-admin-notes' ); ?></label><br/>
				<textarea name="bbp-reply-note" id="bbp-reply-note-<?php bbp_reply_id(); ?>" cols="50" rows="10"></textarea>
			</div>
			<div>
				<input type="hidden" name="bbp-reply-id" value="<?php bbp_reply_id(); ?>" />
				<input type="hidden" name="bbp-topic-id" value="<?php bbp_topic_id(); ?>" />
				<input type="submit" name="bbp-add-note" value="<?php _e( 'Add Note', 'bbp-admin-notes' ); ?>" />
			</div>
		</form>
<?php
	}

	/**
	 * Does the reply have any notes?
	 *
	 * @since 1.0
	 * @access private
	 * @param int $reply_id Reply ID
	 * @return array $notes The notes attached to the reply
	 */
	private function have_notes( $reply_id = 0 ) {
		$notes = $this->get_notes( $reply_id );

		return ! empty( $notes ) ? $notes : array();
	}

	/**
	 * Retrieve the notes for the specified reply
	 *
	 * @since 1.0
	 * @access private
	 * @param int $reply_id Reply ID
	 * @return mixed Array of notes if the reply has notes, false if not
	 */
	private function get_notes( $reply_id = 0 ) {
		$notes = get_comments( array(
			'post_id' => $reply_id,
			'order'   => 'ASC'
		) );

		if( ! empty( $notes ) )
			return $notes;
		return false;
	}

	/**
	 * Store a new note
	 *
	 * @since 1.0
	 * @access public
	 * @return int The ID of the new note
	 */
	public function save_note() {

		if ( ! current_user_can( 'moderate' ) )
			return;

		if ( empty( $_POST['bbp-add-note'] ) )
			return;

		if ( empty( $_POST['bbp-reply-id'] ) )
			return;

		$reply_id   = absint( $_POST['bbp-reply-id'] );

		$reply_note = wp_kses( $_POST['bbp-reply-note'], array() );

		$user = get_userdata( get_current_user_id() );

		$note_id = wp_insert_comment( wp_filter_comment( array(
			'comment_post_ID'      => $reply_id,
			'comment_author'       => $user->user_login,
			'comment_author_url'   => $user->user_url,
			'comment_author_IP'    => bbp_current_author_ip(),
			'comment_author_email' => $user->user_email,
			'comment_content'      => $reply_note,
			'user_id'              => $user->ID,
			'comment_agent'        => bbp_current_author_ua(),
			'comment_date'         => current_time( 'mysql' ),
			'comment_date_gmt'     => current_time( 'mysql', 1 ),
			'comment_approved'     => 1,
			'comment_parent'       => 0,

		) ) );

		/** Email all moderators who are suscribed to this topic */
		$user_ids = bbp_get_topic_subscribers( $_POST['bbp-topic-id'] );

		foreach ( (array) $user_ids as $user_id ) {
			if ( ! empty( $reply_author ) && (int) $user_id == (int) $reply_author ) || user_can( $user_id, 'moderate' )
				continue;

			$link = bbp_get_reply_url( $_POST['bbp-reply-id'] );

			$message = sprintf( __( '%1$s added a new note on:

%2$s
-----------

View note: %3$s

You are receiving this email because you subscribed to a forum topic.

Login and visit the topic to unsubscribe from these emails.', 'bbp-admin-notes' ),

			get_the_author_meta( 'display_name', $user->ID ),
			$reply_note,
			$link
		);

			// For plugins to filter titles per reply/topic/user
			$subject = '[' . wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ) . '] ' . strip_tags( bbp_get_topic_title( $_POST['bbp-topic-id'] ) ) . ' (' . __( 'New Admin Note', 'bbp-admin-notes' ) . ')';

			$headers = apply_filters( 'bbp_subscription_mail_headers', array() );

			// Get user data of this user
			$user = get_userdata( $user_id );

			// Send notification email
			wp_mail( $user->user_email, $subject, $message, $headers );
		}

		return $note_id;
	}

	/**
	 * Output the JS to show the Add Note form
	 *
	 * @since 1.0
	 * @access public
	 * @return void
	 */

	public function notes_js() {

		if( ! bbp_is_single_reply() && ! bbp_is_single_topic() )
			return;

?>
	<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('.bbp-add-note').on('click', function(e) {
				e.preventDefault();
				var id = $(this).data('id');
				$('#bbp-add-note-form-' + id).slideToggle();
				$('.bbp-add-note-' + id).toggle();
			});
		});
	</script>
<?php
	}


	/**
	 * Load the CSS for the notes
	 *
	 * @since v1.0
	 *
	 * @return void
	 */

	public function notes_css() {
		if ( ! bbp_is_single_reply() && ! bbp_is_single_topic() )
			return;

		wp_enqueue_style( 'bbp-admin-notes', plugins_url( 'bbp-admin-notes.css', __FILE__ ) );
	}
}

// Load our class
$GLOBALS['pw_bbp_admin_notes'] = PW_BBP_Admin_Notes::instance();