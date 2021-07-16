<?php

if(!class_exists('BC_CF7_Vault')){
    final class BC_CF7_Vault {

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// private static
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        private static $instance = null;

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// public static
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public static function get_instance($file = ''){
            if(null !== self::$instance){
                return self::$instance;
            }
            if('' === $file){
                wp_die(__('File doesn&#8217;t exist?'));
            }
            if(!is_file($file)){
                wp_die(sprintf(__('File &#8220;%s&#8221; doesn&#8217;t exist?'), $file));
            }
            self::$instance = new self($file);
            return self::$instance;
    	}

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// private
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        private $additional_data = [], $file = '', $post_id = 0;

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function __clone(){}

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function __construct($file = ''){
            $this->file = $file;
            add_action('plugins_loaded', [$this, 'plugins_loaded']);
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function upload_file($tmp_name = '', $post_id = 0){
            $file = bc_move_uploaded_file($tmp_name);
            if(is_wp_error($file)){
                return $file;
            }
            return bc_upload($file, $post_id);
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// public
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function init(){
            register_post_type('bc_cf7_submission', [
                'labels' => bc_post_type_labels('Submission', 'Submissions', false),
                'menu_icon' => 'dashicons-vault',
                'show_in_admin_bar' => false,
                'show_ui' => true,
                'supports' => ['custom-fields', 'title'],
            ]);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function plugins_loaded(){
            if(!defined('BC_FUNCTIONS')){
        		return;
        	}
            if(!defined('WPCF7_VERSION')){
        		return;
        	}
            add_action('init', [$this, 'init']);
            add_action('wpcf7_before_send_mail', [$this, 'wpcf7_before_send_mail'], 10, 3);
            add_action('wpcf7_mail_failed', [$this, 'wpcf7_mail_failed']);
            add_action('wpcf7_mail_sent', [$this, 'wpcf7_mail_sent']);
            add_filter('wpcf7_posted_data', [$this, 'wpcf7_posted_data']);
            add_filter('wpcf7_posted_data_checkbox', [$this, 'wpcf7_posted_data_type'], 10, 3);
            add_filter('wpcf7_posted_data_checkbox*', [$this, 'wpcf7_posted_data_type'], 10, 3);
            add_filter('wpcf7_posted_data_radio', [$this, 'wpcf7_posted_data_type'], 10, 3);
            add_filter('wpcf7_posted_data_radio*', [$this, 'wpcf7_posted_data_type'], 10, 3);
            add_filter('wpcf7_posted_data_select', [$this, 'wpcf7_posted_data_type'], 10, 3);
            add_filter('wpcf7_posted_data_select*', [$this, 'wpcf7_posted_data_type'], 10, 3);
            if(!has_filter('wpcf7_verify_nonce', 'is_user_logged_in')){
                add_filter('wpcf7_verify_nonce', 'is_user_logged_in');
            }
            bc_build_update_checker('https://github.com/beavercoffee/bc-cf7-vault', $this->file, 'bc-cf7-vault');
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_before_send_mail($contact_form, &$abort, $submission){
            if($contact_form->is_true('do_not_store')){
                return;
            }
            if('' !== bc_cf7_type($contact_form)){
                return;
            }
            if(!$submission->is('init')){
                return; // prevent conflicts with other plugins
            }
            $post_id = wp_insert_post([
				'post_status' => 'private',
				'post_title' => '[bc-cf7-submission]',
				'post_type' => 'bc_cf7_submission',
			], true);
            if(is_wp_error($post_id)){
                $abort = true; // prevent mail_sent and mail_failed actions
                $submission->set_response($post_id->get_error_message());
                $submission->set_status('aborted'); // try to prevent conflicts with other plugins
                return;
            }
            $this->post_id = $post_id;
            bc_cf7_update_meta_data(bc_cf7_meta_data($contact_form, $submission), $post_id);
            bc_cf7_update_posted_data($submission->get_posted_data(), $post_id);
            bc_cf7_update_uploaded_files($submission->uploaded_files(), $post_id);
            do_action('bc_cf7_vault', $post_id, $contact_form, $submission);
            // continue to mail_sent or mail_failed action
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_mail_failed($contact_form){
            if(0 !== $this->post_id){
                $submission = WPCF7_Submission::get_instance();
                if(null === $submission){
                    update_post_meta($this->post_id, 'bc_submission_response', $contact_form->message('mail_sent_ng'));
                    update_post_meta($this->post_id, 'bc_submission_status', 'mail_failed');
                } else {
                    update_post_meta($this->post_id, 'bc_submission_response', $submission->get_response());
                    update_post_meta($this->post_id, 'bc_submission_status', $submission->get_status());
                }
            }
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_mail_sent($contact_form){
            if(0 !== $this->post_id){
                $submission = WPCF7_Submission::get_instance();
                if(null === $submission){
                    update_post_meta($this->post_id, 'bc_submission_response', $contact_form->message('mail_sent_ok'));
                    update_post_meta($this->post_id, 'bc_submission_status', 'mail_sent');
                } else {
                    update_post_meta($this->post_id, 'bc_submission_response', $submission->get_response());
                    update_post_meta($this->post_id, 'bc_submission_status', $submission->get_status());
                }
            }
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_posted_data($posted_data){
            if($this->additional_data){
                $posted_data = array_merge($posted_data, $this->additional_data);
            }
            return $posted_data;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_posted_data_type($value, $value_orig, $tag){
			$name = $tag->name;
            $pipes = $tag->pipes;
            $type = $tag->type;
			if(wpcf7_form_tag_supports($type, 'selectable-values')){
                $value = (array) $value;
                $value_orig = (array) $value_orig;
				if($tag->has_option('free_text')){
        			$last_val = array_pop($value);
					list($tied_item) = array_slice(WPCF7_USE_PIPE ? $tag->pipes->collect_afters() : $tag->values, -1, 1);
					$tied_item = html_entity_decode($tied_item, ENT_QUOTES, 'UTF-8');
					if(strpos($last_val, $tied_item) === 0){
						$value[] = $tied_item;
						$this->additional_data[$name . '_free_text'] = trim(str_replace($tied_item, '', $last_val));
					} else {
						$value[] = $last_val;
						$this->additional_data[$name . '_free_text'] = '';
					}
                }
            }
			if(WPCF7_USE_PIPE and $pipes instanceof WPCF7_Pipes and !$pipes->zero()){
				$this->additional_data[$name . '_value'] = $value;
				$value = $value_orig;
            }
            return $value;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    }
}
