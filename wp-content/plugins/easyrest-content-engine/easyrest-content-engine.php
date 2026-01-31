<?php
/**
 * Plugin Name: EasyRest Content Engine
 * Plugin URI: https://easyrest.eu
 * Description: Automated SEO content generation for EasyRest Milan apartment rental.
 * Version: 1.0.0
 * Author: EasyRest
 * Author URI: https://easyrest.eu
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: easyrest-content-engine
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 *
 * @package EasyRest_Content_Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('EASYREST_CE_VERSION', '1.0.0');
define('EASYREST_CE_PLUGIN_FILE', __FILE__);
define('EASYREST_CE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EASYREST_CE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('EASYREST_CE_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
final class EasyRest_Content_Engine {

    /**
     * Plugin instance
     *
     * @var EasyRest_Content_Engine|null
     */
    private static $instance = null;

    /**
     * Get plugin instance
     *
     * @return EasyRest_Content_Engine
     */
    public static function instance(): EasyRest_Content_Engine {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies(): void {
        // Core includes
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/class-activator.php';
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/class-deactivator.php';
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/class-logger.php';
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/class-security.php';
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/class-shortcodes.php';

        // Context
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/Context/class-context-model.php';
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/Context/class-context-repository.php';

        // Domain layer (value objects & registry)
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/Domain/Queue/QueueStatus.php';
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/Domain/Queue/QueueType.php';
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/Domain/Queue/JobHandlerInterface.php';
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/Domain/ContentTypeRegistry.php';

        // Queue (interface must load before implementation)
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/Queue/QueueRepositoryInterface.php';
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/Queue/class-queue-item.php';
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/Queue/class-queue-repository.php';

        // Queue services
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/Service/Queue/JobHandlerRegistry.php';
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/Service/Queue/QueueDispatcher.php';
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/Service/Queue/QueueSelfTest.php';

        // Cron (new unified queue cron)
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/Cron/QueueCron.php';

        // Events
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/Events/class-event-parser.php';

        // Prompts
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/Prompts/class-prompt-library.php';
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/Prompts/class-prompt-renderer.php';

        // Generator
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/Generator/class-openai-client.php';
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/Generator/class-content-generator.php';
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/Generator/class-quality-scorer.php';

        // Publisher
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/Publisher/class-post-publisher.php';
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/Publisher/class-seo-adapter.php';
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/Publisher/class-internal-linker.php';

        // Scheduler
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/Scheduler/class-planner.php';
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/Scheduler/class-worker.php';

        // Channels (multi-channel distribution)
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/Channels/interface-channel-adapter.php';
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/Channels/class-channel-registry.php';
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/Channels/class-wordpress-channel.php';
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/Channels/abstract-social-channel.php';
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/Channels/class-facebook-channel.php';
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/Channels/class-linkedin-channel.php';
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/Channels/class-reddit-channel.php';
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/Channels/class-channel-distributor.php';

        // API
        require_once EASYREST_CE_PLUGIN_DIR . 'includes/API/class-rest-controller.php';

        // Admin
        if (is_admin()) {
            require_once EASYREST_CE_PLUGIN_DIR . 'includes/Admin/class-admin-controller.php';
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks(): void {
        // Activation/Deactivation
        register_activation_hook(EASYREST_CE_PLUGIN_FILE, ['EasyRest_CE_Activator', 'activate']);
        register_deactivation_hook(EASYREST_CE_PLUGIN_FILE, ['EasyRest_CE_Deactivator', 'deactivate']);

        // Run migrations on plugins_loaded (catches upgrades, not just activation)
        // Priority 5 to run early, before other hooks that might depend on new schema
        add_action('plugins_loaded', ['EasyRest_CE_Activator', 'maybe_run_migrations'], 5);

        // Cron intervals are registered by QueueCron::register() (5-min + 15-min)

        // Init
        add_action('init', [$this, 'init']);
        add_action('init', [$this, 'register_post_types']);
        add_action('init', [$this, 'register_taxonomies']);

        // REST API
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // Admin
        if (is_admin()) {
            $admin = new EasyRest_CE_Admin_Controller();
            $admin->init();
        }

        // Shortcodes
        add_action('init', [$this, 'register_shortcodes']);

        // Channels
        add_action('init', [$this, 'register_channels']);
        add_action('init', [$this, 'init_channel_distributor']);

        // Cron hooks — legacy planner (daily content planning)
        add_action('easyrest_ce_planner_cron', [$this, 'run_planner']);

        // New unified queue cron (replaces legacy easyrest_ce_worker_cron)
        EasyRest_CE_Queue_Cron::instance()->register();

        // Ensure cron events are scheduled (self-healing on admin visits)
        add_action('admin_init', [ EasyRest_CE_Queue_Cron::instance(), 'ensure_scheduled' ]);
    }

    /**
     * Add custom cron intervals.
     *
     * @deprecated Intervals are now registered by QueueCron::register().
     * @param array $schedules Existing schedules.
     * @return array Unmodified schedules.
     */
    public function add_cron_intervals(array $schedules): array {
        return $schedules;
    }

    /**
     * Plugin initialization
     */
    public function init(): void {
        load_plugin_textdomain(
            'easyrest-content-engine',
            false,
            dirname(EASYREST_CE_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Register Custom Post Type: easyrest_guide
     *
     * This CPT stores all AI-generated guides (weekly_guide, sport_guide, etc.).
     * The Content Engine's Post Publisher automatically uses this CPT for all guide content.
     *
     * REST API: Enabled at /wp-json/wp/v2/easyrest_guide
     * Block Editor: Gutenberg fully supported
     * Archive URL: /guides/
     * Single URL: /guides/{slug}/
     *
     * ============================================================================
     * TESTING GUIDE
     * ============================================================================
     *
     * 1. MANUAL TEST IN WP ADMIN:
     *    - Navigate to "EasyRest Guides" in the admin menu (dashicons-location-alt icon)
     *    - Click "Add New Guide"
     *    - Verify: title, editor (Gutenberg), excerpt, featured image are available
     *    - Save and verify permalink uses /guides/{slug}/ format
     *
     * 2. CONTENT ENGINE TEST:
     *    - Use WP-CLI: wp easyrest-ce queue:process --item=<ID>
     *    - Or trigger via cron: do_action('easyrest_ce_worker_cron')
     *    - After generation, verify the new post appears under "EasyRest Guides"
     *      (NOT under regular "Posts")
     *    - Check post_type in database: SELECT post_type FROM wp_posts WHERE ID = <new_id>
     *
     * 3. REST API TEST:
     *    - GET /wp-json/wp/v2/easyrest_guide - should list guides
     *    - GET /wp-json/wp/v2/easyrest_guide/<id> - should return single guide
     *
     * 4. REGRESSION CHECK:
     *    - Regular blog posts (post_type 'post') still work normally
     *    - Other pages/CPTs are unaffected
     *
     * IMPORTANT: After changing the 'rewrite' slug, flush permalinks:
     *            Settings > Permalinks > Save Changes
     * ============================================================================
     */
    public function register_post_types(): void {
        register_post_type('easyrest_guide', [
            'labels' => [
                'name'               => __('Guides', 'easyrest-content-engine'),
                'singular_name'      => __('Guide', 'easyrest-content-engine'),
                'menu_name'          => __('EasyRest Guides', 'easyrest-content-engine'),
                'add_new'            => __('Add New Guide', 'easyrest-content-engine'),
                'add_new_item'       => __('Add New Guide', 'easyrest-content-engine'),
                'edit_item'          => __('Edit Guide', 'easyrest-content-engine'),
                'view_item'          => __('View Guide', 'easyrest-content-engine'),
                'search_items'       => __('Search Guides', 'easyrest-content-engine'),
                'not_found'          => __('No guides found', 'easyrest-content-engine'),
                'not_found_in_trash' => __('No guides found in trash', 'easyrest-content-engine'),
                'all_items'          => __('All Guides', 'easyrest-content-engine'),
                'archives'           => __('Guide Archives', 'easyrest-content-engine'),
            ],
            'public'              => true,
            'has_archive'         => true,
            'rewrite'             => ['slug' => 'guides', 'with_front' => true],
            'supports'            => ['title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions'],
            'show_in_rest'        => true,
            'menu_icon'           => 'dashicons-location-alt',
            'menu_position'       => 25,
            'show_in_nav_menus'   => true,
            'publicly_queryable'  => true,
            'exclude_from_search' => false,
        ]);
    }

    /**
     * Register Taxonomies
     */
    public function register_taxonomies(): void {
        // Topic taxonomy (hierarchical)
        register_taxonomy('easyrest_topic', 'easyrest_guide', [
            'labels' => [
                'name'          => __('Topics', 'easyrest-content-engine'),
                'singular_name' => __('Topic', 'easyrest-content-engine'),
                'search_items'  => __('Search Topics', 'easyrest-content-engine'),
                'all_items'     => __('All Topics', 'easyrest-content-engine'),
                'edit_item'     => __('Edit Topic', 'easyrest-content-engine'),
                'add_new_item'  => __('Add New Topic', 'easyrest-content-engine'),
            ],
            'hierarchical'      => true,
            'public'            => true,
            'rewrite'           => ['slug' => 'topic'],
            'show_in_rest'      => true,
            'show_admin_column' => true,
        ]);

        // Language taxonomy
        register_taxonomy('easyrest_lang', 'easyrest_guide', [
            'labels' => [
                'name'          => __('Languages', 'easyrest-content-engine'),
                'singular_name' => __('Language', 'easyrest-content-engine'),
            ],
            'hierarchical'      => false,
            'public'            => true,
            'rewrite'           => ['slug' => 'lang'],
            'show_in_rest'      => true,
            'show_admin_column' => true,
        ]);

        // Content Type taxonomy
        register_taxonomy('easyrest_content_type', 'easyrest_guide', [
            'labels' => [
                'name'          => __('Content Types', 'easyrest-content-engine'),
                'singular_name' => __('Content Type', 'easyrest-content-engine'),
            ],
            'hierarchical'      => false,
            'public'            => false,
            'show_in_rest'      => true,
            'show_admin_column' => true,
        ]);

        // Insert default terms on first run
        $this->maybe_insert_default_terms();
    }

    /**
     * Insert default taxonomy terms if not exist
     */
    private function maybe_insert_default_terms(): void {
        if (get_option('easyrest_ce_terms_inserted')) {
            return;
        }

        // Topics
        $topics = [
            'jo-2026'        => 'JO 2026',
            'hockey'         => 'Hockey',
            'figure-skating' => 'Figure Skating',
            'skiing'         => 'Skiing',
            'biathlon'       => 'Biathlon',
            'ceremony'       => 'Ceremony',
            'transport'      => 'Transport',
            'san-siro'       => 'San Siro',
            'serie-a'        => 'Serie A',
            'fashion-week'   => 'Fashion Week',
            'design-week'    => 'Design Week',
            'milan-city'     => 'Milan City',
        ];

        foreach ($topics as $slug => $name) {
            if (!term_exists($slug, 'easyrest_topic')) {
                wp_insert_term($name, 'easyrest_topic', ['slug' => $slug]);
            }
        }

        // Languages
        $languages = ['en' => 'English', 'fr' => 'Français', 'de' => 'Deutsch', 'it' => 'Italiano', 'es' => 'Español'];
        foreach ($languages as $slug => $name) {
            if (!term_exists($slug, 'easyrest_lang')) {
                wp_insert_term($name, 'easyrest_lang', ['slug' => $slug]);
            }
        }

        // Content Types
        $content_types = [
            'weekly'             => 'Weekly',
            'sport_guide'        => 'Sport Guide',
            'nationality_guide'  => 'Nationality Guide',
            'transport'          => 'Transport',
            'match_preview'      => 'Match Preview',
            'event_guide'        => 'Event Guide',
            'venue_guide'        => 'Venue Guide',
            'neighborhood_guide' => 'Neighborhood Guide',
            'daytrip_guide'      => 'Daytrip Guide',
            'seasonal'           => 'Seasonal',
        ];

        foreach ($content_types as $slug => $name) {
            if (!term_exists($slug, 'easyrest_content_type')) {
                wp_insert_term($name, 'easyrest_content_type', ['slug' => $slug]);
            }
        }

        update_option('easyrest_ce_terms_inserted', true);
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes(): void {
        $rest_controller = new EasyRest_CE_REST_Controller();
        $rest_controller->register_routes();
    }

    /**
     * Register shortcodes
     */
    public function register_shortcodes(): void {
        $shortcodes = new EasyRest_CE_Shortcodes();
        $shortcodes->register();
    }

    /**
     * Register channel adapters
     *
     * Registers all built-in channel adapters with the Channel Registry.
     * Third-party plugins can hook into 'easyrest_ce_register_channels' to add custom channels.
     */
    public function register_channels(): void {
        $registry = EasyRest_CE_Channel_Registry::instance();

        // Register built-in channels
        $registry->register(new EasyRest_CE_WordPress_Channel());
        $registry->register(new EasyRest_CE_Facebook_Channel());
        $registry->register(new EasyRest_CE_LinkedIn_Channel());
        $registry->register(new EasyRest_CE_Reddit_Channel());

        // Allow third-party plugins to register custom channels
        do_action('easyrest_ce_register_channels', $registry);

        EasyRest_CE_Logger::debug('Channel registry initialized', [
            'registered_channels' => $registry->get_channel_ids(),
            'enabled_channels'    => $registry->get_enabled_channel_ids(),
        ]);
    }

    /**
     * Initialize the Channel Distributor
     *
     * Sets up hooks for distributing WordPress content to social channels.
     */
    public function init_channel_distributor(): void {
        $distributor = new EasyRest_CE_Channel_Distributor();
        $distributor->init();
    }

    /**
     * Run planner (cron hook)
     */
    public function run_planner(): void {
        $planner = new EasyRest_CE_Planner();
        $planner->run();
    }

    /**
     * Run worker (cron hook)
     */
    public function run_worker(): void {
        $worker = new EasyRest_CE_Worker();
        $worker->run();
    }
}

/**
 * Initialize plugin
 */
function easyrest_content_engine(): EasyRest_Content_Engine {
    return EasyRest_Content_Engine::instance();
}

// Boot the plugin
easyrest_content_engine();
