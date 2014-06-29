<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

require_once PATH_THIRD . 'mx_notify_control/config.php';

/**
 * MX Notify Control
 *
 * The MX Notify Control extension allows you to send customized notification emails/PMs based on different triggers
 *
 * @package  ExpressionEngine
 * @category Extension
 * @author    Max Lazar <max@eec.ms>
 * @copyright Copyright (c) 2012 Max Lazar (http://eec.ms)
 * @Commercial - please see LICENSE file included with this distribution
 * @version 2.2.9
 * @VERY, VERY CRAZY CODE. IT IS NOT TOO MUCH CHANGES STARTING FROM 2008. BUT PRO V WILL FIX IT.
 */

// -----------------------------------------
// Begin class
// -----------------------------------------



class Mx_notify_control_ext
{
	var $settings = array();


	var $name = MX_NOTIFY_CONTROL_NAME;
	var $version = MX_NOTIFY_CONTROL_VER;
	var $description = MX_NOTIFY_CONTROL_DESC;
	var $settings_exist = 'y';
	var $docs_url = MX_NOTIFY_CONTROL_DOCS;

	/**
	 * Defines the ExpressionEngine hooks that this extension will intercept.
	 *
	 * @since Version 1.0.0
	 * @access private
	 * @var mixed an array of strings that name defined hooks
	 * @see http://codeigniter.com/user_guide/general/hooks.html
	 **/
	private $hooks_trigger = array(4 => 'member_member_register', 2 => 'member_register_validate_members', 1 => 'cp_members_member_create', 5 => 'entry_submission_end', 6 => 'entry_submission_end', 8 => 'cp_members_validate_members', 3 => 'user_register_end',
		7 => 'zoo_visitor_register', 9 => 'member_member_login_start', 10 => 'login_authenticate_start', 11 => 'member_update_start');

	private $hooks = array('member_member_register' => 'member_member_register', 'member_register_validate_members' => 'member_register_validate_members', 'cp_members_member_create' => 'cp_members_member_create', 'entry_submission_end' => 'entry_submission_end', 'cp_members_validate_members' => 'cp_members_validate_members', 'user_register_end' => 'user_register_end',
		'zoo_visitor_register' => 'zoo_visitor_register', 'member_member_login_start' => 'member_member_login_start', 'login_authenticate_start' => 'login_authenticate_start', 'member_update_start' => 'member_update_start');

	// -------------------------------
	// Constructor
	// -------------------------------
	function Mx_notify_control_ext($settings = '')
	{
		$this->EE =& get_instance();
		$this->settings = $settings;
	}

	public function __construct($settings = FALSE)
	{
		$this->EE =& get_instance();

		if ( ! isset($this->EE->TMPL)){
			$this->EE->load->library('template');
			$this->EE->TMPL = new EE_Template();
		}


		$this->EE->TMPL = new EE_Template();

		// define a constant for the current site_id rather than calling $PREFS->ini() all the time
		if (defined('SITE_ID') == FALSE)
			define('SITE_ID', $this->EE->config->item('site_id'));

		// set the settings for all other methods to access
		$this->settings = ($settings == FALSE) ? $this->_getSettings() : $this->_saveSettingsToSession($settings);
	}


	/**
	 * Prepares and loads the settings form for display in the ExpressionEngine control panel.
	 * @since Version 1.0.0
	 * @access public
	 * @return void
	 **/
	public function settings_form()
	{
		$this->EE->lang->loadfile('mx_notify_control');
		$this->EE->load->model('channel_model');

		// Create the variable array
		$vars = array(
			'addon_name' => MX_NOTIFY_CONTROL_NAME,
			'error' => FALSE,
			'input_prefix' => __CLASS__,
			'message' => FALSE,
			'settings_form' => FALSE,
			'mailinglist_exists' => false

		);

		$vars['notify_id']     = '{row}';
		$vars['template_list'] = $this->_template_list(1, false);

		$vars['channel_data'] = $this->EE->channel_model->get_channels()->result();

		$vars['notify_id']     = 1;
		$vars['settings']      = (isset($this->settings[SITE_ID])) ? $this->settings[SITE_ID] : '';
		$vars['settings_form'] = TRUE;
		$vars['webmaster_email'] = $this->EE->config->item('webmaster_email');
		$vars['webmaster_name'] = $this->EE->config->item('webmaster_name');


		$this->EE->load->model('status_model');
		$statuses = $this->EE->status_model->get_statuses();

		if ($statuses->num_rows() > 0)
		{
			foreach ($statuses->result() as $status)
			{
				$vars['statuses'][$status->group_id][] = array(
					'status' => $status->status,
					'status_id' => $status->status_id
				);
			}
		}

		$vars['member_groups'] = $this->EE->member_model->get_member_groups()->result();

		//do mailinglist support
		if (file_exists(PATH_MOD.'mailinglist/mod.mailinglist.php') &&
			$this->EE->db->table_exists($this->EE->db->dbprefix.'mailing_lists') === TRUE)
		{
			$vars['mailinglist_exists'] = TRUE;
		}


		if ($vars['mailinglist_exists'])
		{
			$query = $this->get_mailing_lists();

			if ($query->num_rows() > 0)
			{
				foreach ($query->result() as $row)
				{
					$vars['mailing_lists'][$row->list_title] = array('name' => 'list_'.$row->list_id, 'value' => $row->list_id);
				}
			}
		}else
		{
			$vars['mailing_lists'] = false;
		}


		// @@Deligate modification
		$this->EE->load->model('field_model');
		$custom_fields = $this->EE->field_model->get_fields('', array('site_id' => $this->EE->config->item('site_id'), 'field_type' => array('Mx_delegate_email', 'Vmg_chosen_member')));

		$vars['custom_fields'] = array();
		$vars['custom_fields']['--'] = '--';

		if ($custom_fields->num_rows() > 0)
		{
			$this->EE->load->library('api');
			$this->EE->api->instantiate('channel_fields');

			foreach ($custom_fields->result() as $row)
			{
				$vars['custom_fields'][$row->field_id] = $row->field_label . ' {' . $row->field_name . '}';
			}
		}

		//@@end
		//$hooks_trigger
		if ($new_settings = $this->EE->input->post(__CLASS__))
		{

			foreach ($new_settings['row_order'] as $key => $value)
			{
				$iRow = $value;
				$hooks[] = $this->hooks_trigger[$new_settings["trigger_" . $iRow]];
			}

			$this->EE->db->where_in('hook', array_diff($this->hooks, $hooks));
			$this->EE->db->delete('exp_extensions', array(
					'class' => __CLASS__
			));

			$this->_createHooks($hooks, $new_settings);

			$this->settings[SITE_ID] = $new_settings;
			$vars['settings']        = $new_settings;
			$this->_saveSettingsToDB($this->settings);

			$this->_ee_notice($this->EE->lang->line('extension_settings_saved_success'));
		}

		return $this->EE->load->view('form_settings', $vars, TRUE);
	}
	// END

	/**
	 * Get Mailing Lists
	 *
	 * Retreives the list id list title for all mailing lists
	 * If the $list_id is given to retreive only one mailing list, the
	 *    list_template is also retrieved
	 *
	 * @access	public
	 * @param	int
	 * @return	string
	 */
	function get_mailing_lists($list_id = '')
	{
		$this->EE->db->select('list_id, list_title');

		if (is_array($list_id))
		{
			$this->EE->db->select('list_template');
			$this->EE->db->where_in('list_id', $list_id);
		}
		elseif ($list_id != '')
		{
			$this->EE->db->select('list_template');
			$this->EE->db->where('list_id', $list_id);
		}

		$this->EE->db->order_by('list_title');

		return $this->EE->db->get('mailing_lists');
	}


	/**
	 * _ee_notice function.
	 *
	 * @access private
	 * @param mixed $msg
	 * @return void
	 */
	function _ee_notice($msg)
	{
		$this->EE->javascript->output(array(
				'$.ee_notice("'.$this->EE->lang->line($msg).'",{type:"success",open:true});',
				'window.setTimeout(function(){$.ee_notice.destroy()}, 3000);'
			));
	}

	/**
	 * _template_list function.
	 *
	 * @access private
	 * @param mixed $iRow
	 * @param mixed $current_template
	 * @return void
	 */
	function _template_list($iRow, $current_template)
	{
		$sql = "SELECT exp_template_groups.group_name, exp_templates.template_name, exp_templates.template_id, exp_sites.site_label
                FROM   exp_template_groups, exp_templates, exp_sites
                WHERE  exp_template_groups.group_id =  exp_templates.group_id
                AND    exp_template_groups.site_id = exp_sites.site_id
				AND  exp_sites.site_id = " . SITE_ID;
		$sql .= " ORDER BY exp_sites.site_label, exp_template_groups.group_order, exp_templates.template_name";


		$query = $this->EE->db->query($sql);

		$options = array();


		foreach ($query->result() as $row)
		{
			$options[$row->template_id] = (($this->EE->config->item('multiple_sites_enabled') === 'y') ? $row->site_label . NBS . '-' . NBS : '') . $row->group_name . '/' . $row->template_name;
		}

		$d = $options;

		return $d;
	}

	function member_update_start($member_id, $data)
	{
		$edata = $data;

		if (isset($this->settings[SITE_ID]))
			{$site_settings = $this->settings[SITE_ID];}else
			{return $data;}

		if (!isset($edata['group_id']))
			{return $data;}

		$edata['email']  = $this->member_email($member_id);

		$edata['org_group_id'] = $this->EE->db->query("SELECT group_id
									FROM  exp_members
									WHERE  member_id = '" . $member_id . "'")->row()->group_id;

		if ($edata['org_group_id'] == $edata['group_id'])
			{return $data;}

		foreach ($site_settings['row_order'] as $key => $value)
		{
			$iRow = $value;
			if ($site_settings["trigger_" . $iRow] == '11')
			{

				if ($site_settings["type_" . $iRow] == 'pm')
				{
					$this->first_message_send($edata, $member_id, $iRow);
				}
				if ($site_settings["type_" . $iRow] == 'email')
				{
					$this->first_email_send($edata, $member_id, $iRow);
				}
			}
		}

		return $data;

	}

	function entry_submission_end($entry_id, $meta, $edata)
	{
		if (isset($this->settings[SITE_ID]))
			{$site_settings = $this->settings[SITE_ID];}else
			{return false;}

		$edata['entry_id'] = $edata['revision_post']['entry_id'] = $entry_id;

		foreach ($site_settings['row_order'] as $key => $value)
		{
			$iRow     = $value;
			$group_id = false;

			if ($site_settings["trigger_" . $iRow] == '5' and ($this->EE->input->get_post('entry_id') == 0) and (isset($site_settings['channel_' . $iRow][$meta['channel_id']])) and (isset($site_settings['channel_' . $iRow][$meta['channel_id'] . '_' . str_replace(" ", "_", $meta['status'])])))
			{

				if ($site_settings["type_" . $iRow] == 'email')
				{
					$edata['author_email'] = $this->member_email($meta['author_id']);
					$this->first_email_send($edata, $meta['author_id'], $iRow, $entry_id, ((isset($site_settings['mbr_groups_' . $iRow])) ? $site_settings['mbr_groups_' . $iRow] : false),  ((isset($site_settings['tocustomlist_' . $iRow])) ?  (($site_settings['tocustomlist_' . $iRow] != "") ? $site_settings['tocustomlist_' . $iRow] : false) : false));
				}
			}

			if ($site_settings["trigger_" . $iRow] == '6' and ($this->EE->input->get_post('entry_id') != 0) and (isset($site_settings['channel_' . $iRow][$meta['channel_id']])) and (isset($site_settings['channel_' . $iRow][$meta['channel_id'] . '_' . str_replace(" ", "_", $meta['status'])])))
			{


				if ($site_settings["type_" . $iRow] == 'email')
				{


					$edata['author_email'] = $this->member_email($meta['author_id']);
					$this->first_email_send($edata, $meta['author_id'], $iRow, $entry_id, ((isset($site_settings['mbr_groups_' . $iRow])) ? $site_settings['mbr_groups_' . $iRow] : false), ((isset($site_settings['tocustomlist_' . $iRow])) ?  (($site_settings['tocustomlist_' . $iRow] != "") ? $site_settings['tocustomlist_' . $iRow] : false) : false));
				}
			}

		}

		return TRUE;

	}

	/**
	 * author_email function.
	 *
	 * @access public
	 * @param mixed $author_id
	 * @return void
	 */
	function member_email($author_id)
	{
		$result = $this->EE->db->query("SELECT email
									FROM  exp_members
									WHERE  member_id = '" . $author_id . "'");

		if ($result->num_rows() > 0)
		{
			foreach ($result->result_array() as $row)
			{
				$email = $row['email'];
			}

		}
		return $email;
	}

	/**
	 * load_snippets function.
	 *
	 * @access public
	 * @return void
	 */
	function load_snippets()
	{
		$this->EE->db->select('snippet_name, snippet_contents');
		$this->EE->db->where('(site_id = ' . $this->EE->db->escape_str($this->EE->config->item('site_id')) . ' OR site_id = 0)');
		$fresh = $this->EE->db->get('snippets');

		if ($fresh->num_rows() > 0)
		{
			$snippets = array();

			foreach ($fresh->result() as $var)
			{
				$snippets[$var->snippet_name] = $var->snippet_contents;
			}

			$this->EE->config->_global_vars = array_merge($this->EE->config->_global_vars, $snippets);

			unset($snippets);
			unset($fresh);
		}
	}
	/**
	 * zoo_visitor_register function.
	 *
	 * @access public
	 * @param mixed $edata
	 * @return void
	 */
	function zoo_visitor_register($edata)
	{

		if (isset($this->settings[SITE_ID]))
			{$site_settings = $this->settings[SITE_ID];}else
			{return false;}

		foreach ($site_settings['row_order'] as $key => $value)
		{
			$iRow = $value;
			if ($site_settings["trigger_" . $iRow] == '7')
			{
				$result = $this->EE->db->query("SELECT member_id
								FROM  exp_members
								WHERE  username = '" . $edata['username'] . "'");

				foreach ($result->result_array() as $row)
				{
					$member_id = $row['member_id'];
				}

				if ($site_settings["type_" . $iRow] == 'pm')
				{
					$this->first_message_send($edata, $member_id, $iRow);
				}
				if ($site_settings["type_" . $iRow] == 'email')
				{
					$this->first_email_send($edata, $member_id, $iRow);
				}
			}
		}

		return TRUE;
	}

	/**
	 * login_authenticate_start function.
	 *
	 * @access public
	 * @return void
	 */
	function login_authenticate_start() {

		return $this->member_member_login_start();

	}

	/**
	 * member_member_login_start function.
	 *
	 * @access public
	 * @return void
	 */
	function member_member_login_start()
	{

		if (isset($this->settings[SITE_ID]))
			{$site_settings = $this->settings[SITE_ID];}else
			{return false;}

		foreach ($site_settings['row_order'] as $key => $value)
		{
			$iRow = $value;
			if ($site_settings["trigger_" . $iRow] == '9' or $site_settings["trigger_" . $iRow] == '10')
			{
				$username = (string) $this->EE->input->post('username');
				$password = (string) $this->EE->input->post('password');
				$incoming = $this->EE->auth->authenticate_username($username, $password);

				if (($incoming && isset($site_settings["auth_event_" . $iRow]['auth_success'])) || (!$incoming && isset($site_settings["auth_event_" . $iRow]['auth_failed'])))
				{

					$this->EE->load->model('member_model');
					$member = $this->EE->member_model->get_member_emails(array('screen_name', 'ip_address'), array('username' => $username))->result_array();
					if (count($member) > 0 )
					{
						if (($member[0]['ip_address'] != $this->EE->session->userdata('ip_address')) || !isset($site_settings["auth_event_" . $iRow]['ip_address'])){

							$this->first_email_send(array('email' =>$member[0]['email'], 'member_id' =>$member[0]['member_id'], 'screen_name' =>$member[0]['screen_name']), $member[0]['member_id'], $iRow);
						}
					}
				}
			}

		}
		return TRUE;
	}


	function member_member_register($edata)
	{
		if (isset($this->settings[SITE_ID]))
			{$site_settings = $this->settings[SITE_ID];}else
			{return false;}

		foreach ($site_settings['row_order'] as $key => $value)
		{
			$iRow = $value;
			if ($site_settings["trigger_" . $iRow] == '4')
			{
				$result = $this->EE->db->query("SELECT member_id
								FROM  exp_members
								WHERE  username = '" . $edata['username'] . "'");

				foreach ($result->result_array() as $row)
				{
					$member_id = $row['member_id'];
				}

				if ($site_settings["type_" . $iRow] == 'pm')
				{
					$this->first_message_send($edata, $member_id, $iRow);
				}
				if ($site_settings["type_" . $iRow] == 'email')
				{
					$this->first_email_send($edata, $member_id, $iRow);
				}
			}
		}

		return TRUE;
	}

	function member_register_validate_members($member_id)
	{
		if (isset($this->settings[SITE_ID]))
			{$site_settings = $this->settings[SITE_ID];}else
			{return false;}

		foreach ($site_settings['row_order'] as $key => $value)
		{
			$iRow = $value;

			if ($site_settings["trigger_" . $iRow] == '2')
			{
				$result = $this->EE->db->query("SELECT *
									FROM  exp_members
									WHERE  member_id = '" . $member_id . "'");

				if ($result->num_rows() > 0)
				{
					foreach ($result->result_array() as $row)
					{
						$edata = $row;
					}


					if ($site_settings["type_" . $iRow] == 'pm')
					{
						$this->first_message_send($edata, $member_id, $iRow);
					}
					if ($site_settings["type_" . $iRow] == 'email')
					{
						$this->first_email_send($edata, $member_id, $iRow);

					}
				}
			}
		}
		return TRUE;
	}


	function cp_members_member_create($member_id, $edata)
	{
		if (isset($this->settings[SITE_ID]))
			{$site_settings = $this->settings[SITE_ID];}else
			{return false;}

		foreach ($site_settings['row_order'] as $key => $value)
		{
			$iRow = $value;

			if ($site_settings["trigger_" . $iRow] == '1')
			{
				if ($site_settings["type_" . $iRow] == 'pm')
				{
					$this->first_message_send($edata, $member_id, $iRow);
				}
				if ($site_settings["type_" . $iRow] == 'email')
				{
					$this->first_email_send($edata, $member_id, $iRow);
				}

			}
		}

		return TRUE;
	}

	/**
	 * cp_members_validate_members function.
	 *
	 * @access public
	 * @param mixed $member_id
	 * @return void
	 * @thanks to @johnhenry_ie
	 */
	function cp_members_validate_members($member_id)
	{
		if (isset($this->settings[SITE_ID]))
			{$site_settings = $this->settings[SITE_ID];}else
			{return false;}

		$memberArray = $_POST['toggle'];


		foreach ($site_settings['row_order'] as $key => $value)
		{
			$iRow = $value;


			if ($site_settings["trigger_" . $iRow] == '8')
			{

				foreach ($memberArray as $key1 => $value1)
				{
					$result = $this->EE->db->query("SELECT *
									FROM  exp_members
									WHERE  member_id = '" . $value1 . "'");



					if ($result->num_rows() > 0)
					{
						foreach ($result->result_array() as $row)
						{
							$edata = $row;
						}


						$this->first_email_send($edata, $member_id, $iRow);



					}
				}
			}
		}
		return TRUE;

	}

	/**
	 * user_register_end function.
	 *
	 * @access public
	 * @param mixed $edata
	 * @param mixed $member_id
	 * @return void
	 */
	function user_register_end($edata, $member_id)
	{
		if (isset($this->settings[SITE_ID]))
			{$site_settings = $this->settings[SITE_ID];}else
			{return false;}

		foreach ($site_settings['row_order'] as $key => $value)
		{
			$iRow = $value;

			if ($site_settings["trigger_" . $iRow] == '3')
			{
				if ($site_settings["type_" . $iRow] == 'pm')
				{
					$this->first_message_send($edata->insert_data, $member_id, $iRow);
				}
				if ($site_settings["type_" . $iRow] == 'email')
				{
					$this->first_email_send($edata->insert_data, $member_id, $iRow);
				}

			}
		}

		return TRUE;
	}



	// --------------------------------
	//  Send PM
	// --------------------------------
	function first_message_send($data, $member_id, $iRow, $entry_id = '')
	{
		if (isset($this->settings[SITE_ID]))
			{$site_settings = $this->settings[SITE_ID];}else
			{return false;}

		$msg_data = array(
			'mbr_username' => (isset($data['username'])) ? $data['username'] : '',
			'mbr_screen_name' => (isset($data['screen_name'])) ? $data['screen_name'] : '',
			'mbr_email' => (isset($data['email'])) ? $data['email'] : '',
			'site_name' => stripslashes($this->EE->config->item('site_name')),
			'site_url' => $this->EE->config->item('site_url'),
			'sender_id' => $site_settings['sender_' . $iRow],
			'member_id' => $member_id,
			'password' => (isset($_POST['password'])) ? $_POST['password'] : '',
			'entry_id' => $entry_id
		);

		$m_body = $this->message_body($site_settings['tempale_' . $iRow]);

		$msg_sabj = $this->templater($site_settings['title_' . $iRow], $msg_data);

		$msg_body = $this->templater($m_body, $msg_data);

		$this->EE->db->query("INSERT INTO exp_message_data (message_id, sender_id, message_date, message_subject, message_body, message_tracking, message_attachments, message_recipients, message_cc, message_hide_cc, message_sent_copy, total_recipients, message_status)
			  VALUES (NULL, '" . $msg_data['sender_id'] . "', UNIX_TIMESTAMP(), '" . $this->EE->db->escape_str($msg_sabj) . "', '" . $this->EE->db->escape_str($msg_body) . "', 'n', 'n', '" . $member_id . "', '', 'n', 'n', '1', 'sent')");

		$message_id = $this->EE->db->insert_id();

		$this->EE->db->query("INSERT INTO exp_message_copies (copy_id ,message_id ,sender_id ,recipient_id ,message_received ,message_read ,message_time_read ,attachment_downloaded ,message_folder ,message_authcode ,message_deleted ,message_status)
			  VALUES ( NULL, '" . $this->EE->db->escape_str($message_id) . "', '" . $msg_data['sender_id'] . "', '" . $this->EE->db->escape_str($member_id) . "', 'n', 'n', '0', 'n', '1', '" . $this->EE->functions->random('alpha', 10) . "', 'n', '')");

		$this->EE->db->query("UPDATE exp_members
			  SET private_messages = private_messages + 1
			  WHERE member_id = '" . $this->EE->db->escape_str($member_id) . "'");

		return TRUE;
	}

	/** ----------------------------
	 /**  Send email
	 /** ----------------------------*/

	/* !main */
	function first_email_send($data, $member_id, $iRow, $entry_id = '', $mbr_groups = false, $mbr_c_groups = false)
	{
		if (isset($this->settings[SITE_ID]))
			{$site_settings = $this->settings[SITE_ID];}else
			{return false;}

		/*/
    [entry_id] =&gt; 0
    [channel_id] =&gt; 1
    [autosave_entry_id] =&gt; 0
    [layout_preview] =&gt; 1
    [new_channel] =&gt; 1
    [submit] =&gt; Submit
    [field_id_18] =&gt;
    [cp_call] =&gt; 1
    [revision_post] =&gt; Array
        (
            [entry_id] =&gt; 0
            [channel_id] =&gt; 1
            [autosave_entry_id] =&gt; 0
            [filter] =&gt;
            [layout_preview] =&gt; 1
            [title] =&gt; teertwet
            [url_title] =&gt; teertwet
            [field_id_18_directory] =&gt;
            [entry_date] =&gt; 2011-05-18 07:02 AM
            [expiration_date] =&gt;
            [comment_expiration_date] =&gt;
            [new_channel] =&gt; 1
            [status] =&gt; open
            [author] =&gt; 1
            [allow_comments] =&gt; y
            [submit] =&gt; Submit
            [field_id_18] =&gt;
        )
*/
		//DIE("MAIL");

		$mbr_data = array(
			'mbr_username' => (isset($data['username'])) ? $data['username'] : '',
			'mbr_screen_name' => (isset($data['screen_name'])) ? $data['screen_name'] : '',
			'mbr_email' => str_replace(array(
					'{mbr_email}',
					'{author_email}'
				), array(
					((isset($data['email'])) ? $data['email'] : ''),
					((isset($data['author_email'])) ? $data['author_email'] : '')
				), ((isset($site_settings['toemail_' . $iRow])) ? $this->field_parser($site_settings['toemail_' . $iRow], $data) : ''))
		);

		$msg_data = array(
			'site_name' => stripslashes($this->EE->config->item('site_name')),
			'site_url' => $this->EE->config->item('site_url'),
			'sender_id' => $site_settings['sender_' . $iRow],
			'member_id' => $member_id,
			'password' => (isset($_POST['password'])) ? $_POST['password'] : '',
			'entry_id' => $entry_id
		);

		$email_sabj_tmp = $this->templater($site_settings['title_' . $iRow], $msg_data);

		$msg_body_tmp = $this->template_parser($msg_data, $iRow, $site_settings['tempale_' . $iRow]);

		$this->EE->load->helper('text');
		$this->EE->load->library('email');

		$this->EE->email->wordwrap = true;
		$this->EE->email->mailtype = (isset($site_settings['mail_format_' . $iRow])) ? $site_settings['mail_format_' . $iRow] : 'text';

		$plaintext_alt_tmp  = ($this->EE->email->mailtype == 'html') ? ((isset($site_settings['template_alt_' . $iRow])) ? $this->template_parser($msg_data, $iRow, $site_settings['template_alt_' . $iRow]) : '') : '';

		$this->EE->email->from($site_settings['email_' . $iRow] != '' ? $site_settings['email_' . $iRow] :  $this->EE->config->item('webmaster_email'), ($site_settings['from_' . $iRow] != '') ? $site_settings['from_' . $iRow] : $this->EE->config->item('webmaster_name'));

		$emailTo = array();

		/* mailing list*/
		if (isset($site_settings['mailing_list_' . $iRow]) && !empty($site_settings['mailing_list_' . $iRow]))
		{
			$query = $this->get_mailing_list_emails($site_settings['mailing_list_' . $iRow]);
			if ($query->num_rows() > 0)
			{
				foreach ($query->result_array() as $index => $row)
				{
						$emailTo['ml_'.$index] = array(
							'mbr_email' => $row['email'],
							'mbr_screen_name' => '',
							'mbr_member_id' => ''
						);
				}
			}

		}

		/* !cc */
		if ($site_settings['custom_fields_' . $iRow] != '--' && !empty($site_settings['custom_fields_' . $iRow]))
		{
			$f_id = $site_settings['custom_fields_' . $iRow];
			$this->EE->load->model('field_model');
			$custom_fields = $this->EE->field_model->get_field((int)$f_id)->result_array();

			if (isset($custom_fields[0]))
			{
				$type = $custom_fields[0]['field_type'];

				if ($type = 'mx_delegate_email')
				{
					if ($this->EE->input->get_post('mx_reassign_field_id_' . $f_id))
					{
						$this->EE->email->to(trim($this->EE->input->get_post('mx_reassign_field_id_' . $f_id)));
						$this->EE->email->subject($email_sabj_tmp);
						$this->EE->email->message(entities_to_ascii($msg_body_tmp), (($plaintext_alt_tmp != '')
								? $plaintext_alt_tmp : ''));
						$this->EE->email->Send();
					}

				}

				if ($type = 'vmg_chosen_member')
				{
					if ($data['revision_post']['field_id_'.$f_id] != '')
					{
						$member_ids = explode('|', $data['revision_post']['field_id_'.$f_id]);
						$this->EE->load->model('member_model');

						$custom_emails = $this->EE->member_model->get_member_emails(array('screen_name'), array('member_id' => $member_ids))->result_array();
						foreach ($custom_emails as $member)
						{
							$emailTo[$member['member_id']] =  array(
								'mbr_email' => $member['email'],
								'mbr_screen_name' => $member['screen_name'],
								'mbr_member_id' => $member['member_id']
							);
						}
					}
				}
			}
		}

		// @VERY, VERY CRAZY CODE. IS NOT TOO MUCH CHANGES STARTING FROM 2008. BUT V2 WILL FIX IT.

		if ($mbr_data['mbr_email'] != '')
		{

			$this->EE->email->to($mbr_data['mbr_email']);
			$this->EE->email->subject($this->templater($email_sabj_tmp, $mbr_data));
			$this->EE->email->message(entities_to_ascii($this->templater($msg_body_tmp, $mbr_data)), (($plaintext_alt_tmp != '') ? $this->templater($plaintext_alt_tmp, $mbr_data) : ''));
			$this->EE->email->Send();

		}

		if ($mbr_groups)
		{
			$where['mg.group_id'] = $mbr_groups;
			$this->EE->load->model('member_model');
			$query = $this->EE->member_model->get_member_emails('', $where);

			if ($query->num_rows() > 0)
			{
				foreach ($query->result_array() as $row)
				{
					$emailTo[$row['member_id']] = array(
						'mbr_email' => $row['email'],
						'mbr_screen_name' => $row['screen_name'],
						'mbr_member_id' => $row['member_id']
					);
				}

			}


		}
		;

		if ($mbr_c_groups)
		{
			$mbr_c_groups = trim($this->EE->TMPL->parse_variables_row($mbr_c_groups, $msg_data));


			$list_query = $this->EE->db->select('receiver_id')->where('list_name',  $mbr_c_groups)->get('mx_notify_control_lists');

			$recipient_ids = array();

			if  ($list_query->num_rows())
			{
				foreach ($list_query->result_array() as $key => $val)
				{
					$recipient_ids[] = $val['receiver_id'];
				};

				$recipient_query = $this->EE->db->select('email, name')->where_in('recipient_id', $recipient_ids)->get('mx_notify_control_members');

				if ($recipient_query->num_rows() > 0)
				{
					foreach ($recipient_query->result_array() as $row)
					{
						$emailTo[$row['email']] = array(
							'mbr_email' => $row['email'],
							'mbr_screen_name' => $row['name']
						);
					}
				}

				/*
				foreach ($emails as $key => $val)
				{
					$this->EE->email->to($val);
					$this->EE->email->subject($this->templater($email_sabj_tmp, $val));
					$this->EE->email->message(entities_to_ascii($this->templater($msg_body_tmp, $val)), (($plaintext_alt_tmp != '') ? $this->templater($plaintext_alt_tmp, $val) : ''));
					$this->EE->email->Send();

				}

*/
				//  if  ($list_query->num_rows()) {
				//  email] => at@aqa.at [name
				// }

				//$list_query->row()->recipient_id
				// $receiver_id = $recipient_query->row()->recipient_id;

				/*
				foreach ($emails as $key => $val)
				{
					$mbr_c_groups
				}
				*/
			}
		}

		if (count($emailTo) != 0 )
		{
			foreach ($emailTo as $key => $val)
			{
				$this->EE->email->to($val['mbr_email']);
				$this->EE->email->subject($this->templater($email_sabj_tmp, $val));
				$this->EE->email->message(entities_to_ascii($this->templater($msg_body_tmp, $val)), (($plaintext_alt_tmp != '') ? $this->templater($plaintext_alt_tmp, $val) : ''));
				$this->EE->email->Send();

			}
		}

		return TRUE;
	}


	// --------------------------------------------------------------------

	/**
	 * Get Mailing List Emails
	 *
	 * Retreives the authcode, email, and list id for given mailing lists
	 *
	 * @access	public
	 * @param	array
	 * @return	string
	 */
	function get_mailing_list_emails($list_ids = array())
	{
		if ( ! is_array($list_ids))
		{
			$list_ids = array($list_ids);
		}

		$this->EE->db->select('authcode, email, list_id');

		if ( ! empty($list_ids))
		{
			$this->EE->db->where_in('list_id', $list_ids);
		}

		$this->EE->db->order_by('user_id');

		return $this->EE->db->get('mailing_list');
	}

	/**
	 * field_parser function.
	 *
	 * @access public
	 * @param mixed $email_list
	 * @param mixed $data
	 * @return void
	 */
	function field_parser($email_list, $data)
	{
		$this->EE->db->select('field_name, field_id');
		$this->EE->db->where('site_id', $this->EE->config->item('site_id'));
		$this->EE->db->order_by('field_id', 'desc');
		$query = $this->EE->db->get('channel_fields');
		if ($query->num_rows() > 0)
		{

			foreach ($query->result_array() as $row)
			{

				$value       = (isset($data['field_id_' . $row['field_id']])) ?$data['field_id_' . $row['field_id']] : '';
				$email_list = str_replace(LD.$row['field_name'].RD, $value, $email_list);

			}
		}
		return $email_list;
	}

	/**
	 * template_parser function.
	 *
	 * @access public
	 * @param mixed $msg_data
	 * @param mixed $iRow
	 * @param mixed $template
	 * @return void
	 */
	function template_parser($msg_data, $iRow, $template)
	{
		$OLD_TMPL = isset($this->EE->TMPL) ? $this->EE->TMPL : NULL;
		$this->EE->TMPL = new EE_Template();

		$msg_body = $this->message_body($template);

		$msg_body = $this->templater($msg_body, $msg_data);

		$this->load_snippets();

		$this->EE->load->library('typography');

		$this->EE->typography->initialize();
		$this->EE->typography->convert_curly = FALSE;
		$this->EE->typography->allow_img_url = FALSE;
		$this->EE->typography->auto_links    = FALSE;
		$this->EE->typography->encode_email  = FALSE;


		$this->EE->TMPL->parse($msg_body, FALSE, SITE_ID);

		$msg_body = $this->EE->TMPL->parse_globals($this->EE->TMPL->final_template);

		// restore TMPL pointer
		$this->EE->TMPL = $OLD_TMPL;

		return $msg_body;
	}

	function templater($str, $data)
	{
		$out = $this->EE->TMPL->parse_variables_row($str, $data);
		return $out;
	}

	function message_body($t_id)
	{
		$out = '';

		$query = $this->EE->db->query("SELECT tg.group_name, template_name, template_data, template_type, template_notes, cache, refresh, no_auth_bounce, allow_php, php_parse_location, save_template_file
								 FROM exp_templates t, exp_template_groups tg
								 WHERE t.template_id = '" . $this->EE->db->escape_str($t_id) . "'
								 AND tg.group_id = t.group_id");

		if ($query->num_rows() > 0)
		{
			$out = $query->row('template_data');

			if ($this->EE->config->item('save_tmpl_files') == 'y' && $this->EE->config->item('tmpl_file_basepath') != '' && $query->row('save_template_file') == 'y')
			{
				$this->EE->load->library('api');
				$this->EE->api->instantiate('template_structure');

				$basepath = rtrim($this->EE->config->item('tmpl_file_basepath'), '/') . '/';

				$basepath .= $this->EE->config->item('site_short_name') . '/' . $query->row('group_name') . '.group/' . $query->row('template_name') . $this->EE->api_template_structure->file_extensions($query->row('template_type'));


				if (file_exists($basepath))
				{
					$out = file_get_contents($basepath);
				}
			}
		}

		return $out;
	}




	// --------------------------------
	//  Activate Extension
	// --------------------------------

	function activate_extension()
	{
		$this->_createHooks(array('mx_notify_control'));

		if (!$this->EE->db->table_exists('exp_mx_notify_control_members'))
		{
			$this->EE->db->query("CREATE TABLE IF NOT EXISTS exp_mx_notify_control_members (
									  `recipient_id` int(10) unsigned NOT NULL auto_increment,
									  `name`     varchar(128)     NOT NULL default '',
									  `email`        varchar(128)    NOT NULL default '',
									  `member_id`      varchar(50)      NOT NULL default '',
									  `auth`      varchar(2)     NOT NULL default '0',
									  `unsubscribe`     varchar(128)   NOT NULL default '0',
									  PRIMARY KEY (`recipient_id`)
									)");
		};

		if (!$this->EE->db->table_exists('exp_mx_notify_control_lists'))
		{
			$this->EE->db->query("CREATE TABLE IF NOT EXISTS exp_mx_notify_control_lists (
									  `subscribe_id` int(10) unsigned NOT NULL auto_increment,
									  `receiver_id` int(10) NOT NULL,
									  `list_name` varchar(128)     NOT NULL default '',
									  `unsubscribe`     varchar(128)   NOT NULL default '0',
									  PRIMARY KEY (`subscribe_id`)
									)");
		};
	}

	/**
	 * Saves the specified settings array to the database.
	 *
	 * @since Version 1.0.0
	 * @access protected
	 * @param array $settings an array of settings to save to the database.
	 * @return void
	 **/
	private function _getSettings($refresh = FALSE)
	{
		$settings = FALSE;
		if (isset($this->EE->session->cache[MX_NOTIFY_CONTROL_NAME][__CLASS__]['settings']) === FALSE || $refresh === TRUE)
		{
			$settings_query = $this->EE->db->select('settings')->where('enabled', 'y')->where('class', __CLASS__)->get('extensions', 1);

			if ($settings_query->num_rows())
			{
				$settings = unserialize($settings_query->row()->settings);
				$this->_saveSettingsToSession($settings);
			}
		}
		else
		{
			$settings = $this->EE->session->cache[MX_NOTIFY_CONTROL_NAME][__CLASS__]['settings'];
		}
		return $settings;
	}

	/**
	 * Saves the specified settings array to the session.
	 * @since Version 1.0.0
	 * @access protected
	 * @param array $settings an array of settings to save to the session.
	 * @param array $sess A session object
	 * @return array the provided settings array
	 **/
	private function _saveSettingsToSession($settings, &$sess = FALSE)
	{
		// if there is no $sess passed and EE's session is not instaniated
		if ($sess == FALSE && isset($this->EE->session->cache) == FALSE)
			return $settings;

		// if there is an EE session available and there is no custom session object
		if ($sess == FALSE && isset($this->EE->session) == TRUE)
			$sess =& $this->EE->session;

		// Set the settings in the cache
		$sess->cache[MX_NOTIFY_CONTROL_NAME][__CLASS__]['settings'] = $settings;

		// return the settings
		return $settings;
	}


	/**
	 * Saves the specified settings array to the database.
	 *
	 * @since Version 1.0.0
	 * @access protected
	 * @param array $settings an array of settings to save to the database.
	 * @return void
	 **/
	private function _saveSettingsToDB($settings)
	{
		$this->EE->db->where('class', __CLASS__)->update('extensions', array(
				'settings' => serialize($settings)
			));
	}
	/**
	 * Sets up and subscribes to the hooks specified by the $hooks array.
	 * @since Version 1.0.0
	 * @access private
	 * @param array $hooks a flat array containing the names of any hooks that this extension subscribes to. By default, this parameter is set to FALSE.
	 * @return void
	 * @see http://codeigniter.com/user_guide/general/hooks.html
	 **/
	private function _createHooks($hooks = FALSE, $settings = '')
	{
		if (!$hooks)
		{
			return false;
			// $hooks = $this->hooks;
		}

		$hook_template = array(
			'class' => __CLASS__,
			'settings' => $settings,
			'version' => MX_NOTIFY_CONTROL_VER
		);

		foreach ($hooks as $key => $hook)
		{
			if (is_array($hook))
			{
				$data['hook']   = $key;
				$data['method'] = (isset($hook['method']) === TRUE) ? $hook['method'] : $key;
				$data           = array_merge($data, $hook);
			}
			else
			{
				$data['hook'] = $data['method'] = $hook;
			}

			$hook             = array_merge($hook_template, $data);
			$hook['settings'] = serialize($hook['settings']);

			$query = $this->EE->db->select('hook')->where('class', __CLASS__)->where('method', $data['method'])->get('exp_extensions');

			if
			($query->num_rows() == 0)
			{
				$this->EE->db->query($this->EE->db->insert_string('exp_extensions', $hook));
			}

		}
	}

	/**
	 * Removes all subscribed hooks for the current extension.
	 *
	 * @since Version 1.0.0
	 * @access private
	 * @return void
	 * @see http://codeigniter.com/user_guide/general/hooks.html
	 **/
	private function _deleteHooks()
	{
		$this->EE->db->query("DELETE FROM `exp_extensions` WHERE `class` = '" . __CLASS__ . "'");
	}


	// END




	// --------------------------------
	//  Update Extension
	// --------------------------------

	function update_extension($current = '')
	{
		if ($current == '' or $current == MX_NOTIFY_CONTROL_VER)
		{
			return FALSE;
		}

		if ($current < '2.2.0')
		{
			$settings_query = $this->EE->db->select('settings')->where('enabled', 'y')->where('class', __CLASS__)->get('extensions', 1);

			if ($settings_query->num_rows())
			{
				$settings              = unserialize($settings_query->row()->settings);
				$new_settings[SITE_ID] = $settings;
				$this->_saveSettingsToDB($new_settings);
			}
		}

		if ($current < '2.2.3')
		{
			$this->EE->db->delete('exp_extensions', array('class' => __CLASS__));
			$this->_createHooks();
			$this->_saveSettingsToDB($this->settings);
		}

		if ($current < '2.2.4')
		{
			if (!$this->EE->db->table_exists('exp_mx_notify_control_members'))
			{
				$this->EE->db->query("CREATE TABLE IF NOT EXISTS exp_mx_notify_control_members (
									  `recipient_id` int(10) unsigned NOT NULL auto_increment,
									  `name`     varchar(128)     NOT NULL default '',
									  `email`        varchar(128)    NOT NULL default '',
									  `member_id`      varchar(50)      NOT NULL default '',
									  `auth`      varchar(2)     NOT NULL default '0',
									  `unsubscribe`     varchar(128)   NOT NULL default '0',
									  PRIMARY KEY (`recipient_id`)
									)");
			};

			if (!$this->EE->db->table_exists('exp_mx_notify_control_lists'))
			{
				$this->EE->db->query("CREATE TABLE IF NOT EXISTS exp_mx_notify_control_lists (
									  `subscribe_id` int(10) unsigned NOT NULL auto_increment,
									  `receiver_id` int(10) NOT NULL,
									  `list_name` varchar(128)     NOT NULL default '',
									  `unsubscribe`     varchar(128)   NOT NULL default '0',
									  PRIMARY KEY (`subscribe_id`)
									)");
			};

		}

		$this->EE->db->query("UPDATE exp_extensions SET version = '" . $this->EE->db->escape_str(MX_NOTIFY_CONTROL_VER) . "' WHERE class = '" . get_class($this) . "'");
	}
	// END

	function disable_extension()
	{
		$this->EE->db->delete('exp_extensions', array(
				'class' => __CLASS__
			));
		$this->EE->db->query("DROP TABLE exp_mx_notify_control_members");
		$this->EE->db->query("DROP TABLE exp_mx_notify_control_lists");

	}
	// END
}
// END CLASS

/* End of file ext.mx_notify_control.php */
/* Location: ./system/expressionengine/third_party/mx_notify_control/ext.mx_notify_control.php */
