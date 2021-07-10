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

        private $file = '';

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function __clone(){}

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function __construct($file = ''){
            $this->file = $file;
            add_action('plugins_loaded', [$this, 'plugins_loaded']);
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function get_type($contact_form = null){
            if(null === $contact_form){
                $contact_form = wpcf7_get_current_contact_form();
            }
            if(null === $contact_form){
                return '';
            }
            $type = $contact_form->pref('bc_type');
            if(null === $type){
                return '';
            }
            return $type;
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
                'labels' => $this->post_type_labels('CF7 Submission', 'CF7 Submissions', false),
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
            add_action('wpcf7_mail_sent', [$this, 'wpcf7_mail_sent']);
            if(!has_filter('wpcf7_verify_nonce', 'is_user_logged_in')){
                add_filter('wpcf7_verify_nonce', 'is_user_logged_in');
            }
            bc_build_update_checker('https://github.com/beavercoffee/bc-cf7-vault', $this->file, 'bc-cf7-vault');
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_mail_sent($contact_form){
            if($contact_form->is_true('do_not_store')){
                return;
            }
            if('' !== $this->get_type($contact_form)){
                return;
            }
            $submission = WPCF7_Submission::get_instance();
            if(null === $submission){
                return;
            }
            if(!$submission->is('mail_sent')){
                return;
            }
            $post_id = wp_insert_post([
				'post_status' => 'private',
				'post_title' => sprintf('[contact-form-7 id="%1$d" title="%2$s"]', $contact_form->id(), $contact_form->title()),
				'post_type' => 'bc_cf7_submission',
			], true);
            if(is_wp_error($post_id)){
                $message = $post_id->get_error_message();
                $message .=  ' ' . bc_last_p(__('Application passwords are not available for your account. Please contact the site administrator for assistance.'));
                $submission->set_response($message);
                $submission->set_status('aborted');
                return;
            }
            $posted_data = $submission->get_posted_data();
            if($posted_data){
                foreach($posted_data as $key => $value){
                    if(is_array($value)){
                        delete_post_meta($post_id, $key);
                        foreach($value as $single){
                            add_post_meta($post_id, $key, $single);
                        }
                    } else {
                        update_post_meta($post_id, $key, $value);
                    }
                }
            }
            $error = new WP_Error;
            $uploaded_files = $submission->uploaded_files();
            if($uploaded_files){
                foreach($uploaded_files as $key => $value){
                    delete_post_meta($post_id, $key . '_id');
                    delete_post_meta($post_id, $key . '_filename');
                    foreach((array) $value as $single){
                        $attachment_id = $this->upload_file($single, $post_id);
                        if(is_wp_error($attachment_id)){
                            $error->merge_from($attachment_id);
                        } else {
                            add_post_meta($post_id, $key . '_id', $attachment_id);
                            add_post_meta($post_id, $key . '_filename', wp_basename($single));
                        }
                    }
                }
            }
            do_action('bc_cf7_vault', $post_id, $contact_form, $error);
            if($error->has_errors()){
                $message = $error->get_error_message();
                $message .=  ' ' . bc_last_p(__('Application passwords are not available for your account. Please contact the site administrator for assistance.'));
                $submission->set_response($message);
                $submission->set_status('aborted');
                return;
            }
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    }
}
