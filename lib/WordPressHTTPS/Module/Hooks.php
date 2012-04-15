<?php
/**
 * Hooks Module
 *
 * @author Mike Ems
 * @package WordPressHTTPS
 *
 */

class WordPressHTTPS_Module_Hooks extends WordPressHTTPS_Module implements WordPressHTTPS_Module_Interface {

	/**
	 * Initialize
	 *
	 * @param none
	 * @return void
	 */
	public function init() {
		if ( $this->getPlugin()->getSetting('ssl_host_diff') ) {
			// Remove SSL Host authentication cookies on logout
			add_action('clear_auth_cookie', array(&$this, 'clear_cookies'));

			// Set authentication cookie
			if ( $this->getPlugin()->isSsl() ) {
				add_action('set_auth_cookie', array(&$this, 'set_cookie'), 10, 5);
				add_action('set_logged_in_cookie', array(&$this, 'set_cookie'), 10, 5);
			}

			// Filter redirects in admin panel
			if ( is_admin() && $this->getPlugin()->isSsl() ) {
				add_action('wp_redirect', array(&$this, 'wp_redirect_admin'), 10, 1);
			}
		}

		/*
		 * Run proxy check
		 */
		if ( ! $this->getPlugin()->isSsl() && ! isset($_COOKIE['wp_proxy']) ) {
			add_action('init', array(&$this, 'proxy_check'), 1);
			add_action('admin_init', array(&$this, 'proxy_check'), 1);
			add_action('login_head', array(&$this, 'proxy_check'), 1);
		}

		// Check if the page needs to be redirected
		add_action('template_redirect', array(&$this, 'redirect_check'));
	}

	/**
	 * Proxy Check
	 * 
	 * If the server is on a proxy and not correctly reporting HTTPS, this
	 * JavaScript makes sure that the correct redirect takes place.
	 *
	 * @param none
	 * @return void
	 */
	public function proxy_check() {
		$cookie_expiration = new DateTime;
		$cookie_expiration->setTimezone(new DateTimeZone('GMT'));
		$cookie_expiration = $cookie_expiration->modify('+10 years')->format('D, d-M-Y H:i:s') . ' GMT';

		echo '<!-- WordPress HTTPS Proxy Check -->';
		echo '<script type="text/javascript">function getCookie(a){var b=document.cookie;var c=a+"=";var d=b.indexOf("; "+c);if(d==-1){d=b.indexOf(c);if(d!=0)return null}else{d+=2;var e=document.cookie.indexOf(";",d);if(e==-1){e=b.length}}return unescape(b.substring(d+c.length,e))}if(getCookie("wp_proxy")!=true){if(window.location.protocol=="https:"){document.cookie="wp_proxy=1; path=/; expires=' . $cookie_expiration . '"}else if(getCookie("wp_proxy")==null){document.cookie="wp_proxy=0; path=/; expires=' . $cookie_expiration . '"}if(getCookie("wp_proxy")!=null){window.location.reload()}}</script>';
		echo '<noscript>Your browser does not support JavaScript.</noscript>';
		exit();
	}

	/**
	 * Redirect Check
	 * 
	 * Checks if the current page needs to be redirected
	 *
	 * @param none
	 * @return void
	 */
	public function redirect_check() {
		global $post;
		
		if ( ( is_single() || is_page() || is_front_page() || is_home() ) && $post->ID > 0 ) {
			$force_ssl = apply_filters('force_ssl', $force_ssl, $post->ID );
			
			// Secure Front Page
			if ( is_front_page() ) {
				if ( $this->getPlugin()->getSetting('frontpage') && ! $this->getPlugin()->isSsl() ) {
					$force_ssl = true;
				} else if ( ! $this->getPlugin()->getSetting('frontpage') && ! isset($force_ssl) && $this->getPlugin()->getSetting('exclusive_https') && $this->getPlugin()->isSsl() && ( ! $this->getPlugin()->getSetting('ssl_host_diff') || ( $this->getPlugin()->getSetting('ssl_host_diff') && $this->getPlugin()->getSetting('ssl_admin') && ! is_user_logged_in() ) ) ) {
					$force_ssl = false;
				}
			}

			// Force SSL Admin
			if ( is_admin() && $this->getPlugin()->getSetting('ssl_admin') && ! $this->getPlugin()->isSsl() ) {
				$force_ssl = true;
			}

			// Exclusive HTTPS
			if ( $this->getPlugin()->getSetting('exclusive_https') && $this->getPlugin()->isSsl() && ! isset($force_ssl) ) {
				$force_ssl = false;
			}

			if ( ! $this->getPlugin()->isSsl() && isset($force_ssl) && $force_ssl ) {
				$scheme = 'https';
			} else if ( $this->getPlugin()->isSsl() && isset($force_ssl) && ! $force_ssl ) {
				$scheme = 'http';
			}
		}

		if ( isset($scheme) ) {
			$this->getPlugin()->redirect($scheme);
		}
	}
	
	/**
	 * WP Redirect Admin
	 * WordPress Filter - wp_redirect_admin
	 *
	 * @param string $url
	 * @return string $url
	 */
	public function wp_redirect_admin( $url ) {
		$url = $this->getPlugin()->makeUrlHttps($url);

		// Fix redirect_to
		preg_match('/redirect_to=([^&]+)/i', $url, $redirect);
		$redirect_url = @$redirect[1];
		$url = str_replace($redirect_url, urlencode($this->getPlugin()->makeUrlHttps(urldecode($redirect_url))), $url);
		return $url;
	}

	/**
	 * Set Cookie
	 * WordPress Hook - set_auth_cookie, set_logged_in_cookie
	 *
	 * @param string $cookie
	 * @param string $expire
	 * @param int $expiration
	 * @param int $user_id
	 * @param string $scheme
	 * @return void
	 */
	public function set_cookie($cookie, $expire, $expiration, $user_id, $scheme) {
		if( $scheme == 'logged_in' ) {
			$cookie_name = LOGGED_IN_COOKIE;
		} elseif ( $secure ) {
			$cookie_name = SECURE_AUTH_COOKIE;
			$scheme = 'secure_auth';
		} else {
			$cookie_name = AUTH_COOKIE;
			$scheme = 'auth';
		}

		//$cookie_domain = COOKIE_DOMAIN;
		$cookie_path = COOKIEPATH;
		$cookie_path_site = SITECOOKIEPATH;
		$cookie_path_plugins = PLUGINS_COOKIE_PATH;
		$cookie_path_admin = ADMIN_COOKIE_PATH;

		if ( $this->getPlugin()->getSetting('ssl_host_diff') && $this->getPlugin()->isSsl() ) {
			// If SSL Host is a subdomain and we're setting an authentication cookie, the cookie does not need to be set
			if ( $this->getPlugin()->getSetting('ssl_host_subdomain') && ( $scheme == 'auth' || $scheme == 'secure_auth' ) ) {
				return;
			// If SSL Host is a subdomain, make cookie domain a wildcard
			} else if ( $this->getPlugin()->getSetting('ssl_host_subdomain') ) {
				$cookie_domain = '.' . $this->getPlugin()->getHttpsUrl()->getBaseHost();
			// Otherwise, cookie domain set for different SSL Host
			} else {
				$cookie_domain = parse_url($this->getPlugin()->getHttpsUrl(), PHP_URL_HOST);
			}

			$cookie_path = rtrim($this->getPlugin()->getHttpsUrl()->getPath(), '/') . str_replace($this->getPlugin()->getHttpsUrl()->getPath(), '', $cookie_path);
			$cookie_path_site = rtrim($this->getPlugin()->getHttpsUrl()->getPath(), '/') . str_replace($this->getPlugin()->getHttpsUrl()->getPath(), '', $cookie_path_site);
			$cookie_path_plugins = rtrim($this->getPlugin()->getHttpsUrl()->getPath(), '/') . str_replace($this->getPlugin()->getHttpsUrl()->getPath(), '', $cookie_path_plugins);
			$cookie_path_admin = $cookie_path_site . 'wp-admin';
		}

		// Cookie paths defined to accomodate different SSL Host
		if ( version_compare(phpversion(), '5.2.0', '>=') ) {
			if ( $scheme == 'logged_in' ) {
				setcookie($cookie_name, $cookie, $expire, $cookie_path, $cookie_domain, $secure, true);
				if ( $cookie_path != $cookie_path_site ) {
					setcookie($cookie_name, $cookie, $expire, $cookie_path_site, $cookie_domain, $secure, true);
				}
			} else {
				setcookie($cookie_name, $cookie, $expire, $cookie_path_plugins, $cookie_domain, false, true);
				setcookie($cookie_name, $cookie, $expire, $cookie_path_admin, $cookie_domain, false, true);
			}
		} else {
			if ( !empty($cookie_domain) ) {
				$cookie_domain .= '; HttpOnly';
			}

			if ( $scheme == 'logged_in' ) {
				setcookie($cookie_name, $cookie, $expire, $cookie_path, $cookie_domain, $secure);
				if ( $cookie_path != $cookie_path_site ) {
					setcookie($cookie_name, $cookie, $expire, $cookie_path_site, $cookie_domain, $secure);
				}
			} else {
				setcookie($cookie_name, $cookie, $expire, $cookie_path_plugins, $cookie_domain);
				setcookie($cookie_name, $cookie, $expire, $cookie_path_admin, $cookie_domain);
			}
		}
	}

	/**
	 * Clear Cookies
	 * WordPress Hook - clear_auth_cookie
	 *
	 * @param none
	 * @return void
	 */
	public function clear_cookies() {
		$cookie_domain = '.' . $this->getPlugin()->getHttpsUrl()->getBaseHost();
		$cookie_path = rtrim(parse_url($this->getPlugin()->getHttpsUrl(), PHP_URL_PATH), '/') . COOKIEPATH;
		$cookie_path_site = rtrim(parse_url($this->getPlugin()->getHttpsUrl(), PHP_URL_PATH), '/') . SITECOOKIEPATH;
		$cookie_path_plugins = rtrim(parse_url($this->getPlugin()->getHttpsUrl(), PHP_URL_PATH), '/') . PLUGINS_COOKIE_PATH;
		$cookie_path_admin = $cookie_path_site . 'wp-admin';

		if ( $this->getPlugin()->getSetting('ssl_host_subdomain') ) {
			setcookie(LOGGED_IN_COOKIE, ' ', time() - 31536000, $cookie_path, $cookie_domain);
			setcookie(LOGGED_IN_COOKIE, ' ', time() - 31536000, $cookie_path_site, $cookie_domain);
		}

		setcookie(AUTH_COOKIE, ' ', time() - 31536000, $cookie_path_admin);
		setcookie(AUTH_COOKIE, ' ', time() - 31536000, $cookie_path_plugins);
		setcookie(SECURE_AUTH_COOKIE, ' ', time() - 31536000, $cookie_path_admin);
		setcookie(SECURE_AUTH_COOKIE, ' ', time() - 31536000, $cookie_path_plugins);
		setcookie(LOGGED_IN_COOKIE, ' ', time() - 31536000, $cookie_path);
		setcookie(LOGGED_IN_COOKIE, ' ', time() - 31536000, $cookie_path_site);
	}

}