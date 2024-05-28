<?php

namespace Grav\Plugin\Polls;

use Grav\Common\Config\Config;
use Grav\Common\Data\Blueprint;
use Grav\Common\Data\Data;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Inflector;
use Grav\Common\Uri;
use Grav\Common\Utils;

class PollsManager
{
    protected $blueprint;
    protected $data_file;
    protected $data_path;
    protected $data;
    protected $config;

    public function __construct()
    {
        $this->init();
    }

    public function init()
    {
        $blueprint = new Blueprint('plugin://polls/admin/blueprints/polls.yaml');
        $blueprint->load();
        $this->blueprint = $blueprint;

        $path = Grav::instance()['locator']->findResource('user-data://polls', true, true);
        $this->data_path = $path  . '/polls.yaml';
        $this->data_file = CompiledYamlFile::instance($this->data_path);

        $data = new Data($this->data_file->content(), $this->blueprint);
        $data->file($this->data_file);
        $this->data = $data;

        $config = Grav::instance()['config']->get('plugins.polls');
        $this->config = new Config($config);
    }

    public function getId(string $id = null): ?string
    {
        if (null === $id) {
            $polls = $this->getPolls(['enabled' => true]);
            $id = $polls[0]['id'] ?? null;
        }
        return $id;
    }

    public function getPolls(array $filters = [])
    {
        if (!empty($filters)) {
            $polls = [];
            foreach ($this->data_file->content()['polls'] as $poll) {
                $match = true;
                foreach ($filters as $key => $value) {
                    if ($poll[$key] !== $value) {
                        $match = false;
                        break;
                    }
                }
                if ($match) {
                    $polls[] = $poll;
                }
            }
            return $polls;
        } else {
            return $this->data_file->content()['polls'] ?? [];
        }
    }

    public function getPoll(string $id, array $filters = []): ?array
    {
        $polls = $this->getPolls($filters);
        foreach ($polls as $poll) {
            if ($poll['id'] === $id) {
                return $poll;
            }
        }
        return null;
    }

    public function getPollsByDate(array $filters = []): array
    {
        $polls = $this->getPolls($filters);
        usort($polls, function ($a, $b) {
            return Utils::date2timestamp($a['created_at']) <=> Utils::date2timestamp($b['created_at']);
        });
        return $polls;
    }

    public function addPoll(string $key, array $data)
    {
        $polls = $this->getPolls();
        $polls[$key] = $data;
        $this->setData(['polls' => $polls]);
    }


    public function getBlueprints()
    {
        return $this->blueprint;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setData(array $data)
    {
        $this->data_file->content($data);
        $this->data_file->save();
    }

    public function saveOptions(string $id, array $options)
    {
        $options_file = static::getOptionsFile($id);
        $options = array_map(function($value) {
            if (is_string($value) || is_numeric($value)) {
                switch (strtolower((string)$value)) {
                    case "true":
                    case "1":
                    case "1.0":
                        return true;
                    case "false":
                    case "0":
                    case "0.0":
                        return false;
                }
            }
            return $value;
        }, $options);
        $options_file->save($options);
        $this->mergeSavedOptions($id);
    }

    public function renderPoll(?string $id = null, $options = [])
    {

        $twig = Grav::instance()['twig'];
        $poll = $this->getPoll($id);

        if (null === $poll) {
            return '<div class="alert alert-warning">Poll "'. $id . '" not found</div>';
        }

        $id = $id ?? $poll['id'];

        if (!empty($options)) {
            $this->saveOptions($id, $options);
        }

        $options = $this->config->toArray();

        $options['disabled'] = $options['disabled'] ?? false;
        $options['readonly'] = $options['readonly'] || ($options['disable_after_vote'] && $options['disabled']);

        $callback = Uri::addNonce(Utils::url($options['callback']) . '.json', 'poll');

        return $twig->processTemplate($options['twig_template'], [
            'id' => $id,
            'uri' => $callback,
            'poll' => $poll,
            'options' => $options,
        ]);
    }

    public function loadOptions($id): array
    {
        $options_file = $this->getOptionsFile($id);

        if ($options_file->exists()) {
            return $options_file->content();
        }

        return [];
    }

    public function processVote()
    {
//        if (!Utils::verifyNonce(Grav::instance()['uri']->param('nonce'), 'poll')) {
//            return [false, 'Invalid security nonce'];
//        }

        $rawData = file_get_contents('php://input');
        $data = json_decode($rawData, true);


        return [200, 'Vote processed', '<h1>Voted</h1>'];
    }

    public function getAnswerValue(string $answer): string
    {
        $normalized = $this->toASCII($answer);
        return Inflector::hyphenize($normalized);
    }

    protected function mergeSavedOptions($id)
    {
        $saved_options = $this->loadOptions($id);
        if (!empty($saved_options)) {
            $this->config = new Config(array_merge($this->config->toArray(), $saved_options));
        }
    }

    protected function getOptionsFile($id): CompiledYamlFile
    {
        $path = Grav::instance()['locator']->findResource('user-data://polls', true, true);
        if (!file_exists($path)) {
            Folder::create($path);
        }
        $options_path = $path  . '/' . md5($id) . '.yaml';
        return CompiledYamlFile::instance($options_path);
    }

    protected function toASCII( $str )
    {
        return strtr(utf8_decode($str),
            utf8_decode(
            'ŠŒŽšœžŸ¥µÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝßàáâãäåæçèéêëìíîïðñòóôõöøùúûüýÿ'),
            'SOZsozYYuAAAAAAACEEEEIIIIDNOOOOOOUUUUYsaaaaaaaceeeeiiiionoooooouuuuyy');
    }


}