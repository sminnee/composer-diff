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
        $from = $input->getArgument('sha-from');
        $to = $input->getArgument('sha-to');

        $fileArg = escapeshellarg("$from:composer.lock");
        $lockFrom = trim(`git show $fileArg`);
        if(!$lockFrom) {
            throw new \LogicException("composer.lock can't be found in $from");
        }

        if($to) {
            $fileArg = escapeshellarg("$to:composer.lock");
            $lockTo = trim(`git show $fileArg`);
            if(!$lockTo) {
                throw new \LogicException("composer.lock can't be found in $to");
            }
        } else {
            $fileArg = escapeshellarg("$from:composer.lock");
            if(file_exists("composer.lock")) {
                $lockTo = trim(file_get_contents("composer.lock"));
                if(!$lockTo) {
                    throw new \LogicException("composer.lock is empty");
                }

            } else {
                throw new \LogicException("composer.lock can't be found in current folder");
            }
        }

        // Diff the project

        if($to) {
            $rangeArg = escapeshellarg("$from..$to");
        } else {
            $rangeArg = escapeshellarg("$from");
        }

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
     * Return a map of package name to path on disk
     */
    protected function packagePaths() {
        $raw = trim(`composer show -i --path`);
        if(!$raw) {
            throw new \LogicException("'composer show -i --path' returned nothing");
        }

        $output = array();
        foreach(explode("\n", $raw) as $line) {
            list($package, $path) = preg_split("/\\s+/", $line, 2);
            if(!$package || !$path) {
                throw new \LogicException("Bad line '$line'");
            }
            $output[$package] = $path;
        }
        return $output;
    }

 

    protected function reposFromLock($lockContent) {
        $lock = json_decode($lockContent, true);

        $output = array();

        foreach($lock['packages'] as $package) {
            switch($package['source']['type']) {
                case 'git':
                    $output[$package['name']] = $package['source'];
                    break;


                default:
                    throw new \LogicExpcetion("Bad package source type: '" . $package['source']['type'] . "'");
            }
        }

        return $output;
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
