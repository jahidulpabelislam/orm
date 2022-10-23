# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.1] - 2022-10-23

### Added

- officially specify support for PHP 8.0+
- added PHP CI workflow using GitHub actions

### Changed

- fixed docblocks

## [1.1.0] - 2022-09-23

### Added

- `Entity`: store deleted state on Entity instance

## [1.0.2] - 2022-09-20

### Updated

- `Entity::getById`: added a check on value before querying database

## [1.0.1] - 2022-09-20

### Updated

- `Entity::getById`: updated getById so limit of 1 is set if value isn't an array

## [1.0.0] - 2022-09-07

First release! :fire:

[unreleased]: https://github.com/jahidulpabelislam/orm/compare/v1.1.1...HEAD
[1.1.1]: https://github.com/jahidulpabelislam/orm/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/jahidulpabelislam/orm/compare/v1.0.2...v1.1.0
[1.0.2]: https://github.com/jahidulpabelislam/orm/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/jahidulpabelislam/orm/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/jahidulpabelislam/orm/releases/tag/v1.0.0
