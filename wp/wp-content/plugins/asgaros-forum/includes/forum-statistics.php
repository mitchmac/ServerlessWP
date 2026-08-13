<?php

if (!defined('ABSPATH')) {
    exit;
}

class AsgarosForumStatistics {
    private static $asgarosforum = null;

    public function __construct($asgarosForumObject) {
		self::$asgarosforum = $asgarosForumObject;
    }

    public static function showStatistics() {
        // Check if this functionality is enabled.
        if (self::$asgarosforum->options['show_statistics']) {
            $data = self::getData();
            echo '<div id="statistics">';
                echo '<div class="title-element title-element-dark">';
                    echo '<span class="title-element-icon fas fa-chart-pie"></span>';
                    echo esc_html__('Statistics', 'asgaros-forum');
                echo '</div>';
                echo '<div id="statistics-body">';
                    echo '<div id="statistics-elements">';
                        self::renderStatisticsElement(__('Topics', 'asgaros-forum'), $data->topics, 'far fa-comments');
                        self::renderStatisticsElement(__('Posts', 'asgaros-forum'), $data->posts, 'far fa-comment');

                        if (self::$asgarosforum->options['count_topic_views']) {
                            self::renderStatisticsElement(__('Views', 'asgaros-forum'), $data->views, 'far fa-eye');
                        }

                        self::renderStatisticsElement(__('Users', 'asgaros-forum'), $data->users, 'far fa-user');
                        self::$asgarosforum->online->render_statistics_element();
                        do_action('asgarosforum_statistics_custom_element');
                    echo '</div>';
                    self::$asgarosforum->online->render_online_information();
                echo '</div>';
                do_action('asgarosforum_statistics_custom_content_bottom');
                echo '<div class="clear"></div>';
            echo '</div>';
        }
    }

    public static function getData() {
        global $wpdb;

        // Initialize counters class.
        $counters         = new stdClass();
        $counters->topics = 0;
        $counters->posts  = 0;
        $counters->views  = 0;
        $counters->users  = 0;

        // Create counters query.
        $queryTopics = 'SELECT COUNT(*) FROM '.self::$asgarosforum->tables->topics;
        $queryPosts  = 'SELECT COUNT(*) FROM '.self::$asgarosforum->tables->posts;
        $queryViews  = 0;

        if (self::$asgarosforum->options['count_topic_views']) {
            $queryViews = 'SELECT SUM(views) FROM '.self::$asgarosforum->tables->topics;
        }

        $results = $wpdb->get_row("SELECT ({$queryTopics}) AS topics, ({$queryPosts}) AS posts, ({$queryViews}) AS views");

        // Count users.
        $results->users = self::$asgarosforum->count_users();

        // Fill values.
        if (!empty($results->topics)) {
            $counters->topics = $results->topics;
        }

        if (!empty($results->posts)) {
            $counters->posts = $results->posts;
        }

        if (!empty($results->views)) {
            $counters->views = $results->views;
        }

        if (!empty($results->users)) {
            $counters->users = $results->users;
        }

        return $counters;
    }

    public static function renderStatisticsElement($title, $data, $iconClass) {
        echo '<div class="statistics-element">';
            echo '<div class="element-number">';
                echo '<span class="statistics-element-icon '.esc_attr($iconClass).'"></span>';
                echo esc_html(number_format_i18n($data));
            echo '</div>';
            echo '<div class="element-name">'.esc_html($title).'</div>';
        echo '</div>';
    }
}
