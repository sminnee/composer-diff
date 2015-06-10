<?php

namespace Sminnee\ComposerDiff;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DiffCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('diff')
            ->setDescription('Show differences in your project and linked packages')
            ->addArgument(
                'sha-from',
                InputArgument::REQUIRED,
                'Which project SHA to compare as the start?'
            )
            ->addArgument(
                'sha-to',
                InputArgument::OPTIONAL,
                'Which project SHA to compare as the end? Defaults to current check-out'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $output->writeln("hello");
    }
}
