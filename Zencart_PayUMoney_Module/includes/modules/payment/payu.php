<?php

/**
 *  Zencart
 *  Copyright (c) 2010 PayU
 */
class payu extends base {

  var $code, $title, $description, $enabled, $_order_id;
  
  /**
   * constructor
   */
  function payu() {
    $this->code = 'payu';
    $this->title = MODULE_PAYMENT_PAYU_TEXT_TITLE;
    $this->sort_order = MODULE_PAYMENT_PAYU_SORT_ORDER;
    $this->enabled = ((MODULE_PAYMENT_PAYU_STATUS == 'True') ? true : false);
    $this->form_action_url = 'https://test.payu.in/_payment.php';

    if (MODULE_PAYMENT_PAYU_TESTMODE == 'LIVE') {
      $this->form_action_url = 'https://secure.payu.in/_payment.php';
    }
  }

  /**
   * update status function.
   * 
   * @global type $order
   * @global type $db
   */
  function update_status() {
    global $order;
    if (((int) MODULE_PAYMENT_EBS_VALID_ZONE > 0)) {
      $checkFlag = false;
      global $db;
      $sql = "select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_PAYU_VALID_ZONE . "' and zone_country_id = '" . $order->delivery['country']['id'] . "' order by zone_id";
      $result = $db->Execute($sql);
      if ($result)
        while (!$result->EOF) {
          if ($result->fields['zone_id'] < 1) {
            $checkFlag = true;
            break;
          } elseif ($result->fields['zone_id'] == $order->delivery['zone_id']) {
            $checkFlag = true;
            break;
          }
        }

      if ($checkFlag == false) {
        $this->enabled = false;
      }
    }
  }

  /**
   * javascript validation.
   * 
   * @return string
   */
  function javascript_validation() {
    return '';
  }

  /**
   * selection.
   * 
   * @return type
   */
  function selection() {
    $selection = array('id' => $this->code,
      'module' => $this->title);
    return $selection;
  }

  /**
   * pre confirmation check.
   * 
   * @return boolean
   */
  function pre_confirmation_check() {
    return false;
  }

  /**
   * confirmation.
   * 
   * @return type
   */
  function confirmation() {
    $confirmation = array('title' => $this->description);
    return $confirmation;
  }

  /**
   * process payment to payu.
   * 
   * @global type $insert_id
   * @global type $order
   * @global type $order_total_modules
   * @global type $currencies
   * @global type $customer_id
   * @global type $od_amount
   * @global type $totals
   * @return string
   */
  function process_button() {
    global $insert_id, $order, $order_total_modules, $currencies, $customer_id, $od_amount, $totals;
    
//@TODO: why do we need this ?
//    $temp = mysql_query("select value from currencies where code='INR'") or die(mysql_error());
//    $currency_value = mysql_fetch_array($temp);
    $products_ordered = '';
    for ($i = 0, $n = sizeof($order->products); $i < $n; $i++) {
      $products_ordered .= $order->products[$i]['qty'] . ' x ' . $order->products[$i]['name'] . ' (' . $order->products[$i]['model'] . ') = ' . $currencies->display_price($order->products[$i]['final_price'], $order->products[$i]['tax'], $order->products[$i]['qty'], $order->products[$i]['products_discount_type_from']) . $products_ordered_attributes . "\n";
    }

    $products_ordered .= "\n";
    $order_totals = $order_total_modules->process();

    for ($i = 0, $n = sizeof($order_totals); $i < $n; $i++) {
      $products_ordered .= strip_tags($order_totals[$i]['title']) . ' ' . strip_tags($order_totals[$i]['text']) . "\n";
    }

    $hashSequence = "key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5|udf6|udf7|udf8|udf9|udf10";
    $posted = array();
    $posted['txnid'] = substr(hash('sha256', mt_rand() . microtime()), 0, 20);
    if ($order_totals[5]['title'] == 'Total:') {
      $amt = $order_totals[5]['value'];
    } else {
      $amt = $order_totals[6]['value'];
    }

    $amt = (int) $amt;
    $posted['amount'] = $amt;
    $posted['firstname'] = $order->customer['firstname'];
    $posted['phone'] = $order->customer['telephone'];
    $posted['key'] = MODULE_PAYMENT_PAYU_MERCHANTID;
    $udf2 = $posted['txnid'];
    $posted['udf2'] = $udf2;
    $posted['service_provider'] = 'payu_paisa';
    $posted['productinfo'] = 'Order ID' . $order->info['orders_id'];
    $posted['email'] = $order->customer['email_address'];

    $hashVarsSeq = explode('|', $hashSequence);
    $hash_string = '';
    foreach ($hashVarsSeq as $hash_var) {
      $hash_string .= isset($posted[$hash_var]) ? $posted[$hash_var] : '';
      $hash_string .= '|';
    }
    $hash_string .= MODULE_PAYMENT_PAYU_SALT;
    $hash = strtolower(hash('sha512', $hash_string));
    $posted['hash'] = $hash;

    $process_button_string = zen_draw_hidden_field('key', $posted['key']) .
        zen_draw_hidden_field('amount', $posted['amount']) .
        zen_draw_hidden_field('productinfo', $posted['productinfo']) .
        zen_draw_hidden_field('firstname', $posted['firstname']) .
        zen_draw_hidden_field('email', $posted['email']) .
        zen_draw_hidden_field('phone', $posted['phone']) .
        zen_draw_hidden_field('service_provider', $posted['service_provider']) .
        zen_draw_hidden_field('furl', zen_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL')) .
        zen_draw_hidden_field('surl', zen_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL')) .
        zen_draw_hidden_field('lastname', $order->customer['lastname']) .
        zen_draw_hidden_field('address1', $order->customer['street_address']) .
        zen_draw_hidden_field('address2', $order->delivery['street_address']) .
        zen_draw_hidden_field('city', $order->customer['city']) .
        zen_draw_hidden_field('state', $order->customer['state']) .
        zen_draw_hidden_field('postal_code', $order->customer['postcode']) .
        zen_draw_hidden_field('country', $order->customer['country']['iso_code_3']) .
        zen_draw_hidden_field('udf1', $udf1) . zen_draw_hidden_field('udf2', $udf2) . zen_draw_hidden_field('udf3', $udf3) .
        zen_draw_hidden_field('udf4', $udf4) . zen_draw_hidden_field('udf5', $udf5) .
        zen_draw_hidden_field('txnid', $posted['txnid']) .
        zen_draw_hidden_field('hash', $posted['hash']) .
        zen_draw_hidden_field('curl', zen_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL'));

    return $process_button_string;
  }

  /**
   * before process.
   */
  function before_process() {
    $txnRs = array();
    if (!empty($_POST)) {
      foreach ($_POST as $key => $value) {
        $txnRs[$key] = htmlentities($value, ENT_QUOTES);
      }
    }

    $txnRs['txnid1'] = $txnRs['txnid'];
    $txnRs['txnid'] = htmlentities($_POST['udf2'], ENT_QUOTES);

    if ($txnRs['status'] == 'success') {
      $merc_hash_vars_seq = array_reverse(explode('|', "key|txnid1|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5|udf6|udf7|udf8|udf9|udf10"));
//      //generation of hash after transaction is = salt + status + reverse order of variables
//      $merc_hash_vars_seq = array_reverse($merc_hash_vars_seq);

      $merc_hash_string = MODULE_PAYMENT_PAYU_SALT . '|' . $txnRs['status'];
      foreach ($merc_hash_vars_seq as $merc_hash_var) {
        $merc_hash_string .= '|';
        $merc_hash_string .= isset($txnRs[$merc_hash_var]) ? $txnRs[$merc_hash_var] : '';
      }

      $merc_hash = strtolower(hash('sha512', $merc_hash_string));
      if ($merc_hash != $txnRs['hash']) {
        zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'));
      }
    } else {
      zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'));
    }
  }

  /**
   * after process
   * 
   * @return boolean
   */
  function after_process() {
    return false;
  }

  /**
   * get error
   * 
   * @return type
   */
  function get_error() {
    $error = array('title' => MODULE_PAYMENT_EBS_TEXT_ERROR,
      'error' => stripslashes(urldecode($_GET['error'])));
    return $error;
  }

  /**
   * check function
   * 
   * @global type $db
   * @return type
   */
  function check() {
    global $db;

    if (!isset($this->_check)) {
      $check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_PAYU_STATUS'");
      $this->_check = $check_query->RecordCount();
    }

    return $this->_check;
  }

  /**
   * install payu payment model
   * 
   * @global type $db
   */
  function install() {
    global $db;
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable PayUMoney Payment Module', 'MODULE_PAYMENT_PAYU_STATUS', 'True', 'Do you want to accept PayUMoney payments?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Merchant Key', 'MODULE_PAYMENT_PAYU_MERCHANTID', 'JBZaLc', 'Your Merchant Key from PayUMoney', '5', '0', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('SALT', 'MODULE_PAYMENT_PAYU_SALT', 'GQs7yium', 'Your SALT from PayUMoney', '6', '0', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Test Mode', 'MODULE_PAYMENT_PAYU_TESTMODE', 'TEST', 'Test mode used for the PayUMoney', '6', '0', 'zen_cfg_select_option(array(\'TEST\', \'LIVE\'), ', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display', 'MODULE_PAYMENT_PAYU_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '2', now())");
  }

  /**
   * remove module
   * 
   * @global type $db
   */
  function remove() {
    global $db;
    $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
  }

  /**
   * keys
   * 
   * @return type
   */
  function keys() {
    return array('MODULE_PAYMENT_PAYU_STATUS', 'MODULE_PAYMENT_PAYU_MERCHANTID', 'MODULE_PAYMENT_PAYU_SALT', 'MODULE_PAYMENT_PAYU_TESTMODE');
  }
}
