<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Composer\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Input\InputInterface;
use \Symfony\Component\Console\Output\OutputInterface;

class Composer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'composer';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Use included composer';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        if (PHP_SAPI !== 'cli') {
            echo 'Warning: Composer should be invoked via the CLI version of PHP, not the ' . PHP_SAPI . ' SAPI' . PHP_EOL;
        }

        error_reporting(-1);

        if (function_exists('ini_set')) {
            @ini_set('display_errors', 1);

            $memoryInBytes = function ($value) {
                $unit = strtolower(substr($value, -1, 1));
                $value = (int) $value;
                switch ($unit) {
                    case 'g':
                        $value *= 1024;
                    // no break (cumulative multiplier)
                    case 'm':
                        $value *= 1024;
                    // no break (cumulative multiplier)
                    case 'k':
                        $value *= 1024;
                }

                return $value;
            };

            $memoryLimit = trim(ini_get('memory_limit'));
            // Increase memory_limit if it is lower than 1GB
            if ($memoryLimit != -1 && $memoryInBytes($memoryLimit) < 1024 * 1024 * 1024) {
                @ini_set('memory_limit', '1G');
            }
            unset($memoryInBytes, $memoryLimit);
        }
        if (is_callable([$this->input, '__toString'])) {
            // strip "composer "
            $token = substr($this->input->__toString(), 9);
            $input = new StringInput($token);
        } else {
            $arguments = $this->argument();
            $input = new ArrayInput($arguments['params']);
        }
        $input->setInteractive(false);

        // run the command application
        $application = @new Application();
        $application->run($input);

    }

    public function run(InputInterface $input, OutputInterface $output)
    {
        $options = $input->__toString();
        $arr = explode(' ', $options);
        array_shift($arr);
        foreach ($arr as $i => &$option) {
            $option = trim($option, "'");
            if (strpos($option, '-') === 0) {
                $option = '{' . $option . '?}';
            } else {
                $option = '{k'.$i.'}';
            }
        }
        $this->signature = implode(' ', $arr);
        $this->configureUsingFluentDefinition();
        $this->setDescription($this->description);

        return parent::run($input, $output);
    }
}
