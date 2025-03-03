<?php

declare(strict_types=1);

namespace DaggerModule;

use Dagger\Attribute\DaggerFunction;
use Dagger\Attribute\DaggerObject;
use Dagger\Attribute\DefaultPath;
use Dagger\Attribute\Doc;
use Dagger\Container;
use Dagger\Directory;

use function Dagger\dag;

#[DaggerObject]
#[Doc('A generated module for SymfonySampleApp functions')]
class SymfonySampleApp
{

  #[DaggerFunction]
  public function __construct(
      #[DefaultPath(".")]
      public Directory $source,
      public string $version = '8.3'
  ) {
  }

    #[DaggerFunction]
    #[Doc('Returns a PHP env with dependencies and source code')]
    public function env(): Container
    {
        return dag()
            ->container()
            // use php base image
            ->from('php:' . $this->version . '-cli')
            // mount caches
            ->withMountedCache("/root/.composer", dag()->cacheVolume("composer-php83"))
            ->withMountedCache("/var/cache/apt", dag()->cacheVolume("apt"))
            ->withExec(["apt-get", "update"])
            // install system deps
            ->withExec(["apt-get", "install", "--yes", "git-core", "zip", "curl"])
            // install php deps
            ->withExec(["docker-php-ext-install", "pdo_mysql"])
            // install composer
            ->withExec(["sh", "-c", "curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer"])
            // mount source code and set workdir
            ->withDirectory("/app", $this->source->withoutDirectory(".dagger"))
            ->withWorkdir("/app")
            // install app deps
            ->withExec(["composer", "install"])
            // install symfony cli
            ->withExec(['sh', '-c', 'curl -sS https://get.symfony.com/cli/installer | bash']);
    }

    #[DaggerFunction]
    #[Doc('Returns the result of unit tests and static analysis')]
    public function test(): string {

        $mariadb = dag()
            ->container()
            // use mariadb image
            ->from("mariadb:11.6")
            // create default db for tests
            // as per symfony conventions, db name is with _test suffix)
            ->withEnvVariable("MARIADB_DATABASE", "app_test")
            ->withEnvVariable("MARIADB_ROOT_PASSWORD", "guessme")
            ->withExposedPort(3306)
            // start service
            ->asService([], true);

        return $this
            ->env()
            // bind to mariadb service
            ->withServiceBinding('db', $mariadb)
            // set env=test
            ->withEnvVariable('APP_ENV', 'test')
            // populate db with test data
            ->withExec(['./bin/console', 'doctrine:schema:drop', '--force'])
            ->withExec(['./bin/console', 'doctrine:schema:create'])
            ->withExec(['./bin/console', '-n', 'doctrine:fixtures:load'])
            // run unit tests
            ->withExec(['./bin/phpunit'])
            // install static analyzer
            ->withExec(['composer', 'require', '--dev', 'phpstan/phpstan'])
            // run static analysis
            ->withExec(['./vendor/bin/phpstan', '-v', '--memory-limit=2G'])
            ->stdout();
    }

    #[DaggerFunction]
    #[Doc('Returns the result of linting')]
    public function lint(): string {
        return $this
            ->env()
            // install linter
            ->withExec(['composer', 'require', '--dev', 'friendsofphp/php-cs-fixer'])
            // run linter
            ->withExec(['./vendor/bin/php-cs-fixer', 'check', 'src'])
            ->stdout();
    }

    #[DaggerFunction]
    #[Doc('Publishes the application')]
    public function publish(): string {
        // lint
        $this->lint();
        // run unit tests and static analysis
        $this->test();
        // set entrypoint and publish
        return $this
            ->env()
            ->withEntrypoint(['/root/.symfony5/bin/symfony', 'server:start', '--port=8000', '--listen-ip=0.0.0.0'])
            ->publish('ttl.sh/symfony-sample-app-'  . rand(0, 100000));
    }
}
