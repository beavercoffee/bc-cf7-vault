<?php
/*
Author: Beaver Coffee
Author URI: https://beaver.coffee
Description: Vault for Contact Form 7 successful submissions.
Domain Path:
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Network: true
Plugin Name: BC CF7 Vault
Plugin URI: https://github.com/beavercoffee/bc-cf7-vault
Requires at least: 5.7
Requires PHP: 5.6
Text Domain: bc-cf7-vault
Version: 1.7.9.1
*/

if(defined('ABSPATH')){
    require_once(plugin_dir_path(__FILE__) . 'classes/class-bc-cf7-vault.php');
    BC_CF7_Vault::get_instance(__FILE__);
}
