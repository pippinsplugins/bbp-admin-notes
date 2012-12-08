<?php
/*
Plugin Name: bbPress - Admin Notes
Plugin URI: http://pippinsplugins.com/bbpress-admin-notes
Description: Simple bbPress admin for leaving notes on topics
Version: 1.0
Author: Pippin Williamson
Author URI: http://pippinsplugins.com
Contributors: mordauk 


TODO
- Add meta box in Replies dashboard to show notes

*/

class PW_BBP_Admin_Notes {

	/**
	 * @var bbp Admin Notes instance
	 */

	private static $instance;


	/**
	 * Main class instance
	 *
	 * @since v1.0
	 *
	 * @return the class instance
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
	 * @since v1.0
	 *
	 * @return void
	 */

	private function __construct() { /* nothing here */ }


	/**
	 * Get this puppy started
	 *
	 * @since v1.0
	 *
	 * @return void
	 */

	private function actions() {

		// append the notes to the bottom of replies
		add_action( 'bbp_theme_after_reply_content', array( __CLASS__, 'reply_notes' ) );

		// output the add note form
		add_action( 'bbp_theme_after_reply_content', array( __CLASS__, 'add_note_form' ) );

		add_action( 'init', array( __CLASS__, 'save_note' ) );

		// output our custom JS
		add_action( 'wp_footer', array( __CLASS__, 'notes_js' ) );

		// load the notes CSS
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'notes_css' ) );

	}


	private function filters() {

		// add our custom admin links
		add_filter( 'bbp_get_reply_admin_links', array( __CLASS__, 'add_note_link' ), 10, 2 );

	}

	/**
	 * Add the admin links to topics and replies
	 *
	 * This is kind of a hacky way of doing this for now
	 * Once http://bbpress.trac.wordpress.org/ticket/2090 gets implemented in some form, I will refactor this
	 *
	 * @since v1.0
	 * @param $links string The HTML string of all existing admin links
	 * @param $args array All arguments passed from bbPress
	 *
	 * @return string
	 */

	public function add_note_link( $links = '', $args = array() ) {
		$links .= $args['before'] . '<a href="#" class="bbp-add-note" data-id="' . $args['id'] . '">' . __( 'Add Note', 'bbp-admin-notes' ) . '</a>' . $args['sep'] . '&nbsp;' . $args['after'];
		return $links;
	}


	/**
	 * Shows the notes at the bottom of each reply
	 *
	 * @since v1.0
	 *
	 * @return void
	 */

	public function reply_notes() {
		$reply_id = bbp_get_reply_id();
		$topic_id = bbp_get_topic_id();

		// only show the form on replies after the main topic reply
		if( bbp_is_topic( $reply_id ) )
			return;

		if( ! current_user_can( 'edit_forum', bbp_get_forum_id() ) )
			return;
?>
		<ul id="bbp-reply-<?php bbp_reply_id(); ?>-notes" class="bbp-reply-notes">
			<?php foreach( self::have_notes( $reply_id ) as $note ) : ?>
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
	 *
	 * @return void
	 */

	public function add_note_form() {

		$reply_id = bbp_get_reply_id();
		$topic_id = bbp_get_topic_id();

		// only show the form on replies after the main topic reply
		if( bbp_is_topic( $reply_id ) )
			return;
?>
		<form id="bbp-add-note-form-<?php bbp_reply_id(); ?>" class="bbp-add-note-form" method="post" action="<?php echo get_permalink( $topic_id ); ?>#post-<?php bbp_reply_id(); ?>" style="display:none">
			<div>
				<label for="bbp-reply-note-<?php bbp_reply_id(); ?>"><?php _e( 'Add New Note to this Reply', 'bbp-admin-notes' ); ?></label><br/>
				<textarea name="bbp-reply-note" id="bbp-reply-note-<?php bbp_reply_id(); ?>" cols="50" rows="10"></textarea>
			</div>
			<div>
				<input type="hidden" name="bbp-reply-id" value="<?php bbp_reply_id(); ?>" />
				<input type="submit" name="bbp-add-note" value="<?php _e( 'Add Note', 'bbp-admin-notes' ); ?>" />
			</div>
		</form>
<?php
	}



	/**
	 * Does the reply have any notes?
	 *
	 * @since v1.0
	 *
	 * @return array The notes attached to the reply
	 */

	private function have_notes( $reply_id = 0 ) {
	
		return self::get_notes( $reply_id );
	}


	/**
	 * Retrieve the notes for the specified reply
	 *
	 * @since v1.0
	 *
	 * @return array/bool Array of notes if the reply has notes, false if not
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
	 * @since v1.0
	 *
	 * @return int The ID of the new note
	 */

	public function save_note() {

		if( ! current_user_can( 'publish_forums' ) )
			return;

		if( empty( $_POST['bbp-add-note'] ) )
			return;

		if( empty( $_POST['bbp-reply-id'] ) )
			return;

		$reply_id   = absint( $_POST['bbp-reply-id'] );

		$reply_note = wp_kses( $_POST['bbp-reply-note'], array() );

		$user = get_userdata( get_current_user_id() );

		$note_id = wp_new_comment( wp_filter_comment( array(
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

		return $note_id;
	}


	/**
	 * Output the JS to show the Add Note form
	 *
	 * @since v1.0
	 *
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
		if( ! bbp_is_single_reply() && ! bbp_is_single_topic() )
			return; 

		wp_enqueue_style( 'bbp-admin-notes', plugins_url( 'bbp-admin-notes.css', __FILE__ ) );
		
	}


}

// load our class
$GLOBALS['pw_bbp_admin_notes'] = PW_BBP_Admin_Notes::instance();;