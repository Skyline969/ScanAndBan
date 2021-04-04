<?php
	/*
	 *	ScanAndBan - A Reddit URL Redirect Checker by Skyline969
	 *
	 *	This script will scan your subreddit and inspect recent URLs for redirection.
	 *	It will then take action if any URLs redirect somewhere that matches your blacklist.
	 *	This action is configurable - you can remove the post, mark is as spam,
	 *	and even ban the user who posted it.
	 */
	
	// Shut the script up by flipping these to false.
	$debug = false;		// Used to debug cURL calls
	$verbose = true;	// Used to show more information in the console
	
	// The subreddit to check. Do not include the /r/.
	$subreddit = "YOURSUBREDDITHERE";
	
	// Your subreddit fullname - you can find it at /r/yoursubreddithere/about.json - look for "name"
	// It should look something like "t5_43lzb0"
	$subreddit_fullname = "YOURSUBREDDITFULLNAMEHERE";
	
	// The credentials for the mod bot account.
	$mod_username	= "BOTUSERNAMEHERE";
	$mod_password	= "PASSWORDHERE";
	
	// You also need to create an app on your mod bot account.
	// Go to https://ssl.reddit.com/prefs/apps/ to create one.
	// See https://github.com/reddit-archive/reddit/wiki/OAuth2 for more info.
	$app_name	= "APPNAMEHERE";
	$app_id		= "APPIDHERE";
	$app_secret	= "APPSECRETHERE";
	
	// The list of URLs that should be actioned on.
	// These are processed as regular expressions, so feel free to get fancy with them.
	// See https://www.rexegg.com/regex-quickstart.html for more info.
	$url_blacklist = array(
		"rokzfast.com",
		"^http[s]{0,}:\/\/[www\.]{0,}leafythings.com",
	);
	
	// Whether the offending post should be removed.
	$blacklist_remove = true;
	
	// Whether the URL should be marked as spam.
	// Note that this does nothing if blacklist_remove is false.
	$mark_as_spam = true;
	
	// Whether the offending poster should be banned.
	$blacklist_ban = true;
	
	// The note that will be sent to the user when they are banned.
	// Note that this does nothing if blacklist_ban is false.
	$ban_note = "Spamming is not welcome here.";
	
	// ##################################################
	// ######        CONFIGURATION COMPLETE        ######
	// ###### DO NOT EDIT ANYTHING BELOW THIS LINE ######
	// ##################################################
	
	// Set our user agent string
	$user_agent_string = $app_name . "/0.2";
	
	// Some URLs we will need
	$access_token_url = "https://www.reddit.com/api/v1/access_token";
	$oauth_url = "https://oauth.reddit.com";
	
	// First, let's get our access token which we will need to process any removals/bans
	$oauth_fields = array(
		"grant_type" => "password",
		"username" => $mod_username,
		"password" => $mod_password
	);
	
	$oauth_token = array();
	
	/*
	 *	This function will check the redirection of the passed-in URL.
	 *	It attempts to follow the link 10 times before it gives up and
	 *	returns whatever link it is currently on.
	 */
	function check_redirect($url, $debug, $count=0)
	{
		$redirect_max = 10;
		
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_VERBOSE, $debug);
		$out = curl_exec($ch);
		
		curl_close($ch);

		// Default the real URL to the actual URL
		$real_url = $url;
		
		// If we see a location header, that means it is a redirect
		if (preg_match("/location: (.*)/i", $out, $redirect))
		{
			// Follow the redirect up to the maximum recursion limit,
			// if we hit the limit just return the redirected URL
			if ($count < $redirect_max)
				$real_url = check_redirect($redirect[1], $debug, $count++);
			else
				$real_url = $redirect[1];
		}

		return $real_url;
	}
	
	function blacklist($post, $blacklisted, $debug)
	{
		// There's probably a better way to do this.
		global $subreddit_fullname;
		global $blacklist_remove;
		global $mark_as_spam;
		global $blacklist_ban;
		global $ban_note;
		global $user_agent_string;
		global $oauth_url;
		global $oauth_token;
		
		print "Post " . $post->url . " found to be or contain blacklisted URL " . $blacklisted . "\n";
		
		// Remove the post if configured
		if ($blacklist_remove)
		{
			// These are the fields Reddit expects when removing a post
			$remove_fields = array(
				"id" => $post->name,
				"spam" => $mark_as_spam
			);
			
			$ch = curl_init($oauth_url . "/api/remove");
			// We simply need to authenticate with our access token and we're good to go
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				"Authorization: bearer " . $oauth_token["access_token"]
			));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_USERAGENT, $user_agent_string);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($remove_fields));
			curl_setopt($ch, CURLOPT_VERBOSE, $debug);
			
			$response = curl_exec($ch);
			curl_close($ch);
			
			// Reddit returns an empty JSON string on success
			if ($response == "{}")
				print "Post " . $post->permalink . " successfully removed.\n";
			else
				print "WARNING: Post " . $post->permalink . " could not be removed! Response: " . $response . "\n";
		}
		
		// Ban the user if configured
		if ($blacklist_ban)
		{
			// This is the info Reddit expects when banning someone
			$ban_fields = array(
				"action" => "add",
				"container" => $subreddit_fullname,
				"type" => "banned",
				"name" => $post->author,
				"note" => $ban_note,
				"id" => "#banned",
				"r" => $post->subreddit
			);
			
			$ban_field_string = http_build_query($ban_fields);
			
			$ch = curl_init($oauth_url . "/api/friend");
			// We simply need to authenticate with our access token and we're good to go
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				"Authorization: bearer " . $oauth_token["access_token"]
			));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_USERAGENT, $user_agent_string);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $ban_field_string);
			curl_setopt($ch, CURLOPT_VERBOSE, $debug);
			
			$response = curl_exec($ch);
			curl_close($ch);
			
			// Parse the result of the ban
			if ($ban_result = json_decode($response, true))
			{
				if ($ban_result["success"])
					print "User " . $post->author . " successfully banned.\n";
				else
				{
					print "WARNING: User " . $post->author . " could not be banned. Response:\n";
					print_r($ban_result);
				}
			}
			else
				print "WARNING: Post " . $post->author . " could not be banned! Response: " . $response . "\n";
		}
	}
	
	$ch = curl_init($access_token_url);
	// We also need to authorize using our app
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Basic " . base64_encode($app_id . ':' . $app_secret)));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_USERAGENT, $user_agent_string);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($oauth_fields));
	curl_setopt($ch, CURLOPT_VERBOSE, $debug);

	$response = curl_exec($ch);
	curl_close($ch);
	
	// Process the access token
	// No sense in continuing if we cannot authenticate
	if ($oauth_token = json_decode($response, true))
	{
		// Then, let's get data from reddit. Last 100 posts should be fine.
		// No authorization needed here since we're performing a read-only action.
		$ch = curl_init("https://www.reddit.com/r/" . $subreddit ."/new/.json?count=10");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_VERBOSE, $debug);
		// Set the useragent, otherwise Reddit gets mad.
		curl_setopt($ch, CURLOPT_USERAGENT, $user_agent_string);
		
		$result = curl_exec($ch);
		curl_close($ch);

		$post_ary = array();
		// Parse the result as JSON and load up the posts
		$result = json_decode($result, true) or die("Invalid JSON returned from retrieving posts!\n");
		foreach($result["data"]["children"] as $post)
			$post_ary[] = (object)$post["data"];
		
		// Flip them so we process the oldest posts first
		$post_ary = array_reverse($post_ary);
		
		// Now let's start scanning URLs
		foreach($post_ary as $post)
		{
			// Process the redirect from the post's URL
			$redirect = check_redirect($post->url, $debug);
			
			// Check if the URL is a self post
			if (preg_match("/^http[s]{0,1}\:\/\/(www\.){0,1}reddit\.com\/r\//", $redirect))
			{
				print $redirect . " is a reddit URL - scanning\n";
				// Get the content of the self post in JSON format
				if ($redirect_json = json_decode(file_get_contents($redirect . "/.json"), true))
				{
					if ($verbose)
						print $redirect . " contains content:\n" . $redirect_json[0]["data"]["children"][0]["data"]["selftext"] . "\n";
					
					// Get all URLs in the selftext of the post
					preg_match_all('#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $redirect_json[0]["data"]["children"][0]["data"]["selftext"], $url_match);
					foreach($url_match as $url)
					{
						// Only check if we actually got something
						if (!empty($url))
						{
							// Check if URL redirects to blacklisted domain
							$blacklisted = false;
							foreach($url_blacklist as $bl)
							{
								$self_bl = check_redirect($url[0], $debug);
								if (preg_match("/" . $bl . "/", $self_bl))
									$blacklisted = $self_bl;
							}
							
							// Kill the post if a URL in the post is blacklisted
							if ($blacklisted)
								blacklist($post, $self_bl, $debug);
						}
					}
				}
				else
					print "WARNING: Could not get JSON data for " . $redirect . "\n";
			}
			else
			{
				// If the post is not a self post, check the redirected URL
				if ($verbose)
					print $post->permalink . " has URL " . $post->url . " which redirects to " . $redirect . "\n";
				
				// Run the redirected URL through our list of blacklisted URLs
				$blacklisted = false;
				foreach($url_blacklist as $bl)
					if (preg_match("/" . $bl . "/", $redirect))
						$blacklisted = $bl;
				
				// Kill the post if the redirected URL is blacklisted
				if ($blacklisted)
					blacklist($post, $bl, $debug);
			}
		}
	
	}
	else
		print "OAuth authorization failed! Response: " . $response . "\n";
	
	if ($verbose)
		print "Done.\n";
?>
