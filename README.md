/**
 * Tumblr Blog Posts Downloader (Public API Key)
 *
 * Prompts the user for:
 *   - Tumblr blog name (without .tumblr.com)
 *   - Whether to save posts to a single file or multiple files
 *   - Desired format for saved posts (json, text, markdown)
 *   - Public Tumblr API key
 *
 * Fetches all posts via the Tumblr v2 API and saves them sorted by date.
 *
 * Requirements:
 *  - PHP with cURL extension enabled
 *  - Valid Tumblr API key (https://www.tumblr.com/oauth/apps)
 *   (No user/password needed.)
 *
 * Usage:
 *  php tumblr-blogposts-downloader.php
 *
 * Outputs will be stored in a subdirectory named downloads/"blogname" after the blog.
 */
