<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.remember
 *
 * @copyright   Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * Joomla! System Remember Me Plugin
 *
 * @since  1.5
 */

class PlgSystemRemember extends JPlugin
{
	/**
	 * Application object.
	 *
	 * @var    JApplicationCms
	 * @since  3.2
	 */
	protected $app;

	/**
	 * Remember me method to run onAfterInitialise
	 * Only purpose is to initialise the login authentication process if a cookie is present
	 *
	 * @return  void
	 *
	 * @since   1.5
	 * @throws  InvalidArgumentException
	 */
	public function onAfterInitialise()
	{
		// Get the application if not done by JPlugin. This may happen during upgrades from Joomla 2.5.
		if (!$this->app)
		{
			$this->app = JFactory::getApplication();
		}

		// No remember me for admin.
		if ($this->app->isClient('administrator'))
		{
			return;
		}

		// Check for a cookie if user is not logged in
		if (JFactory::getUser()->get('guest'))
		{
			$cookieName = 'joomla_remember_me_' . JUserHelper::getShortHashedUserAgent();

			// Try with old cookieName (pre 3.6.0) if not found
			if (!$this->app->input->cookie->get($cookieName))
			{
				$cookieName = JUserHelper::getShortHashedUserAgent();
			}

			// Check for the cookie
			if ($this->app->input->cookie->get($cookieName))
			{
				$this->app->login(array('username' => ''), array('silent' => true));
			}
		}
	}

	/**
	 * Imports the authentication plugin on user logout to make sure that the cookie is destroyed.
	 *
	 * @param   array  $user     Holds the user data.
	 * @param   array  $options  Array holding options (remember, autoregister, group).
	 *
	 * @return  boolean
	 */
	public function onUserLogout($user, $options)
	{
		// No remember me for admin
		if ($this->app->isClient('administrator'))
		{
			return true;
		}

		$cookieName = 'joomla_remember_me_' . JUserHelper::getShortHashedUserAgent();

		// Check for the cookie
		if ($this->app->input->cookie->get($cookieName))
		{
			// Make sure authentication group is loaded to process onUserAfterLogout event
			JPluginHelper::importPlugin('authentication');
		}

		return true;
	}

	/**
	 * Method is called before user data is stored in the database
	 * If activated in the configuration of the rememeber-me plugin, this method resets all #__user_keys for the current user
	 * when the user changes his/her password, leaving any existing remember-me cookies on any devices useless!
	 * This functionality was sadly inspired by the horrific events around Alice Ruggles death 
	 *
	 * @param   array    $user   Holds the old user data.
	 * @param   boolean  $isnew  True if a new user is stored.
	 * @param   array    $data   Holds the new user data.
	 *
	 * @return    boolean
	 *
	 * @since   __DEPLOY_VERSION__
	 * @throws    InvalidArgumentException on invalid date.
	 */
	public function onUserBeforeSave($user, $isnew, $data)
	{
		// Irrelevant on new users
		if ($isnew)
		{
			return true;
		}
		
		// Irrelevant, because password was not changed by user
		if ($data['password_clear'] == '')
		{
			return true;
		}
		
		// Irrelevant, because "resetting on pw change" is not activated
		if (!$this->params->get('resetRememberMe')) 
		{
			return true;
		}

		/*
		 * But now, we need to do something 
		 * Delete all tokens for this user!
		*/
		$db = JFactory::getDbo();
		$query = $db->getQuery(true)
			->delete('#__user_keys')
			->where($db->quoteName('user_id') . ' = ' . $db->quote($user['username']));
		try
		{
			$db->setQuery($query)->execute();
		}		
		catch (RuntimeException $e)
		{
			// Log an alert for the site admin
			JLog::add(
				sprintf('Failed to delete cookie token for user %s with the following error: %s', $results[0]->user_id, $e->getMessage()),
				JLog::WARNING,
				'security'
			);
		}
		
		// this->app->enqueueMessage(JText::_('COM_USERS_PROFILE_SAVE_REMEMBERME_USERINFO'), 'notice');
		
		return true;
	}
	
}
