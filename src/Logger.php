<?php

namespace MyQEE\Server;

if (!class_exists('\\Monolog\\Logger')) {
    class_alias(Logger\Lite::class, LoggerLite::class);
}
else {
    class_alias(\Monolog\Logger::class, LoggerLite::class);
}


class Logger extends LoggerLite {
    public static $level = self::WARNING;
    public static $isDebug = false;
    public static $isTrace = false;

    public static $useProcessLoggerSaveFile = false;

    /**
     * 带文件路径的等级
     *
     * @var int
     */
    public static $logWithFileLevel = 0;

    /**
     * 是否在控制台输出
     *
     * @var bool
     */
    public static $stdout = true;

    /**
     * @var static
     */
    protected static $defaultLogger;

    /**
     * 默认日志频道名
     *
     * @var string
     */
    protected static $defaultName = 'server';

    /**
     * @var array
     */
    protected static $loggers = [];

    /**
     * 是否使用文件保存
     *
     * @var bool
     */
    protected static $saveFile = false;

    protected static $inited = false;

    const TRACE = 50;

    /**
     * Logging levels from syslog protocol defined in RFC 5424
     *
     * @var array $levels Logging levels
     */
    protected static $levels = [
        self::TRACE     => 'TRACE',
        self::DEBUG     => 'DEBUG',
        self::INFO      => 'INFO',
        self::NOTICE    => 'NOTICE',
        self::WARNING   => 'WARNING',
        self::ERROR     => 'ERROR',
        self::CRITICAL  => 'CRITICAL',
        self::ALERT     => 'ALERT',
        self::EMERGENCY => 'EMERGENCY',
    ];

    /**
     * 默认输出对象
     *
     * @var Logger\StdoutHandler|null
     */
    protected static $defaultStdoutHandler;

    /**
     * 默认写入文件对象
     *
     * @var Logger\WriteFileCoHandler|Logger\SpecialProcessHandler|null
     */
    protected static $defaultWriteFileHandler;
    
    public function info($message, array $context = []) {
        $this->addRecord(static::INFO, (string)$message, $context);
    }

    /**
     * Adds a log record at the DEBUG level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string|\Exception|\Throwable $message The log message
     * @param array $context The log context
     */
    public function debug($message, array $context = []) {
        self::convertTraceMessage($message, $context);
        $this->addRecord(static::DEBUG, (string)$message, $context);
    }

    /**
     * Adds a log record at the WARNING level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string|\Exception|\Throwable $message The log message
     * @param array $context The log context
     */
    public function warning($message, array $context = []) {
        self::convertTraceMessage($message, $context);
        $this->addRecord(static::WARNING, (string)$message, $context);
    }

    /**
     * Adds a log record at the WARNING level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string|\Exception|\Throwable $message The log message
     * @param array $context The log context
     */
    public function warn($message, array $context = []) {
        self::convertTraceMessage($message, $context);
        $this->addRecord(static::WARNING, (string)$message, $context);
    }

    /**
     * Adds a log record at the ERROR level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string|\Exception|\Throwable $message The log message
     * @param array $context The log context
     */
    public function error($message, array $context = []) {
        self::convertTraceMessage($message, $context);
        $this->addRecord(static::ERROR, (string)$message, $context);
    }

    /**
     * Adds a log record at the CRITICAL level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string|\Exception|\Throwable $message The log message
     * @param array $context The log context
     */
    public function critical($message, array $context = []) {
        self::convertTraceMessage($message, $context);
        $this->addRecord(static::CRITICAL, (string)$message, $context);
    }

    /**
     * Adds a log record at the ALERT level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string|\Exception|\Throwable $message The log message
     * @param array $context The log context
     */
    public function alert($message, array $context = []) {
        self::convertTraceMessage($message, $context);
        $this->addRecord(static::ALERT, (string)$message, $context);
    }

    /**
     * Adds a log record at the EMERGENCY level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string|\Exception|\Throwable $message The log message
     * @param array $context The log context
     */
    public function emergency($message, array $context = []) {
        self::convertTraceMessage($message, $context);
        $this->addRecord(static::EMERGENCY, (string)$message, $context);
    }

    /**
     * 跟踪信息
     *
     * 如果需要扩展请扩展 `$this->saveTrace()` 方法
     *
     * @param string|\Exception|\Throwable $trace
     * @param array $context
     */
    public function trace($trace, array $context = []) {
        if (true === \MyQEE\Server\Logger::$isTrace) {
            self::convertTraceMessage($trace, $context);
            $this->addRecord(\MyQEE\Server\Logger::TRACE, $trace, $context);
        }
        elseif (is_object($trace) && ($trace instanceof \Exception || $trace instanceof \Throwable)) {
            self::convertTraceMessage($trace, $context);
            $this->addRecord(\MyQEE\Server\Logger::WARNING, $trace, $context);
        }
    }

    /**
     * Logger实例化对象
     *
     * @return static
     */
    public static function instance() {
        return self::$defaultLogger;
    }

    /**
     * 初始化Monolog对象
     *
     * 设置系统默认的 formatter 以及 handler 等
     *
     * @param \Monolog\Logger $logger
     */
    public static function initMonolog(\Monolog\Logger $logger) {
        if (false !== self::$logWithFileLevel) {
            # 添加一个附带文件路径的处理器
            $process        = new Logger\BacktraceProcessor();
            $process->level = self::$logWithFileLevel;
            $logger->pushProcessor($process);
        }

        if (self::$saveFile) {
            # 增加文件输出
            self::pushDefaultWriteFileHandler($logger);
        }

        if (self::$stdout) {
            # 增加控制台输出
            self::pushDefaultStdoutHandler($logger);
        }
    }

    /**
     * 加入系统默认的控制台输出处理对象
     *
     * @param \Monolog\Logger $logger
     */
    public static function pushDefaultStdoutHandler(\Monolog\Logger $logger) {
        if (!self::$defaultStdoutHandler) {
            $lineFormatter            = new Logger\LineFormatter();
            $lineFormatter->withColor = true;

            $stdoutHandler = new Logger\StdoutHandler(self::$level);
            $stdoutHandler->setFormatter($lineFormatter);

            self::$defaultStdoutHandler = $stdoutHandler;
        }

        $logger->pushHandler(self::$defaultStdoutHandler);
    }

    /**
     * 加入系统默认的文件输出处理对象
     *
     * @param \Monolog\Logger $logger
     */
    public static function pushDefaultWriteFileHandler(\Monolog\Logger $logger) {
        if (!self::$defaultWriteFileHandler) {
            if (self::$useProcessLoggerSaveFile) {
                $fileHandler = new Logger\SpecialProcessHandler(self::$level);
            }
            else {
                $fileHandler = new Logger\WriteFileCoHandler(self::$level);
            }

            $lineFormatter            = new Logger\LineFormatter();
            $lineFormatter->withColor = false;
            $fileHandler->setFormatter($lineFormatter);

            self::$defaultWriteFileHandler = $fileHandler;
        }

        $logger->pushHandler(self::$defaultWriteFileHandler);
    }

    /**
     * 设置默认日志频道名称
     *
     * @param string $name
     */
    public static function setDefaultName($name) {
        self::$defaultName = $name;

        if (self::$defaultLogger) {
            self::$defaultLogger = self::$defaultLogger->withName($name);
        }
    }

    /**
     * 获取一个Logger对象
     *
     * @param string $name
     * @return static|\Monolog\Logger|null
     */
    public static function getLogger($name) {
        if (!isset(self::$loggers[$name])) {
            return null;
        }

        return self::$loggers[$name];
    }

    /**
     * 设置一个Logger对象
     *
     * @param string $name
     * @param \Monolog\Logger $logger
     */
    public static function setLogger($name, \Monolog\Logger $logger) {
        self::$loggers[$name] = $logger;
    }

    /**
     * 将 Exception 或 Throwable 的信息转换成字符串
     *
     * @param $message
     * @param $context
     */
    public static function convertTraceMessage(& $message, & $context) {
        if (is_object($message) && ($message instanceof \Exception || $message instanceof \Throwable)) {
            $context['_trace'] = $message;
            $message           = $message->getMessage();
        }
    }

    /**
     * 初始化log配置
     *
     * ```php
     * $config = [
     *      'path'              => '/tmp/my_log.log',   # 路径，false = 不输出，支持 $level 替换，比如 /tmp/my_$level.log
     *      'loggerProcess'     => false,               # 在开启 path 时有效，是否使用日志独立进程
     *      'loggerProcessName' => 'logger',            # 进程名，默认 logger
     *      'stdout'            => null,                # 是否输出到控制台，开启log是默认false，否则默认true
     *      'withFilePath'      => true,                # 日志是否带文件路径
     * ];
     * ```
     *
     * @param array $config
     */
    public static function init($config) {
        if (self::$inited === true) {
            return;
        }
        self::$inited = true;

        self::$level = $config['level'];

        if ($config['level'] <= Logger::DEBUG) {
            self::$isDebug = true;
        }

        if ($config['level'] <= Logger::TRACE) {
            self::$isTrace = true;
        }

        # 初始化 Lite 对象
        Logger\Lite::init($config);

        self::$saveFile                 = $config['path'] === false ? false : true;
        self::$logWithFileLevel         = $config['withFilePath'];
        self::$useProcessLoggerSaveFile = $config['loggerProcess'] ? true : false;

        if (self::$saveFile) {
            self::$stdout = isset($config['stdout']) && $config['stdout'] ? true : false;
        }
        else {
            self::$stdout = true;
        }

        self::$defaultLogger = new static(self::$defaultName);

        if (class_exists('\\Monolog\\Logger', false)) {
            self::initMonolog(self::$defaultLogger);
            self::$defaultLogger->debug("Use Monolog\\Logger output logs.");
        }
    }
}