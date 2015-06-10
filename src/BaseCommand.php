<?php

namespace Sminnee\ComposerDiff;

use Symfony\Component\Console\Command\Command;

class BaseCommand extends Command {

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

	protected function reposFromLock($lockContent)
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
}