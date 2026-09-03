<?php
/**
 * SQL read/write classification.
 *
 * The classifier decides which scope a statement needs. Getting it wrong in
 * the permissive direction means a db:read key executes a DELETE, skipping the
 * approval queue and the pre-write snapshot — so the bar is not "classifies
 * common statements correctly", it is "cannot be talked into calling a
 * mutation a read."
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

use Bridgistic\Security\SqlClassifier;

bridgistic_suite( 'sql-classifier' );

/** @param string $sql Statement under test. */
function classify_class( string $sql ): string {
	return SqlClassifier::classify( $sql )['class'];
}

// ---- 1. Plain reads --------------------------------------------------------

$reads = array(
	'SELECT * FROM wp_posts',
	'  select id from wp_users  ',
	'SELECT COUNT(*) FROM wp_options WHERE autoload = "yes"',
	'SHOW TABLES',
	'SHOW CREATE TABLE wp_posts',
	'EXPLAIN SELECT * FROM wp_posts',
	'DESCRIBE wp_posts',
	'DESC wp_posts',
	'(SELECT 1) UNION (SELECT 2)',
	'SELECT * FROM wp_posts WHERE post_title = "DELETE FROM wp_users"',
	"SELECT * FROM wp_posts WHERE post_content = 'DROP TABLE wp_users'",
	'SELECT * FROM wp_posts FOR UPDATE',
	'WITH recent AS (SELECT * FROM wp_posts LIMIT 10) SELECT * FROM recent',
	'SELECT * FROM wp_posts;',
);
foreach ( $reads as $sql ) {
	check_equals( SqlClassifier::READ, classify_class( $sql ), 'classified as a read: ' . substr( $sql, 0, 60 ) );
}

// A keyword inside a string literal must not change classification — a post
// whose title is "DELETE FROM wp_users" is ordinary content.
check_equals( SqlClassifier::READ, classify_class( 'SELECT * FROM wp_posts WHERE post_title = "DROP TABLE x; --"' ), 'a keyword inside a quoted literal does not reclassify a read' );

// ---- 2. Plain writes -------------------------------------------------------

$writes = array(
	'UPDATE wp_posts SET post_status = "draft"',
	'DELETE FROM wp_posts WHERE ID = 1',
	'INSERT INTO wp_options (option_name) VALUES ("x")',
	'REPLACE INTO wp_options (option_name) VALUES ("x")',
	'DROP TABLE wp_posts',
	'TRUNCATE TABLE wp_posts',
	'ALTER TABLE wp_posts ADD COLUMN x INT',
	'CREATE TABLE t (id INT)',
	'RENAME TABLE a TO b',
	'GRANT ALL ON *.* TO "x"@"localhost"',
	'SET GLOBAL general_log = 1',
	'FLUSH PRIVILEGES',
	'CALL some_procedure()',
);
foreach ( $writes as $sql ) {
	check_equals( SqlClassifier::WRITE, classify_class( $sql ), 'classified as a write: ' . substr( $sql, 0, 60 ) );
}

// ---- 3. Mutating CTEs — the case a prefix regex gets wrong ------------------

// MySQL 8 allows a CTE to prefix UPDATE/DELETE. Reading only the leading
// keyword sees WITH, calls it a read, and hands a destructive statement the
// db:read scope. This is the highest-value assertion in the file.
$cte_writes = array(
	'WITH t AS (SELECT ID FROM wp_posts) DELETE FROM wp_posts WHERE ID IN (SELECT ID FROM t)',
	'WITH t AS (SELECT ID FROM wp_posts) UPDATE wp_posts SET post_status = "trash"',
	'with old as (select id from wp_users where id < 5) delete from wp_users where id in (select id from old)',
	'WITH a AS (SELECT 1), b AS (SELECT 2) DELETE FROM wp_posts',
);
foreach ( $cte_writes as $sql ) {
	check_equals( SqlClassifier::WRITE, classify_class( $sql ), 'a CTE feeding a mutation is a write: ' . substr( $sql, 0, 60 ) );
}

// A DELETE appearing inside a CTE subquery must not confuse the walker into
// misreading a genuinely read-only statement.
check_equals(
	SqlClassifier::READ,
	classify_class( 'WITH t AS (SELECT * FROM wp_posts WHERE post_title LIKE "%DELETE%") SELECT * FROM t' ),
	'a mutation-looking string inside a CTE body does not make a SELECT a write'
);

// ---- 4. Stacked statements --------------------------------------------------

$stacked = array(
	'SELECT 1; DROP TABLE wp_posts',
	'SELECT 1;DELETE FROM wp_users',
	'SHOW TABLES; UPDATE wp_options SET option_value = "x"',
	'SELECT 1 ; SELECT 2',
);
foreach ( $stacked as $sql ) {
	check_equals( SqlClassifier::FORBIDDEN, classify_class( $sql ), 'stacked statements are refused: ' . substr( $sql, 0, 50 ) );
}

// A single trailing semicolon is how people paste SQL and is not stacking.
check_equals( SqlClassifier::READ, classify_class( 'SELECT 1;   ' ), 'a lone trailing semicolon is not treated as stacking' );
check_equals( SqlClassifier::READ, classify_class( "SELECT 1;\n" ), 'a trailing semicolon and newline is not treated as stacking' );

// A semicolon inside a literal is data, not a separator.
check_equals( SqlClassifier::READ, classify_class( 'SELECT * FROM wp_posts WHERE post_title = "a; b"' ), 'a semicolon inside a string literal is not a statement separator' );

// ---- 5. Comment-obfuscated statements ---------------------------------------

// A leading comment moves the real keyword off position zero.
check_equals( SqlClassifier::WRITE, classify_class( '/* harmless */ DELETE FROM wp_posts' ), 'a block comment before DELETE does not hide it' );
check_equals( SqlClassifier::WRITE, classify_class( "-- note\nDELETE FROM wp_posts" ), 'a line comment before DELETE does not hide it' );
check_equals( SqlClassifier::WRITE, classify_class( "# note\nUPDATE wp_posts SET x = 1" ), 'a hash comment before UPDATE does not hide it' );
check_equals( SqlClassifier::READ, classify_class( '/* comment */ SELECT 1' ), 'a comment before SELECT still classifies as a read' );

// A comment sitting between the CTE list and the statement it feeds is the
// natural way to try to hide the mutation from a classifier that only looks
// at the character immediately after the closing parenthesis.
check_equals(
	SqlClassifier::WRITE,
	classify_class( 'WITH t AS (SELECT 1) /* nothing to see */ DELETE FROM wp_posts' ),
	'a comment between a CTE and its mutation does not hide the mutation'
);
check_equals(
	SqlClassifier::WRITE,
	classify_class( "WITH t AS (SELECT 1) -- note\n UPDATE wp_posts SET x = 1" ),
	'a line comment between a CTE and its mutation does not hide the mutation'
);

// MySQL executable comments run their contents on a real server, so they must
// not be treated as inert.
check_equals( SqlClassifier::WRITE, classify_class( '/*!50000 DELETE */ FROM wp_posts' ), 'a MySQL executable comment does not hide a mutation' );

// ---- 6. File-access SQL is refused outright ---------------------------------

$file_sql = array(
	'SELECT * FROM wp_users INTO OUTFILE "/tmp/x"',
	'SELECT * INTO DUMPFILE "/tmp/x" FROM wp_users',
	'SELECT LOAD_FILE("/etc/passwd")',
	'LOAD DATA INFILE "/etc/passwd" INTO TABLE t',
	'select load_file("/etc/passwd")',
);
foreach ( $file_sql as $sql ) {
	check_equals( SqlClassifier::FORBIDDEN, classify_class( $sql ), 'file-access SQL is refused: ' . substr( $sql, 0, 50 ) );
}

// ---- 7. Unknown statements fail closed --------------------------------------

check_equals( SqlClassifier::WRITE, classify_class( 'FROBNICATE wp_posts' ), 'an unrecognised statement is treated as a write, not a read' );
check( SqlClassifier::classify( 'FROBNICATE wp_posts' )['destructive'], 'an unrecognised statement is treated as destructive' );

check_equals( SqlClassifier::FORBIDDEN, classify_class( '' ), 'an empty statement is refused' );
check_equals( SqlClassifier::FORBIDDEN, classify_class( '   ' ), 'a whitespace-only statement is refused' );
check_equals( SqlClassifier::FORBIDDEN, classify_class( '/* only a comment */' ), 'a comment-only statement is refused' );

// ---- 8. Destructiveness drives the snapshot requirement ---------------------

foreach ( array( 'UPDATE wp_posts SET x = 1', 'DELETE FROM wp_posts', 'DROP TABLE t', 'TRUNCATE TABLE t', 'ALTER TABLE t ADD x INT' ) as $sql ) {
	check( SqlClassifier::classify( $sql )['destructive'], 'destroys prior state, so a snapshot is required: ' . substr( $sql, 0, 40 ) );
}
foreach ( array( 'INSERT INTO t (a) VALUES (1)', 'CREATE TABLE t (id INT)' ) as $sql ) {
	check( ! SqlClassifier::classify( $sql )['destructive'], 'adds without destroying, so no prior state is lost: ' . substr( $sql, 0, 40 ) );
}

// ---- 9. Every verdict explains itself ---------------------------------------

foreach ( array( 'SELECT 1', 'DELETE FROM t', 'SELECT 1; DROP TABLE t', 'SELECT LOAD_FILE("/x")' ) as $sql ) {
	$verdict = SqlClassifier::classify( $sql );
	check( '' !== $verdict['reason'], 'the verdict carries a reason the caller can surface: ' . substr( $sql, 0, 40 ) );
}

// ---- 10. Skeletonisation keeps statement shape ------------------------------

$skeleton = SqlClassifier::skeleton( "SELECT 'secret value' FROM wp_posts -- trailing\n" );
check( false === strpos( $skeleton, 'secret value' ), 'literal contents are removed from the skeleton' );
check( false !== stripos( $skeleton, 'SELECT' ), 'keywords survive skeletonisation' );
check( false === strpos( $skeleton, 'trailing' ), 'comment text is removed from the skeleton' );
