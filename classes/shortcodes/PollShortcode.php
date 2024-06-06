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
                'disable_after_vote' => $sc->getParameter('disable_after_vote'),
                'unique_ip_check' => $sc->getParameter('unique_ip_check'),
                'readonly' => $sc->getParameter('readonly'),
                'twig_template' => $sc->getParameter('twig_template'),
                'theme' => $sc->getParameter('theme', $this->config->get('plugins.polls.theme')),
                'show_hints' => $sc->getParameter('show_hints'),
                'disabled' => false,
            ], function ($value) {
                return !is_null($value);
            });
            $polls->saveOptions($id, $options);
            return $polls->renderPoll($id, $options);
        });
    }
}