<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Plugin;
use Grav\Plugin\Polls\PollsController;
use Grav\Plugin\Polls\PollsManager;
use RocketTheme\Toolbox\Event\Event;

/**
 * Class PollsPlugin
 * @package Grav\Plugin
 */
class PollsPlugin extends Plugin
{
    protected $admin_route = 'polls';

    /**
     * @return array
     *
     * The getSubscribedEvents() gives the core a list of events
     *     that the plugin wants to listen to. The key of each
     *     array section is the event that the plugin listens to
     *     and the value (in the form of an array) contains the
     *     callable (or function) as well as the priority. The
     *     higher the number the higher the priority.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => [
                ['onPluginsInitialized', 0],
            ]
        ];
    }

    /**
     * Composer autoload
     *
     * @return ClassLoader
     */
    public function autoload(): ClassLoader
    {
        return require __DIR__ . '/vendor/autoload.php';
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized(): void
    {
        $this->grav['polls'] = new PollsManager();

        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            $this->enable([
                'onAdminMenu' => ['onAdminMenu', 0],
                'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
                'onDataTypeExcludeFromDataManagerPluginHook' => ['onDataTypeExcludeFromDataManagerPluginHook', 0],
            ]);

            if ($this->isPluginActiveAdmin($this->admin_route)) {
                $this->enable([
                    'onTwigTemplatePaths' => ['onTwigAdminTemplatePaths', 0],
                    'onAdminTaskExecute' => ['onAdminTaskExecute', 0],
                ]);
            }
            return;
        }

        // Enable the main events we are interested in
        $this->enable([
            'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
            'onShortcodeHandlers' => ['onShortcodeHandlers', 0],
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
            'onPageInitialized'   => ['onPageInitialized', 0],
        ]);
    }

    public function onPageInitialized(Event $e)
    {
        $uri = $this->grav['uri'];
        $callback = $this->config->get('plugins.polls.callback');
        $route = $uri->path();
        // Process vote if appropriate
        if ($callback === $route) {
            /** @var PollsManager $polls */
            $polls = $this->grav['polls'];

            if ($uri->query('view') === 'results') {
                $result = $polls->showResults();

            } else {
                $result = $polls->processVote();
            }

            header('Content-Type: application/json');
            echo json_encode(['code' => $result[0], 'message' => $result[1], 'content' => $result[2] ?? '']);
            exit();
        }
    }

    public function onShortcodeHandlers()
    {
        $this->grav['shortcode']->registerAllShortcodes(__DIR__ . '/classes/shortcodes');
    }

    public function onTwigAdminTemplatePaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/admin/templates';
    }

    public function onAdminTaskExecute(Event $event)
    {
        $controller = new PollsController($event['controller'], $event['method']);

        return $controller->execute();
    }

    /**
     * Add License Manager to admin menu
     */
    public function onAdminMenu()
    {

        $this->grav['twig']->plugins_hooked_nav['PLUGIN_POLLS.TITLE'] = ['route' => $this->admin_route, 'icon' => 'fa-check-square'];
    }

    /**
     * Exclude Polls from DataManager plugin
     * @return void
     */
    public function onDataTypeExcludeFromDataManagerPluginHook()
    {
        $this->grav['admin']->dataTypesExcludedFromDataManagerPlugin[] = 'polls';
    }

    public function onTwigSiteVariables()
    {
        if ($this->config->get('plugins.polls.built_in_css')) {
           $this->grav['assets']
               ->addCss('plugin://polls/assets/poll.css');
        }
        $this->grav['assets']
           ->addJs('plugin://polls/assets/poll.js');

        $this->grav['twig']->twig_vars['polls'] = $this->grav['polls'];
    }

    public function onTwigTemplatePaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }
}
