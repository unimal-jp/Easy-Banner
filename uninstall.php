<?php
require_once dirname( __FILE__ ) . '/includes/easy-banner-db.php';

global $wpdb;
global $easy_banner_table_name;
$easy_banner_table_name = $wpdb->prefix . 'easy_banner_banners';

$plugin_prefix = 'easy-banner-';

//drop easy-banner table
$banner_db = new Easy_Banner_Db( $wpdb, $easy_banner_table_name );
if ($banner_db->has_table()) {
	$banner_db->drop_table();
}

//delete post meta data of easy-banner
$table_name = $wpdb->prefix . 'postmeta';
$wpdb->get_results( "DELETE FROM " . $table_name . " WHERE meta_key='" . $plugin_prefix . "ids';" );
$wpdb->get_results( "DELETE FROM " . $table_name . " WHERE meta_key='" . $plugin_prefix . "positions';" );
?>