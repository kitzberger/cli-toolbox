<?php

namespace Kitzberger\CliToolbox\Command;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class AbstractCommand extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var SymfonyStyle
     */
    protected $io = null;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        return self::FAILURE;
    }

    /**
     * Outputs specified text to the console window and appends a line break
     *
     * @param  string $string Text to output
     * @param  array  $arguments Optional arguments to use for sprintf
     * @return void
     */
    protected function outputLine(string $string, $arguments = [])
    {
        if ($this->io) {
            $this->io->text(vsprintf($string, $arguments));
        }
    }
}
