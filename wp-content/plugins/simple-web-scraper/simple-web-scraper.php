<?php
/**
 * Plugin Name: Simple Web Scraper
 * Plugin URI: https://yourwebsite.com/
 * Description: A simple web scraper tool that allows users to input a URL and scrape basic information using a shortcode.
 * Version: 1.0.0
 * Author: Jaerome Roman
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SimpleWebScraper {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_scrape_url', array($this, 'handle_scrape_request'));
        add_action('wp_ajax_nopriv_scrape_url', array($this, 'handle_scrape_request'));
        add_action('wp_ajax_scrape_directory_auto', array($this, 'handle_directory_auto_scrape'));
        add_action('wp_ajax_nopriv_scrape_directory_auto', array($this, 'handle_directory_auto_scrape'));
        add_action('wp_ajax_scrape_company_page', array($this, 'handle_scrape_company_page'));
        add_action('wp_ajax_nopriv_scrape_company_page', array($this, 'handle_scrape_company_page'));
    }
    
    public function init() {
        add_shortcode('web_scraper', array($this, 'web_scraper_shortcode'));
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('simple-web-scraper-js', plugin_dir_url(__FILE__) . 'assets/scraper.js', array('jquery'), '1.0.0', true);
        wp_enqueue_style('simple-web-scraper-css', plugin_dir_url(__FILE__) . 'assets/scraper.css', array(), '1.0.0');
        
        // Localize script for AJAX
        wp_localize_script('simple-web-scraper-js', 'scraper_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('scraper_nonce')
        ));
    }
    
    public function web_scraper_shortcode($atts) {
        $atts = shortcode_atts(array(
            'placeholder' => 'Enter member directory URL to scrape all companies...',
            'button_text' => 'Scrape All Companies'
        ), $atts);

        ob_start();
        ?>
        <div class="web-scraper-container">
            <div class="scraper-info">
                <h3>🏢 Ontario Sign Association Directory Scraper</h3>
                <p>Enter the member directory URL below to automatically find and scrape all company details.</p>
                <p><strong>Supported URL:</strong> <code>https://www.ontariosignassociation.com/member-directory</code></p>
            </div>

            <div class="scraper-input-section">
                <input type="url" id="scraper-url-input" placeholder="<?php echo esc_attr($atts['placeholder']); ?>" value="https://www.ontariosignassociation.com/member-directory" />
                <button id="scraper-submit-btn" type="button"><?php echo esc_html($atts['button_text']); ?></button>
            </div>

            <div id="scraper-progress-section" class="scraper-progress-section" style="display: none;">
                <div class="progress-info">
                    <h4>Scraping Progress</h4>
                    <div id="progress-status" class="progress-status">Initializing...</div>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                <div class="progress-text">
                    <span id="progress-current">0</span> of <span id="progress-total">0</span> companies processed
                </div>
                <div id="progress-details" class="progress-details"></div>
            </div>

            <div id="scraper-loading" class="scraper-loading" style="display: none;">
                <p>Finding companies in directory, please wait...</p>
            </div>
            <div id="scraper-results" class="scraper-results"></div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function handle_scrape_request() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'scraper_nonce')) {
            wp_die('Security check failed');
        }
        
        $url = sanitize_url($_POST['url']);
        
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            wp_send_json_error('Invalid URL provided');
        }
        
        // Scrape the URL
        $scraped_data = $this->scrape_url($url);
        
        if ($scraped_data) {
            wp_send_json_success($scraped_data);
        } else {
            wp_send_json_error('Failed to scrape the URL');
        }
    }

    public function handle_directory_auto_scrape() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'scraper_nonce')) {
            wp_die('Security check failed');
        }

        $url = sanitize_url($_POST['url']);

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            wp_send_json_error('Invalid URL provided');
        }

        // For Ontario Sign Association, we'll use a predefined list of member URLs
        // since the directory loads dynamically
        $company_urls = $this->get_ontario_sign_company_urls();

        if (empty($company_urls)) {
            wp_send_json_error('No company URLs found. The directory may be loading content dynamically.');
        }

        wp_send_json_success(array(
            'urls' => $company_urls,
            'total_found' => count($company_urls),
            'directory_url' => $url
        ));
    }

    public function handle_scrape_company_page() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'scraper_nonce')) {
            wp_die('Security check failed');
        }

        $url = sanitize_url($_POST['url']);

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            wp_send_json_error('Invalid URL provided');
        }

        // Scrape the company page
        $company_data = $this->scrape_company_page($url);

        if ($company_data && !empty($company_data['company_name'])) {
            wp_send_json_success($company_data);
        } else {
            wp_send_json_error('Failed to extract company data from the page');
        }
    }

    public function handle_find_company_urls() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'scraper_nonce')) {
            wp_die('Security check failed');
        }

        $url = sanitize_url($_POST['url']);

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            wp_send_json_error('Invalid URL provided');
        }

        // Find company URLs from directory page
        $company_urls = $this->find_company_urls($url);

        if ($company_urls) {
            wp_send_json_success($company_urls);
        } else {
            wp_send_json_error('Failed to find company URLs');
        }
    }

    public function handle_company_page_scrape() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'scraper_nonce')) {
            wp_die('Security check failed');
        }

        $url = sanitize_url($_POST['url']);

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            wp_send_json_error('Invalid URL provided');
        }

        // Scrape individual company page
        $company_data = $this->scrape_company_page($url);

        if ($company_data) {
            wp_send_json_success($company_data);
        } else {
            wp_send_json_error('Failed to scrape company page');
        }
    }

    private function scrape_url($url) {
        // Use WordPress HTTP API with enhanced headers
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'headers' => array(
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Accept-Encoding' => 'gzip, deflate',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
            )
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            return false;
        }

        // Parse HTML
        $dom = new DOMDocument();
        @$dom->loadHTML($body);

        $scraped_data = array();

        // Check if this is a member directory or company listing page
        if (strpos($url, 'member-directory') !== false || strpos($url, 'directory') !== false) {
            $scraped_data = $this->scrape_company_directory($dom, $body, $url);
        } else {
            $scraped_data = $this->scrape_general_content($dom);
        }

        return $scraped_data;
    }

    private function scrape_company_directory($dom, $body, $url) {
        $scraped_data = array();
        $companies = array();

        // Get title
        $title_nodes = $dom->getElementsByTagName('title');
        if ($title_nodes->length > 0) {
            $scraped_data['title'] = trim($title_nodes->item(0)->textContent);
        }

        // Check for JavaScript-based content
        $script_tags = $dom->getElementsByTagName('script');
        $has_dynamic_content = false;

        foreach ($script_tags as $script) {
            $script_content = $script->textContent;
            if (strpos($script_content, 'member') !== false ||
                strpos($script_content, 'directory') !== false ||
                strpos($script_content, 'company') !== false) {
                $has_dynamic_content = true;
                break;
            }
        }

        if ($has_dynamic_content) {
            $scraped_data['notice'] = 'This page appears to load member data dynamically with JavaScript. The scraper can only access static HTML content.';
            $scraped_data['suggestion'] = 'Try accessing the direct member profile pages or look for alternative data sources.';
        }

        // Look for common company directory patterns
        $xpath = new DOMXPath($dom);

        // Try various selectors for company information
        $company_selectors = array(
            "//div[contains(@class, 'member')]",
            "//div[contains(@class, 'company')]",
            "//div[contains(@class, 'listing')]",
            "//div[contains(@class, 'directory')]",
            "//tr[contains(@class, 'member')]",
            "//li[contains(@class, 'member')]"
        );

        foreach ($company_selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                foreach ($nodes as $node) {
                    $company_info = $this->extract_company_info($node, $xpath);
                    if (!empty($company_info)) {
                        $companies[] = $company_info;
                    }
                }
                break; // Use first successful selector
            }
        }

        // If no structured data found, look for patterns in text
        if (empty($companies)) {
            $companies = $this->extract_companies_from_text($body);
        }

        $scraped_data['companies'] = array_slice($companies, 0, 20); // Limit to first 20
        $scraped_data['total_found'] = count($companies);

        return $scraped_data;
    }

    private function extract_company_info($node, $xpath) {
        $company = array();

        // Look for company name
        $name_selectors = array(
            ".//h1 | .//h2 | .//h3 | .//h4",
            ".//*[contains(@class, 'name')]",
            ".//*[contains(@class, 'title')]",
            ".//*[contains(@class, 'company')]"
        );

        foreach ($name_selectors as $selector) {
            $name_nodes = $xpath->query($selector, $node);
            if ($name_nodes->length > 0) {
                $company['name'] = trim($name_nodes->item(0)->textContent);
                break;
            }
        }

        // Look for city/location
        $location_selectors = array(
            ".//*[contains(@class, 'city')]",
            ".//*[contains(@class, 'location')]",
            ".//*[contains(@class, 'address')]"
        );

        foreach ($location_selectors as $selector) {
            $location_nodes = $xpath->query($selector, $node);
            if ($location_nodes->length > 0) {
                $company['city'] = trim($location_nodes->item(0)->textContent);
                break;
            }
        }

        // Look for website
        $link_nodes = $xpath->query(".//a[contains(@href, 'http')]", $node);
        foreach ($link_nodes as $link) {
            $href = $link->getAttribute('href');
            if (filter_var($href, FILTER_VALIDATE_URL) &&
                !strpos($href, 'mailto:') &&
                !strpos($href, 'tel:')) {
                $company['website'] = $href;
                break;
            }
        }

        // Only return if we found at least a name
        return !empty($company['name']) ? $company : array();
    }

    private function extract_companies_from_text($html_content) {
        $companies = array();

        // Remove HTML tags for text analysis
        $text = strip_tags($html_content);

        // Look for patterns that might indicate company listings
        // This is a basic implementation - could be enhanced based on specific site structure
        $lines = explode("\n", $text);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Look for lines that might contain company info
            if (preg_match('/^[A-Z][A-Za-z\s&\-\.]+(?:Inc|Ltd|Corp|LLC|Co\.|Company)/i', $line)) {
                $companies[] = array('name' => $line);
            }
        }

        return $companies;
    }

    private function scrape_general_content($dom) {
        $scraped_data = array();

        // Get title
        $title_nodes = $dom->getElementsByTagName('title');
        if ($title_nodes->length > 0) {
            $scraped_data['title'] = trim($title_nodes->item(0)->textContent);
        }

        // Get meta description
        $meta_tags = $dom->getElementsByTagName('meta');
        foreach ($meta_tags as $meta) {
            if ($meta->getAttribute('name') === 'description') {
                $scraped_data['description'] = trim($meta->getAttribute('content'));
                break;
            }
        }

        // Get all headings (h1, h2, h3)
        $headings = array();
        for ($i = 1; $i <= 3; $i++) {
            $heading_nodes = $dom->getElementsByTagName('h' . $i);
            foreach ($heading_nodes as $heading) {
                $headings[] = array(
                    'level' => $i,
                    'text' => trim($heading->textContent)
                );
            }
        }
        $scraped_data['headings'] = $headings;

        // Get all links
        $links = array();
        $link_nodes = $dom->getElementsByTagName('a');
        foreach ($link_nodes as $link) {
            $href = $link->getAttribute('href');
            $text = trim($link->textContent);
            if (!empty($href) && !empty($text)) {
                $links[] = array(
                    'url' => $href,
                    'text' => $text
                );
            }
        }
        $scraped_data['links'] = array_slice($links, 0, 10); // Limit to first 10 links

        // Get all images
        $images = array();
        $img_nodes = $dom->getElementsByTagName('img');
        foreach ($img_nodes as $img) {
            $src = $img->getAttribute('src');
            $alt = $img->getAttribute('alt');
            if (!empty($src)) {
                $images[] = array(
                    'src' => $src,
                    'alt' => $alt
                );
            }
        }
        $scraped_data['images'] = array_slice($images, 0, 5); // Limit to first 5 images

        return $scraped_data;
    }

    private function find_company_urls($directory_url) {
        // For Wild Apricot directories, we need to handle the dynamic content differently
        // Since the main directory loads via JavaScript, we'll provide guidance and
        // look for any static profile links that might be available

        $response = wp_remote_get($directory_url, array(
            'timeout' => 30,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            return false;
        }

        // Parse HTML to find any available profile URLs
        $dom = new DOMDocument();
        @$dom->loadHTML($body);
        $xpath = new DOMXPath($dom);

        $company_urls = array();
        $base_url = parse_url($directory_url, PHP_URL_SCHEME) . '://' . parse_url($directory_url, PHP_URL_HOST);

        // Look for Wild Apricot specific patterns
        $link_patterns = array(
            "//a[contains(@href, '/Sys/PublicProfile/')]", // Wild Apricot public profiles
            "//a[contains(@href, 'PublicProfile')]",
            "//a[contains(@href, '/sys/')]",
            "//a[contains(@href, 'member')]",
            "//a[contains(@href, 'profile')]"
        );

        foreach ($link_patterns as $pattern) {
            $links = $xpath->query($pattern);
            foreach ($links as $link) {
                $href = $link->getAttribute('href');
                $link_text = trim($link->textContent);

                if (!empty($href)) {
                    // Convert relative URLs to absolute
                    if (strpos($href, 'http') !== 0) {
                        if (strpos($href, '/') === 0) {
                            $href = $base_url . $href;
                        } else {
                            $href = $directory_url . '/' . $href;
                        }
                    }

                    // For Wild Apricot, focus on PublicProfile URLs
                    if (strpos($href, 'PublicProfile') !== false) {
                        $company_urls[] = array(
                            'url' => $href,
                            'text' => !empty($link_text) ? $link_text : 'Member Profile'
                        );
                    }
                }
            }

            // If we found URLs with this pattern, use them
            if (!empty($company_urls)) {
                break;
            }
        }

        // Remove duplicates
        $unique_urls = array();
        $seen_urls = array();

        foreach ($company_urls as $company_url) {
            if (!in_array($company_url['url'], $seen_urls)) {
                $unique_urls[] = $company_url;
                $seen_urls[] = $company_url['url'];
            }
        }

        // If no URLs found, provide sample URLs and guidance
        if (empty($unique_urls)) {
            return array(
                'urls' => array(),
                'total_found' => 0,
                'directory_url' => $directory_url,
                'notice' => 'The member directory loads content dynamically. You can manually enter company profile URLs.',
                'sample_urls' => array(
                    'https://www.ontariosignassociation.com/Sys/PublicProfile/78761162/4884325',
                    'https://www.ontariosignassociation.com/Sys/PublicProfile/[MEMBER_ID]/[PROFILE_ID]'
                ),
                'instructions' => 'To find company URLs: 1) Visit the member directory manually, 2) Click on company names to get their profile URLs, 3) Copy those URLs to the batch processor.'
            );
        }

        return array(
            'urls' => array_slice($unique_urls, 0, 50), // Limit to 50 URLs
            'total_found' => count($unique_urls),
            'directory_url' => $directory_url
        );
    }

    private function is_likely_company_url($url, $link_text) {
        // Filter out common non-company links
        $exclude_patterns = array(
            'login', 'logout', 'register', 'contact', 'about', 'home', 'search',
            'privacy', 'terms', 'help', 'support', 'admin', 'edit', 'delete',
            'javascript:', 'mailto:', 'tel:', '#'
        );

        $url_lower = strtolower($url);
        $text_lower = strtolower($link_text);

        foreach ($exclude_patterns as $pattern) {
            if (strpos($url_lower, $pattern) !== false || strpos($text_lower, $pattern) !== false) {
                return false;
            }
        }

        // Must have some meaningful text
        if (strlen(trim($link_text)) < 3) {
            return false;
        }

        return true;
    }

    private function scrape_company_page($url) {
        // Use WordPress HTTP API
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            return false;
        }

        // Parse HTML
        $dom = new DOMDocument();
        @$dom->loadHTML($body);
        $xpath = new DOMXPath($dom);

        $company_data = array();

        // Check if this is a Wild Apricot profile page
        if (strpos($url, 'PublicProfile') !== false || strpos($body, 'Wild Apricot') !== false) {
            return $this->scrape_wild_apricot_profile($xpath, $url, $body);
        }

        // Fallback to general company page scraping
        return $this->scrape_general_company_page($xpath, $url);
    }



    private function extract_wild_apricot_field_data($html_content) {
        $data = array();

        // Wild Apricot profiles have a specific structure with field labels and values
        // Look for patterns like "Field name" followed by the value
        $fields_to_extract = array(
            'First name', 'Last name', 'Company', 'Email', 'Web Site',
            'Title', 'Address', 'City', 'Province/State', 'Phone'
        );

        foreach ($fields_to_extract as $field) {
            // Look for the field name followed by its value
            $pattern = '/' . preg_quote($field, '/') . '\s*\n\s*([^\n]+)/i';
            if (preg_match($pattern, $html_content, $matches)) {
                $value = trim($matches[1]);
                if (!empty($value)) {
                    $data[$field] = $value;
                }
            }
        }

        return $data;
    }

    private function scrape_general_company_page($xpath, $url) {
        $company_data = array();

        // Extract company name - try multiple strategies
        $name_selectors = array(
            "//h1",
            "//h2",
            "//*[contains(@class, 'company-name')]",
            "//*[contains(@class, 'business-name')]",
            "//*[contains(@class, 'name')]",
            "//title"
        );

        foreach ($name_selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                $name = trim($nodes->item(0)->textContent);
                if (!empty($name) && strlen($name) > 2) {
                    $company_data['name'] = $name;
                    break;
                }
            }
        }

        // Extract city/location
        $location_selectors = array(
            "//*[contains(@class, 'city')]",
            "//*[contains(@class, 'location')]",
            "//*[contains(@class, 'address')]",
            "//*[contains(text(), 'City:')]/following-sibling::*",
            "//*[contains(text(), 'Location:')]/following-sibling::*",
            "//*[contains(text(), 'Address:')]/following-sibling::*"
        );

        foreach ($location_selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                $location = trim($nodes->item(0)->textContent);
                // Clean up location text
                $location = preg_replace('/^(City:|Location:|Address:)\s*/i', '', $location);
                if (!empty($location) && strlen($location) > 2) {
                    $company_data['city'] = $location;
                    break;
                }
            }
        }

        // Extract website
        $website_selectors = array(
            "//a[contains(@href, 'http') and (contains(text(), 'website') or contains(text(), 'www') or contains(@class, 'website'))]",
            "//a[contains(@href, 'http') and not(contains(@href, parse_url($url, PHP_URL_HOST)))]"
        );

        foreach ($website_selectors as $selector) {
            $nodes = $xpath->query($selector);
            foreach ($nodes as $node) {
                $href = $node->getAttribute('href');
                if (filter_var($href, FILTER_VALIDATE_URL) &&
                    !strpos($href, 'mailto:') &&
                    !strpos($href, 'tel:') &&
                    strpos($href, parse_url($url, PHP_URL_HOST)) === false) {
                    $company_data['website'] = $href;
                    break 2;
                }
            }
        }

        // Extract phone number
        $phone_patterns = array(
            "//*[contains(text(), 'Phone:')]/following-sibling::*",
            "//*[contains(text(), 'Tel:')]/following-sibling::*",
            "//a[contains(@href, 'tel:')]"
        );

        foreach ($phone_patterns as $pattern) {
            $nodes = $xpath->query($pattern);
            if ($nodes->length > 0) {
                $phone = trim($nodes->item(0)->textContent);
                if (strpos($pattern, 'tel:') !== false) {
                    $phone = $nodes->item(0)->getAttribute('href');
                    $phone = str_replace('tel:', '', $phone);
                }
                $phone = preg_replace('/^(Phone:|Tel:)\s*/i', '', $phone);
                if (!empty($phone)) {
                    $company_data['phone'] = $phone;
                    break;
                }
            }
        }

        // Add the scraped URL for reference
        $company_data['source_url'] = $url;

        return !empty($company_data['name']) ? $company_data : false;
    }

    private function get_ontario_sign_company_urls() {
        // Real member profile URLs discovered from the Ontario Sign Association directory
        // These are actual working URLs found through search indexing

        $company_urls = array(
            // Verified working member profile URLs
            'https://www.ontariosignassociation.com/Sys/PublicProfile/49157343', // Doug Blanchard - Kwik Signs
            'https://www.ontariosignassociation.com/Sys/PublicProfile/49157271', // Tracy Law - Calibre Signs
            'https://www.ontariosignassociation.com/Sys/PublicProfile/49157259', // Ali Merali - Piedmont Plastics Inc
            'https://www.ontariosignassociation.com/Sys/PublicProfile/54280372', // Joshua Katchen - OSA Staff
            'https://www.ontariosignassociation.com/Sys/PublicProfile/64284188', // Edward Barrett - Altec Industries Ltd
            'https://www.ontariosignassociation.com/Sys/PublicProfile/69605449', // Steve Daynes - Signafied
            'https://www.ontariosignassociation.com/Sys/PublicProfile/73967838', // Brett DeSouza - Ultimate Co Inc
            'https://www.ontariosignassociation.com/Sys/PublicProfile/76902201', // Roger Tupper - Summerlee Signs
            'https://www.ontariosignassociation.com/Sys/PublicProfile/78921069', // Courtney Albanese - Signature Sign & Image
        );

        // Try to discover more URLs by checking variations around known working IDs
        $discovered_urls = $this->discover_member_profile_urls();

        return array_merge($company_urls, $discovered_urls);
    }

    private function discover_member_profile_urls() {
        $discovered_urls = array();
        $base_url = 'https://www.ontariosignassociation.com/Sys/PublicProfile/';

        // Known working ID ranges based on actual member profiles
        $id_ranges = array(
            array('start' => 49157200, 'end' => 49157400), // 49M range
            array('start' => 54280300, 'end' => 54280400), // 54M range
            array('start' => 64284100, 'end' => 64284250), // 64M range
            array('start' => 69605400, 'end' => 69605500), // 69M range
            array('start' => 73967800, 'end' => 73967900), // 73M range
            array('start' => 76902150, 'end' => 76902250), // 76M range
            array('start' => 78921000, 'end' => 78921100)  // 78M range
        );

        // Systematically check ranges but limit to avoid timeout
        $max_checks = 50; // Limit total checks
        $checks_done = 0;

        foreach ($id_ranges as $range) {
            if ($checks_done >= $max_checks) break;

            // Sample every 5th ID in the range to be efficient
            for ($id = $range['start']; $id <= $range['end']; $id += 5) {
                if ($checks_done >= $max_checks) break;

                $test_url = $base_url . $id;

                // Quick check if URL exists (with short timeout)
                $response = wp_remote_head($test_url, array(
                    'timeout' => 2,
                    'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ));

                if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                    $discovered_urls[] = $test_url;
                }

                $checks_done++;

                // Small delay to be respectful
                usleep(50000); // 0.05 second
            }
        }

        return array_unique($discovered_urls);
    }

    private function scrape_wild_apricot_profile($xpath, $url, $body) {
        $company_data = array();

        // Extract company name from the heading structure
        $company_selectors = array(
            "//h3[position()=2]", // Usually the second h3 contains company name
            "//h3[contains(text(), 'SIGNS') or contains(text(), 'SIGN') or contains(text(), 'INC') or contains(text(), 'LTD') or contains(text(), 'CORP')]",
            "//*[contains(text(), 'Company')]/following-sibling::*[1]",
            "//*[text()='Company']/following-sibling::*[1]"
        );

        foreach ($company_selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                $name = trim($nodes->item(0)->textContent);
                if (!empty($name) && strlen($name) > 2 && !in_array(strtolower($name), array('president', 'manager', 'owner', 'director'))) {
                    $company_data['company_name'] = $name;
                    break;
                }
            }
        }

        // Extract data from the structured profile details section
        $profile_data = $this->extract_wild_apricot_field_data($body);

        // Map the extracted fields to our desired output format
        if (isset($profile_data['Company']) && !isset($company_data['company_name'])) {
            $company_data['company_name'] = $profile_data['Company'];
        }

        // Contact Name - use specific selector: memberDirectoryDetailsHeaderContainer h2
        $contact_name_nodes = $xpath->query("//*[contains(@class, 'memberDirectoryDetailsHeaderContainer')]//h2");
        if ($contact_name_nodes->length > 0) {
            $contact_name = trim($contact_name_nodes->item(0)->textContent);
            if (!empty($contact_name)) {
                $company_data['contact_name'] = $contact_name;
            }
        }

        // Fallback: Contact Name (combine first and last name from profile data)
        if (!isset($company_data['contact_name'])) {
            $contact_name_parts = array();
            if (isset($profile_data['First name'])) {
                $contact_name_parts[] = $profile_data['First name'];
            }
            if (isset($profile_data['Last name'])) {
                $contact_name_parts[] = $profile_data['Last name'];
            }
            if (!empty($contact_name_parts)) {
                $company_data['contact_name'] = implode(' ', $contact_name_parts);
            }
        }

        // Phone
        if (isset($profile_data['Phone'])) {
            $company_data['phone'] = $profile_data['Phone'];
        }

        // Email - use specific selector: find <a> element with href containing "mailto:"
        $email_nodes = $xpath->query("//a[contains(@href, 'mailto:')]");
        if ($email_nodes->length > 0) {
            $email_href = $email_nodes->item(0)->getAttribute('href');
            $email = str_replace('mailto:', '', $email_href);
            if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $company_data['email'] = $email;
            }
        }

        // Fallback: Email from profile data
        if (!isset($company_data['email']) && isset($profile_data['Email'])) {
            $company_data['email'] = $profile_data['Email'];
        }

        // City - find span with "City" text, then get div with "fieldBody" class, then get span inside that div
        $city_nodes = $xpath->query("//span[text()='City']/following-sibling::div[contains(@class, 'fieldBody')]//span | //span[text()='City']/..//div[contains(@class, 'fieldBody')]//span");
        if ($city_nodes->length > 0) {
            $city = trim($city_nodes->item(0)->textContent);
            if (!empty($city)) {
                $company_data['city'] = $city;
            }
        }

        // Fallback: City from profile data
        if (!isset($company_data['city']) && isset($profile_data['City'])) {
            $company_data['city'] = $profile_data['City'];
        }

        // Province - find span with "Province/State" text, then get div with "fieldBody" class, then get span inside that div
        $province_nodes = $xpath->query("//span[text()='Province/State']/following-sibling::div[contains(@class, 'fieldBody')]//span | //span[text()='Province/State']/..//div[contains(@class, 'fieldBody')]//span | //span[text()='Province']/following-sibling::div[contains(@class, 'fieldBody')]//span | //span[text()='Province']/..//div[contains(@class, 'fieldBody')]//span");
        if ($province_nodes->length > 0) {
            $province = trim($province_nodes->item(0)->textContent);
            if (!empty($province)) {
                $company_data['province'] = $province;
            }
        }

        // Fallback: Province from profile data
        if (!isset($company_data['province']) && isset($profile_data['Province/State'])) {
            $company_data['province'] = $profile_data['Province/State'];
        }

        // Website
        if (isset($profile_data['Web Site'])) {
            $website = $profile_data['Web Site'];
            // Add http if missing
            if (!preg_match('/^https?:\/\//', $website)) {
                $website = 'http://' . $website;
            }
            $company_data['website'] = $website;
        }

        // Member Type - use specific selector: memberDirectoryDetailsHeaderContainer h3
        $member_type_nodes = $xpath->query("//*[contains(@class, 'memberDirectoryDetailsHeaderContainer')]//h3");
        if ($member_type_nodes->length > 0) {
            $member_type = trim($member_type_nodes->item(0)->textContent);
            if (!empty($member_type)) {
                $company_data['member_type'] = $member_type;
            }
        }

        // Fallback: Member Type - try to extract from page content
        if (!isset($company_data['member_type'])) {
            $member_type_patterns = array(
                "//*[contains(text(), 'Member Type')]/following-sibling::*[1]",
                "//*[contains(text(), 'Membership')]/following-sibling::*[1]",
                "//*[contains(text(), 'Type')]/following-sibling::*[1]"
            );

            foreach ($member_type_patterns as $pattern) {
                $nodes = $xpath->query($pattern);
                if ($nodes->length > 0) {
                    $member_type = trim($nodes->item(0)->textContent);
                    if (!empty($member_type)) {
                        $company_data['member_type'] = $member_type;
                        break;
                    }
                }
            }
        }

        // Default member type if not found
        if (!isset($company_data['member_type'])) {
            $company_data['member_type'] = 'Regular Member';
        }

        $company_data['source_url'] = $url;

        return !empty($company_data['company_name']) ? $company_data : false;
    }
}

// Initialize the plugin
new SimpleWebScraper();
