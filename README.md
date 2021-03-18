# ScanAndBan
A script that will monitor posts in your subreddit and check URLs for redirection. If the redirected URL matches a configured blacklist in some way, the script will remove the post, mark it as spam, ban the user, or a combination of the three. Reddit's AutoModerator does not currently support checking URL redirections, and so this script was born.

## Requirements
* PHP, tested and verified on 7.4.3 but it should work on earlier versions
* php-curl

## Configuration
You will need to create a moderator account for the script and create a Reddit app using that account. You can create this at https://ssl.reddit.com/prefs/apps/. See https://github.com/reddit-archive/reddit/wiki/OAuth2 for instructions.

Once you have your moderator account set up for your subreddit and you have your app's client ID, client secret, and name you're good to go! The redirect URL doesn't matter, we will not be using it.

The script supports the following configuration:

* debug - If the script is failing for some reason, flip this to true to get a better idea of what is going on.
* verbose - If you want additional information such as where redirection URLS are pointing, turn this on.
* subreddit - The name of your subreddit without the /r/ prefix. This is the subreddit that ScanAndBan will monitor.
* subreddit_fullname - The ID of your subreddit, also known as its fullname. To find this, go to /r/yoursubreddithere/about.json and look for the value assigned to "name". This is used for issuing bans.
* mod_username - The username of your moderator account you're using.
* mod_password - The password of the moderator account you're using.
* app_name - The name of the app you created above.
* app_id - The client ID of the app you created above.
* app_secret - The client secret of the app you created above.
* url_blacklist - An array of URLs/partial URLs you want to action on. These are processed as regular expressions, so by default if you just specify a domain such as "google.com" it will action on **ANY URL containing that anywhere in it. If you add a word to the blacklist, it will action on ANY URL containing that word.** You have been warned. Action is taken on the redirected URL, not the original. If someone posts a URL to myredirector.com which redirects to mysketchysite.com, you would want to configure mysketchysite.com in the blacklist, not myredirector.com.
* blacklist_remove - Whether posts containing URLs matching the blacklist should be removed.
* mark_as_spam - Whether removed posts should be marked as spam. Doing so will prevent future links using that URL from even being allowed.
* blacklist_ban - Whether the user who posted the link should be banned.
* ban_note - What should be sent to the user when they are banned.

## Installation
Installation is as simple as installing the dependencies, configuring the script, and then running it. The script could be run via a cron job to automatically check for blacklisted posts. It does not repeat on its own, that is on you to set up in whichever way you see fit.

## Donations
If you like ScanAndBan, feel free to buy me a beer. Donations are never mandatory or even expected but are always appreciated.

<a href="https://www.paypal.me/skyline969"><img src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" alt="Donate"/></a>
