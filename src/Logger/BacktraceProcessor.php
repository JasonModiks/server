<?php

namespace MyQEE\Server\Logger;

class BacktraceProcessor implements \Monolog\Processor\ProcessorInterface {
    /**
     * 跟踪 debug_backtrace 的路径序号
     *
     * 默认3，如果经过封装输出错误，可以修改此值
     *
     * @var int
     */
    public $backtraceIndex = 3;

    /**
     * 在此等级之上的log才会附带backtrace
     *
     * @var int
     */
    public $level = 0;

    public function __invoke(array $records) {
        if ($records['level'] >= $this->level) {
            $records['extra']['backtrace'] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $this->backtraceIndex + 1)[$this->backtraceIndex];
        }

        return $records;
    }
}