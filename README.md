composer-diff
=============

`composer-diff` performs a `git diff` command on your project.

Unlike `git diff`, however, it will also return differences in any packages marked in your composer.lock file

Usage
-----

    composer-diff diff sha-from [sha-to]

 * `sha-from` is the SHA of your project to use as the starting point
 * `sha-to` is the SHA of your project to use as the end point. If ommitted, the current check-out of code is used

Limitations
-----------

Isn't actually implemented yet, it's just a hello-world so far.
