<?php
/*
Plugin Name: AI Translate For Polylang
Plugin URI: https://wordpress.org/plugins/ai-translate-polylang/
Description: Add auto AI translation caperbility to Polylang
Version: 1.0.9
Author: James Low
Author URI: http://jameslow.com
License: MIT License
*/

/*
Next Version:
- Auto translate on publish post (publish/save as draft)
- Setting to only auto translate for certain categories/pages
*/

namespace AI_Translate_Polylang;

class AI_Translate_Polylang {
	//Constants
	public static $PROMPT = 'Translate the content from {FROM_CODE} to {TO_CODE} preserving html, formatting and embedded media. Only return the new content.';
	public static $OPENAI_MODEL = 'gpt-4o';
	public static $CLAUDE_MODEL = 'claude-3-5-sonnet-20240620';

	//Variables
	public static $meta_translate;
	public static $meta_clear;

	/* Helper functions */
	public static function require_settings() {
		if (class_exists('\PageApp')) {
			\PageApp::require_settings();
		} else {
			require_once 'inc/settingslib.php';
		}
	}
	public static function require_utils() {
		if (class_exists('\PageApp')) {
			\PageApp::require_utils();
		} else {
			require_once 'inc/utilslib.php';
		}
	}
	public static function require_openai() {
		require_once 'inc/open-ai/Url.php';
		require_once 'inc/open-ai/OpenAi.php';
	}

	/* Hooks */
	public static function add_hooks() {
		add_action('init', array(static::class, 'init'), 11);
		add_filter('default_title', array(static::class, 'default_title'), 10, 2);
		add_filter('default_content', array(static::class, 'default_content'), 10, 2);
		//add_filter('pll_copy_post_metas', array(static::class, 'pll_copy_post_metas'), 11, 5); //This gets called, but doesn't clear out keys
		add_filter('pll_translate_post_meta', array(static::class, 'pll_translate_post_meta'), 10, 3);
	}
	public static function init() {
		self::require_settings();
		$settings = new \SettingsLib(array(
			array('id'=>'ai_translate_new_post', 'type'=>'boolean', 'title'=>'Auto Translate New Translation Posts', 'default'=>'1'),
			array('id'=>'ai_translate_prompt', 'type'=>'string', 'title'=>'Custom Prompt', 'description'=>'{FROM_CODE} and {TO_CODE} will be replaced by from and to languages.', 'default'=>self::$PROMPT),
			array('id'=>'ai_translate_llm', 'type'=>'select', 'title'=>'LLM Service', 'default'=>'OpenAI', 'values'=>array(
				'OpenAI',
				'Claude'
			)),
			array('id'=>'ai_translate_openai', 'type'=>'title', 'title'=>'OpenAI', 'description'=>''),
			array('id'=>'ai_translate_openai_key', 'type'=>'string', 'title'=>'OpenAI API Key', 'description'=>''),
			array('id'=>'ai_translate_openai_org', 'type'=>'string', 'title'=>'OpenAI Organization', 'description'=>'(Optional)'),
			array('id'=>'ai_translate_openai_model', 'type'=>'select', 'title'=>'OpenAI Model', 'default'=>self::$OPENAI_MODEL, 'values'=>array(
				'gpt-4o',
				'gpt-4o-mini',
				'gpt-4-turbo',
				'gpt-4',
				'gpt-3.5-turbo'
			)),
			array('id'=>'ai_translate_claude', 'type'=>'title', 'title'=>'Claude', 'description'=>''),
			array('id'=>'ai_translate_claude_key', 'type'=>'string', 'title'=>'Claude API Key', 'description'=>''),
			array('id'=>'ai_translate_claude_model', 'type'=>'select', 'title'=>'OpenAI Model', 'default'=>self::$CLAUDE_MODEL, 'values'=>array(
				'claude-3-5-sonnet-20240620',
				'claude-3-opus-20240229',
				'claude-3-sonnet-20240229',
				'claude-3-haiku-20240307'
			)),
			array('id'=>'ai_translate_meta', 'type'=>'title', 'title'=>'Meta', 'description'=>''),
			array('id'=>'ai_translate_meta_clear', 'type'=>'text', 'title'=>'Meta keys to clear', 'description'=>'', 'default'=>''),
			array('id'=>'ai_translate_meta_translate', 'type'=>'text', 'title'=>'Meta keys to translate', 'description'=>'', 'default'=>''),
		), 'AI Translate', 'mlang', false, 'manage_options', null, null, '', '_');
	}
	public static function default_title($title, $post) {
		$pattern = '/[^\p{L}\p{N}]+$/u'; //Remove trailing not alpha numeric characters
		return preg_replace($pattern, '', wp_strip_all_tags(self::translate_field($title, 'post_title')));
	}
	public static function default_content($content, $post) {
		return self::translate_field($content, 'post_content');
	}
	public static function pll_copy_post_metas($keys, $sync, $from, $to, $lang) {
		$keys =  array_diff($keys, self::meta_clear());
		return $keys;
	}
	public static function pll_translate_post_meta($value, $key, $lang) {
		if (in_array($key, self::meta_clear())) {
			$value = '';
		} else if (in_array($key, self::meta_translate())) {
			$value = self::translate_field($value, $key, true);
		}
		return $value;
	}
	private static function meta_keys($option) {
		$clear = get_option($option);
		if ($clear) {
			return preg_split('/\s+/', $clear);
		} else {
			return array();
		}
	}
	private static function meta_clear() {
		if (!self::$meta_clear) {
			self::$meta_clear = self::meta_keys('ai_translate_meta_clear');
		}
		return self::$meta_clear;
	}
	private static function meta_translate(){
		if (!self::$meta_translate) {
			self::$meta_translate = self::meta_keys('ai_translate_meta_translate');
		}
		return self::$meta_translate;
	}

	/* Translation */
	public static function translate_field($original, $field = '', $meta = false) {
		$translation = null;
		if (get_option('ai_translate_new_post', '0') == '1' && isset($_GET['new_lang']) && $_GET['new_lang'] && isset($_GET['from_post'])) {
			if (!$original || $original != '') {
				$to = sanitize_key($_GET['new_lang']);
				$post_id = sanitize_key($_GET['from_post']);
				if ($field) {
					if ($meta) {
						$original = get_post_meta($post_id, $field, true);
					} else {
						$post = get_post($post_id);
						$original = $post->$field;
					}
				}
			}
			$translation = self::translate($original, $to, pll_get_post_language($post_id));
		} else {
			$translation = $original;
		}
		return $translation;
	}
	public static function prompt($to, $from = 'en') {
		return str_replace('{TO_CODE}', $to, str_replace('{FROM_CODE}', $from, get_option('ai_translate_prompt', self::$PROMPT)));
	}
	public static function translate($text, $to, $from = 'en') {
		if ($text && trim($text) != '') {
			$prompt = self::prompt($to, $from);
			if (get_option('ai_translate_llm', 'OpenAI') == 'Claude') {
				$result = self::claude_message($text, $prompt);
				$body = json_decode($result['body'], true);
				if ($result['response']['code'] == 200) {
					return $body['content'][0]['text'];
				} else {
					return 'ERROR: '.$body['error']['message'];
				}
			} else {
				$result = self::openai_api($text, $prompt);
				if (!isset($result['error'])) {
					$translation = $result['choices'][0]['message']['content'];
				} else {
					$translation = 'ERROR: '.$result['error']['message'];
				}
				return $translation;
			}
		} else {
			return '';
		}
	}
	public static $author = 0;
	public static function wp_insert_post_data($data , $postarr) {
		$data['post_author'] = self::$author;
		self::$author = 0;
		remove_filter('wp_insert_post_data', array(static::class, 'wp_insert_post_data'), 99);
		return $data;
	}
	public static function translate_post($post_id, $to, $status = 'publish') {
		$from = pll_get_post_language($post_id);
		$translation = pll_get_post($post_id, $to);
		if (!$translation || get_post_status($translation) !== false) {
			$post = get_post($post_id);
			
			self::$author = $post->post_author;
			add_filter('wp_insert_post_data', array(static::class, 'wp_insert_post_data'), '99', 2);
			// Create a new post with the same content
			$new_post_id = wp_insert_post([
				'post_title'        => self::translate($post->post_title, $to, $from),
				'post_content'      => self::translate($post->post_content, $to, $from),
				'post_excerpt'      => self::translate($post->post_excerpt, $to, $from),
				'post_status'       => $status,
				'post_type'         => $post->post_type,
				'post_author'       => $post->post_author, //Wordpress overrides author to 0, hence hook
				'post_date'         => $post->post_date,
				'post_date_gmt'     => $post->post_date_gmt,
				'post_modified'     => $post->post_modified,
				'post_modified_gmt' => $post->post_modified_gmt
			]);

			// Set the language for the new post
			pll_set_post_language($new_post_id, $to);

			// Duplicate post meta
			$meta = get_post_meta($post_id);
			foreach ($meta as $key => $values) {
				foreach ($values as $value) {
					$value = maybe_unserialize($value);
					if (in_array($key, self::meta_clear())) {
						$value = '';
					} else if (in_array($key, self::meta_translate())) {
						$value = self::translate($value, $to, $from);
					}
					add_post_meta($new_post_id, $key, $value);
				}
			}

			//TODO: Should we use PLL_Sync_Tax->copy($from,$to, $lang)
			// Get the translated term IDs for categories
			$categories = get_the_category($post_id);
			$translated_category_ids = [];
			foreach ($categories as $category) {
				$translated_category_id = pll_get_term($category->term_id, $to);
				if ($translated_category_id) {
					$translated_category_ids[] = $translated_category_id;
				}
			}
			wp_set_post_categories($new_post_id, $translated_category_ids);

			// Get the translated term IDs for tags
			$tags = wp_get_post_tags($post_id);
			$translated_tag_ids = [];
			foreach ($tags as $tag) {
				$translated_tag_id = pll_get_term($tag->term_id, $to);
				if ($translated_tag_id) {
					$translated_tag_ids[] = $translated_tag_id;
				}
			}
			wp_set_post_tags($new_post_id, $translated_tag_ids);

			// Link the new translation with the original post
			pll_save_post_translations([
				$to => $new_post_id,
				pll_get_post_language($post_id) => $post_id,
			]);
			return $new_post_id;
		} else {
			return null;
		}
	}

	/* APIs */
	public static function claude_message($content, $role = 'You are a helpful assistant.', $tokens = 1000, $temp = 0) {
		$request = new \WP_Http();
		$headers = array(
			'x-api-key' => get_option('ai_translate_claude_key', ''),
			'anthropic-version' => '2023-06-01',
			'Content-Type' => 'application/json',
		);
		$message = array(
			'model' => get_option('ai_translate_claude_model', self::$CLAUDE_MODEL),
			'max_tokens' => 1000,
			'temperature' => $temp,
			'system' => $role,
			'messages' => array(
				array("role" => 'user', "content" => $content)
			)
		);
		$args = array(
			'method'    => 'POST',
			'headers'   => $headers,
			'body'      => json_encode($message),
			'timeout'   => 60,
		);
		return $request->request('https://api.anthropic.com/v1/messages', $args);
		//https://www.datacamp.com/tutorial/getting-started-with-claude-3-and-the-claude-3-api
		//return $request->request('https://api.anthropic.com/v1/complete', $args);
	}
	public static function openai() {
		self::require_openai();
		//Create new open ai every time, otherwise it preserves conversation between calls and gets confused translating title/content
		$openai = new \Orhanerday\OpenAi\OpenAi(get_option('ai_translate_openai_key', ''));
		if ($org = get_option('ai_translate_openai_org')) {
			$openai->setORG($org);
		}
		return $openai;
	}
	public static function openai_api($content, $role = 'You are a helpful assistant.', $tokens = 1000) {
		//https://packagist.org/packages/orhanerday/open-ai
		return json_decode(self::openai()->chat([
			'model' => get_option('ai_translate_openai_model', self::$OPENAI_MODEL),
			'messages' => [
				[
					"role" => "system",
					"content" => "$role"
				],
				[
					"role" => "user",
					"content" => "$content"
				]
			],
			'temperature' => 1.0,
			'max_tokens' => $tokens,
			'frequency_penalty' => 0,
			'presence_penalty' => 0,
		 ]), true);
	}
}

AI_Translate_Polylang::add_hooks();