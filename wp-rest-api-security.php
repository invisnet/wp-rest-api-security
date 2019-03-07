<?php
/*
 * Plugin Name: WP REST API Security
 * Description: A UI to choose which REST API endpoints to enable.
 * Text Domain: wp-rest-api-security
 * Version: 1.0.0
 * Author: Charles Lecklider
 * Author URI: https://charles.lecklider.org/
 * License: GPLv2
 * SPDX-License-Identifier: GPL-2.0
 * Requires PHP: 7.0
 */

/**
 * @package restes
 */
namespace org\lecklider\charles\wordpress\rest_security;

if (!defined('ABSPATH')) {
    exit;
}

/**
 *
 * @since 1.0.0
 *
 * @param string $str
 */
function hash_piece(string $str)
{
    return md5($str);
}

/**
 *
 * @since 1.0.0
 *
 * @param string    $route
 */
function split_route(string $route)
{
    return preg_split('~\([^\)]*\)(*SKIP)(*F)|\/~', ltrim($route, '/'));
}

if (is_admin()) {
    /**
     * @since 1.0.0
     */
    function admin_enqueue_scripts()
    {
        wp_enqueue_script('wp-rest-api-security', plugins_url('/script.js', __FILE__), ['jquery']);
        wp_enqueue_style('wp-rest-api-security', plugins_url('/style.css', __FILE__));
    }
    add_action('admin_enqueue_scripts', __NAMESPACE__.'\admin_enqueue_scripts');

    /**
     *
     * @since 1.0.0
     */
    function admin_init()
    {
        register_setting(
            'wp-rest-api-security',
            'wp-rest-api-security',
            [
                'sanitize_callback' => __NAMESPACE__.'\sanitize_callback'
            ]
        );
    }
    add_action('admin_init', __NAMESPACE__.'\admin_init');

    /**
     *
     * @since 1.0.0
     *
     * @param $input
     */
    function sanitize_callback($input)
    {
        $tree = [];
        update_tree($tree, $input['enabled'], 'disabled', false);
        update_tree($tree, $input['public'], 'public', true);

        return $tree;
    }

    /**
     *
     * @since 1.0.0
     */
    function admin_menu()
    {
        $hook = add_options_page(
            'WP REST API Security',
            'WP REST API Security',
            'manage_options',
            'wp-rest-api-security',
            __NAMESPACE__.'\display'
        );
        add_action("load-$hook", __NAMESPACE__.'\admin_menu_hook');
    }
    add_action('admin_menu', __NAMESPACE__.'\admin_menu');

    /**
     *
     * @since 1.0.0
     */
    function admin_menu_hook()
    {
        get_current_screen()->add_help_tab( array(
            'id'      => 'wp-rest-api-security',
            'title'   => __('WP REST API Security'),
            'content' => '<p>'.__('All REST endpoints are disabled by default; <strong>Enable</strong> only those you need for your application.').'</p>'.
                         '<p>'.__('All enabled REST endpoints require authentication by default; make <strong>Public</strong> only those you need to expose.').'</p>'
        ) );

        get_current_screen()->set_help_sidebar(
            '<p><strong>' . __('For more information:') . '</strong></p>' .
            '<p>' . __('<a href="https://wordpress.org/support/plugin/wp-rest-api-security/">Support Forums</a>') . '</p>'
        );
    }

    /**
     *
     * @since 1.0.0
     *
     * @param array     $tree
     */
    function load_tree(array $tree)
    {
        $routes = rest_get_server()->get_routes();

        foreach ($routes as $route => $handlers) {
            $tree_ptr = &$tree;
            $pieces = split_route($route);
            foreach ($pieces as $branch) {
                if ($branch > '') {
                    $branch_hash = hash_piece($branch);

                    if (is_array($tree_ptr) && array_key_exists($branch_hash, $tree_ptr)) {
                        $tree_ptr[$branch_hash]['opts']['name'] = $branch;
                    } else {
                        $tree_ptr[$branch_hash] = [
                            'opts' => [
                                'name'      => $branch,
                                'disabled'  => true,
                                'public'    => false
                            ],
                            'branches' => []
                        ];
                    }
                    $tree_ptr = &$tree_ptr[$branch_hash]['branches'];
                }
            }
        }

        return $tree;
    }

    /**
     *
     * @since 1.0.0
     *
     * @param array     $tree_ptr
     * @param array     $input_ptr
     * @param string    $_key
     * @param string    $_value
     */
    function update_tree(array &$tree_ptr, array &$input_ptr, string $_key, string $_value)
    {
        foreach ($input_ptr as $key => $value) {
            $tree_ptr[$key]['opts'][$_key] = $_value;

            if (is_array($value)) {
                if (!array_key_exists('branches', $tree_ptr[$key])) {
                    $tree_ptr[$key]['branches'] = [];
                }
                update_tree($tree_ptr[$key]['branches'], $value, $_key, $_value);
            }
        }
    }

    /**
     *
     * @since 1.0.0
     *
     * @param string|null  $branch
     */
    function filter_regex(string $branch = null)
    {
        $match = preg_match('/(\<\w+\>)/', $branch, $matches);
        $branch = htmlentities($branch);
        return ($match)
            ? "<em>{$branch}</em>"
            : $branch;
    }

    /**
     *
     * @since 1.0.0
     *
     * @param array     $tree
     * @param bool      $enabled
     * @param array     $pnodes
     */
    function print_tree(array $tree, bool $enabled = true, array $pnodes = [])
    {
        foreach ($tree as $branch => $node) {
            $id = join('_', array_merge($pnodes, [$branch]));

            echo '<tr>';
            printf('<td class="endpoint">%s<label for="%s">%s</label>%s</td>',
                str_repeat('&nbsp;', count($pnodes)*2),
                $id,
                filter_regex(@$node['opts']['name']),
                str_repeat('&nbsp;', 4)
            );
            printf('<td><input %s class="enabled %s" %s id="%s" type="checkbox" name="wp-rest-api-security[enabled][%s]"></td>',
                checked($node['opts']['disabled'], false, false),
                join('_', $pnodes),
                disabled(!$node['opts']['disabled'] || $enabled, false, false),
                $id,
                join('][', array_merge($pnodes, [$branch]))
            );
            printf('<td><input %s class="public_%s" %s id="public_%s" type="checkbox" name="wp-rest-api-security[public][%s]"></td>',
                checked(@$node['opts']['public'], true, false),
                join('_', $pnodes),
                disabled($node['opts']['disabled'], true, false),
                $id,
                join('][', array_merge($pnodes, [$branch]))
            );
            echo '</tr>';

            if (array_key_exists('branches', $node)) {
                $rnodes = $pnodes;
                $rnodes[] = $branch;
                print_tree($node['branches'], false, $rnodes);
            }
        }
    }

    /**
     *
     * @since 1.0.0
     */
    function display()
    {
        $tree = load_tree(get_option('wp-rest-api-security', []));
?>
<div class="wrap">
  <div id="icon-options-general" class="icon32"></div>
  <h1>WP REST API Security</h1>
  <h2 class="nav-tab-wrapper">
    <a class="nav-tab nav-tab-active" href="#">Endpoints</a>
  </h2>
  <form action="options.php" method="post" id="poststuff">
    <div id="post-body" class="metabox-holder columns-2">
      <div id="post-body-content">
        <div class="meta-box-sortables ui-sortable">
          <div class="postbox">
            <div class="inside">
              <?php settings_fields('wp-rest-api-security'); ?>
              <table>
                <tr>
                  <th class="endpoint">Endpoint</th>
                  <th><?php _e('Enabled', 'wp-rest-api-security')?></th>
                  <th><?php _e('Public', 'wp-rest-api-security')?></th>
                </tr>
                <?php print_tree($tree); ?>
              </table>
            </div>
          </div>
        </div>
      </div>
      <div id="postbox-container-1" class="postbox-container">
        <div class="meta-box-sortables">
          <div class="postbox">
            <div class="inside">
              <?php submit_button(null, 'primary', 'submit', false); ?>
            </div>
          </div>
        </div>
      </div>
    </div>
    <br class="clear">
  </form>
</div>
<?php
    }
} else {
    /**
     *
     * @since 1.0.0
     *
     * @param null              $response   Response to replace the requested version with. Can be
     *                                      anything a normal endpoint can return, or null to not
     *                                      hijack the request.
     * @param \WP_REST_Server   $server     Server instance.
     * @param \WP_REST_Request  $request    Request used to generate the response.
     */
    function rest_pre_dispatch($response, \WP_REST_Server $server, \WP_REST_Request $request)
    {
        $method = $request->get_method();
        $path   = $request->get_route();

        if ('/' == $path) {
            return $response;
        }

        foreach ($server->get_routes() as $route => $handlers) {
            if (preg_match('@^'.$route.'$@i', $path)) {
                $pieces = split_route($route);
                $tree = get_option('wp-rest-api-security', []);

                $tree_ptr = &$tree;
                for ($i = 0; $i < count($pieces)-1; $i++) {
                    $hash = hash_piece($pieces[$i]);
                    if (array_key_exists($hash, $tree_ptr)) {
                        $tree_ptr = &$tree_ptr[$hash]['branches'];
                    } else {
                        rest_404();
                    }
                }
                $hash = hash_piece($pieces[$i]);
                if (is_array($tree_ptr) && array_key_exists($hash, $tree_ptr)) {
                    $tree_ptr = &$tree_ptr[$hash];
                    if (true == $tree_ptr['opts']['disabled']) {
                        rest_404();
                    }
                } else {
                    rest_404();
                }

                if (@$tree_ptr['opts']['public'] || is_user_logged_in()) {
                    return $response;
                } else {
                    rest_401();
                }
            }
        }

        return $response;
    }
    add_filter('rest_pre_dispatch', __NAMESPACE__.'\rest_pre_dispatch', 10, 3);

    /**
     *
     * @since 1.0.0
     */
    function rest_404()
    {
        exit(json_encode([
            'code'=>'rest_no_route',
            'message'=>__('No route was found matching the URL and request method'),
            'data'=>[
                'status' => 404
            ]
        ]));
    }

    /**
     *
     * @since 1.0.0
     */
    function rest_401()
    {
        exit(json_encode([
            'code' => 'rest_forbidden',
            'message' => __( 'Sorry, you are not allowed to do that.' ),
            'data' => [
                'status' => 401
            ]
        ]));
    }
}

