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

class extensions
{
	/**
	* Check the version and return the available updates.
	*
	* @param \phpbb\extension\metadata_manager $md_manager The metadata manager for the version to check.
	* @param bool $force_update Ignores cached data. Defaults to false.
	* @param bool $force_cache Force the use of the cache. Override $force_update.
	* @return string
	* @throws RuntimeException
	*/
	public static function version_check(\phpbb\extension\metadata_manager $md_manager, $force_update = false, $force_cache = false)
	{
		$cache = objects::$cache; $config = objects::$config; $user = objects::$user;
		$meta = $md_manager->get_metadata('all');

		if (!isset($meta['extra']['version-check']))
		{
			throw new \RuntimeException($user->lang('NO_VERSIONCHECK'), 1);
		}

		$version_check = $meta['extra']['version-check'];

		if (version_compare($config['version'], '3.1.1', '>'))
		{
			$version_helper = new \phpbb\version_helper($cache, $config, new \phpbb\file_downloader(), $user);
		}
		else
		{
			$version_helper = new \phpbb\version_helper($cache, $config, $user);
		}
		$version_helper->set_current_version($meta['version']);
		$version_helper->set_file_location($version_check['host'], $version_check['directory'], $version_check['filename']);
		$version_helper->force_stability($config['extension_force_unstable'] ? 'unstable' : null);

		return $updates = $version_helper->get_suggested_updates($force_update, $force_cache);
	}

	/**
	* Lists all the enabled extensions and dumps to the template
	*
	* @param  $phpbb_extension_manager     An instance of the extension manager
	* @return null
	*/
	public static function list_enabled_exts(\phpbb\extension\manager $phpbb_extension_manager)
	{
		$enabled_extension_meta_data = array();

		foreach ($phpbb_extension_manager->all_enabled() as $name => $location)
		{
			$md_manager = $phpbb_extension_manager->create_extension_metadata_manager($name, objects::$template);

			try
			{
				$meta = $md_manager->get_metadata('all');
				$enabled_extension_meta_data[$name] = array(
					'META_DISPLAY_NAME'	=> $md_manager->get_metadata('display-name'),
					'META_NAME'			=> $name,
					'META_VERSION'		=> $meta['version'],
				);

				$force_update = objects::$request->variable('versioncheck_force', false);
				$updates = self::version_check($md_manager, $force_update, !$force_update);

				$enabled_extension_meta_data[$name]['S_UP_TO_DATE'] = empty($updates);
				$enabled_extension_meta_data[$name]['S_VERSIONCHECK'] = true;
				$enabled_extension_meta_data[$name]['U_VERSIONCHECK_FORCE'] = objects::$u_action . '&amp;action=details&amp;versioncheck_force=1&amp;ext_name=' . urlencode($md_manager->get_metadata('name'));
			}
			catch(\phpbb\extension\exception $e)
			{
				objects::$template->assign_block_vars('disabled', array(
					'META_DISPLAY_NAME'		=> objects::$user->lang('EXTENSION_INVALID_LIST', $name, $e),
					'META_NAME'				=> $name,
					'S_VERSIONCHECK'		=> false,
				));
			}
			catch (\RuntimeException $e)
			{
				$enabled_extension_meta_data[$name]['S_VERSIONCHECK'] = false;
			}
		}

		uasort($enabled_extension_meta_data, array('self', 'sort_extension_meta_data_table'));

		foreach ($enabled_extension_meta_data as $name => $block_vars)
		{
			$block_vars['U_DETAILS'] = objects::$u_action . '&amp;action=details&amp;ext_name=' . urlencode($name);

			objects::$template->assign_block_vars('enabled', $block_vars);

			self::output_actions('enabled', array(
				'DISABLE'		=> objects::$u_action . '&amp;action=disable&amp;ext_name=' . urlencode($name),
			));
		}
	}

	/**
	* Lists all the disabled extensions and dumps to the template
	*
	* @param  $phpbb_extension_manager     An instance of the extension manager
	* @return null
	*/
	public static function list_disabled_exts(\phpbb\extension\manager $phpbb_extension_manager)
	{
		$disabled_extension_meta_data = array();

		foreach ($phpbb_extension_manager->all_disabled() as $name => $location)
		{
			$md_manager = $phpbb_extension_manager->create_extension_metadata_manager($name, objects::$template);

			try
			{
				$meta = $md_manager->get_metadata('all');
				$disabled_extension_meta_data[$name] = array(
					'META_DISPLAY_NAME'	=> $md_manager->get_metadata('display-name'),
					'META_NAME'			=> $name,
					'META_VERSION'		=> $meta['version'],
				);

				$force_update = objects::$request->variable('versioncheck_force', false);
				$updates = self::version_check($md_manager, $force_update, !$force_update);

				$disabled_extension_meta_data[$name]['S_UP_TO_DATE'] = empty($updates);
				$disabled_extension_meta_data[$name]['S_VERSIONCHECK'] = true;
				$disabled_extension_meta_data[$name]['U_VERSIONCHECK_FORCE'] = objects::$u_action . '&amp;action=details&amp;versioncheck_force=1&amp;ext_name=' . urlencode($md_manager->get_metadata('name'));
			}
			catch(\phpbb\extension\exception $e)
			{
				objects::$template->assign_block_vars('disabled', array(
					'META_DISPLAY_NAME'		=> objects::$user->lang('EXTENSION_INVALID_LIST', $name, $e),
					'META_NAME'				=> $name,
					'S_VERSIONCHECK'		=> false,
				));
			}
			catch (\RuntimeException $e)
			{
				$disabled_extension_meta_data[$name]['S_VERSIONCHECK'] = false;
			}
		}

		uasort($disabled_extension_meta_data, array('self', 'sort_extension_meta_data_table'));

		foreach ($disabled_extension_meta_data as $name => $block_vars)
		{
			$block_vars['U_DETAILS'] = objects::$u_action . '&amp;action=details&amp;ext_name=' . urlencode($name);

			objects::$template->assign_block_vars('disabled', $block_vars);

			self::output_actions('disabled', array(
				'ENABLE'		=> objects::$u_action . '&amp;action=enable&amp;ext_name=' . urlencode($name),
				'DELETE_DATA'	=> objects::$u_action . '&amp;action=delete_data&amp;ext_name=' . urlencode($name),
			));
		}
	}

	/**
	* Lists all the available extensions and dumps to the template
	*
	* @param  $phpbb_extension_manager     An instance of the extension manager
	* @return null
	*/
	public static function list_available_exts(\phpbb\extension\manager $phpbb_extension_manager)
	{
		$uninstalled = array_diff_key($phpbb_extension_manager->all_available(), $phpbb_extension_manager->all_configured());

		$available_extension_meta_data = array();

		foreach ($uninstalled as $name => $location)
		{
			$md_manager = $phpbb_extension_manager->create_extension_metadata_manager($name, objects::$template);

			try
			{
				$display_ext_name = $md_manager->get_metadata('display-name');
				$meta = $md_manager->get_metadata('all');
				$available_extension_meta_data[$name] = array(
					'IS_BROKEN'			=> false,
					'META_DISPLAY_NAME'	=> $display_ext_name,
					'META_NAME'			=> $name,
					'META_VERSION'		=> $meta['version'],
					'U_DELETE'			=> objects::$u_action . '&amp;action=delete_ext&amp;ext_name=' . urlencode($name),
					'U_EXT_NAME'		=> $name
				);
			}
			catch(\phpbb\extension\exception $e)
			{
				$available_extension_meta_data[$name] = array(
					'IS_BROKEN'			=> true,
					'META_DISPLAY_NAME'	=> (isset($display_ext_name)) ? $display_ext_name : objects::$user->lang['EXTENSION_BROKEN'] . ' (' . $name . ')',
					'META_NAME'			=> $name,
					'META_VERSION'		=> (isset($meta['version'])) ? $meta['version'] : '0.0.0',
					'U_DELETE'			=> objects::$u_action . '&amp;action=delete_ext&amp;ext_name=' . urlencode($name),
					'U_EXT_NAME'		=> $name
				);
			}
		}

		uasort($available_extension_meta_data, array('self', 'sort_extension_meta_data_table'));

		foreach ($available_extension_meta_data as $name => $block_vars)
		{
			if (!$block_vars['IS_BROKEN'])
			{
				$block_vars['U_DETAILS'] = objects::$u_action . '&amp;action=details&amp;ext_name=' . urlencode($name);
			}

			objects::$template->assign_block_vars('disabled', $block_vars);

			self::output_actions('disabled', array(
				'ENABLE'		=> objects::$u_action . '&amp;action=enable_pre&amp;ext_name=' . urlencode($name),
			));
		}
	}

	/**
	* Lists all the extensions and dumps to the template
	*
	* @return null
	*/
	public static function list_all_exts()
	{
		$extension_meta_data = array();

		foreach (objects::$phpbb_extension_manager->all_available() as $name => $location)
		{
			$md_manager = objects::$phpbb_extension_manager->create_extension_metadata_manager($name, objects::$template);

			try
			{
				$meta = $md_manager->get_metadata('all');
				$extension_meta_data[$name] = array(
					'META_DISPLAY_NAME'	=> $md_manager->get_metadata('display-name'),
					'META_NAME'			=> $name,
					'META_VERSION'		=> $meta['version'],
					'S_IS_ENABLED'		=> objects::$phpbb_extension_manager->is_enabled($name),
					'S_IS_DISABLED'		=> objects::$phpbb_extension_manager->is_disabled($name),
					'S_LOCKED_TOGGLE'	=> ($name === "boardtools/upload"),
				);

				$force_update = objects::$request->variable('versioncheck_force', false);
				$updates = self::version_check($md_manager, $force_update, !$force_update);

				$extension_meta_data[$name]['S_UP_TO_DATE'] = empty($updates);
				$extension_meta_data[$name]['S_VERSIONCHECK'] = true;
				$extension_meta_data[$name]['U_VERSIONCHECK_FORCE'] = objects::$u_action . '&amp;action=details&amp;versioncheck_force=1&amp;ext_name=' . urlencode($md_manager->get_metadata('name'));
			}
			catch(\phpbb\extension\exception $e)
			{
				objects::$template->assign_block_vars('enabled', array(
					'META_DISPLAY_NAME'		=> objects::$user->lang('EXTENSION_INVALID_LIST', $name, $e),
					'META_NAME'				=> $name,
					'S_IS_ENABLED'			=> false,
					'S_LOCKED_TOGGLE'		=> true,
					'S_VERSIONCHECK'		=> false,
				));
			}
			catch (\RuntimeException $e)
			{
				$extension_meta_data[$name]['S_VERSIONCHECK'] = false;
			}
		}

		uasort($extension_meta_data, array('self', 'sort_extension_meta_data_table'));

		foreach ($extension_meta_data as $name => $block_vars)
		{
			$block_vars['U_DETAILS'] = objects::$u_action . '&amp;action=details&amp;ext_name=' . urlencode($name);

			objects::$template->assign_block_vars('enabled', $block_vars);
		}
	}

	/**
	* Output actions to a block
	*
	* @param string $block
	* @param array $actions
	*/
	private static function output_actions($block, $actions)
	{
		foreach ($actions as $lang => $url)
		{
			objects::$template->assign_block_vars($block . '.actions', array(
				'L_ACTION'			=> objects::$user->lang('EXTENSION_' . $lang),
				'L_ACTION_EXPLAIN'	=> (isset(objects::$user->lang['EXTENSION_' . $lang . '_EXPLAIN'])) ? objects::$user->lang('EXTENSION_' . $lang . '_EXPLAIN') : '',
				'U_ACTION'			=> $url,
			));
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
	* The function that gets the manager for the specified extension.
    * @param string $ext_name The name of the extension.
	*/
	public static function get_manager($ext_name)
	{
		// If they've specified an extension, let's load the metadata manager and validate it.
		if ($ext_name && $ext_name !== objects::$upload_ext_name)
		{
			$md_manager = new \phpbb\extension\metadata_manager($ext_name, objects::$config, objects::$phpbb_extension_manager, objects::$template, objects::$user, objects::$phpbb_root_path);

			try
			{
				$md_manager->get_metadata('all');
			}
			catch(\phpbb\extension\exception $e)
			{
				self::response(array(
					'ext_name'	=> $ext_name,
					'status'	=> 'error',
					'error'		=> $e
				));
				return false;
			}
			return $md_manager;
		}
		self::response(array(
			'ext_name'	=> $ext_name,
			'status'	=> 'error',
			'error'		=> objects::$user->lang['EXT_ACTION_ERROR']
		));
		return false;
	}

	/**
	* Output the response.
	* @param array $data The name of the extension and the status of the process.
    *                    The text of the error can also be provided if the status is 'error'.
	*/
	protected static function response(array $data)
	{
		if (objects::$is_ajax)
		{
			$output = new \phpbb\json_response();
			$output->send($data);
		}
		else if ($data['status'] !== 'error')
		{
			load::details($data['ext_name'], $data['status']);
		}
		else
		{
			files::catch_errors($data['error']);
		}
	}

	/**
	* The function that enables the specified extension.
    * @param string $ext_name The name of the extension.
	*/
	public static function enable($ext_name)
	{
		// What is a safe limit of execution time? Half the max execution time should be safe.
		$safe_time_limit = (ini_get('max_execution_time') / 2);
		$start_time = time();

		$md_manager = self::get_manager($ext_name);

		if ($md_manager === false)
		{
			return false;
		}

		if (!$md_manager->validate_dir())
		{
			self::response(array(
				'ext_name'	=> $ext_name,
				'status'	=> 'error',
				'error'		=> objects::$user->lang['EXTENSION_DIR_INVALID']
			));
			return false;
		}

		if (!$md_manager->validate_enable())
		{
			self::response(array(
				'ext_name'	=> $ext_name,
				'status'	=> 'error',
				'error'		=> objects::$user->lang['EXTENSION_NOT_AVAILABLE']
			));
			return false;
		}

		$extension = objects::$phpbb_extension_manager->get_extension($ext_name);
		if (!$extension->is_enableable())
		{
			self::response(array(
				'ext_name'	=> $ext_name,
				'status'	=> 'error',
				'error'		=> objects::$user->lang['EXTENSION_NOT_ENABLEABLE']
			));
			return false;
		}

		if (objects::$phpbb_extension_manager->is_enabled($ext_name))
		{
			self::response(array(
				'ext_name'	=> $ext_name,
				'status'	=> 'enabled'
			));
			return true;
		}

		try
		{
			while (objects::$phpbb_extension_manager->enable_step($ext_name))
			{
				// Are we approaching the time limit? If so we want to pause the update and continue after refreshing
				if ((time() - $start_time) >= $safe_time_limit)
				{
					if (objects::$is_ajax)
					{
						self::response(array(
							'ext_name'	=> $ext_name,
							'status'	=> 'force_update'
						));
					}
					else
					{
						objects::$template->assign_var('S_NEXT_STEP', objects::$user->lang['EXTENSION_ENABLE_IN_PROGRESS']);

						meta_refresh(0, objects::$u_action . '&amp;action=enable&amp;ext_name=' . urlencode($ext_name));
					}
					return false;
				}
			}
			objects::$log->add('admin', objects::$user->data['user_id'], objects::$user->ip, 'LOG_EXT_ENABLE', time(), array($ext_name));
		}
		catch (\phpbb\db\migration\exception $e)
		{
			self::response(array(
				'ext_name'	=> $ext_name,
				'status'	=> 'error',
				'error'		=> $e->getLocalisedMessage(objects::$user)
			));
			return false;
		}
		self::response(array(
			'ext_name'	=> $ext_name,
			'status'	=> 'enabled'
		));
		return true;
	}

	/**
	* The function that disables the specified extension.
    * @param string $ext_name The name of the extension.
	*/
	public static function disable($ext_name)
	{
		// What is a safe limit of execution time? Half the max execution time should be safe.
		$safe_time_limit = (ini_get('max_execution_time') / 2);
		$start_time = time();

		$md_manager = self::get_manager($ext_name);

		if ($md_manager === false)
		{
			return false;
		}

		if (!objects::$phpbb_extension_manager->is_enabled($ext_name))
		{
			self::response(array(
				'ext_name'	=> $ext_name,
				'status'	=> 'disabled'
			));
			return true;
		}

		while (objects::$phpbb_extension_manager->disable_step($ext_name))
		{
			// Are we approaching the time limit? If so we want to pause the update and continue after refreshing
			if ((time() - $start_time) >= $safe_time_limit)
			{
				if (objects::$is_ajax)
				{
					self::response(array(
						'ext_name'	=> $ext_name,
						'status'	=> 'force_update'
					));
				}
				else
				{
					objects::$template->assign_var('S_NEXT_STEP', objects::$user->lang['EXTENSION_DISABLE_IN_PROGRESS']);

					meta_refresh(0, objects::$u_action . '&amp;action=disable&amp;ext_name=' . urlencode($ext_name));
				}
				return false;
			}
		}
		objects::$log->add('admin', objects::$user->data['user_id'], objects::$user->ip, 'LOG_EXT_DISABLE', time(), array($ext_name));
		self::response(array(
			'ext_name'	=> $ext_name,
			'status'	=> 'disabled'
		));
		return true;
	}

	/**
	* The function that purges data of the specified extension.
    * @param string $ext_name The name of the extension.
	*/
	public static function purge($ext_name)
	{
		// What is a safe limit of execution time? Half the max execution time should be safe.
		$safe_time_limit = (ini_get('max_execution_time') / 2);
		$start_time = time();

		$md_manager = self::get_manager($ext_name);

		if ($md_manager === false)
		{
			return false;
		}

		if (objects::$phpbb_extension_manager->is_enabled($ext_name))
		{
			self::response(array(
				'ext_name'	=> $ext_name,
				'status'	=> 'error',
				'error'		=> objects::$user->lang['EXT_CANNOT_BE_PURGED']
			));
			return false;
		}

		try
		{
			while (objects::$phpbb_extension_manager->purge_step($ext_name))
			{
				// Are we approaching the time limit? If so we want to pause the update and continue after refreshing
				if ((time() - $start_time) >= $safe_time_limit)
				{
					if (objects::$is_ajax)
					{
						self::response(array(
							'ext_name'	=> $ext_name,
							'status'	=> 'force_update',
							'hash'		=> generate_link_hash('purge.' . $ext_name)
						));
					}
					else
					{
						objects::$template->assign_var('S_NEXT_STEP', objects::$user->lang['EXTENSION_DELETE_DATA_IN_PROGRESS']);

						meta_refresh(0, objects::$u_action . '&amp;action=purge&amp;hash=' . generate_link_hash('purge.' . $ext_name) . '&amp;ext_name=' . urlencode($ext_name));
					}
					return false;
				}
			}
			objects::$log->add('admin', objects::$user->data['user_id'], objects::$user->ip, 'LOG_EXT_PURGE', time(), array($ext_name));
		}
		catch (\phpbb\db\migration\exception $e)
		{
			self::response(array(
				'ext_name'	=> $ext_name,
				'status'	=> 'error',
				'error'		=> $e->getLocalisedMessage(objects::$user)
			));
			return false;
		}
		self::response(array(
			'ext_name'	=> $ext_name,
			'status'	=> 'purged'
		));
		return true;
	}
}
