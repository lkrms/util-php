<?php

declare(strict_types=1);

namespace Lkrms\Cli;

use Lkrms\Assert;
use Lkrms\Console\Console;
use Lkrms\Convert;
use RuntimeException;
use UnexpectedValueException;

/**
 *
 * @package Lkrms
 */
abstract class CliCommand
{
    /**
     * Return the name of the command as an array of its parts
     *
     * At least one component must be returned, and components are required to
     * match the regular expression: `^[a-zA-Z][a-zA-Z0-9_-]*$`
     *
     * A subclass could return the following, for example:
     *
     * ```php
     * ["sync", "canvas", "from-sis"]
     * ```
     *
     * to register itself as the handler for:
     *
     * ```
     * my-cli-app sync canvas from-sis
     * ```
     *
     * @return string[]
     */
    abstract protected function getDefaultName(): array;

    /**
     * Return a list of CliOption objects and/or arrays to create them from
     *
     * The following return values are equivalent:
     *
     * ```php
     * // 1.
     * [
     *   new CliOption(
     *     "dest", "d", "DIR", "Sync files to DIR", CliOptionType::VALUE, null, true
     *   ),
     * ]
     *
     * // 2.
     * [
     *   [
     *     "long"        => "dest",
     *     "short"       => "d",
     *     "valueName"   => "DIR",
     *     "description" => "Sync files to DIR",
     *     "optionType"  => CliOptionType::VALUE,
     *     "required"    => true,
     *   ],
     * ]
     * ```
     *
     * @return array<int,CliOption|array>
     * @see TConstructible::From()
     */
    abstract protected function getOptionList(): array;

    /**
     * Run the command, optionally returning an exit status
     *
     * PHP's exit status will be:
     * 1. the return value of this method (if an `int` is returned)
     * 2. the last value passed to {@see CliCommand::setExitStatus()}, or
     * 3. `0`, indicating success, unless an unhandled error occurs
     *
     * @return int|void
     */
    abstract protected function run(...$params);

    /**
     * @var int
     */
    private $ExitStatus = 0;

    /**
     * @var string[]
     */
    private $QualifiedName;

    /**
     * @var CliOption[]
     */
    private $Options;

    /**
     * @var array<string,CliOption>
     */
    private $OptionsByName = [];

    /**
     * @var array<string,CliOption>
     */
    private $OptionsByKey = [];

    /**
     * @var array<string,CliOption>
     */
    private $HiddenOptionsByKey = [];

    /**
     * @var array<string,string|array|bool|null>
     */
    private $OptionValues;

    /**
     * @var int
     */
    private $OptionErrors;

    /**
     * @var int
     */
    private $NextArgumentIndex;

    /**
     * @var bool
     */
    private $IsHelp = false;

    public static function assertQualifiedNameIsValid(?array $nameParts)
    {
        Assert::notEmpty($nameParts, "nameParts");

        foreach ($nameParts as $i => $name)
        {
            Assert::pregMatch($name, '/^[a-zA-Z][a-zA-Z0-9_-]*$/', "nameParts[$i]");
        }
    }

    /**
     * Create an instance of the command and register it
     *
     * The following statements are equivalent:
     *
     * ```php
     * // 1.
     * MyCliCommand::register();
     *
     * // 2.
     * Cli::registerCommand(new MyCliCommand());
     * ```
     *
     * But the only way to override a command's default `QualifiedName` is with
     * `CliCommand::register()`:
     *
     * ```php
     * MyCliCommand::register(["command", "subcommand", "my-cli-command"]);
     * ```
     *
     * @param array|null $qualifiedName If set, the qualified name returned by
     * the subclass will be ignored.
     */
    final public static function register(array $qualifiedName = null)
    {
        $command = new static();

        if (!is_null($qualifiedName))
        {
            self::assertQualifiedNameIsValid($qualifiedName);
            $command->QualifiedName = $qualifiedName;
        }

        Cli::registerCommand($command);
    }

    final public function getName(): string
    {
        return implode(" ", $this->getQualifiedName());
    }

    final public function getCommandName()
    {
        return Cli::getProgramName() . " " . $this->getName();
    }

    /**
     *
     * @return string[]
     */
    final public function getQualifiedName(): array
    {
        if (!$this->QualifiedName)
        {
            self::assertQualifiedNameIsValid($nameParts = $this->getDefaultName());
            $this->QualifiedName = $nameParts;
        }

        return $this->QualifiedName;
    }

    /**
     *
     * @param CliOption|array $option
     * @param array|null $options
     * @param bool $hide
     */
    private function addOption($option, array & $options = null, $hide = false)
    {
        if (is_array($option))
        {
            $option = CliOption::From($option);
        }

        if (!is_null($options))
        {
            $options[] = $option;
        }

        list ($short, $long, $names) = [$option->Short, $option->Long, []];

        if ($short)
        {
            $names[] = $short;
        }

        if ($long)
        {
            $names[] = $long;
        }

        if (!empty(array_intersect($names, array_keys($this->OptionsByName))))
        {
            throw new UnexpectedValueException("Option names must be unique: " . implode(", ", $names));
        }

        foreach ($names as $key)
        {
            $this->OptionsByName[$key] = $option;
        }

        $this->OptionsByKey[$option->Key] = $option;

        if ($hide)
        {
            $this->HiddenOptionsByKey[$option->Key] = $option;
        }
    }

    private function loadOptions()
    {
        if (!is_null($this->Options))
        {
            return;
        }

        $_options = $this->getOptionList();
        $options  = [];

        foreach ($_options as $option)
        {
            $this->addOption($option, $options);
        }

        if (!array_key_exists("help", $this->OptionsByName))
        {
            $this->addOption([
                "long"  => "help",
                "short" => array_key_exists("h", $this->OptionsByName) ? null : "h"
            ], $options, true);
        }

        $this->Options = $options;
    }

    /**
     *
     * @return CliOption[]
     */
    final public function getOptions(): array
    {
        $this->loadOptions();

        return $this->Options;
    }

    final public function getOptionByName(string $name)
    {
        $this->loadOptions();

        return $this->OptionsByName[$name] ?? false;
    }

    final public function getUsage(bool $line1 = false): string
    {
        $options = "";

        // To produce a one-line summary like this:
        //
        //     sync [-ny] [--verbose] [--exclude PATTERN] --from SOURCE --to DEST
        //
        // We need values like this:
        //
        //     $shortFlag = ['n', 'y'];
        //     $longFlag  = ['verbose'];
        //     $optional  = ['--exclude PATTERN'];
        //     $required  = ['--from SOURCE', '--to DEST'];
        //
        $shortFlag = [];
        $longFlag  = [];
        $optional  = [];
        $required  = [];

        foreach ($this->getOptions() as $option)
        {
            if (array_key_exists($option->Key, $this->HiddenOptionsByKey))
            {
                continue;
            }

            list ($short, $long, $line, $value, $valueName) = [$option->Short, $option->Long, [], [], ""];

            if ($option->IsFlag)
            {
                if ($short)
                {
                    $line[]      = "-{$short}";
                    $shortFlag[] = $short;
                }

                if ($long)
                {
                    $line[] = "--{$long}";

                    if (!$short)
                    {
                        $longFlag[] = $long;
                    }
                }
            }
            else
            {
                $valueName = $option->ValueName;

                if ($valueName != strtoupper($valueName))
                {
                    $valueName = "<" . Convert::toKebabCase($valueName) . ">";
                }

                if ($short)
                {
                    $line[]  = "-{$short}";
                    $value[] = $option->IsValueRequired ? " $valueName" : "[$valueName]";
                }

                if ($long)
                {
                    $line[]  = "--{$long}";
                    $value[] = $option->IsValueRequired ? " $valueName" : "[=$valueName]";
                }

                if ($option->IsRequired)
                {
                    $required[] = $line[0] . $value[0];
                }
                else
                {
                    $optional[] = $line[0] . $value[0];
                }
            }

            if (!$line1)
            {
                // Format:
                //
                //     _-o, --option_[=__VALUE__]
                //       Option description
                //         default: ___auto___
                //         options:
                //         - _option1_
                //         - _option2_
                //         - _option3_
                $sep      = ($option->Description ? "\n      " : "\n    ");
                $options .= ("\n  _" . implode(", ", $line) . "_"
                    . str_replace($valueName, "__" . $valueName . "__", (array_pop($value) ?: ""))
                    . ($option->Description ? "\n    " . $option->Description : "")
                    . ((!$option->IsFlag && $option->DefaultValue) ? $sep . "default: ___" . implode(",", Convert::AnyToArray($option->DefaultValue)) . "___" : "")
                    . ($option->AllowedValues ? $sep . "options:" . $sep . "- _" . implode("_" . $sep . "- _", $option->AllowedValues) . "_" : "")) . "\n";
            }
        }

        $synopsis = (($shortFlag ? " [-" . implode("", $shortFlag) . "]" : "")
            . ($longFlag ? " [--" . implode("] [--", $longFlag) . "]" : "")
            . ($optional ? " [" . implode("] [", $optional) . "]" : "")
            . ($required ? " " . implode(" ", $required) : ""));

        $name    = $this->getCommandName();
        $options = trim($options, "\n");

        return $line1 ? $synopsis :
<<<EOF
___NAME___
  __{$name}__

___SYNOPSIS___
  __{$name}__{$synopsis}

___OPTIONS___
$options
EOF;
    }

    final protected function optionError(string $message)
    {
        Console::Error($this->getCommandName() . ": $message");
        $this->OptionErrors++;
    }

    private function loadOptionValues()
    {
        if (!is_null($this->OptionValues))
        {
            return;
        }

        if (Cli::getRunningCommand() !== $this)
        {
            throw new RuntimeException(static::class . " is not running");
        }

        $this->loadOptions();
        $this->OptionErrors = 0;

        $args   = $GLOBALS["argv"];
        $merged = [];

        for ($i = Cli::getFirstArgumentIndex(); $i < $GLOBALS["argc"]; $i++)
        {
            list ($arg, $short, $matches) = [$args[$i], false, null];

            if (preg_match("/^-([0-9a-z])(.*)/i", $arg, $matches))
            {
                $name  = $matches[1];
                $value = $matches[2] ?: null;
                $short = true;
            }
            elseif (preg_match("/^--([0-9a-z_-]+)(=(.*))?\$/i", $arg, $matches))
            {
                $name  = $matches[1];
                $value = ($matches[2] ?? null) ? $matches[3] : null;
            }
            else
            {
                if ($arg == "--")
                {
                    $i++;
                }
                elseif (substr($arg, 0, 1) == "-")
                {
                    $this->optionError("invalid argument '$arg'");

                    continue;
                }

                break;
            }

            $option = $this->OptionsByName[$name] ?? null;

            if (is_null($option))
            {
                $this->optionError("unknown option '$name'");

                continue;
            }
            elseif ($option->IsFlag)
            {
                // Handle multiple short flags per argument, e.g. `cp -rv`
                if ($short && $value)
                {
                    $args[$i] = "-$value";
                    $i--;
                }

                $value = true;
            }
            elseif (!$option->IsValueRequired)
            {
                $value = $value ?: "";
            }
            elseif ($option->IsValueRequired)
            {
                if (is_null($value))
                {
                    $i++;

                    if (is_null($value = ($args[$i] ?? null)))
                    {
                        // Allow null to be stored to prevent an additional
                        // "argument required" error
                        $this->optionError("{$option->DisplayName} value required");
                        $i--;
                    }
                }
            }

            $key = $option->Key;

            if (isset($merged[$key]))
            {
                $merged[$key] = array_merge(Convert::AnyToArray($merged[$key]), Convert::AnyToArray($value));
            }
            else
            {
                $merged[$key] = $value;
            }
        }

        $this->NextArgumentIndex = $i;

        foreach ($merged as $key => $value)
        {
            $option = $this->OptionsByKey[$key];

            if ($option->Long == "help")
            {
                $this->IsHelp = true;

                continue;
            }

            if (!$option->MultipleAllowed && is_array($value))
            {
                $this->optionError("{$option->DisplayName} cannot be used multiple times");
            }

            if (!is_null($option->AllowedValues) && !empty($invalid = array_diff(Convert::AnyToArray($value), $option->AllowedValues)))
            {
                $this->optionError("invalid {$option->DisplayName} " . Convert::NumberToNoun(count($invalid), "value") . ": " . implode(", ", $invalid));
            }
        }

        foreach ($this->OptionsByKey as $option)
        {
            if ($option->IsRequired && !array_key_exists($option->Key, $merged))
            {
                if (!($GLOBALS["argc"] - Cli::getFirstArgumentIndex() == 1 && $this->IsHelp))
                {
                    $this->optionError("{$option->DisplayName} argument required");
                }
            }
            else
            {
                $value = $merged[$option->Key] ?? $option->DefaultValue;

                if ($option->IsFlag && $option->MultipleAllowed)
                {
                    $value = is_null($value) ? 0 : count(Convert::AnyToArray($value));
                }
                elseif ($option->MultipleAllowed)
                {
                    $value = is_null($value) ? [] : Convert::AnyToArray($value);
                }

                $option->setValue($value);
            }
        }

        if ($this->OptionErrors)
        {
            throw new CliInvalidArgumentException();
        }

        $this->OptionValues = $merged;
    }

    /**
     * Get the value of a command line option
     *
     * For values that can be given multiple times, an array of values will be
     * returned. For flags that can be given multiple times, the number of uses
     * will be returned.
     *
     * @param string $name Either the `Short` or `Long` name of the option
     * @return string|string[]|bool|int|null
     */
    final public function getOptionValue(string $name)
    {
        if (!($option = $this->getOptionByName($name)))
        {
            throw new UnexpectedValueException("No option with name '$name'");
        }

        $this->loadOptionValues();

        return $option->Value;
    }

    final public function getAllOptionValues()
    {
        $this->loadOptionValues();

        $values = [];

        foreach ($this->Options as $option)
        {
            $name          = $option->Long ?: $option->Short;
            $values[$name] = $option->Value;
        }

        return $values;
    }

    final public function __invoke(): int
    {
        $this->loadOptionValues();

        if ($this->IsHelp)
        {
            Console::PrintTo($this->getUsage(), ...Console::GetOutputTargets());

            return 0;
        }

        $return = $this->run(...array_slice($GLOBALS['argv'], $this->NextArgumentIndex));

        if (is_int($return))
        {
            return $return;
        }

        return $this->ExitStatus;
    }

    /**
     * Set the command's return value / exit status
     *
     * @param int $status
     * @see CliCommand::run()
     */
    protected function setExitStatus(int $status)
    {
        $this->ExitStatus = $status;
    }
}

