<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'mavo_toc_options' );
delete_option( 'mavo_toc_assets_fingerprint' );
