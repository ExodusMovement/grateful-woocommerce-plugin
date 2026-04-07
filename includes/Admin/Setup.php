<?php

namespace GratefulPayments\Admin;

class Setup {
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts' ) );
		add_action( 'admin_menu', array( $this, 'register_page' ) );
	}

	public function register_scripts() {}

	public function register_page() {}
}
