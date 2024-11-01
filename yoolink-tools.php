<?php
/*
Plugin Name: Yoolink Tools
Plugin URI: http://www.yoolink.fr/plugins/wordpress
Description: A complete integration between your WordPress blog and <a href="http://go.yoolink.to/my">your Yoolink account</a>. Export your last bookmarks, your profile picture and stats and your tag cloud into your sidebar.
Version: 1.1
Author: Yoolink Crew
*/
$yoolink_version = '1.1';

/*  Copyright 2008  Yoolink  (email : dev@yoolink.fr)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/* Private Helpers */

function yoolink_check_api_key( $key ) {
	$response = yoolink_http_post("", 'api.yoolink.fr', "/$key/check", 80);
	
    if ( !is_array($response) || !isset($response[0]))
		return false;
//    echo "RES: '".$response[0]."'";

	if (ereg('HTTP\/1.. 200', $response[0])) {
        return true;
    }
    return false;
}

// Returns array with headers in $response[0] and body in $response[1]
// Gently taken from Akismet plugin, thanks!
function yoolink_http_post($request, $host, $path, $port = 80) {
	global $wp_version;
	$http_request  = "POST $path HTTP/1.0\r\n";
	$http_request .= "Host: $host\r\n";
	$http_request .= "Content-Type: application/x-www-form-urlencoded; charset=" . get_option('blog_charset') . "\r\n";
	$http_request .= "Content-Length: " . strlen($request) . "\r\n";
	$http_request .= "User-Agent: WordPress/$wp_version | YoolinkTools/$yoolink_version\r\n";
	$http_request .= "\r\n";
	$http_request .= $request;

	$response = "";
	if( false != ( $fs = @fsockopen($host, $port, $errno, $errstr, 10) ) ) {
		fwrite($fs, $http_request);

		while ( !feof($fs) )
			$response .= fgets($fs, 1160); // One TCP-IP packet
		fclose($fs);
		$response = explode("\r\n\r\n", $response, 2);
	}
	return $response;
}

function yoolink_api_url($api_key) {
    if (ereg('.*_(\w\w)$', $api_key, $match)) {
        if ($match == 'en') {
            return('http://api.yoolink.us');
        }
    }
    return('http://api.yoolink.fr');
}

function yoolink_static_url() {
    if (isset($_GET['debug'])) {
        return "http://yoolink.preprod.yoolink.fr";
    }
    if (isset($_GET['debug_sun'])) {
        return "http://sunny.dev.yoolink.fr";
    }
    return "http://static.yoolink.fr";
}

function yoolink_base_url($api_key) {
    return yoolink_api_url($api_key)."/$api_key";
}

function yoolink_make_url($api_key, $path) {
    return yoolink_base_url($api_key)."/$path";
}

function yoolink_echo_widget_content_for($action, $title, $args) {
    extract($args);
    $api_key = get_option('yoolink_api_key');

    echo $before_widget;
    echo "$before_title $title $after_title";
    echo "<div style=\"width: 100%;\"><script src=\"".yoolink_make_url($api_key, $action)."\"></script></div>";
    echo $after_widget;
}

function yoolink_plugin_action_links($links, $file) {
    $plugin_file = plugin_basename(__FILE__);

	if ($file == $plugin_file) {
		$settings_link = '<a href="options-general.php?page='.$plugin_file.'">Settings</a>';
        array_unshift( $links, $settings_link ); // before other links
        //$links[] = $settings_link; // ... or after other links
	}
	return $links;
}
add_filter('plugin_action_links', 'yoolink_plugin_action_links', 10, 2);


/* Widgets */
function yoolink_register_widgets() {
    if (! function_exists('register_sidebar_widget')) {
        return;
    }

    
    /* My Yoolink - a profile widget, with picture and stats */
    function yoolink_profile_widget($args) {
        $size = get_option('yoolink_profile_widget_size');

        if ($size == 'small') {
            yoolink_echo_widget_content_for('my_yoolink_small.js', '', $args);
        }
        else {
            yoolink_echo_widget_content_for('my_yoolink.js', '', $args);
        }
    }
	register_sidebar_widget('Yoolink Profile', 'yoolink_profile_widget', null, 'yoolink_profile');

    // The profile widget comes with a pref: size={small|big}
	function yoolink_profile_widget_control() {
		$size = get_option('yoolink_profile_widget_size');
		if (isset($_POST['yoolink_profile_widget_size'])) {
			update_option('yoolink_profile_widget_size', $_POST['yoolink_profile_widget_size']);
		}
        
        print('
			<div>
            <div>
                This widget comes in two sizes, choose the one you want to use
            </div>
            <select name="yoolink_profile_widget_size">
                <option '.($size == 'small' ? 'selected=selected' : '').' value="small">Small (150x32)</option>
                <option '.($size == 'big' ? 'selected=selected' : '').' value="big">Big (110x200)</option>
            </select>
            </div>
		');
	}
	register_widget_control('Yoolink Profile', 'yoolink_profile_widget_control', 300, 100);

    /* My TagCloud - A tag-cloud of yoolink bookmarks */
    function yoolink_tagcloud_widget($args) {
        $size = get_option('yoolink_tagcloud_widget_size');
        yoolink_echo_widget_content_for("tagcloud.js?tagcloud_size=$size", 'Mon Nuage Yoolink', $args);
    }
    register_sidebar_widget('Yoolink Tagcloud', 'yoolink_tagcloud_widget', null, 'yoolink_tagcloud');

    // The tagcloud widget comes with a pref: size={small|medium|large}
	function yoolink_tagcloud_widget_control() {
		$size = get_option('yoolink_tagcloud_widget_size');
		if (isset($_POST['yoolink_tagcloud_widget_size'])) {
			update_option('yoolink_tagcloud_widget_size', $_POST['yoolink_tagcloud_widget_size']);
		}
        
        print('
			<div>
            <div>
                Choose the size of your tagcloud
            </div>
            <select name="yoolink_tagcloud_widget_size">
                <option '.($size == 'small' ? 'selected=selected' : '').' value="small">Small</option>
                <option '.($size == 'medium' ? 'selected=selected' : '').' value="medium">Medium</option>
                <option '.($size == 'large' ? 'selected=selected' : '').' value="large">Large</option>
            </select>
            </div>
		');
	}
	register_widget_control('Yoolink Tagcloud', 'yoolink_tagcloud_widget_control', 300, 100);

    /* My Last Bookmarks - last bookmarks */
    function yoolink_bookmarks_widget($args) {
        yoolink_echo_widget_content_for('lastbookmarks.js', 'Derniers ajouts Yoolink', $args);
    }
    register_sidebar_widget('Yoolink Bookmarks', 'yoolink_bookmarks_widget', null, 'yoolink_bookmarks');
}

/* Options */

add_action('admin_menu', 'yoolink_options_register');

function yoolink_init_default_option_values() {
    $size = get_option('yoolink_profile_widget_size');
    if (! isset($size) || ($size != 'small' && $size != 'big')) {
        update_option('yoolink_profile_widget_size', 'big');
    }
    $size = get_option('yoolink_tagcloud_widget_size');
    if (! isset($size) || ($size != 'small' && $size != 'medium' && $size != 'large')) {
        update_option('yoolink_tagcloud_widget_size', 'medium');
    }

    $button = get_option('yoolink_sharing_button');
    if ((! isset($button)) || ($button != 'small' && $button != 'medium' && $button != 'disabled')) {
        update_option('yoolink_sharing_button', 'medium');
    }

    $rss_link = get_option('yoolink_rss_link');
    if (!isset($rss_link) || ($rss_link != 'true' && $rss_link != 'false')) {
        update_option('yoolink_rss_link', 'true');
    }

    $button_display = get_option('yoolink_button_display');
    if (! isset($button_display) || ($button_display != 'all' && $button_display != 'posts')) {
        update_option('yoolink_button_display', 'all');
    }
}

function yoolink_options_register() {
    add_options_page('Yoolink Options', 'Yoolink', 8, __FILE__, 'yoolink_options');
    yoolink_init_default_option_values();
}

function yoolink_options() {
?>    
    <div class="wrap">
    <h2>Yoolink Tools Options</h2>
    <form method="post" action="options.php">
    <?php wp_nonce_field('update-options'); ?>
    
    <table class="form-table">
    
    <!-- The API Key -->
    <tr valign="top">
    <th scope="row">Public API Key</th>
    <td>
        <input type="text" name="yoolink_api_key" value="<?php echo get_option('yoolink_api_key'); ?>" />
        <p>
        <? if (get_option('yoolink_api_key')) { ?>
        <? if (yoolink_check_api_key(get_option('yoolink_api_key'))) { ?>
            <span style="color: #2d2;"> Your API Key is valid.</span> <br />
            You can now enable your <a href="widgets.php">Yoolink Widgets</a> if you want to add them to your sidebar.

        <? } else { ?>
            <span style="color: #d22;">Your API Key is invalid.</span> 
        <? } ?>
        <? } else { ?>
        <span style="color: #d22;">
            API Key needed (for using the widgets)
        </span>
        <? } ?>
        </p>
        The API key is needed for using the Yoolink Widgets. You can get yours in your <a href="http://go.yoolink.to/webmaster?code=api_key">Yoolink Account</a>.
     </td>
    </tr>

    <!-- Enable sharing button ? -->
    <tr valign="top">
    <th scope="row">Sharing button</th>
    <td>
        <div>
        The Yoolink Sharing button comes below every post of your blog, it lets your visitors bookmarks your post entries easliy, with one click.
        </div>
        
        <input <? echo (get_option('yoolink_sharing_button') == 'disabled') ? 'checked=checked' : ''?> type="radio" name="yoolink_sharing_button" value="disabled" id="yoolink_sharing_disabled" />
        <label for="yoolink_sharing_disabled">Disabled</label>
        
        <input <? echo (get_option('yoolink_sharing_button') == 'medium') ? 'checked=checked' : ''?>  type="radio" name="yoolink_sharing_button" value="medium" id="yoolink_sharing_medium" />
        <label for="yoolink_sharing_medium">Default button</label>
        
        <input <? echo (get_option('yoolink_sharing_button') == 'small') ? 'checked=checked' : ''?> type="radio" name="yoolink_sharing_button" value="small" id="yoolink_sharing_small" />
        <label for="yoolink_sharing_small">Small button</label>
    </td>
    </tr>

    <!-- Sharing button options -->
    <tr valign="top">
    <th scope="row">Sharing button display policy</th>
    <td>
        <input id="yoolink_button_display_all" type="radio" <? echo (get_option('yoolink_button_display') == 'all') ? 'checked=checked' : '' ?> name="yoolink_button_display" value="all" />
        <label for="yoolink_button_display_all">Global (homepage and permalinks)</label>
        
        <input id="yoolink_button_display_posts" type="radio" <? echo (get_option('yoolink_button_display') == 'posts') ? 'checked=checked' : '' ?> name="yoolink_button_display" value="posts" />
        <label for="yoolink_button_display_posts">Posts only (don't display the button on the front page)</label>
    </td>
    </tr>

    <!-- RSS Sharing Link -->
    <tr valign="top">
    <th scope="row">RSS Sharing Link</th>
    <td>
        <input id="yoolink_rss_link_true" type="radio" <? echo (get_option('yoolink_rss_link') == 'true') ? 'checked=checked' : '' ?> name="yoolink_rss_link" value="true" />
        <label for="yoolink_rss_link_true">Enabled</label>
        
        <input id="yoolink_rss_link_false" type="radio" <? echo (get_option('yoolink_rss_link') == 'false') ? 'checked=checked' : '' ?> name="yoolink_rss_link" value="false" />
        <label for="yoolink_rss_link_false">Disabled</label>
    </td>
    </tr>

    </table>
    
    <input type="hidden" name="action" value="update" />
    <input type="hidden" name="page_options" value="yoolink_api_key,yoolink_sharing_button,yoolink_button_display,yoolink_rss_link" />
    
    <p class="submit">
    <input type="submit" name="Submit" value="<?php _e('Save Changes') ?>" />
    </p>

    </form>
    </div>
<?php
}

/* filters */

function yoolink_register_filter_sharing_button() {
    add_filter('the_content', 'yoolink_display_sharing_button');

    function yoolink_display_sharing_button($content='') {
        $display = get_option('yoolink_sharing_button');
        $rss_link = get_option('yoolink_rss_link');
        $policy  = get_option('yoolink_button_display');
        if ( $display != 'disabled' ) {
            if ($policy != 'all' && is_home()) {
                return $content;
            }
            global $wp_query;
	        $post = $wp_query->post;
            $title = $post->post_title;
            $permalink = get_permalink($post->ID);

            if (is_feed()) {
                if ($rss_link == 'true') {
                    return "$content\n<p><a href=\"http://go.yoolink.to/addorshare?url_value=".urlencode($permalink)."&title=".urlencode($title)."\">Add on Yoolink</a></p>";
                }
                else {
                    return($content);
                }
            }
            else {
                return "$content\n".'
                    <script type="text/javascript" language="javascript">
                    yoolink_title = "'.$title.'";
                yoolink_permalink = "'.$permalink.'";
                yoolink_size = "'.$display.'";
                </script>
                    <div class="yoolink_button" style="text-align: right;">
                    <script type="text/javascript" src="'.yoolink_static_url().'/javascripts/yoolink-share-button.js"></script>
                    </div>
                    <noscript><a href="http://go.yoolink.to/addorshare">Share this on Yoolink</a></noscript>'; 
            }
        }
        return $content;
    }
}

function yoolink_register_filters() {
    yoolink_register_filter_sharing_button();
}

function yoolink_init_plugin() {
    yoolink_register_widgets();
    yoolink_register_filters();
}

/* init the plugin */
add_action('init', 'yoolink_init_plugin');

?>
