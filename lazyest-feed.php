<?php
/*
Plugin Name: Lazyest Feed
Plugin URI: http://brimosoft.nl/lazyest/feed/
Description: Publish a standard media feed for your Lazyest Gallery 
Date: December 2012
Author: Marcel brinkkemper
Author URI: http://brimosoft.nl
Version: 0.2.1
License: GNU GPLv2
*/
  

/**
 * LazyestFeed
 * 
 * @package Lazyest Gallery
 * @subpackage Lazyest Feed
 * @author Marcel Brinkkemper
 * @copyright 2012 Marcel Brinkkemper
 * @version 0.2
 * @access public
 */
class LazyestFeed {
	
	/**
	 * array with all directories in the gallery
	 */
	var $directories;
	
	/**
	 * LazyestFeed::__construct()
	 * 
	 * @return void
	 */
	function __construct() {	
		$this->init();				
	}
	
	// lazyest-feed core functions
		
	/**
	 * LazyestFeed::init()
	 * 
	 * @return void
	 */
	function init() {	
		$this->filters();
	}
	
	/**
	 * LazyestFeed::filters()
	 * set filters and actions
	 *  
	 * @return void
	 */
	function filters() {
		add_filter( 'query_vars', array( &$this, 'query_vars' ) );
		add_action( 'generate_rewrite_rules', array( &$this, 'rewrite_rules') );
		add_action( 'admin_init', array( &$this, 'flush_rules' ) );
		add_action( 'template_redirect', array( &$this, 'redirect' ) );
		add_action( 'wp_head', array( &$this, 'alternate' ), 2 );
	}
	
	/**
	 * LazyestFeed::query_vars()
	 * add 'lazyestfeed' as query var in WordPress
	 * 
	 * @param array $vars
	 * @return
	 */
	function query_vars( $vars ) {
		$vars[] = 'lazyestfeed';
		return $vars;
	}
	
	/**
	 * LazyestFeed::rewrite_rules()
	 * rewrite rules to handle requests for lazyestfeed.xml and lazyestfeed/folder requests
	 * 
	 * @param array $rules
	 * @return void
	 */
	function rewrite_rules( $rules ){
		global $wp_rewrite;
		$new_rules = array( 
				'lazyestfeed.xml' => 'index.php?lazyestfeed=0',
				'lazyestfeed/(.+)' => 'index.php?lazyestfeed=' . $wp_rewrite->preg_index(1)
			);
  	$wp_rewrite->rules = $new_rules + $wp_rewrite->rules;
	}
	
	/**
	 * LazyestFeed::flush_rules()
	 * flush rules if they are not in database yet
	 * 
	 * @return void
	 */
	function flush_rules() {
    $rules = get_option( 'rewrite_rules' );
    if ( !isset( $rules['lazyestfeed.xml'] ) ) {	
    	global $wp_rewrite;
   		$wp_rewrite->flush_rules();    
 		}
  }
  
  /**
   * LazyestFeed::redirect()
   * if lazyestfeed is requested, handle this request
   * 
   * @return void
   */
  function redirect() {	
  	global $lg_gallery;
  	if ( ! isset( $lg_gallery ) )
  		return;
  		
  	$feed = get_query_var( 'lazyestfeed' );
		if ( ! strlen( $feed ) )
			return;
		if ( $this->media_feed( untrailingslashit( $feed ) ) ) {
			die();
		} else {
			$GLOBALS['wp_query']->is_404 = true;
			return;
		}				
  }
  
  /**
   * LazyestFeed::alternate()
   * places an alternate link for downloading the media feed
   * 
   * @return void
   */
  function alternate() {
  	global $lg_gallery;
  	if ( ! isset( $lg_gallery ) )
  		return; 		
 		if ( $lg_gallery->is_gallery() ) {
 			$title = get_the_title( $lg_gallery->get_option( 'gallery_id' ) ) . ' ' . __( 'Media Feed', 'lazyest-feed' );
 			echo "\n<link rel=\"alternate\" id=\"lazyest-gallery-rss\" type=\"application/rss+xml\" title=\"$title\" href=\"" . trailingslashit( home_url() ) .	"lazyestfeed.xml\" />\n";
 		}
  }
  
  /**
   * LazyestFeed::get_directories()
   * recursively walk all directories in the gallery
   * 
   * @param string $root start in this directory
   * @return array of url encoded folder names 
   */
  function get_directories( $root = '' ) {
  	global $lg_gallery;
		$directories = array();
    if ( ! isset( $lg_gallery->root ) )
    	return;
    	
  	$root = ( $root == '' ) ? $lg_gallery->root : $root;
    if ( $lg_gallery->is_dangerous( $root ) || ! file_exists( $root ) )
    	return;
		
		$base = substr( $root, strlen( $lg_gallery->root ) );	    	
    if ( $dir_handler = @opendir( $root ) ) {
      while ( false !== ( $afile = readdir( $dir_handler ) ) ) {
        if ( $lg_gallery->valid_dir( $root . $afile ) ) {
          $directories[] = lg_nice_link( $base . $afile );
          $directories = array_merge( $directories, $this->get_directories( $root . trailingslashit( $afile ) ) );
        } else {
          continue;
        }
      }
      @closedir( $dir_handler );
      return  $directories;
    } 
  }
  
  /**
   * LazyestFeed::all_directories()
   * checks for cached directories
	 * if no cache, retrieve new array of directories
	 * cache expires after 24 hours 
   * 
   * @return array of url encoded folder names 
   */
  function all_directories() {
  	$this->directories = get_transient( 'lazyestfeed_directories' ); 
  	if ( ! $this->directories ){
  		$this->directories = $this->get_directories();
  		if ( $this->directories && ( 0 < count( $this->directories ) ) ) {
  			set_transient( 'lazyestfeed_directories', $this->directories, 60*60*24 );  		
  		}	else {
  			delete_transient( 'lazyestfeed_directories' );
  			return false;
			}
 		}	
  }
  
  /**
   * LazyestFeed::media_feed()
   * outpu the gallery media feed
   * @param string $feed
   * @return bool true if requested feed exists
   */
  function media_feed( $feed ) { 
  	$this->all_directories();
  	if ( '0' == $feed ) {
  		$directory = $this->directories[0];
			$key = 0;  		
  	} else {
  		$key = array_search( $feed, $this->directories );
  		if ( false !== $key ) {
  			$directory = $feed;
  		} else {
  			return false;
  		}
  	}
 		$this->rss_header();
 		$this->rss_channel( $key );
 		$this->rss_footer();
 		return true;
  }
  
 
  /**
   * LazyestFeed::rss_header()
   * output rss header
   * 
   * @return void
   */
  function rss_header() {  	
		header( 'HTTP/1.1 200 OK', true, 200 );
		header( 'Content-Type: text/xml' );
  	echo "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\" ?>
<rss version=\"2.0\" xmlns:media=\"http://search.yahoo.com/mrss/\" xmlns:atom=\"http://www.w3.org/2005/Atom\">";
  }
  
  /**
   * LazyestFeed::rss_channel()
   * output rss channel part
   * 
   * @param int $key index of feed in directories array
   * @return void
   */
  function rss_channel( $key ) {
  	global $file, $lg_gallery;
  	$file = $this->directories[$key];
  	$path = $lg_gallery->file_decode(); 	
  	$folder = new LazyestFolder( $path );
  	$folder->open();
  	$folder->load( 'thumbs' );
 		echo "
	<channel>
		<title>" . $folder->title() . "</title>
		<description>" . $folder->description() . "</description>
		<link>" . $folder->uri( 'widget' ) . "</link>"; 
  	$this->rss_self( $key );
  	if ( 1 < count( $this->directories ) ) {
  		$this->rss_previous( $key );
  		$this->rss_next( $key );   		
		}
  	if ( count( $folder->list ) ) {
  		foreach( $folder->list as $thumb ) {
  			$this->rss_item( $thumb );
  		}
  	}
  	echo "
	</channel>";
  }
  
  /**
   * LazyestFeed::rss_item()
   * output rss item for an image
   * 
   * @param LazyestThumb $thumb
   * @return void
   */
  function rss_item( $thumb ) {
  	$on_click = $thumb->on_click( 'widget' );
  	$image = new LazyestImage( $thumb->folder );
  	$image->image = $thumb->image;
  	echo "
		<item>
			<title>" . $thumb->title() . "</title>
			<media:description>" . $thumb->description() . "</media:description>
			<link>" . $on_click['href']  . "</link>
			<guid>" . $image->uri( 'widget' ) . "</guid> 
			<media:thumbnail url=\"" . $thumb->src() . "\"/>
			<media:content url=\"" . $image->src() . "\"/>";
	echo "
		</item>";
		unset( $image );
  }
  
  /**
   * LazyestFeed::rss_self()
   * output rss link to gallery folder
   * 
   * @param mixed $key
   * @return void
   */
  function rss_self( $key ) {  	
  	$href = ( 0 == $key ) ? trailingslashit( home_url() ) . 'lazyestfeed.xml' : $this->rss_href( $key ); 
  	echo "
		<atom:link rel=\"self\" href=\"$href\" />"; 
  }
    
  /**
   * LazyestFeed::rss_previous()
   * output rss link to previous gallery folder
   * 
   * @param int $key
   * @return void
   */
  function rss_previous( $key ) {
  	$key--;
  	$key = ( $key < 0 )  ? count( $this->directories) - 1 : $key;  	
		$href = $this->rss_href( $key ); 
  	echo "
		<atom:link rel=\"previous\" href=\"$href\" />";  	
  }
  
  /**
   * LazyestFeed::rss_next()
   * output rss link to next gallery folder
   * 
   * @param integer $key
   * @return void
   */
  function rss_next( $key ) {
  	$key++;
  	$key = ( $key == count( $this->directories) ) ? 0 : $key;  	
		$href = $this->rss_href( $key ); 
  	echo "
		<atom:link rel=\"next\" href=\"$href\" />";  	
  }
  
  /**
   * LazyestFeed::rss_href()
   * the folder feed href permalink structure
   * 
   * @param mixed $key
   * @return
   */
  function rss_href( $key ) {
  	$path = $this->directories[$key];
		$query = ( get_option( 'permalink_structure' ) ) ? 'lazyestfeed/' : '?lazyestfeed=';
		return trailingslashit( home_url() ) . $query . $path;  	
  }
  
  /**
   * LazyestFeed::rss_footer()
   * output the rss footer
   * 
   * @return void
   */
  function rss_footer() {
  	echo "
</rss>  	
  	";
  }
} // LazyestFeed

$lazyest_feed = new LazyestFeed;
?>