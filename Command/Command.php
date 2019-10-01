<?php
declare(strict_types = 1);
/**
 * OriginPHP Framework
 * Copyright 2018 - 2019 Jamiel Sharief.
 *
 * Licensed under The MIT License
 * The above copyright notice and this permission notice shall be included in all copies or substantial
 * portions of the Software.
 *
 * @copyright    Copyright (c) Jamiel Sharief
 * @link         https://www.originphp.com
 * @license      https://opensource.org/licenses/mit-license.php MIT License
 */

namespace Origin\Console\Command;

use Origin\Model\ModelTrait;
use Origin\Console\ConsoleIo;

use Origin\Console\CommandRunner;
use Origin\Console\ArgumentParser;
use Origin\Console\Exception\ConsoleException;
use Origin\Console\Exception\StopExecutionException;

abstract class Command
{
    use ModelTrait;
    /**
     * Default error code.
     *
     * @var int
     */
    const ERROR = 1;

    /**
     * Default success code.
     *
     * @var int
     */
    const SUCCESS = 0;

    /**
     * Console Input/Output Object.
     *
     * @var \Origin\Console\ConsoleIo
     */
    public $io = null;

    /**
     * The name of the command.
     *
     * @var string
     */
    protected $name = 'unknown';

    /**
     * Holds the description for this command. This is shown before help.
     *
     * @var string|array
     */
    protected $description = null;

    /**
     * This is displayed after help.
     *
     * @var string|array
     */
    protected $epilog = null;

    /**
     * This is for showing full help for this command
     *
     * @var string|array
     */
    protected $help = null;

    /**
      * This to set additional usages
      *
      * @var string|array
      */
    protected $usages = [];

    /**
     * Command argument configuration.
     *
     * @var array
     */
    protected $commandArguments = [];
    /**
     * Command options configuration.
     *
     * @var array
     */
    protected $commandOptions = [];

    /**
     * Holds the array of arguments passed
     *
     * @var array
     */
    protected $arguments = [];

    /**
     * Holds the array of options passed
     *
     * @var array
     */
    protected $options = [];

    /**
     * If verbose option has been called
     *
     * @var boolean
     */
    protected $verbose = false;

    /**
     * Undocumented variable.
     *
     * @var \Origin\Console\ArgumentParser;
     */
    protected $parser = null;

    public function __construct(ConsoleIo $io = null)
    {
        if ($io === null) {
            $io = new ConsoleIo();
        }
        $this->io = $io;
        
        $this->parser = new ArgumentParser();

        $this->addOption('help', ['short' => 'h', 'description' => 'Displays this help message', 'type' => 'boolean']);
        $this->addOption('verbose', ['short' => 'v', 'description' => 'Displays additional output (if available)', 'type' => 'boolean']);

        $this->validateName($this->name);
    }

    /**
     * The initialize hook, called before command is executed. Setup your arguments and options for parsing.
     */
    public function initialize() : void
    {
    }

    /**
     * Runs another command from this command
     *
     * @param string $command
     * @param array $args  array of options e.g
     *    $args = ['my_database','--connection'=>'default','--help']
     * @return bool
     */
    public function runCommand(string $command, array $args = []) : bool
    {
        $runner = new CommandRunner($this->io);
        $instance = $runner->findCommand($command);
        if (! $instance instanceof Command) {
            throw new ConsoleException(sprintf('Command `%s` was not found', $command));
        }

        // Convert args
        $argv = [];
        foreach ($args as $key => $value) {
            if (is_int($key)) {
                $argv[] = $value;
            } else {
                $argv[] = "{$key}={$value}";
            }
        }

        return $instance->run($argv);
    }

    public function dontShow()
    {
    }

    /**
     * Runs this command used by Command Runner
     *
     * @param array $args
     * @return bool $result
     */
    public function run(array $args) : bool
    {
        $this->initialize();
      
        # Configure Help
        $this->parser->setCommand($this->name);
        $this->parser->setDescription($this->description);
        $this->parser->setEpilog($this->epilog);
        $this->parser->setHelp($this->help);
        $this->parser->setUsage($this->usages);
     
        try {
            list($options, $arguments) = $this->parser->parse($args);
        } catch (ConsoleException $ex) {
            $this->io->err('<exception> ERROR </exception> <text>'.$ex->getMessage().'</text>');
            $this->io->nl();
            $this->out($this->parser->usage());

            return false;
        }
        $this->options = $options;
        $this->arguments = $arguments;

        // Enable verbosity
        if ($this->options('verbose')) {
            $this->verbose = true;
        }

        if ($this->options('help')) {
            $this->displayHelp();

            return true;
        }

        $this->startup();
        $this->execute();
        $this->shutdown();
        
        return true;
    }

    /**
     * Gets an options or all options.
     *
     * @param string $name
     * @return mixed
     */
    public function options(string $name = null)
    {
        if ($name === null) {
            return $this->options;
        }
        if (isset($this->options[$name])) {
            return $this->options[$name];
        }

        return null;
    }

    /**
     * Gets an argument or aguments.
     *
     * @param string $name
     * @return mixed
     */
    public function arguments(string $name = null)
    {
        if ($name === null) {
            return $this->arguments;
        }
        if (isset($this->arguments[$name])) {
            return $this->arguments[$name];
        }

        return null;
    }

    /**
     * This will set or get the name. Note. Setting name here does not change the command name since name is taken after
     * construction of the object without running any methods.
     * @internal not validating name here. Changes here only affect help usage ouput e.g console app setting name
     * requires spaces.
     * @param string $name
     * @return string|void
     */
    public function name(string $name = null)
    {
        if ($name === null) {
            return $this->name;
        }
        
        $this->name = $name;
    }

    /**
     * Inernal function for validating a command name
     *
     * @param string $name
     * @return void
     */
    protected function validateName(string $name) : void
    {
        // Valid syntax name, some-name, app:some-name, app:name-a:name-b
        if (! preg_match_all('/^[a-z][a-z-]++(?:\:[a-z-]++)*$/', $name)) {
            throw new ConsoleException(sprintf('Command name `%s` is invalid', $name));
        }
    }

    /**
     * Sets the descripton.
     *
     * @param string|array $description
     * @return string|array|void
     */
    public function description($description = null)
    {
        if ($description === null) {
            return $this->description;
        }
        $this->description = $description;
    }

    /**
     * Adds extra Usage examples
     *
     * @param string $usage
     * @return void
     */
    public function addUsage(string $usage) : void
    {
        $this->usages[] = $usage;
    }

    /**
     * Adds an available option or flag.
     *
     * @param string $name
     * @param array  $options Options include help:help text short: short option e.g -ds, boolean=true, required
     *  - description: the help description
     *  - short: short option when using -. e.g. -ds
     *  - required: default false
     *  - type: string, integer, boolean, array, hash
     *  - default: default value
     * @return void
     */
    public function addOption(string $name, array $options = []) : void
    {
        $this->parser->addOption($name, $options);
    }

    /**
     * Adds a available.
     *
     * @param string $name argument name
     * @param array  $options Options accepts the following keys:
     *  - description: the help description
     *  - type: string, integer, array, hash
     *  - required: default false
     */
    public function addArgument(string $name, array $options = []) : void
    {
        $this->parser->addArgument($name, $options);
    }

    /**
     * Displays the help for this command
     * @return void
     */
    public function displayHelp() : void
    {
        $content = $this->parser->help();

        $this->io->out($content);
    }

    /**
     * Aborts the execution of the command and sets the exit code as an error.
     *
     * @param string $status
     * @return void
     */
    public function abort(string $message = 'Command Aborted', $exitCode = self::ERROR) : void
    {
        throw new StopExecutionException($message, $exitCode);
    }

    /**
     * Exits the command succesfully.
     *
     * @param string $status
     * @return void
     */
    public function exit(string $message = 'Exited Command', $exitCode = self::SUCCESS) : void
    {
        throw new StopExecutionException($message, $exitCode);
    }

    /**
     * Displays debug information when the --verbose option is passed
     *
     * @internal this is correct, not --debug
     * @param string|array $message a message or array of messages
     * @param array $context
     * @return void
     */
    public function debug($message, array $context = []) : void
    {
        if ($this->verbose) {
            $message = $this->interpolate($message, $context);
            $message = $this->addTags('debug', $message);
            $this->io->out($message);
        }
    }

    /**
     * Displays a styled info message
     *
     * @param string|array $message a message or array of messages
     * @param array $context
     * @return void
     */
    public function info($message, array $context = []) : void
    {
        $message = $this->interpolate($message, $context);
        $message = $this->addTags('info', $message);
        $this->io->out($message, $context);
    }

    /**
     * Displays a styled notice message
     *
     * @param string|array $message a message or array of messages
     * @param array $context
     * @return void
     */
    public function notice($message, array $context = []) : void
    {
        $message = $this->interpolate($message, $context);
        $message = $this->addTags('notice', $message);
        $this->io->out($message);
    }

    /**
     * Displays a styled success message
     *
     * @param string|array $message a message or array of messages
     * @param array $context
     * @return void
     */
    public function success($message, array $context = []) : void
    {
        $message = $this->interpolate($message, $context);
        $message = $this->addTags('success', $message);
        $this->io->out($message);
    }

    /**
     * Displays a styled warning message
     *
     * @param string|array $message a message or array of messages
     * @param array $context
     * @return void
     */
    public function warning($message, array $context = []) : void
    {
        $message = $this->interpolate($message, $context);
        $message = $this->addTags('warning', $message);
        $this->io->err($message);
    }

    /**
    * Displays a styled error message
    *
    * @param string|array $message a message or array of messages
    * @param array $context
    * @return void
    */
    public function error($message, array $context = []) : void
    {
        $message = $this->interpolate($message, $context);
        $message = $this->addTags('error', $message);
        $this->io->err($message);
    }

    /**
     * Wraps message or messages in tags
     *
     * @param string $tag
     * @param string|array $message
     * @return string|array
     */
    protected function addTags(string $tag, $message)
    {
        if (is_array($message)) {
            foreach ($message as $i => $line) {
                $message[$i] = "<{$tag}>{$line}</{$tag}>";
            }

            return $message;
        }

        return "<{$tag}>{$message}</{$tag}>";
    }

    /**
     * Displays an error message and aborts the command.
     *
     * @param string $title
     * @param string $message
     * @return void
     */
    public function throwError(string $title, string $message = null) : void
    {
        $msg = "<exception> ERROR </exception> <heading>{$title}</heading>\n";
        if ($message) {
            $msg .= "<text>{$message}</text>\n";
        }
        $this->io->err($msg);
        $this->abort($title);
    }

    /**
     * This is called before the executed method
     *
     * @return void
     */
    public function startup() : void
    {
    }

    /**
     * Place the command logic here.
     *
     * @return void
     */
    abstract public function execute() : void;

    /**
     * This is called after executed method
     *
     * @return void
     */
    public function shutdown() : void
    {
    }

    /**
     * Outputs a message (or messages) and adds a new line
     *
     * @param string|array $message a message or array of messages
     * @param array $context
     * @return void
     */
    public function out($message, array $context = []) : void
    {
        $message = $this->interpolate($message, $context);
        $this->io->out($message, $context);
    }

    /**
    * Interpolates context values into the message placeholders.
    *
    * @param string|array $message
    * @param array $context
    * @return array
    */
    protected function interpolate($messages, array $context = []) : array
    {
        if (is_string($messages)) {
            $messages = [$messages];
        }

        $replace = [];
        foreach ($context as $key => $value) {
            if (! is_array($value) and (! is_object($value) or method_exists($value, '__toString'))) {
                $replace['{' . $key . '}'] = $value;
            }
        }

        $out = [];
        foreach ($messages as $message) {
            $out[] = strtr($message, $replace);
        }

        return $out;
    }
}