<?php
/**
 * Plugin Name: AI SEO Automation
 * Description: Automated SEO optimization using AI
 * Version: 1.1.1
 * Author: Dezefy LLC
 * Update URI: https://github.com/dezefy/ai-seo-automation
 */
 
if (!defined('ABSPATH')) {
    exit;
}

// Include HTML to Markdown library
require __DIR__ . '/vendor/autoload.php';
use League\HTMLToMarkdown\HtmlConverter;
use League\HTMLToMarkdown\Converter\TableConverter;
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// Build the update checker (point it at your repo).
$updateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/dezefy/ai-seo-automation/', // Repo URL
    __FILE__,                                // Main plugin file
    'ai-seo-automation'                         // Plugin slug (unique)
);

// If your stable code is on a branch other than 'master':
$updateChecker->setBranch('main');

// If you publish GitHub Releases and attach a ZIP asset (recommended):
$updateChecker->getVcsApi()->enableReleaseAssets();

class AISEOPlugin {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_ai_seo_process', array($this, 'ajax_process_post'));
        add_action('wp_ajax_ai_seo_bulk_process', array($this, 'ajax_bulk_process'));
        add_action('wp_ajax_ai_seo_get_posts', array($this, 'ajax_get_posts'));
        add_action('wp_ajax_ai_seo_get_media', array($this, 'ajax_get_media'));
        add_action('wp_ajax_ai_seo_process_media', array($this, 'ajax_process_media'));
        add_action('wp_ajax_ai_seo_bulk_process_media', array($this, 'ajax_bulk_process_media'));
    }
    
    public function init() {
        // Initialize plugin
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'AI SEO',
            'AI SEO',
            'manage_options',
            'ai-seo',
            array($this, 'settings_page'),
            'dashicons-search',
            30
        );
        
        add_submenu_page(
            'ai-seo',
            'Settings',
            'Settings', 
            'manage_options',
            'ai-seo',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'ai-seo',
            'SEO Automation',
            'SEO Automation',
            'manage_options',
            'ai-seo-automation',
            array($this, 'automation_page')
        );
        add_submenu_page(
            'ai-seo',
            'Media Automation',
            'Media Automation',
            'manage_options',
            'ai-seo-media',
            array($this, 'media_page')
        );
    }
    
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'ai-seo') !== false) {
            wp_enqueue_script('ai-seo-admin', plugin_dir_url(__FILE__) . 'admin.js', array('jquery'), '1.1.1', true);
            wp_localize_script('ai-seo-admin', 'ai_seo_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ai_seo_nonce')
            ));
        }
    }
    
    public function settings_page() {
        if (isset($_POST['submit'])) {
            update_option('ai_seo_company_name', sanitize_text_field($_POST['company_name']));
            update_option('ai_seo_keywords', sanitize_textarea_field($_POST['keywords']));
            update_option('ai_seo_api_key', sanitize_text_field($_POST['api_key']));
            update_option('ai_seo_model', sanitize_text_field($_POST['model']));
            update_option('ai_seo_prompt', sanitize_textarea_field($_POST['prompt']));
            update_option('ai_seo_plugin', sanitize_text_field($_POST['seo_plugin']));
            update_option('ai_seo_media_prompt', sanitize_textarea_field($_POST['media_prompt']));
            echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
        }
        
        $company_name = get_option('ai_seo_company_name', '');
        $keywords = get_option('ai_seo_keywords', '');
        $api_key = get_option('ai_seo_api_key', '');
        $model = get_option('ai_seo_model', 'x-ai/grok-4-fast:free');
        $media_prompt = stripslashes(get_option('ai_seo_media_prompt', 'Generate a descriptive, SEO-friendly alt text for this image. Company: {company_name}. Image URL: {image_url}. Return JSON format: {"alt_text": "descriptive alt text here"}'));
        
        $prompt = stripslashes(get_option('ai_seo_prompt', 'Generate SEO meta title and description for the following content. Company: {company_name}. Target keywords: {keywords}. Content: {content}. Return JSON format: {"meta_title": "title here", "meta_description": "description here"}'));
        $seo_plugin = get_option('ai_seo_plugin', $this->detect_seo_plugin());
        
        ?>
        <div class="wrap">
            <h1>AI SEO Settings</h1>
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th><label for="company_name">Company Name</label></th>
                        <td><input type="text" id="company_name" name="company_name" value="<?php echo esc_attr($company_name); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="keywords">Main Keywords</label></th>
                        <td><textarea id="keywords" name="keywords" rows="3" cols="50"><?php echo esc_textarea($keywords); ?></textarea>
                        <p class="description">Comma separated keywords</p></td>
                    </tr>
                    <tr>
                        <th><label for="api_key">OpenRouter API Key</label></th>
                        <td><input type="password" id="api_key" name="api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="model">AI Model</label></th>
                        <td>
                            <select id="model" name="model">
                                <option value="x-ai/grok-4-fast:free" <?php selected($model, 'x-ai/grok-4-fast:free'); ?>>Grok 4 Fast (Free)</option>
                                <option value="google/gemini-2.0-flash-exp:free" <?php selected($model, 'google/gemini-2.0-flash-exp:free'); ?>>Gemini 2.0 Flash (Free)</option>
                                <option value="meta-llama/llama-4-maverick:free" <?php selected($model, 'meta-llama/llama-4-maverick:free'); ?>>Llama 4 Maverick (Free)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="prompt">AI Prompt</label></th>
                        <td><textarea id="prompt" name="prompt" rows="5" cols="50"><?php echo htmlspecialchars($prompt, ENT_QUOTES, 'UTF-8'); ?></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="media_prompt">Media Alt Text Prompt</label></th>
                        <td><textarea id="media_prompt" name="media_prompt" rows="5" cols="50"><?php echo esc_textarea($media_prompt); ?></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="seo_plugin">SEO Plugin</label></th>
                        <td>
                            <select id="seo_plugin" name="seo_plugin">
                                <option value="yoast" <?php selected($seo_plugin, 'yoast'); ?>>Yoast SEO</option>
                                <option value="rankmath" <?php selected($seo_plugin, 'rankmath'); ?>>RankMath</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    public function automation_page() {
        ?>
        <div class="wrap">
            <h1>SEO Automation</h1>
            
            <div style="margin-bottom: 20px;">
                <label for="post_type">Select Post Type:</label>
                <select id="post_type" onchange="loadPosts()">
                    <?php
                    $post_types = get_post_types(array('public' => true), 'objects');
                    foreach ($post_types as $post_type) {
                        echo '<option value="' . $post_type->name . '">' . $post_type->label . '</option>';
                    }
                    ?>
                </select>
                <button type="button" class="button" onclick="bulkProcess()">Bulk Process All</button>
            </div>
            
            <div id="posts-table"></div>
        </div>
        
        <script>
        function loadPosts() {
            const postType = document.getElementById('post_type').value;
            const data = {
                action: 'ai_seo_get_posts',
                post_type: postType,
                nonce: ai_seo_ajax.nonce
            };
            
            jQuery.post(ai_seo_ajax.ajax_url, data, function(response) {
                document.getElementById('posts-table').innerHTML = response;
            });
        }
        
        function processPost(postId, keyword) {
            const data = {
                action: 'ai_seo_process',
                post_id: postId,
                keyword: keyword,
                nonce: ai_seo_ajax.nonce
            };
            
            jQuery.post(ai_seo_ajax.ajax_url, data, function(response) {
                const result = JSON.parse(response);
                if (result.success) {
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            });
        }
        
        function bulkProcess() {
            const postType = document.getElementById('post_type').value;
            const data = {
                action: 'ai_seo_bulk_process',
                post_type: postType,
                nonce: ai_seo_ajax.nonce
            };
            
            jQuery.post(ai_seo_ajax.ajax_url, data, function(response) {
                const result = JSON.parse(response);
                alert(result.message);
                location.reload();
            });
        }
        
        // Load posts on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadPosts();
        });
        </script>
        <?php
    }

    public function media_page() {
        ?>
        <div class="wrap">
            <h1>Media Automation</h1>
            
            <div style="margin-bottom: 20px;">
                <button type="button" class="button button-primary" onclick="loadMediaItems()">Load Media Items</button>
                <button type="button" class="button" onclick="bulkProcessMedia()">Bulk Process All</button>
            </div>
            
            <div id="media-table"></div>
        </div>
        <?php
    }
    
    public function ajax_get_posts() {
        check_ajax_referer('ai_seo_nonce', 'nonce');
        
        $post_type = sanitize_text_field($_POST['post_type']);
        $posts = get_posts(array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'numberposts' => 50
        ));
        
        $keywords = array_map('trim', explode(',', get_option('ai_seo_keywords', '')));
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>ID</th><th>Post Title</th><th>Current Meta Title</th><th>Current Meta Description</th><th>AI Suggested Title</th><th>AI Suggested Description</th><th>Action</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($posts as $post) {
            $current_title = $this->get_meta_title($post->ID);
            $current_desc = $this->get_meta_description($post->ID);
            
            echo '<tr>';
            echo '<td>' . $post->ID . '</td>';
            echo '<td>' . esc_html($post->post_title) . '</td>';
            echo '<td>' . esc_html($current_title) . '</td>';
            echo '<td>' . esc_html($current_desc) . '</td>';
            echo '<td id="ai-title-' . $post->ID . '">-</td>';
            echo '<td id="ai-desc-' . $post->ID . '">-</td>';
            echo '<td>';
            echo '<select id="keyword-' . $post->ID . '">';
            foreach ($keywords as $keyword) {
                echo '<option value="' . esc_attr($keyword) . '">' . esc_html($keyword) . '</option>';
            }
            echo '</select>';
            echo '<button type="button" class="button" onclick="processPost(' . $post->ID . ', document.getElementById(\'keyword-' . $post->ID . '\').value)">Process</button>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        wp_die();
    }
    
    public function ajax_process_post() {
        check_ajax_referer('ai_seo_nonce', 'nonce');
        
        $post_id = intval($_POST['post_id']);
        $keyword = sanitize_text_field($_POST['keyword']);
        
        $result = $this->process_post_seo($post_id, $keyword);
        
        wp_die(json_encode($result));
    }
    
    public function ajax_bulk_process() {
        check_ajax_referer('ai_seo_nonce', 'nonce');
        
        $post_type = sanitize_text_field($_POST['post_type']);
        $posts = get_posts(array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'numberposts' => 10
        ));
        
        $keywords = array_map('trim', explode(',', get_option('ai_seo_keywords', '')));
        $processed = 0;
        
        foreach ($posts as $post) {
            $keyword = !empty($keywords) ? $keywords[0] : '';
            $this->process_post_seo($post->ID, $keyword);
            $processed++;
        }
        
        wp_die(json_encode(array('success' => true, 'message' => "Processed {$processed} posts")));
    }
    
    private function process_post_seo($post_id, $keyword) {
        $post = get_post($post_id);
        if (!$post) {
            return array('success' => false, 'message' => 'Post not found');
        }
        
        // Get post content via curl to simulate external access
        $post_url = get_permalink($post_id);
        $content = $this->fetch_and_clean_content($post_url);
        
        if (!$content) {
            return array('success' => false, 'message' => 'Could not fetch content');
        }
        
        // Generate AI suggestions
        $ai_result = $this->generate_seo_with_ai($content, $keyword);
        
        if (!$ai_result) {
            return array('success' => false, 'message' => 'AI generation failed');
        }
        
        // Update meta fields
        $this->update_seo_meta($post_id, $ai_result['meta_title'], $ai_result['meta_description']);
        
        return array('success' => true, 'ai_title' => $ai_result['meta_title'], 'ai_description' => $ai_result['meta_description']);
    }
    
    /**
     * Fetch a URL, optionally pre-clean, and convert to Markdown.
     *
     * @param string $url       The webpage to convert
     * @param bool   $debugHtml When true, dumps raw HTML in a <textarea> for debugging
     * @return string           Clean Markdown text
     * @throws RuntimeException If cURL or conversion fails
     */
    private function convertPageToMarkdown(string $url, bool $debugHtml = false): string {
        // 1) Fetch HTML via cURL
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; HTML-to-Markdown Bot/1.0)',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $html = curl_exec($ch);
        if ($html === false) {
            throw new RuntimeException('cURL error: ' . curl_error($ch));
        }
        curl_close($ch);
        
        if ($debugHtml) {
            error_log('AI SEO Plugin - Raw HTML: ' . substr($html, 0, 1000));
        }
        
        // 2) Optional pre-clean with DOM (currently minimal—expand as needed)
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
        $cleanHtml = $dom->saveHTML() ?: $html;
        
        // 3) Convert HTML to Markdown with recommended settings
        $converter = new HtmlConverter([
            'strip_tags'             => true,
            'remove_nodes'           => 'img script style noscript iframe svg canvas template form input button select option label dialog aside picture source video audio link meta object embed',
            'strip_placeholder_links'=> true,
            'use_autolinks'          => false,
            'hard_break'             => false,
            'header_style'           => 'atx',
        ]);
        
        // enable Markdown tables
        $converter->getEnvironment()->addConverter(new TableConverter());
        $markdown = $converter->convert($cleanHtml);
        
        // Clean up extra whitespace
        $markdown = preg_replace("/[ \t]+\n/", "\n", $markdown);
        $markdown = preg_replace("/\n{3,}/", "\n\n", $markdown);
        
        return trim($markdown);
    }
    
    private function fetch_and_clean_content($url) {
        try {
            $markdown = $this->convertPageToMarkdown($url);
            
            // Convert markdown back to plain text for AI processing
            $text = strip_tags($markdown);
            $text = preg_replace('/\s+/', ' ', $text);
            
            return trim($text);
        } catch (Exception $e) {
            error_log('AI SEO Plugin: Content fetch error - ' . $e->getMessage());
            
            // Fallback to basic HTML cleanup
            return $this->fetch_and_clean_content_fallback($url);
        }
    }
    
    private function fetch_and_clean_content_fallback($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; SEO Bot)');
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $html = curl_exec($ch);
        curl_close($ch);
        
        if (!$html) return false;
        
        // Basic HTML cleanup - extract main content
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $html);
        $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $html);
        $html = preg_replace('/<header\b[^<]*(?:(?!<\/header>)<[^<]*)*<\/header>/mi', '', $html);
        $html = preg_replace('/<footer\b[^<]*(?:(?!<\/footer>)<[^<]*)*<\/footer>/mi', '', $html);
        $html = preg_replace('/<nav\b[^<]*(?:(?!<\/nav>)<[^<]*)*<\/nav>/mi', '', $html);
        
        // Extract text content
        $text = wp_strip_all_tags($html);
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }
    
    private function generate_seo_with_ai($content, $keyword) {
        $api_key = get_option('ai_seo_api_key');
        $model = get_option('ai_seo_model', 'x-ai/grok-4-fast:free');
        $prompt_template = get_option('ai_seo_prompt');
        $company_name = get_option('ai_seo_company_name');
        
        if (!$api_key) return false;
        
        $prompt = str_replace(
            array('{company_name}', '{keywords}', '{content}'),
            array($company_name, $keyword, substr($content, 0, 2000)),
            $prompt_template
        );
        
        $data = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'user', 
                    'content' => $prompt
                )
            )
        );
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://openrouter.ai/api/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json'
        ));
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            error_log('AI SEO Plugin: cURL error - ' . $curl_error);
            return false;
        }
        
        if ($http_code !== 200) {
            error_log('AI SEO Plugin: HTTP error - ' . $http_code . ' Response: ' . $response);
            return false;
        }
        
        $result = json_decode($response, true);
        
        if (!isset($result['choices'][0]['message']['content'])) {
            error_log('AI SEO Plugin: Invalid API response - ' . print_r($result, true));
            return false;
        }
        
        $ai_content = $result['choices'][0]['message']['content'];
        
        // Try to extract JSON from the response
        if (preg_match('/\{.*\}/', $ai_content, $matches)) {
            $seo_data = json_decode($matches[0], true);
            if ($seo_data && isset($seo_data['meta_title']) && isset($seo_data['meta_description'])) {
                return $seo_data;
            }
        }
        
        // Fallback: try to parse the entire response as JSON
        $seo_data = json_decode($ai_content, true);
        if ($seo_data && isset($seo_data['meta_title']) && isset($seo_data['meta_description'])) {
            return $seo_data;
        }
        
        error_log('AI SEO Plugin: Could not parse AI response - ' . $ai_content);
        return false;
    }
    
    private function get_meta_title($post_id) {
        $seo_plugin = get_option('ai_seo_plugin', 'yoast');
        
        if ($seo_plugin === 'yoast') {
            return get_post_meta($post_id, '_yoast_wpseo_title', true);
        } else {
            return get_post_meta($post_id, 'rank_math_title', true);
        }
    }
    
    private function get_meta_description($post_id) {
        $seo_plugin = get_option('ai_seo_plugin', 'yoast');
        
        if ($seo_plugin === 'yoast') {
            return get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
        } else {
            return get_post_meta($post_id, 'rank_math_description', true);
        }
    }
    
    private function update_seo_meta($post_id, $title, $description) {
        $seo_plugin = get_option('ai_seo_plugin', 'yoast');
        
        if ($seo_plugin === 'yoast') {
            update_post_meta($post_id, '_yoast_wpseo_title', $title);
            update_post_meta($post_id, '_yoast_wpseo_metadesc', $description);
        } else {
            update_post_meta($post_id, 'rank_math_title', $title);
            update_post_meta($post_id, 'rank_math_description', $description);
        }
    }
    
    private function detect_seo_plugin() {
        if (is_plugin_active('wordpress-seo/wp-seo.php')) {
            return 'yoast';
        } elseif (is_plugin_active('seo-by-rank-math/rank-math.php')) {
            return 'rankmath';
        }
        return 'yoast'; // default
    }

    public function ajax_get_media() {
        check_ajax_referer('ai_seo_nonce', 'nonce');
        
        $attachments = get_posts(array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'numberposts' => 50
        ));
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>ID</th><th>Image</th><th>Filename</th><th>Current Alt Text</th><th>AI Suggested Alt</th><th>Action</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($attachments as $attachment) {
            $current_alt = get_post_meta($attachment->ID, '_wp_attachment_image_alt', true);
            $image_url = wp_get_attachment_url($attachment->ID);
            $thumbnail = wp_get_attachment_image($attachment->ID, array(80, 80));
            
            echo '<tr>';
            echo '<td>' . $attachment->ID . '</td>';
            echo '<td>' . $thumbnail . '</td>';
            echo '<td>' . basename($image_url) . '</td>';
            echo '<td>' . esc_html($current_alt) . '</td>';
            echo '<td id="ai-alt-' . $attachment->ID . '">-</td>';
            echo '<td>';
            echo '<button type="button" class="button" onclick="processMedia(' . $attachment->ID . ')">Process</button>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        wp_die();
    }

    public function ajax_process_media() {
        check_ajax_referer('ai_seo_nonce', 'nonce');
        
        $attachment_id = intval($_POST['attachment_id']);
        
        $result = $this->process_media_alt($attachment_id);
        
        wp_die(json_encode($result));
    }
    
    private function process_media_alt($attachment_id) {
        $image_url = wp_get_attachment_url($attachment_id);
        
        if (!$image_url) {
            return array('success' => false, 'message' => 'Image not found');
        }
        
        $ai_result = $this->generate_alt_text_with_ai($image_url);

        
        if (!$ai_result || !isset($ai_result['alt_text'])) {
            return array('success' => false, 'message' => 'AI generation failed');
        }
        
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $ai_result['alt_text']);
        
        return array('success' => true, 'alt_text' => $ai_result['alt_text']);
    }
    
    private function generate_alt_text_with_ai($image_url) {
        $api_key = get_option('ai_seo_api_key');
        $model = get_option('ai_seo_model', 'x-ai/grok-4-fast:free');
        $prompt_template = stripslashes(get_option('ai_seo_media_prompt'));
        $company_name = get_option('ai_seo_company_name');
        $keywords = get_option('ai_seo_keywords', '');
        
        if (!$api_key) return false;
        
        $prompt = str_replace(
            array('{company_name}', '{keywords}', '{image_url}'),
            array($company_name, $keywords, $image_url),
            $prompt_template
        );
        
        $data = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => array(
                        array('type' => 'text', 'text' => $prompt),
                        array('type' => 'image_url', 'image_url' => array('url' => $image_url))
                    )
                )
            )
        );
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://openrouter.ai/api/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json'
        ));
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if (!isset($result['choices'][0]['message']['content'])) {
            return false;
        }
        
        $ai_content = $result['choices'][0]['message']['content'];

        // Remove markdown code blocks if present
        $ai_content = preg_replace('/```json\s*|\s*```/', '', $ai_content);
        $ai_content = trim($ai_content);

        // Try to extract JSON
        if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $ai_content, $matches)) {
            $alt_data = json_decode($matches[0], true);
            if ($alt_data && isset($alt_data['alt_text'])) {
                return $alt_data;
            }
        }

        // Fallback: try direct decode
        $alt_data = json_decode($ai_content, true);
        if ($alt_data && isset($alt_data['alt_text'])) {
            return $alt_data;
        }

        error_log('AI SEO Plugin: Could not parse alt text response - ' . $ai_content);
        
        return false;
    }

    public function ajax_bulk_process_media() {
        check_ajax_referer('ai_seo_nonce', 'nonce');
        
        $attachments = get_posts(array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'numberposts' => 10
        ));
        
        $processed = 0;
        
        foreach ($attachments as $attachment) {
            $this->process_media_alt($attachment->ID);
            $processed++;
        }
        
        wp_die(json_encode(array('success' => true, 'message' => "Processed {$processed} images")));
    }
}

new AISEOPlugin();
?>