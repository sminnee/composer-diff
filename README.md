composer-diff
=============

`composer-diff` performs a `git diff` command on your project.

Unlike `git diff`, however, it will also return differences in any packages marked in your composer.lock file

Installation
------------

A good way to install CLI tools from composer is 'composer global require':

    composer global require sminnee/composer-diff

Then, if you haven't already, add `~/.composer/vendor/bin` to your shell path.

The binary will be installed to ~/.composer/vendor/bin/composer-diff.

Usage
-----

You can see the changes themselves this way:

    composer-diff diff sha-from [sha-to]

 * `sha-from` is the SHA of your project to use as the starting point
 * `sha-to` is the SHA of your project to use as the end point. If ommitted, the current check-out of code is used

If you wish to see the log messages instead of the changes, use this command:

    composer-diff log sha-from [sha-to]

Limitations
-----------

 * Doesn't work if a package isn't checked out in your project as a git repo
 * Output not the best
