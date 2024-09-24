<?php

namespace Grav\Plugin\Polls\Flex;

use Grav\Common\Flex\Types\Generic\GenericObject;
use Grav\Common\Grav;

class PollObject extends GenericObject
{
    protected $answers_count;
    protected $votes;

    protected function offsetLoad_answers_count($value)
    {
        return count($this->answers);
    }

    /**
     * @param int $value
     * @return null
     */
    protected function offsetSerialize_answers_count($value)
    {
        return null;
    }

    protected function offsetLoad_votes($value)
    {
        $polls = Grav::instance()['polls'];
        $results = $polls->getResults($this->id);
        return array_sum($results);
    }

    /**
     * @param int $value
     * @return null
     */
    protected function offsetSerialize_votes($value)
    {
        return null;
    }
}