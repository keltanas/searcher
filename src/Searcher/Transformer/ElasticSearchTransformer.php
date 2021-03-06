<?php

namespace Searcher\Transformer;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Searcher\LoopBack\Parser\Builder;
use Searcher\LoopBack\Parser\BuilderInterface;
use Searcher\LoopBack\Parser\Filter\FilterCondition;

class ElasticSearchTransformer implements TransformerInterface, BuilderInterface
{
    const BOOL_MUST = 'must';
    const BOOL_MUST_NOT = 'must_not';
    const BOOL_SHOULD = 'should';
    const BOOL = 'bool';
    const TERM = 'term';
    const TERMS = 'terms';
    const REGEXP = 'regexp';

    /** @var LoggerInterface */
    private $logger;

    /** @var Builder */
    private $builder;

    /** @var array  */
    private $results = [];

    /**
     * @return LoggerInterface
     */
    protected function getLogger()
    {
        if (!$this->logger) {
            $this->logger = new NullLogger();
        }

        return $this->logger;
    }

    /**
     * @param Builder $builder
     * @param LoggerInterface $logger
     */
    public function __construct(Builder $builder, LoggerInterface $logger = null)
    {
        $this->builder = $builder;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     * @return $this
     */
    public function build($conditions = null)
    {
        $rangeOperators = [
            FilterCondition::CONDITION_GT,
            FilterCondition::CONDITION_GTE,
            FilterCondition::CONDITION_LT,
            FilterCondition::CONDITION_LTE,
        ];

        $regexpOperators = array(FilterCondition::CONDITION_LIKE);

        $mustNotOperators = array(
            FilterCondition::CONDITION_NIN,
            FilterCondition::CONDITION_NEQ,
        );

        $termsOperators = array(
            FilterCondition::CONDITION_IN,
            FilterCondition::CONDITION_NIN,
        );

        $builder = $this->builder;
        $query = array();
        $query['from'] = $builder->getOffset();
        $query['size'] = $builder->getLimit();

        foreach ($builder->getOrders() as $order) {
            $query['body']['sort'][] = array(
                $order->getField() => $order->getDirection()
            );
        }

        $filters = $builder->getFilters();

        if (count($filters) !== 0) {
            $query['body']['query']['filtered']['filter'][self::BOOL] = array();
        }

        foreach ($filters as $filter) {
            $groupName = self::BOOL_MUST;

            if ($filter->getGroup() === FilterCondition::CONDITION_OR) {
                $groupName = self::BOOL_SHOULD;
            }

            foreach ($filter->getConditions() as $condition) {

                $conditionArray = array(
                    self::TERM => array(
                        $condition->getField() => $condition->getValue()
                    )
                );

                $operator = $condition->getOperator();
                if (in_array($operator, $mustNotOperators)) {
                    if ($groupName === self::BOOL_MUST) {
                        $groupName = self::BOOL_MUST_NOT;
                    } else {
                        $groupName = self::BOOL_MUST;
                    }
                }

                if (in_array($operator, $rangeOperators)) {
                    $conditionArray = array(
                        'range' => array(
                            $condition->getField() => array(
                                $operator => $condition->getValue()
                            )
                        )
                    );
                }

                if (in_array($operator, $termsOperators)) {
                    $conditionArray = array(
                        self::TERMS => array(
                            $condition->getField() => $condition->getValue()
                        )
                    );
                }

                if (in_array($operator, $regexpOperators)) {
                    $conditionArray = array(
                        self::REGEXP => array(
                            $condition->getField() => array(
                                // todo: sanitize values
                                'value' => sprintf('%s.*', $condition->getValue()),
                            )
                        )
                    );
                }

                $query['body']['query']['filtered']['filter'][self::BOOL][$groupName][] = $conditionArray;
            }
        }

        $this->results = $query;

        $this->getLogger()->debug(json_encode($this->results), array('library' => 'searcher'));

        return $this;
    }

    public function getResult()
    {
        return $this->results;
    }
}