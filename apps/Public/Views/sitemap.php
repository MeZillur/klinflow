<?php
// Set the correct content type
header('Content-type: application/xml');

// 1. Define base variables
$domain = "https://www.klinflow.com/";
$exclude_files = ['404.php', 'config.php', 'sitemap.php']; // Files NOT to include

// 2. Output XML header
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n\n";

// 3. Scan the current directory for all .php files
$files = glob("*.php");

foreach ($files as $file) {
    if (in_array($file, $exclude_files)) {
        continue; // Skip files we don't want in the sitemap
    }
    
    // Get the last modification time of the file
    $lastmod_timestamp = filemtime($file);
    $lastmod_date = date("Y-m-d", $lastmod_timestamp);
    
    // Construct the full URL
    $loc = $domain . $file;
    
    // Set default priority and changefreq (can be customized with more logic)
    $priority = ($file == 'home.php') ? '1.0' : '0.8'; 
    $changefreq = 'weekly';

    // 4. Output the URL block
    echo "    <url>\n";
    echo "        <loc>$loc</loc>\n";
    echo "        <lastmod>$lastmod_date</lastmod>\n";
    echo "        <changefreq>$changefreq</changefreq>\n";
    echo "        <priority>$priority</priority>\n";
    echo "    </url>\n";
}

// 5. Close XML
echo "\n" . '</urlset>';
?>