# Dagger

Thie Dagger module is implemented with the Dagger PHP SDK. It includes functions to lint, test, statically analyze, and containerize and publish the Symfony application.

Usage
-----

- [Install the Dagger CLI](https://docs.dagger.io/install)
- Lint: `dagger call [--version=x.y.z] lint`
- Run unit tests and static analysis: `dagger call [--version=x.y.z] test`
- Publish to transient registry ttl.sh: `dagger call [--version=x.y.z] publish`

By default, all Dagger Functions use a PHP 8.3 environment. To change this, pass a `--version` constructor argument. Versions < PHP 8.2.0 are not supported.
