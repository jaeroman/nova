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

### Customized Shortcode
```
[web_scraper placeholder="Enter website URL here..." button_text="Start Scraping"]
```

### Shortcode Parameters

- `placeholder` - Custom placeholder text for the URL input field
- `button_text` - Custom text for the scrape button

## What Gets Scraped

The plugin extracts the following information from the target URL:

1. **Page Title** - The content of the `<title>` tag
2. **Meta Description** - Content from the meta description tag
3. **Headings** - All H1, H2, and H3 headings on the page
4. **Links** - First 10 links found on the page (with link text)
5. **Images** - First 5 images found on the page (with alt text)

## Security Features

- Uses WordPress HTTP API for safe remote requests
- Implements nonce verification for AJAX requests
- Sanitizes all input and output data
- Validates URLs before processing
- Escapes HTML output to prevent XSS attacks

## Browser Compatibility

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- Internet Explorer 11+

## Requirements

- WordPress 4.0 or higher
- PHP 5.6 or higher
- cURL or allow_url_fopen enabled

## Styling

The plugin includes responsive CSS that adapts to your theme. You can customize the appearance by adding your own CSS rules targeting these classes:

- `.web-scraper-container` - Main container
- `.scraper-input-section` - Input and button area
- `.scraper-results` - Results display area
- `.scraper-section` - Individual result sections

## Troubleshooting

### Common Issues

1. **"Failed to scrape the URL"**
   - Check if the URL is accessible
   - Ensure your server can make outbound HTTP requests
   - Some websites may block scraping attempts

2. **No results showing**
   - Check browser console for JavaScript errors
   - Ensure jQuery is loaded on your site
   - Verify the plugin files are properly uploaded

3. **Styling issues**
   - Check if your theme conflicts with the plugin CSS
   - Try adding `!important` to custom CSS rules if needed

### Server Requirements

Make sure your hosting provider allows:
- Outbound HTTP/HTTPS requests
- PHP DOMDocument class
- WordPress HTTP API functionality

## Limitations

- Some websites may block scraping attempts
- JavaScript-generated content won't be captured
- Large pages may take longer to process
- Rate limiting may apply for multiple requests

## Support

For support and feature requests, please contact the plugin developer.

## License

This plugin is licensed under the GPL v2 or later.

## Changelog

### Version 1.0.0
- Initial release
- Basic web scraping functionality
- Shortcode implementation
- AJAX interface
- Responsive design
