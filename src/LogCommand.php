<?php

namespace Sminnee\ComposerDiff;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class LogCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('log')
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
		$argument = 'log --oneline';
		$this->exec($argument, $input, $output);
	}

}
