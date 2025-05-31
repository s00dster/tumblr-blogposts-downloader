<?php
/**
 * Tumblr Blog Posts Downloader (Public API Key)
 *
 * Since Tumblr no longer supports username/password login via API (magic link flow),
 * this script uses a public API key to fetch posts of public blogs.
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
 *  php tumblr_downloader_final.php
 *
 * Outputs will be stored in a subdirectory named downloads/"blogname" after the blog.
 */

// Enable full error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Prompt the user for input with the given message and return the trimmed result.
 */
function prompt($message) {
    echo $message;
    $handle = fopen('php://stdin', 'r');
    $line = fgets($handle);
    fclose($handle);
    return trim($line);
}

// 1. Prompt for Tumblr blog name (without .tumblr.com)
$blogName = '';
while (empty($blogName)) {
    $blogName = prompt("Enter the Tumblr blog name (without .tumblr.com): ");
    if (empty($blogName)) {
        echo "Blog name cannot be empty. Please try again.\n";
    }
}
$blogHost = rtrim($blogName, ".tumblr.com") . ".tumblr.com";

// 2. Ask if the user wants to save to a single file or separate files per post
$multipleFiles = false;
while (true) {
    $choice = strtolower(prompt("Save posts in a single file or separate files per post? (enter 'single' or 'multiple'): "));
    if ($choice === 'single') {
        $multipleFiles = false;
        break;
    } elseif ($choice === 'multiple') {
        $multipleFiles = true;
        break;
    } else {
        echo "Invalid choice. Please enter 'single' or 'multiple'.\n";
    }
}

// 3. Ask for desired format of the saved posts
$validFormats = ['json', 'text', 'markdown'];
$format = '';
while (true) {
    echo "Available formats: json, text, markdown\n";
    $formatInput = strtolower(prompt("Enter desired format for saved posts: "));
    if (in_array($formatInput, $validFormats)) {
        $format = $formatInput;
        break;
    } else {
        echo "Invalid format. Please choose one of: json, text, markdown.\n";
    }
}

// 4. Prompt for Tumblr public API key
$apiKey = '';
while (empty($apiKey)) {
    $apiKey = prompt("Enter your Tumblr public API key: ");
    if (empty($apiKey)) {
        echo "API key cannot be empty. Please try again.\n";
    }
}

// 5. Prepare output directory
$parentDir = 'download';
$outputDir  = __DIR__
            . DIRECTORY_SEPARATOR
            . $parentDir
            . DIRECTORY_SEPARATOR
            . $blogName;

if (!is_dir($outputDir)) {
    if (!mkdir($outputDir, 0755, true)) {
        fwrite(STDERR, "ERROR: Unable to create directory '{$outputDir}'.\n");
        exit(1);
    }
}

/**
 * Helper function to perform a GET request via cURL and return decoded JSON or null on error.
 */
function fetchTumblrJson($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (curl_errno($ch) || $httpCode !== 200) {
        fwrite(STDERR, "cURL or HTTP error (status $httpCode) fetching URL: $url\n");
        curl_close($ch);
        return null;
    }
    curl_close($ch);
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        fwrite(STDERR, "ERROR: Invalid JSON response.\n");
        return null;
    }
    return $data;
}

// 6. Fetch all posts via Tumblr API v2 (public key)
$posts = [];
$offset = 0;
$limit = 20; // Maximum per request

echo "Fetching posts from '{$blogHost}'...\n";
while (true) {
    $apiUrl = "https://api.tumblr.com/v2/blog/{$blogHost}/posts?api_key={$apiKey}&offset={$offset}&limit={$limit}";
    $data = fetchTumblrJson($apiUrl);
    if ($data === null || !isset($data['response']['posts'])) {
        fwrite(STDERR, "ERROR fetching or parsing posts.\n");
        exit(1);
    }
    $batch = $data['response']['posts'];
    if (empty($batch)) {
        // No more posts to fetch
        break;
    }
    $posts = array_merge($posts, $batch);
    $offset += count($batch);
    if (count($batch) < $limit) {
        // Fetched last page
        break;
    }
}

$totalPosts = count($posts);
if ($totalPosts === 0) {
    echo "No posts found for blog '{$blogName}'. Exiting.\n";
    exit(0);
}

echo "Fetched {$totalPosts} posts. Sorting by date...\n";

// 7. Sort posts by date ascending
usort($posts, function($a, $b) {
    $timeA = strtotime($a['date']);
    $timeB = strtotime($b['date']);
    return $timeA - $timeB;
});

// 8. Save posts to files according to user choice
if ($multipleFiles) {
    echo "Saving each post to a separate file...\n";
    foreach ($posts as $post) {
        $datePart  = date('Y-m-d', strtotime($post['date']));
        $idPart    = $post['id'];
        $extension = ($format === 'json') ? 'json' : 'txt';
        $filename  = "{$outputDir}/{$datePart}_post_{$idPart}.{$extension}";

        if ($format === 'json') {
            $content = json_encode($post, JSON_PRETTY_PRINT);
        } elseif ($format === 'markdown') {
            $title = $post['title'] ?? '';
            $body  = $post['body'] ?? '';
            $content  = "# {$title}\n\n";
            $content .= "*Date: {$post['date']}*\n\n";
            $content .= $body;
        } else {
            $content  = "Date: {$post['date']}\n";
            if (isset($post['title'])) {
                $content .= "Title: {$post['title']}\n";
            }
            $content .= "----\n";
            if (isset($post['body'])) {
                $content .= strip_tags($post['body']) . "\n";
            }
        }
        if (file_put_contents($filename, $content) === false) {
            fwrite(STDERR, "ERROR writing to file: {$filename}\n");
        }
    }
    echo "Saved posts to individual files in '{$outputDir}'.\n";
} else {
    echo "Saving all posts into a single file...\n";
    $extension      = ($format === 'json') ? 'json' : 'txt';
    $singleFilename = "{$outputDir}/{$blogName}_posts.{$extension}";

    if ($format === 'json') {
        $allContent = json_encode($posts, JSON_PRETTY_PRINT);
    } elseif ($format === 'markdown') {
        $allContent = '';
        foreach ($posts as $post) {
            $title = $post['title'] ?? '';
            $body  = $post['body'] ?? '';
            $allContent  .= "# {$title}\n\n";
            $allContent  .= "*Date: {$post['date']}*\n\n";
            $allContent  .= $body . "\n\n---\n\n";
        }
    } else {
        $allContent = '';
        foreach ($posts as $post) {
            $allContent .= "Date: {$post['date']}\n";
            if (isset($post['title'])) {
                $allContent .= "Title: {$post['title']}\n";
            }
            $allContent .= "----\n";
            if (isset($post['body'])) {
                $allContent .= strip_tags($post['body']) . "\n";
            }
            $allContent .= "\n";
        }
    }

    if (file_put_contents($singleFilename, $allContent) === false) {
        fwrite(STDERR, "ERROR writing to file: {$singleFilename}\n");
        exit(1);
    }
    echo "Saved all posts to '{$singleFilename}'.\n";
}

echo "Done.\n";
