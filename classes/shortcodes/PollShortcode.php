<?php

namespace Grav\Plugin\Shortcodes;

use Thunder\Shortcode\Shortcode\ProcessedShortcode;

class PollShortcode extends Shortcode
{
    public function init()
    {
        $this->shortcode->getHandlers()->add('poll', function (ProcessedShortcode $sc) {
            $polls = $this->grav['polls'];
            $id = $polls->getId($sc->getParameter('id', null));
            $options = array_filter([
                'theme' => $sc->getParameter('theme'),
                'callback' => $sc->getParameter('callback'),
                'unique_ip_check' => $sc->getParameter('unique_ip_check'),
                'session_vote_check' => $sc->getParameter('session_vote_check'),
                'poll_template' => $sc->getParameter('poll_template'),
                'results_template' => $sc->getParameter('results_template'),
                'readonly' => $sc->getParameter('readonly'),
            ], function ($value) {
                return !is_null($value);
            });

            $polls->saveOptions($id, $options);
            return $polls->renderPoll($id, $options);
        });
    }
}