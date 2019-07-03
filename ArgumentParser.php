<?php
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

 /**
  * @todo implment array and hashes
  */
namespace Origin\Console;

use Origin\Console\Exception\ConsoleException;
use Origin\Console\ConsoleHelpFormatter;

class ArgumentParser
{
    protected $options = [];

    protected $shortOptions = [];

    protected $arguments = [];

    protected $commands = [];

    /**
     * Command Name
     *
     * @var string
     */
    protected $command = null;

    /**
     * Description displayed before help
     *
     * @var string
     */
    protected $description = null;

    /**
     * Text displayed after help
     *
     * @var string
     */
    protected $epilog =  null;

    /**
     * Additional Long Help text
     *
     * @var string
     */
    protected $help = null;

    protected $usage = null;

    public function __construct(string $name = 'command', string $description=null)
    {
        $this->command = $name;
        $this->description = $description;
    }

    public function setCommand(string $command)
    {
        $this->command = $command;
    }

    public function setDescription($description)
    {
        if (is_array($description)) {
            $description = implode("\n", $description);
        }
        $this->description = $description;
    }

    public function setEpilog($epilog)
    {
        if (is_array($epilog)) {
            $epilog = implode("\n", $epilog);
        }
        $this->epilog = $epilog;
    }

    public function setHelp($help)
    {
        if (is_array($help)) {
            $help = implode("\n", $help);
        }
        $this->help = $help;
    }

    public function setUsage($usage)
    {
        if (is_array($usage)) {
            $usage = implode("\n", $usage);
        }
        $this->usage = $usage;
    }

    /**
     * Undocumented function
     *
     * @param string $name
     * @param array $options
     *  - description: help text
     *  - short: the short command, this is with single -. e.g -f
     *  - default: null
     *  - required: default false
     *  - type: string, integer, boolean
     *  - banner: for displayHelp. default is uppercase value e.g --datasource=DATASOURCE
     * @return void
     */
    public function addOption(string $name, array $options=[])
    {
        $options += ['name'=>$name,'short'=>null,'default'=>null,'required'=>false,'type'=>'string','description'=>'','banner'=>strtoupper($name)];
        if ($options['default'] and $options['required']) {
            throw new ConsoleException("Option {$name} cannot be required and have default value");
        }
        if (!in_array($options['type'], ['string','boolean','integer','array','hash'])) {
            throw new ConsoleException("Option {$name} invalid type");
        }
    
        if ($options['short']) {
            $this->shortOptions[$options['short']] = $options;
        }
        $this->options[$name] = $options;
    }

    public function addCommand(string $name, array $options=[])
    {
        $options += ['name'=>$name,'description'=>null];
        $this->commands[$name] = $options;
    }

    /**
     * Undocumented function
     *
     * @param string $name
     * @param array $options
     *  - description: help text
     *  - required: default false
     *  - type: string, integer, boolean, array hash
     * @return void
     */
    public function addArgument(string $name, array $options=[])
    {
        $options += ['name'=>$name,'default'=>null,'required'=>false,'type'=>'string','description'=>''];
        if ($options['required'] and $this->arguments) {
            $arg = end($this->arguments);
            if ($arg['required'] === false) {
                throw new ConsoleException("You cannot add a required argument after an optional one.");
            }
        }
        if ($this->arguments) {
            $arg = end($this->arguments);
            if ($arg['type'] === 'array' or $arg['type'] ==='hash') {
                throw new ConsoleException("You cannot add an argument after an array or hash argument");
            }
        }
        $this->arguments[$name] = $options;
    }

    public function parse(array $argv)
    {
        $arguments = $options = [];
        $args = [];
        foreach ($argv as $key => $arg) {
            if ($this->isLongOption($arg)) {
                $options = $this->parseLongOption($arg, $options);
            } elseif ($this->isShortOption($arg)) {
                $options = $this->parseShortOption($arg, $options);
            } else {
                $args[] = $arg;
            }
        }

        # Process Args
        $arguments = $this->parseArguments($args);

        foreach ($this->options as $option) {
            if (!empty($option['required']) and empty($options[$option['name']])) {
                throw new ConsoleException(sprintf('Missing required option `%s`', $option['name']));
            }
            if ($option['type'] === 'boolean' and !isset($options[$option['name']])) {
                $options[$option['name']] = false;
            } elseif (!empty($option['default']) and !isset($options[$option['name']])) {
                $options[$option['name']] = $option['default'];
            }
        }

        $requiredArguments = [];
        foreach ($this->arguments as $argument) {
            if (!empty($options['help'])) {
                break;
            }
            if (!empty($argument['required']) and !isset($arguments[$argument['name']])) {
                throw new ConsoleException(sprintf('Missing required argument `%s`', $argument['name']));
            }
        }
        return [$options,$arguments];
    }

    /**
     * Undocumented function
     *
     * @param array $args extracted args
     * @return void
     */
    protected function parseArguments(array $args)
    {
        $keys = array_keys($this->arguments);
        $arguments = [];
        foreach ($args as $key => $arg) {
            if (isset($keys[$key])) {
                $name = $keys[$key];
                $type = $this->arguments[$name]['type'];
                $max = count($args);
                if ($type === 'array') {
                    for ($i=$key;$i<$max;$i++) {
                        $values[] = $args[$i];
                    }
                    $arguments[$name] = $values;
                    break;
                } elseif ($type ==='hash') {
                    for ($i=$key;$i<$max;$i++) {
                        if (strpos($args[$i], ':') !== false) {
                            list($k, $v) = explode(':', $args[$i]);
                            $values[$k] = $v;
                        } else {
                            $values[] = $args[$i];
                        }
                    }
                    $arguments[$name] = $values;
                    break;
                }
                $arguments[$name] = $this->value($type, $arg);
            }
        }
        return $arguments;
    }

    protected function value($type, $value)
    {
        if ($type ==='boolean') {
            return (bool) $value;
        }
        if ($type ==='integer') {
            return (int) $value;
        }
        return $value;
    }

    protected function parseOption($option, $options)
    {
        $name = $this->getOptionName($option);
        if ($this->options[$name]['type'] === 'boolean') {
            $options[$name] = true;
            return $options;
        }
 
        $value = $this->options[$name]['default'];
       
        if (strpos($option, '=') !== false) {
            list($option, $value) = explode('=', $option);
        }
      
        $value = $this->value($this->options[$name]['type'], $value);
 
        $options[$name] = $value;
   
        return $options;
    }

    protected function parseLongOption($arg, $options)
    {
        $option = substr($arg, 2);
        $name = $this->getOptionName($option);
        if (!isset($this->options[$name])) {
            throw new ConsoleException(sprintf('Unkown option --%s', $name));
        }
        return $this->parseOption($option, $options);
    }

    protected function parseShortOption($arg, $options)
    {
        $option = substr($arg, 1);
        $name = $this->getOptionName($option);
   
        if (!isset($this->shortOptions[$name])) {
            throw new ConsoleException(sprintf('Unkown short option -%s', $name));
        }
        $option = $this->shortOptions[$name]['name'];
        if (strpos($arg, '=') !== false) {
            list($k, $v) = explode('=', $arg);
            $option .= "={$v}";
        }
        return $this->parseOption($option, $options);
    }

    protected function getOptionName($option)
    {
        if (strpos($option, '=') !== false) {
            list($option, $value) = explode('=', $option);
        }
        return $option;
    }

    protected function isLongOption(string $option)
    {
        return (substr($option, 0, 2) === '--');
    }

    protected function isShortOption(string $option)
    {
        return ($option[0] === '-' and substr($option, 0, 2) != '--');
    }

    /**
     *
     * Generats the usage only
     * @param string $name
     * @return void
     */
    public function usage()
    {
        $formatter = new ConsoleHelpFormatter();
        $formatter->setUsage($this->generateUsage($this->command));
        return $formatter->generate();
    }

    /**
     * Generates the help
     *
     * @return string
     */
    public function help()
    {
        $formatter = new ConsoleHelpFormatter();
       
        if ($this->description) {
            $formatter->setDescription($this->description);
        }

        $usages = $this->generateUsage($this->command);
        if ($this->usage) {
            $usages = $usages ."\n" . $this->usage;
        }
        $formatter->setUsage($usages);
       
        $formatter->setArguments($this->generateArguments());
        $formatter->setOptions($this->generateOptions());
        $formatter->setCommands($this->generateCommands());
        if ($this->epilog) {
            $formatter->setEpilog($this->epilog);
        }
        if ($this->help) {
            $formatter->setHelp($this->help);
        }

        return $formatter->generate();
    }

    protected function generateArguments()
    {
        $arguments = [];
        foreach ($this->arguments as $argument) {
            $description = '';
            if ($argument['description']) {
                $description = $argument['description'];
            }
            $arguments[$argument['name']] = $description;
        }
        return $arguments;
    }

    protected function generateCommands()
    {
        $commands = [];
        foreach ($this->commands as $command) {
            $description = '';
            if ($command['description']) {
                $description = $command['description'];
            }
            $commands[$command['name']] = $description;
        }
        return $commands;
    }

    /**
     * Gets the options for help
     *
     * @return array
     */
    protected function generateOptions()
    {
        $options = [];
        foreach ($this->options as $option) {
            $text = '--' . $option['name'];

            if ($option['short']) {
                $text = '-' . $option['short']. ', ' . $text;
            }
            if ($option['type']  !== 'boolean') {
                $text .=  '=' . $option['banner'] ;
            }
            $help = $option['description'];
            if (!empty($option['default'])) {
                $default = " <yellow>[default: {$option['default']}]</yellow>";
                if (is_array($help)) {
                    $rows = count($help);
                    $help[$rows-1] .= $default;
                } else {
                    $help .= $default;
                }
            }
            $options[$text] =  $help;
        }
        return $options;
    }

    protected function generateUsage(string $command ='command')
    {
        $results = [];
     
        $options = $arguments = [];
        foreach ($this->options as $option) {
            if (!empty($option['required'])) {
                $options[] = '--'.  $option['name'];
            }
        }
     
        $options[] = '[options]';
        
        foreach ($this->arguments as $arg) {
            if (!empty($arg['required'])) {
                $arguments[] = $arg['name'];
            } else {
                $arguments[] = "[{$arg['name']}]";
            }
        }
        // Dont duplicate
        if (empty($arguments)) {
            $arguments[] = '[arguments]';
        }
       
        if (!empty($this->commands)) {
            $command .= " command";
        }
     
        return $command . ' ' .  implode(' ', array_merge($options, $arguments));
    }
}
