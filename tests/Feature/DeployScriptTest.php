<?php

namespace Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class DeployScriptTest extends TestCase
{
    public function test_first_deploy_generates_credentials_and_builds_before_starting_services(): void
    {
        $this->withDeploymentDirectory(function (string $directory, string $shell): void {
            $process = $this->deploy($directory, $shell, ['--url', 'http://demo.example.test:8000']);

            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            $environment = file_get_contents($directory.'/.env.docker');
            $this->assertStringContainsString('APP_KEY=base64:', $environment);
            $this->assertStringContainsString('APP_URL=http://demo.example.test:8000', $environment);
            $this->assertStringContainsString('DOCKER_HTTP_BIND=0.0.0.0', $environment);
            $this->assertStringContainsString('DEMO_ALLOW_REMOTE=true', $environment);
            $this->assertStringNotContainsString('DEMO_PASSWORD=demo12345', $environment);
            preg_match('/^DB_PASSWORD=(.+)$/m', $environment, $databasePassword);
            preg_match('/^MYSQL_ROOT_PASSWORD=(.+)$/m', $environment, $rootPassword);
            $this->assertNotEmpty($databasePassword[1]);
            $this->assertNotSame($databasePassword[1], $rootPassword[1]);
            $calls = file_get_contents($directory.'/docker.calls');
            $this->assertMatchesRegularExpression('/build app web.*stop web worker scheduler app.*up -d --wait.*mysql redis.*up -d --no-build.*--wait.*app web worker scheduler/s', $calls);
            $this->assertStringContainsString('honey:reconcile-finance', $calls);
            $this->assertStringContainsString('http://127.0.0.1/up', $calls);
            preg_match('/^DEMO_PASSWORD=(.+)$/m', $environment, $password);
            $this->assertStringNotContainsString($password[1], $process->getOutput());
        });
    }

    public function test_redeploy_preserves_environment_and_does_not_reset_data(): void
    {
        $this->withDeploymentDirectory(function (string $directory, string $shell): void {
            $this->assertSame(0, $this->deploy($directory, $shell)->getExitCode());
            $originalEnvironment = file_get_contents($directory.'/.env.docker');

            $process = $this->deploy($directory, $shell);

            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            $this->assertSame($originalEnvironment, file_get_contents($directory.'/.env.docker'));
            $calls = file_get_contents($directory.'/docker.calls');
            $this->assertStringNotContainsString('down', $calls);
            $this->assertStringNotContainsString('migrate:fresh', $calls);
        });
    }

    public function test_production_environment_is_rejected_without_modifying_it(): void
    {
        $this->withDeploymentDirectory(function (string $directory, string $shell): void {
            $environment = "APP_ENV=production\nAPP_KEY=existing-secret\n";
            file_put_contents($directory.'/.env.docker', $environment);

            $process = $this->deploy($directory, $shell);

            $this->assertSame(1, $process->getExitCode(), $process->getErrorOutput());
            $this->assertStringContainsString('khusus demo', $process->getErrorOutput());
            $this->assertSame($environment, file_get_contents($directory.'/.env.docker'));
            $this->assertStringNotContainsString('build', file_get_contents($directory.'/docker.calls'));
        });
    }

    public function test_build_failure_keeps_running_application_untouched(): void
    {
        $this->withDeploymentDirectory(function (string $directory, string $shell): void {
            $process = $this->deploy($directory, $shell, environment: ['DEPLOY_TEST_FAIL_BUILD' => 'true']);

            $this->assertSame(1, $process->getExitCode(), $process->getErrorOutput());
            $calls = file_get_contents($directory.'/docker.calls');
            $this->assertStringContainsString('build app web', $calls);
            $this->assertStringNotContainsString('stop ', $calls);
            $this->assertStringNotContainsString(' up ', $calls);
        });
    }

    public function test_startup_failure_reports_logs_and_does_not_claim_success(): void
    {
        $this->withDeploymentDirectory(function (string $directory, string $shell): void {
            $process = $this->deploy($directory, $shell, environment: ['DEPLOY_TEST_FAIL_START' => 'true']);

            $this->assertSame(1, $process->getExitCode(), $process->getErrorOutput());
            $this->assertStringContainsString('logs --tail=100', file_get_contents($directory.'/docker.calls'));
            $this->assertStringNotContainsString('Deploy demo selesai', $process->getOutput());
        });
    }

    public function test_database_startup_failure_reports_logs_without_starting_migrations(): void
    {
        $this->withDeploymentDirectory(function (string $directory, string $shell): void {
            $process = $this->deploy($directory, $shell, environment: ['DEPLOY_TEST_FAIL_DATABASE' => 'true']);

            $this->assertSame(1, $process->getExitCode(), $process->getErrorOutput());
            $calls = file_get_contents($directory.'/docker.calls');
            $this->assertStringContainsString('logs --tail=100 mysql redis', $calls);
            $this->assertStringNotContainsString('up -d --no-build', $calls);
            $this->assertStringContainsString('log-bin-trust-function-creators=1', $process->getErrorOutput());
            $this->assertStringNotContainsString('Deploy demo selesai', $process->getOutput());
        });
    }

    public function test_update_pulls_only_fast_forward_changes_before_building(): void
    {
        $this->withDeploymentDirectory(function (string $directory, string $shell): void {
            $process = $this->deploy($directory, $shell, ['--update']);

            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            $this->assertStringContainsString('pull --ff-only', file_get_contents($directory.'/git.calls'));
            $this->assertStringContainsString('build app web', file_get_contents($directory.'/docker.calls'));
        });
    }

    /**
     * @param  list<string>  $arguments
     * @param  array<string, string>  $environment
     */
    private function deploy(string $directory, string $shell, array $arguments = [], array $environment = []): Process
    {
        $process = new Process([$shell, '-c', 'PATH="$PWD/bin:$PATH"; export PATH; exec sh deploy.sh "$@"', 'deploy-test', ...$arguments], $directory, $environment);
        $process->setTimeout(30);
        $process->run();

        return $process;
    }

    /** @param callable(string, string): void $test */
    private function withDeploymentDirectory(callable $test): void
    {
        $shell = PHP_OS_FAMILY === 'Windows' ? 'C:/Program Files/Git/bin/bash.exe' : (new ExecutableFinder)->find('sh');
        if ($shell === null || ! is_file($shell)) {
            $this->markTestSkipped('A POSIX shell is required to exercise deploy.sh.');
        }

        $directory = sys_get_temp_dir().'/honey-deploy-test-'.bin2hex(random_bytes(8));
        $files = new Filesystem;
        $files->makeDirectory($directory.'/bin', 0700, true);
        $files->copy(base_path('deploy.sh'), $directory.'/deploy.sh');
        $files->copy(base_path('.env.docker.example'), $directory.'/.env.docker.example');
        $files->put($directory.'/bin/docker', <<<'SH'
#!/bin/sh
printf '%s\n' "$*" >> docker.calls
case "$*" in
    *' build app web') [ "${DEPLOY_TEST_FAIL_BUILD:-false}" != true ] || exit 1 ;;
    *' up -d --wait '*) [ "${DEPLOY_TEST_FAIL_DATABASE:-false}" != true ] || exit 1 ;;
    *' up -d --no-build '*) [ "${DEPLOY_TEST_FAIL_START:-false}" != true ] || exit 1 ;;
esac
exit 0
SH);
        $files->put($directory.'/bin/openssl', <<<'SH'
#!/bin/sh
counter=0
[ ! -f openssl.counter ] || counter=$(cat openssl.counter)
counter=$((counter + 1))
printf '%s\n' "$counter" > openssl.counter
if [ "$2" = -base64 ]; then
    printf 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=\n'
else
    printf '%048d\n' "$counter"
fi
SH);
        $files->put($directory.'/bin/git', <<<'SH'
#!/bin/sh
printf '%s\n' "$*" >> git.calls
exit 0
SH);
        foreach (['docker', 'openssl', 'git'] as $executable) {
            chmod($directory.'/bin/'.$executable, 0700);
        }

        try {
            $test($directory, $shell);
        } finally {
            $files->deleteDirectory($directory);
        }
    }
}
