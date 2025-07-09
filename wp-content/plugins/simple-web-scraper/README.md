# Simple Web Scraper WordPress Plugin

A simple and user-friendly WordPress plugin that allows users to scrape basic information from any website using a shortcode.

## Features

- **Easy to use shortcode**: `[web_scraper]`
- **AJAX-powered**: No page reloads required
- **Responsive design**: Works on all devices
- **Secure**: Uses WordPress nonces and sanitization
- **Extracts multiple data types**:
  - Page title
  - Meta description
  - Headings (H1, H2, H3)
  - Links (first 10)
  - Images (first 5)

## Installation

1. Upload the `simple-web-scraper` folder to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Use the `[web_scraper]` shortcode in any post, page, or widget

## Usage

### Basic Shortcode
```
[web_scraper]
```