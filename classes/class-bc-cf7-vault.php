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

        private $file = '', $post_id = 0;

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function __clone(){}

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function __construct($file = ''){
            $this->file = $file;
            add_action('bc_cf7_loaded', [$this, 'bc_cf7_loaded']);
            add_action('bc_functions_loaded', [$this, 'bc_functions_loaded']);
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function is_type($contact_form = null){
            return bc_cf7()->is_type('', $contact_form);
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// public
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function bc_cf7_loaded(){
            add_action('init', [$this, 'init']);
            add_action('wpcf7_before_send_mail', [$this, 'wpcf7_before_send_mail'], 10, 3);
            add_action('wpcf7_mail_failed', [$this, 'wpcf7_mail_failed']);
            add_action('wpcf7_mail_sent', [$this, 'wpcf7_mail_sent']);
            if(!has_filter('wpcf7_verify_nonce', 'is_user_logged_in')){
                add_filter('wpcf7_verify_nonce', 'is_user_logged_in');
            }
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function bc_functions_loaded(){
            bc_build_update_checker('https://github.com/beavercoffee/bc-cf7-vault', $this->file, 'bc-cf7-vault');
            if(!bc_is_plugin_active('bc-cf7/bc-cf7.php')){
                add_action('admin_notices', function(){
                    echo bc_admin_notice(sprintf(__('No plugins found for: %s.'),'<strong>BC CF7</strong>'));
                });
        	}
        }

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

        public function wpcf7_before_send_mail($contact_form, &$abort, $submission){
            if($contact_form->is_true('do_not_store')){
                return;
            }
            if(!$this->is_type($contact_form)){
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
            bc_cf7()->update($contact_form, $submission, 'post', $post_id);
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

    }
}
