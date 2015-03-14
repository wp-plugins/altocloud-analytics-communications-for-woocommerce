<?php
/**
 * Plugin Name: Altocloud Analytics & Communications
 * Plugin URI: https://wordpress.org/plugins/altocloud-analytics-communications-for-woocommerce/
 * Description: Altocloud's WooCommerce integration enables real-time predictive analytics & voice, video or chat communications capabilities in your store. Convert shoppers to buyers and talk with the right visitors at the right moments.
 * Author: Altocloud
 * Author URI: http://altocloud.com/
 * Version: 1.0.0
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

/**
 * Add Altocloud's integration plugin to WooCommerce.
 */
function wc_altocloud_add_integration($integrations) {
  global $woocommerce;

  if (is_object($woocommerce)) {
		include_once 'includes/class-wc-altocloud-integration.php';
    $integrations[] = 'WC_Altocloud';
  }

  return $integrations;
}

/**
 * Action links for the plugin when displayed in the list
 */
function wc_altocloud_plugin_action_links($links) {
  global $woocommerce;

  if (version_compare($woocommerce->version, '2.1', '>=')) {
    $setting_url = 'admin.php?page=wc-settings&tab=integration';
  } else {
    $setting_url = 'admin.php?page=woocommerce_settings&tab=integration';
  }
  $links[] = '<a href="' . get_admin_url(null, $setting_url) . '">Settings</a>';

  return $links;
}

add_filter('woocommerce_integrations', 'wc_altocloud_add_integration', 10);
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wc_altocloud_plugin_action_links');
