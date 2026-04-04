<?php
/**
 * WordPress schema for unit tests for the SQLite database integration project.
 *
 * This is hardcoded to SQLite DDL.
 */

global $blog_tables;

$blog_tables = "
CREATE TABLE IF NOT EXISTS \"wp_users\"(
	\"ID\" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
  \"user_login\" text NOT NULL DEFAULT '' COLLATE NOCASE,
  \"user_pass\" text NOT NULL DEFAULT '' COLLATE NOCASE,
  \"user_nicename\" text NOT NULL DEFAULT '' COLLATE NOCASE,
  \"user_email\" text NOT NULL DEFAULT '' COLLATE NOCASE,
  \"user_url\" text NOT NULL DEFAULT '' COLLATE NOCASE,
  \"user_registered\" text NOT NULL DEFAULT '0000-00-00 00:00:00' COLLATE NOCASE,
  \"user_activation_key\" text NOT NULL DEFAULT '' COLLATE NOCASE,
  \"user_status\" integer NOT NULL DEFAULT '0',
  \"display_name\" text NOT NULL DEFAULT '' COLLATE NOCASE
);
CREATE TABLE IF NOT EXISTS \"wp_usermeta\"(
	\"umeta_id\" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
  \"user_id\" integer NOT NULL DEFAULT '0',
  \"meta_key\" text DEFAULT NULL COLLATE NOCASE,
  \"meta_value\" text COLLATE NOCASE
);
CREATE TABLE IF NOT EXISTS \"wp_termmeta\"(
	\"meta_id\" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
  \"term_id\" integer NOT NULL DEFAULT '0',
  \"meta_key\" text DEFAULT NULL COLLATE NOCASE,
  \"meta_value\" text COLLATE NOCASE
);
CREATE TABLE IF NOT EXISTS \"wp_terms\"(
	\"term_id\" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
  \"name\" text NOT NULL DEFAULT '' COLLATE NOCASE,
  \"slug\" text NOT NULL DEFAULT '' COLLATE NOCASE,
  \"term_group\" integer NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS \"wp_term_taxonomy\"(
	\"term_taxonomy_id\" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
  \"term_id\" integer NOT NULL DEFAULT 0,
  \"taxonomy\" text NOT NULL DEFAULT '' COLLATE NOCASE,
  \"description\" text NOT NULL COLLATE NOCASE,
  \"parent\" integer NOT NULL DEFAULT 0,
  \"count\" integer NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS \"wp_term_relationships\"(
	\"object_id\" integer NOT NULL DEFAULT 0,
  \"term_taxonomy_id\" integer NOT NULL DEFAULT 0,
  \"term_order\" integer NOT NULL DEFAULT 0,
  PRIMARY KEY(\"object_id\", \"term_taxonomy_id\")
);
CREATE TABLE IF NOT EXISTS \"wp_commentmeta\"(
	\"meta_id\" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
  \"comment_id\" integer NOT NULL DEFAULT '0',
  \"meta_key\" text DEFAULT NULL COLLATE NOCASE,
  \"meta_value\" text COLLATE NOCASE
);
CREATE TABLE IF NOT EXISTS \"wp_comments\"(
	\"comment_ID\" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
  \"comment_post_ID\" integer NOT NULL DEFAULT '0',
  \"comment_author\" text NOT NULL COLLATE NOCASE,
  \"comment_author_email\" text NOT NULL DEFAULT '' COLLATE NOCASE,
  \"comment_author_url\" text NOT NULL DEFAULT '' COLLATE NOCASE,
  \"comment_author_IP\" text NOT NULL DEFAULT '' COLLATE NOCASE,
  \"comment_date\" text NOT NULL DEFAULT '0000-00-00 00:00:00' COLLATE NOCASE,
  \"comment_date_gmt\" text NOT NULL DEFAULT '0000-00-00 00:00:00' COLLATE NOCASE,
  \"comment_content\" text NOT NULL COLLATE NOCASE,
  \"comment_karma\" integer NOT NULL DEFAULT '0',
  \"comment_approved\" text NOT NULL DEFAULT '1' COLLATE NOCASE,
  \"comment_agent\" text NOT NULL DEFAULT '' COLLATE NOCASE,
  \"comment_type\" text NOT NULL DEFAULT 'comment' COLLATE NOCASE,
  \"comment_parent\" integer NOT NULL DEFAULT '0',
  \"user_id\" integer NOT NULL DEFAULT '0'
);
CREATE TABLE IF NOT EXISTS \"wp_links\"(
	\"link_id\" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
  \"link_url\" text NOT NULL DEFAULT '' COLLATE NOCASE,
  \"link_name\" text NOT NULL DEFAULT '' COLLATE NOCASE,
  \"link_image\" text NOT NULL DEFAULT '' COLLATE NOCASE,
  \"link_target\" text NOT NULL DEFAULT '' COLLATE NOCASE,
  \"link_description\" text NOT NULL DEFAULT '' COLLATE NOCASE,
  \"link_visible\" text NOT NULL DEFAULT 'Y' COLLATE NOCASE,
  \"link_owner\" integer NOT NULL DEFAULT '1',
  \"link_rating\" integer NOT NULL DEFAULT '0',
  \"link_updated\" text NOT NULL DEFAULT '0000-00-00 00:00:00' COLLATE NOCASE,
  \"link_rel\" text NOT NULL DEFAULT '' COLLATE NOCASE,
  \"link_notes\" text NOT NULL COLLATE NOCASE,
  \"link_rss\" text NOT NULL DEFAULT '' COLLATE NOCASE
);
CREATE TABLE IF NOT EXISTS \"wp_options\"(
	\"option_id\" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
  \"option_name\" text NOT NULL DEFAULT '' COLLATE NOCASE,
  \"option_value\" text NOT NULL COLLATE NOCASE,
  \"autoload\" text NOT NULL DEFAULT 'yes' COLLATE NOCASE
);
CREATE TABLE IF NOT EXISTS \"wp_postmeta\"(
	\"meta_id\" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
  \"post_id\" integer NOT NULL DEFAULT '0',
  \"meta_key\" text DEFAULT NULL COLLATE NOCASE,
  \"meta_value\" text COLLATE NOCASE
);
CREATE TABLE IF NOT EXISTS \"wp_posts\"(
	\"ID\" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
  \"post_author\" integer NOT NULL DEFAULT '0',
  \"post_date\" text NOT NULL DEFAULT '0000-00-00 00:00:00' COLLATE NOCASE,
  \"post_date_gmt\" text NOT NULL DEFAULT '0000-00-00 00:00:00' COLLATE NOCASE,
  \"post_content\" text NOT NULL COLLATE NOCASE,
  \"post_title\" text NOT NULL COLLATE NOCASE,
  \"post_excerpt\" text NOT NULL COLLATE NOCASE,
  \"post_status\" text NOT NULL DEFAULT 'publish' COLLATE NOCASE,
  \"comment_status\" text NOT NULL DEFAULT 'open' COLLATE NOCASE,
  \"ping_status\" text NOT NULL DEFAULT 'open' COLLATE NOCASE,
  \"post_password\" text NOT NULL DEFAULT '' COLLATE NOCASE,
  \"post_name\" text NOT NULL DEFAULT '' COLLATE NOCASE,
  \"to_ping\" text NOT NULL COLLATE NOCASE,
  \"pinged\" text NOT NULL COLLATE NOCASE,
  \"post_modified\" text NOT NULL DEFAULT '0000-00-00 00:00:00' COLLATE NOCASE,
  \"post_modified_gmt\" text NOT NULL DEFAULT '0000-00-00 00:00:00' COLLATE NOCASE,
  \"post_content_filtered\" text NOT NULL COLLATE NOCASE,
  \"post_parent\" integer NOT NULL DEFAULT '0',
  \"guid\" text NOT NULL DEFAULT '' COLLATE NOCASE,
  \"menu_order\" integer NOT NULL DEFAULT '0',
  \"post_type\" text NOT NULL DEFAULT 'post' COLLATE NOCASE,
  \"post_mime_type\" text NOT NULL DEFAULT '' COLLATE NOCASE,
  \"comment_count\" integer NOT NULL DEFAULT '0'
);
CREATE INDEX \"wp_users__user_login_key\" ON \"wp_users\"(\"user_login\");
CREATE INDEX \"wp_users__user_nicename\" ON \"wp_users\"(\"user_nicename\");
CREATE INDEX \"wp_users__user_email\" ON \"wp_users\"(\"user_email\");
CREATE INDEX \"wp_usermeta__user_id\" ON \"wp_usermeta\"(\"user_id\");
CREATE INDEX \"wp_usermeta__meta_key\" ON \"wp_usermeta\"(\"meta_key\");
CREATE INDEX \"wp_termmeta__term_id\" ON \"wp_termmeta\"(\"term_id\");
CREATE INDEX \"wp_termmeta__meta_key\" ON \"wp_termmeta\"(\"meta_key\");
CREATE INDEX \"wp_terms__slug\" ON \"wp_terms\"(\"slug\");
CREATE INDEX \"wp_terms__name\" ON \"wp_terms\"(\"name\");
CREATE UNIQUE INDEX \"wp_term_taxonomy__term_id_taxonomy\" ON \"wp_term_taxonomy\"(
	\"term_id\",
	\"taxonomy\"
);
CREATE INDEX \"wp_term_taxonomy__taxonomy\" ON \"wp_term_taxonomy\"(\"taxonomy\");
CREATE INDEX \"wp_term_relationships__term_taxonomy_id\" ON \"wp_term_relationships\"(
	\"term_taxonomy_id\"
);
CREATE INDEX \"wp_commentmeta__comment_id\" ON \"wp_commentmeta\"(\"comment_id\");
CREATE INDEX \"wp_commentmeta__meta_key\" ON \"wp_commentmeta\"(\"meta_key\");
CREATE INDEX \"wp_comments__comment_post_ID\" ON \"wp_comments\"(
	\"comment_post_ID\"
);
CREATE INDEX \"wp_comments__comment_approved_date_gmt\" ON \"wp_comments\"(
	\"comment_approved\",
	\"comment_date_gmt\"
);
CREATE INDEX \"wp_comments__comment_date_gmt\" ON \"wp_comments\"(
	\"comment_date_gmt\"
);
CREATE INDEX \"wp_comments__comment_parent\" ON \"wp_comments\"(\"comment_parent\");
CREATE INDEX \"wp_comments__comment_author_email\" ON \"wp_comments\"(
	\"comment_author_email\"
);
CREATE INDEX \"wp_links__link_visible\" ON \"wp_links\"(\"link_visible\");
CREATE UNIQUE INDEX \"wp_options__option_name\" ON \"wp_options\"(\"option_name\");
CREATE INDEX \"wp_options__autoload\" ON \"wp_options\"(\"autoload\");
CREATE INDEX \"wp_postmeta__post_id\" ON \"wp_postmeta\"(\"post_id\");
CREATE INDEX \"wp_postmeta__meta_key\" ON \"wp_postmeta\"(\"meta_key\");
CREATE INDEX \"wp_posts__post_name\" ON \"wp_posts\"(\"post_name\");
CREATE INDEX \"wp_posts__type_status_date\" ON \"wp_posts\"(
	\"post_type\",
	\"post_status\",
	\"post_date\",
	\"ID\"
);
CREATE INDEX \"wp_posts__post_parent\" ON \"wp_posts\"(\"post_parent\");
CREATE INDEX \"wp_posts__post_author\" ON \"wp_posts\"(\"post_author\");
";
