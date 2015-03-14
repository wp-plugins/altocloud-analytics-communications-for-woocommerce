<?php
/**
 * Altocloud Integration for WooCommerce.
 *
 * @package WC_Altocloud
 * @category Integration
 * @author Ismael Rivera
 */
class WC_Altocloud extends WC_Integration {

  /**
   * Init and hook in the integration.
   */
  public function __construct() {
    global $woocommerce, $ac_products_list;

    // Define plugin metadata
    $this->id                 = 'altocloud';
    $this->method_title       = __('Altocloud', 'woocommerce-altocloud');
    $this->method_description = __("Altocloud's WooCommerce integration enables real-time predictive analytics & voice, video or chat communications capabilities in your store. Convert shoppers to buyers and talk with the right visitors at the right moments.", 'woocommerce-altocloud');

    // Load settings
    $this->init_form_fields();
    $this->init_settings();

    // Define user set variables
    $this->account_id = $this->get_option('account_id');
    $this->snippet = $this->get_option('snippet');
    $this->ecommerce = $this->get_option('ecommerce');

    // Filters
    add_filter('woocommerce_settings_api_sanitized_fields_' . $this->id, array($this, 'sanitize_settings'));

    // Bootstrap actions
    add_action('woocommerce_update_options_integration_' . $this->id, array($this, 'process_admin_options'));
    add_action('wp_head', array($this, 'basic_snippet'));

    // E-commerce tracking events (server side)
    add_action('woocommerce_add_to_cart', array($this, 'product_added'));
    add_action('woocommerce_thankyou', array($this, 'order_placed'));

    // E-commerce tracking events (AJAX)
    add_action('woocommerce_before_add_to_cart_button', array($this, 'cache_product_data'));
    add_action('woocommerce_before_shop_loop_item', array($this, 'cache_product_data'));
    add_action('wp_footer', array($this, 'track_ajax_add_to_cart'));
  }

  /**
   * Initialize integration settings form fields.
   *
   * @return void
   */
  public function init_form_fields() {
    $this->form_fields = array(
      'account_id' => array(
        'title'             => __('Account ID', 'woocommerce-altocloud'),
        'type'              => 'text',
        'description'       => __('Enter your account ID. You can find this in the registration email, or in the Admin Dashboard -> Settings page', 'woocommerce-altocloud'),
        'desc_tip'          => true,
        'default'           => ''
      ),
      'snippet' => array(
        'title'             => __('Tracking snippet', 'woocommerce-altocloud'),
        'type'              => 'checkbox',
        'label'             => __('Add Altocloud tracking snippet (optional)', 'woocommerce-altocloud'),
        'default'           => 'yes',
        'description'       => __("This feature adds Altocloud tracking snippet to your store. You don't need to add the snippet manually in the head of the page.", 'woocommerce-altocloud')
      ),
      'ecommerce' => array(
        'title'             => __('E-commerce extension', 'woocommerce-altocloud'),
        'type'              => 'checkbox',
        'label'             => __('Enable e-commerce extension to track products & transactions (optional)', 'woocommerce-altocloud'),
        'default'           => 'yes',
        'description'       => __("This feature adds the necessary actions to track when products are added by your customers, and when they checkout successfully their shopping carts.", 'woocommerce-altocloud'),
      )
    );
  }

  /**
   * Santize our settings
   * @see process_admin_options()
   */
  public function sanitize_settings($settings ) {
    // the account ID should be all in lower case characters
    if (isset($settings) &&
        isset($settings['account_id'])) {
      $settings['account_id'] = strtolower($settings['account_id']);
    }
    return $settings;
  }

  /**
  * Altocloud tracking snippet
  *
  * @access public
  * @return void
  */
  function basic_snippet() {
    global $woocommerce;

    if (is_admin() || current_user_can('manage_options')) {
      return;
    }

    if (!$this->account_id || $this->snippet == 'no') {
      return;
    }

    $code = "(function(i,s,o,g,r,a,m){i['altocloud-sdk.js']=r;i[r]=i[r]||function(){
(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
})(window,document,'script','//altocloud-sdk.com/ac.js','ac');
ac('init', '" . esc_js($this->account_id) . "');
ac('pageview');";

    // User/visitor identity tracking (when logged in)
    if (is_user_logged_in()) {
      $current_user = wp_get_current_user();
      $uid = $current_user->ID;

      $profile = array(
        'displayName' => esc_js($current_user->display_name),
        'email' => esc_js($current_user->user_email),
        'givenName' => esc_js($current_user->user_firstname),
        'familyName' => esc_js($current_user->user_lastname),
        'username' => esc_js($current_user->user_login),
        'status' => esc_js($current_user->user_status),
        'registered' => esc_js($current_user->user_registered)
      );

      $code .= "\nac('identify', $uid, " . json_encode($profile) . ");";
    }

    echo "<script type=\"text/javascript\">\n" . $code . "\n</script>";
  }

  /**
   *
   * @access public
   * @return void
   */
  function set_order() {
    global $woocommerce;

    if (is_admin() || current_user_can('manage_options')) {
      return;
    }

    if (!$this->account_id || $this->ecommerce == 'no') {
      return;
    }


  }

  /**
   * E-commerce tracking for single product added to cart
   *
   * @access public
   * @return void
   */
  function product_added($cart_item_key) {
    global $woocommerce;

    if (is_admin() || current_user_can('manage_options')) {
      return;
    }

    if (!$this->account_id || $this->ecommerce == 'no') {
      return;
    }

    // cart values are lazily calculated, thus they need to be calculated
    // before trying to access them
    $woocommerce->cart->calculate_totals();

    // set order details (revenue, tax, etc.) in the visit tracking data.
    $cart_data = array(
      'affiliation' => esc_js(get_bloginfo('name')),
      'item_count' => esc_js($woocommerce->cart->cart_contents_count),
      'revenue' => esc_js($woocommerce->cart->subtotal),
      'tax' => esc_js($woocommerce->cart->tax_total),
      'shipping' => esc_js($woocommerce->cart->shipping_total),
      'currency' => esc_js(get_woocommerce_currency())
    );

    // fire an event when a product is added to the cart
    $item = $woocommerce->cart->cart_contents[$cart_item_key];
    $product = new WC_Product($item['product_id']);
    $product_data = array(
      'id' => esc_js($product->get_sku() ? $product->get_sku() : $product->id),
      'name' => esc_js($product->get_title()),
      'quantity' => esc_js($item['quantity']),
      'categories' => $this->get_category_names($product->id),
      'price' => esc_js($product->get_price()),
      'currency' => esc_js(get_woocommerce_currency())
    );

    $this->wc_enqueue_js("ac('set', " . json_encode($cart_data) . ");");
    $this->wc_enqueue_js("ac('record', 'product.added', '" . $product_data['name'] . "', " . json_encode($product_data) . ");");
  }

  function order_placed($order_id) {
    global $woocommerce;

    // _ac_tracked is set to `1` the 1st time the event is triggered
    if (current_user_can('manage_options') || get_post_meta($order_id, '_ac_tracked', true) == 1) {
      return;
    }

    if (!$this->account_id) {
      return;
    }

    // get the order that was just placed
    $order = new WC_Order($order_id);

    // get shipping cost based on version > 2.1 get_total_shipping() < get_shipping
    if (version_compare($woocommerce->version, "2.1", ">=")) {
      $shipping_cost = $order->get_total_shipping();
    } else {
      $shipping_cost = $order->get_shipping();
    }

    $order_data = array(
      'id' => esc_js($order->get_order_number()),
      'affiliation' => esc_js(get_bloginfo('name')),
      'revenue' => esc_js($order->get_total()),
      'tax' => esc_js($order->get_total_tax()),
      'shipping' => esc_js($shipping_cost),
      'currency' => esc_js(get_woocommerce_currency())
    );

    $code = "\nac('record', 'order.placed', '" . $order_data['id'] . "', " . json_encode($order_data) . ");";

    $this->wc_enqueue_js($code);
    update_post_meta($order_id, '_ac_tracked', 1);
  }

  /**
   * Track products added to cart via AJAX requests.
   *
   * @access public
   * @return void
   */
  function track_ajax_add_to_cart() {
    global $ac_products_list;

    if (is_admin() || current_user_can('manage_options')) {
      return;
    }

    if (!$this->account_id || $this->ecommerce == 'no') {
      return;
    }

    // global variables with data of the products shown in the page, currency, etc.
    $this->wc_enqueue_js('_ac_cur=' . json_encode(get_woocommerce_currency()) . ';');
    $this->wc_enqueue_js('_ac_prod_list=' . json_encode($ac_products_list) . ';');
    $this->wc_enqueue_js('_ac_add_to_cart=[];');

    // keep track of the product_id related to the add_to_cart button when clicked
    $add_to_cart_handler = '
$(document).on("click", ".add_to_cart_button", function() {
  _ac_add_to_cart.push($(this).data("product_id"));
});';

    // woocommerce triggers event `added_to_cart` when the AJAX request is completed,
    // so that the event `product.added` can be sent, taking the product_id of the last
    // click on the add_to_cart button/link
    // if multiple products are added, they will be removed from the queue as in FIFO,
    // since we don't know the relation between them & the `added_to_cart` events
    $added_to_cart_handler = '
$("body").on("added_to_cart", function() {
  var id;
  while (id = _ac_add_to_cart.shift()) {
    if (_ac_prod_list[id]) {
      ac("record", "product.added", _ac_prod_list[id].name, _ac_prod_list[id]);
    }
  }
});';

    $this->wc_enqueue_js($add_to_cart_handler);
    $this->wc_enqueue_js($added_to_cart_handler);
  }

  /**
   * Cache product(s) data into a global variable.
   *
   * @access public
   * @return void
   */
  function cache_product_data() {
    global $product;
    global $ac_products_list;

    if (is_admin() || current_user_can('manage_options')) {
      return;
    }

    if (!$this->account_id || $this->ecommerce == 'no') {
      return;
    }

    if (!is_array($ac_products_list)) {
      $ac_products_list = array();
    }

    $ac_products_list[$product->id] = array(
      'id' => esc_html($product->id),
      'sku' => esc_html($product->get_sku()),
      'name' => esc_html($product->get_title()),
      'categories' => $this->get_category_names($product->id),
      'price' => esc_html($product->get_price())
    );
  }

  /**
   * Get the product's category names as an array.
   *
   * @access private
   * @param integer $product_id
   * @return array[string]
   */
  private function get_category_names($product_id) {
    $names = array();
    $categories = get_the_terms($product_id, 'product_cat');
    if ($categories) {
      foreach ($categories as $category) {
        $names[] = $category->name;
      }
    }
    return $names;
  }

  /**
   * WooCommerce 2.1 support for wc_enqueue_js
   *
   * @access private
   * @param string $code
   * @return void
   */
  private function wc_enqueue_js($code) {
    if (function_exists('wc_enqueue_js')) {
      wc_enqueue_js($code);
    } else {
      global $woocommerce;
      $woocommerce->add_inline_js($code);
    }
  }
}
