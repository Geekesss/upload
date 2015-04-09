<?php
/**
*
* @package Upload Extensions
* @copyright (c) 2014 John Peskens (http://ForumHulp.com) and Igor Lavrov (https://github.com/LavIgor)
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace boardtools\upload\includes\functions;

use \boardtools\upload\includes\objects;
use \boardtools\upload\includes\filetree\filetree;

class load
{
	/**
	* The function that loads a list of zip files.
	*/
	public static function zip_files()
	{
		$zip_array = array();
		$ffs = @scandir(objects::$zip_dir . '/');
		if (!$ffs)
		{
			return false;
		}
		foreach($ffs as $ff)
		{
			if ($ff != '.' && $ff != '..')
			{
				if (strpos($ff,'.zip') === (strlen($ff) - 4))
				{
					$zip_array[] = array(
						'META_DISPLAY_NAME'	=> $ff,
						'U_UPLOAD'			=> objects::$u_action . '&amp;action=upload&amp;local_upload=' . urlencode($ff),
						'U_DOWNLOAD'		=> objects::$u_action . '&amp;action=download&amp;zip_name=' . urlencode($ff),
						'U_DELETE'			=> objects::$u_action . '&amp;action=delete_zip&amp;zip_name=' . urlencode($ff)
					);
				}
			}
		}

		$pagination = objects::$phpbb_container->get('pagination');
		$start		= objects::$request->variable('start', 0);
		$zip_count = sizeof($zip_array);
		$per_page = 25;
		$base_url = objects::$u_action . '&amp;action=zip_packages';
		$pagination->generate_template_pagination($base_url, 'pagination', 'start', $zip_count, $per_page, $start);

		uasort($zip_array, array('self', 'sort_extension_meta_data_table'));
		for($i = $start; $i < $zip_count && $i < $start + $per_page; $i++)
		{
			objects::$template->assign_block_vars('zip', $zip_array[$i]);
		}
	}

	/**
	* Sort helper for the table containing the metadata about the extensions.
	*/
	protected static function sort_extension_meta_data_table($val1, $val2)
	{
		return strnatcasecmp($val1['META_DISPLAY_NAME'], $val2['META_DISPLAY_NAME']);
	}

	/**
	* The function that displays the details page.
    * @param string $ext_name The name of the extension.
    * @param string $ext_show The section that we need to display.
	*/
	public static function details($ext_name, $ext_show)
	{
		// If they've specified an extension, let's load the metadata manager and validate it.
		if ($ext_name && $ext_name !== objects::$upload_ext_name)
		{
			$ext_md_manager = new \phpbb\extension\metadata_manager($ext_name, objects::$config, objects::$phpbb_extension_manager, objects::$template, objects::$user, objects::$phpbb_root_path);

			try
			{
				$ext_md_manager->get_metadata('all');
				$ext_name = $ext_md_manager->get_metadata('name'); // Just to be sure of the name.
				$display_name = $ext_md_manager->get_metadata('display-name');

				// Output it to the template
				$ext_md_manager->output_template_data();

				try
				{
					$updates_available = extensions::version_check($ext_md_manager, objects::$request->variable('versioncheck_force', false));

					objects::$template->assign_vars(array(
						'S_UP_TO_DATE'		=> empty($updates_available),
						'S_VERSIONCHECK'	=> true,
						'UP_TO_DATE_MSG'	=> objects::$user->lang(empty($updates_available) ? 'UP_TO_DATE' : 'NOT_UP_TO_DATE', $ext_md_manager->get_metadata('display-name')),
					));

					foreach ($updates_available as $branch => $version_data)
					{
						objects::$template->assign_block_vars('updates_available', $version_data);
					}
				}
				catch (\RuntimeException $e)
				{
					objects::$template->assign_vars(array(
						'S_VERSIONCHECK_STATUS'			=> $e->getCode(),
						'VERSIONCHECK_FAIL_REASON'		=> ($e->getMessage() !== objects::$user->lang('VERSIONCHECK_FAIL')) ? $e->getMessage() : '',
					));
				}
			}
			catch(\phpbb\extension\exception $e)
			{
				// Display errors in the details tab.
				objects::$template->assign_vars(array(
					'META_NAME'			=> $ext_name,
					'NOT_AVAILABLE'		=> $e,
				));
				$display_name = $ext_name;
			}

			objects::$template->assign_vars(array(
				'S_IS_ENABLED'	=> objects::$phpbb_extension_manager->is_enabled($ext_name),
				'S_IS_DISABLED'	=> objects::$phpbb_extension_manager->is_disabled($ext_name),
			));

			if (!objects::$is_ajax)
			{
				objects::$template->assign_var('S_DETAILS', true);
			}
		}
		else
		{
			$display_name = objects::$md_manager->get_metadata('display-name');
			objects::$md_manager->output_template_data();

			//if (objects::$self_update !== false && (preg_match(objects::$phpbb_link_template, objects::$self_update)))
			//{
			//    objects::$template->assign_vars(array(
			//        'U_UPLOAD_EXT_UPDATE'	=> objects::$u_action . '&amp;action=upload_self_confirm',
			//    ));
			//}

			if (!objects::$is_ajax)
			{
				objects::$template->assign_var('S_UPLOAD_DETAILS', true);
			}
			else
			{
				objects::$tpl_name = 'acp_ext_details';
			}
		}
		if ($ext_name)
		{
			if (file_exists(objects::$phpbb_root_path . 'ext/' . $ext_name . '/README.md') && !objects::$request->is_ajax())
			{
				objects::$template->assign_var('EXT_DETAILS_README', true);
			}

			if (file_exists(objects::$phpbb_root_path . 'ext/' . $ext_name . '/CHANGELOG.md') && !objects::$request->is_ajax())
			{
				objects::$template->assign_var('EXT_DETAILS_CHANGELOG', true);
			}

			switch ($ext_show)
			{
				case 'uploaded':
					objects::$template->assign_var('EXT_UPLOADED', true);
					break;
				case 'updated':
					objects::$template->assign_var('EXT_UPDATED', true);
					break;
				case 'enabled':
					objects::$template->assign_var('EXT_ENABLE_STATUS', objects::$user->lang['EXT_ENABLED']);
					break;
				case 'disabled':
					objects::$template->assign_var('EXT_ENABLE_STATUS', objects::$user->lang['EXT_DISABLED']);
					break;
				case 'purged':
					objects::$template->assign_var('EXT_ENABLE_STATUS', objects::$user->lang['EXT_PURGED']);
					break;
				case 'update':
					objects::$template->assign_vars(array(
						'EXT_DETAILS_UPDATE'	=> true,
						'SHOW_DETAILS_TAB'		=> 'update',
					));
					break;
			}

			// We output everything if this is an ajax request.
			if (objects::$is_ajax)
			{
				$ext_show = 'readme';
			}

			switch ($ext_show)
			{
				case 'update':
					break;
				case 'readme':
					$readme = false;
					$string = @file_get_contents(objects::$phpbb_root_path . 'ext/' . $ext_name . '/README.md');
					if ($string !== false)
					{
						$readme = \Michelf\MarkdownExtra::defaultTransform($string);
						if (!objects::$is_ajax)
						{
							objects::$template->assign_vars(array(
								'SHOW_DETAILS_TAB'		=> 'readme',
								'EXT_DETAILS_MARKDOWN'	=> $readme,
							));
						}
						else
						{
							objects::$template->assign_var('EXT_DETAILS_README', $readme);
						}
					}
					if (!objects::$is_ajax)
					{
						break;
					}
				case 'changelog':
					$changelog = false;
					$string = @file_get_contents(objects::$phpbb_root_path . 'ext/' . $ext_name . '/CHANGELOG.md');
					if ($string !== false)
					{
						$changelog = \Michelf\MarkdownExtra::defaultTransform($string);
						if (!objects::$is_ajax)
						{
							objects::$template->assign_vars(array(
								'SHOW_DETAILS_TAB'		=> 'changelog',
								'EXT_DETAILS_MARKDOWN'	=> $changelog,
							));
						}
						else
						{
							objects::$template->assign_var('EXT_DETAILS_CHANGELOG', $changelog);
						}
					}
					if (!objects::$is_ajax)
					{
						break;
					}
				case 'filetree':
					filetree::$ext_name = $ext_name;
					$ext_file = objects::$request->variable('ext_file', '/composer.json');
					objects::$template->assign_vars(array(
						'EXT_DETAILS_FILETREE'	=> true,
						'FILETREE'				=> filetree::php_file_tree(objects::$phpbb_root_path . 'ext/' . $ext_name, objects::$user->lang('ACP_UPLOAD_EXT_CONT', $display_name), objects::$u_action),
						'FILENAME'				=> substr($ext_file, strrpos($ext_file, '/') + 1),
						'CONTENT'				=> highlight_string(@file_get_contents(objects::$phpbb_root_path . 'ext/' . $ext_name . $ext_file), true)
					));
					if (!objects::$is_ajax)
					{
						objects::$template->assign_var('SHOW_DETAILS_TAB', 'filetree');
						break;
					}
				default:
					objects::$template->assign_vars(array(
						'SHOW_DETAILS_TAB'		=> 'details',
					));
					break;
			}

			objects::$template->assign_vars(array(
				//'S_EXT_DETAILS'			=> true,
				'U_ACTION_LIST'			=> objects::$u_action . '&amp;action=list',
				'U_ACTION' 				=> objects::$u_action,
				'U_UPLOAD'				=> objects::$u_action . '&amp;action=upload',
				'U_BACK'				=> objects::$u_action . '&amp;action=list',
				'U_EXT_DETAILS'			=> objects::$u_action . '&amp;action=details&amp;ext_name=' . urlencode($ext_name),
				'U_VERSIONCHECK_FORCE'	=> objects::$u_action . '&amp;action=details&amp;versioncheck_force=1&amp;ext_name=' . urlencode($ext_name),
				'UPDATE_EXT_PURGE_DATA'	=> objects::$user->lang('EXTENSION_DELETE_DATA_CONFIRM', $display_name),
			));
		}
		else
		{
			redirect(objects::$u_action . '&amp;action=list');
		}
	}

	/**
	* Build Confirm box for Ajax requests
	* @param boolean $check True for checking if confirmed (without any additional parameters) and false for displaying the confirm box
	* @param string $title Title/Message used for confirm box.
	*		message text is _CONFIRM appended to title.
	*		If title cannot be found in user->lang a default one is displayed
	*		If title_CONFIRM cannot be found in user->lang the text given is used.
	* @param string $hidden Hidden variables
	* @param string $u_action Custom form action
	*/
	public static function ajax_confirm_box($check, $title = '', $hidden = '', $u_action = '')
	{
		global $user, $template, $db, $request;
		global $config, $phpbb_path_helper;

		if (!$request->is_ajax())
		{
			return false;
		}

		$confirm = ($user->lang['YES'] === $request->variable('confirm', '', true, \phpbb\request\request_interface::POST));

		if ($check && $confirm)
		{
			$user_id = $request->variable('confirm_uid', 0);
			$session_id = $request->variable('sess', '');
			$confirm_key = $request->variable('confirm_key', '');

			if ($user_id != $user->data['user_id'] || $session_id != $user->session_id || !$confirm_key || !$user->data['user_last_confirm_key'] || $confirm_key != $user->data['user_last_confirm_key'])
			{
				return false;
			}

			// Reset user_last_confirm_key
			$sql = 'UPDATE ' . USERS_TABLE . " SET user_last_confirm_key = ''
					WHERE user_id = " . $user->data['user_id'];
			$db->sql_query($sql);

			return true;
		}
		else if ($check)
		{
			return false;
		}

		$s_hidden_fields = build_hidden_fields(array(
			'confirm_uid'	=> $user->data['user_id'],
			'sess'			=> $user->session_id,
			'sid'			=> $user->session_id,
		));

		// generate activation key
		$confirm_key = gen_rand_string(10);

		// If activation key already exist, we better do not re-use the key (something very strange is going on...)
		if ($request->variable('confirm_key', ''))
		{
			// This should not occur, therefore we cancel the operation to safe the user
			return false;
		}

		// re-add sid / transform & to &amp; for user->page (user->page is always using &)
		$use_page = ($u_action) ? $u_action : str_replace('&', '&amp;', $user->page['page']);
		$u_action = reapply_sid($phpbb_path_helper->get_valid_page($use_page, $config['enable_mod_rewrite']));
		$u_action .= ((strpos($u_action, '?') === false) ? '?' : '&amp;') . 'confirm_key=' . $confirm_key;

		$sql = 'UPDATE ' . USERS_TABLE . " SET user_last_confirm_key = '" . $db->sql_escape($confirm_key) . "'
				WHERE user_id = " . $user->data['user_id'];
		$db->sql_query($sql);

		$u_action .= '&confirm_uid=' . $user->data['user_id'] . '&sess=' . $user->session_id . '&sid=' . $user->session_id;
		$json_response = new \phpbb\json_response;
		$json_response->send(array(
			'MESSAGE_TITLE'		=> (!isset($user->lang[$title])) ? $user->lang['CONFIRM'] : $user->lang[$title],
			'MESSAGE_TEXT'		=> (!isset($user->lang[$title . '_CONFIRM'])) ? $title : $user->lang[$title . '_CONFIRM'],

			'YES_VALUE'			=> $user->lang['YES'],
			'NO_VALUE'			=> $user->lang['NO'],
			'S_CONFIRM_ACTION'	=> str_replace('&amp;', '&', $u_action), //inefficient, rewrite whole function
			'S_HIDDEN_FIELDS'	=> $hidden . $s_hidden_fields
		));
	}
}