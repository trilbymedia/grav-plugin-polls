<?php

namespace Grav\Plugin\Polls;

use Grav\Common\Config\Config;
use Grav\Common\Data\Blueprint;
use Grav\Common\Data\Data;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Inflector;
use Grav\Common\Session;
use Grav\Common\Uri;
use Grav\Common\Utils;
use Grav\Plugin\Database\PDO;

class PollsManager
{
    /** @var PDO */
    protected $db;
    protected $blueprint;
    protected $data_file;
    protected $data_path;
    protected $data_db;
    protected $table_votes = 'poll_votes';

    protected $data;
    protected $config;

    protected $disabled = false;

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
        $this->data_db = $path . '/polls.db';

        $data = new Data($this->data_file->content(), $this->blueprint);
        $data->file($this->data_file);
        $this->data = $data;

        $config = Grav::instance()['config']->get('plugins.polls');
        $this->config = new Config($config);

        $this->dataInit();
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

    public function renderResults(?string $id = null): string
    {
        $twig = Grav::instance()['twig'];
        $poll = $this->getPoll($id);

        if (null === $poll) {
            return '<div class="alert alert-warning">Poll "'. $id . '" not found</div>';
        }

        $id = $id ?? $poll['id'];

        $results = $this->getResults($id);

        $this->mergeSavedOptions($id);


        return $twig->processTemplate($this->config->get('results_template'), [
            'id' => $id,
            'poll' => $poll,
            'polls' => $this,
            'results' => $results,
            'options' => $this->config->toArray(),
            'total_votes' => array_sum($results),
        ]);
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
        $options['readonly'] = $options['readonly'] ?? false;

        $callback = Uri::addNonce(Utils::url($options['callback']) . '.json', 'poll');

        return $twig->processTemplate($options['poll_template'], [
            'id' => $id,
            'uri' => $callback,
            'poll' => $poll,
            'polls' => $this,
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
        $rawData = file_get_contents('php://input');
        $data = json_decode($rawData, true);
        $id = $data['id'];

        $lang = Grav::instance()['language'];

        if (!Utils::verifyNonce($data['nonce'], 'poll-form')) {
            return [400, $lang->translate('PLUGIN_POLLS.ERROR_INVALID_SECURITY_TOKEN')];
        }

        $poll = $this->getPoll($id);

        if (!$poll) {
            return [400, $lang->translate('PLUGIN_POLLS.ERROR_POLL_NOT_FOUND')];
        }

        if (!$this->isValidVote($poll, $data)) {
            return [400, $lang->translate('PLUGIN_POLLS.ERROR_INVALID_VOTE')];
        }

        if ($this->hasAlreadyVoted($id)) {
            return [400, $lang->translate('PLUGIN_POLLS.ERROR_HAS_VOTED')];
        }

        $this->dataStoreVote($poll, $data);

        // Store vote_check in session if required
        if ($this->config->get('session_vote_check')) {
            $session = Grav::instance()['session'];
            $polls_voted = $session->polls_voted ?? [];
            $polls_voted[] = $id;
            $session->polls_voted = $polls_voted;
        }

        return [200, $lang->translate('PLUGIN_POLLS.VOTE_STORED'), $this->renderResults($id)];
    }

    public function showResults()
    {
        $id = Grav::instance()['uri']->query('id');
        return [200, 'Success', $this->renderResults($id)];
    }

    public function showPoll()
    {
        $id = Grav::instance()['uri']->query('id');

        $this->mergeSavedOptions($id);

        return [200, 'Success', $this->renderPoll($id, $this->config->toArray())];
    }


    protected function dataStoreVote(array $poll, array $data): bool
    {
        $id = $data['id'];
        $answers = $data['answers'] ?? [];

        $query = "INSERT INTO $this->table_votes (poll_id, answer, ip) VALUES (:poll_id, :answer, :ip)";

        $statement = $this->db->prepare($query);
        $statement->bindValue(':poll_id', $id, PDO::PARAM_STR);
        $statement->bindValue(':ip', Grav::instance()['uri']->ip(), PDO::PARAM_STR);

        foreach ($answers as $answer) {
            $statement->bindValue(':answer', $answer, PDO::PARAM_STR);
            $statement->execute();
        }

        return true;
    }

    protected function dataInit()
    {
        $connect_string = 'sqlite:' . $this->data_db;

        $this->db = Grav::instance()['database']->connect($connect_string);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if (!$this->db->tableExists($this->table_votes)) {
            $this->dataCreateTables();
        }
    }

    protected function dataCreateTables(): void
    {
        $commands = [
            "CREATE TABLE IF NOT EXISTS $this->table_votes (
                vote_id INTEGER PRIMARY KEY AUTOINCREMENT,
                poll_id VARCHAR(255),
                answer VARCHAR(255),
                ip VARCHAR(255),
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP)",
        ];

       // execute the sql commands to create new tables
       foreach ($commands as $command) {
           $this->db->exec($command);
       }
    }

    public function hasAlreadyVoted($id)
    {
        /** @var Session $session */
        if ($this->config->get('session_vote_check')) {
            $session = Grav::instance()['session'];
            $session->polls_voted = $session->polls_voted ?? [];
            if (in_array($id, $session->polls_voted)) {
                return true;
            }
        }

        if ($this->config->get('unique_ip_check')) {
            $user_ip = Grav::instance()['uri']->ip();

            $query = "SELECT COUNT(*) as count FROM {$this->table_votes} WHERE poll_id = :id AND ip = :ip";

            $statement = $this->db->prepare($query);
            $statement->bindValue(':id', $id, PDO::PARAM_STR);
            $statement->bindValue(':ip', $user_ip, PDO::PARAM_STR);
            $statement->execute();

            $results = $statement->fetch(PDO::FETCH_ASSOC);

            if ($results['count'] > 0) {
                return true;
            }
        }

        return false;
    }

    protected function isValidVote(array $poll, array $data): bool
    {
        $valid_answers = $this->getValidAnswerValues($poll);
        $data_answers = $data['answers'] ?? [];

        foreach ($data_answers as $answer) {
            if (!in_array($this->getAnswerValue($answer), $valid_answers)) {
                return false;
            }
        }

        if (count($data_answers) < $poll['min_answers'] || count($data_answers) > $poll['max_answers']) {
            return false;
        }


        return true;
    }

    public function getResults(string $id)
    {
        $query = "SELECT answer, COUNT(*) as count FROM {$this->table_votes} WHERE poll_id = :id GROUP BY answer";

        $statement = $this->db->prepare($query);
        $statement->bindValue(':id', $id, PDO::PARAM_STR);
        $statement->execute();

        $results = $statement->fetchAll(PDO::FETCH_ASSOC);

        $transformed = array_reduce($results, function($carry, $item) {
            $carry[$item['answer']] = $item['count'];
            return $carry;
        }, []);
        return $transformed;
    }
    public function getAnswerValue(string $answer): string
    {
        $normalized = $this->toASCII($answer);
        return Inflector::hyphenize($normalized);
    }

    public function getValidAnswerValues(array $poll): array
    {
        return array_map(function($answer) {
            return $this->getAnswerValue($answer);
        }, $poll['answers']);
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