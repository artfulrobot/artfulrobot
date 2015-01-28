# About

This is a set of general purpose libraries that I have written and used across
a number of projects.

**Only the code under 'ArtfulRobot/' should be used** The rest is strictly deprecated
and will be removed at my earliest opportunity.

The classes under the `ArtfulRobot` directory are compatible with a PSR-0 autoloader and
the code is aiming for
[PSR-2](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md),
it may not all be quite there yet.


## ArtfulRobot\CSV

Creates CSV files. Can output them to the browser with correct headers.

## ArtfulRobot\Template

Yet another implementation of php template.

## ArtfulRobot\Utils

Various utils, e.g. `ArtfulRobot\Utils::arrayValue($key, $array, $default)`

## ArtfulRobot\Email

Class for generating and sending emails, including MIME/HTML emails with
attachments, inline images, text versions etc.

Example:

```php
$email = new ArtfulRobot\Email(
  $to = 'friend@example.com',
  $subject = 'demo',
  $body = '<p><strong>Hello</strong> world.</p>',
  $from = '"Fred Flintstone" <fred@example.com>"',
  $return_path = 'valid-on-your-server-or-verp@example.com' );
$email->send();
```

## ArtfulRobot\AJAX

Platform independent JS/PHP library to handle ajax requests and responses.
Requests specify the class "Module" and method they want to use. Responses
include json object, error message (and somtimes html, although usually better
to put this in the json response).

The JS stuff is more interesting and goes beyond Ajax, e.g. providing ways
to create classes (with inheritance) that use JS's object prototypes to provide
a more familiar interface for creating objects.

Also there's a class that can be used to create javascript objects that can
communicate with eachother, and use others of these objects within them. This
has proved a very useful framework, e.g. a main app class may re-use a "select
something" class.

Finally there are some helper methods, including a nice way to create HTML
from array structures (which I think looks nicer than jQuery's).

## ArtfulRobot\Debug

Debugger. This system acts like a log. Code can call `ArtfulRobot\Debug::log()`
to add messages of various levels of importance. The debugger can be configured
to do different things based on the importance or the way in which code execution
ends.

For example, it can write messages (all or of a certain level) to a file;
it can send an email with the full log on catching an exception; it can output
coloured log messages to a terminal; it can output a chunk of html that can
be included in output on a development site.

Messages can be grouped, and these can be nested, and at the closing of a group the
execution time is printed, so you can identify bottlenecks.

Several profiles exist for common sets of settings.

It's potentially very verbose, so use with care. It's turned off by default.

In development I've moved over to using Xdebug with Vim, and as I mostly use Drupal
these days I often use that platform's own watchdog function, but it's still useful
to be able to log things that you want to keep an eye on in a platform-independent
way.


## ArtfulRobot\PDO

Classes for ORM with mysql databases. This can generate a PHP class from a MySQL/MariaDB
table which you can then customise as you need.

Table classes provide easy CRUD operations, and can be helpful with dealing
with collections/bulk ops.

The main `ArtfulRobot\PDO` class provides many helper methods such as
`$database->fetchRowsAssoc()`, and most of these methods take a custom
query object, `ArtfulRobot\PDO\_Query` which allows you to do things like
passing an array of values to a placeholder for an `IN` clause.


# Now available via composer, e.g.

Create `composer.json` file:

    {
      require: [
        { "artfulrobot/artfulrobot": "1.0.0" }
      ]
    }

Download [composer](https://getcomposer.org/download/) then run

    $ php composer.phar install


# Deprecated and dragonous

As mentioned above, anything outside of the ArtfulRobot directory
should NOT be used, is probably hideously coded, will be removed without
warning.

