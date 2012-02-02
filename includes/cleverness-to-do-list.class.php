<?php
/**
 * The main to-do list class
 * @author C.M. Kendrick
 * @version 3.0
 * @package cleverness-to-do-list
 */
class ClevernessToDoList {
	protected $cat_id = '';
	public $list = '';
	protected $form = '';

	public function __construct() {
		add_action( 'init', array( &$this, 'cleverness_todo_checklist_init' ) );
		}

	/**
	 * Display a to-do list
	 * @todo break out into smaller functions
	 */
	public function display() {
		global $userdata, $current_user;
		get_currentuserinfo();

		list( $priorities, $user, $url, $action ) = CTDL_Lib::set_variables( $current_user, $userdata );

		if ( is_admin() ) {
			$this->list .= '<div class="wrap"><div class="icon32"><img src="'.CTDL_PLUGIN_URL.'/images/cleverness-todo-icon.png" alt="" /></div> <h2>'.__('To-Do List', 'cleverness-to-do-list').'</h2>';
		}

		if ( isset( $message ) ) {
			$this->list .= '<div id="message" class="updated fade"><p>'.$message.'</p></div>';
			}

		// get the existing to-do data and show the edit form if editing a to-do item
		if ( $action == 'edit-todo' ) {
			$this->edit_todo_item( $url );
			return;
		}

		// otherwise, display the list of to-do items

		if ( is_admin() ) $this->list .= '<h3>'.__( 'To-Do Items', 'cleverness-to-do-list' );

		if ( current_user_can( CTDL_Loader::$settings['add_capability'] ) || CTDL_Loader::$settings['list_view'] == '0' ) {
			$this->list .= ' (<a href="#addtodo">'.__( 'Add New Item', 'cleverness-to-do-list' ).'</a>)';
		 	}

		if ( is_admin() ) $this->list .= '</h3>';

		$this->list .= '<table id="todo-list" class="todo-table widefat">';

		$this->show_table_headings();

		// get uncompleted to-do items
		$results = CTDL_Lib::test_get_todos( $user, 0, 0 );

		if ( $results ) {
			$this->show_todo_list_items( $results, $priorities, $url );
		} else {
			/* if there are no to-do items, display this message */
			$this->list .= '<tr><td>'.__( 'No items to do.', 'cleverness-to-do-list' ).'</td></tr>';
			}

		$this->list .= '</table>';

		/* Show completed items */
		if ( is_admin() ) {
			$this->list .= '<h3>'.__( 'Completed Items', 'cleverness-to-do-list' );

		if ( current_user_can( CTDL_Loader::$settings['purge_capability'] ) || CTDL_Loader::$settings['list_view'] == '0' ) {
			$cleverness_todo_purge_nonce = wp_create_nonce( 'todopurge' );
			$this->list .= ' (<a href="admin.php?page=cleverness-to-do-list&amp;action=purgetodo&_wpnonce='.$cleverness_todo_purge_nonce.'">'.__('Delete All', 'cleverness-to-do-list').'</a>)';
		 	}

		if ( is_admin() ) $this->list .= '</h3>';

		$this->list .= '<table id="todo-list-completed" class="todo-table widefat">';

		$this->show_table_headings( 1 );

		// get completed to-do items
		$results = CTDL_Lib::get_todos( $user, 0, 1 );

		if ( $results ) {
			$this->show_todo_list_items( $results, $priorities, $url, 1 );
		} else {
			/* if there are no to-do items, display this message */
			$this->list .= '<tr><td>'.__( 'No items to do.', 'cleverness-to-do-list' ).'</td></tr>';
			}

		$this->list .= '</table>';
		}

		$this->list .= $this->create_new_todo_form();

		if ( is_admin() ) $this->list .= '</div>';

		wp_reset_postdata();

	}

	/**
	 * Generate the To-Do List
	 * @param $todoitems
	 * @param $priorities
	 * @param $url
	 * @param $completed
	 */
	protected function show_todo_list_items( $todoitems, $priorities, $url, $completed = 0 ) {

		while ( $todoitems->have_posts() ) : $todoitems->the_post();
			$id = get_the_ID();
			$priority = get_post_meta( $id, '_priority', true );
			$priority_class = '';
			if ( $priority == '0' ) $priority_class = ' class="todo-important"';
			if ( $priority == '2' ) $priority_class = ' class="todo-low"';

			$this->list .= '<tr id="todo-'.$id.'"' . $priority_class . '>';
			$this->show_id( $id );
			$this->show_checkbox( $id, $completed );
			$this->show_todo_text( get_the_content() );
			$this->show_priority( $priority, $priorities );
			$this->show_assigned( get_post_meta( $id, '_assigned', true ) );
			$this->show_deadline( get_post_meta( $id, '_deadline', true ) );
			if ( $completed == 1 ) $this->show_completed( get_post_meta( $id, '_completed', true ) );
			$this->show_progress( get_post_meta( $id, '_progress', true ) );
			$this->show_category( $todoitem );
			$this->show_addedby( get_the_author() );
			$this->show_edit_link( $todoitem, $url );
			$this->list .= '</tr>';
		endwhile;

	}

	/**
	 * Get the to-do item data and display the edit form
	 * @param $url
	 */
	protected function edit_todo_item( $url ) {
		$id = absint( $_GET['id'] );
		$result = CTDL_Lib::get_todo( $id );
		$this->list .= $this->create_edit_todo_form( $result, $url );
		if ( is_admin() ) $url = 'admin.php?page=cleverness-to-do-list';
		$this->list .= '<p><a href="' . $url . '">' . __( '&laquo; Return to To-Do List', 'cleverness-to-do-list' ) . '</a></p>';
	}

	/**
	 * Creates the HTML for the form used to edit a to-do item
	 * @param $todo_data Existing to-do item values
	 * @param string $url The URL the form should be submitted to
	 * @return string Form HTML
	 */
	protected function create_edit_todo_form( $todo_data, $url ) {
		if ( is_admin() ) $url = 'admin.php?page=cleverness-to-do-list'; else $url = strtok( $url, "?" );
		$this->form = '';

		if ( is_admin() ) $this->form .= '<h3>'.__( 'Edit To-Do Item', 'cleverness-to-do-list' ).'</h3>';

    	$this->form .= '<form name="edittodo" id="edittodo" action="'.$url.'" method="post">
	  		<table class="todo-form form-table">';
		$this->create_priority_field( $todo_data );
		$this->create_assign_field( $todo_data );
		$this->create_deadline_field( $todo_data );
		$this->create_progress_field( $todo_data );
		$this->create_category_field( $todo_data );
		$this->create_todo_text_field( $todo_data );
		$this->form .= '</table>'.wp_nonce_field( 'todoupdate', 'todoupdate', true, false ).'<input type="hidden" name="action" value="updatetodo" />
        	<p class="submit"><input type="submit" name="submit" class="button-primary" value="'. __( 'Edit To-Do Item', 'cleverness-to-do-list' ).'" /></p>
			<input type="hidden" name="id" value="'. absint( $todo_data->id ).'" />';
		$this->form .= '</form>';

		return $this->form;
	}

	/**
	 * Creates the HTML form to add a new to-do item
	 * @return string Form HTML
	 */
	protected function create_new_todo_form() {
		if ( current_user_can( CTDL_Loader::$settings['add_capability'] ) || CTDL_Loader::$settings['list_view'] == '0' ) {

   	 	$this->form = '<h3>'.__( 'Add New To-Do Item', 'cleverness-to-do-list' ).'</h3>';

    	$this->form .= '<form name="addtodo" id="addtodo" action="" method="post">
	  		<table class="todo-form form-table">';
			$this->create_priority_field();
			$this->create_assign_field();
			$this->create_deadline_field();
			$this->create_progress_field();
			$this->create_category_field();
			$this->create_todo_text_field();
			$this->form .= '</table>'.wp_nonce_field( 'todoadd', 'todoadd', true, false ).'<input type="hidden" name="action" value="addtodo" />
        	<p class="submit"><input type="submit" name="submit" class="button-primary" value="'. __( 'Add To-Do Item', 'cleverness-to-do-list' ).'" /></p>';
		$this->form .= '</form>';

		return $this->form;
		}
	}

	/**
	 * Creates the HTML for the Priority Form Field
	 * @param array $todo_field_data Existing field data
	 */
	protected function create_priority_field( $todo_field_data = NULL ) {
		$selected = '';
		$this->form .= '<tr>
		  		<th scope="row"><label for="cleverness_todo_priority">'.__( 'Priority', 'cleverness-to-do-list' ).'</label></th>
		  		<td>
        			<select name="cleverness_todo_priority">';
					if ( isset( $todo_field_data ) ) $selected = ( $todo_field_data->priority == 0 ? ' selected = "selected"' : '' );
					$this->form .= sprintf( '<option value="0"%s>%s</option>', $selected, CTDL_Loader::$settings['priority_0'] );
					if ( isset( $todo_field_data ) ) {
						$selected = ( $todo_field_data->priority == 1 ? ' selected' : '' );
						} else {
							$selected = ' selected="selected"';
						}
					$this->form .= sprintf( '<option value="1"%s>%s</option>', $selected, CTDL_Loader::$settings['priority_1'] );
					$selected = '';
					if ( isset( $todo_field_data ) ) $selected = ( $todo_field_data->priority == 2 ? ' selected' : '' );
					$this->form .= sprintf( '<option value="2"%s>%s</option>', $selected, CTDL_Loader::$settings['priority_2'] );
        			$this->form .= '</select>
		  		</td>
			</tr>';
		}

	/**
	 * Creates the HTML for the Assign to Use Field
	 * @param array $todo_field_data Existing field data
	 */
	protected function create_assign_field( $todo_field_data = NULL ) {
		if ( CTDL_Loader::$settings['assign'] == '0' && current_user_can( CTDL_Loader::$settings['assign_capability'] ) ) {
			$selected = '';
			$this->form .= '<tr>
		  		<th scope="row"><label for="cleverness_todo_assign">'.__( 'Assign To', 'cleverness-to-do-list' ).'</label></th>
		  		<td>
					<select name="cleverness_todo_assign" id="cleverness_todo_assign">';
					if ( isset ($todo_field_data->assign ) && $todo_field_data->assign == '-1' ) $selected = ' selected="selected"';
					$this->form .= sprintf( '<option value="-1"%s>%s</option>', $selected, __( 'None', 'cleverness-to-do-list' ) );

					if ( CTDL_Loader::$settings['user_roles'] == '' ) {
						$roles = array( 'contributor', 'author', 'editor', 'administrator' );
					} else {
						$roles = explode( ", ", CTDL_Loader::$settings['user_roles'] );
						}
					foreach ( $roles as $role ) {
						$role_users = CTDL_Lib::get_users( $role );
						foreach( $role_users as $role_user ) {
							$user_info = get_userdata( $role_user->ID );
							if ( isset( $todo_field_data->assign ) && $todo_field_data->assign == $role_user->ID ) $selected = ' selected="selected"';
							$this->form .= sprintf( '<option value="%d"%s>%s</option>', $role_user->ID, $selected, $user_info->display_name );
						}
					}

					$this->form .= '</select>
				</td>
			</tr>';
			}
		}

	/**
	 * Creates the HTML for the Deadline Field
	 * @param array $todo_field_data Existing field data
	 */
	protected function create_deadline_field( $todo_field_data = NULL ) {
		if ( CTDL_Loader::$settings['show_deadline'] == '1' ) {
			$value = ( isset( $todo_field_data->deadline ) && $todo_field_data->deadline != 0 ? $todo_field_data->deadline : '' );
			$this->form .= sprintf( '<tr>
				<th scope="row"><label for="cleverness_todo_deadline">%s</label></th>
				<td><input type="text" name="cleverness_todo_deadline" id="cleverness_todo_deadline" value="%s" /></td>
			</tr>', __( 'Deadline', 'cleverness-to-do-list' ), $value );
			}
		}

	/**
	 * Creates the HTML for the Progress Field
	 * @param array $todo_field_data Existing field data
	 */
	protected function create_progress_field( $todo_field_data = NULL ) {
		if ( CTDL_Loader::$settings['show_progress'] == '1' ) {
			$this->form .= '<tr>
				<th scope="row"><label for="cleverness_todo_progress">'.__( 'Progress', 'cleverness-to-do-list' ).'</label></th>
				<td><select name="cleverness_todo_progress">';
				$i = 0;
				while ( $i <= 100 ) {
					$this->form .= '<option value="'.$i.'"';
					if ( isset( $todo_field_data->progress ) && $todo_field_data->progress == $i ) $this->form .= ' selected="selected"';
					$this->form .= '>'.$i.'</option>';
					$i += 5;
				}
				$this->form .= '</select></td>
			</tr>';
			}
		}

	/**
	 * Creates the HTML for the Category Field
	 * @param array $todo_field_data Existing field data
	 */
	protected function create_category_field( $todo_field_data = NULL ) {
		if ( CTDL_Loader::$settings['categories'] == '1' ) {
			$cat_id = ( $todo_field_data != NULL ? $todo_field_data->cat_id : 0 );
			$this->form .= '<tr><th scope="row"><label for="cleverness_todo_category">'.__( 'Category', 'cleverness-to-do-list' ).'</label></th><td>'.
				wp_dropdown_categories( 'taxonomy=todocategories&echo=0&orderby=name&hide_empty=0&show_option_none=None&selected='.$cat_id ).'</td></tr>';
			}
		}

	/**
	 * Creates the HTML for the To-Do Text Field
	 * @param array $todo_field_data Existing field data
	 */
	protected function create_todo_text_field( $todo_field_data = NULL ) {
		$text = ( isset( $todo_field_data ) ? stripslashes( esc_html( $todo_field_data->todotext, 1) ) : '' );
		$this->form .= sprintf( '<tr>
        	<th scope="row" valign="top"><label for="cleverness_todo_description">%s</label></th>
        	<td><textarea name="cleverness_todo_description" rows="5" cols="50" id="the_editor">%s</textarea></td>
			</tr>', __( 'To-Do', 'cleverness-to-do-list' ), $text );
		}

	/**
	 * Creates the HTML for the To-Do List Table Headings
	 * @param $completed
	 * @todo get rid of long assign if statement
	 */
	protected function show_table_headings( $completed = 0 ) {
		$this->list .= '<thead><tr>';
		if ( !is_admin() ) $this->list .= '<th></th>';
		if ( CTDL_Loader::$settings['show_id'] ) $this->list .= '<th>'.__( 'ID', 'cleverness-to-do-list' ).'</th>';
		$this->list .= '<th>'.__( 'Item', 'cleverness-to-do-list' ).'</th>';
	  	$this->list .= '<th>'.__( 'Priority', 'cleverness-to-do-list' ).'</th>';
		if ( CTDL_Loader::$settings['assign'] == 0  && (CTDL_Loader::$settings['list_view'] == 1 && CTDL_Loader::$settings['show_only_assigned'] == 0
			&& ( current_user_can( CTDL_Loader::$settings['view_all_assigned_capability'] ) ) ) || (CTDL_Loader::$settings['list_view'] == 1 && CTDL_Loader::$settings['show_only_assigned'] == 1)
			&& CTDL_Loader::$settings['assign'] == 0) $this->list .= '<th>'.__( 'Assigned To', 'cleverness-to-do-list' ).'</th>';
		if ( CTDL_Loader::$settings['show_deadline'] == 1 ) $this->list .= '<th>'.__('Deadline', 'cleverness-to-do-list').'</th>';
		if ( $completed == 1 && CTDL_Loader::$settings['show_completed_date'] == 1) $this->list .= '<th>'.__('Completed', 'cleverness-to-do-list').'</th>';
		if ( CTDL_Loader::$settings['show_progress'] == 1 ) $this->list .= '<th>'.__('Progress', 'cleverness-to-do-list').'</th>';
		if ( CTDL_Loader::$settings['categories'] == 1 ) $this->list .= '<th>'.__('Category', 'cleverness-to-do-list').'</th>';
		if ( CTDL_Loader::$settings['list_view'] == 1  && CTDL_Loader::$settings['todo_author'] == 0 ) $this->list .= '<th>'.__('Added By', 'cleverness-to-do-list').'</th>';
		if ( current_user_can(CTDL_Loader::$settings['edit_capability']) || CTDL_Loader::$settings['list_view'] == 0 ) $this->list .= '<th>'.__('Action', 'cleverness-to-do-list').'</th>';
    	$this->list .= '</tr></thead>';
	}

	/**
	 * Show the ID for a To-Do Item
	 * @param int $id
	 */
	protected function show_id( $id ) {
		if ( CTDL_Loader::$settings['show_id'] == 1 ) {
			$this->list .= ( $id != '' ? sprintf( '<td>%s</td>', $id ) : '<td></td>' );
		}
	}

	/**
	 * Create the HTML to show a To-Do List Checkbox
	 * @param int $id
	 * @param boolean $completed
	 */
	protected function show_checkbox( $id, $completed = NULL ) {
		$cleverness_todo_permission = CTDL_LIb::check_permission( 'todo', 'complete' );
		if ( $cleverness_todo_permission === true ) {
			if ( $completed == 1 ) {
				$this->list .= sprintf( '<td><input type="checkbox" id="cltd-%d" class="todo-checkbox completed" checked="checked" />', $id );
			} else {
				$this->list .= sprintf( '<td><input type="checkbox" id="ctdl-%d" class="todo-checkbox uncompleted"/>', $id );
			}
			$cleverness_todo_complete_nonce = wp_create_nonce( 'todocomplete' );
			$this->list .= '<input type="hidden" name="cleverness_todo_complete_nonce" value="'.$cleverness_todo_complete_nonce.'" />';
			if ( !is_admin() ) $this->list .= '</td>';
			}
		}

	/**
	 * Show the To-Do Text
	 * @param string $todotext
	 */
	protected function show_todo_text( $todotext ) {
		if ( !is_admin() ) $this->list .= '<td>'; else $this->list .= '&nbsp;';
		$this->list .= stripslashes( $todotext ).'</td>';
		}

	/**
	 * Show the Edit To-Do Link
	 * @param array $todo_field_data
	 * @param string $url
	 */
	protected function show_edit_link( $todo_field_data, $url ) {
		$edit = '';
		$url = $url.'?action=edit-todo&amp;id='.$todo_field_data->id;
		if ( current_user_can( CTDL_Loader::$settings['edit_capability'] ) || CTDL_Loader::$settings['list_view'] == '0' ) {
			if ( is_admin() ) {
				$edit = '<input class="edit-todo button-secondary" type="button" value="'. __( 'Edit' ).'" />';
			} else {
				$edit = '<a href="'.$url.'" class="edit-todo">'.__( 'Edit' ).'</a>';
				}
			}
		if ( current_user_can( CTDL_Loader::$settings['delete_capability'] ) || CTDL_Loader::$settings['list_view'] == '0' ) {
			if ( is_admin() ) {
				$edit .= ' <input class="delete-todo button-secondary" type="button" value="'. __( 'Delete' ).'" />';
			} else {
				$edit .= ' | <a href="" class="delete-todo">'.__( 'Delete' ).'</a>';
				}
			}
	  	if ( current_user_can( CTDL_Loader::$settings['edit_capability'] ) || CTDL_Loader::$settings['list_view'] == '0' )
			$this->list .= '<td>'.$edit.'</td>';
		}

	/**
	 * Show the Priority Level of a To-Do Item
	 * @param int $the_priority
	 * @param array $priority
	 */
	protected function show_priority( $the_priority, $priority ) {
		$this->list .= sprintf( '<td>%s</td>', $priority[$the_priority] );
		}

	/**
	 * Show the User that a To-Do Item is Assigned To
	 * @param int $assign
	 */
	protected function show_assigned( $assign ) {
		if ( ( CTDL_Loader::$settings['list_view'] == 1 && CTDL_Loader::$settings['show_only_assigned'] == 0 && ( current_user_can( CTDL_Loader::$settings['view_all_assigned_capability'] ) ) ) ||
		( CTDL_Loader::$settings['list_view'] == 1 && CTDL_Loader::$settings['show_only_assigned'] == 1) && CTDL_Loader::$settings['assign'] == 0 ) {
			$assign_user = '';
			if ( $assign != '-1' && $assign != '' && $assign != 0 ) {
				$assign_user = get_userdata( $assign );
				$this->list .= '<td>'.$assign_user->display_name.'</td>';
			} else {
				$this->list .= '<td></td>';
				}
			}
   		}

	/**
	 * Show the Category that a To-Do Item is In
	 * @param array $todo_field_data
	 */
	protected function show_category( $todo_field_data ) {
		if ( CTDL_Loader::$settings['categories'] == '1' ) {
			$cat = CTDL_Categories::get_category_name( $todo_field_data->cat_id );
			$this->list .= '<td>';
			if ( isset( $cat ) ) $this->list .= $cat->name;
			$this->list .= '</td>';
			}
		}

	/**
	 * Show Who Added a To-Do Item
	 * @param int $author
	 */
	protected function show_addedby( $author ) {
		if ( CTDL_Loader::$settings['list_view'] == 1 && CTDL_Loader::$settings['todo_author'] == 0 ) {
			$this->list .= '<td>'.$author.'</td>';
			}
		}

	/**
	 * Show the Deadline for a To-Do Item
	 * @param string $deadline
	 */
	protected function show_deadline( $deadline ) {
		if ( CTDL_Loader::$settings['show_deadline'] == 1 ) {
			$this->list .= ( $deadline != '' ? sprintf( '<td>%s</td>', $deadline ) : '<td></td>' );
			}
		}

	/**
	 * Show the Date that a To-Do Item was Completed
	 * @param array $todo_field_data
	 */
	protected function show_completed( $todo_field_data ) {
			if ( CTDL_Loader::$settings['show_completed_date'] && $todo_field_data->completed != '0000-00-00 00:00:00' ) {
				$date = '';
				$date = date( CTDL_Loader::$settings['date_format'], strtotime( $todo_field_data->completed ) );
				$this->list .= '<td>'.$date.'</td>';
				}
		}

	/**
	 * Show the Progress of a To-Do Item
	 * @param int $progress
	 */
	protected function show_progress( $progress) {
		if ( CTDL_Loader::$settings['show_progress'] == 1 ) {
			$this->list .= ( $progress != '' ? sprintf( '<td>%d%%</td>', $progress ) : '<td></td>' );
			}
		}

	/**
	 * Set Up JavaScript and Ajax Variables
	 * @return array
	 * TODO: move to loader or lib file
	 */
	public function cleverness_todo_checklist_get_js_vars() {
		return array(
		'SUCCESS_MSG' => __( 'To-Do Deleted.', 'cleverness-to-do-list' ),
		'ERROR_MSG' => __( 'There was a problem performing that action.', 'cleverness-to-do-list' ),
		'PERMISSION_MSG' => __( 'You do not have sufficient privileges to do that.', 'cleverness-to-do-list' ),
		'CONFIRMATION_MSG' => __( "You are about to permanently delete the selected item. \n 'Cancel' to stop, 'OK' to delete.", 'cleverness-to-do-list' ),
		'NONCE' => wp_create_nonce( 'cleverness-todo' ),
		'AJAX_URL' => admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * Add the JavaScript Files for the To-Do List
	 *  TODO: move to loader or lib file
	 */
	public function cleverness_todo_checklist_init() {
		wp_register_script( 'cleverness_todo_checklist_complete_js', CTDL_PLUGIN_URL.'/js/frontend-todo.js', '', 1.0, true );
		add_action( 'wp_enqueue_scripts', array( &$this, 'cleverness_todo_checklist_add_js' ) );
	}

	/**
	 * Enqueue and Localize JavaScript
	 * TODO: move to loader or lib file
	 */
	public function cleverness_todo_checklist_add_js() {
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_enqueue_script( 'jquery-color' );
		wp_enqueue_style( 'jquery.ui.theme', CTDL_PLUGIN_URL . '/css/jquery-ui-classic.css' );
		wp_enqueue_script( 'cleverness_todo_checklist_complete_js' );
		wp_localize_script( 'cleverness_todo_checklist_complete_js', 'ctdl', $this->cleverness_todo_checklist_get_js_vars() );
    }

} // end class
?>