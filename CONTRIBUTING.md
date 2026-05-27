# Contributing to PHPVector

Thanks for your interest in improving PHPVector. This document explains how to set up the project and submit changes.

## Requirements

* PHP 8.2 or newer.
* [Composer](https://getcomposer.org/).
* `ext-pcntl` is optional and only enables asynchronous document writes.

## Setup

```bash
git clone https://github.com/ezimuel/PHPVector.git
cd PHPVector
composer install
```

## Running checks

Run the full suite before opening a pull request:

```bash
composer test       # PHPUnit
composer phpstan    # static analysis
composer coverage   # HTML coverage report in .coverage/
```

All three must pass. New code should ship with tests, including the failing case the change fixes or the behaviour the feature adds.

## Pull requests

1. Fork the repository and create a topic branch from `main`.
2. Keep each pull request focused on a single change.
3. Write clear commit messages in the imperative mood.
4. Make sure tests and static analysis pass locally.
5. Update the documentation and `CHANGELOG.md` when behaviour, setup, or the public API changes.

## Reporting bugs

Open an issue using the bug report template. Include the PHP version, a minimal reproduction, and the expected versus actual behaviour.

## Code of conduct

By participating you agree to abide by the [Code of Conduct](CODE_OF_CONDUCT.md).
