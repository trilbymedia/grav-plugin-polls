<?php

namespace Grav\Plugin\Polls;

use Grav\Common\Grav;
use Grav\Plugin\Admin\AdminBaseController;

class PollsController extends AdminBaseController
{
    protected $controller;
    protected $method;
    public $post;

    /**
     * LicenseManagerController constructor.
     *
     * @param $controller
     * @param $method
     */
    public function __construct($controller, $method)
    {
        $this->controller = $controller;
        $this->post = $controller->data;
        $this->method = $method;
        $this->admin = Grav::instance()['admin'];
    }

    public function execute()
    {
        if (!$this->authorizeTask('polls', ['admin.super', 'admin.polls'])) {
            return false;
        }

        $success = false;
        if (method_exists($this, $this->method)) {
            try {
                $success = call_user_func([$this, $this->method]);
            } catch (\RuntimeException $e) {
                $success = true;
                $this->admin->setMessage($e->getMessage(), 'error');
            }
        }
        return $success;
    }

    /**
        * Save License task
        *
        * @return bool
        */
       public function taskSavePolls()
       {
           $obj = Grav::instance()['polls']->getData();
           $obj->merge($this->post);
           $obj->filter();
           $obj->save();

           $this->admin->setMessage($this->admin->translate('PLUGIN_ADMIN.SUCCESSFULLY_SAVED'), 'info');
       }
}