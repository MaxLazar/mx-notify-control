<?php

/**
 *  MX Notify Control Class for ExpressionEngine2
 *
 * @package		ExpressionEngine
 * @subpackage	Plugins
 * @category	Plugins
 * @author    Max Lazar <max@eec.ms>
 * @Commercial - please see LICENSE file included with this distribution
 */

require_once PATH_THIRD . 'mx_notify_control/config.php';

$plugin_info = array(
    'pi_name' => MX_NOTIFY_CONTROL_NAME,
    'pi_version' => MX_NOTIFY_CONTROL_VER,
    'pi_author' => MX_NOTIFY_CONTROL_AUTHOR,
    'pi_author_url' => MX_NOTIFY_CONTROL_DOCS,
    'pi_description' => '-',
    'pi_usage' => Mx_notify_control::usage()
);




Class Mx_notify_control
{
    var $return_data = "";
    var $var_name = "";
    var $cache = "";
    
    function Mx_notify_control()
    {
        $this->EE =& get_instance();       
    }
    
    function form()
    {
        $tagdata = $this->EE->TMPL->tagdata;
        
        $result_page = (!$this->EE->TMPL->fetch_param('result_page')) ? $this->EE->functions->fetch_current_uri() : $this->EE->TMPL->fetch_param('result_page');
        $list_name        = (!$this->EE->TMPL->fetch_param('list_name')) ? '' : $this->EE->TMPL->fetch_param('list_name');

        $form_details = array(
            'action' => $result_page,
            'name' => 'mx_notify_control',
            'secure' => FALSE,
            'id' => 'mx_notify_control',
            'hidden_fields' => array(
                'list_name' => $list_name,
                'result_page' => $result_page
            )
        );
        
        $r = $this->EE->functions->form_declaration($form_details);
        $r .= $this->EE->TMPL->tagdata;
        
        $r .= "</form>";
        return $this->return_data = $r;
	}
	
   function subscribe()
   {
   
		if ((isset($_POST) AND count($_POST) > 0) OR (isset($_GET) AND count($_GET) > 0)) {	
			$name = $this->EE->security->xss_clean($this->EE->input->get_post('name'));
			$mail = $this->EE->security->xss_clean($this->EE->input->get_post('email'));
			$list_name = $this->EE->security->xss_clean($this->EE->input->get_post('list_name'));

            $recipient_query = $this->EE->db->select('recipient_id')->where('email', $mail)->get('mx_notify_control_members', 1);
            
            if ($recipient_query->num_rows())
            {
				$receiver_id = $recipient_query->row()->recipient_id;
            }
			else
			{
				$data = array (
					'recipient_id' => '',
					'name'  =>$name,
					'email'  => $mail ,
					'member_id'   =>'',
					'auth' => '1',
					'unsubscribe' =>$this->EE->functions->random('alnum', 10)
				);
				
          		$this->EE->db->query($this->EE->db->insert_string('exp_mx_notify_control_members', $data));
				
				$receiver_id = $this->EE->db->insert_id();

			}
			

			$list_data = array (
					'subscribe_id' => '',
					'list_name' => $list_name,
					'receiver_id' => $receiver_id,
					'unsubscribe' =>$this->EE->functions->random('alnum', 10)
			);
			
			$list_query = $this->EE->db->select('subscribe_id')->where('list_name',  $list_name)->where('receiver_id', $receiver_id)->get('mx_notify_control_lists', 1);
            
            if ($list_query->num_rows())
            {
				return $this->return_data = "duplicate";
			}
			else {
				$this->EE->db->query($this->EE->db->insert_string('exp_mx_notify_control_lists', $list_data));
			}
			 return $this->return_data = "ok";
        } 
		
		return $this->return_data = "data is empty";
    }	
   
   function unsubscribe()
   {
   
		$id = $this->EE->security->xss_clean($this->EE->input->get_post(''));

    }		
	
    // ----------------------------------------
    //  Plugin Usage
    // ----------------------------------------
    
    // This function describes how the plugin is used.
    //  Make sure and use output buffering
    
    function usage()
    {
        ob_start();
		
?>

Short instructions:

MX Notify Settings -  Custom list: "entry_{entry_id}" (you can also use {site_name}{member_id}{author_id}{status}{url_title}{channel_id})

Form template example

{exp:mx_notify_control:form result_page="subscribe_template" list_name=" entry_{entry_id}"}

<p><input type="text" name="email" value="{email}" /></p>

<p><input type="text" name="name" value="" /></p> <p>

<input type="submit" value="submit" /></p>

{/exp:mx_notify_control:form}


Subscribe template:
status: {exp:mx_notify_control:subscribe}

<?php
        $buffer = ob_get_contents();
        
        ob_end_clean();
        
        return $buffer;
    }
    /* END */
    
}

/* End of file pi.mx_notify_control.php */
/* Location: ./system/expressionengine/third_party/mx_notify_control/pi.mx_notify_control.php*/