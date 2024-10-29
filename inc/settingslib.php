<?php
if (!class_exists('SettingsLib')) {
class SettingsLib {
	var $settings;
	var $name;
	var $group;
	var $main;
	var $permission;
	var $icon;
	var $position;
	var $html;
	var $join;

	function __construct($settings, $name = 'Settings', $group = '', $main = false, $permission = 'manage_options', $icon = null, $position = null, $html = '', $join = '-') {
		$this->settings = $settings ? $settings : array();
		$this->name = $name;
		$this->group = $group;
		$this->main = $main;
		$this->permission = $permission;
		$this->icon = $icon;
		$this->position = $position;
		$this->html = $html;
		$this->join = $join;
		add_action('admin_init', array($this, 'admin_init'));
		add_action('admin_menu', array($this, 'admin_menu'));
	}
	public function admin_init() {
		foreach ($this->settings as $setting) {
			$setting = (object) $setting;
			$type = $setting->type;
			$type = $type == 'select' ? 'string' : $type;
			$type = $type == 'text' ? 'string' : $type;
			$type = $type == 'url' ? 'string' : $type;
			$type = $type == 'password' ? 'string' : $type;
			register_setting($this->group, $setting->id,
				array(
					'type' => $type,
					'description' => $setting->title,
					'default' => property_exists($setting, 'default') ? $setting->default : null
				)
			);
		}
	}
	public function admin_menu() {
		if ($this->main) {
			add_menu_page(
				$this->name,
				$this->name,
				$this->permission,
				self::create_key($this->group, $this->join),
				array($this, 'menu_page'),
				$this->icon,
				$this->position
			);
		} else {
			add_submenu_page(
				self::create_key($this->group, $this->join),
				$this->name,
				$this->name,
				$this->permission,
				self::create_key($this->group.' '.$this->name, $this->join),
				array($this, 'menu_page')
			);
		}
	}
	public static function create_key($key, $join = '-') {
		return urlencode(str_replace(' ',$join,strtolower($key)));
	}
	public static function notice($message, $type = 'success') {
		echo '<div class="notice notice-'.$type.' is-dismissible"> 
			<p><strong>'.$message.'</strong></p>
		</div>';
	}
	public static function check_nounce($key) {
		if (isset($_POST[$key])) {
			if (wp_verify_nonce(sanitize_key(wp_unslash($_POST[$key])), plugin_basename( __FILE__ ))) {
				return true;
			} else {
				self::notice('Security check failed, please login and try again.');
			}
		}
		return false;
	}
	public static function echo_nounce($key) {
		wp_nonce_field(plugin_basename( __FILE__ ), $key);
	}
	public static function settings($setting) {
		//TODO: add select
		$setting = (object) $setting;
		if ($setting->type == 'boolean') {
			echo self::settings_checkbox($setting);
		} elseif ($setting->type == 'select') {
			echo self::settings_select($setting);
		} elseif ($setting->type == 'text') {
			echo self::settings_text($setting);
		} elseif ($setting->type == 'title') {
			echo self::settings_row($setting);
		} else {
			echo self::settings_input($setting);
		}
	}
	public static function settings_row($setting, $html = '') {
		return '
		<tr valign="top" class="'.$setting->id.'">
			<th scope="row">'.esc_html($setting->title).($setting->type=='text'&&$setting->description?'<div style="font-weight:normal;">'.esc_html($setting->description).'</div>':'').'</th>
			<td>'.$html.'</td>
		</tr>';
	}
	public static function settings_text($setting) {
		$html = '<textarea id="'.esc_attr($setting->id).'" name="'.esc_attr($setting->id).'" rows="10" cols="70" />'.esc_html(get_option($setting->id)).'</textarea>';
		return self::settings_row($setting, $html);
	}
	public static function settings_select($setting) {
		$assoc = self::associative($setting->values);
		$current = get_option($setting->id);
		$html = '<select id="'.esc_attr($setting->id).'" name="'.esc_attr($setting->id).'">';
		foreach ($setting->values as $key => $name) {
			$value = $assoc ? $key : $name;
			$html .= '<option value="'.esc_attr($value).'"'.($current==$value?' selected="selected"':'').'>'.esc_html($name).'</option>';
		}
		$html .= '</select>';
		return self::settings_row($setting, $html);
	}
	public static function settings_checkbox($setting) {
		$html = '<input type="checkbox" id="'.esc_attr($setting->id).'" name="'.esc_attr($setting->id).'"'.(get_option($setting->id) == '1' ? ' checked="checked"' : '').' />';
		$html .= '<label for="name="'.esc_attr($setting->id).'"">'.(property_exists($setting,'description')?esc_html($setting->description):'Enable').'</label>';
		return self::settings_row($setting, $html);
	}
	public static function settings_input($setting) {
		$html = '<input style="width:520px;" placeholder="'.esc_attr($setting->description).'" type="'.($setting->type=='password'?'password':'text').'" name="'.esc_attr($setting->id).'" value="'.esc_attr(get_option($setting->id)).'" />';
		//$html .= '<div>'.$setting->description.'</div>';
		return self::settings_row($setting, $html);
	}
	public static function checkbox_request($key) {
		return isset($_REQUEST[$key]) && $_REQUEST[$key] != '' ? 1 : 0;
	}
	public static function associative($array) {
		return array_keys($array) !== range(0, count($array) - 1);
	}
	public function menu_page() {
		echo '<h1>'.$this->name.'</h1>';
		echo $this->html;
		$nounce = $this->group;
		if (self::check_nounce($nounce)) {
			foreach ($this->settings as $setting) {
				$setting = (object) $setting;
				$key = $setting->id;
				$value = isset($_REQUEST[$key]) ? stripslashes($_REQUEST[$key]) : '';
				if ($setting->type == 'boolean') {
					update_option($key, self::checkbox_request($key));
				} else if ($setting->type == 'text') {
					update_option($key, sanitize_textarea_field($value));
				} else {
					update_option($key, sanitize_text_field($value));
				}
			}
			self::notice('Saved.');
		}
		?>
		<form method="post">
		<?php self::echo_nounce($nounce); ?>
		<table class="form-table">
			<?php
			foreach ($this->settings as $setting) {
				self::settings((object) $setting);
			}
			?>
		</table>
		<?php submit_button(); ?>
		</form>
		<?php
	}
}
}