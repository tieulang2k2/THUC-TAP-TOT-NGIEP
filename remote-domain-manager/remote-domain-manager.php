<?php
/**
 * Plugin Name: Remote Domain Manager
 * Description: Quản lý domain từ xa và bài viết của chúng.
 * Version: 1.1
 * Author: Tên bạn
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Hook để thêm menu vào trang quản trị
add_action('admin_menu', 'rdm_add_admin_menu');

// Hook để đăng ký custom post type
add_action('init', 'rdm_register_custom_post_type');

// Hook để lưu bài viết và cập nhật lại trên domain gốc
add_action('save_post', 'rdm_save_post', 10, 3);

// Hook để thêm meta box vào trang chỉnh sửa bài viết
add_action('add_meta_boxes', 'rdm_add_meta_boxes');

// Hook để xử lý các yêu cầu GET và POST trên trang quản trị
add_action('admin_init', 'rdm_handle_form');
add_action('admin_init', 'rdm_handle_delete');

// Hook để enqueue styles
add_action('admin_enqueue_scripts', 'rdm_enqueue_styles');

function rdm_enqueue_styles() {
    wp_enqueue_style('remote-domain-manager', plugin_dir_url(__FILE__) . 'remote-domain-manager.css');
}

function rdm_add_admin_menu() {
    add_menu_page('Remote Domain Manager', 'Remote Domains', 'manage_options', 'remote-domain-manager', 'rdm_admin_page', 'dashicons-admin-network');
}

function rdm_register_custom_post_type() {
    register_post_type('remote_post', [
        'label' => 'Remote Posts',
        'public' => true,
        'show_in_menu' => false,
        'supports' => ['title', 'editor'],
        'capabilities' => [
            'create_posts' => 'do_not_allow', // Không cho phép tạo mới bài viết từ quản trị
        ],
        'map_meta_cap' => true,
    ]);
}

function rdm_admin_page() {
    ?>
    <div class="wrap">
        <h1>Remote Domain Manager</h1>
        <h2>Add Remote Domain</h2>
        <form method="post" action="">
            <input type="hidden" name="rdm_action" value="add_domain">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Domain</th>
                    <td><input type="text" name="rdm_domain" required></td>
                </tr>
                <tr valign="top">
                    <th scope="row">User</th>
                    <td><input type="text" name="rdm_user" required></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Password</th>
                    <td><input type="password" name="rdm_password" required></td>
                </tr>
            </table>
            <input type="submit" class="button-primary" value="Add Domain">
        </form>

        <h2>Display Domains</h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Domain</th>
                    <th>User</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $domains = get_option('rdm_domains', []);
                foreach ($domains as $domain) {
                    echo '<tr>';
                    echo '<td>' . esc_html($domain['domain']) . '</td>';
                    echo '<td>' . esc_html($domain['user']) . '</td>';
                    echo '<td>';
                    echo '<a href="' . admin_url('admin.php?page=remote-domain-manager&view=' . urlencode($domain['domain'])) . '" class="button">View Posts</a> ';
                    echo '<a href="' . admin_url('admin.php?page=remote-domain-manager&delete=' . urlencode($domain['domain'])) . '" class="button button-danger">Delete</a>';
                    echo '</td>';
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>

        <?php
        if (isset($_GET['view'])) {
            $domain = urldecode($_GET['view']);
            $paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
            $posts_per_page = 10;

            // Fetch posts for the current page
            $posts = rdm_get_remote_posts($domain, $paged, $posts_per_page);

            echo '<h2>Posts from ' . esc_html($domain) . '</h2>';
            echo '<table class="widefat striped">';
            echo '<thead><tr><th>Title</th><th>Actions</th></tr></thead>';
            echo '<tbody>';
            foreach ($posts as $post) {
                echo '<tr>';
                echo '<td>' . esc_html($post['title']['rendered']) . '</td>';
                echo '<td>';
                echo '<a href="' . admin_url('post.php?post=' . rdm_create_local_post($post, $domain) . '&action=edit') . '" class="button">Edit</a>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';

            // Pagination
            $total_posts = rdm_get_total_remote_posts($domain);
            $total_pages = ceil($total_posts / $posts_per_page);
            $range = 2; // Number of page links to show before and after the current page
            $start = max(1, $paged - $range);
            $end = min($total_pages, $paged + $range);

            echo '<div class="tablenav">';
            echo '<div class="pagination-links">';
            
            if ($paged > 1) {
                echo '<a href="' . esc_url(admin_url('admin.php?page=remote-domain-manager&view=' . urlencode($domain) . '&paged=' . ($paged - 1))) . '" class="page-numbers">&lt;</a> ';
            }

            if ($start > 1) {
                echo '<a href="' . esc_url(admin_url('admin.php?page=remote-domain-manager&view=' . urlencode($domain) . '&paged=1')) . '" class="page-numbers">1</a> ';
                if ($start > 2) {
                    echo '<span class="dots">...</span> ';
                }
            }

            for ($i = $start; $i <= $end; $i++) {
                $class = ($i === $paged) ? ' current' : '';
                echo '<a href="' . esc_url(admin_url('admin.php?page=remote-domain-manager&view=' . urlencode($domain) . '&paged=' . $i)) . '" class="page-numbers' . $class . '">' . $i . '</a> ';
            }

            if ($end < $total_pages) {
                if ($end < $total_pages - 1) {
                    echo '<span class="dots">...</span> ';
                }
                echo '<a href="' . esc_url(admin_url('admin.php?page=remote-domain-manager&view=' . urlencode($domain) . '&paged=' . $total_pages)) . '" class="page-numbers">' . $total_pages . '</a> ';
            }

            if ($paged < $total_pages) {
                echo '<a href="' . esc_url(admin_url('admin.php?page=remote-domain-manager&view=' . urlencode($domain) . '&paged=' . ($paged + 1))) . '" class="page-numbers">&gt;</a>';
            }

            echo '</div>';
            echo '</div>';
        }
        ?>
    </div>
    <?php
}

function rdm_handle_form() {
    if (isset($_POST['rdm_action']) && $_POST['rdm_action'] === 'add_domain') {
        $domains = get_option('rdm_domains', []);
        $domains[] = [
            'domain' => sanitize_text_field($_POST['rdm_domain']),
            'user' => sanitize_text_field($_POST['rdm_user']),
            'password' => sanitize_text_field($_POST['rdm_password']),
        ];
        update_option('rdm_domains', $domains);
        wp_redirect(admin_url('admin.php?page=remote-domain-manager'));
        exit;
    }
}

function rdm_handle_delete() {
    if (isset($_GET['delete'])) {
        $domain_to_delete = urldecode($_GET['delete']);
        $domains = get_option('rdm_domains', []);
        $domains = array_filter($domains, function($domain) use ($domain_to_delete) {
            return $domain['domain'] !== $domain_to_delete;
        });
        update_option('rdm_domains', $domains);
        wp_redirect(admin_url('admin.php?page=remote-domain-manager'));
        exit;
    }
}

function rdm_get_remote_posts($domain, $paged = 1, $posts_per_page = 10) {
    $domains = get_option('rdm_domains', []);
    foreach ($domains as $remote_domain) {
        if ($remote_domain['domain'] === $domain) {
            $url = $domain . '/wp-json/wp/v2/posts?per_page=' . $posts_per_page . '&page=' . $paged;
            $response = wp_remote_get($url, [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($remote_domain['user'] . ':' . $remote_domain['password'])
                ],
                'sslcertificates' => 'C:\xampp\apache\crt\demo.com\server.crt'
            ]);
            if (is_wp_error($response)) {
                return [];
            }
            return json_decode(wp_remote_retrieve_body($response), true);
        }
    }
    return [];
}

function rdm_get_total_remote_posts($domain) {
    $domains = get_option('rdm_domains', []);
    foreach ($domains as $remote_domain) {
        if ($remote_domain['domain'] === $domain) {
            $url = $domain . '/wp-json/wp/v2/posts?per_page=1'; // We just need one post to get the total count
            $response = wp_remote_get($url, [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($remote_domain['user'] . ':' . $remote_domain['password'])
                ],
                'sslcertificates' => 'C:\xampp\apache\crt\demo.com\server.crt'
            ]);
            if (is_wp_error($response)) {
                return 0;
            }
            $total_posts = wp_remote_retrieve_header($response, 'x-wp-total');
            return intval($total_posts);
        }
    }
    return 0;
}

function rdm_create_local_post($remote_post, $domain) {
    $post_id = wp_insert_post([
        'post_title' => $remote_post['title']['rendered'],
        'post_content' => $remote_post['content']['rendered'],
        'post_status' => 'draft',
        'post_type' => 'remote_post',
        'meta_input' => [
            '_rdm_remote_domain' => $domain,
            '_rdm_remote_post_id' => $remote_post['id']
        ]
    ]);

    return $post_id;
}

function rdm_save_post($post_id, $post, $update) {
    if ($post->post_type !== 'remote_post') {
        return;
    }

    $domain = get_post_meta($post_id, '_rdm_remote_domain', true);
    $remote_post_id = get_post_meta($post_id, '_rdm_remote_post_id', true);

    if ($domain && $remote_post_id) {
        $title = $post->post_title;
        $content = $post->post_content;

        rdm_update_remote_post($domain, $remote_post_id, $title, $content);
    }
}

function rdm_update_remote_post($domain, $post_id, $title, $content) {
    $domains = get_option('rdm_domains', []);
    foreach ($domains as $remote_domain) {
        if ($remote_domain['domain'] === $domain) {
            $url = $domain . '/wp-json/wp/v2/posts/' . $post_id;
            $body = json_encode([
                'title' => $title,
                'content' => $content
            ]);
            $response = wp_remote_post($url, [
                'method' => 'PUT',
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($remote_domain['user'] . ':' . $remote_domain['password']),
                    'Content-Type' => 'application/json'
                ],
                'body' => $body,
                'sslcertificates' => 'C:\xampp\apache\crt\demo.com\server.crt'
            ]);
            if (is_wp_error($response)) {
                return false;
            }
            return json_decode(wp_remote_retrieve_body($response), true);
        }
    }
    return false;
}

function rdm_add_meta_boxes() {
    add_meta_box('rdm_meta_box', 'Remote Post Details', 'rdm_meta_box_callback', 'remote_post', 'side', 'default');
}

function rdm_meta_box_callback($post) {
    $remote_domain = get_post_meta($post->ID, '_rdm_remote_domain', true);
    $remote_post_id = get_post_meta($post->ID, '_rdm_remote_post_id', true);
    ?>
    <p><strong>ID Domain:</strong> <?php echo esc_html($remote_domain); ?></p>
    <p><strong>ID Post:</strong> <?php echo esc_html($remote_post_id); ?></p>
    <?php
}
?>
