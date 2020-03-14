# Gravity Forms Blueshift Feed Add-On

Integrates Gravity Forms with Blueshift, allowing form submissions to be automatically sent as mailings via Blueshift.

Construct email content and send mailings via Blueshift using Gravity Forms.

## Installation

Requires Gravity Forms Version 1.9.14.26 or higher.

1. Download or clone the repo files and move them to the `wp-content/plugins/` folder of your WordPress site. You can also download the repo as a zip file and upload via the WordPress Dashboard.
2. Go to WordPress Dashboard > Plugins and activate Gravity Forms Blueshift.
3. Go to WordPress Dashboard > Forms > Settings > Blueshift.
4. Enter your Blueshift API credentials (URL and API key).

## Usage Example

To create a new Blueshift Feed, select a form in Gravity Forms, and go to `Settings > Blueshift`

## Development Setup

To develop this plugin, all you need is an active WordPress installation with Gravity Forms installed.

### Development Resources

[Gravity Forms Add-On Framework Documentation][gfaddonframework]

[Blueshift API Documentation][blueshiftsapicalls]

## Release History

<!-- * 1.0.0
    * The first proper release -->
* 0.0.1
    * First build out

## Todo

* Add in the full API and feed processor 

## Contributing

1. Fork it (<https://github.com/yourname/yourproject/fork>)
2. Create your feature branch (`git checkout -b feature/fooBar`)
3. Commit your changes (`git commit -am 'Add some fooBar'`)
4. Push to the branch (`git push origin feature/fooBar`)
5. Create a new Pull Request

<!-- Markdown link & img dfn's -->
[gfaddonframework]: https://docs.gravityforms.com/category/developers/php-api/add-on-framework/
[blueshiftsapicalls]: https://developer.blueshift.com/reference