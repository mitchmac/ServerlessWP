<?php

require_once __DIR__ . '/../../wp-includes/parser/class-wp-parser-node.php';

use PHPUnit\Framework\TestCase;

class WP_Parser_Node_Tests extends TestCase {
	public function testEmptyChildren(): void {
		$node = new WP_Parser_Node( 1, 'root' );

		$this->assertFalse( $node->has_child() );
		$this->assertFalse( $node->has_child_node() );
		$this->assertFalse( $node->has_child_token() );

		$this->assertNull( $node->get_first_child() );
		$this->assertNull( $node->get_first_child_node() );
		$this->assertNull( $node->get_first_child_node( 'root' ) );
		$this->assertNull( $node->get_first_child_token() );
		$this->assertNull( $node->get_first_child_token( 1 ) );

		$this->assertNull( $node->get_first_descendant_node() );
		$this->assertNull( $node->get_first_descendant_token() );

		$this->assertEmpty( $node->get_children() );
		$this->assertEmpty( $node->get_child_nodes() );
		$this->assertEmpty( $node->get_child_nodes( 'root' ) );
		$this->assertEmpty( $node->get_child_tokens() );
		$this->assertEmpty( $node->get_child_tokens( 1 ) );

		$this->assertEmpty( $node->get_descendants() );
		$this->assertEmpty( $node->get_descendant_nodes() );
		$this->assertEmpty( $node->get_descendant_nodes( 'root' ) );
		$this->assertEmpty( $node->get_descendant_tokens() );
		$this->assertEmpty( $node->get_descendant_tokens( 1 ) );
	}

	public function testNodeTree(): void {
		$input = 'SELECT 1 + 2, 2';

		// Prepare nodes and tokens.
		$root      = new WP_Parser_Node( 1, 'root' );
		$n_keyword = new WP_Parser_Node( 2, 'keyword' );
		$n_expr_a  = new WP_Parser_Node( 3, 'expr' );
		$n_expr_b  = new WP_Parser_Node( 3, 'expr' );
		$n_expr_c  = new WP_Parser_Node( 3, 'expr' );
		$t_select  = new WP_Parser_Token( 100, 0, 6, $input );
		$t_comma   = new WP_Parser_Token( 200, 12, 1, $input );
		$t_plus    = new WP_Parser_Token( 300, 9, 1, $input );
		$t_one     = new WP_Parser_Token( 400, 7, 1, $input );
		$t_two_a   = new WP_Parser_Token( 400, 11, 1, $input );
		$t_two_b   = new WP_Parser_Token( 400, 14, 1, $input );
		$t_eof     = new WP_Parser_Token( 500, 15, 0, $input );

		// Prepare a tree.
		//
		// A simplified testing tree for an input like "SELECT 1 + 2, 2".
		//
		// root
		//   |- keyword
		//   |    |- "SELECT"
		//   |- expr [a]
		//   |    |- "1"
		//   |    |- "+"
		//   |    |- expr [c]
		//   |    |    |- "2" [b]
		//   |- ","
		//   |- expr [b]
		//   |    |- "2" [a]
		//   |- EOF
		$root->append_child( $n_keyword );
		$root->append_child( $n_expr_a );
		$root->append_child( $t_comma );
		$root->append_child( $n_expr_b );
		$root->append_child( $t_eof );

		$n_keyword->append_child( $t_select );
		$n_expr_a->append_child( $t_one );
		$n_expr_a->append_child( $t_plus );
		$n_expr_a->append_child( $n_expr_c );
		$n_expr_b->append_child( $t_two_a );
		$n_expr_c->append_child( $t_two_b );

		// Test "has" methods.
		$this->assertTrue( $root->has_child() );
		$this->assertTrue( $root->has_child_node() );
		$this->assertTrue( $root->has_child_token() );

		// Test single child methods.
		$this->assertSame( $n_keyword, $root->get_first_child() );
		$this->assertSame( $n_keyword, $root->get_first_child_node() );
		$this->assertSame( $n_keyword, $root->get_first_child_node( 'keyword' ) );
		$this->assertSame( $n_expr_a, $root->get_first_child_node( 'expr' ) );
		$this->assertSame( $t_comma, $root->get_first_child_token() );
		$this->assertSame( $t_comma, $root->get_first_child_token( 200 ) );
		$this->assertNull( $root->get_first_child_token( 100 ) );

		// Test multiple children methods.
		$this->assertSame( array( $n_keyword, $n_expr_a, $t_comma, $n_expr_b, $t_eof ), $root->get_children() );
		$this->assertSame( array( $n_keyword, $n_expr_a, $n_expr_b ), $root->get_child_nodes() );
		$this->assertSame( array( $n_expr_a, $n_expr_b ), $root->get_child_nodes( 'expr' ) );
		$this->assertSame( array(), $root->get_child_nodes( 'root' ) );
		$this->assertSame( array( $t_comma, $t_eof ), $root->get_child_tokens() );
		$this->assertSame( array( $t_comma ), $root->get_child_tokens( 200 ) );
		$this->assertSame( array(), $root->get_child_tokens( 100 ) );

		// Test single descendant methods.
		$this->assertSame( $n_keyword, $root->get_first_descendant_node() );
		$this->assertSame( $n_expr_a, $root->get_first_descendant_node( 'expr' ) );
		$this->assertSame( null, $root->get_first_descendant_node( 'root' ) );
		$this->assertSame( $t_select, $root->get_first_descendant_token() );
		$this->assertSame( $t_one, $root->get_first_descendant_token( 400 ) );
		$this->assertSame( null, $root->get_first_descendant_token( 123 ) );

		// Test multiple descendant methods.
		$this->assertSame(
			array( $n_keyword, $t_select, $n_expr_a, $t_one, $t_plus, $n_expr_c, $t_two_b, $t_comma, $n_expr_b, $t_two_a, $t_eof ),
			$root->get_descendants()
		);
		$this->assertSame(
			array( $n_keyword, $n_expr_a, $n_expr_c, $n_expr_b ),
			$root->get_descendant_nodes()
		);
		$this->assertSame(
			array( $n_expr_a, $n_expr_c, $n_expr_b ),
			$root->get_descendant_nodes( 'expr' )
		);
		$this->assertSame(
			array(),
			$root->get_descendant_nodes( 'root' )
		);
		$this->assertSame(
			array( $t_select, $t_one, $t_plus, $t_two_b, $t_comma, $t_two_a, $t_eof ),
			$root->get_descendant_tokens()
		);
		$this->assertSame(
			array( $t_one, $t_two_b, $t_two_a ),
			$root->get_descendant_tokens( 400 )
		);
		$this->assertSame(
			array(),
			$root->get_descendant_tokens( 123 )
		);
	}
}
