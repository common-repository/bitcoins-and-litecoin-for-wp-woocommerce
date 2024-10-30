<?php
/*
Plugin Name: WooCommerce  Btcmerch Gateway
Plugin URI: http://wordpress.org/plugins/bitcoins-and-litecoin-for-wp-woocommerce/
Description: 
Version: 2.01
Author: BTCMerch.com
*/

add_action('plugins_loaded', 'init_WC_Btcmerch_Payment_Gateway', 0);


function init_WC_Btcmerch_Payment_Gateway() {

  if(!class_exists('WC_Payment_Gateway')) return;

  class WC_Btcmerch_Payment_Gateway extends WC_Payment_Gateway{

    public function __construct(){

      $this->id = 'btcmerch';
      $this->has_fields = false;

      // Load the form fields.
      $this->init_form_fields();

      // Load the settings.
      $this->init_settings();

      // Define user set variables

      $this->title = $this->settings['title'];
      $this->description = $this->settings['description'];
      $this->icon = apply_filters('woocommerce_btcmerch_icon', $this->settings['btcmerch_icon_url']);

      // Actions
      add_action('woocommerce_update_options_payment_gateways_'.$this->id, array(&$this, 'process_admin_options'));
      add_action('woocommerce_thankyou_cheque', array(&$this, 'thankyou_page'));
      add_action('woocommerce_receipt_'. $this->id, array( $this, 'receipt_page' ) );
      add_action('woocommerce_email_before_order_table', array(&$this, 'email_instructions'), 10, 2);
      //add_action('init',array($this, 'btcmerch_callback'));

    }

    function init_form_fields()
    {
      $this->form_fields = array(
        'enabled' => array(
          'title' => __( 'Enable/Disable', 'woothemes' ),
          'type' => 'checkbox',
          'label' => __( 'Enable Btcmerch Payment', 'woothemes' ),
          'default' => 'yes'
        ),
        'title' => array(
          'title' => __( 'Title', 'woothemes' ),
          'type' => 'text',
          'description' => __( 'This controls the title which the user sees during checkout.', 'woothemes' ),
          'default' => __( 'Bitcoins/Litecoins', 'woothemes' )
        ),
        'callback' => array(
          'title' => __('Your callback URL', 'woothemes'),
          'type' => 'text',
          'disabled' => true,
          'description' => __('Your callback URL'),
          'default' => get_option('siteurl') . '/?page_id=' . get_option('woocommerce_checkout_page_id')
        ),
        'btcmerch_url' => array(
          'title' => __('Btcmerch url', 'woothemes'),
          'type' => 'text',
          'description' => __('Enter Btcmerch url'),
          'default' => 'https://www.btcmerch.com/payment'
        ),
        'sandbox_enabled' => array(
          'title' => __( 'Enable/Disable Sandbox', 'woothemes' ),
          'type' => 'checkbox',
          'label' => __( 'Enable Sandbox Mode', 'woothemes' ),
          'default' => 'no'
        ),
        'btcmerch_sandbox_url' => array(
          'title' => __('Btcmerch sandbox url', 'woothemes'),
          'type' => 'text',
          'description' => __('Enter Btcmerch sandbox url if you use sandbox'),
          'default' => 'https://sandbox.btcmerch.com/payment'
        ),

        'merchant_id' => array(
          'title' => __('Merchant Id', 'woothemes'),
          'type' => 'text',
          'description' => __('Enter the Merchant Id you created at Btcmerch.com'),
        ),

        'apiKey' => array(
          'title' => __('apiKey', 'woothemes'),
          'type' => 'text',
          'description' => __('Enter the apiKey you created at Btcmerch.com'),
        )
      );
    }

    public function admin_options() {
      ?>
      <h3><?php _e('Bitcoin/Litecoin Payment', 'woothemes'); ?></h3>
      <p><?php _e('Allows bitcoin/litecoin payments via Btcmerch.com ', 'woothemes'); ?></p>
      <table class="form-table">
        <?php
        // Generate the HTML For the settings form.
        $this->generate_settings_html();
        ?>
      </table>
    <?php
    } // End admin_options()

    public function email_instructions( $order, $sent_to_admin ) {
      return;
    }

    function payment_fields() {
      if ($this->description) echo wpautop(wptexturize($this->description));
    }

    function thankyou_page() {
      if ($this->description) echo wpautop(wptexturize($this->description));
    }


    function process_payment($order_id){
      $order = new WC_Order( $order_id );

      return array(
        'result' => 'success',
        'redirect'  => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
      );
    }

    public function receipt_page($order){
      echo $this->generate_form($order);
    }

    public function generate_form($order_id){
      $order = new WC_Order( $order_id );
      $action_adr = $this->settings['btcmerch_url'];


      if ($this->settings['sandbox_enabled'] == 'yes') {
        $btcmerch_url = $this->settings['btcmerch_sandbox_url'];
      } else {
        $btcmerch_url = $this->settings['btcmerch_url'];
      }



      $prefix = 'billing_';

      $args = array(
        'amount'  => $order->order_total,
        'currency_code' => get_woocommerce_currency(),
        'merchant_id' => $this->settings['merchant_id'],
        'item_number' => $order_id,
        'item_name'     => "Payment for order - $order_id",
        'first_name' => $order->{$prefix.first_name},
        'last_name' => $order->{$prefix.last_name},
        'address1' => $order->{$prefix.address_1},
        'address2' => $order->{$prefix.address_2},
        'city' => $order->{$prefix.city},
        'state' => $order->{$prefix.state},
        'zip' => $order->{$prefix.postcode},
        'country' => $order->{$prefix.country},
        'email'   => $order->{$prefix.email}
      );

      $args_array = array();

      $uri = '';

      //create uri for iframe
      foreach ($args as $k => $v) {
        $uri.= $k.'='.urldecode($v).'&';
      }
      $uri = substr($uri,0,-1);

            $html = '<form method="post" id="checkout_form" action="'.$btcmerch_url.'">';
      foreach($args as $k=>$v) {
          $html .= '<input type="hidden" name="'.$k.'" value="'.$v.'">';
      }
      $html .='</form><script type="text/javascript">document.forms["checkout_form"].submit();</script>';

      echo $html;
      die();
    }

    public function admin_options1() {
      ?>
      <h3><?php _e('Bitcoin Payment', 'woothemes'); ?></h3>
      <p><?php _e('Allows bitcoin payments via Btcmerch.com ', 'woothemes'); ?></p>
      <table class="form-table">
        <?php
        // Generate the HTML For the settings form.
        $this->generate_settings_html();
        ?>
      </table>
    <?php
    } // End admin_options()

  }
}

function wc_btcmerch_callback(){


  global $woocommerce;

  $gateways = $woocommerce->payment_gateways->payment_gateways();
  if (!isset($gateways['btcmerch']))
  {
    return;
  }

  $btcmerchGateway = $gateways['btcmerch'];
  $str = '';
  $keys = array_keys($_POST);
  sort($keys);

  for ($i=0; $i < count($keys); $i++) {
    if ($keys[$i] !== 'hash') {
      $str .= $_POST[$keys[$i]];
    }
  }


  $str .= $btcmerchGateway->settings['apiKey'];


  if($_POST['hash'] && $_POST['item_number']) {
    if ($_POST['hash'] == md5($str)) {

      $sessionid = $_POST['item_number'];
      if (is_numeric($sessionid)) {

        $order = new WC_Order($sessionid);
        $order->payment_complete();

        echo "OK";

      } else{
        header("HTTP/1.0 404 Not Found");die();
      }
    } else{
      header("HTTP/1.0 404 Not Found");die();
    }
  } else {
    //header("HTTP/1.0 404 Not Found");die();
  }
}

add_action('init', 'wc_btcmerch_callback');
add_filter( 'woocommerce_payment_gateways', 'add_WC_Btcmerch_Payment_Gateway' );

function add_WC_Btcmerch_Payment_Gateway($methods){
  $methods[] = 'WC_Btcmerch_Payment_Gateway';
  return $methods;
}
?>
