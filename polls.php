<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Plugin;
use Grav\Events\FlexRegisterEvent;
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

    public $features = [
        'blueprints' => 0,
    ];

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
            ],
            FlexRegisterEvent::class       => [['onRegisterFlex', 0]],
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

    public function onRegisterFlex($event): void
    {
        $flex = $event->flex;

        $flex->addDirectoryType(
            'polls',
            'blueprints://flex-objects/polls.yaml'
        );

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
                'onTwigSiteVariables' => ['onTwigAdminSiteVariables', 0],
            ]);
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
            } elseif ($uri->query('view') === 'poll') {
                $result = $polls->showPoll();
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


    public function onTwigAdminSiteVariables()
    {
        $this->grav['assets']->addJs('plugin://polls/assets/admin/polls.js');
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
