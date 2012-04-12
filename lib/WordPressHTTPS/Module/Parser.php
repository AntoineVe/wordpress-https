<?php
/**
 * HTML Parser Module
 *
 * @author Mike Ems
 * @package WordPressHTTPS
 *
 */

require_once('WordPressHTTPS/Module.php');
require_once('WordPressHTTPS/Module/Interface.php');

class WordPressHTTPS_Module_Parser extends WordPressHTTPS_Module implements WordPressHTTPS_Module_Interface {

	/**
	 * HTML
	 *
	 * @var string
	 */
	protected $_html;
	
	/**
	 * Extensions
	 * 
	 * Array of file extensions to be loaded securely.
	 *
	 * @var array
	 */
	protected $_extensions = array('jpg', 'jpeg', 'png', 'gif', 'css', 'js');
	
	/**
	 * Secure External URL's
	 * 
	 * External URL's that are available over HTTPS.
	 *
	 * @var string
	 */
	protected $_secure_external_urls = array();
	
	/**
	 * Unsecure External URL's
	 * 
	 * External URL's that are not available over HTTPS.
	 *
	 * @var string
	 */
	protected $_unsecure_external_urls = array();

	/**
	 * Add Secure External URL
	 * 
	 * Stores the value of this array in WordPress options.
	 *
	 * @param array $value
	 * @return $this
	 */
	public function setSecureExternalUrls( $value ) {
		$property = '_secure_external_urls';
		$this->$property = $value;
		update_option($this->getPlugin()->getSlug() . $property, $this->$property);
		return $this;
	}
	
	/**
	 * Get Secure External URL's
	 * 
	 * Retrieves the value of this array from WordPress options.
	 *
	 * @param none
	 * @return array
	 */
	public function getSecureExternalUrls() {
		$property = '_secure_external_urls';
		$option = get_option($this->getPlugin()->getSlug() . $property);
		if ( $option !== false ) {
			return $option;
		} else {
			return $this->$property;
		}
	}
	
	/**
	 * Add Secure External URL
	 * 
	 * @param string $value
	 * @return $this
	 */
	public function addSecureExternalUrl( $value ) {
		if ( $value == '' ) {
			return $this;
		}
		
		$property = '_secure_external_urls';
		array_push($this->$property, $value);
		update_option($this->getPlugin()->getSlug() . $property, $this->$property);
		return $this;
	}
	
	/**
	 * Set Unsecure External URL's
	 * 
	 * Stores the value of this array in WordPress options.
	 *
	 * @param array $value
	 * @return $this
	 */
	public function setUnsecureExternalUrls( $value = array() ) {
		$property = '_unsecure_external_urls';
		$this->$property = $value;
		update_option($this->getPlugin()->getSlug() . $property, $this->$property);
		return $this;
	}
	
	/**
	 * Add Unsecure External URL
	 * 
	 * @param string $value
	 * @return $this
	 */
	public function addUnsecureExternalUrl( $value ) {
		if ( $value == '' ) {
			return $this;
		}
		
		$property = '_unsecure_external_urls';
		array_push($this->$property, $value);
		update_option($this->getPlugin()->getSlug() . $property, $this->$property);
		return $this;
	}
	
	/**
	 * Get Unsecure External URL's
	 * 
	 * Retrieves the value of this array from WordPress options.
	 *
	 * @param none
	 * @return array
	 */
	public function getUnsecureExternalUrls() {
		$property = '_unsecure_external_urls';
		$option = get_option($this->getPlugin()->getSlug() . $property);
		if ( $option !== false ) {
			return $option;
		} else {
			return $this->$property;
		}
	}
	
	/**
	 * Initialize
	 *
	 * @param none
	 * @return void
	 */
	public function init() {
		// Start output buffering
		add_action('init', array(&$this, 'startOutputBuffering'));
	}
	
	/**
	 * Runs when the plugin settings are reset.
	 *
	 * @param none
	 * @return void
	 */
	public function reset() {
		delete_option($this->getPlugin()->getSlug() . '_secure_external_urls');
		delete_option($this->getPlugin()->getSlug() . '_unsecure_external_urls');
	}

	/**
	 * Parse HTML
	 * 
	 * Parses the output buffer to fix HTML output
	 *
	 * @param string $buffer
	 * @return string $this->_html
	 */
	public function parseHtml( $buffer ) {
		$this->_html = $buffer;
		
		$this->fixExtensions();
		$this->fixElements();
		$this->fixLinksAndForms();

		return $this->_html;
	}
	
	/**
	 * Start output buffering
	 *
	 * @param none
	 * @return void
	 */
	public function startOutputBuffering() {
		ob_start(array(&$this, 'parseHtml'));
	}
	
	/**
	 * Fix Elements
	 * 
	 * Fixes schemes on DOM elements.
	 *
	 * @param string $buffer
	 * @return string $this->_html
	 */
	public function fixElements() {
		// Fix any occurrence of the HTTPS version of the regular domain when using different SSL Host
		if ( $this->getPlugin()->getSetting('ssl_host_diff') ) {
			$url = clone $this->getPlugin()->getHttpUrl();
			$url->setScheme('https');
			
			$count = substr_count($this->_html, $url);
			if ( $count > 0 ) {
				$this->getPlugin()->getLogger()->log('[FIXED] Updated ' . $count . ' Occurrences of URL: ' . $url . ' => ' . $this->getPlugin()->makeUrlHttp($url));
				$this->_html = str_replace($url, $this->getPlugin()->makeUrlHttp($url), $this->_html);
			}
		}

		if ( $this->getPlugin()->isSsl() ) {
			if ( is_admin() ) {
				preg_match_all('/\<(script|link|img)[^>]+[\'"]((http|https):\/\/[^\'"]+)[\'"][^>]*>/im', $this->_html, $matches);
			} else {
				preg_match_all('/\<(script|link|img|form|input|embed|param)[^>]+[\'"]((http|https):\/\/[^\'"]+)[\'"][^>]*>/im', $this->_html, $matches);
			}
			for ($i = 0; $i < sizeof($matches[0]); $i++) {
				$html = $matches[0][$i];
				$type = $matches[1][$i];
				$url = $matches[2][$i];
				$scheme = $matches[3][$i];
				$updated = false;

				if ( $type == 'img' || $type == 'script' || $type == 'embed' ||
					( $type == 'link' && ( strpos($html, 'stylesheet') !== false || strpos($html, 'pingback') !== false ) ) ||
					( $type == 'form' && strpos($html, 'wp-pass.php') !== false ) ||
					( $type == 'form' && strpos($html, 'commentform') !== false ) ||
					( $type == 'input' && strpos($html, 'image') !== false ) ||
					( $type == 'param' && strpos($html, 'movie') !== false )
				) {
					// Fix image tags in the admin panel
					if ( is_admin() && $type == 'img' ) {
						if ( $this->getPlugin()->isUrlLocal($url) && $this->getPlugin()->isSsl() ) {
							$updated = $this->getPlugin()->makeUrlHttps($url);
							$this->_html = str_replace($html, str_replace($url, $updated, $html), $this->_html);
						}
					} else {
						$url = WordPressHTTPS_Url::fromString($url);
						// If local
						if ( $this->getPlugin()->isUrlLocal($url) ) {
							$updated = $this->getPlugin()->makeUrlHttps($url);
							$this->_html = str_replace($html, str_replace($url, $updated, $html), $this->_html);
						// If external and not HTTPS
						} else if ( strpos($url, 'https') === false ) {
							if ( @in_array($url, $this->getSecureExternalUrls()) == false && @in_array($url, $this->getUnsecureExternalUrls()) == false ) {
								$test_url = clone $url;
								$test_url->setScheme('https');
								if ( $test_url->isValid() ) {
									// Cache this URL as available over HTTPS for future reference
									$this->addSecureExternalUrl($url);
								} else {
									// If not available over HTTPS, mark as an unsecure external URL
									$this->addUnsecureExternalUrl($url);
								}
							}

							if ( in_array($url, $this->getSecureExternalUrls()) ) {
								$updated = clone $url;
								$updated->setScheme('https');
								$this->_html = str_replace($html, str_replace($url, $updated, $html), $this->_html);
							}
						}

						if ( $updated == false && strpos($url, 'https') === false ) {
							$this->getPlugin()->getLogger()->log('[WARNING] Unsecure Element: <' . $type . '> - ' . $url);
						}
					}
				}

				if ( $updated && $url != $updated ) {
					$this->getPlugin()->getLogger()->log('[FIXED] Element: <' . $type . '> - ' . $url . ' => ' . $updated);
				}
			}

			// Fix any CSS background images or imports
			preg_match_all('/(import|background)[:]?[^u]*url\([\'"]?(http:\/\/[^)]+)[\'"]?\)/im', $this->_html, $matches);
			for ($i = 0; $i < sizeof($matches[0]); $i++) {
				$css = $matches[0][$i];
				$url = $matches[2][$i];
				$updated = $this->getPlugin()->makeUrlHttps($url);
				$this->_html = str_replace($css, str_replace($url, $updated, $css), $this->_html);
				$this->getPlugin()->getLogger()->log('[FIXED] CSS: ' . $url . ' => ' . $updated);
			}

			// Look for any relative paths that should be udpated to the SSL Host path
			if ( $this->getPlugin()->getHttpUrl()->getPath() != $this->getPlugin()->getHttpsUrl()->getPath() ) {
				preg_match_all('/\<(script|link|img|input|form|embed|param|a)[^>]+(src|href|action|data|movie|image|value)=[\'"](\/[^\'"]*)[\'"][^>]*>/im', $this->_html, $matches);

				for ($i = 0; $i < sizeof($matches[0]); $i++) {
					$html = $matches[0][$i];
					$type = $matches[1][$i];
					$attr = $matches[2][$i];
					$url_path = $matches[3][$i];
					if (
						$type != 'input' ||
						( $type == 'input' && $attr == 'image' ) ||
						( $type == 'input' && strpos($html, '_wp_http_referer') !== false )
					) {
						$updated = clone $this->getPlugin()->getHttpsUrl();
						$updated->setPath($url_path);
						$this->_html = str_replace($html, str_replace($url_path, $updated, $html), $this->_html);
						$this->getPlugin()->getLogger()->log('[FIXED] Element: <' . $type . '> - ' . $url_path . ' => ' . $updated);
					}
				}
			}
		}
		
	}
		
	/**
	 * Fix Extensions
	 * 
	 * Fixes schemes on DOM elements with extensions specified in $this->_extensions
	 *
	 * @param string $buffer
	 * @return string $this->_html
	 */
	public function fixExtensions() {
		if ( $this->getPlugin()->isSsl() ) {
			@preg_match_all('/(http|https):\/\/[\/-\w\d\.,~#@^!\'()?=\+&%;:[\]]+/i', $this->_html, $matches);
			for ($i = 0; $i < sizeof($matches[0]); $i++) {
				$url = rtrim($matches[0][$i], '\'"');
				$filename = basename($url);
				$scheme = $matches[1][$i];
				$updated = false;
	
				foreach( $this->_extensions as $extension ) {
					if ( strpos($filename, '.' . $extension) !== false ) {
						$url = WordPressHTTPS_Url::fromString($url);
						if ( $this->getPlugin()->isUrlLocal( $url ) ) {
							$updated = $this->getPlugin()->makeUrlHttps($url);
							$this->_html = str_replace($url, $updated, $this->_html);
						} else if ( $url->getScheme() != 'https' ) {
							if ( @in_array($url, $this->getSecureExternalUrls()) == false && @in_array($url, $this->getUnsecureExternalUrls()) == false ) {
								$test_url = clone $url;
								$test_url->setScheme('https');
								if ( $test_url->isValid() ) {
									// Cache this URL as available over HTTPS for future reference
									$this->addSecureExternalUrl($url);
								} else {
									// If not available over HTTPS, mark as an unsecure external URL
									$this->addUnsecureExternalUrl($url);
								}
							}
			
							if ( in_array($url, $this->getSecureExternalUrls()) ) {
								$updated = clone $url;
								$updated->setScheme('https');
								$this->_html = str_replace($url, $updated, $this->_html);
								$this->_html = str_replace(preg_quote($url), preg_quote($updated), $this->_html);
							}
						}
		
						if ( $updated && $url != $updated ) {
							$this->getPlugin()->getLogger()->log('[FIXED] Element: ' . $url . ' => ' . $updated);
						} else if ( $updated == false && $url->getScheme() == 'http' ) {
							$this->getPlugin()->getLogger()->log('[WARNING] Unsecure Element: <' . $type . '> - ' . $url);
						}
					}
				}
			}
		}
	}

	/**
	 * Fix links and forms
	 *
	 * @param none
	 * @return void
	 */
	public function fixLinksAndForms() {
		// Update anchor and form tags to appropriate URL's
		preg_match_all('/\<(a|form)[^>]+[\'"]((http|https):\/\/[^\'"]+)[\'"][^>]*>/im', $this->_html, $matches);

		for ($i = 0; $i < sizeof($matches[0]); $i++) {
			$html = $matches[0][$i];
			$type = $matches[1][$i];
			$url = $matches[2][$i];
			$scheme = $matches[3][$i];
			$updated = false;

			unset($force_ssl);

			if ( $this->getPlugin()->isUrlLocal($url) ) {
				$url_parts = parse_url($url);
				if ( $this->getPlugin()->getSetting('ssl_host_diff') && $this->getPlugin()->getHttpsUrl()->getPath() != '/' ) {
					$url_parts['path'] = str_replace($this->getPlugin()->getHttpsUrl()->getPath(), '', $url_parts['path']);
				}
				$url_parts['path'] = str_replace($this->getPlugin()->getHttpUrl()->getPath(), '', $url_parts['path']);

				if ( preg_match("/page_id=([\d]+)/", parse_url($url, PHP_URL_QUERY), $postID) ) {
					$post = $postID[1];
				} else if ( $post = get_page_by_path($url_parts['path']) ) {
					$post = $post->ID;
				} else if ( $url_parts['path'] == '/' ) {
					if ( get_option('show_on_front') == 'posts' ) {
						$post = true;
						$force_ssl = (( $this->getPlugin()->getSetting('frontpage') == 1 ) ? true : false);
					} else {
						$post = get_option('page_on_front');
					}
				//TODO When logged in to HTTP and visiting an HTTPS page, admin links will always be forced to HTTPS, even if the user is not logged in via HTTPS. I need to find a way to detect this.
				} else if ( ( strpos($url_parts['path'], 'wp-admin') !== false || strpos($url_parts['path'], 'wp-login') !== false ) && ( $this->getPlugin()->isSsl() || $this->getPlugin()->getSetting('ssl_admin') ) && ( !is_multisite() || ( is_multisite() && $url_parts['host'] == $this->getPlugin()->getHttpsUrl()->getHost() ) ) ) {
					$post = true;
					$force_ssl = true;
				}

				if ( isset($post) ) {
					// Always change links to HTTPS when logged in via different SSL Host
					if ( $type == 'a' && $this->getPlugin()->getSetting('ssl_host_subdomain') == 0 && $this->getPlugin()->getSetting('ssl_host_diff') && $this->getPlugin()->getSetting('ssl_admin') && is_user_logged_in() ) {
						$force_ssl = true;
					} else if ( (int) $post > 0 ) {
						$force_ssl = (( !isset($force_ssl) ) ? get_post_meta($post, 'force_ssl', true) : $force_ssl);
						
						$postParent = get_post($post);
						while ( $postParent->post_parent ) {
							$postParent = get_post( $postParent->post_parent );
							if ( get_post_meta($postParent->ID, 'force_ssl_children', true) == 1 ) {
								$force_ssl = true;
								break;
							}
						}
						
						$force_ssl = apply_filters('force_ssl', $force_ssl, $post );
					}

					if ( $force_ssl == true ) {
						$updated = $this->getPlugin()->makeUrlHttps($url);
						$this->_html = str_replace($html, str_replace($url, $updated, $html), $this->_html);
					} else if ( $this->getPlugin()->getSetting('exclusive_https') ) {
						$updated = $this->getPlugin()->makeUrlHttp($url);
						$this->_html = str_replace($html, str_replace($url, $updated, $html), $this->_html);
					}
				}

				if ( $updated && $url != $updated ) {
					$this->getPlugin()->getLogger()->log('[FIXED] Element: <' . $type . '> - ' . $url . ' => ' . $updated);
				}
			}
		}
	}

}