<?php
class Easy_Banner_Db {
	private static $cash_banners = array();

	private $db;
	private $table_name;

	function __construct( $db, $table_name ) {
		$this->db = $db;
		$this->table_name = $table_name;
	}

	public function has_table() {
		return ( $this->db->get_var( "SHOW TABLES LIKE '" . $this->table_name . "'" ) == $this->table_name );
	}

	public function create_table() {
		$sql = "CREATE TABLE " . $this->table_name . "(" .
			"id INT(11) NOT NULL AUTO_INCREMENT," .
			"file_name VARCHAR(255) NOT NULL," .
			"title VARCHAR(255) NOT NULL," .
			"alt TEXT," .
			"link VARCHAR(512) NOT NULL," .
			"width INT(11)," .
			"height INT(11)," .
			"target VARCHAR(16)," .
			"wrapper_class VARCHAR(255)," .
			"created_at DATETIME," .
			"updated_at DATETIME, " .
			"PRIMARY KEY (id)
			);";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		
		dbDelta( $sql );
	}

	public function drop_table() {
		$sql = "DROP TABLE ". $this->table_name;
		$this->db->query( $sql );   	
	}

	public function get_banners() {
		$cash_banners = $this->db->get_results( "SELECT * FROM " . $this->table_name . " ORDER BY id;" );

		return $cash_banners;
	}

	public function get_banner( $id ) {
		$banner = $this->db->get_row( "SELECT * FROM " . $this->table_name . " WHERE id='" . $id . "';" );

		return $banner;

/*
		if ( isset( self::$cash_banners[ $id ] ) ) {
			return self::$cash_banners[ $id ];
		}		
*/
	}

	public function add( $data ) {
		$date = date( 'Y-m-d H:i:s' );
		$data['created_at'] = $date;
		$data['updated_at'] = $date;

		$this->db->insert( $this->table_name, $data );

		return $this->db->insert_id;
	}

	public function update( $id, $data ) {
		$data['updated_at'] = date('Y-m-d H:i:s');

		$where = array(
			'id'  => $id
		);

		$this->db->update( $this->table_name, $data, $where );		
	}

	public function delete( $id ) {
		$this->db->query( "DELETE FROM " . $this->table_name . " WHERE id=" . $id . ";" );
	}

	public function get_postmeta_banner_ids() {
		$table_name = $wpdb->prefix . 'postmeta';
		$this->db->get_results( "SELECT * FROM " . $table_name . " where meta_key='" , "';" );
	}

	
}

?>