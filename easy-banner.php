<?php
/**
 * Plugin Name: Easy Banner
 * Version: 1.0.0
 * Description: This plugin enables you to manage banners easily.
 * Author: Hide Nakayama, unimal Co,.Ltd.
 * Author URI: http://unimal.jp
 * Plugin URI: PLUGIN SITE HERE
 * Text Domain: easy-banner
 * Domain Path: /languages
 * @package Easy-banner
 */

require_once dirname( __FILE__ ) . '/includes/easy-banner-db.php';

register_activation_hook( __FILE__,  array( 'easy_banner', 'activate' ) );

global $wpdb;
global $easy_banner_table_name;
$easy_banner_table_name = $wpdb->prefix . 'easy_banner_banners';

class Easy_Banner {
	private static $banner_db = null;
	private static $plugin_prefix = 'easy-banner-';

	public function __construct() {
		global $wpdb;
		global $easy_banner_table_name;

		if ( is_null( self::$banner_db ) ) {
			self::$banner_db = new Easy_Banner_Db( $wpdb, $easy_banner_table_name );
		}

		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'post_data' ) );
		add_action( 'admin_init', array( $this, 'manage_columns' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_post' ) );

		add_action( 'init', array( $this, 'enqueue_scripts_and_styles' ) );

		add_filter( 'the_content', array( $this, 'the_content' ) );
	}

	public static function activate() {
		global $wpdb;
		global $easy_banner_table_name;

		$banner_db = new Easy_Banner_Db( $wpdb, $easy_banner_table_name );
		if ( !$banner_db->has_table() ) {
			$banner_db->create_table();
		}
	}

	public function add_menu() {
		add_menu_page(
			'バナー',
			'バナー',
			'edit_others_posts',
			basename( __FILE__ ),
			array( $this, 'settings' )
		);
	}

	public function admin_notices() {
?>
		<?php if ( $messages = get_transient( self::$plugin_prefix . 'errors' ) ): ?>
		<div class="error">
			<ul>
				<?php foreach ( $messages as $message ): ?>
					<li><?php echo esc_html( $message ); ?></li>
				<?php endforeach; ?>
				<li></li>
			</ul>
		</div>
		<?php endif; ?>
<?php
	}

	public function settings() {
		if ( !current_user_can( 'edit_others_posts' ) ) {
			wp_die( 'アクセスできません。' );
		}

		//settings top
		if ( !isset( $_GET[self::$plugin_prefix . 'page'] ) ) {
			$this->settings_top_page();
			return;
		}

		//settings sub
		switch ( $_GET[self::$plugin_prefix . 'page'] ) {
			case 'form':
				$this->settings_form_page();
				break;
		}
	}

	public function post_data() {
		if ( !current_user_can( 'edit_others_posts' ) ) {
			return;
		}

		//add banner
		if ( isset( $_POST[self::$plugin_prefix . 'add'] ) ) {
			$this->add_banner();
			return;
		}

		//edit banner
		if ( isset( $_POST[self::$plugin_prefix . 'edit'] ) ) {
			$this->edit_banner( $_POST[self::$plugin_prefix . 'id'] );
			return;
		}

		//delete banner
		if ( isset( $_POST[self::$plugin_prefix . 'delete'] ) ) {
			$this->delete_banner( $_POST[self::$plugin_prefix . 'id'] );
			return;
		}		
	}

	public function manage_columns() {
		add_filter('manage_posts_columns', array( $this, 'manage_columns_header' ) );
		add_filter('manage_pages_columns', array( $this, 'manage_columns_header' ) );

		add_action( 'manage_posts_custom_column', array( $this, 'manage_custom_column' ), 10, 2 );
		add_action( 'manage_pages_custom_column', array( $this, 'manage_custom_column' ), 10, 2 );
	}

	public function manage_columns_header($defaults) {
		$defaults[self::$plugin_prefix . 'name'] = 'バナー';
		return $defaults;
	}

	public function manage_custom_column( $column_name, $id ) {
		if ($column_name != self::$plugin_prefix . 'name') {
			return;
		}
		$banner_ids = get_post_meta( $id, self::$plugin_prefix . 'ids', true );

		if ($banner_ids == null) {
			return;
		}

		$banner_names = array();
		foreach ($banner_ids as $banner_id) {
			$banner = self::$banner_db->get_banner( $banner_id );
			if ($banner != null) {
				$banner_names[] = esc_html( $banner->title );
			}
		}

		echo join(',<br>', $banner_names);
	}

	public function add_meta_boxes() {
		add_meta_box( 'easy-banner', 'バナー挿入', array( $this, 'meta_box' ), 'post', 'advanced' );
		add_meta_box( 'easy-banner', 'バナー挿入', array( $this, 'meta_box' ), 'page', 'advanced' );
	}

	public function meta_box() {
?>
		<div>
			<div>バナーの追加、削除は記事を保存するまで反映されません。</div>
			<br>
			<table>
				<thead>
					<tr>
						<th class="left">バナー</th>
						<th>位置</th>
					</tr>
				</thead>
				<tbody class="<?php echo( self::$plugin_prefix )?>banners">
<?php
		$banner_ids = get_post_meta( get_the_ID(), self::$plugin_prefix . 'ids', true );

		if (is_array( $banner_ids ) && count($banner_ids) > 0) {
			$positions = get_post_meta( get_the_ID(), self::$plugin_prefix . 'positions', true );

			$banner_entry_template_html = $this->get_banner_entry_template_html();
			
			for ($i=0; $i < count( $banner_ids ); $i++) {
				$banner = self::$banner_db->get_banner( $banner_ids[$i] );

				if ($banner == null) {
					continue;
				}

				$html = $banner_entry_template_html;
				$html = str_replace( '%banner_name%', $banner->title, $html );
				$html = str_replace( '%banner_id%', $banner->id, $html );
				$html = str_replace( '%position_names%', implode( ', ', $this->get_position_names( $positions[$i] ) ) , $html );
				$html = str_replace( '%position_values%', $positions[$i], $html );

				echo $html;
			}
		}
?>
				</tbody>
			</table>
		</div>
		<p>
			<strong>バナーを追加:</strong>
		</p>
		<table>
			<thead>
				<tr>
					<th class="left">バナー</th>
					<th>位置</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td>
						<select class="<?php echo self::$plugin_prefix; ?>banner-select" name="<?php echo self::$plugin_prefix; ?>banner-select">
							<option value="#NONE#">— 選択 —</option>
<?php
		$banners = self::$banner_db->get_banners();
		foreach ($banners as $banner) {
			$option_html = sprintf('<option value="%d">%s</option>', $banner->id, $banner->title);
			echo $option_html;
		}
?>
						</select>
					</td>
					<td class="right">
						<input type="checkbox" class="<?php echo self::$plugin_prefix; ?>pos-check" name="<?php echo self::$plugin_prefix; ?>pos-header" value="header">記事冒頭
						<input type="checkbox" class="<?php echo self::$plugin_prefix; ?>pos-check" name="<?php echo self::$plugin_prefix; ?>pos-footer" value="footer">記事末尾
						<input type="checkbox" class="<?php echo self::$plugin_prefix; ?>pos-check" name="<?php echo self::$plugin_prefix; ?>pos-beforeH2" value="beforeH2">記事内H2前
						<br><br>「記事内H2前」は記事内の最初のH2のみです。
					</td>
				</tr>
				<tr>
					<td>
						<div class="submit">
							<input type="submit" class="button <?php echo self::$plugin_prefix; ?>add-btn" value="バナーを追加">
						</div>
					</td>
					<td></td>
				</tr>
			</tbody>
		</table>
<?php
	}

	private function get_banner_entry_template_html() {
		$html = <<< EOM
				<tr class="%plugin_prefix%entry">
					<td>
						<div class="%plugin_prefix%banner_name">%banner_name%</div>
						<input type="hidden" class="%plugin_prefix%ids" name="%plugin_prefix%ids[]" value="%banner_id%">
						<div class="submit">
							<input type="submit" class="button button-small %plugin_prefix%delete-btn" value="削除">
						</div>
					</td>
					<td class="right">
						<div class="%plugin_prefix%position_names">%position_names%</div>
						<input type="hidden" class="%plugin_prefix%positions" name="%plugin_prefix%positions[]" value="%position_values%">
					</td>
				</tr>
EOM;

		$html = str_replace( '%plugin_prefix%', self::$plugin_prefix, $html);
		return $html;
	}

	private function get_position_names( $position_values ) {
		$position_names = array();

		if (is_string( $position_values ) ) {
			$position_values = explode( ',', $position_values );
		}
		
		$position_names_hash = $this->get_position_names_hash();
		foreach ($position_values as $position_value) {

			$position_names[] = $position_names_hash[$position_value];
		}

		return $position_names;
	}

	private function get_position_names_hash() {
		return array(
			'header'	=> '記事冒頭',
			'footer'	=> '記事末尾',
			'beforeH2'	=> '記事内H2前'
		);
	}

	public function save_post() {	
		if( empty($_POST) ) { //trash, untrash
			return;
		}

		$param_sub_names = array('ids', 'positions');

		foreach ($param_sub_names as $param_sub_name) {
			$param_name = self::$plugin_prefix . $param_sub_name;

			if (isset( $_POST[$param_name] )) {
				update_post_meta( get_the_ID(), $param_name, $_POST[$param_name] );
			} else {
				update_post_meta( get_the_ID(), $param_name, array() );
			}
		}
	}

	public function enqueue_scripts_and_styles() {
		$is_post_new = ($_SERVER['SCRIPT_NAME'] == '/wp-admin/post-new.php');
		$is_post_edit = (isset( $_GET['post'] ) && isset( $_GET['action'] ) && ($_GET['action'] == 'edit'));

		if (!$is_post_new && !$is_post_edit) {
			return;
		}

		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'easy-banner', plugins_url( 'easy-banner.js' , __FILE__ ), array( 'jquery' ) );

		$params = array(
			'plugin_prefix' => self::$plugin_prefix,
			'banner_entry_template_html' => $this->get_banner_entry_template_html(),
			'position_names_hash' => $this->get_position_names_hash(),
		);
		wp_localize_script( 'easy-banner', 'easy_banner', $params );

		//css
		wp_enqueue_style( 'easy-banner', plugins_url( 'easy-banner.css' , __FILE__ ) );
	}

	public function the_content( $content ) {
		$banner_ids = get_post_meta( get_the_ID(), self::$plugin_prefix . 'ids', true );
		$positions = get_post_meta( get_the_ID(), self::$plugin_prefix . 'positions', true );

		if (empty( $banner_ids )) {
			return $content;
		}

		$header_banners_html = "";
		$footer_banners_html = "";
		$beforeH2_banners_html = "";

		for ($i=0; $i < count( $banner_ids ); $i++) {
			$banner = self::$banner_db->get_banner( $banner_ids[$i] );

			if ($banner == null) {
				continue;
			}

			//<div>
			if (empty( $banner->wrapper_class )) {
				$html = '<div>';
			} else {
				$html = '<div class="' . esc_attr( $banner->wrapper_class ) . '">';
			}

			//<a>
			$html .= '<a href="' . esc_url( $banner->link ) . '" rel="nofollow"';
			if (!empty( $banner->target )) {
				$html .= ' target="' . esc_attr( $banner->target ) . '"';
			}
			$html .= '>';

			//<img>
			$html .= '<img style="max-width: 100%;" src="' . esc_url( $this->image_url( $banner->id, $banner->file_name ) ) . '"';
			if (!empty( $banner->alt )) {
				$html .= ' alt="' . esc_attr( $banner->alt ) . '"';
			}
			$html .= '>';

			//</a></div>
			$html .= '</a>';
			$html .= '</div>';

			$position_array = explode(',', $positions[$i]);

			foreach ($position_array as $position) {
				$position = trim($position);

				if ($position == 'header') {
					$header_banners_html .= $html;
				} else if ($position == 'footer') {
					$footer_banners_html .= $html;
				} else if ($position == 'beforeH2') {
					$beforeH2_banners_html .= $html;
				}
			}
		}

		if (!empty( $beforeH2_banners_html )) {
			$content = preg_replace( '/(<h2)/i', str_replace("\$", "\\\$", $beforeH2_banners_html) . '$1', $content, 1 );
		}

		if (!empty( $header_banners_html )) {
			$content = $header_banners_html . $content;
		}
		if (!empty( $footer_banners_html )) {
			$content = $content . $footer_banners_html;
		}

		return $content;
	}

	private function add_banner() {
		$title = $_POST[self::$plugin_prefix . 'title'];
		$link = $_POST[self::$plugin_prefix . 'link'];
		$alt = $_POST[self::$plugin_prefix . 'alt'];
		if (isset( $_POST[self::$plugin_prefix . 'target'] )) {
			$target = $_POST[self::$plugin_prefix . 'target'];
		} else {
			$target = '';
		}
		$wrapper_class = $_POST[self::$plugin_prefix . 'wrapper_class'];

		$file_name = $_FILES[self::$plugin_prefix . 'file']['name'];
		$tmp_file = $_FILES[self::$plugin_prefix . 'file']['tmp_name'];

		list( $width, $height ) = @getimagesize( $tmp_file );

		$data = array(
			'file_name'		=> $file_name,
			'title'			=> $title,
			'link'			=> $link,
			'alt'			=> $alt,
			'target'		=> $target,
			'wrapper_class'	=> $wrapper_class,
			'width'			=> $width,
			'height'		=> $height
		);

		$id = self::$banner_db->add( $data );

		$this->save_banner_file( $id, $file_name, $tmp_file );

		wp_redirect( $this->get_settings_top_url() );
	}

	private function edit_banner( $id ) {
		$banner = self::$banner_db->get_banner( $id );

		if ($banner == null) {
			return;
		}

		$title = $_POST[self::$plugin_prefix . 'title'];
		$link = $_POST[self::$plugin_prefix . 'link'];
		$alt = $_POST[self::$plugin_prefix . 'alt'];
		if (isset( $_POST[self::$plugin_prefix . 'target'] )) {
			$target = $_POST[self::$plugin_prefix . 'target'];
		} else {
			$target = '';
		}
		$wrapper_class = $_POST[self::$plugin_prefix . 'wrapper_class'];

		//current file
		$file_name = $banner->file_name;
		$width = $banner->width;
		$height = $banner->height;

		// file changed
		if (isset( $_FILES[self::$plugin_prefix . 'file'] ) && isset( $_FILES[self::$plugin_prefix . 'file']['name'] ) &&
			!empty( $_FILES[self::$plugin_prefix . 'file']['name'] )) {

			$new_file_name = $_FILES[self::$plugin_prefix . 'file']['name'];
			$tmp_file = $_FILES[self::$plugin_prefix . 'file']['tmp_name'];

			list( $new_width, $new_height ) = @getimagesize( $tmp_file );

			$this->save_banner_file( $id, $new_file_name, $tmp_file );

			// delete old file
			if ($file_name != $new_file_name) {
				$this->delete_banner_file( $id, $file_name );
			}

			//update file_name, width, height
			$file_name = $new_file_name;
			$width = $new_width;
			$height = $new_height;
		} 

		$data = array(
			'file_name'	=> $file_name,
			'title'			=> $title,
			'link'			=> $link,
			'alt'			=> $alt,
			'target'		=> $target,
			'wrapper_class'	=> $wrapper_class,
			'width'			=> $width,
			'height'		=> $height
		);

		self::$banner_db->update( $id, $data );

		wp_redirect( $this->get_settings_top_url() );
	}

	private function delete_banner( $id ) {
		$banner = self::$banner_db->get_banner( $id );

		if ($banner == null) {
			return;
		}

		self::$banner_db->delete( $id );

		$this->remove_banner_directory( $banner->id );

		wp_redirect( $this->get_settings_top_url() );
	}

	private function save_banner_file( $id, $file_name, $tmp_file ) {
		$wp_upload_dir = wp_upload_dir();
		$basedir = $wp_upload_dir['basedir'] . '/easy-banner';
		$save_dir = $basedir . '/' . $id;
		$file_path = $save_dir . '/' . $file_name;

		if (!file_exists( $basedir )) {
			@mkdir( $basedir );
		}
		if (!file_exists( $save_dir) ) {
			@mkdir( $save_dir );
		}

		move_uploaded_file( $tmp_file, $file_path );
	}

	private function delete_banner_file( $id, $file_name ) {
		$wp_upload_dir = wp_upload_dir();
		$basedir = $wp_upload_dir['basedir'] . '/easy-banner';
		$save_dir = $basedir . '/' . $id;
		$file_path = $save_dir . '/' . $file_name;

		if (!file_exists( $file_path) ) {
			return;
		}

		unlink( $save_dir . '/' . $file_name );
	}

	private function remove_banner_directory( $id ) {
		$wp_upload_dir = wp_upload_dir();
		$basedir = $wp_upload_dir['basedir'] . '/easy-banner';
		$save_dir = $basedir . '/' . $id;

		if (!file_exists( $save_dir) ) {
			return;
		}

		$this->remove_directory( $save_dir );
	}

	private function remove_directory( $dir ) {
		if ($handle = opendir( "$dir" )) {
			while (false !== ($item = readdir( $handle ))) {
				if ($item != "." && $item != "..") {
					if (is_dir( "$dir/$item" )) {
						$this->remove_directory( "$dir/$item" );
					} else {
						unlink( "$dir/$item" );
					}
				}
			}
			closedir( $handle );
			rmdir( $dir );
 		}
	}

	private function image_url( $id, $file_name ) {
		$wp_upload_dir = wp_upload_dir();
		return $basedir = $wp_upload_dir['baseurl'] . '/easy-banner/' . $id . '/' . $file_name;
	}

	private function add_error( $message ) {
		$e = new WP_Error();
		$e->add(
			'error',
			$message
		);
		set_transient( self::$plugin_prefix . 'errors', $e->get_error_messages(), 10 );
	}

	private function settings_top_page() {
		$banners = self::$banner_db->get_banners();

		$count_hash = $this->get_banner_used_count_per_id();

		$datetime_format = $this->get_datetime_format();
?>
		<div>
			<h1>バナー</h1>
			<h2>バナー一覧</h2>


			<button type="button" class="button" onclick="location.href='<?php echo $this->get_settings_url('form'); ?>'">追加</button>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<th>ID</th>
					<th>画像</th>
					<th>バナー名</th>
					<th>ファイル名</th>
					<th>URL</th>
					<th>alt</th>
					<th>target</th>
					<th>クラス名</th>
					<th>作成日時</th>
					<th>使用記事数</th>
					<th>操作</th>
				</thead>
				<tbody>
					<?php foreach ( $banners as $banner ) { 

						if (isset( $count_hash[$banner->id] )) {
							$count = $count_hash[$banner->id];
						} else {
							$count = 0;
						}

?>
						<tr>
							<th><?php echo( esc_html( $banner->id ) ); ?></th>
							<td><img width="80" src="<?php echo( esc_url( $this->image_url( $banner->id, $banner->file_name ) ) ); ?>"></td>
							<td><?php echo( esc_html( $banner->title ) ); ?></td>
							<td>
								<?php echo( esc_html( $banner->file_name ) ); ?> <br>
								<?php echo( esc_html( $banner->width ) ); ?> x <?php echo( esc_html( $banner->height ) ); ?>
							</td>
							<td><a href="<?php echo( esc_url( $banner->link ) ); ?>"><?php echo( esc_html( $banner->link ) ); ?></a></td>
							<td><?php echo( esc_html( $banner->alt ) ); ?></td>
							<td><?php echo( esc_html( $banner->target ) ); ?></td>
							<td><?php echo( esc_html( $banner->wrapper_class ) ); ?></td>
							<td><?php echo( esc_html( get_date_from_gmt( $banner->created_at, $datetime_format ) ) ); ?></td>
							<td><?php echo( esc_html( $count ) ); ?></td>
							<td>
								<form method="get" style="float:left; margin-right:5px; margin-bottom:5px;">
									<input type="submit" class="button" value="編集">
									<input type="hidden" name="page" value="<?php echo( esc_attr( basename( __FILE__ ) ) ); ?>">
									<input type="hidden" name="<?php echo( self::$plugin_prefix ); ?>page" value="form">
									<input type="hidden" name="<?php echo( self::$plugin_prefix ); ?>id" value="<?php echo( esc_attr( $banner->id ) ); ?>">
								</form>
								<form method="post">
									<span>
									<input type="submit" name="<?php echo( self::$plugin_prefix ); ?>delete" class="button" value="削除" onclick="return confirm('削除しますか？')">
									</span>
									<input type="hidden" name="<?php echo( self::$plugin_prefix ); ?>id" value="<?php echo( esc_attr( $banner->id ) ); ?>">
								</form>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
<?php		
	}

	private  function settings_form_page() {
		$is_edit = isset( $_GET[self::$plugin_prefix . 'id'] );
		if ($is_edit) {
			$id = $_GET[self::$plugin_prefix . 'id'];
			$banner = self::$banner_db->get_banner( $id );

			$file_name = $banner->file_name;
			$title = $banner->title;
			$link = $banner->link;
			$alt = $banner->alt;
			$target = $banner->target;
			$wrapper_class = $banner->wrapper_class;
			
			$submit_name = self::$plugin_prefix . 'edit';

			$sub_title = 'バナーの編集';
		} else { //new
			$id = '';

			$file_name = '';
			$title = '';
			$link = '';
			$alt = '';
			$target = '';
			$wrapper_class = '';

			$submit_name = self::$plugin_prefix . 'add';

			$sub_title = 'バナーの追加';
		}
?>
		<div>
			<h1>バナー</h1>
			<h2><?php echo( esc_html( $sub_title ) ) ?></h2>

<?php if ($is_edit) { ?>
			<?php echo( esc_html( $file_name ) ) ?><br/>
			<img src="<?php echo( esc_url( $this->image_url( $id, $file_name ) ) ); ?>"><br/>
			<br>
<?php } ?>

			<form method="post" enctype="multipart/form-data">
				<table class="form-table">
					<tr>
<?php if ($is_edit) { ?>
						<th>ファイルの変更</th>
						<td></td>
<?php } else { ?>
						<th>ファイル</th>
						<td>(必須)</td>
<?php } ?>
						<td>
<?php if ($is_edit) { ?>	
							<input type="file" accept="image/jpeg,image/png" name="<?php echo self::$plugin_prefix; ?>file" /> 
							<input type="hidden" name="<?php echo self::$plugin_prefix; ?>id" value="<?php echo( esc_attr( $id ) ) ?>" /> 
<?php } else { ?>
							<input type="file" required="required" accept="image/jpeg,image/png" name="<?php echo self::$plugin_prefix; ?>file" /> 
<?php } ?>

						</td>
<!--						
						<td><?php if (!$is_edit) { echo esc_html( '(必須)' );}?></td>
-->
					</tr>
					<tr>
						<th>バナー名</th>
						<td>(必須)</td>
						<td>
							<input type="text" class="regular-text" required="required" name="<?php echo self::$plugin_prefix; ?>title" value="<?php echo( esc_attr( $title ) ) ?>" />
						</td>
					</tr>
					<tr>
						<th>URL</th>
						<td>(必須)</td>
						<td>
							<input type="text" class="regular-text" required="required" name="<?php echo self::$plugin_prefix; ?>link" value="<?php echo( esc_attr( $link ) ) ?>" />
						</td>
					</tr>
					<tr>
						<th>alt</th>
						<td></td>
						<td>
							<input type="text" class="regular-text" name="<?php echo self::$plugin_prefix; ?>alt" value="<?php echo( esc_attr( $alt ) ) ?>" />
						</td>
					</tr>
					<tr>
						<th>target</th>
						<td></td>
						<td>
<?php if ($target == '_blank') { ?>
							<input type="checkbox" name="<?php echo self::$plugin_prefix; ?>target" value="_blank" checked="checked" />_blank
<?php } else { ?>
							<input type="checkbox" name="<?php echo self::$plugin_prefix; ?>target" value="_blank" />_blank
<?php } ?>
						</td>
					</tr>
					<tr>
						<th>クラス名</th>
						<td></td>
						<td>
							<input type="text" class="regular-text" name="<?php echo self::$plugin_prefix; ?>wrapper_class" value="<?php echo( esc_attr( $wrapper_class ) ) ?>" />
							<br><br><?php echo( esc_html( '<div class="' ) ) ?><strong>xxx</strong><?php echo( esc_html( '"><a><img/></a></div>' ) ) ?>のxxxになります。
						</td>
					</tr>					
				</table>
				<p class="submit">
					<input name="<?php echo( esc_attr( $submit_name ) ) ?>" type="submit" class="button" value="保存" />
				</p>
				<a href="<?php echo( esc_url( $this->get_settings_top_url() ) ); ?>">キャンセル</a>			
			</form>

		</div>
<?php		
	}

	private function get_datetime_format() {
		return get_option('date_format') . ' ' . get_option('time_format');
	}

	private function get_settings_url( $page ) {
		return $this->get_settings_top_url() . '&' . self::$plugin_prefix . 'page=' . $page;
	}

	private function get_settings_top_url() {
		$exploded = explode( "?", $_SERVER["REQUEST_URI"] );

		$url = $exploded[0];

		parse_str( $_SERVER['QUERY_STRING'], $query_array );
		
		if ( isset( $query_array[self::$plugin_prefix . "page"] ) ) {
			unset( $query_array[self::$plugin_prefix . "page"] );
		}
		if ( isset( $query_array[self::$plugin_prefix . "id"] ) ) {
			unset( $query_array[self::$plugin_prefix . "id"] );
		}
		
		$url = $url . "?" . http_build_query( $query_array );
		
		return $url;		
	}

	private function get_banner_used_count_per_id() {
		global $wpdb;

		$existing_ids = array();
		$banners = self::$banner_db->get_banners();
		foreach ($banners as $banner) {
			$existing_ids[] = $banner->id;
		}

		$count_hash = array();

		$table_name = $wpdb->prefix . 'postmeta';
		$postmetas = $wpdb->get_results( "SELECT meta_value FROM " . $table_name . " where meta_key='" . self::$plugin_prefix . "ids';" );

		foreach ($postmetas as $postmeta) {
			$banner_ids = unserialize($postmeta->meta_value);

			foreach ($banner_ids as $banner_id) {
				if (!in_array( $banner_id, $existing_ids )) { //
					continue;
				}

				if (isset( $count_hash[$banner_id] )) {
					$count_hash[$banner_id] = $count_hash[$banner_id] + 1;
				} else {
					$count_hash[$banner_id] = 1;
				}
			}
		}

		return $count_hash;
	}
}

new Easy_Banner();