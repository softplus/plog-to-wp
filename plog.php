<?php
/**
	Add These Functions to make our lives easier
**/
if(!function_exists('get_catbynicename'))
{
	function get_catbynicename($category_nicename) 
	{
	global $wpdb;
	
	$cat_id -= 0; 	// force numeric
	$name = $wpdb->get_var('SELECT cat_ID FROM '.$wpdb->categories.' WHERE category_nicename="'.$category_nicename.'"');
	
	return $name;
	}
}

if(!function_exists('get_comment_count'))
{
	function get_comment_count($post_ID)
	{
		global $wpdb;
		return $wpdb->get_var('SELECT count(*) FROM '.$wpdb->comments.' WHERE comment_post_ID = '.$post_ID);
	}
}


// comment_exists in admin-db.php has a bug in version 2.0 and 2.0.1, so use this method instead
if(!function_exists('my_comment_exists'))
{
	function my_comment_exists($comment_author, $comment_date) {
		global $wpdb;
	
		return $wpdb->get_var("SELECT comment_ID FROM $wpdb->comments
				WHERE comment_author = '$comment_author' AND comment_date = '$comment_date'");
	}
}

if(!function_exists('link_cat_exists'))
{
	function link_cat_exists($name)
	{
		global $wpdb;
		return $wpdb->get_var('SELECT cat_id FROM '.$wpdb->linkcategories.' WHERE cat_name = "'.$name.'"');
	}
}

if(!function_exists('link_exists'))
{
	function link_exists($url)
	{
		global $wpdb;
		return $wpdb->get_var('SELECT link_id FROM '.$wpdb->links.' WHERE link_url = "'.$url.'"');
	}
}

if(!function_exists('wp_insert_link_cat'))
{
	function wp_insert_link_cat($linkcatdata) {
		global $wpdb, $current_user;
		
		extract($linkcatdata);
	
		$update = false;
		if ( !empty($cat_id) )
			$update = true;
	
		if ( empty($auto_toggle) )
			$auto_toggle = 'Y';	
	
		if ( empty($show_images) )
			$show_images = 'N';	
	
		if ( empty($show_description) )
			$show_description = 'Y';
			
		if ( empty($show_rating) )
			$show_rating = 'Y';
	
		if ( empty($show_updated) )
			$show_updated = 'Y';
			
		if ( empty($sort_order) )
			$sort_order = 'name';
			
		if ( empty($sort_desc) )
			$sort_desc = 'N';
			
		if ( empty($text_before_link) )
			$text_before_link = '<li>';
			
		if ( empty($text_after_link) )
			$text_after_link = '<br/>';
			
		if ( empty($text_after_all) )
			$text_after_all = '</li>';
			
		if ( empty($list_limit) )
			$list_limit = '-1';
	
		if ( $update ) {
			$wpdb->query("UPDATE $wpdb->linkcategories SET cat_id = '$cat_id',
					cat_name = '$cat_name', auto_toggle = '$auto_toggle',
					show_images = '$show_images', show_description = '$show_description',
					show_rating = '$show_rating', show_updated = '$show_updated',
					sort_order = '$sort_order', sort_desc = '$sort_desc',
					text_before_link = '$text_before_link', text_after_link = '$text_after_link',
					text_after_all = '$text_after_all', list_limit = '$list_limit'
				WHERE cat_id = '$cat_id'");
		} else {
			$wpdb->query("INSERT INTO $wpdb->linkcategories (cat_name, auto_toggle, show_images, show_description, show_rating, show_updated, sort_order, sort_desc, text_before_link, text_after_link, text_after_all, list_limit) VALUES('$cat_name','$auto_toggle', '$show_images', '$show_description', '$show_rating', '$show_updated', '$sort_order', '$sort_desc', '$text_before_link', '$text_after_link', '$text_after_all', '$list_limit')");
			$cat_id = $wpdb->insert_id;
		}
	
		return $cat_id;
	}
}

/**
	The Main Importer Class
**/
class Plog_Import {

	function header() 
	{
		echo '<div class="wrap">';
		echo '<h2>'.__('Import pLog').'</h2>';
		echo '<p>'.__('Steps may take a few minutes depending on the size of your database. Please be patient.').'</p>';
	}

	function footer() 
	{
		echo '</div>';
	}
	
	function greet() 
	{
		echo '<p>'.__('Howdy! This importer allows you to extract posts from any pLog 1.0.1 installation into your blog. This has not been tested on previous versions of pLog.').'</p>';
		echo '<p>'.__('Your pLog Configuration settings are as follows:').'</p>';
		echo '<form action="admin.php?import=plog&amp;step=1" method="post">';
		$this->db_form();
		echo '<input type="submit" name="submit" value="Import Categories" />';
		echo '</form>';
	}

	function get_plog_cats() 
	{
		global $wpdb;
		// General Housekeeping
		$plogdb = new wpdb(get_option('ploguser'), get_option('plogpass'), get_option('plogname'), get_option('ploghost'));
		set_magic_quotes_runtime(0);
		$prefix = get_option('plogpre');
		
		// Get Categories
		return $plogdb->get_results('SELECT 
										id,
										name,
										description
							   		 FROM '.$prefix.'articles_categories', 
									 ARRAY_A);
	}
	
	function get_plog_users()
	{
		global $wpdb;
		// General Housekeeping
		$plogdb = new wpdb(get_option('ploguser'), get_option('plogpass'), get_option('plogname'), get_option('ploghost'));
		set_magic_quotes_runtime(0);
		$prefix = get_option('plogpre');
		
		// Get Users
		return $plogdb->get_results('SELECT
										id,
										user,
										full_name,
										email,
										properties
							   		FROM '.$prefix.'users', ARRAY_A);
	}
	
	function get_plog_posts()
	{
		// General Housekeeping
		$plogdb = new wpdb(get_option('ploguser'), get_option('plogpass'), get_option('plogname'), get_option('ploghost'));
		set_magic_quotes_runtime(0);
		$prefix = get_option('plogpre');
		
		// Get Posts
		return $plogdb->get_results('SELECT 
										a.id,
										a.date,
										a.user_id,
										at.topic,
										at.text,
										category_id,
										a.properties,
										a.status,
										a.slug,
										at.mangled_topic
							   		FROM '.$prefix.'articles AS a, '.$prefix.'articles_text AS at
							   		WHERE a.id = at.article_id', ARRAY_A);
	}
	
	function get_plog_post_categories($article_id='') 
	{
		global $wpdb;
		// General Housekeeping
		$plogdb = new wpdb(get_option('ploguser'), get_option('plogpass'), get_option('plogname'), get_option('ploghost'));
		set_magic_quotes_runtime(0);
		$prefix = get_option('plogpre');
		
		// Get Categories
		return $plogdb->get_results('SELECT
										category_id
									FROM '.$prefix.'article_categories_link
									WHERE article_id = '.$article_id, 
									 ARRAY_A);
	}
	
	function get_plog_comment_count($article_id='')
	{
		global $wpdb;
		// General Housekeeping
		$plogdb = new wpdb(get_option('ploguser'), get_option('plogpass'), get_option('plogname'), get_option('ploghost'));
		set_magic_quotes_runtime(0);
		$prefix = get_option('plogpre');
		
		// Get Comments
		return $plogdb->get_results('SELECT COUNT(*)
									FROM '.$prefix.'articles_comments 
									WHERE article_id = '.$article_id, ARRAY_A);
	}
	
	function get_plog_comments()
	{
		global $wpdb;
		// General Housekeeping
		$plogdb = new wpdb(get_option('ploguser'), get_option('plogpass'), get_option('plogname'), get_option('ploghost'));
		set_magic_quotes_runtime(0);
		$prefix = get_option('plogpre');
		
		// Get Comments
		return $plogdb->get_results('SELECT
										id,
										article_id,
										text,
										topic,
										date,
										user_email,
										user_url,
										user_name,
										client_ip,
										status
									FROM '.$prefix.'articles_comments', ARRAY_A);
	}
	
	function get_plog_link_cats() 
	{
		global $wpdb;
		// General Housekeeping
		$plogdb = new wpdb(get_option('ploguser'), get_option('plogpass'), get_option('plogname'), get_option('ploghost'));
		set_magic_quotes_runtime(0);
		$prefix = get_option('plogpre');
		
		// Get Categories
		return $plogdb->get_results('SELECT
										id,
										name
							   		 FROM '.$prefix.'mylinks_categories', 
									 ARRAY_A);
	}
	
	function get_plog_links()
	{
		//General Housekeeping
		$plogdb = new wpdb(get_option('ploguser'), get_option('plogpass'), get_option('plogname'), get_option('ploghost'));
		set_magic_quotes_runtime(0);
		$prefix = get_option('plogpre');
		
		return $plogdb->get_results('SELECT 
										id,
										date,
										category_id,
										url,
										name,
										description,
										rss_feed
									  FROM '.$prefix.'mylinks', 
									  ARRAY_A);						  
	}
	
	function cat2wp($categories='') 
	{
		// General Housekeeping
		global $wpdb;
		$count = 0;
		$plogcat2wpcat = array();
		// Do the Magic
		if(is_array($categories))
		{
			echo '<p>'.__('Importing Categories...').'<br /><br /></p>';
			foreach ($categories as $category) 
			{
				$count++;
				extract($category);
				
				
				// Make Nice Variables
				$name 			= $wpdb->escape($name);
				$description 	= $wpdb->escape($description);
				$nicename		= str_replace(' ', '-', strtolower(trim($name)));
				
				if($cinfo = category_exists($name))
				{
					$ret_id = wp_insert_category(array(
											'cat_ID' 				=> $cinfo,
											'cat_name' 				=> $name,
											'category_nicename' 	=> $nicename,
											'category_description' 	=> $description)
											);
				}
				else
				{
					$ret_id = wp_insert_category(array(
											'cat_name' 				=> $name,
											'category_nicename' 	=> $nicename,
											'category_description' 	=> $description)
											);
				}
				$plogcat2wpcat[$id] = $ret_id;
			}
			
			// Store category translation for future use
			add_option('plogcat2wpcat',$plogcat2wpcat);
			echo '<p>'.sprintf(__('Done! <strong>%1$s</strong> categories imported.'), $count).'<br /><br /></p>';
			return true;
		}
		echo __('No Categories to Import!');
		return false;
	}
	 
	function users2wp($users='')
	{
		// General Housekeeping
		global $wpdb;
		$count = 0;
		$plogid2wpid = array();
		
		// Midnight Mojo
		if(is_array($users))
		{
			echo '<p>'.__('Importing Users...').'<br /><br /></p>';
			foreach($users as $user)
			{
				$count++;
				extract($user);
				
				// Make Nice Variables
				$user 		= $wpdb->escape($user);
				$full_name 	= strtolower($wpdb->escape($full_name));
				$password	= md5('password456');
				
				if($uinfo = get_userdatabylogin($user))
				{
					
					$ret_id = wp_insert_user(array(
								'ID'			=> $uinfo->ID,
								'user_login'	=> $user,
								'user_pass'		=> $password,
								'user_nicename'	=> $full_name,
								'user_email'	=> $email,
								'user_url'		=> 'http://',
								'display_name'	=> $full_name)
								);
				}
				else 
				{
					$ret_id = wp_insert_user(array(
								'user_login'	=> $user,
								'user_pass'		=> $password,
								'user_nicename'	=> $full_name,
								'user_email'	=> $email,
								'user_url'		=> 'http://',
								'display_name'	=> $full_name)
								);
				}
				$plogid2wpid[$user_id] = $ret_id;
				
				// pLog-to-WordPress permissions translation
				$user = new WP_User($ret_id);
				
				if ('s:0:"";' == $properties)
				{
					$user->set_role('administrator');
					update_usermeta( $ret_id, 'wp_user_level', 10 );
				}
				else
				{
					$user->set_role('editor');
					update_usermeta( $ret_id, 'wp_user_level', 9 );
				}
			}// End foreach($users as $user)
			
			echo '<p>'.sprintf(__('Done! <strong>%1$s</strong> users imported.'), $count).'<br /><br /></p>';
			return true;
		}// End if(is_array($users)
		
		echo __('No Users to Import!');
		return false;
		
	}// End function user2wp()
	
	function posts2wp($posts='')
	{
		// Extend execution time limit
		set_time_limit(900);
		
		// General Housekeeping
		global $wpdb;
		$count = 0;
		$plogposts2wpposts = array();
		
		// Get category translation array
		$plogcat2wpcat = get_option('plogcat2wpcat');
		
		// Set pLog-to-WordPress status translation
		$stattrans = array(1 => 'publish', 2 => 'draft');

		// Do the Magic
		if(is_array($posts))
		{
			echo '<p>'.__('Importing Posts...').'<br /><br /></p>';
			foreach($posts as $post)
			{
				$count++;
				extract($post);
				
				$userdata	= get_userdatabylogin( $user_id );
				$uinfo 		= ( $userdata ) ? $userdata : 1;
				$authorid 	= ( is_object( $uinfo ) ) ? $uinfo->ID : $uinfo ;

				$topic 			= $wpdb->escape($topic);
				$text 			= str_replace(array('[@more@]', "\r\n"), '', $wpdb->escape($text));
				$post_name		= str_replace(' ', '-', strtolower($topic));
				$post_status 	= $stattrans[$status];
				$comment_status = strpos($properties, 'comments_enabled') !== false ? 'open' : 'closed';
				
				$comments_count = $this->get_plog_comment_count($id);
				
				// Import Post data into WordPress
				
				if($pinfo = post_exists($topic,$text))
				{
					$ret_id = wp_insert_post(array(
							'ID'				=> $pinfo,
							'post_date'			=> $date,
							'post_date_gmt'		=> $date,
							'post_author'		=> $authorid,
							'post_modified'		=> $date,
							'post_modified_gmt' => $date,
							'post_title'		=> $topic,
							'post_content'		=> $text,
							'post_status'		=> $post_status,
							'comment_status'	=> $comment_status,
							'post_name'			=> $post_name,
							'comment_count'		=> $comments_count)
							);
				}
				else 
				{
					$ret_id = wp_insert_post(array(
							'post_date'			=> $date,
							'post_date_gmt'		=> $date,
							'post_author'		=> $authorid,
							'post_modified'		=> $date,
							'post_modified_gmt' => $date,
							'post_title'		=> $topic,
							'post_content'		=> $text,
							'post_status'		=> $post_status,
							'comment_status'	=> $comment_status,
							'post_name'			=> $post_name,
							'comment_count'		=> $comments_count)
							);
				}
				$plogposts2wpposts[$id] = $ret_id;
				
				// Make Post-to-Category associations
				$plog_article_categories 	= $this->get_plog_post_categories($id);
				
				$cats = array();
				
				if (is_array($plog_article_categories))
				{
					for ($i = 0, $count_art_cats = count($plog_article_categories); $i < $count_art_cats; $i++)
					{
						$cats[$i] = $plogcat2wpcat[$plog_article_categories[$i]['category_id']];
					}
					
					foreach ($plog_article_categories as $plog_article_category)
					{
						$cats[] = $plogcat2wpcat[$plog_article_category['category_id']];
					}
				}

				if(!empty($cats)) { wp_set_post_cats('', $ret_id, $cats); }
			}
		}
		// Store ID translation for later use
		add_option('plogposts2wpposts',$plogposts2wpposts);
		
		echo '<p>'.sprintf(__('Done! <strong>%1$s</strong> posts imported.'), $count).'<br /><br /></p>';
		return true;	
	}
				 
	function comments2wp($comments='')
	{
		// General Housekeeping
		global $wpdb;
		$count = 0;
		$plogcm2wpcm = array();
		$postarr = get_option('plogposts2wpposts');
		
		// Magic Mojo
		if(is_array($comments))
		{
			echo '<p>'.__('Importing Comments...').'<br /><br /></p>';
			foreach($comments as $comment)
			{
				$count++;
				extract($comment);
										
				// WordPressify Data
				$comment_post_ID 	= $postarr[$article_id];
				$name 				= $wpdb->escape($user_name);
				$email 				= $wpdb->escape($user_email);
				$web 				= $wpdb->escape($user_url);
				$message 			= $wpdb->escape($text);
				$comment_approved 	= (0 == $status) ? 1 : 0;
				
				if($cinfo = my_comment_exists($name, $date))
				{
					// Update comments
					$ret_id = wp_update_comment(array(
							'comment_ID'			=> $cinfo,
							'comment_post_ID'		=> $comment_post_ID,
							'comment_author'		=> $name,
							'comment_author_email'	=> $email,
							'comment_author_url'	=> $web,
							'comment_author_IP'		=> $client_ip,
							'comment_date'			=> $date,
							'comment_content'		=> $message,
							'comment_approved'		=> $comment_approved)
							);
				}
				else 
				{
					// Insert comments
					$ret_id = wp_insert_comment(array(
							'comment_post_ID'		=> $comment_post_ID,
							'comment_author'		=> $name,
							'comment_author_email'	=> $email,
							'comment_author_url'	=> $web,
							'comment_author_IP'		=> $client_ip,
							'comment_date'			=> $date,
							'comment_content'		=> $message,
							'comment_approved'		=> $comment_approved)
							);
				}
				$plogcm2wpcm[$id] = $ret_id;
			}
			// Store Comment ID translation for future use
			add_option('plogcm2wpcm', $plogcm2wpcm);						
			
			echo '<p>'.sprintf(__('Done! <strong>%1$s</strong> comments imported.'), $count).'<br /><br /></p>';
			return true;
		}
		echo __('No Comments to Import!');
		return false;
	}
	
	function linkcats2wp($linkcategories='') 
	{
		// General Housekeeping
		global $wpdb;
		$count = 0;
		$ploglinkcat2wplinkcat = array();
		// Do the Magic
		if(is_array($linkcategories))
		{
			echo '<p>'.__('Importing Link Categories...').'<br /><br /></p>';
			foreach ($linkcategories as $linkcategory) 
			{
				$count++;
				extract($linkcategory);
				
				
				// Make Nice Variables
				$name 			= $wpdb->escape($name);
				
				if($cinfo = link_cat_exists($name))
				{
					$ret_id = wp_insert_link_cat(array(
											'cat_id' 				=> $cinfo,
											'cat_name' 				=> $name)
											);
				}
				else
				{
					$ret_id = wp_insert_link_cat(array(
											'cat_name' 				=> $name)
											);
				}
				$ploglinkcat2wplinkcat[$id] = $ret_id;
			}
		
			// Store category translation for future use
			add_option('ploglinkcat2wplinkcat',$ploglinkcat2wplinkcat);
			echo '<p>'.sprintf(__('Done! <strong>%1$s</strong> link categories imported.'), $count).'<br /><br /></p>';
			return true;
		}
		echo __('No Link Categories to Import!');
		return false;
	}
	
	function links2wp($links='')
	{
		// General Housekeeping
		global $wpdb;
		$count = 0;
		$ploglinkcat2wplinkcat = array();
		
		// Get category translation array
		$ploglinkcat2wplinkcat = get_option('ploglinkcat2wplinkcat');
		
		// Deal with the links
		if(is_array($links))
		{
			echo '<p>'.__('Importing Links...').'<br /><br /></p>';
			foreach($links as $link)
			{
				$count++;
				extract($link);
				
				// Make nice vars
				$name 			= $wpdb->escape($name);
				$description 	= $wpdb->escape($description);
				$url			= trim($url);
				
				if($linfo = link_exists($url))
				{
					$ret_id = wp_insert_link(array(
								'link_id'			=> $linfo,
								'link_url'			=> $url,
								'link_name'			=> $name,
								'link_category'		=> $ploglinkcat2wplinkcat[$category_id],
								'link_description'	=> $description,
								'link_updated'		=> $date,
								'link_rss'			=> $rss_feed)
								);
				}
				else 
				{
					$ret_id = wp_insert_link(array(
								'link_url'			=> $url,
								'link_name'			=> $name,
								'link_category'		=> $ploglinkcat2wplinkcat[$category_id],
								'link_description'	=> $description,
								'link_updated'		=> $date,
								'link_rss'			=> $rss_feed)
								);
				}
				$ploglinks2wplinks[$id] = $ret_id;
			}
			add_option('ploglinks2wplinks',$ploglinks2wplinks);
			echo '<p>';
			printf(__('Done! <strong>%s</strong> Links imported'), $count);
			echo '<br /><br /></p>';
			return true;
		}
		echo __('No Links to Import!');
		return false;
	}
	 
	function import_categories() 
	{	
		// Category Import	
		$cats = $this->get_plog_cats();
		$this->cat2wp($cats);
		add_option('plog_cats', $cats);
		
		
			
		echo '<form action="admin.php?import=plog&amp;step=2" method="post">';
		printf('<input type="submit" name="submit" value="%s" />', __('Import Users'));
		echo '</form>';

	}
	
	function import_users()
	{
		// User Import
		$users = $this->get_plog_users(); 
		$this->users2wp($users);
		
		echo '<form action="admin.php?import=plog&amp;step=3" method="post">';
		printf('<input type="submit" name="submit" value="%s" />', __('Import Posts'));
		echo '</form>';
	}
	
	function import_posts()
	{
		// Post Import
		$posts = $this->get_plog_posts();
		$this->posts2wp($posts);
		
		echo '<form action="admin.php?import=plog&amp;step=4" method="post">';
		printf('<input type="submit" name="submit" value="%s" />', __('Import Comments'));
		echo '</form>';
	}
	
	function import_comments()
	{
		// Comment Import
		$comments = $this->get_plog_comments();
		$this->comments2wp($comments);
		
		echo '<form action="admin.php?import=plog&amp;step=5" method="post">';
		printf('<input type="submit" name="submit" value="%s" />', __('Import Link Categories'));
		echo '</form>';
	}
	
	function import_link_cats()
	{
		//Link Import
		$link_cats = $this->get_plog_link_cats();
		$this->linkcats2wp($link_cats);
		add_option('plog_link_cats', $link_cats);
		
		echo '<form action="admin.php?import=plog&amp;step=6" method="post">';
		printf('<input type="submit" name="submit" value="%s" />', __('Import Links'));
		echo '</form>';
	}
	
	function import_links()
	{
		//Link Import
		$links = $this->get_plog_links();
		$this->links2wp($links);
		add_option('plog_links', $links);
		
		echo '<form action="admin.php?import=plog&amp;step=7" method="post">';
		printf('<input type="submit" name="submit" value="%s" />', __('Finish'));
		echo '</form>';
	}
	
	function cleanup_plogimport()
	{
		delete_option('plogpre');
		delete_option('plog_cats');
		delete_option('plogid2wpid');
		delete_option('plogcat2wpcat');
		delete_option('plogposts2wpposts');
		delete_option('plogcm2wpcm');
		delete_option('plog_link_cats');
		delete_option('ploglinkcat2wplinkcat');
		delete_option('plog_links');
		delete_option('ploglinks2wplinks');
		delete_option('ploguser');
		delete_option('plogpass');
		delete_option('plogname');
		delete_option('ploghost');
		$this->tips();
	}
	
	function tips()
	{
		$login_url = get_option('siteurl').'/wp-login.php';
		
		echo '<p>'.__('Welcome to WordPress.  We hope (and expect!) that you will find this platform incredibly rewarding!  As a new WordPress user coming from pLog, there are some things that we would like to point out.  Hopefully, they will help your transition go as smoothly as possible.').'</p>';
		echo '<h3>'.__('Users').'</h3>';
		echo '<p>'.sprintf(__('You have already setup WordPress and have been assigned an administrative login and password.  In addition to this, we have taken care to import all of your users into our system.  Unfortunately there is one downside.  Because both WordPress and pLog use a strong encryption hash with passwords, it is impossible to decrypt it and we are forced to assign temporary passwords to all your users.  Every user has the same username, but their passwords have been reset to <strong>password456</strong>.  Please ensure these passwords are changed &ndash; <a href="%1$s">Login</a> and change them.'), $login_url).'</p>';
		echo '<h3>'.__('Preserving Authors').'</h3>';
		echo '<p>'.__('Secondly, we have attempted to preserve post authors.  If you are the only author or contributor to your blog, then you are safe.  In most cases, we are successful in this preservation endeavor.  However, if we cannot ascertain the name of the writer due to discrepancies between database tables, we assign it to you, the administrative user.').'</p>';
		echo '<h3>'.__('WordPress Resources').'</h3>';
		echo '<p>'.__('There are numerous WordPress resources around the internet.  Some of them are:').'</p>';
		echo '<ul>';
		echo '<li>'.__('<a href="http://www.wordpress.org">The official WordPress site</a>').'</li>';
		echo '<li>'.__('<a href="http://wordpress.org/support/">The WordPress support forums').'</li>';
		echo '<li>'.__('<a href="http://codex.wordpress.org">The Codex (In other words, the WordPress Bible)</a>').'</li>';
		echo '</ul>';
		echo '<h3>'.__('That\'s Everything!').'</h3>';
		echo '<p>'.sprintf(__('We hope that you have found this importer useful and easy to use! Go <a href="%1$s">login</a> and enjoy Wordpress!'), $login_url).'</p>';
	}
	
	function db_form()
	{
		echo '<ul>';
		printf('<li><label for="dbuser">%s</label> <input type="text" name="dbuser" /></li>', __('pLog Database User:'));
		printf('<li><label for="dbpass">%s</label> <input type="password" name="dbpass" /></li>', __('pLog Database Password:'));
		printf('<li><label for="dbname">%s</label> <input type="text" name="dbname" /></li>', __('pLog Database Name:'));
		printf('<li><label for="dbhost">%s</label> <input type="text" name="dbhost" value="localhost" /></li>', __('pLog Database Host:'));
		printf('<li><label for="dbprefix">%s</label> <input type="text" name="dbprefix" /></li>', __('pLog Table prefix (if any):'));
		echo '</ul>';
	}
	
	function dispatch() 
	{

		if (empty ($_GET['step']))
			$step = 0;
		else
			$step = (int) $_GET['step'];
		$this->header();
		
		if ( $step > 0 ) 
		{
			if($_POST['dbuser'])
			{
				if(get_option('ploguser'))
					delete_option('ploguser');	
				add_option('ploguser',$_POST['dbuser']);
			}
			if($_POST['dbpass'])
			{
				if(get_option('plogpass'))
					delete_option('plogpass');	
				add_option('plogpass',$_POST['dbpass']);
			}
			
			if($_POST['dbname'])
			{
				if(get_option('plogname'))
					delete_option('plogname');	
				add_option('plogname',$_POST['dbname']);
			}
			if($_POST['dbhost'])
			{
				if(get_option('ploghost'))
					delete_option('ploghost');
				add_option('ploghost',$_POST['dbhost']); 
			}
			if($_POST['dbprefix'])
			{
				if(get_option('plogpre'))
					delete_option('plogpre');
				add_option('plogpre',$_POST['dbprefix']); 
			}			


		}

		switch ($step) 
		{
			default:
			case 0 :
				$this->greet();
				break;
			case 1 :
				$this->import_categories();
				break;
			case 2 :
				$this->import_users();
				break;
			case 3 :
				$this->import_posts();
				break;
			case 4 :
				$this->import_comments();
				break;
			case 5 :
				$this->import_link_cats();
				break;
			case 6 :
				$this->import_links();
				break;
			case 7 :
				$this->cleanup_plogimport();
				break;
		}
		
		$this->footer();
	}

	function Plog_Import() 
	{
		// Nothing.	
	}
}

$plog_import = new Plog_Import();
register_importer('plog', 'pLog', __('Import posts from a pLog Blog'), array ($plog_import, 'dispatch'));
?>
