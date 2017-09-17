<?php

require_once("vendor/ActiveCampaign/ActiveCampaign.class.php");

class ActiveCampaignMail {

  private $ci; //Codignter Class
  private $activecamp; //activecampaign class
  private $resultMessage = 'No Message!'; //error code
  private $errortext     = false; //error details
  private $listid;  //Defult List ID

  public function __construct() {
    $this->ci = & get_instance();
    $this->ci->load->config('activecampaign');
    //check API key
    if ($this->__checkKeyExist()) {
      $this->activecamp = new ActiveCampaign($this->ci->config->item('activecampaign_url'), $this->ci->config->item('activecampaign_key'));
      $this->setDefultList();
    }
  }

  //checking for if confirigation works
  private function __checkKeyExist() {
    $result = false;
    if (!($this->ci->config->item('activecampaign_url'))) {
      $this->errortext = "No ActiveCampaign URL Found";
    }
    elseif (!($this->ci->config->item('activecampaign_key'))) {
      $this->errortext = "No ActiveCampaign key Found";
    }
    else {
      $result = true;
    }
    return $result;
  }

  //return error
  public function get_error() {
    $result = FALSE;
    if (!$this->errorcode) {
      $result = $this->errortext;
    }
    return $result;
  }

  //echo error
  public function display_error() {
    echo $this->get_error();
  }

  //get message
  public function get_message() {
    return $this->resultMessage;
  }

  //Test ActiveCampaign validation
  public function check_connection() {
    $result = false;
    if (!(int) $this->activecamp->credentials_test()) {
      $this->errorcode = 1;
      $this->errortext = "Access denied: Invalid credentials (URL and/or API key).";
    }
    else {
      $result = true;
    }
    return $result;
  }

  //Get Account View
  public function getAccountInfo() {
    if ($this->check_connection()) {
      return (array) $this->activecamp->api("account/view");
    }
    else {
      return FALSE;
    }
  }

  //List All Lists
  public function getLists($id = 'all') {
    $param  = array('ids' => $id);
    $lists  = $this->activecamp->api("list/list", $param);
    $result = false;
    if (!$lists->success) {
      $this->errortext = $lists->error;
    }
    else {
      //unset($lists->result_code, $lists->result_message, $lists->http_code, $lists->success, $lists->result_output);
      foreach ($lists as $list) {
        if ($list->id)
          $result[] = (array) $list;
      }
    }
    return $result;
  }

  //Setting Defult List
  public function setDefultList($id = 0) {
    if (!$id) {
      if ($this->ci->config->item('activecampaign_list_id'))
        $this->listid    = $this->ci->config->item('activecampaign_list_id');
      else
        $this->errortext = "No List has been selected";
    }
    else
      $this->listid = $id;
  }

  //Add contact into List
  public function addContact($email, $data = array(), $subscribe = 1) {
    $param                            = $data;
    $param['email']                   = $email;
    $param['ip4']                     = $this->ci->input->ip_address();
    $param["p[{$this->listid}]"]      = $this->listid;
    $param["status[{$this->listid}]"] = $subscribe;
    $result                           = $this->activecamp->api("contact/sync", $param);
    dump($result);
    if ($result->success) {
      $this->resultMessage = $result->result_message;
      return $result->subscriber_id;
    }
    else {
      $this->errortext = $result->error;
      return false;
    }
  }

  //Delete Contact by email or ID
  public function deleteContact($id) {
    $return = FALSE;
    $param  = array();
    if (filter_var($id, FILTER_VALIDATE_EMAIL)) {
      $param['id'] = $this->getContactIDbyEmail($id);
      $result      = $this->activecamp->api("contact/delete", $param);
      if ($result->success) {
        $return              = true;
        $this->resultMessage = $result->result_message;
      }
    }
    elseif (is_int($id)) {
      $param['id'] = $id;
      $result      = $this->activecamp->api("contact/delete", $param);
      if ($result->success) {
        $return              = true;
        $this->resultMessage = $result->result_message;
      }
    }
    else {
      $this->errortext = "Not Valid parameter!";
    }
    return $return;
  }

  //Get Contact Information
  public function getContact($id = 0) {
    $return = false;
    if (filter_var($id, FILTER_VALIDATE_EMAIL)) {
      $result = $this->activecamp->api("contact/view?email=$id");
      if ($result->success)
        return $result;
    }
    elseif (is_int($id)) {
      $result = $this->activecamp->api("contact/view?id=$id");
      if ($result->success)
        return $result;
    }
    else
      $this->errortext = "Not Valid parameter!";
    return $return;
  }

  //Get ID by Email
  public function getContactIDbyEmail($email) {
    $return = false;
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $result          = $this->activecamp->api("contact/view?email=$email");
      if ($result->success)
        return (int) $result->id;
      else
        $this->errortext = $result->result_message;
    }
    else
      $this->errortext = "Invalid Email Formate!";
    return $return;
  }

  
  //Sending Message
  public function beta_sendMail($maildata = array()) {
    $param  = array(
      //'id'                     => 0, // adds a new one
      'format'             => 'mime', // possible values: html, text, mime (both html and text)
      'subject'            => 'Fetch at send: ' . date("m/d/Y H:i", strtotime("now")), // username cannot be changed!
      'fromemail'          => 'perry@shipcustomerdirect.com',
      'fromname'           => 'Testing Mail',
      'reply2'             => 'perry@shipcustomerdirect.com',
      'priority'           => '1', // 1=high, 3=medium/default, 5=low
      'charset'            => 'utf-8',
      'encoding'           => 'quoted-printable',
      // html version
      'htmlconstructor'    => 'external', // possible values: editor, external, upload
      // if editor, it uses 'html' parameter
      // if external, uses 'htmlfetch' and 'htmlfetchwhen' parameters
      // if upload, uses 'message_upload_html'
      //'html'                     => '<strong>html</strong> content of your email', // content of your html email
      'htmlfetch'          => 'http://yoursite.com', // URL where to fetch the body from
      'htmlfetchwhen'      => 'send', // possible values: (fetch at) 'send' and (fetch) 'pers'(onalized)
      //'message_upload_html[]'  => 123, // not supported yet: an ID of an uploaded content
      // text version
      'textconstructor'    => 'external', // possible values: editor, external, upload
      // if editor, it uses 'text' parameter
      // if external, uses 'textfetch' and 'textfetchwhen' parameters
      // if upload, uses 'message_upload_text'
      //'text'                     => '_text only_  content of your email', // content of your text only email
      'textfetch'          => 'http://yoursite.com', // URL where to fetch the body from
      'textfetchwhen'      => 'send', // possible values: (fetch at) 'send' and (fetch) 'pers'(onalized)
      //'message_upload_text[]'  => 123, // not supported yet: an ID of an uploaded content
      // assign attachments
      //'attach[123]'            => 123, // not supported yet: an ID of an uploaded file
      // assign to lists:
      "p[{$this->listid}]" => $this->listid, // example list ID
        //'p[345]'                 => 345, // some additional lists?
    );
    $result = $this->activecamp->api("message/add", $param);
    if ($result->success) {
      $return              = true;
      $this->resultMessage = $result->result_message;
    }
    else {
      $return = false;
      $this->errortext = $result->result_message;
    }
    return $return;
  }

}
