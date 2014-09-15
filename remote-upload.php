<?php
/*
Plugin Name: Remote File Upload
Plugin URI: http://tos.com.bd
Description: Upload image, Files from remote server in Media folder.
Author: Total Online Solution Bangladesh
Author URI: http://tos.com.bd
Version: 0.01
Requires at least: 3.9
Tested up to: 4.0
*/

load_plugin_textdomain('rigr', false, 'remote-Upload');

add_action('admin_menu', 'rigr_add_menu');

function rigr_add_menu() {
	add_media_page( __('Remote Upload', 'rigr'), __('Remote Upload', 'rigr'), 'upload_files', 'remote_Upload', $func = 'rigr_manager' );
}

function rigr_admin_init() {
	if ('POST'==$_SERVER['REQUEST_METHOD'] && !empty($_POST['rigr_base_url']) && ''!=($rigr_base_url=trim($_POST['rigr_base_url'])) ) {
		setcookie('rigr_base_url', $c=urlencode(untrailingslashit($rigr_base_url).'/'), 0);
		$_COOKIE['rigr_base_url']= $c;
	}
}

add_action('admin_init', 'rigr_admin_init');

function rigr_manager() {
	global $wpdb, $pagenow;
	if ( 'POST'== $_SERVER['REQUEST_METHOD'] ) {
		$rigr_attach_to= (int)$_POST['rigr_attach_to'];
		$rigr_base_url= empty($_COOKIE['rigr_base_url'])?'':urldecode($_COOKIE['rigr_base_url']);

		$post= $wpdb->get_row("SELECT ID, post_date FROM $wpdb->posts WHERE ID=$rigr_attach_to", ARRAY_A);

		if ( !$post ) {
			$rigr_attach_to= 0;
		}
		
		$page_with_links= $_POST['rigr_page_with_links'];
	
		if ( '' != $page_with_links )
		{
			$tmpname= tempnam(sys_get_temp_dir(), '');
			$headers= wp_get_http($page_with_links, $tmpname);

			if ( $headers['response'] && ( $headers['response'] != '200' ) ) {
				echo '<p>Incorrect: ',$page_with_links,' - response ',$headers['response'], ', should be 200.</p>';
				$urls= array();
			}
			else {
				$s= file_get_contents($tmpname);
				echo '<p>',$page_with_links,' - ',strlen($s), ' bytes</p>';
			
			}
			unlink($tmpname);
		}
		else {
			$s= $_POST['rigr_list'];
		}

		$s= str_replace('><', '> <', $s);

		$find_in_tags= array();

		if ( $_POST['rigr_hrefs'] )
			$find_in_tags['a']= array('href'=>1);

		if ( $_POST['rigr_srcs'] )
			$find_in_tags['img']= array('src'=>1);

		$s= wp_kses($s, $find_in_tags );

		preg_match_all('/https?:\/\/[a-z0-9;=_%\/\Q?&[].-+\E]+/is', $s, $allurls);
		preg_match_all('/href\s{0,}={0,}[]["\'](.+?)["\']/is', $s, $allhref);
		preg_match_all('/src\s{0,}=\s{0,}["\'](.+?)["\']/is', $s, $allsrc);

		if ( !$allhref[1] ) $allhref[1]= array();
		if ( !$allsrc[1] ) $allsrc[1]= array();
		$allurls[0]= array_merge($allurls[0], $allhref[1], $allsrc[1]);

		$urls= array();

		foreach ($allurls[0] as $u) {
			if ( !preg_match('/^https?:\/\//', $u) && ''!=$rigr_base_url )
				$u= $rigr_base_url.ltrim($u, '/');
			if ( preg_match('/(jpg|jpeg|gif|png|rar|zip|mp3|mp4|flv|pdf|swf)/i', $u) )
					$urls[]= $u;
		}
		$rigr_list= array_unique($urls);
		if ( !count($rigr_list) ) {
			echo '<p class="help"><img src="images/no.png"><span style="color: #ff0000;"> '.__('Sorry, file not found.', 'rigr').'<span></p>';
		}
		else foreach ($rigr_list as $k=>$v ) {

			$res[$k]= rigr_fetch_remote_file($post, $v);

			if ( is_object($res[$k]) && $res[$k]->errors ) {
				$out= array_values( $res[$k]->errors );
				$out= array_values( $out[0] );
				echo '<p class="help"><img src="images/no.png"><strong>',$v, '</strong> <span style="color: #ff0000;">- error:</span> ', $out[0], '</p>';
				continue;
			}
			
			if ( !empty($_POST['rigr_post_title']) ) {
				$title= wp_kses($_POST['rigr_post_title'], array()).(($k==0)?'':' - '.$k);
			}
			else {
				$title= basename($res[$k]['file']);
				if ( $dotpos= strpos($title, '.') )
					$title= substr($title, 0, $dotpos);
			}
			$att= array(
				'post_status'=>'publish', 'post_parent'=> $rigr_attach_to, 'ping_status' =>'closed', 'guid'=>$res[$k]['url'], 'post_title'=> $title, 'post_mime_type'=>$res[$k]['content-type'] );

			$att_ID= wp_insert_attachment($att);

			if ( !$att_ID ) {
				echo "<br />can not create attachment for $res[$k][file]<br />";
				continue;
			}

			wp_update_attachment_metadata($att_ID, wp_generate_attachment_metadata($att_ID, $res[$k]['file']));
			update_attached_file($att_ID, $res[$k]['file']);
			echo '<p class="help"><img src="images/yes.png"><span style="color: #00b000;"> File Successfully Uploaded to Media Library</span> <strong><span>(',$v, ')</strong></span></p>';
		}
	}

?>

<div class="wrap">

<?php if ( 'upload.php'==$pagenow) echo '<div id="icon-upload" class="icon32"></div> <h2>'. __('Remote Upload', 'rigr').'</h2>'; ?>

<form method="post" action="" name="rigr_form" id="rigr_form" >

<div style="clear:both;">

<h3 class="media-title"><?php _e('Past Remote file&#39;s Urls here: (one link per line)'); ?></h3>

<textarea name="rigr_list" style="width:100%" rows="5" cols="64"></textarea>
<p class="help">* Use Only ( jpg, jpeg, gif, png, rar, zip, mp3, mp4, flv, swf & pdf) files direct links, </p>
<p class="help">example: http://website.com/path-to-file/my-file.zip</p>
</div>


<div style="float:left; width:60%;">
<p><?php _e('Or the URL of a page that has links to files:', 'rigr'); ?>

<input type="text" name="rigr_page_with_links" size="60" /></p>

<?php if ( isset($_GET['post_id']) )
	echo '<input type="hidden" name="rigr_attach_to" value="'.((int)$_GET['post_id']).'" />';
else 
	echo '<p>'.__('The post ID where files should be attached to:', 'rigr').' <input type="text" name="rigr_attach_to" value="0" size="5" /></p> 
	<p class="help">if not set then files will be unattached</p>'; ?>

<p><?php _e('The title for a file or file group:', 'rigr'); ?> <input type="text" name="rigr_post_title"  size="50" /></p>
<p class="help">(optional) if left empty, files names are used</p>

<p><?php _e('Base URL for relative paths:', 'rigr'); ?> <input type="text" value="<?php
	echo empty($_COOKIE['rigr_base_url'])?'':urldecode($_COOKIE['rigr_base_url']); ?>" name="rigr_base_url" size="50" /></p>
<p class="help">(optional) if left empty, then plugin will only look for absolute URLs</p>

<p><input type="submit" style="width:300px" class="button-primary" name="Submit" value=" <?php _e('Upload!', 'rigr') ?> " /></p>

</div>
<div style="float:left; padding-left:10px; width:38%">
<p style="margin:0 0 0 0 !important"><?php _e('File size limit', 'rigr'); ?> <input type="text" name="rigr_max_size" value="<?php echo get_site_option('fileupload_maxk', 0); /* for wpmu compatibility */ ?>" size="4" /> KB
<small><br />(<?php _e('Leave 0 for no limit', 'rigr'); ?>)</small>
</p>

<p style="margin:0 0 0 0 !important"><?php _e('Ignore files less then', 'rigr'); ?> <input type="text" name="rigr_min_size" value="0" size="4" /> KB
<small><br />(<?php _e('Leave 0 for no limit', 'rigr'); ?>)</small>
</p>

<p><input type="checkbox" name="rigr_hrefs" checked="checked" value="1" /><?php _e('Upload files from URLs in', 'rigr'); ?> <code>&lt;a&nbsp;href="...</code>?</p>

<p><input type="checkbox" name="rigr_srcs" checked="checked" /><?php _e('Upload files from URLs in', 'rigr'); ?> <code>&lt;img&nbsp;src="...</code>?<br /></p>
<p class="help">If uncheck both the uploader strips all html-tags and finds files URLs in the rest of text.</p>
<p class="help"><a href="http://tos.com.bd/support" target="_blank" title="Online Help Servicet">Online Help Service</a>

</p>
</div>

</form>

</div>
<?php
}

/* -- after the example of a function from wp-admin/import/wordpress.php:~666 -- */

function rigr_fetch_remote_file($post, $url) {
		$url2= str_replace('&amp;', '&', str_replace('https://', 'http://', $url));
 
		preg_match('/[a-z0-9;=_%\Q?&.-+[]\E]+\.(jpg|jpeg|gif|png|rar|zip|mp3|mp4|flv|pdf|swf)/i', $url2, $pu);
		$file_name= str_replace('%25', '-', $pu[0]);
		$file_name= preg_replace('/[;=%\Q?&-+\E]+/i', '-', $file_name);
		$file_name= (strlen($file_name)>255)? substr($file_name, 180): $file_name;

		$upload = wp_upload_bits( $file_name, 0, '', $post['post_date']);

		if ( $upload['error'] ) {
			echo $upload['error'];
			return new WP_Error( 'upload_dir_error', $upload['error'] );
		}

		$headers = wp_get_http($url2, $upload['file']);

		if ( !$headers ) {
			@unlink($upload['file']);
			return new WP_Error( 'import_file_error', __('<p class="help"><img src="images/no.png"> <span style="color: #ff0000;">Remote server did not respond</span></p>', 'rigr') );
		}

		if ( $headers['response'] != '200' ) {
			@unlink($upload['file']);
			return new WP_Error( 'import_file_error', sprintf(__('Remote server says: %1$d %2$s', 'rigr'), $headers['response'], get_status_header_desc($headers['response']) ) );
		}
		elseif ( isset($headers['content-length']) && filesize($upload['file']) != $headers['content-length'] ) {
			@unlink($upload['file']);
			return new WP_Error( 'import_file_error', __('<p class="help"><img src="images/no.png"> <span style="color: #ff0000;">Remote file can not be downloaded</span></p>', 'rigr') );
		}

		$min_size = max( (float)$_POST['rigr_min_size'], 0 ) * 1024;

		$max_size = max( (int)$_POST['rigr_max_size'], (int)get_site_option('fileupload_maxk') )*1024;

/* -- fileupload_maxk for wpmu compatibility -- */

		$file_size= filesize($upload['file']);

		if ( !empty($max_size) && $file_size > $max_size ) {
			@unlink($upload['file']);
			return new WP_Error( 'import_file_error', sprintf(__('Remote file is %1$d KB but limit is %2$d', 'rigr'), $file_size/1024, $max_size/1024) );
		}
		elseif ( !empty($min_size) && $file_size < $min_size ) {
			@unlink($upload['file']);
			return new WP_Error( 'import_file_error', sprintf(__('Remote file size is less then %1$d KB', 'rigr'), $min_size/1024) );
		}

/* -- This check is for wpmu compatibility -- */
		if ( function_exists('get_space_allowed') ) {
			$space_allowed = 1048576 * get_space_allowed();
			$space_used = get_dirsize( BLOGUPLOADDIR );
			$space_left = $space_allowed - $space_used;

			if ( $space_left < 0 ) {
				@unlink($upload['file']);
				return new WP_Error( 'not_enough_diskspace', sprintf(__('You have %1$d KB diskspace used but %2$d allowed.', 'rigr'), $space_used/1024, $space_allowed/1024) );

			}
		}

		$upload['content-type']= $headers['content-type'];
		return $upload;
}

function rigr_media_tab($arr) {
	$arr['Upload'] = __('Remote Upload');
	return $arr;
}

add_filter('media_upload_tabs', 'rigr_media_tab');

function rigr_Upload($type = 'Upload') {
	media_upload_header();
	rigr_manager();
}

function rigr_Upload_page() {
    return wp_iframe( 'rigr_Upload');
}

add_action('media_upload_Upload', 'rigr_Upload_page');

function rigr_add_style() {
	global $wp_styles;
	if ( isset($_GET['tab']) && 'Upload'==$_GET['tab'] )
		$wp_styles->concat .= 'media,';

	return true;
}

add_action('print_admin_styles', 'rigr_add_style');
