swissspidy/validate-readme-command
==================================

Validate WordPress plugin readme files

[![Testing](https://github.com/swissspidy/validate-readme-command/actions/workflows/testing.yml/badge.svg)](https://github.com/swissspidy/validate-readme-command/actions/workflows/testing.yml) [![Build Status](https://travis-ci.org/swissspidy/validate-readme-command.svg?branch=master)](https://travis-ci.org/swissspidy/validate-readme-command)

Quick links: [Using](#using) | [Installing](#installing) | [Contributing](#contributing) | [Support](#support)

## Using

~~~
wp plugin validate-readme <readme> [--format=<format>] [--strict]
~~~

**OPTIONS**

	<readme>
		Readme contents, path to a readme file, or readme URL.

	[--format=<format>]
		Desired output format
		---
		default: default
		options:
		  - default
		  - github-actions

	[--strict]
		Whether to perform a strict check by treating all messages as errors.

**EXAMPLES**

    # Validate the Hello Dolly readme
    $ wp plugin validate-readme \
           https://plugins.svn.wordpress.org/hello-dolly/trunk/readme.txt
    Success: Hello World!

## Installing

Installing this package requires WP-CLI v2.5 or greater. Update to the latest stable release with `wp cli update`.

Once you've done so, you can install the latest stable version of this package with:

```bash
wp package install swissspidy/validate-readme-command:@stable
```

To install the latest development version of this package, use the following command instead:

```bash
wp package install swissspidy/validate-readme-command:dev-master
```

## Contributing

We appreciate you taking the initiative to contribute to this project.

Contributing isn’t limited to just code. We encourage you to contribute in the way that best fits your abilities, by writing tutorials, giving a demo at your local meetup, helping other users with their support questions, or revising our documentation.

For a more thorough introduction, [check out WP-CLI's guide to contributing](https://make.wordpress.org/cli/handbook/contributing/). This package follows those policy and guidelines.

### Reporting a bug

Think you’ve found a bug? We’d love for you to help us get it fixed.

Before you create a new issue, you should [search existing issues](https://github.com/swissspidy/validate-readme-command/issues?q=label%3Abug%20) to see if there’s an existing resolution to it, or if it’s already been fixed in a newer version.

Once you’ve done a bit of searching and discovered there isn’t an open or fixed issue for your bug, please [create a new issue](https://github.com/swissspidy/validate-readme-command/issues/new). Include as much detail as you can, and clear steps to reproduce if possible. For more guidance, [review our bug report documentation](https://make.wordpress.org/cli/handbook/bug-reports/).

### Creating a pull request

Want to contribute a new feature? Please first [open a new issue](https://github.com/swissspidy/validate-readme-command/issues/new) to discuss whether the feature is a good fit for the project.

Once you've decided to commit the time to seeing your pull request through, [please follow our guidelines for creating a pull request](https://make.wordpress.org/cli/handbook/pull-requests/) to make sure it's a pleasant experience. See "[Setting up](https://make.wordpress.org/cli/handbook/pull-requests/#setting-up)" for details specific to working on this package locally.

## Support

GitHub issues aren't for general support questions, but there are other venues you can try: https://wp-cli.org/#support


*This README.md is generated dynamically from the project's codebase using `wp scaffold package-readme` ([doc](https://github.com/wp-cli/scaffold-package-command#wp-scaffold-package-readme)). To suggest changes, please submit a pull request against the corresponding part of the codebase.*
