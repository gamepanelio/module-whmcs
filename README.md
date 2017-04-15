GamePanel.io WHMCS Provisioning Module
======================================

This module can be used to automate the provisioning of services from WHMCS
 to your [GamePanel.io](https://gamepanel.io) instance.

## Installation

This project uses [composer](https://getcomposer.org/) to manage it's dependencies.

WHMCS doesn't use composer to manage modules, so we pre-packed everything you need into one neat little zip file.

1. Head to the [latest release](https://github.com/gamepanelio/module-whmcs/releases/latest) and download the `gamepanelio.zip` file. 
2. Unzip & follow the instructions supplied.

## Building

If you want to build the module from the source and use it in WHMCS, we've supplied the script to do it.

1. Clone this repo
2. Run
   ```bash
   composer install
   ```
3. Run the package script
   ```bash
   sh bin/package-module.sh
   ```
4. Unzip the `build/gamepanelio.zip` file and upload to your WHMCS

## Tests

This module has [PHPUnit](https://phpunit.de/) tests.

After installing via composer, run them like so:

```bash
./vendor/bin/phpunit
```

## License

This module is licensed under the MIT license. See the `LICENSE` file for
 more info.
