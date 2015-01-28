# About

This is a set of general purpose libraries that I have written and used across
a number of projects.

**Only the code under 'ArtfulRobot/' should be used** The rest is strictly deprecated
and will be removed at my earliest opportunity.

The classes under the `ArtfulRobot` directory are compatible with a PSR-0 autoloader and
the code is aiming for
[PSR-2](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md),
it may not all be quite there yet.


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

# Now available via composer, e.g.

Create `composer.json` file:

    {
      require: [
        { "artfulrobot/artfulrobot": "1.0.0" }
      ]
    }

Download [composer](https://getcomposer.org/download/) then run

    $ php composer.phar install



