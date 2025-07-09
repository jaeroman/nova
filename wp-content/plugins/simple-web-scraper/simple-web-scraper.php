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

    public function handle_directory_auto_scrape() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'scraper_nonce')) {
            wp_die('Security check failed');
        }

        $url = sanitize_url($_POST['url']);

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            wp_send_json_error('Invalid URL provided');
        }

        $company_urls = $this->discover_member_profile_urls();

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

        // Return false if not a Wild Apricot profile page
        return false;
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

        // City - find span with "City" text, then get the fieldBody div, then get span inside that div
        // Updated to match the specific HTML structure: div.fieldSubContainer > div.fieldLabel > span[text()='City'] + div.fieldBody > span
        $city_nodes = $xpath->query("//div[contains(@class, 'fieldSubContainer')]//div[contains(@class, 'fieldLabel')]/span[text()='City']/../../div[contains(@class, 'fieldBody')]/span");
        if ($city_nodes->length > 0) {
            $city = trim($city_nodes->item(0)->textContent);
            if (!empty($city)) {
                $company_data['city'] = $city;
            }
        }

        // Fallback: Try the previous selector pattern as backup
        if (!isset($company_data['city'])) {
            $city_nodes_fallback = $xpath->query("//span[text()='City']/following-sibling::div[contains(@class, 'fieldBody')]//span | //span[text()='City']/..//div[contains(@class, 'fieldBody')]//span");
            if ($city_nodes_fallback->length > 0) {
                $city = trim($city_nodes_fallback->item(0)->textContent);
                if (!empty($city)) {
                    $company_data['city'] = $city;
                }
            }
        }

        // Fallback: City from profile data
        if (!isset($company_data['city']) && isset($profile_data['City'])) {
            $company_data['city'] = $profile_data['City'];
        }

        // Province - find span with "Province/State" text, then get the fieldBody div, then get span inside that div
        // Updated to match the specific HTML structure: div.fieldSubContainer > div.fieldLabel > span[text()='Province/State'] + div.fieldBody > span
        $province_nodes = $xpath->query("//div[contains(@class, 'fieldSubContainer')]//div[contains(@class, 'fieldLabel')]/span[text()='Province/State']/../../div[contains(@class, 'fieldBody')]/span");
        if ($province_nodes->length > 0) {
            $province = trim($province_nodes->item(0)->textContent);
            if (!empty($province)) {
                $company_data['province'] = $province;
            }
        }

        // Fallback: Try the previous selector patterns as backup
        if (!isset($company_data['province'])) {
            $province_nodes_fallback = $xpath->query("//span[text()='Province/State']/following-sibling::div[contains(@class, 'fieldBody')]//span | //span[text()='Province/State']/..//div[contains(@class, 'fieldBody')]//span | //span[text()='Province']/following-sibling::div[contains(@class, 'fieldBody')]//span | //span[text()='Province']/..//div[contains(@class, 'fieldBody')]//span");
            if ($province_nodes_fallback->length > 0) {
                $province = trim($province_nodes_fallback->item(0)->textContent);
                if (!empty($province)) {
                    $company_data['province'] = $province;
                }
            }
        }

        // Fallback: Province from profile data
        if (!isset($company_data['province']) && isset($profile_data['Province/State'])) {
            $company_data['province'] = $profile_data['Province/State'];
        }

        // Website - find span with "Web Site" text, then get the fieldBody div, then get the link inside that div
        // Updated to match the specific HTML structure: div.fieldSubContainer > div.fieldLabel > span[text()='Web Site'] + div.fieldBody > span > a
        $website_nodes = $xpath->query("//div[contains(@class, 'fieldSubContainer')]//div[contains(@class, 'fieldLabel')]/span[text()='Web Site']/../../div[contains(@class, 'fieldBody')]/span/a");
        if ($website_nodes->length > 0) {
            $website = $website_nodes->item(0)->getAttribute('href');
            if (!empty($website)) {
                // Clean up the website URL
                $website = trim($website);
                // Add http if missing
                if (!preg_match('/^https?:\/\//', $website)) {
                    $website = 'http://' . $website;
                }
                $company_data['website'] = $website;
            }
        }

        // Fallback: Website from profile data
        if (!isset($company_data['website']) && isset($profile_data['Web Site'])) {
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
