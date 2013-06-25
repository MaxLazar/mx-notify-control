<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Mx_delegate_email_ft extends EE_Fieldtype
{

	var $info = array(
		'name' => 'MX Delegate Email',
		'version' => '1.1'
	);

	var $prefix = 'delegate_email_';


	function Mx_delegate_email_ft()
	{
		parent::EE_Fieldtype();
	}

	// --------------------------------------------------------------------

	function display_field($data)
	{

		if (!$this->settings[$this->prefix . 'list'])
		{
			return '';
		}
		;

		$out = '<style>
/*Navigation*/
.nav{
	overflow: hidden;
	list-style: none;
	margin: 0;
	padding: 0 0 2px 0;
}
.nav li{
	width:200px;
	height:27px;
	text-align: center;
	background: #f3f3f3;
	border: 1px solid #d8d8d8;
	position: relative;
	z-index: 1;
	margin-left: -1px;

}
.nav li:hover{
	background: #f3f3f3;
	border-color: #c6c6c6;
	z-index: 2;
	box-shadow:0 1px 2px #dfdfdf;
	-webkit-box-shadow:0 1px 2px #dfdfdf;
	-moz-box-shadow:0 1px 2px #dfdfdf;

}

.nav li:first-child{
	border-left:1px solid #d8d8d8;
	border-radius:2px 0 0 2px;
	-webkit-border-radius:2px 0 0 2px;
	-moz-border-radius:2px 0 0 2px;
	margin-left: 0;
}
.nav li:last-child{
	border-radius:0 2px 2px 0;
	-webkit-border-radius:0 2px 2px 0;
	-moz-border-radius:0 2px 2px 0;

}

.nav li a{
	text-decoration: none;
	color: #444444;
	font-size: 12px;
	line-height:27px;
	display: block;
	text-align: center;
	padding: 0 23px;
	font-weight: bold;
}
.nav li:hover a{
	color: #222222;
}
.nav li.active,.nav li.active:hover{
	border-color: #cccccc;
	z-index: 2;
	color: #333333;

	box-shadow:inset 0 0px 3px #cecece;
	-webkit-box-shadow:inset 0 0px 3px #cecece;
	-moz-box-shadow:inset 0 0px 3px #cecece;

	background-image: url("svg-gradient.svg");
	background-image: linear-gradient(bottom, rgb(223,223,223) 30%, rgb(235,235,235) 65%);
	background-image: -o-linear-gradient(bottom, rgb(223,223,223) 30%, rgb(235,235,235) 65%);
	background-image: -moz-linear-gradient(bottom, rgb(223,223,223) 30%, rgb(235,235,235) 65%);
	background-image: -webkit-linear-gradient(bottom, rgb(223,223,223) 30%, rgb(235,235,235) 65%);
	background-image: -ms-linear-gradient(bottom, rgb(223,223,223) 30%, rgb(235,235,235) 65%);



	background-image: -webkit-gradient(
		linear,
		left bottom,
		left top,
		color-stop(0.3, rgb(223,223,223)),
		color-stop(0.65, rgb(235,235,235))
	);
}
.nav li.active a,.nav li.active:hover a{
	color: #333333;
}
</style>
';


		//        $out . = '<ul>';
		$out = '';
		$out .= '<select name="mx_reassign_'.$this->field_name.'">';
		$out .=  "<option value=''>Reassign the notification</option>";

		$name = '';
		$email = '';
		$responsible = '';
		foreach (explode("\n", $this->settings[$this->prefix . 'list']) as $key => $val)
		{
			//   echo $key.'=>'.$val;
			list($name, $email) = explode('|', $val);


			if ($data == $email)
			{
				$responsible =  $name;

			}

			$out .=  "<option value='$email'>$name</option>";
			/*             $out .= '<ul class="nav">
<li '.(($data == $email) ? 'class="active"' : '') . '><a href="#">'.$name.'</a></li>
';*/

			// $out .= '<li><input type="radio" name="' . $this->field_name . '" value="' . $email . '" ' . (($data == $email)
			//        ? 'checked' : '') . '> ' . $name . '</li>';
		}
		//  $out .= '</ul>'; $entry_id exp_members

		$entry_id = $this->EE->input->get('entry_id');
		
		if ($entry_id)
		{
			$result = $this->EE->db->query("SELECT en.author_id as author_id, mm.email as email, mm.screen_name  as name
									FROM  exp_channel_titles as en
									LEFT JOIN exp_members as mm
									ON  mm.member_id = en.author_id
									WHERE  entry_id = '" . $entry_id . "'");

			if ($result->num_rows() > 0)
			{
				$out .=  "<option value='".$result->row()->email."'".(($data == $result->row()->email) ? "select" : "").">Author: ".$result->row()->name."</option>";
				if ($data == $result->row()->email)
				{
					$responsible =  $result->row()->name;

				}
			}
		}


		$out .= '</select>';
		$out .= '<p><span class="green"><b>Current person: '.$responsible.'</b><input type=hidden value="' . $data . '" name="' . $this->field_name . '"></p>';

		return $out;
	}

	function save($data)
	{
		if ($this->EE->input->get_post('mx_reassign_' . $this->field_name))
		{
			$data = $this->EE->input->get_post('mx_reassign_' . $this->field_name);
		}

		return $data;
	}

	function display_settings($data)
	{
		$val = (isset($data[$this->prefix . 'list'])) ? $data[$this->prefix . 'list'] : '';

		$this->EE->table->add_row($this->EE->lang->line('Persons <br/><span><i>(name | email)</i></span>'), form_textarea(array(
					'id' => $this->prefix . 'list',
					'name' => $this->prefix . 'list',
					'size' => 4,
					'value' => $val

				)));
	}

	// --------------------------------------------------------------------

	/**
	 * Save Settings
	 *
	 * @access public
	 * @return field settings
	 *
	 */

	function save_settings($data)
	{

		return array(
			$this->prefix . 'list' => $_POST[$this->prefix . 'list']
		);

	}
}
// END Mx_delegate_email_ft class

/* End of file ft.mx_delegate_email.php */
/* Location: ./system/expressionengine/third_party/mx_delegate_email/ft.mx_delegate_email.php */