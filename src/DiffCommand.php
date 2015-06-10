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
	    list($lockFrom, $lockTo, $rangeArg) = $this->getGitArguments($input, $output);

	    $output->writeln("<info>Project differences</info>");
        $output->writeln(`git diff $rangeArg`);

        // Diff the packages

        $reposFrom = $this->reposFromLock($lockFrom);
        $reposTo = $this->reposFromLock($lockTo);

        $packagePaths = $this->packagePaths();

        foreach($reposTo as $package => $info) {
            if($info != $reposFrom[$package]) {
                $path = $packagePaths[$package];

                $output->writeln("<info>$package in $path</info>");
                $output->writeln($this->diffRepo($path, $reposFrom[$package]['reference'], $info['reference']));
            }
        }
    }

    /**
     * Run a diff on a checked out repo
     */
    protected function diffRepo($path, $shaFrom, $shaTo) {
        $gitdirArg = escapeshellarg("--git-dir=$path/.git");
        $shaArg = escapeshellarg("$shaFrom..$shaTo");
        return trim(`git $gitdirArg diff $shaArg`);
    }
}
