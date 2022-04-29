Feature: Validate WordPress plugin readme

  Scenario: Bail for invalid readme

    When I try `wp plugin validate-readme ""`
    Then STDERR should contain:
      """
      No readme provided
      """

    When I try `wp plugin validate-readme non-existent-readme.txt`
    Then STDOUT should contain:
      """
      Success: Readme successfully validated.
      """

    When I try `wp plugin validate-readme https://example.com/non-existent.txt`
    Then STDERR should contain:
      """
      Incorrect readme URL provided
      """

  Scenario: Validates local readme.txt file
    Given an empty directory
    And a readme.txt file:
      """
      === Example Plugin ===
      Contributors: (this should be a list of wordpress.org userid's)
      Donate link: https://example.com/
      Tags: tag1, tag2
      Requires at least: 4.7
      Tested up to: 5.4
      Stable tag: 4.3
      Requires PHP: 7.0
      License: GPLv2 or later
      License URI: https://www.gnu.org/licenses/gpl-2.0.html

      Here is a short description of the plugin.  This should be no more than 150 characters.  No markup here.

      == Description ==

      This is the long description.  No limit, and you can use Markdown (as well as in the following sections).

      For backwards compatibility, if this section is missing, the full length of the short description will be used, and
      Markdown parsed.

      A few notes about the sections above:

      * "Contributors" is a comma separated list of wordpress.org usernames
      * "Tags" is a comma separated list of tags that apply to the plugin
      * "Requires at least" is the lowest version that the plugin will work on
      * "Tested up to" is the highest version that you've *successfully used to test the plugin*
      * Stable tag must indicate the Subversion "tag" of the latest stable version

      Note that the `readme.txt` value of stable tag is the one that is the defining one for the plugin.  If the `/trunk/readme.txt` file says that the stable tag is `4.3`, then it is `/tags/4.3/readme.txt` that'll be used for displaying information about the plugin.

      If you develop in trunk, you can update the trunk `readme.txt` to reflect changes in your in-development version, without having that information incorrectly disclosed about the current stable version that lacks those changes -- as long as the trunk's `readme.txt` points to the correct stable tag.

      If no stable tag is provided, your users may not get the correct version of your code.

      == Frequently Asked Questions ==

      = A question that someone might have =

      An answer to that question.

      = What about foo bar? =

      Answer to foo bar dilemma.

      == Screenshots ==

      1. This screen shot description corresponds to screenshot-1.(png|jpg|jpeg|gif). Screenshots are stored in the /assets directory.
      2. This is the second screen shot

      == Changelog ==

      = 1.0 =
      * A change since the previous version.
      * Another change.

      = 0.5 =
      * List versions from most recent at top to oldest at bottom.

      == Upgrade Notice ==

      = 1.0 =
      Upgrade notices describe the reason a user should upgrade.  No more than 300 characters.

      = 0.5 =
      This version fixes a security related bug.  Upgrade immediately.

      == A brief Markdown Example ==

      Markdown is what the parser uses to process much of the readme file.

      [markdown syntax]: https://daringfireball.net/projects/markdown/syntax

      Ordered list:

      1. Some feature
      1. Another feature
      1. Something else about the plugin

      Unordered list:

      * something
      * something else
      * third thing

      Links require brackets and parenthesis:

      Here's a link to [WordPress](https://wordpress.org/ "Your favorite software") and one to [Markdown's Syntax Documentation][markdown syntax]. Link titles are optional, naturally.

      Blockquotes are email style:

      > Asterisks for *emphasis*. Double it up  for **strong**.

      And Backticks for code:

      `<?php code(); ?>`
      """

    When I run `wp plugin validate-readme readme.txt`
    Then STDOUT should contain:
      """
      Success: Readme successfully validated.
      """
    And STDERR should be empty

  Scenario: Validates remote example readme.txt file

    When I try `wp plugin validate-readme https://wordpress.org/plugins/readme.txt`
    Then STDERR should contain:
      """
      We cannot find a plugin name in your readme.
      """
    And STDERR should contain:
      """
      Please change `Plugin Name` to reflect the actual name of your plugin.
      """
    And STDOUT should be empty

  Scenario: Validates remote plugin readme.txt file

    When I try `wp plugin validate-readme https://plugins.svn.wordpress.org/hello-dolly/trunk/readme.txt`
    Then STDOUT should contain:
      """
      Success: Readme successfully validated.
      """
    And STDERR should contain:
      """
      Warning: The `Requires PHP` field is missing. It should be defined here, or in your main plugin file.
      """
    And STDERR should contain:
      """
      Warning: No `== Frequently Asked Questions ==` section was found
      """
    And STDERR should contain:
      """
      Warning: No `== Changelog ==` section was found
      """
    And STDERR should contain:
      """
      Warning: No `== Upgrade Notice ==` section was found
      """
    And STDERR should contain:
      """
      Warning: No `== Screenshots ==` section was found
      """
    And STDERR should contain:
      """
      Warning: No donate link was found
      """


  Scenario: Validates readme with strict mode

    When I try `wp plugin validate-readme https://plugins.svn.wordpress.org/hello-dolly/trunk/readme.txt --strict`
    Then STDERR should contain:
      """
      Error: Readme validated with errors.
      """
    And STDERR should contain:
      """
      Error: The `Requires PHP` field is missing. It should be defined here, or in your main plugin file.
      """
    And STDERR should contain:
      """
      Error: No `== Frequently Asked Questions ==` section was found
      """
    And STDERR should contain:
      """
      Error: No `== Changelog ==` section was found
      """
    And STDERR should contain:
      """
      Error: No `== Upgrade Notice ==` section was found
      """
    And STDERR should contain:
      """
      Error: No `== Screenshots ==` section was found
      """
    And STDERR should contain:
      """
      Error: No donate link was found
      """

  Scenario: Validates readme with GitHub Actions output format

    When I run `wp plugin validate-readme https://plugins.svn.wordpress.org/hello-dolly/trunk/readme.txt --format=github-actions`
    Then STDOUT should contain:
      """
      Success: Readme successfully validated.
      """
    And STDOUT should contain:
      """
      ::notice file=readme.txt::The `Requires PHP` field is missing. It should be defined here, or in your main plugin file.
      """
    And STDOUT should contain:
      """
      ::notice file=readme.txt::No `== Frequently Asked Questions ==` section was found
      """
    And STDOUT should contain:
      """
      ::notice file=readme.txt::No `== Changelog ==` section was found
      """
    And STDOUT should contain:
      """
      ::notice file=readme.txt::No `== Upgrade Notice ==` section was found
      """
    And STDOUT should contain:
      """
      ::notice file=readme.txt::No `== Screenshots ==` section was found
      """
    And STDOUT should contain:
      """
      ::notice file=readme.txt::No donate link was found
      """

  Scenario: Validates readme in strict mode with GitHub Actions output format in strict mode

    When I try `wp plugin validate-readme https://plugins.svn.wordpress.org/hello-dolly/trunk/readme.txt --format=github-actions --strict`
    Then STDERR should contain:
      """
      Error: Readme validated with errors.
      """
    And STDOUT should contain:
      """
      ::error file=readme.txt::The `Requires PHP` field is missing. It should be defined here, or in your main plugin file.
      """
    And STDOUT should contain:
      """
      ::error file=readme.txt::No `== Frequently Asked Questions ==` section was found
      """
    And STDOUT should contain:
      """
      ::error file=readme.txt::No `== Changelog ==` section was found
      """
    And STDOUT should contain:
      """
      ::error file=readme.txt::No `== Upgrade Notice ==` section was found
      """
    And STDOUT should contain:
      """
      ::error file=readme.txt::No `== Screenshots ==` section was found
      """
    And STDOUT should contain:
      """
      ::error file=readme.txt::No donate link was found
      """
