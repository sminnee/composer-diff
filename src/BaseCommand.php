<?php

namespace Sminnee\ComposerDiff;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class BaseCommand extends Command
{
	/**
	 * Return a map of package name to path on disk
	 */
	protected function packagePaths()
	{
		$raw = trim(`composer show -i --path`);
		if (!$raw) {
			throw new \LogicException("'composer show -i --path' returned nothing");
		}

		$output = array();
		foreach (explode("\n", $raw) as $line) {
			list($package, $path) = preg_split("/\\s+/", $line, 2);
			if (!$package || !$path) {
				throw new \LogicException("Bad line '$line'");
			}
			$output[$package] = $path;
		}
		return $output;
	}

	/**
	 * Return an array with lockFrom, lockTo, and rangeArg
	 */
	protected function getGitArguments(InputInterface $input, OutputInterface $output)
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

		return array($lockFrom, $lockTo, $rangeArg);
	}

	/**
	 * @param $lockContent
	 * @return array
	 */
	protected function reposFromLockfile($lockContent)
	{
		$lock = json_decode($lockContent, true);

		$output = array();

		foreach ($lock['packages'] as $package) {
			switch ($package['source']['type']) {
				case 'git':
					$output[$package['name']] = $package['source'];
					break;
				default:
					throw new \LogicException("Bad package source type: '" . $package['source']['type'] . "'");
			}
		}

		return $output;
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @param $argument
	 */
	protected function exec($argument, InputInterface $input, OutputInterface $output)
	{
		list($lockFrom, $lockTo, $rangeArg) = $this->getGitArguments($input, $output);

		$output->writeln("<info>Project differences</info>");

		$cmdProcess = new Process("git $argument $rangeArg");
		$cmdProcess->run();
		$output->writeln($cmdProcess->getOutput());

		$reposFrom = $this->reposFromLockfile($lockFrom);
		$reposTo = $this->reposFromLockfile($lockTo);

		$packagePaths = $this->packagePaths();

		foreach ($reposTo as $package => $info) {
			if (!isset($reposFrom[$package])) {
				$output->writeln("<info>$package</info>");
				$output->writeln('<comment>doesn\'t exists in '.$input->getArgument('sha-from').'</comment>'.PHP_EOL);
				continue;
			}
			if ($info != $reposFrom[$package]) {
				$path = $packagePaths[$package];

				$output->writeln("<info>$package in $path</info>");
                if (!is_dir("$path/.git")) {
                    // check for github
                    if (preg_match('|https://github.com/([^/]+)/([^/]+).git|',$info['url'],$match)) {
                        // take commit message from the github api
                        $compareUrl = 'https://api.github.com/repos/'.$match[1].'/'.$match[2].'/compare/'.$reposFrom[$package]['reference'].'...'. $info['reference'];

                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/vnd.github.v3+json','User-Agent: https://github.com/mtheunissen82/composer-diff'));
                        #curl_setopt($ch, CURLOPT_USERPWD, 'test:test' );
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_URL, $compareUrl );
                        $result = curl_exec($ch);

                        $compare = json_decode($result);
                        if (isset($compare->commits) && is_array($compare->commits)) {
                            foreach ($compare->commits as $commit) {
                                // take first line from commit message
                                $output->writeln(strtok($commit->commit->message,"\n"));
                            }
                            $output->writeln('');
                        } else {
                            $output->writeln("<error>Error reading commits from github " . $compareUrl . "</error>");
                            if ($compare && isset($compare->message)) {
                                // error message from github (authentication, rate limiting)
                                $output->writeln("<error>" . trim($compare->message) . "</error>" . PHP_EOL);
                            } else {
                                // general error
                                $output->writeln("<error>" . trim($result) . "</error>" . PHP_EOL);
                            }
                        }
                    } else {
                        // @TODO maybe ask packagist
                        $output->writeln('<comment>Not a .git repo</comment>'.PHP_EOL);
                        continue;
                    }
                } else {
                    $gitDirArg = escapeshellarg("--git-dir=$path/.git");
                    $shaArg = escapeshellarg($reposFrom[$package]['reference'] . '..' . $info['reference']);
                    $cmd = "git $gitDirArg $argument $shaArg";
                    $this->runCommand($cmd, $gitDirArg, $output);
                }
			}
		}
	}

	/**
	 * Run a git command
	 * @param string $cmd
	 * @param string $gitDirArg
	 * @param OutputInterface $output
	 */
	protected function runCommand($cmd, $gitDirArg, OutputInterface $output)
	{
		$diff = new Process($cmd);
		$diff->run();
		if (!$diff->isSuccessful()) {
			// this is signals that the git remote info is outdated
			if (stristr($diff->getErrorOutput(), 'fatal: Invalid revision range')) {
				$fetch = new Process("git $gitDirArg fetch");
				$fetch->run();
				$output->writeln($fetch->getOutput());
				// try again
				$diff = new Process($cmd);
				$diff->run();
			}
		}

		if (!$diff->isSuccessful()) {
			$output->writeln("<error>" . trim($diff->getErrorOutput()) . "</error>");
		}
		$output->writeln(trim($diff->getOutput()) . PHP_EOL);
	}
}
