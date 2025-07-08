jQuery(document).ready(function($) {

    // Handle form submission - simplified for automated directory scraping
    $('#scraper-submit-btn').on('click', function() {
        var url = $('#scraper-url-input').val().trim();

        if (!url) {
            showError('Please enter the member directory URL.');
            return;
        }

        if (!isValidUrl(url)) {
            showError('Please enter a valid URL (including http:// or https://).');
            return;
        }

        // Check if it's the Ontario Sign Association directory
        if (url.indexOf('ontariosignassociation.com') === -1) {
            showError('This scraper is specifically designed for the Ontario Sign Association member directory.');
            return;
        }

        scrapeDirectoryAuto(url);
    });

    // Handle Enter key press in input field
    $('#scraper-url-input').on('keypress', function(e) {
        if (e.which === 13) { // Enter key
            $('#scraper-submit-btn').click();
        }
    });

    function scrapeDirectoryAuto(url) {
        // Show loading state
        $('#scraper-loading').show();
        $('#scraper-results').empty();
        $('#scraper-submit-btn').prop('disabled', true).text('Finding Companies...');

        // First, get all company URLs from the directory
        $.ajax({
            url: scraper_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'scrape_directory_auto',
                url: url,
                nonce: scraper_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data.urls.length > 0) {
                    $('#scraper-loading').hide();
                    startBatchScraping(response.data.urls, url);
                } else {
                    $('#scraper-loading').hide();
                    showError(response.data || 'No companies found in the directory. The directory may be loading content dynamically.');
                    $('#scraper-submit-btn').prop('disabled', false).text('Scrape All Companies');
                }
            },
            error: function(xhr, status, error) {
                $('#scraper-loading').hide();
                showError('An error occurred while finding companies: ' + error);
                $('#scraper-submit-btn').prop('disabled', false).text('Scrape All Companies');
            }
        });
    }

    function startBatchScraping(companyUrls, directoryUrl) {
        var results = [];
        var currentIndex = 0;
        var totalUrls = companyUrls.length;

        // Show progress section
        $('#scraper-progress-section').show();
        $('#progress-total').text(totalUrls);
        $('#progress-status').text('Starting to scrape companies...');
        $('#scraper-submit-btn').prop('disabled', true).text('Scraping in Progress...');

        function scrapeNext() {
            if (currentIndex >= totalUrls) {
                // All done
                $('#progress-status').text('Scraping completed!');
                displayFinalResults(results, directoryUrl);
                $('#scraper-submit-btn').prop('disabled', false).text('Scrape All Companies');
                return;
            }

            var url = companyUrls[currentIndex];
            currentIndex++;

            // Update progress
            $('#progress-current').text(currentIndex);
            var progressPercent = (currentIndex / totalUrls) * 100;
            $('.progress-fill').css('width', progressPercent + '%');
            $('#progress-status').text('Scraping company ' + currentIndex + ' of ' + totalUrls + '...');

            // Add current company to progress details
            $('#progress-details').prepend('<div class="progress-item">Processing: ' + escapeHtml(url) + '</div>');

            // Keep only last 5 progress items visible
            $('#progress-details .progress-item').slice(5).remove();

            // Scrape this URL
            $.ajax({
                url: scraper_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'scrape_company_page',
                    url: url,
                    nonce: scraper_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        results.push(response.data);
                        $('#progress-details .progress-item:first').addClass('success').text('✅ Success: ' + (response.data.company_name || 'Company'));
                    } else {
                        results.push({
                            error: true,
                            url: url,
                            message: response.data || 'Failed to scrape'
                        });
                        $('#progress-details .progress-item:first').addClass('error').text('❌ Failed: ' + url);
                    }
                },
                error: function() {
                    results.push({
                        error: true,
                        url: url,
                        message: 'Network error'
                    });
                    $('#progress-details .progress-item:first').addClass('error').text('❌ Error: ' + url);
                },
                complete: function() {
                    // Wait a bit before next request to be respectful
                    setTimeout(scrapeNext, 1500); // 1.5 second delay
                }
            });
        }

        scrapeNext();
    }

    function scrapeUrl(url) {
        // Show loading state
        $('#scraper-loading').show();
        $('#scraper-results').empty();
        $('#scraper-submit-btn').prop('disabled', true).text('Scraping...');
        
        // Make AJAX request
        $.ajax({
            url: scraper_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'scrape_url',
                url: url,
                nonce: scraper_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayResults(response.data, url);
                } else {
                    showError(response.data || 'Failed to scrape the URL. Please try again.');
                }
            },
            error: function(xhr, status, error) {
                showError('An error occurred while scraping the URL: ' + error);
            },
            complete: function() {
                // Hide loading state
                $('#scraper-loading').hide();
                $('#scraper-submit-btn').prop('disabled', false).text('Scrape URL');
            }
        });
    }

    function findCompanyUrls(url) {
        // Show loading state
        $('#scraper-loading').show();
        $('#scraper-results').empty();
        $('#scraper-submit-btn').prop('disabled', true).text('Finding URLs...');

        // Make AJAX request
        $.ajax({
            url: scraper_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'find_company_urls',
                url: url,
                nonce: scraper_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayCompanyUrls(response.data, url);
                } else {
                    showError(response.data || 'Failed to find company URLs. Please try again.');
                }
            },
            error: function(xhr, status, error) {
                showError('An error occurred while finding company URLs: ' + error);
            },
            complete: function() {
                // Hide loading state
                $('#scraper-loading').hide();
                $('#scraper-submit-btn').prop('disabled', false).text('Find Company URLs');
            }
        });
    }

    function scrapeCompanyPage(url) {
        // Show loading state
        $('#scraper-loading').show();
        $('#scraper-results').empty();
        $('#scraper-submit-btn').prop('disabled', true).text('Scraping Company...');

        // Make AJAX request
        $.ajax({
            url: scraper_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'scrape_company_page',
                url: url,
                nonce: scraper_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    displaySingleCompanyResult(response.data, url);
                } else {
                    showError(response.data || 'Failed to scrape company page. Please try again.');
                }
            },
            error: function(xhr, status, error) {
                showError('An error occurred while scraping company page: ' + error);
            },
            complete: function() {
                // Hide loading state
                $('#scraper-loading').hide();
                $('#scraper-submit-btn').prop('disabled', false).text('Scrape Company');
            }
        });
    }

    function batchScrapeCompanies(urls) {
        var results = [];
        var currentIndex = 0;
        var totalUrls = urls.length;

        // Show progress
        $('#scraper-batch-progress').show();
        $('#progress-total').text(totalUrls);
        $('#scraper-batch-btn').prop('disabled', true).text('Processing...');

        function scrapeNext() {
            if (currentIndex >= totalUrls) {
                // All done
                displayBatchResults(results);
                $('#scraper-batch-progress').hide();
                $('#scraper-batch-btn').prop('disabled', false).text('Scrape All Companies');
                return;
            }

            var url = urls[currentIndex].trim();
            currentIndex++;

            // Update progress
            $('#progress-current').text(currentIndex);
            var progressPercent = (currentIndex / totalUrls) * 100;
            $('.progress-fill').css('width', progressPercent + '%');

            // Scrape this URL
            $.ajax({
                url: scraper_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'scrape_company_page',
                    url: url,
                    nonce: scraper_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        results.push(response.data);
                    } else {
                        results.push({
                            error: true,
                            url: url,
                            message: response.data || 'Failed to scrape'
                        });
                    }
                },
                error: function() {
                    results.push({
                        error: true,
                        url: url,
                        message: 'Network error'
                    });
                },
                complete: function() {
                    // Wait a bit before next request to be polite
                    setTimeout(scrapeNext, 1000);
                }
            });
        }

        scrapeNext();
    }

    function displayResults(data, originalUrl) {
        var resultsHtml = '<div class="scraper-success">Successfully scraped: ' + escapeHtml(originalUrl) + '</div>';

        // Check if this is company directory data
        if (data.companies) {
            displayCompanyResults(data, originalUrl);
            return;
        }

        // Title
        if (data.title) {
            resultsHtml += '<div class="scraper-section">';
            resultsHtml += '<div class="scraper-title">' + escapeHtml(data.title) + '</div>';
            resultsHtml += '</div>';
        }

        // Description
        if (data.description) {
            resultsHtml += '<div class="scraper-section">';
            resultsHtml += '<h3>Meta Description</h3>';
            resultsHtml += '<div class="scraper-description">' + escapeHtml(data.description) + '</div>';
            resultsHtml += '</div>';
        }
        
        // Headings
        if (data.headings && data.headings.length > 0) {
            resultsHtml += '<div class="scraper-section scraper-headings">';
            resultsHtml += '<h3>Headings</h3>';
            resultsHtml += '<ul>';
            
            data.headings.forEach(function(heading) {
                resultsHtml += '<li class="h' + heading.level + '">' + escapeHtml(heading.text) + '</li>';
            });
            
            resultsHtml += '</ul>';
            resultsHtml += '</div>';
        }
        
        // Links
        if (data.links && data.links.length > 0) {
            resultsHtml += '<div class="scraper-section scraper-links">';
            resultsHtml += '<h3>Links (First 10)</h3>';
            resultsHtml += '<ul>';
            
            data.links.forEach(function(link) {
                var linkUrl = link.url;
                // Convert relative URLs to absolute
                if (linkUrl.startsWith('/')) {
                    var urlObj = new URL(originalUrl);
                    linkUrl = urlObj.protocol + '//' + urlObj.host + linkUrl;
                } else if (!linkUrl.startsWith('http')) {
                    linkUrl = originalUrl + '/' + linkUrl;
                }
                
                resultsHtml += '<li>';
                resultsHtml += '<a href="' + escapeHtml(linkUrl) + '" target="_blank" rel="noopener">';
                resultsHtml += escapeHtml(link.text);
                resultsHtml += '</a>';
                resultsHtml += '</li>';
            });
            
            resultsHtml += '</ul>';
            resultsHtml += '</div>';
        }
        
        // Images
        if (data.images && data.images.length > 0) {
            resultsHtml += '<div class="scraper-section">';
            resultsHtml += '<h3>Images (First 5)</h3>';
            resultsHtml += '<div class="scraper-images">';
            
            data.images.forEach(function(image) {
                var imageSrc = image.src;
                // Convert relative URLs to absolute
                if (imageSrc.startsWith('/')) {
                    var urlObj = new URL(originalUrl);
                    imageSrc = urlObj.protocol + '//' + urlObj.host + imageSrc;
                } else if (!imageSrc.startsWith('http')) {
                    imageSrc = originalUrl + '/' + imageSrc;
                }
                
                resultsHtml += '<div class="scraper-image-item">';
                resultsHtml += '<img src="' + escapeHtml(imageSrc) + '" alt="' + escapeHtml(image.alt || 'Image') + '" onerror="this.style.display=\'none\'">';
                if (image.alt) {
                    resultsHtml += '<div class="scraper-image-alt">' + escapeHtml(image.alt) + '</div>';
                }
                resultsHtml += '</div>';
            });
            
            resultsHtml += '</div>';
            resultsHtml += '</div>';
        }
        
        $('#scraper-results').html(resultsHtml);
    }

    function displayCompanyResults(data, originalUrl) {
        var resultsHtml = '<div class="scraper-success">Successfully scraped: ' + escapeHtml(originalUrl) + '</div>';

        // Title
        if (data.title) {
            resultsHtml += '<div class="scraper-section">';
            resultsHtml += '<div class="scraper-title">' + escapeHtml(data.title) + '</div>';
            resultsHtml += '</div>';
        }

        // Show notices if any
        if (data.notice) {
            resultsHtml += '<div class="scraper-notice">';
            resultsHtml += '<h3>⚠️ Important Notice</h3>';
            resultsHtml += '<p>' + escapeHtml(data.notice) + '</p>';
            if (data.suggestion) {
                resultsHtml += '<p><strong>Suggestion:</strong> ' + escapeHtml(data.suggestion) + '</p>';
            }
            resultsHtml += '</div>';
        }

        // Company listings
        if (data.companies && data.companies.length > 0) {
            resultsHtml += '<div class="scraper-section scraper-companies">';
            resultsHtml += '<h3>Company Directory (' + data.companies.length + ' found)</h3>';

            if (data.total_found && data.total_found > data.companies.length) {
                resultsHtml += '<p class="scraper-note">Showing first ' + data.companies.length + ' of ' + data.total_found + ' companies found.</p>';
            }

            resultsHtml += '<div class="company-grid">';

            data.companies.forEach(function(company, index) {
                resultsHtml += '<div class="company-card">';
                resultsHtml += '<div class="company-number">#' + (index + 1) + '</div>';

                if (company.name) {
                    resultsHtml += '<h4 class="company-name">' + escapeHtml(company.name) + '</h4>';
                }

                if (company.city) {
                    resultsHtml += '<div class="company-city">📍 ' + escapeHtml(company.city) + '</div>';
                }

                if (company.website) {
                    resultsHtml += '<div class="company-website">';
                    resultsHtml += '<a href="' + escapeHtml(company.website) + '" target="_blank" rel="noopener">🌐 Visit Website</a>';
                    resultsHtml += '</div>';
                }

                resultsHtml += '</div>';
            });

            resultsHtml += '</div>';
            resultsHtml += '</div>';
        } else {
            resultsHtml += '<div class="scraper-section">';
            resultsHtml += '<h3>No Company Data Found</h3>';
            resultsHtml += '<p>The scraper could not extract company information from this page. This might be because:</p>';
            resultsHtml += '<ul>';
            resultsHtml += '<li>The page loads data dynamically with JavaScript</li>';
            resultsHtml += '<li>The page structure is different than expected</li>';
            resultsHtml += '<li>The content is behind a login or paywall</li>';
            resultsHtml += '</ul>';
            resultsHtml += '</div>';
        }

        $('#scraper-results').html(resultsHtml);
    }

    function displayCompanyUrls(data, originalUrl) {
        var resultsHtml = '<div class="scraper-success">Searched for company URLs in: ' + escapeHtml(originalUrl) + '</div>';

        if (data.urls && data.urls.length > 0) {
            resultsHtml += '<div class="scraper-section">';
            resultsHtml += '<h3>Company URLs Found (' + data.urls.length + ' of ' + data.total_found + ')</h3>';
            resultsHtml += '<div class="company-urls-actions">';
            resultsHtml += '<button id="copy-urls-btn" class="copy-urls-btn">Copy All URLs</button>';
            resultsHtml += '<button id="load-to-batch-btn" class="load-batch-btn">Load to Batch Processor</button>';
            resultsHtml += '</div>';
            resultsHtml += '<div class="company-urls-list">';

            data.urls.forEach(function(urlData, index) {
                resultsHtml += '<div class="company-url-item">';
                resultsHtml += '<span class="url-number">' + (index + 1) + '.</span>';
                resultsHtml += '<a href="' + escapeHtml(urlData.url) + '" target="_blank" rel="noopener">';
                resultsHtml += escapeHtml(urlData.text || urlData.url);
                resultsHtml += '</a>';
                resultsHtml += '<button class="scrape-single-btn" data-url="' + escapeHtml(urlData.url) + '">Scrape This</button>';
                resultsHtml += '</div>';
            });

            resultsHtml += '</div>';
            resultsHtml += '</div>';
        } else {
            resultsHtml += '<div class="scraper-section">';
            resultsHtml += '<h3>Directory Analysis Results</h3>';

            if (data.notice) {
                resultsHtml += '<div class="scraper-notice">';
                resultsHtml += '<p><strong>Notice:</strong> ' + escapeHtml(data.notice) + '</p>';
                if (data.instructions) {
                    resultsHtml += '<p><strong>Instructions:</strong> ' + escapeHtml(data.instructions) + '</p>';
                }
                resultsHtml += '</div>';
            }

            if (data.sample_urls && data.sample_urls.length > 0) {
                resultsHtml += '<div class="sample-urls-section">';
                resultsHtml += '<h4>Sample Company Profile URLs:</h4>';
                resultsHtml += '<div class="sample-urls">';

                data.sample_urls.forEach(function(sampleUrl, index) {
                    resultsHtml += '<div class="sample-url-item">';
                    resultsHtml += '<code>' + escapeHtml(sampleUrl) + '</code>';
                    if (sampleUrl.indexOf('[') === -1) { // If it's a real URL, not a template
                        resultsHtml += '<button class="scrape-single-btn" data-url="' + escapeHtml(sampleUrl) + '">Test This URL</button>';
                    }
                    resultsHtml += '</div>';
                });

                resultsHtml += '</div>';
                resultsHtml += '<div class="manual-entry-section">';
                resultsHtml += '<h4>Manual URL Entry:</h4>';
                resultsHtml += '<p>Copy company profile URLs from the directory and paste them below:</p>';
                resultsHtml += '<textarea id="manual-urls-input" placeholder="Paste company profile URLs here, one per line..."></textarea>';
                resultsHtml += '<button id="load-manual-urls-btn" class="load-batch-btn">Load to Batch Processor</button>';
                resultsHtml += '</div>';
                resultsHtml += '</div>';
            }

            resultsHtml += '</div>';
        }

        $('#scraper-results').html(resultsHtml);

        // Add event handlers for the new buttons
        $('#copy-urls-btn').on('click', function() {
            var urls = data.urls.map(function(urlData) {
                return urlData.url;
            }).join('\n');

            navigator.clipboard.writeText(urls).then(function() {
                $(this).text('Copied!').addClass('copied');
                setTimeout(function() {
                    $('#copy-urls-btn').text('Copy All URLs').removeClass('copied');
                }, 2000);
            }.bind(this));
        });

        $('#load-to-batch-btn').on('click', function() {
            var urls = data.urls.map(function(urlData) {
                return urlData.url;
            }).join('\n');

            $('#scraper-urls-list').val(urls);
            $('input[name="scraper-mode"][value="company"]').prop('checked', true);
            updateUIForMode('company');

            // Scroll to batch section
            $('#scraper-batch-section')[0].scrollIntoView({ behavior: 'smooth' });
        });

        $('.scrape-single-btn').on('click', function() {
            var url = $(this).data('url');
            scrapeCompanyPage(url);
        });

        $('#load-manual-urls-btn').on('click', function() {
            var urls = $('#manual-urls-input').val().trim();
            if (urls) {
                $('#scraper-urls-list').val(urls);
                $('input[name="scraper-mode"][value="company"]').prop('checked', true);
                updateUIForMode('company');

                // Scroll to batch section
                $('#scraper-batch-section')[0].scrollIntoView({ behavior: 'smooth' });
            } else {
                alert('Please enter some URLs first.');
            }
        });
    }

    function displaySingleCompanyResult(data, originalUrl) {
        var resultsHtml = '<div class="scraper-success">Successfully scraped company: ' + escapeHtml(originalUrl) + '</div>';

        resultsHtml += '<div class="scraper-section">';
        resultsHtml += '<h3>Company Information</h3>';
        resultsHtml += '<div class="company-grid">';
        resultsHtml += '<div class="company-card single-company">';

        if (data.name) {
            resultsHtml += '<h4 class="company-name">' + escapeHtml(data.name) + '</h4>';
        }

        // Contact information
        if (data.contact_name) {
            resultsHtml += '<div class="company-contact">👤 <strong>Contact:</strong> ' + escapeHtml(data.contact_name);
            if (data.contact_title) {
                resultsHtml += ' (' + escapeHtml(data.contact_title) + ')';
            }
            resultsHtml += '</div>';
        }

        // Location information
        if (data.address || data.city || data.province) {
            resultsHtml += '<div class="company-location">📍 ';
            var locationParts = [];
            if (data.address) locationParts.push(escapeHtml(data.address));
            if (data.city) locationParts.push(escapeHtml(data.city));
            if (data.province) locationParts.push(escapeHtml(data.province));
            resultsHtml += locationParts.join(', ');
            resultsHtml += '</div>';
        } else if (data.city) {
            resultsHtml += '<div class="company-city">📍 ' + escapeHtml(data.city) + '</div>';
        }

        // Contact methods
        if (data.website) {
            resultsHtml += '<div class="company-website">';
            resultsHtml += '<a href="' + escapeHtml(data.website) + '" target="_blank" rel="noopener">🌐 Visit Website</a>';
            resultsHtml += '</div>';
        }

        if (data.email) {
            resultsHtml += '<div class="company-email">';
            resultsHtml += '<a href="mailto:' + escapeHtml(data.email) + '">📧 ' + escapeHtml(data.email) + '</a>';
            resultsHtml += '</div>';
        }

        if (data.phone) {
            resultsHtml += '<div class="company-phone">📞 ' + escapeHtml(data.phone) + '</div>';
        }

        resultsHtml += '<div class="company-source">';
        resultsHtml += '<small>Source: <a href="' + escapeHtml(data.source_url) + '" target="_blank" rel="noopener">' + escapeHtml(data.source_url) + '</a></small>';
        resultsHtml += '</div>';

        resultsHtml += '</div>';
        resultsHtml += '</div>';
        resultsHtml += '</div>';

        $('#scraper-results').html(resultsHtml);
    }

    function displayBatchResults(results) {
        var successCount = results.filter(function(r) { return !r.error; }).length;
        var errorCount = results.length - successCount;

        var resultsHtml = '<div class="scraper-success">Batch processing completed: ' + successCount + ' successful, ' + errorCount + ' failed</div>';

        if (successCount > 0) {
            var successResults = results.filter(function(r) { return !r.error; });

            resultsHtml += '<div class="scraper-section scraper-companies">';
            resultsHtml += '<h3>Successfully Scraped Companies (' + successCount + ')</h3>';
            resultsHtml += '<div class="batch-actions">';
            resultsHtml += '<button id="export-csv-btn" class="export-btn">Export as CSV</button>';
            resultsHtml += '</div>';
            resultsHtml += '<div class="company-grid">';

            successResults.forEach(function(company, index) {
                resultsHtml += '<div class="company-card">';
                resultsHtml += '<div class="company-number">#' + (index + 1) + '</div>';

                if (company.name) {
                    resultsHtml += '<h4 class="company-name">' + escapeHtml(company.name) + '</h4>';
                }

                if (company.contact_name) {
                    resultsHtml += '<div class="company-contact">👤 ' + escapeHtml(company.contact_name);
                    if (company.contact_title) {
                        resultsHtml += ' (' + escapeHtml(company.contact_title) + ')';
                    }
                    resultsHtml += '</div>';
                }

                // Location information
                if (company.address || company.city || company.province) {
                    resultsHtml += '<div class="company-location">📍 ';
                    var locationParts = [];
                    if (company.address) locationParts.push(escapeHtml(company.address));
                    if (company.city) locationParts.push(escapeHtml(company.city));
                    if (company.province) locationParts.push(escapeHtml(company.province));
                    resultsHtml += locationParts.join(', ');
                    resultsHtml += '</div>';
                } else if (company.city) {
                    resultsHtml += '<div class="company-city">📍 ' + escapeHtml(company.city) + '</div>';
                }

                if (company.website) {
                    resultsHtml += '<div class="company-website">';
                    resultsHtml += '<a href="' + escapeHtml(company.website) + '" target="_blank" rel="noopener">🌐 Visit Website</a>';
                    resultsHtml += '</div>';
                }

                if (company.email) {
                    resultsHtml += '<div class="company-email">';
                    resultsHtml += '<a href="mailto:' + escapeHtml(company.email) + '">📧 ' + escapeHtml(company.email) + '</a>';
                    resultsHtml += '</div>';
                }

                if (company.phone) {
                    resultsHtml += '<div class="company-phone">📞 ' + escapeHtml(company.phone) + '</div>';
                }

                resultsHtml += '</div>';
            });

            resultsHtml += '</div>';
            resultsHtml += '</div>';

            // Add CSV export functionality
            $('#scraper-results').html(resultsHtml);

            $('#export-csv-btn').on('click', function() {
                exportToCSV(successResults);
            });
        }

        if (errorCount > 0) {
            var errorResults = results.filter(function(r) { return r.error; });

            resultsHtml += '<div class="scraper-section scraper-errors">';
            resultsHtml += '<h3>Failed URLs (' + errorCount + ')</h3>';
            resultsHtml += '<ul class="error-list">';

            errorResults.forEach(function(error) {
                resultsHtml += '<li>';
                resultsHtml += '<strong>' + escapeHtml(error.url) + '</strong>: ' + escapeHtml(error.message);
                resultsHtml += '</li>';
            });

            resultsHtml += '</ul>';
            resultsHtml += '</div>';
        }

        $('#scraper-results').html(resultsHtml);
    }

    function displayFinalResults(results, directoryUrl) {
        var successResults = results.filter(function(r) { return !r.error; });
        var errorResults = results.filter(function(r) { return r.error; });
        var successCount = successResults.length;
        var errorCount = errorResults.length;

        var resultsHtml = '<div class="scraper-success">🎉 Directory scraping completed!</div>';
        resultsHtml += '<div class="scraper-summary">';
        resultsHtml += '<h3>📊 Scraping Summary</h3>';
        resultsHtml += '<div class="summary-stats">';
        resultsHtml += '<div class="stat-item success"><span class="stat-number">' + successCount + '</span><span class="stat-label">Companies Successfully Scraped</span></div>';
        resultsHtml += '<div class="stat-item error"><span class="stat-number">' + errorCount + '</span><span class="stat-label">Failed Attempts</span></div>';
        resultsHtml += '<div class="stat-item total"><span class="stat-number">' + results.length + '</span><span class="stat-label">Total Processed</span></div>';
        resultsHtml += '</div>';
        resultsHtml += '</div>';

        if (successCount > 0) {
            resultsHtml += '<div class="scraper-section scraper-companies">';
            resultsHtml += '<h3>🏢 Successfully Scraped Companies (' + successCount + ')</h3>';
            resultsHtml += '<div class="batch-actions">';
            resultsHtml += '<button id="export-csv-btn" class="export-btn">📊 Export as CSV</button>';
            resultsHtml += '<button id="toggle-details-btn" class="toggle-btn">👁️ Show/Hide Details</button>';
            resultsHtml += '</div>';

            resultsHtml += '<div id="companies-details" class="companies-details">';
            resultsHtml += '<div class="company-grid">';

            successResults.forEach(function(company, index) {
                resultsHtml += '<div class="company-card">';
                resultsHtml += '<div class="company-number">#' + (index + 1) + '</div>';

                if (company.company_name) {
                    resultsHtml += '<h4 class="company-name">' + escapeHtml(company.company_name) + '</h4>';
                }

                if (company.contact_name) {
                    resultsHtml += '<div class="company-contact">👤 ' + escapeHtml(company.contact_name) + '</div>';
                }

                if (company.phone) {
                    resultsHtml += '<div class="company-phone">📞 ' + escapeHtml(company.phone) + '</div>';
                }

                if (company.email) {
                    resultsHtml += '<div class="company-email">';
                    resultsHtml += '<a href="mailto:' + escapeHtml(company.email) + '">📧 ' + escapeHtml(company.email) + '</a>';
                    resultsHtml += '</div>';
                }

                if (company.city || company.province) {
                    resultsHtml += '<div class="company-location">📍 ';
                    var locationParts = [];
                    if (company.city) locationParts.push(escapeHtml(company.city));
                    if (company.province) locationParts.push(escapeHtml(company.province));
                    resultsHtml += locationParts.join(', ');
                    resultsHtml += '</div>';
                }

                if (company.website) {
                    resultsHtml += '<div class="company-website">';
                    resultsHtml += '<a href="' + escapeHtml(company.website) + '" target="_blank" rel="noopener">🌐 Visit Website</a>';
                    resultsHtml += '</div>';
                }

                if (company.member_type) {
                    resultsHtml += '<div class="company-member-type">🏷️ ' + escapeHtml(company.member_type) + '</div>';
                }

                resultsHtml += '</div>';
            });

            resultsHtml += '</div>';
            resultsHtml += '</div>';
            resultsHtml += '</div>';
        }

        if (errorCount > 0) {
            resultsHtml += '<div class="scraper-section scraper-errors">';
            resultsHtml += '<h3>❌ Failed URLs (' + errorCount + ')</h3>';
            resultsHtml += '<div class="error-toggle">';
            resultsHtml += '<button id="toggle-errors-btn" class="toggle-btn">Show/Hide Failed URLs</button>';
            resultsHtml += '</div>';
            resultsHtml += '<div id="error-details" class="error-details" style="display: none;">';
            resultsHtml += '<ul class="error-list">';

            errorResults.forEach(function(error) {
                resultsHtml += '<li>';
                resultsHtml += '<strong>' + escapeHtml(error.url) + '</strong>: ' + escapeHtml(error.message);
                resultsHtml += '</li>';
            });

            resultsHtml += '</ul>';
            resultsHtml += '</div>';
            resultsHtml += '</div>';
        }

        $('#scraper-results').html(resultsHtml);

        // Add event handlers
        $('#export-csv-btn').on('click', function() {
            exportToCSV(successResults);
        });

        $('#toggle-details-btn').on('click', function() {
            $('#companies-details').toggle();
            $(this).text($(this).text().indexOf('Show') !== -1 ? '👁️ Hide Details' : '👁️ Show Details');
        });

        $('#toggle-errors-btn').on('click', function() {
            $('#error-details').toggle();
            $(this).text($(this).text().indexOf('Show') !== -1 ? 'Hide Failed URLs' : 'Show Failed URLs');
        });

        // Hide progress section
        $('#scraper-progress-section').hide();
    }

    function exportToCSV(companies) {
        var csv = 'Company Name,Contact Name,Phone,Email,City,Province,Website,Member Type,Source URL\n';

        companies.forEach(function(company) {
            var row = [
                company.company_name || '',
                company.contact_name || '',
                company.phone || '',
                company.email || '',
                company.city || '',
                company.province || '',
                company.website || '',
                company.member_type || '',
                company.source_url || ''
            ].map(function(field) {
                // Escape quotes and wrap in quotes if contains comma
                field = String(field).replace(/"/g, '""');
                if (field.indexOf(',') !== -1 || field.indexOf('"') !== -1 || field.indexOf('\n') !== -1) {
                    field = '"' + field + '"';
                }
                return field;
            }).join(',');

            csv += row + '\n';
        });

        // Create download
        var blob = new Blob([csv], { type: 'text/csv' });
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'ontario_sign_companies_' + new Date().toISOString().slice(0, 10) + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    }

    function showError(message) {
        var errorHtml = '<div class="scraper-error">' + escapeHtml(message) + '</div>';
        $('#scraper-results').html(errorHtml);
    }
    
    function isValidUrl(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    }
    
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
});
