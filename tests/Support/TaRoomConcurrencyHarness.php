<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class TaRoomConcurrencyHarness
{
    public static function race(array $jobs): array
    {
        if (DB::getDriverName() !== 'mysql' || DB::connection()->transactionLevel() !== 0 || ! str_ends_with(DB::connection()->getDatabaseName(), '_testing')) {
            throw new \LogicException('Race requires committed fixtures on dedicated MySQL testing DB.');
        }
        if (! function_exists('pcntl_fork')) {
            throw new \LogicException('pcntl unavailable.');
        }
        $dir = sys_get_temp_dir().'/ta111-race-'.bin2hex(random_bytes(8));
        mkdir($dir, 0700);
        DB::disconnect();
        $pids = [];
        foreach ($jobs as $i => $job) {
            $pid = pcntl_fork();
            if ($pid < 0) {
                throw new \RuntimeException('fork failed');
            }if ($pid === 0) {
                DB::purge();
                file_put_contents($dir.'/ready-'.$i, '1');
                $deadline = microtime(true) + 10;
                while (! is_file($dir.'/go')) {
                    if (microtime(true) > $deadline) {
                        exit(3);
                    }usleep(1000);
                }
                try {
                    $value = $job();
                    $result = ['ok' => true, 'value' => $value];
                } catch (ValidationException $e) {
                    $result = ['ok' => false, 'kind' => 'validation'];
                } catch (HttpExceptionInterface $e) {
                    $result = ['ok' => false, 'kind' => 'http', 'code' => $e->getStatusCode()];
                } catch (\Throwable $e) {
                    $result = ['ok' => false, 'kind' => 'internal', 'error' => get_class($e), 'message' => $e->getMessage()];
                }
                file_put_contents($dir.'/result-'.$i, json_encode($result));
                DB::disconnect();
                exit(0);
            }$pids[$i] = $pid;
        }
        $deadline = microtime(true) + 10;
        while (count(glob($dir.'/ready-*')) !== count($jobs)) {
            if (microtime(true) > $deadline) {
                throw new \RuntimeException('race barrier timeout');
            }usleep(1000);
        }file_put_contents($dir.'/go', '1');
        $results = [];
        foreach ($pids as $i => $pid) {
            pcntl_waitpid($pid, $status);
            $results[$i] = is_file($dir.'/result-'.$i) ? json_decode(file_get_contents($dir.'/result-'.$i), true) : ['ok' => false, 'kind' => 'internal', 'error' => 'child exited '.$status];
        }
        DB::purge();
        foreach (glob($dir.'/*') as $f) {
            unlink($f);
        }rmdir($dir);

        return $results;
    }
}
