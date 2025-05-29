<?php
/**
 * Plugin Name: Likes Feature
 * Description: Likes and Dislike feature with Admin Panel
 * Version: 1.0.0
 * Author: Maxim Andrianov
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class WP_Likes_System {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'article_likes';
        
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_vote_article', array($this, 'handle_vote'));
        add_action('wp_ajax_nopriv_vote_article', array($this, 'handle_vote'));
        add_action('wp_ajax_get_vote_counts', array($this, 'get_vote_counts_ajax'));
        add_action('wp_ajax_nopriv_get_vote_counts', array($this, 'get_vote_counts_ajax'));
        // Optional.
        // add_filter('the_content', array($this, 'add_like_buttons_to_content'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
                
        register_activation_hook(__FILE__, array($this, 'create_table'));
    }

   
    public function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            user_ip varchar(45) NOT NULL,
            page_url varchar(500) NOT NULL,
            vote_type enum('like','dislike') NOT NULL,
            vote_time datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            -- UNIQUE KEY unique_vote (post_id, user_ip),
            KEY post_id (post_id),
            KEY user_ip (user_ip)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    private function dump($v) {
        echo '<pre>'.print_r($v, true).'</pre>';
    }

    public function enqueue_scripts() {
        if (is_home() || is_front_page()) {
            wp_enqueue_script('likes-system', plugin_dir_url(__FILE__) . 'likes-system.js', array(), '1.0.0', true);
            wp_enqueue_style('likes-system', plugin_dir_url(__FILE__) . 'likes-system.css', array(), '1.0.0');
            
            wp_localize_script('likes-system', 'likes_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('likes_nonce')
            ));
        }
    }
    
    public function render_like_buttons($post_id) {
        $likes_count = $this->get_votes_count($post_id, 'like');
        $dislikes_count = $this->get_votes_count($post_id, 'dislike');
        $likes_sum = (($likes_count - $dislikes_count) > 0) ? $likes_count - $dislikes_count : 0;
        $user_vote = $this->get_user_vote($post_id);
    
        $like_class = ($user_vote === 'like') ? 'active' : '';
        $dislike_class = ($user_vote === 'dislike') ? 'active' : '';
    
        $buttons = '<div class="likes-container" data-post-id="' . esc_attr($post_id) . '">';
        $buttons .= '<button class="like-btn ' . $like_class . '" data-vote="like">';
        $buttons .= '<span class="like-icon">👍</span> ';
        $buttons .= '<span class="like-count">' . $likes_count . '</span>';
        $buttons .= '</button>';
        $buttons .= '<span class="likes-sum">'. $likes_sum .'</span>';
        $buttons .= '<button class="dislike-btn ' . $dislike_class . '" data-vote="dislike">';
        $buttons .= '<span class="dislike-icon">👎</span> ';
        $buttons .= '<span class="dislike-count">' . $dislikes_count . '</span>';
        $buttons .= '</button>';
        $buttons .= '</div>';
    
        return $buttons;
    }
    
    public function add_like_buttons_to_content($excerpt) {
        if (is_home() || is_front_page()) {
            global $post;
            $current_post_id = get_the_ID();
            // $this->dump($current_post_id);
            if (!$current_post_id) {
                $current_post_id = $post->ID;
            }

                  
            $excerpt .= $this->render_like_buttons($current_post_id);
        }
        
        return $excerpt;
    }
    
    public function handle_vote() {
        // Проверка nonce
        if (!wp_verify_nonce($_POST['nonce'], 'likes_nonce')) {
            wp_die('Security Warning');
        }
        
        $post_id = intval($_POST['post_id']);
        $vote_type = sanitize_text_field($_POST['vote_type']);
        $user_ip = $this->get_user_ip();
        $page_url = sanitize_url($_POST['page_url']);
        
        if (!in_array($vote_type, array('like', 'dislike'))) {
            wp_die('Wrong type of vote');
        }
        
        global $wpdb;
        
        // Check if voted
        $existing_vote = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE post_id = %d AND user_ip = %s",
            $post_id, $user_ip
        ));

        // Comment this for debuggin (skip ip check basicly)
        if ($existing_vote) {
            // If vote is the same -> remove
            if ($existing_vote->vote_type === $vote_type) {
                $wpdb->delete(
                    $this->table_name,
                    array('post_id' => $post_id, 'user_ip' => $user_ip),
                    array('%d', '%s')
                );
                $action = 'removed';
            } else {
                // Update.
                $wpdb->update(
                    $this->table_name,
                    array(
                        'vote_type' => $vote_type,
                        'page_url' => $page_url,
                        'vote_time' => current_time('mysql')
                    ),
                    array('post_id' => $post_id, 'user_ip' => $user_ip),
                    array('%s', '%s', '%s'),
                    array('%d', '%s')
                );
                $action = 'updated';
            }
        } else {
            // Add
            $wpdb->insert(
                $this->table_name,
                array(
                    'post_id' => $post_id,
                    'user_ip' => $user_ip,
                    'page_url' => $page_url,
                    'vote_type' => $vote_type,
                    'vote_time' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s', '%s')
            );
            $action = 'added';
        }
        
        // Return
        $likes_count = $this->get_votes_count($post_id, 'like');
        $dislikes_count = $this->get_votes_count($post_id, 'dislike');
        $user_vote = $this->get_user_vote($post_id);
        
        wp_send_json_success(array(
            'likes_count' => $likes_count,
            'dislikes_count' => $dislikes_count,
            'user_vote' => $user_vote,
            'action' => $action
        ));
    }
    
    public function get_vote_counts_ajax() {
        if (!wp_verify_nonce($_POST['nonce'], 'likes_nonce')) {
            wp_die('Security Warning');
        }
        
        $post_id = intval($_POST['post_id']);
        
        $likes_count = $this->get_votes_count($post_id, 'like');
        $dislikes_count = $this->get_votes_count($post_id, 'dislike');
        $user_vote = $this->get_user_vote($post_id);
        
        wp_send_json_success(array(
            'likes_count' => $likes_count,
            'dislikes_count' => $dislikes_count,
            'user_vote' => $user_vote
        ));
    }
    
    public function get_votes_count($post_id, $vote_type) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE post_id = %d AND vote_type = %s",
            $post_id, $vote_type
        ));
    }
    
    private function get_user_vote($post_id) {
        global $wpdb;
        
        $user_ip = $this->get_user_ip();
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT vote_type FROM {$this->table_name} WHERE post_id = %d AND user_ip = %s",
            $post_id, $user_ip
        ));
    }
    
    private function get_user_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Statistic',
            'Likes Feature',
            'read',
            'likes-stats',
            array($this, 'admin_page'),
            'dashicons-thumbs-up',
            30
        );
    }
    
    public function admin_page() {
  
        $list_table = new Likes_Stats_Table();
        $list_table->prepare_items();
        echo '<div class="wrap">';
        echo '<h1>Statistic</h1>';
        echo '<form method="post">';
            $list_table->display();
        echo '</form>';
        echo '</div>';
    }
}

class Likes_Stats_Table extends WP_List_Table {
    
    public function __construct() {
        parent::__construct(array(
            'singular' => 'like_stat',
            'plural' => 'likes_stats',
            'ajax' => false
        ));
    }
    
    public function get_columns() {
        return array(
            'post_title' => 'Article',
            'likes_count' => 'Likes',
            'dislikes_count' => 'Dislikes',
            'total_votes' => 'Total',
            'rating' => 'Rating'
        );
    }

    public function get_sortable_columns() {
        return array(
            'likes_count'      => array('likes_count', false),
            'dislikes_count'      => array('dislikes_count', false),
            'rating'      => array('rating', false),
            'total_votes'      => array('total_votes'. false)
        );
    }
    
    public function prepare_items() {
        global $wpdb;
        
        $likes_table = $wpdb->prefix . 'article_likes';

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);

        $order_by = !empty($_GET['orderby']) ? sanitize_sql_orderby($_GET['orderby']) : 'total_votes';
        $order    = !empty($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

        $query = "
            SELECT DISTINCT
                p.ID as post_id,
                p.post_title,
                (SELECT COUNT(*) FROM {$likes_table} WHERE post_id = p.ID AND vote_type = 'like') as likes_count,
                (SELECT COUNT(*) FROM {$likes_table} WHERE post_id = p.ID AND vote_type = 'dislike') as dislikes_count,
                (SELECT COUNT(*) FROM {$likes_table} WHERE post_id = p.ID) as total_votes
            FROM {$wpdb->posts} p
            WHERE p.post_type = 'post' 
            AND p.post_status = 'publish'
            AND p.ID IN (SELECT DISTINCT post_id FROM {$likes_table})
            ORDER BY {$order_by} $order
    ";
        
        $data = $wpdb->get_results($query, ARRAY_A);
        
        // Add Rating
        foreach ($data as &$item) {
            $likes = intval($item['likes_count']);
            $dislikes = intval($item['dislikes_count']);
            $total = $likes + $dislikes;
            
            if ($total > 0) {
                $item['rating'] = round(($likes / $total) * 100, 1) . '%';
            } else {
                $item['rating'] = '0%';
            }
        }
        
        $this->items = $data;
        $this->set_pagination_args(array(
            'total_items' => count($data),
            'per_page' => 20
        ));
    }
    
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'post_title':
                return '<a href="' . get_edit_post_link($item['post_id']) . '">' . $item['post_title'] . '</a>';
            case 'likes_count':
            case 'dislikes_count':
            case 'total_votes':
            case 'rating':
                return $item[$column_name];
            default:
                return print_r($item, true);
        }
    }
}

function likes_buttons($post_id) {
    global $wp_likes_system;

    if ($wp_likes_system instanceof WP_Likes_System) {
        return $wp_likes_system->render_like_buttons($post_id);
    }

    return '';
}


$wp_likes_system = new WP_Likes_System();