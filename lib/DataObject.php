<?php

/**
 * 任务数据对象
 */
class DataJob
{
    /**
     * 统计系统分配的唯一ID
     *
     * Exp: abcde123af32,1d,hsqj,2016001,123_abc
     *
     * @var string
     */
    public $uniqueId;

    /**
     * 数据的唯一ID
     *
     * @var string
     */
    public $dataId;

    /**
     * 时间分组值
     *
     * @var int
     */
    public $timeOpLimit;

    /**
     * 时间的分组类型
     *
     * m,d,h,i,s
     *
     * @var string
     */
    public $timeOpType;

    /**
     * 时间分组的key
     *
     * @var string
     */
    public $timeKey;

    /**
     * 当前数据的时间戳
     *
     * @var int
     */
    public $time;

    /**
     * 当前数据对应的应用
     *
     * @var string
     */
    public $app;

    /**
     * 当前数据对应的序列的key
     *
     * @var string
     */
    public $seriesKey;

    /**
     * 当前数据的唯一数据列表
     *
     * @var array
     */
    public $dist = [];

    /**
     * 相关统计的数据
     *
     * @var DataTotalItem
     */
    public $total;

    /**
     * 数据内容
     *
     * @var array
     */
    public $data = [];

    /**
     * 分配的任务投递时间
     *
     * @var int
     */
    public $taskTime = 0;

    /**
     * 活跃时间
     *
     * @var int
     */
    public $activeTime = 0;

    /**
     * 是否已保存
     *
     * @var bool
     */
    public $saved = false;

    public function __construct($uniqueId)
    {
        $this->uniqueId = $uniqueId;
        $this->total    = new DataTotalItem();
    }

    public function setData($item, $fun, $allField)
    {
        # 记录唯一值
        if (isset($fun['dist']))
        {
            foreach ($fun['dist'] as $field => $t)
            {
                if (true === $t)
                {
                    # 单字段
                    $v = $item[$field];
                }
                else
                {
                    # 多字段
                    $v = [];
                    foreach ($t as $f)
                    {
                        $v[] = $item[$f];
                    }
                    $v = implode('_', $v);
                }

                $this->dist[$field][$v] = 1;
            }
        }

        $time = isset($item['microtime']) && $item['microtime'] > $item['time'] && $item['microtime'] - $this->time < 1 ? $item['microtime'] : $this->time;
        self::totalData($this->total, $item, $fun, $time);

        if ($allField)
        {
            # 需要所有字段数据
            $data = $item;
        }
        else
        {
            $data = [];
            if (isset($option['function']['value']))
            {
                # 所有需要赋值的字段, 不需要的字段全部丢弃
                foreach ($option['function']['value'] as $field => $tmp)
                {
                    if (isset($item[$field]))
                    {
                        $data[$field] = $item[$field];
                    }
                }
            }
        }

        $this->data = $data;
    }

    /**
     * 将一个新的job数据合并进来
     *
     * @param DataJob $job
     * @return bool
     */
    public function merge(DataJob $job)
    {
        if ($job->uniqueId !== $this->uniqueId)return false;

        # 合并唯一序列
        foreach ($job->dist as $field => $v)
        {
            if (isset($this->dist[$field]))
            {
                $this->dist[$field] = array_merge($this->dist[$field], $v);
            }
            else
            {
                $this->dist[$field] = $v;
            }
        }

        # 合并统计数据
        TaskProcess::mergeTotal($this->total, $job->total);

        $this->dataId = $job->dataId;
        $this->time   = $job->time;
        $this->data   = $job->data;

        return true;
    }

    /**
     * 统计数据
     *
     * @param $total
     * @param $item
     * @param $fun
     * @param $time
     * @return array
     */
    protected static function totalData(DataTotalItem $total, $item, $fun, $time)
    {
        if (isset($fun['sum']))
        {
            # 相加的数值
            foreach ($fun['sum'] as $field => $t)
            {
                $total->sum[$field] += $item[$field];
            }
        }

        if (isset($fun['count']))
        {
            foreach ($fun['count'] as $field => $t)
            {
                $total->count[$field] += 1;
            }
        }

        if (isset($fun['last']))
        {
            foreach ($fun['last'] as $field => $t)
            {
                $tmp = $total->last[$field];

                if (!$tmp || $tmp[1] < $time)
                {
                    $total->last[$field] = [$item[$field], $time];
                }
            }
        }

        if (isset($fun['first']))
        {
            foreach ($fun['first'] as $field => $t)
            {
                $tmp = $total->first[$field];

                if (!$tmp || $tmp[1] > $time)
                {
                    $total->first[$field] = [$item[$field], $time];
                }
            }
        }

        if (isset($fun['min']))
        {
            foreach ($fun['min'] as $field => $t)
            {
                if (isset($total->min[$field]))
                {
                    $total->min[$field] = min($total['min'][$field], $item[$field]);
                }
                else
                {
                    $total->min[$field] = $item[$field];
                }
            }
        }

        if (isset($fun['max']))
        {
            foreach ($fun['max'] as $field => $t)
            {
                if (isset($total->max[$field]))
                {
                    $total->max[$field] = max($total['max'][$field], $item[$field]);
                }
                else
                {
                    $total->max[$field] = $item[$field];
                }
            }
        }

        return $total;
    }
}



/**
 * 统计数据对象
 */
class DataTotalItem
{
    /**
     * @var array
     */
    public $count = [];

    /**
     * @var array
     */
    public $dist = [];

    /**
     * @var array
     */
    public $sum = [];

    /**
     * @var array
     */
    public $min = [];

    /**
     * @var array
     */
    public $max = [];

    /**
     * @var array
     */
    public $first = [];

    /**
     * @var array
     */
    public $last = [];

    public function __sleep()
    {
        $rs = [];
        foreach (['dist', 'count', 'sum', 'min', 'max', 'first', 'last'] as $item)
        {
            if (count($this->$item))
            {
                $rs[] = $item;
            }
        }

        return $rs;
    }

    public function __wakeup()
    {

    }
}