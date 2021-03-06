<?php

namespace Searcher\LoopBack\Parser\Filter\Condition\CompareCondition;


use Searcher\LoopBack\Parser\Filter\Condition\Exception\InvalidConditionException;
use Searcher\LoopBack\Parser\Filter\FilterCondition;

class LikeCondition extends AbstractCondition
{
    public function getOperator()
    {
        return FilterCondition::CONDITION_LIKE;
    }

    /**
     * @inheritdoc
     */
    public function build($conditions = null)
    {
        $value = $this->getValue();
        if (is_array($value)) {
            throw new InvalidConditionException();
        }

        return $this;
    }
}